"""Safe FFmpeg process execution with machine-readable progress."""

from __future__ import annotations

import os
import queue
import subprocess
import tempfile
import threading
import time
from dataclasses import dataclass
from typing import Callable


@dataclass
class FFmpegResult:
    returncode: int
    elapsed_seconds: float
    stderr: str
    progress: dict[str, str]


def run_ffmpeg(
    command: list[str],
    *,
    expected_duration: float = 0,
    timeout: float | None = None,
    on_progress: Callable[[dict[str, str]], None] | None = None,
) -> FFmpegResult:
    """Run FFmpeg without a shell and parse ``-progress pipe:1`` output."""

    started = time.monotonic()
    latest: dict[str, str] = {}
    stderr_file = tempfile.TemporaryFile(mode="w+t", encoding="utf-8", errors="replace")
    try:
        process = subprocess.Popen(
            command,
            stdin=subprocess.DEVNULL,
            stdout=subprocess.PIPE,
            stderr=stderr_file,
            text=True,
            encoding="utf-8",
            errors="replace",
            bufsize=1,
            creationflags=subprocess.CREATE_NEW_PROCESS_GROUP if os.name == "nt" else 0,
        )
    except BaseException:
        stderr_file.close()
        raise

    assert process.stdout is not None
    lines: queue.Queue[str | None] = queue.Queue()

    def read_progress() -> None:
        try:
            for line in process.stdout:
                lines.put(line)
        finally:
            lines.put(None)

    reader = threading.Thread(target=read_progress, name="ffmpeg-progress", daemon=True)
    reader.start()

    try:
        while True:
            if timeout and time.monotonic() - started > timeout:
                process.kill()
                raise TimeoutError(f"FFmpeg {timeout:.0f} saniye içinde tamamlanmadı")
            try:
                raw_line = lines.get(timeout=0.25)
            except queue.Empty:
                if process.poll() is not None and not reader.is_alive():
                    break
                continue
            if raw_line is None:
                break
            line = raw_line.strip()
            if "=" not in line:
                continue
            key, value = line.split("=", 1)
            latest[key] = value
            if key == "progress" and on_progress:
                payload = dict(latest)
                out_time = float(payload.get("out_time_us", "0") or 0) / 1_000_000
                payload["percent"] = (
                    f"{min(100.0, out_time / expected_duration * 100):.2f}"
                    if expected_duration > 0
                    else "0"
                )
                on_progress(payload)
        returncode = process.wait()
    except BaseException:
        if process.poll() is None:
            process.kill()
            process.wait()
        raise
    finally:
        reader.join(timeout=1)
        process.stdout.close()

    stderr_file.seek(0)
    stderr = stderr_file.read()
    stderr_file.close()
    return FFmpegResult(
        returncode=returncode,
        elapsed_seconds=time.monotonic() - started,
        stderr=stderr,
        progress=latest,
    )