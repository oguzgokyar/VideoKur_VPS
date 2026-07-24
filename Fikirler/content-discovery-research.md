# 🎯 İÇERİK KEŞFİ SİSTEMİ - ARAŞTIRMA RAPORU

**Tarih:** 2026-03-21  
**Proje:** Video_Kur  
**Amaç:** Otomatik içerik keşfi ve toplu video üretim sistemi tasarımı

---

## 📋 GENEL BAKIŞ

Video_Kur'a eklenecek içerik keşfi sistemi için 5 farklı yöntem araştırıldı. Her yöntem, mevcut altyapıya uyumluluk, implementation zorluğu ve use case'lere göre değerlendirildi.

---

## 📰 YÖNTEM 1: RSS Feed Aggregator ⭐ ÖNERİLEN

### Açıklama
RSS feed'lerden otomatik içerik toplama ve video pipeline'a gönderme sistemi.

### Workflow
```
1. Kullanıcı RSS feed URL'leri ekler (CNN, BBC, TechCrunch)
2. Scheduler her 30 dakikada feed'leri kontrol eder
3. Yeni makaleler algılanır, skorlanır (keyword match, freshness)
4. Kullanıcı onayına sunar VEYA otomatik pipeline'a gönderir
5. Video üretimi başlar
```

### Kaynaklar
- Haber siteleri (CNN RSS, BBC RSS)
- Blog'lar (TechCrunch, Verge)
- Kategorik feed'ler (spor, teknoloji, ekonomi)
- Google News RSS

### Avantajlar
✅ **Kolay implementasyon** - Python `feedparser` kütüphanesi
✅ **Standardize** - RSS formatı evrensel
✅ **Güvenilir** - Resmi kaynaklardan veri
✅ **Scalable** - Sınırsız feed eklenebilir
✅ **Otomatik** - Scheduler ile tam otomasyon

### Dezavantajlar
❌ RSS yavaş güncellenebilir (bazı siteler 1-2 saatte bir)
❌ Popülerlik metriği eksik (trending detection yok)
❌ Tüm siteler RSS sunmuyor

### Gerekli Component'ler

**Backend:**
- `python/content/feed_parser.py` - RSS okuma
- `python/content/content_scorer.py` - Skorlama
- `api/content_sources.php` - CRUD API
- `data/content_sources.json` - Feed listesi

**Frontend:**
- `frontend/content.php` - İçerik keşfi sayfası
- `frontend/sources.php` - RSS kaynak yönetimi

**Database:**
- `data/discovered_content.json` - Bulunan içerikler
- `data/content_queue.json` - Onay bekleyenler

### Implementation Zorluğu
🟢 **Basit** (2-3 gün)

### Use Case
🎯 **Haber kanalları** - Günlük haber videoları üreten kullanıcılar

---

## 🔍 YÖNTEM 2: Keyword/Trend Monitor

### Açıklama
Keyword bazlı Google News/Bing News sorguları ile trending içerik keşfi.

### Workflow
```
1. Kullanıcı keyword'ler belirler ("yapay zeka", "teknoloji haberleri")
2. Scheduler Google News API / Bing News'u sorgular
3. Trending içerikler algılanır (son 24 saat)
4. Popülerlik skoruna göre sıralanır
5. En iyi 10 içerik pipeline'a gönderilir
```

### Kaynaklar
- Google News API
- Bing News API
- NewsAPI.org (50 req/gün free)
- Reddit trending (r/worldnews, r/technology)

### Avantajlar
✅ **Viral içerik** - Trend olan konuları yakalar
✅ **Multi-source** - Birden fazla kaynaktan aggregate
✅ **Kategori desteği** - Otomatik kategorilendirme
✅ **API-based** - Güvenilir, structured data

### Dezavantajlar
❌ **API limitleri** - Free tier sınırlı (50-100 req/gün)
❌ **Maliyet** - Premium tier gerekebilir ($449/ay NewsAPI)
❌ **Duplicate risk** - Aynı haber farklı kaynaklardan gelebilir

### Gerekli Component'ler

**Python:**
- `python/content/news_api.py` - API integrations
- `python/content/trend_detector.py` - Trending logic
- `python/content/deduplication.py` - Duplicate detection

**API:**
- `api/keywords.php` - Keyword CRUD
- `api/trending.php` - Trending içerik listesi

**Config:**
- NewsAPI key, Google News API key

### Implementation Zorluğu
🟡 **Orta** (4-5 gün)

### Use Case
🎯 **Viral content creators** - Trending konularda hızlı video üreten kullanıcılar

---

## 🤖 YÖNTEM 3: AI-Powered Topic Discovery

### Açıklama
AI ile otomatik konu önerisi ve URL araştırması yapan ileri seviye sistem.

### Workflow
```
1. Kullanıcı niche belirler ("teknoloji startup haberleri")
2. AI her gün 10 ilginç konu önerir
3. Her konu için otomatik Google search yapılır
4. En iyi kaynak URL'i seçilir
5. Pipeline'a gönderilir
```

### Kaynaklar
- AI-generated topics (Gemini, GPT)
- Google Search API (URL bulma)
- Perplexity API (direkt içerik + kaynak)

### Avantajlar
✅ **Yaratıcı** - AI orijinal konular önerir
✅ **Niche targeting** - Spesifik alanlara odaklanır
✅ **Tam otomasyon** - Sıfır manuel effort
✅ **Content quality** - AI ilginç konuları seçer

### Dezavantajlar
❌ **AI maliyet** - Her sorguda API cost
❌ **Unpredictable** - AI bazen off-topic önerir
❌ **Kaynak bulma zorluğu** - Google Search API gerekli

### Gerekli Component'ler

**Python:**
- `python/content/ai_topic_gen.py` - AI topic generation
- `python/content/url_finder.py` - Google Search wrapper

**Config:**
- Perplexity API key / Google Search API key

### Implementation Zorluğu
🔴 **Karmaşık** (7-10 gün)

### Use Case
🎯 **Premium kullanıcılar** - Yüksek kalite, niche içerik isteyenler

---

## 📱 YÖNTEM 4: Social Media Aggregator

### Açıklama
Reddit, Twitter gibi sosyal medya platformlarından viral post'ları toplayıp video'ya çevirme.

### Workflow
```
1. Subreddit'ler belirlenir (r/worldnews, r/tech)
2. Scheduler top posts'u çeker (son 24 saat)
3. Upvote/engagement skoruna göre sıralar
4. Post'taki URL'i extract eder
5. Pipeline'a gönderilir
```

### Kaynaklar
- Reddit API (PRAW library)
- Twitter API (v2)
- LinkedIn trending posts
- Hacker News API

### Avantajlar
✅ **Viral guaranteed** - Sosyal proof var
✅ **Free API** - Reddit, HackerNews free
✅ **Community curated** - İnsanlar filtrelemiş
✅ **Real-time** - Anlık trending

### Dezavantajlar
❌ **URL çıkarma** - Her post'ta URL yok
❌ **Content type mix** - Video, resim, metin karışık
❌ **API rate limits** - Twitter API kısıtlı

### Gerekli Component'ler

**Python:**
- `python/content/reddit_scraper.py` - PRAW integration
- `python/content/twitter_scraper.py` - Twitter API
- `python/content/url_extractor.py` - URL extraction

**Dependencies:**
- `praw` (Reddit)
- `tweepy` (Twitter)

### Implementation Zorluğu
🟡 **Orta** (5-6 gün)

### Use Case
🎯 **Social-first creators** - Reddit/Twitter'da viral olan içerikleri video'ya çevirmek isteyenler

---

## 🗂️ YÖNTEM 5: Hybrid - Manual + Auto ⭐ BAŞLANGIÇ İÇİN ÖNERİLEN

### Açıklama
Manuel URL ekleme ve otomatik RSS feed toplama kombinasyonu.

### Workflow
```
1. **Manuel:** Kullanıcı ilginç URL'leri ekler
2. **Otomatik:** RSS feed'ler arka planda toplar
3. Tüm içerikler tek havuzda toplanır
4. Kullanıcı onaylar veya otomatik skorlama yapar
5. Seçilenler batch olarak pipeline'a gönderilir
```

### Kaynaklar
- User-submitted URLs (manuel)
- RSS feeds (otomatik)
- Bookmark integration (Chrome extension - gelecek)

### Avantajlar
✅ **Kontrol** - Kullanıcı final sözü söyler
✅ **Esneklik** - Manuel + otomatik dengesi
✅ **Kolay başlangıç** - Basit UI
✅ **Sıfır API cost** - RSS free

### Dezavantajlar
❌ **Manuel effort** - Tam otomasyon değil
❌ **Scalability sınırlı** - Kullanıcı kapasitesine bağlı

### Gerekli Component'ler

**Minimal:**
- `frontend/content.php` - Content management UI
- `api/content.php` - Content CRUD
- `data/content_pool.json` - Content storage
- `python/content/batch_processor.py` - Batch pipeline

### Implementation Zorluğu
🟢 **Basit** (2-3 gün)

### Use Case
🎯 **Tüm kullanıcılar** - Başlangıç için ideal, sonra otomasyona geçiş

---

## 📊 KARŞILAŞTIRMA TABLOSU

| Yöntem | Otomasyon | Maliyet | Zorluk | Kalite | Hız | Öncelik |
|--------|-----------|---------|--------|--------|-----|---------|
| **RSS Feeds** | 🟢 Yüksek | Free | Basit | 8/10 | Orta | ⭐⭐⭐ |
| **Keyword/Trend** | 🟢 Yüksek | $-$$ | Orta | 9/10 | Hızlı | ⭐⭐ |
| **AI Discovery** | 🟢 Tam | $$$ | Zor | 10/10 | Orta | ⭐ |
| **Social Media** | 🟡 Orta | Free-$ | Orta | 7/10 | Hızlı | ⭐⭐ |
| **Hybrid Manual** | 🟡 Düşük | Free | Basit | 9/10 | Yavaş | ⭐⭐⭐ |

**Maliyet Açıklaması:**
- Free: Hiç maliyet yok
- $: ~$50-100/ay
- $$: ~$100-500/ay
- $$$: $500+/ay

---

## 🎯 ÖNERİLEN UYGULAMA STRATEJİSİ

### 3 Fazlı Yaklaşım

#### **FAZA 1: Hybrid Manual + RSS (2-3 gün) ✅ BAŞLANGIÇ**

**Neden bu?**
- En hızlı MVP
- Sıfır API cost
- Kullanıcı kontrolü
- Kolay öğrenme eğrisi

**Özellikler:**
- Manuel URL ekleme formu
- RSS feed kaynak yönetimi
- Otomatik feed kontrol (her 30 dk)
- İçerik havuzu listesi
- Batch processing (seçili içerikleri pipeline'a gönder)
- Skorlama sistemi (keyword match, freshness)

**Geliştirme Süresi:** 2-3 gün

---

#### **FAZA 2: Keyword/Trend Monitor (1 hafta)**

**Eklenen Özellikler:**
- NewsAPI.org entegrasyonu
- Keyword yönetimi
- Trending detection
- Duplicate removal
- Otomatik kategorilendirme
- Popülerlik skorlaması

**Geliştirme Süresi:** 4-5 gün

---

#### **FAZA 3: AI Discovery (gelecek)**

**Eklenen Özellikler:**
- Gemini ile topic generation
- Niche targeting
- Google Search API entegrasyonu
- Tam otomasyon
- A/B testing

**Geliştirme Süresi:** 7-10 gün

---

## 🛠️ ÖNERİLEN MİMARİ (FAZA 1)

### Frontend: `content.php`

```
┌─────────────────────────────────────────┐
│   İÇERİK YÖNETİMİ                       │
├─────────────────────────────────────────┤
│                                         │
│  [+ URL Ekle]  [RSS Kaynakları]        │
│                                         │
│  📋 İçerik Havuzu:                     │
│  ┌───────────────────────────────────┐ │
│  │ ☐ AI Devrimi - CNN                │ │
│  │   Yeni • 2 saat önce • Skor: 85   │ │
│  │                                   │ │
│  │ ☐ Tech Startup Haberi - BBC (RSS)│ │
│  │   Yeni • 1 saat önce • Skor: 92   │ │
│  │                                   │ │
│  │ ☑ Ekonomi Raporu - Manuel         │ │
│  │   Hazır • 30 dk önce • Skor: 78   │ │
│  └───────────────────────────────────┘ │
│                                         │
│  Filtre: [Tümü ▼] [Sırala: Skor ▼]    │
│                                         │
│  [3 Seçili] [Pipeline'a Gönder →]     │
└─────────────────────────────────────────┘
```

### Backend: `api/content.php`

**Endpoints:**
```
GET  /api/content.php?list=1          → İçerik listesi
GET  /api/content.php?id=xxx          → Tek içerik detayı
POST /api/content.php (action: add)   → Manuel URL ekle
POST /api/content.php (action: process) → Seçilenleri pipeline'a gönder
DELETE /api/content.php?id=xxx        → İçerik sil
```

### Python: `content/batch_processor.py`

```python
def process_content_batch(content_ids):
    """
    Seçili içerikleri sırasıyla pipeline'a gönder
    """
    for content_id in content_ids:
        content = load_content(content_id)
        
        # Pipeline'a job oluştur
        job_id = create_job(
            url=content['url'],
            template='short_haber',
            videoWidth=1080,
            videoHeight=1920
        )
        
        # İçeriği işaretleme
        mark_content_processed(content_id, job_id)
        
        # Log
        print(f"✅ {content['title']} → {job_id}")
```

### Data: `content_pool.json`

```json
{
  "content": [
    {
      "id": "content_xxxxx",
      "url": "https://cnn.com/article",
      "title": "AI Devrimi Başladı",
      "source": "CNN RSS",
      "source_type": "rss",
      "discovered_at": "2026-03-21T15:00:00Z",
      "score": 85,
      "status": "pending",
      "processed_job_id": null,
      "metadata": {
        "keywords": ["AI", "teknoloji"],
        "category": "teknoloji",
        "freshness_hours": 2
      }
    }
  ]
}
```

---

## 💾 VERİ YAPILARI

### Content Pool Item

```json
{
  "id": "content_648f5a3b2c1e0",
  "url": "https://example.com/article",
  "title": "Başlık",
  "source": "CNN RSS",
  "source_type": "rss|manual|api",
  "discovered_at": "2026-03-21T15:00:00Z",
  "score": 85,
  "status": "pending|processing|completed|failed",
  "processed_job_id": "job_xxxxx",
  "metadata": {
    "keywords": ["keyword1", "keyword2"],
    "category": "teknoloji",
    "freshness_hours": 2,
    "social_shares": 1250
  }
}
```

### RSS Feed Source

```json
{
  "id": "source_xxxxx",
  "name": "CNN Tech",
  "url": "https://rss.cnn.com/rss/tech.rss",
  "type": "rss",
  "category": "teknoloji",
  "enabled": true,
  "check_interval_minutes": 30,
  "last_checked": "2026-03-21T15:00:00Z",
  "keywords": ["AI", "startup", "teknoloji"],
  "auto_approve": false
}
```

---

## 🔄 WORKFLOW DİYAGRAMI

```
┌──────────────────┐
│  RSS Scheduler   │ (Her 30 dk çalışır)
└────────┬─────────┘
         ↓
┌──────────────────┐
│  Feed Parser     │ → Yeni makaleleri çek
└────────┬─────────┘
         ↓
┌──────────────────┐
│  Content Scorer  │ → Keyword match, freshness
└────────┬─────────┘
         ↓
┌──────────────────┐
│  Content Pool    │ (data/content_pool.json)
└────────┬─────────┘
         ↓
┌──────────────────┐
│  Frontend UI     │ → Kullanıcı inceler, seçer
└────────┬─────────┘
         ↓
┌──────────────────┐
│ Batch Processor  │ → Seçilenleri pipeline'a gönder
└────────┬─────────┘
         ↓
┌──────────────────┐
│  Video Pipeline  │ → Mevcut sistem (scraper→video)
└──────────────────┘
```

---

## 📝 SKORLAMA SİSTEMİ (FAZA 1)

### Skor Hesaplama

```python
def calculate_content_score(content):
    score = 0
    
    # 1. Freshness (max 40 puan)
    hours_old = (now - content.published_date).hours
    if hours_old < 2:
        score += 40
    elif hours_old < 6:
        score += 30
    elif hours_old < 12:
        score += 20
    elif hours_old < 24:
        score += 10
    
    # 2. Keyword Match (max 30 puan)
    keywords = ["AI", "yapay zeka", "teknoloji", "startup"]
    matches = sum(1 for k in keywords if k.lower() in content.title.lower())
    score += min(matches * 10, 30)
    
    # 3. Source Quality (max 20 puan)
    trusted_sources = ["CNN", "BBC", "Reuters", "TechCrunch"]
    if content.source in trusted_sources:
        score += 20
    else:
        score += 10
    
    # 4. Content Length (max 10 puan)
    if 500 < content.word_count < 2000:
        score += 10
    elif 200 < content.word_count < 500:
        score += 5
    
    return score  # 0-100 arası
```

---

## 🚀 IMPLEMENTATION CHECKLIST (FAZA 1)

### Backend Tasks

- [ ] `python/content/__init__.py` - Package init
- [ ] `python/content/feed_parser.py` - RSS parsing (feedparser)
- [ ] `python/content/content_scorer.py` - Scoring logic
- [ ] `python/content/batch_processor.py` - Batch pipeline
- [ ] `python/content/scheduler.py` - RSS feed checker
- [ ] `api/content.php` - Content CRUD API
- [ ] `api/content_sources.php` - RSS source management
- [ ] `data/content_pool.json` - Content storage
- [ ] `data/content_sources.json` - RSS sources

### Frontend Tasks

- [ ] `frontend/content.php` - Main content management page
- [ ] `frontend/sources.php` - RSS source manager
- [ ] Frontend: Alpine.js state management
- [ ] Frontend: Checkbox selection system
- [ ] Frontend: Batch action buttons
- [ ] Frontend: Scoring display
- [ ] Frontend: Filter/sort controls
- [ ] Sidebar: Add "İçerikler" menu item

### Scheduler Integration

- [ ] `start_content_scheduler.bat` - Windows scheduler starter
- [ ] Cron job setup documentation
- [ ] Scheduler error handling
- [ ] Scheduler logging

---

## 🧪 TEST PLAN

### Unit Tests
- RSS feed parsing accuracy
- Scoring algorithm correctness
- Batch processor job creation
- Duplicate detection

### Integration Tests
- End-to-end: RSS → Pipeline → Video
- API endpoint functionality
- Scheduler execution
- Error handling

### User Acceptance
- UI usability
- Batch selection workflow
- RSS source management
- Content approval process

---

## 📈 BAŞARI METRİKLERİ

### FAZA 1 Hedefleri
- ✅ 10+ RSS feed kaynağı eklenebilir
- ✅ Saatte 50+ içerik keşfedebilir
- ✅ %80+ doğrulukla skorlama
- ✅ 10 içeriği 5 dakikada batch işleyebilir
- ✅ Sıfır duplicate içerik

### FAZA 2 Hedefleri
- ✅ 100+ günlük trending içerik
- ✅ %90+ doğrulukla duplicate detection
- ✅ 5+ farklı API kaynağı

### FAZA 3 Hedefleri
- ✅ Tam otomatik içerik üretimi
- ✅ Niche targeting (10+ kategori)
- ✅ AI quality scoring

---

## 🔧 DEPENDENCIES

### Python Packages
```
feedparser==6.0.10       # RSS parsing
requests==2.31.0         # HTTP requests
beautifulsoup4==4.12.0   # HTML parsing (future)
python-dateutil==2.8.2   # Date handling
schedule==1.2.0          # Scheduler (alternative to cron)
```

### PHP
- Existing Video_Kur stack
- No additional PHP extensions

---

## 💡 FUTURE ENHANCEMENTS

### Short-term (3-6 ay)
- Chrome extension: Bookmark → Content pool
- Mobile app: URL sharing
- Webhook integration: Zapier, IFTTT
- Email digest: Günlük içerik raporu

### Long-term (6-12 ay)
- AI content quality prediction
- Auto A/B testing (title/thumbnail)
- Performance analytics
- Multi-language support
- Content collaboration (team features)

---

## 🎯 KARAR MATRİSİ

Hangi yöntemi seçmelisiniz?

| Eğer... | O Zaman... |
|---------|------------|
| Hızlı başlamak istiyorsanız | **FAZA 1: Hybrid Manual + RSS** |
| Viral içerik istiyorsanız | **YÖNTEM 2: Keyword/Trend** |
| Tam otomasyon istiyorsanız | **3 Fazlı Yaklaşım** |
| Bütçeniz kısıtlıysa | **YÖNTEM 1: RSS** veya **YÖNTEM 5: Hybrid** |
| Sosyal proof önemliyse | **YÖNTEM 4: Social Media** |
| Premium kalite istiyorsanız | **YÖNTEM 3: AI Discovery** |

---

## 📞 SONRAKI ADIMLAR

1. **Yöntem seçimi yapın**
2. **Implementation planı onaylayın**
3. **Geliştirmeye başlayın**
4. **Test edin**
5. **Kullanıcıya sunun**

---

**Tavsiye:** FAZA 1 (Hybrid Manual + RSS) ile başlayıp, kullanıcı feedback'ine göre FAZA 2 ve FAZA 3'e geçin. Bu yaklaşım hem hızlı MVP hem de gelecek-proof bir mimari sağlar.

---

**Rapor Sahibi:** GitHub Copilot CLI  
**Proje:** Video_Kur  
**Versiyon:** 1.0  
**Son Güncelleme:** 2026-03-21
