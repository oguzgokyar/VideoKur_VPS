"""
YouTube Shorts Otomasyon — Kısmi Yeniden Üretim
Belirli bir bölümü yeniden üretir: news, script, images, tts, subtitles, video
Extra params (JSON string 5th arg): image_service, scene_index, subtitle_style, prompt
"""
import sys
import os
import json
import shutil

# Windows'ta UTF-8 çıktı için
if sys.platform == 'win32':
    sys.stdout.reconfigure(encoding='utf-8', errors='replace')
    sys.stderr.reconfigure(encoding='utf-8', errors='replace')

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from pipeline import update_job, get_audio_duration, concat_audio_files

def ensure_outro_cta(script: dict) -> dict:
    outro = (script.get('outro') or '').strip()
    if not outro:
        outro = "Abone ol, Beğen ve Yorum yap! Daha fazlası için bizi takip et."
    required_terms = ['Abone ol', 'Beğen', 'Yorum yap']
    if not all(term in outro for term in required_terms):
        outro = f"{outro} Abone ol, Beğen ve Yorum yap!".strip()
    script['outro'] = outro
    return script


# ── Görsel Versiyonlama ────────────────────────────────────────────────────────

def _backup_image(img_path: str) -> str | None:
    """Mevcut görseli versiyonlu isimle yedekler. Yedek yolunu döndürür."""
    if not os.path.exists(img_path):
        return None
    # Versiyon numarasını bul: scene_1_v1.png, scene_1_v2.png ...
    base, ext = os.path.splitext(img_path)
    # Zaten versiyonlu ise (örn _v2) ana ismi bul
    import re
    match = re.search(r'_v(\d+)$', base)
    if match:
        base = base[:match.start()]
    # En yüksek mevcut versiyonu bul
    v = 1
    while os.path.exists(f"{base}_v{v}{ext}"):
        v += 1
    backup_path = f"{base}_v{v}{ext}"
    shutil.copy2(img_path, backup_path)
    return backup_path


def _update_versions_json(versions_file: str, key: str, backup_path: str, current_path: str):
    """versions.json dosyasına yeni versiyonu ekler."""
    versions = {}
    if os.path.exists(versions_file):
        try:
            with open(versions_file, 'r', encoding='utf-8') as f:
                versions = json.load(f)
        except Exception:
            versions = {}
    if key not in versions:
        versions[key] = []
    # Mevcut aktif görseli listeye ekle (ilk kez)
    if backup_path and not any(v['path'] == backup_path for v in versions[key]):
        versions[key].append({'path': backup_path, 'active': False})
    # En son (yeni üretilen) aktif olacak — current_path placeholder (henüz üretilmedi)
    versions[key].append({'path': current_path, 'active': True})
    # Önceki aktif=False yap
    for i, v in enumerate(versions[key][:-1]):
        versions[key][i]['active'] = False
    with open(versions_file, 'w', encoding='utf-8') as f:
        json.dump(versions, f, ensure_ascii=False, indent=2)


def _save_versions_after_gen(versions_file: str, key: str, img_path: str):
    """Görsel üretildikten sonra versions.json'ı günceller."""
    versions = {}
    if os.path.exists(versions_file):
        try:
            with open(versions_file, 'r', encoding='utf-8') as f:
                versions = json.load(f)
        except Exception:
            versions = {}
    if key not in versions:
        versions[key] = []
    # Son kayıt aktif current olmalı; path'i aktif olarak işaretle
    # Eğer listedeyse güncelle
    found = False
    for v in versions[key]:
        if v['path'] == img_path:
            v['active'] = True
            found = True
        else:
            v['active'] = False
    if not found:
        # Öncekiler inactive
        for v in versions[key]:
            v['active'] = False
        versions[key].append({'path': img_path, 'active': True})
    with open(versions_file, 'w', encoding='utf-8') as f:
        json.dump(versions, f, ensure_ascii=False, indent=2)



def run_regenerate(job_id: str, section: str, config_file: str, extra: dict = None):
    extra = extra or {}
    base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    jobs_dir = os.path.join(base_dir, 'data', 'jobs')
    output_dir = os.path.join(base_dir, 'output', job_id)
    images_dir = os.path.join(output_dir, 'images')
    audio_dir = os.path.join(output_dir, 'audio_segments')

    os.makedirs(output_dir, exist_ok=True)
    os.makedirs(images_dir, exist_ok=True)
    os.makedirs(audio_dir, exist_ok=True)

    config = {}
    if os.path.exists(config_file):
        with open(config_file, 'r', encoding='utf-8') as f:
            config = json.load(f)

    gemini_key   = config.get('geminiKey', '')
    eleven_key   = config.get('elevenKey', '')
    hf_key       = config.get('hfKey', '')
    pexels_key   = config.get('pexelsKey', '')
    pollinations_key = config.get('pollinationsKey', '')  # Pollinations API key
    fal_key      = config.get('falKey', '')  # Fal.ai API key
    tts_provider = config.get('ttsProvider', 'elevenlabs')
    gemini_model = config.get('geminiModel', 'gemini-2.0-flash')
    pollinations_model = config.get('pollinationsModel', 'flux')

    job_file = os.path.join(jobs_dir, f"{job_id}.json")
    with open(job_file, 'r', encoding='utf-8') as f:
        job = json.load(f)

    # Job'dan video ebatlarını al
    video_width = job.get('videoWidth', 1080)
    video_height = job.get('videoHeight', 1920)
    fal_width    = video_width
    fal_height   = video_height
    fal_steps    = config.get('falSteps', 4)

    prev_status = job.get('status', 'done')

    def restore_done(updates=None):
        d = {'status': 'done'}
        if updates:
            d.update(updates)
        update_job(jobs_dir, job_id, d)

    def fail(msg):
        update_job(jobs_dir, job_id, {'status': 'failed', 'error': msg})

    try:
        _run_section(section, job_id, job, prev_status, extra, config, jobs_dir, output_dir, images_dir, audio_dir,
                     gemini_key, eleven_key, hf_key, pexels_key, pollinations_key, fal_key, 
                     tts_provider, gemini_model, pollinations_model, fal_width, fal_height, fal_steps,
                     restore_done, fail)
    except Exception as e:
        import traceback
        traceback.print_exc()
        fail(f'Beklenmeyen hata: {e}')


def _run_section(section, job_id, job, prev_status, extra, config, jobs_dir, output_dir, images_dir, audio_dir,
                 gemini_key, eleven_key, hf_key, pexels_key, pollinations_key, fal_key,
                 tts_provider, gemini_model, pollinations_model, fal_width, fal_height, fal_steps,
                 restore_done, fail):
    """Bölüm bazlı yeniden üretim mantığı."""

    # ── Haber yeniden çek ──────────────────────────────────────────────────────
    if section == 'news':
        from scraper import scrape_news
        url = job.get('url', '')
        update_job(jobs_dir, job_id, {'status': 'scraping'})
        news = scrape_news(url)
        if not news.get('text'):
            update_job(jobs_dir, job_id, {'status': prev_status, 'error': 'Haber metni çekilemedi'})
            return
        with open(os.path.join(output_dir, 'news.json'), 'w', encoding='utf-8') as f:
            json.dump(news, f, ensure_ascii=False, indent=2)
        restore_done()

    # ── Script yeniden oluştur ─────────────────────────────────────────────────
    elif section == 'script':
        from script_gen import generate_script
        news_file = os.path.join(output_dir, 'news.json')
        if not os.path.exists(news_file):
            update_job(jobs_dir, job_id, {'status': prev_status, 'error': 'news.json bulunamadı'})
            return
        with open(news_file, 'r', encoding='utf-8') as f:
            news = json.load(f)
        update_job(jobs_dir, job_id, {'status': 'scripting'})
        result = generate_script(news['title'], news['text'], gemini_key, model_name=gemini_model)
        if not result.get('success'):
            update_job(jobs_dir, job_id, {'status': prev_status, 'error': f"Script hatası: {result.get('error', '')}"})
            return
        script = ensure_outro_cta(result['script'])
        with open(os.path.join(output_dir, 'script.json'), 'w', encoding='utf-8') as f:
            json.dump(script, f, ensure_ascii=False, indent=2)
        restore_done()

    # ── Tüm görseller yeniden üret ────────────────────────────────────────────
    elif section == 'images':
        script_file = os.path.join(output_dir, 'script.json')
        news_file = os.path.join(output_dir, 'news.json')
        if not os.path.exists(script_file):
            update_job(jobs_dir, job_id, {'status': prev_status, 'error': 'script.json bulunamadı'})
            return
        with open(script_file, 'r', encoding='utf-8') as f:
            script = json.load(f)
        news = {}
        if os.path.exists(news_file):
            with open(news_file, 'r', encoding='utf-8') as f:
                news = json.load(f)
        scenes = script.get('scenes', [])
        image_service = extra.get('image_service', config.get('imageService', 'auto'))
        update_job(jobs_dir, job_id, {'status': 'imaging'})
        
        # Hook görseli üret (AI tarafından üretilen prompt'u kullan)
        if script.get('hook'):
            hook_prompt = script.get('hook_image_prompt', f"Eye-catching video thumbnail, attention grabbing intro visual, viral content style, breaking news cover, dramatic lighting, {news.get('title', 'news')[:50]}")
            hook_img_path = os.path.join(images_dir, 'hook.png')
            hook_used = _generate_with_service(image_service, hook_prompt, hook_img_path,
                                               hf_key, pexels_key, pollinations_key, fal_key,
                                               pollinations_model, fal_width, fal_height, fal_steps)
            script['hook_used_service'] = hook_used
            print(f"  Hook görseli: {'OK' if os.path.exists(hook_img_path) else 'FAIL'} ({hook_used})")
        
        # Sahne görselleri üret
        for i, scene in enumerate(scenes):
            prompt = scene.get('image_prompt', 'news background')
            img_path = os.path.join(images_dir, f"scene_{i+1}.png")
            used = _generate_with_service(image_service, prompt, img_path, 
                                          hf_key, pexels_key, pollinations_key, fal_key,
                                          pollinations_model, fal_width, fal_height, fal_steps)
            scenes[i]['used_service'] = used
            print(f"  Sahne {i+1}: {'OK' if os.path.exists(img_path) else 'FAIL'} ({used})")
        
        # Outro görseli üret (AI tarafından üretilen prompt'u kullan)
        if script.get('outro'):
            outro_prompt = script.get('outro_image_prompt', "Video outro closing scene, call to action visual, subscribe and comment icons, social media engagement, like and follow buttons, channel subscribe reminder, clean modern design")
            outro_img_path = os.path.join(images_dir, 'outro.png')
            outro_used = _generate_with_service(image_service, outro_prompt, outro_img_path,
                                                hf_key, pexels_key, pollinations_key, fal_key,
                                                pollinations_model, fal_width, fal_height, fal_steps)
            script['outro_used_service'] = outro_used
            print(f"  Outro görseli: {'OK' if os.path.exists(outro_img_path) else 'FAIL'} ({outro_used})")
        
        # Save service info back to script.json
        script['scenes'] = scenes
        with open(script_file, 'w', encoding='utf-8') as f:
            json.dump(script, f, ensure_ascii=False, indent=2)
        restore_done()

    # ── Tek sahne görseli yeniden üret ────────────────────────────────────────
    elif section == 'image_single':
        scene_type = extra.get('scene_type', 'scene')  # 'scene', 'hook', 'outro', 'thumbnail'
        scene_index = int(extra.get('scene_index', 1))  # 1-based (sadece scene için)
        script_file = os.path.join(output_dir, 'script.json')
        news_file = os.path.join(output_dir, 'news.json')
        image_service = extra.get('image_service', config.get('imageService', 'auto'))
        prompt = extra.get('prompt', '')
        versions_file = os.path.join(output_dir, 'image_versions.json')
        
        news = {}
        if os.path.exists(news_file):
            with open(news_file, 'r', encoding='utf-8') as f:
                news = json.load(f)
        
        script = {}
        if os.path.exists(script_file):
            with open(script_file, 'r', encoding='utf-8') as f:
                script = json.load(f)
        
        update_job(jobs_dir, job_id, {'status': 'imaging'})
        
        if scene_type == 'hook':
            if not prompt:
                prompt = script.get('hook_image_prompt', f"Eye-catching video thumbnail, attention grabbing intro visual, viral content style, breaking news cover, dramatic lighting, {news.get('title', 'news')[:50]}")
            img_path = os.path.join(images_dir, 'hook.png')
            ver_key = 'hook'
            backup = _backup_image(img_path)
            _update_versions_json(versions_file, ver_key, backup, img_path)
            used = _generate_with_service(image_service, prompt, img_path,
                                          hf_key, pexels_key, pollinations_key, fal_key,
                                          pollinations_model, fal_width, fal_height, fal_steps)
            script['hook_used_service'] = used
            _save_versions_after_gen(versions_file, ver_key, img_path)
            print(f"  Hook: {'OK' if os.path.exists(img_path) else 'FAIL'} ({used})")
            
        elif scene_type == 'outro':
            if not prompt:
                prompt = script.get('outro_image_prompt', "Video outro closing scene, call to action visual, subscribe and comment icons, social media engagement, like and follow buttons, channel subscribe reminder, clean modern design")
            img_path = os.path.join(images_dir, 'outro.png')
            ver_key = 'outro'
            backup = _backup_image(img_path)
            _update_versions_json(versions_file, ver_key, backup, img_path)
            used = _generate_with_service(image_service, prompt, img_path,
                                          hf_key, pexels_key, pollinations_key, fal_key,
                                          pollinations_model, fal_width, fal_height, fal_steps)
            script['outro_used_service'] = used
            _save_versions_after_gen(versions_file, ver_key, img_path)
            print(f"  Outro: {'OK' if os.path.exists(img_path) else 'FAIL'} ({used})")
            
        elif scene_type == 'thumbnail':
            if not prompt:
                prompt = script.get('thumbnail_image_prompt', f"Professional YouTube thumbnail, dramatic lighting, bold colors, eye-catching, news media style, {news.get('title', 'breaking news')[:50]}, space for text overlay")
            img_path = os.path.join(output_dir, 'thumbnail.jpg')
            ver_key = 'thumbnail'
            backup = _backup_image(img_path)
            _update_versions_json(versions_file, ver_key, backup, img_path)
            used = _generate_with_service(image_service, prompt, img_path,
                                          hf_key, pexels_key, pollinations_key, fal_key,
                                          pollinations_model, fal_width, fal_height, fal_steps)
            script['thumbnail_used_service'] = used
            _save_versions_after_gen(versions_file, ver_key, img_path)
            print(f"  Thumbnail: {'OK' if os.path.exists(img_path) else 'FAIL'} ({used})")
            
        else:  # scene
            if not prompt:
                scenes = script.get('scenes', [])
                if 0 < scene_index <= len(scenes):
                    prompt = scenes[scene_index - 1].get('image_prompt', 'news background')
            prompt = prompt or 'news background'
            img_path = os.path.join(images_dir, f"scene_{scene_index}.png")
            ver_key = f'scene_{scene_index}'
            backup = _backup_image(img_path)
            _update_versions_json(versions_file, ver_key, backup, img_path)
            used = _generate_with_service(image_service, prompt, img_path, 
                                          hf_key, pexels_key, pollinations_key, fal_key,
                                          pollinations_model, fal_width, fal_height, fal_steps)
            # Save used service to script.json
            scenes = script.get('scenes', [])
            if 0 < scene_index <= len(scenes):
                scenes[scene_index - 1]['used_service'] = used
                script['scenes'] = scenes
            _save_versions_after_gen(versions_file, ver_key, img_path)
            print(f"  Sahne {scene_index}: {'OK' if os.path.exists(img_path) else 'FAIL'} ({used})")
        
        # Script'i kaydet
        with open(script_file, 'w', encoding='utf-8') as f:
            json.dump(script, f, ensure_ascii=False, indent=2)
        restore_done()

    # ── Prompt güncelle (script.json'a kaydet) ────────────────────────────────
    elif section == 'update_prompt':
        scene_index = int(extra.get('scene_index', 1))  # 1-based
        new_prompt = extra.get('prompt', '')
        script_file = os.path.join(output_dir, 'script.json')
        if not os.path.exists(script_file):
            update_job(jobs_dir, job_id, {'status': prev_status, 'error': 'script.json bulunamadı'})
            return
        with open(script_file, 'r', encoding='utf-8') as f:
            script = json.load(f)
        scenes = script.get('scenes', [])
        if 0 < scene_index <= len(scenes):
            scenes[scene_index - 1]['image_prompt'] = new_prompt
            script['scenes'] = scenes
            with open(script_file, 'w', encoding='utf-8') as f:
                json.dump(script, f, ensure_ascii=False, indent=2)
            print(f"  Sahne {scene_index} prompt güncellendi.")
        restore_done()

    # ── TTS yeniden üret ──────────────────────────────────────────────────────
    elif section == 'tts':
        from tts_engine import generate_tts
        script_file = os.path.join(output_dir, 'script.json')
        if not os.path.exists(script_file):
            update_job(jobs_dir, job_id, {'status': prev_status, 'error': 'script.json bulunamadı'})
            return
        with open(script_file, 'r', encoding='utf-8') as f:
            script = json.load(f)
        scenes = script.get('scenes', [])
        segments = []
        if script.get('hook'):
            segments.append({'text': script['hook'], 'type': 'hook'})
        for i, scene in enumerate(scenes):
            segments.append({'text': scene.get('text', ''), 'type': 'scene', 'index': i})
        if script.get('outro'):
            segments.append({'text': script['outro'], 'type': 'outro'})

        update_job(jobs_dir, job_id, {'status': 'tts'})
        audio_files = []
        actual_durations = []
        for idx, seg in enumerate(segments):
            seg_audio = os.path.join(audio_dir, f"seg_{idx:02d}.mp3")
            tts_ok = generate_tts(seg['text'], seg_audio, tts_provider, eleven_key)
            if tts_ok:
                dur = get_audio_duration(seg_audio)
                actual_durations.append(dur)
                audio_files.append(seg_audio)
                print(f"  Segment {idx+1}: {dur:.1f}s")
            else:
                actual_durations.append(3.0)
                print(f"  Segment {idx+1}: FAIL")

        if not audio_files:
            update_job(jobs_dir, job_id, {'status': prev_status, 'error': 'Seslendirme üretilemedi'})
            return

        audio_path = os.path.join(output_dir, 'audio.mp3')
        if len(audio_files) == 1:
            shutil.copy2(audio_files[0], audio_path)
        else:
            concat_audio_files(audio_files, audio_path)

        # Auto-regenerate subtitles with new actual durations (keeps them in sync)
        from subtitle_gen import generate_srt
        srt_segments = [{'text': seg['text'], 'duration': actual_durations[idx]} for idx, seg in enumerate(segments)]
        srt_path = os.path.join(output_dir, 'subtitles.srt')
        srt_content = generate_srt(srt_segments, srt_path)
        restore_done({'subtitles': srt_content, 'ttsProvider': tts_provider})

    # ── Altyazı yeniden üret ──────────────────────────────────────────────────
    elif section == 'subtitles':
        from subtitle_gen import generate_srt
        script_file = os.path.join(output_dir, 'script.json')
        if not os.path.exists(script_file):
            update_job(jobs_dir, job_id, {'status': prev_status, 'error': 'script.json bulunamadı'})
            return
        with open(script_file, 'r', encoding='utf-8') as f:
            script = json.load(f)
        scenes = script.get('scenes', [])
        segments = []
        if script.get('hook'):
            segments.append({'text': script['hook'], 'type': 'hook'})
        for i, scene in enumerate(scenes):
            segments.append({'text': scene.get('text', ''), 'type': 'scene', 'index': i})
        if script.get('outro'):
            segments.append({'text': script['outro'], 'type': 'outro'})

        actual_durations = []
        for idx in range(len(segments)):
            seg_audio = os.path.join(audio_dir, f"seg_{idx:02d}.mp3")
            actual_durations.append(get_audio_duration(seg_audio) if os.path.exists(seg_audio) else 5.0)

        update_job(jobs_dir, job_id, {'status': 'subtitling'})
        srt_segments = [{'text': seg['text'], 'duration': actual_durations[idx]} for idx, seg in enumerate(segments)]
        srt_path = os.path.join(output_dir, 'subtitles.srt')
        srt_content = generate_srt(srt_segments, srt_path)
        update_job(jobs_dir, job_id, {'status': 'done', 'subtitles': srt_content})

    # ── Video yeniden birleştir ────────────────────────────────────────────────
    elif section == 'video':
        from video_composer import compose_video, SUBTITLE_PRESETS
        script_file = os.path.join(output_dir, 'script.json')
        if not os.path.exists(script_file):
            update_job(jobs_dir, job_id, {'status': prev_status, 'error': 'script.json bulunamadı'})
            return
        with open(script_file, 'r', encoding='utf-8') as f:
            script = json.load(f)
        scenes = script.get('scenes', [])
        segments = []
        if script.get('hook'):
            segments.append({'text': script['hook'], 'type': 'hook'})
        for i, scene in enumerate(scenes):
            segments.append({'text': scene.get('text', ''), 'type': 'scene', 'index': i})
        if script.get('outro'):
            segments.append({'text': script['outro'], 'type': 'outro'})

        actual_durations = []
        for idx in range(len(segments)):
            seg_audio = os.path.join(audio_dir, f"seg_{idx:02d}.mp3")
            actual_durations.append(get_audio_duration(seg_audio) if os.path.exists(seg_audio) else 5.0)

        update_job(jobs_dir, job_id, {'status': 'composing'})
        video_scenes = []
        for idx, seg in enumerate(segments):
            vs = {'text': seg['text'], 'duration': actual_durations[idx], 'type': seg['type']}
            if seg['type'] == 'scene':
                vs['image_index'] = seg['index']
            video_scenes.append(vs)

        audio_path = os.path.join(output_dir, 'audio.mp3')
        srt_path   = os.path.join(output_dir, 'subtitles.srt')
        video_path = os.path.join(output_dir, 'final_video.mp4')

        # Remove stale temp file if left from previous run
        temp_path = video_path.replace('.mp4', '_temp.mp4')
        if os.path.exists(temp_path):
            os.remove(temp_path)

        # Altyazı stili: extra'dan al, job'dan al, yoksa preset adı
        subtitle_style = extra.get('subtitle_style')  # dict or preset name
        if isinstance(subtitle_style, str):
            subtitle_style = SUBTITLE_PRESETS.get(subtitle_style, SUBTITLE_PRESETS['classic'])
        elif subtitle_style is None:
            saved_style = job.get('subtitleStyle')
            if isinstance(saved_style, str):
                subtitle_style = SUBTITLE_PRESETS.get(saved_style, SUBTITLE_PRESETS['classic'])
            elif isinstance(saved_style, dict):
                subtitle_style = saved_style
            else:
                subtitle_style = SUBTITLE_PRESETS['classic']

        # Stili job'a kaydet
        if extra.get('subtitle_style'):
            update_job(jobs_dir, job_id, {'subtitleStyle': extra['subtitle_style']})

        video_ok = compose_video(video_scenes, images_dir, audio_path, srt_path, video_path,
                                 subtitle_style=subtitle_style)

        if video_ok and os.path.exists(video_path):
            preview_url = f"/output/{job_id}/final_video.mp4"
            update_job(jobs_dir, job_id, {'status': 'done', 'previewUrl': preview_url, 'error': ''})
            print(f"[OK] Video hazir: {video_path}")
        else:
            update_job(jobs_dir, job_id, {'status': prev_status, 'error': 'Video birleştirme başarısız'})
            print("[FAIL] Video olusturulamadi")

    else:
        print(f"Bilinmeyen bölüm: {section}")


def _generate_with_service(service: str, prompt: str, img_path: str, 
                           hf_key: str, pexels_key: str, pollinations_key: str, fal_key: str,
                           pollinations_model: str = 'flux', 
                           fal_width: int = 768, fal_height: int = 768, fal_steps: int = 4) -> str:
    """Belirtilen servisle görsel üretir. Kullanılan servis adını döndürür."""
    from image_gen import generate_image_pollinations, generate_image_huggingface, generate_image_pexels, generate_image_fal
    import os as _os

    def _poll():
        return generate_image_pollinations(prompt, img_path, pollinations_model, 
                                           width=fal_width, height=fal_height,
                                           api_key=pollinations_key)

    def _fal():
        if not fal_key:
            return False
        _os.environ['FAL_KEY'] = fal_key
        return generate_image_fal(prompt, img_path, width=fal_width, height=fal_height, steps=fal_steps)

    def _hf():
        return generate_image_huggingface(prompt, img_path, hf_key) if hf_key else False

    def _pex():
        query = ' '.join(prompt.split()[:4])
        return generate_image_pexels(query, img_path, pexels_key) if pexels_key else False

    if service == 'pollinations':
        return 'pollinations' if _poll() else 'failed'
    elif service == 'fal':
        return 'fal' if _fal() else 'failed'
    elif service == 'huggingface':
        return 'huggingface' if _hf() else 'failed'
    elif service == 'pexels':
        return 'pexels' if _pex() else 'failed'
    else:
        # auto: pollinations → fal → huggingface → pexels
        if _poll():
            return 'pollinations'
        if _fal():
            return 'fal'
        if _hf():
            return 'huggingface'
        if _pex():
            return 'pexels'
        return 'failed'


if __name__ == '__main__':
    if len(sys.argv) < 4:
        print("Kullanım: python regenerate.py <job_id> <section> <config_file> [extra_json]")
        sys.exit(1)
    extra_arg = {}
    if len(sys.argv) >= 5:
        try:
            extra_arg = json.loads(sys.argv[4])
        except Exception:
            pass
    run_regenerate(sys.argv[1], sys.argv[2], sys.argv[3], extra_arg)
