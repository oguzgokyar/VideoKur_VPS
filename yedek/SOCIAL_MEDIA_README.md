# 📱 Çapraz Platform Sosyal Medya Paylaşımı

Bu dokümantasyon, Video_Kur uygulamasının çoklu sosyal medya platform desteğini açıklar.

## 🎯 Desteklenen Platformlar

| Platform | Durum | API |
|----------|-------|-----|
| YouTube | ✅ Hazır | YouTube Data API v3 |
| TikTok | 🔧 Başvuru gerekiyor | Content Posting API |
| Instagram | 🔧 Kurulum gerekiyor | Meta Graph API |
| Facebook | 🔧 Kurulum gerekiyor | Meta Graph API |

## 🚀 Hızlı Başlangıç

### 1. YouTube (Zaten Kurulu)
YouTube API zaten yapılandırılmış. Ek işlem gerekmez.

### 2. Instagram & Facebook (Meta Graph API)

#### Adım 1: Meta Developer Hesabı
1. https://developers.facebook.com adresine gidin
2. Geliştirici hesabı oluşturun

#### Adım 2: Facebook App Oluşturma
1. "My Apps" → "Create App"
2. App türü: "Business"
3. App adı: "Video Kur Social"

#### Adım 3: Instagram Graph API Ekleme
1. App Dashboard → "Add Products"
2. "Instagram Graph API" → "Set Up"
3. Gerekli izinleri ekleyin:
   - `instagram_basic`
   - `instagram_content_publish`
   - `pages_read_engagement`

#### Adım 4: Credentials Alma
1. Settings → Basic
2. App ID ve App Secret'ı kopyalayın

#### Adım 5: Kimlik Doğrulama
```bash
cd python
python -c "
from social.instagram.auth import MetaAuth
auth = MetaAuth('../data/social_credentials/meta')
auth.save_config('YOUR_APP_ID', 'YOUR_APP_SECRET')
auth.authenticate()
"
```

### 3. TikTok (Content Posting API)

⚠️ **NOT:** TikTok Content Posting API başvuru ve onay süreci gerektirir (1-2 hafta).

#### Adım 1: TikTok Developer Hesabı
1. https://developers.tiktok.com adresine gidin
2. Developer hesabı oluşturun

#### Adım 2: App Oluşturma
1. "Manage Apps" → "Create App"
2. App kategorisi: "Content Posting"
3. Platform: "Web"

#### Adım 3: Content Posting API Başvurusu
1. "Products" → "Content Posting API"
2. Başvuru formunu doldurun
3. Onay bekleyin

#### Adım 4: Onay Sonrası Kurulum
```bash
cd python
python -c "
from social.tiktok.auth import TikTokAuth
auth = TikTokAuth('../data/social_credentials/tiktok')
auth.save_config('YOUR_CLIENT_KEY', 'YOUR_CLIENT_SECRET')
auth.authenticate()
"
```

## 📋 Kullanım

### Web Arayüzü

1. **http://localhost/social.php** adresini açın
2. "Platformlar" sekmesinde bağlı hesapları görün
3. Dashboard'dan video seçip "Çoklu Platform Paylaşımı" yapın

### Scheduler Başlatma

```bash
# Social Media Scheduler'ı başlat
start_social_scheduler.bat

# Veya manuel:
cd python
python scheduler/social_scheduler.py --interval 60
```

### API Kullanımı

#### Çoklu Platform Zamanlama
```bash
curl -X POST http://localhost/api/social.php \
  -H "Content-Type: application/json" \
  -d '{
    "action": "schedule_multi",
    "job_id": "job_xxx",
    "video_path": "output/job_xxx/final_video.mp4",
    "platforms": ["youtube", "instagram", "facebook"],
    "scheduled_time": "2026-03-21T15:00:00Z",
    "metadata": {
      "title": "Video Başlığı",
      "description": "Açıklama...",
      "tags": ["shorts", "viral"]
    }
  }'
```

#### Platform Durumu Sorgulama
```bash
curl "http://localhost/api/social.php?action=get_platforms"
```

#### Kuyruk Görüntüleme
```bash
curl "http://localhost/api/social.php?action=get_queue"
```

## 🔧 Dosya Yapısı

```
Video_Kur/
├── python/
│   ├── social/
│   │   ├── __init__.py
│   │   ├── base.py                 # Base uploader class
│   │   ├── platform_optimizer.py   # AI metadata optimizer
│   │   ├── tiktok/
│   │   │   ├── auth.py
│   │   │   └── uploader.py
│   │   ├── instagram/
│   │   │   ├── auth.py             # Meta OAuth (shared)
│   │   │   └── uploader.py
│   │   └── facebook/
│   │       └── uploader.py
│   └── scheduler/
│       ├── social_queue_manager.py
│       └── social_scheduler.py
├── data/
│   ├── social_credentials/
│   │   ├── meta/
│   │   │   ├── meta_config.json
│   │   │   ├── meta_token.json
│   │   │   └── meta_accounts.json
│   │   └── tiktok/
│   │       ├── tiktok_config.json
│   │       └── tiktok_token.json
│   ├── social_queue.json
│   └── social_history.json
├── api/
│   └── social.php
├── frontend/
│   └── social.php
└── start_social_scheduler.bat
```

## 📊 Platform Özel Metadata

Her platform için optimize edilmiş metadata otomatik oluşturulur:

### TikTok
- Kısa, emoji ağırlıklı caption
- #fyp #viral zorunlu hashtag'ler
- Max 8 hashtag

### Instagram
- CTA ağırlıklı caption
- #reels zorunlu
- Max 30 hashtag
- "Kaydet!", "Yorum yap!" çağrıları

### Facebook
- Soru formatı caption
- Tartışma teşviki
- Max 10 hashtag

## ⚠️ Önemli Notlar

### API Limitleri

| Platform | Günlük Limit |
|----------|-------------|
| YouTube | ~6 upload (10,000 units) |
| TikTok | ~50 upload |
| Instagram | ~25 upload |
| Facebook | ~25 upload |

### Instagram Özel Gereksinimler
- Business veya Creator hesabı gerekli
- Facebook Page'e bağlı olmalı
- Video URL gerekli (yerel dosya direkt desteklenmiyor)

### Güvenlik
- Credentials dosyalarını paylaşmayın
- Token'ları .gitignore'a ekleyin
- App Secret'ları güvenli tutun

## 🆘 Sorun Giderme

### "Token expired" hatası
```bash
# Meta için yeniden authenticate
python -c "
from social.instagram.auth import MetaAuth
auth = MetaAuth('../data/social_credentials/meta')
auth.authenticate()
"
```

### "Permission denied" hatası
- Meta App'te gerekli izinlerin eklendiğinden emin olun
- Instagram hesabının Business/Creator olduğunu kontrol edin

### "Video upload failed" hatası
- Video formatının desteklendiğini kontrol edin (MP4 önerilir)
- Video süresinin platform limitlerinde olduğunu doğrulayın
- Dosya boyutunu kontrol edin

## 📞 Destek

Sorun yaşarsanız:
1. Scheduler loglarını kontrol edin
2. `social_queue.json` ve `social_history.json` dosyalarını inceleyin
3. Platform-specific error mesajlarını okuyun
