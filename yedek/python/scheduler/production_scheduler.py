"""
Production Scheduler
Serial video production scheduler - processes one video at a time
"""
import sys
import os
import time
import json
import subprocess
import uuid
from pathlib import Path
from datetime import datetime, timezone, timedelta
from typing import Optional

# Add parent directory to path
sys.path.insert(0, str(Path(__file__).parent.parent))

from scheduler.production_queue_manager import ProductionQueueManager


class ProductionScheduler:
    """Background scheduler for serial video production"""
    
    def __init__(self, base_dir: str, check_interval: int = 30):
        """
        Initialize production scheduler
        
        Args:
            base_dir: Base directory of the application
            check_interval: Check interval in seconds (default 30 seconds)
        """
        self.base_dir = Path(base_dir)
        self.check_interval = check_interval
        
        # Setup paths
        data_dir = self.base_dir / 'data'
        self.jobs_dir = data_dir / 'jobs'
        self.config_file = data_dir / 'config.json'
        self.upload_queue_file = data_dir / 'upload_queue.json'
        
        # Initialize manager
        self.queue_manager = ProductionQueueManager(
            str(data_dir / 'production_queue.json'),
            str(self.jobs_dir),
            str(data_dir / 'queues.json')
        )
        
        print("Production Scheduler başlatıldı")
        print(f"Kontrol aralığı: {check_interval} saniye")
        print(f"Base directory: {base_dir}")
        print("Max concurrent: 1 (serial production)")
    
    def run(self):
        """Main scheduler loop"""
        print("\nProduction Scheduler çalışıyor...\n")
        print("🔐 YouTube Token Monitoring: Aktif")
        print("⚠️  Token hatası alındığında otomatik yeniden auth yapılacak\n")
        
        try:
            while True:
                self.process_production_queue()
                time.sleep(self.check_interval)
                
        except KeyboardInterrupt:
            print("\n\n⏹️  Production Scheduler durduruldu")
        except Exception as e:
            print(f"\n❌ Scheduler hatası: {e}")
            import traceback
            traceback.print_exc()
            raise
    
    def process_production_queue(self):
        """Process production queue - start next video if none is producing"""
        now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        
        # Check if something is currently producing
        current = self.queue_manager.get_current_production()
        if current:
            job_id = current['job_id']
            elapsed = self._get_elapsed_time(current['started_at'])
            print(f"[{now}] Üretiliyor: {job_id} (Geçen süre: {elapsed})", end='\r')
            
            # Check for timeout (1 hour)
            timeout_info = self.queue_manager.check_production_timeout(timeout_seconds=3600)
            if timeout_info:
                print(f"\n[{now}] ⏱️  TIMEOUT: {timeout_info['job_id']}")
                print(f"    Elapsed: {timeout_info['elapsed_seconds']}s (limit: {timeout_info['timeout_seconds']}s)")
                print(f"    Error: {timeout_info['error']}")
                return
            
            # Check if job is done or failed
            job_status = self._get_job_status(job_id)
            if job_status == 'done':
                print(f"\n[{now}] TAMAMLANDI: {job_id}")
                self.queue_manager.mark_done(current['prod_queue_id'])
                
                # Add to upload queue
                self._add_to_upload_queue(job_id, current['queue_id'])
                
            elif job_status == 'failed':
                error = self._get_job_error(job_id)
                print(f"\n[{now}] ❌ Başarısız: {job_id} - {error}")
                self.queue_manager.mark_failed(current['prod_queue_id'], error)
            
            return
        
        # No current production - get next job
        next_job = self.queue_manager.get_next_job()
        
        if not next_job:
            waiting_count = self.queue_manager.get_waiting_count()
            if waiting_count > 0:
                print(f"[{now}] ⏸️  {waiting_count} video bekliyor (kuyruklar durdurulmuş)", end='\r')
            else:
                print(f"[{now}] Bekleyen video yok", end='\r')
            return
        
        # Start production
        print(f"\n[{now}] ÜRETIM BAŞLIYOR: {next_job['job_id']}")
        print(f"    📋 Kuyruk: {next_job['queue_id']}")
        print(f"    ⏰ Sırada bekletilme: {self._get_elapsed_time(next_job['added_at'])}")
        
        self.queue_manager.mark_producing(next_job['prod_queue_id'])
        
        # Start pipeline
        success = self._start_pipeline(next_job['job_id'])
        
        if not success:
            print(f"[{now}] ❌ Pipeline başlatılamadı: {next_job['job_id']}")
            self.queue_manager.mark_failed(next_job['prod_queue_id'], 'Pipeline başlatma hatası')
    
    def _start_pipeline(self, job_id: str) -> bool:
        """
        Start video production pipeline
        
        Args:
            job_id: Job ID
            
        Returns:
            Success status
        """
        try:
            # Load job data
            job_file = self.jobs_dir / f"{job_id}.json"
            if not job_file.exists():
                print(f"❌ Job dosyası bulunamadı: {job_id}")
                return False
            
            with open(job_file, 'r', encoding='utf-8') as f:
                job_data = json.load(f)
            
            url = job_data.get('url', '')
            template = job_data.get('template', 'short_haber')
            
            if not url:
                print(f"❌ Job URL eksik: {job_id}")
                return False
            
            # Start pipeline in background
            python_script = self.base_dir / 'python' / 'pipeline.py'
            
            if sys.platform == 'win32':
                # Windows: start /B for background
                cmd = f'start /B cmd /c python "{python_script}" "{job_id}" "{url}" "{template}" "{self.config_file}"'
            else:
                # Linux/Mac: & for background
                cmd = f'python "{python_script}" "{job_id}" "{url}" "{template}" "{self.config_file}" &'
            
            subprocess.Popen(cmd, shell=True)
            print(f"✅ Pipeline başlatıldı: {job_id}")
            return True
            
        except Exception as e:
            print(f"❌ Pipeline başlatma hatası: {e}")
            return False
    
    def _get_job_status(self, job_id: str) -> str:
        """Get current job status from job file"""
        try:
            job_file = self.jobs_dir / f"{job_id}.json"
            if not job_file.exists():
                return 'unknown'
            
            with open(job_file, 'r', encoding='utf-8') as f:
                job_data = json.load(f)
            
            return job_data.get('status', 'unknown')
        except:
            return 'unknown'
    
    def _get_job_error(self, job_id: str) -> str:
        """Get job error message"""
        try:
            job_file = self.jobs_dir / f"{job_id}.json"
            if not job_file.exists():
                return 'Job dosyası bulunamadı'
            
            with open(job_file, 'r', encoding='utf-8') as f:
                job_data = json.load(f)
            
            return job_data.get('error', 'Bilinmeyen hata')
        except:
            return 'Hata okunamadı'
    
    def _get_elapsed_time(self, iso_time: str) -> str:
        """Get elapsed time since timestamp"""
        try:
            start_time = datetime.fromisoformat(iso_time.replace('Z', '+00:00'))
            now = datetime.now(timezone.utc)
            elapsed = now - start_time
            
            hours = int(elapsed.total_seconds() // 3600)
            minutes = int((elapsed.total_seconds() % 3600) // 60)
            seconds = int(elapsed.total_seconds() % 60)
            
            if hours > 0:
                return f"{hours}s {minutes}d {seconds}sn"
            elif minutes > 0:
                return f"{minutes}d {seconds}sn"
            else:
                return f"{seconds}sn"
        except:
            return "?"
    
    def _add_to_upload_queue(self, job_id: str, queue_id: str):
        """
        Add completed video to upload queue
        
        Args:
            job_id: Job ID
            queue_id: Queue ID (for scheduling info)
        """
        try:
            # Load job data
            job_file = self.jobs_dir / f"{job_id}.json"
            if not job_file.exists():
                print(f"⚠️  Job dosyası bulunamadı: {job_id}")
                return
            
            with open(job_file, 'r', encoding='utf-8') as f:
                job_data = json.load(f)
            
            # Check if video exists
            video_path = self.base_dir / 'output' / job_id / 'final_video.mp4'
            if not video_path.exists():
                print(f"⚠️  Video dosyası bulunamadı: {job_id}")
                return
            
            # Load queue data for scheduling
            queues_file = self.base_dir / 'data' / 'queues.json'
            with open(queues_file, 'r', encoding='utf-8') as f:
                queues_data = json.load(f)
            
            target_queue = None
            for queue in queues_data.get('queues', []):
                if queue['id'] == queue_id:
                    target_queue = queue
                    break
            
            if not target_queue:
                print(f"⚠️  Kuyruk bulunamadı: {queue_id}")
                return
            
            # DUPLICATE KONTROLÜ - Video zaten kuyrukta mı?
            existing_job_ids = [v.get('job_id') for v in target_queue.get('videos', [])]
            if job_id in existing_job_ids:
                print(f"ℹ️  Video zaten kuyrukta, tekrar eklenmedi: {job_id}")
                return
            
            # Calculate scheduled time based on queue schedule
            scheduled_time = self._calculate_scheduled_time(target_queue)
            
            # Add to queue's video list
            video_item = {
                'job_id': job_id,
                'added_at': datetime.now(timezone.utc).isoformat(),
                'scheduled_at': scheduled_time,
                'status': 'queued',
                'platform_status': {platform: 'pending' for platform in target_queue.get('platforms', ['youtube'])},
                'position': len(target_queue.get('videos', []))
            }
            
            if 'videos' not in target_queue:
                target_queue['videos'] = []
            
            target_queue['videos'].append(video_item)
            
            # Save queues
            with open(queues_file, 'w', encoding='utf-8') as f:
                json.dump(queues_data, f, indent=2, ensure_ascii=False)
            
            print(f"✅ Upload kuyruğuna eklendi: {job_id} → {target_queue['name']}")
            
            # Also add to social_queue.json for social scheduler
            self._add_to_social_queue(job_id, job_data, target_queue, scheduled_time, str(video_path))
            
        except Exception as e:
            print(f"⚠️  Upload queue ekleme hatası: {e}")
    
    def _calculate_scheduled_time(self, queue: dict) -> str:
        """Calculate next scheduled time based on queue settings"""
        schedule = queue.get('schedule', {})
        schedule_type = schedule.get('type', 'now')
        
        now = datetime.now(timezone.utc)
        
        # Parse timezone offset (default: UTC+3 for Turkey)
        tz_offset_hours = 3
        timezone_str = schedule.get('timezone', 'Europe/Istanbul')
        if 'Istanbul' in timezone_str or 'Turkey' in timezone_str:
            tz_offset_hours = 3
        
        if schedule_type == 'now':
            # Immediate
            return now.isoformat()
        
        elif schedule_type == 'interval':
            # Check if start_time is specified
            start_time = schedule.get('start_time')
            last_publish = queue.get('last_publish')
            
            # Determine base time
            if start_time and not last_publish:
                # First video - use start_time
                try:
                    # Try parsing full datetime
                    if 'T' in start_time or ' ' in start_time:
                        base_time = datetime.fromisoformat(start_time.replace('Z', '+00:00').replace(' ', 'T'))
                    else:
                        # Only time provided (HH:MM) - use today
                        hour, minute = map(int, start_time.split(':'))
                        local_now = now + timedelta(hours=tz_offset_hours)
                        base_time = local_now.replace(hour=hour, minute=minute, second=0, microsecond=0)
                        base_time = base_time - timedelta(hours=tz_offset_hours)  # Convert back to UTC
                        
                        # If time already passed today, schedule for tomorrow
                        if base_time < now:
                            base_time = base_time + timedelta(days=1)
                except Exception:
                    base_time = now
            elif last_publish:
                # Not first video - use last publish time
                base_time = datetime.fromisoformat(last_publish.replace('Z', '+00:00'))
            else:
                # No start_time and no last_publish - use now
                base_time = now
            
            # Get interval in minutes (support both interval_minutes and interval_hours)
            interval_minutes = schedule.get('interval_minutes', 0)
            if interval_minutes == 0:
                interval_hours = schedule.get('interval_hours', 2)
                interval_minutes = interval_hours * 60
            
            next_time = base_time + timedelta(minutes=interval_minutes)
            
            # If next time is in the past, schedule for now
            if next_time < now:
                next_time = now
            
            return next_time.isoformat()
        
        elif schedule_type == 'specific':
            # Find next specific time
            specific_times = schedule.get('specific_times', ['09:00', '15:00', '21:00'])
            
            # Convert to local time for comparison
            local_now = now + timedelta(hours=tz_offset_hours)
            
            for time_str in sorted(specific_times):
                hour, minute = map(int, time_str.split(':'))
                candidate = local_now.replace(hour=hour, minute=minute, second=0, microsecond=0)
                
                if candidate > local_now:
                    # Convert back to UTC
                    candidate_utc = candidate - timedelta(hours=tz_offset_hours)
                    return candidate_utc.isoformat()
            
            # All times passed today, schedule for tomorrow
            first_time = sorted(specific_times)[0]
            hour, minute = map(int, first_time.split(':'))
            next_day = local_now + timedelta(days=1)
            next_day_time = next_day.replace(hour=hour, minute=minute, second=0, microsecond=0)
            next_day_utc = next_day_time - timedelta(hours=tz_offset_hours)
            return next_day_utc.isoformat()
        
        # Default: now
        return now.isoformat()
    
    def _load_script_text(self, job_id: str) -> str:
        """output/job_id/script.json'dan duz metin olustur (metadata motoru icin)"""
        script_file = self.base_dir / 'output' / job_id / 'script.json'
        if not script_file.exists():
            return ''
        try:
            with open(script_file, 'r', encoding='utf-8') as f:
                script = json.load(f)
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

    def _add_to_social_queue(self, job_id: str, job_data: dict, queue: dict, scheduled_time: str, video_path: str):
        """Add video to social_queue.json for social scheduler"""
        try:
            social_queue_file = self.base_dir / 'data' / 'social_queue.json'
            
            if social_queue_file.exists():
                with open(social_queue_file, 'r', encoding='utf-8') as f:
                    social_data = json.load(f)
            else:
                social_data = {'queue': []}
            
            platforms = queue.get('platforms', ['youtube'])
            
            # Create platform status
            platform_status = {}
            for platform in platforms:
                platform_status[platform] = {
                    'status': 'pending',
                    'post_id': None,
                    'post_url': None,
                    'error': None,
                    'uploaded_at': None
                }

            # Gercek script metnini yukle — metadata motoru bunu kullanacak
            script_text = self._load_script_text(job_id)

            queue_item = {
                'queue_id': f"social_{uuid.uuid4().hex[:16]}",
                'job_id': job_id,
                'video_path': video_path,
                'platforms': platforms,
                'platform_status': platform_status,
                'scheduled_time': scheduled_time,
                'status': 'pending',
                'priority': 0,
                'metadata': {
                    'title': job_data.get('title', 'Video'),
                    # description = gercek script metni (upload oncesi AI optimizasyon icin)
                    'description': script_text or job_data.get('description', ''),
                    'tags': job_data.get('tags', []),
                    'privacy_status': 'public'
                },
                'platform_metadata': {},  # social_scheduler upload oncesi dolduracak
                'created_at': datetime.now(timezone.utc).isoformat(),
                'retry_count': 0,
                'last_error': None
            }
            
            social_data['queue'].append(queue_item)
            
            with open(social_queue_file, 'w', encoding='utf-8') as f:
                json.dump(social_data, f, indent=2, ensure_ascii=False)
            
            print(f"\u2705 Social queue'ya eklendi: {job_id} | Script: {len(script_text)} kar | Zamanlama: {scheduled_time}")
            
        except Exception as e:
            print(f"\u26a0\ufe0f  Social queue ekleme hatasi: {e}")


# CLI Entry Point
if __name__ == '__main__':
    import argparse
    
    parser = argparse.ArgumentParser(description='Production Scheduler - Serial Video Production')
    parser.add_argument('--interval', type=int, default=30, help='Check interval in seconds (default: 30)')
    
    args = parser.parse_args()
    
    # Get base directory (2 levels up from this script)
    base_dir = Path(__file__).parent.parent.parent
    
    scheduler = ProductionScheduler(str(base_dir), check_interval=args.interval)
    scheduler.run()
