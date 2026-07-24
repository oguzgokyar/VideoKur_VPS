"""
Production Scheduler - Active Mode
Processes production queue sequentially - one video at a time
"""
import sys
import time
import json
import subprocess
import traceback
from pathlib import Path
from datetime import datetime

sys.path.insert(0, str(Path(__file__).parent.parent))

from scheduler.production_queue_manager import ProductionQueueManager
from utils.production_lock import GlobalProductionLock


class ProductionScheduler:
    """Production scheduler - processes queue sequentially"""
    
    def __init__(self, base_dir: str, check_interval: int = 10):
        self.base_dir = Path(base_dir)
        self.check_interval = check_interval
        self.stuck_threshold = 600  # 10 minutes in seconds
        self.watchdog_check_interval = 30  # Check every 30 seconds
        self.last_watchdog_check = 0
        
        # Initialize queue manager and lock
        self.queue_manager = ProductionQueueManager(str(self.base_dir / 'data'))
        self.production_lock = GlobalProductionLock()
        
        # Paths
        self.config_file = self.base_dir / 'data' / 'config.json'
        self.python_dir = self.base_dir / 'python'
        self.jobs_dir = self.base_dir / 'data' / 'jobs'
        
        print("=" * 70)
        print("Production Scheduler - ACTIVE MODE")
        print("=" * 70)
        print(f"Base directory: {base_dir}")
        print(f"Check interval: {check_interval}s")
        print(f"Stuck detection: {self.stuck_threshold}s ({self.stuck_threshold//60} min)")
        print(f"Watchdog check: {self.watchdog_check_interval}s")
        print()
        print("✅ Sequential video production enabled")
        print("✅ One video at a time - no parallel processing")
        print("✅ Stuck job detection enabled")
        print("=" * 70)
    
    def run(self):
        """Main loop - process production queue"""
        print()
        print("🚀 Production Scheduler started")
        print("📋 Monitoring production queue...")
        print("🔍 Watchdog monitoring for stuck jobs...")
        print("Press Ctrl+C to stop")
        print()
        
        try:
            while True:
                # Run watchdog check if needed
                now = time.time()
                if now - self.last_watchdog_check > self.watchdog_check_interval:
                    self._check_stuck_jobs()
                    self.last_watchdog_check = now
                
                # Process queue
                self._process_queue()
                time.sleep(self.check_interval)
                
        except KeyboardInterrupt:
            print("\n\n⏸️  Production Scheduler stopped")
        except Exception as e:
            print(f"\n❌ Error: {e}")
            traceback.print_exc()
            raise
    
    def _process_queue(self):
        """Check queue and process next job if available"""
        now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        
        # Check if production is locked (another process running)
        if self.production_lock.is_locked():
            current_job = self.production_lock.get_current_job()
            print(f"[{now}] ⏳ Production in progress: {current_job}", end='\r')
            return
        
        # Get queue status
        status = self.queue_manager.get_status()
        queue_length = status['queue_length']
        current_job = status['current_job']
        
        if queue_length == 0 and not current_job:
            print(f"[{now}] 💤 Queue empty - waiting for jobs...", end='\r')
            return
        
        # Get next job
        next_job = self.queue_manager.get_next_job()
        
        if not next_job:
            print(f"[{now}] ⏸️  Queue: {queue_length} job(s) | No jobs ready", end='\r')
            return
        
        job_id = next_job['job_id']
        position = next_job.get('position', '?')
        
        print(f"\n[{now}] 🎬 Starting production: {job_id} (position {position}/{queue_length})")
        
        # Start the job
        self._start_production(job_id)
    
    def _start_production(self, job_id: str):
        """Start video production for a job"""
        try:
            # Mark job as started
            result = self.queue_manager.start_job(job_id)
            if not result['success']:
                print(f"❌ Failed to start job: {result.get('error')}")
                return
            
            print(f"📝 Job marked as processing: {job_id}")
            
            # Build pipeline command
            pipeline_script = self.python_dir / 'pipeline.py'
            
            # Get URL from job file
            job_file = self.base_dir / 'data' / 'jobs' / f'{job_id}.json'
            if not job_file.exists():
                print(f"❌ Job file not found: {job_file}")
                self.queue_manager.complete_job(job_id, success=False, error='Job file not found')
                return
            
            with open(job_file, 'r', encoding='utf-8') as f:
                job_data = json.load(f)
            
            url = job_data.get('url')
            source_mode = (job_data.get('source_mode') or 'url').lower()
            template = job_data.get('template', 'short_haber')
            
            if source_mode != 'prompt' and not url:
                print(f"❌ No URL in job data")
                self.queue_manager.complete_job(job_id, success=False, error='No URL in job data')
                return
            if source_mode == 'prompt' and not url:
                url = f'prompt://{job_id}'
            
            # Start pipeline in background
            cmd = [
                'python',
                str(pipeline_script),
                job_id,
                url,
                template,
                str(self.config_file)
            ]
            
            print(f"🚀 Starting pipeline: {' '.join(cmd)}")
            
            # Use detached async execution.
            # Do NOT pipe stdout/stderr without readers; PIPE buffers can block child process.
            process = subprocess.Popen(
                cmd,
                cwd=str(self.base_dir),
                stdout=subprocess.DEVNULL,
                stderr=subprocess.STDOUT
            )
            
            print(f"✅ Pipeline started (PID: {process.pid})")
            print(f"📊 Status will be monitored by pipeline process")
            
        except Exception as e:
            print(f"❌ Error starting production: {e}")
            traceback.print_exc()
            
            # Mark job as failed
            self.queue_manager.complete_job(job_id, success=False, error=str(e))
    
    def _check_stuck_jobs(self):
        """
        Watchdog: Check for stuck jobs and mark them as stuck
        
        A job is considered stuck if:
        - Status is 'processing', 'scripting', 'rendering', etc.
        - No progress update for stuck_threshold seconds (default 10 minutes)
        """
        try:
            # Get all job files
            if not self.jobs_dir.exists():
                return
            
            now = time.time()
            stuck_count = 0
            
            for job_file in self.jobs_dir.glob('*.json'):
                # Skip temp files
                if job_file.name.endswith('.tmp'):
                    continue
                
                try:
                    with open(job_file, 'r', encoding='utf-8') as f:
                        job = json.load(f)
                    
                    job_id = job.get('id', job_file.stem)
                    status = job.get('status', '')
                    
                    # Only check active jobs
                    if status not in ['processing', 'scripting', 'rendering', 'generating']:
                        continue
                    
                    # Check last update time
                    last_update = job.get('last_update_time', job.get('created_at', now))
                    
                    # Convert created_at string to timestamp if needed
                    if isinstance(last_update, str):
                        try:
                            from datetime import datetime
                            dt = datetime.fromisoformat(last_update)
                            last_update = dt.timestamp()
                        except:
                            last_update = now
                    
                    elapsed = now - last_update
                    
                    # Check if stuck
                    if elapsed > self.stuck_threshold:
                        stuck_count += 1
                        elapsed_minutes = int(elapsed // 60)
                        
                        print(f"\n🚨 STUCK JOB DETECTED: {job_id}")
                        print(f"   Status: {status}")
                        print(f"   No update for: {elapsed_minutes} minutes")
                        print(f"   Threshold: {self.stuck_threshold//60} minutes")
                        
                        # Mark as stuck
                        self._mark_job_stuck(job_id, elapsed_minutes, status)
                        
                except Exception as e:
                    # Skip problematic job files
                    print(f"⚠️ Error checking job {job_file.name}: {e}")
                    continue
            
            if stuck_count > 0:
                print(f"\n⚠️ Watchdog found {stuck_count} stuck job(s)\n")
                
        except Exception as e:
            print(f"⚠️ Watchdog error: {e}")
    
    def _mark_job_stuck(self, job_id: str, elapsed_minutes: int, previous_status: str):
        """Mark a job as stuck and notify user"""
        job_file = self.jobs_dir / f'{job_id}.json'
        
        try:
            with open(job_file, 'r', encoding='utf-8') as f:
                job = json.load(f)
            
            # Update job status
            job['status'] = 'stuck'
            job['previous_status'] = previous_status
            job['error'] = (
                f"İşlem Takıldı\n"
                f"{elapsed_minutes} dakikadır ilerleme yok.\n\n"
                f"Yapılması gerekenler: \"Devam Et\" butonuna tıklayın veya \"Log Gör\" ile detayları inceleyin.\n"
                f"İşlem tekrarlanıyorsa lütfen log dosyalarını kontrol edin.\n\n"
                f"Teknik detay: No progress update for {elapsed_minutes} minutes (threshold: {self.stuck_threshold//60} min)"
            )
            job['stuck_at'] = datetime.now().isoformat()
            job['stuck_elapsed_minutes'] = elapsed_minutes
            job['last_update_time'] = time.time()
            job['updated_at'] = time.strftime('%Y-%m-%d %H:%M:%S')
            
            # Write atomically
            temp_file = self.jobs_dir / f'{job_id}.json.tmp'
            with open(temp_file, 'w', encoding='utf-8') as f:
                json.dump(job, f, ensure_ascii=False, indent=2)
            
            temp_file.replace(job_file)
            
            print(f"✅ Job {job_id} marked as stuck")
            
        except Exception as e:
            print(f"❌ Error marking job as stuck: {e}")
    
    def _check_status(self):
        """Check and display queue status"""
        status = self.queue_manager.get_status()
        now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
        
        queue_length = status['queue_length']
        current_job = status['current_job']
        stats = status['stats']
        
        if current_job:
            print(f"[{now}] 🎬 Processing: {current_job} | Queue: {queue_length}", end='\r')
        elif queue_length > 0:
            print(f"[{now}] 📋 Queue: {queue_length} job(s) waiting", end='\r')
        else:
            print(f"[{now}] 💤 Queue empty | Total completed: {stats['total_completed']}", end='\r')


if __name__ == '__main__':
    import argparse
    
    parser = argparse.ArgumentParser(description='Production Scheduler (Passive Mode)')
    parser.add_argument('--interval', type=int, default=30, help='Check interval in seconds')
    args = parser.parse_args()
    
    base_dir = Path(__file__).parent.parent.parent
    scheduler = ProductionScheduler(str(base_dir), args.interval)
    scheduler.run()
