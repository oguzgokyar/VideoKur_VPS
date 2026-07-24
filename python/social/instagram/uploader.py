"""
Instagram Reels Uploader
Handles video upload to Instagram using Graph API
"""
import os
import sys
import json
import time
import collections
from pathlib import Path
from typing import Optional, Dict, List
from urllib.parse import quote

# Add parent paths
sys.path.insert(0, str(Path(__file__).parent.parent.parent))

# Python 3.10+ compatibility shim for legacy boto3/botocore stacks
if not hasattr(collections, 'Callable'):
    collections.Callable = collections.abc.Callable

try:
    import requests
except ImportError:
    print("HATA: requests kütüphanesi bulunamadı!")

try:
    import boto3
except ImportError:
    boto3 = None

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
        self.data_dir = self.credentials_dir.parent.parent
        self.config_file = self.data_dir / 'config.json'
    
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
            if video_path.startswith('http'):
                return self._publish_from_video_url(
                    ig_user_id=ig_user_id,
                    access_token=access_token,
                    video_url=video_path,
                    caption=full_caption,
                    share_to_feed=share_to_feed,
                    account=account
                )
            else:
                return self._upload_local_video(
                    ig_user_id=ig_user_id,
                    access_token=access_token,
                    video_path=video_path,
                    caption=full_caption,
                    share_to_feed=share_to_feed,
                    account=account
                )
        except Exception as e:
            self._log(f"Upload hatası: {e}", error=True)
            return UploadResult(
                success=False,
                platform=self.PLATFORM_NAME,
                error=str(e)
            )

    def _publish_from_video_url(
        self,
        ig_user_id: str,
        access_token: str,
        video_url: str,
        caption: str,
        share_to_feed: bool,
        account: Optional[Dict] = None
    ) -> UploadResult:
        """Publish an Instagram reel from a publicly accessible video URL."""
        container_result = self._create_container(
            ig_user_id=ig_user_id,
            access_token=access_token,
            video_url=video_url,
            caption=caption,
            share_to_feed=share_to_feed
        )

        if not container_result.get('success'):
            return UploadResult(
                success=False,
                platform=self.PLATFORM_NAME,
                error=container_result.get('error', 'Container creation failed')
            )

        container_id = container_result['container_id']
        if not self._wait_for_processing(container_id, access_token):
            return UploadResult(
                success=False,
                platform=self.PLATFORM_NAME,
                error="Video işleme zaman aşımı"
            )

        publish_result = self._publish_media(ig_user_id, container_id, access_token)
        if not publish_result.get('success'):
            return UploadResult(
                success=False,
                platform=self.PLATFORM_NAME,
                error=publish_result.get('error', 'Publishing failed')
            )

        media_id = publish_result['media_id']
        permalink = self._get_permalink(media_id, access_token)
        self._log(f"✅ Yükleme başarılı! Media ID: {media_id}")

        return UploadResult(
            success=True,
            platform=self.PLATFORM_NAME,
            post_id=media_id,
            post_url=permalink,
            uploaded_at=self._get_timestamp(),
            metadata={
                'caption': caption,
                'account': (account or {}).get('username'),
                'share_to_feed': share_to_feed,
                'video_url': video_url
            }
        )
    
    def _upload_local_video(
        self,
        ig_user_id: str,
        access_token: str,
        video_path: str,
        caption: str,
        share_to_feed: bool,
        account: Optional[Dict] = None
    ) -> UploadResult:
        """Stage local file to object storage (S3/R2), then publish using public URL."""
        stage = self._stage_video_to_object_storage(video_path)
        if not stage.get('success'):
            return UploadResult(
                success=False,
                platform=self.PLATFORM_NAME,
                error=stage.get('error', 'Video staging başarısız')
            )

        video_url = stage['video_url']
        cleanup_enabled = bool(stage.get('cleanup_after_upload', True))
        try:
            return self._publish_from_video_url(
                ig_user_id=ig_user_id,
                access_token=access_token,
                video_url=video_url,
                caption=caption,
                share_to_feed=share_to_feed,
                account=account
            )
        finally:
            if cleanup_enabled:
                self._cleanup_staged_video(
                    bucket=stage.get('bucket'),
                    object_key=stage.get('object_key')
                )

    def _stage_video_to_object_storage(self, video_path: str) -> Dict:
        """
        Upload local file to S3/R2 and return public URL.
        Config keys (data/config.json):
          socialStaging.enabled = true
          socialStaging.provider = "s3" | "r2"
          socialStaging.bucket
          socialStaging.region
          socialStaging.endpointUrl (R2 için zorunlu)
          socialStaging.accessKeyId
          socialStaging.secretAccessKey
          socialStaging.publicBaseUrl (R2 için önerilen/fiilen gerekli)
          socialStaging.prefix
          socialStaging.cleanupAfterUpload (true/false)
        """
        cfg = self._load_staging_config()
        if not cfg.get('enabled'):
            return {
                'success': False,
                'error': "Yerel dosya için socialStaging etkin değil. data/config.json içinde socialStaging.enabled=true ayarlayın."
            }

        if boto3 is None:
            return {
                'success': False,
                'error': "boto3 kurulu değil. S3/R2 staging için `pip install boto3` gerekli."
            }

        bucket = cfg.get('bucket')
        if not bucket:
            return {'success': False, 'error': "socialStaging.bucket eksik"}

        access_key = cfg.get('accessKeyId') or cfg.get('access_key_id')
        secret_key = cfg.get('secretAccessKey') or cfg.get('secret_access_key')
        endpoint_url = cfg.get('endpointUrl') or cfg.get('endpoint_url')
        region = cfg.get('region') or 'auto'
        provider = str(cfg.get('provider', 's3')).lower()
        prefix = str(cfg.get('prefix', 'instagram')).strip('/ ')
        cleanup_after_upload = cfg.get('cleanupAfterUpload', True)

        if not access_key or not secret_key:
            return {'success': False, 'error': "socialStaging access key/secret eksik"}
        if provider == 'r2' and not endpoint_url:
            return {'success': False, 'error': "R2 için socialStaging.endpointUrl zorunlu"}

        timestamp = int(time.time())
        file_name = Path(video_path).name
        object_key = f"{prefix}/{timestamp}_{file_name}" if prefix else f"{timestamp}_{file_name}"
        object_key = object_key.replace('\\', '/')

        try:
            client = boto3.client(
                's3',
                aws_access_key_id=access_key,
                aws_secret_access_key=secret_key,
                endpoint_url=endpoint_url,
                region_name=region
            )
            with open(video_path, 'rb') as stream:
                client.put_object(
                    Bucket=bucket,
                    Key=object_key,
                    Body=stream,
                    ContentType='video/mp4'
                )
        except Exception as e:
            return {'success': False, 'error': f"Object storage upload hatası: {e}"}

        public_base = cfg.get('publicBaseUrl') or cfg.get('public_base_url')
        if public_base:
            video_url = f"{str(public_base).rstrip('/')}/{quote(object_key, safe='/')}"
        elif provider == 's3':
            safe_key = quote(object_key, safe='/')
            if region and region != 'us-east-1':
                video_url = f"https://{bucket}.s3.{region}.amazonaws.com/{safe_key}"
            else:
                video_url = f"https://{bucket}.s3.amazonaws.com/{safe_key}"
        else:
            return {
                'success': False,
                'error': "R2 için publicBaseUrl gerekli (örn. custom domain URL)"
            }

        self._log(f"Staging başarılı: {video_url[:80]}...")
        return {
            'success': True,
            'video_url': video_url,
            'bucket': bucket,
            'object_key': object_key,
            'cleanup_after_upload': bool(cleanup_after_upload)
        }

    def _cleanup_staged_video(self, bucket: Optional[str], object_key: Optional[str]):
        """Delete staged object after publish attempt."""
        if not bucket or not object_key:
            return

        cfg = self._load_staging_config()
        if not cfg.get('enabled'):
            return
        if boto3 is None:
            return

        access_key = cfg.get('accessKeyId') or cfg.get('access_key_id')
        secret_key = cfg.get('secretAccessKey') or cfg.get('secret_access_key')
        endpoint_url = cfg.get('endpointUrl') or cfg.get('endpoint_url')
        region = cfg.get('region') or 'auto'
        if not access_key or not secret_key:
            return

        try:
            client = boto3.client(
                's3',
                aws_access_key_id=access_key,
                aws_secret_access_key=secret_key,
                endpoint_url=endpoint_url,
                region_name=region
            )
            client.delete_object(Bucket=bucket, Key=object_key)
            self._log(f"Staged obje silindi: {object_key}")
        except Exception as e:
            self._log(f"Staged obje temizlenemedi: {e}", error=True)

    def _load_staging_config(self) -> Dict:
        """Load staging configuration from data/config.json."""
        if not self.config_file.exists():
            return {}

        try:
            with open(self.config_file, 'r', encoding='utf-8') as f:
                cfg = json.load(f) or {}
        except Exception as e:
            self._log(f"Config okunamadı: {e}", error=True)
            return {}

        staging = cfg.get('socialStaging') or cfg.get('social_staging') or cfg.get('instagramStaging') or {}
        return staging if isinstance(staging, dict) else {}
    
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
            
        except Exception as e:
            print(f"[WARN] Failed to get reel permalink, using fallback: {e}")
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
        print("\nℹ️  Instagram API public video URL gerektirir.")
        print("Yerel dosya için data/config.json içinde socialStaging ayarlanırsa otomatik S3/R2 staging kullanılır.\n")
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
