"""Small, dependency-free FFmpeg/FFprobe helpers."""

from __future__ import annotations

import json
import os
import shutil
import subprocess
from fractions import Fraction
from pathlib import Path
from typing import Any


def find_ffmpeg() -> str:
    configured = os.environ.get("FFMPEG_BIN")
    if configured and os.path.isfile(configured):
        return configured
    discovered = shutil.which("ffmpeg")
    if discovered:
        return discovered
    raise FileNotFoundError("FFmpeg bulunamadı. FFMPEG_BIN ayarlayın veya ffmpeg'i PATH'e ekleyin.")


def find_ffprobe() -> str:
    configured = os.environ.get("FFPROBE_BIN")
    if configured and os.path.isfile(configured):
        return configured

    ffmpeg_env = os.environ.get("FFMPEG_BIN")
    if ffmpeg_env:
        sibling_name = "ffprobe.exe" if os.name == "nt" else "ffprobe"
        sibling = os.path.join(os.path.dirname(ffmpeg_env), sibling_name)
        if os.path.isfile(sibling):
            return sibling

    discovered = shutil.which("ffprobe")
    if discovered:
        return discovered
    raise FileNotFoundError("FFprobe bulunamadı. FFPROBE_BIN ayarlayın veya ffprobe'u PATH'e ekleyin.")


def _rate_to_float(value: str | None) -> float:
    if not value or value in {"0/0", "N/A"}:
        return 0.0
    try:
        return float(Fraction(value))
    except (ValueError, ZeroDivisionError):
        return 0.0


def probe_media(path: str | os.PathLike[str]) -> dict[str, Any]:
    media_path = os.fspath(path)
    if not os.path.isfile(media_path):
        raise FileNotFoundError(media_path)

    command = [
        find_ffprobe(),
        "-v",
        "error",
        "-show_format",
        "-show_streams",
        "-of",
        "json",
        media_path,
    ]
    result = subprocess.run(command, capture_output=True, text=True, encoding="utf-8", errors="replace")
    if result.returncode != 0:
        detail = result.stderr.strip()[-1000:] or "bilinmeyen ffprobe hatası"
        raise RuntimeError(f"FFprobe başarısız: {detail}")

    data = json.loads(result.stdout or "{}")
    streams = data.get("streams", [])
    video = next((stream for stream in streams if stream.get("codec_type") == "video"), None)
    audio = next((stream for stream in streams if stream.get("codec_type") == "audio"), None)
    format_info = data.get("format", {})

    duration_value = format_info.get("duration")
    if duration_value in (None, "N/A"):
        duration_value = (video or audio or {}).get("duration", 0)

    return {
        "path": str(Path(media_path)),
        "duration": float(duration_value or 0),
        "size": int(format_info.get("size") or os.path.getsize(media_path)),
        "bit_rate": int(format_info.get("bit_rate") or 0),
        "format_name": format_info.get("format_name", ""),
        "video": {
            "codec_name": video.get("codec_name", ""),
            "width": int(video.get("width") or 0),
            "height": int(video.get("height") or 0),
            "pix_fmt": video.get("pix_fmt", ""),
            "frame_rate": _rate_to_float(video.get("avg_frame_rate") or video.get("r_frame_rate")),
        }
        if video
        else None,
        "audio": {
            "codec_name": audio.get("codec_name", ""),
            "sample_rate": int(audio.get("sample_rate") or 0),
            "channels": int(audio.get("channels") or 0),
        }
        if audio
        else None,
        "raw": data,
    }


def get_media_duration(path: str | os.PathLike[str], fallback: float | None = None) -> float:
    try:
        duration = probe_media(path)["duration"]
        if duration > 0:
            return duration
    except Exception:
        if fallback is None:
            raise
    if fallback is None:
        raise RuntimeError(f"Medya süresi okunamadı: {path}")
    return fallback


def validate_render(
    path: str | os.PathLike[str],
    *,
    width: int,
    height: int,
    fps: float,
    expected_duration: float,
    duration_tolerance: float = 0.20,
) -> tuple[bool, dict[str, Any]]:
    try:
        info = probe_media(path)
    except Exception as exc:
        return False, {"error": str(exc)}

    issues: list[str] = []
    video = info.get("video")
    audio = info.get("audio")

    if not video:
        issues.append("Video stream yok")
    else:
        if video["codec_name"] != "h264":
            issues.append(f"Video codec h264 değil: {video['codec_name']}")
        if video["width"] != width or video["height"] != height:
            issues.append(
                f"Çözünürlük yanlış: {video['width']}x{video['height']} (beklenen {width}x{height})"
            )
        if video["pix_fmt"] != "yuv420p":
            issues.append(f"Pixel format yuv420p değil: {video['pix_fmt']}")
        if abs(video["frame_rate"] - fps) > 0.01:
            issues.append(f"FPS yanlış: {video['frame_rate']} (beklenen {fps})")

    if not audio:
        issues.append("Audio stream yok")
    elif audio["codec_name"] != "aac":
        issues.append(f"Audio codec aac değil: {audio['codec_name']}")

    if abs(info["duration"] - expected_duration) > duration_tolerance:
        issues.append(
            f"Süre farkı yüksek: {info['duration']:.3f}s (beklenen {expected_duration:.3f}s)"
        )

    if info["size"] <= 0:
        issues.append("Çıktı dosyası boş")

    return not issues, {"info": info, "issues": issues}
