"""
YouTube Upload Scheduler
Background service that processes upload queue
"""
import sys
import os
import time
import json
from pathlib import Path
from datetime import datetime, timezone
from typing import Optional

# Add parent directory to path
sys.path.insert(0, str(Path(__file__).parent.parent))

from scheduler.queue_manager import QueueManager
from youtube.auth import YouTubeAuth
from youtube.uploader import YouTubeUploader


class UploadScheduler:
    """Background scheduler for YouTube uploads"""
    
    def __init__(self, base_dir: str, check_interval: int = 300):
        """
        Initialize scheduler
        
        Args:
            base_dir: Base directory of the application
            check_interval: Check interval in seconds (default 5 minutes)
        """
        self.base_dir = Path(base_dir)
        self.check_interval = check_interval
        
        # Setup paths
        data_dir = self.base_dir / 'data'
        creds_dir = data_dir / 'youtube_credentials'
        jobs_dir = data_dir / 'jobs'
        
        self.queue_file = data_dir / 'upload_queue.json'
        self.history_file = data_dir / 'upload_history.json'
        self.jobs_dir = jobs_dir
        
        # Initialize managers
        self.queue_manager = QueueManager(
            str(self.queue_file),
            str(self.history_file)
        )
        
        self.uploader = YouTubeUploader(str(creds_dir))
        
        print(f"📅 Scheduler başlatıldı")
        print(f"⏱️  Kontrol aralığı: {check_interval} saniye")
        print(f"📂 Base directory: {base_dir}")
    
    def run(self):
        """Main scheduler loop"""
        print("\n🚀 Scheduler çalışıyor...\n")
        
        try:
            while True:
                self.process_queue()
                time.sleep(self.check_interval)
                
        except KeyboardInterrupt:
            print("\n\n⏹️  Scheduler durduruldu")
        except Exception as e:
            print(f"\n❌ Scheduler hatası: {e}")
            raise
    
    def process_queue(self):
        """Process pending items in queue"""
        pending = self.queue_manager.get_pending_items()
        
        if not pending:
            now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            print(f"[{now}] ⏳ Bekleyen yükleme yok", end='\r')
            return
        
        print(f"\n📋 {len(pending)} yükleme işleniyor...")
        
        for item in pending:
            self._process_item(item)
    
    def _process_item(self, item: Dict):
        """Process single queue item"""
        queue_id = item['queue_id']
        job_id = item['job_id']
        
        print(f"\n{'='*60}")
        print(f"📤 Upload başlıyor: {item['metadata']['title'][:50]}")
        print(f"🆔 Queue ID: {queue_id}")
        print(f"📁 Job ID: {job_id}")
        
        # Mark as processing
        self.queue_manager.mark_processing(queue_id)
        
        # Check if video file exists
        video_path = item['video_path']
        if not os.path.exists(video_path):
            error = f"Video dosyası bulunamadı: {video_path}"
            print(f"❌ {error}")
            self.queue_manager.mark_failed(queue_id, error, retry=False)
            return
        
        # Upload video
        try:
            result = self.uploader.upload_video(
                video_path=video_path,
                title=item['metadata']['title'],
                description=item['metadata']['description'],
                tags=item['metadata'].get('tags', []),
                category_id=item['metadata'].get('category_id', '28'),
                privacy_status=item['metadata'].get('privacy_status', 'public'),
                notify_subscribers=item['metadata'].get('notify_subscribers', True),
                channel_id=item['channel_id']
            )
            
            if result and result.get('status') == 'success':
                # Success
                video_id = result['video_id']
                video_url = result['video_url']
                
                self.queue_manager.mark_success(queue_id, video_id, video_url)
                
                # Update job JSON
                self._update_job(job_id, {
                    'youtube_upload': {
                        'status': 'uploaded',
                        'video_id': video_id,
                        'video_url': video_url,
                        'uploaded_at': result['uploaded_at'],
                        'queue_id': queue_id
                    }
                })
                
                print(f"✅ Upload başarılı!")
                print(f"🔗 {video_url}")
                
            else:
                # Failed
                error = result.get('error', 'Unknown error') if result else 'Upload failed'
                print(f"❌ Upload başarısız: {error}")
                
                # Retry logic
                retry = 'quota' not in error.lower()  # Don't retry quota errors
                self.queue_manager.mark_failed(queue_id, error, retry=retry)
                
        except Exception as e:
            error = str(e)
            print(f"❌ Exception: {error}")
            self.queue_manager.mark_failed(queue_id, error, retry=True)
    
    def _update_job(self, job_id: str, updates: Dict):
        """Update job JSON file"""
        job_file = self.jobs_dir / f'{job_id}.json'
        
        if not job_file.exists():
            print(f"⚠️  Job dosyası bulunamadı: {job_id}")
            return
        
        try:
            with open(job_file, 'r', encoding='utf-8') as f:
                job = json.load(f)
            
            job.update(updates)
            
            with open(job_file, 'w', encoding='utf-8') as f:
                json.dump(job, f, ensure_ascii=False, indent=2)
            
            print(f"💾 Job güncellendi: {job_id}")
            
        except Exception as e:
            print(f"⚠️  Job güncelleme hatası: {e}")


def main():
    """CLI entry point"""
    import argparse
    
    parser = argparse.ArgumentParser(description='YouTube Upload Scheduler')
    parser.add_argument(
        '--base-dir',
        default=str(Path(__file__).parent.parent.parent),
        help='Base directory of the application'
    )
    parser.add_argument(
        '--interval',
        type=int,
        default=300,
        help='Check interval in seconds (default: 300)'
    )
    
    args = parser.parse_args()
    
    scheduler = UploadScheduler(args.base_dir, args.interval)
    scheduler.run()


if __name__ == '__main__':
    main()
