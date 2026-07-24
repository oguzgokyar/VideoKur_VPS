<?php $page_title = 'İçerikler - Video Otomasyon'; $active_page = 'content'; ?>
<!DOCTYPE html>
<html lang="tr" x-data="{ darkMode: localStorage.getItem('darkMode') === '1' }" :class="{ 'dark': darkMode }">
<head>
  <?php include __DIR__ . '/components/_head.php'; ?>
  <style>
    [x-cloak] { display: none !important; }
    @keyframes fade-in { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
    .anim-fade-in { animation: fade-in .3s ease-out both; }
    .content-item:hover { background: rgba(59, 130, 246, 0.05); }
    .score-badge { animation: pulse 2s ease-in-out infinite; }
    @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.7; } }
  </style>
  <script>
  function contentApp() {
    return {
      sidebarOpen: false,
      darkMode: false,
      loading: true,
      content: [],
      sources: [],
      stats: {},
      
      selectedItems: [],
      filterStatus: 'all',
      sortBy: 'score',
      
      // Modals
      addUrlModal: false,
      manageSourcesModal: false,
      processing: false,
      
      // Forms
      urlForm: {
        url: '',
        title: ''
      },
      
      sourceForm: {
        name: '',
        url: '',
        category: 'genel',
        keywords: '',
        enabled: true
      },
      
      async init() {
        this.darkMode = localStorage.getItem('darkMode') === '1';
        try {
          await Promise.all([
            this.loadContent(),
            this.loadSources(),
            this.loadStats()
          ]);
        } catch (err) {
          console.error('Init error:', err);
        } finally {
          this.loading = false;
        }
        
        // Auto-refresh her 30 saniyede
        setInterval(() => this.loadContent(), 30000);
      },
      
      async loadContent() {
        try {
          const resp = await fetch(`/api/content.php?list=1&sort=${this.sortBy}`);
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
      
      get filteredContent() {
        let filtered = [...this.content];
        
        if (this.filterStatus !== 'all') {
          filtered = filtered.filter(c => c.status === this.filterStatus);
        }
        
        return filtered;
      },
      
      toggleSelect(contentId) {
        const idx = this.selectedItems.indexOf(contentId);
        if (idx > -1) {
          this.selectedItems.splice(idx, 1);
        } else {
          this.selectedItems.push(contentId);
        }
      },
      
      toggleSelectAll() {
        if (this.selectedItems.length === this.filteredContent.length) {
          this.selectedItems = [];
        } else {
          this.selectedItems = this.filteredContent.map(c => c.id);
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
        if (!confirm('Bu içeriği silmek istediğinizden emin misiniz?')) {
          return;
        }
        
        try {
          const resp = await fetch('/api/content.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: contentId })
          });
          
          const data = await resp.json();
          
          if (data.success) {
            await this.loadContent();
          } else {
            alert('❌ Silme hatası: ' + data.error);
          }
        } catch (err) {
          alert('❌ Network hatası');
        }
      },
      
      async processBatch() {
        if (this.selectedItems.length === 0) {
          alert('⚠️ Lütfen en az bir içerik seçin');
          return;
        }
        
        if (!confirm(`${this.selectedItems.length} içerik video üretimine gönderilecek. Devam edilsin mi?`)) {
          return;
        }
        
        this.processing = true;
        
        try {
          const resp = await fetch('/api/content.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'process',
              content_ids: this.selectedItems
            })
          });
          
          const data = await resp.json();
          
          if (data.success) {
            alert('✅ ' + data.message);
            this.selectedItems = [];
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
      
      openManageSourcesModal() {
        this.sourceForm = {
          name: '',
          url: '',
          category: 'genel',
          keywords: '',
          enabled: true
        };
        this.manageSourcesModal = true;
      },
      
      async addSource() {
        if (!this.sourceForm.name || !this.sourceForm.url) {
          alert('⚠️ İsim ve URL gerekli');
          return;
        }
        
        this.processing = true;
        
        try {
          const resp = await fetch('/api/content_sources.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              name: this.sourceForm.name,
              url: this.sourceForm.url,
              category: this.sourceForm.category,
              keywords: this.sourceForm.keywords.split(',').map(k => k.trim()).filter(k => k)
            })
          });
          
          const data = await resp.json();
          
          if (data.success) {
            alert('✅ RSS kaynağı eklendi!');
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
      
      async toggleSource(sourceId, enabled) {
        try {
          const resp = await fetch('/api/content_sources.php', {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: sourceId, enabled: !enabled })
          });
          
          const data = await resp.json();
          
          if (data.success) {
            await this.loadSources();
          }
        } catch (err) {
          alert('❌ Network hatası');
        }
      },
      
      async deleteSource(sourceId) {
        if (!confirm('Bu RSS kaynağını silmek istediğinizden emin misiniz?')) {
          return;
        }
        
        try {
          const resp = await fetch('/api/content_sources.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: sourceId })
          });
          
          const data = await resp.json();
          
          if (data.success) {
            await this.loadSources();
          } else {
            alert('❌ Hata: ' + data.error);
          }
        } catch (err) {
          alert('❌ Network hatası');
        }
      },
      
      getScoreColor(score) {
        if (score >= 80) return 'bg-green-500';
        if (score >= 60) return 'bg-blue-500';
        if (score >= 40) return 'bg-yellow-500';
        return 'bg-gray-500';
      },
      
      getScoreText(score) {
        if (score >= 80) return 'Mükemmel';
        if (score >= 60) return 'İyi';
        if (score >= 40) return 'Orta';
        return 'Düşük';
      },
      
      getStatusBadge(status) {
        const badges = {
          pending: { text: 'Bekliyor', color: 'bg-gray-500' },
          processing: { text: 'İşleniyor', color: 'bg-blue-500' },
          completed: { text: 'Tamamlandı', color: 'bg-green-500' },
          failed: { text: 'Başarısız', color: 'bg-red-500' }
        };
        return badges[status] || badges.pending;
      },
      
      formatDate(dateStr) {
        const date = new Date(dateStr);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);
        
        if (diffMins < 1) return 'Az önce';
        if (diffMins < 60) return `${diffMins} dakika önce`;
        if (diffHours < 24) return `${diffHours} saat önce`;
        if (diffDays < 7) return `${diffDays} gün önce`;
        
        return date.toLocaleDateString('tr-TR');
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
      <main class="flex-1 p-6 lg:p-8 overflow-y-auto">
        <div class="max-w-8xl mx-auto">
          
          <!-- Header -->
          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
            <div>
              <h1 class="text-2xl md:text-3xl font-bold text-gray-900 dark:text-white">İçerik Keşfi</h1>
              <p class="text-gray-600 dark:text-gray-400 mt-1">RSS feed'lerden otomatik içerik toplama ve video üretimine gönderme</p>
            </div>
            
            <div class="flex gap-2">
              <button @click="openAddUrlModal()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition flex items-center gap-2">
                <span>➕</span>
                <span>URL Ekle</span>
              </button>
              
              <button @click="openManageSourcesModal()" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-medium transition flex items-center gap-2">
                <span>📡</span>
                <span>RSS Kaynakları</span>
              </button>
            </div>
          </div>
          
          <!-- Stats -->
          <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white dark:bg-slate-800 rounded-lg p-4 border border-gray-200 dark:border-slate-700">
              <div class="text-sm text-gray-600 dark:text-gray-400">Toplam</div>
              <div class="text-2xl font-bold text-gray-900 dark:text-white mt-1" x-text="stats.total || 0"></div>
            </div>
            
            <div class="bg-white dark:bg-slate-800 rounded-lg p-4 border border-gray-200 dark:border-slate-700">
              <div class="text-sm text-gray-600 dark:text-gray-400">Bekliyor</div>
              <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400 mt-1" x-text="stats.pending || 0"></div>
            </div>
            
            <div class="bg-white dark:bg-slate-800 rounded-lg p-4 border border-gray-200 dark:border-slate-700">
              <div class="text-sm text-gray-600 dark:text-gray-400">İşleniyor</div>
              <div class="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1" x-text="stats.processing || 0"></div>
            </div>
            
            <div class="bg-white dark:bg-slate-800 rounded-lg p-4 border border-gray-200 dark:border-slate-700">
              <div class="text-sm text-gray-600 dark:text-gray-400">Tamamlandı</div>
              <div class="text-2xl font-bold text-green-600 dark:text-green-400 mt-1" x-text="stats.completed || 0"></div>
            </div>
          </div>
          
          <!-- Filters -->
          <div class="bg-white dark:bg-slate-800 rounded-lg p-4 mb-4 border border-gray-200 dark:border-slate-700">
            <div class="flex flex-col md:flex-row md:items-center gap-4">
              <div class="flex-1 flex items-center gap-4">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Filtre:</label>
                <select x-model="filterStatus" @change="loadContent()" class="px-3 py-1.5 border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white">
                  <option value="all">Tümü</option>
                  <option value="pending">Bekliyor</option>
                  <option value="processing">İşleniyor</option>
                  <option value="completed">Tamamlandı</option>
                  <option value="failed">Başarısız</option>
                </select>
                
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300 ml-4">Sırala:</label>
                <select x-model="sortBy" @change="loadContent()" class="px-3 py-1.5 border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white">
                  <option value="score">Skor (Yüksek→Düşük)</option>
                  <option value="date">Tarih (Yeni→Eski)</option>
                </select>
              </div>
              
              <div x-show="selectedItems.length > 0" class="flex items-center gap-2">
                <span class="text-sm text-gray-600 dark:text-gray-400" x-text="`${selectedItems.length} seçili`"></span>
                <button @click="processBatch()" :disabled="processing" class="px-4 py-2 bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white rounded-lg font-medium transition">
                  <span x-show="!processing">Pipeline'a Gönder →</span>
                  <span x-show="processing">Gönderiliyor...</span>
                </button>
              </div>
            </div>
          </div>
          
          <!-- Content List -->
          <div x-show="loading" x-cloak class="text-center py-12">
            <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
            <p class="text-gray-600 dark:text-gray-400 mt-4">Yükleniyor...</p>
          </div>
          
          <div x-show="!loading && filteredContent.length === 0" x-cloak class="text-center py-12 bg-white dark:bg-slate-800 rounded-lg border border-gray-200 dark:border-slate-700">
            <span class="text-6xl">📭</span>
            <p class="text-gray-600 dark:text-gray-400 mt-4">Henüz içerik bulunamadı</p>
            <p class="text-sm text-gray-500 dark:text-gray-500 mt-2">RSS kaynağı ekleyin veya manuel URL girin</p>
          </div>
          
          <div x-show="!loading && filteredContent.length > 0" class="space-y-3">
            <template x-for="item in filteredContent" :key="item.id">
              <div class="content-item bg-white dark:bg-slate-800 rounded-lg p-4 border border-gray-200 dark:border-slate-700 hover:border-blue-400 dark:hover:border-blue-500 transition anim-fade-in">
                <div class="flex items-start gap-4">
                  <!-- Checkbox -->
                  <input type="checkbox" 
                    :checked="selectedItems.includes(item.id)"
                    @change="toggleSelect(item.id)"
                    :disabled="item.status !== 'pending'"
                    class="mt-1.5 w-5 h-5 text-blue-600 rounded focus:ring-2 focus:ring-blue-500 disabled:opacity-30">
                  
                  <!-- Content -->
                  <div class="flex-1 min-w-0">
                    <div class="flex items-start justify-between gap-4">
                      <div class="flex-1 min-w-0">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-1 line-clamp-2" x-text="item.title"></h3>
                        
                        <div class="flex flex-wrap items-center gap-3 text-sm text-gray-600 dark:text-gray-400 mb-2">
                          <span class="flex items-center gap-1">
                            <span>🌐</span>
                            <span x-text="item.source"></span>
                          </span>
                          
                          <span class="flex items-center gap-1">
                            <span>📁</span>
                            <span x-text="item.metadata?.category || 'genel'"></span>
                          </span>
                          
                          <span class="flex items-center gap-1">
                            <span>🕐</span>
                            <span x-text="formatDate(item.discovered_at)"></span>
                          </span>
                        </div>
                        
                        <a :href="item.url" target="_blank" class="text-sm text-blue-600 dark:text-blue-400 hover:underline break-all" x-text="item.url"></a>
                      </div>
                      
                      <div class="flex flex-col items-end gap-2">
                        <!-- Score Badge -->
                        <div class="flex items-center gap-2 px-3 py-1.5 rounded-lg score-badge" :class="getScoreColor(item.score)">
                          <span class="text-white font-bold" x-text="item.score"></span>
                          <span class="text-xs text-white opacity-90" x-text="getScoreText(item.score)"></span>
                        </div>
                        
                        <!-- Status Badge -->
                        <div class="px-3 py-1 rounded-full text-xs font-medium text-white" 
                          :class="getStatusBadge(item.status).color"
                          x-text="getStatusBadge(item.status).text">
                        </div>
                        
                        <!-- Job Link -->
                        <template x-if="item.processed_job_id">
                          <a :href="`/frontend/project.php?id=${item.processed_job_id}`" 
                            class="text-xs text-blue-600 dark:text-blue-400 hover:underline">
                            Job: <span x-text="item.processed_job_id"></span>
                          </a>
                        </template>
                      </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="flex items-center gap-2 mt-3 pt-3 border-t border-gray-200 dark:border-slate-700">
                      <button x-show="item.status === 'pending'" 
                        @click="toggleSelect(item.id)" 
                        class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                        <span x-show="!selectedItems.includes(item.id)">Seç</span>
                        <span x-show="selectedItems.includes(item.id)">Seçimi Kaldır</span>
                      </button>
                      
                      <button @click="deleteContent(item.id)" 
                        class="text-sm text-red-600 dark:text-red-400 hover:underline ml-auto">
                        Sil
                      </button>
                    </div>
                  </div>
                </div>
              </div>
            </template>
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
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-lg p-6">
      <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">Manuel URL Ekle</h2>
      
      <form @submit.prevent="submitUrl()">
        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">URL *</label>
            <input type="url" x-model="urlForm.url" required
              placeholder="https://example.com/article"
              class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500">
          </div>
          
          <div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Başlık (Opsiyonel)</label>
            <input type="text" x-model="urlForm.title"
              placeholder="İçerik başlığı"
              class="w-full px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500">
          </div>
        </div>
        
        <div class="flex justify-end gap-3 mt-6">
          <button type="button" @click="addUrlModal = false" 
            class="px-4 py-2 border border-gray-300 dark:border-slate-600 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-slate-700 transition">
            İptal
          </button>
          <button type="submit" :disabled="processing"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white rounded-lg font-medium transition">
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
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-3xl p-6 my-8">
      <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">RSS Kaynak Yönetimi</h2>
      
      <!-- Add Source Form -->
      <div class="bg-blue-50 dark:bg-slate-700 rounded-lg p-4 mb-6">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">Yeni RSS Kaynağı Ekle</h3>
        <form @submit.prevent="addSource()" class="space-y-3">
          <div class="grid grid-cols-2 gap-3">
            <input type="text" x-model="sourceForm.name" required
              placeholder="Kaynak adı (örn: TechCrunch)"
              class="px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-gray-900 dark:text-white text-sm">
            
            <input type="url" x-model="sourceForm.url" required
              placeholder="RSS URL"
              class="px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-gray-900 dark:text-white text-sm">
          </div>
          
          <div class="grid grid-cols-2 gap-3">
            <select x-model="sourceForm.category"
              class="px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-gray-900 dark:text-white text-sm">
              <option value="genel">Genel</option>
              <option value="teknoloji">Teknoloji</option>
              <option value="spor">Spor</option>
              <option value="ekonomi">Ekonomi</option>
              <option value="sağlık">Sağlık</option>
            </select>
            
            <input type="text" x-model="sourceForm.keywords"
              placeholder="Anahtar kelimeler (virgülle ayırın)"
              class="px-3 py-2 border border-gray-300 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-gray-900 dark:text-white text-sm">
          </div>
          
          <button type="submit" :disabled="processing"
            class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white rounded-lg font-medium transition text-sm">
            <span x-show="!processing">➕ Kaynak Ekle</span>
            <span x-show="processing">Ekleniyor...</span>
          </button>
        </form>
      </div>
      
      <!-- Sources List -->
      <div class="space-y-2 max-h-96 overflow-y-auto">
        <template x-for="source in sources" :key="source.id">
          <div class="bg-gray-50 dark:bg-slate-700 rounded-lg p-3 flex items-start justify-between gap-3">
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 mb-1">
                <h4 class="font-semibold text-gray-900 dark:text-white text-sm" x-text="source.name"></h4>
                <span class="px-2 py-0.5 bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 rounded text-xs" x-text="source.category"></span>
                <span x-show="source.enabled" class="px-2 py-0.5 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 rounded text-xs">Aktif</span>
                <span x-show="!source.enabled" class="px-2 py-0.5 bg-gray-100 dark:bg-gray-900 text-gray-700 dark:text-gray-300 rounded text-xs">Pasif</span>
              </div>
              <a :href="source.url" target="_blank" class="text-xs text-blue-600 dark:text-blue-400 hover:underline break-all" x-text="source.url"></a>
              <div x-show="source.keywords && source.keywords.length > 0" class="flex flex-wrap gap-1 mt-1">
                <template x-for="keyword in source.keywords" :key="keyword">
                  <span class="px-1.5 py-0.5 bg-gray-200 dark:bg-slate-600 text-gray-700 dark:text-gray-300 rounded text-xs" x-text="keyword"></span>
                </template>
              </div>
            </div>
            
            <div class="flex items-center gap-2">
              <button @click="toggleSource(source.id, source.enabled)"
                class="px-3 py-1 rounded text-xs font-medium transition"
                :class="source.enabled ? 'bg-yellow-100 dark:bg-yellow-900 text-yellow-700 dark:text-yellow-300 hover:bg-yellow-200' : 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300 hover:bg-green-200'">
                <span x-show="source.enabled">Pasifleştir</span>
                <span x-show="!source.enabled">Aktifleştir</span>
              </button>
              
              <button @click="deleteSource(source.id)"
                class="px-3 py-1 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 hover:bg-red-200 rounded text-xs font-medium transition">
                Sil
              </button>
            </div>
          </div>
        </template>
        
        <div x-show="sources.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400 text-sm">
          Henüz RSS kaynağı eklenmemiş
        </div>
      </div>
      
      <div class="flex justify-end mt-6">
        <button @click="manageSourcesModal = false" 
          class="px-4 py-2 bg-gray-200 dark:bg-slate-700 text-gray-700 dark:text-gray-300 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-slate-600 transition">
          Kapat
        </button>
      </div>
    </div>
  </div>
  
  </div><!-- x-data wrapper kapanışı -->
  
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.0/dist/cdn.min.js" defer></script>
  <?php include __DIR__ . '/components/_footer.php'; ?>
</body>
</html>
