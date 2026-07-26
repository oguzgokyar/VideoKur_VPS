import os
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

PYTHON_DIR = Path(__file__).resolve().parents[1]
if str(PYTHON_DIR) not in sys.path:
    sys.path.insert(0, str(PYTHON_DIR))

from ffmpeg_graph import build_filter_graph, even_dimension, scene_filter
from media_probe import find_ffmpeg, probe_media
from video_composer import build_subtitle_style, compose_video


class FFmpegGraphTests(unittest.TestCase):
    def test_dimensions_are_clamped_and_even(self):
        self.assertEqual(even_dimension(359), 360)
        self.assertEqual(even_dimension(1081), 1080)
        self.assertEqual(even_dimension(5000), 4096)

    def test_all_supported_effects_build(self):
        effects = [
            'static', 'ken_burns_zoom_in', 'ken_burns_zoom_out',
            'zoom_in_fast', 'zoom_out_fast', 'pulse', 'pulse_strong',
            'pan_left', 'pan_right', 'drift_left_right',
            'micro_zoom_jitter', 'tilt_pan', 'cinematic_push',
            'glitch_transition',
        ]
        for effect in effects:
            with self.subTest(effect=effect):
                graph = scene_filter(effect, 1.25, 360, 640, 30)
                self.assertIn('trim=end_frame=', graph)
                self.assertIn('setpts=PTS-STARTPTS', graph)

    def test_unknown_effect_uses_safe_fallback(self):
        self.assertEqual(
            scene_filter('unknown', 1, 360, 640, 30),
            scene_filter('ken_burns_zoom_in', 1, 360, 640, 30),
        )

    def test_graph_has_single_video_and_audio_outputs(self):
        graph = build_filter_graph(
            scenes=[{'duration': 1, 'effect': 'static'}],
            width=360,
            height=640,
            fps=30,
            audio_input_index=1,
            audio_duration=1,
            subtitle_path=None,
            subtitle_style=None,
        )
        self.assertEqual(graph.video_label, 'video_out')
        self.assertEqual(graph.audio_label, 'voice')
        self.assertIn('concat=n=1:v=1:a=0', graph.text)
        self.assertIn('format=yuv420p[video_out]', graph.text)

    def test_subtitle_hex_colors_are_ass_colors(self):
        style = build_subtitle_style({
            'PrimaryColour': '#112233',
            'OutlineColour': '#000000',
            'FontSize': 24,
            'preset': 'ignored',
        })
        self.assertIn('PrimaryColour=&H00332211', style)
        self.assertIn('OutlineColour=&H00000000', style)
        self.assertNotIn('preset', style)


class FFmpegComposerSmokeTests(unittest.TestCase):
    def test_single_pass_render_and_probe(self):
        try:
            ffmpeg = find_ffmpeg()
        except FileNotFoundError:
            self.skipTest('ffmpeg is not installed')

        temp_dir = os.environ.get('VIDEOKUR_TEST_DIR')
        if not temp_dir:
            self.skipTest('set VIDEOKUR_TEST_DIR to run the FFmpeg integration test')
        os.makedirs(temp_dir, exist_ok=True)
        audio = os.path.join(temp_dir, 'audio.m4a')
        subprocess.run(
            [ffmpeg, '-hide_banner', '-loglevel', 'error', '-y',
             '-f', 'lavfi', '-i', 'sine=frequency=440:duration=0.5',
             '-c:a', 'aac', audio],
            check=True,
        )
        old_values = {
            key: os.environ.get(key)
            for key in ('VIDEO_PRESET', 'VIDEO_CRF', 'VIDEO_RENDER_TIMEOUT')
        }
        os.environ['VIDEO_PRESET'] = 'ultrafast'
        os.environ['VIDEO_CRF'] = '28'
        os.environ['VIDEO_RENDER_TIMEOUT'] = '20'
        try:
            for width, height in ((360, 640), (360, 360), (640, 360)):
                with self.subTest(width=width, height=height):
                    output = os.path.join(temp_dir, f'video_{width}x{height}.mp4')
                    success = compose_video(
                        [{'type': 'scene', 'image_index': 0, 'duration': 0.5, 'effect': 'static'}],
                        os.path.join(temp_dir, 'missing-images'),
                        audio,
                        '',
                        output,
                        width=width,
                        height=height,
                        fps=30,
                    )
                    self.assertTrue(success)
                    info = probe_media(output)
                    self.assertEqual(info['video']['codec_name'], 'h264')
                    self.assertEqual(info['video']['pix_fmt'], 'yuv420p')
                    self.assertEqual((info['video']['width'], info['video']['height']), (width, height))
                    self.assertEqual(info['audio']['codec_name'], 'aac')
        finally:
            for key, value in old_values.items():
                if value is None:
                    os.environ.pop(key, None)
                else:
                    os.environ[key] = value


if __name__ == '__main__':
    unittest.main()