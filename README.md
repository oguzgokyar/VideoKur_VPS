# 🎬 Video_Kur

**AI-Powered YouTube Shorts Automation Platform**

Haber URL'lerinden otomatik YouTube Shorts videoları üreten ve çoklu sosyal medya platformlarına yayınlayan tam entegre otomasyon sistemi.

[![GitHub](https://img.shields.io/badge/GitHub-Video__Kur-blue?logo=github)](https://github.com/oguzgokyar/Video_Kur)
[![Python](https://img.shields.io/badge/Python-3.9+-green?logo=python)](https://python.org)
[![PHP](https://img.shields.io/badge/PHP-8.0+-purple?logo=php)](https://php.net)
[![License](https://img.shields.io/badge/License-MIT-yellow)](LICENSE)

---

## ✨ Özellikler

### 🤖 AI Destekli İçerik Üretimi
- **Script Üretimi** - Gemini / Pollinations AI
- **Görsel Üretimi** - Fal.ai FLUX / Pollinations / HuggingFace / Pexels
- **Ses Sentezi** - ElevenLabs (Premium) / Edge-TTS (Ücretsiz)

### 🎬 Gelişmiş Video Düzenleme
- **10 Video Efekti** - Ken Burns, Zoom, Pan, Pulse, Glitch
- **7 Ses Profili** - Neutral, Excited, Urgent, Serious, Calm, Dramatic, Cheerful
- **6 Altyazı Stili** - Classic, Bold, Yellow, TikTok, Minimal, News

### 📱 Çoklu Platform Desteği
- ✅ YouTube Shorts (OAuth 2.0)
- 🔧 TikTok (Content Posting API)
- 🔧 Instagram Reels (Meta Graph API)
- 🔧 Facebook Reels (Meta Graph API)

### ⏰ Akıllı Zamanlama
- Otomatik optimal saat hesaplama
- Kuyruk tabanlı yayın yönetimi
- Retry mekanizması

---

## 🖼️ Ekran Görüntüleri

<details>
<summary>📺 Dashboard</summary>

Web tabanlı dashboard ile tüm videoları yönetin, durumları takip edin.
</details>

<details>
<summary>🎨 Video Oluşturma</summary>

URL girin, format seçin, altyazı stilini özelleştirin - gerisi otomatik!
</details>

---

## 🚀 Hızlı Başlangıç

### Gereksinimler

- Python 3.9+
- PHP 8.0+
- FFmpeg
- Composer (opsiyonel)

### Kurulum

```bash
# Repo'yu klonlayın
git clone https://github.com/oguzgokyar/Video_Kur.git
cd Video_Kur

# Python bağımlılıklarını yükleyin
cd python
pip install -r requirements.txt
cd ..
```

### Yapılandırma

`data/config.json` dosyasını düzenleyin:

```json
{
  "geminiKey": "YOUR_GEMINI_API_KEY",
  "elevenKey": "YOUR_ELEVENLABS_API_KEY",
  "falKey": "YOUR_FAL_API_KEY",
  "pexelsKey": "YOUR_PEXELS_API_KEY"
}
```

### Çalıştırma

```bash
# Tek komutla başlatın (önerilen)
start_all.bat

# veya manuel:
php -S localhost:8000 router.php
```

---

## 📁 Proje Yapısı

```
Video_Kur/
├── api/                    # REST API endpoints
├── python/                 # Video üretim pipeline
│   ├── scraper.py          # Haber çekme
│   ├── script_gen.py       # AI script üretimi
│   ├── image_gen.py        # AI görsel üretimi
│   ├── tts_engine.py       # Ses sentezi
│   ├── video_composer.py   # Video birleştirme
│   ├── pipeline.py         # Ana orkestratör
│   ├── scheduler/          # Zamanlama modülleri
│   ├── social/             # Sosyal medya uploaderları
│   └── youtube/            # YouTube API modülleri
├── frontend/               # Web arayüzü
├── data/                   # Yapılandırma ve veriler
└── output/                 # Üretilen videolar
```

---

## 🔄 Video Üretim Pipeline

```
URL Girişi
    ↓
[1] 📰 Haber Çekme (scraper.py)
    ↓
[2] 📝 AI Script Üretimi (script_gen.py)
    ↓
[3] 🖼️ AI Görsel Üretimi (image_gen.py)
    ↓
[4] 🎙️ Ses Sentezi (tts_engine.py)
    ↓
[5] 💬 Altyazı Oluşturma (subtitle_gen.py)
    ↓
[6] 🎬 Video Birleştirme (video_composer.py)
    ↓
📺 YouTube / TikTok / Instagram / Facebook
```

---

## 🎬 Video Efektleri

| Efekt | Açıklama | Kullanım |
|-------|----------|----------|
| `ken_burns_zoom_in` | Yavaş zoom in + pan | Profesyonel |
| `ken_burns_zoom_out` | Yavaş zoom out | Açılış |
| `zoom_in_fast` | Hızlı zoom in | Heyecan |
| `zoom_out_fast` | Hızlı zoom out | Dramatik |
| `pulse` | Hafif nabız | Vurgular |
| `pulse_strong` | Güçlü nabız | CTA |
| `pan_left` | Sola kaydırma | Geçmiş |
| `pan_right` | Sağa kaydırma | Gelecek |
| `static` | Hareketsiz | İstatistik |
| `glitch_transition` | Glitch efekti | Teknoloji |

---

## 🎙️ Ses Profilleri

| Profil | Ton | Kullanım |
|--------|-----|----------|
| `neutral` | Nötr | Genel haberler |
| `excited` | Heyecanlı | Teknoloji |
| `urgent` | Acil | Son dakika |
| `serious` | Ciddi | Resmi |
| `calm` | Sakin | Analiz |
| `dramatic` | Dramatik | Hikaye |
| `cheerful` | Neşeli | Pozitif |

---

## 📊 API Limitleri

| Platform | Günlük Limit |
|----------|-------------|
| YouTube | ~6 upload |
| TikTok | ~50 upload |
| Instagram | ~25 upload |
| Fal.ai | ~$0.002/görsel |

---

## 📚 Dokümantasyon

### 🚀 Başlangıç
- [📋 Hızlı Başlangıç](QUICKSTART.md) - 5 dakikada kurulum
- [🌐 Web Kullanım](docs/user-guides/web-kullanim.md) - Dashboard kullanımı

### 👤 Kullanıcı Kılavuzları
- [📖 Nasıl Kullanılır](docs/user-guides/kullanim.md) - Temel kullanım
- [🎬 Custom Script](docs/user-guides/custom-scripts.md) - Özel script oluşturma
- [💬 Altyazı Stilleri](docs/user-guides/subtitle-styling.md) - Altyazı özelleştirme

### ⚙️ Özellikler ve Entegrasyonlar
- [📺 YouTube Entegrasyonu](docs/features/youtube-integration.md) - OAuth, zamanlama, çoklu proje
- [📱 Sosyal Medya](docs/features/social-media.md) - TikTok, Instagram, Facebook
- [🔍 İçerik Keşfi](docs/features/content-discovery.md) - Trending içerik bulma

### 📊 Proje Bilgileri
- [📈 Proje Durumu](PROJECT_STATUS.md) - Mevcut durum ve yol haritası
- [📜 Değişiklik Geçmişi](CHANGELOG.md) - Sürüm notları
- [📁 Dokümantasyon İndeksi](docs/setup/DOCUMENTATION_INDEX.md) - Aktif/arşiv sınıflandırması

---

## 🛠️ Teknoloji Stack

### Backend
- **Python 3.9+** - Video üretim pipeline
- **PHP 8.0+** - REST API
- **MoviePy** - Video düzenleme
- **FFmpeg** - Video kodlama

### Frontend
- **Tailwind CSS** - UI framework
- **Alpine.js** - Reaktif framework

### AI Servisleri
- **Google Gemini** - Script üretimi
- **Fal.ai / Pollinations** - Görsel üretimi
- **ElevenLabs / Edge-TTS** - Ses sentezi

---

## 🤝 Katkıda Bulunma

1. Fork edin
2. Feature branch oluşturun (`git checkout -b feature/amazing`)
3. Commit edin (`git commit -m 'Add amazing feature'`)
4. Push edin (`git push origin feature/amazing`)
5. Pull Request açın

---

## 📄 Lisans

Bu proje MIT lisansı altında lisanslanmıştır. Detaylar için [LICENSE](LICENSE) dosyasına bakın.

---

## 👨‍💻 Geliştirici

**Oğuz Gökyar**

[![GitHub](https://img.shields.io/badge/GitHub-oguzgokyar-black?logo=github)](https://github.com/oguzgokyar)

---

## ⭐ Destek

Bu projeyi beğendiyseniz ⭐ vermeyi unutmayın!

---

<p align="center">
  <b>🎬 Video_Kur v2.0</b><br>
  AI-Powered YouTube Shorts Automation
</p>
