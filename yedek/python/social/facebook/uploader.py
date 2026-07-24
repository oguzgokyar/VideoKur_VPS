"""
Facebook Reels Uploader
Handles video upload to Facebook Pages using Graph API
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

# Reuse Meta auth from Instagram module
try:
    from social.instagram.auth import MetaAuth
except ImportError:
    sys.path.insert(0, str(Path(__file__).parent.parent))
    from instagram.auth import MetaAuth


class FacebookUploader(BaseSocialUploader):
    """
    Facebook Reels/Video Uploader using Graph API
    
    Supports:
    - Facebook Pages (not personal profiles)
    - Reels format
    - Regular video posts
    """
    
    PLATFORM_NAME = "facebook"
    MAX_VIDEO_DURATION = 240  # 4 minutes for Reels, longer for regular
    MAX_VIDEO_SIZE = 1024 * 1024 * 1024  # 1GB
    SUPPORTED_FORMATS = ['mp4', 'mov', 'avi', 'wmv']
    
    # Facebook Graph API
    GRAPH_URL = "https://graph.facebook.com/v18.0"
    
    def __init__(self, credentials_dir: str):
        """
        Initialize Facebook uploader
        
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
            pages = self.auth.get_facebook_pages()
            if pages:
                self._authenticated = True
                return True
            else:
                self._log("Facebook sayfası bulunamadı!", error=True)
                return False
        
        return self.auth.authenticate()
    
    def upload_video(
        self,
        video_path: str,
        caption: str,
        hashtags: List[str] = None,
        account_id: Optional[str] = None,
        as_reels: bool = True,
        **kwargs
    ) -> UploadResult:
        """
        Upload video to Facebook Page
        
        Args:
            video_path: Path to video file (local or URL)
            caption: Post description
            hashtags: List of hashtags
            account_id: Facebook Page ID (uses first if not specified)
            as_reels: Upload as Reels (True) or regular video (False)
            
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
        
        # Get Facebook Pages
        pages = self.auth.get_facebook_pages()
        if not pages:
            return UploadResult(
                success=False,
                platform=self.PLATFORM_NAME,
                error="Facebook sayfası bulunamadı"
            )
        
        # Select page
        page = None
        if account_id:
            for p in pages:
                if p['id'] == account_id:
                    page = p
                    break
        else:
            page = pages[0]
        
        if not page:
            return UploadResult(
                success=False,
                platform=self.PLATFORM_NAME,
                error=f"Sayfa bulunamadı: {account_id}"
            )
        
        # Get page access token
        access_token = page.get('access_token')
        if not access_token:
            # Try to get from auth
            access_token = self.auth.get_page_access_token(page['id'])
        
        if not access_token:
            return UploadResult(
                success=False,
                platform=self.PLATFORM_NAME,
                error="Page access token bulunamadı"
            )
        
        # Build caption with hashtags
        full_caption = caption
        if hashtags:
            hashtag_str = self.format_hashtags(hashtags)
            full_caption = f"{caption}\n\n{hashtag_str}"
        
        page_id = page['id']
        page_name = page.get('name', 'Unknown')
        
        self._log(f"Video yükleniyor: {page_name}")
        self._log(f"Format: {'Reels' if as_reels else 'Video'}")
        
        try:
            if as_reels:
                result = self._upload_reels(
                    page_id=page_id,
                    access_token=access_token,
                    video_path=video_path,
                    caption=full_caption
                )
            else:
                result = self._upload_video(
                    page_id=page_id,
                    access_token=access_token,
                    video_path=video_path,
                    caption=full_caption
                )
            
            if result.get('success'):
                post_id = result['post_id']
                post_url = f"https://www.facebook.com/{post_id}"
                
                self._log(f"✅ Yükleme başarılı! Post ID: {post_id}")
                
                return UploadResult(
                    success=True,
                    platform=self.PLATFORM_NAME,
                    post_id=post_id,
                    post_url=post_url,
                    uploaded_at=self._get_timestamp(),
                    metadata={
                        'caption': full_caption,
                        'page_name': page_name,
                        'as_reels': as_reels
                    }
                )
            else:
                return UploadResult(
                    success=False,
                    platform=self.PLATFORM_NAME,
                    error=result.get('error', 'Upload failed')
                )
                
        except Exception as e:
            self._log(f"Upload hatası: {e}", error=True)
            return UploadResult(
                success=False,
                platform=self.PLATFORM_NAME,
                error=str(e)
            )
    
    def _upload_reels(
        self,
        page_id: str,
        access_token: str,
        video_path: str,
        caption: str
    ) -> Dict:
        """Upload video as Facebook Reels"""
        
        # Check if local file or URL
        if video_path.startswith('http'):
            return self._upload_reels_url(page_id, access_token, video_path, caption)
        else:
            return self._upload_reels_file(page_id, access_token, video_path, caption)
    
    def _upload_reels_url(
        self,
        page_id: str,
        access_token: str,
        video_url: str,
        caption: str
    ) -> Dict:
        """Upload Reels from URL"""
        
        try:
            # Initialize Reels upload
            response = requests.post(
                f"{self.GRAPH_URL}/{page_id}/video_reels",
                data={
                    'upload_phase': 'start',
                    'access_token': access_token
                }
            )
            
            data = response.json()
            
            if 'video_id' not in data:
                return {
                    'success': False,
                    'error': data.get('error', {}).get('message', str(data))
                }
            
            video_id = data['video_id']
            
            # Upload from URL
            response = requests.post(
                f"{self.GRAPH_URL}/{video_id}",
                data={
                    'upload_phase': 'transfer',
                    'file_url': video_url,
                    'access_token': access_token
                }
            )
            
            # Finish upload
            response = requests.post(
                f"{self.GRAPH_URL}/{page_id}/video_reels",
                data={
                    'upload_phase': 'finish',
                    'video_id': video_id,
                    'description': caption,
                    'access_token': access_token
                }
            )
            
            data = response.json()
            
            if data.get('success'):
                return {
                    'success': True,
                    'post_id': video_id
                }
            else:
                return {
                    'success': False,
                    'error': data.get('error', {}).get('message', str(data))
                }
                
        except Exception as e:
            return {
                'success': False,
                'error': str(e)
            }
    
    def _upload_reels_file(
        self,
        page_id: str,
        access_token: str,
        video_path: str,
        caption: str
    ) -> Dict:
        """Upload Reels from local file"""
        
        try:
            file_size = os.path.getsize(video_path)
            
            # Initialize upload session
            response = requests.post(
                f"{self.GRAPH_URL}/{page_id}/video_reels",
                data={
                    'upload_phase': 'start',
                    'access_token': access_token
                }
            )
            
            data = response.json()
            
            if 'video_id' not in data:
                return {
                    'success': False,
                    'error': data.get('error', {}).get('message', str(data))
                }
            
            video_id = data['video_id']
            upload_url = data.get('upload_url')
            
            if not upload_url:
                # Fallback: use regular video endpoint
                return self._upload_video(page_id, access_token, video_path, caption)
            
            # Upload file
            with open(video_path, 'rb') as f:
                response = requests.post(
                    upload_url,
                    headers={
                        'Authorization': f'OAuth {access_token}',
                        'file_size': str(file_size)
                    },
                    data=f
                )
            
            if response.status_code not in [200, 201]:
                return {
                    'success': False,
                    'error': f"Upload failed: {response.status_code}"
                }
            
            # Finish upload
            response = requests.post(
                f"{self.GRAPH_URL}/{page_id}/video_reels",
                data={
                    'upload_phase': 'finish',
                    'video_id': video_id,
                    'description': caption,
                    'access_token': access_token
                }
            )
            
            data = response.json()
            
            if data.get('success') or 'video_id' in str(data):
                return {
                    'success': True,
                    'post_id': video_id
                }
            else:
                return {
                    'success': False,
                    'error': data.get('error', {}).get('message', str(data))
                }
                
        except Exception as e:
            return {
                'success': False,
                'error': str(e)
            }
    
    def _upload_video(
        self,
        page_id: str,
        access_token: str,
        video_path: str,
        caption: str
    ) -> Dict:
        """Upload regular video (not Reels)"""
        
        try:
            file_size = os.path.getsize(video_path)
            
            # For small files, use simple upload
            if file_size < 100 * 1024 * 1024:  # < 100MB
                with open(video_path, 'rb') as f:
                    response = requests.post(
                        f"{self.GRAPH_URL}/{page_id}/videos",
                        data={
                            'description': caption,
                            'access_token': access_token
                        },
                        files={
                            'source': f
                        }
                    )
                
                data = response.json()
                
                if 'id' in data:
                    return {
                        'success': True,
                        'post_id': data['id']
                    }
                else:
                    return {
                        'success': False,
                        'error': data.get('error', {}).get('message', str(data))
                    }
            
            # For larger files, use resumable upload
            return self._resumable_upload(page_id, access_token, video_path, caption)
            
        except Exception as e:
            return {
                'success': False,
                'error': str(e)
            }
    
    def _resumable_upload(
        self,
        page_id: str,
        access_token: str,
        video_path: str,
        caption: str
    ) -> Dict:
        """Resumable upload for large videos"""
        
        try:
            file_size = os.path.getsize(video_path)
            
            # Start upload session
            response = requests.post(
                f"{self.GRAPH_URL}/{page_id}/videos",
                data={
                    'upload_phase': 'start',
                    'file_size': file_size,
                    'access_token': access_token
                }
            )
            
            data = response.json()
            
            if 'upload_session_id' not in data:
                return {
                    'success': False,
                    'error': data.get('error', {}).get('message', str(data))
                }
            
            upload_session_id = data['upload_session_id']
            
            # Upload in chunks
            chunk_size = 10 * 1024 * 1024  # 10MB chunks
            offset = 0
            
            with open(video_path, 'rb') as f:
                while offset < file_size:
                    chunk = f.read(chunk_size)
                    
                    response = requests.post(
                        f"{self.GRAPH_URL}/{page_id}/videos",
                        data={
                            'upload_phase': 'transfer',
                            'upload_session_id': upload_session_id,
                            'start_offset': offset,
                            'access_token': access_token
                        },
                        files={
                            'video_file_chunk': chunk
                        }
                    )
                    
                    data = response.json()
                    offset = int(data.get('end_offset', offset + len(chunk)))
                    
                    progress = int(offset / file_size * 100)
                    self._log(f"Upload progress: {progress}%")
            
            # Finish upload
            response = requests.post(
                f"{self.GRAPH_URL}/{page_id}/videos",
                data={
                    'upload_phase': 'finish',
                    'upload_session_id': upload_session_id,
                    'description': caption,
                    'access_token': access_token
                }
            )
            
            data = response.json()
            
            if data.get('success') or 'id' in data:
                return {
                    'success': True,
                    'post_id': data.get('id', upload_session_id)
                }
            else:
                return {
                    'success': False,
                    'error': data.get('error', {}).get('message', str(data))
                }
                
        except Exception as e:
            return {
                'success': False,
                'error': str(e)
            }
    
    def get_account_info(self, account_id: Optional[str] = None) -> Optional[Dict]:
        """Get Facebook Page information"""
        pages = self.auth.get_facebook_pages()
        
        if account_id:
            for page in pages:
                if page['id'] == account_id:
                    return page
            return None
        
        return pages[0] if pages else None


def main():
    """CLI for Facebook upload"""
    
    print("Facebook Video/Reels Uploader")
    print("=" * 50)
    
    if len(sys.argv) < 3:
        print("\nKullanım: python uploader.py <video_path> <caption> [hashtags]")
        print("\nVideo local dosya veya URL olabilir.")
        print("\nKurulum:")
        print("  from social.instagram.auth import MetaAuth")
        print("  auth = MetaAuth('path/to/creds')")
        print("  auth.save_config('APP_ID', 'APP_SECRET')")
        print("  auth.authenticate()")
        sys.exit(1)
    
    base_dir = Path(__file__).parent.parent.parent.parent
    creds_dir = base_dir / 'data' / 'social_credentials' / 'facebook'
    
    uploader = FacebookUploader(str(creds_dir))
    
    video_path = sys.argv[1]
    caption = sys.argv[2]
    hashtags = sys.argv[3].split(',') if len(sys.argv) > 3 else ['reels', 'facebook']
    
    result = uploader.upload_video(
        video_path=video_path,
        caption=caption,
        hashtags=hashtags,
        as_reels=True
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
