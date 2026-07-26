"""Build a single-pass FFmpeg filter graph for VideoKur scenes."""

from __future__ import annotations

import math
import os
from dataclasses import dataclass


@dataclass(frozen=True)
class GraphResult:
    text: str
    video_label: str
    audio_label: str | None


def even_dimension(value: int, minimum: int = 360, maximum: int = 4096) -> int:
    value = max(minimum, min(maximum, int(value)))
    return value if value % 2 == 0 else value - 1


def ffmpeg_filter_path(path: str) -> str:
    """Escape a filename for use inside an FFmpeg filter option."""

    normalized = os.path.abspath(path).replace("\\", "/")
    return (
        normalized.replace("\\", "\\\\")
        .replace(":", "\\:")
        .replace("'", "\\'")
        .replace("[", "\\[")
        .replace("]", "\\]")
        .replace(",", "\\,")
        .replace(";", "\\;")
    )


def _num(value: float) -> str:
    return f"{max(0.001, float(value)):.6f}".rstrip("0").rstrip(".")


def _zoompan(
    *,
    width: int,
    height: int,
    fps: int,
    frames: int,
    zoom_expr: str,
    x_expr: str = "iw/2-(iw/zoom/2)",
    y_expr: str = "ih/2-(ih/zoom/2)",
) -> str:
    buffer_width = even_dimension(math.ceil(width * 1.35), minimum=width)
    buffer_height = even_dimension(math.ceil(height * 1.35), minimum=height)
    return (
        f"scale={buffer_width}:{buffer_height}:force_original_aspect_ratio=increase,"
        f"crop={buffer_width}:{buffer_height},"
        f"zoompan=z='{zoom_expr}':x='{x_expr}':y='{y_expr}':"
        f"d=1:s={width}x{height}:fps={fps},"
        f"setsar=1,trim=end_frame={frames},setpts=PTS-STARTPTS"
    )


def scene_filter(effect: str, duration: float, width: int, height: int, fps: int) -> str:
    frames = max(1, int(round(duration * fps)))
    last = max(1, frames - 1)
    progress = f"on/{last}"
    effect = (effect or "ken_burns_zoom_in").strip().lower()

    if effect == "static":
        return (
            f"scale={width}:{height}:force_original_aspect_ratio=increase,"
            f"crop={width}:{height},setsar=1,fps={fps},"
            f"setsar=1,trim=end_frame={frames},setpts=PTS-STARTPTS"
        )
    if effect in {"ken_burns_zoom_in", "cinematic_push"}:
        amount = "0.16" if effect == "cinematic_push" else "0.15"
        base = "1.02" if effect == "cinematic_push" else "1.0"
        return _zoompan(
            width=width,
            height=height,
            fps=fps,
            frames=frames,
            zoom_expr=f"{base}+{amount}*{progress}",
        )
    if effect == "ken_burns_zoom_out":
        return _zoompan(
            width=width,
            height=height,
            fps=fps,
            frames=frames,
            zoom_expr=f"1.15-0.15*{progress}",
        )
    if effect == "zoom_in_fast":
        return _zoompan(
            width=width,
            height=height,
            fps=fps,
            frames=frames,
            zoom_expr=f"1.0+0.25*{progress}",
        )
    if effect == "zoom_out_fast":
        return _zoompan(
            width=width,
            height=height,
            fps=fps,
            frames=frames,
            zoom_expr=f"1.25-0.25*{progress}",
        )
    if effect in {"pulse", "pulse_strong", "micro_zoom_jitter"}:
        if effect == "pulse_strong":
            zoom = f"1.10+0.10*sin(2*PI*3*{progress})"
        elif effect == "micro_zoom_jitter":
            zoom = f"1.04+0.02*sin(2*PI*5*{progress})"
        else:
            zoom = f"1.05+0.05*sin(2*PI*2*{progress})"
        return _zoompan(
            width=width,
            height=height,
            fps=fps,
            frames=frames,
            zoom_expr=zoom,
        )
    if effect in {"pan_left", "pan_right", "drift_left_right", "tilt_pan"}:
        buffer_width = even_dimension(math.ceil(width * 1.40), minimum=width)
        buffer_height = even_dimension(math.ceil(height * 1.40), minimum=height)
        duration_text = _num(duration)
        if effect == "pan_left":
            x_expr = f"(in_w-out_w)*(1-min(t/{duration_text},1))"
            y_expr = "(in_h-out_h)/2"
        elif effect == "pan_right":
            x_expr = f"(in_w-out_w)*min(t/{duration_text},1)"
            y_expr = "(in_h-out_h)/2"
        elif effect == "drift_left_right":
            x_expr = f"(in_w-out_w)*(0.5+0.35*sin(2*PI*t/{duration_text}))"
            y_expr = "(in_h-out_h)/2"
        else:
            x_expr = f"(in_w-out_w)*0.8*min(t/{duration_text},1)"
            y_expr = f"(in_h-out_h)*(0.5+0.15*sin(2*PI*t/{duration_text}))"
        return (
            f"scale={buffer_width}:{buffer_height}:force_original_aspect_ratio=increase,"
            f"crop={buffer_width}:{buffer_height},"
            f"crop={width}:{height}:x='{x_expr}':y='{y_expr}',"
            f"setsar=1,fps={fps},trim=end_frame={frames},setpts=PTS-STARTPTS"
        )
    if effect == "glitch_transition":
        shake_x = "if(or(lt(on,6),gte(on,N-6)),6*sin(on*3),0)"
        shake_y = "if(or(lt(on,6),gte(on,N-6)),3*cos(on*4),0)"
        return _zoompan(
            width=width,
            height=height,
            fps=fps,
            frames=frames,
            zoom_expr="1.08",
            x_expr="iw/2-(iw/zoom/2)+6*sin(on*3)",
            y_expr="ih/2-(ih/zoom/2)+3*cos(on*4)",
        )

    return scene_filter("ken_burns_zoom_in", duration, width, height, fps)


def build_filter_graph(
    *,
    scenes: list[dict],
    width: int,
    height: int,
    fps: int,
    audio_input_index: int,
    audio_duration: float,
    subtitle_path: str | None,
    subtitle_style: str | None,
    bgm_input_index: int | None = None,
    bgm_volume_db: float = -22.0,
) -> GraphResult:
    lines: list[str] = []
    scene_labels: list[str] = []

    for index, scene in enumerate(scenes):
        label = f"scene{index}"
        lines.append(
            f"[{index}:v]{scene_filter(scene.get('effect', ''), scene['duration'], width, height, fps)}"
            f"[{label}]"
        )
        scene_labels.append(f"[{label}]")

    concat_label = "video_concat"
    lines.append(f"{''.join(scene_labels)}concat=n={len(scene_labels)}:v=1:a=0[{concat_label}]")

    current_video = concat_label
    if subtitle_path and os.path.isfile(subtitle_path) and os.path.getsize(subtitle_path) > 0:
        subtitle_label = "video_subtitled"
        subtitle_filter = f"subtitles=filename='{ffmpeg_filter_path(subtitle_path)}'"
        if subtitle_style:
            escaped_style = subtitle_style.replace("\\", "\\\\").replace("'", "\\'")
            subtitle_filter += f":force_style='{escaped_style}'"
        lines.append(f"[{current_video}]{subtitle_filter}[{subtitle_label}]")
        current_video = subtitle_label

    final_video = "video_out"
    lines.append(
        f"[{current_video}]trim=duration={_num(audio_duration)},"
        f"tpad=stop_mode=clone:stop_duration={_num(audio_duration)},trim=duration={_num(audio_duration)},"
        f"setpts=PTS-STARTPTS,scale=in_range=full:out_range=tv,format=yuv420p[{final_video}]"
    )

    voice_label = "voice"
    lines.append(
        f"[{audio_input_index}:a]aresample=48000:async=1:first_pts=0,"
        f"atrim=duration={_num(audio_duration)},asetpts=PTS-STARTPTS[{voice_label}]"
    )

    audio_label = voice_label
    if bgm_input_index is not None:
        music_label = "music"
        mixed_label = "audio_out"
        lines.append(
            f"[{bgm_input_index}:a]aloop=loop=-1:size=2147483647,"
            f"atrim=duration={_num(audio_duration)},asetpts=PTS-STARTPTS,"
            f"volume={float(bgm_volume_db):.2f}dB[{music_label}]"
        )
        lines.append(
            f"[{voice_label}][{music_label}]amix=inputs=2:duration=first:dropout_transition=0,"
            f"alimiter=limit=0.95[{mixed_label}]"
        )
        audio_label = mixed_label

    return GraphResult(
        text=";\n".join(lines) + "\n",
        video_label=final_video,
        audio_label=audio_label,
    )
