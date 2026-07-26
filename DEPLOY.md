# VideoKur — Tek Docker Ortamı

VideoKur lokal bilgisayarda ve VPS'te aynı `Dockerfile` ile aynı `docker-compose.yml` dosyasını kullanır. Tek `videokur` container'ı Nginx, PHP-FPM, Python/FFmpeg ve üç scheduler sürecini Supervisor ile çalıştırır.

## Lokal kullanım

```powershell
Copy-Item .env.example .env
docker compose up -d --build
```

Panel: `http://localhost:8000`

Kontrol:

```powershell
docker compose ps
docker compose logs -f videokur
curl.exe -f http://localhost:8000/api/health.php
```

## Günlük geliştirme akışı

1. Kod değişikliğini yapın.
2. `docker compose up -d --build` çalıştırın.
3. Paneli ve `/api/health.php` adresini test edin.
4. Değişiklikleri GitHub'a gönderin.
5. VPS'te `sudo /var/www/videokur/deploy/update.sh` çalıştırın.

## Kalıcı veriler

Aşağıdaki klasörler image dışında bind volume olarak kalır ve Git tarafından takip edilmez:

- `data/`
- `output/`
- `logs/`

API anahtarları `data/config.json` içinde tutulur. `.env` ve credential dosyaları GitHub'a gönderilmez. İlk kurulum başlangıç verilerini `docker/data-seed/` klasöründen oluşturur.

## İlk VPS Docker geçişi

Ubuntu üzerinde Docker Engine ve Compose plugin kurulduktan sonra:

```bash
cd /var/www/videokur
sudo ./deploy/migrate-to-docker.sh
```

Script runtime verilerini yedekler, native Nginx/PHP-FPM/scheduler servislerini durdurur, tek container'ı build eder ve health check sonucunu bekler. Başarısızlıkta native servisleri yeniden başlatır.

## Sonraki VPS güncellemeleri

En kolay yöntem panelde **Ayarlar → Genel → Sistem Güncellemesi** bölümünden önce **Güncelleme Kontrol Et**, ardından **Güncelle** düğmesine basmaktır. Web güncelleme hizmetinin ilk kurulumu:

```bash
cd /var/www/videokur
sudo ./deploy/install-update-service.sh
```

Komut satırı alternatifi:

```bash
cd /var/www/videokur
sudo ./deploy/update.sh
```

Updater:

1. Runtime verilerini `/var/backups/videokur/` altına yedekler.
2. `origin/main` için fast-forward güncelleme yapar.
3. Commit SHA etiketli image build eder.
4. Container'ı yeniler.
5. Health check'i bekler.
6. Başarısızlıkta önceki image ve commit'e döner.

## Manuel rollback

```bash
cd /var/www/videokur
sudo ./deploy/rollback.sh <commit-sha>
```

## Mimari

```text
videokur container
├── Supervisor
├── Nginx :80
├── PHP-FPM :9000
├── production-scheduler
├── social-scheduler
└── content-scheduler

Host bind volume'leri
├── ./data   -> /app/data
├── ./output -> /app/output
├── ./logs   -> /app/logs
└── ./assets -> /app/assets
```

`deploy/nginx/` ve eski scheduler unit dosyaları yalnız native kurulumun geri dönüş referanslarıdır. `videokur-update.path` ile `videokur-update.service`, web panelinden güvenli host güncellemesini tetikleyen aktif unit dosyalarıdır. Ortak container yapılandırmaları `docker/` altındadır.
