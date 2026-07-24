# YouTube Multi-Project Kurulum Rehberi

## 🎯 Amaç
Birden fazla Google Cloud projesi kullanarak YouTube API kotasını artırmak.

**Her proje = 10,000 birim/gün = ~6 video/gün**

| Proje Sayısı | Günlük Kota | Tahmini Video |
|--------------|-------------|---------------|
| 1 | 10,000 | ~6 |
| 2 | 20,000 | ~12 |
| 3 | 30,000 | ~18 |
| 5 | 50,000 | ~30 |

---

## 📋 Yeni Proje Ekleme Adımları

### 1. Google Cloud Console'da Yeni Proje Oluştur

1. [Google Cloud Console](https://console.cloud.google.com/) açın
2. Üst menüden proje seçiciye tıklayın → **Yeni Proje**
3. Proje adı: `VideoKur-2` (veya benzeri)
4. **Oluştur** tıklayın

### 2. YouTube Data API v3 Etkinleştir

1. Hamburger menü → **APIs & Services** → **Library**
2. "YouTube Data API v3" arayın
3. **Enable** tıklayın

### 3. OAuth Consent Screen Yapılandır

1. **APIs & Services** → **OAuth consent screen**
2. **External** seçin → **Create**
3. Bilgileri doldurun:
   - App name: `VideoKur Uploader 2`
   - User support email: E-posta adresiniz
   - Developer contact: E-posta adresiniz
4. **Save and Continue** (Scopes ve Test users bölümlerini atlayabilirsiniz)

### 4. OAuth 2.0 Credentials Oluştur

1. **APIs & Services** → **Credentials**
2. **+ Create Credentials** → **OAuth client ID**
3. Application type: **Desktop app**
4. Name: `VideoKur Desktop 2`
5. **Create** tıklayın
6. **Download JSON** tıklayın

### 5. Credentials Dosyasını Kaydet

İndirilen JSON dosyasını şu konuma kopyalayın:
```
data/youtube_credentials/client_secrets_2.json
```

⚠️ **ÖNEMLİ:** Dosya adını `client_secrets_2.json` olarak değiştirin!

### 6. Projeyi Sisteme Ekle

**Yöntem A: Komut Satırı**
```bash
cd python
python -m youtube.project_manager add "VideoKur Proje 2" "client_secrets_2.json" 10000 "İkinci YouTube API projesi"
```

**Yöntem B: JSON Dosyasını Düzenle**

`data/youtube_projects.json` dosyasını açın ve `projects` dizisine ekleyin:
```json
{
  "id": "project_2",
  "name": "VideoKur Proje 2",
  "client_secrets_file": "client_secrets_2.json",
  "is_active": true,
  "is_default": false,
  "daily_quota": 10000,
  "quota_used_today": 0,
  "last_reset": null,
  "upload_count_today": 0,
  "last_upload": null,
  "created_at": "2026-03-29T00:00:00Z",
  "notes": "İkinci YouTube API projesi"
}
```

### 7. Yeni Proje için OAuth Yetkilendirme

İlk upload'da tarayıcı açılacak ve Google hesabınızla giriş yapmanız istenecek.
Aynı YouTube kanalı için farklı projelerden yetkilendirme yapabilirsiniz.

---

## 🔄 Rotasyon Stratejileri

`youtube_projects.json` → `rotation_strategy`:

| Strateji | Açıklama |
|----------|----------|
| `round_robin` | Projeleri sırayla kullan (varsayılan) |
| `least_used` | En az kullanılan projeyi seç |
| `failover` | Önce varsayılanı kullan, kota dolunca diğerine geç |

---

## 📊 Durum Kontrolü

```bash
cd python
python -m youtube.project_manager status
```

Çıktı:
```
============================================================
📊 YOUTUBE PROJELERİ DURUMU
============================================================
Toplam proje: 2 (2 aktif)
Strateji: round_robin
Toplam kota: 0/20000 (20000 kalan)
Bugün yüklenen: 0 video
Tahmini kalan: ~12 video
------------------------------------------------------------
✅ VideoKur Ana Proje ⭐: 0/10000 (0 video)
✅ VideoKur Proje 2: 0/10000 (0 video)
============================================================
```

---

## ⚠️ Önemli Notlar

1. **Aynı Kanal, Farklı Projeler:** Tüm projeleri aynı YouTube kanalına bağlayabilirsiniz

2. **ToS Uyumluluğu:** Google'ın hizmet şartları, kota aşmak için birden fazla proje kullanmayı açıkça yasaklamıyor, ancak kötüye kullanımdan kaçının

3. **Kota Sıfırlama:** Her gün 00:00 UTC'de kotalar otomatik sıfırlanır

4. **Token Yönetimi:** Her proje için ayrı token dosyası oluşturulur:
   - `project_1_default_token.pickle`
   - `project_2_default_token.pickle`

5. **Hata Durumu:** Bir proje kota aşımı verdiğinde, sistem otomatik olarak sonraki projeye geçer

---

## 🛠️ Sorun Giderme

### "quotaExceeded" Hatası
- Tüm projelerin kotası dolmuş
- Gece yarısı (UTC) sıfırlanmasını bekleyin
- Yeni proje ekleyin

### "invalid_grant" Hatası  
- Token süresi dolmuş
- İlgili projenin token dosyasını silin
- Yeniden yetkilendirme yapın

### Proje Algılanmıyor
- `client_secrets_X.json` dosya adını kontrol edin
- `youtube_projects.json` formatını kontrol edin
- `is_active: true` olduğundan emin olun
