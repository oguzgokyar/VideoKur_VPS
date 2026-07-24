<?php $page_title = 'Script Yönetimi - YouTube Shorts Otomasyon'; $active_page = 'scripts'; ?>
<!DOCTYPE html>
<html lang="tr">
  <head>
  <?php include __DIR__ . '/components/_head.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.0/dist/cdn.min.js" defer></script>
  <style>
    .line-clamp-2 {
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }
  </style>
</head>
<body class="bg-gray-100 min-h-screen" x-data="scriptManager()">
  <div class="flex flex-col h-screen">
    <?php include __DIR__ . '/components/_header.php'; ?>

    <div class="flex flex-1 overflow-hidden">
      <?php include __DIR__ . '/components/_sidebar.php'; ?>

      <main class="flex-1 overflow-y-auto p-6 md:p-8">
        <div class="max-w-6xl mx-auto">
          <div class="bg-white rounded-lg border border-gray-200 p-3 mb-4 space-y-2">
            <div class="flex flex-col md:flex-row gap-2">
              <input
                x-model.trim="searchQuery"
                type="text"
                placeholder="Script ara..."
                class="flex-1 border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"
              >
              <button
                @click="sortOrder = sortOrder === 'updated_desc' ? 'name_asc' : 'updated_desc'"
                class="px-3 py-2 rounded-md border border-gray-300 bg-white text-sm hover:bg-gray-50 transition"
                x-text="sortOrder === 'updated_desc' ? 'Son Güncellenen' : 'Ada Göre'"
              ></button>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
              <div class="flex flex-wrap gap-1.5 items-center">
                <div class="flex flex-wrap gap-1.5">
                  <button @click="contentTypeFilter = 'all'" class="px-2.5 py-1 rounded-md border transition"
                    :class="contentTypeFilter === 'all' ? 'bg-gray-900 border-gray-900 text-white' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'">Kategori: Tümü</button>
                  <template x-for="type in uniqueContentTypes" :key="type">
                    <button @click="contentTypeFilter = type" class="px-2.5 py-1 rounded-md border transition"
                      :class="contentTypeFilter === type ? 'bg-gray-900 border-gray-900 text-white' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'"
                      x-text="type"></button>
                  </template>
                </div>
                <div class="flex flex-wrap gap-1.5">
                  <button @click="videoTypeFilter = 'all'" class="px-2.5 py-1 rounded-md border transition"
                    :class="videoTypeFilter === 'all' ? 'bg-gray-900 border-gray-900 text-white' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'">Tip: Tümü</button>
                  <button @click="videoTypeFilter = 'short'" class="px-2.5 py-1 rounded-md border transition"
                    :class="videoTypeFilter === 'short' ? 'bg-gray-900 border-gray-900 text-white' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'">Short</button>
                  <button @click="videoTypeFilter = 'square'" class="px-2.5 py-1 rounded-md border transition"
                    :class="videoTypeFilter === 'square' ? 'bg-gray-900 border-gray-900 text-white' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'">Kare</button>
                  <button @click="videoTypeFilter = 'wide'" class="px-2.5 py-1 rounded-md border transition"
                    :class="videoTypeFilter === 'wide' ? 'bg-gray-900 border-gray-900 text-white' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'">Geniş</button>
                </div>
              </div>
              <div class="flex items-center gap-3">
                <span class="text-gray-500"><span x-text="filteredScripts.length"></span> / <span x-text="scripts.length"></span></span>
                <button @click="clearFilters()" class="text-gray-500 hover:text-gray-700 underline">Temizle</button>
              </div>
            </div>
          </div>

          <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-4">
            <div class="bg-white rounded-lg border border-gray-200 p-4">
              <h3 class="font-semibold text-gray-800 mb-3">📂 Kategoriler</h3>
              <div class="flex gap-2 mb-3">
                <input x-model.trim="newCategoryName" type="text" placeholder="Yeni kategori adı"
                  class="flex-1 border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                <button @click="addCategory()" class="px-3 py-2 rounded-md bg-blue-600 text-white text-sm hover:bg-blue-700 transition">Ekle</button>
              </div>
              <div class="flex flex-wrap gap-2">
                <template x-for="cat in categories" :key="cat.id">
                  <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-md border border-gray-200 text-sm text-gray-700 bg-gray-50">
                    <span x-text="cat.name"></span>
                    <button @click="deleteCategory(cat.id)" class="text-red-500 hover:text-red-700 text-xs">Sil</button>
                  </span>
                </template>
              </div>
            </div>

            <div class="bg-white rounded-lg border border-gray-200 p-4">
              <h3 class="font-semibold text-gray-800 mb-2">🎵 Müzik Yönetimi</h3>
              <p class="text-sm text-gray-600 mb-3">Müzik ekleme/silme işlemleri ayrı sayfaya taşındı.</p>
              <a href="music.php" class="inline-flex items-center px-3 py-2 rounded-md bg-indigo-600 text-white text-sm hover:bg-indigo-700 transition">Müzik Yönetimine Git</a>
            </div>
          </div>

          <!-- Scripts List -->
          <div class="bg-white rounded-lg border border-gray-200 overflow-hidden">
            <template x-if="filteredScripts.length > 0">
              <div class="divide-y divide-gray-100">
                <template x-for="script in filteredScripts" :key="script.id">
                  <div class="p-3">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                      <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 mb-1">
                          <h3 class="font-semibold text-gray-800 truncate" x-text="script.name"></h3>
                        </div>
                        <p class="text-sm text-gray-500 line-clamp-2" x-text="script.description || 'Açıklama yok'"></p>
                        <p class="text-xs text-gray-500 mt-1">
                          <span x-text="script.contentType"></span> ·
                          <span x-text="videoTypeLabel(script.videoType)"></span> ·
                          <span x-text="script.maxDuration + 's'"></span> ·
                          <span x-text="getPromptLineCount(script.prompt) + ' satır'"></span>
                        </p>
                      </div>
                      <div class="flex gap-2 md:flex-shrink-0">
                        <button @click="editScript(script)" class="border border-gray-300 hover:bg-gray-50 text-gray-700 px-3 py-1.5 rounded-md text-sm transition">
                          Düzenle
                        </button>
                        <button @click="deleteScript(script.id)" class="border border-gray-300 hover:bg-gray-50 text-gray-700 px-3 py-1.5 rounded-md text-sm transition">
                          Sil
                        </button>
                      </div>
                    </div>
                  </div>
                </template>
              </div>
            </template>

            <template x-if="filteredScripts.length === 0">
              <div class="p-12 text-center">
                <p class="text-gray-500">Filtreye uygun script bulunamadı.</p>
              </div>
            </template>
          </div>
        </div>
      </main>
    </div>

    <?php include __DIR__ . '/components/_footer.php'; ?>
  </div>

  <!-- Modal: Yeni/Düzenle Script -->
  <div x-show="modalOpen" @click.self="closeModal()" class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" x-transition>
    <div class="bg-white rounded-xl shadow-2xl max-w-3xl w-full max-h-[90vh] overflow-y-auto">
      <div class="sticky top-0 bg-white border-b p-4 flex justify-between items-center">
        <h2 class="text-xl font-bold text-gray-800" x-text="editMode ? 'Script Düzenle' : 'Yeni Script'"></h2>
        <button @click="closeModal()" class="text-gray-400 hover:text-gray-600">
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
      </div>

      <form @submit.prevent="saveScript()" class="p-6 space-y-4">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Script Adı *</label>
          <input type="text" x-model="form.name" required class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" placeholder="Örn: Haber Scripti">
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Açıklama</label>
          <input type="text" x-model="form.description" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none" placeholder="Script hakkında kısa açıklama">
        </div>

        <div class="grid grid-cols-2 gap-4">
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Kategori</label>
            <select x-model="form.categoryId" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
              <template x-for="cat in categories" :key="cat.id">
                <option :value="cat.id" x-text="cat.name"></option>
              </template>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">Maksimum Süre (sn)</label>
            <input type="number" x-model.number="form.maxDuration" min="10" max="300" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
          </div>
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Video Tipi</label>
          <div class="flex flex-wrap gap-2">
            <button type="button" @click="form.videoType='short'" class="px-3 py-1.5 rounded-full border text-sm transition"
              :class="form.videoType==='short' ? 'bg-blue-600 border-blue-600 text-white' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'">Short (9:16)</button>
            <button type="button" @click="form.videoType='square'" class="px-3 py-1.5 rounded-full border text-sm transition"
              :class="form.videoType==='square' ? 'bg-blue-600 border-blue-600 text-white' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'">Kare (1:1)</button>
            <button type="button" @click="form.videoType='wide'" class="px-3 py-1.5 rounded-full border text-sm transition"
              :class="form.videoType==='wide' ? 'bg-blue-600 border-blue-600 text-white' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'">Geniş (16:9)</button>
          </div>
        </div>

        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-2">Prompt *</label>
          <textarea x-model="form.prompt" required rows="16" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none font-mono text-sm" placeholder="AI'ya gönderilecek prompt. {{TITLE}}, {{TEXT}}, {{MAX_DURATION}} değişkenlerini kullanabilirsiniz."></textarea>
          <p class="text-xs text-gray-500 mt-1">💡 Kullanılabilir değişkenler: {{TITLE}} (başlık), {{TEXT}} (metin), {{MAX_DURATION}} (süre)</p>
        </div>

        <div class="flex gap-3 pt-4 border-t">
          <button type="button" @click="closeModal()" class="flex-1 bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2.5 rounded-lg font-semibold transition">
            İptal
          </button>
          <button type="submit" :disabled="saving" class="flex-1 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2.5 rounded-lg font-semibold transition disabled:opacity-50">
            <span x-show="!saving" x-text="editMode ? 'Güncelle' : 'Oluştur'"></span>
            <span x-show="saving">Kaydediliyor...</span>
          </button>
        </div>
      </form>
    </div>
  </div>

  <script>
  function scriptManager() {
    return {
      // App State
      sidebarOpen: false, sidebarCollapsed: localStorage.getItem('sidebarCollapsed') === '1',
      darkMode: localStorage.getItem('darkMode') === '1',
      
      // Scripts State
      scripts: [],
      categories: [],
      newCategoryName: '',
      searchQuery: '',
      contentTypeFilter: 'all',
      videoTypeFilter: 'all',
      sortOrder: 'updated_desc',
      modalOpen: false,
      editMode: false,
      saving: false,
      form: {
        id: '',
        name: '',
        description: '',
        categoryId: 'genel',
        contentType: 'haber',
        videoType: 'short',
        maxDuration: 55,
        prompt: ''
      },

      init() {
        this.loadScripts();
      },
      get uniqueContentTypes() {
        const types = this.scripts.map(s => (s.contentType || '').trim()).filter(Boolean);
        return [...new Set(types)].sort((a, b) => a.localeCompare(b, 'tr'));
      },
      get filteredScripts() {
        const q = this.searchQuery.trim().toLowerCase();
        let items = this.scripts.filter(script => {
          const name = (script.name || '').toLowerCase();
          const desc = (script.description || '').toLowerCase();
          const type = (script.contentType || '').toLowerCase();
          const videoType = (script.videoType || 'short').toLowerCase();
          const matchText = !q || name.includes(q) || desc.includes(q) || type.includes(q);
          if (!matchText) return false;
          if (this.contentTypeFilter !== 'all' && (script.contentType || '') !== this.contentTypeFilter) return false;
          if (this.videoTypeFilter !== 'all' && videoType !== this.videoTypeFilter) return false;
          return true;
        });
        items.sort((a, b) => {
          if (this.sortOrder === 'name_asc') {
            return (a.name || '').localeCompare((b.name || ''), 'tr');
          }
          const da = new Date(a.updatedAt || a.createdAt || 0).getTime();
          const db = new Date(b.updatedAt || b.createdAt || 0).getTime();
          return db - da;
        });
        return items;
      },
      clearFilters() {
        this.searchQuery = '';
        this.contentTypeFilter = 'all';
        this.videoTypeFilter = 'all';
        this.sortOrder = 'updated_desc';
      },
      videoTypeLabel(v) {
        if (v === 'square') return 'Kare';
        if (v === 'wide') return 'Geniş';
        return 'Short';
      },
      getPromptLineCount(prompt) {
        if (!prompt) return 0;
        return prompt.split('\n').filter(Boolean).length;
      },
      formatDate(value) {
        if (!value) return '-';
        const d = new Date(value);
        if (Number.isNaN(d.getTime())) return '-';
        return d.toLocaleString('tr-TR');
      },
      
      toggleDark() {
        this.darkMode = !this.darkMode;
        localStorage.setItem('darkMode', this.darkMode ? '1' : '0');
        document.documentElement.classList.toggle('dark', this.darkMode);
      },

      async loadScripts() {
        const res = await fetch('/api/scripts.php');
        const data = await res.json();
        this.scripts = data.scripts || [];
        this.categories = data.categories || [];
        if (!this.categories.length) {
          this.categories = [{ id: 'genel', name: 'genel' }];
        }
      },
      async addCategory() {
        if (!this.newCategoryName) return;
        const res = await fetch('/api/scripts.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'create_category', name: this.newCategoryName })
        });
        if (res.ok) {
          this.newCategoryName = '';
          await this.loadScripts();
        } else {
          alert('Kategori eklenemedi');
        }
      },
      async deleteCategory(id) {
        if (!confirm('Kategori silinsin mi?')) return;
        const res = await fetch('/api/scripts.php', {
          method: 'DELETE',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'delete_category', id })
        });
        if (res.ok) {
          await this.loadScripts();
        } else {
          const d = await res.json().catch(() => ({}));
          alert(d.error || 'Kategori silinemedi');
        }
      },
      openNewScriptModal() {
        this.editMode = false;
        this.form = {
          id: '',
          name: '',
          description: '',
          categoryId: this.categories[0]?.id || 'genel',
          contentType: 'haber',
          videoType: 'short',
          maxDuration: 55,
          prompt: `Sen bir profesyonel YouTube Shorts video scripti yazarısın.
Aşağıdaki haber içeriğinden maksimum {{MAX_DURATION}} saniyelik, dikkat çekici bir Türkçe video scripti yaz.

Kurallar:
- Kısa, vurucu cümleler kullan
- Her sahne 5-8 saniye olsun
- Her sahne için: sahne numarası, metin (seslendirme), görsel açıklaması (İngilizce, AI görsel üretimi için) ver
- Hook (giriş) ve Outro (kapanış) için de görsel açıklaması ver
- Thumbnail (video kapak görseli) için de özel görsel açıklaması ver - bu görsel videoyu özetlemeli ve tıklanabilir olmalı
- JSON formatında döndür
- image_prompt: Sahne metninde geçen ürün adı, oyun adı, marka adı, kişi adı, yer adı gibi spesifik bilgileri MUTLAKA İngilizce görsel açıklamasına ekle. Örnek: sahne metni "iPhone 16 tanıtıldı" ise image_prompt "Apple iPhone 16 smartphone product launch promotional photo" olmalı. Genel ifadelerden kaçın, sahneye özel ve somut açıklama yaz.
- camera_effect: Her sahne için hikayeye uygun kamera efekti seç. Seçenekler:
  * "zoom_in": Detaya odaklanma, önemli anlar, dramatik vurgular için (örn: ürün tanıtımı, şok edici detay)
  * "zoom_out": Genel görüntü, bağlam gösterme, finalde büyük resmi gösterme için (örn: manzara, toplam etki)
  * "pan_right": Hareket hissi, ilerleme, gelecek odaklı sahneler için (örn: yol gösterme, pozitif gelişme)
  * "pan_left": Geçmişe bakış, nostaljik anlar, geriye dönüş sahneleri için (örn: hatırlama, önceki durumlar)
- hook_image_prompt: Hook için dikkat çekici, viral içerik tarzında, haberin ana konusunu yansıtan İngilizce görsel açıklaması
- outro_image_prompt: Kapanış için call-to-action, subscribe/like ikonları içeren modern İngilizce görsel açıklaması
- thumbnail_image_prompt: YouTube kapak görseli için dikkat çekici, high quality, professional thumbnail görsel açıklaması (haberin ana konusunu yansıtan, bold text space bırakacak şekilde)

Haber Başlığı: {{TITLE}}
Haber Metni: {{TEXT}}

Yanıtı şu JSON formatında ver (sadece JSON, başka açıklama yazma):
{
  "hook": "Dikkat çekici açılış cümlesi",
  "hook_image_prompt": "Eye-catching viral intro visual with specific news topic elements",
  "hook_camera_effect": "zoom_in",
  "scenes": [
    {
      "scene": 1,
      "text": "Seslendirme metni",
      "image_prompt": "Specific English image description mentioning exact product/brand/game/person from the scene text",
      "camera_effect": "zoom_in",
      "duration": 6
    }
  ],
  "outro": "Kapanış cümlesi",
  "outro_image_prompt": "Video outro with subscribe button, like icon, comment reminder, modern social media style",
  "outro_camera_effect": "zoom_out",
  "thumbnail_image_prompt": "Professional YouTube thumbnail with dramatic lighting, bold colors, eye-catching composition for news topic, space for text overlay"
}`
        };
        this.modalOpen = true;
      },

      editScript(script) {
        this.editMode = true;
        this.form = { ...script, categoryId: script.categoryId || script.contentType || (this.categories[0]?.id || 'genel'), videoType: script.videoType || 'short' };
        this.modalOpen = true;
      },

      closeModal() {
        this.modalOpen = false;
      },

      async saveScript() {
        this.saving = true;
        const method = this.editMode ? 'PUT' : 'POST';
        
        try {
          const selectedCategory = this.categories.find(c => c.id === this.form.categoryId);
          this.form.contentType = selectedCategory?.name || this.form.categoryId || this.form.contentType;
          const res = await fetch('/api/scripts.php', {
            method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(this.form)
          });
          
          if (res.ok) {
            await this.loadScripts();
            this.closeModal();
          } else {
            alert('Hata: Script kaydedilemedi');
          }
        } catch (e) {
          alert('Bağlantı hatası');
        } finally {
          this.saving = false;
        }
      },

      async deleteScript(id) {
        if (!confirm('Bu scripti silmek istediğinize emin misiniz?')) return;
        
        const res = await fetch('/api/scripts.php', {
          method: 'DELETE',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id })
        });
        
        if (res.ok) {
          await this.loadScripts();
        } else {
          alert('Script silinemedi');
        }
      }
    }
  }
  </script>
</body>
</html>
