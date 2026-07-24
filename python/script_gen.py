import json
import requests
import google.genai as genai
import sys
import os
import threading
import time
import random

# Import error messages
sys.path.insert(0, os.path.join(os.path.dirname(__file__), 'utils'))
try:
    from error_messages import get_user_friendly_error, format_error_for_job, extract_error_code
except ImportError:
    # Fallback if error_messages.py not found
    def format_error_for_job(error_type, details=''):
        return f"{error_type}: {details}"
    def extract_error_code(exception):
        error_str = str(exception)
        if '503' in error_str:
            return '503'
        elif '429' in error_str:
            return '429'
        else:
            return 'unknown'


def _build_script_prompt(title: str, text: str, max_duration: int = 55, prompt_template: str | None = None) -> str:
    if prompt_template:
        return (prompt_template
                .replace('{{TITLE}}', title)
                .replace('{{TEXT}}', text[:2000])
                .replace('{{MAX_DURATION}}', str(max_duration)))
    return f"""Sen bir profesyonel YouTube Shorts video scripti yazarısın.
Aşağıdaki haber içeriğinden maksimum {max_duration} saniyelik, dikkat çekici bir Türkçe video scripti yaz.

Kurallar:
- Kısa, vurucu cümleler kullan
- Her sahne 5-8 saniye olsun
- Her sahne için: sahne numarası, metin (seslendirme), görsel açıklaması (İngilizce, AI görsel üretimi için) ver
- Hook (giriş) ve Outro (kapanış) için de görsel açıklaması ver
- Thumbnail (video kapak görseli) için de özel görsel açıklaması ver - bu görsel videoyu özetlemeli ve tıklanabilir olmalı
- JSON formatında döndür
- image_prompt: Sahne metninde geçen ürün adı, oyun adı, marka adı, kişi adı, yer adı gibi spesifik bilgileri MUTLAKA İngilizce görsel açıklamasına ekle. Örnek: sahne metni "iPhone 16 tanıtıldı" ise image_prompt "Apple iPhone 16 smartphone product launch promotional photo" olmalı. Genel ifadelerden kaçın, sahneye özel ve somut açıklama yaz.
- hook_image_prompt: Hook için dikkat çekici, viral içerik tarzında, haberin ana konusunu yansıtan İngilizce görsel açıklaması
- outro_image_prompt: Kapanış için call-to-action, subscribe/like ikonları içeren modern İngilizce görsel açıklaması
- thumbnail_image_prompt: YouTube kapak görseli için dikkat çekici, high quality, professional thumbnail görsel açıklaması (haberin ana konusunu yansıtan, bold text space bırakacak şekilde)

**VİDEO EFEKT SEÇİMİ (ÖNEMLİ):**
Her sahne için içeriğe uygun video efekti seç:

Mevcut efektler:
- "ken_burns_zoom_in" - Yavaş zoom in + pan (profesyonel, genel haberler)
- "ken_burns_zoom_out" - Yavaş zoom out + pan (açılış sahneleri, büyük resim)
- "zoom_in_fast" - Hızlı zoom in (heyecanlı, acil haberler, şok edici içerik)
- "zoom_out_fast" - Hızlı zoom out (dramatik açılış)
- "pulse" - Hafif nabız efekti (ürün tanıtımı, önemli vurgular)
- "pulse_strong" - Güçlü nabız (CTA, outro, çok önemli bilgi)
- "pan_left" - Sola kaydırma (zaman akışı, geçiş)
- "pan_right" - Sağa kaydırma (ilerleme, süreklilik)
- "static" - Hareketsiz (istatistik, grafik, okunması gereken metin)
- "glitch_transition" - Glitch geçiş efekti (teknoloji haberleri, modern içerik)
- "drift_left_right" - Yumuşak sağ-sol drift (dengeli dinamizm, konuşma sahneleri)
- "micro_zoom_jitter" - Mikro zoom titreşimi (enerjik vurgu, kısa dikkat çekme)
- "tilt_pan" - Hafif tilt + pan hareketi (hikaye akışı, geçiş sahneleri)
- "cinematic_push" - Sinematik ileri itiş zoomu (dramatik/odaklı anlatım)

Efekt seçim rehberi:
- Heyecanlı/acil haberler → zoom_in_fast
- Teknoloji/oyun haberleri → glitch_transition veya ken_burns_zoom_in
- İstatistik/sayısal veri → static veya pulse
- Duygusal/derin içerik → ken_burns_zoom_in (yavaş)
- Ürün lansmanı → pulse veya zoom_in_fast
- Genel haberler → ken_burns_zoom_in veya pan_right
- Hikaye/akış odaklı sahneler → tilt_pan veya drift_left_right
- Kısa vurgu anları → micro_zoom_jitter
- Dramatik odak → cinematic_push
- Hook (açılış) → zoom_in_fast (dikkat çek)
- Outro (kapanış) → pulse_strong (CTA vurgusu)

Her sahne için "effect" parametresi ekle!

**SESLENDİRME DUYGU/TON SEÇİMİ (ÖNEMLİ):**
Her sahne için içeriğe uygun ses tonu ve duygu seç:

Mevcut ses profilleri:
- "neutral" - Nötr, standart haber tonu (genel haberler)
- "excited" - Heyecanlı, enerjik (ürün lansmanı, müjdeli haber)
- "urgent" - Acil, hızlı (son dakika, kritik bilgi)
- "serious" - Ciddi, otoriter (önemli duyuru, resmi haber)
- "calm" - Sakin, yatıştırıcı (açıklama, istatistik)
- "dramatic" - Dramatik, duygusal (trajik haber, derin konu)
- "cheerful" - Neşeli, pozitif (iyi haber, başarı hikayesi)

Ses profili seçim rehberi:
- Son dakika/şok haber → urgent
- Teknoloji lansmanı → excited
- Resmi açıklama → serious
- İstatistik/analiz → calm
- Trajedi/kaza → dramatic
- Başarı hikayesi → cheerful
- Genel haber → neutral
- Hook (açılış) → urgent veya excited (dikkat çek)
- Outro (kapanış) → cheerful (pozitif bırak)

Her sahne için "voice_profile" parametresi ekle!

Haber Başlığı: {title}
Haber Metni: {text[:2000]}

Yanıtı şu JSON formatında ver (sadece JSON, başka açıklama yazma):
{{
  "hook": "Dikkat çekici açılış cümlesi",
  "hook_image_prompt": "Eye-catching viral intro visual with specific news topic elements",
  "hook_effect": "zoom_in_fast",
  "hook_voice_profile": "urgent",
  "scenes": [
    {{
      "scene": 1,
      "text": "Seslendirme metni",
      "image_prompt": "Specific English image description mentioning exact product/brand/game/person from the scene text",
      "duration": 6,
      "effect": "ken_burns_zoom_in",
      "voice_profile": "neutral"
    }}
  ],
  "outro": "Kapanış cümlesi",
  "outro_image_prompt": "Video outro with subscribe button, like icon, comment reminder, modern social media style",
  "outro_effect": "pulse_strong",
  "outro_voice_profile": "cheerful",
  "thumbnail_image_prompt": "Professional YouTube thumbnail with dramatic lighting, bold colors, eye-catching composition for news topic, space for text overlay"
}}"""

def generate_script(title: str, text: str, api_key: str, max_duration: int = 55, model_name: str = 'gemini-2.0-flash', prompt_template: str | None = None, timeout: int = 120) -> dict:
    """
    Gemini API ile haber metninden video scripti üretir.
    
    Args:
        title: Video başlığı
        text: Haber metni
        api_key: Gemini API key
        max_duration: Maksimum video süresi (saniye)
        model_name: Kullanılacak model
        prompt_template: Özel prompt template
        timeout: API timeout süresi (saniye, varsayılan 120)
    
    Returns:
        dict with success, script/error fields
    """
    result = {'success': False, 'timeout': False, 'error': ''}
    
    def _api_call():
        """API call in separate thread for timeout control"""
        try:
            client = genai.Client(api_key=api_key)
            response = client.models.generate_content(
                model=model_name,
                contents=_build_script_prompt(title, text, max_duration, prompt_template)
            )
            
            raw = response.text.strip()

            if '```json' in raw:
                raw = raw.split('```json')[1].split('```')[0].strip()
            elif '```' in raw:
                raw = raw.split('```')[1].split('```')[0].strip()

            script = json.loads(raw)
            result['success'] = True
            result['script'] = script
            
        except Exception as e:
            result['success'] = False
            result['error'] = str(e)
    
    # Start API call in thread
    thread = threading.Thread(target=_api_call, daemon=True)
    thread.start()
    thread.join(timeout=timeout)
    
    # Check if timeout occurred
    if thread.is_alive():
        # Thread still running = TIMEOUT
        result['timeout'] = True
        result['success'] = False
        result['error'] = format_error_for_job('timeout', f'API did not respond in {timeout} seconds')
        return result
    
    # Thread finished - check result
    if not result['success'] and result['error']:
        # Format error with user-friendly message
        error_code = extract_error_code(result['error'])
        result['error'] = format_error_for_job(error_code, result['error'])
    
    return result


def generate_script_with_fallback(title: str, text: str, api_keys: list, max_duration: int = 55, model_name: str = 'gemini-2.0-flash', prompt_template: str | None = None, timeout: int = 120) -> dict:
    """
    Multi-key desteği ile Gemini script üretimi.
    
    Retry/Fallback stratejisi:
    - 429 (quota): Sonraki key'i dene
    - 503 (server yoğun): Sonraki key'i dene
    - 500, 502 (server error): Sonraki key'i dene
    - timeout: Sonraki key'i dene
    - 403 (permission denied): Bu key'i atla, sonraki key'i dene
    - 404, diğer: Non-retriable, durdur
    
    Kullanıcı kontrolü:
    - Tüm key'ler denendi ve başarısız → Kullanıcıya bildir
    - Non-retriable error → Hemen durdur, kullanıcıya bildir
    - Kullanıcı "Devam Et" ile manuel retry yapabilir
    
    Args:
        api_keys: API key listesi
        timeout: Her API call için timeout (saniye, varsayılan 120)
    
    Returns:
        dict with success, script/error, timeout fields
    """
    if not api_keys:
        return {'success': False, 'error': 'No API keys provided'}
    
    # Tek key ise array'e çevir
    if isinstance(api_keys, str):
        api_keys = [api_keys]
    
    # Bu hatalarda sonraki key'e fallback yapılır
    KEY_FALLBACK_ERRORS = ['403', '429', '503', '500', '502', 'timeout']
    MAX_ROUNDS = 2  # tüm key setini en fazla 2 tur dener
    service_busy_count = 0
    attempts = []
    last_result = {'success': False, 'error': 'Unknown error'}

    for round_idx in range(MAX_ROUNDS):
        print(f"🔁 API key round {round_idx + 1}/{MAX_ROUNDS}")
        all_retriable = True

        for idx, key in enumerate(api_keys):
            print(f"Trying API key {idx + 1}/{len(api_keys)}...")
            result = generate_script(title, text, key, max_duration, model_name, prompt_template, timeout)
            last_result = result

            if result['success']:
                print(f"✅ Success with API key {idx + 1}")
                result['attempts'] = attempts
                return result

            last_error = result.get('error', 'Unknown error')
            error_code = extract_error_code(last_error)
            attempts.append({'round': round_idx + 1, 'key_index': idx + 1, 'error_code': error_code})
            print(f"❌ Key {idx + 1} failed: {error_code}")

            # Check if we should try next key
            should_try_next_key = any(code in str(last_error) for code in KEY_FALLBACK_ERRORS)
            if not should_try_next_key:
                all_retriable = False
                print(f"🛑 Non-retriable error ({error_code}), stopping")
                result['attempts'] = attempts
                return result

            # Special logging for common errors
            if '403' in str(last_error):
                print("   ⚠️  403 PERMISSION_DENIED: API key banned or project access denied")
            elif '429' in str(last_error):
                print("   ⚠️  429 RESOURCE_EXHAUSTED: Quota exceeded, trying next key...")
            elif '503' in str(last_error):
                print("   ⚠️  503 SERVICE_UNAVAILABLE: Server busy, trying next key...")
                service_busy_count += 1
                base_delay = min(45.0, 5.0 * (2 ** max(0, service_busy_count - 1)))
                jitter = random.uniform(0.0, 3.0)
                sleep_time = round(base_delay + jitter, 1)
                print(f"⏳ 503 backoff: {sleep_time}s bekleniyor (base={base_delay:.1f}s, jitter={jitter:.1f}s)")
                time.sleep(sleep_time)

            if idx < len(api_keys) - 1:
                print(f"♻️ Fallback error ({error_code}), trying next key...")

        # Tüm key turu bitti: sadece retriable hatalar varsa bir tur daha dene
        if round_idx < MAX_ROUNDS - 1 and all_retriable:
            wait_time = 10 + (round_idx * 10)
            print(f"⏳ All keys failed with retriable errors, waiting {wait_time}s before next round...")
            time.sleep(wait_time)

    # All keys exhausted across all rounds
    final_error = last_result.get('error', 'Unknown error')
    return {
        'success': False,
        'attempts': attempts,
        'error': format_error_for_job(
            'quota_exceeded',
            f'All {len(api_keys)} API keys failed after {MAX_ROUNDS} round(s). Last error: {final_error}'
        )
    }



def generate_script_pollinations(title: str, text: str, model: str = 'openai', max_duration: int = 55, prompt_template: str | None = None) -> dict:
    """Pollinations.ai Text API ile haber metninden video scripti üretir. API anahtarı gerekmez."""
    try:
        prompt = _build_script_prompt(title, text, max_duration, prompt_template)
        url = "https://text.pollinations.ai/"
        payload = {
            "messages": [
                {"role": "system", "content": "Sen bir JSON üreten API'sin. Sadece geçerli JSON döndür, başka bir şey yazma."},
                {"role": "user", "content": prompt}
            ],
            "model": model,
            "seed": -1,
            "jsonMode": True
        }
        resp = requests.post(url, json=payload, timeout=60)
        if resp.status_code != 200:
            return {'success': False, 'error': f'Pollinations hatası: HTTP {resp.status_code}'}

        raw = resp.text.strip()
        if '```json' in raw:
            raw = raw.split('```json')[1].split('```')[0].strip()
        elif '```' in raw:
            raw = raw.split('```')[1].split('```')[0].strip()

        script = json.loads(raw)
        return {'success': True, 'script': script}
    except json.JSONDecodeError as e:
        return {'success': False, 'error': f'JSON parse hatası: {e}'}
    except Exception as e:
        return {'success': False, 'error': str(e)}


if __name__ == '__main__':
    import sys
    api_key = sys.argv[1] if len(sys.argv) > 1 else ''
    title = sys.argv[2] if len(sys.argv) > 2 else 'Test'
    text = sys.argv[3] if len(sys.argv) > 3 else 'Test haber metni'
    result = generate_script(title, text, api_key)
    print(json.dumps(result, ensure_ascii=False, indent=2))
