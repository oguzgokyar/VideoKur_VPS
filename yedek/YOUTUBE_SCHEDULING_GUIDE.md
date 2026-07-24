# 📅 YouTube Shorts Zamanlanmış Paylaşım - Kullanım Kılavuzu

## 🎯 Yeni Özellikler

Bu güncellemede YouTube Shorts için 3 yeni zamanlama özelliği eklendi:

### 1. **İlk Paylaşım Saati (start_time)**
Kuyruktaki ilk videonun ne zaman paylaşılacağını belirler.

**Kullanım:**
- Boş bırakırsanız: Video hemen paylaşılır
- Sadece saat: Örn: `09:00` → Bugün saat 09:00'da
- Tam tarih-saat: Örn: `2026-03-29 20:00` → Belirtilen tarih ve saatte

### 2. **Paylaşım Aralığı - Dakika Bazlı (interval_minutes)**
Videolar arası bekleme süresini dakika cinsinden ayarlar.

**Örnekler:**
- `30` → Her 30 dakikada bir
- `90` → Her 1.5 saatte bir (90 dakika)
- `120` → Her 2 saatte bir
- `180` → Her 3 saatte bir
- `1440` → Günde bir (24 saat)

### 3. **Günlük Paylaşım Limiti (daily_limit)**
Günde maksimum kaç video paylaşılacağını belirler.

**Kullanım:**
- `0` → Limitsiz (tüm videolar paylaşılır)
- `3` → Günde 3 video
- `5` → Günde 5 video
- `10` → Günde 10 video

---

## 📋 Kullanım Senaryoları

### Senaryo 1: Sabah Başla, Her 2 Saatte 1, Max 4 Video/Gün

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

**Sonuç:**
- 1. Video: 09:00
- 2. Video: 11:00
- 3. Video: 13:00
- 4. Video: 15:00
- Ertesi gün tekrar 09:00'dan başlar

---

### Senaryo 2: Hemen Başla, Her 90 Dakikada 1, Limitsiz

```json
{
  "schedule": {
    "type": "interval",
    "interval_minutes": 90,
    "daily_limit": 0,
    "timezone": "Europe/Istanbul"
  }
}
```

**Sonuç:**
- Videolar 90 dakika arayla kesintisiz paylaşılır
- Günlük limit yok

---

### Senaryo 3: Akşam Başla, Her 3 Saatte 1, Max 6 Video/Gün

```json
{
  "schedule": {
    "type": "interval",
    "start_time": "18:00",
    "interval_minutes": 180,
    "daily_limit": 6,
    "timezone": "Europe/Istanbul"
  }
}
```

**Sonuç:**
- 1. Video: 18:00
- 2. Video: 21:00
- 3. Video: 00:00 (ertesi gün)
- 4. Video: 03:00
- 5. Video: 06:00
- 6. Video: 09:00
- Ertesi gün 18:00'den devam

---

## 🎨 Web Arayüzünden Kullanım

1. **Dashboard'a Git** → `http://localhost/frontend/queues.php`

2. **Yeni Kuyruk Oluştur** veya **Mevcut Kuyruğu Düzenle**

3. **Zamanlama Ayarları** bölümünde:
   - **Zamanlama Tipi:** "Aralıklı" seçin
   - **📅 İlk Paylaşım Saati:** Başlangıç saatini girin (opsiyonel)
   - **⏱️ Paylaşım Aralığı:** Dakika cinsinden aralık
   - **📊 Günlük Limit:** Günde kaç video (0 = limitsiz)

4. **Kaydet**

---

## 📊 YouTube Shorts İçin Önerilen Ayarlar

### Yeni Kanal (0-1000 Abone)
```
İlk Paylaşım: 09:00
Aralık: 180 dakika (3 saat)
Günlük Limit: 3-4 video
```
**Neden?** YouTube algoritması yeni kanalları test eder. Fazla paylaşım spam olarak algılanabilir.

---

### Orta Seviye Kanal (1K-10K Abone)
```
İlk Paylaşım: 08:00
Aralık: 120 dakika (2 saat)
Günlük Limit: 4-6 video
```
**Neden?** İzleyici tabanı var, daha sık paylaşım engagement'ı artırır.

---

### Büyük Kanal (10K+ Abone)
```
İlk Paylaşım: 07:00
Aralık: 90 dakika (1.5 saat)
Günlük Limit: 6-8 video
```
**Neden?** Güçlü kanal, algoritma daha fazla içeriği destekler.

---

### Agresif Büyüme Stratejisi
```
İlk Paylaşım: 06:00
Aralık: 60 dakika (1 saat)
Günlük Limit: 8-12 video
```
**Uyarı:** Sadece kaliteli içerikle kullanın! Spam riski var.

---

## 🕐 En İyi Paylaşım Saatleri (Türkiye)

### Hafta İçi:
- **Sabah:** 07:00 - 09:00 (işe giderken)
- **Öğle:** 12:00 - 14:00 (öğle arası)
- **Akşam:** 17:00 - 20:00 (eve dönerken, akşam) ⭐ **En İyi**
- **Gece:** 21:00 - 23:00

### Hafta Sonu:
- **Sabah:** 09:00 - 11:00
- **Öğle:** 12:00 - 15:00
- **Akşam:** 18:00 - 22:00 ⭐ **En İyi**

---

## ⚙️ Teknik Detaylar

### Nasıl Çalışır?

1. **Production Scheduler** (Python):
   - Video üretimi tamamlandığında çalışır
   - `start_time` ve `interval_minutes` kullanarak sonraki video zamanını hesaplar
   - Zamanı `social_queue.json`'a ekler

2. **Social Scheduler** (Python):
   - Her dakika `social_queue.json` kontrol eder
   - Zamanı gelen videoları işler
   - `daily_limit` kontrolü yapar
   - Günlük limitler doluysa videoları ertesi güne atar

3. **API & Frontend** (PHP & JavaScript):
   - Kullanıcı ayarlarını `queues.json`'a kaydeder
   - Gerçek zamanlı kuyruk durumunu gösterir

---

## 🧪 Test Etme

Test scripti ile ayarları test edebilirsiniz:

```bash
python test_new_scheduling.py
```

Bu test şunları doğrular:
- ✅ İlk paylaşım saati doğru çalışıyor
- ✅ Dakika bazlı aralık hesaplanıyor
- ✅ Günlük limit yapısı hazır

---

## 🐛 Sorun Giderme

### Problem: Videolar zamanında paylaşılmıyor
**Çözüm:** Social scheduler çalışıyor mu kontrol edin:
```bash
# Windows
start_social_scheduler.bat

# Durum kontrolü
python python/scheduler/social_scheduler.py
```

### Problem: Günlük limit aşılıyor
**Çözüm:** `social_queue.json` dosyasında `completed_at` tarihlerini kontrol edin. Sistem UTC zamanı kullanır.

### Problem: İlk paylaşım saati geçmişte kalıyor
**Çözüm:** `start_time` sadece ilk video için kullanılır. Kuyruk durduysa ve tekrar başlıyorsa, `last_publish` alanını temizleyin.

---

## 📝 Örnek Queue Konfigürasyonu

```json
{
  "id": "youtube-shorts-8h23d",
  "name": "YouTube Shorts - Prime Time",
  "platforms": ["youtube"],
  "is_active": true,
  "schedule": {
    "type": "interval",
    "start_time": "09:00",
    "interval_minutes": 120,
    "daily_limit": 5,
    "timezone": "Europe/Istanbul"
  },
  "platform_settings": {
    "youtube": {
      "enabled": true,
      "privacy": "public",
      "categoryId": "22",
      "titleTemplate": "{title}",
      "descriptionTemplate": "{description}\n\n#shorts",
      "tagsTemplate": "shorts,viral,trending"
    }
  }
}
```

---

## 🚀 Hızlı Başlangıç

1. Dashboard'a git: `http://localhost/frontend/queues.php`
2. "Yeni Kuyruk Oluştur"
3. İsim ver: "YouTube Prime Time"
4. Platform seç: YouTube
5. Zamanlama ayarla:
   - İlk paylaşım: `17:00`
   - Aralık: `120` dakika
   - Günlük limit: `4`
6. Kaydet!

Artık videolarınız her gün 17:00'den başlayarak 2 saatte bir paylaşılacak (17:00, 19:00, 21:00, 23:00).

---

## 💡 İpuçları

- **Tutarlı olun:** Her gün aynı saatlerde paylaşın
- **Prime time kullanın:** 17:00-20:00 arası en yüksek izlenme
- **Günlük limiti aşmayın:** 8-10 video/gün idealdir
- **İçerik kalitesine odaklanın:** Miktar değil, kalite!
- **Analytics takip edin:** Hangi saatler daha iyi çalışıyor?

---

**Not:** Bu özellikler sadece `type: "interval"` modunda çalışır. `"specific"` (belirli saatler) veya `"now"` (hemen) modlarında farklı ayarlar kullanılır.
