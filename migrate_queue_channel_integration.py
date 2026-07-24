#!/usr/bin/env python3
"""
Queue Channel Integration Migration Script

Migrates existing queues.json to add channelId to YouTube platform settings.
Old queues without channelId will use the default channel.

Usage:
    python migrate_queue_channel_integration.py
"""

import os
import json
import sys
from datetime import datetime
from pathlib import Path


def main():
    """Main migration function"""
    
    # Paths
    script_dir = Path(__file__).parent
    data_dir = script_dir / 'data'
    queues_file = data_dir / 'queues.json'
    channels_file = data_dir / 'youtube_channels.json'
    backup_dir = data_dir / 'backups'
    
    print("=" * 60)
    print("🔄 Queue Channel Integration Migration")
    print("=" * 60)
    
    # Check files exist
    if not queues_file.exists():
        print(f"❌ queues.json bulunamadı: {queues_file}")
        return 1
    
    if not channels_file.exists():
        print(f"⚠️  youtube_channels.json bulunamadı: {channels_file}")
        print("   Migration devam edecek ama default channel atanamayacak.")
    
    # Load queues
    print(f"\n📂 Yükleniyor: {queues_file}")
    try:
        with open(queues_file, 'r', encoding='utf-8') as f:
            queues_data = json.load(f)
    except Exception as e:
        print(f"❌ queues.json okunamadı: {e}")
        return 1
    
    # Load channels (for default channel)
    default_channel_id = None
    if channels_file.exists():
        try:
            with open(channels_file, 'r', encoding='utf-8') as f:
                channels_data = json.load(f)
            
            # Find default channel
            for channel in channels_data.get('channels', []):
                if channel.get('is_default'):
                    default_channel_id = channel['id']
                    print(f"✅ Default channel bulundu: {channel.get('channel_title')} ({default_channel_id})")
                    break
            
            if not default_channel_id and channels_data.get('channels'):
                # No default? Use first active channel
                for channel in channels_data.get('channels', []):
                    if channel.get('is_active'):
                        default_channel_id = channel['id']
                        print(f"⚠️  Default channel yok, ilk aktif channel kullanılacak: {channel.get('channel_title')} ({default_channel_id})")
                        break
        except Exception as e:
            print(f"⚠️  youtube_channels.json okunamadı: {e}")
    
    # Create backup
    backup_dir.mkdir(exist_ok=True)
    timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
    backup_file = backup_dir / f'queues_{timestamp}.json'
    
    print(f"\n💾 Backup oluşturuluyor: {backup_file}")
    try:
        with open(backup_file, 'w', encoding='utf-8') as f:
            json.dump(queues_data, f, ensure_ascii=False, indent=2)
        print("   ✅ Backup başarılı")
    except Exception as e:
        print(f"   ❌ Backup başarısız: {e}")
        return 1
    
    # Migrate queues
    print(f"\n🔄 Migration başlıyor...")
    
    queues = queues_data.get('queues', [])
    updated_count = 0
    skipped_count = 0
    
    for queue in queues:
        queue_id = queue.get('id', 'unknown')
        platforms = queue.get('platforms', [])
        
        # Check if YouTube is in platforms
        if 'youtube' not in platforms:
            continue
        
        # Get platform settings
        platform_settings = queue.get('platform_settings', {})
        if 'youtube' not in platform_settings:
            platform_settings['youtube'] = {}
            queue['platform_settings'] = platform_settings
        
        youtube_settings = platform_settings['youtube']
        
        # Check if channelId already exists
        if 'channelId' in youtube_settings:
            skipped_count += 1
            print(f"   ⏭️  {queue_id}: channelId zaten var ({youtube_settings['channelId']})")
            continue
        
        # Add default channelId
        if default_channel_id:
            youtube_settings['channelId'] = default_channel_id
            updated_count += 1
            print(f"   ✅ {queue_id}: channelId eklendi → {default_channel_id}")
        else:
            # No default channel available, leave empty (fallback behavior)
            updated_count += 1
            print(f"   ⚠️  {queue_id}: Default channel yok, channelId boş bırakıldı")
    
    # Save updated queues
    if updated_count > 0:
        print(f"\n💾 Değişiklikler kaydediliyor...")
        try:
            with open(queues_file, 'w', encoding='utf-8') as f:
                json.dump(queues_data, f, ensure_ascii=False, indent=2)
            print("   ✅ queues.json güncellendi")
        except Exception as e:
            print(f"   ❌ Kaydetme hatası: {e}")
            print(f"   💾 Backup'tan geri yüklemek için: cp {backup_file} {queues_file}")
            return 1
    
    # Summary
    print("\n" + "=" * 60)
    print("📊 Migration Özeti")
    print("=" * 60)
    print(f"Toplam kuyruk: {len(queues)}")
    print(f"Güncellenen: {updated_count}")
    print(f"Atlanan (zaten var): {skipped_count}")
    print(f"YouTube olmayan: {len(queues) - updated_count - skipped_count}")
    
    if default_channel_id:
        print(f"\nDefault Channel: {default_channel_id}")
    else:
        print(f"\n⚠️  Default channel bulunamadı - kuyruklarda channelId boş")
    
    print(f"\n✅ Migration tamamlandı!")
    print(f"💾 Backup: {backup_file}")
    
    return 0


if __name__ == '__main__':
    sys.exit(main())
