import asyncio
import requests

# Voice profile ayarları
VOICE_PROFILES = {
    'neutral': {'stability': 0.5, 'similarity_boost': 0.75, 'style': 0.5},
    'excited': {'stability': 0.3, 'similarity_boost': 0.8, 'style': 0.9},
    'urgent': {'stability': 0.4, 'similarity_boost': 0.8, 'style': 0.8},
    'serious': {'stability': 0.7, 'similarity_boost': 0.75, 'style': 0.5},
    'calm': {'stability': 0.8, 'similarity_boost': 0.7, 'style': 0.3},
    'dramatic': {'stability': 0.5, 'similarity_boost': 0.8, 'style': 0.8},
    'cheerful': {'stability': 0.4, 'similarity_boost': 0.75, 'style': 0.7},
}


async def tts_edge(text: str, output_path: str, voice: str = 'tr-TR-EmelNeural', voice_profile: str = 'neutral') -> bool:
    """edge-tts ile Türkçe seslendirme üretir (ücretsiz, sınırsız)."""
    try:
        import edge_tts
        # Edge-TTS voice profile'ı rate ve pitch ile simüle et
        profile = VOICE_PROFILES.get(voice_profile, VOICE_PROFILES['neutral'])
        
        # Style bazlı rate ayarı
        rate_map = {
            'excited': '+15%', 'urgent': '+20%', 'serious': '-5%',
            'calm': '-10%', 'dramatic': '+5%', 'cheerful': '+10%', 'neutral': '+0%'
        }
        rate = rate_map.get(voice_profile, '+0%')
        
        communicate = edge_tts.Communicate(text, voice, rate=rate)
        await communicate.save(output_path)
        return True
    except Exception as e:
        print(f"edge-tts hatası: {e}")
        return False


def tts_elevenlabs(text: str, output_path: str, api_key: str, voice_id: str = None, voice_profile: str = 'neutral') -> bool:
    """ElevenLabs API ile profesyonel seslendirme üretir."""
    try:
        if not voice_id:
            headers = {'xi-api-key': api_key}
            resp = requests.get('https://api.elevenlabs.io/v1/voices', headers=headers, timeout=15)
            voices = resp.json().get('voices', [])
            voice_id = voices[0]['voice_id'] if voices else 'EXAVITQu4vr4xnSDxMaL'

        url = f'https://api.elevenlabs.io/v1/text-to-speech/{voice_id}'
        headers = {
            'xi-api-key': api_key,
            'Content-Type': 'application/json'
        }
        
        # Voice profile'a göre ayarlar
        profile = VOICE_PROFILES.get(voice_profile, VOICE_PROFILES['neutral'])
        
        data = {
            'text': text,
            'model_id': 'eleven_multilingual_v2',
            'voice_settings': {
                'stability': profile['stability'],
                'similarity_boost': profile['similarity_boost'],
                'style': profile.get('style', 0.5),
                'use_speaker_boost': True
            }
        }

        resp = requests.post(url, json=data, headers=headers, timeout=60)
        if resp.status_code == 200:
            with open(output_path, 'wb') as f:
                f.write(resp.content)
            return True
        else:
            print(f"ElevenLabs hatası: {resp.status_code} - {resp.text[:200]}")
            return False
    except Exception as e:
        print(f"ElevenLabs hatası: {e}")
        return False


def generate_tts(text: str, output_path: str, provider: str = 'elevenlabs', api_key: str = '',
                 voice_profile: str = 'neutral', svc_elevenlabs: bool = True, svc_edge_tts: bool = True) -> bool:
    """TTS üret. Voice profile desteği ile. ElevenLabs başarısız olursa edge-tts'e düşer."""
    if provider == 'elevenlabs' and api_key and svc_elevenlabs:
        success = tts_elevenlabs(text, output_path, api_key, voice_profile=voice_profile)
        if success:
            print(f"  [TTS] ElevenLabs, profil: {voice_profile}")
            return True
        if svc_edge_tts:
            print("ElevenLabs başarısız, edge-tts'e geçiliyor...")

    if svc_edge_tts:
        result = asyncio.run(tts_edge(text, output_path, voice_profile=voice_profile))
        if result:
            print(f"  [TTS] Edge-TTS, profil: {voice_profile}")
        return result

    print("Tüm TTS servisleri devre dışı veya başarısız")
    return False


if __name__ == '__main__':
    import sys
    text = sys.argv[1] if len(sys.argv) > 1 else 'Merhaba, bu bir test seslendirmesidir.'
    output = sys.argv[2] if len(sys.argv) > 2 else 'test_audio.mp3'
    provider = sys.argv[3] if len(sys.argv) > 3 else 'edge-tts'
    api_key = sys.argv[4] if len(sys.argv) > 4 else ''
    result = generate_tts(text, output, provider, api_key)
    print(f"TTS: {'OK' if result else 'FAIL'}")
