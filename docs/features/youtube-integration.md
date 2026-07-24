# YouTube Shorts Entegrasyonu - Kapsamlı Kılavuz

## 📖 İçindekiler
- [Genel Bakış](#genel-bakış)
- [Kurulum](#kurulum)
- [Kimlik Doğrulama](#kimlik-doğrulama)
- [Video Yükleme](#video-yükleme)
- [Zamanlama Stratejileri](#zamanlama-stratejileri)
- [Çoklu Proje Yönetimi](#çoklu-proje-yönetimi)
- [Token Yönetimi](#token-yönetimi)
- [Sorun Giderme](#sorun-giderme)

---

## 🎯 Genel Bakış

Video_Kur, üretilen YouTube Shorts videolarını otomatik olarak YouTube'a yükleyen, zamanlayan ve yöneten bir entegrasyon sağlar.

### Özellikler
- ✅ **OAuth 2.0 Kimlik Doğrulama** - Güvenli YouTube hesap bağlantısı
- ✅ **Otomatik Video Yükleme** - Manuel veya zamanlanmış yükleme
- ✅ **Akıllı Zamanlama** - En yüksek trafik saatlerinde yükleme
- ✅ **AI-Powered Metadata** - Gemini ile optimize edilmiş başlık, açıklama ve etiketler
- ✅ **Planlanmış Yayın (publishAt)** - YouTube'un native zamanlama özelliği
- ✅ **Yükleme Kuyruğu** - JSON tabanlı kuyruk yönetimi
- ✅ **Çoklu Proje Desteği** - API kotasını artırmak için birden fazla Google Cloud projesi
- ✅ **Geçmiş Takibi** - Yükleme başarı/başarısızlıklarını izleme
- ✅ **Retry Mekanizması** - Başarısız yüklemeleri otomatik tekrar deneme

---

## 🔧 Kurulum

### 1. Python Kütüphaneleri

```bash
cd python
pip install -r requirements.txt
```

Gerekli kütüphaneler:
- `google-auth-oauthlib` - OAuth 2.0 kimlik doğrulama
- `google-api-python-client` - YouTube Data API v3
- `google-generativeai` - Gemini AI (metadata optimizasyonu)

### 2. Google Cloud Console Kurulumu

#### a) Proje Oluşturma
1. [Google Cloud Console](https://console.cloud.google.com/) açın
2. Yeni proje oluşturun (örn: "Video-Kur-YouTube")

#### b) YouTube Data API v3 Aktifleştirme
1. API'ler ve Hizmetler → Kütüphane
2. "YouTube Data API v3" arayın
3. "Etkinleştir" butonuna tıklayın

#### c) OAuth 2.0 Credentials Oluşturma
1. API'ler ve Hizmetler → Kimlik Bilgileri
2. "Kimlik Bilgileri Oluştur" → "OAuth istemci kimliği"
3. Uygulama türü: **Masaüstü uygulaması**
4. İsim: "Video-Kur Desktop Client"
5. **client_secrets.json** dosyasını indirin

#### d) Credentials Dosyasını Yerleştirme
```bash
# İndirdiğiniz dosyayı şu konuma kopyalayın:
cp ~/Downloads/client_secret_*.json data/youtube_credentials/client_secrets.json
```

---

## 🔐 Kimlik Doğrulama

### İlk Kimlik Doğrulama

```bash
cd python
python youtube/auth.py
```

Bu komut:
1. Tarayıcınızda OAuth sayfasını açar
2. Google hesabınızla giriş yapmanızı ister
3. İzinleri onaylamanızı bekler
4. Token'ı kaydeder: `data/youtube_credentials/<project>_<channel>_<api>_token.json`
5. Kanal bilgilerini çeker: `data/youtube_channels.json`

### Test Users Ekleme

**Problem:** `Hata 403: access_denied` - "Uygulama şu anda test edilmektedir"

**Çözüm:**
1. [Google Cloud Console](https://console.cloud.google.com/) → Projenizi seçin
2. **API'ler ve Hizmetler → OAuth consent screen**
3. **"Test users"** bölümüne gidin
4. **"+ ADD USERS"** butonuna tıklayın
5. **YouTube hesabınızın email adresini ekleyin**
6. Tekrar deneyin: `python youtube/auth.py`

---

## 📤 Video Yükleme

### Test Yüklemesi

```bash
# Test videosu yükle (unlisted olarak)
python youtube/uploader.py "output/job_xxx/final_video.mp4" "Test Video Title" "Test description"
```

### Web Arayüzü Kullanımı

#### Dashboard Entegrasyonu
Tamamlanmış videolar için:
- **⚡ Hemen Yükle:** Videoyu anında YouTube'a yükler
- **📅 Zamanla:** Belirli tarih/saat için zamanlar
- **🔗 YouTube'da Aç:** Yüklenen videoyu açar

### API Kullanımı

#### Manuel Upload
```bash
curl -X POST http://localhost:8000/api/youtube_upload.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "upload",
    "job_id": "job_xxx",
    "video_path": "output/job_xxx/final_video.mp4",
    "channel_id": "UCxxxxxxxxxx",
    "metadata": {
      "title": "Video Başlığı #Shorts",
      "description": "Açıklama...\n\n#Shorts #Haber",
      "tags": ["shorts", "haber", "teknoloji"],
      "category_id": "28",
      "privacy_status": "public"
    }
  }'
```

#### Kuyruğa Ekleme
```bash
curl -X POST http://localhost:8000/api/queues.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "add_video",
    "queue_id": "youtube_shorts",
    "job_id": "job_xxx",
    "scheduled_time": "2026-03-28T17:30:00Z"
  }'
```

---

## 📅 Zamanlama Stratejileri

### 1. Planlanmış Yayın (publishAt)

YouTube'un native zamanlama özelliğini kullanır. Video yüklenir ancak belirlenen zamana kadar private kalır.

**Kullanım:**
```python
upload_video(
    video_path="output/job_xxx/final_video.mp4",
    title="Video Başlığı",
    description="Açıklama",
    publish_at="2026-03-28T20:00:00+00:00"  # ISO 8601 format
)
```

**Özellikler:**
- publishAt kullanıldığında otomatik olarak `privacyStatus: 'private'` ayarlanır
- Video planlanmış zamanda otomatik olarak `public` olur
- Scheduler 5 dakikadan fazla gelecekteki zamanları publishAt ile işler

### 2. Aralıklı Zamanlama

Videolar arası bekleme süresini dakika cinsinden ayarlar.

**Web Arayüzü Ayarları:**
- **İlk Paylaşım Saati (start_time):** Kuyruktaki ilk videonun ne zaman paylaşılacağı
- **Paylaşım Aralığı (interval_minutes):** Videolar arası bekleme süresi (dakika)
- **Günlük Paylaşım Limiti (daily_limit):** Günde maksimum kaç video

**Örnek Senaryolar:**

**Sabah Başla, Her 2 Saatte 1, Max 4 Video/Gün:**
```json
{
  "schedule": {
    "timezone": "Europe/Istanbul"
  },
  "platform_settings": {
    "youtube": {
      "scheduleType": "interval",
      "startTime": "09:00",
      "intervalMinutes": 120,
      "dailyLimit": 4
    }
  }
}
```
Sonuç: 09:00, 11:00, 13:00, 15:00

**Hemen Başla, Her 90 Dakikada 1, Limitsiz:**
```json
{
  "platform_settings": {
    "youtube": {
      "scheduleType": "interval",
      "intervalMinutes": 90,
      "dailyLimit": 0
    }
  }
}
```

### 3. Akıllı Strateji

En yüksek trafik saatlerinde yükleme:

**Hafta İçi:**
- 🟢 18:00-20:00 (En iyi)
- 🟡 12:00-14:00 (İyi)
- 🟡 17:00, 21:00 (İyi)

**Hafta Sonu:**
- 🟢 19:00-22:00 (En iyi)
- 🟡 09:00-11:00 (İyi)

### YouTube Shorts İçin Önerilen Ayarlar

#### Yeni Kanal (0-1000 Abone)
```
İlk Paylaşım: 09:00
Aralık: 180 dakika (3 saat)
Günlük Limit: 3-4 video
```
**Neden?** YouTube algoritması yeni kanalları test eder. Fazla paylaşım spam olarak algılanabilir.

#### Orta Seviye Kanal (1K-10K Abone)
```
İlk Paylaşım: 08:00
Aralık: 120 dakika (2 saat)
Günlük Limit: 4-6 video
```

#### Büyük Kanal (10K+ Abone)
```
İlk Paylaşım: 07:00
Aralık: 90 dakika (1.5 saat)
Günlük Limit: 6-8 video
```

---

## 🔄 Çoklu Proje Yönetimi

Her Google Cloud projesi günde 10,000 birim kota sağlar (~6 video/gün). Birden fazla proje kullanarak kotayı artırabilirsiniz.

| Proje Sayısı | Günlük Kota | Tahmini Video |
|--------------|-------------|---------------|
| 1 | 10,000 | ~6 |
| 2 | 20,000 | ~12 |
| 3 | 30,000 | ~18 |
| 5 | 50,000 | ~30 |

### Yeni Proje Ekleme

#### 1. Google Cloud Console'da Yeni Proje Oluştur
1. [Google Cloud Console](https://console.cloud.google.com/)
2. Üst menü → Yeni Proje → Proje adı: `VideoKur-2`

#### 2. YouTube Data API v3 Etkinleştir
1. APIs & Services → Library
2. "YouTube Data API v3" → Enable

#### 3. OAuth Credentials Oluştur
1. APIs & Services → Credentials
2. + Create Credentials → OAuth client ID
3. Application type: Desktop app
4. Download JSON → `data/youtube_credentials/client_secrets_2.json`

#### 4. Projeyi Sisteme Ekle

**Komut Satırı:**
```bash
cd python
python -m youtube.project_manager add "VideoKur Proje 2" "client_secrets_2.json" 10000
```

**Manuel (youtube_projects.json):**
```json
{
  "id": "project_2",
  "name": "VideoKur Proje 2",
  "client_secrets_file": "client_secrets_2.json",
  "is_active": true,
  "daily_quota": 10000
}
```

### Rotasyon Stratejileri

| Strateji | Açıklama |
|----------|----------|
| `round_robin` | Projeleri sırayla kullan (varsayılan) |
| `least_used` | En az kullanılan projeyi seç |
| `failover` | Önce varsayılanı kullan, kota dolunca diğerine geç |

### Durum Kontrolü

```bash
python -m youtube.project_manager status
```

Çıktı:
```
============================================================
📊 YOUTUBE PROJELERİ DURUMU
============================================================
Toplam proje: 2 (2 aktif)
Strateji: round_robin
Toplam kota: 0/20000 (20000 kalan)
Tahmini kalan: ~12 video
```

---

## 🔑 Token Yönetimi

### Token Dosyaları
```
data/youtube_credentials/
├── <project>_<channel>_<api>_token.json  # OAuth token'ları
└── client_secrets_<project>.json         # OAuth client credentials
```

### Token Hataları ve Çözümleri

#### "invalid_grant: Token has been expired or revoked"

**Hızlı Çözüm:**
```bash
# Windows
reset_youtube_credentials.bat

# Linux/Mac
python reset_youtube_credentials.py
```

Bu komut:
- ✅ Eski token'ları siler
- ✅ Backup oluşturur (`data/youtube_credentials.backup/`)
- ✅ Yeni auth talebi tetikler

**Manuel Çözüm:**
```bash
# Token dosyalarını sil
rm data/youtube_credentials/*_token.json
# Yeniden kimlik doğrula
python youtube/auth.py
```

### Otomatik Token Yenileme

Sistem otomatik olarak token hatalarını tespit eder ve yeniden kimlik doğrulama sürecini başlatır:

1. Token refresh hatası tespit edilir
2. Eski token dosyası silinir
3. Tarayıcı açılır → Google OAuth
4. Yeni token kaydedilir
5. Scheduler normal şekilde devam eder

---

## 🎯 Metadata Optimizasyonu

### Başlık Optimizasyonu
- Max 100 karakter (önerilen: 40-50)
- Emoji ekleme
- Soru/ünlem işareti
- İlk kelimelere odaklanma

### Açıklama Optimizasyonu
- İlk 100 karakter kritik
- Hashtag'ler (#Shorts zorunlu)
- Call-to-Action (CTA)
- İlgili linkler

### Tag Stratejisi
- 5-12 adet hedefli tag
- Broad + Specific karışımı
- Türkçe + İngilizce

### AI-Powered Optimization

```python
from youtube.metadata_optimizer import MetadataOptimizer

optimizer = MetadataOptimizer(gemini_key="YOUR_KEY")

result = optimizer.optimize_metadata(
    original_title="Yapay zeka iş yükünü artırdı",
    script_text="Yapay zeka iş yükünüzü azaltacağını mı sanıyordunuz?...",
    use_ai=True
)

# result = {
#   'title': '🤖 Yapay Zeka İş Yükünü Azaltmıyor mu? #Shorts',
#   'description': '...',
#   'tags': ['#Shorts', 'yapay zeka', 'AI', ...]
# }
```

---

## 🐛 Sorun Giderme

### API Quota Hataları

**Problem:** `quotaExceeded`
- YouTube API günlük limiti: 10,000 units
- Bir upload: ~1600 units
- Günde max ~6 upload

**Çözüm:**
1. Google Cloud Console → Quota artırım talebi
2. Birden fazla Google Cloud projesi kullan
3. Yükleme sıklığını azalt

### Upload Başarısız

**Problem:** Video çok büyük
```bash
# Video boyutunu kontrol et (max 256GB)
python utils/video_validator.py output/job_xxx/final_video.mp4
```

**Problem:** Video süresi > 60s
- Shorts için max 60 saniye
- Pipeline ayarlarını kontrol edin

### Videolar Zamanında Paylaşılmıyor

**Çözüm:** Social scheduler çalışıyor mu kontrol edin:
```bash
# Windows
start_social_scheduler.bat

# Durum kontrolü
python python/scheduler/social_scheduler.py
```

---

## 📁 Dosya Yapısı

```
Video_Kur/
├── python/
│   ├── youtube/
│   │   ├── auth.py              # OAuth 2.0 kimlik doğrulama
│   │   ├── uploader.py          # Video yükleme
│   │   ├── metadata_optimizer.py # SEO optimizasyonu
│   │   └── project_manager.py   # Multi-project yönetimi
│   ├── scheduler/
│   │   ├── production_scheduler.py  # Video üretim scheduler
│   │   ├── social_scheduler.py      # Sosyal medya scheduler
│   │   └── timing_optimizer.py      # Optimal zaman hesaplama
│   └── utils/
│       └── video_validator.py   # Video kontrolü
├── data/
│   ├── youtube_credentials/
│   │   ├── client_secrets.json      # OAuth credentials
│   │   ├── client_secrets_2.json    # İkinci proje
│   │   └── *_token.json             # Token'lar
│   ├── youtube_channels.json    # Bağlı kanallar
│   ├── youtube_projects.json    # Multi-project config
│   └── queues.json              # Publish kuyrukları
├── api/
│   ├── youtube.php              # YouTube API endpoint
│   └── queues.php               # Queue API endpoint
└── frontend/
    ├── youtube.php              # YouTube yönetim sayfası
    └── queues.php               # Queue yönetimi sayfası
```

---

## ⚠️ Önemli Notlar

### YouTube API Kısıtlamaları

1. **Yeni Projeler:** İlk yüklemeler "private" olarak kalır
   - **Çözüm:** API audit başvurusu yapın (4-6 hafta)
   - **Alternatif:** "unlisted" yükle, sonra manuel "public" yap

2. **Günlük Quota:** 10,000 units (≈6 upload/gün)
   - Quota artırımı için başvuru yapılabilir

3. **Video Limitleri:**
   - Max boyut: 256GB
   - Max süre: 12 saat (Shorts için 60s)
   - Desteklenen formatlar: MP4, MOV, AVI

### Güvenlik

- `client_secrets.json` dosyasını asla paylaşmayın
- Token dosyalarını `.gitignore`'a ekleyin
- API key'leri environment variable'larda tutun

---

## 📞 Destek

Sorun yaşarsanız:
1. Logları kontrol edin: `logs/scheduler.log`
2. Python hatalarını inceleyin
3. API quota'yı kontrol edin
4. Token durumunu kontrol edin

---

## 💡 İpuçları

- **Tutarlı olun:** Her gün aynı saatlerde paylaşın
- **Prime time kullanın:** 17:00-20:00 arası en yüksek izlenme
- **Günlük limiti aşmayın:** 8-10 video/gün idealdir
- **İçerik kalitesine odaklanın:** Miktar değil, kalite!
- **Analytics takip edin:** Hangi saatler daha iyi çalışıyor?
- **publishAt kullanın:** 5+ dakika sonrası için native YouTube zamanlama kullanın
