# 🎬 Sequential Production System - Quick Start

## Paralel Üretim Kaldırıldı! ✅

Video_Kur artık **tek seferde sadece 1 video üretir** - paralel üretim tamamen engellendi.

---

## 🚀 Hızlı Başlangıç

### 1. Production Scheduler'ı Başlat

```bash
python python/scheduler/production_scheduler.py
```

**Çıktı:**
```
======================================================================
Production Scheduler - ACTIVE MODE
======================================================================
✅ Sequential video production enabled
✅ One video at a time - no parallel processing
======================================================================

🚀 Production Scheduler started
📋 Monitoring production queue...
```

### 2. Video Oluştur

Web arayüzünden video oluşturduğunuzda **otomatik olarak kuyruğa eklenir**.

```
http://localhost:8000/frontend/create.php
```

### 3. Kuyruğu İzle

```
http://localhost:8000/frontend/production_queue.php
```

Real-time olarak:
- 🎬 Şu anda üretilen video
- 📋 Sırada bekleyen videolar
- 📊 İstatistikler

---

## 🔄 Nasıl Çalışır?

```
Video Oluştur ──> Kuyruğa Ekle ──> Sırayla İşle ──> Bitir ──> Sıradaki Video
      │                │                │              │
      └────────────────┴────────────────┴──────────────┘
                 Tek Seferde 1 Video
```

### Önceki Sistem (Paralel)
```
❌ Video 1 │████████│ (paralel)
❌ Video 2 │████████│ (paralel)
❌ Video 3 │████████│ (paralel)
   └─> Kaynak çakışmaları!
```

### Yeni Sistem (Sıralı)
```
✅ Video 1 │████████│ ────> Bitti
✅ Video 2           │████████│ ────> Bitti
✅ Video 3                     │████████│ ────> Bitti
   └─> Kararlı, öngörülebilir!
```

---

## 📋 CLI Komutları

### Kuyruğa Manuel Ekleme
```bash
python python/scheduler/production_queue_manager.py add --job-id job_123 --priority 5
```

### Durum Kontrolü
```bash
python python/scheduler/production_queue_manager.py status
```

**Çıktı:**
```json
{
  "current_job": "job_123",
  "queue_length": 3,
  "queue": [...],
  "stats": {
    "total_completed": 10,
    "total_failed": 1
  }
}
```

### Kuyruktan Çıkarma
```bash
python python/scheduler/production_queue_manager.py remove --job-id job_123
```

### Kuyruğu Temizle
```bash
python python/scheduler/production_queue_manager.py clear
```

---

## 🧪 Test

```bash
python test_sequential_production.py
```

**4 test çalışır:**
1. ✅ Queue Operations
2. ✅ Global Production Lock
3. ✅ Sequential Enforcement
4. ✅ Integration Test

---

## 📊 API Endpoints

### Production Queue API

**GET** `/api/production_queue.php?action=status`
```json
{
  "success": true,
  "current_job": "job_xxx",
  "queue_length": 5,
  "queue": [...]
}
```

**GET** `/api/production_queue.php?action=position&job_id=xxx`
```json
{
  "success": true,
  "status": "waiting",
  "position": 3,
  "queue_length": 5
}
```

**POST** `/api/production_queue.php?action=add`
```json
{
  "job_id": "job_xxx",
  "priority": 0
}
```

**DELETE** `/api/production_queue.php?job_id=xxx`
```json
{
  "success": true,
  "message": "Job removed from queue"
}
```

---

## ⚙️ Ayarlar

`data/production_queue.json` dosyasında:

```json
{
  "settings": {
    "auto_start_next": true,      // Otomatik sonraki işe geç
    "max_retries": 3,              // Maksimum tekrar deneme
    "retry_delay_seconds": 60      // Tekrar denemeler arası süre
  }
}
```

---

## 🔍 Sorun Giderme

### "Lock timeout" hatası
Başka bir video zaten üretiliyor. Kuyruğa ekle ve bekle.

### Kuyruk işlenmiyor
Production scheduler'ın çalıştığından emin ol:
```bash
python python/scheduler/production_scheduler.py
```

### Video takılı kaldı
Lock dosyasını kontrol et:
```bash
# Lock durumu
ls data/.locks/

# Gerekirse manuel sil (DİKKATLİ!)
rm data/.locks/production.lock
rm data/.locks/production.meta
```

---

## 📂 Önemli Dosyalar

| Dosya | Açıklama |
|-------|----------|
| `python/scheduler/production_queue_manager.py` | Kuyruk yöneticisi |
| `python/scheduler/production_scheduler.py` | Kuyruk işleyici (aktif mod) |
| `python/utils/production_lock.py` | Global üretim kilidi |
| `api/production_queue.php` | Queue API |
| `frontend/production_queue.php` | Queue görünümü |
| `data/production_queue.json` | Kuyruk verisi |
| `data/.locks/production.lock` | Aktif kilit |

---

## ✅ Avantajlar

- 🎯 **Kararlı Sistem:** Kaynak çakışması yok
- 📊 **Öngörülebilir:** Sabit performans
- 🔍 **İzlenebilir:** Net durum görünümü
- 🔄 **Otomatik:** Kur ve unut
- ⚖️ **Adil:** FIFO sıralama
- 🔁 **Dayanıklı:** Otomatik tekrar deneme

---

## 📝 Önemli Notlar

1. **Upload scheduler değişmedi** - Zaten sıralı çalışıyor
2. **Resume/regenerate doğrudan çalışır** - Kuyruğa eklenmez
3. **Manuel pipeline çalıştırma** - Kilit sayesinde yine sıralı
4. **Batch processor** - Artık kuyruğa ekliyor

---

## 🎉 Sonuç

**Paralel video üretimi %100 engellendi!**

✅ Tek video üretimi garantisi  
✅ Kuyruk sistemi aktif  
✅ Global lock çalışıyor  
✅ Tüm testler başarılı  
✅ Production-ready  

---

**Detaylı dokümantasyon:** `SEQUENTIAL_PRODUCTION_IMPLEMENTATION.md`
