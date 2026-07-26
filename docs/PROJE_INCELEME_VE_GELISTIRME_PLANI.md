# VideoKur Proje İncelemesi ve Geliştirme Planı

## Genel değerlendirme

VideoKur; PHP paneli, Python video üretim pipeline'ı, FFmpeg, üç scheduler ve sosyal platform entegrasyonlarından oluşan güçlü bir otomasyon ürünüdür. Mevcut yapı tek VPS ve tek yönetici kullanıcılı üretim için çalışabilir; SaaS seviyesine geçmeden önce güvenlik, veri bütünlüğü ve gözlemlenebilirlik güçlendirilmelidir.

## Acil öncelikler

1. Login, session, admin yetkisi, CSRF ve API middleware.
2. `Access-Control-Allow-Origin: *` kullanımının sınırlandırılması.
3. API anahtarları ve OAuth token'larının Git dışı, izinleri sıkı dosyalarda tutulması.
4. PHP `exec()` çağrılarının whitelist ve `escapeshellarg()` ile güvenli hale getirilmesi.
5. TLS doğrulamasını kapatan cURL ayarlarının kaldırılması.
6. JSON kuyruklarında `flock()` ve atomik yazma.
7. Health check, yedekleme, rollback ve log rotation.

## Orta vadeli geliştirmeler

- Kuyruk durum makinesi: `queued`, `running`, `retry_wait`, `completed`, `failed`, `cancelled`.
- Video adımlarında süre, deneme, hata ve log bilgilerinin tutulması.
- PHP isteğinden bağımsız worker mimarisi.
- Disk kullanım sınırı, eski output temizliği ve object storage desteği.
- Scheduler heartbeat, kaynak alarmı ve Telegram/e-posta bildirimleri.
- Sosyal platform adapter sözleşmelerinin ortaklaştırılması.
- Birim, entegrasyon, güvenlik ve production smoke testleri.

## SaaS hazırlığı

JSON yerine önce SQLite, büyüme halinde PostgreSQL kullanılmalıdır. `users`, `workspaces`, `projects`, `jobs`, `provider_accounts`, `oauth_tokens` ve `system_logs` tabloları oluşturulmalıdır. Her SaaS ayrı dizin, `.env`, systemd servisleri, alan adı ve updater ile izole edilmelidir.

## Uygulama sırası

### Faz 1 — Güvenlik ve stabilite

Auth, API yetkilendirme, CSRF, secret hardening, shell güvenliği, JSON kilitleri, backup, health endpoint ve HTTPS.

### Faz 2 — Üretim güvenilirliği

Worker/heartbeat, retry/timeout standardı, job recovery, output temizliği, alarm ve log yönetimi.

### Faz 3 — Ürün kalitesi

Job detayları, ilerleme görünümü, filtreler, toplu işlemler, sağlayıcı hesap yönetimi ve responsive iyileştirmeler.

### Faz 4 — SaaS

Veritabanı, kullanıcı/workspace modeli, tenant izolasyonu, kota/plan sistemi, release updater ve merkezi monitoring.

## İlk çalışma paketi

```text
Auth + CSRF
API authorization
Secret hardening
Atomic JSON writes
Queue locking
Backup/rollback
Health check
HTTPS
```
