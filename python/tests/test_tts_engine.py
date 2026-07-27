import os
import sys
import tempfile
import unittest
from unittest.mock import Mock, patch

sys.path.insert(0, os.path.dirname(os.path.dirname(__file__)))

import tts_engine


class CartesiaTtsTests(unittest.TestCase):
    @patch('tts_engine.requests.post')
    def test_cartesia_writes_mp3_and_maps_voice_profile(self, post):
        response = Mock(status_code=200, content=b'fake-mp3', headers={})
        post.return_value = response

        with tempfile.TemporaryDirectory(dir=os.path.dirname(__file__)) as temp_dir:
            output = os.path.join(temp_dir, 'speech.mp3')
            result = tts_engine.tts_cartesia(
                'Merhaba dünya', output, 'sk_car_test', 'voice-123',
                model_id='sonic-3.5', voice_profile='excited'
            )

            self.assertTrue(result)
            with open(output, 'rb') as audio_file:
                self.assertEqual(audio_file.read(), b'fake-mp3')

        request = post.call_args
        self.assertEqual(request.args[0], 'https://api.cartesia.ai/tts/bytes')
        self.assertEqual(request.kwargs['headers']['Cartesia-Version'], '2026-03-01')
        self.assertEqual(request.kwargs['headers']['Authorization'], 'Bearer sk_car_test')
        payload = request.kwargs['json']
        self.assertEqual(payload['language'], 'tr')
        self.assertEqual(payload['output_format']['container'], 'mp3')
        self.assertEqual(payload['generation_config']['emotion'], 'excited')
        self.assertEqual(payload['generation_config']['speed'], 1.12)

    @patch('tts_engine.tts_elevenlabs', return_value=True)
    @patch('tts_engine.tts_cartesia', return_value=False)
    def test_fallback_uses_its_own_model_and_credentials(self, cartesia, elevenlabs):
        result = tts_engine.generate_tts(
            'Deneme', 'speech.mp3', provider='cartesia', api_key='eleven-key',
            cartesia_api_key='cartesia-key', voice_id='cartesia-voice',
            model_id='sonic-3.5', fallback_provider='elevenlabs',
            fallback_voice_id='eleven-voice', fallback_model_id='eleven_flash_v2_5'
        )

        self.assertTrue(result)
        cartesia.assert_called_once_with(
            'Deneme', 'speech.mp3', 'cartesia-key', 'cartesia-voice', 'sonic-3.5', 'neutral'
        )
        elevenlabs.assert_called_once_with(
            'Deneme', 'speech.mp3', 'eleven-key', 'eleven-voice', 'eleven_flash_v2_5', 'neutral'
        )

    @patch('tts_engine.time.sleep')
    @patch('tts_engine.requests.post')
    def test_cartesia_retries_transient_errors(self, post, sleep):
        retry = Mock(status_code=429, content=b'', headers={'Retry-After': '1'})
        success = Mock(status_code=200, content=b'audio', headers={})
        post.side_effect = [retry, success]

        with tempfile.TemporaryDirectory(dir=os.path.dirname(__file__)) as temp_dir:
            output = os.path.join(temp_dir, 'speech.mp3')
            self.assertTrue(tts_engine.tts_cartesia(
                'Tekrar dene', output, 'sk_car_test', 'voice-123'
            ))

        self.assertEqual(post.call_count, 2)
        sleep.assert_called_once_with(1.0)


if __name__ == '__main__':
    unittest.main()