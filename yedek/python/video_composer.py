import os
import subprocess
import numpy as np
import random
import math
from moviepy import (
    ImageClip, AudioFileClip,
    CompositeVideoClip, concatenate_videoclips
)
from moviepy.config import FFMPEG_BINARY

WIDTH = 1080
HEIGHT = 1920
FPS = 30


from PIL import Image as PILImage


def apply_zoom_crop_effect(clip, scale_func, duration, target_w, target_h):
    """
    Zoom efektlerini siyah kenar olmadan uygular.
    Görsel önce büyütülür, sonra her frame'de dinamik crop yapılır.
    """
    # Maksimum zoom için buffer (tüm zoom efektlerini karşılar)
    scale_buffer = 1.35
    enlarged_w = int(target_w * scale_buffer)
    enlarged_h = int(target_h * scale_buffer)
    
    # Büyütülmüş clip oluştur
    enlarged = clip.resized((enlarged_w, enlarged_h))
    
    def make_frame(t):
        # O anki zoom seviyesi
        current_scale = max(scale_func(t), 1.0)
        
        # Crop boyutları (zoom ne kadar büyükse, crop o kadar küçük)
        crop_w = int(target_w * scale_buffer / current_scale)
        crop_h = int(target_h * scale_buffer / current_scale)
        crop_w = max(1, min(crop_w, enlarged_w))
        crop_h = max(1, min(crop_h, enlarged_h))
        
        # Merkez noktasından kırp
        x1 = (enlarged_w - crop_w) // 2
        y1 = (enlarged_h - crop_h) // 2
        x1 = max(0, min(x1, enlarged_w - crop_w))
        y1 = max(0, min(y1, enlarged_h - crop_h))
        x2 = min(x1 + crop_w, enlarged_w)
        y2 = min(y1 + crop_h, enlarged_h)
        
        # Frame al ve kırp
        frame = enlarged.get_frame(t)
        cropped_frame = frame[y1:y2, x1:x2]
        if cropped_frame.size == 0:
            cropped_frame = frame
        
        # Hedef boyuta resize (PIL kullanarak kaliteli)
        img = PILImage.fromarray(cropped_frame)
        img = img.resize((target_w, target_h), PILImage.LANCZOS)
        return np.array(img)
    
    # Yeni clip oluştur
    from moviepy import VideoClip
    new_clip = VideoClip(make_frame, duration=duration)
    new_clip = new_clip.with_fps(FPS)
    return new_clip


def apply_pan_effect(clip, direction, duration, target_w, target_h):
    """
    Pan efektlerini siyah kenar olmadan uygular.
    """
    # Pan için buffer
    scale_buffer = 1.4
    enlarged_w = int(target_w * scale_buffer)
    enlarged_h = int(target_h * scale_buffer)
    
    enlarged = clip.resized((enlarged_w, enlarged_h))
    
    def make_frame(t):
        progress = t / duration
        
        # Yatay hareket alanı
        max_x_offset = enlarged_w - target_w
        # Dikey merkezleme
        y_offset = (enlarged_h - target_h) // 2
        
        if direction == 'left':
            # Sağdan sola (sağdan başla, sola git)
            x_offset = int(max_x_offset * (1 - progress))
        else:  # right
            # Soldan sağa
            x_offset = int(max_x_offset * progress)
        
        frame = enlarged.get_frame(t)
        cropped_frame = frame[y_offset:y_offset+target_h, x_offset:x_offset+target_w]
        return cropped_frame
    
    from moviepy import VideoClip
    new_clip = VideoClip(make_frame, duration=duration)
    new_clip = new_clip.with_fps(FPS)
    return new_clip


def apply_video_effect(clip, effect_type='ken_burns_zoom_in'):
    """
    Gelişmiş video efektleri uygular.
    Siyah kenar oluşmasını önlemek için önce büyütüp sonra kırpar.
    
    Efekt tipleri:
    - ken_burns_zoom_in: Yavaş zoom in + hafif pan
    - ken_burns_zoom_out: Yavaş zoom out + hafif pan
    - zoom_in_fast: Hızlı zoom in
    - zoom_out_fast: Hızlı zoom out
    - pulse: Hafif nabız efekti
    - pulse_strong: Güçlü nabız efekti
    - pan_left: Sola kaydırma
    - pan_right: Sağa kaydırma
    - static: Hareketsiz
    - glitch_transition: Glitch geçiş efekti
    """
    duration = clip.duration
    base_w, base_h = clip.w, clip.h
    
    if effect_type == 'ken_burns_zoom_in':
        # Yavaş zoom in (1.0 → 1.15)
        return apply_zoom_crop_effect(
            clip, 
            lambda t: 1.0 + 0.15 * (t / duration),
            duration, base_w, base_h
        )
    
    elif effect_type == 'ken_burns_zoom_out':
        # Yavaş zoom out (1.15 → 1.0)
        return apply_zoom_crop_effect(
            clip,
            lambda t: 1.15 - 0.15 * (t / duration),
            duration, base_w, base_h
        )
    
    elif effect_type == 'zoom_in_fast':
        # Hızlı zoom in (1.0 → 1.25)
        return apply_zoom_crop_effect(
            clip,
            lambda t: 1.0 + 0.25 * (t / duration),
            duration, base_w, base_h
        )
    
    elif effect_type == 'zoom_out_fast':
        # Hızlı zoom out (1.25 → 1.0)
        return apply_zoom_crop_effect(
            clip,
            lambda t: 1.25 - 0.25 * (t / duration),
            duration, base_w, base_h
        )
    
    elif effect_type == 'pulse':
        # Hafif nabız efekti (1.0 ↔ 1.1)
        return apply_zoom_crop_effect(
            clip,
            lambda t: 1.05 + 0.05 * math.sin(2 * math.pi * t / duration * 2),
            duration, base_w, base_h
        )
    
    elif effect_type == 'pulse_strong':
        # Güçlü nabız efekti (1.0 ↔ 1.2)
        return apply_zoom_crop_effect(
            clip,
            lambda t: 1.1 + 0.1 * math.sin(2 * math.pi * t / duration * 3),
            duration, base_w, base_h
        )
    
    elif effect_type == 'pan_left':
        return apply_pan_effect(clip, 'left', duration, base_w, base_h)
    
    elif effect_type == 'pan_right':
        return apply_pan_effect(clip, 'right', duration, base_w, base_h)
    
    elif effect_type == 'static':
        # Hareketsiz - hiçbir efekt yok
        return clip
    
    elif effect_type == 'glitch_transition':
        # Glitch efekti - büyütülmüş görsel üzerinde shake (siyah kenarsız)
        scale_buffer = 1.2
        enlarged_w = int(base_w * scale_buffer)
        enlarged_h = int(base_h * scale_buffer)
        enlarged = clip.resized((enlarged_w, enlarged_h))
        
        def make_frame(t):
            if t < 0.2 or t > duration - 0.2:
                shake_x = random.randint(-10, 10)
                shake_y = random.randint(-5, 5)
            else:
                shake_x, shake_y = 0, 0
            
            # Merkez noktasından shake offset'i uygula
            x_offset = (enlarged_w - base_w) // 2 + shake_x
            y_offset = (enlarged_h - base_h) // 2 + shake_y
            
            # Sınır kontrolü
            x_offset = max(0, min(x_offset, enlarged_w - base_w))
            y_offset = max(0, min(y_offset, enlarged_h - base_h))
            
            frame = enlarged.get_frame(t)
            cropped_frame = frame[y_offset:y_offset+base_h, x_offset:x_offset+base_w]
            return cropped_frame
        
        from moviepy import VideoClip
        new_clip = VideoClip(make_frame, duration=duration)
        new_clip = new_clip.with_fps(FPS)
        return new_clip

    elif effect_type == 'drift_left_right':
        scale_buffer = 1.22
        enlarged_w = int(base_w * scale_buffer)
        enlarged_h = int(base_h * scale_buffer)
        enlarged = clip.resized((enlarged_w, enlarged_h))

        def make_frame(t):
            progress = t / duration
            x_sway = math.sin(2 * math.pi * progress) * (enlarged_w - base_w) * 0.35
            x_offset = int((enlarged_w - base_w) / 2 + x_sway)
            y_offset = (enlarged_h - base_h) // 2
            x_offset = max(0, min(x_offset, enlarged_w - base_w))
            frame = enlarged.get_frame(t)
            return frame[y_offset:y_offset+base_h, x_offset:x_offset+base_w]

        from moviepy import VideoClip
        return VideoClip(make_frame, duration=duration).with_fps(FPS)

    elif effect_type == 'micro_zoom_jitter':
        return apply_zoom_crop_effect(
            clip,
            lambda t: 1.04 + 0.02 * math.sin(2 * math.pi * t / duration * 5),
            duration, base_w, base_h
        )

    elif effect_type == 'tilt_pan':
        scale_buffer = 1.28
        enlarged_w = int(base_w * scale_buffer)
        enlarged_h = int(base_h * scale_buffer)
        enlarged = clip.resized((enlarged_w, enlarged_h))

        def make_frame(t):
            progress = t / duration
            x_offset = int((enlarged_w - base_w) * progress * 0.8)
            y_wave = int(math.sin(progress * 2 * math.pi) * (enlarged_h - base_h) * 0.15)
            y_offset = int((enlarged_h - base_h) / 2 + y_wave)
            x_offset = max(0, min(x_offset, enlarged_w - base_w))
            y_offset = max(0, min(y_offset, enlarged_h - base_h))
            frame = enlarged.get_frame(t)
            return frame[y_offset:y_offset+base_h, x_offset:x_offset+base_w]

        from moviepy import VideoClip
        return VideoClip(make_frame, duration=duration).with_fps(FPS)

    elif effect_type == 'cinematic_push':
        return apply_zoom_crop_effect(
            clip,
            lambda t: 1.02 + 0.16 * (t / duration),
            duration, base_w, base_h
        )
    
    # Fallback: varsayılan ken_burns zoom in (siyah kenarsız)
    return apply_zoom_crop_effect(
        clip,
        lambda t: 1.0 + 0.1 * (t / duration),
        duration, base_w, base_h
    )


def create_effect_clip(img_path, duration, effect_type='ken_burns_zoom_in'):
    """Görsel yükler ve belirtilen efekti uygular."""
    try:
        img_clip = ImageClip(img_path, duration=duration).resized((WIDTH, HEIGHT))
        return apply_video_effect(img_clip, effect_type)
    except Exception as e:
        print(f"  [WARN] Video efekti uygulanamadı: {e}, statik görsel kullanılıyor")
        return ImageClip(img_path, duration=duration).resized((WIDTH, HEIGHT))


# ── Subtitle style presets ────────────────────────────────────────────────────
SUBTITLE_PRESETS = {
    'classic': {
        'FontName': 'Arial', 'FontSize': 20,
        'PrimaryColour': '&H00FFFFFF', 'OutlineColour': '&H00000000',
        'BorderStyle': 3, 'Outline': 2, 'Shadow': 0,
        'MarginV': 80, 'MarginL': 40, 'MarginR': 40, 'Alignment': 2, 'Bold': 0,
    },
    'bold_bottom': {
        'FontName': 'Arial', 'FontSize': 24,
        'PrimaryColour': '&H00FFFFFF', 'OutlineColour': '&H00000000',
        'BorderStyle': 3, 'Outline': 3, 'Shadow': 1,
        'MarginV': 100, 'MarginL': 40, 'MarginR': 40, 'Alignment': 2, 'Bold': 1,
    },
    'yellow_bold': {
        'FontName': 'Arial', 'FontSize': 22,
        'PrimaryColour': '&H0000FFFF', 'OutlineColour': '&H00000000',
        'BorderStyle': 1, 'Outline': 2, 'Shadow': 1,
        'MarginV': 80, 'MarginL': 40, 'MarginR': 40, 'Alignment': 2, 'Bold': 1,
    },
    'box_white': {
        'FontName': 'Arial', 'FontSize': 20,
        'PrimaryColour': '&H00000000', 'OutlineColour': '&H00FFFFFF',
        'BackColour': '&H80000000', 'BorderStyle': 4, 'Outline': 0, 'Shadow': 0,
        'MarginV': 80, 'MarginL': 40, 'MarginR': 40, 'Alignment': 2, 'Bold': 0,
    },
    'tiktok': {
        'FontName': 'Arial', 'FontSize': 26,
        'PrimaryColour': '&H00FFFFFF', 'OutlineColour': '&H000000FF',
        'BorderStyle': 3, 'Outline': 3, 'Shadow': 0,
        'MarginV': 120, 'MarginL': 40, 'MarginR': 40, 'Alignment': 2, 'Bold': 1,
    },
    'minimal': {
        'FontName': 'Arial', 'FontSize': 18,
        'PrimaryColour': '&H00FFFFFF', 'OutlineColour': '&H00000000',
        'BorderStyle': 1, 'Outline': 1, 'Shadow': 0,
        'MarginV': 60, 'MarginL': 40, 'MarginR': 40, 'Alignment': 2, 'Bold': 0,
    },
}


def _hex_to_ass(hex_color: str) -> str:
    """#RRGGBB → &H00BBGGRR (ASS/SSA format for ffmpeg)."""
    if not hex_color or not hex_color.startswith('#'):
        return hex_color  # already in &H format or unknown
    h = hex_color.lstrip('#')
    if len(h) == 6:
        r, g, b = h[0:2], h[2:4], h[4:6]
        return f"&H00{b}{g}{r}".upper()
    return hex_color


def build_subtitle_style(style_params: dict) -> str:
    """style_params dict'ini ffmpeg force_style string'ine çevirir."""
    color_keys = {'PrimaryColour', 'OutlineColour', 'BackColour'}
    parts = []
    key_map = {
        'FontName': 'FontName', 'FontSize': 'FontSize',
        'PrimaryColour': 'PrimaryColour', 'OutlineColour': 'OutlineColour',
        'BackColour': 'BackColour', 'BorderStyle': 'BorderStyle',
        'Outline': 'Outline', 'Shadow': 'Shadow',
        'MarginV': 'MarginV', 'MarginL': 'MarginL', 'MarginR': 'MarginR', 'Alignment': 'Alignment', 'Bold': 'Bold',
    }
    for k, v in style_params.items():
        if k in key_map and v is not None:
            if k in color_keys:
                v = _hex_to_ass(str(v))
            parts.append(f"{key_map[k]}={v}")
    
    # Debug: stil bilgisini yazdır
    style_str = ','.join(parts)
    print(f"  [Altyazı stili] {style_str}")
    return style_str


def compose_video(scenes: list, images_dir: str, audio_path: str, srt_path: str,
                  output_path: str, subtitle_style: dict = None, enable_effects: bool = True) -> bool:
    """Görseller, ses ve altyazıdan Short video oluşturur. scenes artık hook/outro dahil tüm segmentleri içerir."""
    try:
        audio = AudioFileClip(audio_path)
        clips = []

        # Efekt çeşitliliği için fallback liste (script'te belirtilmemişse)
        fallback_effects = ['ken_burns_zoom_in', 'ken_burns_zoom_out', 'pan_right', 'pan_left', 'drift_left_right', 'cinematic_push']

        for i, scene in enumerate(scenes):
            duration = scene.get('duration', 6)
            seg_type = scene.get('type', 'scene')
            
            # Script'ten gelen effect'i kullan (artık 'effect' parametresi, 'camera_effect' değil)
            effect_type = scene.get('effect', scene.get('camera_effect', fallback_effects[i % len(fallback_effects)])) if enable_effects else 'static'

            if seg_type == 'scene':
                img_idx = scene.get('image_index', i)
                img_path = os.path.join(images_dir, f"scene_{img_idx+1}.png")
                if os.path.exists(img_path):
                    if enable_effects:
                        img_clip = create_effect_clip(img_path, duration, effect_type)
                        print(f"  [Efekt] Sahne {img_idx+1}: {effect_type} {'(AI seçti)' if 'effect' in scene or 'camera_effect' in scene else '(varsayılan)'}")
                    else:
                        img_clip = ImageClip(img_path, duration=duration).resized((WIDTH, HEIGHT))
                else:
                    img_clip = ImageClip(create_gradient_bg(), duration=duration).resized((WIDTH, HEIGHT))
            elif seg_type == 'hook':
                # Hook için hook.png kullan, yoksa gradient
                hook_img_path = os.path.join(images_dir, 'hook.png')
                hook_effect = scene.get('effect', scene.get('camera_effect', 'zoom_in_fast'))
                if os.path.exists(hook_img_path):
                    if enable_effects:
                        img_clip = create_effect_clip(hook_img_path, duration, hook_effect)
                        print(f"  [Efekt] Hook: {hook_effect} {'(AI seçti)' if 'effect' in scene or 'camera_effect' in scene else '(varsayılan)'}")
                    else:
                        img_clip = ImageClip(hook_img_path, duration=duration).resized((WIDTH, HEIGHT))
                else:
                    img_clip = ImageClip(create_gradient_bg(), duration=duration).resized((WIDTH, HEIGHT))
            elif seg_type == 'outro':
                # Outro için outro.png kullan, yoksa gradient
                outro_img_path = os.path.join(images_dir, 'outro.png')
                outro_effect = scene.get('effect', scene.get('camera_effect', 'pulse_strong'))
                if os.path.exists(outro_img_path):
                    if enable_effects:
                        img_clip = create_effect_clip(outro_img_path, duration, outro_effect)
                        print(f"  [Efekt] Outro: {outro_effect} {'(AI seçti)' if 'effect' in scene or 'camera_effect' in scene else '(varsayılan)'}")
                    else:
                        img_clip = ImageClip(outro_img_path, duration=duration).resized((WIDTH, HEIGHT))
                else:
                    img_clip = ImageClip(create_gradient_bg(), duration=duration).resized((WIDTH, HEIGHT))
            else:
                # Bilinmeyen tip için gradient arka plan
                img_clip = ImageClip(create_gradient_bg(), duration=duration).resized((WIDTH, HEIGHT))

            clips.append(img_clip)

        final = concatenate_videoclips(clips, method='compose')

        # Video süresini ses süresine eşitle
        if abs(final.duration - audio.duration) > 0.5:
            if audio.duration < final.duration:
                final = final.subclipped(0, audio.duration)
        final = final.with_audio(audio)

        # Önce geçici video oluştur
        temp_path = output_path.replace('.mp4', '_temp.mp4')
        final.write_videofile(
            temp_path,
            fps=FPS,
            codec='libx264',
            audio_codec='aac',
            preset='medium',
            threads=4
        )

        final.close()
        audio.close()

        # ffmpeg ile SRT altyazıları yak
        if os.path.exists(srt_path) and os.path.getsize(srt_path) > 0:
            # Stil belirle
            if subtitle_style is None:
                subtitle_style = SUBTITLE_PRESETS['classic']
            style_str = build_subtitle_style(subtitle_style)

            srt_escaped = srt_path.replace('\\', '/').replace(':', '\\:')
            subtitle_filter = f"subtitles='{srt_escaped}':force_style='{style_str}'"
            cmd = [
                FFMPEG_BINARY, '-y',
                '-i', temp_path,
                '-vf', subtitle_filter,
                '-c:a', 'copy',
                '-c:v', 'libx264',
                '-preset', 'medium',
                output_path
            ]
            print(f"  Altyazılar ekleniyor (ffmpeg)...")
            result = subprocess.run(cmd, capture_output=True, text=True)
            if result.returncode == 0 and os.path.exists(output_path):
                os.remove(temp_path)
                print(f"  Altyazılar başarıyla eklendi.")
            else:
                print(f"  Altyazı ekleme hatası: {result.stderr[-300:] if result.stderr else 'Bilinmeyen hata'}")
                os.replace(temp_path, output_path)
        else:
            os.replace(temp_path, output_path)

        return True
    except Exception as e:
        print(f"Video birleştirme hatası: {e}")
        return False


def create_gradient_bg():
    """Basit gradient arka plan oluşturur."""
    img = np.zeros((HEIGHT, WIDTH, 3), dtype=np.uint8)
    for y in range(HEIGHT):
        ratio = y / HEIGHT
        img[y, :] = [int(20 + 30 * ratio), int(20 + 20 * ratio), int(40 + 60 * ratio)]
    return img


if __name__ == '__main__':
    print("Video composer modülü. Pipeline üzerinden kullanılır.")
