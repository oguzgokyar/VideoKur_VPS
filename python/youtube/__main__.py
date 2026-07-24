"""
YouTube uploader CLI module entry point
Usage: python -m youtube.uploader <video_path> <title> <description> [privacy] [category] [tags]
"""
import sys
from pathlib import Path
from .uploader import YouTubeUploader

def main():
    if len(sys.argv) < 4:
        print("Kullanım: python -m youtube.uploader <video_path> <title> <description> [privacy] [category] [tags] [thumbnail] [publish_at]")
        sys.exit(1)
    
    # Parse arguments
    video_path = sys.argv[1]
    title = sys.argv[2]
    description = sys.argv[3]
    privacy_status = sys.argv[4] if len(sys.argv) > 4 else 'public'
    category_id = sys.argv[5] if len(sys.argv) > 5 else '28'
    tags = sys.argv[6].split(',') if len(sys.argv) > 6 else ['#Shorts']
    thumbnail_path = sys.argv[7] if len(sys.argv) > 7 and sys.argv[7] != '' else None
    publish_at = sys.argv[8] if len(sys.argv) > 8 and sys.argv[8] != '' else None
    
    # Get credentials directory
    base_dir = Path(__file__).parent.parent.parent
    creds_dir = base_dir / 'data' / 'youtube_credentials'
    
    # Create uploader
    uploader = YouTubeUploader(str(creds_dir))
    
    # Upload video
    result = uploader.upload_video(
        video_path=video_path,
        title=title,
        description=description,
        tags=tags,
        category_id=category_id,
        privacy_status=privacy_status,
        notify_subscribers=(privacy_status == 'public'),
        thumbnail_path=thumbnail_path,
        publish_at=publish_at
    )
    
    # Output result in parseable format
    if result and result.get('status') == 'success':
        # PHP will parse these lines
        print(f"Video ID: {result['video_id']}")
        print(f"URL: {result['video_url']}")
        sys.exit(0)
    else:
        print(f"ERROR: {result.get('error', 'Unknown error')}")
        sys.exit(1)

if __name__ == '__main__':
    main()
