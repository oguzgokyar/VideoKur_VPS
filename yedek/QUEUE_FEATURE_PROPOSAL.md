# 📋 Kuyruk Sistemi Geliştirme Önerileri

**Tarih:** 2026-03-29  
**Talepler:** 2 yeni özellik  
**Durum:** Analiz Tamamlandı - Onay Bekleniyor

---

## 🎯 Talep 1: Planlanan Paylaşım Zamanı Gösterimi

### Kullanıcı Talebi
> "Planlanan paylaşım tarih saatini, kuyruktaki öğenin önizleme bölümünde görebilelim. Planlanan Paylaşım bilgileri tarih saat belirtsin."

### Mevcut Durum
**Video Kartı Önizleme** (frontend/queues.php lines 1368-1450):
```
┌──────────────────────────────────────┐
│ ⋮⋮ 1  [📹]  Video Başlığı           │
│          [badge] [badge] [badge]    │
└──────────────────────────────────────┘
```

Şu an gösterilen:
- Sıra numarası
- Thumbnail
- Başlık
- İş aşaması badge (producing, done, etc.)
- Platform durum badge'leri (YouTube pending, Instagram published, etc.)
- Canlı/Tamamlandı ikonu

❌ **Eksik:** Planlanan paylaşım tarihi/saati (`scheduled_time`)

### Önerilen Çözüm

**A. Veri Kaynağı**
- `social_queue.json` dosyasında her video için `scheduled_time` alanı mevcut
- Backend API'den bu veri alınabilir
- Format: ISO 8601 (örn: `2026-03-30T15:00:00+02:00`)

**B. UI Yerleşimi - 3 Seçenek:**

#### **Seçenek 1: Badge olarak göster (ÖNERİLEN)**
```
┌──────────────────────────────────────┐
│ ⋮⋮ 1  [📹]  Video Başlığı           │
│          ⏰ Yarın 15:00              │
│          [YouTube] [Instagram]      │
└──────────────────────────────────────┘
```
✅ Az yer kaplar  
✅ Görsel olarak belirgin  
✅ Mevcut badge sistemiyle uyumlu  

#### **Seçenek 2: Başlık altında küçük metin**
```
┌──────────────────────────────────────┐
│ ⋮⋮ 1  [📹]  Video Başlığı           │
│          Planlanan: Yarın 15:00     │
│          [YouTube] [Instagram]      │
└──────────────────────────────────────┘
```
✅ Daha açıklayıcı  
⚠️ Biraz daha fazla yer kaplar  

#### **Seçenek 3: Sağ üst köşede tarih**
```
┌──────────────────────────────────────┐
│ ⋮⋮ 1  [📹]  Video Başlığı   📅 15:00│
│          [YouTube] [Instagram]      │
└──────────────────────────────────────┘
```
✅ Kompakt  
⚠️ Küçük ekranlarda sıkışabilir  

### Önerilen Kod Değişiklikleri

**1. Frontend: Tarih formatlama fonksiyonu ekle**
```javascript
// queuesApp() içine ekle
formatScheduledTime(scheduledTime) {
    if (!scheduledTime) return '';
    
    const scheduled = new Date(scheduledTime);
    const now = new Date();
    const diffMs = scheduled - now;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMins / 60);
    const diffDays = Math.floor(diffHours / 24);
    
    // Geçmişte ise
    if (diffMs < 0) {
        return '⚠️ Geçti';
    }
    
    // Yakın zamanda
    if (diffMins < 60) {
        return `${diffMins} dk sonra`;
    }
    
    if (diffHours < 24) {
        const mins = diffMins % 60;
        return `${diffHours} saat ${mins} dk`;
    }
    
    // Bugün/Yarın kontrolü
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const scheduledDate = new Date(scheduled.getFullYear(), scheduled.getMonth(), scheduled.getDate());
    const dayDiff = Math.floor((scheduledDate - today) / 86400000);
    
    const timeStr = scheduled.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
    
    if (dayDiff === 0) return `Bugün ${timeStr}`;
    if (dayDiff === 1) return `Yarın ${timeStr}`;
    if (dayDiff < 7) return `${dayDiff} gün sonra ${timeStr}`;
    
    // Tam tarih
    return scheduled.toLocaleDateString('tr-TR', { 
        day: 'numeric', 
        month: 'short', 
        hour: '2-digit', 
        minute: '2-digit' 
    });
},

isScheduledSoon(scheduledTime) {
    if (!scheduledTime) return false;
    const diffMs = new Date(scheduledTime) - new Date();
    return diffMs > 0 && diffMs < 3600000; // 1 saat içinde
},

isScheduledOverdue(scheduledTime) {
    if (!scheduledTime) return false;
    return new Date(scheduledTime) < new Date();
}
```

**2. Frontend: Video kartında göster (Seçenek 1 - Badge)**

Line 1436'dan sonra ekle:
```html
<!-- Planlanan paylaşım zamanı -->
<div x-show="video.scheduled_time" class="inline-flex items-center gap-0.5 platform-badge"
     :class="{
       'badge-uploading badge-live': isScheduledSoon(video.scheduled_time),
       'badge-failed': isScheduledOverdue(video.scheduled_time),
       'badge-queued': !isScheduledSoon(video.scheduled_time) && !isScheduledOverdue(video.scheduled_time)
     }">
  <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
  </svg>
  <span x-text="formatScheduledTime(video.scheduled_time)"></span>
</div>
```

**3. Backend: API'den scheduled_time döndür**

`api/queues.php` içinde `get_detail` action'ında, videoları döndürürken `social_queue.json`'dan scheduled_time'ı al ve ekle.

Mevcut kod (line ~800-850):
```php
// Her video için social_queue.json'dan scheduled_time al
$socialQueue = @file_get_contents($dataDir . '/social_queue.json');
$socialQueueData = $socialQueue ? json_decode($socialQueue, true) : ['queue' => []];

foreach ($queue['videos'] as &$video) {
    // social_queue'dan scheduled_time bul
    foreach ($socialQueueData['queue'] as $sqItem) {
        if ($sqItem['job_id'] === $video['job_id']) {
            $video['scheduled_time'] = $sqItem['scheduled_time'] ?? null;
            break;
        }
    }
}
```

---

## 🔄 Talep 2: Reset & Yeniden Planlama

### Kullanıcı Talebi
> "Reset & Başlat butonu, kuyruktaki öğelerin paylaşım zamanlarını Kuyruk ayarlarında belirtilen şekilde güncellesin."

### Kullanım Senaryosu
1. Kullanıcı 20 video ekler kuyruğa
2. Kuyruk ayarları: 15:00 başla, 30dk aralık, günde 2 limit
3. İlk scheduled_time hesaplaması yapılır
4. Kullanıcı ayarları değiştirir: 18:00 başla, 1 saat aralık, günde 3 limit
5. **ŞU AN:** Eski videolar eski zamanlarla kalır
6. **TALEP:** "Reset & Başlat" ile tüm videoların scheduled_time'ı yeniden hesaplansın

### Mevcut Kontrol Butonları
Frontend/queues.php line ~1336:
```html
<button @click="pauseQueue(selectedQueue)">⏸ Duraklat</button>
<button @click="resumeQueue(selectedQueue)">▶ Başlat</button>
```

### Önerilen Çözüm

**A. Buton Yerleşimi**

Pause/Resume butonlarının yanına ekle:
```
[⏸ Duraklat] [▶ Başlat] [🔄 Reset & Başlat]
```

**B. Fonksiyon Akışı**
```
1. Kullanıcı "Reset & Başlat" butonuna tıklar
2. Onay sorusu: "Tüm videoların planlaması sıfırlanacak. Devam?"
3. Backend'e POST isteği gönder:
   - action: 'reset_and_reschedule'
   - queue_id: selectedQueue.id
4. Backend:
   a. social_queue.json'daki bu kuyruğa ait tüm videoları bul
   b. Kuyruk ayarlarını (startTime, interval, dailyLimit) oku
   c. Her video için position'a göre scheduled_time'ı yeniden hesapla
   d. social_queue.json'ı güncelle
   e. Kuyruğu aktif yap (is_active = true)
5. Frontend yenile
6. Başarı mesajı: "✅ Kuyruk sıfırlandı, 20 video yeniden planlandı"
```

**C. Backend API Endpoint**

`api/queues.php` içine yeni action ekle:

```php
case 'reset_and_reschedule':
    $queueId = $input['queue_id'] ?? '';
    if (empty($queueId)) {
        echo json_encode(['error' => 'Queue ID gerekli']);
        exit;
    }
    
    $data = loadQueues();
    $queue = null;
    foreach ($data['queues'] as &$q) {
        if ($q['id'] === $queueId) {
            $queue = &$q;
            break;
        }
    }
    
    if (!$queue) {
        echo json_encode(['error' => 'Kuyruk bulunamadı']);
        exit;
    }
    
    // social_queue.json'ı yükle
    $socialQueueFile = $dataDir . '/social_queue.json';
    if (!file_exists($socialQueueFile)) {
        echo json_encode(['error' => 'Social queue bulunamadı']);
        exit;
    }
    
    $socialQueue = json_decode(file_get_contents($socialQueueFile), true);
    $updatedCount = 0;
    
    // Kuyruktaki her video için scheduled_time yeniden hesapla
    foreach ($queue['videos'] as $video) {
        $position = $video['position'] ?? 0;
        $jobId = $video['job_id'];
        
        // social_queue'da bul ve güncelle
        foreach ($socialQueue['queue'] as &$sqItem) {
            if ($sqItem['job_id'] === $jobId) {
                // Yeni scheduled_time hesapla
                $platforms = $sqItem['platforms'] ?? ['youtube'];
                $newScheduledTime = calculateScheduledTime($queue, $position, $platforms);
                
                $sqItem['scheduled_time'] = $newScheduledTime;
                $sqItem['status'] = 'pending'; // Durumu pending yap
                
                // Her platform için durumu pending yap
                foreach ($sqItem['platform_status'] as $platform => &$ps) {
                    if (is_array($ps)) {
                        $ps['status'] = 'pending';
                    }
                }
                
                $updatedCount++;
                break;
            }
        }
    }
    
    // social_queue.json'ı kaydet
    file_put_contents($socialQueueFile, json_encode($socialQueue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Kuyruğu aktif yap
    $queue['is_active'] = true;
    $queue['resumed_at'] = date('c');
    saveQueues($data);
    
    echo json_encode([
        'success' => true,
        'message' => "$updatedCount video yeniden planlandı",
        'updated_count' => $updatedCount
    ]);
    break;
```

**D. Frontend Fonksiyonu**

```javascript
async resetAndReschedule(queue) {
    const confirmed = confirm(
        `Kuyruk sıfırlanacak ve tüm videolar (${queue.videos?.length || 0}) yeniden planlanacak.\n\n` +
        `Mevcut paylaşım zamanları silinecek ve kuyruk ayarlarına göre yeniden hesaplanacak.\n\n` +
        `Devam edilsin mi?`
    );
    
    if (!confirmed) return;
    
    try {
        const response = await fetch('/api/queues.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'reset_and_reschedule',
                queue_id: queue.id
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(`✅ ${result.message}\n\nKuyruk yeniden başlatıldı.`);
            await this.loadQueues();
            await this.selectQueueForDetail({ id: queue.id });
        } else {
            alert('❌ Hata: ' + (result.error || 'Bilinmeyen hata'));
        }
    } catch (e) {
        console.error('Reset hatası:', e);
        alert('❌ Hata: ' + e.message);
    }
}
```

**E. Frontend Butonu**

Line ~1336 civarına ekle:
```html
<!-- Reset & Reschedule Button -->
<button 
  @click="resetAndReschedule(selectedQueue)"
  class="flex items-center gap-2 px-3 py-1.5 text-sm font-medium rounded-lg transition text-purple-600 dark:text-purple-400 hover:bg-purple-50 dark:hover:bg-purple-900/30"
  title="Tüm videoların paylaşım zamanlarını yeniden hesapla"
>
  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
  </svg>
  <span x-text="selectedQueue?.is_active ? 'Reset & Başlat' : 'Yeniden Planla'"></span>
</button>
```

---

## 📊 Özet Değerlendirme

### Özellik 1: Planlanan Paylaşım Zamanı

| Kriter | Değerlendirme |
|--------|---------------|
| **Zorluk** | ⭐⭐ Kolay |
| **Süre** | ~30 dakika |
| **Dosya Sayısı** | 2 dosya (frontend/queues.php, api/queues.php) |
| **Satır** | ~60 satır eklenecek |
| **Risk** | Düşük - sadece gösterim |

**Önerilen Seçenek:** Seçenek 1 (Badge)  
**Renk Kodlaması:**  
- 🟢 1+ saat sonra: Mavi badge (normal)
- 🟡 1 saat içinde: Amber badge (yanıp sönen - dikkat!)
- 🔴 Geçmiş: Kırmızı badge (gecikmiş)

### Özellik 2: Reset & Yeniden Planlama

| Kriter | Değerlendirme |
|--------|---------------|
| **Zorluk** | ⭐⭐⭐ Orta |
| **Süre** | ~1 saat |
| **Dosya Sayısı** | 2 dosya (frontend/queues.php, api/queues.php) |
| **Satır** | ~120 satır eklenecek |
| **Risk** | Orta - veri manipülasyonu var |

**Güvenlik Kontrolleri:**
- ✅ Onay sorusu (2 aşamalı)
- ✅ Sadece pending videoları etkiler (published olanlar dokunulmaz)
- ✅ Backup yok ama social_queue.json versiyonlanabilir
- ⚠️ Scheduler aktifse yarış durumu olabilir (queue pause önerilir)

---

## 🎯 Uygulama Planı

### Önerilen Sıra

**Faz 1: Planlanan Zaman Gösterimi** (önce)
- Basit ve risk-free
- Kullanıcı görebilir, problemleri tespit edebilir
- Reset özelliği için zemin hazırlar

**Faz 2: Reset & Yeniden Planlama** (sonra)
- Faz 1 test edildikten sonra
- Planlanan zamanların görünür olması ile test daha kolay

### Alternatif İyileştirmeler

**Bonus 1: Toplu Zamanlama Düzenleme**
- Video kartında "Zamanı Düzenle" butonu
- Modal ile manuel tarih/saat seçimi
- Tek video için custom zamanlama

**Bonus 2: Zamanlama Görselleştirme**
- Timeline görünümü (zaman çizelgesi)
- Günlük/haftalık takvim
- Drag & drop ile zamanlama

**Bonus 3: Akıllı Planlama**
- "En iyi zamanları öner" butonu
- Platform algoritmalarına göre optimal saatler
- Hedef kitle analizi ile zamanlama

---

## ✅ Onay Soruları

1. **Planlanan Zaman Gösterimi** için hangi seçeneği tercih ediyorsunuz?
   - [ ] Seçenek 1: Badge (ÖNERİLEN)
   - [ ] Seçenek 2: Başlık altında metin
   - [ ] Seçenek 3: Sağ üst köşe

2. **Renk kodlaması** nasıl olsun?
   - [ ] Zamana göre (yakın = amber, uzak = mavi)
   - [ ] Tek renk (hep mavi)
   - [ ] Custom önerin

3. **Reset butonu** nerede olsun?
   - [ ] Pause/Resume yanında (ÖNERİLEN)
   - [ ] Kuyruk ayarları modalında
   - [ ] Dropdown menü içinde

4. **Reset butonu** ne yapsın?
   - [ ] Tüm videoları yeniden planla + kuyruğu aktif yap (ÖNERİLEN)
   - [ ] Sadece yeniden planla (kullanıcı manuel başlatsın)
   - [ ] Published videoları dokunma, sadece pending'leri yeniden planla

5. **Ek özellikler** istiyor musunuz?
   - [ ] Bonus 1: Tek video için manuel zamanlama
   - [ ] Bonus 2: Timeline görselleştirme
   - [ ] Bonus 3: Akıllı zamanlama önerileri
   - [ ] Hayır, sadece talep ettiğim 2 özellik

---

**Onayınızla birlikte kodlamaya başlayabilirim! 🚀**
