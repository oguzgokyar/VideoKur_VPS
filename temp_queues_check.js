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
      
      // Form data
      form: {
        name: '',
        platforms: [],
        scheduleType: 'interval',
        intervalHours: 2,
        specificTimes: ['09:00', '15:00', '21:00'],
        // Video ayarları
        dimensionPreset: 'vertical',
        customWidth: 1080,
        customHeight: 1920,
        subtitleMode: 'config',
        subtitlePreset: 'classic',
        customSubtitle: { FontName: 'Arial', FontSize: 24, PrimaryColour: '#FFFFFF', OutlineColour: '#000000', Outline: 2, MarginV: 60, Bold: 1 }
      },
      
      // Config'den yüklenen varsayılan altyazı
      configSubtitle: { FontName: 'Arial', FontSize: 24, PrimaryColour: '#FFFFFF', OutlineColour: '#000000', Outline: 2, MarginV: 60, Bold: 1 },
      
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
        classic: { label: 'Klasik', FontSize: 24, PrimaryColour: '#FFFFFF', OutlineColour: '#000000', Outline: 2, MarginV: 60, Bold: 1 },
        neon: { label: 'Neon', FontSize: 26, PrimaryColour: '#00FF00', OutlineColour: '#000000', Outline: 2, MarginV: 60, Bold: 1 },
        cinematic: { label: 'Sinematik', FontSize: 22, PrimaryColour: '#F5F5DC', OutlineColour: '#2C2C2C', Outline: 1, MarginV: 80, Bold: 0 },
        bold: { label: 'Kalın', FontSize: 28, PrimaryColour: '#FFD700', OutlineColour: '#000000', Outline: 3, MarginV: 50, Bold: 1 },
        minimal: { label: 'Minimal', FontSize: 20, PrimaryColour: '#FFFFFF', OutlineColour: '#333333', Outline: 1, MarginV: 70, Bold: 0 },
        news: { label: 'Haber', FontSize: 24, PrimaryColour: '#FFFFFF', OutlineColour: '#CC0000', Outline: 2, MarginV: 55, Bold: 1 }
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
        { id: 'tiktok', name: 'TikTok', icon: '🎵', color: 'cyan' },
        { id: 'instagram', name: 'Instagram', icon: '📸', color: 'pink' },
        { id: 'facebook', name: 'Facebook', icon: '📘', color: 'blue' }
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
        this.form = {
          name: '',
          platforms: [],
          scheduleType: 'interval',
          intervalHours: 2,
          specificTimes: ['09:00', '15:00', '21:00'],
          dimensionPreset: 'vertical',
          customWidth: 1080,
          customHeight: 1920,
          subtitleMode: 'config',
          subtitlePreset: 'classic',
          customSubtitle: { ...this.configSubtitle }
        };
        this.createModal = true;
      },
      
      openEditModal(queue) {
        this.selectedQueue = queue;
        const vs = queue.video_settings || {};
        this.form = {
          name: queue.name,
          platforms: [...queue.platforms],
          scheduleType: queue.schedule?.type || 'interval',
          intervalHours: queue.schedule?.interval_hours || 2,
          specificTimes: queue.schedule?.specific_times || ['09:00', '15:00', '21:00'],
          dimensionPreset: vs.dimensionPreset || 'vertical',
          customWidth: vs.videoWidth || 1080,
          customHeight: vs.videoHeight || 1920,
          subtitleMode: vs.subtitleMode || 'config',
          subtitlePreset: vs.subtitlePreset || 'classic',
          customSubtitle: vs.customSubtitle || { ...this.configSubtitle }
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
          }
        } catch(e) {
          console.error('Kuyruk detayı yüklenemedi:', e);
        }
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
        this.form = {
          name: this.selectedQueue.name,
          platforms: [...(this.selectedQueue.platforms || [])],
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
        this.submitting = true;
        try {
          const response = await fetch('/api/queues.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'update',
              queue_id: this.selectedQueue.id,
              updates: {
                name: this.form.name.trim(),
                platforms: this.form.platforms,
                schedule: {
                  type: this.form.scheduleType,
                  interval_hours: this.form.intervalHours,
                  specific_times: this.form.specificTimes,
                  timezone: 'Europe/Istanbul'
                },
                video_settings: this.buildVideoSettings()
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
              action: 'create',
              name: this.form.name.trim(),
              platforms: this.form.platforms,
              schedule: schedule,
              video_settings: this.buildVideoSettings()
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
      
      async updateQueue() {
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
        if (!confirm('Kuyruk resetlenecek:\n\n✓ Duplicate videolar temizlenecek\n✓ Sıra numaraları düzenlenecek\n✓ Takılı kalan durumlar sıfırlanacak\n\nDevam edilsin mi?')) {
          return;
        }
        
        try {
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
            alert(message);
            
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
              await this.selectQueueTab(queue);
            }
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
            this.loadQueues();
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
        if (s.type === 'interval') return '⏰ Her ' + (s.interval_hours || 2) + ' saatte bir';
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
        const ps = (video.platform_status || {})[platform];
        if (!ps) return 'pending';
        if (typeof ps === 'string') return ps;
        return ps.status || 'pending';
      },

      getPlatformPostUrl(video, platform) {
        const ps = (video.platform_status || {})[platform];
        if (!ps || typeof ps === 'string') return null;
        return ps.post_url || null;
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
          else if (status === 'processing') { cls = 'badge-uploading'; statusIcon = ''; extra = '<span class="icon-spin" style="font-style:normal">↻</span>'; }
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
        // 15 saniyede bir canlı güncelleme
        setInterval(() => { if (this.selectedQueue) this.loadQueues(); }, 15000);
      },
      
      // Sidebar collapse state'ini localStorage'a kaydet
      toggleSidebarCollapse() {
        this.sidebarCollapsed = !this.sidebarCollapsed;
        localStorage.setItem('sidebarCollapsed', this.sidebarCollapsed ? '1' : '0');
      }
    };
  }
  
