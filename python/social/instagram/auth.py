"""
Meta (Instagram/Facebook) OAuth Authentication Module
Handles OAuth 2.0 for Instagram Graph API and Facebook API
"""
import os
import sys
import json
import webbrowser
import http.server
import socketserver
from pathlib import Path
from typing import Optional, Dict, List
from urllib.parse import urlencode, parse_qs, urlparse
from datetime import datetime, timedelta

try:
    import requests
except ImportError:
    print("HATA: requests kütüphanesi bulunamadı!")
    print("Yüklemek için: pip install requests")


class MetaAuth:
    """
    Meta (Instagram/Facebook) OAuth 2.0 Authentication Manager
    
    Supports:
    - Instagram Graph API (for Business/Creator accounts)
    - Facebook Graph API (for Pages)
    
    Requirements:
    1. Meta Developer account (developers.facebook.com)
    2. Facebook App with Instagram Graph API enabled
    3. Instagram Business/Creator account connected to Facebook Page
    """
    
    # Meta OAuth endpoints
    AUTH_URL = "https://www.facebook.com/v18.0/dialog/oauth"
    TOKEN_URL = "https://graph.facebook.com/v18.0/oauth/access_token"
    DEBUG_TOKEN_URL = "https://graph.facebook.com/debug_token"
    GRAPH_URL = "https://graph.facebook.com/v18.0"
    
    # Required permissions for Reels posting
    PERMISSIONS = [
        'instagram_basic',
        'instagram_content_publish',
        'pages_show_list',
        'pages_read_engagement',
        'business_management',
        # Facebook specific
        'publish_video',
        'pages_manage_posts'
    ]
    
    def __init__(self, credentials_dir: str):
        """
        Initialize Meta auth manager
        
        Args:
            credentials_dir: Directory for storing credentials
        """
        self.credentials_dir = Path(credentials_dir)
        self.credentials_dir.mkdir(parents=True, exist_ok=True)
        
        self.config_file = self.credentials_dir / 'meta_config.json'
        self.token_file = self.credentials_dir / 'meta_token.json'
        self.accounts_file = self.credentials_dir / 'meta_accounts.json'
        
        self._config = self._load_config()
    
    def _load_config(self) -> Dict:
        """Load Meta app configuration"""
        if self.config_file.exists():
            try:
                with open(self.config_file, 'r') as f:
                    return json.load(f)
            except Exception as e:
                print(f"[WARN] Failed to load Meta config: {e}")
        return {}
    
    def save_config(self, app_id: str, app_secret: str):
        """
        Save Meta app credentials
        
        Args:
            app_id: Facebook App ID
            app_secret: Facebook App Secret
        """
        config = {
            'app_id': app_id,
            'app_secret': app_secret,
            'redirect_uri': 'http://localhost:8686/callback'
        }
        
        with open(self.config_file, 'w') as f:
            json.dump(config, f, indent=2)
        
        self._config = config
        print(f"✅ Meta config kaydedildi: {self.config_file}")
    
    def get_auth_url(self) -> Optional[str]:
        """
        Generate OAuth authorization URL
        
        Returns:
            Authorization URL or None if config missing
        """
        if not self._config.get('app_id'):
            print("HATA: Meta app_id bulunamadı!")
            print("Önce save_config() ile credentials kaydedin.")
            return None
        
        params = {
            'client_id': self._config['app_id'],
            'redirect_uri': self._config.get('redirect_uri', 'http://localhost:8686/callback'),
            'scope': ','.join(self.PERMISSIONS),
            'response_type': 'code',
            'state': 'video_kur_meta_auth'
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
        
        print("\n🔐 Meta OAuth Başlatılıyor...")
        print(f"Tarayıcınızda şu URL açılacak:\n{auth_url}\n")
        
        # Open browser
        webbrowser.open(auth_url)
        
        # Start local server to receive callback
        code = self._wait_for_callback()
        
        if code:
            if self._exchange_code(code):
                # Fetch connected accounts
                self._fetch_accounts()
                return True
        
        return False
    
    def _wait_for_callback(self, port: int = 8686) -> Optional[str]:
        """Wait for OAuth callback and extract code"""
        
        code_holder = {'code': None}
        
        class CallbackHandler(http.server.SimpleHTTPRequestHandler):
            def do_GET(self):
                query = parse_qs(urlparse(self.path).query)
                if 'code' in query:
                    code_holder['code'] = query['code'][0]
                    self.send_response(200)
                    self.send_header('Content-type', 'text/html; charset=utf-8')
                    self.end_headers()
                    self.wfile.write("""
                        <html><body style="font-family: Arial; text-align: center; padding: 50px;">
                        <h1>✅ Meta Yetkilendirme Başarılı!</h1>
                        <p>Bu pencereyi kapatabilirsiniz.</p>
                        </body></html>
                    """.encode('utf-8'))
                else:
                    error = query.get('error_description', ['Unknown error'])[0]
                    self.send_response(400)
                    self.send_header('Content-type', 'text/html; charset=utf-8')
                    self.end_headers()
                    self.wfile.write(f"""
                        <html><body style="font-family: Arial; text-align: center; padding: 50px;">
                        <h1>❌ Yetkilendirme Başarısız</h1>
                        <p>{error}</p>
                        </body></html>
                    """.encode('utf-8'))
            
            def log_message(self, format, *args):
                pass
        
        try:
            with socketserver.TCPServer(("", port), CallbackHandler) as httpd:
                print(f"Callback bekleniyor (port {port})...")
                httpd.timeout = 180  # 3 minutes timeout
                httpd.handle_request()
        except Exception as e:
            print(f"Callback server hatası: {e}")
            return None
        
        return code_holder['code']
    
    def _exchange_code(self, code: str) -> bool:
        """Exchange authorization code for access token"""
        
        try:
            response = requests.get(
                self.TOKEN_URL,
                params={
                    'client_id': self._config['app_id'],
                    'client_secret': self._config['app_secret'],
                    'redirect_uri': self._config.get('redirect_uri'),
                    'code': code
                }
            )
            
            data = response.json()
            
            if 'access_token' in data:
                # Get long-lived token
                long_lived = self._get_long_lived_token(data['access_token'])
                
                token_data = {
                    'access_token': long_lived.get('access_token', data['access_token']),
                    'token_type': data.get('token_type', 'bearer'),
                    'expires_at': (datetime.now() + timedelta(seconds=long_lived.get('expires_in', 5184000))).isoformat()
                }
                
                self._save_token(token_data)
                print("✅ Meta token kaydedildi!")
                return True
            else:
                print(f"❌ Token alma hatası: {data}")
                return False
                
        except Exception as e:
            print(f"❌ Token exchange hatası: {e}")
            return False
    
    def _get_long_lived_token(self, short_token: str) -> Dict:
        """Exchange short-lived token for long-lived token (60 days)"""
        try:
            response = requests.get(
                self.TOKEN_URL,
                params={
                    'grant_type': 'fb_exchange_token',
                    'client_id': self._config['app_id'],
                    'client_secret': self._config['app_secret'],
                    'fb_exchange_token': short_token
                }
            )
            return response.json()
        except Exception as e:
            print(f"[WARN] Failed to exchange token, using short-lived: {e}")
            return {'access_token': short_token, 'expires_in': 3600}
    
    def _fetch_accounts(self):
        """Fetch connected Instagram and Facebook accounts"""
        token = self.get_access_token()
        if not token:
            return
        
        accounts = {
            'instagram': [],
            'facebook': []
        }
        
        try:
            # Get Facebook Pages
            response = requests.get(
                f"{self.GRAPH_URL}/me/accounts",
                params={
                    'access_token': token,
                    'fields': 'id,name,access_token,instagram_business_account'
                }
            )
            
            data = response.json()
            pages = data.get('data', [])
            
            for page in pages:
                # Add Facebook Page
                accounts['facebook'].append({
                    'id': page['id'],
                    'name': page['name'],
                    'access_token': page.get('access_token'),
                    'type': 'page'
                })
                
                # Check for Instagram Business Account
                if 'instagram_business_account' in page:
                    ig_id = page['instagram_business_account']['id']
                    
                    # Get Instagram account details
                    ig_response = requests.get(
                        f"{self.GRAPH_URL}/{ig_id}",
                        params={
                            'access_token': token,
                            'fields': 'id,username,name,profile_picture_url,followers_count'
                        }
                    )
                    
                    ig_data = ig_response.json()
                    
                    accounts['instagram'].append({
                        'id': ig_id,
                        'username': ig_data.get('username'),
                        'name': ig_data.get('name'),
                        'profile_picture': ig_data.get('profile_picture_url'),
                        'followers': ig_data.get('followers_count', 0),
                        'page_id': page['id'],
                        'page_access_token': page.get('access_token')
                    })
            
            # Save accounts
            with open(self.accounts_file, 'w', encoding='utf-8') as f:
                json.dump(accounts, f, ensure_ascii=False, indent=2)
            
            print(f"✅ Hesaplar kaydedildi:")
            print(f"   Instagram: {len(accounts['instagram'])} hesap")
            print(f"   Facebook: {len(accounts['facebook'])} sayfa")
            
        except Exception as e:
            print(f"Hesap bilgisi alma hatası: {e}")
    
    def get_access_token(self) -> Optional[str]:
        """Get valid access token"""
        token_data = self._load_token()
        if not token_data:
            return None
        
        # Check expiration
        expires_at = datetime.fromisoformat(token_data.get('expires_at', '2000-01-01'))
        if datetime.now() >= expires_at:
            print("Token expired - yeniden authenticate() çağırın")
            return None
        
        return token_data.get('access_token')
    
    def get_instagram_accounts(self) -> List[Dict]:
        """Get list of connected Instagram accounts"""
        if self.accounts_file.exists():
            try:
                with open(self.accounts_file, 'r', encoding='utf-8') as f:
                    data = json.load(f)
                    return data.get('instagram', [])
            except Exception as e:
                print(f"[WARN] Failed to load Instagram accounts: {e}")
        return []
    
    def get_facebook_pages(self) -> List[Dict]:
        """Get list of connected Facebook Pages"""
        if self.accounts_file.exists():
            try:
                with open(self.accounts_file, 'r', encoding='utf-8') as f:
                    data = json.load(f)
                    return data.get('facebook', [])
            except Exception as e:
                print(f"[WARN] Failed to load Facebook pages: {e}")
        return []
    
    def get_page_access_token(self, page_id: str) -> Optional[str]:
        """Get access token for specific Facebook Page"""
        pages = self.get_facebook_pages()
        for page in pages:
            if page['id'] == page_id:
                return page.get('access_token')
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
            except Exception as e:
                print(f"[WARN] Failed to load token: {e}")
        return None
    
    def is_authenticated(self) -> bool:
        """Check if we have valid credentials"""
        return self.get_access_token() is not None
    
    def revoke(self):
        """Remove stored credentials"""
        for f in [self.token_file, self.accounts_file]:
            if f.exists():
                f.unlink()
        print("Meta credentials silindi")


def main():
    """CLI test"""
    import sys
    
    base_dir = Path(__file__).parent.parent.parent.parent
    creds_dir = base_dir / 'data' / 'social_credentials' / 'meta'
    
    auth = MetaAuth(str(creds_dir))
    
    print("Meta (Instagram/Facebook) Authentication")
    print("=" * 50)
    
    if auth.is_authenticated():
        print("\n✅ Zaten kimlik doğrulanmış!")
        
        ig_accounts = auth.get_instagram_accounts()
        fb_pages = auth.get_facebook_pages()
        
        print(f"\n📸 Instagram Hesapları ({len(ig_accounts)}):")
        for acc in ig_accounts:
            print(f"   @{acc.get('username')} - {acc.get('followers', 0)} takipçi")
        
        print(f"\n📘 Facebook Sayfaları ({len(fb_pages)}):")
        for page in fb_pages:
            print(f"   {page.get('name')} (ID: {page.get('id')})")
    else:
        print("\n⚠️  Kimlik doğrulama gerekli.")
        print("\nKurulum adımları:")
        print("1. developers.facebook.com'da uygulama oluşturun")
        print("2. Instagram Graph API ve Facebook Login ekleyin")
        print("3. App ID ve Secret alın")
        print("\nSonra:")
        print("  auth.save_config('APP_ID', 'APP_SECRET')")
        print("  auth.authenticate()")


if __name__ == '__main__':
    main()
