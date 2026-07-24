# VideoKur VPS ve Lokal Test Mimarisi

Bu repo, orijinal `oguzgokyar/Video_Kur` reposundan ayrilmis temiz bir projedir. Orijinal repo bu kurulumdan etkilenmez.

## Hedef Mimari

```text
Internet
  |
Nginx + SSL
  |
PHP web panel / API
  |
Python pipeline + FFmpeg
  |
data, output, logs

Arka plan servisleri:
- production scheduler: tek seferde 1 video uretimi
- social scheduler: sosyal medya kuyruklari
- content scheduler: RSS/icerik havuzu
```

## Lokal VPS Provasi

Gerekenler:

- Docker Desktop
- Docker Compose

Ilk calistirma:

```bash
cp .env.example .env
docker compose up --build
```

Windows PowerShell icin:

```powershell
Copy-Item .env.example .env
docker compose up --build
```

Panel:

```text
http://localhost:8000
```

Scheduler servisleriyle calistirma:

```bash
docker compose --profile schedulers up --build
```

Arka planda calistirma:

```bash
docker compose --profile schedulers up -d --build
```

Log izleme:

```bash
docker compose logs -f app
docker compose logs -f production-scheduler
```

Temel kontrol:

```bash
docker compose ps
docker compose exec app python --version
docker compose exec app ffmpeg -version
```

Runtime dosyalari lokal klasorlere yazilir:

```text
data/
output/
logs/
```

## VPS Native Kurulum

Onerilen image:

```text
Ubuntu 24.04 LTS
```

Temel paketler:

```bash
sudo apt update
sudo apt install -y nginx php-fpm php-cli php-curl php-mbstring python3 python3-venv python3-pip ffmpeg certbot python3-certbot-nginx unzip
```

Proje dizini:

```text
/var/www/videokur
```

Python ortami:

```bash
cd /var/www/videokur
python3 -m venv .venv
. .venv/bin/activate
pip install --upgrade pip setuptools wheel
pip install -r python/requirements.txt
```

Gerekli runtime klasorleri:

```bash
mkdir -p data/jobs data/.locks output logs
```

Docker lokal testte kolaylik icin `php -S` kullanir. VPS'te Nginx + PHP-FPM tercih edilir.

## systemd Servisleri

Production scheduler:

```ini
[Unit]
Description=VideoKur Production Scheduler
After=network.target

[Service]
Type=simple
WorkingDirectory=/var/www/videokur
Environment=PYTHONUNBUFFERED=1
ExecStart=/var/www/videokur/.venv/bin/python python/scheduler/production_scheduler.py
Restart=always
RestartSec=5
User=www-data
Group=www-data

[Install]
WantedBy=multi-user.target
```

Social scheduler:

```ini
[Unit]
Description=VideoKur Social Scheduler
After=network.target

[Service]
Type=simple
WorkingDirectory=/var/www/videokur
Environment=PYTHONUNBUFFERED=1
ExecStart=/var/www/videokur/.venv/bin/python python/scheduler/social_scheduler.py --interval 60
Restart=always
RestartSec=5
User=www-data
Group=www-data

[Install]
WantedBy=multi-user.target
```

Content scheduler:

```ini
[Unit]
Description=VideoKur Content Scheduler
After=network.target

[Service]
Type=simple
WorkingDirectory=/var/www/videokur
Environment=PYTHONUNBUFFERED=1
ExecStart=/var/www/videokur/.venv/bin/python python/content/scheduler.py
Restart=always
RestartSec=5
User=www-data
Group=www-data

[Install]
WantedBy=multi-user.target
```

## Yayina Almadan Once Test Listesi

- `http://localhost:8000` veya staging domain aciliyor.
- Ayarlar ekraninda Python ve FFmpeg testi basarili.
- `data/config.json` repoya commitlenmiyor.
- Kisa bir test video kuyruga ekleniyor.
- Production scheduler tek video isliyor.
- `output/<job_id>/final_video.mp4` olusuyor.
- Disk kullanimi ve loglar kontrol ediliyor.
- Staging domain SSL ile aciliyor.

## Kaynak Notu

2 vCPU / 4 GB RAM baslangic icin yeterli olabilir. Render sirasinda rahat calismasi icin VPS'te 2-4 GB swap onerilir. Uzun vadede ikinci SaaS veya daha yogun video uretimi icin 4 vCPU / 8 GB RAM daha saglikli olur.
