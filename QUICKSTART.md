# ⚡ QUICKSTART (Güncel)

Bu kılavuz mevcut repository yapısına göre güncellenmiştir.

## 1. Gereksinimler

- Python 3.9+
- PHP 8+
- FFmpeg

## 2. Kurulum

```bash
cd python
pip install -r requirements.txt
cd ..
```

`data/config.json` dosyanızın mevcut olduğundan ve gerekli anahtarların tanımlı olduğundan emin olun.

## 3. Uygulamayı Başlatma

### Seçenek A (önerilen): tek komutla

```bash
start_all.bat
```

### Seçenek B: manuel

```bash
php -S localhost:8000 router.php
```

## 4. Scheduler Başlatma (ihtiyaca göre)

```bash
start_production_scheduler.bat
start_social_scheduler.bat
start_content_scheduler.bat
```

## 5. Web Arayüzü

- Dashboard: `http://localhost:8000/`
- Yeni Video: `http://localhost:8000/create.php`
- İçerikler: `http://localhost:8000/content.php`
- Kuyruklar: `http://localhost:8000/queues.php`
- Script Yönetimi: `http://localhost:8000/scripts.php`
- Hesaplar: `http://localhost:8000/accounts.php`
- Ayarlar: `http://localhost:8000/settings.php`

## Not

Eski dokümanlarda geçen `test_upload.bat` ve `start_scheduler.bat` bu repoda aktif başlangıç yolu değildir.
