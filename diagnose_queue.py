"""
Queue System Diagnostic Tool
Analyze why queue is not processing items
"""
import json
import sys
from pathlib import Path
from datetime import datetime, timezone

sys.path.insert(0, str(Path(__file__).parent / 'python'))

from scheduler.unified_queue_manager import UnifiedQueueManager

def diagnose_queue():
    """Diagnose queue issues"""
    print("=" * 60)
    print("QUEUE SYSTEM DIAGNOSTIC")
    print("=" * 60)
    
    # 1. Check if schedulers are running
    print("\n1️⃣  SCHEDULER STATUS:")
    # (Manual check via task manager)
    print("   ⚠️  Check manually: Are schedulers running?")
    print("   - start_social_scheduler.bat")
    print("   - start_production_scheduler.bat")
    
    # 2. Check queue manager
    print("\n2️⃣  QUEUE MANAGER:")
    mgr = UnifiedQueueManager('data')
    pending = mgr.get_pending_items()
    print(f"   Pending items: {len(pending)}")
    
    if len(pending) > 0:
        print("\n   First pending item:")
        item = pending[0]
        print(f"   - Job ID: {item.get('job_id')}")
        print(f"   - Platforms: {item.get('platforms')}")
        print(f"   - Scheduled: {item.get('scheduled_time')}")
        print(f"   - Immediate: {item.get('_immediate_mode')}")
    
    # 3. Check queues.json directly
    print("\n3️⃣  QUEUES.JSON ANALYSIS:")
    with open('data/queues.json', 'r', encoding='utf-8') as f:
        data = json.load(f)
    
    for queue in data.get('queues', []):
        print(f"\n   Queue: {queue.get('name')}")
        print(f"   - ID: {queue.get('id')}")
        print(f"   - Active: {queue.get('is_active')}")
        print(f"   - Videos: {len(queue.get('videos', []))}")
        
        # Check platform settings
        ps = queue.get('platform_settings', {})
        for platform, settings in ps.items():
            print(f"   - {platform}:")
            print(f"     • Enabled: {settings.get('enabled')}")
            print(f"     • Schedule Type: {settings.get('scheduleType')}")
            print(f"     • Daily Limit: {settings.get('dailyLimit', 0)}")
        
        # Analyze video statuses
        videos = queue.get('videos', [])
        if len(videos) > 0:
            pending_count = 0
            processing_count = 0
            success_count = 0
            failed_count = 0
            
            for v in videos:
                v_status = v.get('platform_status', {})
                for plat, stat in v_status.items():
                    if isinstance(stat, dict):
                        s = stat.get('status', 'unknown')
                    else:
                        s = stat
                    
                    if s == 'pending':
                        pending_count += 1
                    elif s == 'processing':
                        processing_count += 1
                    elif s == 'success':
                        success_count += 1
                    elif s == 'failed':
                        failed_count += 1
            
            print(f"\n   Video Status Breakdown:")
            print(f"   - Pending: {pending_count}")
            print(f"   - Processing: {processing_count}")
            print(f"   - Success: {success_count}")
            print(f"   - Failed: {failed_count}")
            
            # Show first pending video details
            for v in videos:
                v_status = v.get('platform_status', {}).get('youtube', {})
                if isinstance(v_status, dict):
                    if v_status.get('status') == 'pending':
                        print(f"\n   First Pending Video:")
                        print(f"   - Job ID: {v.get('job_id')}")
                        print(f"   - Scheduled: {v.get('scheduled_time')}")
                        print(f"   - Added: {v.get('added_at')}")
                        break
    
    # 4. Check YouTube projects quota
    print("\n4️⃣  YOUTUBE QUOTA STATUS:")
    with open('data/youtube_projects.json', 'r', encoding='utf-8') as f:
        projects = json.load(f)
    
    for proj in projects.get('projects', []):
        remaining = proj.get('daily_quota', 10000) - proj.get('quota_used_today', 0)
        print(f"\n   {proj.get('name')}:")
        print(f"   - Quota: {proj.get('quota_used_today', 0)}/{proj.get('daily_quota', 10000)}")
        print(f"   - Remaining: {remaining}")
        print(f"   - Active: {proj.get('is_active')}")
        print(f"   - Last Reset: {proj.get('last_reset')}")
    
    # 5. Check errors
    print("\n5️⃣  ERROR LOG:")
    try:
        with open('data/scheduler_errors.json', 'r', encoding='utf-8') as f:
            errors = json.load(f)
        
        recent_errors = errors.get('errors', [])[-3:]
        print(f"   Last 3 errors:")
        for err in recent_errors:
            print(f"\n   - [{err.get('timestamp')}]")
            print(f"     Job: {err.get('job_id')}")
            print(f"     Error: {err.get('error_message')}")
            print(f"     Resolved: {err.get('resolved')}")
    except Exception as e:
        print(f"   ⚠️  Could not read errors: {e}")
    
    # 6. Summary and recommendations
    print("\n" + "=" * 60)
    print("DIAGNOSIS SUMMARY:")
    print("=" * 60)
    
    if len(pending) == 0:
        print("\n❌ PROBLEM: No pending items found!")
        print("\nPossible reasons:")
        print("  1. All videos already uploaded (check success count)")
        print("  2. All videos marked as failed")
        print("  3. Queue is inactive (is_active = false)")
        print("  4. Platform settings disabled")
        print("  5. Schedule time not reached yet")
    else:
        print(f"\n✅ {len(pending)} items waiting to be processed")
        print("\nCheck:")
        print("  1. Are schedulers running? (Task Manager)")
        print("  2. Check scheduler logs (data/scheduler.log)")
        print("  3. YouTube quota available?")
    
    print("\n" + "=" * 60)

if __name__ == '__main__':
    diagnose_queue()
