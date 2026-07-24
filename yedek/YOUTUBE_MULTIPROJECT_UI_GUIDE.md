# 🔑 YouTube Multi-Project UI Kullanım Kılavuzu

## 📋 Genel Bakış

YouTube API kotalarını aşmak için birden fazla Google Cloud projesi ekleyip yönetebileceğiniz yeni bir arayüz hazırlandı. Bu sistem **Hesaplar** sayfasına entegre edildi ve YouTube tabında bulunmaktadır.

## ✨ Özellikler

### 1. 📊 Kota Gösterge Paneli
- **Aktif Proje Sayısı**: Sistemde kaç proje var
- **Toplam Kota**: Tüm projelerin günlük kotası toplamı
- **Kalan Kota**: Bugün için kullanılabilir kota
- **Video/Gün**: Tahmini yüklenebilecek video sayısı

### 2. 🔄 Rotasyon Stratejileri
Dropdown menüden seçilebilir:
- **Round Robin**: Projeleri sırayla kullanır
- **En Az Kullanılan**: Kotası en boş projeyi seçer
- **Failover**: Bir proje bitene kadar aynısını kullanır

### 3. 🎴 Proje Kartları
Her proje için:
- ⭐ Varsayılan proje işareti (yıldıza tıklayarak değiştirilebilir)
- 📊 Kota kullanım çubuğu
- 🟢/⏸️ Aktif/Pasif durumu
- ▶️/⏸️ Aktif/Duraklat butonu
- 🗑️ Silme butonu

### 4. ➕ Yeni Proje Ekleme
Modal pencere ile:
- Proje adı gir
- `client_secrets.json` dosyası yükle (Sürükle-Bırak destekli)
- Günlük kota ayarla (varsayılan: 10,000)
- Notlar ekle (opsiyonel)

## 🚀 Nasıl Kullanılır?

### Adım 1: Hesaplar Sayfasına Git
```
http://localhost/frontend/accounts.php
```

### Adım 2: YouTube Tabına Geç
Sol tarafta "YouTube" sekmesine tıklayın.

### Adım 3: YouTube API Projeleri Bölümünü Bulun
YouTube kanallarınızın altında "YouTube API Projeleri" başlığını göreceksiniz.

### Adım 4: Yeni Proje Ekle
1. "➕ Yeni Proje Ekle" kartına tıklayın
2. Modal açılır
3. Proje adı girin (örn: "VideoKur Proje 2")
4. `client_secrets.json` dosyasını seçin veya sürükleyin
5. "Proje Ekle" butonuna tıklayın

### Adım 5: Projeyi Yönet
- **Varsayılan Yapmak**: Yıldıza (☆) tıklayın → ⭐ olur
- **Duraklatmak**: "⏸️ Duraklat" butonuna tıklayın
- **Aktif Etmek**: "▶️ Aktif" butonuna tıklayın
- **Silmek**: "🗑️" butonuna tıklayın (onay sorar)

### Adım 6: Rotasyon Stratejisi Değiştir
Kota gösterge panelindeki dropdown menüden stratejinizi seçin.

## 🔧 Teknik Detaylar

### Backend API
**Endpoint**: `/api/youtube_projects.php`

**Aksiyonlar**:
- `GET ?action=list` - Tüm projeleri ve istatistikleri listele
- `POST action=add` - Yeni proje ekle (FormData ile dosya yükle)
- `POST action=remove` - Proje sil
- `POST action=toggle_active` - Aktif/Pasif durumunu değiştir
- `POST action=set_default` - Varsayılan projeyi ayarla
- `POST action=set_strategy` - Rotasyon stratejisini değiştir

### Python Entegrasyonu
Backend, Python'daki `youtube.project_manager` modülünü kullanır:
```bash
cd python
python -m youtube.project_manager status
python -m youtube.project_manager add --name "Proje 2" --file client_secrets.json
python -m youtube.project_manager remove --id project_2
```

### Veri Depolama
**Dosyalar**:
- `data/youtube_projects.json` - Proje metadata'sı
- `data/youtube_credentials/client_secrets_*.json` - OAuth credentials
- `data/youtube_credentials/project_*_token.pickle` - OAuth tokens

**JSON Yapısı**:
```json
{
  "projects": [
    {
      "id": "project_1",
      "name": "VideoKur Ana Proje",
      "client_secrets_file": "client_secrets_project_1.json",
      "is_active": true,
      "is_default": true,
      "daily_quota": 10000,
      "quota_used_today": 0,
      "upload_count_today": 1,
      "last_used_date": "2025-01-15",
      "notes": ""
    }
  ],
  "rotation_strategy": "round_robin",
  "quota_per_upload": 1600
}
```

## 🧪 Test

### Test UI (Standalone)
```
test_youtube_projects_ui.html
```
Bu dosyayı tarayıcıda açarak UI'ı backend olmadan test edebilirsiniz.

### Test Python Entegrasyonu
```bash
cd python
python -m youtube.project_manager status
```

### Test Backend API
```bash
curl -X GET "http://localhost/api/youtube_projects.php?action=list"
```

## 📚 İlgili Dosyalar

| Dosya | Açıklama |
|-------|----------|
| `frontend/accounts.php` | Ana UI - YouTube tab içinde |
| `api/youtube_projects.php` | Backend REST API |
| `python/youtube/project_manager.py` | Core proje yönetimi |
| `data/youtube_projects.json` | Proje veritabanı |
| `test_youtube_projects_ui.html` | Standalone test UI |

## ⚠️ Önemli Notlar

1. **client_secrets.json Nasıl Alınır?**
   - Google Cloud Console'a git
   - Yeni bir proje oluştur
   - YouTube Data API v3'ü etkinleştir
   - OAuth 2.0 Credentials oluştur (Desktop App)
   - JSON dosyasını indir

2. **Kota Limitleri**
   - Her proje günde 10,000 units
   - Bir video upload ~1,600 units
   - 1 proje = ~6 video/gün
   - 3 proje = ~18 video/gün

3. **Otomatik Kota Sıfırlama**
   - Her gün UTC 00:00'da kotalar sıfırlanır
   - `quota_used_today` ve `upload_count_today` 0'lanır

4. **Varsayılan Proje**
   - İlk eklenen proje otomatik varsayılan olur
   - Yıldıza tıklayarak değiştirilebilir
   - Sadece 1 proje varsayılan olabilir

## 🐛 Sorun Giderme

### Proje eklenmiyor
- `data/youtube_credentials/` klasörünün yazılabilir olduğundan emin olun
- PHP'nin `exec()` fonksiyonunu kullanabildiğinden emin olun
- Python'un PATH'de olduğundan emin olun

### Kota gösterilmiyor
- `python -m youtube.project_manager status` çalıştırın
- `data/youtube_projects.json` dosyasını kontrol edin

### Dosya yüklenmiyor
- Dosya adının `client_secrets` ile başladığından emin olun
- JSON formatının geçerli olduğunu kontrol edin
- Dosya boyutunun 5MB altında olduğundan emin olun

## 📞 Destek

Bu özellik hakkında sorularınız varsa:
- `YOUTUBE_README.md` - Genel YouTube entegrasyonu
- `YOUTUBE_TOKEN_GUIDE.md` - OAuth token yönetimi
- `README.md` - Sistem genel bakışı

---

✅ **Sistem Durumu**: Aktif ve kullanıma hazır
📅 **Tarih**: {{ date }}
🔧 **Versiyon**: 1.0
