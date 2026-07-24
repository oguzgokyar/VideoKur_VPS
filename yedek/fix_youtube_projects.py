"""
Fix youtube_projects.json - Reset quotas and fix last_reset format
"""
import json
from datetime import datetime, timezone
from pathlib import Path

def fix_projects():
    """Fix youtube_projects.json format and reset quotas"""
    projects_file = Path('data/youtube_projects.json')
    
    if not projects_file.exists():
        print("❌ youtube_projects.json not found!")
        return False
    
    # Load projects
    with open(projects_file, 'r', encoding='utf-8') as f:
        data = json.load(f)
    
    print('🔧 Fixing youtube_projects.json...')
    print(f'Projects found: {len(data["projects"])}')
    
    # Fix last_reset format for all projects
    for project in data['projects']:
        old_reset = project.get('last_reset', '')
        print(f'\n📦 Project: {project["name"]}')
        print(f'  Old last_reset: {old_reset}')
        
        # Set to current time in ISO format
        project['last_reset'] = datetime.now(timezone.utc).isoformat()
        print(f'  New last_reset: {project["last_reset"]}')
        
        # Reset quota to 0 for today
        project['quota_used_today'] = 0
        project['upload_count_today'] = 0
        print(f'  ✅ Quota reset to 0')
    
    # Save back
    with open(projects_file, 'w', encoding='utf-8') as f:
        json.dump(data, f, indent=2, ensure_ascii=False)
    
    print('\n✅ youtube_projects.json fixed!')
    print('📊 All quotas reset to 0')
    print('🔄 last_reset set to current time')
    
    return True

if __name__ == '__main__':
    fix_projects()
