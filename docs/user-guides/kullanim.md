# YouTube Shorts Zamanlama - Hızlı Başlangıç Rehberi

## 🚀 3 Adımda Kullanmaya Başlayın

### ADIM 1: Kuyruk Sayfasını Açın
```
http://localhost:8000/queues.php
```

### ADIM 2: Mevcut Kuyruğu Düzenleyin veya Yeni Oluşturun

#### Mevcut Kuyruk İçin:
1. Mevcut "Youtube" kuyruğunuzun üzerine tıklayın
2. ⚙️ Ayarlar ikonuna tıklayın
3. "Zamanlama Ayarları" bölümünü bulun

#### Yeni Kuyruk İçin:
1. "➕ Yeni Kuyruk" butonuna tıklayın
2. İsim verin (örn: "YouTube Prime Time")
3. YouTube platformunu seçin

### ADIM 3: Yeni Özellikleri Kullanın!

Şimdi göreceksiniz:

```
┌─────────────────────────────────────────────┐
│  Zamanlama Tipi                             │
│  ○ Hemen   ● Aralıklı   ○ Belirli Saatler  │
└─────────────────────────────────────────────┘

┌─────────────────────────────────────────────┐
│  📅 İlk Paylaşım Saati (opsiyonel)         │
│  [___________] ← datetime picker           │
│  Örn: 2026-03-28T09:00                      │
└─────────────────────────────────────────────┘

┌─────────────────────────────────────────────┐
│  ⏱️ Paylaşım Aralığı (dakika)              │
│  [120____] [dropdown: Her 2 saat ▼]        │
└─────────────────────────────────────────────┘

┌─────────────────────────────────────────────┐
│  📊 Günlük Paylaşım Limiti                  │
│  [4______] [dropdown: Günde 4 video ▼]     │
└─────────────────────────────────────────────┘
```

---

## 📝 Örnek Kullanım

### Senaryo: Sabahları Başla, Günde 4 Video

1. **İlk Paylaşım Saati:** `09:00` yazın (veya picker'dan seçin)
2. **Paylaşım Aralığı:** Dropdown'dan `120` dakika seçin
3. **Günlük Limit:** Dropdown'dan `Günde 4 video` seçin
4. **Kaydet** butonuna tıklayın

**Sonuç:**
- Her gün 09:00'da başlar
- 2 saat arayla 4 video paylaşılır
- Saatler: 09:00, 11:00, 13:00, 15:00
- Ertesi gün tekrar 09:00'dan devam

---

## 🖼️ UI'da Görecekleriniz

### Kuyruk Listesinde:
```
📺 Youtube
   ⏰ Her 120 dk (2.0 saat) | Max 4/gün | Başlangıç: 09:00
   ✅ Aktif | 12 video
```

### Modal'da (Ayarlar):
- ✅ Datetime picker (İlk paylaşım saati)
- ✅ Number input (Dakika aralığı)
- ✅ Dropdown (Hızlı seçenekler: 30dk, 60dk, 90dk...)
- ✅ Number input (Günlük limit)
- ✅ Dropdown (Hızlı: 0, 1, 2, 3, 4, 5, 6, 8, 10)

---

## 🔍 Gerçek Zamanlı Kontrol

Ayarları yaptıktan sonra:

1. `data/queues.json` dosyasını açın
2. Kuyruğunuzun schedule bölümünü kontrol edin:

```json
{
  "schedule": {
    "type": "interval",
    "start_time": "09:00",
    "interval_minutes": 120,
    "daily_limit": 4,
    "timezone": "Europe/Istanbul"
  }
}
```

✅ Görmüyorsanız → Kaydet butonuna bastınız mı?
✅ Görüyorsanız → Sistem hazır!

---

## ⚡ Hemen Test Edin

```bash
# Scheduler'ı başlat
start_social_scheduler.bat
```

Scheduler loglarında hata yoksa sistem hazırdır.

---

## 🆘 Sorun mu var?

**Sorun 1:** Yeni alanları göremiyorum
- **Çözüm:** Sayfayı yenileyin (Ctrl+F5)
- **Çözüm:** Tarayıcı cache'ini temizleyin

**Sorun 2:** Kaydettiğimde hata veriyor
- **Çözüm:** Browser console'u açın (F12), hata mesajını kontrol edin
- **Çözüm:** `data/queues.json` dosya izinlerini kontrol edin

**Sorun 3:** Scheduler çalışmıyor
- **Çözüm:** Social scheduler'ı başlatın:
  ```bash
  python python/scheduler/social_scheduler.py
  ```

---

## 🎯 Özet

1. ✅ Sistem mevcut kodunuza entegre
2. ✅ Web arayüzü güncel
3. ✅ API otomatik çalışıyor
4. ✅ Yeni UI alanları hazır
5. ✅ Testler başarılı

**Sadece web arayüzüne gidin ve kullanmaya başlayın! 🚀**
