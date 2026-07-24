"""
Instagram Reels Uploader
Handles video upload to Instagram using Graph API
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
    from social.instagram.auth import MetaAuth
except ImportError:
    from auth import MetaAuth


class InstagramUploader(BaseSocialUploader):
    """
    Instagram Reels Uploader using Graph API
    
    Requirements:
    - Instagram Business or Creator account
    - Connected to Facebook Page
    - Meta app with instagram_content_publish permission
    """
    
    PLATFORM_NAME = "instagram"
    MAX_VIDEO_DURATION = 90  # seconds for Reels
    MAX_VIDEO_SIZE = 100 * 1024 * 1024  # 100MB
    SUPPORTED_FORMATS = ['mp4', 'mov']
    
    # Instagram Graph API
    GRAPH_URL = "https://graph.facebook.com/v18.0"
    
    def __init__(self, credentials_dir: str):
        """
        Initialize Instagram uploader
        
        Args:
            credentials_dir: Directory containing Meta credentials
        """
        super().__init__(credentials_dir)
        
        # Meta auth handles both Instagram and Facebook
        meta_creds = self.credentials_dir.parent / 'meta'
        self.auth = MetaAuth(str(meta_creds))
    
    def authenticate(self, account_id: Optional[str] = None) -> bool:
        """
        Check authentication status or trigger OAuth flow
        
        Returns:
            True if authenticated
        """
        if self.auth.is_authenticated():
            accounts = self.auth.get_instagram_accounts()
            if accounts:
                self._authenticated = True
                return True
            else:
                self._log("Instagram Business hesabı bulunamadı!", error=True)
                return False
        
        return self.auth.authenticate()
    
    def upload_video(
        self,
        video_path: str,
        caption: str,
        hashtags: List[str] = None,
        account_id: Optional[str] = None,
        share_to_feed: bool = True,
        **kwargs
    ) -> UploadResult:
        """
        Upload video as Instagram Reels
        
        Args:
            video_path: Path to video file
            caption: Post caption
            hashtags: List of hashtags
            account_id: Instagram account ID (uses first if not specified)
            share_to_feed: Also share to main feed
            
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
        
        # Get Instagram account
        accounts = self.auth.get_instagram_accounts()
        if not accounts:
            return UploadResult(
                success=False,
                platform=self.PLATFORM_NAME,
                error="Instagram Business hesabı bulunamadı"
            )
        
        # Select account
        account = None
        if account_id:
            for acc in accounts:
                if acc['id'] == account_id:
                    account = acc
                    break
        else:
            account = accounts[0]  # Use first account
        
        if not account:
            return UploadResult(
                success=False,
                platform=self.PLATFORM_NAME,
                error=f"Hesap bulunamadı: {account_id}"
            )
        
        # Get access token (use page token for posting)
        access_token = account.get('page_access_token') or self.auth.get_access_token()
        if not access_token:
            return UploadResult(
                success=False,
                platform=self.PLATFORM_NAME,
                error="Access token bulunamadı"
            )
        
        # Build caption with hashtags
        full_caption = caption
        if hashtags:
            hashtag_str = self.format_hashtags(hashtags)
            # Instagram allows up to 2200 chars
            if len(full_caption) + len(hashtag_str) + 4 <= 2200:
                full_caption = f"{caption}\n\n{hashtag_str}"
        
        ig_user_id = account['id']
        
        self._log(f"Video yükleniyor: @{account.get('username', 'unknown')}")
        self._log(f"Caption: {full_caption[:50]}...")
        
        try:
            # Step 1: Create media container (upload video URL)
            # Instagram requires video to be accessible via URL
            # For local files, we need to host temporarily or use a workaround
            
            # Check if video_path is URL or local file
            if video_path.startswith('http'):
                video_url = video_path
            else:
                # For local files, we need to upload to a temporary hosting
                # or use the resumable upload API
                self._log("Yerel dosya için resumable upload kullanılıyor...")
                return self._upload_local_video(
                    ig_user_id, access_token, video_path, 
                    full_caption, share_to_feed
                )
            
            # Create container with video URL
            container_result = self._create_container(
                ig_user_id=ig_user_id,
                access_token=access_token,
                video_url=video_url,
                caption=full_caption,
                share_to_feed=share_to_feed
            )
            
            if not container_result.get('success'):
                return UploadResult(
                    success=False,
                    platform=self.PLATFORM_NAME,
                    error=container_result.get('error', 'Container creation failed')
                )
            
            container_id = container_result['container_id']
            
            # Step 2: Wait for video processing
            if not self._wait_for_processing(container_id, access_token):
                return UploadResult(
                    success=False,
                    platform=self.PLATFORM_NAME,
                    error="Video işleme zaman aşımı"
                )
            
            # Step 3: Publish the media
            publish_result = self._publish_media(ig_user_id, container_id, access_token)
            
            if publish_result.get('success'):
                media_id = publish_result['media_id']
                # Get permalink
                permalink = self._get_permalink(media_id, access_token)
                
                self._log(f"✅ Yükleme başarılı! Media ID: {media_id}")
                
                return UploadResult(
                    success=True,
                    platform=self.PLATFORM_NAME,
                    post_id=media_id,
                    post_url=permalink,
                    uploaded_at=self._get_timestamp(),
                    metadata={
                        'caption': full_caption,
                        'account': account.get('username'),
                        'share_to_feed': share_to_feed
                    }
                )
            else:
                return UploadResult(
                    success=False,
                    platform=self.PLATFORM_NAME,
                    error=publish_result.get('error', 'Publishing failed')
                )
                
        except Exception as e:
            self._log(f"Upload hatası: {e}", error=True)
            return UploadResult(
                success=False,
                platform=self.PLATFORM_NAME,
                error=str(e)
            )
    
    def _upload_local_video(
        self,
        ig_user_id: str,
        access_token: str,
        video_path: str,
        caption: str,
        share_to_feed: bool
    ) -> UploadResult:
        """
        Upload local video file using resumable upload
        
        This is a simplified version - for production, consider using
        a file hosting service or implementing full resumable upload.
        """
        
        # For now, return an error suggesting to use URL
        # Full implementation would require:
        # 1. Start resumable upload session
        # 2. Upload video in chunks
        # 3. Create container with upload_id
        # 4. Publish
        
        self._log("Yerel dosya yükleme: Video URL gerekli", error=True)
        self._log("Video'yu bir hosting servisine yükleyin (örn: S3, GCS)")
        
        return UploadResult(
            success=False,
            platform=self.PLATFORM_NAME,
            error="Instagram API yerel dosya desteklemiyor. Video URL'i gerekli. "
                  "Video'yu bir hosting servisine yükleyip URL'ini kullanın."
        )
    
    def _create_container(
        self,
        ig_user_id: str,
        access_token: str,
        video_url: str,
        caption: str,
        share_to_feed: bool
    ) -> Dict:
        """Create media container for Reels"""
        
        try:
            response = requests.post(
                f"{self.GRAPH_URL}/{ig_user_id}/media",
                data={
                    'media_type': 'REELS',
                    'video_url': video_url,
                    'caption': caption,
                    'share_to_feed': str(share_to_feed).lower(),
                    'access_token': access_token
                }
            )
            
            data = response.json()
            
            if 'id' in data:
                return {
                    'success': True,
                    'container_id': data['id']
                }
            else:
                error = data.get('error', {})
                return {
                    'success': False,
                    'error': error.get('message', str(data))
                }
                
        except Exception as e:
            return {
                'success': False,
                'error': str(e)
            }
    
    def _wait_for_processing(
        self,
        container_id: str,
        access_token: str,
        max_wait: int = 300,
        check_interval: int = 10
    ) -> bool:
        """Wait for video to finish processing"""
        
        elapsed = 0
        while elapsed < max_wait:
            try:
                response = requests.get(
                    f"{self.GRAPH_URL}/{container_id}",
                    params={
                        'fields': 'status_code',
                        'access_token': access_token
                    }
                )
                
                data = response.json()
                status = data.get('status_code')
                
                if status == 'FINISHED':
                    return True
                elif status == 'ERROR':
                    self._log("Video işleme hatası", error=True)
                    return False
                
                self._log(f"İşleniyor... Status: {status} ({elapsed}s)")
                time.sleep(check_interval)
                elapsed += check_interval
                
            except Exception as e:
                self._log(f"Status check hatası: {e}", error=True)
                time.sleep(check_interval)
                elapsed += check_interval
        
        return False
    
    def _publish_media(
        self,
        ig_user_id: str,
        container_id: str,
        access_token: str
    ) -> Dict:
        """Publish the processed media"""
        
        try:
            response = requests.post(
                f"{self.GRAPH_URL}/{ig_user_id}/media_publish",
                data={
                    'creation_id': container_id,
                    'access_token': access_token
                }
            )
            
            data = response.json()
            
            if 'id' in data:
                return {
                    'success': True,
                    'media_id': data['id']
                }
            else:
                error = data.get('error', {})
                return {
                    'success': False,
                    'error': error.get('message', str(data))
                }
                
        except Exception as e:
            return {
                'success': False,
                'error': str(e)
            }
    
    def _get_permalink(self, media_id: str, access_token: str) -> str:
        """Get permalink for published media"""
        try:
            response = requests.get(
                f"{self.GRAPH_URL}/{media_id}",
                params={
                    'fields': 'permalink',
                    'access_token': access_token
                }
            )
            
            data = response.json()
            return data.get('permalink', f'https://www.instagram.com/reel/{media_id}/')
            
        except:
            return f'https://www.instagram.com/reel/{media_id}/'
    
    def get_account_info(self, account_id: Optional[str] = None) -> Optional[Dict]:
        """Get Instagram account information"""
        accounts = self.auth.get_instagram_accounts()
        
        if account_id:
            for acc in accounts:
                if acc['id'] == account_id:
                    return acc
            return None
        
        return accounts[0] if accounts else None


def main():
    """CLI for Instagram upload"""
    
    print("Instagram Reels Uploader")
    print("=" * 50)
    
    if len(sys.argv) < 3:
        print("\nKullanım: python uploader.py <video_url> <caption>")
        print("\n⚠️  NOT: Instagram API video URL gerektirir (yerel dosya değil)")
        print("Video'yu önce bir hosting servisine yükleyin.\n")
        print("Kurulum:")
        print("  from social.instagram.auth import MetaAuth")
        print("  auth = MetaAuth('path/to/creds')")
        print("  auth.save_config('APP_ID', 'APP_SECRET')")
        print("  auth.authenticate()")
        sys.exit(1)
    
    base_dir = Path(__file__).parent.parent.parent.parent
    creds_dir = base_dir / 'data' / 'social_credentials' / 'instagram'
    
    uploader = InstagramUploader(str(creds_dir))
    
    video_url = sys.argv[1]
    caption = sys.argv[2]
    hashtags = sys.argv[3].split(',') if len(sys.argv) > 3 else ['reels', 'instagram']
    
    result = uploader.upload_video(
        video_path=video_url,
        caption=caption,
        hashtags=hashtags
    )
    
    if result.success:
        print(f"\n✅ Yükleme başarılı!")
        print(f"Post ID: {result.post_id}")
        print(f"URL: {result.post_url}")
    else:
        print(f"\n❌ Yükleme başarısız: {result.error}")
        sys.exit(1)


if __name__ == '__main__':
    main()
