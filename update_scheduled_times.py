#!/usr/bin/env python3
"""
Calculate and update scheduled_time for all videos in queues
"""
import json
import sys
from pathlib import Path
from datetime import datetime

# Add parent to path for imports
sys.path.insert(0, str(Path(__file__).parent))

def load_json(file_path):
    """Load JSON file"""
    with open(file_path, 'r', encoding='utf-8') as f:
        return json.load(f)

def save_json(file_path, data):
    """Save JSON file"""
    with open(file_path, 'w', encoding='utf-8') as f:
        json.dump(data, f, indent=2, ensure_ascii=False)

def main():
    """Update all scheduled times"""
    data_dir = Path(__file__).parent / 'data'
    queues_file = data_dir / 'queues.json'
    
    print("🔄 Tüm videoların scheduled_time'larını hesaplıyorum...")
    
    queues_data = load_json(queues_file)
    
    # Import PHP calculateScheduledTime logic (reimplemented in Python)
    from datetime import datetime, timedelta, timezone
    
    def calculate_scheduled_time_py(queue, position, platforms):
        """Calculate scheduled time (Python port of PHP function)"""
        tz = timezone.utc
        now = datetime.now(tz)
        
        # Get platform settings
        primary_platform = platforms[0] if platforms else 'youtube'
        platform_settings = queue.get('platform_settings', {}).get(primary_platform, {})
        
        schedule_type = platform_settings.get('scheduleType', 'now')
        
        # NOW: immediate
        if schedule_type == 'now':
            return now.isoformat()
        
        # INTERVAL: based on interval and daily limit
        if schedule_type == 'interval':
            start_time = platform_settings.get('startTime', '09:00')
            interval_minutes = int(platform_settings.get('intervalMinutes', 30))
            daily_limit = int(platform_settings.get('dailyLimit', 0))
            
            # Parse start time
            hour, minute = map(int, start_time.split(':'))
            base_time = now.replace(hour=hour, minute=minute, second=0, microsecond=0)
            
            # Position offset (1-indexed)
            interval_offset = position - 1
            
            if daily_limit > 0:
                # Calculate day and position in day
                day = interval_offset // daily_limit
                position_in_day = interval_offset % daily_limit
                
                # Add days
                if day > 0:
                    base_time += timedelta(days=day)
                
                # Add interval
                base_time += timedelta(minutes=position_in_day * interval_minutes)
            else:
                # No daily limit, just add interval
                base_time += timedelta(minutes=interval_offset * interval_minutes)
            
            # If in past, move to tomorrow
            if base_time < now:
                base_time += timedelta(days=1)
            
            return base_time.isoformat()
        
        # SPECIFIC: specific times
        if schedule_type == 'specific':
            specific_times = platform_settings.get('specificTimes', ['09:00', '15:00', '21:00'])
            daily_limit = int(platform_settings.get('dailyLimit', len(specific_times)))
            
            if daily_limit == 0:
                daily_limit = len(specific_times)
            
            # Calculate day and time index
            day = (position - 1) // daily_limit
            time_index = (position - 1) % daily_limit
            
            # Get time slot
            time_slot = specific_times[time_index % len(specific_times)]
            hour, minute = map(int, time_slot.split(':'))
            
            scheduled = now.replace(hour=hour, minute=minute, second=0, microsecond=0)
            
            # Add days
            if day > 0:
                scheduled += timedelta(days=day)
            
            # If in past, move to tomorrow
            if scheduled < now:
                scheduled += timedelta(days=1)
            
            return scheduled.isoformat()
        
        # Fallback: now
        return now.isoformat()
    
    updated = 0
    for queue in queues_data.get('queues', []):
        platforms = queue.get('platforms', ['youtube'])
        
        for video in queue.get('videos', []):
            position = video.get('position', 1)
            
            # Recalculate scheduled_time
            scheduled_time = calculate_scheduled_time_py(queue, position, platforms)
            video['scheduled_time'] = scheduled_time
            
            updated += 1
            print(f"  ✅ {video.get('job_id')} (pos {position}) → {scheduled_time}")
    
    # Save
    save_json(queues_file, queues_data)
    
    print(f"\n✅ {updated} videonun scheduled_time'ı güncellendi")

if __name__ == '__main__':
    main()
