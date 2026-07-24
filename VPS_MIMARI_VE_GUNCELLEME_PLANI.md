# VideoKur VPS Mimari ve Güncelleme Planı

## 1. Seçilen yaklaşım

VideoKur, VPS üzerinde Docker kullanılmadan, Ubuntu 24.04 LTS üzerinde native servisler olarak çalıştırılacaktır.

Bu yaklaşımın avantajları:

- Daha düşük RAM ve CPU kullanımı
- 2 vCPU / 4 GB RAM sunucu için uygunluk
- PHP, Python ve FFmpeg süreçlerine doğrudan erişim
- Basit bakım ve hızlı başlangıç

Orijinal `oguzgokyar/Video_Kur` reposu korunacak; kurulum ve geliştirme ayrı yeni repo üzerinden yapılacaktır.

## 2. VPS sistem mimarisi

```text
Internet
   |
   v
Nginx (80/443, HTTPS)
   |
   +-- PHP-FPM -> /var/www/videokur/api
   |
   +-- Static frontend -> /var/www/videokur/frontend
   |
   +-- Output files -> /var/www/videokur/output

systemd servisleri:
   +-- videokur-production.service
   +-- videokur-social.service
   +-- videokur-content.service
   +-- videokur-updater.service (güncelleme sırasında çalışır)
```

### Temel bileşenler

- İşletim sistemi: Ubuntu 24.04 LTS
- Web sunucusu: Nginx
- PHP: PHP 8.3 + PHP-FPM
- Python: Python 3.12 ve proje virtualenv'i
- Video işleme: FFmpeg
- Süreç yönetimi: systemd
- Güvenlik duvarı: UFW
- TLS: Let's Encrypt
- Kod kaynağı: ayrı GitHub repository

## 3. Önerilen dizin yapısı

```text
/var/www/videokur/
├── api/
├── frontend/
├── python/
├── assets/
├── data/
├── output/
├── logs/
├── .venv/
├── .env                 # GitHub'a gönderilmez
└── current_release      # aktif sürüm/tag bilgisi

/var/backups/videokur/  # uygulama yedekleri
/var/log/videokur/      # updater ve servis logları
```

`data/`, `output/`, `logs/` ve `.env` güncellemelerde korunacaktır.

## 4. Güvenlik modeli

- Sadece 22, 80 ve 443 portları açık olacak.
- SSH şifreli giriş yerine SSH anahtarı kullanılacak.
- Root ile günlük çalışma yapılmayacak.
- Uygulama servisleri `www-data` kullanıcısıyla çalışacak.
- `.env` dosyası `600` izinli olacak.
- Nginx üzerinden `data/`, `python/`, `logs/` ve gizli dosyalar yayınlanmayacak.
- GitHub erişimi için yalnızca read-only deploy key veya release erişimi kullanılacak.
- API anahtarları ve sosyal medya şifreleri GitHub'a konulmayacak.

## 5. Güvenli güncelleme mimarisi

Web panelindeki Ayarlar bölümüne güncelleme ekranı eklenecektir:

- Mevcut sürüm
- GitHub'daki son release/tag
- Güncelleme durumu
- Release notları
- Güncellemeleri kontrol et
- Güncellemeyi başlat
- Son güncelleme zamanı ve logları

Web PHP süreci doğrudan `git pull` çalıştırmayacaktır. Akış:

```text
Admin güncelleme butonuna basar
          |
          v
GitHub Release/tag kontrol edilir
          |
          v
/var/lib/videokur/update-request oluşturulur
          |
          v
videokur-updater systemd servisi çalışır
          |
          +-- bakım kilidi oluşturur
          +-- mevcut sürümü ve verileri yedekler
          +-- yeni release'i indirir
          +-- PHP/Python bağımlılıklarını kontrol eder
          +-- servisleri yeniden başlatır
          +-- health check yapar
          +-- başarılıysa sürümü aktif eder
          +-- hata varsa önceki sürüme geri döner
```

Güncelleme yalnızca imzalı GitHub Release/tag üzerinden yapılacak; doğrudan rastgele branch kodu çalıştırılmayacaktır.

## 6. Yedekleme ve geri dönüş

Güncelleme öncesi şu veriler yedeklenecek:

- `.env`
- `data/`
- `output/`
- `logs/`
- aktif release bilgisi

Günlük otomatik yedek ve en az 7 günlük saklama planlanacaktır. Başarısız health check durumunda önceki release'e rollback yapılacaktır.

## 7. Kurulum sırası

1. Yeni GitHub repository oluşturulacak.
2. VPS'e Ubuntu 24.04 LTS kurulacak.
3. SSH anahtarı, UFW ve fail2ban yapılandırılacak.
4. Nginx, PHP-FPM, Python, FFmpeg ve systemd servisleri kurulacak.
5. Proje `/var/www/videokur` altına alınacak.
6. `.env` ve VPS'e özel klasör izinleri oluşturulacak.
7. Nginx + alan adı + HTTPS yapılandırılacak.
8. Uygulama ve scheduler servisleri başlatılacak.
9. Fonksiyon, video üretimi ve scheduler testleri yapılacak.
10. Yedekleme, log rotation ve rollback test edilecek.
11. Güncelleme ekranı ve updater servisi devreye alınacak.

## 8. Kaynak planı

2 vCPU / 4 GB RAM başlangıç için yeterlidir. Video üretimi sırasında aynı anda tek üretim çalıştırılacaktır. İkinci SaaS eklendiğinde ayrı systemd servisleri, ayrı dizin ve ayrı alan adı kullanılacaktır.

## 9. Uygulama onayı

Önerilen mimari:

> Ubuntu 24.04 LTS + Nginx + PHP-FPM + Python virtualenv + FFmpeg + systemd + GitHub Release tabanlı güvenli updater + UFW + Let's Encrypt

VPS IP adresi, SSH kullanıcı adı/portu, alan adı ve yeni GitHub repository bilgileri verildikten sonra kurulum adımlarına geçilecektir. SSH parolası veya API anahtarları sohbet içinde paylaşılmamalıdır.
