"""
YouTube Multi-Project Manager
Manages multiple Google Cloud projects for quota rotation
"""
import os
import json
from pathlib import Path
from datetime import datetime, timezone, timedelta
from typing import Optional, Dict, List, Tuple
import sys


class YouTubeProjectManager:
    """
    Manages multiple YouTube API projects for quota rotation.
    
    Each project has its own:
    - client_secrets.json file
    - OAuth tokens
    - Daily quota tracking
    
    Rotation strategies:
    - round_robin: Rotate through projects sequentially
    - least_used: Use project with most remaining quota
    - failover: Use primary until quota exceeded, then switch
    """
    
    QUOTA_PER_UPLOAD = 1600  # Approximate quota cost per video upload
    QUOTA_PER_THUMBNAIL = 50
    DEFAULT_DAILY_QUOTA = 10000
    
    def __init__(self, data_dir: str):
        """
        Initialize project manager
        
        Args:
            data_dir: Path to data directory containing youtube_channels.json
        """
        self.data_dir = Path(data_dir)
        self.channels_file = self.data_dir / 'youtube_channels.json'
        self.credentials_dir = self.data_dir / 'youtube_credentials'
        
        # Ensure directories exist
        self.credentials_dir.mkdir(parents=True, exist_ok=True)
        
        # Load projects from unified system
        self._load_projects()
    
    def _load_projects(self):
        """Load projects configuration from unified youtube_channels.json"""
        # Initialize with default config
        self.config = self._default_config()
        
        # Load from unified youtube_channels.json
        if self.channels_file.exists():
            try:
                with open(self.channels_file, 'r', encoding='utf-8') as f:
                    channels_data = json.load(f)
                
                # Extract all APIs from all channels as projects
                for channel in channels_data.get('channels', []):
                    for api in channel.get('apis', []):
                        project_id = api.get('project_id')
                        if project_id:
                            # Add this API as a project
                            self.config['projects'].append({
                                'id': project_id,
                                'name': api.get('name', project_id),
                                'client_secrets_file': api.get('client_secrets_file', '').replace('youtube_credentials/', ''),
                                'daily_quota': api.get('daily_quota', self.DEFAULT_DAILY_QUOTA),
                                'quota_used_today': api.get('quota_used_today', 0),
                                'is_active': api.get('is_active', True),
                                'channel_id': channel.get('id'),  # Track which channel this belongs to
                                'api_id': api.get('api_id'),
                                'upload_count_today': api.get('upload_count_today', 0),
                                'last_upload': api.get('last_upload'),
                                'last_reset': api.get('last_reset')
                            })
                
                # Copy global settings from channels config
                self.config['rotation_strategy'] = channels_data.get('rotation_strategy', 'round_robin')
                self.config['auto_switch_on_quota_error'] = channels_data.get('auto_switch_on_quota_error', True)
                self.config['quota_per_upload'] = channels_data.get('quota_per_upload', 1600)
                self.config['quota_per_thumbnail'] = channels_data.get('quota_per_thumbnail', 50)
                self.config['quota_safety_margin'] = channels_data.get('quota_safety_margin', 500)
                
                if self.config['projects']:
                    print(f"   [INFO] {len(self.config['projects'])} proje yüklendi (Unified System)", file=sys.stderr)
                    
            except Exception as e:
                print(f"⚠️  Channels config okunamadı: {e}", file=sys.stderr)
    
    def _default_config(self) -> dict:
        """Create default configuration"""
        return {
            "projects": [],
            "rotation_strategy": "round_robin",
            "auto_switch_on_quota_error": True,
            "quota_per_upload": self.QUOTA_PER_UPLOAD,
            "quota_per_thumbnail": self.QUOTA_PER_THUMBNAIL,
            "quota_safety_margin": 500,
            "current_project_index": 0
        }
    
    def _save_projects(self):
        """Save projects configuration back to youtube_channels.json"""
        try:
            # Load current channels data
            if not self.channels_file.exists():
                print(f"⚠️  Channels dosyası bulunamadı, quota güncellenemiyor", file=sys.stderr)
                return
            
            with open(self.channels_file, 'r', encoding='utf-8') as f:
                channels_data = json.load(f)
            
            # Update quota info for each project back to its API
            for project in self.config['projects']:
                project_id = project['id']
                channel_id = project.get('channel_id')
                api_id = project.get('api_id')
                
                if not channel_id or not api_id:
                    continue
                
                # Find the channel and API
                for channel in channels_data.get('channels', []):
                    if channel['id'] == channel_id:
                        for api in channel.get('apis', []):
                            if api['api_id'] == api_id:
                                # Update quota info
                                api['quota_used_today'] = project.get('quota_used_today', 0)
                                api['upload_count_today'] = project.get('upload_count_today', 0)
                                api['last_upload'] = project.get('last_upload')
                                api['last_reset'] = project.get('last_reset')
                                api['is_active'] = project.get('is_active', True)
                                break
            
            # Save back to channels file
            with open(self.channels_file, 'w', encoding='utf-8') as f:
                json.dump(channels_data, f, ensure_ascii=False, indent=2)
                
        except Exception as e:
            print(f"⚠️  Quota bilgileri kaydedilemedi: {e}", file=sys.stderr)
    
    def add_project(
        self,
        name: str,
        client_secrets_file: str,
        daily_quota: int = 10000,
        notes: str = ""
    ) -> str:
        """
        Add a new YouTube API project
        
        Args:
            name: Project display name
            client_secrets_file: Filename of client_secrets in credentials dir
            daily_quota: Daily quota limit (default 10000)
            notes: Optional notes
            
        Returns:
            Project ID
        """
        # Generate unique project ID (find max existing ID + 1)
        existing_ids = [int(p['id'].split('_')[1]) for p in self.config['projects'] if p['id'].startswith('project_')]
        next_num = max(existing_ids, default=0) + 1
        project_id = f"project_{next_num}"
        
        # Verify client_secrets file exists
        secrets_path = self.credentials_dir / client_secrets_file
        if not secrets_path.exists():
            raise FileNotFoundError(f"Client secrets dosyası bulunamadı: {secrets_path}")
        
        project = {
            "id": project_id,
            "name": name,
            "client_secrets_file": client_secrets_file,
            "is_active": True,
            "is_default": len(self.config['projects']) == 0,
            "daily_quota": daily_quota,
            "quota_used_today": 0,
            "last_reset": datetime.now(timezone.utc).isoformat(),
            "upload_count_today": 0,
            "last_upload": None,
            "created_at": datetime.now(timezone.utc).isoformat(),
            "notes": notes
        }
        
        self.config['projects'].append(project)
        self._save_projects()
        
        print(f"✅ Proje eklendi: {name} ({project_id})", file=sys.stderr)
        return project_id
    
    def remove_project(self, project_id: str) -> bool:
        """Remove a project"""
        for i, project in enumerate(self.config['projects']):
            if project['id'] == project_id:
                self.config['projects'].pop(i)
                self._save_projects()
                print(f"🗑️  Proje silindi: {project_id}", file=sys.stderr)
                return True
        return False
    
    def get_projects(self, active_only: bool = True) -> List[Dict]:
        """Get all projects"""
        projects = self.config.get('projects', [])
        if active_only:
            return [p for p in projects if p.get('is_active', True)]
        return projects
    
    def get_project(self, project_id: str) -> Optional[Dict]:
        """Get specific project by ID"""
        for project in self.config.get('projects', []):
            if project['id'] == project_id:
                return project
        return None
    
    def get_best_project(self) -> Optional[Dict]:
        """
        Get the best project to use based on rotation strategy
        
        Returns:
            Best project dict or None if no available projects
        """
        self._reset_daily_quotas_if_needed()
        
        active_projects = self.get_projects(active_only=True)
        if not active_projects:
            return None
        
        strategy = self.config.get('rotation_strategy', 'round_robin')
        safety_margin = self.config.get('quota_safety_margin', 500)
        quota_per_upload = self.config.get('quota_per_upload', self.QUOTA_PER_UPLOAD)
        
        # Filter projects with enough quota
        available_projects = [
            p for p in active_projects
            if (p.get('daily_quota', self.DEFAULT_DAILY_QUOTA) - p.get('quota_used_today', 0)) 
               >= (quota_per_upload + safety_margin)
        ]
        
        if not available_projects:
            print("⚠️  Tüm projelerin kotası dolmuş!", file=sys.stderr)
            # Return project with most remaining quota anyway
            return max(active_projects, key=lambda p: 
                       p.get('daily_quota', self.DEFAULT_DAILY_QUOTA) - p.get('quota_used_today', 0))
        
        if strategy == 'round_robin':
            # Get next project in rotation
            current_index = self.config.get('current_project_index', 0)
            
            # Find next available project starting from current index
            for i in range(len(available_projects)):
                idx = (current_index + i) % len(available_projects)
                project = available_projects[idx]
                if project in available_projects:
                    # Update index for next call
                    self.config['current_project_index'] = (idx + 1) % len(available_projects)
                    self._save_projects()
                    return project
            
            return available_projects[0]
        
        elif strategy == 'least_used':
            # Return project with most remaining quota
            return max(available_projects, key=lambda p: 
                       p.get('daily_quota', self.DEFAULT_DAILY_QUOTA) - p.get('quota_used_today', 0))
        
        elif strategy == 'failover':
            # Use default project first, then others
            default_project = next((p for p in available_projects if p.get('is_default')), None)
            if default_project:
                return default_project
            return available_projects[0]
        
        return available_projects[0]
    
    def get_best_project_for_channel(self, channel_id: str) -> Optional[Dict]:
        """
        Get the best project for a specific YouTube channel
        
        Filters APIs by channel and returns the one with most remaining quota.
        Falls back to get_best_project() if channel not found or no APIs available.
        
        Args:
            channel_id: Channel ID from youtube_channels.json
            
        Returns:
            Best project dict with highest quota, or None
        """
        # Try to load unified channels file
        if not self.channels_file.exists():
            print(f"   [WARN] youtube_channels.json bulunamadı, fallback get_best_project() kullanılacak", file=sys.stderr)
            return self.get_best_project()
        
        try:
            with open(self.channels_file, 'r', encoding='utf-8') as f:
                channels_data = json.load(f)
        except Exception as e:
            print(f"   [WARN] youtube_channels.json okunamadı: {e}, fallback kullanılacak", file=sys.stderr)
            return self.get_best_project()
        
        # Find channel
        channel = None
        for ch in channels_data.get('channels', []):
            if ch.get('id') == channel_id:
                channel = ch
                break
        
        if not channel:
            print(f"   [WARN] Channel {channel_id} bulunamadı, fallback kullanılacak", file=sys.stderr)
            return self.get_best_project()
        
        # Get active & authenticated APIs for this channel
        channel_apis = []
        for api in channel.get('apis', []):
            if api.get('is_active') and api.get('is_authenticated'):
                project_id = api.get('project_id')
                # Check if this project exists in youtube_projects.json (legacy)
                # or use unified data directly
                channel_apis.append({
                    'project_id': project_id,
                    'api_id': api.get('api_id'),
                    'name': api.get('name', project_id),
                    'client_secrets_file': api.get('client_secrets_file', ''),
                    'daily_quota': api.get('daily_quota', self.DEFAULT_DAILY_QUOTA),
                    'quota_used_today': api.get('quota_used_today', 0),
                    'is_active': True
                })
        
        if not channel_apis:
            print(f"   [WARN] Channel {channel_id} için aktif API yok, fallback kullanılacak", file=sys.stderr)
            return self.get_best_project()
        
        # Reset quotas if needed
        self._reset_daily_quotas_if_needed()
        
        # Filter by remaining quota
        safety_margin = self.config.get('quota_safety_margin', 500)
        quota_per_upload = self.config.get('quota_per_upload', self.QUOTA_PER_UPLOAD)
        
        available_apis = [
            api for api in channel_apis
            if (api.get('daily_quota', self.DEFAULT_DAILY_QUOTA) - api.get('quota_used_today', 0))
               >= (quota_per_upload + safety_margin)
        ]
        
        if not available_apis:
            print(f"   [WARN] Channel {channel_id} için kota yeterli API yok", file=sys.stderr)
            return None
        
        # Return API with most remaining quota
        best_api = max(available_apis, key=lambda api:
                      api.get('daily_quota', self.DEFAULT_DAILY_QUOTA) - api.get('quota_used_today', 0))
        
        print(f"   [INFO] Channel {channel_id} için API seçildi: {best_api['name']}", file=sys.stderr)
        
        # Return in project format (for compatibility)
        return {
            'id': best_api['project_id'],
            'name': best_api['name'],
            'client_secrets_file': best_api['client_secrets_file'],
            'daily_quota': best_api['daily_quota'],
            'quota_used_today': best_api['quota_used_today'],
            'is_active': True
        }
    
    def get_credentials_path(self, project_id: str) -> Optional[Path]:
        """Get client_secrets path for a project"""
        project = self.get_project(project_id)
        if not project:
            return None
        return self.credentials_dir / project['client_secrets_file']
    
    def get_token_path(self, project_id: str, channel_id: Optional[str] = None) -> Path:
        """Get token file path for a project (JSON token format)."""
        if channel_id:
            return self.credentials_dir / f"{project_id}_{channel_id}_token.json"
        return self.credentials_dir / f"{project_id}_token.json"
    
    def record_upload(self, project_id: str, success: bool = True, with_thumbnail: bool = False):
        """
        Record an upload and update quota usage
        
        Args:
            project_id: Project that was used
            success: Whether upload was successful
            with_thumbnail: Whether thumbnail was uploaded
        """
        for project in self.config['projects']:
            if project['id'] == project_id:
                quota_cost = self.config.get('quota_per_upload', self.QUOTA_PER_UPLOAD)
                if with_thumbnail:
                    quota_cost += self.config.get('quota_per_thumbnail', self.QUOTA_PER_THUMBNAIL)
                
                project['quota_used_today'] = project.get('quota_used_today', 0) + quota_cost
                project['upload_count_today'] = project.get('upload_count_today', 0) + 1
                project['last_upload'] = datetime.now(timezone.utc).isoformat()
                
                self._save_projects()
                
                remaining = project.get('daily_quota', self.DEFAULT_DAILY_QUOTA) - project['quota_used_today']
                print(f"📊 [{project['name']}] Kota: {project['quota_used_today']}/{project['daily_quota']} (Kalan: {remaining})", file=sys.stderr)
                break
    
    def record_quota_error(self, project_id: str):
        """
        Record a quota exceeded error for a project
        
        Args:
            project_id: Project that hit quota limit
        """
        for project in self.config['projects']:
            if project['id'] == project_id:
                # Mark quota as fully used
                project['quota_used_today'] = project.get('daily_quota', self.DEFAULT_DAILY_QUOTA)
                project['quota_error_at'] = datetime.now(timezone.utc).isoformat()
                self._save_projects()
                
                print(f"⚠️  [{project['name']}] Kota hatası kaydedildi!", file=sys.stderr)
                break
    
    def _reset_daily_quotas_if_needed(self):
        """Reset daily quotas if a new day has started (UTC)"""
        now = datetime.now(timezone.utc)
        today = now.date()
        
        for project in self.config['projects']:
            last_reset = project.get('last_reset')
            if last_reset:
                try:
                    last_reset_date = datetime.fromisoformat(last_reset.replace('Z', '+00:00')).date()
                    if last_reset_date < today:
                        # New day - reset quota
                        project['quota_used_today'] = 0
                        project['upload_count_today'] = 0
                        project['last_reset'] = now.isoformat()
                        print(f"🔄 [{project['name']}] Günlük kota sıfırlandı", file=sys.stderr)
                except Exception:
                    pass
            else:
                project['last_reset'] = now.isoformat()
        
        self._save_projects()
    
    def get_total_remaining_quota(self) -> int:
        """Get total remaining quota across all projects"""
        self._reset_daily_quotas_if_needed()
        
        total = 0
        for project in self.get_projects(active_only=True):
            remaining = project.get('daily_quota', self.DEFAULT_DAILY_QUOTA) - project.get('quota_used_today', 0)
            total += max(0, remaining)
        return total
    
    def get_estimated_uploads_remaining(self) -> int:
        """Estimate how many uploads can still be done today"""
        total_quota = self.get_total_remaining_quota()
        quota_per_upload = self.config.get('quota_per_upload', self.QUOTA_PER_UPLOAD)
        safety_margin = self.config.get('quota_safety_margin', 500)
        
        return max(0, (total_quota - safety_margin) // quota_per_upload)
    
    def get_status_summary(self) -> Dict:
        """Get status summary of all projects"""
        self._reset_daily_quotas_if_needed()
        
        projects = self.get_projects(active_only=False)
        active_count = len([p for p in projects if p.get('is_active')])
        
        total_quota = sum(p.get('daily_quota', self.DEFAULT_DAILY_QUOTA) for p in projects if p.get('is_active'))
        total_used = sum(p.get('quota_used_today', 0) for p in projects if p.get('is_active'))
        total_uploads = sum(p.get('upload_count_today', 0) for p in projects if p.get('is_active'))
        
        return {
            'total_projects': len(projects),
            'active_projects': active_count,
            'total_quota': total_quota,
            'quota_used': total_used,
            'quota_remaining': total_quota - total_used,
            'uploads_today': total_uploads,
            'estimated_uploads_remaining': self.get_estimated_uploads_remaining(),
            'rotation_strategy': self.config.get('rotation_strategy', 'round_robin'),
            'projects': [
                {
                    'id': p['id'],
                    'name': p['name'],
                    'is_active': p.get('is_active', True),
                    'is_default': p.get('is_default', False),
                    'quota': p.get('daily_quota', self.DEFAULT_DAILY_QUOTA),
                    'used': p.get('quota_used_today', 0),
                    'remaining': p.get('daily_quota', self.DEFAULT_DAILY_QUOTA) - p.get('quota_used_today', 0),
                    'uploads_today': p.get('upload_count_today', 0)
                }
                for p in projects
            ]
        }
    
    def print_status(self):
        """Print status summary to console"""
        status = self.get_status_summary()
        
        print("\n" + "=" * 60, file=sys.stderr)
        print("📊 YOUTUBE PROJELERİ DURUMU", file=sys.stderr)
        print("=" * 60, file=sys.stderr)
        print(f"Toplam proje: {status['total_projects']} ({status['active_projects']} aktif)", file=sys.stderr)
        print(f"Strateji: {status['rotation_strategy']}", file=sys.stderr)
        print(f"Toplam kota: {status['quota_used']}/{status['total_quota']} ({status['quota_remaining']} kalan)", file=sys.stderr)
        print(f"Bugün yüklenen: {status['uploads_today']} video", file=sys.stderr)
        print(f"Tahmini kalan: ~{status['estimated_uploads_remaining']} video", file=sys.stderr)
        print("-" * 60, file=sys.stderr)
        
        for p in status['projects']:
            status_icon = "✅" if p['is_active'] else "⏸️"
            default_mark = " ⭐" if p['is_default'] else ""
            print(f"{status_icon} {p['name']}{default_mark}: {p['used']}/{p['quota']} ({p['uploads_today']} video)", file=sys.stderr)
        
        print("=" * 60 + "\n", file=sys.stderr)


def main():
    """CLI for project management"""
    import sys
    
    base_dir = Path(__file__).parent.parent.parent
    data_dir = base_dir / 'data'
    
    manager = YouTubeProjectManager(str(data_dir))
    
    if len(sys.argv) < 2:
        manager.print_status()
        return
    
    command = sys.argv[1]
    
    if command == 'status':
        manager.print_status()
    
    elif command == 'add':
        if len(sys.argv) < 4:
            print("Kullanım: python project_manager.py add <name> <client_secrets_file> [quota] [notes]")
            sys.exit(1)
        
        name = sys.argv[2]
        secrets_file = sys.argv[3]
        quota = int(sys.argv[4]) if len(sys.argv) > 4 else 10000
        notes = sys.argv[5] if len(sys.argv) > 5 else ""
        
        try:
            project_id = manager.add_project(name, secrets_file, quota, notes)
            print(f"Proje eklendi: {project_id}")
        except Exception as e:
            print(f"Hata: {e}")
            sys.exit(1)
    
    elif command == 'remove':
        if len(sys.argv) < 3:
            print("Kullanım: python project_manager.py remove <project_id>")
            sys.exit(1)
        
        project_id = sys.argv[2]
        if manager.remove_project(project_id):
            print(f"Proje silindi: {project_id}")
        else:
            print(f"Proje bulunamadı: {project_id}")
            sys.exit(1)
    
    elif command == 'best':
        project = manager.get_best_project()
        if project:
            print(f"En iyi proje: {project['name']} ({project['id']})")
            remaining = project['daily_quota'] - project.get('quota_used_today', 0)
            print(f"Kalan kota: {remaining}")
        else:
            print("Kullanılabilir proje yok!")
    
    else:
        print(f"Bilinmeyen komut: {command}")
        print("Komutlar: status, add, remove, best")
        sys.exit(1)


if __name__ == '__main__':
    main()
