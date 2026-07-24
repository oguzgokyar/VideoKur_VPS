# YouTube Shorts Otomatik Paylaşım Sistemi

Bu sistem, üretilen YouTube Shorts videolarını otomatik olarak YouTube'a yükleyen, zamanlayan ve yöneten bir entegrasyon sağlar.

## 🚀 Özellikler

- ✅ **OAuth 2.0 Kimlik Doğrulama** - Güvenli YouTube hesap bağlantısı
- ✅ **Otomatik Video Yükleme** - Manuel veya zamanlanmış yükleme
- ✅ **Akıllı Zamanlama** - En yüksek trafik saatlerinde yükleme
- ✅ **AI-Powered Metadata** - Gemini ile optimize edilmiş başlık, açıklama ve etiketler
- ✅ **Yükleme Kuyruğu** - JSON tabanlı kuyruk yönetimi
- ✅ **Geçmiş Takibi** - Yükleme başarı/başarısızlıklarını izleme
- ✅ **Retry Mekanizması** - Başarısız yüklemeleri otomatik tekrar deneme

## 📋 Gereksinimler

### 1. Python Kütüphaneleri

```bash
cd python
pip install -r requirements.txt
```

Yüklenecek kütüphaneler:
- `google-auth-oauthlib` - OAuth 2.0 kimlik doğrulama
- `google-api-python-client` - YouTube Data API v3
- `google-generativeai` - Gemini AI (metadata optimizasyonu için)

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
5. Oluştur butonuna tıklayın
6. **client_secrets.json** dosyasını indirin

#### d) Credentials Dosyasını Yerleştirme
```bash
# İndirdiğiniz dosyayı şu konuma kopyalayın:
cp ~/Downloads/client_secret_*.json data/youtube_credentials/client_secrets.json
```

## 🔧 Kurulum Adımları

### 1. İlk Kimlik Doğrulama

```bash
cd python
python youtube/auth.py
```

Bu komut:
1. Tarayıcınızda OAuth sayfasını açar
2. Google hesabınızla giriş yapmanızı ister
3. İzinleri onaylamanızı bekler
4. Token'ı kaydeder: `data/youtube_credentials/default_token.pickle`
5. Kanal bilgilerini çeker: `data/youtube_channels.json`

### 2. Test Yüklemesi

```bash
# Test videosu yükle (unlisted olarak)
python youtube/uploader.py "output/job_xxx/final_video.mp4" "Test Video Title" "Test description"
```

### 3. Scheduler Servisi Başlatma

#### Windows (Task Scheduler)

1. Task Scheduler'ı açın
2. Yeni Task oluşturun:
   - **Name:** YouTube Upload Scheduler
   - **Trigger:** At startup
   - **Action:** Start a program
     - Program: `python`
     - Arguments: `C:\path\to\Video_Kur\python\scheduler\scheduler.py --interval 300`
     - Start in: `C:\path\to\Video_Kur`
3. Ayarları kaydedin

#### Linux/Mac (Cron)

```bash
# Crontab düzenle
crontab -e

# Her 5 dakikada bir çalıştır
*/5 * * * * cd /path/to/Video_Kur && python python/scheduler/scheduler.py --interval 300 >> logs/scheduler.log 2>&1
```

#### Manuel Başlatma (Test)

```bash
cd python
python scheduler/scheduler.py --interval 60
```

## 📱 Kullanım

### Web Arayüzü

#### 1. YouTube Yönetimi (`/youtube.php`)

- **Hesap Bağlama:** "YouTube Hesabı Bağla" butonuna tıklayın
- **Varsayılan Kanal:** Birden fazla kanal varsa varsayılanı seçin
- **Bağlantı Kesme:** İstenmeyen kanalları kaldırın

#### 2. Dashboard Entegrasyonu

Tamamlanmış videolar için yeni butonlar:

- **⚡ Hemen Yükle:** Videoyu anında YouTube'a yükler
- **📅 Zamanla:** Belirli tarih/saat için zamanlar
- **🔗 YouTube'da Aç:** Yüklenen videoyu açar

#### 3. Kuyruk Yönetimi Sayfası (`/queues.php`)

**Kuyruk Yönetimi:**
- Bekleyen paylaşımları görüntüle (YouTube, Instagram, TikTok, Facebook)
- Platform bazlı durumları takip et
- Zamanlama ayarları yap
- İçerik önceliği belirle

**Platform Ayarları:**
- YouTube, Instagram, TikTok, Facebook için ayrı kuyruklar
- Paylaşım aralığı ayarla
- Zamanlama stratejisi seç (Hemen/Aralıklı/Belirli Saat)
- Günlük paylaşım sayısı belirleme

### API Kullanımı

#### Manuel Upload

```bash
curl -X POST http://localhost/api/youtube.php \
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
curl -X POST http://localhost/api/queues.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "add_video",
    "queue_id": "youtube_shorts",
    "job_id": "job_xxx",
    "video_path": "output/job_xxx/final_video.mp4",
    "scheduled_time": "2026-03-18T17:30:00Z",
    "metadata": {
      "title": "Video Başlığı",
      "description": "Açıklama",
      "tags": ["shorts"]
    }
  }'
```

#### Otomatik Kuyruğa Ekleme

```bash
curl -X POST http://localhost/api/queues.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "add_video",
    "queue_id": "youtube_shorts",
    "job_id": "job_xxx",
    "platform_settings": {
      "youtube": {
        "scheduleType": "interval",
        "intervalHours": 4
      }
    }
  }'
```

## 🎯 Metadata Optimizasyonu

Sistem otomatik olarak SEO-friendly metadata oluşturur:

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

### AI-Powered Optimization (Opsiyonel)

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

## 📊 Zamanlama Stratejileri

### 1. Akıllı Strateji (Önerilen)

En yüksek trafik saatlerinde yükleme:

**Hafta İçi:**
- 🟢 18:00-20:00 (En iyi)
- 🟡 12:00-14:00 (İyi)
- 🟡 17:00, 21:00 (İyi)

**Hafta Sonu:**
- 🟢 19:00-22:00 (En iyi)
- 🟡 09:00-11:00 (İyi)

### 2. Sabit Strateji

Belirli saatlerde yükleme:
```python
preferred_hours = [17, 20]  # Her gün 17:00 ve 20:00
```

### 3. Rastgele Strateji

24 saat içinde rastgele zamanlama (algoritma testleri için).

## 🔍 Sorun Giderme

### Kimlik Doğrulama Hataları

**Problem:** `Hata 403: access_denied` - "Uygulama şu anda test edilmektedir"

**Neden:** OAuth consent screen "Testing" modunda ve email adresiniz test users listesinde yok.

**Çözüm:**
1. [Google Cloud Console](https://console.cloud.google.com/) → Projenizi seçin
2. **API'ler ve Hizmetler → OAuth consent screen**
3. **"Test users"** bölümüne gidin
4. **"+ ADD USERS"** butonuna tıklayın
5. **YouTube hesabınızın email adresini ekleyin** (OAuth yaparken kullanacağınız hesap)
6. **SAVE** butonuna tıklayın
7. Tekrar deneyin: `python youtube/auth.py`

**Alternatif:** Uygulamayı Production moduna almak
- OAuth consent screen → "PUBLISH APP" 
- ⚠️ Bu, Google verification süreci gerektirir (1-2 hafta)
- Kişisel kullanım için test users eklemek daha hızlıdır

**Problem:** `client_secrets.json` bulunamadı
```bash
# Dosyanın doğru konumda olduğundan emin olun
ls data/youtube_credentials/client_secrets.json
```

**Problem:** Token geçersiz
```bash
# Token'ı sil ve yeniden kimlik doğrula
rm data/youtube_credentials/*_token.pickle
python youtube/auth.py
```

### API Quota Hataları

**Problem:** `quotaExceeded`
- YouTube API günlük limiti: 10,000 units
- Bir upload: ~1600 units
- Günde max ~6 upload

**Çözüm:**
1. Google Cloud Console → Quota artırım talebi
2. Birden fazla Google hesabı kullan
3. Yükleme sıklığını azalt

### Upload Başarısız

**Problem:** Video çok büyük
```bash
# Video boyutunu kontrol et (max 256GB)
python utils/video_validator.py output/job_xxx/final_video.mp4
```

**Problem:** Video süresi > 60s
```bash
# Shorts için max 60 saniye
# Pipeline ayarlarını kontrol edin
```

## 📁 Dosya Yapısı

```
Video_Kur/
├── python/
│   ├── youtube/
│   │   ├── auth.py              # OAuth 2.0 kimlik doğrulama
│   │   ├── uploader.py          # Video yükleme
│   │   └── metadata_optimizer.py # SEO optimizasyonu
│   ├── scheduler/
│   │   ├── scheduler.py         # Arka plan servisi
│   │   ├── queue_manager.py     # Kuyruk yönetimi
│   │   └── timing_optimizer.py  # Optimal zaman hesaplama
│   └── utils/
│       └── video_validator.py   # Video kontrolü
├── data/
│   ├── youtube_credentials/
│   │   ├── client_secrets.json  # OAuth credentials (SİZ EKLEYIN)
│   │   └── *_token.pickle       # Kayıtlı token'lar
│   ├── youtube_channels.json    # Bağlı kanallar
│   └── queues.json              # Birleşik kuyruk sistemi
├── api/
│   ├── youtube.php              # YouTube API endpoint
│   └── queues.php               # Kuyruk API endpoint
└── frontend/
    ├── youtube.php              # YouTube yönetim sayfası
    └── queues.php               # Kuyruk yönetimi sayfası
```

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
   - Desteklenen formatlar: MP4, MOV, AVI, etc.

### Güvenlik

- `client_secrets.json` dosyasını asla paylaşmayın
- Token dosyalarını `.gitignore` ekleyin
- API key'leri environment variable'larda tutun

## 📞 Destek

Sorun yaşarsanız:
1. Logları kontrol edin: `logs/scheduler.log`
2. Python hatalarını inceleyin
3. API quota'yı kontrol edin

## 🎉 Tamamlandı!

Sistem artık hazır! Dashboard'dan video oluşturun ve otomatik olarak YouTube'a yüklensin.
