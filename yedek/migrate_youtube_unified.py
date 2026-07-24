#!/usr/bin/env python3
"""
YouTube Unified Channel Model Migration
========================================

Bu script mevcut youtube_projects.json ve youtube_channels.json dosyalarını
unified youtube_channels.json modeline migrate eder.

Yeni Model:
- Her channel kendi API listesine sahip
- API'ler channel altında organize edilir
- Quota tracking per API
- Authentication status per API

Kullanım:
    python migrate_youtube_unified.py [--dry-run] [--backup]
"""

import json
import shutil
import sys
from pathlib import Path
from datetime import datetime
from typing import Dict, List, Optional

# Paths
DATA_DIR = Path(__file__).parent / 'data'
PROJECTS_FILE = DATA_DIR / 'youtube_projects.json'
CHANNELS_FILE = DATA_DIR / 'youtube_channels.json'
UNIFIED_FILE = DATA_DIR / 'youtube_channels.json'
BACKUP_DIR = DATA_DIR / 'backups'


def load_json(file_path: Path) -> Dict:
    """Load JSON file"""
    if not file_path.exists():
        print(f"⚠️  Dosya bulunamadı: {file_path}")
        return {}
    
    with open(file_path, 'r', encoding='utf-8') as f:
        return json.load(f)


def save_json(file_path: Path, data: Dict, pretty: bool = True):
    """Save JSON file"""
    with open(file_path, 'w', encoding='utf-8') as f:
        if pretty:
            json.dump(data, f, indent=2, ensure_ascii=False)
        else:
            json.dump(data, f, ensure_ascii=False)


def backup_files():
    """Backup existing files"""
    BACKUP_DIR.mkdir(exist_ok=True)
    timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
    
    backups = []
    
    if PROJECTS_FILE.exists():
        backup_path = BACKUP_DIR / f'youtube_projects_{timestamp}.json'
        shutil.copy2(PROJECTS_FILE, backup_path)
        backups.append(('youtube_projects.json', backup_path))
        print(f"✅ Backup: {backup_path}")
    
    if CHANNELS_FILE.exists():
        backup_path = BACKUP_DIR / f'youtube_channels_{timestamp}.json'
        shutil.copy2(CHANNELS_FILE, backup_path)
        backups.append(('youtube_channels.json', backup_path))
        print(f"✅ Backup: {backup_path}")
    
    return backups


def migrate_to_unified_model(
    projects_data: Dict, 
    channels_data: Dict
) -> Dict:
    """
    Migrate to unified model
    
    Strategy:
    1. Load existing channels (from youtube_channels.json)
    2. Load existing projects (from youtube_projects.json)
    3. Map projects to channels (heuristic: same google account or default)
    4. Create unified structure
    """
    
    existing_channels = channels_data.get('channels', [])
    existing_projects = projects_data.get('projects', [])
    
    # Unified channels list
    unified_channels = []
    
    # Process existing channels
    for channel in existing_channels:
        channel_id = channel.get('channel_id')
        channel_title = channel.get('channel_title', 'Unknown Channel')
        is_default = channel.get('is_default', False)
        
        # Create unified channel object
        unified_channel = {
            'id': f"channel_{len(unified_channels) + 1:03d}",
            'channel_id': channel_id,
            'channel_title': channel_title,
            'channel_url': f"https://youtube.com/channel/{channel_id}",
            'thumbnail': channel.get('thumbnail', ''),
            'subscriber_count': channel.get('subscriber_count', 0),
            'video_count': channel.get('video_count', 0),
            'description': channel.get('description', ''),
            'is_default': is_default,
            'is_active': channel.get('is_active', True),
            'connected_at': channel.get('connected_at'),
            'apis': []
        }
        
        # Map projects to this channel
        # Heuristic: Default channel gets default projects
        for project in existing_projects:
            project_id = project.get('id')
            project_name = project.get('name')
            is_project_default = project.get('is_default', False)
            
            # Mapping logic:
            # 1. If channel is default and project is default → map
            # 2. If there's only one channel → map all projects to it
            should_map = False
            
            if len(existing_channels) == 1:
                # Only one channel, map all projects
                should_map = True
            elif is_default and is_project_default:
                # Both default, map together
                should_map = True
            elif is_default and not any(p.get('is_default') for p in existing_projects):
                # Default channel and no default project, map all to default channel
                should_map = True
            
            if should_map:
                # Create API object
                api = {
                    'api_id': f"api_{project_id}",
                    'name': project_name,
                    'project_id': project_id,
                    'client_secrets_file': project.get('client_secrets_file'),
                    'google_account_email': '',  # Will be filled after OAuth
                    'is_authenticated': True,  # Assume authenticated if project exists
                    'is_active': project.get('is_active', True),
                    'daily_quota': project.get('daily_quota', 10000),
                    'quota_used_today': project.get('quota_used_today', 0),
                    'upload_count_today': project.get('upload_count_today', 0),
                    'last_upload': project.get('last_upload'),
                    'last_reset': project.get('last_reset'),
                    'created_at': project.get('created_at', datetime.now().isoformat()),
                    'notes': project.get('notes', ''),
                    'quota_error_at': project.get('quota_error_at')
                }
                
                unified_channel['apis'].append(api)
        
        unified_channels.append(unified_channel)
    
    # Handle case: No channels exist but projects exist
    if not existing_channels and existing_projects:
        print("⚠️  Kanal yok ama proje var. Varsayılan kanal oluşturuluyor...")
        
        default_channel = {
            'id': 'channel_001',
            'channel_id': '',  # Will be filled after first OAuth
            'channel_title': 'Varsayılan Kanal',
            'channel_url': '',
            'thumbnail': '',
            'subscriber_count': 0,
            'video_count': 0,
            'description': '',
            'is_default': True,
            'is_active': True,
            'connected_at': None,
            'apis': []
        }
        
        # Map all projects to default channel
        for project in existing_projects:
            api = {
                'api_id': f"api_{project.get('id')}",
                'name': project.get('name'),
                'project_id': project.get('id'),
                'client_secrets_file': project.get('client_secrets_file'),
                'google_account_email': '',
                'is_authenticated': True,
                'is_active': project.get('is_active', True),
                'daily_quota': project.get('daily_quota', 10000),
                'quota_used_today': project.get('quota_used_today', 0),
                'upload_count_today': project.get('upload_count_today', 0),
                'last_upload': project.get('last_upload'),
                'last_reset': project.get('last_reset'),
                'created_at': project.get('created_at', datetime.now().isoformat()),
                'notes': project.get('notes', ''),
                'quota_error_at': project.get('quota_error_at')
            }
            default_channel['apis'].append(api)
        
        unified_channels.append(default_channel)
    
    # Create unified structure
    unified_data = {
        'channels': unified_channels,
        'rotation_strategy': projects_data.get('rotation_strategy', 'round_robin'),
        'auto_switch_on_quota_error': projects_data.get('auto_switch_on_quota_error', True),
        'quota_per_upload': projects_data.get('quota_per_upload', 1600),
        'quota_per_thumbnail': projects_data.get('quota_per_thumbnail', 50),
        'quota_safety_margin': projects_data.get('quota_safety_margin', 500),
        'migrated_at': datetime.now().isoformat(),
        'migration_version': '1.0'
    }
    
    return unified_data


def print_migration_summary(unified_data: Dict):
    """Print migration summary"""
    channels = unified_data.get('channels', [])
    
    print("\n" + "="*60)
    print("📊 MIGRATION ÖZETI")
    print("="*60)
    
    print(f"\n✅ Toplam Channel: {len(channels)}")
    
    for channel in channels:
        channel_title = channel.get('channel_title', 'N/A')
        channel_id = channel.get('channel_id', 'N/A')
        apis = channel.get('apis', [])
        is_default = channel.get('is_default', False)
        
        print(f"\n📺 {channel_title}")
        print(f"   ID: {channel.get('id')}")
        print(f"   Channel ID: {channel_id}")
        print(f"   Default: {'✅ Evet' if is_default else '❌ Hayır'}")
        print(f"   APIs: {len(apis)} adet")
        
        for api in apis:
            status = '🟢 Active' if api.get('is_active') else '🔴 Inactive'
            quota = api.get('quota_used_today', 0)
            quota_limit = api.get('daily_quota', 10000)
            quota_pct = int((quota / quota_limit) * 100) if quota_limit > 0 else 0
            
            print(f"      • {api.get('name')} - {status} - Kota: {quota}/{quota_limit} ({quota_pct}%)")
    
    print("\n" + "="*60)


def main():
    """Main migration function"""
    import argparse
    
    parser = argparse.ArgumentParser(description='YouTube Unified Channel Model Migration')
    parser.add_argument('--dry-run', action='store_true', help='Dosyaları değiştirmeden önizleme')
    parser.add_argument('--backup', action='store_true', default=True, help='Mevcut dosyaları yedekle')
    parser.add_argument('--no-backup', action='store_false', dest='backup', help='Yedekleme yapma')
    
    args = parser.parse_args()
    
    print("🚀 YouTube Unified Channel Model Migration")
    print("="*60)
    
    # Load existing data
    print("\n📂 Mevcut dosyalar yükleniyor...")
    projects_data = load_json(PROJECTS_FILE)
    channels_data = load_json(CHANNELS_FILE)
    
    if not projects_data and not channels_data:
        print("❌ youtube_projects.json veya youtube_channels.json bulunamadı!")
        return 1
    
    print(f"   ✅ Projects: {len(projects_data.get('projects', []))} adet")
    print(f"   ✅ Channels: {len(channels_data.get('channels', []))} adet")
    
    # Backup
    if args.backup and not args.dry_run:
        print("\n💾 Yedekleme yapılıyor...")
        backup_files()
    
    # Migrate
    print("\n🔄 Unified model'e migrate ediliyor...")
    unified_data = migrate_to_unified_model(projects_data, channels_data)
    
    # Print summary
    print_migration_summary(unified_data)
    
    # Save
    if args.dry_run:
        print("\n⚠️  DRY-RUN MODE: Dosyalar değiştirilmedi")
        print("\nÖnizleme:")
        print(json.dumps(unified_data, indent=2, ensure_ascii=False)[:1000])
        print("...")
    else:
        print(f"\n💾 Kaydediliyor: {UNIFIED_FILE}")
        save_json(UNIFIED_FILE, unified_data)
        print("✅ Migration tamamlandı!")
        
        # Archive old projects file
        if PROJECTS_FILE.exists():
            archive_path = DATA_DIR / 'youtube_projects.json.old'
            shutil.move(PROJECTS_FILE, archive_path)
            print(f"📦 Eski dosya arşivlendi: {archive_path}")
    
    print("\n" + "="*60)
    print("✅ İşlem başarıyla tamamlandı!")
    
    return 0


if __name__ == '__main__':
    sys.exit(main())
