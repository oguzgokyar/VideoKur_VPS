"""
Scheduler Error Logger
Logs scheduler errors to JSON file for UI display
"""
import json
from pathlib import Path
from datetime import datetime, timezone
from typing import Optional


class SchedulerErrorLogger:
    """Log scheduler errors to persistent storage"""
    
    def __init__(self, data_dir: str):
        """
        Initialize error logger
        
        Args:
            data_dir: Data directory path
        """
        self.data_dir = Path(data_dir)
        self.error_file = self.data_dir / 'scheduler_errors.json'
        self._init_file()
    
    def _init_file(self):
        """Initialize error log file if it doesn't exist"""
        if not self.error_file.exists():
            self._save_errors({'errors': []})
    
    def _load_errors(self) -> dict:
        """Load errors from file"""
        try:
            with open(self.error_file, 'r', encoding='utf-8') as f:
                return json.load(f)
        except Exception:
            return {'errors': []}
    
    def _save_errors(self, data: dict):
        """Save errors to file"""
        with open(self.error_file, 'w', encoding='utf-8') as f:
            json.dump(data, f, indent=2, ensure_ascii=False)
    
    def log_error(
        self,
        job_id: str,
        platform: str,
        error_type: str,
        error_message: str,
        queue_id: Optional[str] = None,
        retry_count: int = 0
    ):
        """
        Log an error
        
        Args:
            job_id: Job ID
            platform: Platform name (youtube, tiktok, etc.)
            error_type: Error type (upload_failed, auth_failed, quota_exceeded, etc.)
            error_message: Error message
            queue_id: Queue ID (optional)
            retry_count: Number of retries attempted
        """
        errors_data = self._load_errors()
        
        error_entry = {
            'id': f"error_{datetime.now().timestamp()}".replace('.', '_'),
            'job_id': job_id,
            'platform': platform,
            'queue_id': queue_id,
            'error_type': error_type,
            'error_message': error_message,
            'retry_count': retry_count,
            'timestamp': datetime.now(timezone.utc).isoformat(),
            'resolved': False
        }
        
        errors_data['errors'].append(error_entry)
        
        # Keep only last 100 errors
        if len(errors_data['errors']) > 100:
            errors_data['errors'] = errors_data['errors'][-100:]
        
        self._save_errors(errors_data)
        
        print(f"📝 Error logged: {job_id} → {platform}: {error_message}")
    
    def resolve_error(self, job_id: str, platform: str):
        """
        Mark errors as resolved for a job+platform
        
        Args:
            job_id: Job ID
            platform: Platform name
        """
        errors_data = self._load_errors()
        
        for error in errors_data['errors']:
            if error['job_id'] == job_id and error['platform'] == platform:
                error['resolved'] = True
                error['resolved_at'] = datetime.now(timezone.utc).isoformat()
        
        self._save_errors(errors_data)
    
    def get_recent_errors(self, limit: int = 20, unresolved_only: bool = False) -> list:
        """
        Get recent errors
        
        Args:
            limit: Maximum number of errors to return
            unresolved_only: Only return unresolved errors
            
        Returns:
            List of error entries
        """
        errors_data = self._load_errors()
        errors = errors_data.get('errors', [])
        
        if unresolved_only:
            errors = [e for e in errors if not e.get('resolved', False)]
        
        # Return most recent first
        return sorted(errors, key=lambda x: x['timestamp'], reverse=True)[:limit]
    
    def get_errors_for_job(self, job_id: str, platform: Optional[str] = None) -> list:
        """
        Get errors for a specific job
        
        Args:
            job_id: Job ID
            platform: Platform name (optional, if None returns all platforms)
            
        Returns:
            List of error entries for the job
        """
        errors_data = self._load_errors()
        errors = errors_data.get('errors', [])
        
        result = [e for e in errors if e['job_id'] == job_id]
        
        if platform:
            result = [e for e in result if e['platform'] == platform]
        
        return sorted(result, key=lambda x: x['timestamp'], reverse=True)
    
    def clear_old_errors(self, days: int = 7):
        """
        Clear errors older than specified days
        
        Args:
            days: Number of days to keep
        """
        errors_data = self._load_errors()
        now = datetime.now(timezone.utc)
        
        new_errors = []
        for error in errors_data.get('errors', []):
            try:
                error_time = datetime.fromisoformat(error['timestamp'].replace('Z', '+00:00'))
                age = (now - error_time).days
                if age < days:
                    new_errors.append(error)
            except Exception:
                # Keep error if we can't parse timestamp
                new_errors.append(error)
        
        errors_data['errors'] = new_errors
        self._save_errors(errors_data)
        
        removed = len(errors_data.get('errors', [])) - len(new_errors)
        if removed > 0:
            print(f"🗑️  Removed {removed} old errors (>{days} days)")
