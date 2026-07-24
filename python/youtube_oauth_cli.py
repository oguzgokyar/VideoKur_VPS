#!/usr/bin/env python3
"""
YouTube OAuth CLI for Unified Channel System
Handles OAuth authentication for specific channel-API pairs
"""
import sys
import json
import argparse
from pathlib import Path

# Add parent directory to path for imports
sys.path.insert(0, str(Path(__file__).parent.parent))

from youtube.auth import YouTubeAuth

def main():
    parser = argparse.ArgumentParser(description='YouTube OAuth for Channel-API')
    parser.add_argument('--project-id', required=True, help='Project ID')
    parser.add_argument('--client-secrets', required=True, help='Path to client_secrets.json')
    parser.add_argument('--channel-id', required=True, help='Channel ID')
    parser.add_argument('--api-id', required=True, help='API ID')
    
    args = parser.parse_args()
    
    # Clean up shell escaping
    project_id = args.project_id.strip("'\"")
    client_secrets = args.client_secrets.strip("'\"")
    channel_id = args.channel_id.strip("'\"")
    api_id = args.api_id.strip("'\"")
    
    base_dir = Path(__file__).parent.parent
    creds_dir = base_dir / 'data' / 'youtube_credentials'
    creds_dir.mkdir(parents=True, exist_ok=True)
    
    # Check client_secrets file
    secrets_path = Path(client_secrets)
    if not secrets_path.exists():
        print(f"❌ Client secrets dosyası bulunamadı: {client_secrets}", file=sys.stderr)
        sys.exit(1)
    
    print(f"🔑 OAuth başlatılıyor...", file=sys.stderr)
    print(f"   Project: {project_id}", file=sys.stderr)
    print(f"   Client Secrets: {secrets_path.name}", file=sys.stderr)
    print(f"   Channel: {channel_id}", file=sys.stderr)
    print(f"   API: {api_id}", file=sys.stderr)
    
    # Create auth instance with custom client_secrets path
    auth = YouTubeAuth(str(creds_dir), project_id=project_id)
    auth.client_secrets_file = secrets_path
    
    # Authenticate
    service = auth.build_service(channel_id=f"{channel_id}_{api_id}")
    
    if service:
        print("✅ OAuth başarılı!", file=sys.stderr)
        
        # Get channel info
        channel_info = auth.get_channel_info(service)
        if channel_info:
            print(f"📺 Kanal: {channel_info['channel_title']}", file=sys.stderr)
            
            # Update youtube_channels.json
            channels_file = base_dir / 'data' / 'youtube_channels.json'
            if channels_file.exists():
                with open(channels_file, 'r', encoding='utf-8') as f:
                    data = json.load(f)
                
                # Find and update the API
                for ch in data['channels']:
                    if ch['id'] == channel_id:
                        for api in ch['apis']:
                            if api['api_id'] == api_id:
                                api['is_authenticated'] = True
                                api['is_active'] = True
                                api['google_account_email'] = channel_info.get('channel_id', '')
                                print(f"✅ API güncellendi: {api['name']}", file=sys.stderr)
                                break
                        break
                
                with open(channels_file, 'w', encoding='utf-8') as f:
                    json.dump(data, f, indent=2, ensure_ascii=False)
        
        print(json.dumps({'success': True, 'message': 'OAuth completed'}))
        sys.exit(0)
    else:
        print("❌ OAuth başarısız!", file=sys.stderr)
        print(json.dumps({'success': False, 'error': 'OAuth failed'}))
        sys.exit(1)

if __name__ == '__main__':
    main()
