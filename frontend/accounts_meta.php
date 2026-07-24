<?php $page_title = 'Meta Hesapları - Facebook & Instagram'; $active_page = 'accounts_meta'; ?>
<!DOCTYPE html>
<html lang="tr" x-data="{ darkMode: localStorage.getItem('darkMode') === '1' }" :class="{ 'dark': darkMode }">
<head>
  <?php include __DIR__ . '/components/_head.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.0/dist/cdn.min.js" defer></script>
  <script>
  function metaAccountsApp() {
    return {
      sidebarOpen: false,
      darkMode: localStorage.getItem('darkMode') === '1',
      loading: true,
      saving: false,
      refreshing: false,
      error: '',
      notice: '',
      activeTab: 'connections',
      popupTimer: null,
      dashboard: {
        feature_flags: { meta_web_ui_enabled: false },
        apps: [],
        active_app_id: null,
        active_app: null,
        connections: [],
        settings: {
          active_connection_id: null,
          defaults: { instagram_account_id: null, facebook_page_id: null },
          feature_flags: { meta_web_ui_enabled: false }
        },
        accounts: { instagram: [], facebook: [] },
        guidance: { permissions: [], oauth_callback_url: '' }
      },
      appForm: {
        id: '',
        label: '',
        app_id: '',
        app_secret: '',
        redirect_uri: ''
      },
      socialStaging: {
        enabled: false,
        provider: 'r2',
        bucket: '',
        region: 'auto',
        endpointUrl: '',
        accessKeyId: '',
        secretAccessKey: '',
        publicBaseUrl: '',
        prefix: 'instagram',
        cleanupAfterUpload: true
      },

      get instagramAccounts() {
        return this.dashboard.accounts?.instagram || [];
      },
      get facebookPages() {
        return this.dashboard.accounts?.facebook || [];
      },
      get activeConnections() {
        return (this.dashboard.connections || []).filter(c => !!c.is_active);
      },

      toast(message, isError = false) {
        if (isError) {
          this.error = message;
          this.notice = '';
        } else {
          this.notice = message;
          this.error = '';
        }
      },

      async apiPost(body) {
        const r = await fetch('/api/meta_accounts.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(body)
        });
        return await r.json();
      },

      resetAppForm() {
        this.appForm = {
          id: '',
          label: '',
          app_id: '',
          app_secret: '',
          redirect_uri: this.dashboard.guidance?.oauth_callback_url || ''
        };
      },

      editApp(app) {
        this.appForm = {
          id: app.id || '',
          label: app.label || '',
          app_id: app.app_id || '',
          app_secret: '',
          redirect_uri: app.redirect_uri || (this.dashboard.guidance?.oauth_callback_url || '')
        };
        this.activeTab = 'apps';
      },

      async loadDashboard() {
        this.refreshing = true;
        this.error = '';
        try {
          const r = await fetch('/api/meta_accounts.php?action=dashboard');
          const d = await r.json();
          if (!d.success) throw new Error(d.error || 'Meta dashboard alınamadı');
          this.dashboard = d;
          if (!this.appForm.label && this.dashboard.guidance?.oauth_callback_url) {
            this.appForm.redirect_uri = this.dashboard.guidance.oauth_callback_url;
          }
        } catch (e) {
          this.toast(e.message || 'Meta dashboard hatası', true);
        } finally {
          this.loading = false;
          this.refreshing = false;
        }
      },

      async saveApp() {
        if (!this.appForm.label.trim() || !this.appForm.app_id.trim()) {
          this.toast('App adı ve App ID zorunlu', true);
          return;
        }
        if (!this.appForm.id && !this.appForm.app_secret.trim()) {
          this.toast('Yeni app için App Secret zorunlu', true);
          return;
        }

        this.saving = true;
        try {
          const d = await this.apiPost({
            action: 'save_app',
            id: this.appForm.id || null,
            label: this.appForm.label.trim(),
            app_id: this.appForm.app_id.trim(),
            app_secret: this.appForm.app_secret.trim(),
            redirect_uri: this.appForm.redirect_uri.trim(),
            set_active: true
          });
          if (!d.success) throw new Error(d.error || 'App kaydedilemedi');
          this.toast('Meta app kaydedildi');
          this.resetAppForm();
          await this.loadDashboard();
        } catch (e) {
          this.toast(e.message || 'App kaydetme hatası', true);
        } finally {
          this.saving = false;
        }
      },

      async deleteApp(app) {
        if (!confirm(`"${app.label}" app kaydını silmek istediğine emin misin?`)) return;
        this.saving = true;
        try {
          const d = await this.apiPost({ action: 'delete_app', app_id_ref: app.id });
          if (!d.success) throw new Error(d.error || 'App silinemedi');
          this.toast('Meta app silindi');
          await this.loadDashboard();
        } catch (e) {
          this.toast(e.message || 'App silme hatası', true);
        } finally {
          this.saving = false;
        }
      },

      async setActiveApp(app) {
        this.saving = true;
        try {
          const d = await this.apiPost({ action: 'set_active_app', app_id_ref: app.id });
          if (!d.success) throw new Error(d.error || 'Aktif app güncellenemedi');
          this.toast(`Aktif app: ${app.label}`);
          await this.loadDashboard();
        } catch (e) {
          this.toast(e.message || 'Aktif app güncelleme hatası', true);
        } finally {
          this.saving = false;
        }
      },

      startOAuth(app) {
        this.error = '';
        fetch('/api/meta_oauth.php?action=start&app_id=' + encodeURIComponent(app.id))
          .then(r => r.json())
          .then(d => {
            if (!d.success || !d.oauth_url) {
              throw new Error(d.error || 'OAuth başlatılamadı');
            }
            const popup = window.open(d.oauth_url, 'meta_oauth_popup', 'width=700,height=850');
            if (!popup) {
              throw new Error('Popup engellendi. Tarayıcıdan popup izni verin.');
            }
            this.toast('OAuth penceresi açıldı. Giriş sonrası otomatik yenilenecek.');
            if (this.popupTimer) clearInterval(this.popupTimer);
            this.popupTimer = setInterval(async () => {
              if (!popup || popup.closed) {
                clearInterval(this.popupTimer);
                this.popupTimer = null;
                await this.loadDashboard();
              }
            }, 1000);
          })
          .catch(e => this.toast(e.message || 'OAuth hatası', true));
      },

      async refreshConnection(connection) {
        this.saving = true;
        try {
          const d = await this.apiPost({ action: 'refresh_connection', connection_id: connection.id });
          if (!d.success) throw new Error(d.error || 'Connection yenilenemedi');
          this.toast(`${connection.owner_name || connection.id} yenilendi`);
          await this.loadDashboard();
        } catch (e) {
          this.toast(e.message || 'Connection yenileme hatası', true);
        } finally {
          this.saving = false;
        }
      },

      async refreshAllConnections() {
        this.saving = true;
        try {
          const d = await this.apiPost({ action: 'refresh_all_connections' });
          if (!d.success) throw new Error(d.error || 'Bağlantılar yenilenemedi');
          this.toast(d.message || 'Bağlantılar yenilendi');
          await this.loadDashboard();
        } catch (e) {
          this.toast(e.message || 'Toplu yenileme hatası', true);
        } finally {
          this.saving = false;
        }
      },

      async disconnectConnection(connection) {
        if (!confirm(`"${connection.owner_name || connection.id}" bağlantısını pasife almak istiyor musun?`)) return;
        this.saving = true;
        try {
          const d = await this.apiPost({ action: 'disconnect_connection', connection_id: connection.id });
          if (!d.success) throw new Error(d.error || 'Bağlantı kaldırılamadı');
          this.toast('Bağlantı pasife alındı');
          await this.loadDashboard();
        } catch (e) {
          this.toast(e.message || 'Bağlantı kaldırma hatası', true);
        } finally {
          this.saving = false;
        }
      },

      async setActiveConnection(connectionId) {
        this.saving = true;
        try {
          const d = await this.apiPost({ action: 'set_active_connection', connection_id: connectionId });
          if (!d.success) throw new Error(d.error || 'Aktif bağlantı güncellenemedi');
          this.toast('Aktif bağlantı güncellendi');
          await this.loadDashboard();
        } catch (e) {
          this.toast(e.message || 'Aktif bağlantı hatası', true);
        } finally {
          this.saving = false;
        }
      },

      async saveDefaults() {
        this.saving = true;
        try {
          const d = await this.apiPost({
            action: 'set_defaults',
            active_connection_id: this.dashboard.settings.active_connection_id || null,
            instagram_account_id: this.dashboard.settings.defaults.instagram_account_id || null,
            facebook_page_id: this.dashboard.settings.defaults.facebook_page_id || null
          });
          if (!d.success) throw new Error(d.error || 'Varsayılanlar kaydedilemedi');
          this.toast('Varsayılan hesap ayarları kaydedildi');
          await this.loadDashboard();
        } catch (e) {
          this.toast(e.message || 'Varsayılan kaydetme hatası', true);
        } finally {
          this.saving = false;
        }
      },

      async loadSocialStaging() {
        try {
          const r = await fetch('/api/config.php');
          const d = await r.json();
          const staging = d.socialStaging || {};
          this.socialStaging = Object.assign({}, this.socialStaging, staging);
          this.socialStaging.enabled = !!this.socialStaging.enabled;
          this.socialStaging.cleanupAfterUpload = this.socialStaging.cleanupAfterUpload !== false;
        } catch (e) {
          this.toast(e.message || 'Social staging ayarları alınamadı', true);
        }
      },

      async saveSocialStaging() {
        const staging = {
          enabled: !!this.socialStaging.enabled,
          provider: (this.socialStaging.provider || 'r2').trim().toLowerCase(),
          bucket: (this.socialStaging.bucket || '').trim(),
          region: (this.socialStaging.region || 'auto').trim(),
          endpointUrl: (this.socialStaging.endpointUrl || '').trim(),
          accessKeyId: (this.socialStaging.accessKeyId || '').trim(),
          secretAccessKey: (this.socialStaging.secretAccessKey || '').trim(),
          publicBaseUrl: (this.socialStaging.publicBaseUrl || '').trim(),
          prefix: (this.socialStaging.prefix || 'instagram').trim() || 'instagram',
          cleanupAfterUpload: this.socialStaging.cleanupAfterUpload !== false
        };

        if (staging.enabled) {
          if (!staging.bucket) {
            this.toast('Bucket zorunlu', true);
            return;
          }
          if (!staging.accessKeyId || !staging.secretAccessKey) {
            this.toast('Access Key ID ve Secret Access Key zorunlu', true);
            return;
          }
          if (staging.provider === 'r2' && !staging.endpointUrl) {
            this.toast('R2 için Endpoint URL zorunlu', true);
            return;
          }
          if (!staging.publicBaseUrl) {
            this.toast('Public Base URL zorunlu', true);
            return;
          }
        }

        this.saving = true;
        try {
          const currentResp = await fetch('/api/config.php');
          const currentConfig = await currentResp.json();
          const payload = Object.assign({}, currentConfig, { socialStaging: staging });

          const saveResp = await fetch('/api/config.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
          });
          const result = await saveResp.json();
          if (!result.success) throw new Error(result.error || 'Social staging kaydedilemedi');

          this.toast('Social staging ayarları kaydedildi');
          await this.loadSocialStaging();
        } catch (e) {
          this.toast(e.message || 'Social staging kaydetme hatası', true);
        } finally {
          this.saving = false;
        }
      },

      async toggleFeatureFlag() {
        const enabled = !this.dashboard.feature_flags.meta_web_ui_enabled;
        this.saving = true;
        try {
          const d = await this.apiPost({
            action: 'set_feature_flag',
            meta_web_ui_enabled: enabled
          });
          if (!d.success) throw new Error(d.error || 'Feature flag güncellenemedi');
          this.toast(`Meta Web UI v2 ${enabled ? 'aktif' : 'pasif'} yapıldı`);
          await this.loadDashboard();
        } catch (e) {
          this.toast(e.message || 'Feature flag hatası', true);
        } finally {
          this.saving = false;
        }
      },

      async updateAccount(platform, account, payload) {
        this.saving = true;
        try {
          const d = await this.apiPost(Object.assign({
            action: 'update_account',
            platform: platform,
            account_id: account.id
          }, payload));
          if (!d.success) throw new Error(d.error || 'Hesap güncellenemedi');
          await this.loadDashboard();
        } catch (e) {
          this.toast(e.message || 'Hesap güncelleme hatası', true);
        } finally {
          this.saving = false;
        }
      },

      async toggleAccountActive(platform, account) {
        await this.updateAccount(platform, account, { is_active: !account.is_active });
      },

      async editAccountLabel(platform, account) {
        const current = account.label || '';
        const value = prompt('Hesap etiketi (boş bırakılırsa temizlenir):', current);
        if (value === null) return;
        await this.updateAccount(platform, account, { label: value.trim() });
      },

      formatDate(value) {
        if (!value) return '-';
        const d = new Date(value);
        if (isNaN(d.getTime())) return value;
        return d.toLocaleString('tr-TR');
      },

      toggleDark() {
        this.darkMode = !this.darkMode;
        localStorage.setItem('darkMode', this.darkMode ? '1' : '0');
        document.documentElement.classList.toggle('dark', this.darkMode);
      },

      init() {
        document.documentElement.classList.toggle('dark', this.darkMode);
        this.resetAppForm();
        this.loadDashboard();
        this.loadSocialStaging();
        window.addEventListener('message', (event) => {
          if (event?.data?.source === 'meta_oauth') {
            this.loadDashboard();
          }
        });
      }
    };
  }
  </script>
</head>
<body class="bg-gray-100 dark:bg-gray-900 min-h-screen" x-data="metaAccountsApp()" x-init="init()">
  <div class="flex flex-col h-screen">
    <?php include __DIR__ . '/components/_header.php'; ?>
    <div class="flex flex-1 overflow-hidden">
      <?php include __DIR__ . '/components/_sidebar.php'; ?>
      <main class="flex-1 overflow-y-auto p-6 md:p-8">
        <div class="max-w-6xl mx-auto space-y-6">
          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
            <div>
              <h1 class="text-2xl font-bold text-gray-900 dark:text-white">📘 Meta Hesapları</h1>
              <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Meta App, OAuth bağlantıları ve hesap ayarlarını tek ekrandan yönetin.</p>
            </div>
            <div class="flex gap-2">
              <button @click="toggleFeatureFlag()" class="px-3 py-2 text-xs rounded-lg border border-gray-300 dark:border-gray-600 dark:text-gray-200">
                <span x-text="dashboard.feature_flags.meta_web_ui_enabled ? 'V2 Açık (Kapat)' : 'V2 Kapalı (Aç)'"></span>
              </button>
              <button @click="loadDashboard()" :disabled="refreshing" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700 disabled:opacity-50">
                <span x-show="!refreshing">↻ Yenile</span>
                <span x-show="refreshing">Yükleniyor...</span>
              </button>
            </div>
          </div>

          <template x-if="error">
            <div class="rounded-xl border border-red-200 bg-red-50 dark:bg-red-900/20 dark:border-red-900 p-4 text-sm text-red-700 dark:text-red-300" x-text="error"></div>
          </template>
          <template x-if="notice">
            <div class="rounded-xl border border-green-200 bg-green-50 dark:bg-green-900/20 dark:border-green-900 p-4 text-sm text-green-700 dark:text-green-300" x-text="notice"></div>
          </template>

          <template x-if="loading">
            <div class="bg-white dark:bg-gray-800 rounded-xl border dark:border-gray-700 p-8 text-center text-gray-600 dark:text-gray-300">Yükleniyor...</div>
          </template>

          <template x-if="!loading">
            <div class="space-y-6">
              <div class="flex flex-wrap gap-2 border-b dark:border-gray-700 pb-2">
                <button @click="activeTab='apps'" :class="activeTab==='apps' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200'" class="px-3 py-2 rounded-lg text-sm border dark:border-gray-700">Meta App Ayarları</button>
                <button @click="activeTab='connections'" :class="activeTab==='connections' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200'" class="px-3 py-2 rounded-lg text-sm border dark:border-gray-700">Bağlı Hesaplar</button>
                <button @click="activeTab='defaults'" :class="activeTab==='defaults' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200'" class="px-3 py-2 rounded-lg text-sm border dark:border-gray-700">Hesap Varsayılanları</button>
                <button @click="activeTab='staging'" :class="activeTab==='staging' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200'" class="px-3 py-2 rounded-lg text-sm border dark:border-gray-700">Social Staging</button>
                <button @click="activeTab='guide'" :class="activeTab==='guide' ? 'bg-blue-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200'" class="px-3 py-2 rounded-lg text-sm border dark:border-gray-700">Kurulum Rehberi</button>
              </div>

              <div x-show="activeTab==='apps'" class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="lg:col-span-1 bg-white dark:bg-gray-800 rounded-xl border dark:border-gray-700 p-5 space-y-3">
                  <h3 class="font-semibold text-gray-900 dark:text-white">Meta App Ekle / Düzenle</h3>
                  <input x-model="appForm.label" type="text" placeholder="Uygulama adı (örn: Client A)" class="w-full px-3 py-2 rounded-lg border dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm">
                  <input x-model="appForm.app_id" type="text" placeholder="Meta App ID" class="w-full px-3 py-2 rounded-lg border dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm">
                  <input x-model="appForm.app_secret" type="password" placeholder="Meta App Secret (düzenlemede boş bırakılabilir)" class="w-full px-3 py-2 rounded-lg border dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm">
                  <input x-model="appForm.redirect_uri" type="text" placeholder="Redirect URI" class="w-full px-3 py-2 rounded-lg border dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm">
                  <div class="flex gap-2">
                    <button @click="saveApp()" :disabled="saving" class="flex-1 px-3 py-2 rounded-lg bg-blue-600 text-white text-sm hover:bg-blue-700">Kaydet</button>
                    <button @click="resetAppForm()" type="button" class="px-3 py-2 rounded-lg border text-sm dark:border-gray-600 dark:text-gray-200">Temizle</button>
                  </div>
                </div>

                <div class="lg:col-span-2 bg-white dark:bg-gray-800 rounded-xl border dark:border-gray-700 overflow-hidden">
                  <div class="px-5 py-4 border-b dark:border-gray-700 flex items-center justify-between">
                    <h3 class="font-semibold text-gray-900 dark:text-white">Kayıtlı Meta App'ler</h3>
                    <span class="text-xs px-2 py-1 rounded-full bg-gray-100 dark:bg-gray-700 dark:text-gray-200" x-text="`Toplam: ${dashboard.apps.length}`"></span>
                  </div>
                  <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                      <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                          <th class="px-4 py-3 text-left">Ad</th>
                          <th class="px-4 py-3 text-left">App ID</th>
                          <th class="px-4 py-3 text-left">Redirect URI</th>
                          <th class="px-4 py-3 text-left">İşlemler</th>
                        </tr>
                      </thead>
                      <tbody>
                        <template x-for="app in dashboard.apps" :key="app.id">
                          <tr class="border-t dark:border-gray-700">
                            <td class="px-4 py-3 text-gray-900 dark:text-white font-medium">
                              <div class="flex items-center gap-2">
                                <span x-text="app.label"></span>
                                <span x-show="dashboard.active_app_id === app.id" class="text-[10px] px-2 py-0.5 rounded-full bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">Aktif</span>
                              </div>
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300 font-mono text-xs" x-text="app.app_id_masked"></td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300 text-xs" x-text="app.redirect_uri || '-'"></td>
                            <td class="px-4 py-3">
                              <div class="flex flex-wrap gap-1">
                                <button @click="editApp(app)" class="px-2 py-1 text-xs rounded bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">Düzenle</button>
                                <button @click="setActiveApp(app)" class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">Aktif Yap</button>
                                <button @click="startOAuth(app)" class="px-2 py-1 text-xs rounded bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">OAuth Bağla</button>
                                <button @click="deleteApp(app)" class="px-2 py-1 text-xs rounded bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">Sil</button>
                              </div>
                            </td>
                          </tr>
                        </template>
                        <tr x-show="dashboard.apps.length === 0">
                          <td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Henüz kayıtlı Meta app yok.</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>

              <div x-show="activeTab==='connections'" class="space-y-4">
                <div class="bg-white dark:bg-gray-800 rounded-xl border dark:border-gray-700 p-4 flex flex-wrap items-center justify-between gap-3">
                  <div class="text-sm text-gray-700 dark:text-gray-300">
                    Aktif bağlantı: <strong x-text="dashboard.settings.active_connection_id || 'Yok'"></strong>
                  </div>
                  <button @click="refreshAllConnections()" :disabled="saving" class="px-3 py-2 rounded-lg bg-blue-600 text-white text-sm hover:bg-blue-700">Tümünü Yenile</button>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-xl border dark:border-gray-700 overflow-hidden">
                  <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                      <thead class="bg-gray-50 dark:bg-gray-900/40">
                        <tr>
                          <th class="px-4 py-3 text-left">Bağlantı</th>
                          <th class="px-4 py-3 text-left">App</th>
                          <th class="px-4 py-3 text-left">Hesaplar</th>
                          <th class="px-4 py-3 text-left">Son Senkron</th>
                          <th class="px-4 py-3 text-left">Durum</th>
                          <th class="px-4 py-3 text-left">İşlemler</th>
                        </tr>
                      </thead>
                      <tbody>
                        <template x-for="c in dashboard.connections" :key="c.id">
                          <tr class="border-t dark:border-gray-700">
                            <td class="px-4 py-3 text-gray-900 dark:text-white font-medium" x-text="c.owner_name || c.id"></td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300" x-text="c.app_label || '-'"></td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                              <span x-text="`IG: ${c.instagram_count || 0}, FB: ${c.facebook_count || 0}`"></span>
                            </td>
                            <td class="px-4 py-3 text-gray-700 dark:text-gray-300" x-text="formatDate(c.last_sync_at)"></td>
                            <td class="px-4 py-3">
                              <span class="text-xs px-2 py-1 rounded-full" :class="c.is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'" x-text="c.is_active ? 'Aktif' : 'Pasif'"></span>
                            </td>
                            <td class="px-4 py-3">
                              <div class="flex flex-wrap gap-1">
                                <button @click="setActiveConnection(c.id)" class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">Aktif Seç</button>
                                <button @click="refreshConnection(c)" class="px-2 py-1 text-xs rounded bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">Senkronize</button>
                                <button @click="disconnectConnection(c)" class="px-2 py-1 text-xs rounded bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-300">Bağlantıyı Kapat</button>
                              </div>
                            </td>
                          </tr>
                        </template>
                        <tr x-show="dashboard.connections.length === 0">
                          <td colspan="6" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Henüz OAuth bağlantısı yok. Önce Meta App ekleyip OAuth Bağla butonunu kullanın.</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>

              <div x-show="activeTab==='defaults'" class="space-y-4">
                <div class="bg-white dark:bg-gray-800 rounded-xl border dark:border-gray-700 p-5 space-y-4">
                  <h3 class="font-semibold text-gray-900 dark:text-white">Varsayılan Yayın Hesapları</h3>
                  <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                      <label class="block text-xs mb-1 text-gray-600 dark:text-gray-400">Aktif Connection</label>
                      <select x-model="dashboard.settings.active_connection_id" class="w-full px-3 py-2 rounded-lg border dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm">
                        <option value="">Seçilmedi</option>
                        <template x-for="c in activeConnections" :key="'active-' + c.id">
                          <option :value="c.id" x-text="c.owner_name || c.id"></option>
                        </template>
                      </select>
                    </div>
                    <div>
                      <label class="block text-xs mb-1 text-gray-600 dark:text-gray-400">Varsayılan Instagram</label>
                      <select x-model="dashboard.settings.defaults.instagram_account_id" class="w-full px-3 py-2 rounded-lg border dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm">
                        <option value="">Seçilmedi</option>
                        <template x-for="ig in instagramAccounts" :key="'ig-default-' + ig.id">
                          <option :value="ig.id" x-text="(ig.label ? '[' + ig.label + '] ' : '') + '@' + (ig.username || ig.id)"></option>
                        </template>
                      </select>
                    </div>
                    <div>
                      <label class="block text-xs mb-1 text-gray-600 dark:text-gray-400">Varsayılan Facebook</label>
                      <select x-model="dashboard.settings.defaults.facebook_page_id" class="w-full px-3 py-2 rounded-lg border dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm">
                        <option value="">Seçilmedi</option>
                        <template x-for="fb in facebookPages" :key="'fb-default-' + fb.id">
                          <option :value="fb.id" x-text="(fb.label ? '[' + fb.label + '] ' : '') + (fb.name || fb.id)"></option>
                        </template>
                      </select>
                    </div>
                  </div>
                  <button @click="saveDefaults()" :disabled="saving" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm hover:bg-blue-700">Varsayılanları Kaydet</button>
                </div>

                <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                  <div class="bg-white dark:bg-gray-800 rounded-xl border dark:border-gray-700 overflow-hidden">
                    <div class="px-4 py-3 border-b dark:border-gray-700 font-semibold text-gray-900 dark:text-white">Instagram Hesapları</div>
                    <div class="overflow-x-auto">
                      <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900/40">
                          <tr>
                            <th class="px-4 py-3 text-left">Hesap</th>
                            <th class="px-4 py-3 text-left">Connection</th>
                            <th class="px-4 py-3 text-left">Durum</th>
                            <th class="px-4 py-3 text-left">İşlem</th>
                          </tr>
                        </thead>
                        <tbody>
                          <template x-for="ig in instagramAccounts" :key="'ig-row-' + ig.id">
                            <tr class="border-t dark:border-gray-700">
                              <td class="px-4 py-3 text-gray-900 dark:text-white">
                                <div class="font-medium" x-text="'@' + (ig.username || ig.id)"></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400" x-text="ig.label || '-'"></div>
                              </td>
                              <td class="px-4 py-3 text-gray-700 dark:text-gray-300" x-text="ig.connection_label || ig.connection_id || '-'"></td>
                              <td class="px-4 py-3">
                                <span class="text-xs px-2 py-1 rounded-full" :class="ig.is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'" x-text="ig.is_active ? 'Aktif' : 'Pasif'"></span>
                                <span x-show="ig.is_default" class="ml-1 text-[10px] px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">Varsayılan</span>
                              </td>
                              <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                  <button @click="toggleAccountActive('instagram', ig)" class="px-2 py-1 text-xs rounded bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">Aktif/Pasif</button>
                                  <button @click="editAccountLabel('instagram', ig)" class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">Etiket</button>
                                </div>
                              </td>
                            </tr>
                          </template>
                          <tr x-show="instagramAccounts.length === 0">
                            <td colspan="4" class="px-4 py-5 text-center text-gray-500 dark:text-gray-400">Instagram hesabı bulunamadı.</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>

                  <div class="bg-white dark:bg-gray-800 rounded-xl border dark:border-gray-700 overflow-hidden">
                    <div class="px-4 py-3 border-b dark:border-gray-700 font-semibold text-gray-900 dark:text-white">Facebook Sayfaları</div>
                    <div class="overflow-x-auto">
                      <table class="w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-900/40">
                          <tr>
                            <th class="px-4 py-3 text-left">Sayfa</th>
                            <th class="px-4 py-3 text-left">Connection</th>
                            <th class="px-4 py-3 text-left">Durum</th>
                            <th class="px-4 py-3 text-left">İşlem</th>
                          </tr>
                        </thead>
                        <tbody>
                          <template x-for="fb in facebookPages" :key="'fb-row-' + fb.id">
                            <tr class="border-t dark:border-gray-700">
                              <td class="px-4 py-3 text-gray-900 dark:text-white">
                                <div class="font-medium" x-text="fb.name || fb.id"></div>
                                <div class="text-xs text-gray-500 dark:text-gray-400" x-text="fb.label || '-'"></div>
                              </td>
                              <td class="px-4 py-3 text-gray-700 dark:text-gray-300" x-text="fb.connection_label || fb.connection_id || '-'"></td>
                              <td class="px-4 py-3">
                                <span class="text-xs px-2 py-1 rounded-full" :class="fb.is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300' : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'" x-text="fb.is_active ? 'Aktif' : 'Pasif'"></span>
                                <span x-show="fb.is_default" class="ml-1 text-[10px] px-2 py-0.5 rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300">Varsayılan</span>
                              </td>
                              <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-1">
                                  <button @click="toggleAccountActive('facebook', fb)" class="px-2 py-1 text-xs rounded bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">Aktif/Pasif</button>
                                  <button @click="editAccountLabel('facebook', fb)" class="px-2 py-1 text-xs rounded bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">Etiket</button>
                                </div>
                              </td>
                            </tr>
                          </template>
                          <tr x-show="facebookPages.length === 0">
                            <td colspan="4" class="px-4 py-5 text-center text-gray-500 dark:text-gray-400">Facebook sayfası bulunamadı.</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>

              <div x-show="activeTab==='staging'" class="space-y-4">
                <div class="bg-white dark:bg-gray-800 rounded-xl border dark:border-gray-700 p-5">
                  <div class="flex items-center justify-between mb-2">
                    <h3 class="font-semibold text-gray-900 dark:text-white">📦 Instagram/Facebook Social Staging</h3>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                      <input type="checkbox" x-model="socialStaging.enabled" class="rounded border-gray-300 dark:border-gray-600">
                      <span>Etkin</span>
                    </label>
                  </div>
                  <p class="text-xs text-gray-600 dark:text-gray-400 mb-4">
                    Meta upload için local videoyu object storage'a yükleyip public URL üzerinden paylaşır.
                  </p>

                  <div :class="!socialStaging.enabled && 'opacity-50 pointer-events-none'" class="space-y-3">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                      <div>
                        <label class="block text-xs mb-1 text-gray-600 dark:text-gray-400">Provider</label>
                        <select x-model="socialStaging.provider" class="w-full px-3 py-2 rounded-lg border dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm">
                          <option value="r2">Cloudflare R2</option>
                          <option value="s3">Amazon S3</option>
                        </select>
                      </div>
                      <div>
                        <label class="block text-xs mb-1 text-gray-600 dark:text-gray-400">Bucket</label>
                        <input x-model="socialStaging.bucket" type="text" placeholder="my-bucket" class="w-full px-3 py-2 rounded-lg border dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm">
                      </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                      <div>
                        <label class="block text-xs mb-1 text-gray-600 dark:text-gray-400">Region</label>
                        <input x-model="socialStaging.region" type="text" placeholder="auto / eu-central-1" class="w-full px-3 py-2 rounded-lg border dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm">
                      </div>
                      <div>
                        <label class="block text-xs mb-1 text-gray-600 dark:text-gray-400">Prefix</label>
                        <input x-model="socialStaging.prefix" type="text" placeholder="instagram" class="w-full px-3 py-2 rounded-lg border dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm">
                      </div>
                    </div>

                    <div x-show="socialStaging.provider === 'r2'">
                      <label class="block text-xs mb-1 text-gray-600 dark:text-gray-400">Endpoint URL (R2 zorunlu)</label>
                      <input x-model="socialStaging.endpointUrl" type="text" placeholder="https://&lt;account&gt;.r2.cloudflarestorage.com" class="w-full px-3 py-2 rounded-lg border dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm">
                    </div>

                    <div>
                      <label class="block text-xs mb-1 text-gray-600 dark:text-gray-400">Public Base URL</label>
                      <input x-model="socialStaging.publicBaseUrl" type="text" placeholder="https://cdn.example.com" class="w-full px-3 py-2 rounded-lg border dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                      <div>
                        <label class="block text-xs mb-1 text-gray-600 dark:text-gray-400">Access Key ID</label>
                        <input x-model="socialStaging.accessKeyId" type="password" placeholder="AKIA... / R2 key" class="w-full px-3 py-2 rounded-lg border dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm">
                      </div>
                      <div>
                        <label class="block text-xs mb-1 text-gray-600 dark:text-gray-400">Secret Access Key</label>
                        <input x-model="socialStaging.secretAccessKey" type="password" placeholder="********" class="w-full px-3 py-2 rounded-lg border dark:border-gray-600 dark:bg-gray-900 dark:text-white text-sm">
                      </div>
                    </div>

                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                      <input type="checkbox" x-model="socialStaging.cleanupAfterUpload" class="rounded border-gray-300 dark:border-gray-600">
                      <span>Upload sonrası staged objeyi sil</span>
                    </label>
                  </div>

                  <div class="mt-4">
                    <button @click="saveSocialStaging()" :disabled="saving" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm hover:bg-blue-700 disabled:opacity-50">
                      <span x-show="!saving">Social Staging Kaydet</span>
                      <span x-show="saving">Kaydediliyor...</span>
                    </button>
                  </div>
                </div>
              </div>

              <div x-show="activeTab==='guide'" class="bg-white dark:bg-gray-800 rounded-xl border dark:border-gray-700 p-5 space-y-4 text-sm">
                <h3 class="font-semibold text-gray-900 dark:text-white">Kurulum ve OAuth Bilgilendirmesi</h3>
                <ol class="list-decimal pl-5 space-y-2 text-gray-700 dark:text-gray-300">
                  <li>Meta Developer hesabında uygulama oluşturun ve <strong>Instagram Graph API</strong> + <strong>Facebook Login</strong> ürünlerini ekleyin.</li>
                  <li>Bu sayfadaki <strong>Meta App Ayarları</strong> bölümünden App ID, App Secret ve Redirect URI bilgisini kaydedin.</li>
                  <li>Kayıtlı app satırındaki <strong>OAuth Bağla</strong> butonuna tıklayıp yetki adımlarını tamamlayın.</li>
                  <li>Bağlantı başarılı olduğunda Instagram/Facebook hesapları otomatik senkronize edilir ve kuyruk ekranında seçim için görünür.</li>
                </ol>
                <div class="p-3 rounded-lg border border-amber-200 bg-amber-50 dark:bg-amber-900/20 dark:border-amber-900 text-amber-800 dark:text-amber-300">
                  <div class="font-semibold mb-1">Redirect URI (Meta uygulamasına eklenmeli)</div>
                  <code class="text-xs break-all" x-text="dashboard.guidance.oauth_callback_url || 'http://localhost:8000/api/meta_oauth.php'"></code>
                </div>
                <div class="p-3 rounded-lg border border-blue-200 bg-blue-50 dark:bg-blue-900/20 dark:border-blue-900 text-blue-800 dark:text-blue-300">
                  <div class="font-semibold mb-1">Gerekli izinler</div>
                  <template x-for="perm in (dashboard.guidance.permissions || [])" :key="perm">
                    <span class="inline-block mr-2 mb-1 px-2 py-1 rounded bg-blue-100 dark:bg-blue-900/40 text-xs" x-text="perm"></span>
                  </template>
                </div>
              </div>
            </div>
          </template>
        </div>
      </main>
    </div>
  </div>
</body>
</html>
