"""
Queue System - Detailed Analysis Report
Answers all 4 questions about queue system
"""

print("=" * 70)
print("QUEUE SYSTEM - DETAYLI ANALİZ RAPORU")
print("=" * 70)

print("\n" + "=" * 70)
print("SORU 1: Kuyruk şuan neden çalışmıyor?")
print("=" * 70)

print("""
✅ TESPIT EDİLEN DURUM:
   • 25 video "pending" durumda bekliyor
   • Tümü "scheduleType: now" - HEMEN paylaşılmalı
   • Schedulerlar çalışıyor (2 process aktif)
   • YouTube quota: 20,000 toplam (2 proje x 10,000)
   
❌ SORUN:
   1. GÜNLÜK LİMİT AYARI:
      - platform_settings.youtube.dailyLimit = 2
      - Bugün 0 video paylaşıldı ama limit kontrolü hata veriyor olabilir
   
   2. YOUTUBE API QUOTA AŞILMIŞ:
      - Error logs: "The user has exceeded the number of videos they may upload"
      - YouTube'un gerçek API limiti dolmuş
      - Quotalar sıfırlanmış (0/10000) ama YouTube tarafında limit devam ediyor
   
   3. SCHEDULER DAILY LIMIT CHECK:
      - _count_today_uploads() sadece social_queue.json'daki SUCCESS'leri sayıyor
      - Ancak dailyLimit=2 olduğu için ilk 2 videodan sonra durmuş olabilir

💡 ÇÖZÜM:
   A. Geçici: queues.json'da dailyLimit değerini artır (örn: 50)
   B. YouTube API: 24 saat bekle (quota reset için)
   C. Manuel test: Bir videoyu manuel upload et ve sonucu gözle
""")

print("\n" + "=" * 70)
print("SORU 2: Kuyruğu resetlediğimde neden paylaşılmış bir öğe ekleniyor?")
print("=" * 70)

print("""
✅ DURUM:
   • queues.json'da 25 video "pending" durumda
   • İlk video: job_69c92ad036c451.92790437
     - Added: 2026-03-29 (2 gün önce)
     - Status: pending (hiç paylaşılmamış)
   
❓ "PAYLAŞILMIŞ ÖĞE" NEDİR?
   Queue reset yapıldığında:
   • Eğer eski pending videolar varsa, bunlar hala orada durur
   • scheduleType="now" olduğu için scheduler bunları HEMEN işlemeye çalışır
   • "⚡ HEMEN PAYLAŞ: job_xxx" mesajları bu yüzden görünüyor
   
📋 NE OLUYOR?
   1. Queue resetlendiğinde pending videolar SİLİNMİYOR
   2. Scheduler çalıştığında bunları tekrar işlemeye çalışıyor
   3. Ama YouTube quota dolduğu için başarısız oluyor
   4. Başarısız videolar "pending" olarak kalıyor (retry loop)
   
💡 ÇÖZÜM:
   A. Manuel temizlik: clean_queue.py çalıştır (failed olanları temizler)
   B. queues.json'da videos array'ini manuel temizle
   C. Frontend'den queue'yu "flush/clear" et
""")

print("\n" + "=" * 70)
print("SORU 3: Kuyruk bir hatadan dolayı çalışmıyorsa, bilgisi veriliyor mu?")
print("=" * 70)

print("""
🟡 KISMEN EVET:

✅ MEVCUT ERROR LOGGING:
   1. scheduler_errors.json:
      - Her hata kaydediliyor
      - Timestamp, job_id, error_message, platform
      - Resolved flag (false olarak işaretli)
   
   2. Console logs:
      - "❌ YouTube başarısız: ..." mesajları
      - Real-time görünüyor
   
   3. Job files (data/jobs/xxx.json):
      - Her job'un kendi error field'ı var
      - Status: "failed" olarak işaretleniyor

❌ EKSİK:
   1. Kullanıcı bildirimi YOK:
      - Email notification yok
      - Webhook yok
      - UI alert sistemi yok
   
   2. Dashboard'da error summary YOK:
      - Frontend'de error gösterimi eksik
      - "Son hatalar" widget'ı yok
   
   3. Retry mekanizması:
      - Failed videolar otomatik retry edilmiyor
      - Manuel müdahale gerekiyor

📊 HATA LOG KONTROL:
   Dosya: data/scheduler_errors.json
   Son 3 hata:
   - job_69bf559bb644d4: "quota exceeded" (Resolved: false)
   - job_69bf559baf60e0: "quota exceeded" (Resolved: false)
   - job_69bf559b9f1cf9: "quota exceeded" (Resolved: false)

💡 İYİLEŞTİRME ÖNERİLERİ:
   A. Error notification webhook ekle
   B. Frontend'e error dashboard ekle
   C. Email alert sistemi (opsiyonel)
   D. Retry mekanizmasını aktif et (quota hatası için YAPMA ama network hatası için yap)
""")

print("\n" + "=" * 70)
print("SORU 4: API limitleri kendini nasıl güncelliyor?")
print("=" * 70)

print("""
✅ OTOMATİK GÜNCELLENME SİSTEMİ:

📂 DOSYA: python/youtube/project_manager.py

🔄 RESET MEKANİZMASI:
   
   1. _reset_daily_quotas_if_needed() metodu:
      ┌─────────────────────────────────────┐
      │ Her get_best_project() çağrısında   │
      │ otomatik olarak çalışır             │
      └─────────────────────────────────────┘
   
   2. Kontrol mantığı:
      ```python
      now = datetime.now(timezone.utc)
      today = now.date()
      
      for project in projects:
          last_reset_date = parse(project['last_reset']).date()
          
          if last_reset_date < today:
              # YENİ GÜN - RESET!
              project['quota_used_today'] = 0
              project['upload_count_today'] = 0
              project['last_reset'] = now.isoformat()
              save_projects()
      ```
   
   3. UTC bazlı:
      - Türkiye UTC+3 ama sistem UTC kullanıyor
      - Sıfırlama UTC gece yarısında (Türkiye 03:00)
   
   4. Ne zaman çalışır?:
      - Her video upload öncesi
      - get_best_project() çağrıldığında
      - Scheduler her cycle'da

📊 MEVCUT DURUM:
   • VideoKur Ana Proje:
     - quota_used_today: 0
     - last_reset: 2026-03-31T05:53:19 (bugün sıfırlanmış)
     - Remaining: 10,000
   
   • video-kur2:
     - quota_used_today: 0
     - last_reset: 2026-03-31T05:53:19 (bugün sıfırlanmış)
     - Remaining: 10,000

⚠️  ÖZEL DURUM - YOUTUBE API QUOTA:
   • Sistem quota'sı sıfırlanmış (0/10000)
   • Ancak YouTube API tarafında GERÇEK limit hala dolmuş durumda
   • YouTube'un limiti BAĞIMSIZ - Google Cloud Console'da takip edilir
   • Reset zamanı: YouTube'un belirlediği 24 saatlik pencere

💡 SONUÇ:
   ✅ Otomatik reset: ÇALIŞIYOR
   ✅ Günlük takip: ÇALIŞIYOR
   ⚠️  YouTube API limit: Sistem kontrolü dışında (harici faktör)
""")

print("\n" + "=" * 70)
print("GENEL SONUÇ VE ÖNERİLER")
print("=" * 70)

print("""
🔴 ACİL SORUNLAR:
   1. dailyLimit=2 çok düşük → Artır (örn: 50)
   2. YouTube API quota dolmuş → 24 saat bekle
   3. 25 pending video birikmiş → Temizle (clean_queue.py)

🟡 ORTA VADELİ İYİLEŞTİRMELER:
   1. Error notification sistemi ekle
   2. Frontend error dashboard ekle
   3. Retry mekanizması iyileştir (quota errors için disable et)

🟢 İYİ ÇALIŞAN SİSTEMLER:
   1. ✅ Quota otomatik reset mekanizması
   2. ✅ Multi-project rotation
   3. ✅ Error logging (scheduler_errors.json)
   4. ✅ Recursion fix (max 2 retry)
   5. ✅ Gemini API fix

📝 YAPILACAKLAR (ÖNCELİK SIRASINA GÖRE):
   [ ] 1. queues.json → platform_settings.youtube.dailyLimit değiştir (2 → 50)
   [ ] 2. clean_queue.py çalıştır (failed videoları temizle)
   [ ] 3. 24 saat bekle (YouTube quota reset için)
   [ ] 4. Test: Yeni video ekle ve upload sonucunu gözle
   [ ] 5. Error notification sistemi planla (gelecek için)
""")

print("\n" + "=" * 70)
