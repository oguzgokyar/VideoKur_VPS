"""
Base Social Media Uploader
Abstract base class for all social platform uploaders
"""
import os
import sys
from abc import ABC, abstractmethod
from dataclasses import dataclass, field
from typing import Optional, Dict, List
from datetime import datetime
from pathlib import Path


@dataclass
class UploadResult:
    """Standardized upload result across all platforms"""
    success: bool
    platform: str
    post_id: Optional[str] = None
    post_url: Optional[str] = None
    error: Optional[str] = None
    error_code: Optional[str] = None
    uploaded_at: Optional[str] = None
    metadata: Dict = field(default_factory=dict)
    
    def to_dict(self) -> Dict:
        return {
            'success': self.success,
            'platform': self.platform,
            'post_id': self.post_id,
            'post_url': self.post_url,
            'error': self.error,
            'error_code': self.error_code,
            'uploaded_at': self.uploaded_at,
            'metadata': self.metadata
        }


class BaseSocialUploader(ABC):
    """
    Abstract base class for social media uploaders.
    All platform-specific uploaders should inherit from this.
    """
    
    PLATFORM_NAME: str = "base"
    MAX_VIDEO_DURATION: int = 60  # seconds
    MAX_VIDEO_SIZE: int = 100 * 1024 * 1024  # bytes
    SUPPORTED_FORMATS: List[str] = ['mp4', 'mov']
    
    def __init__(self, credentials_dir: str):
        """
        Initialize uploader with credentials directory
        
        Args:
            credentials_dir: Directory containing platform credentials
        """
        self.credentials_dir = Path(credentials_dir)
        self.credentials_dir.mkdir(parents=True, exist_ok=True)
        self._authenticated = False
    
    @abstractmethod
    def authenticate(self, account_id: Optional[str] = None) -> bool:
        """
        Authenticate with the platform
        
        Args:
            account_id: Optional specific account ID
            
        Returns:
            True if authentication successful
        """
        pass
    
    @abstractmethod
    def upload_video(
        self,
        video_path: str,
        caption: str,
        hashtags: List[str] = None,
        account_id: Optional[str] = None,
        **kwargs
    ) -> UploadResult:
        """
        Upload video to platform
        
        Args:
            video_path: Path to video file
            caption: Post caption/description
            hashtags: List of hashtags (without #)
            account_id: Specific account to use
            **kwargs: Platform-specific options
            
        Returns:
            UploadResult with success/failure info
        """
        pass
    
    @abstractmethod
    def get_account_info(self, account_id: Optional[str] = None) -> Optional[Dict]:
        """
        Get account information
        
        Args:
            account_id: Account ID
            
        Returns:
            Dict with account info or None
        """
        pass
    
    def validate_video(self, video_path: str) -> tuple[bool, Optional[str]]:
        """
        Validate video file before upload
        
        Args:
            video_path: Path to video file
            
        Returns:
            Tuple of (is_valid, error_message)
        """
        if not os.path.exists(video_path):
            return False, f"Video dosyası bulunamadı: {video_path}"
        
        # Check file size
        file_size = os.path.getsize(video_path)
        if file_size > self.MAX_VIDEO_SIZE:
            max_mb = self.MAX_VIDEO_SIZE / (1024 * 1024)
            return False, f"Video çok büyük. Max: {max_mb}MB"
        
        # Check format
        ext = os.path.splitext(video_path)[1].lower().lstrip('.')
        if ext not in self.SUPPORTED_FORMATS:
            return False, f"Desteklenmeyen format: {ext}. Desteklenen: {', '.join(self.SUPPORTED_FORMATS)}"
        
        return True, None
    
    def format_hashtags(self, hashtags: List[str]) -> str:
        """
        Format hashtags for the platform
        
        Args:
            hashtags: List of hashtags
            
        Returns:
            Formatted hashtag string
        """
        if not hashtags:
            return ""
        
        formatted = []
        for tag in hashtags:
            tag = tag.strip()
            if not tag.startswith('#'):
                tag = f'#{tag}'
            formatted.append(tag)
        
        return ' '.join(formatted)
    
    def _get_timestamp(self) -> str:
        """Get current UTC timestamp in ISO format"""
        return datetime.utcnow().isoformat() + 'Z'
    
    def _log(self, message: str, error: bool = False):
        """Log message to stderr"""
        stream = sys.stderr
        prefix = "❌" if error else "📱"
        print(f"{prefix} [{self.PLATFORM_NAME}] {message}", file=stream)
