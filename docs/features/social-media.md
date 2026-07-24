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

#### Adım 5: Kimlik Doğrulama (Web UI)
1. **http://localhost:8000/accounts_meta.php** sayfasını açın
2. **Meta App Ayarları** sekmesinde App ID / App Secret / Redirect URI girip kaydedin
3. Kayıt satırındaki **OAuth Bağla** butonuna tıklayın
4. Meta login/izin adımlarını tamamlayın
5. İşlem sonrası aynı sayfada bağlantılar ve hesaplar otomatik görünür

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

1. **http://localhost:8000/accounts_youtube.php** ile YouTube hesaplarını yönetin
2. **http://localhost:8000/accounts_meta.php** ile Meta App, OAuth bağlantıları ve hesap varsayılanlarını yönetin
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
curl -X POST http://localhost:8000/api/social.php \
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
curl "http://localhost:8000/api/social.php?action=get_platforms"
```

#### Kuyruk Görüntüleme
```bash
curl "http://localhost:8000/api/social.php?action=get_queue"
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
│   │   │   ├── meta_accounts.json
│   │   │   ├── meta_apps.json
│   │   │   ├── meta_connections.json
│   │   │   └── meta_settings.json
│   │   └── tiktok/
│   │       ├── tiktok_config.json
│   │       └── tiktok_token.json
│   ├── social_queue.json
│   └── social_history.json
├── api/
│   ├── social.php
│   ├── meta_accounts.php
│   └── meta_oauth.php
├── frontend/
│   ├── accounts_youtube.php    # YouTube hesap yönetimi
│   └── accounts_meta.php       # Meta (Instagram/Facebook) hesap yönetimi
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

## ⚙️ Kuyruk Platform Yayın Seçenekleri

Queue ayarlarında Instagram/Facebook için aşağıdaki yayın seçenekleri desteklenir:

- `platform_settings.instagram.shareToFeed`: Reels içeriğini profil akışında da yayınlar (`true/false`).
- `platform_settings.facebook.type`: Facebook hedefini seçer (`reel` veya `video`).
- `platform_settings.facebook.publishAsStatus`: Durum/akış gönderisi zorlaması (`true` ise `type=reel` seçili olsa bile video moduna düşer).

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

### Instagram Local Video (S3/R2 Staging)
Instagram API local dosya yerine public URL istediği için sistem, `data/config.json` içindeki `socialStaging` ayarı varsa videoyu otomatik object storage'a yükler.

```json
{
  "socialStaging": {
    "enabled": true,
    "provider": "r2",
    "bucket": "video-kur-social",
    "region": "auto",
    "endpointUrl": "https://<accountid>.r2.cloudflarestorage.com",
    "accessKeyId": "R2_ACCESS_KEY",
    "secretAccessKey": "R2_SECRET_KEY",
    "publicBaseUrl": "https://cdn.ornek.com/instagram",
    "prefix": "instagram",
    "cleanupAfterUpload": true
  }
}
```

## 🆘 Sorun Giderme

### "Token expired" hatası
1. **accounts_meta.php** ekranında ilgili bağlantıda **Senkronize** deneyin
2. Gerekirse aynı app için tekrar **OAuth Bağla** çalıştırın
3. Hâlâ hata varsa bağlantıyı kapatıp yeniden OAuth ile bağlayın

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
