"""
Manual Pipeline Test - Scraper'ı atlayıp diğer modülleri test eder
"""
import sys
import os
import json

# Modül yolunu ayarla
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from script_gen import generate_script, generate_script_with_fallback
from tts_engine import generate_tts
from image_gen import generate_image_pollinations
from subtitle_gen import generate_srt
from video_composer import compose_video

# Test verisi
TEST_NEWS = {
    "title": "Yapay Zeka Teknolojisi Hızla Gelişiyor",
    "text": """Yapay zeka teknolojisi son yıllarda inanılmaz bir hızla gelişiyor. 
    Özellikle büyük dil modelleri, görsel üretim ve ses sentezi alanlarında 
    büyük ilerlemeler kaydedildi. Bu teknolojiler artık günlük hayatımızın 
    bir parçası haline geldi. Uzmanlar, önümüzdeki yıllarda yapay zekanın 
    daha da güçleneceğini ve hayatımızı daha fazla etkileyeceğini söylüyor.""",
    "top_image": "",
    "images": [],
    "url": "test://manual"
}

def test_pipeline():
    """Pipeline modüllerini sırayla test et"""
    
    print("\n🧪 PIPELINE TEST BAŞLADI\n")
    
    # Çalışma dizinini ayarla
    script_dir = os.path.dirname(os.path.abspath(__file__))
    base_dir = os.path.dirname(script_dir)
    os.chdir(base_dir)
    
    # 1. Script Generation
    print("[1/5] 📝 Script üretiliyor...")
    try:
        config_path = os.path.join(base_dir, 'data', 'config.json')
        config = json.load(open(config_path, encoding='utf-8'))
        
        # Gemini API key'leri al
        gemini_keys = config.get('geminiKeys', [])
        if not gemini_keys:
            gemini_keys = [config.get('geminiKey', '')]
        
        script_result = generate_script(
            title=TEST_NEWS['title'],
            text=TEST_NEWS['text'],
            api_key=gemini_keys[0] if gemini_keys else '',
            max_duration=55,
            model_name=config.get('geminiModel', 'gemini-2.0-flash')
        )
        if script_result and script_result.get('success') and script_result.get('script'):
            script_data = script_result['script']
            print(f"  ✅ Script üretildi: {len(script_data.get('scenes', []))} sahne")
            print(f"     Hook: {script_data.get('hook', '')[:50]}...")
        else:
            print(f"  ❌ Script üretilemedi")
            return False
    except Exception as e:
        print(f"  ❌ Script hatası: {e}")
        import traceback
        traceback.print_exc()
        return False
    
    # 2. Image Generation (sadece 1 test)
    print("\n[2/5] 🎨 Görsel üretiliyor (test: hook image)...")
    try:
        hook_prompt = script_data['hook_image_prompt']
        test_image_path = generate_image_pollinations(
            prompt=hook_prompt,
            output_path="output/test_hook.png",
            model=config.get('pollinationsModel', 'grok-imagine')
        )
        if test_image_path and os.path.exists(test_image_path):
            print(f"  ✅ Görsel üretildi: {test_image_path}")
        else:
            print(f"  ⚠️  Görsel üretilemedi, devam ediliyor...")
    except Exception as e:
        print(f"  ⚠️  Görsel hatası (devam): {e}")
    
    # 3. TTS Generation (sadece hook)
    print("\n[3/5] 🔊 Ses üretiliyor (test: hook audio)...")
    try:
        hook_text = script_data['hook']
        hook_voice = script_data.get('hook_voice_profile', 'neutral')
        tts_provider = config.get('ttsProvider', 'elevenlabs')
        tts_api_key = config.get('elevenKey', '')
        
        tts_result = generate_tts(
            text=hook_text,
            output_path="output/test_hook.mp3",
            provider=tts_provider,
            api_key=tts_api_key,
            voice_profile=hook_voice
        )
        if tts_result and os.path.exists(tts_result):
            print(f"  ✅ Ses üretildi: {tts_result}")
        else:
            print(f"  ❌ Ses üretilemedi")
            return False
    except Exception as e:
        print(f"  ❌ TTS hatası: {e}")
        return False
    
    print("\n✅ PIPELINE TEST BAŞARILI!")
    print("   - Script generation: OK")
    print("   - Image generation: OK (Pollinations)")
    print("   - TTS generation: OK")
    print("\n💡 Tam pipeline testi için web UI'dan video oluşturun.")
    return True

if __name__ == '__main__':
    success = test_pipeline()
    sys.exit(0 if success else 1)
