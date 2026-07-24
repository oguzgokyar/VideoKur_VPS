"""
Global/Platform Queue Settings Migration Script
================================================
Removes duplicate schedule settings from global level,
keeps only timezone and metadata in global schedule object.

Author: Copilot
Date: 2026-03-31
"""

import json
import os
from datetime import datetime
from pathlib import Path

def migrate_queue_structure():
    """Migrate queues.json to new global/platform structure"""
    
    queues_file = Path('data/queues.json')
    
    if not queues_file.exists():
        print("❌ data/queues.json bulunamadı!")
        return False
    
    # Backup first
    backup_dir = Path('data/backup_' + datetime.now().strftime('%Y%m%d_%H%M%S'))
    backup_dir.mkdir(exist_ok=True)
    backup_file = backup_dir / 'queues.json'
    
    print(f"📦 Backup oluşturuluyor: {backup_file}")
    
    with open(queues_file, 'r', encoding='utf-8') as f:
        data = json.load(f)
    
    with open(backup_file, 'w', encoding='utf-8') as f:
        json.dump(data, f, indent=4, ensure_ascii=False)
    
    print(f"✅ Backup tamamlandı!")
    
    # Migration
    migrated_count = 0
    
    for queue in data.get('queues', []):
        queue_id = queue.get('id', 'unknown')
        print(f"\n🔄 Migrating queue: {queue_id}")
        
        # Get current schedule
        schedule = queue.get('schedule', {})
        platform_settings = queue.get('platform_settings', {})
        
        # Extract values that should move to platform_settings
        schedule_type = schedule.get('type', 'now')
        interval_hours = schedule.get('interval_hours', 1)
        interval_minutes = schedule.get('interval_minutes', 60)
        start_time = schedule.get('start_time', '')
        daily_limit = schedule.get('daily_limit', 0)
        specific_times = schedule.get('specific_times', [])
        timezone = schedule.get('timezone', 'Europe/Istanbul')
        
        print(f"   📊 Global schedule: type={schedule_type}, interval={interval_hours}h, daily_limit={daily_limit}")
        
        # Update each platform's settings
        for platform_name, settings in platform_settings.items():
            if not settings.get('enabled', False):
                print(f"   ⏭️  {platform_name}: disabled, skipping")
                continue
            
            # Only set if not already present (platform-specific overrides take priority)
            if 'scheduleType' not in settings:
                settings['scheduleType'] = schedule_type
                print(f"   ✓ {platform_name}: scheduleType = {schedule_type}")
            
            if 'intervalHours' not in settings:
                settings['intervalHours'] = str(interval_hours)
                print(f"   ✓ {platform_name}: intervalHours = {interval_hours}")
            
            if 'intervalMinutes' not in settings:
                settings['intervalMinutes'] = str(interval_minutes)
                print(f"   ✓ {platform_name}: intervalMinutes = {interval_minutes}")
            
            if 'startTime' not in settings:
                settings['startTime'] = start_time
                print(f"   ✓ {platform_name}: startTime = {start_time}")
            
            if 'dailyLimit' not in settings:
                settings['dailyLimit'] = str(daily_limit)
                print(f"   ✓ {platform_name}: dailyLimit = {daily_limit}")
            
            if 'specificTimes' not in settings:
                settings['specificTimes'] = specific_times
                print(f"   ✓ {platform_name}: specificTimes = {specific_times}")
        
        # Clean global schedule - keep only timezone
        new_schedule = {
            'timezone': timezone
        }
        
        queue['schedule'] = new_schedule
        print(f"   🧹 Global schedule cleaned, kept only timezone: {timezone}")
        
        migrated_count += 1
    
    # Save migrated data
    print(f"\n💾 Saving migrated data to {queues_file}...")
    with open(queues_file, 'w', encoding='utf-8') as f:
        json.dump(data, f, indent=4, ensure_ascii=False)
    
    print(f"\n✅ Migration tamamlandı!")
    print(f"   📊 {migrated_count} queue güncellendi")
    print(f"   📦 Backup: {backup_file}")
    
    return True

def verify_migration():
    """Verify migration was successful"""
    
    queues_file = Path('data/queues.json')
    
    if not queues_file.exists():
        print("❌ data/queues.json bulunamadı!")
        return False
    
    with open(queues_file, 'r', encoding='utf-8') as f:
        data = json.load(f)
    
    print("\n🔍 VERIFICATION REPORT")
    print("=" * 60)
    
    all_ok = True
    
    for queue in data.get('queues', []):
        queue_id = queue.get('id', 'unknown')
        schedule = queue.get('schedule', {})
        platform_settings = queue.get('platform_settings', {})
        
        print(f"\n📋 Queue: {queue_id}")
        
        # Check global schedule only has timezone
        expected_global_keys = {'timezone'}
        actual_global_keys = set(schedule.keys())
        
        if actual_global_keys == expected_global_keys:
            print(f"   ✅ Global schedule: OK (only timezone)")
        else:
            extra_keys = actual_global_keys - expected_global_keys
            if extra_keys:
                print(f"   ⚠️  Global schedule: Extra keys found: {extra_keys}")
                all_ok = False
            missing_keys = expected_global_keys - actual_global_keys
            if missing_keys:
                print(f"   ⚠️  Global schedule: Missing keys: {missing_keys}")
                all_ok = False
        
        # Check each enabled platform has required keys
        required_platform_keys = {
            'enabled', 'scheduleType', 'intervalHours', 'intervalMinutes',
            'startTime', 'dailyLimit', 'specificTimes'
        }
        
        for platform_name, settings in platform_settings.items():
            if not settings.get('enabled', False):
                continue
            
            actual_platform_keys = set(settings.keys())
            missing = required_platform_keys - actual_platform_keys
            
            if not missing:
                print(f"   ✅ {platform_name}: OK (all schedule keys present)")
            else:
                print(f"   ⚠️  {platform_name}: Missing keys: {missing}")
                all_ok = False
    
    print("\n" + "=" * 60)
    if all_ok:
        print("✅ All queues migrated successfully!")
    else:
        print("⚠️  Some issues found, please review above")
    
    return all_ok

if __name__ == '__main__':
    print("🚀 QUEUE STRUCTURE MIGRATION")
    print("=" * 60)
    print("This script will:")
    print("  1. Backup current queues.json")
    print("  2. Move schedule settings from global to platform_settings")
    print("  3. Keep only 'timezone' in global schedule")
    print("  4. Verify migration")
    print("=" * 60)
    
    response = input("\nProceed with migration? (yes/no): ").strip().lower()
    
    if response != 'yes':
        print("❌ Migration cancelled")
        exit(0)
    
    # Run migration
    if migrate_queue_structure():
        print("\n" + "=" * 60)
        verify_migration()
    else:
        print("❌ Migration failed!")
        exit(1)
