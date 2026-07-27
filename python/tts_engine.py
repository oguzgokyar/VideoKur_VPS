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


def tts_elevenlabs(text: str, output_path: str, api_key: str, voice_id: str, model_id: str, voice_profile: str = 'neutral') -> bool:
    """ElevenLabs API ile profesyonel seslendirme üretir."""
    try:
        if not voice_id or not model_id:
            print('ElevenLabs için voice_id ve model_id zorunludur.')
            return False
        url = f'https://api.elevenlabs.io/v1/text-to-speech/{voice_id}'
        headers = {
            'xi-api-key': api_key,
            'Content-Type': 'application/json'
        }

        # Voice profile'a göre ayarlar
        profile = VOICE_PROFILES.get(voice_profile, VOICE_PROFILES['neutral'])

        data = {
            'text': text,
            'model_id': model_id,
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


def generate_tts(text: str, output_path: str, provider: str = 'edge-tts', api_key: str = '',
                 voice_id: str = '', model_id: str = '', voice_profile: str = 'neutral',
                 fallback_provider: str = '', fallback_voice_id: str = '',
                 svc_elevenlabs: bool = True, svc_edge_tts: bool = True) -> bool:
    """Script profilinde açıkça seçilen servis/model/ses ile TTS üretir."""
    def run(selected_provider: str, selected_voice: str) -> bool:
        if selected_provider == 'elevenlabs':
            return bool(svc_elevenlabs and api_key and tts_elevenlabs(text, output_path, api_key, selected_voice, model_id, voice_profile))
        if selected_provider == 'edge-tts':
            return bool(svc_edge_tts and selected_voice and asyncio.run(tts_edge(text, output_path, voice=selected_voice, voice_profile=voice_profile)))
        return False
    if run(provider, voice_id):
        print(f"  [TTS] {provider}, ses: {voice_id}, profil: {voice_profile}")
        return True
    if fallback_provider and run(fallback_provider, fallback_voice_id):
        print(f"  [TTS] Fallback {fallback_provider}, ses: {fallback_voice_id}")
        return True
    print("Seçilen TTS servisi başarısız oldu; tanımlı fallback bulunamadı.")
    return False


if __name__ == '__main__':
    import sys
    text = sys.argv[1] if len(sys.argv) > 1 else 'Merhaba, bu bir test seslendirmesidir.'
    output = sys.argv[2] if len(sys.argv) > 2 else 'test_audio.mp3'
    provider = sys.argv[3] if len(sys.argv) > 3 else 'edge-tts'
    api_key = sys.argv[4] if len(sys.argv) > 4 else ''
    default_voice = 'tr-TR-EmelNeural' if provider == 'edge-tts' else ''
    result = generate_tts(text, output, provider, api_key, voice_id=default_voice)
    print(f"TTS: {'OK' if result else 'FAIL'}")
