"""
Production Queue Manager
Manages serial video production queue
"""
import json
import os
from pathlib import Path
from typing import Dict, List, Optional
from datetime import datetime, timezone
import uuid


class ProductionQueueManager:
    """Manage production queue with JSON file storage"""
    
    def __init__(self, queue_file: str = 'data/production_queue.json', jobs_dir: str = 'data/jobs', queues_file: str = 'data/queues.json'):
        """
        Initialize production queue manager
        
        Args:
            queue_file: Path to production_queue.json
            jobs_dir: Path to jobs directory
            queues_file: Path to queues.json
        """
        self.queue_file = Path(queue_file)
        self.jobs_dir = Path(jobs_dir)
        self.queues_file = Path(queues_file)
        
        # Ensure file exists
        self._init_file()
    
    def _init_file(self):
        """Initialize JSON file if it doesn't exist"""
        if not self.queue_file.exists():
            self._save_queue({
                'production_queue': [],
                'current_production': None,
                'max_concurrent': 1,
                'metadata': {
                    'last_updated': datetime.now(timezone.utc).isoformat(),
                    'total_produced': 0,
                    'total_failed': 0
                }
            })
    
    def _load_queue(self) -> Dict:
        """Load production queue from file"""
        try:
            with open(self.queue_file, 'r', encoding='utf-8') as f:
                return json.load(f)
        except (FileNotFoundError, json.JSONDecodeError):
            self._init_file()
            return self._load_queue()
    
    def _save_queue(self, data: Dict):
        """Save production queue to file"""
        data['metadata']['last_updated'] = datetime.now(timezone.utc).isoformat()
        with open(self.queue_file, 'w', encoding='utf-8') as f:
            json.dump(data, f, indent=2, ensure_ascii=False)
    
    def _load_queues(self) -> Dict:
        """Load queues.json to check is_active status"""
        try:
            with open(self.queues_file, 'r', encoding='utf-8') as f:
                return json.load(f)
        except (FileNotFoundError, json.JSONDecodeError):
            return {'queues': []}
    
    def _is_queue_active(self, queue_id: str) -> bool:
        """Check if a queue is active"""
        # 'default' queue is always active (for retry jobs)
        if queue_id == 'default':
            return True
            
        queues_data = self._load_queues()
        for queue in queues_data.get('queues', []):
            if queue['id'] == queue_id:
                return queue.get('is_active', True)
        return False
    
    def add_to_production_queue(
        self,
        job_id: str,
        queue_id: str,
        priority: int = 0
    ) -> str:
        """
        Add job to production queue
        
        Args:
            job_id: Job ID
            queue_id: Queue ID this job belongs to
            priority: Production priority (higher = sooner)
            
        Returns:
            Production queue item ID
        """
        data = self._load_queue()
        
        # Check if already in queue
        for item in data['production_queue']:
            if item['job_id'] == job_id:
                return item['prod_queue_id']
        
        prod_queue_id = f"prod_{uuid.uuid4().hex[:16]}"
        
        item = {
            'prod_queue_id': prod_queue_id,
            'job_id': job_id,
            'queue_id': queue_id,
            'status': 'waiting',  # waiting, producing, done, failed
            'priority': priority,
            'added_at': datetime.now(timezone.utc).isoformat(),
            'started_at': None,
            'completed_at': None,
            'error': None
        }
        
        data['production_queue'].append(item)
        self._save_queue(data)
        
        return prod_queue_id
    
    def get_next_job(self) -> Optional[Dict]:
        """
        Get next job to produce (highest priority, FIFO within priority)
        Only returns jobs from ACTIVE queues
        
        Returns:
            Production queue item or None
        """
        data = self._load_queue()
        
        # If something is currently producing, don't start another
        if data['current_production']:
            return None
        
        # Filter waiting items from active queues only
        waiting_items = [
            item for item in data['production_queue']
            if item['status'] == 'waiting' and self._is_queue_active(item['queue_id'])
        ]
        
        if not waiting_items:
            return None
        
        # Sort by priority (desc) then by added_at (asc)
        waiting_items.sort(key=lambda x: (-x['priority'], x['added_at']))
        
        return waiting_items[0]
    
    def mark_producing(self, prod_queue_id: str) -> bool:
        """Mark item as currently producing"""
        data = self._load_queue()
        
        for item in data['production_queue']:
            if item['prod_queue_id'] == prod_queue_id:
                item['status'] = 'producing'
                item['started_at'] = datetime.now(timezone.utc).isoformat()
                data['current_production'] = prod_queue_id
                self._save_queue(data)
                return True
        
        return False
    
    def mark_done(self, prod_queue_id: str) -> bool:
        """Mark item as done"""
        data = self._load_queue()
        
        for item in data['production_queue']:
            if item['prod_queue_id'] == prod_queue_id:
                item['status'] = 'done'
                item['completed_at'] = datetime.now(timezone.utc).isoformat()
                
                if data['current_production'] == prod_queue_id:
                    data['current_production'] = None
                
                data['metadata']['total_produced'] += 1
                self._save_queue(data)
                return True
        
        return False
    
    def mark_failed(self, prod_queue_id: str, error: str = None) -> bool:
        """Mark item as failed"""
        data = self._load_queue()
        
        for item in data['production_queue']:
            if item['prod_queue_id'] == prod_queue_id:
                item['status'] = 'failed'
                item['completed_at'] = datetime.now(timezone.utc).isoformat()
                item['error'] = error
                
                if data['current_production'] == prod_queue_id:
                    data['current_production'] = None
                
                data['metadata']['total_failed'] += 1
                self._save_queue(data)
                return True
        
        return False
    
    def get_current_production(self) -> Optional[Dict]:
        """Get currently producing item"""
        data = self._load_queue()
        
        if not data['current_production']:
            return None
        
        for item in data['production_queue']:
            if item['prod_queue_id'] == data['current_production']:
                return item
        
        return None
    
    def get_queue_items(self, queue_id: str) -> List[Dict]:
        """Get all production queue items for a specific queue"""
        data = self._load_queue()
        return [item for item in data['production_queue'] if item['queue_id'] == queue_id]
    
    def get_waiting_count(self, queue_id: str = None) -> int:
        """Get count of waiting items (optionally filtered by queue)"""
        data = self._load_queue()
        items = data['production_queue']
        
        if queue_id:
            items = [item for item in items if item['queue_id'] == queue_id]
        
        return len([item for item in items if item['status'] == 'waiting'])
    
    def check_production_timeout(self, timeout_seconds: int = 3600) -> Optional[Dict]:
        """
        Check if current production has exceeded timeout
        
        Args:
            timeout_seconds: Maximum production time in seconds (default: 3600 = 1 hour)
            
        Returns:
            Dict with timeout info if timed out, None otherwise
        """
        current = self.get_current_production()
        
        if not current or not current.get('started_at'):
            return None
        
        # Calculate elapsed time
        started_at = datetime.fromisoformat(current['started_at'].replace('Z', '+00:00'))
        now = datetime.now(timezone.utc)
        elapsed_seconds = (now - started_at).total_seconds()
        
        if elapsed_seconds > timeout_seconds:
            # Timeout exceeded - mark as failed
            error_msg = f"Production timeout - stuck for {int(elapsed_seconds/60)} minutes"
            self.mark_failed(current['prod_queue_id'], error_msg)
            
            return {
                'prod_queue_id': current['prod_queue_id'],
                'job_id': current['job_id'],
                'elapsed_seconds': int(elapsed_seconds),
                'timeout_seconds': timeout_seconds,
                'error': error_msg
            }
        
        return None
    
    
    def remove_from_queue(self, prod_queue_id: str) -> bool:
        """Remove item from production queue"""
        data = self._load_queue()
        
        original_len = len(data['production_queue'])
        data['production_queue'] = [
            item for item in data['production_queue']
            if item['prod_queue_id'] != prod_queue_id
        ]
        
        if len(data['production_queue']) < original_len:
            self._save_queue(data)
            return True
        
        return False
    
    def cleanup_completed(self, max_age_hours: int = 24) -> int:
        """Remove old completed/failed items"""
        data = self._load_queue()
        
        from datetime import timedelta
        cutoff = datetime.now(timezone.utc) - timedelta(hours=max_age_hours)
        
        original_len = len(data['production_queue'])
        data['production_queue'] = [
            item for item in data['production_queue']
            if item['status'] in ['waiting', 'producing'] or
               (item.get('completed_at') and datetime.fromisoformat(item['completed_at'].replace('Z', '+00:00')) > cutoff)
        ]
        
        removed = original_len - len(data['production_queue'])
        if removed > 0:
            self._save_queue(data)
        
        return removed


# CLI Test
if __name__ == '__main__':
    print("🧪 Production Queue Manager Test\n")
    
    manager = ProductionQueueManager()
    
    # Add test jobs
    print("📝 Adding test jobs...")
    id1 = manager.add_to_production_queue('job_test_001', 'youtube-testr', priority=0)
    id2 = manager.add_to_production_queue('job_test_002', 'youtube-testr', priority=5)
    id3 = manager.add_to_production_queue('job_test_003', 'testet-421e56', priority=0)
    
    print(f"✅ Added: {id1}, {id2}, {id3}\n")
    
    # Get next job
    print("🔍 Getting next job...")
    next_job = manager.get_next_job()
    if next_job:
        print(f"✅ Next: {next_job['job_id']} (priority: {next_job['priority']})\n")
        
        # Mark producing
        print("🚀 Marking as producing...")
        manager.mark_producing(next_job['prod_queue_id'])
        print(f"✅ Now producing: {next_job['job_id']}\n")
        
        # Check current
        current = manager.get_current_production()
        if current:
            print(f"ℹ️  Current production: {current['job_id']}\n")
        
        # Mark done
        print("✅ Marking as done...")
        manager.mark_done(next_job['prod_queue_id'])
        print(f"✅ Completed: {next_job['job_id']}\n")
    
    # Get waiting count
    waiting = manager.get_waiting_count()
    print(f"⏳ Waiting jobs: {waiting}\n")
    
    print("✅ Test completed!")
