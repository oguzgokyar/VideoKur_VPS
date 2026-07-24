<?php
$page_title = 'Proje Detayı - YouTube Shorts Otomasyon';
$active_page = 'project';
$show_status = true;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <?php include __DIR__ . '/components/_head.php'; ?>
  <style>
    @keyframes spin-slow  { to { transform: rotate(360deg); } }
    @keyframes fade-in-up { from { opacity:0; transform:translateY(8px); } to { opacity:1; transform:translateY(0); } }
    .anim-spin   { animation: spin-slow 1.2s linear infinite; }
    .anim-fade   { animation: fade-in-up .3s ease-out both; }
    .srt-box     { font-family:'Courier New',monospace; white-space:pre; }
    .img-9-16    { aspect-ratio: 9/16; }
    .tab-active  { border-bottom: 2px solid #4f46e5; color: #4f46e5; font-weight: 600; }
    .tab-btn     { transition: color .15s, border-color .15s; }
    .sub-preview { position:relative; background:#111; border-radius:.75rem; overflow:hidden; aspect-ratio:9/16; max-width:160px; }
    .sub-preview-text { position:absolute; left:0; right:0; text-align:center; }

    /* ── Dark Mode ────────────────────────────────────────────── */
    html.dark { color-scheme: dark; }
    html.dark body { background-color: #0f172a !important; }
    html.dark .bg-white { background-color: #1e293b !important; }
    html.dark .bg-gray-50 { background-color: #0f172a !important; }
    html.dark .bg-gray-100 { background-color: #1e293b !important; }
    html.dark .border-gray-100, html.dark .border-gray-200 { border-color: #334155 !important; }
    html.dark .border-b, html.dark .border-r, html.dark .border-t { border-color: #334155 !important; }
    html.dark .text-gray-800 { color: #f1f5f9 !important; }
    html.dark .text-gray-700 { color: #e2e8f0 !important; }
    html.dark .text-gray-600 { color: #cbd5e1 !important; }
    html.dark .text-gray-500 { color: #94a3b8 !important; }
    html.dark .text-gray-400 { color: #64748b !important; }
    html.dark header { background-color: #1e293b !important; border-color: #334155 !important; }
    html.dark aside { background-color: #1e293b !important; border-color: #334155 !important; }
    html.dark footer { background-color: #1e293b !important; border-color: #334155 !important; }
    html.dark select, html.dark input[type=range] { background-color: #0f172a !important; border-color: #334155 !important; color: #e2e8f0 !important; }
    html.dark .srt-box { background-color: #0f172a !important; border-color: #334155 !important; color: #94a3b8 !important; }
    html.dark .shadow-sm, html.dark .shadow-md, html.dark .shadow-lg { box-shadow: 0 1px 6px 0 rgba(0,0,0,.5) !important; }
    html.dark .hover\:bg-gray-100:hover { background-color: #334155 !important; }
    html.dark .hover\:bg-gray-200:hover { background-color: #475569 !important; }
    html.dark .tab-active { border-bottom-color: #818cf8 !important; color: #818cf8 !important; }
    html.dark audio { filter: invert(0.85) hue-rotate(180deg); }
    html.dark .bg-yellow-50 { background-color: #2d2000 !important; }
    html.dark .bg-green-50 { background-color: #052e16 !important; }
    html.dark .bg-purple-50 { background-color: #2e1065 !important; }
    html.dark .bg-gray-800 { background-color: #0f172a !important; }
    html.dark .border-yellow-200 { border-color: #854d0e !important; }
    html.dark .border-green-200 { border-color: #166534 !important; }
    html.dark .border-purple-100 { border-color: #4c1d95 !important; }
    html.dark .text-yellow-700 { color: #fde047 !important; }
    html.dark .text-green-700 { color: #4ade80 !important; }
    html.dark .text-purple-700, html.dark .text-purple-800 { color: #c4b5fd !important; }
    html.dark .sub-preview { background: #0f172a !important; }
  </style>

  <script>
  function projectApp() {
    const params = new URLSearchParams(location.search);
    const jobId  = params.get('id') || '';

    const SUBTITLE_PRESETS = {
      classic:    { label:'Classic',      FontName:'Arial', FontSize:20, PrimaryColour:'#FFFFFF', OutlineColour:'#000000', BorderStyle:3, Outline:2, Shadow:0, MarginV:80,  MarginL:40, MarginR:40, Alignment:2, Bold:0 },
      bold_bottom:{ label:'Bold Alt',     FontName:'Arial', FontSize:24, PrimaryColour:'#FFFFFF', OutlineColour:'#000000', BorderStyle:3, Outline:3, Shadow:1, MarginV:100, MarginL:40, MarginR:40, Alignment:2, Bold:1 },
      yellow_bold:{ label:'Sarı Kalın',   FontName:'Arial', FontSize:22, PrimaryColour:'#FFFF00', OutlineColour:'#000000', BorderStyle:1, Outline:2, Shadow:1, MarginV:80,  MarginL:40, MarginR:40, Alignment:2, Bold:1 },
      box_white:  { label:'Kutu Beyaz',   FontName:'Arial', FontSize:20, PrimaryColour:'#000000', OutlineColour:'#FFFFFF', BorderStyle:4, Outline:0, Shadow:0, MarginV:80,  MarginL:40, MarginR:40, Alignment:2, Bold:0 },
      tiktok:     { label:'TikTok',       FontName:'Arial', FontSize:26, PrimaryColour:'#FFFFFF', OutlineColour:'#0000FF', BorderStyle:3, Outline:3, Shadow:0, MarginV:120, MarginL:40, MarginR:40, Alignment:2, Bold:1 },
      minimal:    { label:'Minimal',      FontName:'Arial', FontSize:18, PrimaryColour:'#FFFFFF', OutlineColour:'#000000', BorderStyle:1, Outline:1, Shadow:0, MarginV:60,  MarginL:40, MarginR:40, Alignment:2, Bold:0 },
    };

    const statusLabel = { pending:'Bekliyor', scraping:'Haber Çekiliyor', scripting:'Script Yazılıyor', imaging:'Görseller Üretiliyor', tts:'Seslendirme', subtitling:'Altyazı', composing:'Video Birleştirme', done:'Tamamlandı', failed:'Hata' };
    const statusColor = { pending:'bg-gray-100 text-gray-500', scraping:'bg-blue-100 text-blue-700', scripting:'bg-indigo-100 text-indigo-700', imaging:'bg-purple-100 text-purple-700', tts:'bg-pink-100 text-pink-700', subtitling:'bg-orange-100 text-orange-700', composing:'bg-amber-100 text-amber-700', done:'bg-green-100 text-green-700', failed:'bg-red-100 text-red-700' };

    return {
      sidebarOpen: false,
      darkMode: false,
      activeTab: 'content',   // content | images | media | video | youtube | services
      jobId,
      job:       null,
      news:      null,
      script:    null,
      pageError: '',
      loading:   true,
      regenState: {},          // key → 'idle'|'running'|'done'|'error'
      imgTimestamps: {},       // sceneNum → timestamp (for cache-busting)
      autoRefreshInterval: null, // Real-time update interval
      
      // YouTube metadata
      youtubeMetadata: {
        title: '',
        description: '',
        tags: [],
        thumbnail: null,
        category_id: '28',
        privacy_status: 'public'
      },
      editingYouTubeMetadata: false,
      uploadingToYouTube: false,

      // images tab
      globalImageService: 'auto',
      sceneImageService:  {},
      pollinationsModel:  'flux',
      configImageService: 'auto',

      // prompt editing
      editingPrompt: null,
      promptDraft:   '',

      // subtitle designer
      SUBTITLE_PRESETS,
      selectedPreset:   'classic',
      customStyle:      null,   // initialized in init
      subtitleMode:     'preset',

      // service check
      serviceResults:   {},   // provider → {loading, valid, message}

      statusLabel, statusColor,
      get isActive() { return this.job && !['done','failed','pending'].includes(this.job.status); },
      getStatusLabel(s) { return this.statusLabel[s] || s; },
      getStatusColor(s) { return this.statusColor[s] || 'bg-gray-100 text-gray-500'; },

      // ── Load ──────────────────────────────────────────────────────────────
      async loadJob() {
        if (!this.jobId) { this.pageError = 'URL\'de ?id= parametresi eksik.'; this.loading = false; return; }
        try {
          const r = await fetch(`/api/jobs.php?jobId=${encodeURIComponent(this.jobId)}`);
          const d = await r.json();
          if (d.error) { this.pageError = d.error; this.loading = false; return; }
          this.job = d;
          if (d.subtitleStyle) {
            if (typeof d.subtitleStyle === 'string' && this.SUBTITLE_PRESETS[d.subtitleStyle]) {
              this.selectedPreset = d.subtitleStyle;
              this.customStyle = { ...this.SUBTITLE_PRESETS[d.subtitleStyle] };
            } else if (typeof d.subtitleStyle === 'object') {
              this.subtitleMode = 'custom';
              this.customStyle = { ...this.SUBTITLE_PRESETS.classic, ...d.subtitleStyle };
            }
          }
        } catch(e) { this.pageError = 'Sunucu hatası: ' + e.message; this.loading = false; return; }
        this.loading = false;
        await Promise.all([this.loadNews(), this.loadScript()]);
        // Start auto refresh if job is still processing
        if (this.job && !['done', 'failed'].includes(this.job.status)) {
          this.startAutoRefresh();
        }
      },
      async loadNews()   { try { const r = await fetch(`/output/${this.jobId}/news.json`);   if (r.ok) this.news   = await r.json(); } catch(_) {} },
      async loadScript() { 
        try { 
          const r = await fetch(`/output/${this.jobId}/script.json?t=${Date.now()}`); 
          if (r.ok) this.script = await r.json(); 
        } catch(_) {} 
      },
      async refreshJob() { 
        try { 
          const r = await fetch(`/api/jobs.php?jobId=${encodeURIComponent(this.jobId)}`); 
          const d = await r.json(); 
          if (!d.error) this.job = d; 
        } catch(_) {} 
      },

      // ── Regenerate ────────────────────────────────────────────────────────
      async regenerate(section, extra = {}) {
        const key = section;
        this.setRegen(key, 'running');
        try {
          const r = await fetch('/api/regenerate.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ jobId: this.jobId, section, extra })
          });
          const d = await r.json();
          if (d.error) { this.setRegen(key, 'error'); return; }
          if (section === 'update_prompt') {
            this.setRegen(key, 'done');
            await this.loadScript();
            setTimeout(() => this.setRegen(key, 'idle'), 1500);
            return;
          }
        } catch(e) { this.setRegen(key, 'error'); return; }
        this.pollSection(section, key);
      },

      pollSection(section, key) {
        // PHP already set the processing status, so poll until it returns to done/failed
        const iv = setInterval(async () => {
          await this.refreshJob();
          const s = this.job?.status;
          if (s === 'done' || s === 'failed') {
            clearInterval(iv);
            this.setRegen(key, s === 'done' ? 'done' : 'error');
            if (['news'].includes(section)) await this.loadNews();
            if (['script'].includes(section)) await this.loadScript();
            if (['tts','subtitles'].includes(section)) { /* job.subtitles auto-updated */ }
            setTimeout(() => this.setRegen(key, 'idle'), 2500);
          }
        }, 1500);
      },

      setRegen(key, val) { this.regenState = { ...this.regenState, [key]: val }; },
      rs(key) { return this.regenState[key] || 'idle'; },

      // ── Image regen ───────────────────────────────────────────────────────
      regenAllImages() { this.regenerate('images', { image_service: this.globalImageService }); },

      regenSingleImage(sceneNum) {
        const service = this.sceneImageService[sceneNum] || this.globalImageService;
        const sc = this.sceneSegments[sceneNum - 1];
        const prompt = sc ? sc.image_prompt : '';
        const key = `img_${sceneNum}`;
        this.setRegen(key, 'running');
        fetch('/api/regenerate.php', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ jobId: this.jobId, section: 'image_single', extra: { scene_type: 'scene', scene_index: sceneNum, image_service: service, prompt } })
        }).then(r => r.json()).then(d => {
          if (d.error) { this.setRegen(key, 'error'); return; }
          this.pollImgSingle(sceneNum, key);
        }).catch(() => this.setRegen(key, 'error'));
      },

      // Hook, Outro, Thumbnail için ayrı yeniden üretim fonksiyonu
      regenSpecialImage(segType) {
        const service = this.sceneImageService[segType] || this.globalImageService;
        const key = `img_${segType}`;
        this.setRegen(key, 'running');
        
        // Segment'e göre prompt belirle
        let prompt = '';
        if (segType === 'hook' && this.script?.hook_image_prompt) {
          prompt = this.script.hook_image_prompt;
        } else if (segType === 'outro' && this.script?.outro_image_prompt) {
          prompt = this.script.outro_image_prompt;
        } else if (segType === 'thumbnail' && this.script?.thumbnail_image_prompt) {
          prompt = this.script.thumbnail_image_prompt;
        }
        
        fetch('/api/regenerate.php', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ jobId: this.jobId, section: 'image_single', extra: { scene_type: segType, image_service: service, prompt } })
        }).then(r => r.json()).then(d => {
          if (d.error) { this.setRegen(key, 'error'); return; }
          this.pollSpecialImg(segType, key);
        }).catch(() => this.setRegen(key, 'error'));
      },
      
      pollSpecialImg(segType, key) {
        let tries = 0;
        const iv = setInterval(async () => {
          await this.refreshJob();
          const s = this.job?.status;
          tries++;
          if (s === 'done' || s === 'failed' || tries > 80) {  // max 80 × 1.5s = 2 min
            clearInterval(iv);
            const ok = s === 'done' && tries <= 80;
            this.setRegen(key, ok ? 'done' : 'error');
            if (ok) {
              await this.loadScript();
              this.imgTimestamps = { ...this.imgTimestamps, [segType]: Date.now() };
            }
            setTimeout(() => this.setRegen(key, 'idle'), 2500);
          }
        }, 1500);
      },

      pollImgSingle(sceneNum, key) {
        let tries = 0;
        const iv = setInterval(async () => {
          await this.refreshJob();
          const s = this.job?.status;
          tries++;
          if (s === 'done' || s === 'failed' || tries > 80) {  // max 80 × 1.5s = 2 min
            clearInterval(iv);
            const ok = s === 'done' && tries <= 80;
            this.setRegen(key, ok ? 'done' : 'error');
            if (ok) {
              await this.loadScript();
              this.imgTimestamps = { ...this.imgTimestamps, [sceneNum]: Date.now() };
            }
            setTimeout(() => this.setRegen(key, 'idle'), 2500);
          }
        }, 1500);
      },

      // Reactively updates all images after full regen
      async pollImagesAll() {
        let tries = 0;
        const iv = setInterval(async () => {
          await this.refreshJob();
          const s = this.job?.status;
          tries++;
          if (s === 'done' || s === 'failed' || tries > 200) {  // max 200 × 1.5s = 5 min
            clearInterval(iv);
            const ok = s === 'done' && tries <= 200;
            this.setRegen('images', ok ? 'done' : 'error');
            if (ok) {
              await this.loadScript();
              const ts = Date.now();
              const updated = {};
              (this.script?.scenes || []).forEach((sc, i) => { updated[sc.scene || (i+1)] = ts; });
              this.imgTimestamps = { ...this.imgTimestamps, ...updated };
            }
            setTimeout(() => this.setRegen('images', 'idle'), 2500);
          }
        }, 1500);
      },

      // Override regenAllImages to use dedicated poller
      regenAllImagesBtn() {
        this.setRegen('images', 'running');
        fetch('/api/regenerate.php', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ jobId: this.jobId, section: 'images', extra: { image_service: this.globalImageService } })
        }).then(r => r.json()).then(d => {
          if (d.error) { this.setRegen('images', 'error'); return; }
          this.pollImagesAll();
        }).catch(() => this.setRegen('images', 'error'));
      },

      // ── Prompt editing ────────────────────────────────────────────────────
      startEditPrompt(sceneNum) {
        this.editingPrompt = sceneNum;
        const sc = this.sceneSegments[sceneNum - 1];
        this.promptDraft = sc ? (sc.image_prompt || '') : '';
      },
      cancelEditPrompt() { this.editingPrompt = null; this.promptDraft = ''; },

      async savePrompt(sceneNum) {
        await this.regenerate('update_prompt', { scene_index: sceneNum, prompt: this.promptDraft });
        this.editingPrompt = null; this.promptDraft = '';
      },

      async saveAndRegenPrompt(sceneNum) {
        // 1. sync save prompt
        await fetch('/api/regenerate.php', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ jobId: this.jobId, section: 'update_prompt', extra: { scene_index: sceneNum, prompt: this.promptDraft } })
        });
        await this.loadScript();
        this.editingPrompt = null; this.promptDraft = '';
        // 2. regen single image
        this.regenSingleImage(sceneNum);
      },

      // ── Subtitle designer ─────────────────────────────────────────────────
      applyPreset(name) { this.selectedPreset = name; this.customStyle = { ...this.SUBTITLE_PRESETS[name] }; },
      get currentSubtitleStyle() { return this.subtitleMode === 'preset' ? this.selectedPreset : { ...this.customStyle }; },
      get allSegments() {
        if (!this.job || !this.job.script) return [];
        const segs = [];
        const s = this.job.script;
        if (s.hook) segs.push({ idx: 0, type: 'hook', text: s.hook });
        (s.scenes || []).forEach((sc, i) => { segs.push({ idx: i+1, type: 'scene', sceneNum: i+1, text: sc.narration || sc.text || '' }); });
        if (s.outro) segs.push({ idx: segs.length, type: 'outro', text: s.outro });
        return segs;
      },

      subPreviewStyle(key) {
        const p = key === '_custom' ? this.customStyle : (this.SUBTITLE_PRESETS[key] || this.customStyle);
        if (!p) return {};
        return {
          color: p.PrimaryColour || '#fff', fontSize: (p.FontSize || 18) + 'px', fontWeight: p.Bold ? 'bold' : 'normal',
          textShadow: p.Outline > 0 ? `0 0 ${p.Outline*2}px ${p.OutlineColour||'#000'}` : 'none',
          background: p.BorderStyle === 4 ? 'rgba(0,0,0,.65)' : 'transparent',
          padding: p.BorderStyle === 4 ? '3px 8px' : '0', borderRadius: '3px', lineHeight: '1.3',
        };
      },
      subContainerStyle(key) {
        const p = key === '_custom' ? this.customStyle : (this.SUBTITLE_PRESETS[key] || this.customStyle);
        return p ? { bottom: (p.MarginV || 60) + 'px' } : {};
      },

      async regenVideoWithStyle() {
        const style = this.currentSubtitleStyle;
        this.setRegen('video', 'running');
        fetch('/api/regenerate.php', {
          method: 'POST', headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ jobId: this.jobId, section: 'video', extra: { subtitle_style: style } })
        }).then(r => r.json()).then(d => {
          if (d.error) { this.setRegen('video', 'error'); return; }
          this.pollSection('video', 'video');
        }).catch(() => this.setRegen('video', 'error'));
      },

      // ── Service check ─────────────────────────────────────────────────────
      async checkService(provider) {
        this.serviceResults = { ...this.serviceResults, [provider]: { loading: true, valid: null, message: '' } };
        // Load keys from config via jobs API... use check.php directly
        try {
          const cfgR = await fetch('/api/config.php');
          const cfg  = await cfgR.json();
          const keyMap = { gemini: cfg.geminiKey, elevenlabs: cfg.elevenKey, huggingface: cfg.hfKey, pexels: cfg.pexelsKey };
          const key = keyMap[provider] || '';
          const r = await fetch('/api/check.php', {
            method: 'POST', headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ provider, key })
          });
          const d = await r.json();
          this.serviceResults = { ...this.serviceResults, [provider]: { loading: false, valid: d.valid, message: d.message } };
        } catch(e) {
          this.serviceResults = { ...this.serviceResults, [provider]: { loading: false, valid: false, message: 'Bağlantı hatası: ' + e.message } };
        }
      },

      async checkAllServices() {
        // Pollinations is always free, no API key needed
        this.serviceResults = {
          ...this.serviceResults,
          pollinations: { loading: false, valid: true, message: '✅ Ücretsiz — API anahtarı gerekmez' }
        };
        for (const p of ['gemini','elevenlabs','huggingface','pexels']) await this.checkService(p);
      },

      // ── Helpers ───────────────────────────────────────────────────────────
      sceneImgSrc(sceneNum) {
        const ts = this.imgTimestamps[sceneNum];
        return `/output/${this.jobId}/images/scene_${sceneNum}.png` + (ts ? `?t=${ts}` : '');
      },
      hookImgSrc() {
        const ts = this.imgTimestamps['hook'];
        return `/output/${this.jobId}/images/hook.png` + (ts ? `?t=${ts}` : '');
      },
      outroImgSrc() {
        const ts = this.imgTimestamps['outro'];
        return `/output/${this.jobId}/images/outro.png` + (ts ? `?t=${ts}` : '');
      },
      thumbnailImgSrc() {
        const ts = this.imgTimestamps['thumbnail'];
        return `/output/${this.jobId}/thumbnail.jpg` + (ts ? `?t=${ts}` : '');
      },
      segAudioUrl(i)  { return `/output/${this.jobId}/audio_segments/seg_${String(i).padStart(2,'0')}.mp3`; },
      fullAudioUrl()  { return `/output/${this.jobId}/audio.mp3`; },
      get videoUrl()  { return this.job?.previewUrl || ''; },

      // Hook + Scenes + Outro dahil tüm görseller (AI tarafından üretilen prompt'larla)
      get allImageSegments() {
        if (!this.script) return [];
        const segs = [];
        if (this.script.hook) segs.push({ 
          type: 'hook', 
          text: this.script.hook, 
          image_prompt: this.script.hook_image_prompt || 'Hook intro visual',
          used_service: this.script.hook_used_service
        });
        (this.script.scenes || []).forEach((sc, i) => {
          segs.push({ type: 'scene', sceneNum: sc.scene || (i + 1), text: sc.text, image_prompt: sc.image_prompt, used_service: sc.used_service });
        });
        if (this.script.outro) segs.push({ 
          type: 'outro', 
          text: this.script.outro, 
          image_prompt: this.script.outro_image_prompt || 'Outro closing visual',
          used_service: this.script.outro_used_service
        });
        // Thumbnail ekle
        if (this.script.thumbnail_image_prompt) segs.push({
          type: 'thumbnail',
          text: 'YouTube Kapak Görseli',
          image_prompt: this.script.thumbnail_image_prompt || 'Professional YouTube thumbnail',
          used_service: this.script.thumbnail_used_service
        });
        return segs;
      },

      get allSegments() {
        if (!this.script) return [];
        const segs = [];
        if (this.script.hook)  segs.push({ type:'hook',  text:this.script.hook,  idx:0 });
        (this.script.scenes||[]).forEach((sc,i) => segs.push({ type:'scene', text:sc.text, idx:segs.length, sceneNum:sc.scene||(i+1) }));
        if (this.script.outro) segs.push({ type:'outro', text:this.script.outro, idx:segs.length });
        return segs;
      },
      get sceneSegments() { return this.script?.scenes || []; },
      truncate(s, n=380) { return s && s.length>n ? s.slice(0,n)+'…' : (s||''); },

      serviceIcon(provider) {
        const icons = { gemini:'🧠', elevenlabs:'🎙️', huggingface:'🤗', pexels:'📷', pollinations:'🎨' };
        return icons[provider] || '🔧';
      },
      serviceLabel(provider) {
        const labels = { gemini:'Gemini (Script)', elevenlabs:'ElevenLabs (TTS)', huggingface:'HuggingFace (Görsel)', pexels:'Pexels (Görsel)', pollinations:'Pollinations.ai (Görsel)' };
        return labels[provider] || provider;
      },
      usedServiceBadge(service) {
        const badges = { pollinations:'🎨 Pollinations', huggingface:'🤗 HF', pexels:'📷 Pexels', fal:'⚡ Fal', failed:'❌ Hata' };
        return badges[service] || (service ? '✓ '+service : '');
      },
      usedServiceColor(service) {
        if (service === 'pollinations') return 'bg-purple-100 text-purple-800';
        if (service === 'huggingface') return 'bg-yellow-100 text-yellow-800';
        if (service === 'pexels') return 'bg-blue-100 text-blue-800';
        if (service === 'fal') return 'bg-green-100 text-green-800';
        if (service === 'failed') return 'bg-red-100 text-red-600';
        return 'bg-gray-100 text-gray-600';
      },

      // ── Real-time auto refresh ─────────────────────────────────────────────
      startAutoRefresh() {
        if (this.autoRefreshInterval) return;
        this.autoRefreshInterval = setInterval(async () => {
          const prevStatus = this.job?.status;
          await this.refreshJob();
          await this.loadScript();
          // Update all image timestamps when status changes or imaging
          if (this.job?.status === 'imaging' || this.job?.status === 'done') {
            const ts = Date.now();
            this.imgTimestamps = { ...this.imgTimestamps, hook: ts, outro: ts };
            (this.script?.scenes || []).forEach((sc, i) => {
              this.imgTimestamps[sc.scene || (i + 1)] = ts;
            });
          }
          // Stop auto refresh when done or failed
          if (this.job?.status === 'done' || this.job?.status === 'failed') {
            this.stopAutoRefresh();
          }
        }, 3000);
      },
      stopAutoRefresh() {
        if (this.autoRefreshInterval) {
          clearInterval(this.autoRefreshInterval);
          this.autoRefreshInterval = null;
        }
      },
      
      // ── YouTube Metadata ────────────────────────────────────────────────────
      initializeYouTubeMetadata() {
        if (!this.job || !this.news) return;
        
        // If job already has saved metadata, use it
        if (this.job.youtube_metadata) {
          this.youtubeMetadata = { 
            title: this.job.youtube_metadata.title || '',
            description: this.job.youtube_metadata.description || '',
            tags: this.job.youtube_metadata.tags || [],
            thumbnail: this.job.youtube_metadata.thumbnail || null,
            category_id: this.job.youtube_metadata.category_id || '28',
            privacy_status: this.job.youtube_metadata.privacy_status || 'public'
          };
          return;
        }
        
        // Generate default metadata
        const title = this.optimizeTitle(this.news.title || 'YouTube Short');
        const description = this.generateDescription();
        const tags = this.generateTags();
        
        this.youtubeMetadata = {
          title,
          description,
          tags,
          thumbnail: this.job.previewUrl ? `/output/${this.jobId}/thumbnail.jpg` : null,
          category_id: '28',
          privacy_status: 'public'
        };
      },
      
      optimizeTitle(text) {
        if (!text) return 'YouTube Short';
        // Max 100 karakter, hashtag ekle
        let title = text.trim();
        if (title.length > 90) {
          title = title.substring(0, 90) + '...';
        }
        return title + ' #Shorts';
      },
      
      generateDescription() {
        if (!this.news) return '';
        
        let desc = '';
        if (this.news.title) {
          desc += this.news.title + '\n\n';
        }
        if (this.news.text) {
          const summary = this.news.text.substring(0, 300);
          desc += summary + (this.news.text.length > 300 ? '...' : '') + '\n\n';
        }
        
        // Hashtags ve footer ekle
        desc += '🔔 Abone olmayı unutmayın!\n\n';
        desc += '#Shorts #Haber #Teknoloji #Gündem #Türkçe\n\n';
        
        if (this.news.url) {
          desc += '📰 Kaynak: ' + this.news.url;
        }
        
        return desc;
      },
      
      generateTags() {
        const baseTags = ['Shorts', 'haber', 'teknoloji', 'türkçe', 'gündem'];
        
        // Title'dan keyword çıkar
        if (this.news?.title) {
          const words = this.news.title.toLowerCase()
            .replace(/[^\wığüşöçİĞÜŞÖÇ\s]/g, '')
            .split(/\s+/)
            .filter(w => w.length > 4);
          baseTags.push(...words.slice(0, 10));
        }
        
        return Array.from(new Set(baseTags)).slice(0, 15);
      },
      
      async saveYouTubeMetadata() {
        // Metadata'yı job JSON'a kaydet
        try {
          const response = await fetch('/api/jobs.php', {
            method: 'PATCH',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
              jobId: this.jobId,
              action: 'update_youtube_metadata',
              metadata: this.youtubeMetadata
            })
          });
          
          const result = await response.json();
          if (result.success) {
            alert('✅ YouTube metadata kaydedildi!');
            this.editingYouTubeMetadata = false;
            await this.loadJob(); // Reload to get saved metadata
          } else {
            alert('❌ Kaydetme başarısız: ' + (result.error || 'Bilinmeyen hata'));
          }
        } catch (error) {
          alert('❌ Hata: ' + error.message);
        }
      },
      
      async uploadToYouTube() {
        if (!confirm('Bu videoyu YouTube\'a yüklemek istediğinizden emin misiniz?')) return;
        
        this.uploadingToYouTube = true;
        
        try {
          const response = await fetch('/api/youtube_upload.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
              job_id: this.jobId,
              video_path: this.job.previewUrl,
              metadata: {
                title: this.youtubeMetadata.title,
                description: this.youtubeMetadata.description,
                tags: this.youtubeMetadata.tags,
                category_id: this.youtubeMetadata.category_id || '28',
                privacy_status: this.youtubeMetadata.privacy_status || 'public'
              }
            })
          });
          
          const result = await response.json();
          
          if (result.success) {
            alert('✅ Video başarıyla yüklendi!\n\nVideo ID: ' + result.video_id);
            await this.loadJob(); // Refresh to show upload status
          } else {
            alert('❌ Yükleme başarısız: ' + (result.error || 'Bilinmeyen hata'));
          }
        } catch (error) {
          alert('❌ Hata: ' + error.message);
        } finally {
          this.uploadingToYouTube = false;
        }
      },

      init() {
        this.customStyle = { ...this.SUBTITLE_PRESETS.classic };
        this.darkMode = localStorage.getItem('darkMode') === '1';
        document.documentElement.classList.toggle('dark', this.darkMode);
        // Load config to prefill image service selector
        fetch('/api/config.php').then(r => r.json()).then(cfg => {
          this.configImageService = cfg.imageService || 'auto';
          this.globalImageService = cfg.imageService || 'auto';
          this.pollinationsModel  = cfg.pollinationsModel || 'flux';
        }).catch(() => {});
        this.loadJob().then(() => {
          // Initialize YouTube metadata after job loads
          this.initializeYouTubeMetadata();
        });
      },
      toggleDark() {
        this.darkMode = !this.darkMode;
        localStorage.setItem('darkMode', this.darkMode ? '1' : '0');
        document.documentElement.classList.toggle('dark', this.darkMode);
      }
    };
  }

  // Small regen button component helper
  function regenBtn(section, colors) { return colors; }

  </script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.0/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-50" x-data="projectApp()" x-init="init()">
  <div class="flex flex-col h-screen">
    <?php include __DIR__ . '/components/_header.php'; ?>
    <div class="flex flex-1 overflow-hidden">
      <?php include __DIR__ . '/components/_sidebar.php'; ?>
      <!-- Main scrollable area -->
    <div class="flex-1 overflow-y-auto flex flex-col">

  <!-- Loading -->
  <template x-if="loading">
    <div class="flex items-center justify-center py-32">
      <svg class="w-8 h-8 text-blue-500 anim-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
      <span class="ml-3 text-gray-500">Yükleniyor…</span>
    </div>
  </template>

  <!-- Page Error -->
  <template x-if="!loading && pageError">
    <div class="max-w-xl mx-auto mt-16 p-6 bg-red-50 border border-red-200 rounded-xl text-center">
      <p class="text-red-600 font-medium" x-text="pageError"></p>
      <a href="dashboard.html" class="mt-3 inline-block text-sm text-blue-600 hover:underline">← Dashboard'a dön</a>
    </div>
  </template>

  <template x-if="!loading && job">
    <div class="flex flex-col flex-1">

      <!-- Job info bar -->
      <div class="bg-white border-b border-gray-200 px-5 py-3">
        <div class="max-w-5xl mx-auto flex flex-wrap items-center gap-3 justify-between">
          <div>
            <p class="font-semibold text-gray-800 text-sm" x-text="news?.title || jobId"></p>
            <p class="text-xs text-gray-400 font-mono truncate max-w-xs sm:max-w-none" x-text="jobId"></p>
          </div>
          <div class="flex items-center gap-3 text-xs text-gray-400">
            <!-- Video Dimensions -->
            <template x-if="job.videoWidth && job.videoHeight">
              <span class="bg-blue-50 text-blue-700 px-2 py-1 rounded-full font-semibold" x-text="'📐 ' + job.videoWidth + 'x' + job.videoHeight"></span>
            </template>
            <!-- Status Badge -->
            <span class="px-2 py-1 rounded-full font-semibold flex items-center gap-1.5" :class="getStatusColor(job.status)">
              <template x-if="isActive">
                <svg class="w-3 h-3 anim-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
              </template>
              <span x-text="getStatusLabel(job.status)"></span>
            </span>
            <span x-text="job.created_at"></span>
            <template x-if="job.status==='failed' && job.error">
              <span class="text-red-500" x-text="'❌ '+job.error"></span>
            </template>
          </div>
        </div>
      </div>

      <!-- ── Tab Nav ─────────────────────────────────────────────────────── -->
      <div class="bg-white border-b border-gray-200 sticky top-0 z-20">
        <div class="max-w-5xl mx-auto flex overflow-x-auto">
          <button @click="activeTab='content'"  class="tab-btn flex-shrink-0 px-5 py-3 text-sm text-gray-500 border-b-2 border-transparent whitespace-nowrap" :class="activeTab==='content'?'tab-active':''">📰 Haber & Script</button>
          <button @click="activeTab='images'"   class="tab-btn flex-shrink-0 px-5 py-3 text-sm text-gray-500 border-b-2 border-transparent whitespace-nowrap" :class="activeTab==='images'?'tab-active':''">🖼️ Görseller</button>
          <button @click="activeTab='media'"    class="tab-btn flex-shrink-0 px-5 py-3 text-sm text-gray-500 border-b-2 border-transparent whitespace-nowrap" :class="activeTab==='media'?'tab-active':''">🔊 Ses & Altyazı</button>
          <button @click="activeTab='video'"    class="tab-btn flex-shrink-0 px-5 py-3 text-sm text-gray-500 border-b-2 border-transparent whitespace-nowrap" :class="activeTab==='video'?'tab-active':''">🎬 Video</button>
          <button @click="activeTab='youtube'"  class="tab-btn flex-shrink-0 px-5 py-3 text-sm text-gray-500 border-b-2 border-transparent whitespace-nowrap" :class="activeTab==='youtube'?'tab-active'':''">📺 YouTube</button>
          <button @click="activeTab='services'" class="tab-btn flex-shrink-0 px-5 py-3 text-sm text-gray-500 border-b-2 border-transparent whitespace-nowrap" :class="activeTab==='services'?'tab-active':''">🔧 Servisler</button>
        </div>
      </div>

      <!-- ── Tab Content ─────────────────────────────────────────────────── -->
      <div class="flex-1 overflow-y-auto py-6 px-4 md:px-6">
        <div class="max-w-5xl mx-auto space-y-5">

          <!-- ══════════════════════════════════ TAB: Haber & Script ══════ -->
          <div x-show="activeTab==='content'" class="space-y-5 anim-fade">

            <!-- Haber -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
              <div class="flex items-center justify-between px-5 py-3 border-b border-gray-50">
                <h2 class="font-bold text-gray-700 flex items-center gap-2"><span>📰</span> Haber İçeriği</h2>
                <button @click="regenerate('news')" :disabled="rs('news')==='running'"
                  class="regen-btn text-xs px-3 py-1.5 rounded-lg font-semibold transition flex items-center gap-1.5"
                  :class="rs('news')==='running'?'bg-gray-100 text-gray-400 cursor-not-allowed':rs('news')==='done'?'bg-green-100 text-green-700':'bg-blue-50 text-blue-700 hover:bg-blue-100'">
                  <template x-if="rs('news')==='running'"><svg class="w-3 h-3 anim-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></template>
                  <span x-text="rs('news')==='running'?'Çekiliyor…':rs('news')==='done'?'✓ Tamam':'↺ Yeniden Çek'"></span>
                </button>
              </div>
              <div class="p-5">
                <template x-if="news">
                  <div class="space-y-3">
                    <p class="font-semibold text-gray-800" x-text="news.title"></p>
                    <p class="text-sm text-gray-600 leading-relaxed" x-text="truncate(news.text, 400)"></p>
                    <template x-if="news.top_image">
                      <img :src="news.top_image" class="rounded-lg max-h-40 object-cover w-full" onerror="this.style.display='none'">
                    </template>
                  </div>
                </template>
                <template x-if="!news"><p class="text-sm text-gray-400 italic">Henüz mevcut değil.</p></template>
              </div>
            </div>

            <!-- Script -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
              <div class="flex items-center justify-between px-5 py-3 border-b border-gray-50">
                <h2 class="font-bold text-gray-700 flex items-center gap-2"><span>📝</span> Video Scripti</h2>
                <button @click="regenerate('script')" :disabled="rs('script')==='running'"
                  class="text-xs px-3 py-1.5 rounded-lg font-semibold transition flex items-center gap-1.5"
                  :class="rs('script')==='running'?'bg-gray-100 text-gray-400 cursor-not-allowed':rs('script')==='done'?'bg-green-100 text-green-700':'bg-indigo-50 text-indigo-700 hover:bg-indigo-100'">
                  <template x-if="rs('script')==='running'"><svg class="w-3 h-3 anim-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></template>
                  <span x-text="rs('script')==='running'?'Üretiliyor…':rs('script')==='done'?'✓ Tamam':'↺ Yeniden Oluştur'"></span>
                </button>
              </div>
              <div class="p-5 space-y-4">
                <template x-if="!script"><p class="text-sm text-gray-400 italic">Henüz mevcut değil.</p></template>
                <template x-if="script">
                  <div class="space-y-3">
                    <!-- Hook - Sahne gibi görünür -->
                    <template x-if="script.hook">
                      <div class="border border-gray-100 rounded-lg p-3 bg-gray-50 space-y-2">
                        <div class="flex gap-2 items-center">
                          <span class="text-[11px] font-bold bg-yellow-500 text-black px-2 py-0.5 rounded-full">🎯 Hook</span>
                          <template x-if="script.hook_used_service">
                            <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full" :class="usedServiceColor(script.hook_used_service)" x-text="usedServiceBadge(script.hook_used_service)"></span>
                          </template>
                        </div>
                        <p class="text-sm text-gray-700" x-text="script.hook"></p>
                        <!-- Hook Prompt -->
                        <div class="bg-purple-50 border border-purple-100 rounded p-2 space-y-2">
                          <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold text-purple-700">🖼️ Görsel Promptu</span>
                          </div>
                          <p class="text-xs text-purple-800 leading-snug" x-text="script.hook_image_prompt || 'Hook için görsel prompt henüz üretilmedi'"></p>
                        </div>
                      </div>
                    </template>
                    <!-- Sahneler -->
                    <template x-for="(sc, si) in sceneSegments" :key="si">
                      <div class="border border-gray-100 rounded-lg p-3 bg-gray-50 space-y-2">
                        <div class="flex gap-2 items-center">
                          <span class="text-[11px] font-bold bg-indigo-600 text-white px-2 py-0.5 rounded-full" x-text="'Sahne '+(sc.scene||(si+1))"></span>
                          <span class="text-xs text-gray-400" x-text="(sc.duration||'?')+'s'"></span>
                          <template x-if="sc.used_service">
                            <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full" :class="usedServiceColor(sc.used_service)" x-text="usedServiceBadge(sc.used_service)"></span>
                          </template>
                        </div>
                        <p class="text-sm text-gray-700" x-text="sc.text"></p>
                        <!-- Prompt edit -->
                        <div class="bg-purple-50 border border-purple-100 rounded p-2 space-y-2">
                          <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold text-purple-700">🖼️ Görsel Promptu</span>
                            <button @click="startEditPrompt(sc.scene||(si+1))" x-show="editingPrompt !== (sc.scene||(si+1))"
                              class="text-[10px] text-purple-600 border border-purple-200 px-2 py-0.5 rounded hover:bg-purple-100 transition">✏️ Düzenle</button>
                          </div>
                          <p class="text-xs text-purple-800 leading-snug" x-show="editingPrompt !== (sc.scene||(si+1))" x-text="sc.image_prompt"></p>
                          <div x-show="editingPrompt === (sc.scene||(si+1))" class="space-y-2">
                            <textarea x-model="promptDraft" rows="3" class="w-full text-xs border border-purple-300 rounded p-2 bg-white focus:outline-none focus:ring-2 focus:ring-purple-400 resize-y"></textarea>
                            <div class="flex flex-wrap gap-2">
                              <button @click="savePrompt(sc.scene||(si+1))" class="text-xs px-3 py-1 bg-purple-600 text-white rounded hover:bg-purple-700 font-semibold">💾 Kaydet</button>
                              <button @click="saveAndRegenPrompt(sc.scene||(si+1))" class="text-xs px-3 py-1 bg-indigo-600 text-white rounded hover:bg-indigo-700 font-semibold">🔄 Kaydet &amp; Görsel Yenile</button>
                              <button @click="cancelEditPrompt()" class="text-xs px-3 py-1 bg-gray-100 text-gray-600 rounded hover:bg-gray-200">İptal</button>
                            </div>
                          </div>
                        </div>
                      </div>
                    </template>
                    <!-- Outro - Sahne gibi görünür -->
                    <template x-if="script.outro">
                      <div class="border border-gray-100 rounded-lg p-3 bg-gray-50 space-y-2">
                        <div class="flex gap-2 items-center">
                          <span class="text-[11px] font-bold bg-green-500 text-white px-2 py-0.5 rounded-full">🏁 Outro</span>
                          <template x-if="script.outro_used_service">
                            <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded-full" :class="usedServiceColor(script.outro_used_service)" x-text="usedServiceBadge(script.outro_used_service)"></span>
                          </template>
                        </div>
                        <p class="text-sm text-gray-700" x-text="script.outro"></p>
                        <!-- Outro Prompt -->
                        <div class="bg-purple-50 border border-purple-100 rounded p-2 space-y-2">
                          <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold text-purple-700">🖼️ Görsel Promptu</span>
                          </div>
                          <p class="text-xs text-purple-800 leading-snug" x-text="script.outro_image_prompt || 'Outro için görsel prompt henüz üretilmedi'"></p>
                        </div>
                      </div>
                    </template>
                  </div>
                </template>
              </div>
            </div>

          </div><!-- /content tab -->

          <!-- ══════════════════════════════════ TAB: Görseller ═══════════ -->
          <div x-show="activeTab==='images'" class="space-y-5 anim-fade">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
              <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 border-b border-gray-50">
                <h2 class="font-bold text-gray-700 flex items-center gap-2"><span>🖼️</span> Tüm Görseller (Hook + Sahneler + Outro)</h2>
                <div class="flex flex-wrap items-center gap-2">
                  <!-- Video ebat bilgisi -->
                  <template x-if="job?.videoWidth && job?.videoHeight">
                    <span class="text-xs text-gray-500 bg-gray-100 px-2 py-1 rounded-lg">
                      📐 <span x-text="job.videoWidth + 'x' + job.videoHeight"></span>
                    </span>
                  </template>
                  <select x-model="globalImageService" class="text-xs border border-gray-200 rounded-lg px-2 py-1.5 bg-gray-50 focus:outline-none focus:ring-2 focus:ring-purple-300">
                    <option value="auto">🔄 Otomatik</option>
                    <option value="fal">⚡ Fal.ai</option>
                    <option value="pollinations">🎨 Pollinations.ai</option>
                    <option value="huggingface">🤗 HuggingFace</option>
                    <option value="pexels">📷 Pexels</option>
                  </select>
                  <button @click="regenAllImagesBtn()" :disabled="rs('images')==='running'"
                    class="text-xs px-3 py-1.5 rounded-lg font-semibold transition flex items-center gap-1.5"
                    :class="rs('images')==='running'?'bg-gray-100 text-gray-400 cursor-not-allowed':rs('images')==='done'?'bg-green-100 text-green-700':'bg-purple-50 text-purple-700 hover:bg-purple-100'">
                    <template x-if="rs('images')==='running'"><svg class="w-3 h-3 anim-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></template>
                    <span x-text="rs('images')==='running'?'Üretiliyor…':rs('images')==='done'?'✓ Tamam':'↺ Tümünü Yenile'"></span>
                  </button>
                </div>
              </div>
              <div class="p-5">
                <template x-if="allImageSegments.length === 0"><p class="text-sm text-gray-400 italic">Script yüklenmeden görseller gösterilemiyor.</p></template>
                <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
                  <template x-for="(seg, si) in allImageSegments" :key="si">
                    <div class="space-y-2">
                      <!-- Image with overlay -->
                      <div class="relative bg-gray-100 rounded-lg overflow-hidden"
                        :class="{
                          'img-9-16': seg.type !== 'thumbnail',
                          'ring-2 ring-yellow-400': seg.type === 'hook',
                          'ring-2 ring-green-400': seg.type === 'outro',
                          'ring-2 ring-blue-400': seg.type === 'thumbnail'
                        }"
                        :style="seg.type === 'thumbnail' ? 'aspect-ratio: 9/16' : ''">
                        <img 
                          :src="seg.type==='hook' ? hookImgSrc() : (seg.type==='outro' ? outroImgSrc() : (seg.type==='thumbnail' ? thumbnailImgSrc() : sceneImgSrc(seg.sceneNum)))"
                          class="w-full h-full object-cover"
                          onerror="this.parentElement.querySelector('.img-err').style.display='flex'; this.style.display='none'">
                        <div class="img-err hidden absolute inset-0 items-center justify-center text-gray-400 text-xs text-center p-2 bg-gray-50">Görsel yok</div>
                        <!-- Badge -->
                        <span class="absolute top-1.5 left-1.5 text-[10px] font-bold px-1.5 py-0.5 rounded-full"
                          :class="{
                            'bg-yellow-500 text-black': seg.type === 'hook',
                            'bg-green-500 text-white': seg.type === 'outro',
                            'bg-blue-500 text-white': seg.type === 'thumbnail',
                            'bg-black/60 text-white': seg.type === 'scene'
                          }"
                          x-text="seg.type==='hook'?'🎯 Hook':(seg.type==='outro'?'🏁 Outro':(seg.type==='thumbnail'?'📸 Kapak':'S'+seg.sceneNum))"></span>
                        <!-- Service badge -->
                        <template x-if="seg.used_service">
                          <span class="absolute bottom-1.5 right-1.5 text-[10px] font-bold px-1.5 py-0.5 rounded-full" :class="usedServiceColor(seg.used_service)" x-text="usedServiceBadge(seg.used_service)"></span>
                        </template>
                        <!-- Regen overlay -->
                        <template x-if="rs('img_'+(seg.type==='scene'?seg.sceneNum:seg.type))==='running'">
                          <div class="absolute inset-0 bg-black/50 flex items-center justify-center">
                            <svg class="w-7 h-7 text-white anim-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                          </div>
                        </template>
                        <template x-if="rs('img_'+(seg.type==='scene'?seg.sceneNum:seg.type))==='done'">
                          <div class="absolute inset-0 bg-green-500/30 flex items-center justify-center">
                            <svg class="w-8 h-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg>
                          </div>
                        </template>
                      </div>
                      <!-- Prompt preview (tüm tipler için AI promptu göster) -->
                      <p class="text-[10px] text-gray-500 leading-snug line-clamp-2" x-text="seg.image_prompt"></p>
                      <!-- Service selector + regen button (tüm tipler için) -->
                      <div class="space-y-1">
                        <select @change="sceneImageService[seg.type==='scene'?seg.sceneNum:seg.type] = $event.target.value"
                          class="w-full text-[10px] border border-gray-200 rounded px-1.5 py-1 bg-gray-50 text-gray-600 focus:outline-none">
                          <option value="">Global servis</option>
                          <option value="fal">⚡ Fal.ai</option>
                          <option value="pollinations">🎨 Pollinations</option>
                          <option value="huggingface">🤗 HuggingFace</option>
                          <option value="pexels">📷 Pexels</option>
                        </select>
                        <button @click="seg.type==='scene' ? regenSingleImage(seg.sceneNum) : regenSpecialImage(seg.type)"
                          :disabled="rs('img_'+(seg.type==='scene'?seg.sceneNum:seg.type))==='running'"
                          class="w-full text-[10px] font-semibold px-2 py-1.5 rounded border transition"
                          :class="rs('img_'+(seg.type==='scene'?seg.sceneNum:seg.type))==='running'?'bg-gray-50 text-gray-400 border-gray-200 cursor-not-allowed':'bg-white text-purple-700 border-purple-200 hover:bg-purple-50'">
                          <span x-text="rs('img_'+(seg.type==='scene'?seg.sceneNum:seg.type))==='running'?'Üretiliyor…':'🔄 Yenile'"></span>
                        </button>
                      </div>
                    </div>
                  </template>
                </div>
              </div>
            </div>
            
          </div><!-- /images tab -->

          <!-- ══════════════════════════════════ TAB: Ses & Altyazı ══════ -->
          <div x-show="activeTab==='media'" class="space-y-5 anim-fade">

            <!-- Seslendirme -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
              <div class="flex items-center justify-between px-5 py-3 border-b border-gray-50">
                <h2 class="font-bold text-gray-700 flex items-center gap-2"><span>🔊</span> Seslendirme</h2>
                <div class="flex items-center gap-2">
                  <span class="text-[10px] text-gray-400 hidden sm:block">TTS sonrası altyazı otomatik güncellenir</span>
                  <button @click="regenerate('tts')" :disabled="rs('tts')==='running'"
                    class="text-xs px-3 py-1.5 rounded-lg font-semibold transition flex items-center gap-1.5"
                    :class="rs('tts')==='running'?'bg-gray-100 text-gray-400 cursor-not-allowed':rs('tts')==='done'?'bg-green-100 text-green-700':'bg-pink-50 text-pink-700 hover:bg-pink-100'">
                    <template x-if="rs('tts')==='running'"><svg class="w-3 h-3 anim-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></template>
                    <span x-text="rs('tts')==='running'?'Üretiliyor…':rs('tts')==='done'?'✓ Tamam':'↺ Yeniden Üret'"></span>
                  </button>
                </div>
              </div>
              <div class="p-5 space-y-4">
                <div>
                  <p class="text-xs font-semibold text-gray-500 mb-2">Tam Ses Dosyası</p>
                  <audio controls class="w-full rounded-lg" :src="fullAudioUrl()" preload="metadata">Tarayıcınız desteklemiyor.</audio>
                </div>
                <template x-if="allSegments.length > 0">
                  <div>
                    <p class="text-xs font-semibold text-gray-500 mb-2">Segment Bazlı (Hook + Sahneler + Outro)</p>
                    <div class="space-y-2">
                      <template x-for="seg in allSegments" :key="seg.idx">
                        <div class="flex items-center gap-3 bg-gray-50 border border-gray-100 rounded-lg p-2.5">
                          <span class="flex-shrink-0 text-xs font-bold px-2 py-0.5 rounded-full"
                            :class="seg.type==='hook'?'bg-yellow-100 text-yellow-700':seg.type==='outro'?'bg-green-100 text-green-700':'bg-indigo-100 text-indigo-700'"
                            x-text="seg.type==='hook'?'Hook':seg.type==='outro'?'Outro':'S'+(seg.sceneNum||seg.idx)"></span>
                          <p class="flex-1 text-xs text-gray-600 truncate" x-text="seg.text"></p>
                          <audio controls class="h-8 flex-shrink-0 w-36 md:w-52" :src="segAudioUrl(seg.idx)" preload="none"></audio>
                        </div>
                      </template>
                    </div>
                  </div>
                </template>
              </div>
            </div>

            <!-- Altyazılar + Tasarım -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
              <div class="flex items-center justify-between px-5 py-3 border-b border-gray-50">
                <h2 class="font-bold text-gray-700 flex items-center gap-2"><span>📺</span> Altyazılar (SRT)</h2>
                <button @click="regenerate('subtitles')" :disabled="rs('subtitles')==='running'"
                  class="text-xs px-3 py-1.5 rounded-lg font-semibold transition flex items-center gap-1.5"
                  :class="rs('subtitles')==='running'?'bg-gray-100 text-gray-400 cursor-not-allowed':rs('subtitles')==='done'?'bg-green-100 text-green-700':'bg-orange-50 text-orange-700 hover:bg-orange-100'">
                  <template x-if="rs('subtitles')==='running'"><svg class="w-3 h-3 anim-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></template>
                  <span x-text="rs('subtitles')==='running'?'Üretiliyor…':rs('subtitles')==='done'?'✓ Tamam':'↺ Yeniden Üret'"></span>
                </button>
              </div>
              <div class="p-5 space-y-4">
                <!-- Segment-by-segment subtitle view (shows hook + scenes + outro) -->
                <template x-if="allSegments.length > 0">
                  <div>
                    <p class="text-xs font-semibold text-gray-500 mb-2">Altyazı Segmentleri (Hook + Sahneler + Outro)</p>
                    <div class="space-y-1.5">
                      <template x-for="seg in allSegments" :key="seg.idx">
                        <div class="flex items-start gap-2 bg-gray-50 border border-gray-100 rounded-lg px-3 py-2">
                          <span class="flex-shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded-full mt-0.5"
                            :class="seg.type==='hook'?'bg-yellow-100 text-yellow-700':seg.type==='outro'?'bg-green-100 text-green-700':'bg-indigo-100 text-indigo-700'"
                            x-text="seg.type==='hook'?'Hook':seg.type==='outro'?'Outro':'S'+(seg.sceneNum||seg.idx)"></span>
                          <p class="text-xs text-gray-600" x-text="seg.text"></p>
                        </div>
                      </template>
                    </div>
                  </div>
                </template>
                <template x-if="job.subtitles">
                  <pre class="srt-box text-xs text-gray-700 bg-gray-50 border border-gray-200 rounded-lg p-4 overflow-x-auto max-h-56 overflow-y-auto" x-text="job.subtitles"></pre>
                </template>
                <template x-if="!job.subtitles"><p class="text-sm text-gray-400 italic">Henüz mevcut değil.</p></template>

                <!-- Subtitle Designer (always open) -->
                <div class="border border-gray-200 rounded-lg overflow-hidden">
                  <div class="flex items-center gap-2 px-4 py-3 bg-gray-50 font-semibold text-sm text-gray-700">
                    🎨 Altyazı Tasarımı
                  </div>
                  <div class="p-4 space-y-4">
                    <div class="flex gap-2 pb-3 border-b border-gray-100">
                      <button @click="subtitleMode='preset'" class="text-xs font-semibold px-3 py-1.5 rounded-lg transition" :class="subtitleMode==='preset'?'bg-indigo-600 text-white':'bg-gray-100 text-gray-600 hover:bg-gray-200'">Hazır Tasarım</button>
                      <button @click="subtitleMode='custom'" class="text-xs font-semibold px-3 py-1.5 rounded-lg transition" :class="subtitleMode==='custom'?'bg-indigo-600 text-white':'bg-gray-100 text-gray-600 hover:bg-gray-200'">Özel Tasarım</button>
                    </div>

                    <!-- Preset grid -->
                    <div x-show="subtitleMode==='preset'" class="grid grid-cols-3 sm:grid-cols-6 gap-3">
                      <template x-for="(preset, key) in SUBTITLE_PRESETS" :key="key">
                        <div @click="applyPreset(key)" class="cursor-pointer rounded-xl border-2 overflow-hidden transition"
                          :class="selectedPreset===key?'border-indigo-500 shadow-md':'border-gray-100 hover:border-gray-300'">
                          <div class="sub-preview mx-auto">
                            <div class="sub-preview-text" :style="subContainerStyle(key)">
                              <span :style="subPreviewStyle(key)" class="block text-center px-1" x-text="preset.label"></span>
                            </div>
                          </div>
                          <p class="text-[10px] text-center font-semibold text-gray-600 py-1 px-1 truncate" x-text="preset.label"></p>
                        </div>
                      </template>
                    </div>

                    <!-- Custom controls -->
                    <div x-show="subtitleMode==='custom'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      <div><label class="text-xs font-semibold text-gray-600 block mb-1">Yazı Boyutu: <span x-text="customStyle.FontSize+'px'"></span></label>
                        <input type="range" min="12" max="40" x-model.number="customStyle.FontSize" class="w-full accent-indigo-600"></div>
                      <div><label class="text-xs font-semibold text-gray-600 block mb-1">Alt Boşluk: <span x-text="customStyle.MarginV+'px'"></span></label>
                        <input type="range" min="20" max="300" x-model.number="customStyle.MarginV" class="w-full accent-indigo-600"></div>
                      <div><label class="text-xs font-semibold text-gray-600 block mb-1">Sol Boşluk: <span x-text="customStyle.MarginL+'px'"></span></label>
                        <input type="range" min="0" max="200" x-model.number="customStyle.MarginL" class="w-full accent-indigo-600"></div>
                      <div><label class="text-xs font-semibold text-gray-600 block mb-1">Sağ Boşluk: <span x-text="customStyle.MarginR+'px'"></span></label>
                        <input type="range" min="0" max="200" x-model.number="customStyle.MarginR" class="w-full accent-indigo-600"></div>
                      <div><label class="text-xs font-semibold text-gray-600 block mb-1">Yazı Rengi</label>
                        <input type="color" x-model="customStyle.PrimaryColour" class="w-full h-8 rounded border border-gray-200 cursor-pointer p-0.5"></div>
                      <div><label class="text-xs font-semibold text-gray-600 block mb-1">Dış Hat Rengi</label>
                        <input type="color" x-model="customStyle.OutlineColour" class="w-full h-8 rounded border border-gray-200 cursor-pointer p-0.5"></div>
                      <div><label class="text-xs font-semibold text-gray-600 block mb-1">Dış Hat: <span x-text="customStyle.Outline"></span></label>
                        <input type="range" min="0" max="5" step="0.5" x-model.number="customStyle.Outline" class="w-full accent-indigo-600"></div>
                      <div><label class="text-xs font-semibold text-gray-600 block mb-1">Gölge: <span x-text="customStyle.Shadow"></span></label>
                        <input type="range" min="0" max="4" x-model.number="customStyle.Shadow" class="w-full accent-indigo-600"></div>
                      <div class="flex items-center gap-2">
                        <input type="checkbox" :checked="customStyle.Bold===1" @change="customStyle.Bold=$event.target.checked?1:0" class="w-4 h-4 accent-indigo-600">
                        <label class="text-xs font-semibold text-gray-600">Kalın Yazı</label>
                      </div>
                      <div><label class="text-xs font-semibold text-gray-600 block mb-1">Arka Plan Stili</label>
                        <select x-model.number="customStyle.BorderStyle" class="w-full text-xs border border-gray-200 rounded-lg px-2 py-1.5 bg-gray-50 focus:outline-none">
                          <option value="1">Çerçeve</option><option value="3">Dolu Kutu</option><option value="4">Opak Kutu</option>
                        </select>
                      </div>
                      <!-- Preview -->
                      <div class="sm:col-span-2 flex justify-center pt-2">
                        <div class="sub-preview"><div class="sub-preview-text" :style="subContainerStyle('_custom')">
                          <span :style="subPreviewStyle('_custom')" class="block text-center px-1">Örnek altyazı metni</span>
                        </div></div>
                      </div>
                    </div>

                    <!-- Apply -->
                    <div class="flex justify-end pt-2 border-t border-gray-100">
                      <button @click="regenVideoWithStyle()" :disabled="rs('video')==='running'"
                        class="flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold transition"
                        :class="rs('video')==='running'?'bg-gray-100 text-gray-400 cursor-not-allowed':'bg-indigo-600 text-white hover:bg-indigo-700'">
                        <template x-if="rs('video')==='running'"><svg class="w-4 h-4 anim-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></template>
                        <span x-text="rs('video')==='running'?'Video üretiliyor…':'🎬 Bu Tasarımla Video Üret'"></span>
                      </button>
                    </div>
                  </div>
                </div><!-- /subtitle designer div -->
              </div>
            </div>

          </div><!-- /media tab -->

          <!-- ══════════════════════════════════ TAB: Video ═════════════ -->
          <div x-show="activeTab==='video'" class="anim-fade">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
              <div class="flex items-center justify-between px-5 py-3 border-b border-gray-50">
                <h2 class="font-bold text-gray-700 flex items-center gap-2"><span>🎬</span> Final Video</h2>
                <button @click="regenVideoWithStyle()" :disabled="rs('video')==='running'"
                  class="text-xs px-3 py-1.5 rounded-lg font-semibold transition flex items-center gap-1.5"
                  :class="rs('video')==='running'?'bg-gray-100 text-gray-400 cursor-not-allowed':rs('video')==='done'?'bg-green-100 text-green-700':'bg-amber-50 text-amber-700 hover:bg-amber-100'">
                  <template x-if="rs('video')==='running'"><svg class="w-3 h-3 anim-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg></template>
                  <span x-text="rs('video')==='running'?'Birleştiriliyor…':rs('video')==='done'?'✓ Tamam':'↺ Yeniden Oluştur'"></span>
                </button>
              </div>
              <div class="p-6">
                <template x-if="videoUrl">
                  <div class="flex flex-col items-center gap-4">
                    <video controls class="rounded-xl shadow-lg max-h-[75vh] w-full max-w-xs"
                      :src="videoUrl + (rs('video')==='done' ? '?t='+Date.now() : '')" preload="metadata">
                      Tarayıcınız video oynatmayı desteklemiyor.
                    </video>
                    <a :href="videoUrl" download class="inline-flex items-center gap-2 px-4 py-2 bg-gray-800 hover:bg-gray-900 text-white text-sm rounded-lg font-semibold transition">
                      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                      İndir
                    </a>
                  </div>
                </template>
                <template x-if="!videoUrl">
                  <div class="flex flex-col items-center justify-center py-16 text-gray-400">
                    <svg class="w-16 h-16 mb-3 text-gray-200" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 10l4.553-2.069A1 1 0 0121 8.87v6.263a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                    <p class="text-sm italic">Video henüz hazır değil.</p>
                  </div>
                </template>

                <!-- Regen progress overlay message -->
                <template x-if="rs('video')==='running'">
                  <div class="mt-4 flex items-center justify-center gap-2 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg p-3">
                    <svg class="w-4 h-4 anim-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Video yeniden üretiliyor, bu birkaç dakika sürebilir…
                  </div>
                </template>
              </div>
            </div>
          </div><!-- /video tab -->

          <!-- ══════════════════════════════════ TAB: YouTube ══════════════ -->
          <div x-show="activeTab==='youtube'" class="space-y-5 anim-fade">
            
            <!-- YouTube Metadata Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
              <div class="flex items-center justify-between px-5 py-3 border-b border-gray-50">
                <h2 class="font-bold text-gray-700 flex items-center gap-2">
                  <span>📺</span> YouTube Metadata
                </h2>
                <template x-if="!editingYouTubeMetadata">
                  <button @click="editingYouTubeMetadata = true" class="text-xs px-3 py-1.5 bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100 font-semibold transition">
                    ✏️ Düzenle
                  </button>
                </template>
              </div>
              
              <div class="p-5 space-y-4">
                <!-- Title -->
                <div>
                  <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Video Başlığı (Max 100 karakter)</label>
                  <template x-if="!editingYouTubeMetadata">
                    <p class="text-sm text-gray-800 font-medium" x-text="youtubeMetadata.title"></p>
                  </template>
                  <template x-if="editingYouTubeMetadata">
                    <input type="text" x-model="youtubeMetadata.title" maxlength="100" 
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                  </template>
                </div>
                
                <!-- Description -->
                <div>
                  <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Açıklama (Max 5000 karakter)</label>
                  <template x-if="!editingYouTubeMetadata">
                    <p class="text-sm text-gray-600 whitespace-pre-wrap" x-text="youtubeMetadata.description"></p>
                  </template>
                  <template x-if="editingYouTubeMetadata">
                    <textarea x-model="youtubeMetadata.description" rows="8" maxlength="5000"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-400 resize-y"></textarea>
                  </template>
                </div>
                
                <!-- Tags -->
                <div>
                  <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Etiketler / Keywords</label>
                  <template x-if="!editingYouTubeMetadata">
                    <div class="flex flex-wrap gap-2">
                      <template x-for="tag in youtubeMetadata.tags" :key="tag">
                        <span class="px-2 py-1 bg-blue-50 text-blue-700 rounded-full text-xs font-semibold" x-text="'#' + tag"></span>
                      </template>
                    </div>
                  </template>
                  <template x-if="editingYouTubeMetadata">
                    <input type="text" :value="youtubeMetadata.tags.join(', ')" 
                      @input="youtubeMetadata.tags = $event.target.value.split(',').map(t => t.trim()).filter(t => t)"
                      placeholder="Virgülle ayırarak yazın: haber, teknoloji, türkçe"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                  </template>
                </div>
                
                <!-- Thumbnail Preview -->
                <div>
                  <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Video Kapağı</label>
                  <template x-if="youtubeMetadata.thumbnail || job.previewUrl">
                    <div class="relative inline-block">
                      <img :src="youtubeMetadata.thumbnail || job.previewUrl" 
                        class="rounded-lg max-h-48 object-cover border border-gray-200"
                        onerror="this.src='/output/' + jobId + '/hook.jpg'">
                      <div class="absolute top-2 right-2 bg-black bg-opacity-75 text-white text-xs px-2 py-1 rounded">
                        Kapak
                      </div>
                    </div>
                  </template>
                  <template x-if="!youtubeMetadata.thumbnail && !job.previewUrl">
                    <p class="text-sm text-gray-400 italic">Kapak görseli henüz yok</p>
                  </template>
                </div>
                
                <!-- Category & Privacy (only in edit mode) -->
                <template x-if="editingYouTubeMetadata">
                  <div class="grid grid-cols-2 gap-4">
                    <!-- Category -->
                    <div>
                      <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Kategori</label>
                      <select x-model="youtubeMetadata.category_id" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                        <option value="1">Film & Animasyon</option>
                        <option value="2">Araçlar & Taşıtlar</option>
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
                        <option value="28" selected>Bilim & Teknoloji</option>
                        <option value="29">Sivil Haklar & Aktivizm</option>
                      </select>
                    </div>
                    
                    <!-- Privacy -->
                    <div>
                      <label class="block text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Gizlilik</label>
                      <select x-model="youtubeMetadata.privacy_status" 
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-400">
                        <option value="public">🌍 Herkese Açık</option>
                        <option value="unlisted">🔗 Liste Dışı (link ile)</option>
                        <option value="private">🔒 Gizli (sadece siz)</option>
                      </select>
                    </div>
                  </div>
                </template>
                
                <!-- Show current settings when not editing -->
                <template x-if="!editingYouTubeMetadata">
                  <div class="flex gap-4 text-sm">
                    <span class="px-2 py-1 bg-gray-100 rounded text-gray-600">
                      📁 Kategori: <span x-text="{'1':'Film','10':'Müzik','20':'Oyun','22':'Blog','24':'Eğlence','25':'Haber','28':'Teknoloji'}[youtubeMetadata.category_id] || 'Teknoloji'"></span>
                    </span>
                    <span class="px-2 py-1 bg-gray-100 rounded text-gray-600">
                      <span x-text="{'public':'🌍 Herkese Açık','unlisted':'🔗 Liste Dışı','private':'🔒 Gizli'}[youtubeMetadata.privacy_status] || '🌍 Herkese Açık'"></span>
                    </span>
                  </div>
                </template>
                
                <!-- Action Buttons -->
                <template x-if="editingYouTubeMetadata">
                  <div class="flex gap-2 pt-4 border-t">
                    <button @click="saveYouTubeMetadata()" 
                      class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold transition">
                      💾 Kaydet
                    </button>
                    <button @click="editingYouTubeMetadata = false" 
                      class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg text-sm font-semibold transition">
                      ✖️ İptal
                    </button>
                  </div>
                </template>
              </div>
            </div>
            
            <!-- Upload Status & Actions -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
              <div class="px-5 py-3 border-b border-gray-50">
                <h2 class="font-bold text-gray-700 flex items-center gap-2">
                  <span>📤</span> Yükleme Durumu
                </h2>
              </div>
              
              <div class="p-5 space-y-3">
                <!-- Not Uploaded -->
                <template x-if="!job.youtube_upload || job.youtube_upload.status === 'not_uploaded'">
                  <div>
                    <p class="text-sm text-gray-600 mb-3">Video henüz YouTube'a yüklenmedi</p>
                    <button @click="uploadToYouTube()" 
                      :disabled="uploadingToYouTube || job.status !== 'done'"
                      class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold transition shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                      <template x-if="uploadingToYouTube">
                        <svg class="w-5 h-5 animate-spin" fill="none" viewBox="0 0 24 24">
                          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                      </template>
                      <template x-if="!uploadingToYouTube">
                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                          <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                        </svg>
                      </template>
                      <span x-text="uploadingToYouTube ? 'Yükleniyor...' : 'YouTube\'a Yükle'"></span>
                    </button>
                  </div>
                </template>
                
                <!-- Uploaded -->
                <template x-if="job.youtube_upload && job.youtube_upload.status === 'uploaded'">
                  <div class="space-y-3">
                    <div class="flex items-center gap-2 p-3 bg-green-50 border border-green-200 rounded-lg">
                      <span class="text-2xl">✅</span>
                      <div class="flex-1">
                        <p class="text-sm font-semibold text-green-800">YouTube'a başarıyla yüklendi!</p>
                        <p class="text-xs text-green-600 mt-1" x-text="'Video ID: ' + job.youtube_upload.video_id"></p>
                      </div>
                    </div>
                    
                    <a :href="job.youtube_upload.video_url" target="_blank" 
                      class="inline-flex items-center gap-2 px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-lg font-semibold transition shadow-sm">
                      <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
                      </svg>
                      YouTube'da Aç
                    </a>
                  </div>
                </template>
                
                <!-- Scheduled -->
                <template x-if="job.youtube_upload && job.youtube_upload.status === 'scheduled'">
                  <div class="p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                    <p class="text-sm text-yellow-800">
                      📅 Zamanlandı: <span x-text="new Date(job.youtube_upload.scheduled_time).toLocaleString('tr-TR')"></span>
                    </p>
                  </div>
                </template>
              </div>
            </div>
          </div><!-- /youtube tab -->

          <!-- ══════════════════════════════════ TAB: Servisler ══════════ -->
          <div x-show="activeTab==='services'" class="space-y-5 anim-fade">

            <!-- Kullanılan servisler özeti -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100">
              <div class="px-5 py-3 border-b border-gray-50">
                <h2 class="font-bold text-gray-700 flex items-center gap-2"><span>📊</span> Bu Projede Kullanılan Servisler</h2>
              </div>
              <div class="p-5 space-y-3">
                <!-- Video Ebatları -->
                <div>
                  <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Video Ebatları</p>
                  <div class="flex items-center gap-2 bg-gray-50 border border-gray-100 rounded-lg px-3 py-2 text-xs">
                    <span class="text-gray-600">Ebat:</span>
                    <span class="font-bold text-blue-700 bg-blue-50 px-2 py-0.5 rounded-full"
                      x-text="(job.videoWidth || 1080) + ' x ' + (job.videoHeight || 1920) + ' px'"></span>
                    <span class="text-gray-400" x-text="(job.videoWidth || 1080) > (job.videoHeight || 1920) ? '(Yatay)' : (job.videoWidth || 1080) < (job.videoHeight || 1920) ? '(Dikey)' : '(Kare)'"></span>
                  </div>
                </div>
                <template x-if="sceneSegments.length > 0">
                  <div>
                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Görsel Üretimi</p>
                    <div class="flex flex-wrap gap-2">
                      <template x-for="(sc, si) in sceneSegments" :key="si">
                        <div class="flex items-center gap-1.5 bg-gray-50 border border-gray-100 rounded-lg px-3 py-2 text-xs">
                          <span class="font-semibold text-gray-600" x-text="'Sahne '+(sc.scene||(si+1))+':'"></span>
                          <template x-if="sc.used_service">
                            <span class="font-bold px-1.5 py-0.5 rounded-full" :class="usedServiceColor(sc.used_service)" x-text="usedServiceBadge(sc.used_service)"></span>
                          </template>
                          <template x-if="!sc.used_service">
                            <span class="text-gray-400 italic">Henüz üretilmedi</span>
                          </template>
                        </div>
                      </template>
                    </div>
                    <!-- Config'den gelen servis tercihi ve model -->
                    <div class="mt-3 flex flex-wrap gap-2 items-center">
                      <span class="text-xs text-gray-500">Ayarlardaki tercih:</span>
                      <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-purple-100 text-purple-800"
                        x-text="configImageService === 'auto' ? '🔄 Otomatik (Poll → Fal → HF → Pexels)' : configImageService === 'fal' ? '⚡ Fal.ai' : configImageService === 'pollinations' ? '🎨 Pollinations.ai' : configImageService === 'huggingface' ? '🤗 HuggingFace' : configImageService === 'pexels' ? '📷 Pexels' : configImageService"></span>
                      <template x-if="configImageService === 'pollinations' || configImageService === 'auto'">
                        <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-indigo-100 text-indigo-700" x-text="'Model: ' + pollinationsModel"></span>
                      </template>
                    </div>
                  </div>
                </template>
                <div>
                  <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Seslendirme</p>
                  <div class="flex items-center gap-2 bg-gray-50 border border-gray-100 rounded-lg px-3 py-2 text-xs">
                    <span class="text-gray-600">TTS Servisi:</span>
                    <span class="font-bold text-pink-700 bg-pink-50 px-2 py-0.5 rounded-full"
                      x-text="job.ttsProvider === 'elevenlabs' ? '🎙️ ElevenLabs' : job.ttsProvider === 'edge-tts' ? '🔊 Edge TTS' : (job.ttsProvider || 'Bilinmiyor')"></span>
                  </div>
                </div>
                <div>
                  <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">Altyazı Tasarımı</p>
                  <div class="flex items-center gap-2 bg-gray-50 border border-gray-100 rounded-lg px-3 py-2 text-xs">
                    <span class="text-gray-600">Stil:</span>
                    <template x-if="typeof job.subtitleStyle === 'string'">
                      <span class="font-bold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-full" x-text="SUBTITLE_PRESETS[job.subtitleStyle]?.label || job.subtitleStyle"></span>
                    </template>
                    <template x-if="typeof job.subtitleStyle === 'object' && job.subtitleStyle">
                      <span class="font-bold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-full">Özel Tasarım</span>
                    </template>
                    <template x-if="!job.subtitleStyle">
                      <span class="text-gray-400">Classic (varsayılan)</span>
                    </template>
                  </div>
                </div>
              </div>
            </div>

          </div><!-- /services tab -->

        </div>
      </div>
    </div>
  </template>

    </div><!-- /main scrollable area -->
  </div><!-- /flex layout -->
</div>

    </div>
    <?php include __DIR__ . '/components/_footer.php'; ?>
  </div>
</body>
</html>
