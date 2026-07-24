# 📊 Video_Kur - Proje Durumu ve İlerleme Raporu

> **Son Güncelleme:** 2026-03-21  
> **Versiyon:** 2.0  
> **Durum:** ✅ Aktif - Production Ready

---

## 🎯 Proje Özeti

**Video_Kur**, haber URL'lerinden otomatik YouTube Shorts videoları üreten ve çoklu sosyal medya platformlarına yayınlayan tam entegre bir otomasyon sistemidir.

### Temel Özellikler
- 🤖 AI destekli script üretimi (Gemini / Pollinations)
- 🖼️ AI görsel üretimi (Fal.ai / Pollinations / HuggingFace / Pexels)
- 🎙️ Text-to-Speech (ElevenLabs / Edge-TTS)
- 🎬 10 farklı video efekti (Ken Burns, Zoom, Pan, Pulse, Glitch)
- 💬 7 farklı ses profili (Neutral, Excited, Urgent, Serious, Calm, Dramatic, Cheerful)
- 📺 YouTube otomatik yükleme (OAuth 2.0)
- 📱 Çoklu platform desteği (YouTube, TikTok, Instagram, Facebook)
- ⏰ Akıllı zamanlama sistemi
- 🎨 Özelleştirilebilir altyazı stilleri

---

## 📁 Proje Yapısı

```
Video_Kur/
├── api/                    # 10 PHP REST API endpoint
│   ├── accounts.php        # Sosyal medya hesap yönetimi
│   ├── check.php           # API sağlık kontrolü
│   ├── config.php          # Yapılandırma yönetimi
│   ├── image_versions.php  # Görsel versiyon takibi
│   ├── jobs.php            # Video iş yaşam döngüsü
│   ├── queues.php          # Çoklu platform kuyrukları
│   ├── regenerate.php      # Seçici yeniden üretim
│   ├── scripts.php         # Script şablon yönetimi
│   ├── social.php          # Çoklu platform operasyonları
│   └── youtube.php         # YouTube entegrasyonu
│
├── python/                 # 31 Python script
│   ├── scraper.py          # Haber çekme
│   ├── script_gen.py       # AI script üretimi
│   ├── image_gen.py        # AI görsel üretimi
│   ├── tts_engine.py       # Ses sentezi
│   ├── subtitle_gen.py     # Altyazı oluşturma
│   ├── video_composer.py   # Video birleştirme
│   ├── pipeline.py         # Ana orkestratör
│   ├── regenerate.py       # Seçici yeniden üretim
│   ├── scheduler/          # Zamanlama modülleri
│   ├── social/             # Sosyal medya uploaderları
│   ├── youtube/            # YouTube API modülleri
│   └── utils/              # Yardımcı araçlar
│
├── frontend/               # 19 PHP/HTML dosyası
│   ├── dashboard.php       # Ana video listesi
│   ├── create.php          # Video oluşturma formu
│   ├── project.php         # Proje detay görünümü
│   ├── settings.php        # Ayarlar (6 sekme)
│   ├── queues.php          # Kuyruk yönetimi
│   ├── accounts.php        # Hesap yönetimi
│   ├── scripts.php         # Script yönetimi
│   └── components/         # Paylaşılan bileşenler
│
├── data/                   # Yapılandırma ve veriler
│   ├── config.json         # API anahtarları ve ayarlar
│   ├── jobs/               # İş kayıtları (14 tamamlanmış)
│   ├── queues.json         # Yayın kuyrukları (birleşik sistem)
│   ├── scripts.json        # Script şablonları
│   ├── social_credentials/ # Platform kimlik bilgileri
│   └── youtube_credentials/# YouTube OAuth
│
└── output/                 # Üretilen videolar
```

---

## 🔧 Teknoloji Stack

### Backend
| Teknoloji | Kullanım |
|-----------|----------|
| PHP 8.x | REST API endpoints |
| Python 3.x | Video üretim pipeline |
| MoviePy | Video birleştirme |
| FFmpeg | Video kodlama |

### Frontend
| Teknoloji | Kullanım |
|-----------|----------|
| Tailwind CSS | UI framework |
| Alpine.js 3.13 | Reaktif framework |
| PHP | Server-side rendering |

### AI Servisleri
| Servis | Kullanım | Durum |
|--------|----------|-------|
| Google Gemini | Script üretimi | ✅ Aktif |
| Pollinations | Script/Görsel (ücretsiz) | ✅ Aktif |
| Fal.ai | FLUX görsel üretimi | ✅ Aktif |
| HuggingFace | Yedek görsel üretimi | ✅ Aktif |
| Pexels | Stock görseller | ✅ Aktif |
| ElevenLabs | Premium TTS | ✅ Aktif |
| Edge-TTS | Ücretsiz TTS | ✅ Aktif |

### Sosyal Medya Entegrasyonları
| Platform | API | Durum |
|----------|-----|-------|
| YouTube | Data API v3 | ✅ Hazır |
| TikTok | Content Posting API | 🔧 Başvuru gerekiyor |
| Instagram | Meta Graph API | 🔧 Kurulum gerekiyor |
| Facebook | Meta Graph API | 🔧 Kurulum gerekiyor |

---

## 📈 Mevcut Durum

### İstatistikler
- **Tamamlanan İşler:** 14 video
- **Aktif Kuyruklar:** 1 ("Youtube Testr")
- **Bağlı Hesaplar:**
  - YouTube: 1 kanal (Video Kur Kanalım)
  - Instagram: 1 hesap (dijital.dahi1)
  - TikTok: Yapılandırılmadı

### Bekleyen İşlemler
- 1 video çoklu platform yüklemesi bekliyor (YouTube/TikTok/Instagram/Facebook)

---

## 🔄 Video Üretim Pipeline

```
URL Girişi
    ↓
[1] scraper.py → Haber çekme (başlık, metin, görseller)
    ↓
[2] script_gen.py → AI script üretimi (Gemini/Pollinations)
    → Hook + Sahneler + Outro + Efektler + Ses profilleri
    ↓
[3] image_gen.py → AI görsel üretimi (Fal/Pollinations/HF/Pexels)
    → hook.png, scene_1-N.png, outro.png, thumbnail.png
    ↓
[4] tts_engine.py → Ses üretimi (ElevenLabs/Edge-TTS)
    → Her sahne için ses segmenti
    ↓
[5] subtitle_gen.py → SRT altyazı dosyası
    ↓
[6] video_composer.py → Video birleştirme
    → Efektler uygula, klipleri birleştir, altyazı yak
    ↓
Çıktı: output/{job_id}/final_video.mp4
    ↓
[UPLOAD] scheduler.py → YouTube/TikTok/Instagram/Facebook
```

---

## 🎬 Video Efektleri (10 tip)

| Efekt | Açıklama | Kullanım |
|-------|----------|----------|
| `ken_burns_zoom_in` | Yavaş zoom in + pan | Genel, profesyonel |
| `ken_burns_zoom_out` | Yavaş zoom out + pan | Açılış, büyük resim |
| `zoom_in_fast` | Hızlı zoom in | Heyecan, aciliyet |
| `zoom_out_fast` | Hızlı zoom out | Dramatik |
| `pulse` | Hafif nabız (2 döngü) | Vurgular |
| `pulse_strong` | Güçlü nabız (3 döngü) | CTA, outro |
| `pan_left` | Sola kaydırma | Geçmiş, nostaljik |
| `pan_right` | Sağa kaydırma | İlerleme, gelecek |
| `static` | Hareketsiz | Metin, istatistik |
| `glitch_transition` | Glitch efekti | Teknoloji, modern |

---

## 🎙️ Ses Profilleri (7 tip)

| Profil | Stability | Kullanım |
|--------|-----------|----------|
| `neutral` | 0.5 | Genel haberler |
| `excited` | 0.3 | Teknoloji lansmanı |
| `urgent` | 0.4 | Son dakika |
| `serious` | 0.7 | Resmi açıklamalar |
| `calm` | 0.8 | Analiz, istatistik |
| `dramatic` | 0.5 | Duygusal hikayeler |
| `cheerful` | 0.4 | Başarı hikayeleri |

---

## 📋 Altyazı Stilleri (6 preset)

1. **Classic** - Sade, okunabilir
2. **Bold Bottom** - Kalın kenarlık ve gölge
3. **Yellow Bold** - Sarı metin, siyah kenarlık
4. **White Box** - Opak arka plan
5. **TikTok** - Kırmızı kenarlık, büyük yazı
6. **Minimal** - Kenarlıksız, temiz

---

## 📊 API Limitleri

| Platform | Günlük Limit |
|----------|-------------|
| YouTube | ~6 upload (10,000 units) |
| TikTok | ~50 upload |
| Instagram | ~25 upload |
| Facebook | ~25 upload |
| Fal.ai | ~$0.002/görsel (768x768) |

---

## 🚀 Hızlı Başlangıç

### 1. Test Yüklemesi
```bash
test_upload.bat
```

### 2. Scheduler Başlatma
```bash
start_scheduler.bat
```

### 3. Web Arayüzü
```
http://localhost/dashboard.php
```

---

## 📝 Yapılacaklar (TODO)

### Kısa Vadeli
- [ ] TikTok Content Posting API başvurusu
- [ ] Instagram Business hesap kurulumu
- [ ] Facebook Page bağlantısı

### Orta Vadeli
- [ ] Toplu video üretimi
- [ ] A/B test desteği
- [ ] Analytics dashboard

### Uzun Vadeli
- [ ] Çoklu dil desteği
- [ ] Özel ses klonlama
- [ ] Otomatik trend analizi

---

## 📞 Dosya Konumları

| Dosya | Konum |
|-------|-------|
| API Keys | `data/config.json` |
| İş Kayıtları | `data/jobs/*.json` |
| YouTube Credentials | `data/youtube_credentials/` |
| Social Credentials | `data/social_credentials/` |
| Üretilen Videolar | `output/{job_id}/` |
| Loglar | `logs/` |

---

*Bu dosya, proje durumunu oturumlararası hatırlamak için oluşturulmuştur.*
