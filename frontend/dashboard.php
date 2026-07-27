<?php $page_title = 'Ana Sayfa - Video Otomasyon'; $active_page = 'home'; ?>
<!DOCTYPE html>
<html lang="tr" x-data="{ darkMode: localStorage.getItem('darkMode') === '1' }" :class="{ 'dark': darkMode }">
<head>
  <?php include __DIR__ . '/components/_head.php'; ?>
  <style>
    [x-cloak] { display: none !important; }
    .video-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(170px,1fr)); gap:1rem; }
    .video-cover { aspect-ratio:9 / 14; }
    .line-clamp-2 { display:-webkit-box; -webkit-box-orient:vertical; -webkit-line-clamp:2; overflow:hidden; }
    @keyframes spin { to { transform:rotate(360deg); } }
    .spin { animation:spin .8s linear infinite; }
    @media (min-width:1280px) { .video-grid { grid-template-columns:repeat(auto-fill,minmax(190px,1fr)); } }
  </style>
  <script>
    function homeApp() {
      const scripts = { haber:'default_haber', teknoloji:'default_teknoloji_short', spor:'default_spor_short', komedi:'default_komedi_short' };
      return {
        sidebarOpen:false,
        sidebarCollapsed:localStorage.getItem('sidebarCollapsed') === '1',
        darkMode:localStorage.getItem('darkMode') === '1',
        jobs:[], loading:true, autoRefresh:null, videoPopup:null,
        sourceMode:'url', sourceValue:'', category:'haber', submitting:false,
        formError:'', createdJobId:'', configSubtitle:null,
        get videos() {
          return this.jobs.filter(job => ['done','completed'].includes(String(job?.status || '').toLowerCase()) && job?.previewUrl);
        },
        toggleDark() {
          this.darkMode=!this.darkMode;
          localStorage.setItem('darkMode', this.darkMode ? '1' : '0');
          document.documentElement.classList.toggle('dark', this.darkMode);
        },
        posterUrl(job) { return '/output/' + encodeURIComponent(job.id) + '/thumbnail.jpg'; },
        formatDate(value) {
          if (!value) return '';
          const date = new Date(String(value).includes('T') ? value : String(value).replace(' ', 'T'));
          return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat('tr-TR',{day:'2-digit',month:'short',year:'numeric'}).format(date);
        },
        async loadJobs() {
          try { const response=await fetch('/api/jobs.php?list=1'); const data=await response.json(); this.jobs=data.jobs || []; }
          catch (error) { console.error('Videolar yüklenemedi:', error); }
          finally { this.loading=false; }
        },
        async loadDefaults() {
          try { const response=await fetch('/api/config.php'); const data=await response.json(); this.configSubtitle=data.subtitleStyle || null; }
          catch (error) { this.configSubtitle=null; }
        },
        async startProject() {
          const value=this.sourceValue.trim(); this.formError=''; this.createdJobId='';
          if (!value) { this.formError=this.sourceMode === 'url' ? 'Bir haber bağlantısı girin.' : 'Video fikrinizi yazın.'; return; }
          if (this.sourceMode === 'url' && !/^https?:\/\//i.test(value)) { this.formError='Geçerli bir http veya https bağlantısı girin.'; return; }
          if (this.sourceMode === 'prompt' && value.length < 20) { this.formError='Video fikri en az 20 karakter olmalı.'; return; }
          this.submitting=true;
          try {
            const response=await fetch('/api/jobs.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify({
              url:this.sourceMode === 'url' ? value : '', source_mode:this.sourceMode, prompt_text:this.sourceMode === 'prompt' ? value : '',
              template:'short_haber', scriptId:scripts[this.category], contentType:this.category, videoWidth:1080, videoHeight:1920,
              subtitleStyle:this.configSubtitle, visual_theme_id:'default', visual_theme_prompt:'', music_mode:'off', bgm_volume_db:-22
            }) });
            const data=await response.json();
            if (!response.ok || data.error) throw new Error(data.error || 'Proje başlatılamadı.');
            this.createdJobId=data.jobId; this.sourceValue=''; await this.loadJobs();
          } catch (error) { this.formError=error.message || 'Proje başlatılamadı.'; }
          finally { this.submitting=false; }
        },
        openVideo(job) { this.videoPopup=job; },
        closeVideo() { this.videoPopup=null; },
        init() { this.loadDefaults(); this.loadJobs(); this.autoRefresh=setInterval(() => this.loadJobs(), 5000); },
        destroy() { clearInterval(this.autoRefresh); }
      };
    }
  </script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.0/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-50 dark:bg-slate-900 min-h-screen" x-data="homeApp()" x-init="init()" @destroy.window="destroy()" @keydown.escape.window="closeVideo()">
  <div class="flex flex-col h-screen">
    <?php include __DIR__ . '/components/_header.php'; ?>
    <div class="flex flex-1 overflow-hidden">
      <?php include __DIR__ . '/components/_sidebar.php'; ?>
      <main class="flex-1 overflow-y-auto">
        <div class="max-w-7xl mx-auto px-5 py-7 md:px-8 md:py-9">
          <section class="mb-10">
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-blue-600 dark:text-blue-400 mb-2">Hızlı başlangıç</p>
            <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-gray-900 dark:text-white mb-5">Yeni bir video oluştur</h1>
            <form @submit.prevent="startProject()" class="bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-2xl p-3 shadow-sm">
              <div class="flex flex-wrap items-center gap-2 mb-3">
                <div class="inline-flex rounded-lg bg-gray-100 dark:bg-slate-900 p-1">
                  <button type="button" @click="sourceMode='url'; formError=''" class="px-3 py-1.5 rounded-md text-sm font-medium transition" :class="sourceMode==='url' ? 'bg-white dark:bg-slate-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-gray-400'">URL</button>
                  <button type="button" @click="sourceMode='prompt'; formError=''" class="px-3 py-1.5 rounded-md text-sm font-medium transition" :class="sourceMode==='prompt' ? 'bg-white dark:bg-slate-700 text-gray-900 dark:text-white shadow-sm' : 'text-gray-500 dark:text-gray-400'">Prompt</button>
                </div>
                <select x-model="category" aria-label="Video kategorisi" class="ml-auto bg-transparent text-sm text-gray-600 dark:text-gray-300 border-0 rounded-lg focus:ring-2 focus:ring-blue-500">
                  <option value="haber">Haber</option><option value="teknoloji">Teknoloji</option><option value="spor">Spor</option><option value="komedi">Komedi</option>
                </select>
              </div>
              <div class="flex flex-col sm:flex-row gap-2">
                <input x-show="sourceMode==='url'" x-model="sourceValue" type="url" placeholder="Haber bağlantısını yapıştırın" aria-label="Haber bağlantısı" class="flex-1 min-w-0 px-4 py-3 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-xl text-gray-900 dark:text-white placeholder-gray-400 outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <textarea x-show="sourceMode==='prompt'" x-cloak x-model="sourceValue" rows="1" placeholder="Nasıl bir video oluşturmak istediğinizi yazın" aria-label="Video fikri" class="flex-1 min-w-0 px-4 py-3 bg-gray-50 dark:bg-slate-900 border border-gray-200 dark:border-slate-700 rounded-xl text-gray-900 dark:text-white placeholder-gray-400 outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"></textarea>
                <button type="submit" :disabled="submitting" class="inline-flex items-center justify-center gap-2 px-5 py-3 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white rounded-xl font-semibold transition whitespace-nowrap">
                  <svg x-show="submitting" class="w-4 h-4 spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.37 0 0 5.37 0 12h4z"></path></svg>
                  <span x-text="submitting ? 'Başlatılıyor' : 'Projeyi Başlat'"></span>
                </button>
              </div>
              <p x-show="formError" x-cloak class="mt-2 px-1 text-sm text-red-600 dark:text-red-400" x-text="formError"></p>
              <div x-show="createdJobId" x-cloak class="mt-3 flex flex-wrap items-center justify-between gap-2 rounded-xl bg-green-50 dark:bg-green-900/20 px-4 py-3 text-sm text-green-800 dark:text-green-300">
                <span>Proje üretim kuyruğuna eklendi.</span><a :href="'project.php?id=' + encodeURIComponent(createdJobId)" class="font-semibold hover:underline">Projeyi aç →</a>
              </div>
            </form>
          </section>
          <section>
            <div class="flex items-end justify-between gap-4 mb-5">
              <div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-400 dark:text-gray-500 mb-1">Arşiv</p><h2 class="text-xl md:text-2xl font-bold text-gray-900 dark:text-white">Üretilen videolar</h2></div>
              <span x-show="!loading" class="text-sm text-gray-400 dark:text-gray-500"><span x-text="videos.length"></span> video</span>
            </div>
            <div x-show="loading" class="py-16 flex items-center justify-center text-gray-400"><svg class="w-5 h-5 mr-3 spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.37 0 0 5.37 0 12h4z"></path></svg>Videolar yükleniyor</div>
            <div x-show="!loading && videos.length === 0" x-cloak class="py-16 border border-dashed border-gray-300 dark:border-slate-700 rounded-2xl text-center"><p class="text-gray-500 dark:text-gray-400">Henüz tamamlanmış video yok.</p></div>
            <div x-show="!loading && videos.length > 0" x-cloak class="video-grid">
              <template x-for="job in videos" :key="job.id">
                <button type="button" @click="openVideo(job)" class="group text-left rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900" :aria-label="(job.title || 'Video') + ' videosunu izle'">
                  <div class="video-cover relative overflow-hidden rounded-2xl bg-gray-200 dark:bg-slate-800 shadow-sm">
                    <img :src="posterUrl(job)" :alt="job.title || 'Video kapağı'" loading="lazy" class="w-full h-full object-cover transition duration-300 group-hover:scale-[1.025]">
                    <div class="absolute inset-0 bg-gradient-to-t from-black/55 via-transparent to-transparent"></div>
                    <div class="absolute inset-0 flex items-center justify-center"><span class="flex items-center justify-center w-12 h-12 rounded-full bg-white/95 text-gray-900 shadow-lg transition duration-200 group-hover:scale-110"><svg class="w-5 h-5 ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg></span></div>
                    <span class="absolute left-3 right-3 bottom-3 text-sm font-semibold leading-snug text-white line-clamp-2" x-text="job.title || 'İsimsiz video'"></span>
                  </div><p class="mt-2 px-1 text-xs text-gray-400 dark:text-gray-500" x-text="formatDate(job.created_at)"></p>
                </button>
              </template>
            </div>
          </section>
        </div>
      </main>
    </div>
  </div>
  <div x-show="videoPopup" x-cloak x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4" @click.self="closeVideo()">
    <div class="relative w-full" :class="videoPopup && Number(videoPopup.videoWidth || 1080) > Number(videoPopup.videoHeight || 1920) ? 'max-w-5xl' : 'max-w-md'">
      <button type="button" @click="closeVideo()" aria-label="Video oynatıcıyı kapat" class="absolute -top-11 right-0 flex items-center justify-center w-9 h-9 rounded-full bg-white/10 hover:bg-white/20 text-white transition"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg></button>
      <template x-if="videoPopup"><video :src="videoPopup.previewUrl" :poster="posterUrl(videoPopup)" controls autoplay playsinline class="w-full max-h-[84vh] rounded-2xl bg-black shadow-2xl"></video></template>
    </div>
  </div>
</body>
</html>