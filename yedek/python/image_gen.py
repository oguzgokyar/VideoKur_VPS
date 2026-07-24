import requests
import time
import random
import os
try:
    from urllib.parse import quote
except ImportError:
    from urllib import quote

# Fal.ai imports
try:
    import fal_client
    FAL_AVAILABLE = True
except ImportError:
    FAL_AVAILABLE = False

# ============================================================================
# FAL.AI AYARLARI - Minimum maliyet için optimize edilmiş
# ============================================================================
# Fiyatlandırma: $0.003 / megapiksel
# 1024x1024 = 1.05 MP ≈ $0.003
# 512x512 = 0.26 MP ≈ $0.001 (en ucuz)
# 768x768 = 0.59 MP ≈ $0.002

FAL_DEFAULT_MODEL = "fal-ai/flux/schnell"  # En hızlı ve ucuz FLUX modeli
FAL_DEFAULT_STEPS = 4  # Minimum step (1-4 arası), 4 optimal kalite/hız
FAL_COST_OPTIMIZED_SIZE = {"width": 768, "height": 768}  # Maliyet/kalite dengesi

# ============================================================================
# POLLINATIONS AYARLARI
# ============================================================================
POLLINATIONS_RATE_LIMIT = 15  # Anonymous tier: 15 saniye/istek
POLLINATIONS_TIMEOUT = 180
POLLINATIONS_MAX_RETRIES = 3

# Son başarılı istek zamanı (rate limit için)
_last_pollinations_request = 0


# ============================================================================
# FAL.AI FONKSİYONLARI
# ============================================================================

def generate_image_fal(prompt: str, output_path: str, 
                       width: int = None, height: int = None,
                       steps: int = FAL_DEFAULT_STEPS,
                       model: str = FAL_DEFAULT_MODEL,
                       seed: int = None,
                       optimize_cost: bool = True) -> bool:
    """Fal.ai FLUX.1 Schnell ile görsel üretir.
    
    Maliyet: ~$0.003/megapiksel
    - 512x512: ~$0.001
    - 768x768: ~$0.002  
    - 1024x1024: ~$0.003
    
    Args:
        prompt: Görsel açıklaması
        output_path: Çıktı dosya yolu
        width: Görsel genişliği (None ise optimize_cost'a göre)
        height: Görsel yüksekliği (None ise optimize_cost'a göre)
        steps: İnference step sayısı (1-4, default 4)
        model: Fal.ai model ID
        seed: Tekrarlanabilir sonuçlar için seed
        optimize_cost: True ise maliyet optimize boyut kullanır
    
    Returns:
        bool: Başarılı ise True
    """
    if not FAL_AVAILABLE:
        print("[Fal.ai] HATA: fal-client yüklü değil. 'pip install fal-client' çalıştırın.")
        return False
    
    # API key kontrolü
    fal_key = os.environ.get('FAL_KEY')
    if not fal_key:
        print("[Fal.ai] HATA: FAL_KEY environment variable ayarlanmamış.")
        print("[Fal.ai] API key almak için: https://fal.ai/dashboard/keys")
        return False
    
    try:
        # Boyut ayarları - maliyet optimizasyonu
        if width is None or height is None:
            if optimize_cost:
                width = FAL_COST_OPTIMIZED_SIZE["width"]
                height = FAL_COST_OPTIMIZED_SIZE["height"]
            else:
                width = 1024
                height = 1024
        
        # Maliyet tahmini
        megapixels = (width * height) / 1_000_000
        estimated_cost = megapixels * 0.003
        print(f"[Fal.ai] Boyut: {width}x{height}, Tahmini maliyet: ${estimated_cost:.4f}")
        
        # Input hazırla
        input_data = {
            "prompt": prompt,
            "image_size": {"width": width, "height": height},
            "num_inference_steps": steps,
            "num_images": 1,
            "enable_safety_checker": True,
            "output_format": "jpeg"
        }
        
        if seed is not None:
            input_data["seed"] = seed
        
        print(f"[Fal.ai] Görsel üretiliyor... (model: {model}, steps: {steps})")
        
        # API çağrısı
        result = fal_client.subscribe(
            model,
            arguments=input_data
        )
        
        # Sonucu işle
        if result and "images" in result and len(result["images"]) > 0:
            image_url = result["images"][0]["url"]
            
            # Görseli indir
            resp = requests.get(image_url, timeout=60)
            if resp.status_code == 200:
                with open(output_path, 'wb') as f:
                    f.write(resp.content)
                print(f"[Fal.ai] Başarılı! Boyut: {len(resp.content)} bytes, Seed: {result.get('seed', 'N/A')}")
                return True
            else:
                print(f"[Fal.ai] Görsel indirme hatası: HTTP {resp.status_code}")
                return False
        else:
            print(f"[Fal.ai] Beklenmeyen yanıt: {result}")
            return False
            
    except Exception as e:
        print(f"[Fal.ai] Hata: {e}")
        return False


def generate_image_fal_batch(prompts: list, output_dir: str,
                              width: int = None, height: int = None,
                              steps: int = FAL_DEFAULT_STEPS) -> list:
    """Birden fazla görsel üretir (batch işlem).
    
    Args:
        prompts: Prompt listesi
        output_dir: Çıktı klasörü
        width: Görsel genişliği
        height: Görsel yüksekliği
        steps: İnference step sayısı
    
    Returns:
        list: Başarılı dosya yolları listesi
    """
    successful = []
    total_cost = 0
    
    for i, prompt in enumerate(prompts):
        output_path = os.path.join(output_dir, f"image_{i+1:03d}.jpg")
        
        # Maliyet tahmini
        w = width or FAL_COST_OPTIMIZED_SIZE["width"]
        h = height or FAL_COST_OPTIMIZED_SIZE["height"]
        megapixels = (w * h) / 1_000_000
        estimated_cost = megapixels * 0.003
        total_cost += estimated_cost
        
        print(f"\n[Fal.ai Batch] {i+1}/{len(prompts)}: {prompt[:50]}...")
        
        if generate_image_fal(prompt, output_path, width, height, steps):
            successful.append(output_path)
    
    print(f"\n[Fal.ai Batch] Tamamlandı: {len(successful)}/{len(prompts)} başarılı")
    print(f"[Fal.ai Batch] Toplam tahmini maliyet: ${total_cost:.4f}")
    
    return successful


def estimate_fal_cost(width: int, height: int, num_images: int = 1) -> float:
    """Fal.ai maliyet tahmini hesaplar.
    
    Args:
        width: Görsel genişliği
        height: Görsel yüksekliği
        num_images: Görsel sayısı
    
    Returns:
        float: Tahmini maliyet (USD)
    """
    megapixels = (width * height) / 1_000_000
    cost_per_image = megapixels * 0.003
    total = cost_per_image * num_images
    
    print(f"[Fal.ai Maliyet] {width}x{height} = {megapixels:.2f} MP")
    print(f"[Fal.ai Maliyet] Görsel başına: ${cost_per_image:.4f}")
    print(f"[Fal.ai Maliyet] {num_images} görsel için toplam: ${total:.4f}")
    
    return total


# ============================================================================
# POLLINATIONS FONKSİYONLARI
# ============================================================================

def _wait_for_rate_limit():
    """Rate limit için gerekli süreyi bekler."""
    global _last_pollinations_request
    elapsed = time.time() - _last_pollinations_request
    if elapsed < POLLINATIONS_RATE_LIMIT:
        wait = POLLINATIONS_RATE_LIMIT - elapsed
        print(f"[Pollinations] Rate limit: {wait:.1f}s bekleniyor...")
        time.sleep(wait)


def _update_last_request():
    """Son istek zamanını günceller."""
    global _last_pollinations_request
    _last_pollinations_request = time.time()


# Pollinations mevcut görsel modelleri (fallback sırası)
POLLINATIONS_IMAGE_MODELS = ['flux', 'grok-imagine', 'gptimage', 'zimage', 'qwen-image']

# Pollinations API endpoint'leri
POLLINATIONS_API_URL = "https://gen.pollinations.ai/image"  # Yeni endpoint (API key destekli)
POLLINATIONS_FREE_URL = "https://image.pollinations.ai/prompt"  # Eski ücretsiz endpoint


def generate_image_pollinations(prompt: str, output_path: str, model: str = None, 
                                 width: int = None, height: int = None, 
                                 enhance: bool = False, safe: bool = False,
                                 api_key: str = None) -> bool:
    """Pollinations.ai ile AI görsel üretir.
    
    Args:
        prompt: Görsel açıklaması
        output_path: Çıktı dosya yolu
        model: AI modeli ('flux', 'turbo') - None ise otomatik fallback
        width: Görsel genişliği (opsiyonel)
        height: Görsel yüksekliği (opsiyonel)
        enhance: AI ile prompt iyileştirme
        safe: NSFW filtresi
        api_key: Pollinations API anahtarı (opsiyonel, hız ve güvenilirlik için önerilir)
    
    Returns:
        bool: Başarılı ise True
    """
    encoded = quote(prompt)
    
    # Model listesi: belirtilen model varsa önce onu dene, sonra diğerlerini
    if model:
        models_to_try = [model] + [m for m in POLLINATIONS_IMAGE_MODELS if m != model]
    else:
        models_to_try = POLLINATIONS_IMAGE_MODELS
    
    for current_model in models_to_try:
        for attempt in range(POLLINATIONS_MAX_RETRIES):
            try:
                # Rate limit kontrolü (API key varsa daha hızlı)
                if not api_key:
                    _wait_for_rate_limit()
                
                seed = random.randint(1, 99999)
                
                # URL parametreleri
                params = [f"model={current_model}", f"seed={seed}", "enhance=false"]
                if width:
                    params.append(f"width={width}")
                if height:
                    params.append(f"height={height}")
                if enhance:
                    params[-3] = "enhance=true"  # enhance parametresini güncelle
                if safe:
                    params.append("safe=true")
                if api_key:
                    params.append(f"key={api_key}")
                
                # API key varsa yeni endpoint, yoksa eski endpoint
                if api_key:
                    url = f"{POLLINATIONS_API_URL}/{encoded}?{'&'.join(params)}"
                else:
                    url = f"{POLLINATIONS_FREE_URL}/{encoded}?{'&'.join(params)}"
                
                print(f"[Pollinations] Model={current_model}, Deneme {attempt+1}/{POLLINATIONS_MAX_RETRIES}" + (" (API)" if api_key else " (Free)"))
                
                resp = requests.get(url, timeout=POLLINATIONS_TIMEOUT, headers={
                    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
                })
                
                _update_last_request()
                
                if resp.status_code == 200:
                    # Görsel formatı kontrolü (JPEG veya PNG)
                    valid_headers = [b'\xff\xd8\xff\xe0', b'\xff\xd8\xff\xe1', b'\xff\xd8\xff\xdb', b'\x89PNG']
                    if len(resp.content) > 5000 and any(resp.content.startswith(h) for h in valid_headers):
                        with open(output_path, 'wb') as f:
                            f.write(resp.content)
                        print(f"[Pollinations] Başarılı! Model={current_model}, Boyut: {len(resp.content)} bytes")
                        return True
                    else:
                        print(f"[Pollinations] Geçersiz görsel: boyut={len(resp.content)}")
                elif resp.status_code == 429:
                    print(f"[Pollinations] Rate limit aşıldı - 30s bekleniyor...")
                    time.sleep(30)
                    continue
                elif resp.status_code == 500:
                    error_msg = resp.text[:100] if resp.text else "Unknown error"
                    print(f"[Pollinations] Sunucu hatası ({current_model}): {error_msg}")
                    if "No active" in resp.text or "fetch failed" in resp.text:
                        print(f"[Pollinations] {current_model} sunucusu müsait değil, sonraki model deneniyor...")
                        break  # Bu modeli atla, sonrakine geç
                else:
                    print(f"[Pollinations] HTTP {resp.status_code}: {resp.text[:200]}")
                
                if attempt < POLLINATIONS_MAX_RETRIES - 1:
                    wait_time = 10 + (attempt * 5)
                    print(f"[Pollinations] Yeniden deneme için {wait_time}s bekleniyor...")
                    time.sleep(wait_time)
                    
            except requests.exceptions.Timeout:
                print(f"[Pollinations] Zaman aşımı (deneme {attempt+1}/{POLLINATIONS_MAX_RETRIES})")
                if attempt < POLLINATIONS_MAX_RETRIES - 1:
                    time.sleep(10)
            except Exception as e:
                print(f"[Pollinations] Hata (deneme {attempt+1}/{POLLINATIONS_MAX_RETRIES}): {e}")
                if attempt < POLLINATIONS_MAX_RETRIES - 1:
                    time.sleep(10)
    
    print("[Pollinations] Tüm modeller başarısız oldu.")
    return False


def generate_text_pollinations(prompt: str, model: str = 'openai-fast', 
                                system: str = None, temperature: float = 0.7) -> str:
    """Pollinations.ai ile ücretsiz metin üretir.
    
    Args:
        prompt: Kullanıcı mesajı/sorusu
        model: AI modeli ('openai-fast', 'gpt-oss')
        system: Sistem mesajı (AI davranışı)
        temperature: Yaratıcılık seviyesi (0.0-3.0)
    
    Returns:
        str: Üretilen metin veya boş string
    """
    try:
        _wait_for_rate_limit()
        
        messages = []
        if system:
            messages.append({"role": "system", "content": system})
        messages.append({"role": "user", "content": prompt})
        
        payload = {
            "model": model,
            "messages": messages,
            "temperature": temperature
        }
        
        resp = requests.post(
            "https://text.pollinations.ai/openai",
            json=payload,
            timeout=60
        )
        
        _update_last_request()
        
        if resp.status_code == 200:
            data = resp.json()
            return data.get('choices', [{}])[0].get('message', {}).get('content', '')
        else:
            print(f"[Pollinations Text] HTTP {resp.status_code}: {resp.text[:200]}")
            return ''
            
    except Exception as e:
        print(f"[Pollinations Text] Hata: {e}")
        return ''


def generate_audio_pollinations(text: str, output_path: str, voice: str = 'nova') -> bool:
    """Pollinations.ai ile ücretsiz TTS (metin-ses) üretir.
    NOT: Audio modeli şu an anonymous tier'da desteklenmiyor. Kayıt gerekebilir.
    
    Args:
        text: Seslendirilecek metin
        output_path: Çıktı MP3 dosya yolu
        voice: Ses tipi ('alloy', 'echo', 'fable', 'onyx', 'nova', 'shimmer')
    
    Returns:
        bool: Başarılı ise True
    """
    print("[Pollinations Audio] UYARI: Audio modeli şu an anonymous tier'da desteklenmiyor.")
    print("[Pollinations Audio] Kayıt için: https://auth.pollinations.ai")
    return False


def generate_image_huggingface(prompt: str, output_path: str, api_token: str, 
                               model: str = "black-forest-labs/FLUX.1-schnell") -> bool:
    """HuggingFace Inference Providers API ile görsel üretir.
    
    Yeni HuggingFace API, 'Inference Providers' izni gerektirir.
    Token oluşturma: https://huggingface.co/settings/tokens/new?tokenType=fineGrained
    İzin: 'inference.serverless.write' ekleyin.
    
    Args:
        prompt: Görsel açıklaması
        output_path: Çıktı dosya yolu
        api_token: HuggingFace API token'ı (Inference Providers izni gerekli)
        model: Kullanılacak model (varsayılan: FLUX.1-schnell)
    
    Returns:
        bool: Başarı durumu
    """
    # Yeni router API endpoint
    url = f"https://router.huggingface.co/hf-inference/models/{model}"
    headers = {
        "Authorization": f"Bearer {api_token}",
        "Content-Type": "application/json"
    }
    
    # FLUX modelleri farklı parametre formatı kullanır
    if "FLUX" in model:
        payload = {
            "inputs": prompt,
            "parameters": {"width": 768, "height": 1344}  # 9:16 oran
        }
    else:
        payload = {"inputs": prompt}

    for attempt in range(3):
        try:
            resp = requests.post(url, headers=headers, json=payload, timeout=180)
            ct = resp.headers.get('content-type', '')
            
            if resp.status_code == 200 and (ct.startswith('image') or len(resp.content) > 1000):
                with open(output_path, 'wb') as f:
                    f.write(resp.content)
                return True
            elif resp.status_code == 503:
                wait_sec = int(resp.headers.get('X-wait-seconds', '30'))
                wait_sec = min(wait_sec + 5, 60)
                print(f"HuggingFace model yükleniyor, {wait_sec}s bekleniyor... (deneme {attempt+1}/3)")
                time.sleep(wait_sec)
            elif resp.status_code == 403:
                error_msg = resp.json().get('error', 'Bilinmeyen hata')
                print(f"[HuggingFace] İzin hatası: {error_msg}")
                print("[HuggingFace] Token'ınız 'Inference Providers' iznine sahip değil.")
                print("[HuggingFace] Yeni token oluşturun: https://huggingface.co/settings/tokens/new?tokenType=fineGrained")
                print("[HuggingFace] 'Inference Providers - Make calls to the serverless Inference Providers' iznini ekleyin.")
                return False
            elif resp.status_code == 401:
                print("[HuggingFace] Geçersiz API token!")
                return False
            else:
                print(f"[HuggingFace] Hata: {resp.status_code} - {resp.text[:300]}")
                return False
        except Exception as e:
            print(f"[HuggingFace] İstek hatası: {e}")
            if attempt < 2:
                time.sleep(10)
    return False


def generate_image_pexels(query: str, output_path: str, api_key: str) -> bool:
    """Pexels API'den stok görsel indirir (fallback)."""
    try:
        url = "https://api.pexels.com/v1/search"
        headers = {"Authorization": api_key}
        params = {"query": query, "per_page": 1, "orientation": "portrait"}

        resp = requests.get(url, headers=headers, params=params, timeout=15)
        photos = resp.json().get('photos', [])
        if not photos:
            return False

        img_url = photos[0]['src']['large2x']
        img_resp = requests.get(img_url, timeout=30)
        with open(output_path, 'wb') as f:
            f.write(img_resp.content)
        return True
    except Exception as e:
        print(f"Pexels hatası: {e}")
        return False


# ============================================================================
# ANA GÖRSEL ÜRETİM FONKSİYONU (Akıllı Fallback)
# ============================================================================

def generate_image(prompt: str, output_path: str, 
                   fal_key: str = None, hf_token: str = '', pexels_key: str = '',
                   width: int = None, height: int = None,
                   provider: str = 'auto') -> bool:
    """Görsel üretir. Akıllı fallback mekanizması ile en uygun servisi seçer.
    
    Öncelik sırası (provider='auto'):
    1. Fal.ai (FAL_KEY varsa) - Hızlı, ucuz, stabil
    2. Pollinations (ücretsiz) - Rate limit var
    3. HuggingFace (hf_token varsa) - Yavaş olabilir
    4. Pexels (pexels_key varsa) - Stok görsel
    
    Args:
        prompt: Görsel açıklaması
        output_path: Çıktı dosya yolu
        fal_key: Fal.ai API anahtarı (veya FAL_KEY env var)
        hf_token: HuggingFace API token
        pexels_key: Pexels API anahtarı
        width: Görsel genişliği
        height: Görsel yüksekliği
        provider: 'auto', 'fal', 'pollinations', 'huggingface', 'pexels'
    
    Returns:
        bool: Başarılı ise True
    """
    # FAL_KEY environment variable veya parametre
    fal_api_key = fal_key or os.environ.get('FAL_KEY')
    
    # Provider seçimi
    if provider == 'fal' or (provider == 'auto' and fal_api_key):
        if fal_api_key:
            os.environ['FAL_KEY'] = fal_api_key
            success = generate_image_fal(prompt, output_path, width, height)
            if success:
                return True
            if provider == 'fal':
                return False
            print("[Fallback] Fal.ai başarısız, Pollinations deneniyor...")
    
    if provider == 'pollinations' or provider == 'auto':
        success = generate_image_pollinations(prompt, output_path)
        if success:
            return True
        if provider == 'pollinations':
            return False
        print("[Fallback] Pollinations başarısız, diğer servisler deneniyor...")
    
    if provider == 'huggingface' or (provider == 'auto' and hf_token):
        if hf_token:
            success = generate_image_huggingface(prompt, output_path, hf_token)
            if success:
                return True
            if provider == 'huggingface':
                return False
            print("[Fallback] HuggingFace başarısız...")
    
    if provider == 'pexels' or (provider == 'auto' and pexels_key):
        if pexels_key:
            query = ' '.join(prompt.split()[:3])
            success = generate_image_pexels(query, output_path, pexels_key)
            if success:
                return True
    
    print("[Hata] Görsel üretilemedi: Tüm servisler başarısız veya API anahtarı yok")
    return False


if __name__ == '__main__':
    import sys
    
    print("=" * 60)
    print("GÖRSEL ÜRETİM TEST")
    print("=" * 60)
    
    prompt = sys.argv[1] if len(sys.argv) > 1 else 'a beautiful sunset over mountains'
    output = sys.argv[2] if len(sys.argv) > 2 else 'test_output.jpg'
    
    print(f"Prompt: {prompt}")
    print(f"Output: {output}")
    print("-" * 60)
    
    # Maliyet tahmini
    if os.environ.get('FAL_KEY'):
        print("\n[Maliyet Tahmini]")
        estimate_fal_cost(768, 768, 1)
    
    print("\n[Üretim Başlıyor]")
    result = generate_image(prompt, output)
    
    print("\n" + "=" * 60)
    print(f"Sonuç: {'✓ BAŞARILI' if result else '✗ BAŞARISIZ'}")
    print("=" * 60)
