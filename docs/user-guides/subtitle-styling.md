# 💬 Altyazı Stili Yönetimi - Kullanım Kılavuzu

## ✅ Yapılan Geliştirmeler

### 1. Ayarlar Sayfasına "Altyazı" Tabı Eklendi
- Frontend → Ayarlar → Altyazı sekmesi
- Görsel altyazı stil editörü
- Canlı önizleme desteği
- Hazır stil şablonları

### 2. Varsayılan Altyazı Stili Sistemi
- Config dosyasına kaydedilen stil tüm videolarda kullanılır
- Job bazlı özel stil desteği (isteğe bağlı)
- Hex renk desteği (#FFFFFF formatı)
- ASS formatına otomatik dönüşüm

---

## 🎨 Altyazı Stil Parametreleri

### Temel Ayarlar

| Parametre | Açıklama | Varsayılan | Aralık |
|-----------|----------|------------|--------|
| `FontName` | Yazı tipi | Arial | Arial, Helvetica, Impact, vb. |
| `FontSize` | Yazı boyutu | 24 | 12-48 |
| `PrimaryColour` | Yazı rengi | #FFFFFF (Beyaz) | Hex kod |
| `OutlineColour` | Kenarlık rengi | #000000 (Siyah) | Hex kod |
| `BackColour` | Arka plan rengi | #80000000 (Yarı saydam siyah) | Hex kod + alpha |
| `Bold` | Kalın yazı | 1 (Evet) | 0 veya 1 |

### Gelişmiş Ayarlar

| Parametre | Açıklama | Varsayılan | Aralık |
|-----------|----------|------------|--------|
| `Outline` | Kenarlık kalınlığı | 3 | 0-5 |
| `Shadow` | Gölge şiddeti | 1 | 0-5 |
| `MarginV` | Alt boşluk (px) | 100 | 20-300 |
| `Alignment` | Hizalama | 2 (Alt orta) | 1-9 |
| `BorderStyle` | Kenarlık stili | 3 (Köşeli kenarlık) | 1-4 |

---

## 📝 Kullanım Senaryoları

### Senaryo 1: Varsayılan Stili Değiştirme

**Adımlar:**
1. Ayarlar → Altyazı sekmesine gidin
2. İstediğiniz ayarları yapın
3. Önizlemede sonucu görün
4. "Tüm Ayarları Kaydet" butonuna basın

**Sonuç:** 
- Yeni oluşturulan tüm videolar bu stili kullanır
- Mevcut videolar etkilenmez

---

### Senaryo 2: Hazır Stil Kullanma

Frontend'te 4 hazır stil var:

**1. Klasik (Classic)**
```json
{
  "FontName": "Arial",
  "FontSize": 20,
  "PrimaryColour": "#FFFFFF",
  "OutlineColour": "#000000",
  "Outline": 2,
  "Shadow": 0,
  "MarginV": 80,
  "Bold": 0
}
```
- Genel haberler için ideal
- Sade ve okunabilir

**2. Kalın (Bold Bottom)**
```json
{
  "FontName": "Arial",
  "FontSize": 24,
  "PrimaryColour": "#FFFFFF",
  "OutlineColour": "#000000",
  "Outline": 3,
  "Shadow": 1,
  "MarginV": 100,
  "Bold": 1
}
```
- Dikkat çekici içerik için
- Kalın kenarlık ve gölge

**3. Sarı (Yellow Bold)**
```json
{
  "FontName": "Arial",
  "FontSize": 22,
  "PrimaryColour": "#FFFF00",
  "OutlineColour": "#000000",
  "Outline": 2,
  "Shadow": 1,
  "MarginV": 80,
  "Bold": 1
}
```
- Vurgulu haberler için
- Sarı metin, siyah kenarlık

**4. TikTok Stili**
```json
{
  "FontName": "Arial",
  "FontSize": 26,
  "PrimaryColour": "#FFFFFF",
  "OutlineColour": "#FF0000",
  "Outline": 3,
  "Shadow": 0,
  "MarginV": 120,
  "Bold": 1
}
```
- Viral içerik için
- Kırmızı kenarlık, büyük yazı

---

### Senaryo 3: Job Bazlı Özel Stil

API üzerinden iş oluştururken özel stil belirtebilirsiniz:

```json
{
  "url": "https://example.com/news",
  "subtitleStyle": {
    "FontName": "Impact",
    "FontSize": 28,
    "PrimaryColour": "#00FFFF",
    "OutlineColour": "#FF00FF",
    "Bold": 1,
    "MarginV": 150
  }
}
```

**Not:** Job bazlı stil, varsayılan stili override eder.

---

## 🎯 Önerilen Ayarlar

### YouTube Shorts
```json
{
  "FontName": "Arial",
  "FontSize": 24,
  "PrimaryColour": "#FFFFFF",
  "OutlineColour": "#000000",
  "Outline": 3,
  "Shadow": 1,
  "MarginV": 100,
  "Bold": 1
}
```

### TikTok
```json
{
  "FontName": "Arial",
  "FontSize": 26,
  "PrimaryColour": "#FFFFFF",
  "OutlineColour": "#000000",
  "Outline": 3,
  "Shadow": 0,
  "MarginV": 120,
  "Bold": 1
}
```

### Instagram Reels
```json
{
  "FontName": "Helvetica",
  "FontSize": 22,
  "PrimaryColour": "#FFFFFF",
  "OutlineColour": "#000000",
  "Outline": 2,
  "Shadow": 1,
  "MarginV": 80,
  "Bold": 1
}
```

---

## 🔧 Teknik Detaylar

### Renk Formatları

**Frontend (UI):**
- Hex renk: `#FFFFFF`
- Renk seçici ile kolay düzenleme

**Backend (ASS Format):**
- ASS renk: `&H00BBGGRR`
- Otomatik dönüşüm: `#RRGGBB` → `&H00BBGGRR`

**Örnek Dönüşüm:**
```
#FFFFFF (Beyaz) → &H00FFFFFF
#FF0000 (Kırmızı) → &H000000FF
#00FF00 (Yeşil) → &H0000FF00
#0000FF (Mavi) → &H00FF0000
```

### Öncelik Sırası

1. **Job-specific style** (API'den gelen)
2. **Config default style** (Ayarlardan kaydedilen)
3. **Hardcoded classic** (video_composer.py'deki varsayılan)

### Dosya Konumları

- **Frontend:** `frontend/settings.php` → Altyazı tabı
- **Config:** `data/config.json` → `subtitleStyle` objesi
- **Backend:** `python/video_composer.py` → `compose_video()` fonksiyonu
- **Pipeline:** `python/pipeline.py` → Config okuma

---

## ⚠️ Önemli Notlar

1. **Renk Alpha Kanalı:** 
   - 4 karakterli alpha: `#80000000` (ilk 2 hane alpha, son 6 hane RGB)
   - Frontend'te alpha ayarı şu an desteklenmiyor (gelecek güncelleme)

2. **Hizalama (Alignment):**
   ```
   7  8  9
   4  5  6
   1  2  3
   ```
   - 2: Alt orta (varsayılan)
   - 5: Tam orta
   - 8: Üst orta

3. **BorderStyle:**
   - 1: Outline + drop shadow
   - 3: Opaque box
   - 4: Outline + opaque box

---

## 🚀 Hızlı Başlangıç

**1. Ayarlar sayfasını açın:**
```
http://localhost:8000/settings.php
```

**2. Altyazı sekmesine gidin**

**3. Stilinizi seçin:**
- Hazır stillerden birini seçin veya
- Manuel olarak özelleştirin

**4. Kaydedin:**
- "Tüm Ayarları Kaydet" butonuna basın

**5. Test edin:**
- Yeni bir video oluşturun
- Altyazıların yeni stili kullandığını görün

---

## 📊 Örnek Config Dosyası

```json
{
  "geminiKey": "...",
  "elevenKey": "...",
  "subtitleStyle": {
    "FontName": "Arial",
    "FontSize": 24,
    "PrimaryColour": "#FFFFFF",
    "OutlineColour": "#000000",
    "BackColour": "#80000000",
    "BorderStyle": 3,
    "Outline": 3,
    "Shadow": 1,
    "MarginV": 100,
    "Alignment": 2,
    "Bold": 1
  }
}
```

---

**Tarih:** 2026-03-19  
**Versiyon:** 1.0 - Altyazı Stil Yönetimi  
**Durum:** ✅ Aktif
