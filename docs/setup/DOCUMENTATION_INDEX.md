# Documentation Index & Sınıflandırma

Bu dosya, hangi dokümanın **aktif kaynak** olduğunu ve hangilerinin yalnızca **tarihsel referans** olduğunu belirtir.

## 1) Source of Truth (Öncelikli)

1. `README.md` — genel ürün özeti ve ana linkler
2. `QUICKSTART.md` — güncel kurulum/başlatma komutları
3. `PROJECT_STATUS.md` — güncel kod yüzeyi ve çalışma modeli

> Çelişki durumunda yukarıdaki 3 dosya önceliklidir.

## 2) Aktif Dokümanlar

### `docs/features/`
- `content-discovery.md`
- `social-media.md`
- `youtube-integration.md`

### `docs/user-guides/`
- `web-kullanim.md`
- `kullanim.md`
- `custom-scripts.md`
- `subtitle-styling.md`

## 3) Arşiv Dokümanlar (Aktif Geliştirme Kaynağı Değil)

### `docs/archive/completed/`
- Tamamlanmış değişiklik/proposal özetleri

### `docs/archive/legacy/`
- Eski analiz notları ve geçmiş raporlar

Bu klasörlerdeki bilgiler referans içindir; doğrudan uygulama davranışını temsil etmeyebilir.

## 4) Bilerek Arşivde Tutulan Kök Dökümanlar

- `IMPLEMENTATION_COMPLETE.md`
- `SEQUENTIAL_PRODUCTION_IMPLEMENTATION.md`
- `SEQUENTIAL_PRODUCTION_QUICKSTART.md`

Bu dosyalar tarihsel geçiş dokümanlarıdır. Güncel kullanım için `QUICKSTART.md` ve `PROJECT_STATUS.md` kullanılmalıdır.

## 5) Bakım Kuralı

- Yeni özellikte önce ilgili `docs/features/*` veya `docs/user-guides/*` güncellenir.
- Komut/path değiştiğinde aynı PR’da `QUICKSTART.md` ve gerekirse `README.md` güncellenir.
- Eski doküman kaldırılmıyorsa `docs/archive/` altına taşınır.
