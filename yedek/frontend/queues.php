<?php $page_title = 'Kuyruklar - Video Otomasyon'; $active_page = 'queues'; ?>
<!DOCTYPE html>
<html lang="tr" x-data="{ darkMode: localStorage.getItem('darkMode') === '1' }" :class="{ 'dark': darkMode }">
<head>
  <?php include __DIR__ . '/components/_head.php'; ?>
  <style>
    @keyframes fade-in-up { from { opacity: 0; transform: translateY(12px); } to { opacity: 1; transform: translateY(0); } }
    .anim-fade-in { animation: fade-in-up .4s ease-out both; }
    .sortable-ghost { opacity: 0.4; background: #dbeafe !important; }
    .sortable-drag { box-shadow: 0 10px 25px rgba(0,0,0,0.15); }
    .phone-frame { background: linear-gradient(145deg, #1a1a1a, #0a0a0a); border-radius: 24px; padding: 4px; }
    .phone-screen { background: #000; border-radius: 20px; overflow: hidden; position: relative; }
    .phone-notch { position: absolute; top: 6px; left: 50%; transform: translateX(-50%); width: 60px; height: 18px; background: #000; border-radius: 10px; z-index: 10; }
    /* Platform durum badge animasyonları */
    @keyframes upload-pulse { 0%,100% { opacity:1; transform:scale(1); } 50% { opacity:.6; transform:scale(1.15); } }
    @keyframes spin-slow { from { transform:rotate(0deg); } to { transform:rotate(360deg); } }
    @keyframes live-blink { 0%,100% { opacity:1; } 50% { opacity:0.3; } }
    .badge-uploading { animation: upload-pulse 1.2s ease-in-out infinite; }
    .badge-spin { animation: spin-slow 1.5s linear infinite; display:inline-block; }
    .badge-live { animation: live-blink 1s ease-in-out infinite; }
    .platform-badge { display:inline-flex; align-items:center; gap:2px; border-radius:6px; padding:2px 5px; font-size:10px; font-weight:600; white-space:nowrap; }
    .badge-pending   { background:rgba(234,179,8,0.15);  color:#92400e; border:1px solid rgba(234,179,8,0.3); }
    .badge-queued    { background:rgba(99,102,241,0.12); color:#4338ca; border:1px solid rgba(99,102,241,0.25); }
    .badge-producing { background:rgba(59,130,246,0.15); color:#1d4ed8; border:1px solid rgba(59,130,246,0.3); }
    .badge-uploading { background:rgba(245,158,11,0.15); color:#92400e; border:1px solid rgba(245,158,11,0.3); }
    .badge-published { background:rgba(34,197,94,0.15);  color:#166534; border:1px solid rgba(34,197,94,0.3); }
    .badge-failed    { background:rgba(239,68,68,0.15);  color:#991b1b; border:1px solid rgba(239,68,68,0.3); }
    .dark .badge-pending   { background:rgba(234,179,8,0.2);  color:#fcd34d; border-color:rgba(234,179,8,0.4); }
    .dark .badge-queued    { background:rgba(99,102,241,0.2); color:#a5b4fc; border-color:rgba(99,102,241,0.35); }
    .dark .badge-producing { background:rgba(59,130,246,0.2); color:#93c5fd; border-color:rgba(59,130,246,0.35); }
    .dark .badge-uploading { background:rgba(245,158,11,0.2); color:#fcd34d; border-color:rgba(245,158,11,0.4); }
    .dark .badge-published { background:rgba(34,197,94,0.2);  color:#86efac; border-color:rgba(34,197,94,0.35); }
    .dark .badge-failed    { background:rgba(239,68,68,0.2);  color:#fca5a5; border-color:rgba(239,68,68,0.35); }
    /* job_status satır sol border rengi */
    .row-pending   { border-left: 3px solid rgba(234,179,8,0.5); }
    .row-producing { border-left: 3px solid rgba(59,130,246,0.7); }
    .row-done      { border-left: 3px solid rgba(34,197,94,0.5); }
    .row-failed    { border-left: 3px solid rgba(239,68,68,0.5); }
    /* Küçük dönen ikon */
    .icon-spin { display:inline-block; animation:spin-slow 1.2s linear infinite; }
  </style>
  <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
  <script>
  function queuesApp() {
    return {
      sidebarOpen: false,
      sidebarCollapsed: localStorage.getItem('sidebarCollapsed') === '1',
      darkMode: false,
      loading: true,
      queues: [],
      activeTab: null,
      selectedQueue: null,
      selectedVideo: null,
      filteredVideos: [],
      filterStatus: 'pending',
      previewPlatform: 'youtube',
      editingQueue: false,
      editingMetadata: false,
      
      // YouTube Channels for dropdown
      youtubeChannels: [],
      
      // Queue Stats
      queueStats: null,
      loadingStats: false,
      statsInterval: null,
      
      // Metadata form
      metadata: {
        title: '',
        description: '',
        tags: ''
      },
      
      // Modal states
      createModal: false,
      editModal: false,
      detailModal: false,
      moveModal: false,
      queueSettingsModal: false,
      activePlatformTab: 'youtube', // Active platform tab in settings modal
      
      // Form data
      form: {
        name: '',
        platforms: [],
        scheduleType: 'interval',
        intervalHours: 2,
        intervalMinutes: 120,
        startTime: '',
        dailyLimit: 0,
        specificTimes: ['09:00', '15:00', '21:00'],
        // Video ayarları
        dimensionPreset: 'vertical',
        customWidth: 1080,
        customHeight: 1920,
        subtitleMode: 'config',
        subtitlePreset: 'classic',
        customSubtitle: { FontName: 'Arial', FontSize: 24, PrimaryColour: '#FFFFFF', OutlineColour: '#000000', Outline: 2, MarginV: 60, MarginL: 40, MarginR: 40, Bold: 1 },
        // Platform-specific settings
        platformSettings: {
          youtube: {
            enabled: true,
            channelId: '',  // Hedef kanal
            scheduleType: 'interval',
            intervalHours: 2,
            intervalMinutes: 120,
            startTime: '',
            dailyLimit: 0,
            specificTimes: ['09:00', '15:00', '21:00'],
            playlistId: '',
            categoryId: '22',
            privacy: 'public',
            titleTemplate: '{title}',
            descriptionTemplate: '{description}\n\n#shorts',
            tagsTemplate: 'shorts,haber'
          },
          instagram: {
            enabled: false,
            scheduleType: 'specific',
            intervalHours: 3,
            specificTimes: ['10:00', '16:00', '20:00'],
            hashtagStrategy: 'auto',
            hashtags: '#reels,#haber',
            captionTemplate: '{title}\n\n{description}',
            allowComments: true
          },
          tiktok: {
            enabled: false,
            scheduleType: 'interval',
            intervalHours: 4,
            specificTimes: ['12:00', '18:00'],
            privacy: 'public_to_everyone',
            allowDuet: true,
            allowStitch: true,
            captionTemplate: '{title} #fyp'
          },
          facebook: {
            enabled: false,
            scheduleType: 'now',
            intervalHours: 6,
            specificTimes: ['11:00', '17:00'],
            pageId: '',
            privacy: 'EVERYONE'
          }
        }
      },
      
      // Config'den yüklenen varsayılan altyazı
      configSubtitle: { FontName: 'Arial', FontSize: 24, PrimaryColour: '#FFFFFF', OutlineColour: '#000000', Outline: 2, MarginV: 60, MarginL: 40, MarginR: 40, Bold: 1 },
      
      submitting: false,
      
      // Video ebat presetleri
      dimensionPresets: {
        vertical: { label: '📱 Dikey (9:16)', width: 1080, height: 1920, desc: 'Shorts/Reels/TikTok' },
        square: { label: '⬛ Kare (1:1)', width: 1080, height: 1080, desc: 'Instagram Post' },
        horizontal: { label: '🖥️ Yatay (16:9)', width: 1920, height: 1080, desc: 'YouTube/TV' },
        custom: { label: '✏️ Özel', width: null, height: null, desc: 'Manuel giriş' }
      },
      
      // Altyazı presetleri
      subtitlePresets: {
        classic: { label: 'Klasik', FontSize: 24, PrimaryColour: '#FFFFFF', OutlineColour: '#000000', Outline: 2, MarginV: 60, MarginL: 40, MarginR: 40, Bold: 1 },
        neon: { label: 'Neon', FontSize: 26, PrimaryColour: '#00FF00', OutlineColour: '#000000', Outline: 2, MarginV: 60, MarginL: 40, MarginR: 40, Bold: 1 },
        cinematic: { label: 'Sinematik', FontSize: 22, PrimaryColour: '#F5F5DC', OutlineColour: '#2C2C2C', Outline: 1, MarginV: 80, MarginL: 40, MarginR: 40, Bold: 0 },
        bold: { label: 'Kalın', FontSize: 28, PrimaryColour: '#FFD700', OutlineColour: '#000000', Outline: 3, MarginV: 50, MarginL: 40, MarginR: 40, Bold: 1 },
        minimal: { label: 'Minimal', FontSize: 20, PrimaryColour: '#FFFFFF', OutlineColour: '#333333', Outline: 1, MarginV: 70, MarginL: 40, MarginR: 40, Bold: 0 },
        news: { label: 'Haber', FontSize: 24, PrimaryColour: '#FFFFFF', OutlineColour: '#CC0000', Outline: 2, MarginV: 55, MarginL: 40, MarginR: 40, Bold: 1 }
      },
      
      // Computed video dimensions
      get formVideoWidth() { 
        return this.form.dimensionPreset === 'custom' ? this.form.customWidth : this.dimensionPresets[this.form.dimensionPreset]?.width || 1080; 
      },
      get formVideoHeight() { 
        return this.form.dimensionPreset === 'custom' ? this.form.customHeight : this.dimensionPresets[this.form.dimensionPreset]?.height || 1920; 
      },
      
      platformOptions: [
        { id: 'youtube', name: 'YouTube', icon: '📺', color: 'red' },
        { id: 'instagram', name: 'Instagram', icon: '📸', color: 'pink' }
        // TikTok ve Facebook şimdilik devre dışı
      ],
      
      scheduleOptions: [
        { id: 'now', name: 'Hemen Paylaş', desc: 'Video eklenince hemen paylaşılır' },
        { id: 'interval', name: 'Aralıklı', desc: 'Belirli saat aralıklarında paylaşılır' },
        { id: 'specific', name: 'Belirli Saatler', desc: 'Günün belirli saatlerinde paylaşılır' }
      ],
      
      togglePlatform(platformId) {
        const idx = this.form.platforms.indexOf(platformId);
        if (idx > -1) {
          this.form.platforms.splice(idx, 1);
        } else {
          this.form.platforms.push(platformId);
        }
      },
      
      openCreateModal() {
        // Basit kuyruk oluşturma - sadece isim ve platformlar
        this.form = {
          name: '',
          platforms: [],
          timezone: 'Europe/Istanbul'
        };
        this.createModal = true;
      },
      
      openEditModal(queue) {
        this.selectedQueue = queue;
        const vs = queue.video_settings || {};
        const schedule = queue.schedule || {};
        const ps = queue.platform_settings || {};
        
        this.form = {
          name: queue.name,
          platforms: [...queue.platforms],
          scheduleType: schedule.type || 'interval',
          intervalHours: schedule.interval_hours || 2,
          intervalMinutes: schedule.interval_minutes || (schedule.interval_hours * 60) || 120,
          startTime: schedule.start_time || '',
          dailyLimit: schedule.daily_limit || 0,
          specificTimes: schedule.specific_times || ['09:00', '15:00', '21:00'],
          dimensionPreset: vs.dimensionPreset || 'vertical',
          customWidth: vs.videoWidth || 1080,
          customHeight: vs.videoHeight || 1920,
          subtitleMode: vs.subtitleMode || 'config',
          subtitlePreset: vs.subtitlePreset || 'classic',
          customSubtitle: vs.customSubtitle || { ...this.configSubtitle },
          platformSettings: {
            youtube: {
              enabled: queue.platforms.includes('youtube'),
              channelId: ps.youtube?.channelId || '',
              categoryId: ps.youtube?.categoryId || '28',
              scheduleType: ps.youtube?.scheduleType || 'interval',
              intervalHours: ps.youtube?.intervalHours || 2,
              intervalMinutes: ps.youtube?.intervalMinutes || 120,
              startTime: ps.youtube?.startTime || '',
              specificTimes: ps.youtube?.specificTimes || ['09:00', '15:00', '21:00'],
              privacy: ps.youtube?.privacy || 'public',
              playlistId: ps.youtube?.playlistId || '',
              dailyLimit: ps.youtube?.dailyLimit || 0
            },
            tiktok: {
              enabled: queue.platforms.includes('tiktok'),
              privacy: ps.tiktok?.privacy || 'public',
              scheduleType: ps.tiktok?.scheduleType || 'interval',
              intervalHours: ps.tiktok?.intervalHours || 2,
              intervalMinutes: ps.tiktok?.intervalMinutes || 120,
              startTime: ps.tiktok?.startTime || '',
              specificTimes: ps.tiktok?.specificTimes || ['10:00', '16:00', '22:00']
            },
            instagram: {
              enabled: queue.platforms.includes('instagram'),
              type: ps.instagram?.type || 'reel',
              scheduleType: ps.instagram?.scheduleType || 'interval',
              intervalHours: ps.instagram?.intervalHours || 2,
              intervalMinutes: ps.instagram?.intervalMinutes || 120,
              startTime: ps.instagram?.startTime || '',
              specificTimes: ps.instagram?.specificTimes || ['11:00', '17:00', '23:00']
            },
            facebook: {
              enabled: queue.platforms.includes('facebook'),
              privacy: ps.facebook?.privacy || 'public',
              scheduleType: ps.facebook?.scheduleType || 'interval',
              intervalHours: ps.facebook?.intervalHours || 2,
              intervalMinutes: ps.facebook?.intervalMinutes || 120,
              startTime: ps.facebook?.startTime || '',
              specificTimes: ps.facebook?.specificTimes || ['08:00', '14:00', '20:00']
            }
          }
        };
        this.editModal = true;
      },
      
      async openDetailModal(queue) {
        this.selectedQueue = null;
        this.detailModal = true;
        
        try {
          const r = await fetch('/api/queues.php?action=get&id=' + queue.id);
          const d = await r.json();
          if (d.success) {
            this.selectedQueue = d.queue;
          }
        } catch(e) {
          console.error('Kuyruk detayı yüklenemedi:', e);
        }
      },
      
      closeModals() {
        this.createModal = false;
        this.editModal = false;
        this.detailModal = false;
        this.moveModal = false;
        this.queueSettingsModal = false;
      },
      
      // Select queue tab
      async selectQueueTab(queue) {
        this.activeTab = queue.id;
        this.editingQueue = false;
        try {
          const r = await fetch('/api/queues.php?action=get&id=' + queue.id);
          const d = await r.json();
          if (d.success) {
            this.selectedQueue = d.queue;
            this.filterVideos();
            this.selectedVideo = null;
            this.editingMetadata = false;
            this.$nextTick(() => this.initSortable());
            // Load queue stats with auto-refresh
            this.loadQueueStats();
            // Clear old interval and set new 15-second refresh
            if (this.statsInterval) clearInterval(this.statsInterval);
            this.statsInterval = setInterval(() => this.loadQueueStats(), 15000);
          }
        } catch(e) {
          console.error('Kuyruk detayı yüklenemedi:', e);
        }
      },
      
      // Load queue statistics
      async loadQueueStats() {
        if (!this.selectedQueue) return;
        this.loadingStats = true;
        try {
          const r = await fetch('/api/queues.php?action=get_queue_stats&id=' + this.selectedQueue.id);
          const d = await r.json();
          if (d.success) {
            this.queueStats = d.stats;
          } else {
            this.queueStats = null;
          }
        } catch(e) {
          console.error('Stats yüklenemedi:', e);
          this.queueStats = null;
        }
        this.loadingStats = false;
      },

      isSocialSchedulerRunning() {
        return !!(this.queueStats?.scheduler_status?.social?.running);
      },

      isProductionSchedulerRunning() {
        return !!(this.queueStats?.scheduler_status?.production?.running);
      },
      
      // New: Select queue for two-column view
      async selectQueueForDetail(queue) {
        await this.selectQueueTab(queue);
      },
      
      // Toggle queue settings edit mode
      toggleQueueEdit() {
        if (!this.editingQueue) {
          const vs = this.selectedQueue.video_settings || {};
          this.form = {
            name: this.selectedQueue.name,
            platforms: [...this.selectedQueue.platforms],
            scheduleType: this.selectedQueue.schedule?.type || 'interval',
            intervalHours: this.selectedQueue.schedule?.interval_hours || 2,
            specificTimes: this.selectedQueue.schedule?.specific_times || ['09:00', '15:00', '21:00'],
            dimensionPreset: vs.dimensionPreset || 'vertical',
            customWidth: vs.videoWidth || 1080,
            customHeight: vs.videoHeight || 1920,
            subtitleMode: vs.subtitleMode || 'config',
            subtitlePreset: vs.subtitlePreset || 'classic',
            customSubtitle: vs.customSubtitle || { ...this.configSubtitle }
          };
        }
        this.editingQueue = !this.editingQueue;
      },
      
      // Open queue settings modal
      openQueueSettingsModal() {
        if (!this.selectedQueue) return;
        const vs = this.selectedQueue.video_settings || {};
        const ps = this.selectedQueue.platform_settings || {};
        const schedule = this.selectedQueue.schedule || {};
        
        // Set active tab to first enabled platform
        this.activePlatformTab = this.selectedQueue.platforms?.[0] || 'youtube';
        
        this.form = {
          name: this.selectedQueue.name,
          platforms: [...(this.selectedQueue.platforms || [])],
          scheduleType: schedule.type || 'interval',
          intervalHours: schedule.interval_hours || 2,
          intervalMinutes: schedule.interval_minutes || (schedule.interval_hours * 60) || 120,
          startTime: schedule.start_time || '',
          dailyLimit: schedule.daily_limit || 0,
          specificTimes: schedule.specific_times || ['09:00', '15:00', '21:00'],
          dimensionPreset: vs.dimensionPreset || 'vertical',
          customWidth: vs.videoWidth || 1080,
          customHeight: vs.videoHeight || 1920,
          subtitleMode: vs.subtitleMode || 'config',
          subtitlePreset: vs.subtitlePreset || 'classic',
          customSubtitle: vs.customSubtitle || { ...this.configSubtitle },
          // Load platform settings or use defaults
          platformSettings: {
            youtube: ps.youtube || {
              enabled: this.selectedQueue.platforms?.includes('youtube') || false,
              channelId: '',
              scheduleType: schedule.type || 'interval',
              intervalHours: schedule.interval_hours || 2,
              intervalMinutes: schedule.interval_minutes || (schedule.interval_hours * 60) || 120,
              startTime: schedule.start_time || '',
              dailyLimit: schedule.daily_limit || 0,
              specificTimes: [...(schedule.specific_times || ['09:00', '15:00', '21:00'])],
              playlistId: '',
              categoryId: '22',
              privacy: 'public',
              titleTemplate: '{title}',
              descriptionTemplate: '{description}\n\n#shorts',
              tagsTemplate: 'shorts,haber'
            },
            instagram: ps.instagram || {
              enabled: this.selectedQueue.platforms?.includes('instagram') || false,
              scheduleType: 'specific',
              intervalHours: 3,
              specificTimes: ['10:00', '16:00', '20:00'],
              hashtagStrategy: 'auto',
              hashtags: '#reels,#haber',
              captionTemplate: '{title}\n\n{description}',
              allowComments: true
            },
            tiktok: ps.tiktok || {
              enabled: this.selectedQueue.platforms?.includes('tiktok') || false,
              scheduleType: 'interval',
              intervalHours: 4,
              specificTimes: ['12:00', '18:00'],
              privacy: 'public_to_everyone',
              allowDuet: true,
              allowStitch: true,
              captionTemplate: '{title} #fyp'
            },
            facebook: ps.facebook || {
              enabled: this.selectedQueue.platforms?.includes('facebook') || false,
              scheduleType: 'now',
              intervalHours: 6,
              specificTimes: ['11:00', '17:00'],
              pageId: '',
              privacy: 'EVERYONE'
            }
          }
        };
        this.queueSettingsModal = true;
      },
      
      // Build video_settings object from form
      buildVideoSettings() {
        return {
          dimensionPreset: this.form.dimensionPreset,
          videoWidth: this.formVideoWidth,
          videoHeight: this.formVideoHeight,
          subtitleMode: this.form.subtitleMode,
          subtitlePreset: this.form.subtitlePreset,
          customSubtitle: this.form.subtitleMode === 'custom' ? this.form.customSubtitle : null
        };
      },
      
      // Save queue settings from modal
      async saveQueueSettings() {
        // YouTube platformu aktifse kanal zorunlu
        if (this.form.platformSettings.youtube.enabled) {
          if (!this.form.platformSettings.youtube.channelId) {
            alert('YouTube için kanal seçimi zorunludur!');
            return;
          }
        }
        
        this.submitting = true;
        
        // Build platforms array from enabled platform settings
        const enabledPlatforms = [];
        if (this.form.platformSettings.youtube.enabled) enabledPlatforms.push('youtube');
        if (this.form.platformSettings.instagram.enabled) enabledPlatforms.push('instagram');
        if (this.form.platformSettings.tiktok.enabled) enabledPlatforms.push('tiktok');
        if (this.form.platformSettings.facebook.enabled) enabledPlatforms.push('facebook');
        
        try {
          const response = await fetch('/api/queues.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'update',
              queue_id: this.selectedQueue.id,
              updates: {
                name: this.form.name.trim(),
                platforms: enabledPlatforms,
                schedule: {
                  type: this.form.scheduleType,
                  interval_hours: this.form.intervalHours,
                  interval_minutes: this.form.intervalMinutes,
                  start_time: this.form.startTime,
                  daily_limit: parseInt(this.form.dailyLimit) || 0,
                  specific_times: this.form.specificTimes,
                  timezone: 'Europe/Istanbul'
                },
                video_settings: this.buildVideoSettings(),
                platform_settings: this.form.platformSettings
              }
            })
          });
          const result = await response.json();
          if (result.success) {
            this.queueSettingsModal = false;
            await this.loadQueues();
            await this.selectQueueTab({ id: this.selectedQueue.id });
          } else {
            alert('Hata: ' + (result.error || 'Bilinmeyen hata'));
          }
        } catch (error) {
          alert('Hata: ' + error.message);
        }
        this.submitting = false;
      },
      
      // Video metadata editing
      startEditMetadata() {
        this.metadata = {
          title: this.selectedVideo?.title || '',
          description: this.selectedVideo?.description || '',
          tags: (this.selectedVideo?.tags || []).join(', ')
        };
        this.editingMetadata = true;
      },
      
      cancelEditMetadata() {
        this.editingMetadata = false;
      },
      
      async saveMetadata() {
        // For now just close - API extension needed for full implementation
        this.editingMetadata = false;
        alert('Metadata güncellendi!');
      },
      
      filterVideos() {
        if (!this.selectedQueue?.videos) {
          this.filteredVideos = [];
          return;
        }
        let videos = [...this.selectedQueue.videos];
        if (this.filterStatus === 'pending') {
          // Henüz tüm platformlarda yayınlanmamış olanlar
          videos = videos.filter(v => !this.allPublished(v));
        } else if (this.filterStatus === 'published') {
          // Tüm platformlarda yayınlanmış olanlar
          videos = videos.filter(v => this.allPublished(v));
        }
        this.filteredVideos = videos;
      },
      
      selectVideo(video) {
        this.selectedVideo = video;
        if (video.platform_status) {
          const platforms = Object.keys(video.platform_status);
          if (platforms.length > 0) this.previewPlatform = platforms[0];
        }
      },
      
      initSortable() {
        const el = document.getElementById('videoSortList');
        if (!el || !window.Sortable) return;
        if (el._sortable) el._sortable.destroy();
        el._sortable = new Sortable(el, {
          animation: 150,
          ghostClass: 'sortable-ghost',
          handle: '.drag-handle',
          onEnd: async (evt) => {
            const items = el.querySelectorAll('[data-job-id]');
            const order = Array.from(items).map(item => item.dataset.jobId);
            await this.reorderVideos(order);
          }
        });
      },
      
      async reorderVideos(order) {
        try {
          await fetch('/api/queues.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'reorder', queue_id: this.selectedQueue.id, video_order: order })
          });
          await this.selectQueueForDetail({ id: this.selectedQueue.id });
        } catch(e) { console.error('Sıralama hatası:', e); }
      },
      
      openMoveModal() {
        this.moveModal = true;
      },
      
      async moveVideoToQueue(targetQueueId) {
        if (!this.selectedVideo) return;
        try {
          // Remove from current
          await fetch('/api/queues.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'remove_video', queue_id: this.selectedQueue.id, job_id: this.selectedVideo.job_id })
          });
          // Add to target
          await fetch('/api/queues.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'add_video', queue_id: targetQueueId, job_id: this.selectedVideo.job_id })
          });
          this.moveModal = false;
          this.selectedVideo = null;
          await this.selectQueueForDetail({ id: this.selectedQueue.id });
          await this.loadQueues();
        } catch(e) { alert('Hata: ' + e.message); }
      },
      
      async createQueue() {
        if (!this.form.name.trim()) {
          alert('Kuyruk ismi gerekli!');
          return;
        }
        if (this.form.platforms.length === 0) {
          alert('En az bir platform seçmelisiniz!');
          return;
        }
        
        this.submitting = true;
        
        try {
          const response = await fetch('/api/queues.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'create',
              name: this.form.name.trim(),
              platforms: this.form.platforms,
              timezone: this.form.timezone || 'Europe/Istanbul'
            })
          });
          
          const result = await response.json();
          
          if (result.success) {
            alert('✅ Kuyruk oluşturuldu! Şimdi kuyruk ayarlarından platform ayarlarını yapılandırın.');
            this.closeModals();
            this.loadQueues();
          } else {
            alert('Hata: ' + (result.error || 'Bilinmeyen hata'));
          }
        } catch (error) {
          alert('Hata: ' + error.message);
        } finally {
          this.submitting = false;
        }
      },
      
      async updateQueue() {
        if (!this.form.name.trim()) {
          alert('Kuyruk ismi gerekli!');
          return;
        }
        if (this.form.platforms.length === 0) {
          alert('En az bir platform seçmelisiniz!');
          return;
        }
        
        // YouTube platformu seçiliyse kanal zorunlu
        if (this.form.platforms.includes('youtube') && this.form.platformSettings.youtube.enabled) {
          if (!this.form.platformSettings.youtube.channelId) {
            alert('YouTube için kanal seçimi zorunludur!');
            return;
          }
        }
        
        this.submitting = true;
        
        try {
          const schedule = {
            type: this.form.scheduleType,
            interval_hours: this.form.intervalHours,
            specific_times: this.form.specificTimes,
            timezone: 'Europe/Istanbul'
          };
          
          const response = await fetch('/api/queues.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'update',
              queue_id: this.selectedQueue.id,
              updates: {
                name: this.form.name.trim(),
                platforms: this.form.platforms,
                schedule: schedule,
                video_settings: this.buildVideoSettings()
              }
            })
          });
          
          const result = await response.json();
          
          if (result.success) {
            this.closeModals();
            this.loadQueues();
          } else {
            alert('Hata: ' + (result.error || 'Bilinmeyen hata'));
          }
        } catch (error) {
          alert('Hata: ' + error.message);
        } finally {
          this.submitting = false;
        }
      },
      
      async pauseQueue(queue) {
        try {
          const response = await fetch('/api/queues.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'pause',
              queue_id: queue.id
            })
          });
          
          const result = await response.json();
          
          if (result.success) {
            await this.loadQueues();
            if (this.selectedQueue && this.selectedQueue.id === queue.id) {
              await this.selectQueueTab(queue);
            }
          } else {
            alert('Hata: ' + (result.error || 'Bilinmeyen hata'));
          }
        } catch (error) {
          alert('Hata: ' + error.message);
        }
      },
      
      async resumeQueue(queue) {
        try {
          const response = await fetch('/api/queues.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'resume',
              queue_id: queue.id
            })
          });
          
          const result = await response.json();
          
          if (result.success) {
            await this.loadQueues();
            if (this.selectedQueue && this.selectedQueue.id === queue.id) {
              await this.selectQueueTab(queue);
            }
          } else {
            alert('Hata: ' + (result.error || 'Bilinmeyen hata'));
          }
        } catch (error) {
          alert('Hata: ' + error.message);
        }
      },
      
      async resetAndResumeQueue(queue) {
        if (!confirm('Kuyruk resetlenecek ve scheduler yeniden başlatılacak:\n\n✓ Duplicate videolar temizlenecek\n✓ Sıra numaraları düzenlenecek\n✓ Takılı kalan durumlar sıfırlanacak\n✓ Social scheduler yeniden başlatılacak\n\nDevam edilsin mi?')) {
          return;
        }
        
        try {
          // Step 1: Reset queue
          const response = await fetch('/api/queues.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'reset_and_resume',
              queue_id: queue.id
            })
          });
          
          const result = await response.json();
          
          if (result.success) {
            const stats = result.stats || {};
            let message = 'Kuyruk başarıyla resetlendi!\n\n';
            if (stats.duplicates_removed > 0) message += `✓ ${stats.duplicates_removed} duplicate video temizlendi\n`;
            if (stats.positions_fixed > 0) message += `✓ ${stats.positions_fixed} position düzeltildi\n`;
            if (stats.status_reset > 0) message += `✓ ${stats.status_reset} durum sıfırlandı\n`;
            if (stats.jobs_reset > 0) message += `✓ ${stats.jobs_reset} job dosyası güncellendi\n`;
            
            // Step 2: Restart social scheduler
            try {
              const schedulerResponse = await fetch('/api/scheduler_control.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                  action: 'restart',
                  type: 'social'
                })
              });
              
              const schedulerResult = await schedulerResponse.json();
              if (schedulerResult.success) {
                message += `\n🔄 Scheduler yeniden başlatıldı (PID: ${schedulerResult.pid})`;
              } else {
                message += `\n⚠️ Scheduler restart başarısız: ${schedulerResult.error || 'Bilinmeyen hata'}`;
              }
            } catch (schedulerError) {
              message += `\n⚠️ Scheduler restart hatası: ${schedulerError.message}`;
            }
            
            alert(message);
            
            await this.loadQueues();
            if (this.selectedQueue && this.selectedQueue.id === queue.id) {
              await this.selectQueueTab(queue);
            }
            // İstatistikleri yenile
            await this.loadQueueStats();
          } else {
            alert('Hata: ' + (result.error || 'Bilinmeyen hata'));
          }
        } catch (error) {
          alert('Hata: ' + error.message);
        }
      },
      
      async deleteQueue(queue) {
        if (!confirm('Bu kuyruğu silmek istediğinizden emin misiniz?\n\nKuyruk: ' + queue.name + '\nİçindeki ' + (queue.videos?.length || 0) + ' video kuyruktan çıkarılacak.')) {
          return;
        }
        
        try {
          const response = await fetch('/api/queues.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'delete',
              queue_id: queue.id
            })
          });
          
          const result = await response.json();
          
          if (result.success) {
            // Close any open modals
            this.closeModals();
            // Reload queue list
            await this.loadQueues();
            // Reset active tab to first queue or null
            if (this.queues.length > 0) {
              await this.selectQueueTab(this.queues[0]);
            } else {
              this.activeTab = null;
              this.selectedQueue = null;
            }
          } else {
            alert('Hata: ' + (result.error || 'Bilinmeyen hata'));
          }
        } catch (error) {
          alert('Hata: ' + error.message);
        }
      },
      
      async removeFromQueue(jobId) {
        if (!confirm('Bu videoyu kuyruktan çıkarmak istediğinizden emin misiniz?')) {
          return;
        }
        
        try {
          const response = await fetch('/api/queues.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'remove_video',
              queue_id: this.selectedQueue.id,
              job_id: jobId
            })
          });
          
          const result = await response.json();
          
          if (result.success) {
            this.selectedVideo = null;
            // Reload with new method if in two-column view
            if (!this.detailModal) {
              await this.selectQueueForDetail({id: this.selectedQueue.id});
            } else {
              this.openDetailModal({id: this.selectedQueue.id});
            }
            this.loadQueues();
          } else {
            alert('Hata: ' + (result.error || 'Bilinmeyen hata'));
          }
        } catch (error) {
          alert('Hata: ' + error.message);
        }
      },
      
      getScheduleText(queue) {
        const s = queue.schedule;
        if (!s) return 'Ayarlanmamış';
        if (s.type === 'now') return '⚡ Hemen';
        if (s.type === 'interval') {
          const mins = s.interval_minutes || (s.interval_hours * 60) || 120;
          const hours = (mins / 60).toFixed(1);
          let text = `⏰ Her ${mins} dk (${hours} saat)`;
          if (s.daily_limit > 0) text += ` | Max ${s.daily_limit}/gün`;
          if (s.start_time) {
            const st = s.start_time.includes('T') ? s.start_time.split('T')[1].substring(0,5) : s.start_time;
            text += ` | Başlangıç: ${st}`;
          }
          return text;
        }
        if (s.type === 'specific') return '📅 ' + (s.specific_times?.join(', ') || '');
        return 'Bilinmiyor';
      },
      
      getPendingCount(queue) {
        return (queue.videos || []).filter(v => v.status !== 'published').length;
      },
      
      getPublishedCount(queue) {
        return (queue.videos || []).filter(v => v.status === 'published').length;
      },
      
      toggleDark() {
        this.darkMode = !this.darkMode;
        localStorage.setItem('darkMode', this.darkMode ? '1' : '0');
        document.documentElement.classList.toggle('dark', this.darkMode);
      },
      
      async loadQueues() {
        try {
          const r = await fetch('/api/queues.php?action=list');
          const d = await r.json();
          this.queues = d.queues || [];
          
          // İlk kuyruk seçili gelsin (boş ekran olmasın)
          if (this.queues.length > 0 && !this.activeTab) {
            await this.selectQueueTab(this.queues[0]);
          } else if (this.activeTab && this.selectedQueue) {
            // Seçili kuyruğu güncelle (canlı refresh için)
            const r2 = await fetch('/api/queues.php?action=get&id=' + this.activeTab);
            const d2 = await r2.json();
            if (d2.success) {
              this.selectedQueue = d2.queue;
              this.filterVideos();
            }
          }
        } catch(e) {
          console.error('Kuyruklar yüklenemedi:', e);
        }
        this.loading = false;
      },

      // ── Platform durum yardımcıları ──────────────────────────────
      // platform_status hem { youtube: 'pending' } hem { youtube: { status:'pending' } } olabilir
      getPlatformStatus(video, platform) {
        if (!video || !video.platform_status) return 'pending';
        const ps = (video.platform_status || {})[platform];
        if (!ps) return 'pending';
        if (typeof ps === 'string') return ps;
        const raw = ps.status || 'pending';
        if (raw === 'uploaded' || raw === 'published') return 'success';
        if (raw === 'queued') return 'pending';
        if (raw === 'uploading') return 'processing';
        return raw;
      },

      getPlatformPostUrl(video, platform) {
        if (!video || !video.platform_status) return null;
        const ps = (video.platform_status || {})[platform];
        if (!ps || typeof ps === 'string') return null;
        return ps.post_url || null;
      },

      // ── Planlanan Paylaşım Tarihi Hesaplama ──────────────────────────────
      getScheduledPublishDate(video, platform) {
        if (!video || !this.selectedQueue) return 'Hesaplanıyor...';
        
        // 1. Video'da direkt scheduled_publish_date varsa kullan
        if (video.scheduled_publish_date) {
          return this.formatScheduleDate(video.scheduled_publish_date);
        }
        
        // 2. Platform status'ta uploaded_at varsa "Yayınlandı" göster
        const ps = video.platform_status?.[platform];
        if (ps) {
          const status = typeof ps === 'string' ? ps : ps.status;
          if (status === 'success' || status === 'published' || status === 'uploaded') {
            const uploadedAt = typeof ps === 'object' ? ps.uploaded_at : null;
            if (uploadedAt) {
              return this.formatScheduleDate(uploadedAt) + ' ✓';
            }
            return 'Yayınlandı ✓';
          }
        }
        
        // 3. Kuyruk ayarlarından hesapla
        const schedule = this.selectedQueue.schedule || {};
        const platformSettings = this.selectedQueue.platform_settings?.[platform] || {};
        
        // Schedule type kontrolü
        if (schedule.type === 'now') {
          return '⚡ Hemen';
        }
        
        // Video pozisyonu
        const position = video.position || 1;
        
        // Günlük limit
        const dailyLimit = parseInt(platformSettings.dailyLimit) || parseInt(schedule.daily_limit) || 0;
        
        // Başlangıç saati
        const startTime = platformSettings.startTime || schedule.start_time || '09:00';
        
        // Interval (dakika)
        const intervalMins = parseInt(platformSettings.intervalMinutes) || parseInt(schedule.interval_minutes) || 60;
        
        // Hesaplama
        const now = new Date();
        let targetDate = new Date();
        
        // Başlangıç saatini parse et
        const [startHour, startMin] = startTime.split(':').map(Number);
        
        if (schedule.type === 'interval') {
          // Kuyrukta bu videodan önceki pending video sayısı
          const pendingBefore = (this.selectedQueue.videos || [])
            .filter(v => (v.position || 999) < position && this.getPlatformStatus(v, platform) === 'pending')
            .length;
          
          // Bugün yapılan upload sayısı (published olanlar)
          const todayPublished = (this.selectedQueue.videos || [])
            .filter(v => {
              const s = this.getPlatformStatus(v, platform);
              return s === 'success' || s === 'published';
            }).length;
          
          // Eğer günlük limit varsa, hangi güne denk geleceğini hesapla
          let daysToAdd = 0;
          let videoIndexToday = pendingBefore;
          
          if (dailyLimit > 0) {
            // Bu video kaçıncı günde paylaşılacak?
            daysToAdd = Math.floor(videoIndexToday / dailyLimit);
            videoIndexToday = videoIndexToday % dailyLimit;
          }
          
          // Hedef gün
          targetDate.setDate(now.getDate() + daysToAdd);
          
          // Hedef saat = başlangıç + (sıra * interval)
          const totalMinutes = startHour * 60 + startMin + (videoIndexToday * intervalMins);
          targetDate.setHours(Math.floor(totalMinutes / 60), totalMinutes % 60, 0, 0);
          
          // Eğer hesaplanan zaman geçmişte kaldıysa, sonraki güne at
          if (targetDate < now && daysToAdd === 0) {
            targetDate.setDate(targetDate.getDate() + 1);
          }
          
        } else if (schedule.type === 'specific') {
          // Belirli saatler modunda
          const times = platformSettings.specificTimes || schedule.specific_times || ['09:00', '15:00', '21:00'];
          
          // Kuyrukta bu videodan önceki pending sayısı
          const pendingBefore = (this.selectedQueue.videos || [])
            .filter(v => (v.position || 999) < position && this.getPlatformStatus(v, platform) === 'pending')
            .length;
          
          // Hangi slot'a denk geliyor?
          const slotIndex = pendingBefore % times.length;
          const daysToAdd = Math.floor(pendingBefore / times.length);
          
          const [slotHour, slotMin] = times[slotIndex].split(':').map(Number);
          
          targetDate.setDate(now.getDate() + daysToAdd);
          targetDate.setHours(slotHour, slotMin, 0, 0);
          
          // Geçmişteyse sonraki güne
          if (targetDate < now && daysToAdd === 0) {
            targetDate.setDate(targetDate.getDate() + 1);
          }
        }
        
        return this.formatScheduleDate(targetDate.toISOString());
      },
      
      // ── Scheduled Time Gösterimi (YENİ UNIFIED QUEUE SİSTEMİ) ──────────
      formatScheduledTime(scheduledTimeStr) {
        if (!scheduledTimeStr) return 'Hesaplanıyor...';
        
        try {
          const scheduled = new Date(scheduledTimeStr);
          const now = new Date();
          
          // Geçmiş mi kontrol et
          const isPast = scheduled < now;
          
          // Tarih formatı
          const options = {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
          };
          const dateStr = scheduled.toLocaleDateString('tr-TR', options);
          
          // Kalan süre hesapla
          const diff = scheduled - now;
          const hours = Math.floor(diff / (1000 * 60 * 60));
          const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
          const days = Math.floor(hours / 24);
          
          let remainingStr = '';
          if (isPast) {
            remainingStr = '⚠️ Geçti';
          } else if (days > 0) {
            remainingStr = `${days} gün ${hours % 24} saat`;
          } else if (hours > 0) {
            remainingStr = `${hours} saat ${minutes} dk`;
          } else if (minutes > 0) {
            remainingStr = `${minutes} dakika`;
          } else {
            remainingStr = '⚡ Şimdi';
          }
          
          return { dateStr, remainingStr, isPast };
        } catch (e) {
          return { dateStr: scheduledTimeStr, remainingStr: '', isPast: false };
        }
      },
      
      getScheduledTimeDisplay(video) {
        if (!video || !video.scheduled_time) {
          return { show: false, dateStr: '', remainingStr: '' };
        }
        
        const result = this.formatScheduledTime(video.scheduled_time);
        return { show: true, ...result };
      },
      
      formatScheduleDate(isoString) {
        if (!isoString) return 'Bilinmiyor';
        try {
          const date = new Date(isoString);
          const now = new Date();
          const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
          const tomorrow = new Date(today);
          tomorrow.setDate(tomorrow.getDate() + 1);
          const targetDay = new Date(date.getFullYear(), date.getMonth(), date.getDate());
          
          const timeStr = date.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
          
          if (targetDay.getTime() === today.getTime()) {
            return `Bugün ${timeStr}`;
          } else if (targetDay.getTime() === tomorrow.getTime()) {
            return `Yarın ${timeStr}`;
          } else {
            const dateStr = date.toLocaleDateString('tr-TR', { day: 'numeric', month: 'short' });
            return `${dateStr} ${timeStr}`;
          }
        } catch (e) {
          return isoString;
        }
      },
      
      getScheduledPublishStatus(video, platform) {
        if (!video) return 'future';
        
        // Platform durumunu kontrol et
        const ps = video.platform_status?.[platform];
        if (ps) {
          const status = typeof ps === 'string' ? ps : ps.status;
          if (status === 'success' || status === 'published' || status === 'uploaded') {
            return 'published';
          }
        }
        
        // Schedule type kontrolü
        const schedule = this.selectedQueue?.schedule || {};
        if (schedule.type === 'now') {
          return 'now';
        }
        
        // Tarih hesapla
        const dateStr = this.getScheduledPublishDate(video, platform);
        if (dateStr.includes('Bugün')) return 'today';
        if (dateStr.includes('Yarın')) return 'tomorrow';
        return 'future';
      },

      // job_status: video üretim aşaması (job.json'dan API ile geliyor)
      getJobPhase(video) {
        const s = video.job_status || 'pending';
        return s; // pending, scraping, scripting, imaging, tts, subtitling, composing, done, failed
      },

      jobPhaseLabel(phase) {
        const map = {
          pending: '⏳ Bekliyor', scraping: '📰 Haber', scripting: '✍️ Script',
          imaging: '🖼️ Görsel', tts: '🎙️ Ses', subtitling: '💬 Altyazı',
          composing: '🎬 Video', done: '✅ Hazır', failed: '❌ Hata', paused: '⏸️ Durduruldu'
        };
        return map[phase] || phase;
      },

      jobPhaseClass(phase) {
        if (phase === 'done') return 'badge-published';
        if (phase === 'failed') return 'badge-failed';
        if (phase === 'pending') return 'badge-pending';
        if (phase === 'paused') return 'badge-pending';
        return 'badge-producing'; // üretim aşamaları
      },

      isProducing(video) {
        const s = video.job_status || 'pending';
        return !['done','failed','pending','paused'].includes(s);
      },

      getPlatformIcon(platform) {
        const icons = { youtube:'📺', tiktok:'🎵', instagram:'📸', facebook:'📘' };
        return icons[platform] || '🌐';
      },

      getPlatformKeys(video) {
        return Object.keys(video.platform_status || {});
      },

      // Platform badge HTML'ini string olarak üret (iç içe x-for sorununu çözer)
      renderPlatformBadges(video) {
        const icons = { youtube:'📺', tiktok:'🎵', instagram:'📸', facebook:'📘' };
        const ps = video.platform_status || {};
        return Object.keys(ps).map(platform => {
          const rawStatus = ps[platform];
          const status = typeof rawStatus === 'string' ? rawStatus : (rawStatus?.status || 'pending');
          const postUrl = typeof rawStatus === 'object' ? rawStatus?.post_url : null;
          const icon = icons[platform] || '🌐';
          let cls = 'badge-pending';
          let statusIcon = '·';
          let extra = '';
          if (status === 'published' || status === 'success') { cls = 'badge-published'; statusIcon = '✓'; }
          else if (status === 'processing') { cls = 'badge-uploading'; statusIcon = ''; extra = '<span class="icon-spin not-italic">↻</span>'; }
          else if (status === 'failed') { cls = 'badge-failed'; statusIcon = '✕'; }
          else if (status === 'queued') { cls = 'badge-queued'; statusIcon = '…'; }
          const link = postUrl ? `<a href="${postUrl}" target="_blank" onclick="event.stopPropagation()" class="hover:opacity-70">↗</a>` : '';
          const title = this.platformStatusLabel(status) + ' — ' + platform;
          return `<span class="platform-badge ${cls}" title="${title}">${icon}${extra || statusIcon}${link}</span>`;
        }).join('');
      },

      platformStatusClass(status) {
        if (status === 'published' || status === 'success') return 'badge-published';
        if (status === 'processing') return 'badge-uploading badge-uploading';
        if (status === 'failed') return 'badge-failed';
        if (status === 'queued') return 'badge-queued';
        return 'badge-pending';
      },

      platformStatusIcon(status) {
        if (status === 'published' || status === 'success') return '✓';
        if (status === 'processing') return '↑';
        if (status === 'failed') return '✕';
        if (status === 'queued') return '…';
        return '·';
      },

      platformStatusLabel(status) {
        if (status === 'published' || status === 'success') return 'Yayınlandı';
        if (status === 'processing') return 'Yükleniyor';
        if (status === 'failed') return 'Hata';
        if (status === 'queued') return 'Sırada';
        return 'Bekliyor';
      },

      // Platform hata mesajını al
      getPlatformError(video, platform) {
        if (!video || !video.platform_status) return null;
        const ps = (video.platform_status || {})[platform];
        if (!ps || typeof ps === 'string') return null;
        return ps.error || ps.last_error || null;
      },

      // Kuyruktaki platform hatalarını topla
      getQueuePlatformErrors(platform) {
        if (!this.selectedQueue?.videos) return [];
        const errors = [];
        for (const video of this.selectedQueue.videos) {
          const status = this.getPlatformStatus(video, platform);
          if (status === 'failed') {
            const error = this.getPlatformError(video, platform);
            errors.push({
              job_id: video.job_id,
              title: video.title || video.job_id,
              error: error || 'Bilinmeyen hata',
              added_at: video.added_at
            });
          }
        }
        return errors;
      },

      // Platformdaki başarılı yayın sayısı
      getPlatformPublishedCount(platform) {
        if (!this.selectedQueue?.videos) return 0;
        return this.selectedQueue.videos.filter(v => {
          const s = this.getPlatformStatus(v, platform);
          return s === 'published' || s === 'success';
        }).length;
      },

      // Platformdaki bekleyen video sayısı
      getPlatformPendingCount(platform) {
        if (!this.selectedQueue?.videos) return 0;
        return this.selectedQueue.videos.filter(v => {
          const s = this.getPlatformStatus(v, platform);
          return s === 'pending' || s === 'queued';
        }).length;
      },

      // Platformdaki hatalı video sayısı  
      getPlatformFailedCount(platform) {
        if (!this.selectedQueue?.videos) return 0;
        return this.selectedQueue.videos.filter(v => 
          this.getPlatformStatus(v, platform) === 'failed'
        ).length;
      },

      // Tüm platformlar yayınlandı mı?
      allPublished(video) {
        const platforms = Object.keys(video.platform_status || {});
        if (platforms.length === 0) return false;
        return platforms.every(p => {
          const s = this.getPlatformStatus(video, p);
          return s === 'published' || s === 'success';
        });
      },

      // Herhangi bir platform aktif upload ediyor mu?
      anyUploading(video) {
        return Object.keys(video.platform_status || {}).some(p =>
          this.getPlatformStatus(video, p) === 'processing'
        );
      },

      anyFailed(video) {
        return Object.keys(video.platform_status || {}).some(p =>
          this.getPlatformStatus(video, p) === 'failed'
        );
      },

      // Video satır sol kenar rengi sınıfı
      rowBorderClass(video) {
        if (this.anyUploading(video) || this.isProducing(video)) return 'row-producing';
        if (this.allPublished(video)) return 'row-done';
        if (this.anyFailed(video) || video.job_status === 'failed') return 'row-failed';
        return 'row-pending';
      },

      init() {
        this.darkMode = localStorage.getItem('darkMode') === '1';
        document.documentElement.classList.toggle('dark', this.darkMode);
        this.loadQueues();
        this.loadYoutubeChannels();
        // 15 saniyede bir canlı güncelleme
        setInterval(() => { if (this.selectedQueue) this.loadQueues(); }, 15000);
      },
      
      async loadYoutubeChannels() {
        try {
          const r = await fetch('/api/youtube_channels.php?action=list');
          const d = await r.json();
          if (d.success) {
            this.youtubeChannels = d.channels || [];
          }
        } catch(e) {
          console.error('YouTube kanalları yüklenemedi:', e);
        }
      },
      
      toggleSidebarCollapse() {
        this.sidebarCollapsed = !this.sidebarCollapsed;
        localStorage.setItem('sidebarCollapsed', this.sidebarCollapsed ? '1' : '0');
      }
    };
  }
  </script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-100 dark:bg-slate-900 min-h-screen" x-data="queuesApp()" x-init="init()">
  <div class="flex flex-col h-screen">
    <?php include __DIR__ . '/components/_header.php'; ?>

    <div class="flex flex-1 overflow-hidden">
      <?php include __DIR__ . '/components/_sidebar.php'; ?>

      <main class="flex-1 overflow-y-auto p-4 md:p-6">
        <div class="max-w-[1600px] mx-auto">

          <!-- Loading -->
          <template x-if="loading">
            <div class="flex items-center justify-center py-16">
              <svg class="w-8 h-8 text-indigo-500 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
              <span class="ml-3 text-gray-500 font-medium">Yükleniyor...</span>
            </div>
          </template>

          <!-- Empty State -->
          <template x-if="!loading && queues.length === 0">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 p-12 text-center">
              <svg class="w-16 h-16 mx-auto mb-4 text-gray-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
              </svg>
              <p class="text-gray-500 dark:text-gray-400 text-lg mb-2">Henüz kuyruk oluşturulmamış.</p>
              <p class="text-gray-400 dark:text-gray-500 text-sm mb-4">Videolarınızı organize etmek için bir kuyruk oluşturun.</p>
              <button 
                @click="openCreateModal()"
                class="inline-flex items-center gap-2 mt-3 px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold transition"
              >
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
                İlk Kuyruğu Oluştur
              </button>
            </div>
          </template>

          <!-- Queue List -->
          <template x-if="!loading && queues.length > 0">
            <div>
              <!-- Queue Tabs -->
              <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 mb-4">
                <div class="flex items-center gap-1 p-2 overflow-x-auto">
                  <template x-for="queue in queues" :key="queue.id">
                    <button 
                      @click="selectQueueTab(queue)"
                      class="px-4 py-2 rounded-lg text-sm font-medium transition whitespace-nowrap flex items-center gap-2"
                      :class="activeTab === queue.id ? 'bg-indigo-600 text-white shadow-sm' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700'"
                    >
                      <template x-for="p in queue.platforms.slice(0,2)" :key="p">
                        <span class="text-sm" x-text="p === 'youtube' ? '📺' : p === 'tiktok' ? '🎵' : p === 'instagram' ? '📸' : '📘'"></span>
                      </template>
                      <span x-text="queue.name"></span>
                      <span class="px-1.5 py-0.5 text-xs rounded-full" 
                            :class="activeTab === queue.id ? 'bg-white/20' : 'bg-gray-200 dark:bg-slate-600'"
                            x-text="(queue.videos?.length || 0)"></span>
                      <!-- Durum göstergesi -->
                      <span 
                        :title="queue.is_active ? 'Kuyruk çalışıyor' : 'Kuyruk durduruldu'"
                        class="text-lg"
                        x-text="queue.is_active ? '🟢' : '🔴'"
                      ></span>
                    </button>
                  </template>
                </div>
              </div>
              
              <!-- Main Content Grid: 3 columns (5-4-3) -->
              <div class="grid grid-cols-1 lg:grid-cols-12 gap-4" x-show="selectedQueue">
                
                <!-- Left: Videos List (5 cols) -->
                <div class="lg:col-span-4">
                  <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden">
                    <div class="p-3 border-b border-gray-100 dark:border-slate-700">
                      <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                          <button 
                            @click="openQueueSettingsModal()"
                            class="flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 rounded-lg transition"
                          >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                            Kuyruğu Düzenle
                          </button>
                          <!-- Pause/Resume Button -->
                          <button 
                            @click="selectedQueue?.is_active ? pauseQueue(selectedQueue) : resumeQueue(selectedQueue)"
                            class="flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-lg transition"
                            :class="selectedQueue?.is_active ? 'text-yellow-600 dark:text-yellow-400 hover:bg-yellow-50 dark:hover:bg-yellow-900/30' : 'text-green-600 dark:text-green-400 hover:bg-green-50 dark:hover:bg-green-900/30'"
                          >
                            <svg x-show="selectedQueue?.is_active" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <svg x-show="!selectedQueue?.is_active" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span x-text="selectedQueue?.is_active ? 'Durdur' : 'Çalıştır'"></span>
                          </button>
                          <!-- Reset & Resume Button -->
                          <button 
                            @click="resetAndResumeQueue(selectedQueue)"
                            class="flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-orange-600 dark:text-orange-400 hover:bg-orange-50 dark:hover:bg-orange-900/30 rounded-lg transition"
                            title="Duplicate temizle, sıra düzelt, durumları resetle"
                          >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Reset & Başlat
                          </button>
                        </div>
                        <select x-model="filterStatus" @change="filterVideos()" class="text-xs border border-gray-200 dark:border-slate-600 rounded-lg px-2 py-1 bg-white dark:bg-slate-700 dark:text-white">
                          <option value="pending">⏳ Bekleyen</option>
                          <option value="published">✅ Yayınlanan</option>
                          <option value="all">Tümü</option>
                        </select>
                      </div>
                    </div>
                    
                    <div class="max-h-[500px] overflow-y-auto" id="videoSortList">
                      <div x-show="filteredVideos.length === 0" class="p-6 text-center">
                        <p class="text-gray-400 dark:text-gray-500 text-sm">Video yok</p>
                        <a href="dashboard.php" class="text-indigo-600 text-xs hover:underline mt-2 inline-block">Ekle →</a>
                      </div>
                      
                      <template x-for="(video, idx) in filteredVideos" :key="idx">
                        <div 
                          @click="selectVideo(video)"
                          class="px-3 py-2.5 cursor-pointer transition-all border-b border-gray-100 dark:border-slate-700/60 last:border-0"
                          :class="[
                            selectedVideo?.job_id === video.job_id
                              ? 'bg-indigo-50 dark:bg-indigo-900/25'
                              : 'hover:bg-gray-50 dark:hover:bg-slate-700/40',
                            rowBorderClass(video)
                          ]"
                          :data-job-id="video.job_id"
                        >
                          <div class="flex items-start gap-2.5">
                            
                            <!-- Drag handle -->
                            <div class="drag-handle cursor-grab text-gray-300 dark:text-slate-600 hover:text-gray-400 mt-2">
                              <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20"><path d="M7 2a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm0 6a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm0 6a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm6-12a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm0 6a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm0 6a2 2 0 1 0 0 4 2 2 0 0 0 0-4z"/></svg>
                            </div>

                            <!-- Sıra numarası -->
                            <div class="w-5 h-5 rounded-full flex items-center justify-center flex-shrink-0 mt-1.5 text-[10px] font-bold"
                                 :class="allPublished(video) ? 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300' : isProducing(video) || anyUploading(video) ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300' : 'bg-gray-100 dark:bg-slate-600 text-gray-500 dark:text-gray-400'"
                                 x-text="idx + 1">
                            </div>

                            <!-- Thumbnail -->
                            <div class="w-10 h-14 rounded overflow-hidden bg-gray-200 dark:bg-slate-700 flex-shrink-0 relative group">
                              <video
                                x-show="video.videoUrl"
                                :src="video.videoUrl || ''"
                                :poster="video.thumbnailUrl || ''"
                                class="w-full h-full object-cover cursor-pointer"
                                muted playsinline
                                @click.stop="$event.target.paused ? $event.target.play() : $event.target.pause()"
                                @mouseenter="$event.target.play()"
                                @mouseleave="$event.target.pause(); $event.target.currentTime = 0;"
                              ></video>
                              <img
                                x-show="!video.videoUrl && video.thumbnailUrl"
                                :src="video.thumbnailUrl || ''"
                                class="w-full h-full object-cover"
                              >
                              <div x-show="!video.videoUrl && !video.thumbnailUrl" class="w-full h-full flex items-center justify-center">
                                <svg class="w-5 h-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                              </div>
                              <!-- Oynat overlay -->
                              <div x-show="video.videoUrl" class="absolute inset-0 bg-black/40 flex items-center justify-center opacity-0 group-hover:opacity-100 transition pointer-events-none">
                                <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                              </div>
                              <!-- Üretim spinnerı (video yoksa üretiliyor) -->
                              <div x-show="!video.videoUrl && isProducing(video)"
                                   class="absolute inset-0 bg-gradient-to-b from-blue-900/80 to-blue-600/80 flex items-center justify-center">
                                <svg class="w-4 h-4 text-white icon-spin" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                              </div>
                            </div>

                            <!-- Bilgi kolonu -->
                            <div class="flex-1 min-w-0">
                              <!-- Başlık -->
                              <p class="text-xs font-semibold text-gray-800 dark:text-white leading-tight mb-1.5" x-text="video.title || 'Video'"></p>

                              <!-- İş üretim aşaması + canlı nokta -->
                              <div class="inline-flex items-center gap-1 mb-1">
                                <span x-show="isProducing(video)" class="text-[9px] badge-live text-blue-500">●</span>
                                <span class="platform-badge" :class="jobPhaseClass(getJobPhase(video))" x-text="jobPhaseLabel(getJobPhase(video))"></span>
                              </div>
                              
                              <!-- 📅 SCHEDULED TIME (YENİ UNIFIED QUEUE) -->
                              <template x-if="video.scheduled_time">
                                <div class="inline-flex mt-1.5 text-[10px]">
                                  <div class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300">
                                    <span>📅</span>
                                    <span x-text="formatScheduleDate(video.scheduled_time)"></span>
                                  </div>                                                
                                </div>
                              </template>

                              <!-- Platform paylaşım durumu badge'leri (JS'de HTML olarak üretilir) -->
                              <div class="inline-flex flex-wrap gap-1" x-html="renderPlatformBadges(video)"></div>
                            </div>

                            <!-- Sağ: durum ikonu -->
                            <div class="flex-shrink-0 flex flex-col items-center gap-1 mt-1">
                              <div x-show="anyUploading(video)" class="flex items-center gap-0.5" title="Şu an yükleniyor">
                                <span class="text-[9px] badge-live font-bold text-amber-500">●</span>
                                <span class="text-[10px] font-semibold text-amber-500">Canlı</span>
                              </div>
                              <div x-show="allPublished(video)" class="w-5 h-5 rounded-full bg-green-100 dark:bg-green-900/40 flex items-center justify-center" title="Tüm platformlarda yayınlandı">
                                <svg class="w-3 h-3 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                              </div>
                              <div x-show="anyFailed(video) && !allPublished(video)" class="w-5 h-5 rounded-full bg-red-100 dark:bg-red-900/40 flex items-center justify-center" title="Bazı platformlarda hata">
                                <svg class="w-3 h-3 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M6 18L18 6M6 6l12 12"/></svg>
                              </div>
                            </div>

                          </div>
                        </div>
                      </template>
                    </div>
                  </div>
                </div>
                
                <!-- Center: Video Preview + Metadata (4 cols) -->
                <div class="lg:col-span-4">
                  <template x-if="!selectedVideo">
                    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 p-12 text-center h-full flex flex-col items-center justify-center">
                      <svg class="w-16 h-16 mb-4 text-gray-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                      <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-2">Video Seçin</h3>
                      <p class="text-gray-400 dark:text-gray-500 text-sm">Sol listeden bir video seçin</p>
                    </div>
                  </template>
                  
                  <template x-if="selectedVideo">
                    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 overflow-hidden">
                      <!-- Platform Tabs -->
                      <div class="p-4 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
                        <h3 class="font-semibold text-gray-800 dark:text-white flex items-center gap-2">
                          👁️ Önizleme
                        </h3>
                      </div>
                      <div class="flex gap-2 p-3 border-b border-gray-100 dark:border-slate-700 overflow-x-auto">
                        <template x-for="(status, platform) in selectedVideo?.platform_status || {}" :key="'tab-' + platform">
                          <button 
                            @click="previewPlatform = platform"
                            class="px-3 py-1.5 rounded-lg text-xs font-medium transition flex items-center gap-1.5"
                            :class="previewPlatform === platform ? 'bg-indigo-600 text-white' : 'bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-slate-600'"
                          >
                            <span x-text="platform === 'youtube' ? '📺' : platform === 'tiktok' ? '🎵' : platform === 'instagram' ? '📸' : '📘'"></span>
                            <span class="capitalize" x-text="platform"></span>
                          </button>
                        </template>
                      </div>
                      
                      <!-- Planlanan Paylaşım Tarihi/Saati -->
                      <div class="px-4 py-2 bg-gradient-to-r from-indigo-50 to-purple-50 dark:from-slate-800 dark:to-slate-700 border-b border-gray-100 dark:border-slate-600">
                        <div class="flex items-center justify-between">
                          <div class="flex items-center gap-2">
                            <span class="text-lg">📅</span>
                            <div>
                              <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Planlanan Paylaşım</span>
                              <p class="text-sm font-semibold text-gray-800 dark:text-white" x-text="getScheduledPublishDate(selectedVideo, previewPlatform)"></p>
                            </div>
                          </div>
                          <div class="text-right">
                            <template x-if="getScheduledPublishStatus(selectedVideo, previewPlatform) === 'today'">
                              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">
                                🟢 Bugün
                              </span>
                            </template>
                            <template x-if="getScheduledPublishStatus(selectedVideo, previewPlatform) === 'tomorrow'">
                              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
                                📆 Yarın
                              </span>
                            </template>
                            <template x-if="getScheduledPublishStatus(selectedVideo, previewPlatform) === 'future'">
                              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300">
                                ⏳ Bekliyor
                              </span>
                            </template>
                            <template x-if="getScheduledPublishStatus(selectedVideo, previewPlatform) === 'published'">
                              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                ✅ Yayınlandı
                              </span>
                            </template>
                            <template x-if="getScheduledPublishStatus(selectedVideo, previewPlatform) === 'now'">
                              <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300">
                                ⚡ Hemen
                              </span>
                            </template>
                          </div>
                        </div>
                        <!-- Reschedule reason if any -->
                        <template x-if="selectedVideo?.reschedule_reason === 'daily_limit_reached'">
                          <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                            ⚠️ Günlük limit nedeniyle sonraki güne aktarıldı
                          </p>
                        </template>
                      </div>
                      
                      <!-- Preview Grid: Video + Metadata -->
                      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4">
                        <!-- Left: Video Player with Platform Overlay -->
                        <div class="flex justify-center">
                          <div class="phone-frame w-48 shadow-2xl">
                            <div class="phone-screen aspect-[9/16] relative" x-data="{ playing: false }">
                              <!-- Video Player -->
                              <template x-if="selectedVideo?.videoUrl">
                                <div class="absolute inset-0">
                                  <video 
                                    x-ref="videoPlayer"
                                    :src="selectedVideo.videoUrl" 
                                    :poster="selectedVideo.thumbnailUrl"
                                    class="absolute inset-0 w-full h-full object-cover cursor-pointer"
                                    muted
                                    loop
                                    playsinline
                                    @click="playing = !playing; playing ? $refs.videoPlayer.play() : $refs.videoPlayer.pause()"
                                    @play="playing = true"
                                    @pause="playing = false"
                                  ></video>
                                  <!-- Play Button Overlay -->
                                  <div 
                                    x-show="!playing" 
                                    @click="playing = true; $refs.videoPlayer.play()"
                                    class="absolute inset-0 flex items-center justify-center bg-black/30 cursor-pointer transition"
                                  >
                                    <div class="w-16 h-16 bg-white/90 rounded-full flex items-center justify-center shadow-xl">
                                      <svg class="w-8 h-8 text-indigo-600 ml-1" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                    </div>
                                  </div>
                                </div>
                              </template>
                              <template x-if="!selectedVideo?.videoUrl && selectedVideo?.thumbnailUrl">
                                <img :src="selectedVideo.thumbnailUrl" class="absolute inset-0 w-full h-full object-cover">
                              </template>
                              <template x-if="!selectedVideo?.videoUrl && !selectedVideo?.thumbnailUrl">
                                <div class="absolute inset-0 bg-gradient-to-b from-gray-800 to-gray-900 flex items-center justify-center">
                                  <svg class="w-12 h-12 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                </div>
                              </template>
                              
                              <!-- TikTok Overlay -->
                              <div x-show="previewPlatform === 'tiktok'" class="absolute inset-0 pointer-events-none">
                                <div class="absolute right-1 bottom-12 flex flex-col items-center gap-2">
                                  <div class="text-center"><div class="w-7 h-7 bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center text-xs">❤️</div><span class="text-white text-[9px]">12K</span></div>
                                  <div class="text-center"><div class="w-7 h-7 bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center text-xs">💬</div><span class="text-white text-[9px]">234</span></div>
                                  <div class="w-7 h-7 bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center text-xs">↗️</div>
                                </div>
                                <div class="absolute bottom-1 left-1 right-8 bg-gradient-to-t from-black/60 to-transparent p-1">
                                  <p class="text-white font-semibold text-[10px]">@kullanici</p>
                                  <p class="text-white/90 text-[8px] line-clamp-1" x-text="selectedVideo?.title"></p>
                                </div>
                              </div>
                              
                              <!-- YouTube Overlay -->
                              <div x-show="previewPlatform === 'youtube'" class="absolute inset-0 pointer-events-none">
                                <div class="absolute top-1 left-1 bg-black/50 px-2 py-0.5 rounded">
                                  <span class="text-white text-[10px] font-bold">Shorts</span>
                                </div>
                                <div class="absolute right-1 bottom-12 flex flex-col items-center gap-2">
                                  <div class="text-center"><div class="w-7 h-7 bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center text-xs">👍</div><span class="text-white text-[9px]">15K</span></div>
                                  <div class="text-center"><div class="w-7 h-7 bg-white/20 backdrop-blur-sm rounded-full flex items-center justify-center text-xs">💬</div><span class="text-white text-[9px]">456</span></div>
                                </div>
                                <div class="absolute bottom-1 left-1 right-8 bg-gradient-to-t from-black/60 to-transparent p-1">
                                  <div class="flex items-center gap-1 mb-0.5">
                                    <div class="w-4 h-4 bg-red-500 rounded-full"></div>
                                    <span class="text-white text-[9px]">VideoKur</span>
                                  </div>
                                  <p class="text-white/90 text-[8px] line-clamp-1" x-text="selectedVideo?.title"></p>
                                </div>
                              </div>
                              
                              <!-- Instagram Overlay -->
                              <div x-show="previewPlatform === 'instagram'" class="absolute inset-0 pointer-events-none">
                                <div class="absolute top-1 left-1 bg-gradient-to-r from-purple-500 to-pink-500 px-2 py-0.5 rounded">
                                  <span class="text-white text-[10px] font-semibold">Reels</span>
                                </div>
                                <div class="absolute right-1 bottom-12 flex flex-col items-center gap-2">
                                  <div class="w-6 h-6 flex items-center justify-center text-sm">❤️</div>
                                  <div class="w-6 h-6 flex items-center justify-center text-sm">💬</div>
                                  <div class="w-6 h-6 flex items-center justify-center text-sm">↗️</div>
                                </div>
                                <div class="absolute bottom-1 left-1 right-8 bg-gradient-to-t from-black/60 to-transparent p-1">
                                  <p class="text-white font-semibold text-[10px]">videokur</p>
                                  <p class="text-white/90 text-[8px] line-clamp-1" x-text="selectedVideo?.title"></p>
                                </div>
                              </div>
                              
                              <!-- Facebook Overlay -->
                              <div x-show="previewPlatform === 'facebook'" class="absolute inset-0 pointer-events-none">
                                <div class="absolute top-1 left-1 flex items-center gap-1 bg-black/50 px-2 py-0.5 rounded">
                                  <div class="w-5 h-5 bg-blue-500 rounded-full flex items-center justify-center text-white text-[7px] font-bold">VK</div>
                                  <span class="text-white text-[9px] font-medium">Video Kur</span>
                                </div>
                                <div class="absolute bottom-1 left-1 right-1 bg-gradient-to-t from-black/60 to-transparent p-1">
                                  <p class="text-white/90 text-[8px] line-clamp-1" x-text="selectedVideo?.title"></p>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>
                        
                        <!-- Right: Metadata -->
                        <div class="space-y-3">
                          <div class="flex items-center justify-between">
                            <h4 class="font-semibold text-gray-800 dark:text-white text-sm">
                              <span x-text="previewPlatform === 'youtube' ? '📺 YouTube' : previewPlatform === 'tiktok' ? '🎵 TikTok' : previewPlatform === 'instagram' ? '📸 Instagram' : '📘 Facebook'"></span> Bilgileri
                            </h4>
                            <template x-if="!editingMetadata">
                              <button @click="startEditMetadata()" class="text-xs text-indigo-600 hover:underline">Düzenle</button>
                            </template>
                          </div>
                          
                          <template x-if="!editingMetadata">
                            <div class="space-y-3">
                              <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Başlık</label>
                                <p class="text-sm text-gray-800 dark:text-white" x-text="selectedVideo?.title || '-'"></p>
                              </div>
                              <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Açıklama</label>
                                <p class="text-sm text-gray-800 dark:text-white line-clamp-3" x-text="selectedVideo?.description || '-'"></p>
                              </div>
                              <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Durum</label>
                                <span 
                                  class="text-xs px-2 py-1 rounded-full"
                                  :class="{
                                    'bg-green-100 dark:bg-green-900/30 text-green-700': getPlatformStatus(selectedVideo, previewPlatform) === 'success',
                                    'bg-yellow-100 dark:bg-yellow-900/30 text-yellow-700': getPlatformStatus(selectedVideo, previewPlatform) === 'pending',
                                    'bg-blue-100 dark:bg-blue-900/30 text-blue-700': getPlatformStatus(selectedVideo, previewPlatform) === 'processing',
                                    'bg-red-100 dark:bg-red-900/30 text-red-700': getPlatformStatus(selectedVideo, previewPlatform) === 'failed'
                                  }"
                                  x-text="getPlatformStatus(selectedVideo, previewPlatform) === 'success' ? '✓ Yayınlandı' : getPlatformStatus(selectedVideo, previewPlatform) === 'processing' ? '🔄 Yükleniyor' : getPlatformStatus(selectedVideo, previewPlatform) === 'failed' ? '✗ Başarısız' : '⏳ Bekliyor'"
                                ></span>
                              </div>
                              <template x-if="getPlatformStatus(selectedVideo, previewPlatform) === 'success' && getPlatformPostUrl(selectedVideo, previewPlatform)">
                                <a :href="getPlatformPostUrl(selectedVideo, previewPlatform)" target="_blank" class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:underline">
                                  <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                                  Paylaşıma Git
                                </a>
                              </template>
                            </div>
                          </template>
                          
                          <template x-if="editingMetadata">
                            <div class="space-y-3">
                              <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Başlık</label>
                                <input type="text" x-model="metadata.title" class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-slate-600 rounded-lg">
                              </div>
                              <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Açıklama</label>
                                <textarea x-model="metadata.description" rows="3" class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-slate-600 rounded-lg"></textarea>
                              </div>
                              <div>
                                <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Etiketler (virgülle ayırın)</label>
                                <input type="text" x-model="metadata.tags" class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-slate-600 rounded-lg" placeholder="#shorts, #viral">
                              </div>
                              <div class="flex gap-2">
                                <button @click="cancelEditMetadata()" class="flex-1 px-3 py-2 text-xs bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300 rounded-lg">İptal</button>
                                <button @click="saveMetadata()" class="flex-1 px-3 py-2 text-xs bg-indigo-600 text-white rounded-lg">Kaydet</button>
                              </div>
                            </div>
                          </template>
                          
                          <!-- Actions -->
                          <div class="pt-3 border-t border-gray-100 dark:border-slate-700 flex gap-2">
                            <button @click="openMoveModal()" class="flex-1 px-3 py-2 text-xs bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-slate-600 transition">
                              📦 Taşı
                            </button>
                            <button @click="removeFromQueue(selectedVideo.job_id)" class="flex-1 px-3 py-2 text-xs bg-red-50 dark:bg-red-900/30 text-red-600 rounded-lg hover:bg-red-100 transition">
                              🗑️ Çıkar
                            </button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </template>
                </div>
                
                <!-- Right: Queue Stats Widget (3 cols) -->
                <div class="lg:col-span-4">
                  <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 sticky top-4">
                    <div class="p-4 border-b border-gray-100 dark:border-slate-700 flex items-center justify-between">
                      <h3 class="font-semibold text-gray-800 dark:text-white flex items-center gap-2">
                        📊 Kuyruk Durumu
                      </h3>
                      <button @click="loadQueueStats()" class="text-xs text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                        🔄 Yenile
                      </button>
                    </div>
                    
                    <div class="p-4 space-y-4">
                      <!-- Loading -->
                      <template x-if="loadingStats">
                        <div class="text-center py-4">
                          <div class="w-6 h-6 border-2 border-indigo-500 border-t-transparent rounded-full animate-spin mx-auto"></div>
                          <p class="text-xs text-gray-400 mt-2">Yükleniyor...</p>
                        </div>
                      </template>
                      
                      <!-- Stats Content -->
                      <template x-if="!loadingStats && queueStats">
                        <div class="space-y-4">
                          <!-- Queue Status -->
                          <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-slate-700/50 rounded-lg">
                            <span class="text-sm text-gray-600 dark:text-gray-300">Durum</span>
                            <span class="flex items-center gap-1.5 text-sm font-medium" 
                                  :class="queueStats.blocked_reason ? 'text-red-600 dark:text-red-400' : (queueStats.is_active && isSocialSchedulerRunning() ? 'text-green-600 dark:text-green-400' : 'text-yellow-500')">
                              <span class="w-2 h-2 rounded-full animate-pulse" :class="queueStats.blocked_reason ? 'bg-red-500' : (queueStats.is_active && isSocialSchedulerRunning() ? 'bg-green-500' : 'bg-yellow-500')"></span>
                              <span x-text="queueStats.blocked_reason ? '⛔ Engellendi' : (queueStats.is_active && isSocialSchedulerRunning() ? 'Çalışıyor' : '⏸️ Durduruldu')"></span>
                            </span>
                          </div>
                          
                          <!-- Error Alert -->
                          <template x-if="queueStats.blocked_reason">
                            <div class="p-3 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg">
                              <div class="flex items-start gap-2">
                                <span class="text-red-500 text-lg">⚠️</span>
                                <div class="flex-1 min-w-0">
                                  <p class="text-xs font-medium text-red-700 dark:text-red-400 mb-1">Paylaşım Engellendi</p>
                                  <p class="text-xs text-red-600 dark:text-red-300 break-words" x-html="queueStats.blocked_reason.replace(/<a[^>]*>|<\/a>/g, '')"></p>
                                </div>
                              </div>
                              <button 
                                @click="resetAndResumeQueue(selectedQueue)"
                                class="mt-2 w-full py-1.5 text-xs font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg transition flex items-center justify-center gap-1"
                              >
                                🔄 Sıfırla ve Tekrar Dene
                              </button>
                            </div>
                          </template>
                          
                          <!-- Quota Error Warning -->
                          <template x-if="queueStats.scheduler_errors?.quota_blocked && !queueStats.blocked_reason">
                            <div class="p-3 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 rounded-lg">
                              <div class="flex items-start gap-2">
                                <span class="text-amber-500 text-lg">⚠️</span>
                                <div class="flex-1 min-w-0">
                                  <p class="text-xs font-medium text-amber-700 dark:text-amber-400 mb-1">YouTube Kota Aşıldı</p>
                                  <p class="text-xs text-amber-600 dark:text-amber-300">
                                    <span x-text="queueStats.scheduler_errors.quota_error_count || 0"></span> video kota nedeniyle yüklenemedi.
                                    Google API kotası günlük sıfırlanır (TR saat ~10:00).
                                  </p>
                                </div>
                              </div>
                              <div class="mt-2 text-xs text-amber-600 dark:text-amber-400">
                                <template x-if="queueStats.scheduler_errors.last_error">
                                  <p class="truncate" x-text="'Son hata: ' + (queueStats.scheduler_errors.last_error.error_message || '-')"></p>
                                </template>
                              </div>
                            </div>
                          </template>
                          
                          <!-- Current Item -->
                          <template x-if="queueStats.current_item">
                            <div class="p-3 border border-gray-200 dark:border-slate-600 rounded-lg">
                              <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">📍 Sıradaki Video</p>
                              <div class="flex items-center gap-2">
                                <span class="w-6 h-6 flex items-center justify-center text-xs font-bold rounded-full"
                                      :class="queueStats.current_item.status === 'failed' ? 'bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-400' : 
                                              queueStats.current_item.status === 'processing' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/50 dark:text-blue-400' : 
                                              'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/50 dark:text-yellow-400'"
                                      x-text="queueStats.current_item.position"></span>
                                <div class="flex-1 min-w-0">
                                  <p class="text-sm font-medium text-gray-800 dark:text-white truncate" x-text="queueStats.current_item.title"></p>
                                  <p class="text-xs" 
                                     :class="queueStats.current_item.status === 'failed' ? 'text-red-500' : 
                                             queueStats.current_item.status === 'processing' ? 'text-blue-500' : 'text-yellow-500'"
                                     x-text="queueStats.current_item.status === 'failed' ? '❌ Başarısız' : 
                                             queueStats.current_item.status === 'processing' ? '🔄 Yükleniyor...' : '⏳ Bekliyor'"></p>
                                </div>
                              </div>
                            </div>
                          </template>
                          
                          <!-- Production Status -->
                          <template x-if="queueStats.production_status && isProductionSchedulerRunning()">
                            <div class="p-3 bg-purple-50 dark:bg-purple-900/20 border border-purple-200 dark:border-purple-800 rounded-lg">
                              <div class="flex items-center gap-2">
                                <span class="text-purple-500">🎬</span>
                                <div class="flex-1">
                                  <p class="text-xs font-medium text-purple-700 dark:text-purple-400">
                                    <span x-show="queueStats.production_status.status === 'producing'">Video Üretiliyor...</span>
                                    <span x-show="queueStats.production_status.status === 'waiting'" x-text="'⏳ ' + (queueStats.production_status.waiting_count || 0) + ' video üretim bekliyor'"></span>
                                  </p>
                                </div>
                                <div x-show="queueStats.production_status.status === 'producing'" class="w-4 h-4 border-2 border-purple-500 border-t-transparent rounded-full animate-spin"></div>
                              </div>
                            </div>
                          </template>
                          
                          <!-- Platform Stats -->
                          <template x-for="platform in Object.keys(queueStats.platforms || {})" :key="platform">
                            <div class="border border-gray-100 dark:border-slate-600 rounded-lg p-3">
                              <div class="flex items-center gap-2 mb-2">
                                <span x-text="platform === 'youtube' ? '📺' : platform === 'tiktok' ? '🎵' : platform === 'instagram' ? '📸' : '📘'" class="text-lg"></span>
                                <span class="font-medium text-gray-800 dark:text-white capitalize" x-text="platform === 'youtube' ? 'YouTube' : platform === 'tiktok' ? 'TikTok' : platform === 'instagram' ? 'Instagram' : 'Facebook'"></span>
                              </div>
                              <div class="grid grid-cols-2 gap-2 text-xs">
                                <div class="flex items-center justify-between bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 px-2 py-1 rounded">
                                  <span>✅ Yayında</span>
                                  <span class="font-bold" x-text="queueStats.platforms[platform]?.published || 0"></span>
                                </div>
                                <div class="flex items-center justify-between bg-yellow-50 dark:bg-yellow-900/20 text-yellow-700 dark:text-yellow-400 px-2 py-1 rounded">
                                  <span>⏳ Bekliyor</span>
                                  <span class="font-bold" x-text="queueStats.platforms[platform]?.pending || 0"></span>
                                </div>
                                <div class="flex items-center justify-between bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 px-2 py-1 rounded">
                                  <span>🔄 Yükleniyor</span>
                                  <span class="font-bold" x-text="queueStats.platforms[platform]?.uploading || 0"></span>
                                </div>
                                <div class="flex items-center justify-between bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 px-2 py-1 rounded">
                                  <span>❌ Hata</span>
                                  <span class="font-bold" x-text="queueStats.platforms[platform]?.failed || 0"></span>
                                </div>
                              </div>
                            </div>
                          </template>
                          
                          <!-- Totals -->
                          <div class="pt-3 border-t border-gray-100 dark:border-slate-600">
                            <div class="grid grid-cols-2 gap-2 text-sm">
                              <div class="text-center p-2 bg-indigo-50 dark:bg-indigo-900/30 rounded-lg">
                                <div class="text-2xl font-bold text-indigo-600 dark:text-indigo-400" x-text="Object.values(queueStats.platforms || {}).reduce((s, p) => s + (p.published || 0), 0)"></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Toplam Yayınlanan</div>
                              </div>
                              <div class="text-center p-2 bg-amber-50 dark:bg-amber-900/30 rounded-lg">
                                <div class="text-2xl font-bold text-amber-600 dark:text-amber-400" x-text="Object.values(queueStats.platforms || {}).reduce((s, p) => s + (p.pending || 0), 0)"></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">Toplam Bekleyen</div>
                              </div>
                            </div>
                          </div>
                        </div>
                      </template>
                      
                      <!-- No Stats -->
                      <template x-if="!loadingStats && !queueStats">
                        <div class="text-center py-4 text-gray-400 dark:text-gray-500 text-sm">
                          İstatistik bulunamadı
                        </div>
                      </template>
                    </div>
                  </div>
                </div>
                
              </div>
              
              <!-- No Queue Selected -->
              <div x-show="!selectedQueue" class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-gray-100 dark:border-slate-700 p-12 text-center">
                <svg class="w-16 h-16 mx-auto mb-4 text-gray-300 dark:text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-300 mb-2">Kuyruk Seçin</h3>
                <p class="text-gray-400 dark:text-gray-500">Yukarıdaki tab'lardan bir kuyruk seçin</p>
              </div>
            </div>
          </template>
        </div>
      </main>
    </div>

    <?php include __DIR__ . '/components/_footer.php'; ?>
  </div>

  <!-- Create/Edit Queue Modal - Same Design as Queue Settings -->
  <template x-if="createModal || editModal">
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" @click.self="closeModals()">
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xl w-full max-w-lg overflow-hidden max-h-[95vh] flex flex-col">
        
        <!-- Minimal Header -->
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-200 dark:border-slate-700">
          <div class="flex items-center gap-2">
            <span class="text-xl" x-text="createModal ? '📋' : '✏️'"></span>
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white" x-text="createModal ? 'Yeni Kuyruk Oluştur' : 'Kuyruğu Düzenle'"></h3>
          </div>
          <button @click="closeModals()" class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 transition">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
        
        <!-- Content - Scrollable -->
        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
          
          <!-- CREATE MODE: Simple form (name + platforms only) -->
          <template x-if="createModal">
            <div class="space-y-4">
              <!-- Queue Name -->
              <div>
                <label class="flex items-center gap-1.5 text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">
                  <span>📝</span>
                  <span>Kuyruk Adı</span>
                </label>
                <input 
                  type="text" 
                  x-model="form.name"
                  placeholder="Örn: YouTube Prime Time"
                  class="w-full px-3 py-2.5 text-sm border border-gray-200 dark:border-slate-600 dark:bg-slate-900 dark:text-white rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
                >
              </div>
              
              <!-- Platform Selection -->
              <div>
                <label class="flex items-center gap-1.5 text-xs font-semibold text-gray-600 dark:text-gray-400 mb-3">
                  <span>🌐</span>
                  <span>Platform Seçimi</span>
                </label>
                
                <div class="grid grid-cols-2 gap-2">
                  <label class="flex items-center gap-2 p-3 border-2 rounded-lg cursor-pointer transition"
                    :class="form.platforms.includes('youtube') ? 'border-red-500 bg-red-50/50 dark:bg-red-900/20' : 'border-gray-200 dark:border-slate-600'">
                    <input type="checkbox" value="youtube" 
                      :checked="form.platforms.includes('youtube')"
                      @change="$event.target.checked ? form.platforms.push('youtube') : form.platforms = form.platforms.filter(p => p !== 'youtube')"
                      class="w-4 h-4 text-red-600 rounded">
                    <span>📺 YouTube</span>
                  </label>
                  
                  <label class="flex items-center gap-2 p-3 border-2 rounded-lg cursor-pointer transition"
                    :class="form.platforms.includes('instagram') ? 'border-pink-500 bg-pink-50/50 dark:bg-pink-900/20' : 'border-gray-200 dark:border-slate-600'">
                    <input type="checkbox" value="instagram"
                      :checked="form.platforms.includes('instagram')"
                      @change="$event.target.checked ? form.platforms.push('instagram') : form.platforms = form.platforms.filter(p => p !== 'instagram')"
                      class="w-4 h-4 text-pink-600 rounded">
                    <span>📸 Instagram</span>
                  </label>
                  
                  <!-- TikTok ve Facebook şimdilik devre dışı -->
                </div>
              </div>
              
              <!-- Info Box -->
              <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg">
                <div class="flex gap-2">
                  <span class="text-lg">💡</span>
                  <div class="text-xs text-blue-800 dark:text-blue-200">
                    <p class="font-semibold mb-1">Sonraki Adım:</p>
                    <p>Kuyruk oluşturulduktan sonra, kuyruk ayarlarından platform ayarlarını (zamanlama, gizlilik, vs.) yapılandırabilirsiniz.</p>
                  </div>
                </div>
              </div>
            </div>
          </template>
          
          <!-- EDIT MODE: Full form (all settings) -->
          <template x-if="editModal">
            <div class="space-y-3">
              <!-- Queue Name & Video Settings -->
          <div class="space-y-3">
            <div>
              <label class="flex items-center gap-1.5 text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">
                <span>📝</span>
                <span>Kuyruk Adı</span>
              </label>
              <input 
                type="text" 
                x-model="form.name"
                placeholder="Örn: YouTube Prime Time"
                class="w-full px-3 py-2.5 text-sm border border-gray-200 dark:border-slate-600 dark:bg-slate-900 dark:text-white rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
              >
            </div>
            
            <div>
              <label class="flex items-center gap-1.5 text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">
                <span>🎬</span>
                <span>Video Boyutu</span>
              </label>
              <select 
                x-model="form.dimensionPreset"
                class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-slate-600 dark:bg-slate-900 dark:text-white rounded-lg focus:ring-2 focus:ring-indigo-500"
              >
                <option value="vertical">📱 Dikey (9:16) - Shorts/Reels</option>
                <option value="square">⬛ Kare (1:1) - Instagram</option>
                <option value="horizontal">🖥️ Yatay (16:9) - YouTube</option>
              </select>
            </div>
          </div>

          <!-- Platform Selection -->
          <div class="pt-3 border-t border-gray-200 dark:border-slate-700">
            <label class="flex items-center gap-1.5 text-xs font-semibold text-gray-600 dark:text-gray-400 mb-3">
              <span>🌐</span>
              <span>Platform Seçimi</span>
            </label>
            
            <div class="grid grid-cols-2 gap-2">
              <label class="flex items-center gap-2 p-2.5 border-2 rounded-lg cursor-pointer transition text-sm"
                :class="form.platformSettings.youtube.enabled ? 'border-red-500 bg-red-50/50 dark:bg-red-900/20' : 'border-gray-200 dark:border-slate-600'">
                <input type="checkbox" x-model="form.platformSettings.youtube.enabled" class="w-4 h-4 text-red-600 rounded">
                <span>📺 YouTube</span>
              </label>
              
              <label class="flex items-center gap-2 p-2.5 border-2 rounded-lg cursor-pointer transition text-sm"
                :class="form.platformSettings.instagram.enabled ? 'border-pink-500 bg-pink-50/50 dark:bg-pink-900/20' : 'border-gray-200 dark:border-slate-600'">
                <input type="checkbox" x-model="form.platformSettings.instagram.enabled" class="w-4 h-4 text-pink-600 rounded">
                <span>📸 Instagram</span>
              </label>
              
              <!-- TikTok ve Facebook şimdilik devre dışı -->
            </div>
          </div>

          <!-- YouTube Settings (if enabled) -->
          <div x-show="form.platformSettings.youtube.enabled" x-transition class="pt-3 border-t border-gray-200 dark:border-slate-700 space-y-3 bg-red-50/30 dark:bg-red-900/10 rounded-lg p-3 border border-red-100 dark:border-red-900/30">
            <div class="flex items-center gap-2 mb-2">
              <span>📺</span>
              <span class="text-xs font-semibold text-gray-700 dark:text-gray-300">YouTube Ayarları</span>
            </div>

            <!-- Hedef Kanal -->
            <div>
              <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5 block">🔗 Hedef Kanal <span class="text-red-500">*</span></label>
              <select x-model="form.platformSettings.youtube.channelId" 
                      x-effect="$el.innerHTML = '<option value=\'\' disabled>-- Kanal Seçin --</option>' + youtubeChannels.map(ch => `<option value='${ch.id}' ${ch.id === form.platformSettings.youtube.channelId ? 'selected' : ''}>${ch.channel_title}</option>`).join('')"
                      class="w-full px-2.5 py-1.5 text-xs border border-gray-200 dark:border-slate-600 dark:bg-slate-900 dark:text-white rounded-lg" required>
                <option value="" disabled>-- Kanal Seçin --</option>
              </select>
              <p class="text-[10px] text-red-500 dark:text-red-400 mt-0.5" x-show="!form.platformSettings.youtube.channelId && form.platformSettings.youtube.enabled">⚠️ Kanal seçimi zorunludur</p>
            </div>

            <!-- Zamanlama Tipi -->
              <div>
                <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5 block">⏰ Zamanlama</label>
                <select x-model="form.platformSettings.youtube.scheduleType" class="w-full px-2.5 py-1.5 text-xs border border-gray-200 dark:border-slate-600 dark:bg-slate-900 dark:text-white rounded-lg">
                  <option value="now">Hemen</option>
                  <option value="interval">Aralıklı</option>
                  <option value="specific">Belirli Saatler</option>
                </select>
              </div>

              <!-- Interval Settings -->
              <template x-if="form.platformSettings.youtube.scheduleType === 'interval'">
                <div class="space-y-2">
                  <!-- Start Time -->
                  <div>
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1 block">📅 İlk Paylaşım Saati</label>
                    <input 
                      type="time"
                      x-model="form.platformSettings.youtube.startTime"
                      class="w-full px-2.5 py-1.5 text-xs border border-gray-200 dark:border-slate-600 dark:bg-slate-900 dark:text-white rounded-lg"
                      placeholder="09:00"
                    />
                    <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">Boş = hemen başlar</p>
                  </div>
                  
                  <!-- Interval Minutes -->
                  <div>
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1 block">⏱️ Aralık (Dakika)</label>
                    <div class="grid grid-cols-2 gap-1.5">
                      <input 
                        type="number"
                        x-model="form.platformSettings.youtube.intervalMinutes"
                        min="1"
                        class="w-full px-2.5 py-1.5 text-xs border border-gray-200 dark:border-slate-600 dark:bg-slate-900 dark:text-white rounded-lg"
                        placeholder="120"
                      />
                      <select x-model="form.platformSettings.youtube.intervalMinutes" class="w-full px-2.5 py-1.5 text-xs border border-gray-200 dark:border-slate-600 dark:bg-slate-900 dark:text-white rounded-lg">
                        <option value="30">30 dk</option>
                        <option value="60">1 saat</option>
                        <option value="90">1.5 saat</option>
                        <option value="120">2 saat</option>
                        <option value="180">3 saat</option>
                        <option value="240">4 saat</option>
                        <option value="360">6 saat</option>
                        <option value="720">12 saat</option>
                      </select>
                    </div>
                    <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">
                      = <span x-text="(form.platformSettings.youtube.intervalMinutes/60).toFixed(1)"></span> saat
                    </p>
                  </div>
                  
                  <!-- Daily Limit -->
                  <div>
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1 block">📊 Günlük Limit</label>
                    <div class="grid grid-cols-2 gap-1.5">
                      <input 
                        type="number"
                        x-model="form.platformSettings.youtube.dailyLimit"
                        min="0"
                        class="w-full px-2.5 py-1.5 text-xs border border-gray-200 dark:border-slate-600 dark:bg-slate-900 dark:text-white rounded-lg"
                        placeholder="0"
                      />
                      <select x-model="form.platformSettings.youtube.dailyLimit" class="w-full px-2.5 py-1.5 text-xs border border-gray-200 dark:border-slate-600 dark:bg-slate-900 dark:text-white rounded-lg">
                        <option value="0">Limitsiz</option>
                        <option value="3">3 video/gün</option>
                        <option value="4">4 video/gün</option>
                        <option value="5">5 video/gün</option>
                        <option value="6">6 video/gün</option>
                        <option value="8">8 video/gün</option>
                        <option value="10">10 video/gün</option>
                      </select>
                    </div>
                    <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">
                      <span x-show="form.platformSettings.youtube.dailyLimit == 0">Tüm videolar paylaşılır</span>
                      <span x-show="form.platformSettings.youtube.dailyLimit > 0">Max <span x-text="form.platformSettings.youtube.dailyLimit"></span>/gün</span>
                    </p>
                  </div>
                </div>
              </template>

              <!-- Specific Times -->
              <template x-if="form.platformSettings.youtube.scheduleType === 'specific'">
                <div>
                  <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1 block">🕐 Saatler</label>
                  <div class="flex flex-wrap gap-1.5">
                    <template x-for="(time, idx) in form.platformSettings.youtube.specificTimes" :key="idx">
                      <div class="flex items-center gap-1 bg-white dark:bg-slate-800 rounded px-2 py-1 border border-gray-200 dark:border-slate-600">
                        <input 
                          type="time" 
                          x-model="form.platformSettings.youtube.specificTimes[idx]"
                          class="bg-transparent border-none text-xs w-16 focus:outline-none dark:text-white"
                        >
                        <button 
                          @click="form.platformSettings.youtube.specificTimes.splice(idx, 1)"
                          class="text-gray-400 hover:text-red-500"
                          x-show="form.platformSettings.youtube.specificTimes.length > 1"
                        >
                          <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                      </div>
                    </template>
                    <button 
                      @click="form.platformSettings.youtube.specificTimes.push('12:00')"
                      class="px-2 py-1 text-xs font-medium text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded transition"
                    >
                      + Saat
                    </button>
                  </div>
                </div>
              </template>

              <!-- Privacy -->
              <div>
                <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1 block">👁️ Görünürlük</label>
                <select x-model="form.platformSettings.youtube.privacy" class="w-full px-2.5 py-1.5 text-xs border border-gray-200 dark:border-slate-600 dark:bg-slate-900 dark:text-white rounded-lg">
                  <option value="public">Herkese Açık</option>
                  <option value="unlisted">Liste Dışı</option>
                  <option value="private">Gizli</option>
                </select>
              </div>
            </div>
          </template>
          <!-- END EDIT MODE -->

        </div>

        <!-- Footer Actions -->
        <div class="flex items-center justify-end gap-2 px-5 py-3.5 border-t border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-900/50">
          <button 
            @click="closeModals()" 
            class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-slate-800 hover:bg-gray-100 dark:hover:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg transition"
          >
            İptal
          </button>
          <button 
            @click="createModal ? createQueue() : updateQueue()" 
            :disabled="submitting"
            class="px-4 py-2 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed"
          >
            <span x-show="!submitting" x-text="createModal ? '✓ Oluştur' : '✓ Kaydet'"></span>
            <span x-show="submitting">⏳ İşleniyor...</span>
          </button>
        </div>
      </div>
    </div>
  </template>

  <!-- Queue Detail Modal -->
  <template x-if="detailModal">
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" @click.self="closeModals()">
      <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-2xl mx-4 overflow-hidden max-h-[90vh] overflow-y-auto">
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4">
          <div class="flex items-center justify-between">
            <h3 class="text-xl font-bold text-white">
              📋 <span x-text="selectedQueue?.name || 'Kuyruk Detayı'"></span>
            </h3>
            <button @click="closeModals()" class="text-white/80 hover:text-white">
              <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
        </div>
        
        <div class="p-6">
          <template x-if="!selectedQueue">
            <div class="text-center py-8">
              <svg class="w-8 h-8 mx-auto text-indigo-500 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
              <p class="mt-3 text-gray-500 dark:text-gray-400">Yükleniyor...</p>
            </div>
          </template>
          
          <template x-if="selectedQueue">
            <div>
              <!-- Queue Info -->
              <div class="mb-6 p-4 bg-gray-50 dark:bg-slate-700 rounded-xl">
                <div class="flex flex-wrap gap-2 mb-3">
                  <template x-for="platform in selectedQueue.platforms" :key="platform">
                    <span 
                      class="px-2.5 py-1 rounded-full text-xs font-medium"
                      :class="{
                        'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400': platform === 'youtube',
                        'bg-cyan-100 text-cyan-700 dark:bg-cyan-900/30 dark:text-cyan-400': platform === 'tiktok',
                        'bg-pink-100 text-pink-700 dark:bg-pink-900/30 dark:text-pink-400': platform === 'instagram',
                        'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400': platform === 'facebook'
                      }"
                    >
                      <span x-text="platform === 'youtube' ? '📺 YouTube' : platform === 'tiktok' ? '🎵 TikTok' : platform === 'instagram' ? '📸 Instagram' : '📘 Facebook'"></span>
                    </span>
                  </template>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-400" x-text="getScheduleText(selectedQueue)"></p>
              </div>
              
              <!-- Videos List -->
              <h4 class="font-semibold text-gray-800 dark:text-gray-200 mb-3">Kuyruktaki Videolar (<span x-text="selectedQueue.videos?.length || 0"></span>)</h4>
              
              <template x-if="!selectedQueue.videos || selectedQueue.videos.length === 0">
                <div class="text-center py-8 bg-gray-50 dark:bg-slate-700 rounded-xl">
                  <p class="text-gray-500 dark:text-gray-400">Bu kuyrukta henüz video yok.</p>
                  <a href="dashboard.php" class="inline-block mt-3 text-indigo-600 dark:text-indigo-400 hover:underline">Videolar sayfasından ekleyin →</a>
                </div>
              </template>
              
              <template x-if="selectedQueue.videos && selectedQueue.videos.length > 0">
                <div class="space-y-3">
                  <template x-for="(video, idx) in selectedQueue.videos" :key="video.job_id">
                    <div class="flex items-center gap-4 p-4 bg-gray-50 dark:bg-slate-700 rounded-xl">
                      <div class="flex-shrink-0 w-8 h-8 bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 rounded-full flex items-center justify-center font-bold text-sm" x-text="idx + 1"></div>
                      <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-800 dark:text-gray-200 truncate" x-text="video.title || 'Video'"></p>
                        <div class="flex flex-wrap gap-1.5 mt-1">
                          <template x-for="(status, platform) in video.platform_status" :key="platform">
                            <span 
                              class="px-1.5 py-0.5 rounded text-xs font-medium"
                              :class="{
                                'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400': getPlatformStatus(video, platform) === 'success',
                                'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400': getPlatformStatus(video, platform) === 'pending',
                                'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400': getPlatformStatus(video, platform) === 'processing',
                                'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400': getPlatformStatus(video, platform) === 'failed'
                              }"
                            >
                              <span x-text="platform === 'youtube' ? '📺' : platform === 'tiktok' ? '🎵' : platform === 'instagram' ? '📸' : '📘'"></span>
                              <span x-text="getPlatformStatus(video, platform) === 'success' ? '✓' : getPlatformStatus(video, platform) === 'failed' ? '✗' : getPlatformStatus(video, platform) === 'processing' ? '↻' : '⏳'"></span>
                            </span>
                          </template>
                        </div>
                      </div>
                      <button 
                        @click="removeFromQueue(video.job_id)"
                        class="p-2 text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition"
                        title="Kuyruktan Çıkar"
                      >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                      </button>
                    </div>
                  </template>
                </div>
              </template>
            </div>
          </template>
        </div>
      </div>
    </div>
  </template>

  <!-- Move Video Modal -->
  <template x-if="moveModal">
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60" @click.self="moveModal = false">
      <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden">
        <div class="bg-gradient-to-r from-indigo-600 to-purple-600 px-6 py-4">
          <div class="flex items-center justify-between">
            <h3 class="text-xl font-bold text-white">📦 Videoyu Taşı</h3>
            <button @click="moveModal = false" class="text-white/80 hover:text-white">
              <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
          </div>
        </div>
        <div class="p-6">
          <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            "<span class="font-medium text-gray-800 dark:text-gray-200" x-text="selectedVideo?.title"></span>" videosunu taşımak için hedef kuyruğu seçin:
          </p>
          <div class="space-y-2 max-h-60 overflow-y-auto">
            <template x-for="queue in queues.filter(q => q.id !== selectedQueue?.id)" :key="queue.id">
              <button 
                @click="moveVideoToQueue(queue.id)"
                class="w-full p-4 text-left rounded-xl border-2 border-gray-200 dark:border-slate-600 hover:border-indigo-500 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition"
              >
                <div class="flex items-center justify-between">
                  <div>
                    <p class="font-medium text-gray-800 dark:text-gray-200" x-text="queue.name"></p>
                    <div class="flex items-center gap-1 mt-1">
                      <template x-for="p in queue.platforms" :key="p">
                        <span class="text-sm" x-text="p === 'youtube' ? '📺' : p === 'tiktok' ? '🎵' : p === 'instagram' ? '📸' : '📘'"></span>
                      </template>
                    </div>
                  </div>
                  <span class="text-xs text-gray-400 dark:text-gray-500" x-text="(queue.videos?.length || 0) + ' video'"></span>
                </div>
              </button>
            </template>
          </div>
          <button 
            @click="moveModal = false" 
            class="w-full mt-4 px-4 py-2.5 bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 text-gray-700 dark:text-gray-300 rounded-lg font-medium transition"
          >
            İptal
          </button>
        </div>
      </div>
    </div>
  </template>

  <!-- Queue Settings Modal - Minimalist Design -->
  <template x-if="queueSettingsModal">
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" @click.self="closeModals()">
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-xl w-full max-w-lg overflow-hidden max-h-[95vh] flex flex-col">
        
        <!-- Minimal Header -->
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-200 dark:border-slate-700">
          <div class="flex items-center gap-2">
            <span class="text-xl">⚙️</span>
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white">Kuyruk Ayarları</h3>
          </div>
          <button @click="closeModals()" class="p-1.5 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 transition">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
          </button>
        </div>
        
        <!-- Content - Scrollable -->
        <div class="flex-1 overflow-y-auto px-5 py-4 space-y-4">
          
          <!-- Queue Name & Video Settings -->
          <div class="space-y-3">
            <div>
              <label class="flex items-center gap-1.5 text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">
                <span>📝</span>
                <span>Kuyruk Adı</span>
              </label>
              <input 
                type="text" 
                x-model="form.name"
                placeholder="Örn: Haber Kuyruğu"
                class="w-full px-3 py-2.5 text-sm border border-gray-200 dark:border-slate-600 dark:bg-slate-900 dark:text-white rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition"
              >
            </div>
            
            <div>
              <label class="flex items-center gap-1.5 text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">
                <span>🎬</span>
                <span>Video Boyutu</span>
              </label>
              <select 
                x-model="form.dimensionPreset"
                class="w-full px-3 py-2 text-sm border border-gray-200 dark:border-slate-600 dark:bg-slate-900 dark:text-white rounded-lg focus:ring-2 focus:ring-indigo-500"
              >
                <option value="vertical">📱 Dikey (9:16) - Shorts/Reels</option>
                <option value="square">⬛ Kare (1:1) - Instagram</option>
                <option value="horizontal">🖥️ Yatay (16:9) - YouTube</option>
              </select>
            </div>
          </div>

          <!-- Platform Tabs -->
          <div class="pt-3 border-t border-gray-200 dark:border-slate-700">
            <label class="flex items-center gap-1.5 text-xs font-semibold text-gray-600 dark:text-gray-400 mb-3">
              <span>🌐</span>
              <span>Platform Ayarları</span>
            </label>
            
            <!-- Platform Tab Buttons -->
            <div class="flex gap-1 mb-4 overflow-x-auto pb-1">
              <button 
                type="button"
                @click="activePlatformTab = 'youtube'"
                class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold transition whitespace-nowrap"
                :class="activePlatformTab === 'youtube' 
                  ? 'bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300 ring-2 ring-red-500' 
                  : 'bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-slate-600'"
              >
                <span>📺</span>
                <span>YouTube</span>
                <span x-show="!form.platformSettings.youtube.enabled" class="text-[10px] opacity-60">●</span>
                <span x-show="getPlatformFailedCount('youtube') > 0" class="ml-1 px-1.5 py-0.5 text-[10px] bg-red-500 text-white rounded-full" x-text="getPlatformFailedCount('youtube')"></span>
              </button>
              <button 
                type="button"
                @click="activePlatformTab = 'instagram'"
                class="flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-semibold transition whitespace-nowrap"
                :class="activePlatformTab === 'instagram' 
                  ? 'bg-pink-100 dark:bg-pink-900/30 text-pink-700 dark:text-pink-300 ring-2 ring-pink-500' 
                  : 'bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-slate-600'"
              >
                <span>📸</span>
                <span>Instagram</span>
                <span x-show="!form.platformSettings.instagram.enabled" class="text-[10px] opacity-60">●</span>
                <span x-show="getPlatformFailedCount('instagram') > 0" class="ml-1 px-1.5 py-0.5 text-[10px] bg-red-500 text-white rounded-full" x-text="getPlatformFailedCount('instagram')"></span>
              </button>
              <!-- TikTok ve Facebook tabs şimdilik devre dışı -->
            </div>

            <!-- YouTube Tab Content -->
            <div x-show="activePlatformTab === 'youtube'" class="space-y-3 p-3 bg-red-50/30 dark:bg-red-900/10 rounded-lg border border-red-100 dark:border-red-900/30">
              <div class="flex items-center justify-between">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Platform Durumu</label>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" x-model="form.platformSettings.youtube.enabled" class="sr-only peer">
                  <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-red-300 dark:peer-focus:ring-red-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-red-600"></div>
                  <span class="ml-2 text-xs font-medium" :class="form.platformSettings.youtube.enabled ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'" x-text="form.platformSettings.youtube.enabled ? 'Aktif' : 'Pasif'"></span>
                </label>
              </div>

              <template x-if="form.platformSettings.youtube.enabled">
                <div class="space-y-3 pt-2 border-t border-red-200 dark:border-red-900/50">
                  <!-- Hedef Kanal -->
                  <div>
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5 block">🔗 Hedef Kanal <span class="text-red-500">*</span></label>
                    <select x-model="form.platformSettings.youtube.channelId" 
                            x-effect="$el.innerHTML = '<option value=\'\' disabled>-- Kanal Seçin --</option>' + youtubeChannels.map(ch => `<option value='${ch.id}' ${ch.id === form.platformSettings.youtube.channelId ? 'selected' : ''}>${ch.channel_title}</option>`).join('')"
                            class="w-full px-2.5 py-1.5 text-xs border border-gray-200 dark:border-slate-600 dark:bg-slate-900 dark:text-white rounded-lg" required>
                      <option value="" disabled>-- Kanal Seçin --</option>
                    </select>
                    <p class="text-[10px] text-red-500 dark:text-red-400 mt-0.5" x-show="!form.platformSettings.youtube.channelId">⚠️ Kanal seçimi zorunludur</p>
                  </div>
                  <!-- Zamanlama -->
                  <div>
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5 block">⏰ Zamanlama</label>
                    <select x-model="form.platformSettings.youtube.scheduleType" class="w-full px-2.5 py-1.5 text-xs border border-gray-200 dark:border-slate-600 dark:bg-slate-900 dark:text-white rounded-lg">
                      <option value="now">Hemen</option>
                      <option value="interval">Aralıklı</option>
                      <option value="specific">Belirli Saatler</option>
                    </select>
                  </div>
                  
                  <template x-if="form.platformSettings.youtube.scheduleType === 'interval'">
                    <div class="space-y-2">
                      <!-- Start Time -->
                      <div>
                        <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1 block">📅 İlk Paylaşım Saati</label>
                        <input 
                          type="time"
                          x-model="form.platformSettings.youtube.startTime"
                          class="w-full px-2.5 py-1.5 text-xs border border-gray-200 dark:border-slate-600 dark:bg-slate-900 dark:text-white rounded-lg"
                          placeholder="09:00"
                        />
                        <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">Boş = hemen başlar</p>
                      </div>
                      
                      <!-- Interval Minutes -->
                      <div>
                        <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1 block">⏱️ Aralık (Dakika)</label>
                        <div class="grid grid-cols-2 gap-1.5">
                          <input 
                            type="number"
                            x-model="form.platformSettings.youtube.intervalMinutes"
                            min="1"
                            class="w-full px-2.5 py-1.5 text-xs border border-gray-200 dark:border-slate-600 dark:bg-slate-900 dark:text-white rounded-lg"
                            placeholder="120"
                          />
                          <select x-model="form.platformSettings.youtube.intervalMinutes" class="w-full px-2.5 py-1.5 text-xs border border-gray-200 dark:border-slate-600 dark:bg-slate-900 dark:text-white rounded-lg">
                            <option value="30">30 dk</option>
                            <option value="60">1 saat</option>
                            <option value="90">1.5 saat</option>
                            <option value="120">2 saat</option>
                            <option value="180">3 saat</option>
                            <option value="240">4 saat</option>
                            <option value="360">6 saat</option>
                            <option value="720">12 saat</option>
                          </select>
                        </div>
                        <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">
                          = <span x-text="(form.platformSettings.youtube.intervalMinutes/60).toFixed(1)"></span> saat
                        </p>
                      </div>
                      
                      <!-- Daily Limit -->
                      <div>
                        <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1 block">📊 Günlük Limit</label>
                        <div class="grid grid-cols-2 gap-1.5">
                          <input 
                            type="number"
                            x-model="form.platformSettings.youtube.dailyLimit"
                            min="0"
                            class="w-full px-2.5 py-1.5 text-xs border border-gray-200 dark:border-slate-600 dark:bg-slate-900 dark:text-white rounded-lg"
                            placeholder="0"
                          />
                          <select x-model="form.platformSettings.youtube.dailyLimit" class="w-full px-2.5 py-1.5 text-xs border border-gray-200 dark:border-slate-600 dark:bg-slate-900 dark:text-white rounded-lg">
                            <option value="0">Limitsiz</option>
                            <option value="3">3 video/gün</option>
                            <option value="4">4 video/gün</option>
                            <option value="5">5 video/gün</option>
                            <option value="6">6 video/gün</option>
                            <option value="8">8 video/gün</option>
                            <option value="10">10 video/gün</option>
                          </select>
                        </div>
                        <p class="text-[10px] text-gray-500 dark:text-gray-400 mt-0.5">
                          <span x-show="form.platformSettings.youtube.dailyLimit == 0">Tüm videolar paylaşılır</span>
                          <span x-show="form.platformSettings.youtube.dailyLimit > 0">Max <span x-text="form.platformSettings.youtube.dailyLimit"></span>/gün</span>
                        </p>
                      </div>
                    </div>
                  </template>

                  <!-- Visibility -->
                  <div>
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1 block">👁️ Görünürlük</label>
                    <select x-model="form.platformSettings.youtube.privacy" class="w-full px-2.5 py-1.5 text-xs border border-gray-200 dark:border-slate-600 dark:bg-slate-900 dark:text-white rounded-lg">
                      <option value="public">Herkese Açık</option>
                      <option value="unlisted">Liste Dışı</option>
                      <option value="private">Gizli</option>
                    </select>
                  </div>

                  <!-- Playlist ID -->
                  <div>
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1 block">📂 Playlist ID (Opsiyonel)</label>
                    <input type="text" x-model="form.platformSettings.youtube.playlistId" placeholder="PLxxxxxx" class="w-full px-2.5 py-1.5 text-xs border border-gray-200 dark:border-slate-600 dark:bg-slate-900 dark:text-white rounded-lg">
                  </div>
                  
                  <!-- Platform Stats -->
                  <div class="pt-2 border-t border-red-200 dark:border-red-900/50">
                    <div class="flex items-center gap-3 text-xs">
                      <span class="text-green-600 dark:text-green-400">✓ <span x-text="getPlatformPublishedCount('youtube')"></span> yayında</span>
                      <span class="text-yellow-600 dark:text-yellow-400">⏳ <span x-text="getPlatformPendingCount('youtube')"></span> bekliyor</span>
                      <span class="text-red-600 dark:text-red-400" x-show="getPlatformFailedCount('youtube') > 0">✕ <span x-text="getPlatformFailedCount('youtube')"></span> hata</span>
                    </div>
                  </div>
                  
                  <!-- Error List -->
                  <template x-if="getPlatformFailedCount('youtube') > 0">
                    <div class="mt-2 p-2 bg-red-100 dark:bg-red-900/30 rounded-lg">
                      <div class="text-xs font-semibold text-red-700 dark:text-red-300 mb-1">❌ Hatalar</div>
                      <div class="space-y-1 max-h-24 overflow-y-auto">
                        <template x-for="err in getQueuePlatformErrors('youtube').slice(0,5)" :key="err.job_id">
                          <div class="text-[11px] text-red-600 dark:text-red-400 truncate" :title="err.error">
                            • <span x-text="err.title?.substring(0,30) || err.job_id"></span>: <span x-text="err.error.substring(0,50)"></span>
                          </div>
                        </template>
                      </div>
                    </div>
                  </template>
                </div>
              </template>
            </div>

            <!-- Instagram Tab Content -->
            <div x-show="activePlatformTab === 'instagram'" class="space-y-3 p-3 bg-pink-50/30 dark:bg-pink-900/10 rounded-lg border border-pink-100 dark:border-pink-900/30">
              <div class="flex items-center justify-between">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Platform Durumu</label>
                <label class="relative inline-flex items-center cursor-pointer">
                  <input type="checkbox" x-model="form.platformSettings.instagram.enabled" class="sr-only peer">
                  <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-pink-300 dark:peer-focus:ring-pink-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-pink-600"></div>
                  <span class="ml-2 text-xs font-medium" :class="form.platformSettings.instagram.enabled ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400'" x-text="form.platformSettings.instagram.enabled ? 'Aktif' : 'Pasif'"></span>
                </label>
              </div>

              <template x-if="form.platformSettings.instagram.enabled">
                <div class="space-y-3 pt-2 border-t border-pink-200 dark:border-pink-900/50">
                  <div>
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1.5 block">⏰ Zamanlama</label>
                    <select x-model="form.platformSettings.instagram.scheduleType" class="w-full px-2.5 py-1.5 text-xs border border-gray-200 dark:border-slate-600 dark:bg-slate-900 dark:text-white rounded-lg">
                      <option value="now">Hemen</option>
                      <option value="interval">Aralıklı</option>
                      <option value="specific">Belirli Saatler</option>
                    </select>
                  </div>
                  
                  <div>
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1 block">#️⃣ Hashtags</label>
                    <input type="text" x-model="form.platformSettings.instagram.hashtags" placeholder="#reels,#haber" class="w-full px-2.5 py-1.5 text-xs border border-gray-200 dark:border-slate-600 dark:bg-slate-900 dark:text-white rounded-lg">
                  </div>
                  
                  <div>
                    <label class="text-xs font-medium text-gray-600 dark:text-gray-400 mb-1 block">✏️ Caption Şablonu</label>
                    <textarea x-model="form.platformSettings.instagram.captionTemplate" rows="2" placeholder="{title}\n\n{description}" class="w-full px-2.5 py-1.5 text-xs border border-gray-200 dark:border-slate-600 dark:bg-slate-900 dark:text-white rounded-lg font-mono"></textarea>
                  </div>
                  
                  <!-- Platform Stats -->
                  <div class="pt-2 border-t border-pink-200 dark:border-pink-900/50">
                    <div class="flex items-center gap-3 text-xs">
                      <span class="text-green-600 dark:text-green-400">✓ <span x-text="getPlatformPublishedCount('instagram')"></span> yayında</span>
                      <span class="text-yellow-600 dark:text-yellow-400">⏳ <span x-text="getPlatformPendingCount('instagram')"></span> bekliyor</span>
                      <span class="text-red-600 dark:text-red-400" x-show="getPlatformFailedCount('instagram') > 0">✕ <span x-text="getPlatformFailedCount('instagram')"></span> hata</span>
                    </div>
                  </div>
                  
                  <!-- Error List -->
                  <template x-if="getPlatformFailedCount('instagram') > 0">
                    <div class="mt-2 p-2 bg-red-100 dark:bg-red-900/30 rounded-lg">
                      <div class="text-xs font-semibold text-red-700 dark:text-red-300 mb-1">❌ Hatalar</div>
                      <div class="space-y-1 max-h-24 overflow-y-auto">
                        <template x-for="err in getQueuePlatformErrors('instagram').slice(0,5)" :key="err.job_id">
                          <div class="text-[11px] text-red-600 dark:text-red-400 truncate" :title="err.error">
                            • <span x-text="err.title?.substring(0,30) || err.job_id"></span>: <span x-text="err.error.substring(0,50)"></span>
                          </div>
                        </template>
                      </div>
                    </div>
                  </template>
                </div>
              </template>
            </div>

            <!-- TikTok ve Facebook Tab Content - Şimdilik devre dışı -->

          </div>
          
        </div>
        
        <!-- Footer Actions - Sticky -->
        <div class="flex items-center gap-2 px-5 py-3 border-t border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-900">
          <button 
            @click="deleteQueue(selectedQueue)" 
            class="px-3 py-2 text-sm font-medium bg-white dark:bg-slate-800 border border-red-200 dark:border-red-900/50 hover:bg-red-50 dark:hover:bg-red-900/20 text-red-600 dark:text-red-400 rounded-lg transition flex items-center gap-1.5"
            title="Kuyruğu Sil"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            <span class="hidden sm:inline">Sil</span>
          </button>
          <div class="flex-1"></div>
          <button 
            @click="closeModals()" 
            class="px-4 py-2 text-sm font-medium bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-600 hover:bg-gray-100 dark:hover:bg-slate-700 text-gray-700 dark:text-gray-300 rounded-lg transition"
          >
            İptal
          </button>
          <button 
            @click="saveQueueSettings()" 
            :disabled="submitting"
            class="px-4 py-2 text-sm font-semibold bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1.5"
          >
            <svg x-show="!submitting" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <svg x-show="submitting" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
            <span x-text="submitting ? 'Kaydediliyor...' : 'Kaydet'"></span>
          </button>
        </div>
        
      </div>
    </div>
  </template>
</body>
</html>
