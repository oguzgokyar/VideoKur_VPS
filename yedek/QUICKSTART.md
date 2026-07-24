# 🎬 Hızlı Başlangıç Kılavuzu

## ✅ Şu Anda Yapılacaklar

### 1️⃣ İlk Test Yüklemesi (ŞİMDİ)

```bash
# Çift tıklayın:
test_upload.bat
```

**Ne olacak:**
- Metadata optimize edilecek
- Video YouTube'a yüklenecek (UNLISTED)
- Video linki verilecek
- YouTube'da kontrol edebileceksiniz

**Beklenen süre:** 2-5 dakika

---

### 2️⃣ Scheduler'ı Başlatın (Test yüklemesi başarılıysa)

**Yeni bir Command Prompt açın:**

```bash
# Çift tıklayın:
start_scheduler.bat
```

**Ne olacak:**
- Arka planda sürekli çalışacak
- Her dakika kuyruğu kontrol edecek
- Zamanı gelen videoları otomatik yükleyecek

**Bu pencereyi açık bırakın!** Kapatırsanız scheduler durur.

---

### 3️⃣ İlk Zamanlamayı Yapın

1. **Tarayıcıda aç:** http://localhost/dashboard.php

2. **Tamamlanmış bir video** bulun (yeşil "✅ Tamamlandı" yazısı olan)

3. İki seçenek:

   **A) Hemen Yükle ⚡**
   - "⚡ Hemen Yükle" butonuna tıklayın
   - Metadata gözden geçirin
   - "Yükle" butonuna tıklayın
   - 2-5 dakika bekleyin
   - "🔗 YouTube'da Aç" linki belirecek

   **B) Zamanla 📅**
   - "📅 Zamanla" butonuna tıklayın
   - **5 dakika sonrası** için zamanlayın
   - Metadata gözden geçirin
   - "Zamanla" butonuna tıklayın
   - Scheduler otomatik yükleyecek

---

## 🔍 Durum Kontrolü

### Scheduler Çalışıyor mu?

```bash
# Task Manager açın (Ctrl+Shift+Esc)
# "Details" sekmesine gidin
# "python.exe" process'ini arayın
# Varsa çalışıyor demektir ✓
```

### Kuyruğu Görüntüle

http://localhost/queues.php

- **Kuyruk Yönetimi:** Bekleyen yüklemeler ve paylaşımlar
- **Platform Durumu:** YouTube, Instagram, TikTok, Facebook
- **Zamanlama Ayarları:** Tarih ve saat ayarları

---

## 📊 Mevcut Videolar

Yüklenmeye hazır **5 video** var:

1. ✅ job_69b9e56b244608.61484112 - Yapay Zeka (39 saniye)
2. ✅ job_69b9dd508f4cc8.47654028
3. ✅ job_69b9d807f1d232.08915877
4. ✅ job_69b9a187819ff3.24253636
5. ✅ job_69ba99a1a0b239.72027867

---

## ⚠️ Önemli Notlar

### İlk Test
- **UNLISTED** modda yükleyin
- YouTube'da kontrol edin
- Sorunsuzsa devam edin

### Günlük Limit
- YouTube API: **Günde ~6 upload**
- Quota aşarsanız 24 saat bekleyin

### Scheduler
- **Sürekli çalışır durumda** olmalı
- Bilgisayar kapatılırsa durur
- Task Scheduler ile otomatik başlatabilirsiniz

---

## 🚀 Başarılar!

**Şimdi `test_upload.bat` dosyasına çift tıklayın!** 🎉
