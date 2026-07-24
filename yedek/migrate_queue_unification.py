#!/usr/bin/env python3
"""
Queue Unification Migration Script
Migrates social_queue.json data into queues.json structure
"""
import json
import sys
from pathlib import Path
from datetime import datetime
from typing import Dict, List

def load_json(file_path: Path) -> dict:
    """Load JSON file"""
    if not file_path.exists():
        return {}
    with open(file_path, 'r', encoding='utf-8') as f:
        return json.load(f)

def save_json(file_path: Path, data: dict):
    """Save JSON file"""
    with open(file_path, 'w', encoding='utf-8') as f:
        json.dump(data, f, indent=2, ensure_ascii=False)

def migrate_queues(data_dir: Path):
    """
    Migrate social_queue.json scheduled times and status into queues.json
    """
    queues_file = data_dir / 'queues.json'
    social_queue_file = data_dir / 'social_queue.json'
    
    # Load files
    queues_data = load_json(queues_file)
    social_queue_data = load_json(social_queue_file)
    
    if not queues_data or 'queues' not in queues_data:
        print("❌ queues.json bulunamadı veya geçersiz")
        return False
    
    # Build mapping: job_id -> social queue data
    social_map = {}
    for item in social_queue_data.get('queue', []):
        job_id = item.get('job_id')
        if job_id:
            social_map[job_id] = {
                'scheduled_time': item.get('scheduled_time'),
                'platform_status': item.get('platform_status', {}),
                'status': item.get('status', 'pending'),
                'last_error': item.get('last_error'),
                'retry_count': item.get('retry_count', 0),
                'created_at': item.get('created_at'),
                'priority': item.get('priority', 0)
            }
    
    print(f"📦 Social queue'dan {len(social_map)} video bulundu")
    
    # Migrate data to queues.json
    updated = 0
    for queue in queues_data.get('queues', []):
        for video in queue.get('videos', []):
            job_id = video.get('job_id')
            
            # Check if we have social queue data for this job
            if job_id in social_map:
                social_data = social_map[job_id]
                
                # Add scheduled_time if it exists
                if social_data['scheduled_time']:
                    video['scheduled_time'] = social_data['scheduled_time']
                    updated += 1
                
                # Update platform_status with more detailed info
                if social_data['platform_status']:
                    video['platform_status'] = social_data['platform_status']
                
                # Add error info if exists
                if social_data['last_error']:
                    video['last_error'] = social_data['last_error']
                
                if social_data['retry_count']:
                    video['retry_count'] = social_data['retry_count']
                
                print(f"  ✅ Migrated: {job_id} → {social_data['scheduled_time']}")
            else:
                # No social queue data - calculate scheduled_time based on queue settings
                # This will be done by the API when needed, but we can add a placeholder
                if 'scheduled_time' not in video:
                    video['scheduled_time'] = None  # Will be calculated later
                
                # Ensure retry_count exists
                if 'retry_count' not in video:
                    video['retry_count'] = 0
    
    # Save updated queues.json
    save_json(queues_file, queues_data)
    print(f"\n✅ {updated} video'nun scheduled_time bilgisi güncellendi")
    print(f"✅ queues.json kaydedildi: {queues_file}")
    
    return True

def main():
    """Main migration function"""
    script_dir = Path(__file__).parent
    data_dir = script_dir / 'data'
    
    print("🔄 Queue Unification Migration Başlıyor...")
    print(f"📁 Data directory: {data_dir}")
    print()
    
    if not data_dir.exists():
        print(f"❌ Data directory bulunamadı: {data_dir}")
        return 1
    
    # Run migration
    success = migrate_queues(data_dir)
    
    if success:
        print("\n✅ Migration tamamlandı!")
        print("\nℹ️  Not: social_queue.json dosyası artık kullanılmayacak.")
        print("   Yeni sistem tamamen queues.json üzerinden çalışacak.")
        return 0
    else:
        print("\n❌ Migration başarısız!")
        return 1

if __name__ == '__main__':
    sys.exit(main())
