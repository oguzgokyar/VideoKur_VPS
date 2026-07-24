"""
TikTok Video Uploader
Handles video upload to TikTok using Content Posting API
"""
import os
import sys
import json
import time
from pathlib import Path
from typing import Optional, Dict, List

# Add parent paths
sys.path.insert(0, str(Path(__file__).parent.parent.parent))

try:
    import requests
except ImportError:
    print("HATA: requests kütüphanesi bulunamadı!")

from social.base import BaseSocialUploader, UploadResult

try:
    from social.tiktok.auth import TikTokAuth
except ImportError:
    from auth import TikTokAuth


class TikTokUploader(BaseSocialUploader):
    """
    TikTok Video Uploader using Content Posting API
    
    Note: Requires approved TikTok Developer application with
    Content Posting API access.
    """
    
    PLATFORM_NAME = "tiktok"
    MAX_VIDEO_DURATION = 60  # seconds for regular uploads
    MAX_VIDEO_SIZE = 72 * 1024 * 1024  # 72MB
    SUPPORTED_FORMATS = ['mp4', 'webm']
    
    # TikTok API endpoints
    UPLOAD_INIT_URL = "https://open.tiktokapis.com/v2/post/publish/video/init/"
    UPLOAD_STATUS_URL = "https://open.tiktokapis.com/v2/post/publish/status/fetch/"
    
    def __init__(self, credentials_dir: str):
        """
        Initialize TikTok uploader
        
        Args:
            credentials_dir: Directory containing TikTok credentials
        """
        super().__init__(credentials_dir)
        self.auth = TikTokAuth(str(self.credentials_dir))
    
    def authenticate(self, account_id: Optional[str] = None) -> bool:
        """
        Check authentication status or trigger OAuth flow
        
        Returns:
            True if authenticated
        """
        if self.auth.is_authenticated():
            self._authenticated = True
            return True
        
        # Check if config exists
        if not self.auth._config.get('client_key'):
            self._log("TikTok credentials yapılandırılmamış!", error=True)
            self._log("Önce TikTok Developer başvurusu yapın ve credentials ekleyin.")
            return False
        
        # Trigger OAuth flow
        return self.auth.authenticate()
    
    def upload_video(
        self,
        video_path: str,
        caption: str,
        hashtags: List[str] = None,
        account_id: Optional[str] = None,
        privacy_level: str = "PUBLIC_TO_EVERYONE",
        disable_duet: bool = False,
        disable_stitch: bool = False,
        disable_comment: bool = False,
        **kwargs
    ) -> UploadResult:
        """
        Upload video to TikTok
        
        Args:
            video_path: Path to video file
            caption: Video caption
            hashtags: List of hashtags
            account_id: Not used for TikTok (single account)
            privacy_level: PUBLIC_TO_EVERYONE, MUTUAL_FOLLOW_FRIENDS, SELF_ONLY
            disable_duet: Disable duet feature
            disable_stitch: Disable stitch feature
            disable_comment: Disable comments
            
        Returns:
            UploadResult
        """
        # Validate video
        is_valid, error = self.validate_video(video_path)
        if not is_valid:
            return UploadResult(
                success=False,
                platform=self.PLATFORM_NAME,
                error=error
            )
        
        # Check authentication
        access_token = self.auth.get_access_token()
        if not access_token:
            return UploadResult(
                success=False,
                platform=self.PLATFORM_NAME,
                error="Kimlik doğrulama gerekli. authenticate() çağırın."
            )
        
        # Build caption with hashtags
        full_caption = caption
        if hashtags:
            hashtag_str = self.format_hashtags(hashtags)
            if len(full_caption) + len(hashtag_str) + 2 <= 2200:
                full_caption = f"{caption}\n\n{hashtag_str}"
        
        self._log(f"Video yükleniyor: {os.path.basename(video_path)}")
        self._log(f"Caption: {full_caption[:50]}...")
        
        try:
            # Step 1: Initialize upload
            init_result = self._init_upload(
                access_token=access_token,
                video_path=video_path,
                caption=full_caption,
                privacy_level=privacy_level,
                disable_duet=disable_duet,
                disable_stitch=disable_stitch,
                disable_comment=disable_comment
            )
            
            if not init_result.get('success'):
                return UploadResult(
                    success=False,
                    platform=self.PLATFORM_NAME,
                    error=init_result.get('error', 'Upload initialization failed'),
                    error_code=init_result.get('error_code')
                )
            
            publish_id = init_result.get('publish_id')
            upload_url = init_result.get('upload_url')
            
            # Step 2: Upload video file
            if upload_url:
                upload_success = self._upload_video_file(upload_url, video_path)
                if not upload_success:
                    return UploadResult(
                        success=False,
                        platform=self.PLATFORM_NAME,
                        error="Video dosyası yüklenemedi"
                    )
            
            # Step 3: Wait for processing and get result
            result = self._wait_for_publish(access_token, publish_id)
            
            if result.get('success'):
                video_id = result.get('video_id', publish_id)
                video_url = f"https://www.tiktok.com/@user/video/{video_id}"
                
                self._log(f"✅ Yükleme başarılı! Video ID: {video_id}")
                
                return UploadResult(
                    success=True,
                    platform=self.PLATFORM_NAME,
                    post_id=video_id,
                    post_url=video_url,
                    uploaded_at=self._get_timestamp(),
                    metadata={
                        'caption': full_caption,
                        'privacy_level': privacy_level
                    }
                )
            else:
                return UploadResult(
                    success=False,
                    platform=self.PLATFORM_NAME,
                    error=result.get('error', 'Publishing failed'),
                    error_code=result.get('error_code')
                )
                
        except Exception as e:
            self._log(f"Upload hatası: {e}", error=True)
            return UploadResult(
                success=False,
                platform=self.PLATFORM_NAME,
                error=str(e)
            )
    
    def _init_upload(
        self,
        access_token: str,
        video_path: str,
        caption: str,
        privacy_level: str,
        disable_duet: bool,
        disable_stitch: bool,
        disable_comment: bool
    ) -> Dict:
        """Initialize video upload with TikTok API"""
        
        file_size = os.path.getsize(video_path)
        
        # Prepare request
        headers = {
            'Authorization': f'Bearer {access_token}',
            'Content-Type': 'application/json'
        }
        
        payload = {
            'post_info': {
                'title': caption[:150],  # TikTok title limit
                'privacy_level': privacy_level,
                'disable_duet': disable_duet,
                'disable_stitch': disable_stitch,
                'disable_comment': disable_comment
            },
            'source_info': {
                'source': 'FILE_UPLOAD',
                'video_size': file_size,
                'chunk_size': file_size,  # Single chunk upload
                'total_chunk_count': 1
            }
        }
        
        try:
            response = requests.post(
                self.UPLOAD_INIT_URL,
                headers=headers,
                json=payload
            )
            
            data = response.json()
            
            if data.get('error', {}).get('code') == 'ok':
                return {
                    'success': True,
                    'publish_id': data.get('data', {}).get('publish_id'),
                    'upload_url': data.get('data', {}).get('upload_url')
                }
            else:
                error = data.get('error', {})
                return {
                    'success': False,
                    'error': error.get('message', 'Unknown error'),
                    'error_code': error.get('code')
                }
                
        except Exception as e:
            return {
                'success': False,
                'error': str(e)
            }
    
    def _upload_video_file(self, upload_url: str, video_path: str) -> bool:
        """Upload video file to TikTok upload URL"""
        
        try:
            with open(video_path, 'rb') as f:
                video_data = f.read()
            
            headers = {
                'Content-Type': 'video/mp4',
                'Content-Length': str(len(video_data))
            }
            
            response = requests.put(
                upload_url,
                headers=headers,
                data=video_data
            )
            
            return response.status_code in [200, 201]
            
        except Exception as e:
            self._log(f"Video upload hatası: {e}", error=True)
            return False
    
    def _wait_for_publish(
        self,
        access_token: str,
        publish_id: str,
        max_wait: int = 300,
        check_interval: int = 5
    ) -> Dict:
        """Wait for video to finish processing"""
        
        headers = {
            'Authorization': f'Bearer {access_token}',
            'Content-Type': 'application/json'
        }
        
        elapsed = 0
        while elapsed < max_wait:
            try:
                response = requests.post(
                    self.UPLOAD_STATUS_URL,
                    headers=headers,
                    json={'publish_id': publish_id}
                )
                
                data = response.json()
                status = data.get('data', {}).get('status')
                
                if status == 'PUBLISH_COMPLETE':
                    return {
                        'success': True,
                        'video_id': data.get('data', {}).get('video_id', publish_id)
                    }
                elif status in ['FAILED', 'PUBLISH_FAILED']:
                    return {
                        'success': False,
                        'error': data.get('data', {}).get('fail_reason', 'Publishing failed')
                    }
                
                # Still processing
                self._log(f"İşleniyor... ({elapsed}s)", error=False)
                time.sleep(check_interval)
                elapsed += check_interval
                
            except Exception as e:
                self._log(f"Status check hatası: {e}", error=True)
                time.sleep(check_interval)
                elapsed += check_interval
        
        return {
            'success': False,
            'error': 'Timeout waiting for video processing'
        }
    
    def get_account_info(self, account_id: Optional[str] = None) -> Optional[Dict]:
        """Get TikTok account information"""
        return self.auth.get_user_info()


def main():
    """CLI for TikTok upload"""
    
    print("TikTok Video Uploader")
    print("=" * 50)
    print("\n⚠️  NOT: Bu modül TikTok Content Posting API kullanır.")
    print("API erişimi için developers.tiktok.com'dan başvuru yapın.\n")
    
    if len(sys.argv) < 3:
        print("Kullanım: python uploader.py <video_path> <caption>")
        print("\nÖnce credentials ayarlayın:")
        print("  from social.tiktok.auth import TikTokAuth")
        print("  auth = TikTokAuth('path/to/creds')")
        print("  auth.save_config('CLIENT_KEY', 'CLIENT_SECRET')")
        print("  auth.authenticate()")
        sys.exit(1)
    
    base_dir = Path(__file__).parent.parent.parent.parent
    creds_dir = base_dir / 'data' / 'social_credentials' / 'tiktok'
    
    uploader = TikTokUploader(str(creds_dir))
    
    video_path = sys.argv[1]
    caption = sys.argv[2]
    hashtags = sys.argv[3].split(',') if len(sys.argv) > 3 else ['fyp', 'viral']
    
    result = uploader.upload_video(
        video_path=video_path,
        caption=caption,
        hashtags=hashtags
    )
    
    if result.success:
        print(f"\n✅ Yükleme başarılı!")
        print(f"Video ID: {result.post_id}")
        print(f"URL: {result.post_url}")
    else:
        print(f"\n❌ Yükleme başarısız: {result.error}")
        sys.exit(1)


if __name__ == '__main__':
    main()
