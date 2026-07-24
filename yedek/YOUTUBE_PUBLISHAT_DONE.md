# ✅ YouTube Planlanmış Yayın (publishAt) Özelliği - TAMAMLANDI

## 🎉 Özet

YouTube API'nin **publishAt** özelliği başarıyla entegre edildi! Artık videolar planlanmış zamanlarda otomatik olarak yayınlanacak.

---

## 📋 Yapılan Değişiklikler

### 1. **YouTube Uploader** (`python/youtube/uploader.py`)
```python
def upload_video(
    ...
    publish_at: Optional[str] = None  # ✅ YENİ PARAMETRE
)
```

**Özellikler:**
- `publish_at` parametresi eklendi (ISO 8601 formatında)
- publishAt kullanıldığında otomatik olarak `privacyStatus: 'private'` ayarlanıyor
- Video planlanmış zamanda otomatik olarak `public` oluyor

**Konsol Çıktısı:**
```
📤 Video yükleniyor: TEST - Planlanmış Yayın
🔐 Gizlilik: private
⏰ Planlanmış: 2026-03-28T19:09:28+00:00
```

---

### 2. **Social Media Scheduler** (`python/scheduler/social_scheduler.py`)

**Akıllı Zamanlama Mantığı:**
```python
# scheduled_time gelecekte ise (>5 dakika), publishAt kullan
if scheduled_time:
    scheduled_dt = datetime.fromisoformat(scheduled_time)
    now = datetime.now(timezone.utc)
    
    if (scheduled_dt - now).total_seconds() > 300:  # >5 min
        publish_at = scheduled_time
        privacy_status = 'private'  # publishAt gerektirir
```

**Konsol Çıktısı:**
```
[YT] Planlanmış yayın: 2026-03-28T19:30:00+00:00
[YT] Baslik: Kısa Video Başlığı
```

---

### 3. **YouTube API** (`api/youtube.php`)

**Güncelleme:**
```php
$scheduledTime = $input['scheduled_time'] ?? '';

$cmd = sprintf(
    'cd "%s" && %s -m youtube.uploader "%s" "%s" "%s" "%s" "%s" "%s" "%s" "%s" 2>&1',
    ...
    $scheduledTime  // ✅ 8. parametre olarak eklendi
);
```

**API Kullanımı:**
```json
POST /api/youtube.php?action=upload
{
  "job_id": "job_123",
  "video_path": "output/job_123/final_video.mp4",
  "scheduled_time": "2026-03-28T20:00:00Z",  // ✅ YENİ
  "metadata": {
    "title": "Video Başlığı",
    "privacy_status": "public"
  }
}
```

---

## 🧪 Test Sonuçları

### ✅ Test 1: Uploader Testi
```bash
python test_publish_at.py
```

**Sonuç:**
```
✅ BAŞARILI!
   Video ID: GBqiYHyMw_k
   URL: https://youtube.com/shorts/GBqiYHyMw_k
   
🎯 Planlanmış saat: 2026-03-28 19:09:28 UTC
```

**YouTube Studio'da:**
- ✅ Video "Scheduled" olarak görünüyor
- ✅ Visibility: Private (until scheduled time)
- ✅ Publish date/time: 2026-03-28 19:09:28 UTC

---

## 🎯 Nasıl Çalışıyor?

### Senaryo 1: Hemen Yayın
```
scheduled_time: null veya geçmiş zaman
→ privacy_status: 'public'
→ publish_at: null
→ Video hemen yayınlanır ✅
```

### Senaryo 2: Planlanmış Yayın (5+ dakika sonra)
```
scheduled_time: '2026-03-28T20:00:00Z'
→ privacy_status: 'private' (otomatik)
→ publish_at: '2026-03-28T20:00:00Z'
→ YouTube otomatik yayınlar ⏰
```

### Senaryo 3: Yakın Gelecek (<5 dakika)
```
scheduled_time: 2 dakika sonra
→ privacy_status: 'public'
→ publish_at: null
→ Video hemen yayınlanır (çok yakın) ✅
```

---

## 📅 Zamanlama Akışı

### 1. **Production Scheduler** (Video tamamlandığında)
```python
# production_scheduler.py
scheduled_time = self._calculate_scheduled_time(queue_data, job_id)
# → '2026-03-28T20:00:00Z'
```

### 2. **Social Queue** (Kuyruğa ekleme)
```python
# social_queue.json
{
  "queue_id": "youtube-shorts",
  "scheduled_time": "2026-03-28T20:00:00Z",  # ← Hesaplanan zaman
  ...
}
```

### 3. **Social Scheduler** (İşleme)
```python
# social_scheduler.py
scheduled_dt = parse(item['scheduled_time'])
if scheduled_dt > now + 5min:
    publish_at = scheduled_time  # YouTube'a gönder
    privacy = 'private'
```

### 4. **YouTube Upload** (Yükleme)
```python
# uploader.upload_video()
body['status'] = {
    'privacyStatus': 'private',
    'publishAt': '2026-03-28T20:00:00Z'
}
```

### 5. **YouTube Otomatik Yayın** ⏰
```
2026-03-28 20:00:00 UTC'de:
- privacyStatus: private → public
- Video herkese açılır
```

---

## 🚀 Kullanım Örnekleri

### Örnek 1: Frontend'den Kuyruk Ayarı
```javascript
// Kuyruk oluştur/düzenle
const queue = {
  name: "YouTube Shorts",
  platforms: ["youtube"],
  schedule: {
    type: "interval",
    start_time: "09:00",
    interval_minutes: 30,
    daily_limit: 10
  }
};

// Video 09:00'da başlayıp 30'ar dakika aralıkla yayınlanır:
// 09:00 → 09:30 → 10:00 → 10:30 ...
```

### Örnek 2: Manuel API Çağrısı
```bash
curl -X POST http://localhost/api/youtube.php?action=upload \
  -H "Content-Type: application/json" \
  -d '{
    "job_id": "job_123",
    "video_path": "output/job_123/final_video.mp4",
    "scheduled_time": "2026-03-29T15:00:00Z",
    "metadata": {
      "title": "Harika Video",
      "description": "Açıklama",
      "tags": ["shorts", "trending"],
      "privacy_status": "public"
    }
  }'
```

---

## ⚡ Avantajlar

### ✅ YouTube API Scheduling
- **Güvenilir**: YouTube kendi sunucularında zamanlamayı yönetir
- **Offline Çalışma**: Yükleme sonrası bilgisayar kapalı olabilir
- **YouTube Studio Entegrasyonu**: Tüm planlanmış videolar Studio'da görünür
- **Düzenlenebilir**: Kullanıcı YouTube Studio'dan zamanı değiştirebilir

### ✅ Günlük Limit Kontrolü
- **Mevcut Özellik**: `daily_limit` kontrolü zaten çalışıyor
- **Öncelik**: Günlük limit dolmuşsa video kuyruğa bile eklenmez

---

## 📊 Dosya Değişiklikleri

| Dosya | Satırlar | Değişiklik |
|-------|---------|------------|
| `python/youtube/uploader.py` | 61, 76-77, 105-112, 118-120 | `publish_at` parametresi ve publishAt ayarı |
| `python/youtube/__main__.py` | 10, 19, 23, 38 | CLI'ye publish_at argümanı eklendi |
| `python/scheduler/social_scheduler.py` | 433-452 | scheduled_time kontrolü ve publish_at kullanımı |
| `api/youtube.php` | 142, 221 | scheduled_time parametresi eklendi |

---

## 🧪 Test Komutları

### Test 1: Uploader
```bash
python test_publish_at.py
```

### Test 2: Tam Akış (Queue → Upload)
```bash
# 1. Video oluştur
python python/pipeline.py

# 2. Scheduler başlat
python python/scheduler/production_scheduler.py

# 3. Social scheduler başlat
python python/scheduler/social_scheduler.py
```

### Test 3: YouTube Studio Kontrolü
1. https://studio.youtube.com adresine git
2. "Content" sekmesini aç
3. "Filter" → "Visibility" → "Scheduled"
4. Planlanmış videoları gör

---

## 🎯 Sonraki Adımlar (Opsiyonel)

### 1. Thumbnail Desteği
Planlanmış videolara thumbnail eklemek:
```python
# Mevcut kod zaten destekliyor!
uploader.upload_video(
    ...
    thumbnail_path='path/to/thumbnail.jpg',
    publish_at='2026-03-29T12:00:00Z'
)
```

### 2. Frontend Gösterimi
Queue listesinde planlanmış videoları göster:
```javascript
// queues.php'de
if (item.scheduled_time) {
    showScheduledBadge(item.scheduled_time);
}
```

### 3. Email Bildirimleri
Video yayınlandığında email gönder (YouTube zaten bildirim gönderiyor)

---

## ✅ Durum

| Özellik | Durum |
|---------|-------|
| YouTube Uploader (publishAt) | ✅ TAMAMLANDI |
| Social Scheduler Entegrasyonu | ✅ TAMAMLANDI |
| API Desteği | ✅ TAMAMLANDI |
| Test | ✅ BAŞARILI |
| Dokümantasyon | ✅ TAMAMLANDI |

---

## 🎉 Sonuç

**YouTube planlanmış yayın özelliği başarıyla çalışıyor!**

- ✅ Videolar belirlenen zamanda otomatik yayınlanıyor
- ✅ YouTube Studio'da "Scheduled" olarak görünüyor
- ✅ Mevcut zamanlama sistemi (interval, start_time) ile tam uyumlu
- ✅ Test başarılı: Video ID `GBqiYHyMw_k`

**Hemen kullanabilirsiniz! 🚀**
