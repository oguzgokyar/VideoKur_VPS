"""
YouTube Shorts Otomasyon — Ana Pipeline
Tüm modülleri sırayla çalıştırarak haber URL'sinden video üretir.
SEQUENTIAL PROCESSING: Uses global production lock to prevent parallel video production
"""
import sys
import os
import json
import subprocess

# Windows'ta UTF-8 çıktı için
if sys.platform == 'win32':
    sys.stdout.reconfigure(encoding='utf-8', errors='replace')
    sys.stderr.reconfigure(encoding='utf-8', errors='replace')

# Modül yolunu ayarla
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from scraper import scrape_news
from script_gen import generate_script
from tts_engine import generate_tts
from image_gen import generate_image, generate_image_fal, generate_image_pollinations, generate_image_huggingface, generate_image_pexels
from subtitle_gen import generate_srt
from video_composer import compose_video, SUBTITLE_PRESETS
from utils.video_lock import VideoCompositorLock, setup_job_temp_dir, cleanup_job_temp_dir
from utils.production_lock import GlobalProductionLock


def setup_job_logging(job_id: str, base_dir: str) -> str:
    """
    Setup job-specific log file.
    
    Args:
        job_id: Job ID
        base_dir: Base directory
    
    Returns:
        Path to log file
    """
    log_dir = os.path.join(base_dir, 'output', job_id)
    os.makedirs(log_dir, exist_ok=True)
    log_file = os.path.join(log_dir, 'job.log')
    
    # Initialize log file with header
    import time
    try:
        with open(log_file, 'w', encoding='utf-8') as f:
            f.write(f"{'='*80}\n")
            f.write(f"JOB LOG: {job_id}\n")
            f.write(f"Started: {time.strftime('%Y-%m-%d %H:%M:%S')}\n")
            f.write(f"{'='*80}\n\n")
    except Exception as e:
        print(f"⚠️ Could not create log file: {e}")
    
    return log_file


def log_to_job(log_file: str, message: str, level: str = 'INFO'):
    """
    Write to job log with timestamp.
    
    Args:
        log_file: Path to log file
        message: Log message
        level: Log level (INFO, WARNING, ERROR, SUCCESS)
    """
    import time
    try:
        timestamp = time.strftime('%Y-%m-%d %H:%M:%S')
        emoji = {
            'INFO': 'ℹ️',
            'WARNING': '⚠️',
            'ERROR': '❌',
            'SUCCESS': '✅',
            'DEBUG': '🔍'
        }.get(level, 'ℹ️')
        
        with open(log_file, 'a', encoding='utf-8') as f:
            f.write(f"[{timestamp}] [{level}] {emoji} {message}\n")
    except Exception as e:
        print(f"⚠️ Could not write to log: {e}")


def _get_video_type(width: int, height: int) -> str:
    if width == height:
        return 'square'
    if width > height:
        return 'wide'
    return 'short'


VISUAL_THEME_PROMPTS = {
    'default': '',
    'realistic': 'ultra realistic photo style, natural lighting, real-world textures',
    'cinematic': 'cinematic film still, dramatic lighting, high contrast, anamorphic composition',
    'photographic': 'professional photography style, crisp details, editorial quality',
    '3d-model': 'high quality 3D render, stylized 3D model look, clean geometry',
    'anime': 'anime style illustration, vibrant cel shading, Japanese animation aesthetic',
    'digital-art': 'digital painting style, concept art quality, rich color grading',
    'dark': 'dark moody atmosphere, low-key lighting, dramatic shadows',
    'reportage-sketch': 'reportage sketch style, monochrome ink and charcoal texture',
    'infinity': 'epic fantasy concept art, monumental scale, surreal atmosphere'
}


def resolve_visual_theme(job_data: dict) -> tuple[str, str | None]:
    theme_id = (job_data.get('visual_theme_id') or 'default').strip().lower()
    custom_prompt = (job_data.get('visual_theme_prompt') or '').strip() or None
    return theme_id, custom_prompt


def apply_visual_theme(base_prompt: str, theme_id: str, custom_prompt: str | None) -> str:
    parts = [base_prompt.strip()]
    if custom_prompt:
        parts.append(custom_prompt.strip())
    else:
        theme_prompt = VISUAL_THEME_PROMPTS.get(theme_id, '')
        if theme_prompt:
            parts.append(theme_prompt)
    return ', '.join([p for p in parts if p])


def build_prompt_news(prompt_text: str) -> dict:
    text = (prompt_text or '').strip()
    if not text:
        return {'title': 'Prompt Video', 'text': 'Prompt içeriği girilmedi'}
    first_line = text.splitlines()[0].strip()
    title = first_line[:120] if first_line else text[:120]
    return {'title': title or 'Prompt Video', 'text': text}


def resolve_bgm_for_job(base_dir: str, job_data: dict) -> tuple[str | None, float]:
    bgm_file = (job_data.get('bgm_file') or '').strip()
    bgm_volume_db = float(job_data.get('bgm_volume_db', -22.0))
    if not bgm_file:
        return None, bgm_volume_db
    safe_rel = bgm_file.replace('\\', '/').lstrip('/')
    if '..' in safe_rel:
        return None, bgm_volume_db
    full_path = os.path.join(base_dir, safe_rel.replace('/', os.sep))
    if not os.path.exists(full_path):
        return None, bgm_volume_db
    return full_path, bgm_volume_db


def _load_custom_script(base_dir: str, job_data: dict, video_type: str):
    scripts_file = os.path.join(base_dir, 'data', 'scripts.json')
    if not os.path.exists(scripts_file):
        return None
    try:
        with open(scripts_file, 'r', encoding='utf-8') as f:
            scripts = (json.load(f) or {}).get('scripts', [])
    except Exception:
        return None

    script_id = (job_data.get('scriptId') or '').strip()
    if script_id:
        for s in scripts:
            if s.get('id') == script_id:
                return s
    return None


def _ensure_outro_cta(script: dict) -> dict:
    outro = (script.get('outro') or '').strip()
    if not outro:
        outro = "Abone ol, Beğen ve Yorum yap! Daha fazlası için bizi takip et."
    required_terms = ['Abone ol', 'Beğen', 'Yorum yap']
    missing = [t for t in required_terms if t not in outro]
    if missing:
        addon = "Abone ol, Beğen ve Yorum yap!"
        outro = f"{outro} {addon}".strip()
    script['outro'] = outro
    return script


def update_job(jobs_dir: str, job_id: str, updates: dict, max_retries: int = 3, log_file: str = None):
    """
    İş dosyasını günceller - guaranteed update with retry.
    
    Args:
        jobs_dir: Jobs dizini
        job_id: Job ID
        updates: Güncellenecek alanlar
        max_retries: Maximum retry sayısı (varsayılan 3)
        log_file: Optional job-specific log file
    
    Returns:
        True if successful, False if all retries failed
    """
    import time
    
    job_file = os.path.join(jobs_dir, f"{job_id}.json")
    temp_file = os.path.join(jobs_dir, f"{job_id}.json.tmp")
    
    for attempt in range(max_retries):
        try:
            # Read existing job
            if os.path.exists(job_file):
                with open(job_file, 'r', encoding='utf-8') as f:
                    job = json.load(f)
            else:
                job = {}
            
            # Update fields
            job.update(updates)
            
            # Add last update timestamp for stuck detection
            job['last_update_time'] = time.time()
            job['updated_at'] = time.strftime('%Y-%m-%d %H:%M:%S')
            
            # Write to temp file first (atomic write)
            with open(temp_file, 'w', encoding='utf-8') as f:
                json.dump(job, f, ensure_ascii=False, indent=2)
            
            # Atomic rename
            os.replace(temp_file, job_file)
            
            # Log to job file if provided
            if log_file:
                try:
                    log_to_job(log_file, f"Job updated: {', '.join(updates.keys())}", "INFO")
                except:
                    pass
            
            return True
            
        except Exception as e:
            if attempt == max_retries - 1:
                # Last attempt failed - CRITICAL ERROR
                print(f"\n{'='*70}")
                print(f"❌ CRITICAL: Job update failed after {max_retries} attempts")
                print(f"   Job ID: {job_id}")
                print(f"   Updates: {updates}")
                print(f"   Error: {e}")
                print(f"{'='*70}\n")
                
                # Try to log to file
                try:
                    log_file_path = os.path.join(jobs_dir, 'update_failures.log')
                    with open(log_file_path, 'a', encoding='utf-8') as f:
                        f.write(f"{time.strftime('%Y-%m-%d %H:%M:%S')} - Job {job_id} - {e}\n")
                except:
                    pass
                
                # Log to job file if provided
                if log_file:
                    try:
                        log_to_job(log_file, f"CRITICAL: Job update failed after {max_retries} attempts: {e}", "ERROR")
                    except:
                        pass
                
                return False
            
            # Wait before retry
            time.sleep(0.5 * (attempt + 1))  # 0.5s, 1s, 1.5s
    
    return False


def check_pause(jobs_dir: str, job_id: str, timeout: int = 3600):
    """Eğer iş 'paused' durumundaysa resume edilene kadar bekler."""
    import time
    job_file = os.path.join(jobs_dir, f"{job_id}.json")
    waited = 0
    while waited < timeout:
        if os.path.exists(job_file):
            with open(job_file, 'r', encoding='utf-8') as f:
                job = json.load(f)
            if job.get('status') != 'paused':
                return True
        time.sleep(2)
        waited += 2
    return False


def get_audio_duration(audio_path: str) -> float:
    """Ses dosyasının süresini saniye cinsinden döndürür."""
    try:
        from moviepy import AudioFileClip
        clip = AudioFileClip(audio_path)
        duration = clip.duration
        clip.close()
        return duration
    except Exception:
        return 5.0


def concat_audio_files(audio_files: list, output_path: str) -> bool:
    """ffmpeg ile ses dosyalarını birleştirir."""
    try:
        from moviepy.config import FFMPEG_BINARY
        concat_dir = os.path.dirname(output_path)
        list_file = os.path.join(concat_dir, '_concat_list.txt')
        with open(list_file, 'w', encoding='utf-8') as f:
            for af in audio_files:
                safe_path = af.replace('\\', '/')
                f.write(f"file '{safe_path}'\n")
        cmd = [FFMPEG_BINARY, '-y', '-f', 'concat', '-safe', '0',
               '-i', list_file, '-c', 'copy', output_path]
        result = subprocess.run(cmd, capture_output=True, text=True)
        os.remove(list_file)
        return result.returncode == 0
    except Exception as e:
        print(f"Ses birleştirme hatası: {e}")
        return False


def run_pipeline(job_id: str, url: str, template: str, config_file: str):
    """Ana pipeline: URL → Video."""
    base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    jobs_dir = os.path.join(base_dir, 'data', 'jobs')
    output_dir = os.path.join(base_dir, 'output', job_id)
    images_dir = os.path.join(output_dir, 'images')
    audio_dir = os.path.join(output_dir, 'audio_segments')

    os.makedirs(output_dir, exist_ok=True)
    os.makedirs(images_dir, exist_ok=True)
    os.makedirs(audio_dir, exist_ok=True)

    # Setup job-specific logging
    log_file = setup_job_logging(job_id, base_dir)
    log_to_job(log_file, f"Pipeline started for job {job_id}", "INFO")
    log_to_job(log_file, f"URL: {url}", "INFO")

    # Config yükle
    config = {}
    if os.path.exists(config_file):
        with open(config_file, 'r', encoding='utf-8') as f:
            config = json.load(f)

    # Job dosyasından video ebatlarını ve subtitle style'ı oku
    job_file = os.path.join(jobs_dir, f"{job_id}.json")
    job_data = {}
    if os.path.exists(job_file):
        with open(job_file, 'r', encoding='utf-8') as f:
            job_data = json.load(f)
    source_mode = (job_data.get('source_mode') or 'url').lower()
    prompt_text = (job_data.get('prompt_text') or '').strip()
    visual_theme_id, visual_theme_prompt = resolve_visual_theme(job_data)
    bgm_path, bgm_volume_db = resolve_bgm_for_job(base_dir, job_data)
    
    # Video ve görsel ebatları - job'dan al, yoksa varsayılan
    video_width = job_data.get('videoWidth', 1080)
    video_height = job_data.get('videoHeight', 1920)
    video_type = _get_video_type(video_width, video_height)
    
    # Subtitle style - Ayarlar > Altyazı (config) her zaman varsayılan kaynak
    subtitle_style = config.get('subtitleStyle', None)
    if job_data.get('subtitleStyle') and subtitle_style is not None:
        print("  [Altyazı] Job içi stil yok sayıldı, Ayarlar > Altyazı varsayılanı kullanılıyor")
    elif job_data.get('subtitleStyle') and subtitle_style is None:
        # Config eksikse eski job stilini son çare olarak kullan
        subtitle_style = job_data['subtitleStyle']
    
    # Handle string preset names (e.g., 'classic', 'bold_bottom')
    if isinstance(subtitle_style, str):
        subtitle_style = SUBTITLE_PRESETS.get(subtitle_style, SUBTITLE_PRESETS['classic'])
        print(f"  [Altyazı] Preset kullanılıyor: {subtitle_style}")
    
    # Handle dict with 'preset' field (remove it, it's just metadata)
    elif isinstance(subtitle_style, dict) and 'preset' in subtitle_style:
        preset_name = subtitle_style.pop('preset', None)
        # If subtitle_style is now empty or minimal (was only preset name), load full preset
        if not subtitle_style or len(subtitle_style) <= 1:
            subtitle_style = SUBTITLE_PRESETS.get(preset_name, SUBTITLE_PRESETS['classic'])
            print(f"  [Altyazı] Preset yüklendi: {preset_name}")
    
    # Fallback to classic if None
    if subtitle_style is None:
        print("  [Altyazı] Config ve job'da stil yok, 'classic' preset kullanılıyor")
        subtitle_style = SUBTITLE_PRESETS['classic']

    # Multi-key support - use geminiKeys array if available
    gemini_key = config.get('geminiKey', '')
    gemini_keys = config.get('geminiKeys', [])
    
    # If geminiKeys array exists and not empty, use it (multi-key support)
    # Otherwise fallback to single geminiKey
    if gemini_keys and len(gemini_keys) > 0:
        api_keys_to_use = gemini_keys
        print(f"  [Multi-key] {len(gemini_keys)} Gemini API key kullanılabilir")
    elif gemini_key:
        api_keys_to_use = [gemini_key]
        print(f"  [Single-key] 1 Gemini API key kullanılabilir")
    else:
        api_keys_to_use = []
        print(f"  [UYARI] Hiç Gemini API key yok!")
    
    eleven_key         = config.get('elevenKey', '')
    hf_key             = config.get('hfKey', '')
    pexels_key         = config.get('pexelsKey', '')
    fal_key            = config.get('falKey', '')  # Fal.ai API key
    pollinations_key   = config.get('pollinationsKey', '')  # Pollinations API key
    tts_provider       = config.get('ttsProvider', 'elevenlabs')
    gemini_model       = config.get('geminiModel', 'gemini-2.0-flash')
    image_service      = config.get('imageService', 'pollinations')   # pollinations | fal | huggingface | pexels | auto
    pollinations_model = config.get('pollinationsModel', 'flux')
    script_provider    = config.get('scriptProvider', 'gemini')
    poll_text_model    = config.get('pollinationsTextModel', 'openai-fast')
    
    # Fal.ai ayarları - video ebatlarını kullan
    fal_width          = video_width
    fal_height         = video_height
    fal_steps          = config.get('falSteps', 4)

    tools_enabled = config.get('toolsEnabled', {})
    script_enabled = tools_enabled.get('scriptGen', True)
    image_enabled  = tools_enabled.get('imageGen', True)
    tts_enabled    = tools_enabled.get('ttsGen', True)
    video_enabled  = tools_enabled.get('videoCompose', True)

    services_enabled = config.get('servicesEnabled', {})
    svc_fal_image          = services_enabled.get('fal_image', True)  # Fal.ai varsayılan aktif
    svc_pollinations_image = services_enabled.get('pollinations_image', True)
    svc_huggingface_image  = services_enabled.get('huggingface_image', True)
    svc_pexels_image       = services_enabled.get('pexels_image', True)
    svc_gemini_script      = services_enabled.get('gemini_script', True)
    svc_pollinations_text  = services_enabled.get('pollinations_text', True)
    svc_elevenlabs_tts     = services_enabled.get('elevenlabs_tts', True)
    svc_edge_tts           = services_enabled.get('edge_tts', True)
    
    # Fal.ai API key'i environment variable olarak ayarla
    if fal_key:
        import os as _os
        _os.environ['FAL_KEY'] = fal_key

    print(f"[1/6] Haber çekiliyor: {url}")
    update_job(jobs_dir, job_id, {'status': 'scraping'}, log_file=log_file)
    log_to_job(log_file, "Starting scraping phase", "INFO")

    if source_mode == 'prompt':
        news = build_prompt_news(prompt_text)
        log_to_job(log_file, "Prompt mode active: scraping skipped", "INFO")
    else:
        news = scrape_news(url)
        if not news.get('text'):
            error_msg = 'Haber metni çekilemedi'
            update_job(jobs_dir, job_id, {'status': 'failed', 'error': error_msg}, log_file=log_file)
            log_to_job(log_file, f"Scraping failed: {error_msg}", "ERROR")
            return

    with open(os.path.join(output_dir, 'news.json'), 'w', encoding='utf-8') as f:
        json.dump(news, f, ensure_ascii=False, indent=2)
    
    log_to_job(log_file, f"News scraped successfully: {len(news.get('text', ''))} characters", "SUCCESS")

    # Pause check
    check_pause(jobs_dir, job_id)

    print(f"[2/6] Script üretiliyor...")
    update_job(jobs_dir, job_id, {'status': 'scripting'}, log_file=log_file)
    log_to_job(log_file, "Starting script generation phase", "INFO")
    
    selected_script = _load_custom_script(base_dir, job_data, video_type)
    if not selected_script:
        error_msg = 'Script seçimi zorunlu: geçerli bir scriptId bulunamadı'
        update_job(jobs_dir, job_id, {'status': 'failed', 'error': error_msg}, log_file=log_file)
        log_to_job(log_file, error_msg, "ERROR")
        return

    selected_prompt = selected_script.get('prompt') if selected_script else None
    selected_max_duration = int(selected_script.get('maxDuration', 55)) if selected_script else 55

    if not script_enabled:
        print("  Script üretimi devre dışı, atlanıyor.")
        log_to_job(log_file, "Script generation disabled, using fallback", "WARNING")
        script = {'hook': news.get('title', ''), 'scenes': [{'scene': 1, 'text': news['text'][:200], 'image_prompt': 'news background', 'duration': 10}], 'outro': ''}
    elif script_provider == 'pollinations':
        if not svc_pollinations_text:
            error_msg = 'Pollinations Text servisi devre dışı'
            update_job(jobs_dir, job_id, {'status': 'failed', 'error': error_msg}, log_file=log_file)
            log_to_job(log_file, error_msg, "ERROR")
            return
        from script_gen import generate_script_pollinations
        log_to_job(log_file, f"Using Pollinations for script generation (model: {poll_text_model})", "INFO")
        result = generate_script_pollinations(news['title'], news['text'], poll_text_model, selected_max_duration, selected_prompt)
        if not result.get('success'):
            error_msg = f"Script hatası: {result.get('error', '')}"
            update_job(jobs_dir, job_id, {'status': 'failed', 'error': error_msg}, log_file=log_file)
            log_to_job(log_file, f"Pollinations script failed: {result.get('error')}", "ERROR")
            return
        script = result['script']
        log_to_job(log_file, "Pollinations script generated successfully", "SUCCESS")
    else:
        if not svc_gemini_script:
            error_msg = 'Gemini Script servisi devre dışı'
            update_job(jobs_dir, job_id, {'status': 'failed', 'error': error_msg}, log_file=log_file)
            log_to_job(log_file, error_msg, "ERROR")
            return
        
        # Multi-key desteği: geminiKeys array'i varsa onu kullan, yoksa geminiKey'i array'e çevir
        gemini_keys = config.get('geminiKeys', [])
        if not gemini_keys and gemini_key:
            gemini_keys = [gemini_key]
        
        if not gemini_keys:
            error_msg = 'Gemini API key eksik'
            update_job(jobs_dir, job_id, {'status': 'failed', 'error': error_msg}, log_file=log_file)
            log_to_job(log_file, error_msg, "ERROR")
            return
        
        log_to_job(log_file, f"Using Gemini for script generation with {len(gemini_keys)} API key(s)", "INFO")
        log_to_job(log_file, f"Model: {gemini_model}, Max duration: {selected_max_duration}s", "INFO")
        
        from script_gen import generate_script_with_fallback
        result = generate_script_with_fallback(
            news['title'],
            news['text'],
            gemini_keys,
            max_duration=selected_max_duration,
            model_name=gemini_model,
            prompt_template=selected_prompt
        )
        if not result.get('success'):
            error_msg = f"Script hatası: {result.get('error', '')}"
            update_job(jobs_dir, job_id, {'status': 'failed', 'error': error_msg}, log_file=log_file)
            log_to_job(log_file, f"Gemini script generation failed: {result.get('error')}", "ERROR")
            return
        script = result['script']
        log_to_job(log_file, "Gemini script generated successfully", "SUCCESS")

    script = _ensure_outro_cta(script)

    scenes = script.get('scenes', [])

    with open(os.path.join(output_dir, 'script.json'), 'w', encoding='utf-8') as f:
        json.dump(script, f, ensure_ascii=False, indent=2)

    # Görsel üretimi için yardımcı fonksiyonlar
    def _try_fal(prompt, img_path):
        if not svc_fal_image:
            return False
        if not fal_key and not os.environ.get('FAL_KEY'):
            print(f"  [Fal.ai] API key yok, atlanıyor...")
            return False
        return generate_image_fal(prompt, img_path, 
                                  width=fal_width, height=fal_height, 
                                  steps=fal_steps, optimize_cost=True)

    def _try_pollinations(prompt, img_path):
        if not svc_pollinations_image:
            return False
        return generate_image_pollinations(prompt, img_path, pollinations_model, 
                                           width=video_width, height=video_height, 
                                           api_key=pollinations_key)

    def _try_huggingface(prompt, img_path):
        if not svc_huggingface_image:
            return False
        return generate_image_huggingface(prompt, img_path, hf_key) if hf_key else False

    def _try_pexels(prompt, img_path):
        if not svc_pexels_image:
            return False
        query = ' '.join(prompt.split()[:4])
        return generate_image_pexels(query, img_path, pexels_key) if pexels_key else False

    def _generate_image(prompt, img_path):
        """Belirtilen servisle görsel üretir, kullanılan servis adını döndürür."""
        used_service = 'failed'
        if image_service == 'fal':
            if _try_fal(prompt, img_path):
                used_service = 'fal'
        elif image_service == 'pollinations':
            if _try_pollinations(prompt, img_path):
                used_service = 'pollinations'
        elif image_service == 'huggingface':
            if _try_huggingface(prompt, img_path):
                used_service = 'huggingface'
        elif image_service == 'pexels':
            if _try_pexels(prompt, img_path):
                used_service = 'pexels'
        else:
            # auto: fal → pollinations → huggingface → pexels
            if _try_fal(prompt, img_path):
                used_service = 'fal'
            elif _try_pollinations(prompt, img_path):
                used_service = 'pollinations'
            elif _try_huggingface(prompt, img_path):
                used_service = 'huggingface'
            elif _try_pexels(prompt, img_path):
                used_service = 'pexels'
        return used_service

    total_images = len(scenes) + (1 if script.get('hook') else 0) + (1 if script.get('outro') else 0)
    print(f"[3/6] Görseller üretiliyor ({total_images} görsel: sahneler + hook/outro)...")
    update_job(jobs_dir, job_id, {'status': 'imaging'})

    # Pause check
    check_pause(jobs_dir, job_id)

    if not image_enabled:
        print("  Görsel üretimi devre dışı, atlanıyor.")
    else:
        # Hook görseli üret (AI tarafından üretilen prompt'u kullan)
        if script.get('hook'):
            hook_prompt = script.get('hook_image_prompt', f"Eye-catching video thumbnail, attention grabbing intro visual, viral content style, breaking news cover, dramatic lighting, {news.get('title', 'news')[:50]}")
            hook_prompt = apply_visual_theme(hook_prompt, visual_theme_id, visual_theme_prompt)
            hook_img_path = os.path.join(images_dir, 'hook.png')
            hook_service = _generate_image(hook_prompt, hook_img_path)
            script['hook_used_service'] = hook_service
            print(f"  Hook görseli: {'OK' if os.path.exists(hook_img_path) else 'FAIL'} ({hook_service})")
            if hook_service == 'pollinations':
                import time
                print(f"  [Pollinations cooldown: 15 saniye bekleniyor...]")
                time.sleep(15)

        # Sahne görselleri üret
        for i, scene in enumerate(scenes):
            prompt = scene.get('image_prompt', 'news background')
            prompt = apply_visual_theme(prompt, visual_theme_id, visual_theme_prompt)
            img_path = os.path.join(images_dir, f"scene_{i+1}.png")
            used_service = _generate_image(prompt, img_path)

            scenes[i]['used_service'] = used_service
            print(f"  Sahne {i+1} görseli: {'OK' if os.path.exists(img_path) else 'FAIL'} ({used_service})")
            
            # Pollinations API için görseller arası 15 saniye bekleme (rate limit)
            if used_service == 'pollinations' and (i < len(scenes) - 1 or script.get('outro')):
                import time
                print(f"  [Pollinations cooldown: 15 saniye bekleniyor...]")
                time.sleep(15)

        # Outro görseli üret (AI tarafından üretilen prompt'u kullan)
        if script.get('outro'):
            outro_prompt = script.get('outro_image_prompt', "Video outro closing scene, call to action visual, subscribe and comment icons, social media engagement, like and follow buttons, channel subscribe reminder, clean modern design")
            outro_prompt = apply_visual_theme(outro_prompt, visual_theme_id, visual_theme_prompt)
            outro_img_path = os.path.join(images_dir, 'outro.png')
            outro_service = _generate_image(outro_prompt, outro_img_path)
            script['outro_used_service'] = outro_service
            print(f"  Outro görseli: {'OK' if os.path.exists(outro_img_path) else 'FAIL'} ({outro_service})")
            
            # Pollinations cooldown before thumbnail
            if outro_service == 'pollinations':
                import time
                print(f"  [Pollinations cooldown: 15 saniye bekleniyor...]")
                time.sleep(15)
        
        # Thumbnail görseli üret (YouTube kapak için)
        thumbnail_prompt = script.get('thumbnail_image_prompt', f"Professional YouTube thumbnail, dramatic lighting, bold colors, eye-catching, news media style, {news.get('title', 'breaking news')[:50]}, space for text overlay")
        thumbnail_prompt = apply_visual_theme(thumbnail_prompt, visual_theme_id, visual_theme_prompt)
        thumbnail_path = os.path.join(output_dir, 'thumbnail.jpg')
        thumbnail_service = _generate_image(thumbnail_prompt, thumbnail_path)
        script['thumbnail_used_service'] = thumbnail_service
        print(f"  Thumbnail görseli: {'OK' if os.path.exists(thumbnail_path) else 'FAIL'} ({thumbnail_service})")

    # Save used_service info back to script.json
    script['scenes'] = scenes
    with open(os.path.join(output_dir, 'script.json'), 'w', encoding='utf-8') as f:
        json.dump(script, f, ensure_ascii=False, indent=2)

    # --- Sahne bazlı TTS ---
    print(f"[4/6] Seslendirme üretiliyor ({tts_provider}) — sahne bazlı...")
    update_job(jobs_dir, job_id, {'status': 'tts'})

    # Pause check
    check_pause(jobs_dir, job_id)

    if not tts_enabled:
        print("  TTS devre dışı, varsayılan süreler kullanılıyor.")
        segments = []
        if script.get('hook'):
            segments.append({
                'text': script['hook'], 
                'type': 'hook',
                'effect': script.get('hook_effect', 'zoom_in_fast'),
                'voice_profile': script.get('hook_voice_profile', 'urgent')
            })
        for i, scene in enumerate(scenes):
            segments.append({
                'text': scene.get('text', ''), 
                'type': 'scene', 
                'index': i,
                'effect': scene.get('effect', 'ken_burns_zoom_in'),
                'voice_profile': scene.get('voice_profile', 'neutral')
            })
        if script.get('outro'):
            segments.append({
                'text': script['outro'], 
                'type': 'outro',
                'effect': script.get('outro_effect', 'pulse_strong'),
                'voice_profile': script.get('outro_voice_profile', 'cheerful')
            })
        audio_files = []
        actual_durations = [scene.get('duration', 6) for scene in segments]
        audio_path = None
    else:
        # Tüm segmentleri oluştur: hook + scenes + outro
        segments = []
        if script.get('hook'):
            segments.append({
                'text': script['hook'], 
                'type': 'hook',
                'effect': script.get('hook_effect', 'zoom_in_fast'),
                'voice_profile': script.get('hook_voice_profile', 'urgent')
            })
        for i, scene in enumerate(scenes):
            segments.append({
                'text': scene.get('text', ''), 
                'type': 'scene', 
                'index': i,
                'effect': scene.get('effect', 'ken_burns_zoom_in'),
                'voice_profile': scene.get('voice_profile', 'neutral')
            })
        if script.get('outro'):
            segments.append({
                'text': script['outro'], 
                'type': 'outro',
                'effect': script.get('outro_effect', 'pulse_strong'),
                'voice_profile': script.get('outro_voice_profile', 'cheerful')
            })

        audio_files = []
        actual_durations = []

        for idx, seg in enumerate(segments):
            seg_audio = os.path.join(audio_dir, f"seg_{idx:02d}.mp3")
            voice_prof = seg.get('voice_profile', 'neutral')
            print(f"  TTS segment {idx+1}/{len(segments)}: {seg['type']} (profil: {voice_prof})...")
            tts_ok = generate_tts(seg['text'], seg_audio, tts_provider, eleven_key,
                                  voice_profile=voice_prof,
                                  svc_elevenlabs=svc_elevenlabs_tts, svc_edge_tts=svc_edge_tts)
            if not tts_ok:
                print(f"  [WARN] Segment {idx+1} TTS basarisiz, atlaniyor")
                actual_durations.append(3.0)
                continue
            dur = get_audio_duration(seg_audio)
            actual_durations.append(dur)
            audio_files.append(seg_audio)
            print(f"    → {dur:.1f}s")

        if not audio_files:
            update_job(jobs_dir, job_id, {'status': 'failed', 'error': 'Seslendirme üretilemedi'})
            return

        # Sesleri birleştir
        audio_path = os.path.join(output_dir, 'audio.mp3')
        if len(audio_files) == 1:
            import shutil
            shutil.copy2(audio_files[0], audio_path)
        else:
            if not concat_audio_files(audio_files, audio_path):
                update_job(jobs_dir, job_id, {'status': 'failed', 'error': 'Ses birleştirme başarısız'})
                return

        # Kullanılan TTS servisini kaydet
        update_job(jobs_dir, job_id, {'ttsProvider': tts_provider})

        print(f"  Toplam ses: {sum(actual_durations):.1f}s ({len(segments)} segment)")

    # --- Altyazı: gerçek sürelerle ---
    print(f"[5/6] Altyazı üretiliyor (gerçek sürelerle)...")
    update_job(jobs_dir, job_id, {'status': 'subtitling'})

    # Pause check
    check_pause(jobs_dir, job_id)

    srt_segments = []
    for idx, seg in enumerate(segments):
        srt_segments.append({
            'text': seg['text'],
            'duration': actual_durations[idx]
        })

    srt_path = os.path.join(output_dir, 'subtitles.srt')
    srt_content = generate_srt(srt_segments, srt_path)

    # --- Video: gerçek sürelerle ---
    print(f"[6/6] Video birleştiriliyor...")
    update_job(jobs_dir, job_id, {'status': 'composing'})

    # Pause check
    check_pause(jobs_dir, job_id)

    if not video_enabled:
        print("  Video birleştirme devre dışı.")
        update_job(jobs_dir, job_id, {'status': 'done', 'subtitles': srt_content, 'error': ''})
        print("[OK] Pipeline tamamlandi (video devre disi)")
        return

    if audio_path is None:
        update_job(jobs_dir, job_id, {'status': 'failed', 'error': 'TTS devre dışı, video oluşturulamaz'})
        return

    # Video sahneleri: hook + scenes + outro (gerçek sürelerle + AI seçimli efektler)
    video_scenes = []
    for idx, seg in enumerate(segments):
        vs = {
            'text': seg['text'],
            'duration': actual_durations[idx],
            'type': seg['type'],
            'effect': seg.get('effect', 'ken_burns_zoom_in')  # AI'dan gelen efekt (yeni parametre adı)
        }
        if seg['type'] == 'scene':
            vs['image_index'] = seg['index']
        video_scenes.append(vs)

    video_path = os.path.join(output_dir, 'final_video.mp4')
    
    # Efektleri config'den veya job'dan al (varsayılan: True)
    enable_effects = config.get('enableVideoEffects', True)
    if 'enableVideoEffects' in job_data:
        enable_effects = job_data['enableVideoEffects']
    
    # ===== VIDEO COMPOSITION WITH LOCK & TEMP ISOLATION =====
    # Setup job-specific temp directory for MoviePy
    job_temp_dir = setup_job_temp_dir(job_id)
    original_temp = os.environ.get('TEMP', '')
    original_tmp = os.environ.get('TMP', '')
    
    # Acquire compositor lock (prevents parallel MoviePy conflicts)
    lock = VideoCompositorLock()
    video_ok = False
    
    try:
        # Set job-specific temp directory
        os.environ['TEMP'] = job_temp_dir
        os.environ['TMP'] = job_temp_dir
        
        # Acquire lock (will wait for other jobs to finish)
        lock.acquire(job_id, blocking=True)
        
        # Compose video with isolation
        video_ok = compose_video(
            video_scenes,
            images_dir,
            audio_path,
            srt_path,
            video_path,
            subtitle_style,
            enable_effects,
            bgm_path=bgm_path,
            bgm_volume_db=bgm_volume_db
        )
        
    except TimeoutError as e:
        print(f"  [Lock] Timeout waiting for video compositor: {e}")
        update_job(jobs_dir, job_id, {'status': 'failed', 'error': f'Video kompozisyon kilidi timeout: {e}'})
        return
    except Exception as e:
        print(f"  [Error] Video composition error: {e}")
        import traceback
        traceback.print_exc()
    finally:
        # Release lock
        lock.release()
        
        # Restore original temp directories
        if original_temp:
            os.environ['TEMP'] = original_temp
        if original_tmp:
            os.environ['TMP'] = original_tmp
        
        # Cleanup job temp (only old dirs)
        cleanup_job_temp_dir(job_temp_dir, max_age_hours=1)
    # ===== END VIDEO COMPOSITION =====

    if video_ok and os.path.exists(video_path):
        preview_url = f"/output/{job_id}/final_video.mp4"
        update_job(jobs_dir, job_id, {
            'status': 'done',
            'previewUrl': preview_url,
            'subtitles': srt_content,
            'error': ''
        })
        print(f"[OK] Video hazir: {video_path}")
    else:
        update_job(jobs_dir, job_id, {'status': 'failed', 'error': 'Video birleştirme başarısız'})
        print("[FAIL] Video olusturulamadi")


if __name__ == '__main__':
    if len(sys.argv) < 5:
        print("Kullanım: python pipeline.py <job_id> <url> <template> <config_file>")
        sys.exit(1)
    
    job_id = sys.argv[1]
    url = sys.argv[2]
    template = sys.argv[3]
    config_file = sys.argv[4]
    
    # ===== GLOBAL PRODUCTION LOCK =====
    # Acquire global production lock to ensure sequential processing
    # Only ONE video production at a time across the entire system
    production_lock = GlobalProductionLock()
    
    try:
        print(f"\n[LOCK] Acquiring global production lock for job: {job_id}")
        production_lock.acquire(job_id, blocking=True)
        print(f"[LOCK] ✅ Production lock acquired - starting video production\n")
        
        # Mark job as started in production queue
        try:
            from scheduler.production_queue_manager import ProductionQueueManager
            base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
            queue_manager = ProductionQueueManager(os.path.join(base_dir, 'data'))
            queue_manager.start_job(job_id)
        except Exception as e:
            print(f"[QUEUE] Warning: Could not update queue status: {e}")
        
        # Run the pipeline
        run_pipeline(job_id, url, template, config_file)
        
        # Check if job completed successfully
        jobs_dir = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'data', 'jobs')
        job_file = os.path.join(jobs_dir, f"{job_id}.json")
        success = False
        error_msg = None
        
        if os.path.exists(job_file):
            with open(job_file, 'r', encoding='utf-8') as f:
                job_data = json.load(f)
            status = job_data.get('status')
            success = (status == 'done')
            error_msg = job_data.get('error') if not success else None
        
        # Mark job as completed in production queue
        try:
            queue_manager.complete_job(job_id, success=success, error=error_msg)
            print(f"\n[QUEUE] Job marked as {'completed' if success else 'failed'}")
            
            # Get next job info
            if success:
                status = queue_manager.get_status()
                if status['queue_length'] > 0:
                    next_job = queue_manager.get_next_job()
                    if next_job:
                        print(f"[QUEUE] Next job in queue: {next_job['job_id']}")
        except Exception as e:
            print(f"[QUEUE] Warning: Could not complete job in queue: {e}")
        
    except TimeoutError as e:
        print(f"\n[LOCK] ❌ Timeout acquiring production lock: {e}")
        print("[LOCK] Another video is currently being produced")
        print("[LOCK] Job will remain in queue for retry")
        sys.exit(1)
    
    except KeyboardInterrupt:
        print(f"\n[LOCK] ❌ Production interrupted by user")
        try:
            from scheduler.production_queue_manager import ProductionQueueManager
            base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
            queue_manager = ProductionQueueManager(os.path.join(base_dir, 'data'))
            queue_manager.complete_job(job_id, success=False, error='Interrupted by user')
        except:
            pass
        sys.exit(1)
    
    except Exception as e:
        print(f"\n[PIPELINE] ❌ Error during production: {e}")
        import traceback
        traceback.print_exc()
        
        try:
            from scheduler.production_queue_manager import ProductionQueueManager
            base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
            queue_manager = ProductionQueueManager(os.path.join(base_dir, 'data'))
            queue_manager.complete_job(job_id, success=False, error=str(e))
        except:
            pass
        sys.exit(1)
    
    finally:
        # Release production lock
        production_lock.release()
        print(f"\n[LOCK] ✅ Production lock released")

