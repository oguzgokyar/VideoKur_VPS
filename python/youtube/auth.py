"""
YouTube OAuth 2.0 Authentication Module
Handles authentication flow, token management, and refresh
Supports multiple Google Cloud projects for quota rotation
"""
import os
import sys
import json
from pathlib import Path
from typing import Optional, Dict
from datetime import datetime

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
                with open(token_file, 'r', encoding='utf-8') as f:
                    token_data = json.load(f)

                # Web OAuth stores as 'access_token', Python client may expect 'token'
                access_token = token_data.get('access_token') or token_data.get('token')
                if not access_token:
                    print(f"❌ Geçersiz token dosyası (access_token yok): {token_file.name}", file=sys.stderr)
                    return None

                raw_scopes = token_data.get('scopes') or token_data.get('scope') or SCOPES
                scopes = raw_scopes.split() if isinstance(raw_scopes, str) else raw_scopes

                expiry_raw = token_data.get('expiry')
                expiry = None
                if expiry_raw:
                    try:
                        expiry = datetime.fromisoformat(expiry_raw.replace('Z', '+00:00'))
                    except ValueError:
                        expiry = None

                # Build Credentials from JSON token data
                # Backfill missing fields from client_secrets if not in token file
                client_id = token_data.get('client_id')
                client_secret = token_data.get('client_secret')
                token_uri = token_data.get('token_uri')
                
                # If missing, try to load from client_secrets file
                if not (client_id and client_secret and token_uri):
                    try:
                        with open(self.client_secrets_file, 'r', encoding='utf-8') as f:
                            secrets = json.load(f)
                        secret_config = secrets.get('web') or secrets.get('installed', {})
                        
                        if not client_id:
                            client_id = secret_config.get('client_id')
                        if not client_secret:
                            client_secret = secret_config.get('client_secret')
                        if not token_uri:
                            token_uri = secret_config.get('token_uri', 'https://oauth2.googleapis.com/token')
                        
                        if client_id and client_secret and token_uri:
                            print(f"📦 Eksik fields backfill'ed from client_secrets: {self.client_secrets_file.name}", file=sys.stderr)
                    except Exception as e:
                        print(f"⚠️  Backfill error: {e}", file=sys.stderr)
                
                # Validate required fields before creating Credentials
                if not (client_id and client_secret and token_uri):
                    print(f"❌ Token incomplete - missing client_id/client_secret/token_uri. Re-auth required.", file=sys.stderr)
                    print("🔑 Çözüm: Hesaplar sayfasından ilgili API için yeniden login yapın.", file=sys.stderr)
                    return None
                
                creds = Credentials(
                    token=access_token,
                    refresh_token=token_data.get('refresh_token'),
                    token_uri=token_uri,
                    client_id=client_id,
                    client_secret=client_secret,
                    scopes=scopes,
                    expiry=expiry
                )
                print(f"✅ JSON token yüklendi: {token_file.name}", file=sys.stderr)
            except (json.JSONDecodeError, UnicodeDecodeError) as e:
                print(f"❌ Token JSON formatı bozuk ({token_file.name}): {e}", file=sys.stderr)
                print("🔑 Çözüm: Hesaplar sayfasından ilgili API için yeniden login yapın.", file=sys.stderr)
                return None
            except Exception as e:
                print(f"Token yükleme hatası: {e}", file=sys.stderr)
                return None
        
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
                    except Exception as e:
                        print(f"[WARN] Failed to delete old token: {e}")
                
                creds = None
        
        if creds and creds.valid:
            return creds
        else:
            return None
    
    def authenticate(self, channel_id: Optional[str] = None) -> Optional[Credentials]:
        """
        **DEPRECATED:** This method is disabled.
        
        OAuth authentication must be done via web interface only.
        Use the Accounts page in the web app to authenticate APIs.
        
        Args:
            channel_id: Channel ID (ignored)
            
        Returns:
            None (always)
        """
        error_msg = (
            f"\n❌ HATA: Otomatik OAuth akışı devre dışı bırakıldı!\n\n"
            f"🔑 OAuth girişi için:\n"
            f"   1. Web tarayıcıda uygulamayı açın\n"
            f"   2. 'Hesaplar' sayfasına gidin\n"
            f"   3. İlgili API'nin yanındaki '🔑 Login' butonuna tıklayın\n"
            f"   4. Google OAuth penceresinde yetkilendirin\n\n"
            f"⚠️  Python backend artık otomatik tarayıcı açmaz.\n"
            f"         Tüm OAuth işlemleri web arayüzünden yapılmalıdır.\n"
        )
        print(error_msg, file=sys.stderr)
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
            # ⚠️ IMPORTANT: Do NOT auto-authenticate!
            # Users must authenticate via web interface (Accounts page)
            error_msg = (
                f"\n❌ YouTube OAuth token bulunamadı!\n"
                f"   Channel ID: {channel_id or 'default'}\n"
                f"   Project ID: {self.project_id or 'default'}\n\n"
                f"🔑 Çözüm:\n"
                f"   1. Web arayüzünde 'Hesaplar' sayfasına gidin\n"
                f"   2. İlgili API'nin yanındaki '🔑 Login' butonuna tıklayın\n"
                f"   3. Google OAuth'u tamamlayın\n\n"
                f"⚠️  Not: Uygulama otomatik olarak tarayıcı açmaz.\n"
                f"         OAuth işlemi sadece web arayüzünden yapılmalıdır.\n"
            )
            print(error_msg, file=sys.stderr)
            return None
        
        if not creds.valid:
            print("Geçersiz YouTube kimlik bilgisi bulundu.", file=sys.stderr)
            return None
        
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
        """Get token file path for channel and project (JSON only)."""
        if self.project_id:
            channel_match = self._find_token_from_channels(channel_id)
            if channel_match:
                return channel_match

            import glob
            if channel_id:
                exact_pattern = str(self.credentials_dir / f'{self.project_id}_{channel_id}_*_token.json')
                exact_matches = sorted(glob.glob(exact_pattern))
                if exact_matches:
                    return Path(exact_matches[0])

            project_pattern = str(self.credentials_dir / f'{self.project_id}_*_token.json')
            project_matches = sorted(glob.glob(project_pattern))
            if project_matches:
                return Path(project_matches[0])

            # Deterministic fallback path if no token exists yet.
            fallback_channel = channel_id or 'default'
            return self.credentials_dir / f'{self.project_id}_{fallback_channel}_token.json'

        # Non-project mode: legacy default JSON path
        if channel_id:
            return self.credentials_dir / f'{channel_id}_token.json'
        return self.credentials_dir / 'default_token.json'
    
    def _save_token(self, creds: Credentials, channel_id: Optional[str] = None):
        """Save credentials to JSON token file."""
        token_file = self._get_token_file(channel_id)
        try:
            token_data = {
                'access_token': creds.token,
                'refresh_token': creds.refresh_token,
                'token_uri': creds.token_uri,
                'client_id': creds.client_id,
                'client_secret': creds.client_secret,
                'scopes': creds.scopes or SCOPES,
                'expiry': creds.expiry.isoformat() if creds.expiry else None
            }
            with open(token_file, 'w', encoding='utf-8') as f:
                json.dump(token_data, f, ensure_ascii=False, indent=2)
            print(f"Token kaydedildi: {token_file.name}", file=sys.stderr)
        except Exception as e:
            print(f"Token kaydetme hatası: {e}", file=sys.stderr)

    def _find_token_from_channels(self, channel_id: Optional[str]) -> Optional[Path]:
        """Resolve token file from youtube_channels.json when available."""
        channels_file = self.credentials_dir.parent / 'youtube_channels.json'
        if not channels_file.exists():
            return None

        try:
            with open(channels_file, 'r', encoding='utf-8') as f:
                channels_data = json.load(f)
        except (OSError, json.JSONDecodeError):
            return None

        matched_paths = []
        for channel in channels_data.get('channels', []):
            if channel_id and channel.get('id') != channel_id:
                continue

            for api in channel.get('apis', []):
                if api.get('project_id') != self.project_id:
                    continue
                token_name = (api.get('token_file') or '').strip()
                if not token_name:
                    continue
                token_path = self.credentials_dir / token_name
                if token_path.exists():
                    matched_paths.append(token_path)

        if matched_paths:
            return sorted(matched_paths)[0]
        return None


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
