"""
Multi-Platform Social Media Scheduler
Background service that processes social media upload queue
"""
import sys
import os
import time
import json
import traceback
from pathlib import Path
from datetime import datetime, timezone, timedelta
from typing import Dict, Optional, Tuple

# Add parent directory to path
sys.path.insert(0, str(Path(__file__).parent.parent))

# Queue manager is imported inside __init__ to avoid circular imports
from social.platform_optimizer import PlatformMetadataOptimizer
from youtube.metadata_optimizer import MetadataOptimizer

# Platform uploaders
from youtube.uploader import YouTubeUploader

# Multi-project manager
try:
    from youtube.project_manager import YouTubeProjectManager
    MULTI_PROJECT_AVAILABLE = True
except ImportError:
    MULTI_PROJECT_AVAILABLE = False

# Try to import social uploaders
try:
    from social.tiktok.uploader import TikTokUploader
    TIKTOK_AVAILABLE = True
except ImportError:
    TIKTOK_AVAILABLE = False

try:
    from social.instagram.uploader import InstagramUploader
    INSTAGRAM_AVAILABLE = True
except ImportError:
    INSTAGRAM_AVAILABLE = False

try:
    from social.facebook.uploader import FacebookUploader
    FACEBOOK_AVAILABLE = True
except ImportError:
    FACEBOOK_AVAILABLE = False


class SocialMediaScheduler:
    """Background scheduler for multi-platform social media uploads"""
    
    def __init__(self, base_dir: str, check_interval: int = 60):
        """
        Initialize scheduler
        
        Args:
            base_dir: Base directory of the application
            check_interval: Check interval in seconds (default 1 minute)
        """
        self.base_dir = Path(base_dir)
        self.check_interval = check_interval
        
        # Setup paths
        self.data_dir = self.base_dir / 'data'
        self.jobs_dir = self.data_dir / 'jobs'
        self.creds_dir = self.data_dir / 'social_credentials'
        self.youtube_creds = self.data_dir / 'youtube_credentials'
        
        # Ensure directories exist
        self.creds_dir.mkdir(parents=True, exist_ok=True)
        
        # Initialize UNIFIED queue manager (new system!)
        from scheduler.unified_queue_manager import UnifiedQueueManager
        from scheduler.error_logger import SchedulerErrorLogger
        self.queue_manager = UnifiedQueueManager(str(self.data_dir))
        self.error_logger = SchedulerErrorLogger(str(self.data_dir))
        
        # Clean up old errors on startup (keep last 7 days)
        try:
            self.error_logger.clear_old_errors(days=7)
            print("[ERROR_LOG] Cleaned up old errors (>7 days)", flush=True)
        except Exception as e:
            print(f"[ERROR_LOG] Cleanup failed: {e}", flush=True)
        
        # Initialize metadata optimizers
        config = self._load_config()
        gemini_key = config.get('geminiKey')
        gemini_model = config.get('geminiModel', 'gemini-2.0-flash')
        # Sosyal platformlar icin (TikTok, Instagram, Facebook) → caption + hashtags
        self.metadata_optimizer = PlatformMetadataOptimizer(gemini_key, model=gemini_model)
        # YouTube icin → optimize edilmis title + description + tags
        self.yt_metadata_optimizer = MetadataOptimizer(gemini_key=gemini_key, model=gemini_model)
        
        # Initialize uploaders
        self.uploaders = self._init_uploaders()
        
        print(f"[SOCIAL] Scheduler başlatıldı (UNIFIED QUEUE)")
        print(f"[SOCIAL] Kontrol aralığı: {check_interval} saniye")
        print(f"[SOCIAL] Base directory: {base_dir}")
        print(f"[SOCIAL] Aktif platformlar: {', '.join(self.uploaders.keys())}")
        print(f"[SOCIAL] ℹ️  Unified queue system (queues.json)")
    
    def _load_config(self) -> Dict:
        """Load application config"""
        config_file = self.data_dir / 'config.json'
        if config_file.exists():
            try:
                with open(config_file, 'r', encoding='utf-8') as f:
                    return json.load(f)
            except Exception as e:
                print(f"[WARN] Config loading failed: {e}")
        return {}
    
    def _init_uploaders(self) -> Dict:
        """Initialize available platform uploaders"""
        uploaders = {}
        
        # Initialize YouTube project manager for multi-project support
        self.youtube_project_manager = None
        if MULTI_PROJECT_AVAILABLE:
            try:
                self.youtube_project_manager = YouTubeProjectManager(str(self.data_dir))
                projects = self.youtube_project_manager.get_projects(active_only=True)
                if projects:
                    print(f"  [OK] YouTube Multi-Project: {len(projects)} proje aktif")
                    self.youtube_project_manager.print_status()
            except Exception as e:
                print(f"  [WARN] YouTube Project Manager başlatılamadı: {e}")
        
        # YouTube (always available if configured)
        # Note: We don't create a single uploader here for multi-project mode
        # Instead, we'll create one dynamically based on best available project
        try:
            # Check if multi-project mode has available projects
            if self.youtube_project_manager and self.youtube_project_manager.get_projects():
                uploaders['youtube'] = 'multi_project'  # Marker for multi-project mode
                print("  [OK] YouTube uploader hazır (Multi-Project Modu)")
            else:
                uploaders['youtube'] = YouTubeUploader(str(self.youtube_creds))
                print("  [OK] YouTube uploader hazır (Tek Proje)")
        except Exception as e:
            print(f"  [WARN] YouTube uploader başlatılamadı: {e}")
        
        # TikTok
        if TIKTOK_AVAILABLE:
            try:
                tiktok_creds = self.creds_dir / 'tiktok'
                uploaders['tiktok'] = TikTokUploader(str(tiktok_creds))
                print("  [OK] TikTok uploader hazır")
            except Exception as e:
                print(f"  [WARN] TikTok uploader başlatılamadı: {e}")
        
        # Instagram
        if INSTAGRAM_AVAILABLE:
            try:
                ig_creds = self.creds_dir / 'instagram'
                uploaders['instagram'] = InstagramUploader(str(ig_creds))
                print("  [OK] Instagram uploader hazır")
            except Exception as e:
                print(f"  [WARN] Instagram uploader başlatılamadı: {e}")
        
        # Facebook
        if FACEBOOK_AVAILABLE:
            try:
                fb_creds = self.creds_dir / 'facebook'
                uploaders['facebook'] = FacebookUploader(str(fb_creds))
                print("  [OK] Facebook uploader hazır")
            except Exception as e:
                print(f"  [WARN] Facebook uploader başlatılamadı: {e}")
        
        return uploaders
    
    def run(self):
        """Main scheduler loop"""
        print("\n🚀 Social Media Scheduler çalışıyor...\n")
        
        try:
            while True:
                self.process_queue()
                time.sleep(self.check_interval)
                
        except KeyboardInterrupt:
            print("\n\n[STOP] Scheduler durduruldu")
        except Exception as e:
            print(f"\n❌ Scheduler hatası: {e}")
            raise
    
    def process_queue(self):
        """Process pending items in queue"""
        pending = self.queue_manager.get_pending_items()
        
        if not pending:
            now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
            print(f"[{now}] ⏳ Bekleyen yükleme yok", end='\r')
            return
        
        print(f"\n📋 {len(pending)} yükleme işleniyor...")
        
        # Load queues data to check daily limits
        queues_data = self._load_queues()
        
        for item in pending:
            try:
                # Check daily limit before processing
                limit_check = self._check_daily_limit_with_reschedule(item, queues_data)
                if not limit_check['allowed']:
                    queue_id = item.get('queue_id', 'unknown')
                    job_id = item.get('job_id', 'unknown')
                    if limit_check.get('rescheduled'):
                        new_date = limit_check.get('new_scheduled_date', 'bilinmiyor')
                        print(f"📅 Günlük limit doldu - sonraki güne aktarıldı: {job_id}")
                        print(f"   Yeni tarih: {new_date}")
                    else:
                        print(f"⏸️  Günlük limit doldu, atlanıyor: {queue_id}")
                    continue
                
                self._process_item(item)
            except Exception as e:
                queue_id = item.get('queue_id', 'unknown') if isinstance(item, dict) else 'unknown'
                print(f"\n❌ Queue item işlenemedi ({queue_id}): {e}")
                traceback.print_exc()
    
    def _load_queues(self) -> dict:
        """Load queues configuration"""
        queues_file = self.data_dir / 'queues.json'
        if not queues_file.exists():
            return {'queues': []}
        try:
            with open(queues_file, 'r', encoding='utf-8') as f:
                return json.load(f)
        except Exception:
            return {'queues': []}
    
    def _get_queue_settings(self, queue_id: str, original_queue_id: str = None) -> dict:
        """Get settings for a specific queue by ID"""
        if not queue_id:
            return {}
        
        # Use provided original_queue_id if available, otherwise try to extract it
        if not original_queue_id:
            original_queue_id = queue_id
            if '_' in queue_id:
                original_queue_id = queue_id.rsplit('_', 1)[0]
        
        queues_data = self._load_queues()
        for queue in queues_data.get('queues', []):
            if queue.get('id') == original_queue_id:
                return queue
        return {}
    
    def _check_daily_limit(self, item: Dict, queues_data: dict) -> bool:
        """
        Check if daily upload limit has been reached for this queue
        
        Args:
            item: Queue item to check
            queues_data: Full queues configuration
            
        Returns:
            True if upload is allowed, False if daily limit reached
        """
        result = self._check_daily_limit_with_reschedule(item, queues_data)
        return result['allowed']
    
    def _check_daily_limit_with_reschedule(self, item: Dict, queues_data: dict) -> Dict:
        """
        Check if daily upload limit has been reached. If reached, reschedule to next day.
        
        Args:
            item: Queue item to check
            queues_data: Full queues configuration
            
        Returns:
            Dict with:
                - allowed: True if upload is allowed
                - rescheduled: True if item was rescheduled
                - new_scheduled_date: New scheduled date if rescheduled
        """
        result = {'allowed': True, 'rescheduled': False, 'new_scheduled_date': None}
        
        # Find the queue this item belongs to
        queue_id_prefix = item.get('queue_id', '').split('_')[0]  # Extract queue ID
        queue = None
        
        for q in queues_data.get('queues', []):
            if queue_id_prefix in q.get('id', ''):
                queue = q
                break
        
        if not queue:
            # Queue not found, allow upload
            return result
        
        # Get daily limit from platform_settings only (global schedule no longer has this)
        platform_settings = queue.get('platform_settings', {})
        daily_limit = 0
        
        # Get the platform for this item
        item_platform = item.get('platform', 'youtube')
        
        # Check if this platform has a daily limit set
        if item_platform in platform_settings:
            settings = platform_settings[item_platform]
            if settings.get('enabled'):
                platform_limit = settings.get('dailyLimit')
                if platform_limit:
                    try:
                        daily_limit = int(platform_limit)
                    except (ValueError, TypeError):
                        daily_limit = 0
        
        # If no limit set (0 or None), allow upload
        if not daily_limit or daily_limit <= 0:
            return result
        
        # Count uploads today for this queue
        today_uploads = self._count_today_uploads(queue.get('id'))
        
        if today_uploads >= daily_limit:
            result['allowed'] = False
            
            # Reschedule to next day
            rescheduled = self._reschedule_to_next_day(item, queue, queues_data)
            if rescheduled:
                result['rescheduled'] = True
                result['new_scheduled_date'] = rescheduled
            
            return result
        
        return result
    
    def _reschedule_to_next_day(self, item: Dict, queue: Dict, queues_data: dict) -> Optional[str]:
        """
        Reschedule an item to the next available day
        
        Args:
            item: Queue item to reschedule
            queue: Queue configuration
            queues_data: Full queues configuration
            
        Returns:
            New scheduled date string if successful, None otherwise
        """
        try:
            schedule = queue.get('schedule', {})
            platform_settings = queue.get('platform_settings', {})
            timezone_str = schedule.get('timezone', 'Europe/Istanbul')
            
            # Get the start time from schedule or platform settings
            start_time = schedule.get('start_time', '09:00')
            
            # Check platform settings for start time
            for platform, settings in platform_settings.items():
                if settings.get('enabled') and settings.get('startTime'):
                    start_time = settings.get('startTime')
                    break
            
            # Parse start time
            try:
                hour, minute = map(int, start_time.split(':'))
            except Exception as e:
                print(f"[WARN] Invalid start time format '{start_time}': {e}, using default 09:00")
                hour, minute = 9, 0
            
            # Calculate next day
            now = datetime.now(timezone.utc)
            tomorrow = now + timedelta(days=1)
            
            # Create new scheduled time for tomorrow at start_time
            new_scheduled = tomorrow.replace(hour=hour, minute=minute, second=0, microsecond=0)
            new_scheduled_str = new_scheduled.isoformat()
            
            # Update queues.json video entry with scheduled_publish_date
            self._update_queue_video_scheduled_date(item.get('job_id'), queue.get('id'), new_scheduled_str, queues_data)
            
            return new_scheduled_str
            
        except Exception as e:
            print(f"⚠️  Yeniden zamanlama hatası: {e}")
            return None
    
    def _update_queue_video_scheduled_date(self, job_id: str, queue_id: str, scheduled_date: str, queues_data: dict):
        """
        Update the scheduled_publish_date for a video in queues.json
        
        Args:
            job_id: Job ID of the video
            queue_id: Queue ID
            scheduled_date: New scheduled date
            queues_data: Full queues configuration (will be modified and saved)
        """
        try:
            for queue in queues_data.get('queues', []):
                if queue.get('id') == queue_id:
                    for video in queue.get('videos', []):
                        if video.get('job_id') == job_id:
                            video['scheduled_publish_date'] = scheduled_date
                            video['reschedule_reason'] = 'daily_limit_reached'
                            break
                    break
            
            # Save updated queues.json
            queues_file = self.data_dir / 'queues.json'
            with open(queues_file, 'w', encoding='utf-8') as f:
                json.dump(queues_data, f, ensure_ascii=False, indent=2)
                
        except Exception as e:
            print(f"⚠️  queues.json güncelleme hatası: {e}")
    
    def _count_today_uploads(self, queue_id: str) -> int:
        """
        Count how many videos were uploaded today from this queue
        
        Args:
            queue_id: Queue ID to check
            
        Returns:
            Number of uploads today
        """
        try:
            queues_file = self.data_dir / 'queues.json'
            if not queues_file.exists():
                return 0
            
            with open(queues_file, 'r', encoding='utf-8') as f:
                queues_data = json.load(f)
            
            # Get today's date in UTC
            today = datetime.now(timezone.utc).date()
            
            count = 0
            for queue in queues_data.get('queues', []):
                if queue.get('id') != queue_id:
                    continue
                
                for video in queue.get('videos', []):
                    if video.get('status') == 'completed':
                        # Check platform_status for today's publishes
                        platform_status = video.get('platform_status', {})
                        for platform, status_info in platform_status.items():
                            if isinstance(status_info, dict):
                                status_value = status_info.get('status')
                                published_at = status_info.get('published_at', '')
                            else:
                                status_value = status_info
                                published_at = ''

                            if str(status_value).lower() in ('published', 'success'):
                                if published_at:
                                    try:
                                        published_date = datetime.fromisoformat(published_at.replace('Z', '+00:00')).date()
                                        if published_date == today:
                                            count += 1
                                            break  # Count each video once
                                    except Exception:
                                        pass
                                else:
                                    # Legacy string statuses may not include published_at.
                                    # Count conservatively so daily-limit checks don't crash.
                                    count += 1
                                    break
            
            return count
            
        except Exception as e:
            print(f"⚠️  Upload sayımı hatası: {e}")
            return 0
    
    def _get_script_text(self, job_id: str) -> str:
        """output/job_id/script.json dosyasindan seslendirme metnini birlestirerek dondurur.
        Metadata motorunun gercek icerige gore optimizasyon yapabilmesi icin kullanilir."""
        script_file = self.base_dir / 'output' / job_id / 'script.json'
        if not script_file.exists():
            return ''
        try:
            with open(script_file, 'r', encoding='utf-8') as f:
                script = json.load(f)
            parts = []
            if script.get('hook'):
                parts.append(script['hook'])
            for scene in script.get('scenes', []):
                if scene.get('text'):
                    parts.append(scene['text'])
            if script.get('outro'):
                parts.append(script['outro'])
            return ' '.join(parts)
        except Exception:
            return ''

    def _process_item(self, item: Dict):
        """Process single queue item across all pending platforms"""
        queue_id = item.get('queue_id', 'unknown')
        job_id = item.get('job_id', '')
        video_path = item.get('video_path', '')
        platforms = item.get('platforms', [])
        platform_statuses = item.get('platform_status', {})

        if not isinstance(platforms, list):
            print(f"⚠️  Geçersiz platforms alanı ({queue_id}), atlanıyor")
            return
        if not isinstance(platform_statuses, dict):
            platform_statuses = {}
        
        print(f"\n{'='*60}")
        print(f"[PROCESS] Queue item: {queue_id}")
        print(f"🎬 Job ID: {job_id}")
        
        # Check video file exists
        if not os.path.exists(video_path):
            error = f"Video dosyası bulunamadı: {video_path}"
            print(f"❌ {error}")
            # Mark all platforms as failed
            for platform in platforms:
                self.queue_manager.mark_platform_failed(queue_id, platform, error, retry=False)
            return
        
        # Process each pending platform
        for platform in platforms:
            raw_platform_status = platform_statuses.get(platform, {})
            if isinstance(raw_platform_status, dict):
                platform_status_value = raw_platform_status.get('status', 'pending')
            else:
                platform_status_value = raw_platform_status or 'pending'

            if platform_status_value != 'pending':
                continue  # Skip non-pending platforms
            
            if platform not in self.uploaders:
                self.queue_manager.mark_platform_failed(
                    queue_id, platform, 
                    f"{platform} uploader mevcut değil",
                    retry=False
                )
                continue
            
            self._upload_to_platform(item, platform)
    
    def _upload_to_platform(self, item: Dict, platform: str):
        """Upload video to specific platform"""
        queue_id = item.get('queue_id', 'unknown')
        video_path = item.get('video_path', '')
        base_metadata = item.get('metadata', {})
        if not isinstance(base_metadata, dict):
            base_metadata = {}
        
        print(f"\n📱 [{platform.upper()}] Yükleniyor...")
        
        # Mark as processing
        self.queue_manager.mark_platform_processing(
            queue_id, 
            platform, 
            original_queue_id=item.get('original_queue_id'),
            job_id=item.get('job_id')
        )
        
        # Get platform-specific metadata
        platform_meta_map = item.get('platform_metadata', {})
        if not isinstance(platform_meta_map, dict):
            print(f"   [WARN] Geçersiz platform_metadata tipi ({type(platform_meta_map).__name__}), boş metadata kullanılacak")
            platform_meta_map = {}

        platform_meta = platform_meta_map.get(platform, {})
        if not isinstance(platform_meta, dict):
            platform_meta = {}

        # Upload oncesi platform-aware SEO metadata uret
        if not platform_meta:
            try:
                # Once social_queue'daki description'i dene (gercek script metni)
                script_text = base_metadata.get('description', '')
                # Yoksa script.json'dan yukle
                if not script_text:
                    script_text = self._get_script_text(item['job_id'])
                # Hala yoksa baslik ile devam et
                if not script_text:
                    script_text = base_metadata.get('title', '')

                original_title = base_metadata.get('title', '')
                base_tags = base_metadata.get('tags', [])
                use_ai = bool(getattr(self.metadata_optimizer, 'gemini_key', None))

                if platform == 'youtube':
                    # YouTube: MetadataOptimizer → title + description + tags
                    print(f"   [META] YouTube icin AI metadata uretiliyor... ({len(script_text)} kar)")
                    platform_meta = self.yt_metadata_optimizer.optimize_metadata(
                        original_title=original_title,
                        script_text=script_text,
                        tags=base_tags,
                        use_ai=use_ai
                    )
                else:
                    # TikTok / Instagram / Facebook: PlatformMetadataOptimizer → caption + hashtags
                    print(f"   [META] {platform.title()} icin AI metadata uretiliyor... ({len(script_text)} kar)")
                    platform_meta = self.metadata_optimizer.optimize_for_platform(
                        platform=platform,
                        original_title=original_title,
                        script_text=script_text,
                        base_tags=base_tags,
                        use_ai=use_ai
                    )
            except Exception as e:
                print(f"   [WARN] Metadata optimizasyonu basarisiz, fallback kullaniliyor: {e}")
                platform_meta = {
                    'title': base_metadata.get('title', 'Video'),
                    'description': base_metadata.get('description', ''),
                    'caption': base_metadata.get('description', base_metadata.get('title', '')),
                    'tags': base_metadata.get('tags', []),
                    'hashtags': base_metadata.get('tags', [])
                }
        
        try:
            uploader = self.uploaders[platform]
            queue_data = self._get_queue_settings(
                item.get('queue_id'),
                original_queue_id=item.get('original_queue_id')
            )
            if not isinstance(queue_data, dict):
                queue_data = {}
            queue_platform_settings = queue_data.get('platform_settings', {})
            if not isinstance(queue_platform_settings, dict):
                queue_platform_settings = {}
            
            if platform == 'youtube':
                # Multi-project mode: get best available project
                selected_project = None
                if uploader == 'multi_project' and self.youtube_project_manager:
                    # Check if queue has specific channel_id preference
                    channel_id = None
                    if queue_data:
                        platform_settings = queue_data.get('platform_settings', {}).get('youtube', {})
                        channel_id = platform_settings.get('channelId')
                    
                    # Use channel-specific project or fallback to best available
                    if channel_id:
                        print(f"   [YT] Kanal belirtildi: {channel_id}")
                        selected_project = self.youtube_project_manager.get_best_project_for_channel(channel_id)
                    else:
                        selected_project = self.youtube_project_manager.get_best_project()
                    
                    if selected_project:
                        project_id = selected_project['id']
                        remaining_quota = selected_project['daily_quota'] - selected_project.get('quota_used_today', 0)
                        print(f"   [YT] Proje: {selected_project['name']} (Kalan kota: {remaining_quota})")
                        uploader = YouTubeUploader(str(self.youtube_creds), project_id=project_id)
                    else:
                        print(f"   ⚠️  [YT] Tüm projelerin kotası dolmuş!")
                        self.queue_manager.mark_platform_failed(
                            queue_id, 
                            platform, 
                            "Tüm projelerin kotası dolmuş",
                            original_queue_id=item.get('original_queue_id'),
                            job_id=item.get('job_id')
                        )
                        return
                
                # Optimize edilmis title'i kullan (MetadataOptimizer'dan geliyor)
                yt_title = (
                    platform_meta.get('title')
                    or base_metadata.get('title', 'Video')
                )
                yt_description = (
                    platform_meta.get('description')
                    or platform_meta.get('caption', '')
                    or base_metadata.get('description', '')
                )
                yt_tags = (
                    platform_meta.get('tags')
                    or platform_meta.get('hashtags')
                    or base_metadata.get('tags', [])
                )
                
                # Get scheduled time from item and determine privacy settings
                scheduled_time = item.get('scheduled_time')
                privacy_status = base_metadata.get('privacy_status', 'public')
                publish_at = None
                
                # If scheduled_time is in the future, use YouTube's publishAt feature
                if scheduled_time:
                    from datetime import datetime, timezone
                    try:
                        scheduled_dt = datetime.fromisoformat(scheduled_time.replace('Z', '+00:00'))
                        now = datetime.now(timezone.utc)
                        
                        # If scheduled time is in the future (>5 min), use publishAt
                        if (scheduled_dt - now).total_seconds() > 300:
                            publish_at = scheduled_time
                            privacy_status = 'private'  # Required for publishAt
                            print(f"   [YT] Planlanmış yayın: {scheduled_time}")
                    except Exception as e:
                        print(f"   [WARN] scheduled_time parse hatası: {e}")
                
                # Get thumbnail path from job folder
                job_id = item.get('job_id', '')
                thumbnail_path = None
                if job_id:
                    for ext in ['jpg', 'png', 'jpeg']:
                        thumb_candidate = self.base_dir / 'output' / job_id / f'thumbnail.{ext}'
                        if thumb_candidate.exists():
                            thumbnail_path = str(thumb_candidate)
                            print(f"   [YT] Thumbnail bulundu: {thumb_candidate.name}")
                            break
                
                # Get playlist_id, category and privacy settings from queue settings
                playlist_id = None
                category_id = '28'  # Default: Science & Technology
                channel_id = None
                if queue_data:
                    platform_settings = queue_data.get('platform_settings', {}).get('youtube', {})
                    playlist_id = platform_settings.get('playlistId')
                    channel_id = platform_settings.get('channelId')
                    
                    # Try to get category from channel settings
                    if channel_id:
                        try:
                            channels_file = self.base_dir / 'data' / 'youtube_channels.json'
                            if channels_file.exists():
                                with open(channels_file, 'r', encoding='utf-8') as f:
                                    channels_data = json.load(f)
                                for ch in channels_data.get('channels', []):
                                    if ch.get('id') == channel_id:
                                        category_id = ch.get('default_category_id', '28')
                                        print(f"   [YT] Kanal kategorisi: {category_id}")
                                        break
                        except Exception as e:
                            print(f"   [WARN] Kanal kategorisi okunamadı: {e}")
                    
                    # Get privacy setting from queue platform settings
                    if 'privacy' in platform_settings:
                        privacy_status = platform_settings['privacy']
                        print(f"   [YT] Görünürlük (kuyruk ayarı): {privacy_status}")
                    if playlist_id:
                        print(f"   [YT] Playlist: {playlist_id}")
                
                print(f"   [YT] Baslik: {yt_title[:60]}")
                result = uploader.upload_video(
                    video_path=video_path,
                    title=yt_title,
                    description=yt_description,
                    tags=yt_tags,
                    category_id=category_id,
                    privacy_status=privacy_status,
                    publish_at=publish_at,
                    thumbnail_path=thumbnail_path,
                    playlist_id=playlist_id,
                    channel_id=channel_id  # Multi-channel support!
                )
                
                if result and result.get('status') == 'success':
                    video_url = result.get('video_url', '')
                    self.queue_manager.mark_platform_published(
                        queue_id, 
                        platform, 
                        video_url,
                        original_queue_id=item.get('original_queue_id'),
                        job_id=item.get('job_id')
                    )
                    print(f"   [OK] YouTube başarılı: {result['video_url']}")
                    
                    # Record quota usage
                    if selected_project and self.youtube_project_manager:
                        # Check if thumbnail was uploaded
                        with_thumbnail = thumbnail_path is not None and result.get('thumbnail_uploaded', False)
                        self.youtube_project_manager.record_upload(
                            project_id=selected_project['id'],
                            success=True,
                            with_thumbnail=with_thumbnail
                        )
                        print(f"   📊 Proje: {selected_project['name']}")
                    
                    # Resolve previous errors
                    self.error_logger.resolve_error(item.get('job_id', ''), 'youtube')
                        
                elif result and result.get('quota_exceeded'):
                    # Quota exceeded - try next project if available
                    error = result.get('error', 'Kota aşıldı')
                    print(f"   ⚠️  [YT] Kota aşıldı: {error}")
                    
                    # Record quota error for this project
                    if self.youtube_project_manager and selected_project:
                        self.youtube_project_manager.record_quota_error(selected_project['id'])
                    
                    # Prevent infinite recursion - check retry count
                    retry_count = item.get('_quota_retry_count', 0)
                    max_retries = 2  # Maximum number of project switches
                    
                    if retry_count >= max_retries:
                        print(f"   ❌ Tüm projeler denendi, başarısız")
                        self.queue_manager.mark_platform_failed(
                            queue_id, 
                            platform, 
                            f"Quota exceeded on all projects: {error}",
                            original_queue_id=item.get('original_queue_id'),
                            job_id=item.get('job_id')
                        )
                    elif self.youtube_project_manager:
                        # Try another project
                        next_project = self.youtube_project_manager.get_best_project()
                        if next_project and next_project['id'] != (selected_project['id'] if selected_project else None):
                            print(f"   🔄 Başka proje deneniyor: {next_project['name']} (retry {retry_count + 1}/{max_retries})")
                            # Mark retry count to prevent infinite loop
                            item['_quota_retry_count'] = retry_count + 1
                            # Reset uploader for new project
                            self.uploaders['youtube'] = 'multi_project'
                            return self._upload_to_platform(item, platform)
                        else:
                            print(f"   ❌ Başka kullanılabilir proje yok")
                            self.queue_manager.mark_platform_failed(
                                queue_id, 
                                platform, 
                                f"All projects quota exceeded: {error}",
                                original_queue_id=item.get('original_queue_id'),
                                job_id=item.get('job_id')
                            )
                            # Log quota error
                            self.error_logger.log_error(
                                job_id=item.get('job_id', 'unknown'),
                                platform='youtube',
                                error_type='quota_exceeded',
                                error_message=f"All projects quota exceeded: {error}",
                                queue_id=item.get('original_queue_id'),
                                retry_count=item.get('retry_count', 0)
                            )
                    else:
                        self.queue_manager.mark_platform_failed(
                            queue_id, 
                            platform, 
                            error,
                            original_queue_id=item.get('original_queue_id'),
                            job_id=item.get('job_id')
                        )
                        # Log quota error
                        self.error_logger.log_error(
                            job_id=item.get('job_id', 'unknown'),
                            platform='youtube',
                            error_type='quota_exceeded',
                            error_message=error,
                            queue_id=item.get('original_queue_id'),
                            retry_count=item.get('retry_count', 0)
                        )
                    
                else:
                    error = result.get('error', 'Unknown error') if result else 'Upload failed'
                    self.queue_manager.mark_platform_failed(queue_id, platform, error)
                    print(f"   ❌ YouTube başarısız: {error}")
                    
                    # Log error
                    self.error_logger.log_error(
                        job_id=item.get('job_id', 'unknown'),
                        platform='youtube',
                        error_type='upload_failed',
                        error_message=error,
                        queue_id=item.get('original_queue_id'),
                        retry_count=item.get('retry_count', 0)
                    )
            
            else:
                # TikTok, Instagram, Facebook
                platform_settings = queue_platform_settings.get(platform, {})
                if not isinstance(platform_settings, dict):
                    platform_settings = {}

                upload_kwargs = {}
                if platform == 'instagram':
                    account_id = platform_settings.get('accountId') or platform_settings.get('account_id')
                    if account_id:
                        upload_kwargs['account_id'] = account_id
                        print(f"   [IG] Hesap: {account_id}")
                    share_to_feed_raw = platform_settings.get('shareToFeed')
                    if share_to_feed_raw is None:
                        share_to_feed_raw = platform_settings.get('share_to_feed')
                    if share_to_feed_raw is None:
                        share_to_feed = platform_settings.get('type', 'reel') != 'story'
                    elif isinstance(share_to_feed_raw, str):
                        share_to_feed = share_to_feed_raw.strip().lower() in ('1', 'true', 'yes', 'on', 'enabled')
                    else:
                        share_to_feed = bool(share_to_feed_raw)
                    upload_kwargs['share_to_feed'] = share_to_feed
                elif platform == 'facebook':
                    page_id = platform_settings.get('pageId') or platform_settings.get('page_id')
                    if page_id:
                        upload_kwargs['account_id'] = page_id
                        print(f"   [FB] Sayfa: {page_id}")
                    fb_type = str(platform_settings.get('type', 'reel')).lower()
                    publish_as_status_raw = platform_settings.get('publishAsStatus')
                    if publish_as_status_raw is None:
                        publish_as_status_raw = platform_settings.get('publish_as_status')
                    if isinstance(publish_as_status_raw, str):
                        publish_as_status = publish_as_status_raw.strip().lower() in ('1', 'true', 'yes', 'on', 'enabled')
                    else:
                        publish_as_status = bool(publish_as_status_raw)

                    upload_kwargs['as_reels'] = (fb_type != 'video') and (not publish_as_status)
                    if publish_as_status and fb_type != 'video':
                        print("   [FB] Durum yayını aktif: Reels yerine Video modu kullanılacak")
                elif platform == 'tiktok':
                    privacy_map = {
                        'public': 'PUBLIC_TO_EVERYONE',
                        'public_to_everyone': 'PUBLIC_TO_EVERYONE',
                        'friends': 'MUTUAL_FOLLOW_FRIENDS',
                        'mutual_follow_friends': 'MUTUAL_FOLLOW_FRIENDS',
                        'private': 'SELF_ONLY',
                        'self_only': 'SELF_ONLY'
                    }
                    privacy_raw = str(platform_settings.get('privacy', 'public_to_everyone')).lower()
                    upload_kwargs['privacy_level'] = privacy_map.get(privacy_raw, 'PUBLIC_TO_EVERYONE')

                result = uploader.upload_video(
                    video_path=video_path,
                    caption=platform_meta.get('caption', base_metadata.get('title', '')),
                    hashtags=platform_meta.get('hashtags', []),
                    **upload_kwargs
                )
                
                if result.success:
                    video_url = result.post_url if hasattr(result, 'post_url') else ''
                    self.queue_manager.mark_platform_published(
                        queue_id, 
                        platform, 
                        video_url,
                        original_queue_id=item.get('original_queue_id'),
                        job_id=item.get('job_id')
                    )
                    print(f"   [OK] {platform.title()} başarılı: {result.post_url}")
                    
                    # Resolve previous errors
                    self.error_logger.resolve_error(item.get('job_id', ''), platform)
                else:
                    self.queue_manager.mark_platform_failed(
                        queue_id, 
                        platform, 
                        result.error,
                        retry='quota' not in str(result.error).lower(),
                        original_queue_id=item.get('original_queue_id'),
                        job_id=item.get('job_id')
                    )
                    print(f"   ❌ {platform.title()} başarısız: {result.error}")
                    
                    # Log error
                    self.error_logger.log_error(
                        job_id=item.get('job_id', 'unknown'),
                        platform=platform,
                        error_type='upload_failed',
                        error_message=result.error,
                        queue_id=item.get('original_queue_id'),
                        retry_count=item.get('retry_count', 0)
                    )
        
        except Exception as e:
            error = str(e)
            self.queue_manager.mark_platform_failed(
                queue_id, 
                platform, 
                error, 
                retry=True,
                original_queue_id=item.get('original_queue_id'),
                job_id=item.get('job_id')
            )
            print(f"   ❌ {platform.title()} exception: {error}")
            
            # Log error
            self.error_logger.log_error(
                job_id=item.get('job_id', 'unknown'),
                platform=platform,
                error_type='exception',
                error_message=error,
                queue_id=item.get('original_queue_id'),
                retry_count=item.get('retry_count', 0)
            )
        
        # Update job file
        self._update_job(item.get('job_id', ''), queue_id)
    
    def _update_job(self, job_id: str, queue_id: str):
        """Update job JSON file with social upload status"""
        job_file = self.jobs_dir / f'{job_id}.json'
        
        if not job_file.exists():
            return
        
        try:
            with open(job_file, 'r', encoding='utf-8') as f:
                job = json.load(f)
            
            # Get current status from queue
            status = self.queue_manager.get_job_status(job_id)
            
            if status:
                job['social_upload'] = {
                    'queue_id': queue_id,
                    'status': status['status'],
                    'platforms': status['platforms'],
                    'updated_at': datetime.now(timezone.utc).isoformat()
                }
                
                with open(job_file, 'w', encoding='utf-8') as f:
                    json.dump(job, f, ensure_ascii=False, indent=2)
        
        except Exception as e:
            print(f"   [WARN] Job güncelleme hatası: {e}")


def main():
    """CLI entry point"""
    import argparse
    
    parser = argparse.ArgumentParser(description='Social Media Upload Scheduler')
    parser.add_argument(
        '--base-dir',
        default=str(Path(__file__).parent.parent.parent),
        help='Base directory of the application'
    )
    parser.add_argument(
        '--interval',
        type=int,
        default=60,
        help='Check interval in seconds (default: 60)'
    )
    
    args = parser.parse_args()
    
    scheduler = SocialMediaScheduler(args.base_dir, args.interval)
    scheduler.run()


if __name__ == '__main__':
    main()
