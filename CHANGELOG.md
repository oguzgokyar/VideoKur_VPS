# 📜 Video_Kur - Değişiklik Günlüğü (CHANGELOG)

Bu dosya, projedeki tüm önemli değişiklikleri kronolojik sırayla takip eder.

---

## [2.5.1] - 2026-04-13 - Meta Hesap Kaynağı Birleştirme

### 🧹 Kaldırılanlar
- Kuyruk hesap listesinde `social_accounts.json` kaynaklı legacy Instagram/Facebook fallback kaldırıldı.
- `api/accounts.php` içinde legacy Instagram yönetimi devre dışı bırakıldı (Instagram artık yalnızca Meta Accounts V2).

### 🔧 Değişiklikler
- `api/social.php`: `get_accounts` ve `get_platforms` akışları Meta V2 (`meta_connections.json` / `meta_accounts.json`) odaklı hale getirildi.
- `api/social.php`: Meta V2 aktifken legacy `social_accounts.json` içindeki Instagram/Facebook kayıtları otomatik temizleniyor.
- `api/social.php`: `get_accounts` yanıtına `meta_diagnostics` eklendi (aktif bağlantı var ama hesap listesi boş durumunu teşhis eder).
- `api/accounts.php`: Legacy endpoint sadece TikTok için aktif, Instagram çağrıları yönlendirici hata mesajı döndürüyor.
- `frontend/queues.php`: Instagram/Facebook hesap listesi boşken teşhis mesajı gösterimi ve hesap/sayfa yoksa kaydı engelleyen doğrulama eklendi.

---

## [2.5.0] - 2026-03-30 - Sistem Temizliği ve Dark Mode İyileştirmesi

### 🗑️ Kaldırılanlar
- **Eski Scheduler Sistemi:** `frontend/scheduler.php` ve `api/scheduler.php` dosyaları kaldırıldı (artık `queues.php` birleşik sistem kullanılıyor)
- **Yedek Dosyalar:** `frontend/queues_old.php` ve `frontend/queues_backup.php` temizlendi
- **Deprecation:** Eski `upload_queue.json` ve `upload_history.json` kullanımı sonlandırıldı (zaten `queues.json` birleşik sistem aktif)

### ✨ Eklenenler
- **YouTube Multi-API Dosya İsimlendirme:** Yeni API projeleri ekleme sırasında client_secrets dosyaları artık `client_secrets_project_{id}.json` formatında oluşturuluyor (timestamp yerine ID-bazlı)
- **Dark Mode Badge Desteği:** Dashboard ve tüm sayfalarda badge renklerine `dark:bg-*-900/40` ve `dark:text-*-300` classes eklendi
- **Tailwind Dynamic Class Düzeltmesi:** Scheduler sayfasında çalışmayan dynamic Tailwind classes (`bg-${color}-100`) conditional classes ile değiştirildi

### 🔧 Değişiklikler
- `api/youtube_projects.php`: Proje ID'sine göre tahmin edilebilir dosya isimlendirme
- `frontend/dashboard.php`: 7 yerde dark mode badge classes eklendi
- `frontend/scheduler.php`: 4 yerde dynamic class → conditional class + dark mode desteği
- Dokümantasyon güncellendi: `QUICKSTART.md`, `WEB_KULLANIM.md`, `PROJECT_STATUS.md` dosyalarında scheduler.php referansları queues.php ile değiştirildi

---

## [2.4.0] - 2026-03-23 - Kuyruk Senkronizasyonu ve UI Tutarlılığı

### ✨ Eklenenler
- **İçerikler > Kuyruklar paneli güncellemesi:** Tamamen paylaşılmış öğeler listeden gizlenir, kalan öğeler güncel paylaşım durumlarıyla gösterilir.
- **Videolar sayfası filtreleri:** Üretim durumu (bekleyen/tamamlanan/tümü) ve kuyruk üyeliği filtreleri eklendi; varsayılan görünüm "bekleyen" olarak ayarlandı.
- **Sidebar durum kalıcılığı:** Sidebar açık/kapalı (collapsed) durumu sayfalar arasında `localStorage` ile korunur.

### 🐛 Hata Düzeltmeleri
- **Social scheduler çökmesi düzeltildi:** `platform_metadata` alanı list geldiğinde oluşan `'list' object has no attribute 'get'` hatası tip kontrolleriyle giderildi.
- **Scheduler durumu UI senkronu:** Paylaşım scheduler durduğunda Kuyruk Durumu kartı artık "Çalışıyor" göstermiyor.
- **Kuyruk reset sonrası yanlış durumlar:** `failed/processing` statüleri reset sonrası doğru şekilde temizlenip yeniden sıraya alınır.
- **Yanlış "Video Üretiliyor..." göstergesi:** Üretim scheduler duruyken bu durum artık gösterilmiyor.

### 🔧 Değişiklikler
- `api/queues.php`: platform durum normalizasyonu, çoklu kaynak durum birleştirme, reset kapsamı genişletme, scheduler durumu çıktısı.
- `frontend/queues.php`: platform badge/önizleme durumlarının normalize edilmesi ve gerçek scheduler durumuna göre kuyruk durum etiketi.
- `python/scheduler/social_scheduler.py`: veri şekli doğrulama, güvenli metadata erişimi ve item-bazlı hata izolasyonu.
- `frontend/content.php`: kuyruk panelinde canlı durum eşleme ve paylaşımı tamamlanan öğeleri gizleme.
- `frontend/dashboard.php`: üretim + kuyruk filtreleme ve filtreye bağlı liste/sayaç güncellemeleri.
- `frontend/components/_sidebar.php`: sidebar durumunun component içinde yönetimi ve sayfalar arası kalıcılık.

---

## [2.3.0] - 2026-03-22 - SEO Metadata Motoru ve Yükleme Stabilizasyonu

### ✨ Eklenenler
- **SEO Metadata Motoru:** Video yüklenmeden önce AI ile platform-spesifik (YouTube vb.) başlık, açıklama ve SEO uyumlu etiketlerin otomatik üretimi sağlandı.
- **`metadata_cli.py` Aracı:** Metadata motoru öncesi üretilmiş veya metadatası eksik videoların, yükleme aşamasından önce anlık metadata oluşturması için CLI modülü eklendi.
- **Scheduler Entegrasyonu:** `social_scheduler.py` ve `production_scheduler.py` içerisinde video kuyruğa alınırken otomatik metadata kontrolü ve üretimi mekanizması kuruldu.

### 🐛 Hata Düzeltmeleri
- **Yükleme Hatalarının Çözümü:** Metadata eksikliği yüzünden yükleme esnasında oluşan API hataları giderildi.
- Kuyrukta takılı kalan veya donarak hata veren videoların tekrar islenip başariyla yüklenmesi sağlandı.

### 🔧 Değişiklikler
- `python/metadata_cli.py` adlı yeni metadata yönetim modülü oluşturuldu.
- `python/scheduler/social_scheduler.py` ve `python/scheduler/production_scheduler.py` dosyalarında yükleme öncesi metadata validasyonu eklendi.
- `api/youtube.php` üzerinde metadata kullanım ve kontrol iyileştirmeleri yapıldı.

---

## [2.2.0] - 2026-03-22 - Kuyruk UI Gerçek Zamanlı Durum Göstergesi

### ✨ Eklenenler
- **Platform durum badge'leri:** Her video için platform bazlı canlı paylaşım durumu göstergesi (📺 · bekliyor, ↻ işleniyor, ✓ yayınlandı, ✕ hata)
- **İş aşaması badge'leri:** ⏳ Bekliyor / ✅ Hazır / 🎬 Video / ❌ Hata etiketleri
- **Canlı animasyon:** Aktif upload sırasında "● Canlı" yanıp sönen gösterge
- **Renkli sol kenarlık:** Video satırlarında durum bazlı renk kodu (sarı, mavi, yeşil, kırmızı)
- **Thumbnail üretim spinner:** Video üretilirken thumbnail alanında dönen mavi animasyon
- **Otomatik yenileme:** 15 saniyede bir kuyruk listesi güncellenir
- **Gerçek platform durumu:** `social_queue.json` + `social_history.json` kaynağından anlık durum okunur

### 🐛 Hata Düzeltmeleri
- **Alpine.js `x-for` nested template crash:** `<template x-if>` yerine `x-show` kullanımına geçildi
- **Duplicate job_id crash:** `x-for` key'i `video.job_id` yerine `idx` (index) kullanıyor; duplicate girişlerde Alpine çökmüyor
- **`selectedQueue` null hatası:** `selectedQueue.is_active` → `selectedQueue?.is_active` optional chaining ile korunuyor
- **Kuyruk veri deduplication:** `queues.json`'daki duplicate job_id girişleri temizlendi
- **Platform durumu "Bekliyor" kalıyor:** `api/queues.php`'de `loadSocialPlatformStatus()` fonksiyonu ile `social_queue.json`'dan gerçek durum okunuyor

### 🔧 Değişiklikler
- `frontend/queues.php`
  - Alpine.js `3.13.0` → `3.14.1` güncellendi
  - `renderPlatformBadges(video)` — iç içe `x-for` yerine JS'de HTML string üretimi
  - `filterStatus` varsayılanı `'all'` → `'pending'` (Bekleyen ilk görünür)
  - `filterVideos()` — `platform_status` bazlı filtreleme (`allPublished()`)
  - Filtre dropdown sırası: Bekleyen > Yayınlanan > Tümü
  - CSS: `.badge-*`, `.row-*`, `.icon-spin`, `.badge-live` animasyon stilleri
  - JS: `getPlatformStatus`, `getJobPhase`, `jobPhaseClass`, `jobPhaseLabel`, `isProducing`, `platformStatusClass`, `platformStatusIcon`, `platformStatusLabel`, `getPlatformPostUrl`, `allPublished`, `anyUploading`, `anyFailed`, `rowBorderClass`, `renderPlatformBadges` yardımcı fonksiyonları
- `api/queues.php`
  - `loadSocialPlatformStatus()` fonksiyonu eklendi — `social_queue.json` ve `social_history.json`'dan durum okur
  - `list` ve `get` aksiyonlarında `platform_status` gerçek durum ile override ediliyor
  - `job_status` alanı `get` aksiyon yanıtına eklendi

---

## [2.1.0] - 2026-01-18 - Modern Kuyruk UI Tasarımı

### ✨ Eklenenler
- **Kuyruk tab sistemi:** Kuyruklar arası hızlı geçiş
- **Üç kolonlu layout:** Video listesi, önizleme, ayarlar paneli
- **Video player önizleme:** Platform özel overlay'ler (TikTok, YouTube, Instagram, Facebook)
- **Düzenlenebilir metadata:** Başlık, açıklama, etiketler inline düzenlenebilir
- **Inline kuyruk ayarları:** İsim, platformlar, zamanlama anlık düzenleme
- **Dinamik sayfa başlıkları:** Header'da tüm sayfa isimleri görünür
- **Dark mode persistence:** localStorage + sistem tercih desteği
- **Genişletilmiş dark mode:** Tüm bileşenler için kapsamlı CSS

### 🔧 Değişiklikler
- `frontend/queues.php` - Komple UI yeniden tasarımı
- `frontend/components/_header.php` - Dinamik sayfa başlıkları
- `frontend/components/_dark_mode.php` - Genişletilmiş CSS, persistence
- Main content max-width 1600px'e genişletildi
- Drag & drop sıralama eklendi
- Responsive tasarım iyileştirmeleri

---

## [2.0.0] - 2026-03-19 - Custom Script Sistemi

### ✨ Eklenenler
- **10 yeni video efekti:**
  - `ken_burns_zoom_in` - Yavaş zoom in + pan
  - `ken_burns_zoom_out` - Yavaş zoom out + pan
  - `zoom_in_fast` - Hızlı zoom in (heyecan, aciliyet)
  - `zoom_out_fast` - Hızlı zoom out (dramatik açılış)
  - `pulse` - Hafif nabız efekti (vurgular)
  - `pulse_strong` - Güçlü nabız (CTA, outro)
  - `pan_left` - Sola kaydırma (geçmiş, nostaljik)
  - `pan_right` - Sağa kaydırma (ilerleme, gelecek)
  - `static` - Hareketsiz (metin, istatistik)
  - `glitch_transition` - Glitch geçiş (teknoloji, modern)

- **7 ses profili:**
  - `neutral` - Nötr, standart haber tonu
  - `excited` - Heyecanlı, enerjik
  - `urgent` - Acil, hızlı
  - `serious` - Ciddi, otoriter
  - `calm` - Sakin, yatıştırıcı
  - `dramatic` - Dramatik, duygusal
  - `cheerful` - Neşeli, pozitif

- AI'nın sahne bazında efekt ve ses profili seçmesi
- ElevenLabs ses profili parametreleri (stability, similarity_boost, style)
- Edge-TTS rate ve pitch ayarları ile profil simülasyonu

### 🔧 Değişiklikler
- `script_gen.py` - AI prompt'a efekt ve profil seçenekleri eklendi
- `video_composer.py` - 10 efekt kodlandı
- `tts_engine.py` - Ses profili sistemi eklendi
- `pipeline.py` - Parametre aktarımı güncellendi
- `frontend/scripts.php` - Varsayılan template güncellendi

---

## [1.9.0] - 2026-03-19 - Altyazı Stili Yönetimi

### ✨ Eklenenler
- Ayarlar sayfasına "Altyazı" tabı
- Görsel altyazı stil editörü
- Canlı önizleme desteği
- 4 hazır stil şablonu (Classic, Bold, Yellow, TikTok)
- Job bazlı özel stil desteği
- Hex renk desteği (#FFFFFF formatı)
- ASS formatına otomatik dönüşüm

### 🔧 Değişiklikler
- `frontend/settings.php` - Altyazı sekmesi eklendi
- `data/config.json` - subtitleStyle objesi eklendi
- `python/video_composer.py` - Stil parametreleri entegre edildi
- `python/pipeline.py` - Config okuma güncellendi

---

## [1.8.0] - 2026-03-18 - Çoklu Platform Desteği

### ✨ Eklenenler
- TikTok Content Posting API entegrasyonu (başvuru gerekli)
- Instagram Reels desteği (Meta Graph API)
- Facebook Reels desteği (Meta Graph API)
- Platform-spesifik metadata optimizasyonu
- Çoklu platform zamanlama kuyruğu
- `start_social_scheduler.bat` - Sosyal medya scheduler
- `api/social.php` - Çoklu platform API endpoint
- `python/social/` - Platform uploader modülleri

### 📁 Yeni Dosyalar
- `SOCIAL_MEDIA_README.md` - Sosyal medya kurulum rehberi
- `python/social/base.py` - Base uploader class
- `python/social/platform_optimizer.py` - Metadata optimizer
- `python/social/tiktok/` - TikTok modülleri
- `python/social/instagram/` - Instagram modülleri
- `python/social/facebook/` - Facebook modülleri
- `python/scheduler/social_scheduler.py`
- `python/scheduler/social_queue_manager.py`

---

## [1.7.0] - 2026-03-18 - YouTube Otomatik Yükleme

### ✨ Eklenenler
- YouTube Data API v3 entegrasyonu
- OAuth 2.0 kimlik doğrulama
- Otomatik video yükleme
- Akıllı zamanlama sistemi
- AI-powered metadata optimizasyonu (Gemini)
- Yükleme kuyruğu ve geçmişi
- Retry mekanizması (3 deneme)

### 📁 Yeni Dosyalar
- `YOUTUBE_README.md` - YouTube kurulum rehberi
- `VIDEO_UPLOAD_PLAN.md` - Yükleme planı
- `QUICKSTART.md` - Hızlı başlangıç
- `python/youtube/auth.py`
- `python/youtube/uploader.py`
- `python/youtube/metadata_optimizer.py`
- `python/scheduler/scheduler.py`
- `python/scheduler/queue_manager.py`
- `python/scheduler/timing_optimizer.py`
- `api/youtube.php`
- `api/scheduler.php`
- `frontend/youtube.php`
- `frontend/scheduler.php`
- `start_scheduler.bat`
- `test_upload.bat`

---

## [1.6.0] - 2026-03-17 - Kuyruk Yönetimi

### ✨ Eklenenler
- Çoklu yayın kuyruğu sistemi
- Video sıralama ve yeniden sıralama
- Platform bazlı durum takibi
- Zamanlama tipleri (Hemen, Aralıklı, Belirli saatler)
- Kuyruk CRUD operasyonları

### 📁 Yeni Dosyalar
- `api/queues.php`
- `frontend/queues.php`

---

## [1.5.0] - 2026-03-16 - Görsel Versiyon Sistemi

### ✨ Eklenenler
- Görsel versiyon takibi
- Önceki versiyonlara geri dönüş
- Aktif versiyon seçimi
- `image_versions.json` metadata dosyası

### 📁 Yeni Dosyalar
- `api/image_versions.php`

---

## [1.4.0] - 2026-03-15 - Seçici Yeniden Üretim

### ✨ Eklenenler
- Pipeline bölümlerini seçici yeniden üretme
- Desteklenen bölümler: news, script, images, image_single, tts, subtitles, video
- Prompt güncelleme desteği
- Hook/outro/thumbnail prompt düzenleme

### 📁 Yeni Dosyalar
- `api/regenerate.php`
- `python/regenerate.py`

---

## [1.3.0] - 2026-03-14 - Script Şablon Sistemi

### ✨ Eklenenler
- Yeniden kullanılabilir script şablonları
- İçerik tipi kategorileri (genel, haber, eğlence)
- Varsayılan şablon seçimi
- Script CRUD operasyonları

### 📁 Yeni Dosyalar
- `api/scripts.php`
- `frontend/scripts.php`
- `data/scripts.json`

---

## [1.2.0] - 2026-03-13 - Çoklu Görsel Servisi

### ✨ Eklenenler
- Fal.ai FLUX.1 Schnell entegrasyonu
- Pollinations.ai desteği
- HuggingFace entegrasyonu
- Pexels stock foto desteği
- Otomatik fallback mekanizması
- Maliyet optimizasyonu

### 🔧 Değişiklikler
- `python/image_gen.py` - Çoklu servis desteği

---

## [1.1.0] - 2026-03-12 - TTS Geliştirmeleri

### ✨ Eklenenler
- ElevenLabs premium TTS
- Edge-TTS ücretsiz alternatif
- Otomatik fallback
- Async TTS desteği

### 🔧 Değişiklikler
- `python/tts_engine.py` - Dual TTS sistemi

---

## [1.0.0] - 2026-03-10 - İlk Sürüm

### ✨ Eklenenler
- Haber URL'sinden video üretimi
- Gemini AI script üretimi
- Temel görsel üretimi
- Video birleştirme (MoviePy)
- Altyazı oluşturma
- Web dashboard
- İş durumu takibi
- Pause/Resume desteği

### 📁 Temel Dosyalar
- `python/scraper.py`
- `python/script_gen.py`
- `python/image_gen.py`
- `python/tts_engine.py`
- `python/subtitle_gen.py`
- `python/video_composer.py`
- `python/pipeline.py`
- `api/jobs.php`
- `api/config.php`
- `api/check.php`
- `frontend/dashboard.php`
- `frontend/create.php`
- `frontend/project.php`
- `frontend/settings.php`

---

## Versiyon Numaralandırma

- **Major (X.0.0):** Büyük özellik eklemeleri, breaking changes
- **Minor (0.X.0):** Yeni özellikler, geriye uyumlu
- **Patch (0.0.X):** Bug fix, küçük iyileştirmeler

---

## Planlanan Özellikler

### v2.3.0
- [ ] Toplu video üretimi (birden fazla içerik aynı anda)
- [ ] A/B test desteği
- [ ] `queues.json`'daki `platform_status` alanını scheduler güncellediğinde otomatik senkronize etme

### v2.4.0
- [ ] Analytics dashboard
- [ ] Performans metrikleri (izlenme, etkileşim)
- [ ] Upload geçmişi sayfası (`social_history.json` görselleştirme)

### v3.0.0
- [ ] Çoklu dil desteği
- [ ] Özel ses klonlama
- [ ] Otomatik trend analizi

---

*Bu dosya, proje değişikliklerini takip etmek için oluşturulmuştur.*
