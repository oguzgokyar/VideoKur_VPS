"""
Upload Queue Manager
Manages YouTube upload queue with JSON storage
"""
import json
import os
from pathlib import Path
from typing import Dict, List, Optional
from datetime import datetime, timezone
import uuid


class QueueManager:
    """Manage upload queue with JSON file storage"""
    
    def __init__(self, queue_file: str, history_file: str):
        """
        Initialize queue manager
        
        Args:
            queue_file: Path to upload_queue.json
            history_file: Path to upload_history.json
        """
        self.queue_file = Path(queue_file)
        self.history_file = Path(history_file)
        
        # Ensure files exist
        self._init_files()
    
    def _init_files(self):
        """Initialize JSON files if they don't exist"""
        if not self.queue_file.exists():
            self._save_queue({'queue': []})
        
        if not self.history_file.exists():
            self._save_history({'history': []})
    
    def add_to_queue(
        self,
        job_id: str,
        video_path: str,
        channel_id: str,
        scheduled_time: str,
        metadata: Dict,
        priority: int = 0
    ) -> str:
        """
        Add item to upload queue
        
        Args:
            job_id: Associated job ID
            video_path: Path to video file
            channel_id: YouTube channel ID
            scheduled_time: ISO format datetime string
            metadata: Video metadata (title, description, tags, etc.)
            priority: Queue priority (higher = sooner)
            
        Returns:
            Queue item ID
        """
        queue = self._load_queue()
        
        queue_id = f"upload_{uuid.uuid4().hex[:20]}"
        
        item = {
            'queue_id': queue_id,
            'job_id': job_id,
            'video_path': video_path,
            'channel_id': channel_id,
            'scheduled_time': scheduled_time,
            'status': 'pending',
            'priority': priority,
            'metadata': metadata,
            'created_at': datetime.now(timezone.utc).isoformat(),
            'retry_count': 0,
            'last_error': None
        }
        
        queue['queue'].append(item)
        self._save_queue(queue)
        
        print(f"✅ Queue'ya eklendi: {queue_id}")
        return queue_id
    
    def get_pending_items(self) -> List[Dict]:
        """Get all pending items that are ready to upload"""
        queue = self._load_queue()
        now = datetime.now(timezone.utc)
        
        pending = []
        for item in queue['queue']:
            if item['status'] == 'pending':
                scheduled = datetime.fromisoformat(item['scheduled_time'].replace('Z', '+00:00'))
                if now >= scheduled:
                    pending.append(item)
        
        # Sort by priority (high to low) then by scheduled time
        pending.sort(key=lambda x: (-x['priority'], x['scheduled_time']))
        
        return pending
    
    def get_all_scheduled(self) -> List[Dict]:
        """Get all scheduled items (pending status)"""
        queue = self._load_queue()
        return [item for item in queue['queue'] if item['status'] == 'pending']
    
    def get_item(self, queue_id: str) -> Optional[Dict]:
        """Get specific queue item"""
        queue = self._load_queue()
        for item in queue['queue']:
            if item['queue_id'] == queue_id:
                return item
        return None
    
    def update_item(self, queue_id: str, updates: Dict):
        """Update queue item"""
        queue = self._load_queue()
        for i, item in enumerate(queue['queue']):
            if item['queue_id'] == queue_id:
                queue['queue'][i].update(updates)
                self._save_queue(queue)
                return True
        return False
    
    def mark_processing(self, queue_id: str):
        """Mark item as processing"""
        self.update_item(queue_id, {'status': 'processing'})
    
    def mark_success(self, queue_id: str, video_id: str, video_url: str):
        """Mark item as successfully uploaded and move to history"""
        queue = self._load_queue()
        item = None
        
        # Find and remove from queue
        for i, q_item in enumerate(queue['queue']):
            if q_item['queue_id'] == queue_id:
                item = queue['queue'].pop(i)
                break
        
        if not item:
            return False
        
        # Add to history
        history = self._load_history()
        history_item = {
            **item,
            'status': 'success',
            'video_id': video_id,
            'video_url': video_url,
            'uploaded_at': datetime.now(timezone.utc).isoformat(),
            'error': None
        }
        history['history'].insert(0, history_item)  # Add to front
        
        # Keep only last 100 items
        history['history'] = history['history'][:100]
        
        self._save_queue(queue)
        self._save_history(history)
        
        print(f"✅ Upload başarılı: {queue_id} -> {video_id}")
        return True
    
    def mark_failed(self, queue_id: str, error: str, retry: bool = True):
        """Mark item as failed, optionally retry"""
        queue = self._load_queue()
        
        for i, item in enumerate(queue['queue']):
            if item['queue_id'] == queue_id:
                item['retry_count'] += 1
                item['last_error'] = error
                
                # Retry logic (max 3 attempts)
                if retry and item['retry_count'] < 3:
                    item['status'] = 'pending'
                    # Reschedule for 5 minutes later
                    from datetime import timedelta
                    scheduled = datetime.fromisoformat(item['scheduled_time'].replace('Z', '+00:00'))
                    new_time = scheduled + timedelta(minutes=5 * item['retry_count'])
                    item['scheduled_time'] = new_time.isoformat()
                    print(f"⚠️  Retry scheduled: {queue_id} (attempt {item['retry_count']})")
                else:
                    item['status'] = 'failed'
                    print(f"❌ Upload failed: {queue_id} - {error}")
                
                queue['queue'][i] = item
                self._save_queue(queue)
                return True
        
        return False
    
    def remove_item(self, queue_id: str) -> bool:
        """Remove item from queue (cancel)"""
        queue = self._load_queue()
        
        for i, item in enumerate(queue['queue']):
            if item['queue_id'] == queue_id:
                queue['queue'].pop(i)
                self._save_queue(queue)
                print(f"🗑️  Removed from queue: {queue_id}")
                return True
        
        return False
    
    def get_history(self, limit: int = 50) -> List[Dict]:
        """Get upload history"""
        history = self._load_history()
        return history['history'][:limit]
    
    def get_job_upload_status(self, job_id: str) -> Optional[Dict]:
        """Get upload status for a specific job"""
        # Check queue first
        queue = self._load_queue()
        for item in queue['queue']:
            if item['job_id'] == job_id:
                return {
                    'status': item['status'],
                    'queue_id': item['queue_id'],
                    'scheduled_time': item['scheduled_time']
                }
        
        # Check history
        history = self._load_history()
        for item in history['history']:
            if item['job_id'] == job_id:
                return {
                    'status': item['status'],
                    'video_id': item.get('video_id'),
                    'video_url': item.get('video_url'),
                    'uploaded_at': item.get('uploaded_at')
                }
        
        return None
    
    def _load_queue(self) -> Dict:
        """Load queue from JSON file"""
        try:
            with open(self.queue_file, 'r', encoding='utf-8') as f:
                return json.load(f)
        except Exception as e:
            print(f"Queue load error: {e}")
            return {'queue': []}
    
    def _save_queue(self, data: Dict):
        """Save queue to JSON file"""
        try:
            with open(self.queue_file, 'w', encoding='utf-8') as f:
                json.dump(data, f, ensure_ascii=False, indent=2)
        except Exception as e:
            print(f"Queue save error: {e}")
    
    def _load_history(self) -> Dict:
        """Load history from JSON file"""
        try:
            with open(self.history_file, 'r', encoding='utf-8') as f:
                return json.load(f)
        except Exception as e:
            print(f"History load error: {e}")
            return {'history': []}
    
    def _save_history(self, data: Dict):
        """Save history to JSON file"""
        try:
            with open(self.history_file, 'w', encoding='utf-8') as f:
                json.dump(data, f, ensure_ascii=False, indent=2)
        except Exception as e:
            print(f"History save error: {e}")


def main():
    """CLI test"""
    base_dir = Path(__file__).parent.parent.parent
    queue_file = base_dir / 'data' / 'upload_queue.json'
    history_file = base_dir / 'data' / 'upload_history.json'
    
    manager = QueueManager(str(queue_file), str(history_file))
    
    # Test add
    queue_id = manager.add_to_queue(
        job_id='test_job_123',
        video_path='/path/to/video.mp4',
        channel_id='UCxxxxxx',
        scheduled_time=datetime.now(timezone.utc).isoformat(),
        metadata={
            'title': 'Test Video',
            'description': 'Test description',
            'tags': ['test']
        }
    )
    
    print(f"\nAdded: {queue_id}")
    
    # Test get pending
    pending = manager.get_pending_items()
    print(f"\nPending items: {len(pending)}")
    
    # Test mark success
    # manager.mark_success(queue_id, 'video_123', 'https://youtube.com/shorts/video_123')
    
    print("\n✅ Queue manager test completed")


if __name__ == '__main__':
    main()
