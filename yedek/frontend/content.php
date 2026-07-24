<?php $page_title = 'İçerikler - Video Otomasyon'; $active_page = 'content'; ?>
<!DOCTYPE html>
<html lang="tr" x-data="{ darkMode: localStorage.getItem('darkMode') === '1' }" :class="{ 'dark': darkMode }">
<head>
  <?php include __DIR__ . '/components/_head.php'; ?>
  <style>
    [x-cloak] { display: none !important; }
    @keyframes fade-in { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
    .anim-fade-in { animation: fade-in .3s ease-out both; }
    .content-item { transition: all 0.2s ease; }
    .content-item:hover { background: rgba(59, 130, 246, 0.08); transform: translateX(4px); }
    .content-item.selected { background: rgba(59, 130, 246, 0.15); border-color: #3b82f6 !important; }
    .queue-item { transition: all 0.2s ease; }
    .queue-item:hover { background: rgba(239, 68, 68, 0.08); }
    .score-badge { font-weight: 600; }
    .drag-over { background: rgba(59, 130, 246, 0.2); border: 2px dashed #3b82f6 !important; }
  </style>
  <script>
  function contentApp() {
    return {
      sidebarOpen: false, sidebarCollapsed: localStorage.getItem('sidebarCollapsed') === '1',
      darkMode: false,
      loading: true,
      
      // İçerik verileri
      content: [],
      sources: [],
      stats: {},
      
      // Kuyruk verileri
      queues: [],
      activeQueueTab: null,
      
      // Seçim
      selectedItems: [],
      
      // Modals
      addUrlModal: false,
      manageSourcesModal: false,
      fetchModal: false,
      processing: false,
      
      // Forms
      urlForm: { url: '', title: '' },
      sourceForm: { name: '', url: '', category: 'genel', keywords: '', enabled: true },
      fetchForm: { source_id: 'all', limit: 20 },
      
      async init() {
        this.darkMode = localStorage.getItem('darkMode') === '1';
        try {
          await Promise.all([
            this.loadContent(),
            this.loadSources(),
            this.loadStats(),
            this.loadQueues()
          ]);
          // İlk kuyruğu seç
          if (this.queues.length > 0) {
            this.activeQueueTab = this.queues[0].id;
          }
        } catch (err) {
          console.error('Init error:', err);
        } finally {
          this.loading = false;
        }
        
        // Auto-refresh
        setInterval(() => {
          this.loadContent();
          this.loadQueues();
        }, 30000);
      },
      
      async loadContent() {
        try {
          const resp = await fetch('/api/content.php?list=1&sort=date');
          const data = await resp.json();
          if (data.success) {
            this.content = data.content || [];
          }
        } catch (err) {
          console.error('Content load error:', err);
        }
      },
      
      async loadSources() {
        try {
          const resp = await fetch('/api/content_sources.php');
          const data = await resp.json();
          if (data.success) {
            this.sources = data.sources || [];
          }
        } catch (err) {
          console.error('Sources load error:', err);
        }
      },
      
      async loadStats() {
        try {
          const resp = await fetch('/api/content.php');
          const data = await resp.json();
          if (data.success) {
            this.stats = data.stats || {};
          }
        } catch (err) {
          console.error('Stats load error:', err);
        }
      },
      
      async loadQueues() {
        try {
          const resp = await fetch('/api/queues.php?action=list');
          const data = await resp.json();
          if (data.success) {
            this.queues = data.queues || [];
            // Aktif tab yoksa veya artık yoksa ilk kuyruğu seç
            if (this.queues.length > 0 && !this.queues.some(q => q.id === this.activeQueueTab)) {
              this.activeQueueTab = this.queues[0].id;
            } else if (this.queues.length === 0) {
              this.activeQueueTab = null;
            }
          }
        } catch (err) {
          console.error('Queues load error:', err);
        }
      },
      
      get filteredContent() {
        return [...this.content];
      },
      
      get activeQueue() {
        return this.queues.find(q => q.id === this.activeQueueTab);
      },
      
      normalizePlatformStatus(rawStatus) {
        const s = typeof rawStatus === 'string' ? rawStatus : (rawStatus?.status || 'pending');
        const normalized = (s || 'pending').toLowerCase();
        if (normalized === 'published' || normalized === 'uploaded' || normalized === 'success') return 'success';
        if (normalized === 'queued') return 'pending';
        if (normalized === 'uploading') return 'processing';
        return normalized;
      },

      getVideoPlatformStatuses(video) {
        const ps = video?.platform_status || {};
        return Object.keys(ps).map(platform => this.normalizePlatformStatus(ps[platform]));
      },

      isVideoFullyPublished(video) {
        if (!video) return false;
        if ((video.status || '').toLowerCase() === 'published') return true;
        const statuses = this.getVideoPlatformStatuses(video);
        return statuses.length > 0 && statuses.every(s => s === 'success');
      },

      getVisibleQueueVideos(queue) {
        if (!queue) return [];
        const videos = queue.videos || [];
        return videos.filter(video => !this.isVideoFullyPublished(video));
      },

      getQueueTabCount(queue) {
        return this.getVisibleQueueVideos(queue).length;
      },

      get activeQueueVideos() {
        return this.getVisibleQueueVideos(this.activeQueue);
      },
      
      toggleSelect(contentId) {
        const idx = this.selectedItems.indexOf(contentId);
        if (idx > -1) {
          this.selectedItems.splice(idx, 1);
        } else {
          this.selectedItems.push(contentId);
        }
      },
      
      isSelected(contentId) {
        return this.selectedItems.includes(contentId);
      },
      
      toggleSelectAll() {
        if (this.selectedItems.length === this.filteredContent.length) {
          // Tümü seçiliyse, hepsini kaldır
          this.selectedItems = [];
        } else {
          // Tümünü seç
          this.selectedItems = this.filteredContent.map(c => c.id);
        }
      },
      
      // Seçili içerikleri kuyruğa ekle
      async addToQueue() {
        if (this.selectedItems.length === 0) {
          alert('⚠️ Lütfen en az bir içerik seçin');
          return;
        }
        
        if (!this.activeQueueTab) {
          alert('⚠️ Lütfen bir kuyruk seçin');
          return;
        }
        
        const selectedContents = this.content.filter(c => this.selectedItems.includes(c.id));
        
        this.processing = true;
        
        try {
          // Her içerik için video job oluştur ve kuyruğa ekle
          for (const item of selectedContents) {
            // Önce video job oluştur (content'i pipeline'a gönder)
            const jobResp = await fetch('/api/content.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                action: 'create_job',
                content_id: item.id,
                queue_id: this.activeQueueTab
              })
            });
            
            const jobData = await jobResp.json();
            
            if (jobData.success && jobData.job_id) {
              // Kuyruğa video ekle
              await fetch('/api/queues.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                  action: 'add_video',
                  queue_id: this.activeQueueTab,
                  job_id: jobData.job_id
                })
              });
            }
          }
          
          this.selectedItems = [];
          await Promise.all([this.loadContent(), this.loadQueues()]);
          
        } catch (err) {
          console.error('Add to queue error:', err);
          alert('❌ Kuyruğa ekleme hatası');
        } finally {
          this.processing = false;
        }
      },
      
      // Kuyruktan video kaldır
      async removeFromQueue(videoId) {
        if (!confirm('Bu videoyu kuyruktan kaldırmak istediğinizden emin misiniz?')) return;
        
        try {
          await fetch('/api/queues.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'remove_video',
              queue_id: this.activeQueueTab,
              video_id: videoId,
              job_id: videoId
            })
          });
          await this.loadQueues();
        } catch (err) {
          console.error('Remove from queue error:', err);
        }
      },
      
      openAddUrlModal() {
        this.urlForm = { url: '', title: '' };
        this.addUrlModal = true;
      },
      
      async submitUrl() {
        if (!this.urlForm.url) {
          alert('⚠️ URL gerekli');
          return;
        }
        
        this.processing = true;
        
        try {
          const resp = await fetch('/api/content.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'add',
              url: this.urlForm.url,
              title: this.urlForm.title
            })
          });
          
          const data = await resp.json();
          
          if (data.success) {
            alert('✅ URL eklendi!');
            this.addUrlModal = false;
            await this.loadContent();
          } else {
            alert('❌ Hata: ' + data.error);
          }
        } catch (err) {
          alert('❌ Network hatası');
        } finally {
          this.processing = false;
        }
      },
      
      async deleteContent(contentId) {
        if (!confirm('Bu içeriği silmek istediğinizden emin misiniz?')) return;
        
        try {
          await fetch('/api/content.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: contentId })
          });
          await this.loadContent();
        } catch (err) {
          console.error('Delete error:', err);
        }
      },
      
      openManageSourcesModal() {
        this.sourceForm = { name: '', url: '', category: 'genel', keywords: '', enabled: true };
        this.manageSourcesModal = true;
      },
      
      async addSource() {
        if (!this.sourceForm.name || !this.sourceForm.url) {
          alert('⚠️ Kaynak adı ve URL gerekli');
          return;
        }
        
        this.processing = true;
        
        try {
          const keywords = this.sourceForm.keywords
            .split(',')
            .map(k => k.trim())
            .filter(k => k);
          
          const resp = await fetch('/api/content_sources.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              name: this.sourceForm.name,
              url: this.sourceForm.url,
              category: this.sourceForm.category,
              keywords: keywords,
              enabled: this.sourceForm.enabled
            })
          });
          
          const data = await resp.json();
          
          if (data.success) {
            this.sourceForm = { name: '', url: '', category: 'genel', keywords: '', enabled: true };
            await this.loadSources();
          } else {
            alert('❌ Hata: ' + data.error);
          }
        } catch (err) {
          alert('❌ Network hatası');
        } finally {
          this.processing = false;
        }
      },
      
      async toggleSource(sourceId) {
        try {
          const source = this.sources.find(s => s.id === sourceId);
          await fetch('/api/content_sources.php', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              id: sourceId,
              enabled: !source.enabled
            })
          });
          await this.loadSources();
        } catch (err) {
          console.error('Toggle source error:', err);
        }
      },
      
      async deleteSource(sourceId) {
        if (!confirm('Bu kaynağı silmek istediğinizden emin misiniz?')) return;
        
        try {
          await fetch('/api/content_sources.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: sourceId })
          });
          await this.loadSources();
        } catch (err) {
          console.error('Delete source error:', err);
        }
      },
      
      async fetchFeeds() {
        this.processing = true;
        try {
          const resp = await fetch('/api/content_sources.php?action=fetch');
          const data = await resp.json();
          if (data.success) {
            alert(`✅ ${data.new_items || 0} yeni içerik eklendi`);
            await this.loadContent();
          }
        } catch (err) {
          alert('❌ Feed çekme hatası');
        } finally {
          this.processing = false;
        }
      },
      
      // Havuzu temizle
      async clearPool() {
        if (!confirm('⚠️ Tüm içerik havuzu silinecek! Emin misiniz?')) return;
        
        this.processing = true;
        try {
          const resp = await fetch('/api/content.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'clear_all' })
          });
          const data = await resp.json();
          if (data.success) {
            alert('✅ İçerik havuzu temizlendi');
            this.content = [];
            this.selectedItems = [];
            await this.loadStats();
          } else {
            alert('❌ Hata: ' + data.error);
          }
        } catch (err) {
          alert('❌ İşlem hatası');
        } finally {
          this.processing = false;
        }
      },
      
      // Araştır modal
      openFetchModal() {
        this.fetchForm = { source_id: 'all', limit: 20 };
        this.fetchModal = true;
      },
      
      // Seçili kaynaktan içerik çek
      async fetchFromSource() {
        this.processing = true;
        try {
          const params = new URLSearchParams({
            action: 'fetch',
            limit: this.fetchForm.limit
          });
          
          if (this.fetchForm.source_id !== 'all') {
            params.append('source_id', this.fetchForm.source_id);
          }
          
          const resp = await fetch('/api/content_sources.php?' + params.toString());
          const data = await resp.json();
          
          if (data.success) {
            this.fetchModal = false;
            alert(`✅ ${data.new_items || 0} yeni içerik eklendi`);
            await this.loadContent();
            await this.loadStats();
          } else {
            alert('❌ Hata: ' + data.error);
          }
        } catch (err) {
          alert('❌ Feed çekme hatası');
        } finally {
          this.processing = false;
        }
      },
      
      getStatusBadge(status) {
        const badges = {
          'pending': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
          'processing': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
          'completed': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
          'failed': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'
        };
        return badges[status] || badges['pending'];
      },
      
      getStatusText(status) {
        const texts = {
          'processing': 'İşleniyor',
          'completed': 'Tamamlandı',
          'failed': 'Başarısız'
        };
        return texts[status] || '';
      },
      
      // Kuyruk öğesi için durum metni (video üretilmiş mi?)
      getQueueVideoStatus(video) {
        const statuses = this.getVideoPlatformStatuses(video);
        const jobStatus = (video.job_status || video.status || 'pending').toLowerCase();
        const producingPhases = ['scraping', 'scripting', 'imaging', 'tts', 'subtitling', 'composing'];

        if (statuses.length > 0) {
          const successCount = statuses.filter(s => s === 'success').length;
          if (successCount === statuses.length) return 'Yayınlandı';
          if (statuses.includes('failed') && successCount > 0) return 'Kısmi Başarılı';
          if (statuses.includes('failed')) return 'Başarısız';
          if (statuses.includes('processing')) return 'Yükleniyor';
          if (statuses.includes('pending')) {
            if (jobStatus === 'done' || jobStatus === 'completed') return 'Paylaşım Bekliyor';
            if (producingPhases.includes(jobStatus)) return 'Üretiliyor';
            return 'Sırada';
          }
        }

        if (jobStatus === 'done' || jobStatus === 'completed') return 'Paylaşım Bekliyor';
        if (jobStatus === 'failed') return 'Başarısız';
        if (producingPhases.includes(jobStatus)) return 'Üretiliyor';
        return 'Sırada';
      },
      
      getQueueVideoStatusBadge(video) {
        const status = this.getQueueVideoStatus(video);
        const badges = {
          'Yayınlandı': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
          'Kısmi Başarılı': 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-300',
          'Yükleniyor': 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300',
          'Paylaşım Bekliyor': 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-300',
          'Üretiliyor': 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-300',
          'Sırada': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
          'Başarısız': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-300'
        };
        return badges[status] || badges['Sırada'];
      },
      
      formatDate(dateStr) {
        if (!dateStr) return '-';
        const date = new Date(dateStr);
        return date.toLocaleDateString('tr-TR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
      }
    };
  }
  </script>
</head>

<body class="bg-gray-50 dark:bg-slate-900 transition-colors" x-data="contentApp()" x-init="init()">
  <?php include __DIR__ . '/components/_dark_mode.php'; ?>
  
  <div class="min-h-screen flex flex-col">
    <?php include __DIR__ . '/components/_header.php'; ?>
    
    <div class="flex flex-1">
      <?php include __DIR__ . '/components/_sidebar.php'; ?>
      
      <!-- Main Content -->
      <main class="flex-1 p-4 lg:p-6 overflow-y-auto">
        <div class="max-w-8xl mx-auto">
          
          <!-- Loading -->
          <div x-show="loading" x-cloak class="text-center py-20">
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
            <p class="text-gray-600 dark:text-gray-400 mt-4">Yükleniyor...</p>
          </div>
          
          <!-- Two Column Layout -->
          <div x-show="!loading" x-cloak class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            
            <!-- LEFT: İçerik Havuzu -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden flex flex-col" style="max-height: calc(100vh - 100px);">
              
              <!-- Header -->
              <div class="p-4 border-b border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800/50">
                <div class="flex items-center justify-between mb-3">
                  <h2 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                    📥 İçerik Havuzu
                    <span class="text-sm font-normal text-gray-500">(<span x-text="filteredContent.length"></span>)</span>
                  </h2>
                  
                  <div class="flex gap-1">
                    <button @click="openFetchModal()" class="text-xs px-2 py-1 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 rounded hover:bg-green-200 dark:hover:bg-green-800 flex items-center gap-1">
                      🔍 Araştır
                    </button>
                    <button @click="clearPool()" :disabled="processing || content.length === 0" class="text-xs px-2 py-1 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 rounded hover:bg-red-200 dark:hover:bg-red-800 disabled:opacity-50">
                      🗑️ Temizle
                    </button>
                    <button @click="toggleSelectAll()" class="text-xs px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 rounded hover:bg-blue-200 dark:hover:bg-blue-800">
                      <span x-text="selectedItems.length === filteredContent.length && filteredContent.length > 0 ? 'Seçimi Kaldır' : 'Tümünü Seç'"></span>
                    </button>
                  </div>
                </div>
              </div>
              
              <!-- Content List -->
              <div class="flex-1 overflow-y-auto p-2 space-y-2">
                <template x-for="item in filteredContent" :key="item.id">
                  <div 
                    @click="toggleSelect(item.id)"
                    :class="{ 'selected': isSelected(item.id) }"
                    class="content-item p-3 rounded-lg border border-gray-200 dark:border-slate-700 anim-fade-in cursor-pointer">
                    
                    <div class="flex items-start gap-3">
                      <!-- Checkbox -->
                      <input type="checkbox" 
                        :checked="isSelected(item.id)"
                        @click.stop="toggleSelect(item.id)"
                        class="mt-1 w-4 h-4 text-blue-600 rounded focus:ring-2 focus:ring-blue-500">
                      
                      <!-- Content -->
                      <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-white line-clamp-2 mb-1" x-text="item.title"></h3>
                        
                        <div class="flex flex-wrap items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                          <span x-text="item.source"></span>
                          <span>•</span>
                          <span x-text="formatDate(item.discovered_at)"></span>
                        </div>
                      </div>
                      
                      <!-- Status (sadece processing, completed, failed göster) -->
                      <div class="flex flex-col items-end gap-1">
                        <span x-show="item.status && item.status !== 'pending'" :class="getStatusBadge(item.status)" class="px-2 py-0.5 rounded text-xs" x-text="getStatusText(item.status)"></span>
                      </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-2 mt-2 pt-2 border-t border-gray-100 dark:border-slate-700">
                      <a :href="item.url" target="_blank" class="text-xs text-blue-600 dark:text-blue-400 hover:underline">
                        Kaynağı Aç ↗
                      </a>
                      <button @click.stop="deleteContent(item.id)" class="text-xs text-red-600 dark:text-red-400 hover:underline">
                        Sil
                      </button>
                    </div>
                  </div>
                </template>
                
                <!-- Empty State -->
                <div x-show="filteredContent.length === 0" class="text-center py-12">
                  <span class="text-4xl">📭</span>
                  <p class="text-gray-500 dark:text-gray-400 mt-2 text-sm">İçerik bulunamadı</p>
                </div>
              </div>
              
              <!-- Bottom Action -->
              <div x-show="selectedItems.length > 0" class="p-3 border-t border-gray-200 dark:border-slate-700 bg-blue-50 dark:bg-blue-900/20">
                <div class="flex items-center justify-between">
                  <span class="text-sm text-blue-800 dark:text-blue-300 font-medium">
                    <span x-text="selectedItems.length"></span> içerik seçili
                  </span>
                  <button 
                    @click="addToQueue()" 
                    :disabled="processing || !activeQueueTab"
                    class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white rounded-lg text-sm font-medium transition flex items-center gap-2">
                    <span x-show="!processing">Kuyruğa Ekle →</span>
                    <span x-show="processing">Ekleniyor...</span>
                  </button>
                </div>
              </div>
            </div>
            
            <!-- RIGHT: Kuyruklar -->
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 overflow-hidden flex flex-col" style="max-height: calc(100vh - 100px);">
              
              <!-- Queue Tabs -->
              <div class="border-b border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800/50">
                <div class="flex items-center justify-between p-3">
                  <h2 class="text-lg font-semibold text-gray-900 dark:text-white">📤 Kuyruklar</h2>
                  <a href="/queues.php" class="text-xs text-blue-600 dark:text-blue-400 hover:underline">Yönet →</a>
                </div>
                
                <!-- Tabs -->
                <div class="flex overflow-x-auto px-2 pb-2 gap-1">
                  <template x-for="queue in queues" :key="queue.id">
                    <button 
                      @click="activeQueueTab = queue.id"
                      :class="activeQueueTab === queue.id 
                        ? 'bg-blue-600 text-white' 
                        : 'bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-slate-600'"
                      class="px-3 py-1.5 rounded-lg text-sm font-medium whitespace-nowrap transition flex items-center gap-2">
                      <span x-text="queue.name"></span>
                      <span class="text-xs opacity-70" x-text="`(${getQueueTabCount(queue)})`"></span>
                    </button>
                  </template>
                  
                  <div x-show="queues.length === 0" class="text-sm text-gray-500 dark:text-gray-400 px-3 py-1.5">
                    Kuyruk bulunamadı
                  </div>
                </div>
              </div>
              
              <!-- Queue Content -->
              <div class="flex-1 overflow-y-auto p-2">
                
                <!-- No Queue Selected -->
                <div x-show="!activeQueueTab" class="text-center py-12">
                  <span class="text-4xl">📋</span>
                  <p class="text-gray-500 dark:text-gray-400 mt-2 text-sm">Bir kuyruk seçin</p>
                </div>
                
                <!-- Queue Videos -->
                <div x-show="activeQueueTab" class="space-y-2">
                  <template x-for="(video, index) in activeQueueVideos" :key="video.job_id || video.id || index">
                    <div class="queue-item p-3 rounded-lg border border-gray-200 dark:border-slate-700 anim-fade-in">
                      <div class="flex items-center gap-3">
                        
                        <!-- Order Number -->
                        <div class="w-6 h-6 rounded-full bg-gray-200 dark:bg-slate-700 flex items-center justify-center text-xs font-medium text-gray-600 dark:text-gray-400">
                          <span x-text="index + 1"></span>
                        </div>
                        
                        <!-- Thumbnail -->
                        <div class="w-16 h-10 rounded bg-gray-200 dark:bg-slate-700 overflow-hidden flex-shrink-0">
                          <img x-show="video.thumbnailUrl" :src="video.thumbnailUrl" class="w-full h-full object-cover">
                          <div x-show="!video.thumbnailUrl" class="w-full h-full flex items-center justify-center text-gray-400">
                            🎬
                          </div>
                        </div>
                        
                        <!-- Info: Tam başlık göster -->
                        <div class="flex-1 min-w-0">
                          <p class="text-sm font-medium text-gray-900 dark:text-white line-clamp-2" x-text="video.title || 'İsimsiz Video'"></p>
                        </div>
                        
                        <!-- Status: Üretilecek / Paylaşılacak -->
                        <div class="flex items-center gap-2">
                          <span :class="getQueueVideoStatusBadge(video)" class="px-2 py-0.5 rounded text-xs whitespace-nowrap" x-text="getQueueVideoStatus(video)"></span>
                          
                          <button @click="removeFromQueue(video.id || video.job_id)" class="text-gray-400 hover:text-red-500 transition" title="Kuyruktan Kaldır">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                          </button>
                        </div>
                      </div>
                    </div>
                  </template>
                  
                  <!-- Empty Queue -->
                  <div x-show="activeQueueVideos.length === 0" class="text-center py-12">
                    <span class="text-4xl">📭</span>
                    <p class="text-gray-500 dark:text-gray-400 mt-2 text-sm">Bu kuyruk boş</p>
                    <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Soldan içerik seçip ekleyin</p>
                  </div>
                </div>
              </div>
              
              <!-- Queue Stats -->
              <div x-show="activeQueue" class="p-3 border-t border-gray-200 dark:border-slate-700 bg-gray-50 dark:bg-slate-800/50">
                <div class="flex items-center justify-between text-sm">
                  <span class="text-gray-600 dark:text-gray-400">
                    Platform: <span class="font-medium text-gray-900 dark:text-white" x-text="(activeQueue?.platforms || []).join(', ') || '-'"></span>
                  </span>
                  <span class="text-gray-600 dark:text-gray-400">
                    <span class="font-medium text-gray-900 dark:text-white" x-text="activeQueueVideos.length"></span> video
                  </span>
                </div>
              </div>
            </div>
            
          </div>
        </div>
      </main>
    </div>
  
    <!-- Add URL Modal -->
    <div x-show="addUrlModal" x-cloak
      x-transition:enter="transition ease-out duration-200"
      x-transition:enter-start="opacity-0"
      x-transition:enter-end="opacity-100"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
      @click.self="addUrlModal = false">
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-md p-6">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">Manuel URL Ekle</h2>
        
        <form @submit.prevent="submitUrl()">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">URL *</label>
              <input type="url" x-model="urlForm.url" required placeholder="https://example.com/article"
                class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white text-sm">
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Başlık (Opsiyonel)</label>
              <input type="text" x-model="urlForm.title" placeholder="İçerik başlığı"
                class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white text-sm">
            </div>
          </div>
          
          <div class="flex justify-end gap-3 mt-6">
            <button type="button" @click="addUrlModal = false" 
              class="px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700 text-sm">
              İptal
            </button>
            <button type="submit" :disabled="processing"
              class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white rounded-lg text-sm font-medium">
              <span x-show="!processing">Ekle</span>
              <span x-show="processing">Ekleniyor...</span>
            </button>
          </div>
        </form>
      </div>
    </div>
    
    <!-- Manage Sources Modal -->
    <div x-show="manageSourcesModal" x-cloak
      x-transition:enter="transition ease-out duration-200"
      x-transition:enter-start="opacity-0"
      x-transition:enter-end="opacity-100"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50 overflow-y-auto"
      @click.self="manageSourcesModal = false">
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-2xl p-6 my-8">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">RSS Kaynak Yönetimi</h2>
        
        <!-- Add Source Form -->
        <div class="bg-blue-50 dark:bg-slate-700 rounded-lg p-4 mb-4">
          <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Yeni RSS Kaynağı</h3>
          <form @submit.prevent="addSource()" class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
              <input type="text" x-model="sourceForm.name" required placeholder="Kaynak adı"
                class="px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-gray-900 dark:text-white text-sm">
              <input type="url" x-model="sourceForm.url" required placeholder="RSS Feed URL"
                class="px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-gray-900 dark:text-white text-sm">
            </div>
            <div class="grid grid-cols-2 gap-3">
              <select x-model="sourceForm.category"
                class="px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-gray-900 dark:text-white text-sm">
                <option value="teknoloji">Teknoloji</option>
                <option value="haber">Haber</option>
                <option value="bilim">Bilim</option>
                <option value="genel">Genel</option>
              </select>
              <input type="text" x-model="sourceForm.keywords" placeholder="Anahtar kelimeler (virgülle ayır)"
                class="px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-gray-900 dark:text-white text-sm">
            </div>
            <div class="flex justify-between items-center">
              <label class="flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <input type="checkbox" x-model="sourceForm.enabled" class="rounded">
                Aktif
              </label>
              <button type="submit" :disabled="processing"
                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white rounded-lg text-sm font-medium">
                Kaynak Ekle
              </button>
            </div>
          </form>
        </div>
        
        <!-- Source List -->
        <div class="space-y-2 max-h-64 overflow-y-auto">
          <template x-for="source in sources" :key="source.id">
            <div class="flex items-center justify-between p-3 rounded-lg border border-gray-200 dark:border-slate-700">
              <div class="flex items-center gap-3">
                <span :class="source.enabled ? 'bg-green-500' : 'bg-gray-400'" class="w-2 h-2 rounded-full"></span>
                <div>
                  <p class="text-sm font-medium text-gray-900 dark:text-white" x-text="source.name"></p>
                  <p class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-xs" x-text="source.url"></p>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <button @click="toggleSource(source.id)" class="text-xs px-2 py-1 rounded" 
                  :class="source.enabled ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800'"
                  x-text="source.enabled ? 'Duraklat' : 'Etkinleştir'">
                </button>
                <button @click="deleteSource(source.id)" class="text-xs px-2 py-1 bg-red-100 text-red-800 rounded hover:bg-red-200">
                  Sil
                </button>
              </div>
            </div>
          </template>
          
          <div x-show="sources.length === 0" class="text-center py-6 text-gray-500 dark:text-gray-400 text-sm">
            Henüz kaynak eklenmemiş
          </div>
        </div>
        
        <!-- Actions -->
        <div class="flex justify-between items-center mt-4 pt-4 border-t border-gray-200 dark:border-slate-700">
          <button @click="fetchFeeds()" :disabled="processing"
            class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white rounded-lg text-sm font-medium">
            <span x-show="!processing">🔄 Feed'leri Çek</span>
            <span x-show="processing">Çekiliyor...</span>
          </button>
          <button @click="manageSourcesModal = false" 
            class="px-4 py-2 bg-gray-200 dark:bg-slate-700 text-gray-700 dark:text-gray-300 rounded-lg text-sm font-medium hover:bg-gray-300 dark:hover:bg-slate-600">
            Kapat
          </button>
        </div>
      </div>
    </div>
    
    <!-- Fetch/Araştır Modal -->
    <div x-show="fetchModal" x-cloak
      x-transition:enter="transition ease-out duration-200"
      x-transition:enter-start="opacity-0"
      x-transition:enter-end="opacity-100"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50"
      @click.self="fetchModal = false">
      <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-md p-6">
        <h2 class="text-lg font-bold text-gray-900 dark:text-white mb-4">🔍 İçerik Araştır</h2>
        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Seçili RSS kaynağından içerik çekerek skorla</p>
        
        <form @submit.prevent="fetchFromSource()">
          <div class="space-y-4">
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">RSS Kaynağı</label>
              <select x-model="fetchForm.source_id"
                class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white text-sm">
                <option value="all">Tüm Kaynaklar</option>
                <template x-for="source in sources.filter(s => s.enabled)" :key="source.id">
                  <option :value="source.id" x-text="source.name"></option>
                </template>
              </select>
            </div>
            
            <div>
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Çekilecek İçerik Sayısı</label>
              <select x-model="fetchForm.limit"
                class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white text-sm">
                <option value="10">10 içerik</option>
                <option value="20">20 içerik</option>
                <option value="50">50 içerik</option>
                <option value="100">100 içerik</option>
              </select>
            </div>
          </div>
          
          <div class="flex justify-end gap-3 mt-6">
            <button type="button" @click="fetchModal = false" 
              class="px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700 text-sm">
              İptal
            </button>
            <button type="submit" :disabled="processing"
              class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white rounded-lg text-sm font-medium">
              <span x-show="!processing">🔍 Araştır</span>
              <span x-show="processing">Çekiliyor...</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  
  </div>
  
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.0/dist/cdn.min.js" defer></script>
  <?php include __DIR__ . '/components/_footer.php'; ?>
</body>
</html>
