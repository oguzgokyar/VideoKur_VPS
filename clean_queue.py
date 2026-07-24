"""
Clean failed/stuck videos from queue
Marks quota-exceeded videos as failed to prevent endless retries
"""
import json
from datetime import datetime, timezone
from pathlib import Path

def clean_queue():
    """Clean stuck videos from queues.json"""
    queues_file = Path('data/queues.json')
    
    if not queues_file.exists():
        print("❌ queues.json not found!")
        return False
    
    # Load queues
    with open(queues_file, 'r', encoding='utf-8') as f:
        data = json.load(f)
    
    print('🧹 Cleaning queues.json...')
    
    total_videos = 0
    quota_failed = 0
    marked_failed = 0
    
    for queue in data.get('queues', []):
        print(f'\n📋 Queue: {queue["name"]} ({queue["id"]})')
        
        for video in queue.get('videos', []):
            total_videos += 1
            platform_status = video.get('platform_status', {})
            
            for platform, status_obj in platform_status.items():
                if isinstance(status_obj, dict):
                    status = status_obj.get('status', 'pending')
                    error = status_obj.get('error')
                    
                    # Check if quota exceeded error (handle None)
                    if error and isinstance(error, str) and 'exceeded' in error.lower():
                        quota_failed += 1
                        
                        # Mark as failed if still pending or processing
                        if status in ('pending', 'processing'):
                            status_obj['status'] = 'failed'
                            status_obj['failed_at'] = datetime.now(timezone.utc).isoformat()
                            status_obj['retry'] = False
                            marked_failed += 1
                            print(f'  ❌ Marked as failed: {video["job_id"]} (quota exceeded)')
    
    # Save back
    with open(queues_file, 'w', encoding='utf-8') as f:
        json.dump(data, f, indent=2, ensure_ascii=False)
    
    print(f'\n✅ Queue cleaned!')
    print(f'📊 Total videos: {total_videos}')
    print(f'⚠️  Quota failed: {quota_failed}')
    print(f'🔧 Marked as failed: {marked_failed}')
    
    return True

if __name__ == '__main__':
    clean_queue()
