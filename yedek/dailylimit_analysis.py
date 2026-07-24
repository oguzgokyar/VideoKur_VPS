"""
dailyLimit Configuration Flow Analysis
Shows where dailyLimit values are set and how they work
"""

print("=" * 70)
print("dailyLimit DEĞER AKIŞ ANALİZİ")
print("=" * 70)

print("""

📍 1. VARSAYILAN DEĞERLER (DEFAULT)
════════════════════════════════════════════════════════════════════

📂 Dosya: frontend/queues.php (satır 80-110)

JavaScript Alpine.js form data:

    form: {
        dailyLimit: 0,  // ← QUEUE seviyesi (deprecated)
        
        platformSettings: {
            youtube: {
                dailyLimit: 0,  // ← PLATFORM seviyesi (kullanılıyor)
                ...
            }
        }
    }

✅ Varsayılan: 0 (Limitsiz)


📍 2. KULLANICI AYARLAMA (UI)
════════════════════════════════════════════════════════════════════

📂 Dosya: frontend/queues.php (satır 2090-2115)

HTML UI Component:

    <label>📊 Günlük Limit</label>
    
    <!-- Input field (manuel sayı girişi) -->
    <input 
        type="number"
        x-model="form.platformSettings.youtube.dailyLimit"
        min="0"
        placeholder="0"
    />
    
    <!-- Dropdown (hızlı seçim) -->
    <select x-model="form.platformSettings.youtube.dailyLimit">
        <option value="0">Limitsiz</option>
        <option value="3">3 video/gün</option>
        <option value="4">4 video/gün</option>
        <option value="5">5 video/gün</option>
        <option value="6">6 video/gün</option>
        <option value="8">8 video/gün</option>
        <option value="10">10 video/gün</option>
    </select>

📍 Nerede: Frontend → Queues sayfası → Platform Settings → YouTube

🎯 Kullanıcı:
   1. Queue oluştururken VEYA
   2. Mevcut queue'yu düzenlerken
   → dailyLimit değerini seçer


📍 3. API'YE GÖNDERİLME
════════════════════════════════════════════════════════════════════

📂 Dosya: api/queues.php

POST /api/queues.php

    {
        "action": "create" veya "update",
        "platform_settings": {
            "youtube": {
                "dailyLimit": 2,  // ← Kullanıcının seçtiği değer
                "enabled": true,
                "scheduleType": "now"
            }
        }
    }


📍 4. KAYIT (queues.json)
════════════════════════════════════════════════════════════════════

📂 Dosya: data/queues.json

Yapı:

    {
        "queues": [
            {
                "id": "youtube-testr-8737ec",
                "name": "Youtube",
                "platform_settings": {
                    "youtube": {
                        "dailyLimit": "2",  // ← String olarak saklanıyor
                        "enabled": true,
                        "scheduleType": "now"
                    }
                }
            }
        ]
    }

⚠️  NOT: String olarak saklanıyor ama integer olarak kullanılıyor


📍 5. KULLANIM (Scheduler)
════════════════════════════════════════════════════════════════════

📂 Dosya: python/scheduler/social_scheduler.py (satır 283-319)

Scheduler process_queue() metodunda:

    1. Queue'dan platform_settings oku:
       platform_settings = queue.get('platform_settings', {})
       
    2. YouTube için dailyLimit al:
       settings = platform_settings.get('youtube', {})
       dailyLimit = int(settings.get('dailyLimit', 0))
       
    3. Günlük yükleme sayısını kontrol et:
       today_uploads = _count_today_uploads(queue_id)
       
    4. Karşılaştır:
       if today_uploads >= dailyLimit:
           # Limit doldu - videoyu atla veya reschedule et
           return False
       else:
           # Upload'a izin ver
           return True


📍 6. SAYIM MEKANİZMASI
════════════════════════════════════════════════════════════════════

📂 Dosya: python/scheduler/social_scheduler.py (satır 414-457)

_count_today_uploads() metodu:

    1. social_queue.json dosyasını oku
    
    2. Bugün için filtrele:
       - status == 'success'
       - completed_at == bugün (UTC)
       - queue_id eşleşiyor
    
    3. Sayıyı döndür

📊 ÖRNEK:
   dailyLimit = 2
   today_uploads = 0
   
   → İlk video: ✅ Upload (0 < 2)
   → İkinci video: ✅ Upload (1 < 2)
   → Üçüncü video: ❌ Atla (2 >= 2)


📍 7. MEVCUT DURUM ANALİZİ
════════════════════════════════════════════════════════════════════

🔍 Şu anki değer: dailyLimit = 2

📊 Bugünkü durum:
   - today_uploads = 0 (success yok)
   - Ama yine de çalışmıyor

❓ NEDEN?
   1. YouTube API quota gerçekten dolmuş
   2. Videolar quota hatası yüzünden 'success' olamadı
   3. Dolayısıyla today_uploads hala 0
   4. Ama limit kontrolü 2'de durmuş olabilir (logic bug?)


📍 8. NASIL DEĞİŞTİRİLİR?
════════════════════════════════════════════════════════════════════

YÖNTEM 1: Frontend UI (ÖNERİLEN)
─────────────────────────────────
1. Tarayıcıda: http://localhost/frontend/queues.php
2. Queue'yu bul: "Youtube"
3. Edit butonuna tıkla
4. Platform Settings → YouTube → Daily Limit
5. Değeri değiştir: 2 → 50 (veya 0 = limitsiz)
6. Save butonuna tıkla


YÖNTEM 2: Manuel JSON Düzenleme
─────────────────────────────────
1. Dosya: data/queues.json
2. Bul: "dailyLimit": "2"
3. Değiştir: "dailyLimit": "50"
4. Kaydet


YÖNTEM 3: API Call (Programmatik)
─────────────────────────────────
POST /api/queues.php

{
    "action": "update",
    "queue_id": "youtube-testr-8737ec",
    "updates": {
        "platform_settings": {
            "youtube": {
                "dailyLimit": 50
            }
        }
    }
}


📍 9. ÖNERİLEN DEĞERLER
════════════════════════════════════════════════════════════════════

Platform              Önerilen Limit   Neden
─────────────────────────────────────────────────────────────────────
YouTube Shorts        6-10/gün        Algoritma için ideal
Instagram Reels       8-12/gün        Yüksek engagement
TikTok                10-15/gün       Viral potansiyel yüksek
Facebook              3-5/gün         Organik reach için

🎯 SENIN DURUMUN:
   • YouTube için: 50/gün (test için)
   • Veya: 0 (limitsiz - API quota zaten kontrol ediyor)


📍 10. SORUN GİDERME
════════════════════════════════════════════════════════════════════

❌ SORUN: dailyLimit = 2 çok düşük
✅ ÇÖZÜM: 50 veya 0 (limitsiz) yap

❌ SORUN: Değer değişmiyor
✅ ÇÖZÜM: 
   1. Browser cache temizle
   2. Scheduler'ı restart et
   3. queues.json'ı kontrol et

❌ SORUN: Hala çalışmıyor
✅ ÇÖZÜM:
   1. YouTube API quota'yı kontrol et (Google Cloud Console)
   2. scheduler_errors.json'ı incele
   3. diagnose_queue.py çalıştır

""")

print("=" * 70)
print("ÖZET")
print("=" * 70)

print("""
📋 AKIŞ:
   Frontend UI → API (queues.php) → queues.json → Scheduler → Upload

🔧 AYAR YERİ:
   frontend/queues.php → Platform Settings → YouTube → Daily Limit

💾 SAKLAMA:
   data/queues.json → platform_settings.youtube.dailyLimit

🎯 KULLANIM:
   python/scheduler/social_scheduler.py → _check_daily_limit()

📊 MEVCUT:
   dailyLimit = 2 (çok düşük!)

✅ ÖNERİ:
   dailyLimit = 50 veya 0 (limitsiz)
""")
