"""
Unified Queue Manager
Manages video queues directly from queues.json (no more social_queue.json)
"""
import json
import os
from pathlib import Path
from typing import Dict, List, Optional
from datetime import datetime, timezone


class UnifiedQueueManager:
    """
    Manage video queues directly from queues.json.
    Replaces the old dual-queue system (queues.json + social_queue.json).
    """
    
    PLATFORMS = ['youtube', 'tiktok', 'instagram', 'facebook']
    
    def __init__(self, data_dir: str):
        """
        Initialize unified queue manager
        
        Args:
            data_dir: Directory for queue data files
        """
        self.data_dir = Path(data_dir)
        self.data_dir.mkdir(parents=True, exist_ok=True)
        
        self.queues_file = self.data_dir / 'queues.json'
        self.history_file = self.data_dir / 'social_history.json'
        
        # Base directory for resolving video paths
        self.base_dir = self.data_dir.parent
        
        self._init_files()
    
    def _init_files(self):
        """Initialize JSON files if they don't exist"""
        if not self.queues_file.exists():
            self._save_queues({'queues': []})
        
        if not self.history_file.exists():
            self._save_history({'history': []})
    
    def _load_queues(self) -> dict:
        """Load queues.json"""
        try:
            with open(self.queues_file, 'r', encoding='utf-8') as f:
                return json.load(f)
        except Exception as e:
            print(f"❌ Error loading queues.json: {e}")
            return {'queues': []}
    
    def _save_queues(self, data: dict):
        """Save queues.json"""
        with open(self.queues_file, 'w', encoding='utf-8') as f:
            json.dump(data, f, indent=2, ensure_ascii=False)
    
    def _load_history(self) -> dict:
        """Load social_history.json"""
        try:
            with open(self.history_file, 'r', encoding='utf-8') as f:
                return json.load(f)
        except Exception:
            return {'history': []}
    
    def _save_history(self, data: dict):
        """Save social_history.json"""
        with open(self.history_file, 'w', encoding='utf-8') as f:
            json.dump(data, f, indent=2, ensure_ascii=False)
    
    def get_pending_items(self, platform: str = None) -> List[Dict]:
        """
        Get all pending items that are ready to upload.
        Reads directly from queues.json.

        Args:
            platform: Filter by specific platform (optional)

        Returns:
            List of pending queue items
        """
        queues_data = self._load_queues()
        now = datetime.now(timezone.utc)

        pending = []
        
        for queue in queues_data.get('queues', []):
            # Skip inactive queues
            if not queue.get('is_active', True):
                continue
            
            queue_id = queue.get('id', 'unknown')
            platforms = queue.get('platforms', ['youtube'])
            
            # Check if queue is in "now" mode (immediate publish)
            platform_settings = queue.get('platform_settings', {})
            is_immediate_mode = False
            for plat, settings in platform_settings.items():
                if settings.get('scheduleType') == 'now':
                    is_immediate_mode = True
                    break
            
            for video in queue.get('videos', []):
                # For "now" mode: process immediately regardless of scheduled_time
                if is_immediate_mode:
                    # Check if not already processing/completed
                    platform_status = video.get('platform_status', {})
                    has_pending = False
                    for plat in platforms:
                        status_obj = platform_status.get(plat, {})
                        if isinstance(status_obj, dict):
                            status = status_obj.get('status', 'pending')
                        else:
                            status = status_obj
                        if status in ('pending', 'processing'):
                            has_pending = True
                            break
                    
                    if not has_pending:
                        continue  # Skip if all completed
                    
                    # Process immediately (bypass scheduled_time check)
                    print(f"⚡ HEMEN PAYLAŞ: {video.get('job_id')} (scheduleType=now)")
                else:
                    # Normal mode: Check if scheduled time has passed
                    scheduled_time_str = video.get('scheduled_time')
                    if not scheduled_time_str:
                        continue  # No scheduled time, skip
                    
                    try:
                        scheduled = datetime.fromisoformat(scheduled_time_str.replace('Z', '+00:00'))
                        if now < scheduled:
                            continue  # Not ready yet
                    except Exception as e:
                        print(f"⚠️  Invalid scheduled_time for {video.get('job_id')}: {e}")
                        continue
                
                # Build video path
                job_id = video.get('job_id')
                video_path = str(self.base_dir / 'output' / job_id / 'final_video.mp4')
                
                # Check if video file exists
                if not os.path.exists(video_path):
                    print(f"⚠️  Video file not found: {video_path}")
                    continue
                
                # Check platform status
                platform_status = video.get('platform_status', {})
                
                # Determine which platforms are pending
                pending_platforms = []
                for plat in platforms:
                    # Filter by platform if specified
                    if platform and plat != platform:
                        continue
                    
                    status_obj = platform_status.get(plat, {})
                    
                    # Handle both dict and string status
                    if isinstance(status_obj, dict):
                        status = status_obj.get('status', 'pending')
                    else:
                        status = status_obj
                    
                    # Include pending and processing (stuck items after restart)
                    if status in ('pending', 'processing'):
                        pending_platforms.append(plat)
                        
                        # Reset processing to pending (after scheduler restart)
                        if status == 'processing':
                            if isinstance(platform_status[plat], dict):
                                platform_status[plat]['status'] = 'pending'
                            else:
                                platform_status[plat] = 'pending'
                
                if not pending_platforms:
                    continue  # All platforms completed or no pending ones
                
                # Create queue item structure (compatible with social scheduler)
                item = {
                    'queue_id': f"{queue_id}_{job_id}",  # Unique identifier
                    'original_queue_id': queue_id,
                    'job_id': job_id,
                    'video_path': video_path,
                    'platforms': pending_platforms,
                    'platform_status': {
                        p: platform_status.get(p, {'status': 'pending'}) 
                        for p in pending_platforms
                    },
                    'scheduled_time': video.get('scheduled_time', now.isoformat()),
                    'status': video.get('status', 'queued'),
                    'priority': -(video.get('position', 999)),  # Lower position = higher priority
                    'metadata': {
                        'title': '',  # Will be loaded from job.json
                        'description': '',
                        'tags': []
                    },
                    'platform_metadata': {},
                    'created_at': video.get('added_at'),
                    'retry_count': video.get('retry_count', 0),
                    'last_error': video.get('last_error'),
                    '_video_ref': video,  # Reference to original video object for updates
                    '_queue_ref': queue,   # Reference to original queue object
                    '_immediate_mode': is_immediate_mode  # Flag for immediate publishing
                }
                
                pending.append(item)
        
        # Sort by priority (high to low) and scheduled time
        # Immediate mode items go first
        pending.sort(key=lambda x: (
            0 if x.get('_immediate_mode') else 1,  # Immediate first
            x['priority'],
            x['scheduled_time']
        ))
        
        return pending
    
    def update_item_status(
        self,
        job_id: str,
        platform: str,
        status: str,
        post_id: str = None,
        post_url: str = None,
        error: str = None
    ):
        """
        Update platform status for a video in queues.json
        
        Args:
            job_id: Job ID
            platform: Platform name
            status: New status (pending/processing/success/failed)
            post_id: Platform post ID (optional)
            post_url: Platform post URL (optional)
            error: Error message if failed (optional)
        """
        queues_data = self._load_queues()
        updated = False
        
        for queue in queues_data.get('queues', []):
            for video in queue.get('videos', []):
                if video.get('job_id') == job_id:
                    # Update platform status
                    if 'platform_status' not in video:
                        video['platform_status'] = {}
                    
                    if platform not in video['platform_status']:
                        video['platform_status'][platform] = {}
                    
                    # Ensure it's a dict
                    if not isinstance(video['platform_status'][platform], dict):
                        video['platform_status'][platform] = {'status': video['platform_status'][platform]}
                    
                    video['platform_status'][platform]['status'] = status
                    
                    if post_id:
                        video['platform_status'][platform]['post_id'] = post_id
                    if post_url:
                        video['platform_status'][platform]['post_url'] = post_url
                    if error:
                        video['platform_status'][platform]['error'] = error
                        video['last_error'] = error
                        video['retry_count'] = video.get('retry_count', 0) + 1
                    
                    video['platform_status'][platform]['uploaded_at'] = datetime.now(timezone.utc).isoformat()
                    
                    updated = True
                    break
            
            if updated:
                break
        
        if updated:
            self._save_queues(queues_data)
            print(f"✅ Updated {job_id} → {platform}: {status}")
        else:
            print(f"⚠️  Video not found in queues: {job_id}")
    
    def move_to_history(self, job_id: str):
        """
        Move completed video to history
        (Remove from queue if all platforms are completed)
        
        Args:
            job_id: Job ID
        """
        queues_data = self._load_queues()
        history_data = self._load_history()
        
        for queue in queues_data.get('queues', []):
            new_videos = []
            for video in queue.get('videos', []):
                if video.get('job_id') == job_id:
                    # Check if all platforms are completed
                    platform_status = video.get('platform_status', {})
                    all_done = all(
                        ps.get('status') in ('success', 'failed') if isinstance(ps, dict) else ps in ('success', 'failed')
                        for ps in platform_status.values()
                    )
                    
                    if all_done:
                        # Move to history
                        history_item = {
                            **video,
                            'queue_id': queue.get('id'),
                            'queue_name': queue.get('name'),
                            'completed_at': datetime.now(timezone.utc).isoformat()
                        }
                        history_data.setdefault('history', []).append(history_item)
                        print(f"📦 Moved to history: {job_id}")
                    else:
                        # Keep in queue (not all platforms done)
                        new_videos.append(video)
                else:
                    new_videos.append(video)
            
            queue['videos'] = new_videos
        
        self._save_queues(queues_data)
        self._save_history(history_data)
    
    def mark_platform_processing(self, queue_id: str, platform: str, original_queue_id: str = None, job_id: str = None):
        """Mark platform as processing for a queue item"""
        data = self._load_queues()
        
        # Use provided original_queue_id if available, otherwise try to extract it
        if not original_queue_id:
            original_queue_id = queue_id
            if '_' in queue_id:
                original_queue_id = queue_id.rsplit('_', 1)[0]
        
        for queue in data.get('queues', []):
            if queue['id'] == original_queue_id:
                for video in queue.get('videos', []):
                    if job_id and video.get('job_id') != job_id:
                        continue
                    
                    if 'platform_status' not in video:
                        video['platform_status'] = {}
                    
                    if not isinstance(video['platform_status'], dict):
                        video['platform_status'] = {}
                    
                    video['platform_status'][platform] = {
                        'status': 'processing',
                        'updated_at': datetime.now(timezone.utc).isoformat()
                    }
                    
                    self._save_queues(data)
                    return
    
    def mark_platform_published(self, queue_id: str, platform: str, video_url: str = None, original_queue_id: str = None, job_id: str = None):
        """Mark platform as published for a queue item"""
        data = self._load_queues()
        
        # Use provided original_queue_id if available, otherwise try to extract it
        if not original_queue_id:
            original_queue_id = queue_id
            if '_' in queue_id:
                original_queue_id = queue_id.rsplit('_', 1)[0]
        
        for queue in data.get('queues', []):
            if queue['id'] == original_queue_id:
                for video in queue.get('videos', []):
                    if job_id and video.get('job_id') != job_id:
                        continue
                    
                    if 'platform_status' not in video:
                        video['platform_status'] = {}
                    
                    if not isinstance(video['platform_status'], dict):
                        video['platform_status'] = {}
                    
                    video['platform_status'][platform] = {
                        'status': 'published',
                        'video_url': video_url,
                        'published_at': datetime.now(timezone.utc).isoformat()
                    }
                    
                    # Check if all enabled platforms are completed
                    platform_settings = queue.get('platform_settings', {})
                    enabled_platforms = [p for p, settings in platform_settings.items() if settings.get('enabled', False)]
                    
                    all_completed = True
                    for enabled_platform in enabled_platforms:
                        platform_status = video['platform_status'].get(enabled_platform, {})
                        if platform_status.get('status') != 'published':
                            all_completed = False
                            break
                    
                    # If all enabled platforms are published, mark video as completed
                    if all_completed:
                        video['status'] = 'completed'
                    
                    self._save_queues(data)
                    return
    
    def mark_platform_failed(self, queue_id: str, platform: str, error: str, retry: bool = True, original_queue_id: str = None, job_id: str = None):
        """Mark platform as failed for a queue item"""
        data = self._load_queues()
        
        # Use provided original_queue_id if available, otherwise try to extract it
        if not original_queue_id:
            original_queue_id = queue_id
            if '_' in queue_id:
                original_queue_id = queue_id.rsplit('_', 1)[0]
        
        for queue in data.get('queues', []):
            if queue['id'] == original_queue_id:
                for video in queue.get('videos', []):
                    if job_id and video.get('job_id') != job_id:
                        continue
                    
                    if 'platform_status' not in video:
                        video['platform_status'] = {}
                    
                    if not isinstance(video['platform_status'], dict):
                        video['platform_status'] = {}
                    
                    video['platform_status'][platform] = {
                        'status': 'failed',
                        'error': error,
                        'retry': retry,
                        'failed_at': datetime.now(timezone.utc).isoformat()
                    }
                    
                    self._save_queues(data)
                    return
    
    def get_job_status(self, job_id: str) -> Optional[Dict]:
        """
        Get current status of a job across all platforms
        
        Args:
            job_id: Job ID to check
            
        Returns:
            Dict with status info or None if not found
        """
        queues_data = self._load_queues()
        
        for queue in queues_data.get('queues', []):
            for video in queue.get('videos', []):
                if video.get('job_id') == job_id:
                    platform_status = video.get('platform_status', {})
                    
                    # Build status summary
                    platforms_summary = {}
                    for platform, status_obj in platform_status.items():
                        if isinstance(status_obj, dict):
                            platforms_summary[platform] = status_obj.get('status', 'unknown')
                        else:
                            platforms_summary[platform] = status_obj
                    
                    return {
                        'status': video.get('status', 'unknown'),
                        'platforms': platforms_summary,
                        'queue_id': queue.get('id'),
                        'scheduled_time': video.get('scheduled_time'),
                        'last_error': video.get('last_error')
                    }
        
        return None

