# YouTube Token Yönetimi ve Sorun Çözümü

## 🔴 Sorun: "invalid_grant: Token has been expired or revoked"

Bu hata, YouTube tarafından verilen access/refresh token'ının artık geçerli olmadığı anlamına gelir.

### Sebepleri:
1. **Token Revoke**: Google hesap ayarlarında YouTube'a erişim kaldırılmış
2. **Token Expire**: Refresh token geçerliliği yitirmiş (~6 ay+ eski)
3. **Güvenlik Protokolü**: Google, IP/Device değişikliğinden dolayı token'ı revoke etmiş
4. **Token Dosyası Bozuk**: Pickle dosyası okunamıyor veya veri bozuk

---

## ✅ Çözüm

### **Hızlı Çözüm (Önerilen)**

```bash
# Windows
reset_youtube_credentials.bat

# Linux/Mac
python reset_youtube_credentials.py
```

Bu komut:
- ✅ Eski token'ları siler
- ✅ Backup oluşturur (`data/youtube_credentials.backup/`)
- ✅ Yeni auth talebi tetikler

### **Manuel Çözüm**

Şu dizini silin:
```
data/youtube_credentials/
```

Scheduler yeniden başladığında yeni kimlik doğrulama yapılacak.

---

## 🔧 Otomatik Iyileştirmeler

### Yapılan Güncellemeler:
1. ✅ **auth.py**: `invalid_grant` hatası tespit edilince eski token otomatik silinir
2. ✅ **scheduler**: Token hatası başarısız olarak işaretlenir, bir sonraki çalıştırmada yeni auth yapılır
3. ✅ **production_scheduler.py**: Token monitoring başlatıldı

### Nasıl Çalışır?

```
Scheduler başlatılır
    ↓
Token refresh hatası (invalid_grant)
    ↓
Hata tespit edilir → Token dosyası silinir
    ↓
get_or_authenticate() çağrılır
    ↓
Tarayıcı açılır → Google OAuth
    ↓
Yeni token kaydedilir
    ↓
Production normal şekilde devam eder
```

---

## 🚀 Scheduler Başlatma

### Production Scheduler
```bash
python start_production_scheduler.bat
# veya
python python/scheduler/production_scheduler.py
```

### Social Scheduler
```bash
python start_social_scheduler.bat
```

---

## 📝 Token Dosyaları Nerede?

```
data/youtube_credentials/
├── default.pkl              # Default channel token
├── <channel_id_1>.pkl       # Channel-specific tokens
└── <channel_id_2>.pkl
```

---

## 🔐 Google Authenticator Sürecü

Scheduler token refresh hatası aldığında:

1. Browser otomatik açılır (https://localhost:8080)
2. Google hesabıyla giriş yaparsınız
3. YouTube erişim iznini verirsiniz
4. Token kaydedilir ve otomatik kapanır
5. Scheduler devam eder

**⏱️ Timeout**: 5 dakika (`flow.run_local_server()`)

---

## 🐛 Debug & Logging

### Token Hataları Loglanır:
- ❌ Token yenileme hatası
- ⚠️ Token revoke edilme
- 🔄 Token dosyası silme
- ✅ Yeni kimlik doğrulama başlatma

### Logları Görmek:
```bash
# stderr'e yazılır
python python/scheduler/production_scheduler.py 2>&1 | tee scheduler.log
```

---

## ❓ Sık Sorulan Sorular

**S: Backup'tan restore edebilir miyim?**
A: Evet, eğer revoke edilmiş değilse:
```bash
# data/youtube_credentials.backup/ dosyasını
# data/youtube_credentials/ olarak kopyalayın
```

**S: Token ne kadar dayanır?**
A: Access token: ~1 saat
   Refresh token: ~6 ay (Google google'da revoke edebilir)

**S: Multiple kanal token'ları?**
A: Her kanal için ayrı token:
- `default.pkl`: Ana channel
- `UCxxx.pkl`: Spesifik channel ID token'ı

---

## 📞 Destek

Sorun devam ederse:
1. [data/youtube_credentials.backup/](../data/youtube_credentials.backup/) kontrol edin
2. Google OAuth logs'u kontrol edin
3. `client_secrets.json` dosyası doğru mu kontrol edin
4. Firewall/VPN tarafından bloke ediliyor mu kontrol edin
