import json
import requests
import google.genai as genai


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

def generate_script(title: str, text: str, api_key: str, max_duration: int = 55, model_name: str = 'gemini-2.0-flash', prompt_template: str | None = None) -> dict:
    """Gemini API ile haber metninden video scripti üretir."""
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
        return {'success': True, 'script': script}
    except Exception as e:
        return {'success': False, 'error': str(e)}


def generate_script_with_fallback(title: str, text: str, api_keys: list, max_duration: int = 55, model_name: str = 'gemini-2.0-flash', prompt_template: str | None = None) -> dict:
    """Multi-key desteği ile Gemini script üretimi. Biri fail olursa sıradakini dener."""
    if not api_keys:
        return {'success': False, 'error': 'No API keys provided'}
    
    # Tek key ise array'e çevir
    if isinstance(api_keys, str):
        api_keys = [api_keys]
    
    last_error = None
    for idx, key in enumerate(api_keys):
        try:
            result = generate_script(title, text, key, max_duration, model_name, prompt_template)
            if result['success']:
                return result
            else:
                last_error = result.get('error', 'Unknown error')
                # 429 (quota) hatası ise sonraki key'i dene
                if '429' in str(last_error) or 'quota' in str(last_error).lower():
                    continue
                else:
                    # Başka bir hata ise hemen döndür
                    return result
        except Exception as e:
            last_error = str(e)
            if '429' in str(e) or 'quota' in str(e).lower():
                continue
            else:
                return {'success': False, 'error': str(e)}
    
    return {'success': False, 'error': f'All {len(api_keys)} API keys failed. Last error: {last_error}'}



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
