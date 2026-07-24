# 📡 İçerik Keşfi Sistemi

**Yöntem 1: Hybrid Manual + Auto (RSS Feed Aggregator)**

Otomatik içerik toplama ve toplu video üretim sistemi.

---

## 🎯 Özellikler

### ✅ Tamamlanan
- ✅ Manuel URL ekleme
- ✅ RSS feed kaynak yönetimi
- ✅ Otomatik feed kontrol (her 30 dk)
- ✅ İçerik skorlama algoritması
- ✅ Batch processing (toplu pipeline gönderimi)
- ✅ Frontend UI (content.php)
- ✅ REST API (content.php, content_sources.php)
- ✅ Content scheduler
- ✅ Sidebar entegrasyonu

---

## 📁 Dosya Yapısı

```
Video_Kur/
├── python/
│   └── content/
│       ├── __init__.py              ← Package init
│       ├── feed_parser.py           ← RSS parsing (feedparser)
│       ├── content_scorer.py        ← Skorlama algoritması
│       ├── batch_processor.py       ← Batch pipeline
│       └── scheduler.py             ← RSS scheduler (30 dk)
│
├── api/
│   ├── content.php                  ← Content CRUD API
│   └── content_sources.php          ← RSS source management
│
├── frontend/
│   ├── content.php                  ← Ana içerik yönetim sayfası
│   └── components/
│       └── _sidebar.php             ← "İçerikler" menu item eklendi
│
├── data/
│   ├── content_pool.json            ← İçerik havuzu
│   └── content_sources.json         ← RSS kaynakları (varsayılan: TechCrunch, BBC)
│
└── start_content_scheduler.bat      ← Scheduler başlatıcı
```

---

## 🚀 Kullanım

### 1️⃣ Scheduler Başlat

```bash
# Windows
start_content_scheduler.bat

# Veya manuel
python python/content/scheduler.py
```

**Scheduler:**
- Her 30 dakikada RSS feed'leri kontrol eder
- Yeni içerikleri `content_pool.json`'a ekler
- Otomatik skorlama yapar
- Durdurmak için: `Ctrl+C`

### 2️⃣ Frontend Kullanımı

1. **Tarayıcıda aç:** `http://localhost:8000/content.php`

2. **Manuel URL Ekle:**
   - "URL Ekle" butonuna tıkla
   - URL ve başlık gir
   - İçerik havuzuna eklenir

3. **RSS Kaynağı Ekle:**
   - "RSS Kaynakları" butonuna tıkla
   - Kaynak adı, URL, kategori, keyword'ler gir
   - Scheduler otomatik bu kaynaktan içerik toplayacak

4. **İçerik Seç ve İşle:**
   - İçerik listesinden istediğini seç (checkbox)
   - "Pipeline'a Gönder" butonuna tıkla
   - Seçili içerikler sırasıyla video üretimine gönderilir

### 3️⃣ API Kullanımı

**İçerik Listesi:**
```bash
GET /api/content.php?list=1
GET /api/content.php?list=1&status=pending
GET /api/content.php?list=1&sort=score
```

**Manuel URL Ekle:**
```bash
POST /api/content.php
{
  "action": "add",
  "url": "https://example.com/article",
  "title": "Başlık"
}
```

**Batch Processing:**
```bash
POST /api/content.php
{
  "action": "process",
  "content_ids": ["content_abc123", "content_def456"]
}
```

**RSS Kaynak Ekle:**
```bash
POST /api/content_sources.php
{
  "name": "TechCrunch",
  "url": "https://techcrunch.com/feed/",
  "category": "teknoloji",
  "keywords": ["AI", "startup"]
}
```

---

## 📊 İçerik Skorlama

**Toplam Skor: 0-100**

| Faktör | Max Puan | Açıklama |
|--------|----------|----------|
| **Tazelik** | 40 | Son 2 saat = 40p, 6 saat = 30p, 12 saat = 20p |
| **Keyword Match** | 30 | Yüksek öncelikli keyword = 10p/adet |
| **Kaynak Güvenilirliği** | 20 | Güvenilir kaynak (CNN, BBC) = 20p |
| **Başlık Kalitesi** | 10 | Optimal uzunluk, soru işareti, sayılar |

**Skor Seviyeleri:**
- 80-100: Mükemmel (yeşil)
- 60-79: İyi (mavi)
- 40-59: Orta (sarı)
- 0-39: Düşük (gri)

---

## 🔧 Veri Yapısı

### Content Pool Item
```json
{
  "id": "content_abc123",
  "url": "https://example.com/article",
  "title": "AI Devrimi Başladı",
  "source": "TechCrunch RSS",
  "source_type": "rss",
  "discovered_at": "2026-03-21T15:00:00Z",
  "published_at": "2026-03-21T14:30:00Z",
  "score": 85,
  "status": "pending",
  "processed_job_id": null,
  "metadata": {
    "keywords": ["AI", "teknoloji"],
    "category": "teknoloji",
    "description": "Kısa açıklama..."
  }
}
```

### RSS Source
```json
{
  "id": "source_abc123",
  "name": "TechCrunch",
  "url": "https://techcrunch.com/feed/",
  "type": "rss",
  "category": "teknoloji",
  "enabled": true,
  "check_interval_minutes": 30,
  "last_checked": "2026-03-21T15:00:00Z",
  "keywords": ["AI", "startup"],
  "auto_approve": false
}
```

---

## ⚙️ Konfigürasyon

### Scheduler İnterval
`python/content/scheduler.py` dosyasında:
```python
time.sleep(1800)  # 30 dakika = 1800 saniye
```

### Yüksek Öncelikli Keywords
`python/content/content_scorer.py` dosyasında:
```python
self.high_priority_keywords = [
    'AI', 'yapay zeka', 'teknoloji', 'startup', ...
]
```

### Güvenilir Kaynaklar
`python/content/content_scorer.py` dosyasında:
```python
self.trusted_sources = [
    'TechCrunch', 'BBC News', 'CNN', 'Reuters', ...
]
```

---

## 🧪 Test

### Python Modülleri Test

**Feed Parser Test:**
```bash
python python/content/feed_parser.py
```

**Content Scorer Test:**
```bash
python python/content/content_scorer.py
```

**Batch Processor Test:**
```bash
python python/content/batch_processor.py content_abc123 content_def456
```

### API Test

**Content API:**
```bash
curl http://localhost:8000/api/content.php?list=1
```

**Sources API:**
```bash
curl http://localhost:8000/api/content_sources.php
```

---

## 📈 Workflow

```
┌──────────────────┐
│  Scheduler       │ (Her 30 dk çalışır)
│  scheduler.py    │
└────────┬─────────┘
         ↓
┌──────────────────┐
│  Feed Parser     │ → RSS'leri parse et
│  feed_parser.py  │
└────────┬─────────┘
         ↓
┌──────────────────┐
│  Content Scorer  │ → Skorla
│  content_scorer.py│
└────────┬─────────┘
         ↓
┌──────────────────┐
│  Content Pool    │ (content_pool.json)
│  data/           │
└────────┬─────────┘
         ↓
┌──────────────────┐
│  Frontend UI     │ → Kullanıcı seçer
│  content.php     │
└────────┬─────────┘
         ↓
┌──────────────────┐
│ Batch Processor  │ → Pipeline'a gönder
│ batch_processor.py│
└────────┬─────────┘
         ↓
┌──────────────────┐
│  Video Pipeline  │ → Mevcut sistem
│  pipeline.py     │
└──────────────────┘
```

---

## 🐛 Troubleshooting

### RSS feed parse edilmiyor
- Feed URL'ini kontrol edin (valid RSS/Atom?)
- `feedparser` yüklü mü? `pip install feedparser`
- Scheduler log'larını kontrol edin

### İçerik skorlanmıyor
- `python/content/content_scorer.py` test edin
- `content_pool.json` dosyasını kontrol edin

### Batch processing çalışmıyor
- Job dosyaları oluşuyor mu? (`data/jobs/`)
- Pipeline başlatılıyor mu? Log'ları kontrol edin

---

## 🎯 Gelecek İyileştirmeler

### Phase 2 (Planlanan)
- [ ] NewsAPI.org entegrasyonu
- [ ] Keyword/trend monitoring
- [ ] Duplicate detection (content similarity)
- [ ] Auto-approve mekanizması
- [ ] Email/Slack notifications

### Phase 3 (Gelecek)
- [ ] AI-powered topic generation (Gemini)
- [ ] Reddit/Twitter integration
- [ ] Content analytics
- [ ] A/B testing support

---

## 📞 Destek

**Dökümantasyon:** Bu dosya
**Fikirler:** `Fikirler/content-discovery-research.md`
**Plan:** `~/.copilot/session-state/.../plan.md`

---

**Versiyon:** 1.0.0  
**Oluşturma Tarihi:** 2026-03-21  
**Durum:** ✅ Production Ready
