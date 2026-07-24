"""
YouTube Video Uploader Module
Handles video upload, metadata setting, and retry logic
"""
import os
import sys
import json
import time
from pathlib import Path
from typing import Optional, Dict
from datetime import datetime

# Add parent directory to path for standalone execution
if __name__ == '__main__':
    sys.path.insert(0, str(Path(__file__).parent.parent))

try:
    from googleapiclient.http import MediaFileUpload
    from googleapiclient.errors import HttpError
except ImportError:
    print("HATA: Google API kütüphaneleri bulunamadı!")
    sys.exit(1)

# Import auth module
try:
    from youtube.auth import YouTubeAuth
except ImportError:
    from auth import YouTubeAuth


class YouTubeUploader:
    """YouTube video uploader with retry logic and multi-project support"""
    
    # Resumable upload
    MAX_RETRIES = 5
    RETRIABLE_STATUS_CODES = [500, 502, 503, 504]
    QUOTA_EXCEEDED_CODES = [403]  # quotaExceeded
    
    def __init__(self, credentials_dir: str, project_id: Optional[str] = None):
        """
        Initialize uploader
        
        Args:
            credentials_dir: Directory containing credentials
            project_id: Optional project ID for multi-project setups
        """
        self.credentials_dir = credentials_dir
        self.project_id = project_id
        self.auth = YouTubeAuth(credentials_dir, project_id=project_id)
        self.service = None
        self.project_manager = None
        
        # Try to load project manager for quota tracking
        try:
            from youtube.project_manager import YouTubeProjectManager
            data_dir = Path(credentials_dir).parent
            self.project_manager = YouTubeProjectManager(str(data_dir))
        except ImportError:
            pass
    
    def upload_video(
        self,
        video_path: str,
        title: str,
        description: str,
        tags: list = None,
        category_id: str = "28",  # Science & Technology
        privacy_status: str = "public",
        notify_subscribers: bool = True,
        channel_id: Optional[str] = None,
        made_for_kids: bool = False,
        thumbnail_path: Optional[str] = None,
        publish_at: Optional[str] = None,
        playlist_id: Optional[str] = None
    ) -> Optional[Dict]:
        """
        Upload video to YouTube
        
        Args:
            video_path: Path to video file
            title: Video title (max 100 chars)
            description: Video description (max 5000 chars)
            tags: List of tags (max 500 chars total)
            category_id: YouTube category ID
            privacy_status: public, private, or unlisted
            notify_subscribers: Send notification to subscribers
            channel_id: Specific channel ID
            made_for_kids: COPPA compliance
            thumbnail_path: Path to custom thumbnail image (optional)
            publish_at: ISO 8601 datetime for scheduled publishing (e.g., '2024-12-31T15:00:00Z')
                        Requires privacy_status='private'. YouTube will auto-publish at this time.
            playlist_id: YouTube playlist ID to add video to (optional)
            
        Returns:
            Dict with video_id, video_url, and status or None
        """
        if not os.path.exists(video_path):
            print(f"HATA: Video dosyası bulunamadı: {video_path}", file=sys.stderr)
            return None
        
        # Get authenticated service
        self.service = self.auth.build_service(channel_id)
        if not self.service:
            print("HATA: YouTube servisi oluşturulamadı!", file=sys.stderr)
            return None
        
        # Validate and prepare metadata
        title = self._truncate(title, 100)
        description = self._truncate(description, 5000)
        
        if tags:
            tags = [self._truncate(tag, 30) for tag in tags[:30]]  # Max 30 tags
        
        # Build video body
        body = {
            'snippet': {
                'title': title,
                'description': description,
                'tags': tags or [],
                'categoryId': category_id
            },
            'status': {
                'privacyStatus': privacy_status,
                'selfDeclaredMadeForKids': made_for_kids
            }
        }
        
        # Add scheduled publishing if publish_at provided
        if publish_at:
            # publishAt requires privacy_status to be 'private'
            if privacy_status != 'private':
                print(f"⚠️  publishAt için privacyStatus 'private' olmalı, otomatik değiştirildi", file=sys.stderr)
                body['status']['privacyStatus'] = 'private'
            body['status']['publishAt'] = publish_at
        
        print(f"\n📤 Video yükleniyor: {title}", file=sys.stderr)
        print(f"📁 Dosya: {os.path.basename(video_path)}", file=sys.stderr)
        print(f"🔐 Gizlilik: {body['status']['privacyStatus']}", file=sys.stderr)
        if publish_at:
            print(f"⏰ Planlanmış: {publish_at}", file=sys.stderr)
        if thumbnail_path and os.path.exists(thumbnail_path):
            print(f"🖼️ Thumbnail: {os.path.basename(thumbnail_path)}", file=sys.stderr)
        
        try:
            # Create media upload
            media = MediaFileUpload(
                video_path,
                mimetype='video/mp4',
                resumable=True,
                chunksize=1024*1024  # 1MB chunks
            )
            
            # Create insert request
            request = self.service.videos().insert(
                part='snippet,status',
                body=body,
                media_body=media,
                notifySubscribers=notify_subscribers
            )
            
            # Execute upload with progress
            response = self._resumable_upload(request)
            
            if response:
                video_id = response['id']
                video_url = f"https://youtube.com/shorts/{video_id}"
                
                print(f"\n✅ Yükleme başarılı!", file=sys.stderr)
                print(f"🆔 Video ID: {video_id}", file=sys.stderr)
                print(f"🔗 URL: {video_url}", file=sys.stderr)
                
                # Upload custom thumbnail if provided
                thumbnail_uploaded = False
                if thumbnail_path and os.path.exists(thumbnail_path):
                    thumbnail_uploaded = self._set_thumbnail(video_id, thumbnail_path)
                    if thumbnail_uploaded:
                        print(f"🖼️ Thumbnail yüklendi!", file=sys.stderr)
                    else:
                        print(f"⚠️ Thumbnail yüklenemedi (video hala mevcut)", file=sys.stderr)
                
                # Add to playlist if provided
                playlist_added = False
                if playlist_id:
                    playlist_added = self._add_to_playlist(video_id, playlist_id)
                    if playlist_added:
                        print(f"📋 Playlist'e eklendi!", file=sys.stderr)
                    else:
                        print(f"⚠️ Playlist'e eklenemedi", file=sys.stderr)
                
                # Record successful upload for quota tracking
                if self.project_manager and self.project_id:
                    self.project_manager.record_upload(
                        self.project_id, 
                        success=True, 
                        with_thumbnail=thumbnail_uploaded
                    )
                
                return {
                    'video_id': video_id,
                    'video_url': video_url,
                    'title': title,
                    'status': 'success',
                    'thumbnail_uploaded': thumbnail_uploaded,
                    'playlist_added': playlist_added,
                    'project_id': self.project_id,
                    'uploaded_at': datetime.utcnow().isoformat() + 'Z'
                }
            else:
                return None
                
        except HttpError as e:
            error_msg = self._parse_error(e)
            print(f"\n❌ HTTP Hatası: {error_msg}", file=sys.stderr)
            
            # Check if quota exceeded
            if e.resp.status == 403 and 'quota' in error_msg.lower():
                if self.project_manager and self.project_id:
                    self.project_manager.record_quota_error(self.project_id)
                return {
                    'status': 'failed',
                    'error': error_msg,
                    'error_code': e.resp.status,
                    'quota_exceeded': True,
                    'project_id': self.project_id
                }
            
            return {
                'status': 'failed',
                'error': error_msg,
                'error_code': e.resp.status,
                'project_id': self.project_id
            }
        except Exception as e:
            print(f"\n❌ Yükleme hatası: {e}", file=sys.stderr)
            return {
                'status': 'failed',
                'error': str(e),
                'project_id': self.project_id
            }
    
    def _resumable_upload(self, request):
        """Execute resumable upload with retry logic"""
        response = None
        error = None
        retry = 0
        
        while response is None:
            try:
                status, response = request.next_chunk()
                if status:
                    progress = int(status.progress() * 100)
                    print(f"⏳ İlerleme: {progress}%", end='\r', file=sys.stderr)
                    
            except HttpError as e:
                if e.resp.status in self.RETRIABLE_STATUS_CODES:
                    error = f"HTTP {e.resp.status} hatası, tekrar deneniyor..."
                    print(f"\n⚠️  {error}", file=sys.stderr)
                else:
                    raise
                
            except Exception as e:
                error = f"Beklenmeyen hata: {e}"
                print(f"\n⚠️  {error}", file=sys.stderr)
            
            if error is not None:
                retry += 1
                if retry > self.MAX_RETRIES:
                    print(f"\n❌ Maksimum deneme sayısı aşıldı!", file=sys.stderr)
                    return None
                
                sleep_time = 2 ** retry
                print(f"🔄 {sleep_time} saniye sonra tekrar deneniyor... ({retry}/{self.MAX_RETRIES})", file=sys.stderr)
                time.sleep(sleep_time)
                error = None
        
        return response
    
    def _set_thumbnail(self, video_id: str, thumbnail_path: str) -> bool:
        """
        Set custom thumbnail for a video
        
        Args:
            video_id: YouTube video ID
            thumbnail_path: Path to thumbnail image (JPEG, PNG, GIF, etc.)
            
        Returns:
            True if successful, False otherwise
        """
        if not self.service:
            return False
        
        if not os.path.exists(thumbnail_path):
            print(f"Thumbnail dosyası bulunamadı: {thumbnail_path}", file=sys.stderr)
            return False
        
        try:
            # Determine mimetype based on file extension
            ext = os.path.splitext(thumbnail_path)[1].lower()
            mimetype_map = {
                '.jpg': 'image/jpeg',
                '.jpeg': 'image/jpeg',
                '.png': 'image/png',
                '.gif': 'image/gif',
                '.bmp': 'image/bmp',
                '.webp': 'image/webp'
            }
            mimetype = mimetype_map.get(ext, 'image/jpeg')
            
            media = MediaFileUpload(thumbnail_path, mimetype=mimetype, resumable=True)
            
            request = self.service.thumbnails().set(
                videoId=video_id,
                media_body=media
            )
            
            response = request.execute()
            return 'items' in response
            
        except HttpError as e:
            error_msg = self._parse_error(e)
            print(f"Thumbnail yükleme HTTP hatası: {error_msg}", file=sys.stderr)
            return False
        except Exception as e:
            print(f"Thumbnail yükleme hatası: {e}", file=sys.stderr)
            return False
    
    def _add_to_playlist(self, video_id: str, playlist_id: str) -> bool:
        """
        Add video to a YouTube playlist
        
        Args:
            video_id: YouTube video ID
            playlist_id: YouTube playlist ID
            
        Returns:
            True if successful, False otherwise
        """
        if not self.service:
            return False
        
        try:
            body = {
                'snippet': {
                    'playlistId': playlist_id,
                    'resourceId': {
                        'kind': 'youtube#video',
                        'videoId': video_id
                    }
                }
            }
            
            request = self.service.playlistItems().insert(
                part='snippet',
                body=body
            )
            
            response = request.execute()
            return 'id' in response
            
        except HttpError as e:
            error_msg = self._parse_error(e)
            print(f"Playlist ekleme HTTP hatası: {error_msg}", file=sys.stderr)
            return False
        except Exception as e:
            print(f"Playlist ekleme hatası: {e}", file=sys.stderr)
            return False
    
    def get_video_info(self, video_id: str, channel_id: Optional[str] = None) -> Optional[Dict]:
        """
        Get video information
        
        Args:
            video_id: YouTube video ID
            channel_id: Channel ID for authentication
            
        Returns:
            Video info dict or None
        """
        service = self.auth.build_service(channel_id)
        if not service:
            return None
        
        try:
            request = service.videos().list(
                part='snippet,statistics,status',
                id=video_id
            )
            response = request.execute()
            
            if not response.get('items'):
                return None
            
            video = response['items'][0]
            stats = video.get('statistics', {})
            
            return {
                'video_id': video_id,
                'title': video['snippet']['title'],
                'description': video['snippet']['description'],
                'published_at': video['snippet']['publishedAt'],
                'view_count': int(stats.get('viewCount', 0)),
                'like_count': int(stats.get('likeCount', 0)),
                'comment_count': int(stats.get('commentCount', 0)),
                'privacy_status': video['status']['privacyStatus']
            }
            
        except Exception as e:
            print(f"Video bilgisi alma hatası: {e}", file=sys.stderr)
            return None
    
    def _truncate(self, text: str, max_length: int) -> str:
        """Truncate text to max length"""
        if not text:
            return ""
        return text[:max_length] if len(text) > max_length else text
    
    def _parse_error(self, error: HttpError) -> str:
        """Parse HTTP error message"""
        try:
            content = json.loads(error.content.decode('utf-8'))
            if 'error' in content:
                err = content['error']
                if 'errors' in err and err['errors']:
                    return err['errors'][0].get('message', str(error))
                return err.get('message', str(error))
        except:
            pass
        return str(error)


def main():
    """CLI for video upload"""
    import sys
    
    if len(sys.argv) < 4:
        print("Kullanım: python uploader.py <video_path> <title> <description> [privacy] [category] [tags] [thumbnail_path] [scheduled_time] [project_id]")
        sys.exit(1)
    
    base_dir = Path(__file__).parent.parent.parent
    creds_dir = base_dir / 'data' / 'youtube_credentials'
    
    # Parse arguments
    video_path = sys.argv[1]
    title = sys.argv[2]
    description = sys.argv[3]
    privacy_status = sys.argv[4] if len(sys.argv) > 4 else 'public'
    category_id = sys.argv[5] if len(sys.argv) > 5 else '28'
    
    # Parse tags - remove # prefix and empty strings
    if len(sys.argv) > 6:
        tags = [tag.strip().lstrip('#') for tag in sys.argv[6].split(',') if tag.strip()]
    else:
        tags = ['Shorts']
    
    # Thumbnail path (optional)
    thumbnail_path = sys.argv[7] if len(sys.argv) > 7 else None
    
    # Scheduled time (publishAt) - optional
    publish_at = sys.argv[8] if len(sys.argv) > 8 and sys.argv[8] else None
    
    # Project ID for multi-API support - optional
    project_id = sys.argv[9] if len(sys.argv) > 9 and sys.argv[9] else None
    
    # Initialize uploader with project_id
    uploader = YouTubeUploader(str(creds_dir), project_id=project_id)
    
    result = uploader.upload_video(
        video_path=video_path,
        title=title,
        description=description,
        tags=tags,
        category_id=category_id,
        privacy_status=privacy_status,
        notify_subscribers=(privacy_status == 'public'),
        thumbnail_path=thumbnail_path,
        publish_at=publish_at
    )
    
    # Output in parseable format for PHP
    if result and result.get('status') == 'success':
        print(f"Video ID: {result['video_id']}")
        print(f"URL: {result['video_url']}")
        if result.get('thumbnail_uploaded'):
            print("Thumbnail: uploaded")
        if result.get('project_id'):
            print(f"Project: {result['project_id']}")
        sys.exit(0)
    else:
        print(f"ERROR: {result.get('error', 'Unknown error')}")
        sys.exit(1)


if __name__ == '__main__':
    main()
