"""
Production Queue Manager
Manages sequential video production - ensures only one video is produced at a time
"""
import json
import os
import sys
from pathlib import Path
from datetime import datetime, timezone
from typing import Dict, List, Optional, Tuple


class ProductionQueueManager:
    """
    Manages production queue for sequential video processing.
    Ensures only one video is produced at a time (no parallelism).
    """
    
    def __init__(self, data_dir: str):
        """
        Initialize production queue manager
        
        Args:
            data_dir: Directory for queue data files
        """
        self.data_dir = Path(data_dir)
        self.data_dir.mkdir(parents=True, exist_ok=True)
        
        self.queue_file = self.data_dir / 'production_queue.json'
        
        # Base directory for resolving paths
        self.base_dir = self.data_dir.parent
        
        self._init_files()
    
    def _init_files(self):
        """Initialize JSON file if it doesn't exist"""
        if not self.queue_file.exists():
            self._save_queue({
                'queue': [],
                'current_job': None,
                'settings': {
                    'auto_start_next': True,
                    'max_retries': 3,
                    'retry_delay_seconds': 60
                },
                'stats': {
                    'total_queued': 0,
                    'total_processed': 0,
                    'total_completed': 0,
                    'total_failed': 0,
                    'last_started': None,
                    'last_completed': None
                },
                'metadata': {
                    'created_at': datetime.now(timezone.utc).isoformat(),
                    'last_updated': datetime.now(timezone.utc).isoformat(),
                    'version': '1.0'
                }
            })
    
    def _load_queue(self) -> dict:
        """Load production_queue.json"""
        try:
            with open(self.queue_file, 'r', encoding='utf-8') as f:
                return json.load(f)
        except Exception as e:
            print(f"❌ Error loading production_queue.json: {e}")
            return self._get_empty_queue()
    
    def _save_queue(self, data: dict):
        """Save production_queue.json"""
        data['metadata']['last_updated'] = datetime.now(timezone.utc).isoformat()
        with open(self.queue_file, 'w', encoding='utf-8') as f:
            json.dump(data, f, indent=2, ensure_ascii=False)
    
    def _get_empty_queue(self) -> dict:
        """Get empty queue structure"""
        return {
            'queue': [],
            'current_job': None,
            'settings': {
                'auto_start_next': True,
                'max_retries': 3,
                'retry_delay_seconds': 60
            },
            'stats': {
                'total_queued': 0,
                'total_processed': 0,
                'total_completed': 0,
                'total_failed': 0,
                'last_started': None,
                'last_completed': None
            },
            'metadata': {
                'created_at': datetime.now(timezone.utc).isoformat(),
                'last_updated': datetime.now(timezone.utc).isoformat(),
                'version': '1.0'
            }
        }
    
    def add_to_queue(self, job_id: str, priority: int = 0, metadata: dict = None) -> Dict:
        """
        Add a job to the production queue
        
        Args:
            job_id: Job ID to add
            priority: Priority level (higher = more important, default 0)
            metadata: Optional metadata dict
            
        Returns:
            Dict with status and position info
        """
        queue_data = self._load_queue()
        
        # Check if job already in queue
        for item in queue_data['queue']:
            if item['job_id'] == job_id:
                return {
                    'success': False,
                    'error': 'Job already in queue',
                    'position': item['position']
                }
        
        # Check if job is currently processing
        if queue_data.get('current_job') == job_id:
            return {
                'success': False,
                'error': 'Job is currently being processed'
            }
        
        # Add to queue
        new_item = {
            'job_id': job_id,
            'status': 'waiting',
            'priority': priority,
            'added_at': datetime.now(timezone.utc).isoformat(),
            'started_at': None,
            'completed_at': None,
            'retry_count': 0,
            'last_error': None,
            'metadata': metadata or {}
        }
        
        queue_data['queue'].append(new_item)
        
        # Update stats
        queue_data['stats']['total_queued'] += 1
        
        # Sort by priority (descending) and then by added_at (ascending)
        queue_data['queue'].sort(
            key=lambda x: (-x['priority'], x['added_at'])
        )
        
        # Update positions
        for i, item in enumerate(queue_data['queue'], 1):
            item['position'] = i
        
        self._save_queue(queue_data)
        
        position = next(
            (item['position'] for item in queue_data['queue'] if item['job_id'] == job_id),
            None
        )
        
        return {
            'success': True,
            'job_id': job_id,
            'position': position,
            'queue_length': len(queue_data['queue']),
            'message': f'Added to production queue at position {position}'
        }
    
    def get_next_job(self) -> Optional[Dict]:
        """
        Get the next job to process (highest priority, oldest first)
        
        Returns:
            Job dict or None if queue is empty
        """
        queue_data = self._load_queue()
        
        # Check if there's already a current job
        if queue_data.get('current_job'):
            return None
        
        # Get first waiting job
        waiting_jobs = [j for j in queue_data['queue'] if j['status'] == 'waiting']
        
        if not waiting_jobs:
            return None
        
        return waiting_jobs[0]
    
    def start_job(self, job_id: str) -> Dict:
        """
        Mark a job as started (move from queue to current_job)
        
        Args:
            job_id: Job ID to start
            
        Returns:
            Dict with success status
        """
        queue_data = self._load_queue()
        
        # Check if there's already a current job
        if queue_data.get('current_job') and queue_data['current_job'] != job_id:
            return {
                'success': False,
                'error': f"Another job is already processing: {queue_data['current_job']}"
            }
        
        # Find job in queue
        job_item = None
        for item in queue_data['queue']:
            if item['job_id'] == job_id:
                job_item = item
                break
        
        if not job_item:
            return {
                'success': False,
                'error': 'Job not found in queue'
            }
        
        # Update job status
        job_item['status'] = 'processing'
        job_item['started_at'] = datetime.now(timezone.utc).isoformat()
        
        # Set as current job
        queue_data['current_job'] = job_id
        
        # Update stats
        queue_data['stats']['total_processed'] += 1
        queue_data['stats']['last_started'] = job_item['started_at']
        
        self._save_queue(queue_data)
        
        return {
            'success': True,
            'job_id': job_id,
            'message': 'Job started'
        }
    
    def complete_job(self, job_id: str, success: bool = True, error: str = None) -> Dict:
        """
        Mark a job as completed (successful or failed)
        
        Args:
            job_id: Job ID to complete
            success: True if completed successfully, False if failed
            error: Error message if failed
            
        Returns:
            Dict with success status and next job info
        """
        queue_data = self._load_queue()
        
        # Find job in queue
        job_item = None
        job_index = None
        for i, item in enumerate(queue_data['queue']):
            if item['job_id'] == job_id:
                job_item = item
                job_index = i
                break
        
        if not job_item:
            return {
                'success': False,
                'error': 'Job not found in queue'
            }
        
        # Update job status
        completed_at = datetime.now(timezone.utc).isoformat()
        
        if success:
            job_item['status'] = 'completed'
            job_item['completed_at'] = completed_at
            queue_data['stats']['total_completed'] += 1
            queue_data['stats']['last_completed'] = completed_at
            
            # Remove from queue
            queue_data['queue'].pop(job_index)
            
        else:
            # Failed - check if should retry
            job_item['retry_count'] += 1
            job_item['last_error'] = error
            
            max_retries = queue_data['settings']['max_retries']
            
            if job_item['retry_count'] >= max_retries:
                job_item['status'] = 'failed'
                job_item['completed_at'] = completed_at
                queue_data['stats']['total_failed'] += 1
                
                # Remove from queue
                queue_data['queue'].pop(job_index)
            else:
                # Retry - move back to waiting
                job_item['status'] = 'waiting'
                job_item['started_at'] = None
        
        # Clear current job
        queue_data['current_job'] = None
        
        # Update positions
        for i, item in enumerate(queue_data['queue'], 1):
            item['position'] = i
        
        self._save_queue(queue_data)
        
        # Get next job info
        next_job = self.get_next_job()
        
        return {
            'success': True,
            'job_id': job_id,
            'status': 'completed' if success else ('retry' if job_item['retry_count'] < max_retries else 'failed'),
            'next_job': next_job['job_id'] if next_job else None,
            'queue_length': len(queue_data['queue'])
        }
    
    def get_status(self) -> Dict:
        """
        Get current production queue status
        
        Returns:
            Dict with queue status, current job, and stats
        """
        queue_data = self._load_queue()
        
        return {
            'current_job': queue_data.get('current_job'),
            'queue_length': len(queue_data['queue']),
            'queue': queue_data['queue'],
            'stats': queue_data['stats'],
            'settings': queue_data['settings']
        }
    
    def remove_from_queue(self, job_id: str) -> Dict:
        """
        Remove a job from the queue
        
        Args:
            job_id: Job ID to remove
            
        Returns:
            Dict with success status
        """
        queue_data = self._load_queue()
        
        # Check if job is currently processing
        if queue_data.get('current_job') == job_id:
            return {
                'success': False,
                'error': 'Cannot remove job that is currently processing'
            }
        
        # Find and remove job
        original_length = len(queue_data['queue'])
        queue_data['queue'] = [
            item for item in queue_data['queue'] 
            if item['job_id'] != job_id
        ]
        
        if len(queue_data['queue']) == original_length:
            return {
                'success': False,
                'error': 'Job not found in queue'
            }
        
        # Update positions
        for i, item in enumerate(queue_data['queue'], 1):
            item['position'] = i
        
        self._save_queue(queue_data)
        
        return {
            'success': True,
            'job_id': job_id,
            'message': 'Job removed from queue'
        }
    
    def clear_queue(self) -> Dict:
        """
        Clear all waiting jobs from the queue (does not affect current job)
        
        Returns:
            Dict with count of removed jobs
        """
        queue_data = self._load_queue()
        
        waiting_count = sum(1 for j in queue_data['queue'] if j['status'] == 'waiting')
        
        # Keep only processing jobs
        queue_data['queue'] = [
            item for item in queue_data['queue'] 
            if item['status'] == 'processing'
        ]
        
        # Update positions
        for i, item in enumerate(queue_data['queue'], 1):
            item['position'] = i
        
        self._save_queue(queue_data)
        
        return {
            'success': True,
            'removed_count': waiting_count,
            'message': f'Removed {waiting_count} waiting job(s) from queue'
        }
    
    def reorder_queue(self, job_id: str, new_position: int) -> Dict:
        """
        Change the position of a job in the queue
        
        Args:
            job_id: Job ID to reorder
            new_position: New position (1-indexed)
            
        Returns:
            Dict with success status
        """
        queue_data = self._load_queue()
        
        # Find job
        job_item = None
        job_index = None
        for i, item in enumerate(queue_data['queue']):
            if item['job_id'] == job_id and item['status'] == 'waiting':
                job_item = item
                job_index = i
                break
        
        if not job_item:
            return {
                'success': False,
                'error': 'Job not found or not in waiting status'
            }
        
        # Remove from current position
        queue_data['queue'].pop(job_index)
        
        # Insert at new position (adjust for 0-indexing)
        insert_index = max(0, min(new_position - 1, len(queue_data['queue'])))
        queue_data['queue'].insert(insert_index, job_item)
        
        # Update positions
        for i, item in enumerate(queue_data['queue'], 1):
            item['position'] = i
        
        self._save_queue(queue_data)
        
        return {
            'success': True,
            'job_id': job_id,
            'new_position': job_item['position'],
            'message': f'Job moved to position {job_item["position"]}'
        }


# Convenience functions
def add_job_to_queue(job_id: str, data_dir: str = None, priority: int = 0, metadata: dict = None) -> Dict:
    """Add a job to production queue"""
    if data_dir is None:
        base_dir = Path(__file__).parent.parent.parent
        data_dir = base_dir / 'data'
    
    manager = ProductionQueueManager(str(data_dir))
    return manager.add_to_queue(job_id, priority, metadata)


def get_queue_status(data_dir: str = None) -> Dict:
    """Get production queue status"""
    if data_dir is None:
        base_dir = Path(__file__).parent.parent.parent
        data_dir = base_dir / 'data'
    
    manager = ProductionQueueManager(str(data_dir))
    return manager.get_status()


if __name__ == '__main__':
    # CLI usage
    import argparse
    
    parser = argparse.ArgumentParser(description='Production Queue Manager')
    parser.add_argument('action', choices=['add', 'status', 'clear', 'remove'], help='Action to perform')
    parser.add_argument('--job-id', help='Job ID (for add/remove)')
    parser.add_argument('--priority', type=int, default=0, help='Priority (for add)')
    args = parser.parse_args()
    
    base_dir = Path(__file__).parent.parent.parent
    manager = ProductionQueueManager(str(base_dir / 'data'))
    
    if args.action == 'add':
        if not args.job_id:
            print("Error: --job-id required for add action")
            sys.exit(1)
        result = manager.add_to_queue(args.job_id, args.priority)
        print(json.dumps(result, indent=2))
    
    elif args.action == 'status':
        result = manager.get_status()
        print(json.dumps(result, indent=2))
    
    elif args.action == 'clear':
        result = manager.clear_queue()
        print(json.dumps(result, indent=2))
    
    elif args.action == 'remove':
        if not args.job_id:
            print("Error: --job-id required for remove action")
            sys.exit(1)
        result = manager.remove_from_queue(args.job_id)
        print(json.dumps(result, indent=2))
