"""
Metadata CLI - Platform bazli SEO metadata uretici
Kullanim: python metadata_cli.py --job-id JOB_ID --platform youtube [--base-dir /path]

PHP ve diger istemciler tarafindan cagrilir.
Cikti: Tek satirlik JSON
"""
import sys
import os
import json
import argparse
from pathlib import Path

# Windows UTF-8 cikti
if sys.platform == 'win32':
    sys.stdout.reconfigure(encoding='utf-8', errors='replace')
    sys.stderr.reconfigure(encoding='utf-8', errors='replace')

# Python modullerini bul
SCRIPT_DIR = Path(__file__).parent
sys.path.insert(0, str(SCRIPT_DIR))


def load_config(base_dir: Path) -> dict:
    config_file = base_dir / 'data' / 'config.json'
    if config_file.exists():
        try:
            return json.loads(config_file.read_text(encoding='utf-8'))
        except Exception:
            pass
    return {}


def load_job(base_dir: Path, job_id: str) -> dict:
    """Job verisini yukle (duz veya klasor formatini destekler)"""
    # Duz format: data/jobs/job_id.json
    job_file = base_dir / 'data' / 'jobs' / f'{job_id}.json'
    if job_file.exists():
        try:
            return json.loads(job_file.read_text(encoding='utf-8'))
        except Exception:
            pass
    # Klasor format: data/jobs/job_id/job.json
    job_file = base_dir / 'data' / 'jobs' / job_id / 'job.json'
    if job_file.exists():
        try:
            return json.loads(job_file.read_text(encoding='utf-8'))
        except Exception:
            pass
    return {}


def load_script_text(base_dir: Path, job_id: str) -> str:
    """output/{job_id}/script.json'dan duz metin olustur"""
    script_file = base_dir / 'output' / job_id / 'script.json'
    if not script_file.exists():
        return ''
    try:
        script = json.loads(script_file.read_text(encoding='utf-8'))
        parts = []
        if script.get('hook'):
            parts.append(script['hook'])
        for scene in script.get('scenes', []):
            if scene.get('text'):
                parts.append(scene['text'])
        if script.get('outro'):
            parts.append(script['outro'])
        return ' '.join(parts)
    except Exception:
        return ''


def generate_youtube_metadata(title: str, script_text: str, gemini_key: str, model: str) -> dict:
    """YouTube icin title+description+tags uretir (MetadataOptimizer)"""
    try:
        from youtube.metadata_optimizer import MetadataOptimizer
        optimizer = MetadataOptimizer(gemini_key=gemini_key, model=model)
        result = optimizer.optimize_metadata(
            original_title=title,
            script_text=script_text,
            use_ai=bool(gemini_key)
        )
        return result
    except Exception as e:
        # Fallback: basit kural tabanli
        return {
            'title': _basic_title(title),
            'description': _basic_description(script_text, title),
            'tags': _basic_tags(title)
        }


def generate_social_metadata(platform: str, title: str, script_text: str,
                              gemini_key: str, model: str, base_tags: list = None) -> dict:
    """TikTok/Instagram/Facebook icin caption+hashtags uretir"""
    try:
        from social.platform_optimizer import PlatformMetadataOptimizer
        optimizer = PlatformMetadataOptimizer(gemini_key=gemini_key, model=model)
        result = optimizer.optimize_for_platform(
            platform=platform,
            original_title=title,
            script_text=script_text,
            base_tags=base_tags or [],
            use_ai=bool(gemini_key)
        )
        return result
    except Exception as e:
        return {
            'caption': f'{title}\n\n{script_text[:150]}',
            'hashtags': (base_tags or [])[:8],
            'hook': title[:100],
            'platform': platform
        }


# --- Basit fallback fonksiyonlari ---

def _basic_title(title: str) -> str:
    import re
    title = re.sub(r'[-_]+', ' ', title).strip().title()
    if not title:
        title = 'Video'
    emojis = ['🔥', '⚡', '📰', '🎯']
    has_emoji = any(ord(c) > 0x1F000 for c in title)
    if not has_emoji:
        title = f'{emojis[0]} {title}'
    if '?' not in title and '!' not in title:
        title += '!'
    return title[:100]


def _basic_description(script_text: str, title: str) -> str:
    summary = (script_text or title)[:200].strip()
    if len(script_text) > 200:
        summary += '...'
    return (
        f"{summary}\n\n"
        "🔔 Abone olmayı unutmayın!\n"
        "💬 Yorumlarınızı bekliyoruz!\n"
        "👍 Beğenmeyi unutmayın!\n\n"
        "#Shorts #Haber #Teknoloji"
    )


def _basic_tags(title: str) -> list:
    import re
    words = re.findall(r'\w+', title.lower())
    tags = ['Shorts', 'YouTubeShorts']
    for w in words:
        if len(w) > 3 and w not in tags:
            tags.append(w)
    tags += ['haber', 'teknoloji', 'gündem', 'türkçe']
    return list(dict.fromkeys(tags))[:20]


# --- Ana fonksiyon ---

def main():
    parser = argparse.ArgumentParser(
        description='Platform bazli SEO metadata uretici'
    )
    parser.add_argument('--job-id', required=True, help='Job ID')
    parser.add_argument(
        '--platform',
        default='youtube',
        choices=['youtube', 'tiktok', 'instagram', 'facebook', 'all'],
        help='Hedef platform (varsayilan: youtube)'
    )
    parser.add_argument(
        '--base-dir',
        default=str(SCRIPT_DIR.parent),
        help='Projenin kok dizini'
    )
    parser.add_argument('--title', default='', help='Manuel baslik (job.json basliği override eder)')
    parser.add_argument('--script', default='', help='Manuel script metni (script.json override eder)')

    args = parser.parse_args()

    base_dir = Path(args.base_dir)
    config   = load_config(base_dir)

    gemini_key = config.get('geminiKey', '')
    gemini_model = config.get('geminiModel', 'gemini-2.0-flash')

    # Job ve script metnini yükle
    job = load_job(base_dir, args.job_id)
    if not job:
        print(json.dumps({
            'success': False,
            'error': f'Job bulunamadi: {args.job_id}'
        }, ensure_ascii=False))
        sys.exit(1)

    title = args.title or job.get('title', 'Video')
    script_text = args.script or load_script_text(base_dir, args.job_id)

    if not script_text:
        # Son caredir: job description veya title
        script_text = job.get('description', title)

    # Platform/platformlari belirle
    if args.platform == 'all':
        platforms = ['youtube', 'tiktok', 'instagram', 'facebook']
    else:
        platforms = [args.platform]

    results = {}
    for platform in platforms:
        if platform == 'youtube':
            meta = generate_youtube_metadata(title, script_text, gemini_key, gemini_model)
        else:
            meta = generate_social_metadata(platform, title, script_text, gemini_key, gemini_model)
        results[platform] = meta

    # Tek platform istendiyse duz cikti ver, 'all' ise nested
    if args.platform == 'all':
        output = {
            'success': True,
            'job_id': args.job_id,
            'platforms': results
        }
    else:
        output = results[args.platform]
        output['success'] = True
        output['job_id'] = args.job_id
        output['platform'] = args.platform

    print(json.dumps(output, ensure_ascii=False))


if __name__ == '__main__':
    main()
