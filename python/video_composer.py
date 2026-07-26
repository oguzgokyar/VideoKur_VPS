"""Single-pass FFmpeg video composer for VideoKur."""

from __future__ import annotations

import math
import os
import tempfile
from pathlib import Path

from ffmpeg_graph import build_filter_graph, even_dimension
from ffmpeg_runner import run_ffmpeg
from media_probe import find_ffmpeg, get_media_duration, validate_render


SUBTITLE_PRESETS = {
    "classic": {
        "FontName": "Arial",
        "FontSize": 20,
        "PrimaryColour": "&H00FFFFFF",
        "OutlineColour": "&H00000000",
        "BorderStyle": 3,
        "Outline": 2,
        "Shadow": 0,
        "MarginV": 80,
        "MarginL": 40,
        "MarginR": 40,
        "Alignment": 2,
        "Bold": 0,
    },
    "bold_bottom": {
        "FontName": "Arial",
        "FontSize": 24,
        "PrimaryColour": "&H00FFFFFF",
        "OutlineColour": "&H00000000",
        "BorderStyle": 3,
        "Outline": 3,
        "Shadow": 1,
        "MarginV": 100,
        "MarginL": 40,
        "MarginR": 40,
        "Alignment": 2,
        "Bold": 1,
    },
    "yellow_bold": {
        "FontName": "Arial",
        "FontSize": 22,
        "PrimaryColour": "&H0000FFFF",
        "OutlineColour": "&H00000000",
        "BorderStyle": 1,
        "Outline": 2,
        "Shadow": 1,
        "MarginV": 80,
        "MarginL": 40,
        "MarginR": 40,
        "Alignment": 2,
        "Bold": 1,
    },
    "box_white": {
        "FontName": "Arial",
        "FontSize": 20,
        "PrimaryColour": "&H00000000",
        "OutlineColour": "&H00FFFFFF",
        "BackColour": "&H80000000",
        "BorderStyle": 4,
        "Outline": 0,
        "Shadow": 0,
        "MarginV": 80,
        "MarginL": 40,
        "MarginR": 40,
        "Alignment": 2,
        "Bold": 0,
    },
    "tiktok": {
        "FontName": "Arial",
        "FontSize": 26,
        "PrimaryColour": "&H00FFFFFF",
        "OutlineColour": "&H000000FF",
        "BorderStyle": 3,
        "Outline": 3,
        "Shadow": 0,
        "MarginV": 120,
        "MarginL": 40,
        "MarginR": 40,
        "Alignment": 2,
        "Bold": 1,
    },
    "minimal": {
        "FontName": "Arial",
        "FontSize": 18,
        "PrimaryColour": "&H00FFFFFF",
        "OutlineColour": "&H00000000",
        "BorderStyle": 1,
        "Outline": 1,
        "Shadow": 0,
        "MarginV": 60,
        "MarginL": 40,
        "MarginR": 40,
        "Alignment": 2,
        "Bold": 0,
    },
}


def _hex_to_ass(value: str) -> str:
    if not value or not value.startswith("#"):
        return value
    value = value.removeprefix("#")
    if len(value) != 6:
        return f"#{value}"
    red, green, blue = value[0:2], value[2:4], value[4:6]
    return f"&H00{blue}{green}{red}".upper()


def build_subtitle_style(style_params: dict | None) -> str:
    style_params = style_params or SUBTITLE_PRESETS["classic"]
    allowed = {
        "FontName",
        "FontSize",
        "PrimaryColour",
        "OutlineColour",
        "BackColour",
        "BorderStyle",
        "Outline",
        "Shadow",
        "MarginV",
        "MarginL",
        "MarginR",
        "Alignment",
        "Bold",
    }
    colors = {"PrimaryColour", "OutlineColour", "BackColour"}
    parts: list[str] = []
    for key, value in style_params.items():
        if key not in allowed or value is None:
            continue
        if key in colors:
            value = _hex_to_ass(str(value))
        parts.append(f"{key}={value}")
    return ",".join(parts)


def _scene_image_path(scene: dict, scene_number: int, images_dir: str) -> str | None:
    scene_type = scene.get("type", "scene")
    if scene_type == "hook":
        candidate = os.path.join(images_dir, "hook.png")
    elif scene_type == "outro":
        candidate = os.path.join(images_dir, "outro.png")
    else:
        image_index = int(scene.get("image_index", scene_number))
        candidate = os.path.join(images_dir, f"scene_{image_index + 1}.png")
    return candidate if os.path.isfile(candidate) and os.path.getsize(candidate) > 0 else None


def compose_video(
    scenes: list,
    images_dir: str,
    audio_path: str,
    srt_path: str,
    output_path: str,
    subtitle_style: dict | None = None,
    enable_effects: bool = True,
    bgm_path: str | None = None,
    bgm_volume_db: float = -22.0,
    *,
    width: int = 1080,
    height: int = 1920,
    fps: int = 30,
) -> bool:
    """Render all visuals, audio and subtitles in one FFmpeg encode."""

    if not scenes:
        print("Video birleştirme hatası: sahne listesi boş")
        return False
    if not os.path.isfile(audio_path):
        print(f"Video birleştirme hatası: ses dosyası yok: {audio_path}")
        return False

    width = even_dimension(width)
    height = even_dimension(height)
    fps = max(1, min(60, int(fps)))
    audio_duration = get_media_duration(audio_path, fallback=sum(float(s.get("duration", 0)) for s in scenes))
    output_dir = os.path.dirname(output_path)
    os.makedirs(output_dir, exist_ok=True)
    part_path = f"{output_path}.part"

    command = [find_ffmpeg(), "-hide_banner", "-loglevel", "error", "-y", "-nostdin"]
    normalized_scenes: list[dict] = []

    for index, original in enumerate(scenes):
        scene = dict(original)
        scene["duration"] = max(0.05, float(scene.get("duration", 6)))
        if not enable_effects:
            scene["effect"] = "static"
        image_path = _scene_image_path(scene, index, images_dir)
        if image_path:
            command.extend(
                [
                    "-loop", "1", "-framerate", str(fps),
                    "-t", f"{scene['duration']:.6f}", "-i", image_path,
                ]
            )
        else:
            command.extend(
                [
                    "-f",
                    "lavfi",
                    "-t",
                    f"{scene['duration']:.6f}",
                    "-i",
                    f"color=c=0x172033:s={width}x{height}:r={fps}:d={scene['duration']:.6f}",
                ]
            )
        normalized_scenes.append(scene)

    audio_input_index = len(normalized_scenes)
    command.extend(["-i", audio_path])

    bgm_input_index: int | None = None
    if bgm_path and os.path.isfile(bgm_path):
        bgm_input_index = audio_input_index + 1
        command.extend(["-i", bgm_path])

    graph = build_filter_graph(
        scenes=normalized_scenes,
        width=width,
        height=height,
        fps=fps,
        audio_input_index=audio_input_index,
        audio_duration=audio_duration,
        subtitle_path=srt_path,
        subtitle_style=build_subtitle_style(subtitle_style),
        bgm_input_index=bgm_input_index,
        bgm_volume_db=bgm_volume_db,
    )

    graph_path: str | None = None
    try:
        with tempfile.NamedTemporaryFile(
            mode="w",
            encoding="utf-8",
            suffix=".ffgraph",
            prefix="videokur_",
            dir=output_dir,
            delete=False,
        ) as graph_file:
            graph_file.write(graph.text)
            graph_path = graph_file.name

        preset = os.environ.get("VIDEO_PRESET", "veryfast")
        crf = str(max(0, min(51, int(os.environ.get("VIDEO_CRF", "21")))))
        threads = str(max(1, int(os.environ.get("VIDEO_THREADS", "4"))))
        encoder = os.environ.get("VIDEO_ENCODER", "libx264")
        timeout = float(os.environ.get("VIDEO_RENDER_TIMEOUT", "900"))

        command.extend(
            [
                "-filter_complex_script",
                graph_path,
                "-map",
                f"[{graph.video_label}]",
                "-map",
                f"[{graph.audio_label}]",
                "-t",
                f"{audio_duration:.6f}",
                "-c:v",
                encoder,
                "-preset",
                preset,
                "-crf",
                crf,
                "-threads",
                threads,
                "-r",
                str(fps),
                "-pix_fmt",
                "yuv420p",
                "-c:a",
                "aac",
                "-b:a",
                "128k",
                "-ar",
                "48000",
                "-ac",
                "2",
                "-movflags",
                "+faststart",
                "-progress",
                "pipe:1",
                "-nostats",
                "-f",
                "mp4",
                part_path,
            ]
        )

        def report_progress(progress: dict[str, str]) -> None:
            if progress.get("progress") == "continue":
                print(
                    "  [FFmpeg] "
                    f"%{progress.get('percent', '0')} "
                    f"fps={progress.get('fps', '0')} "
                    f"speed={progress.get('speed', '0x')}"
                )

        print(
            f"  [FFmpeg] Tek geçişli render: {width}x{height} {fps} FPS, "
            f"{encoder}/{preset}, {audio_duration:.2f}s"
        )
        result = run_ffmpeg(
            command,
            expected_duration=audio_duration,
            timeout=timeout,
            on_progress=report_progress,
        )
        if result.returncode != 0:
            print(f"Video birleştirme hatası: {result.stderr.strip()[-2000:]}")
            return False

        valid, validation = validate_render(
            part_path,
            width=width,
            height=height,
            fps=fps,
            expected_duration=audio_duration,
        )
        if not valid:
            print(f"Video doğrulama hatası: {validation.get('issues') or validation.get('error')}")
            return False

        os.replace(part_path, output_path)
        realtime_factor = result.elapsed_seconds / audio_duration if audio_duration else 0
        print(
            f"  [FFmpeg] Render tamamlandı: {result.elapsed_seconds:.1f}s, "
            f"realtime factor={realtime_factor:.2f}"
        )
        return True
    except Exception as exc:
        print(f"Video birleştirme hatası: {exc}")
        return False
    finally:
        if graph_path and os.path.exists(graph_path):
            try:
                os.remove(graph_path)
            except OSError:
                pass
        if os.path.exists(part_path):
            try:
                os.remove(part_path)
            except OSError:
                pass
