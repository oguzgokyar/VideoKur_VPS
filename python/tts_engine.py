import asyncio
import time
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

CARTESIA_VERSION = '2026-03-01'
CARTESIA_PROFILE_CONFIG = {
    'neutral': {'speed': 1.0, 'emotion': 'neutral'},
    'excited': {'speed': 1.12, 'emotion': 'excited'},
    'urgent': {'speed': 1.15, 'emotion': 'alarmed'},
    'serious': {'speed': 0.95, 'emotion': 'confident'},
    'calm': {'speed': 0.9, 'emotion': 'calm'},
    'dramatic': {'speed': 0.95, 'emotion': 'mysterious'},
    'cheerful': {'speed': 1.08, 'emotion': 'happy'},
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


def tts_cartesia(text: str, output_path: str, api_key: str, voice_id: str,
                 model_id: str = 'sonic-3.5', voice_profile: str = 'neutral') -> bool:
    """Cartesia Sonic API ile Türkçe MP3 seslendirme üretir."""
    if not api_key or not voice_id or not model_id:
        print('Cartesia için api_key, voice_id ve model_id zorunludur.')
        return False

    profile = CARTESIA_PROFILE_CONFIG.get(voice_profile, CARTESIA_PROFILE_CONFIG['neutral'])
    payload = {
        'model_id': model_id,
        'transcript': text,
        'voice': {'mode': 'id', 'id': voice_id},
        'output_format': {
            'container': 'mp3',
            'sample_rate': 44100,
            'bit_rate': 128000,
        },
        'language': 'tr',
        'generation_config': {
            'volume': 1.0,
            'speed': profile['speed'],
            'emotion': profile['emotion'],
        },
    }
    headers = {
        'Authorization': f'Bearer {api_key}',
        'Cartesia-Version': CARTESIA_VERSION,
        'Content-Type': 'application/json',
    }

    try:
        for attempt in range(3):
            response = requests.post(
                'https://api.cartesia.ai/tts/bytes',
                json=payload,
                headers=headers,
                timeout=90,
            )
            if response.status_code == 200 and response.content:
                with open(output_path, 'wb') as output_file:
                    output_file.write(response.content)
                return True

            retryable = response.status_code == 429 or response.status_code >= 500
            if retryable and attempt < 2:
                retry_after = response.headers.get('Retry-After', '')
                delay = float(retry_after) if retry_after.replace('.', '', 1).isdigit() else 2 ** attempt
                time.sleep(min(max(delay, 0.5), 10))
                continue

            try:
                error = response.json()
            except ValueError:
                error = {}
            message = error.get('message') or response.text[:200]
            request_id = error.get('request_id')
            detail = f" - {message}" if message else ''
            if request_id:
                detail += f" (request_id: {request_id})"
            print(f"Cartesia hatası: {response.status_code}{detail}")
            return False
    except (requests.RequestException, OSError, ValueError) as error:
        print(f"Cartesia hatası: {error}")
    return False


def generate_tts(text: str, output_path: str, provider: str = 'edge-tts', api_key: str = '',
                 voice_id: str = '', model_id: str = '', voice_profile: str = 'neutral',
                 fallback_provider: str = '', fallback_voice_id: str = '', fallback_model_id: str = '',
                 cartesia_api_key: str = '', svc_elevenlabs: bool = True,
                 svc_edge_tts: bool = True, svc_cartesia: bool = True) -> bool:
    """Script profilinde açıkça seçilen servis/model/ses ile TTS üretir."""
    def run(selected_provider: str, selected_voice: str, selected_model: str) -> bool:
        if selected_provider == 'elevenlabs':
            return bool(svc_elevenlabs and api_key and tts_elevenlabs(
                text, output_path, api_key, selected_voice, selected_model, voice_profile
            ))
        if selected_provider == 'cartesia':
            return bool(svc_cartesia and cartesia_api_key and tts_cartesia(
                text, output_path, cartesia_api_key, selected_voice, selected_model, voice_profile
            ))
        if selected_provider == 'edge-tts':
            return bool(svc_edge_tts and selected_voice and asyncio.run(tts_edge(text, output_path, voice=selected_voice, voice_profile=voice_profile)))
        return False
    if run(provider, voice_id, model_id):
        print(f"  [TTS] {provider}, ses: {voice_id}, profil: {voice_profile}")
        return True
    if fallback_provider and run(fallback_provider, fallback_voice_id, fallback_model_id):
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
