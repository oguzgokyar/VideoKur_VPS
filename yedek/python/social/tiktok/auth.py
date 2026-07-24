"""
TikTok Authentication Module
Handles OAuth 2.0 flow for TikTok Content Posting API
"""
import os
import sys
import json
import pickle
import webbrowser
import http.server
import socketserver
from pathlib import Path
from typing import Optional, Dict
from urllib.parse import urlencode, parse_qs, urlparse
from datetime import datetime, timedelta

try:
    import requests
except ImportError:
    print("HATA: requests kütüphanesi bulunamadı!")
    print("Yüklemek için: pip install requests")


class TikTokAuth:
    """
    TikTok OAuth 2.0 Authentication Manager
    
    Note: TikTok Content Posting API requires developer application approval.
    This module provides the framework - you need to:
    1. Apply at developers.tiktok.com
    2. Get approved for Content Posting API
    3. Add client_id and client_secret to credentials
    """
    
    # TikTok OAuth endpoints
    AUTH_URL = "https://www.tiktok.com/v2/auth/authorize/"
    TOKEN_URL = "https://open.tiktokapis.com/v2/oauth/token/"
    USERINFO_URL = "https://open.tiktokapis.com/v2/user/info/"
    
    # Required scopes for video posting
    SCOPES = [
        'user.info.basic',
        'video.upload',
        'video.publish'
    ]
    
    def __init__(self, credentials_dir: str):
        """
        Initialize TikTok auth manager
        
        Args:
            credentials_dir: Directory for storing credentials
        """
        self.credentials_dir = Path(credentials_dir)
        self.credentials_dir.mkdir(parents=True, exist_ok=True)
        
        self.config_file = self.credentials_dir / 'tiktok_config.json'
        self.token_file = self.credentials_dir / 'tiktok_token.json'
        
        self._config = self._load_config()
    
    def _load_config(self) -> Dict:
        """Load TikTok app configuration"""
        if self.config_file.exists():
            try:
                with open(self.config_file, 'r') as f:
                    return json.load(f)
            except:
                pass
        return {}
    
    def save_config(self, client_key: str, client_secret: str):
        """
        Save TikTok app credentials
        
        Args:
            client_key: TikTok app client key
            client_secret: TikTok app client secret
        """
        config = {
            'client_key': client_key,
            'client_secret': client_secret,
            'redirect_uri': 'http://localhost:8585/callback'
        }
        
        with open(self.config_file, 'w') as f:
            json.dump(config, f, indent=2)
        
        self._config = config
        print(f"✅ TikTok config kaydedildi: {self.config_file}")
    
    def get_auth_url(self) -> Optional[str]:
        """
        Generate OAuth authorization URL
        
        Returns:
            Authorization URL or None if config missing
        """
        if not self._config.get('client_key'):
            print("HATA: TikTok client_key bulunamadı!")
            print("Önce save_config() ile credentials kaydedin.")
            return None
        
        # Generate CSRF state
        import secrets
        state = secrets.token_urlsafe(16)
        
        # Save state for verification
        state_file = self.credentials_dir / 'tiktok_state.txt'
        with open(state_file, 'w') as f:
            f.write(state)
        
        params = {
            'client_key': self._config['client_key'],
            'scope': ','.join(self.SCOPES),
            'response_type': 'code',
            'redirect_uri': self._config.get('redirect_uri', 'http://localhost:8585/callback'),
            'state': state
        }
        
        return f"{self.AUTH_URL}?{urlencode(params)}"
    
    def authenticate(self) -> bool:
        """
        Perform OAuth flow with local callback server
        
        Returns:
            True if authentication successful
        """
        auth_url = self.get_auth_url()
        if not auth_url:
            return False
        
        print("\n🔐 TikTok OAuth Başlatılıyor...")
        print(f"Tarayıcınızda şu URL açılacak:\n{auth_url}\n")
        
        # Open browser
        webbrowser.open(auth_url)
        
        # Start local server to receive callback
        code = self._wait_for_callback()
        
        if code:
            return self._exchange_code(code)
        
        return False
    
    def _wait_for_callback(self, port: int = 8585) -> Optional[str]:
        """Wait for OAuth callback and extract code"""
        
        code_holder = {'code': None}
        
        class CallbackHandler(http.server.SimpleHTTPRequestHandler):
            def do_GET(self):
                query = parse_qs(urlparse(self.path).query)
                if 'code' in query:
                    code_holder['code'] = query['code'][0]
                    self.send_response(200)
                    self.send_header('Content-type', 'text/html')
                    self.end_headers()
                    self.wfile.write(b"""
                        <html><body style="font-family: Arial; text-align: center; padding: 50px;">
                        <h1>&#10004; TikTok Yetkilendirme Basarili!</h1>
                        <p>Bu pencereyi kapatabilirsiniz.</p>
                        </body></html>
                    """)
                else:
                    self.send_response(400)
                    self.end_headers()
            
            def log_message(self, format, *args):
                pass  # Suppress logs
        
        try:
            with socketserver.TCPServer(("", port), CallbackHandler) as httpd:
                print(f"Callback bekleniyor (port {port})...")
                httpd.timeout = 120  # 2 minutes timeout
                httpd.handle_request()
        except Exception as e:
            print(f"Callback server hatası: {e}")
            return None
        
        return code_holder['code']
    
    def _exchange_code(self, code: str) -> bool:
        """Exchange authorization code for access token"""
        
        try:
            response = requests.post(
                self.TOKEN_URL,
                headers={'Content-Type': 'application/x-www-form-urlencoded'},
                data={
                    'client_key': self._config['client_key'],
                    'client_secret': self._config['client_secret'],
                    'code': code,
                    'grant_type': 'authorization_code',
                    'redirect_uri': self._config.get('redirect_uri')
                }
            )
            
            data = response.json()
            
            if 'access_token' in data:
                token_data = {
                    'access_token': data['access_token'],
                    'refresh_token': data.get('refresh_token'),
                    'expires_at': (datetime.now() + timedelta(seconds=data.get('expires_in', 86400))).isoformat(),
                    'open_id': data.get('open_id'),
                    'scope': data.get('scope')
                }
                
                self._save_token(token_data)
                print("✅ TikTok token kaydedildi!")
                return True
            else:
                print(f"❌ Token alma hatası: {data}")
                return False
                
        except Exception as e:
            print(f"❌ Token exchange hatası: {e}")
            return False
    
    def get_access_token(self) -> Optional[str]:
        """
        Get valid access token, refreshing if needed
        
        Returns:
            Access token or None
        """
        token_data = self._load_token()
        if not token_data:
            return None
        
        # Check expiration
        expires_at = datetime.fromisoformat(token_data.get('expires_at', '2000-01-01'))
        if datetime.now() >= expires_at:
            # Try to refresh
            if token_data.get('refresh_token'):
                if self._refresh_token(token_data['refresh_token']):
                    token_data = self._load_token()
                else:
                    return None
            else:
                print("Token expired and no refresh token available")
                return None
        
        return token_data.get('access_token')
    
    def _refresh_token(self, refresh_token: str) -> bool:
        """Refresh access token"""
        try:
            response = requests.post(
                self.TOKEN_URL,
                headers={'Content-Type': 'application/x-www-form-urlencoded'},
                data={
                    'client_key': self._config['client_key'],
                    'client_secret': self._config['client_secret'],
                    'refresh_token': refresh_token,
                    'grant_type': 'refresh_token'
                }
            )
            
            data = response.json()
            
            if 'access_token' in data:
                token_data = {
                    'access_token': data['access_token'],
                    'refresh_token': data.get('refresh_token', refresh_token),
                    'expires_at': (datetime.now() + timedelta(seconds=data.get('expires_in', 86400))).isoformat(),
                    'open_id': data.get('open_id'),
                    'scope': data.get('scope')
                }
                
                self._save_token(token_data)
                print("✅ TikTok token yenilendi!")
                return True
                
        except Exception as e:
            print(f"Token refresh hatası: {e}")
        
        return False
    
    def get_user_info(self) -> Optional[Dict]:
        """Get authenticated user information"""
        token = self.get_access_token()
        if not token:
            return None
        
        try:
            response = requests.get(
                self.USERINFO_URL,
                headers={'Authorization': f'Bearer {token}'},
                params={'fields': 'open_id,union_id,avatar_url,display_name'}
            )
            
            data = response.json()
            if data.get('data', {}).get('user'):
                return data['data']['user']
                
        except Exception as e:
            print(f"User info hatası: {e}")
        
        return None
    
    def _save_token(self, token_data: Dict):
        """Save token to file"""
        with open(self.token_file, 'w') as f:
            json.dump(token_data, f, indent=2)
    
    def _load_token(self) -> Optional[Dict]:
        """Load token from file"""
        if self.token_file.exists():
            try:
                with open(self.token_file, 'r') as f:
                    return json.load(f)
            except:
                pass
        return None
    
    def is_authenticated(self) -> bool:
        """Check if we have valid credentials"""
        return self.get_access_token() is not None
    
    def revoke(self):
        """Remove stored credentials"""
        if self.token_file.exists():
            self.token_file.unlink()
            print("TikTok token silindi")


def main():
    """CLI test"""
    import sys
    
    base_dir = Path(__file__).parent.parent.parent.parent
    creds_dir = base_dir / 'data' / 'social_credentials' / 'tiktok'
    
    auth = TikTokAuth(str(creds_dir))
    
    print("TikTok Authentication Test")
    print("=" * 50)
    print("\n⚠️  NOT: TikTok Content Posting API kullanmak için:")
    print("1. developers.tiktok.com'da hesap oluşturun")
    print("2. Content Posting API için başvurun")
    print("3. Onay alın (1-2 hafta)")
    print("4. Client Key ve Secret alın")
    print("\nOnay aldıktan sonra:")
    print("auth.save_config('YOUR_CLIENT_KEY', 'YOUR_CLIENT_SECRET')")
    print("auth.authenticate()")


if __name__ == '__main__':
    main()
