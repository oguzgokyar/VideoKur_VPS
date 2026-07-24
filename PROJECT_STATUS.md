# 📌 Project Status (Güncel Envanter)

Bu dosya, mevcut repository durumunu kısa ve doğrulanabilir şekilde özetler.

## Genel Durum

- **Durum:** Aktif geliştirme
- **Ana akış:** İçerik → Job → Production Queue → Pipeline → Sosyal paylaşım kuyrukları
- **Script seçimi:** Job bazlı açık seçim (otomatik varsayılan fallback kaldırıldı)

## Kod Yüzeyi

- **API (`api/*.php`):** 18 dosya  
  Önemli uçlar: `jobs.php`, `content.php`, `queues.php`, `production_queue.php`, `scripts.php`, `youtube_upload.php`, `youtube_oauth.php`, `youtube_channels.php`
- **Frontend (`frontend/*.php`):** 10 sayfa  
  Ana sayfalar: `dashboard.php`, `create.php`, `content.php`, `queues.php`, `scripts.php`, `accounts.php`, `settings.php`, `production_queue.php`
- **Python (`python/**/*.py`):** 48 dosya  
  Ana orkestrasyon: `pipeline.py`  
  Scheduler katmanı: `python/scheduler/`

## Çalıştırma

- Toplu başlangıç: `start_all.bat`
- Manuel web: `php -S localhost:8000 router.php`
- Scheduler’lar:
  - `start_production_scheduler.bat`
  - `start_social_scheduler.bat`
  - `start_content_scheduler.bat`

## Dokümantasyon Sınıflandırması

- **Aktif giriş dokümanları:** `README.md`, `QUICKSTART.md`, `PROJECT_STATUS.md`
- **Aktif modül dokümanları:** `docs/features/*`, `docs/user-guides/*`
- **Arşiv (referans):** `docs/archive/completed/*`, `docs/archive/legacy/*`
- **Fikir notları:** `Fikirler/*` (ürünle birebir tutarlılık garanti edilmez)

Detaylı sınıflandırma için: `docs/setup/DOCUMENTATION_INDEX.md`
