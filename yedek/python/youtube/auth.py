"""
YouTube OAuth 2.0 Authentication Module
Handles authentication flow, token management, and refresh
Supports multiple Google Cloud projects for quota rotation
"""
import os
import sys
import json
import pickle
from pathlib import Path
from typing import Optional, Dict

try:
    from google.oauth2.credentials import Credentials
    from google_auth_oauthlib.flow import InstalledAppFlow
    from google.auth.transport.requests import Request
    from googleapiclient.discovery import build
except ImportError:
    print("HATA: Google API kütüphaneleri bulunamadı!")
    print("Yüklemek için: pip install google-auth-oauthlib google-api-python-client")
    sys.exit(1)

# YouTube Data API v3 scopes
SCOPES = [
    'https://www.googleapis.com/auth/youtube.upload',
    'https://www.googleapis.com/auth/youtube',
    'https://www.googleapis.com/auth/youtube.force-ssl'
]

class YouTubeAuth:
    """YouTube OAuth 2.0 authentication manager with multi-project support"""
    
    def __init__(self, credentials_dir: str, project_id: Optional[str] = None):
        """
        Initialize auth manager
        
        Args:
            credentials_dir: Directory containing client_secrets.json and tokens
            project_id: Optional project ID for multi-project setups
        """
        self.credentials_dir = Path(credentials_dir)
        self.credentials_dir.mkdir(parents=True, exist_ok=True)
        self.project_id = project_id
        
        # Determine client_secrets file based on project
        if project_id:
            # Multi-project mode: load from project config
            self.client_secrets_file = self._get_project_secrets_file(project_id)
        else:
            # Single project mode: use default
            self.client_secrets_file = self.credentials_dir / 'client_secrets.json'
    
    def _get_project_secrets_file(self, project_id: str) -> Path:
        """
        Get client_secrets file for a specific project from unified youtube_channels.json
        Validates that the file exists and returns error if not
        
        Args:
            project_id: Project ID (e.g., 'video-kur3')
            
        Returns:
            Path to client_secrets file
        """
        # Try unified youtube_channels.json first (NEW SYSTEM)
        channels_file = self.credentials_dir.parent / 'youtube_channels.json'
        
        if channels_file.exists():
            try:
                with open(channels_file, 'r', encoding='utf-8') as f:
                    channels_data = json.load(f)
                
                # Search through all channels and their APIs
                for channel in channels_data.get('channels', []):
                    for api in channel.get('apis', []):
                        if api.get('project_id') == project_id:
                            secrets_file = api.get('client_secrets_file', '')
                            
                            # Handle both absolute and relative paths
                            if secrets_file.startswith('youtube_credentials/'):
                                # Relative to data dir
                                full_path = self.credentials_dir / secrets_file.replace('youtube_credentials/', '')
                            else:
                                # Already relative to credentials dir
                                full_path = self.credentials_dir / secrets_file
                            
                            # ✅ VALIDATION: Check if file exists
                            if not full_path.exists():
                                error_msg = (
                                    f"❌ HATA: client_secrets dosyası bulunamadı!\n"
                                    f"   Proje: {api.get('name', project_id)}\n"
                                    f"   Kanal: {channel.get('channel_title', 'Bilinmeyen')}\n"
                                    f"   Aranan dosya: {full_path}\n"
                                    f"   Çözüm: Google Cloud Console'dan client_secrets.json dosyasını indirin\n"
                                    f"   ve '{secrets_file}' olarak kaydedin."
                                )
                                print(error_msg, file=sys.stderr)
                                raise FileNotFoundError(f"client_secrets file not found: {full_path}")
                            
                            print(f"✅ Client secrets bulundu: {secrets_file} (Unified System)")
                            return full_path
                            
            except FileNotFoundError:
                # Re-raise validation errors
                raise
            except Exception as e:
                print(f"⚠️  Unified channels config okunamadı: {e}", file=sys.stderr)
        
        # Fallback: Try legacy youtube_projects.json (OLD SYSTEM - for backward compatibility)
        projects_file = self.credentials_dir.parent / 'youtube_projects.json'
        
        if projects_file.exists():
            try:
                with open(projects_file, 'r', encoding='utf-8') as f:
                    config = json.load(f)
                
                for project in config.get('projects', []):
                    if project['id'] == project_id:
                        secrets_file = project.get('client_secrets_file', 'client_secrets.json')
                        full_path = self.credentials_dir / secrets_file
                        
                        if full_path.exists():
                            print(f"✅ Client secrets bulundu: {secrets_file} (Legacy System)")
                            return full_path
                        
            except Exception as e:
                print(f"⚠️  Legacy project config okunamadı: {e}", file=sys.stderr)
        
        # Final fallback to default
        default_path = self.credentials_dir / 'client_secrets.json'
        
        # ✅ VALIDATION: Check default file too
        if not default_path.exists():
            error_msg = (
                f"❌ HATA: client_secrets dosyası bulunamadı!\n"
                f"   Aranan project_id: {project_id}\n"
                f"   Aranan dosya: {default_path}\n"
                f"   Çözüm:\n"
                f"   1. Hesaplar sayfasından API ekleyin ve client_secrets dosyasını yükleyin\n"
                f"   2. VEYA Google Cloud Console'dan client_secrets.json dosyasını indirin"
            )
            print(error_msg, file=sys.stderr)
            raise FileNotFoundError(f"client_secrets.json not found for project: {project_id}")
        
        print(f"⚠️  Varsayılan client_secrets.json kullanılıyor")
        return default_path
    
    def get_credentials(self, channel_id: Optional[str] = None) -> Optional[Credentials]:
        """
        Get valid credentials for YouTube API
        
        Args:
            channel_id: Specific channel ID, if None uses default
            
        Returns:
            Valid Credentials object or None
        """
        # ✅ VALIDATION: Check if client_secrets file exists before proceeding
        if not self.client_secrets_file.exists():
            error_msg = (
                f"❌ HATA: client_secrets dosyası bulunamadı!\n"
                f"   Aranan dosya: {self.client_secrets_file}\n"
                f"   Proje ID: {self.project_id or 'default'}\n"
                f"   Çözüm: Google Cloud Console'dan client_secrets.json indirin."
            )
            print(error_msg, file=sys.stderr)
            return None
        
        token_file = self._get_token_file(channel_id)
        creds = None
        
        # Load existing token
        if token_file.exists():
            try:
                # First try pickle format (even if file is .json - some systems save pickle with .json extension)
                try:
                    with open(token_file, 'rb') as f:
                        creds = pickle.load(f)
                    print(f"✅ Pickle token yüklendi: {token_file.name}", file=sys.stderr)
                except (pickle.UnpicklingError, EOFError, KeyError):
                    # Not pickle, try JSON
                    with open(token_file, 'r', encoding='utf-8') as f:
                        token_data = json.load(f)
                    
                    # Build Credentials from JSON token data
                    creds = Credentials(
                        token=token_data.get('token'),
                        refresh_token=token_data.get('refresh_token'),
                        token_uri=token_data.get('token_uri', 'https://oauth2.googleapis.com/token'),
                        client_id=token_data.get('client_id'),
                        client_secret=token_data.get('client_secret'),
                        scopes=token_data.get('scopes', SCOPES)
                    )
                    print(f"✅ JSON token yüklendi: {token_file.name}", file=sys.stderr)
            except Exception as e:
                print(f"Token yükleme hatası: {e}", file=sys.stderr)
        
        # Refresh if expired
        if creds and creds.expired and creds.refresh_token:
            try:
                creds.refresh(Request())
                self._save_token(creds, channel_id)
                print("✅ Token yenilendi", file=sys.stderr)
            except Exception as e:
                error_str = str(e)
                print(f"❌ Token yenileme hatası: {e}", file=sys.stderr)
                
                # invalid_grant hatası = token revoke edilmiş veya expire olmuş
                if 'invalid_grant' in error_str:
                    print("⚠️  Token revoke edilmiş veya expire. Yeni kimlik doğrulama gerekli.", file=sys.stderr)
                    # Token dosyasını sil ve yeni auth'u tetikle
                    try:
                        token_file.unlink()
                        print(f"🔄 Eski token silindi: {token_file}", file=sys.stderr)
                    except:
                        pass
                
                creds = None
        
        if creds and creds.valid:
            return creds
        else:
            return None
    
    def authenticate(self, channel_id: Optional[str] = None) -> Optional[Credentials]:
        """
        Perform OAuth flow to get new credentials
        
        Args:
            channel_id: Channel ID to associate with token
            
        Returns:
            Valid Credentials object or None
        """
        if not self.client_secrets_file.exists():
            print(f"HATA: {self.client_secrets_file} bulunamadı!", file=sys.stderr)
            print("Google Cloud Console'dan client_secrets.json dosyasını indirin.", file=sys.stderr)
            return None
        
        try:
            flow = InstalledAppFlow.from_client_secrets_file(
                str(self.client_secrets_file),
                scopes=SCOPES
            )
            
            # Run local server for OAuth callback
            creds = flow.run_local_server(
                port=8080,
                prompt='consent',
                authorization_prompt_message='Tarayıcı açılıyor...'
            )
            
            # Save token
            self._save_token(creds, channel_id)
            print("✅ Kimlik doğrulama başarılı!", file=sys.stderr)
            
            return creds
            
        except Exception as e:
            print(f"Kimlik doğrulama hatası: {e}", file=sys.stderr)
            return None
    
    def get_or_authenticate(self, channel_id: Optional[str] = None) -> Optional[Credentials]:
        """
        Get existing credentials or authenticate if needed
        
        Args:
            channel_id: Channel ID
            
        Returns:
            Valid Credentials object or None
        """
        creds = self.get_credentials(channel_id)
        if not creds:
            print("Yeni kimlik doğrulama gerekli...", file=sys.stderr)
            creds = self.authenticate(channel_id)
        return creds
    
    def build_service(self, channel_id: Optional[str] = None):
        """
        Build YouTube API service
        
        Args:
            channel_id: Channel ID
            
        Returns:
            YouTube API service object or None
        """
        creds = self.get_or_authenticate(channel_id)
        if not creds:
            return None
        
        try:
            service = build('youtube', 'v3', credentials=creds)
            return service
        except Exception as e:
            print(f"Service oluşturma hatası: {e}", file=sys.stderr)
            return None
    
    def get_channel_info(self, service) -> Optional[Dict]:
        """
        Get authenticated user's channel information
        
        Args:
            service: YouTube API service
            
        Returns:
            Channel info dict or None
        """
        try:
            request = service.channels().list(
                part='snippet,contentDetails,statistics',
                mine=True
            )
            response = request.execute()
            
            if not response.get('items'):
                print("Kanal bulunamadı!", file=sys.stderr)
                return None
            
            channel = response['items'][0]
            return {
                'channel_id': channel['id'],
                'channel_title': channel['snippet']['title'],
                'thumbnail': channel['snippet']['thumbnails']['default']['url'],
                'subscriber_count': int(channel['statistics'].get('subscriberCount', 0)),
                'video_count': int(channel['statistics'].get('videoCount', 0)),
                'description': channel['snippet'].get('description', '')
            }
            
        except Exception as e:
            print(f"Kanal bilgisi alma hatası: {e}", file=sys.stderr)
            return None
    
    def revoke_credentials(self, channel_id: Optional[str] = None) -> bool:
        """
        Revoke and delete stored credentials
        
        Args:
            channel_id: Channel ID
            
        Returns:
            True if successful
        """
        token_file = self._get_token_file(channel_id)
        try:
            if token_file.exists():
                token_file.unlink()
                print("Token silindi", file=sys.stderr)
            return True
        except Exception as e:
            print(f"Token silme hatası: {e}", file=sys.stderr)
            return False
    
    def _get_token_file(self, channel_id: Optional[str] = None) -> Path:
        """Get token file path for channel and project
        
        Supports both legacy pickle format and new JSON token format from unified system
        """
        prefix = f"{self.project_id}_" if self.project_id else ""
        
        # First try to find existing JSON tokens from unified system
        # These have format: {project_id}_{channel_id}_{api_id}_token.json
        if self.project_id:
            import glob
            pattern = str(self.credentials_dir / f'{self.project_id}_*_token.json')
            json_tokens = glob.glob(pattern)
            if json_tokens:
                # Return first matching JSON token
                return Path(json_tokens[0])
        
        # Legacy format: {project}_{channel}_token.pickle
        if channel_id:
            pickle_path = self.credentials_dir / f'{prefix}{channel_id}_token.pickle'
            if pickle_path.exists():
                return pickle_path
                
        default_pickle = self.credentials_dir / f'{prefix}default_token.pickle'
        if default_pickle.exists():
            return default_pickle
            
        # Return default path for new token creation
        if channel_id:
            return self.credentials_dir / f'{prefix}{channel_id}_token.pickle'
        return self.credentials_dir / f'{prefix}default_token.pickle'
    
    def _save_token(self, creds: Credentials, channel_id: Optional[str] = None):
        """Save credentials to file"""
        token_file = self._get_token_file(channel_id)
        try:
            with open(token_file, 'wb') as f:
                pickle.dump(creds, f)
            print(f"Token kaydedildi: {token_file.name}", file=sys.stderr)
        except Exception as e:
            print(f"Token kaydetme hatası: {e}", file=sys.stderr)


def main():
    """CLI test for authentication"""
    import sys
    
    base_dir = Path(__file__).parent.parent.parent
    creds_dir = base_dir / 'data' / 'youtube_credentials'
    
    auth = YouTubeAuth(str(creds_dir))
    
    print("YouTube Kimlik Doğrulama")
    print("=" * 50)
    
    # Build service and get channel info
    service = auth.build_service()
    if service:
        print("\n✅ API servisi hazır!")
        
        # Get channel info
        channel_info = auth.get_channel_info(service)
        if channel_info:
            print(f"\n📺 Kanal: {channel_info['channel_title']}")
            print(f"📊 Aboneler: {channel_info['subscriber_count']:,}")
            print(f"🎬 Videolar: {channel_info['video_count']:,}")
            
            # Save channel info to JSON
            channels_file = base_dir / 'data' / 'youtube_channels.json'
            if channels_file.exists():
                with open(channels_file, 'r', encoding='utf-8') as f:
                    data = json.load(f)
            else:
                data = {'channels': []}
            
            # Update or add channel
            channel_exists = False
            for i, ch in enumerate(data['channels']):
                if ch['channel_id'] == channel_info['channel_id']:
                    data['channels'][i] = {**channel_info, 'is_active': True, 'is_default': True}
                    channel_exists = True
                    break
            
            if not channel_exists:
                data['channels'].append({
                    **channel_info,
                    'is_active': True,
                    'is_default': len(data['channels']) == 0,
                    'connected_at': None
                })
            
            with open(channels_file, 'w', encoding='utf-8') as f:
                json.dump(data, f, ensure_ascii=False, indent=2)
            
            print(f"\n💾 Kanal bilgileri kaydedildi: {channels_file}")
    else:
        print("\n❌ Kimlik doğrulama başarısız!")
        sys.exit(1)


if __name__ == '__main__':
    main()
