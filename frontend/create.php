<?php $page_title = 'Yeni Video - YouTube Shorts Otomasyon'; $active_page = 'create'; ?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <?php include __DIR__ . '/components/_head.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.0/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-100 min-h-screen" x-data="{ 
  sidebarOpen: false, sidebarCollapsed: localStorage.getItem('sidebarCollapsed') === '1', 
  url: '', 
  sourceMode: 'url',
  promptText: '',
  template: 'short_haber', 
  jobId: '', 
  loading: false, 
  previewUrl: '', 
  status: '', 
  error: '', 
  steps: [], 
  configLoaded: false,
  scripts: [],
  scriptId: '',
  contentType: 'haber',
  musicMode: 'off',
  bgmVolumeDb: -22,
  visualThemeId: 'default',
  visualThemePrompt: '',
  darkMode: localStorage.getItem('darkMode')==='1', 
  toggleDark(){ this.darkMode=!this.darkMode; localStorage.setItem('darkMode',this.darkMode?'1':'0'); document.documentElement.classList.toggle('dark',this.darkMode); },
  
  // Video dimension settings
  dimensionPreset: 'vertical',
  customWidth: 1080,
  customHeight: 1920,
  dimensionPresets: {
    vertical: { label: '📱 Dikey (9:16)', width: 1080, height: 1920, desc: 'Shorts/Reels/TikTok' },
    square: { label: '⬛ Kare (1:1)', width: 1080, height: 1080, desc: 'Instagram Post' },
    horizontal: { label: '🖥️ Yatay (16:9)', width: 1920, height: 1080, desc: 'YouTube/TV' },
    custom: { label: '✏️ Özel Ebat', width: null, height: null, desc: 'Manuel giriş' }
  },
  get videoWidth() { return this.dimensionPreset === 'custom' ? this.customWidth : this.dimensionPresets[this.dimensionPreset].width; },
  get videoHeight() { return this.dimensionPreset === 'custom' ? this.customHeight : this.dimensionPresets[this.dimensionPreset].height; },
  get videoType() {
    if (this.videoWidth === this.videoHeight) return 'square';
    if (this.videoWidth > this.videoHeight) return 'wide';
    return 'short';
  },
  get contextScripts() {
    return this.scripts.filter(s => {
      const sType = (s.videoType || 'short') === this.videoType;
      const scriptCategory = (s.categoryId || s.contentType || '').toLowerCase();
      const sCategory = scriptCategory === this.contentType.toLowerCase();
      return sType && sCategory;
    });
  },
  get availableCategories() {
    const cats = this.scripts.map(s => (s.categoryId || s.contentType || '').toLowerCase()).filter(Boolean);
    const uniq = [...new Set(cats)];
    return uniq.length ? uniq : ['haber'];
  },
  visualThemes: [
    { id: 'default', label: 'Default', prompt: '' },
    { id: 'realistic', label: 'Realistic', prompt: 'ultra realistic photo style, natural lighting, real-world textures' },
    { id: 'cinematic', label: 'Cinematic', prompt: 'cinematic film still, dramatic lighting, high contrast, anamorphic composition' },
    { id: 'photographic', label: 'Photographic', prompt: 'professional photography style, crisp details, editorial quality' },
    { id: '3d-model', label: '3D Model', prompt: 'high quality 3D render, stylized 3D model look, clean geometry' },
    { id: 'anime', label: 'Anime', prompt: 'anime style illustration, vibrant cel shading, Japanese animation aesthetic' },
    { id: 'digital-art', label: 'Digital Art', prompt: 'digital painting style, concept art quality, rich color grading' },
    { id: 'dark', label: 'Dark', prompt: 'dark moody atmosphere, low-key lighting, dramatic shadows' },
    { id: 'reportage-sketch', label: 'Reportage Sketch', prompt: 'reportage sketch style, monochrome ink and charcoal texture' },
    { id: 'infinity', label: 'Infinity', prompt: 'epic fantasy concept art, monumental scale, surreal atmosphere' }
  ],
  get selectedThemePrompt() {
    if (this.visualThemeId === 'default') return this.visualThemePrompt || '';
    const found = this.visualThemes.find(t => t.id === this.visualThemeId);
    return found ? found.prompt : '';
  },
  
  // Subtitle style settings - varsayılan olarak config'den yükleniyor
  subtitleMode: 'config',
  selectedSubtitlePreset: 'classic',
  configSubtitle: { FontName: 'Arial', FontSize: 24, PrimaryColour: '#FFFFFF', OutlineColour: '#000000', Outline: 3, Shadow: 1, MarginV: 100, MarginL: 40, MarginR: 40, Bold: 1 },
  customSubtitle: { FontName: 'Arial', FontSize: 24, PrimaryColour: '#FFFFFF', OutlineColour: '#000000', Outline: 2, MarginV: 60, MarginL: 40, MarginR: 40, Bold: 1 },
  subtitlePresets: {
    classic: { label: 'Klasik', FontSize: 24, PrimaryColour: '#FFFFFF', OutlineColour: '#000000', Outline: 2, MarginV: 60, MarginL: 40, MarginR: 40, Bold: 1 },
    neon: { label: 'Neon', FontSize: 26, PrimaryColour: '#00FF00', OutlineColour: '#000000', Outline: 2, MarginV: 60, MarginL: 40, MarginR: 40, Bold: 1 },
    cinematic: { label: 'Sinematik', FontSize: 22, PrimaryColour: '#F5F5DC', OutlineColour: '#2C2C2C', Outline: 1.5, MarginV: 80, MarginL: 40, MarginR: 40, Bold: 0 },
    bold: { label: 'Kalın', FontSize: 28, PrimaryColour: '#FFD700', OutlineColour: '#000000', Outline: 3, MarginV: 50, MarginL: 40, MarginR: 40, Bold: 1 },
    minimal: { label: 'Minimal', FontSize: 20, PrimaryColour: '#FFFFFF', OutlineColour: '#333333', Outline: 1, MarginV: 70, MarginL: 40, MarginR: 40, Bold: 0 },
    news: { label: 'Haber', FontSize: 24, PrimaryColour: '#FFFFFF', OutlineColour: '#CC0000', Outline: 2, MarginV: 55, MarginL: 40, MarginR: 40, Bold: 1 }
  },
  get subtitleStyle() {
    if (this.subtitleMode === 'config') {
      return { ...this.configSubtitle, preset: 'config' };
    }
    if (this.subtitleMode === 'preset') {
      return { ...this.subtitlePresets[this.selectedSubtitlePreset], preset: this.selectedSubtitlePreset };
    }
    return { ...this.customSubtitle, preset: 'custom' };
  },
  loadConfig() {
    fetch('/api/config.php')
      .then(r => r.json())
      .then(d => {
        if (d.subtitleStyle) {
          this.configSubtitle = { ...this.configSubtitle, ...d.subtitleStyle };
        }
        this.configLoaded = true;
      })
      .catch(() => { this.configLoaded = true; });
  },
  loadScripts() {
    fetch('/api/scripts.php')
      .then(r => r.json())
      .then(d => { this.scripts = d.scripts || []; })
      .catch(() => { this.scripts = []; });
  },
  init() {
    this.loadConfig();
    this.loadScripts();
  }
}" x-init="init()">
  <div class="flex flex-col h-screen">
    <?php include __DIR__ . '/components/_header.php'; ?>

    <div class="flex flex-1 overflow-hidden">
      <?php include __DIR__ . '/components/_sidebar.php'; ?>

      <main class="flex-1 overflow-y-auto p-6 md:p-8">
        <div class="max-w-2xl mx-auto">
          <h1 class="text-2xl font-bold text-gray-800 mb-6">Yeni Video Oluştur</h1>

          <form @submit.prevent="
            loading=true; error='';
            if(sourceMode==='url' && !url){ error='Lütfen URL girin'; loading=false; return; }
            if(sourceMode==='prompt' && (!promptText || promptText.trim().length < 20)){ error='Prompt en az 20 karakter olmalı'; loading=false; return; }
            if(!scriptId){ error='Lütfen bir script seçin'; loading=false; return; }
            steps=['Haber çekiliyor...'];
            fetch('/api/jobs.php', {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({url, source_mode: sourceMode, prompt_text: promptText, template, scriptId, contentType, videoWidth, videoHeight, subtitleStyle, visual_theme_id: visualThemeId, visual_theme_prompt: selectedThemePrompt, music_mode: musicMode, bgm_volume_db: bgmVolumeDb})})
              .then(r=>r.json())
              .then(d=>{
                if(d.error){ error=d.error; loading=false; return; }
                jobId=d.jobId; status='pending'; loading=false; steps.push('İş oluşturuldu: '+d.jobId);
              })
              .catch(e=>{ error='İş başlatılamadı. Backend çalışıyor mu?'; loading=false; })
          " class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
            <label class="block mb-2 text-sm font-semibold text-gray-700">Kaynak Türü</label>
            <div class="flex gap-2 mb-4">
              <button type="button" @click="sourceMode='url'" class="px-3 py-1.5 rounded-full border text-sm transition"
                :class="sourceMode==='url' ? 'bg-blue-600 border-blue-600 text-white' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'">URL</button>
              <button type="button" @click="sourceMode='prompt'" class="px-3 py-1.5 rounded-full border text-sm transition"
                :class="sourceMode==='prompt' ? 'bg-blue-600 border-blue-600 text-white' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'">Prompt</button>
            </div>

            <template x-if="sourceMode==='url'">
              <div>
                <label class="block mb-2 text-sm font-semibold text-gray-700">Haber Linki</label>
                <input type="url" x-model="url" placeholder="https://www.example.com/haber/..." class="w-full border border-gray-300 rounded-lg px-4 py-2.5 mb-4 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
              </div>
            </template>

            <template x-if="sourceMode==='prompt'">
              <div>
                <label class="block mb-2 text-sm font-semibold text-gray-700">Video Prompt</label>
                <textarea x-model="promptText" rows="4" maxlength="2000" placeholder="Videoda anlatılmasını istediğiniz konuyu detaylı yazın..." class="w-full border border-gray-300 rounded-lg px-4 py-2.5 mb-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none"></textarea>
                <p class="text-xs text-gray-500 mb-4"><span x-text="promptText.length"></span> / 2000</p>
              </div>
            </template>

            <label class="block mb-2 text-sm font-semibold text-gray-700">Video Formatı</label>
            <select x-model="template" class="w-full border border-gray-300 rounded-lg px-4 py-2.5 mb-4 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
              <option value="short_haber">Short Haber (60sn)</option>
            </select>

            <label class="block mb-2 text-sm font-semibold text-gray-700">Kategori</label>
            <div class="flex flex-wrap gap-2 mb-4">
              <template x-for="cat in availableCategories" :key="cat">
                <button type="button" @click="contentType=cat" class="px-3 py-1.5 rounded-full border text-sm transition"
                  :class="contentType===cat ? 'bg-blue-600 border-blue-600 text-white' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'">
                  <span x-text="cat"></span>
                </button>
              </template>
            </div>

            <!-- Video Ebat Seçimi -->
            <label class="block mb-2 text-sm font-semibold text-gray-700">Video Ebatı</label>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-4">
              <template x-for="(preset, key) in dimensionPresets" :key="key">
                <button type="button" @click="dimensionPreset = key"
                  class="p-3 rounded-lg border-2 text-center transition"
                  :class="dimensionPreset === key ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-gray-200 hover:border-gray-300 text-gray-600'">
                  <span class="block text-sm font-semibold" x-text="preset.label"></span>
                  <span class="block text-xs text-gray-500" x-text="preset.desc"></span>
                  <template x-if="key !== 'custom'">
                    <span class="block text-xs text-gray-400 mt-0.5" x-text="preset.width + 'x' + preset.height"></span>
                  </template>
                </button>
              </template>
            </div>

            <!-- Özel Ebat Girişi -->
            <template x-if="dimensionPreset === 'custom'">
              <div class="bg-gray-50 rounded-lg p-4 mb-4 border border-gray-200">
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Genişlik (px)</label>
                    <input type="number" x-model.number="customWidth" min="360" max="4096" step="1"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-center focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                  </div>
                  <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Yükseklik (px)</label>
                    <input type="number" x-model.number="customHeight" min="360" max="4096" step="1"
                      class="w-full border border-gray-300 rounded-lg px-3 py-2 text-center focus:ring-2 focus:ring-blue-500 focus:border-blue-500 outline-none">
                  </div>
                </div>
                <p class="text-xs text-gray-500 mt-2 text-center">Özel ebat: <span class="font-semibold" x-text="customWidth + 'x' + customHeight"></span></p>
              </div>
            </template>

            <!-- Seçilen Ebat Özeti -->
            <div class="bg-blue-50 border border-blue-100 rounded-lg px-4 py-2.5 mb-4 flex items-center justify-between">
              <span class="text-sm text-blue-700">
                📐 Görsel & Video Ebatı: <span class="font-bold" x-text="videoWidth + ' x ' + videoHeight + ' px'"></span>
              </span>
              <span class="text-xs text-blue-500" x-text="videoWidth > videoHeight ? 'Yatay' : (videoWidth < videoHeight ? 'Dikey' : 'Kare')"></span>
            </div>

            <label class="block mb-2 text-sm font-semibold text-gray-700">Görsel Tema</label>
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mb-4">
              <template x-for="theme in visualThemes" :key="theme.id">
                <button type="button" @click="visualThemeId=theme.id" class="px-3 py-2 rounded-lg border text-sm transition text-left"
                  :class="visualThemeId===theme.id ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-200 text-gray-700 hover:bg-gray-50'">
                  <span x-text="theme.label"></span>
                </button>
              </template>
            </div>

            <label class="block mb-2 text-sm font-semibold text-gray-700">Script (Zorunlu)</label>
            <div class="flex flex-wrap gap-2 mb-4">
              <template x-for="script in contextScripts" :key="script.id">
                <button type="button" @click="scriptId=script.id; contentType=(script.categoryId || script.contentType || contentType)" class="px-3 py-1.5 rounded-full border text-sm transition"
                  :class="scriptId===script.id ? 'bg-gray-900 border-gray-900 text-white' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'"
                  x-text="script.name"></button>
              </template>
            </div>
            <p x-show="contextScripts.length === 0" class="text-xs text-red-600 mb-4">Bu kategori ve video tipi için script bulunamadı. Script Yönetimi'nden script ekleyin.</p>

            <label class="block mb-2 text-sm font-semibold text-gray-700">Fon Müziği</label>
            <div class="flex gap-2 mb-3">
              <button type="button" @click="musicMode='off'" class="px-3 py-1.5 rounded-full border text-sm transition"
                :class="musicMode==='off' ? 'bg-gray-900 border-gray-900 text-white' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'">Kapalı</button>
              <button type="button" @click="musicMode='auto'" class="px-3 py-1.5 rounded-full border text-sm transition"
                :class="musicMode==='auto' ? 'bg-gray-900 border-gray-900 text-white' : 'bg-white border-gray-200 text-gray-700 hover:bg-gray-50'">Otomatik (Kategori)</button>
            </div>
            <div x-show="musicMode==='auto'" class="bg-gray-50 border border-gray-200 rounded-lg p-3 mb-4">
              <label class="block text-xs font-medium text-gray-600 mb-1">Müzik Ses Seviyesi: <span x-text="bgmVolumeDb + ' dB'"></span></label>
              <input type="range" min="-35" max="-8" step="1" x-model.number="bgmVolumeDb" class="w-full accent-indigo-600">
              <p class="text-xs text-gray-500 mt-1">Seçili script kategorisinden aktif bir müzik otomatik atanır.</p>
            </div>

            <!-- Altyazı Stili Seçimi -->
            <label class="block mb-2 text-sm font-semibold text-gray-700">Altyazı Stili</label>
            <div class="mb-4">
              <div class="flex gap-2 mb-3 flex-wrap">
                <button type="button" @click="subtitleMode='config'"
                  class="text-xs font-semibold px-3 py-1.5 rounded-lg transition"
                  :class="subtitleMode==='config'?'bg-green-600 text-white':'bg-gray-100 text-gray-600 hover:bg-gray-200'">⚙️ Varsayılan (Ayarlar)</button>
                <button type="button" @click="subtitleMode='preset'"
                  class="text-xs font-semibold px-3 py-1.5 rounded-lg transition"
                  :class="subtitleMode==='preset'?'bg-indigo-600 text-white':'bg-gray-100 text-gray-600 hover:bg-gray-200'">🎨 Hazır Tasarım</button>
                <button type="button" @click="subtitleMode='custom'"
                  class="text-xs font-semibold px-3 py-1.5 rounded-lg transition"
                  :class="subtitleMode==='custom'?'bg-indigo-600 text-white':'bg-gray-100 text-gray-600 hover:bg-gray-200'">✏️ Özel Tasarım</button>
              </div>

              <!-- Config Preview (Varsayılan Ayarlar) -->
              <template x-if="subtitleMode==='config'">
                <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                  <div class="flex items-center gap-2 mb-3">
                    <span class="text-green-700 text-sm">Ayarlar sayfasındaki varsayılan stil kullanılacak</span>
                  </div>
                  <div class="flex justify-center">
                    <div class="w-32 h-48 bg-gray-800 rounded-lg flex items-end justify-center pb-4">
                      <span class="text-center px-2"
                        :style="{
                          fontFamily: configSubtitle.FontName || 'Arial',
                          color: configSubtitle.PrimaryColour,
                          fontSize: Math.min(configSubtitle.FontSize, 14) + 'px',
                          fontWeight: configSubtitle.Bold ? 'bold' : 'normal',
                          textShadow: configSubtitle.Outline > 0 ? '0 0 ' + (configSubtitle.Outline*2) + 'px ' + configSubtitle.OutlineColour : 'none'
                        }">Örnek Altyazı</span>
                    </div>
                  </div>
                  <div class="text-xs text-green-600 mt-3 text-center">
                    Font: <span x-text="configSubtitle.FontName"></span> | 
                    Boyut: <span x-text="configSubtitle.FontSize + 'px'"></span> | 
                    Dış Hat: <span x-text="configSubtitle.Outline"></span>
                  </div>
                </div>
              </template>

              <!-- Preset Grid -->
              <template x-if="subtitleMode==='preset'">
                <div class="grid grid-cols-3 sm:grid-cols-6 gap-2">
                  <template x-for="(preset, key) in subtitlePresets" :key="key">
                    <button type="button" @click="selectedSubtitlePreset = key"
                      class="p-2 rounded-lg border-2 text-center transition"
                      :class="selectedSubtitlePreset === key ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300'">
                      <div class="h-12 bg-gray-800 rounded flex items-end justify-center pb-1 mb-1">
                        <span class="text-[8px] px-1 rounded"
                          :style="{color: preset.PrimaryColour, fontWeight: preset.Bold ? 'bold' : 'normal', textShadow: '0 0 2px ' + preset.OutlineColour}"
                          x-text="preset.label"></span>
                      </div>
                      <p class="text-[10px] text-gray-600 truncate" x-text="preset.label"></p>
                    </button>
                  </template>
                </div>
              </template>

              <!-- Custom Controls -->
              <template x-if="subtitleMode==='custom'">
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200 space-y-3">
                  <div class="grid grid-cols-2 gap-4">
                    <div>
                      <label class="block text-xs font-medium text-gray-600 mb-1">Yazı Boyutu: <span x-text="customSubtitle.FontSize + 'px'"></span></label>
                      <input type="range" min="12" max="40" x-model.number="customSubtitle.FontSize" class="w-full accent-indigo-600">
                    </div>
                    <div>
                      <label class="block text-xs font-medium text-gray-600 mb-1">Alt Boşluk: <span x-text="customSubtitle.MarginV + 'px'"></span></label>
                      <input type="range" min="20" max="300" x-model.number="customSubtitle.MarginV" class="w-full accent-indigo-600">
                    </div>
                    <div>
                      <label class="block text-xs font-medium text-gray-600 mb-1">Sol Boşluk: <span x-text="customSubtitle.MarginL + 'px'"></span></label>
                      <input type="range" min="0" max="200" x-model.number="customSubtitle.MarginL" class="w-full accent-indigo-600">
                    </div>
                    <div>
                      <label class="block text-xs font-medium text-gray-600 mb-1">Sağ Boşluk: <span x-text="customSubtitle.MarginR + 'px'"></span></label>
                      <input type="range" min="0" max="200" x-model.number="customSubtitle.MarginR" class="w-full accent-indigo-600">
                    </div>
                    <div>
                      <label class="block text-xs font-medium text-gray-600 mb-1">Yazı Rengi</label>
                      <input type="color" x-model="customSubtitle.PrimaryColour" class="w-full h-8 rounded border border-gray-200 cursor-pointer p-0.5">
                    </div>
                    <div>
                      <label class="block text-xs font-medium text-gray-600 mb-1">Dış Hat Rengi</label>
                      <input type="color" x-model="customSubtitle.OutlineColour" class="w-full h-8 rounded border border-gray-200 cursor-pointer p-0.5">
                    </div>
                    <div>
                      <label class="block text-xs font-medium text-gray-600 mb-1">Dış Hat: <span x-text="customSubtitle.Outline"></span></label>
                      <input type="range" min="0" max="5" step="0.5" x-model.number="customSubtitle.Outline" class="w-full accent-indigo-600">
                    </div>
                    <div class="flex items-center gap-2">
                      <input type="checkbox" :checked="customSubtitle.Bold===1" @change="customSubtitle.Bold=$event.target.checked?1:0" class="w-4 h-4 accent-indigo-600">
                      <label class="text-xs font-medium text-gray-600">Kalın Yazı</label>
                    </div>
                  </div>
                  <!-- Preview -->
                  <div class="flex justify-center pt-2">
                    <div class="w-24 h-36 bg-gray-800 rounded-lg flex items-end justify-center pb-2">
                      <span class="text-center px-1"
                        :style="{
                          color: customSubtitle.PrimaryColour,
                          fontSize: Math.min(customSubtitle.FontSize, 14) + 'px',
                          fontWeight: customSubtitle.Bold ? 'bold' : 'normal',
                          textShadow: customSubtitle.Outline > 0 ? '0 0 ' + (customSubtitle.Outline*2) + 'px ' + customSubtitle.OutlineColour : 'none'
                        }">Örnek</span>
                    </div>
                  </div>
                </div>
              </template>
            </div>

            <!-- Seçilen Altyazı Özeti -->
            <div class="bg-indigo-50 border border-indigo-100 rounded-lg px-4 py-2.5 mb-6 flex items-center justify-between">
              <span class="text-sm text-indigo-700">
                📺 Altyazı Stili: <span class="font-bold" x-text="subtitleMode==='config' ? 'Varsayılan (Ayarlar)' : (subtitleMode==='preset' ? subtitlePresets[selectedSubtitlePreset].label : 'Özel Tasarım')"></span>
              </span>
            </div>

            <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-lg font-semibold transition disabled:opacity-50" :disabled="loading">
              <span x-show="!loading">🚀 Otomasyonu Başlat</span>
              <span x-show="loading" class="flex items-center justify-center gap-2">
                <svg class="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                İşleniyor...
              </span>
            </button>

            <template x-if="error">
              <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm" x-text="error"></div>
            </template>
          </form>

          <template x-if="jobId">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
              <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-800">İş Durumu</h2>
                <span class="px-3 py-1 rounded-full text-xs font-semibold" :class="status==='done' ? 'bg-green-100 text-green-700' : status==='failed' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'" x-text="status"></span>
              </div>
              <p class="text-sm text-gray-500 mb-3">Job ID: <span class="font-mono" x-text="jobId"></span></p>
              
              <div class="flex gap-2 mb-4">
                <button class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-semibold transition" 
                        @click="fetch('/api/jobs.php?jobId='+jobId).then(r=>r.json()).then(d=>{status=d.status; error=d.error||''; previewUrl=d.previewUrl||'';}).catch(()=>{})">
                  🔄 Durumu Güncelle
                </button>
                
                <!-- Resume Button -->
                <template x-if="status === 'failed'">
                  <button class="bg-orange-500 hover:bg-orange-600 text-white px-4 py-2 rounded-lg text-sm font-semibold transition flex items-center gap-2" 
                          @click="async function(){
                            if (!confirm('Bu işi kaldığı yerden devam ettirmek istiyor musunuz?')) return;
                            try {
                              const res = await fetch('/api/jobs.php', {
                                method: 'PATCH',
                                headers: {'Content-Type': 'application/json'},
                                body: JSON.stringify({action: 'resume', jobId: jobId})
                              });
                              const data = await res.json();
                              if (data.success) {
                                alert('İş kuyruğa eklendi! ' + data.resume_info.message);
                                status = 'waiting';
                                error = '';
                              } else {
                                alert('Hata: ' + (data.error || 'Bilinmeyen hata'));
                              }
                            } catch(e) {
                              alert('Bağlantı hatası: ' + e.message);
                            }
                          }()">
                    <span>🔄</span>
                    <span>Kaldığı Yerden Devam Et</span>
                  </button>
                </template>
              </div>
              
              <!-- Error display -->
              <template x-if="error">
                <div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                  <p class="text-sm text-red-700">
                    <strong>Hata:</strong> <span x-text="error"></span>
                  </p>
                </div>
              </template>
              
              <template x-if="previewUrl">
                <div class="mt-4">
                  <video controls class="w-full rounded-lg shadow">
                    <source :src="previewUrl" type="video/mp4">
                  </video>
                </div>
              </template>
            </div>
          </template>
        </div>
      </main>
    </div>

    <?php include __DIR__ . '/components/_footer.php'; ?>
  </div>
</body>
</html>
