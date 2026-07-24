<?php $page_title = 'YouTube Hesapları - Çoklu Kanal Yönetimi'; $active_page = 'accounts'; ?>
<!DOCTYPE html>
<html lang="tr" x-data="{ darkMode: localStorage.getItem('darkMode') === '1' }" :class="{ 'dark': darkMode }">
<head>
  <?php include __DIR__ . '/components/_head.php'; ?>
  <style>
    /* Minimalist Table Styles */
    .table-minimal { width: 100%; border-collapse: collapse; }
    .table-minimal thead { background: rgba(239, 68, 68, 0.05); }
    .table-minimal th { padding: 10px 12px; text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: #6b7280; }
    .table-minimal td { padding: 10px 12px; border-top: 1px solid #e5e7eb; }
    .dark .table-minimal thead { background: rgba(239, 68, 68, 0.1); }
    .dark .table-minimal th { color: #9ca3af; }
    .dark .table-minimal td { border-color: #374151; }
    
    /* Accordion Styles */
    .accordion-header { cursor: pointer; transition: background 0.15s; }
    .accordion-header:hover { background: rgba(0,0,0,0.02); }
    .dark .accordion-header:hover { background: rgba(255,255,255,0.03); }
    
    /* Quota Progress Bar */
    .quota-bar { height: 6px; background: #e5e7eb; border-radius: 3px; overflow: hidden; }
    .quota-fill { height: 100%; transition: width 0.3s; }
    .quota-fill.good { background: #10b981; }
    .quota-fill.warning { background: #f59e0b; }
    .quota-fill.danger { background: #ef4444; }
    .dark .quota-bar { background: #374151; }
    
    /* Status Badges */
    .status-badge { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600; }
    .status-active { background: rgba(34, 197, 94, 0.1); color: #16a34a; }
    .status-inactive { background: rgba(239, 68, 68, 0.1); color: #dc2626; }
    .status-pending { background: rgba(234, 179, 8, 0.1); color: #ca8a04; }
    .dark .status-active { background: rgba(34, 197, 94, 0.15); color: #4ade80; }
    .dark .status-inactive { background: rgba(239, 68, 68, 0.15); color: #f87171; }
    .dark .status-pending { background: rgba(234, 179, 8, 0.15); color: #fbbf24; }
  </style>
  <script>
  function accountsApp() {
    return {
      // App State
      sidebarOpen: false,
      darkMode: localStorage.getItem('darkMode') === '1',
      loading: true,
      
      // YouTube Unified Channels State
      channels: [],
      expandedChannels: {},
      
      // Modals
      addChannelModal: false,
      addApiModal: false,
      editCategoryModal: false,
      currentChannelId: null,
      submitting: false,
      
      // Forms
      channelForm: { channel_title: '', channel_id: '', description: '' },
      apiForm: { name: '', client_secrets_file: null, project_id: '', daily_quota: 10000, notes: '' },
      categoryForm: { channel_id: '', category_id: '28' },
      
      // ==================== CHANNEL OPERATIONS ====================
      async loadChannels() {
        this.loading = true;
        try {
          const r = await fetch('/api/youtube_channels.php?action=list');
          const d = await r.json();
          if (d.success) {
            this.channels = d.channels || [];
            // Auto-expand first channel
            if (this.channels.length > 0 && Object.keys(this.expandedChannels).length === 0) {
              this.expandedChannels[this.channels[0].id] = true;
            }
          }
        } catch(e) {
          console.error('Kanal yükleme hatası:', e);
        }
        this.loading = false;
      },
      
      toggleChannel(channelId) {
        this.expandedChannels[channelId] = !this.expandedChannels[channelId];
      },
      
      isExpanded(channelId) {
        return this.expandedChannels[channelId] === true;
      },
      
      getQuotaClass(used, total) {
        const pct = (used / total) * 100;
        if (pct >= 90) return 'danger';
        if (pct >= 70) return 'warning';
        return 'good';
      },
      
      getCategoryName(id) {
        const categories = {
          '1': 'Film & Animasyon', '2': 'Otomobil & Araçlar', '10': 'Müzik',
          '15': 'Hayvanlar', '17': 'Spor', '19': 'Seyahat', '20': 'Oyun',
          '22': 'İnsanlar & Bloglar', '23': 'Komedi', '24': 'Eğlence',
          '25': 'Haber & Politika', '26': 'Nasıl Yapılır', '27': 'Eğitim',
          '28': 'Bilim & Teknoloji'
        };
        return categories[id] || 'Bilim & Teknoloji';
      },
      
      // ==================== ADD CHANNEL ====================
      openAddChannelModal() {
        this.channelForm = { channel_title: '', channel_id: '', description: '' };
        this.addChannelModal = true;
      },
      
      async addChannel() {
        if (!this.channelForm.channel_title) {
          alert('❌ Kanal adı gerekli');
          return;
        }
        this.submitting = true;
        try {
          const r = await fetch('/api/youtube_channels.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'add_channel', ...this.channelForm })
          });
          const d = await r.json();
          if (d.success) {
            alert('✅ ' + d.message);
            this.addChannelModal = false;
            await this.loadChannels();
          } else {
            alert('❌ ' + d.error);
          }
        } catch(e) {
          alert('❌ Hata: ' + e.message);
        }
        this.submitting = false;
      },
      
      // ==================== DELETE CHANNEL ====================
      async deleteChannel(channelId, channelTitle) {
        const apiCount = this.channels.find(c => c.id === channelId)?.apis?.length || 0;
        const msg = apiCount > 0 
          ? `"${channelTitle}" kanalı ve ${apiCount} API silinecek. Devam edilsin mi?`
          : `"${channelTitle}" kanalı silinecek. Devam edilsin mi?`;
        
        if (!confirm(msg)) return;
        
        try {
          const r = await fetch('/api/youtube_channels.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'delete_channel', channel_id: channelId })
          });
          const d = await r.json();
          if (d.success) {
            alert('✅ ' + d.message);
            await this.loadChannels();
          } else {
            alert('❌ ' + d.error);
          }
        } catch(e) {
          alert('❌ Hata: ' + e.message);
        }
      },
      
      // ==================== ADD API ====================
      openAddApiModal(channelId) {
        this.currentChannelId = channelId;
        this.apiForm = { name: '', client_secrets_file: null, project_id: '', daily_quota: 10000, notes: '' };
        this.addApiModal = true;
      },
      
      handleApiFileSelect(event) {
        const file = event.target.files[0];
        if (file) {
          const reader = new FileReader();
          reader.onload = (e) => {
            try {
              const data = JSON.parse(e.target.result);
              this.apiForm.client_secrets_file = file.name;
              this.apiForm.fileData = data;
              // Extract project_id and auto-fill name
              const config = data.installed || data.web;
              if (config?.project_id) {
                this.apiForm.project_id = config.project_id;
                // Auto-fill API name if empty
                if (!this.apiForm.name) {
                  this.apiForm.name = config.project_id;
                }
              }
            } catch(err) {
              alert('❌ Geçersiz JSON dosyası');
            }
          };
          reader.readAsText(file);
        }
      },
      
      async addApi() {
        if (!this.apiForm.name || !this.apiForm.fileData) {
          alert('❌ API adı ve client secrets dosyası gerekli');
          return;
        }
        this.submitting = true;
        try {
          const r = await fetch('/api/youtube_channels.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
              action: 'add_api',
              channel_id: this.currentChannelId,
              name: this.apiForm.name,
              project_id: this.apiForm.project_id,
              daily_quota: this.apiForm.daily_quota,
              notes: this.apiForm.notes,
              client_secrets: this.apiForm.fileData
            })
          });
          const d = await r.json();
          if (d.success) {
            alert('✅ ' + d.message);
            this.addApiModal = false;
            await this.loadChannels();
          } else {
            alert('❌ ' + d.error);
          }
        } catch(e) {
          alert('❌ Hata: ' + e.message);
        }
        this.submitting = false;
      },
      
      // ==================== API OPERATIONS ====================
      async loginApi(channelId, apiId) {
        if (!confirm('OAuth login başlatılsın mı?')) return;
        try {
          const r = await fetch('/api/youtube_channels.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'login_api', channel_id: channelId, api_id: apiId })
          });
          const d = await r.json();
          if (d.success && d.oauth_url) {
            window.open(d.oauth_url, '_blank');
            alert('OAuth sayfası yeni sekmede açıldı. İşlemi tamamladıktan sonra sayfayı yenileyin.');
          } else {
            alert('❌ ' + (d.error || 'OAuth başlatılamadı'));
          }
        } catch(e) {
          alert('❌ Hata: ' + e.message);
        }
      },
      
      async toggleApiStatus(channelId, apiId, currentStatus) {
        try {
          const r = await fetch('/api/youtube_channels.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'update_api', channel_id: channelId, api_id: apiId, is_active: !currentStatus })
          });
          const d = await r.json();
          if (d.success) await this.loadChannels();
          else alert('❌ ' + d.error);
        } catch(e) {
          alert('❌ Hata: ' + e.message);
        }
      },
      
      async deleteApi(channelId, apiId, apiName) {
        if (!confirm(`"${apiName}" API'sini silmek istediğinize emin misiniz?`)) return;
        try {
          const r = await fetch('/api/youtube_channels.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'delete_api', channel_id: channelId, api_id: apiId })
          });
          const d = await r.json();
          if (d.success) {
            alert('✅ ' + d.message);
            await this.loadChannels();
          } else {
            alert('❌ ' + d.error);
          }
        } catch(e) {
          alert('❌ Hata: ' + e.message);
        }
      },
      
      editChannelCategory(channel) {
        this.categoryForm = {
          channel_id: channel.id,
          category_id: channel.default_category_id || '28'
        };
        this.editCategoryModal = true;
      },
      
      async updateChannelCategory() {
        this.submitting = true;
        try {
          const r = await fetch('/api/youtube_channels.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
              action: 'update_channel_category',
              channel_id: this.categoryForm.channel_id,
              category_id: this.categoryForm.category_id
            })
          });
          const d = await r.json();
          if (d.success) {
            alert('✅ Kategori güncellendi');
            this.editCategoryModal = false;
            await this.loadChannels();
          } else {
            alert('❌ ' + d.error);
          }
        } catch(e) {
          alert('❌ Hata: ' + e.message);
        }
        this.submitting = false;
      },
      
      // ==================== UTILITIES ====================
      formatNumber(num) {
        if (num >= 1000000) return (num / 1000000).toFixed(1) + 'M';
        if (num >= 1000) return (num / 1000).toFixed(1) + 'K';
        return num?.toString() || '0';
      },
      
      toggleDark() {
        this.darkMode = !this.darkMode;
        localStorage.setItem('darkMode', this.darkMode ? '1' : '0');
        document.documentElement.classList.toggle('dark', this.darkMode);
      },
      
      init() {
        this.loadChannels();
      }
    };
  }
  </script>
  <script src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js" defer></script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.0/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-100 dark:bg-gray-900 min-h-screen" x-data="accountsApp()" x-init="init()">
  <div class="flex flex-col h-screen">
    <?php include __DIR__ . '/components/_header.php'; ?>

    <div class="flex flex-1 overflow-hidden">
      <?php include __DIR__ . '/components/_sidebar.php'; ?>

      <main class="flex-1 overflow-y-auto p-6 md:p-8">
        <div class="max-w-5xl mx-auto">
          
          <!-- Page Header -->
          <div class="flex items-center justify-between mb-6">
            <div>
              <h1 class="text-2xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                📺 YouTube Hesapları
              </h1>
              <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Çoklu kanal ve API yönetimi</p>
            </div>
            <button @click="openAddChannelModal()" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 text-sm font-medium">
              ➕ Yeni Kanal
            </button>
          </div>

          <!-- Loading State -->
          <div x-show="loading" class="text-center py-12">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-red-600 mx-auto"></div>
            <p class="mt-4 text-gray-600 dark:text-gray-400">Yükleniyor...</p>
          </div>

          <!-- Empty State -->
          <template x-if="!loading && channels.length === 0">
            <div class="text-center py-12 bg-white dark:bg-gray-800 rounded-xl border">
              <div class="text-6xl mb-4">📺</div>
              <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Henüz Kanal Yok</h3>
              <p class="text-gray-600 dark:text-gray-400 mb-6">İlk YouTube kanalınızı ekleyin</p>
              <button @click="openAddChannelModal()" class="bg-red-600 text-white px-6 py-3 rounded-lg hover:bg-red-700">
                ➕ Kanal Ekle
              </button>
            </div>
          </template>

          <!-- Channels List (Accordion) -->
          <div x-show="!loading && channels.length > 0" class="space-y-4">
            <template x-for="channel in channels" :key="channel.id">
              <div class="bg-white dark:bg-gray-800 rounded-xl border dark:border-gray-700 overflow-hidden">
                
                <!-- Channel Header (Accordion Toggle) -->
                <div @click="toggleChannel(channel.id)" class="accordion-header px-5 py-4 flex items-center justify-between">
                  <div class="flex items-center gap-4">
                    <div class="w-12 h-12 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center text-2xl">
                      📺
                    </div>
                    <div>
                      <div class="flex items-center gap-2">
                        <h3 class="font-semibold text-gray-900 dark:text-white" x-text="channel.channel_title"></h3>
                      </div>
                      <div class="text-sm text-gray-500 dark:text-gray-400 flex items-center gap-4 mt-1">
                        <span x-text="(channel.apis?.length || 0) + ' API'"></span>
                        <span>•</span>
                        <span x-text="channel.apis?.filter(a => a.is_active).length + ' aktif'"></span>
                        <span>•</span>
                        <span>📁 Kategori: <span x-text="getCategoryName(channel.default_category_id || '28')"></span></span>
                      </div>
                    </div>
                  </div>
                  <div class="flex items-center gap-3">
                    <button @click.stop="editChannelCategory(channel)" class="text-sm px-3 py-1 bg-blue-100 dark:bg-blue-900/30 hover:bg-blue-200 dark:hover:bg-blue-900/50 rounded text-blue-600 dark:text-blue-400" title="Kategori Ayarla">
                      📁 Kategori
                    </button>
                    <button @click.stop="deleteChannel(channel.id, channel.channel_title)" class="text-sm px-3 py-1 bg-red-100 dark:bg-red-900/30 hover:bg-red-200 dark:hover:bg-red-900/50 rounded text-red-600 dark:text-red-400" title="Kanalı Sil">
                      🗑️
                    </button>
                    <svg class="w-5 h-5 text-gray-400 transition-transform" :class="isExpanded(channel.id) ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                  </div>
                </div>
                
                <!-- Channel Content (API Table) -->
                <div x-show="isExpanded(channel.id)" x-collapse class="border-t dark:border-gray-700">
                  
                  <!-- API Table -->
                  <div x-show="channel.apis?.length > 0" class="overflow-x-auto">
                    <table class="w-full">
                      <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase">API Adı</th>
                          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase">Durum</th>
                          <th class="px-4 py-3 text-left text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase">Kota</th>
                          <th class="px-4 py-3 text-right text-xs font-semibold text-gray-600 dark:text-gray-400 uppercase">İşlemler</th>
                        </tr>
                      </thead>
                      <tbody x-ref="apiTableBody">
                        <template x-for="(api, idx) in channel.apis" :key="api.api_id + '_' + idx">
                          <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 border-b dark:border-gray-700">
                            <td class="px-4 py-3">
                              <div class="font-medium text-gray-900 dark:text-white text-sm" x-text="api.name || api.project_id"></div>
                              <div class="text-xs text-gray-500" x-text="api.project_id"></div>
                            </td>
                            <td class="px-4 py-3">
                              <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium" :class="api.is_authenticated && api.is_active ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : api.is_authenticated ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' : 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'">
                                <span x-text="api.is_authenticated && api.is_active ? '🟢 Aktif' : api.is_authenticated ? '⏸️ Pasif' : '🔴 Login Gerekli'"></span>
                              </span>
                            </td>
                            <td class="px-4 py-3">
                              <div class="w-32">
                                <div class="flex justify-between text-xs mb-1">
                                  <span class="text-gray-500" x-text="(api.quota_used_today || 0).toLocaleString()"></span>
                                  <span class="text-gray-700 dark:text-gray-300 font-medium" x-text="(api.daily_quota || 10000).toLocaleString()"></span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                  <div class="h-2 rounded-full transition-all" :class="((api.quota_used_today || 0) / (api.daily_quota || 10000)) >= 0.9 ? 'bg-red-500' : ((api.quota_used_today || 0) / (api.daily_quota || 10000)) >= 0.7 ? 'bg-yellow-500' : 'bg-green-500'" :style="'width:' + Math.min(100, ((api.quota_used_today || 0) / (api.daily_quota || 10000)) * 100) + '%'"></div>
                                </div>
                              </div>
                            </td>
                            <td class="px-4 py-3 text-right">
                              <div class="flex items-center justify-end gap-2">
                                <button x-show="!api.is_authenticated" @click="loginApi(channel.id, api.api_id)" class="text-xs px-3 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 rounded hover:bg-blue-200 dark:hover:bg-blue-900/50">
                                  🔑 Login
                                </button>
                                <button x-show="api.is_authenticated" @click="toggleApiStatus(channel.id, api.api_id, api.is_active)" class="text-xs px-3 py-1 rounded" :class="api.is_active ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-700 dark:text-amber-400 hover:bg-amber-200' : 'bg-green-100 dark:bg-green-900/30 text-green-700 dark:text-green-400 hover:bg-green-200'">
                                  <span x-text="api.is_active ? '⏸️ Duraklat' : '▶️ Aktif Et'"></span>
                                </button>
                                <button @click="deleteApi(channel.id, api.api_id, api.name)" class="text-xs px-2 py-1 bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-400 rounded hover:bg-red-200 dark:hover:bg-red-900/50">
                                  🗑️
                                </button>
                              </div>
                            </td>
                          </tr>
                        </template>
                      </tbody>
                    </table>
                  </div>
                  
                  <!-- Add API Button -->
                  <div class="px-5 py-4 border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                    <button @click="openAddApiModal(channel.id)" class="text-sm text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium flex items-center gap-2">
                      ➕ Bu kanala API ekle
                    </button>
                  </div>
                  
                </div>
              </div>
            </template>
          </div>

          <!-- Setup Guide (Collapsible) -->
          <div class="mt-6 bg-white dark:bg-gray-800 rounded-xl border dark:border-gray-700 overflow-hidden" x-data="{ showGuide: false }">
            <button @click="showGuide = !showGuide" class="w-full px-5 py-4 flex items-center justify-between text-left hover:bg-gray-50 dark:hover:bg-gray-700/50">
              <div class="flex items-center gap-3">
                <span class="text-2xl">📖</span>
                <div>
                  <h3 class="font-semibold text-gray-900 dark:text-white">YouTube API Kurulum Rehberi</h3>
                  <p class="text-sm text-gray-500 dark:text-gray-400">Google Cloud Console'dan API oluşturma adımları</p>
                </div>
              </div>
              <svg class="w-5 h-5 text-gray-400 transition-transform" :class="showGuide ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
              </svg>
            </button>
            
            <div x-show="showGuide" x-collapse class="border-t dark:border-gray-700">
              <div class="p-5 space-y-6 text-sm">
                
                <!-- Step 1 -->
                <div class="flex gap-4">
                  <div class="flex-shrink-0 w-8 h-8 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center text-red-600 dark:text-red-400 font-bold">1</div>
                  <div>
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-1">Google Cloud Console'a Gidin</h4>
                    <p class="text-gray-600 dark:text-gray-400">
                      <a href="https://console.cloud.google.com" target="_blank" class="text-blue-600 dark:text-blue-400 hover:underline">console.cloud.google.com</a> adresine gidin ve Google hesabınızla giriş yapın.
                    </p>
                  </div>
                </div>
                
                <!-- Step 2 -->
                <div class="flex gap-4">
                  <div class="flex-shrink-0 w-8 h-8 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center text-red-600 dark:text-red-400 font-bold">2</div>
                  <div>
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-1">Yeni Proje Oluşturun</h4>
                    <p class="text-gray-600 dark:text-gray-400">
                      Üst menüden "Select a project" → "New Project" tıklayın. Projeye anlamlı bir isim verin (örn: "video-kur-1").
                    </p>
                  </div>
                </div>
                
                <!-- Step 3 -->
                <div class="flex gap-4">
                  <div class="flex-shrink-0 w-8 h-8 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center text-red-600 dark:text-red-400 font-bold">3</div>
                  <div>
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-1">YouTube Data API v3'ü Etkinleştirin</h4>
                    <p class="text-gray-600 dark:text-gray-400">
                      Sol menüden "APIs & Services" → "Library" gidin. "YouTube Data API v3" arayın ve "Enable" tıklayın.
                    </p>
                  </div>
                </div>
                
                <!-- Step 4 -->
                <div class="flex gap-4">
                  <div class="flex-shrink-0 w-8 h-8 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center text-red-600 dark:text-red-400 font-bold">4</div>
                  <div>
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-1">OAuth Consent Screen Ayarlayın</h4>
                    <p class="text-gray-600 dark:text-gray-400">
                      "APIs & Services" → "OAuth consent screen" gidin. "External" seçin. Uygulama adı, e-posta ve logo ekleyin. Test kullanıcısı olarak kendi e-postanızı ekleyin.
                    </p>
                  </div>
                </div>
                
                <!-- Step 5 -->
                <div class="flex gap-4">
                  <div class="flex-shrink-0 w-8 h-8 bg-red-100 dark:bg-red-900/30 rounded-full flex items-center justify-center text-red-600 dark:text-red-400 font-bold">5</div>
                  <div>
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-1">OAuth Credentials Oluşturun</h4>
                    <p class="text-gray-600 dark:text-gray-400">
                      "Credentials" → "Create Credentials" → "OAuth client ID" tıklayın. Application type: <strong>"Web application"</strong> seçin.
                    </p>
                    <div class="mt-2 p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg text-amber-800 dark:text-amber-300">
                      <strong>⚠️ Önemli:</strong> "Authorized redirect URIs" bölümüne şunu ekleyin:<br>
                      <code class="text-xs bg-amber-100 dark:bg-amber-900/40 px-2 py-1 rounded mt-1 inline-block">http://localhost:8000/api/youtube_oauth.php</code>
                    </div>
                  </div>
                </div>
                
                <!-- Step 6 -->
                <div class="flex gap-4">
                  <div class="flex-shrink-0 w-8 h-8 bg-green-100 dark:bg-green-900/30 rounded-full flex items-center justify-center text-green-600 dark:text-green-400 font-bold">6</div>
                  <div>
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-1">JSON Dosyasını İndirin</h4>
                    <p class="text-gray-600 dark:text-gray-400">
                      Oluşturulan credential'ın yanındaki <strong>⬇ Download</strong> butonuna tıklayın. İndirilen <code>client_secret_xxx.json</code> dosyasını yukarıdaki "API Ekle" bölümünde kullanın.
                    </p>
                  </div>
                </div>
                
                <!-- Tips -->
                <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                  <h4 class="font-semibold text-blue-800 dark:text-blue-300 mb-2">💡 İpuçları</h4>
                  <ul class="text-blue-700 dark:text-blue-400 space-y-1 text-xs">
                    <li>• Her API günlük 10.000 kota sağlar (~6 video yükleme)</li>
                    <li>• Birden fazla API ekleyerek kota limitini artırabilirsiniz</li>
                    <li>• Farklı Google hesaplarıyla farklı projeler oluşturabilirsiniz</li>
                    <li>• Test kullanıcısı olarak eklenmeden OAuth çalışmaz</li>
                  </ul>
                </div>
                
              </div>
            </div>
          </div>

        </div>
      </main>
    </div>
  </div>

  <!-- Add Channel Modal -->
  <div x-show="addChannelModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="addChannelModal = false" x-cloak>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md overflow-hidden">
      <div class="px-6 py-4 border-b dark:border-gray-700 flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">➕ Yeni Kanal Ekle</h3>
        <button @click="addChannelModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">✕</button>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Kanal Adı *</label>
          <input type="text" x-model="channelForm.channel_title" class="w-full px-3 py-2 border dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white" placeholder="Video Kur Ana Kanalı">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Kanal ID (opsiyonel)</label>
          <input type="text" x-model="channelForm.channel_id" class="w-full px-3 py-2 border dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white" placeholder="UCxxxxxxx">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Açıklama (opsiyonel)</label>
          <textarea x-model="channelForm.description" class="w-full px-3 py-2 border dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white" rows="2" placeholder="Kanal hakkında not..."></textarea>
        </div>
      </div>
      <div class="px-6 py-4 border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex justify-end gap-3">
        <button @click="addChannelModal = false" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg">İptal</button>
        <button @click="addChannel()" :disabled="submitting" class="px-4 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50">
          <span x-show="!submitting">Kanal Ekle</span>
          <span x-show="submitting">⏳ Ekleniyor...</span>
        </button>
      </div>
    </div>
  </div>

  <!-- Edit Channel Category Modal -->
  <div x-show="editCategoryModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="editCategoryModal = false" x-cloak>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md overflow-hidden">
      <div class="px-6 py-4 border-b dark:border-gray-700 flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">📁 Varsayılan Kategori</h3>
        <button @click="editCategoryModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">✕</button>
      </div>
      <div class="p-6 space-y-4">
        <p class="text-sm text-gray-600 dark:text-gray-400">Bu kanaldan yüklenen videolar için varsayılan kategori seçin.</p>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Kategori</label>
          <select x-model="categoryForm.category_id" class="w-full px-3 py-2 border dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white">
            <option value="1">Film & Animasyon</option>
            <option value="2">Otomobil & Araçlar</option>
            <option value="10">Müzik</option>
            <option value="15">Hayvanlar</option>
            <option value="17">Spor</option>
            <option value="19">Seyahat & Etkinlikler</option>
            <option value="20">Oyun</option>
            <option value="22">İnsanlar & Bloglar</option>
            <option value="23">Komedi</option>
            <option value="24">Eğlence</option>
            <option value="25">Haber & Politika</option>
            <option value="26">Nasıl Yapılır & Stil</option>
            <option value="27">Eğitim</option>
            <option value="28">Bilim & Teknoloji</option>
          </select>
        </div>
      </div>
      <div class="px-6 py-4 border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex justify-end gap-3">
        <button @click="editCategoryModal = false" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg">İptal</button>
        <button @click="updateChannelCategory()" :disabled="submitting" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
          <span x-show="!submitting">✓ Kaydet</span>
          <span x-show="submitting">⏳ Kaydediliyor...</span>
        </button>
      </div>
    </div>
  </div>

  <!-- Add API Modal -->
  <div x-show="addApiModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4" @click.self="addApiModal = false" x-cloak>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md overflow-hidden">
      <div class="px-6 py-4 border-b dark:border-gray-700 flex items-center justify-between">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">🔑 Yeni API Ekle</h3>
        <button @click="addApiModal = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">✕</button>
      </div>
      <div class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Client Secrets Dosyası *</label>
          <input type="file" @change="handleApiFileSelect($event)" accept=".json" class="w-full px-3 py-2 border dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white">
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Google Cloud Console'dan indirdiğiniz JSON dosyası</p>
        </div>
        <div x-show="apiForm.project_id" class="p-3 bg-green-50 dark:bg-green-900/20 rounded-lg border border-green-200 dark:border-green-800">
          <div class="text-xs text-green-700 dark:text-green-400">✅ Project ID algılandı: <strong x-text="apiForm.project_id"></strong></div>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">API Adı *</label>
          <input type="text" x-model="apiForm.name" class="w-full px-3 py-2 border dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white" :placeholder="apiForm.project_id || 'Proje adı (otomatik dolar)'">
          <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">JSON'dan otomatik dolar, değiştirebilirsiniz</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Günlük Kota</label>
          <input type="number" x-model="apiForm.daily_quota" class="w-full px-3 py-2 border dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white" placeholder="10000">
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Not (opsiyonel)</label>
          <input type="text" x-model="apiForm.notes" class="w-full px-3 py-2 border dark:border-gray-600 rounded-lg text-sm dark:bg-gray-700 dark:text-white" placeholder="API hakkında not...">
        </div>
      </div>
      <div class="px-6 py-4 border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50 flex justify-end gap-3">
        <button @click="addApiModal = false" class="px-4 py-2 text-sm text-gray-600 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-gray-700 rounded-lg">İptal</button>
        <button @click="addApi()" :disabled="submitting || !apiForm.fileData" class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50">
          <span x-show="!submitting">API Ekle</span>
          <span x-show="submitting">⏳ Ekleniyor...</span>
        </button>
      </div>
    </div>
  </div>

</body>
</html>
