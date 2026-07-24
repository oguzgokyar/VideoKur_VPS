# 📺 YouTube Shorts Video Yükleme Planı

## 🎯 Hedef
Üretilen videoları otomatik ve planlı şekilde YouTube kanalınıza yüklemek.

---

## 🚀 1. İlk Test Yüklemesi (5 dakika)

### Adım 1: Mevcut Bir Videoyu Test Edin

```bash
cd python

# Mevcut bir videoyu test amaçlı yükleyin (unlisted olarak)
python youtube/uploader.py "C:\Users\user\Documents\GitHub\Antigravity\Video_Kur\output\job_69b9e56b244608.61484112\final_video.mp4" "Test Video - Yapay Zeka Haberi" "Bu bir test yüklemesidir."
```

**Beklenen Sonuç:**
```
📤 Video yükleniyor: Test Video - Yapay Zeka Haberi
📁 Dosya: final_video.mp4
🔐 Gizlilik: unlisted
⏳ İlerleme: 100%
✅ Yükleme başarılı!
🆔 Video ID: abc123xyz
🔗 https://youtube.com/shorts/abc123xyz
```

### Adım 2: YouTube'da Kontrol Edin

1. Yukarıdaki linke tıklayın veya YouTube Studio'ya gidin
2. Videonun yüklendiğini doğrulayın
3. Metadata'yı kontrol edin (başlık, açıklama)
4. Başarılıysa bir sonraki adıma geçin!

---

## 📱 2. Web Arayüzünden Manuel Yükleme (Önerilen)

### Dashboard'dan Hızlı Yükleme

1. **http://localhost/dashboard.php** açın
2. Tamamlanmış bir video kartı bulun (status: done)
3. İki seçeneğiniz var:

#### A) Hemen Yükle ⚡
- **"⚡ Hemen Yükle"** butonuna tıklayın
- Modal açılır → Metadata düzenleyin:
  - Başlık (otomatik optimize edilmiş)
  - Açıklama (#Shorts hashtag'i ile)
  - Tags
  - Gizlilik: Public/Unlisted/Private
- **"Yükle"** butonuna tıklayın
- Progress bar gösterilir
- Başarılı olunca **"🔗 YouTube'da Aç"** butonu belirir

#### B) Zamanla 📅
- **"📅 Zamanla"** butonuna tıklayın
- Tarih ve saat seçin
- Metadata düzenleyin
- **"Zamanla"** butonuna tıklayın
- Video kuyruğa eklenir
- Scheduler otomatik yükler

---

## ⏰ 3. Zamanlama Sistemi Kurulumu

### Scheduler Servisini Başlatın

#### Windows - Task Scheduler (Otomatik Başlatma)

1. **Task Scheduler**'ı açın (Windows arama: "Task Scheduler")
2. Sağ tarafta **"Create Basic Task"** tıklayın
3. **Name:** `YouTube Upload Scheduler`
4. **Trigger:** "When the computer starts"
5. **Action:** "Start a program"
   - **Program/script:** `python`
   - **Add arguments:** `C:\Users\user\Documents\GitHub\Antigravity\Video_Kur\python\scheduler\scheduler.py --interval 300`
   - **Start in:** `C:\Users\user\Documents\GitHub\Antigravity\Video_Kur`
6. **Finish** → Task oluşturuldu
7. Task'ı sağ tıklayıp **"Run"** ile test edin

#### Manuel Başlatma (Test için)

```bash
cd python
python scheduler/scheduler.py --interval 60

# Çıktı:
# 📅 Scheduler başlatıldı
# ⏱️  Kontrol aralığı: 60 saniye
# 🚀 Scheduler çalışıyor...
# [2026-03-18 16:08:45] ⏳ Bekleyen yükleme yok
```

Scheduler arka planda çalışırken:
- Her 60 saniyede (veya belirlediğiniz aralıkta) kuyruğu kontrol eder
- Zamanı gelen videoları otomatik yükler
- Başarısız yüklemeleri 3 kez tekrar dener
- Logları ekrana yazar

---

## 🤖 4. Otomatik Zamanlama Stratejisi

### Senaryo 1: Günde 2 Video (Sabah & Akşam)

**Ayarlar:**
- Günlük upload: **2 video**
- Tercih edilen saatler: **12:00, 19:00**
- Strateji: **Sabit**

**Web Arayüzü:**
1. **http://localhost/queues.php** açın
2. Platform ayarlarında YouTube seçin
3. Ayarları yapın:
   - Günlük yükleme sayısı: **2**
   - Tercih saatler: 12:00, 19:00
   - Zamanlama türü: **Belirli Saatlerde**
4. **"💾 Kaydet"**

**Sonuç:**
- Video üretimi tamamlandığında otomatik olarak:
  - İlk video → Bugün 12:00 (eğer henüz olmadıysa)
  - İkinci video → Bugün 19:00
  - Üçüncü video → Yarın 12:00
  - vs.

### Senaryo 2: Günde 3 Video (Akıllı Zamanlama)

**Ayarlar:**
- Günlük upload: **3 video**
- Strateji: **Akıllı** (En yüksek trafik saatlerinde)

**Akıllı strateji otomatik seçer:**
- Hafta içi: 12:00-13:00, 17:00-18:00, 19:00-20:00
- Hafta sonu: 10:00-11:00, 19:00-20:00, 21:00-22:00

### Senaryo 3: Yoğun Yayın (Günde 5-6 Video)

**Ayarlar:**
- Günlük upload: **6 video** (YouTube API quota limiti)
- Tercih saatler: 09:00, 12:00, 15:00, 18:00, 20:00, 22:00
- Strateji: **Sabit**

⚠️ **Dikkat:** YouTube API günlük limiti 10,000 units (≈6 upload/gün). Daha fazla için quota artırımı gerekir.

---

## 📊 5. Günlük İş Akışı (Workflow)

### Sabah Rutini (09:00)

```bash
# 1. Scheduler'ın çalıştığından emin olun
# Task Manager → "python.exe" process'ini arayın
# Veya Task Scheduler'da durumu kontrol edin

# 2. Dashboard'a göz atın
http://localhost/dashboard.php

# 3. Kuyrukları kontrol edin
http://localhost/queues.php
```

### Yeni Video Üretimi

1. **http://localhost/create.php** → Yeni video oluştur
2. Video üretimi tamamlanınca (status: done)
3. İki seçenek:

**A) Otomatik zamanlama aktifse:**
- Video otomatik kuyruğa eklenir
- En yakın boş slota zamanlanır
- Hiçbir şey yapmanıza gerek yok! 🎉

**B) Manuel zamanlama:**
- Dashboard → "📅 Zamanla" butonuna tıkla
- Tarih/saat seç
- Metadata düzenle
- Zamanla

### Akşam Kontrolü (20:00)

```bash
# 1. Kuyruk durumunu kontrol edin
http://localhost/queues.php

# 2. Başarılı yüklemeleri YouTube'da kontrol edin
# Her videoda "🔗 YouTube'da Aç" linki var

# 3. Başarısız yüklemeleri inceleyin
# Hata mesajlarını okuyun
# Gerekirse manuel yükleyin
```

---

## 🎯 6. Örnek Haftalık Plan

### Pazartesi - Cuma (Hafta İçi)

| Saat  | Video | Konu | Durum |
|-------|-------|------|-------|
| 12:00 | Video 1 | Teknoloji Haberi | ✅ Zamanlandı |
| 19:00 | Video 2 | Yapay Zeka | ✅ Zamanlandı |

**Toplam:** 2 video/gün × 5 gün = **10 video/hafta**

### Cumartesi - Pazar (Hafta Sonu)

| Saat  | Video | Konu | Durum |
|-------|-------|------|-------|
| 10:00 | Video 1 | Hafta Özeti | ✅ Zamanlandı |
| 19:00 | Video 2 | Trend Konu | ✅ Zamanlandı |
| 21:00 | Video 3 | Popüler Haber | ✅ Zamanlandı |

**Toplam:** 3 video/gün × 2 gün = **6 video/hafta**

### Haftalık Toplam: **16 video** 📈

---

## 💡 7. En İyi Pratikler

### ✅ Yapılması Gerekenler

1. **İlk 1 hafta unlisted yükleyin**
   - Sistemi test edin
   - Metadata optimizasyonunu gözlemleyin
   - Sorunsuz çalıştığından emin olun

2. **Metadata'yı gözden geçirin**
   - AI-generated başlık/açıklama iyidir ama kontrol edin
   - Gerekirse manuel düzeltin

3. **Scheduler'ı sürekli çalışır durumda tutun**
   - Task Scheduler ile otomatik başlat
   - Hata loglarını kontrol edin

4. **API quota'yı takip edin**
   - Google Cloud Console → Quotas
   - Günlük 10,000 units limitini aşmayın

5. **Yedek alın**
   - `data/youtube_channels.json`
   - `data/upload_queue.json`
   - `data/upload_history.json`

### ❌ Yapılmaması Gerekenler

1. **Aynı anda 10+ video zamanlamayın**
   - Quota problemi olabilir
   - Yavaş yavaş artırın

2. **Test users listesini boş bırakmayın**
   - 403 hatası alırsınız

3. **Private modda bırakmayın**
   - Public/unlisted yapın ki izlensin

4. **Scheduler'sız zamanlamayın**
   - Scheduler servisi çalışmazsa videolar yüklenmez

---

## 🚀 8. Hemen Başlayın!

### Şimdi Yapılacaklar (15 dakika)

```bash
# 1. İlk test yüklemesi
cd python
python youtube/uploader.py "output/[JOB_ID]/final_video.mp4" "Test Video" "Test"

# 2. Scheduler'ı başlat (yeni terminal)
python scheduler/scheduler.py --interval 60

# 3. Web arayüzünden video zamanla
# http://localhost/dashboard.php
# → Tamamlanmış video bul
# → "Zamanla" butonuna tıkla
# → 5 dakika sonrası için zamanla

# 4. Scheduler loglarını izle
# Scheduler terminalinde yüklemeyi göreceksiniz

# 5. YouTube'da kontrol et
# Yükleme başarılı olunca linka tıklayın
```

### İlk Hafta Hedefleri

- ✅ Günde 1-2 video yükle (test amaçlı)
- ✅ Metadata optimizasyonunu gözle
- ✅ Scheduler'ın sorunsuz çalıştığını doğrula
- ✅ Unlisted → Public geçiş yap
- ✅ 2. haftadan itibaren günde 3-4 video

---

## 📞 Sorun mu Yaşıyorsunuz?

### Upload başarısız oluyor?
→ **YOUTUBE_README.md** "Sorun Giderme" bölümüne bakın

### Scheduler çalışmıyor?
```bash
# Scheduler'ın çalışıp çalışmadığını kontrol edin
# Windows: Task Manager → "python.exe" process'ini arayın
# Manuel başlatın: python scheduler/scheduler.py --interval 60
```

### Metadata optimizasyonu çalışmıyor?
```bash
# Gemini API key'inizi kontrol edin
# data/config.json → geminiKey
```

---

## 🎉 Başarılar!

Artık tam otomatik bir YouTube Shorts yayın sisteminiz var! 

**Günlük 2-3 video × 30 gün = 60-90 video/ay** 📈

Sistemi çalıştırın ve izleyin! 🚀
