"""
Multi-Platform Social Media Queue Manager
Manages upload queue for all social platforms
"""
import json
import os
from pathlib import Path
from typing import Dict, List, Optional
from datetime import datetime, timezone
import uuid


class SocialQueueManager:
    """
    Manage multi-platform upload queue with JSON file storage.
    Supports: YouTube, TikTok, Instagram, Facebook
    """
    
    PLATFORMS = ['youtube', 'tiktok', 'instagram', 'facebook']
    
    def __init__(self, data_dir: str):
        """
        Initialize queue manager
        
        Args:
            data_dir: Directory for queue data files
        """
        self.data_dir = Path(data_dir)
        self.data_dir.mkdir(parents=True, exist_ok=True)
        
        self.queue_file = self.data_dir / 'social_queue.json'
        self.history_file = self.data_dir / 'social_history.json'
        
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
        platforms: List[str],
        scheduled_time: str,
        metadata: Dict,
        platform_metadata: Dict[str, Dict] = None,
        priority: int = 0
    ) -> str:
        """
        Add item to upload queue for multiple platforms
        
        Args:
            job_id: Associated job ID
            video_path: Path to video file
            platforms: List of target platforms ['youtube', 'tiktok', 'instagram', 'facebook']
            scheduled_time: ISO format datetime string
            metadata: Base video metadata (title, description, tags)
            platform_metadata: Platform-specific metadata override
            priority: Queue priority (higher = sooner)
            
        Returns:
            Queue item ID
        """
        queue = self._load_queue()
        
        queue_id = f"social_{uuid.uuid4().hex[:16]}"
        
        # Validate platforms
        platforms = [p.lower() for p in platforms if p.lower() in self.PLATFORMS]
        if not platforms:
            raise ValueError(f"No valid platforms. Supported: {self.PLATFORMS}")
        
        # Create platform status tracking
        platform_status = {}
        for platform in platforms:
            platform_status[platform] = {
                'status': 'pending',
                'post_id': None,
                'post_url': None,
                'error': None,
                'uploaded_at': None
            }
        
        item = {
            'queue_id': queue_id,
            'job_id': job_id,
            'video_path': video_path,
            'platforms': platforms,
            'platform_status': platform_status,
            'scheduled_time': scheduled_time,
            'status': 'pending',  # Overall status
            'priority': priority,
            'metadata': metadata,
            'platform_metadata': platform_metadata or {},
            'created_at': datetime.now(timezone.utc).isoformat(),
            'retry_count': 0,
            'last_error': None
        }
        
        queue['queue'].append(item)
        self._save_queue(queue)
        
        print(f"✅ Queue'ya eklendi: {queue_id}")
        print(f"   Platformlar: {', '.join(platforms)}")
        return queue_id
    
    def get_pending_items(self, platform: str = None) -> List[Dict]:
        """
        Get all pending items that are ready to upload.
        Also picks up items stuck in 'processing' state (after scheduler restart).

        Args:
            platform: Filter by specific platform (optional)

        Returns:
            List of pending queue items
        """
        queue = self._load_queue()
        now = datetime.now(timezone.utc)

        # Base dir for resolving relative video paths
        base_dir = self.data_dir.parent

        pending = []
        for item in queue['queue']:
            # Check if scheduled time has passed
            try:
                scheduled = datetime.fromisoformat(item['scheduled_time'].replace('Z', '+00:00'))
                if now < scheduled:
                    continue
            except Exception:
                pass  # If parse fails, treat as ready

            # Fix relative video_path to absolute
            video_path = item.get('video_path', '')
            if video_path and not os.path.isabs(video_path):
                item['video_path'] = str(base_dir / video_path)

            # Check if item has pending OR stuck-processing platforms
            ACTIVE_STATUSES = ('pending', 'processing')
            if platform:
                if platform in item['platform_status']:
                    if item['platform_status'][platform]['status'] in ACTIVE_STATUSES:
                        if item['platform_status'][platform]['status'] == 'processing':
                            item['platform_status'][platform]['status'] = 'pending'
                        pending.append(item)
            else:
                has_active = any(
                    ps['status'] in ACTIVE_STATUSES
                    for ps in item['platform_status'].values()
                )
                if has_active:
                    # Reset stuck 'processing' back to 'pending'
                    for ps in item['platform_status'].values():
                        if ps['status'] == 'processing':
                            ps['status'] = 'pending'
                    pending.append(item)

        # Persist any status resets we made
        self._save_queue(queue)

        # Sort by priority (high to low) then by scheduled time
        pending.sort(key=lambda x: (-x['priority'], x['scheduled_time']))

        return pending

    
    def get_item(self, queue_id: str) -> Optional[Dict]:
        """Get specific queue item"""
        queue = self._load_queue()
        for item in queue['queue']:
            if item['queue_id'] == queue_id:
                return item
        return None
    
    def mark_platform_processing(self, queue_id: str, platform: str):
        """Mark specific platform as processing"""
        queue = self._load_queue()
        for item in queue['queue']:
            if item['queue_id'] == queue_id:
                if platform in item['platform_status']:
                    item['platform_status'][platform]['status'] = 'processing'
                    self._save_queue(queue)
                return
    
    def mark_platform_success(
        self,
        queue_id: str,
        platform: str,
        post_id: str,
        post_url: str
    ):
        """Mark specific platform upload as successful"""
        queue = self._load_queue()
        
        for i, item in enumerate(queue['queue']):
            if item['queue_id'] == queue_id:
                if platform in item['platform_status']:
                    item['platform_status'][platform] = {
                        'status': 'success',
                        'post_id': post_id,
                        'post_url': post_url,
                        'error': None,
                        'uploaded_at': datetime.now(timezone.utc).isoformat()
                    }
                    
                    # Update overall status
                    self._update_overall_status(item)
                    queue['queue'][i] = item
                    self._save_queue(queue)
                    
                    # Move to history if all platforms done
                    if self._all_platforms_done(item):
                        self._move_to_history(queue_id)
                return
    
    def mark_platform_failed(
        self,
        queue_id: str,
        platform: str,
        error: str,
        retry: bool = True
    ):
        """Mark specific platform upload as failed"""
        queue = self._load_queue()
        
        for i, item in enumerate(queue['queue']):
            if item['queue_id'] == queue_id:
                if platform in item['platform_status']:
                    current_retry = item.get('retry_count', 0)
                    
                    if retry and current_retry < 3:
                        # Retry this platform
                        item['platform_status'][platform]['status'] = 'pending'
                        item['platform_status'][platform]['error'] = error
                        item['retry_count'] = current_retry + 1
                        print(f"⚠️  Retry scheduled: {queue_id}/{platform} (attempt {item['retry_count']})")
                    else:
                        # Mark as permanently failed
                        item['platform_status'][platform] = {
                            'status': 'failed',
                            'post_id': None,
                            'post_url': None,
                            'error': error,
                            'uploaded_at': None
                        }
                        print(f"❌ Upload failed: {queue_id}/{platform} - {error}")
                    
                    self._update_overall_status(item)
                    queue['queue'][i] = item
                    self._save_queue(queue)
                    
                    # Move to history if all platforms done
                    if self._all_platforms_done(item):
                        self._move_to_history(queue_id)
                return
    
    def _update_overall_status(self, item: Dict):
        """Update overall item status based on platform statuses"""
        statuses = [ps['status'] for ps in item['platform_status'].values()]
        
        if all(s == 'success' for s in statuses):
            item['status'] = 'success'
        elif all(s in ['success', 'failed'] for s in statuses):
            item['status'] = 'partial' if 'success' in statuses else 'failed'
        elif 'processing' in statuses:
            item['status'] = 'processing'
        else:
            item['status'] = 'pending'
    
    def _all_platforms_done(self, item: Dict) -> bool:
        """Check if all platforms are done (success or failed)"""
        return all(
            ps['status'] in ['success', 'failed']
            for ps in item['platform_status'].values()
        )
    
    def _move_to_history(self, queue_id: str):
        """Move completed item to history"""
        queue = self._load_queue()
        history = self._load_history()
        
        for i, item in enumerate(queue['queue']):
            if item['queue_id'] == queue_id:
                item['completed_at'] = datetime.now(timezone.utc).isoformat()
                history['history'].insert(0, queue['queue'].pop(i))
                break
        
        # Keep only last 200 items
        history['history'] = history['history'][:200]
        
        self._save_queue(queue)
        self._save_history(history)
    
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
    
    def get_all_scheduled(self) -> List[Dict]:
        """Get all scheduled items"""
        queue = self._load_queue()
        return [
            item for item in queue['queue']
            if item['status'] in ['pending', 'processing']
        ]
    
    def get_history(self, limit: int = 50, platform: str = None) -> List[Dict]:
        """Get upload history, optionally filtered by platform"""
        history = self._load_history()
        items = history['history']
        
        if platform:
            items = [
                item for item in items
                if platform in item.get('platforms', [])
            ]
        
        return items[:limit]
    
    def get_job_status(self, job_id: str) -> Optional[Dict]:
        """Get upload status for a specific job across all platforms"""
        # Check queue first
        queue = self._load_queue()
        for item in queue['queue']:
            if item['job_id'] == job_id:
                return {
                    'queue_id': item['queue_id'],
                    'status': item['status'],
                    'platforms': item['platform_status'],
                    'scheduled_time': item['scheduled_time']
                }
        
        # Check history
        history = self._load_history()
        for item in history['history']:
            if item['job_id'] == job_id:
                return {
                    'queue_id': item['queue_id'],
                    'status': item['status'],
                    'platforms': item['platform_status'],
                    'completed_at': item.get('completed_at')
                }
        
        return None
    
    def get_platform_stats(self) -> Dict:
        """Get statistics by platform"""
        history = self._load_history()
        
        stats = {platform: {'success': 0, 'failed': 0} for platform in self.PLATFORMS}
        
        for item in history['history']:
            for platform, ps in item.get('platform_status', {}).items():
                if ps['status'] == 'success':
                    stats[platform]['success'] += 1
                elif ps['status'] == 'failed':
                    stats[platform]['failed'] += 1
        
        return stats
    
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
    from datetime import timedelta
    
    base_dir = Path(__file__).parent.parent.parent
    data_dir = base_dir / 'data'
    
    manager = SocialQueueManager(str(data_dir))
    
    print("Social Queue Manager Test")
    print("=" * 50)
    
    # Test add
    queue_id = manager.add_to_queue(
        job_id='test_job_123',
        video_path='/path/to/video.mp4',
        platforms=['youtube', 'tiktok', 'instagram'],
        scheduled_time=datetime.now(timezone.utc).isoformat(),
        metadata={
            'title': 'Test Video',
            'description': 'Test description',
            'tags': ['test', 'video']
        },
        platform_metadata={
            'tiktok': {
                'caption': 'TikTok specific caption 🔥',
                'hashtags': ['fyp', 'viral']
            },
            'instagram': {
                'caption': 'Instagram specific caption ✨',
                'hashtags': ['reels', 'instagram']
            }
        }
    )
    
    print(f"\nAdded: {queue_id}")
    
    # Test get pending
    pending = manager.get_pending_items()
    print(f"\nPending items: {len(pending)}")
    
    # Test stats
    stats = manager.get_platform_stats()
    print(f"\nPlatform stats: {stats}")
    
    print("\n✅ Social queue manager test completed")


if __name__ == '__main__':
    main()
