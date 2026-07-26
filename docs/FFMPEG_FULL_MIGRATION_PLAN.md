# VideoKur Tam FFmpeg Geçiş Planı

> **Durum — 26 Temmuz 2026:** Tam FFmpeg geçişi uygulandı. MoviePy ve yalnız ona bağlı paketler kaldırıldı. Pipeline ve yeniden üretim akışı tek geçişli FFmpeg composer kullanıyor. FFmpeg 5.1 container testinde 49,56 saniyelik 1080×1920 video 20,1 saniyede üretildi (`realtime factor=0,41`); eski MoviePy ilk encode ölçümü 139 saniyeydi. Tüm efektler, BGM, altyazı, dinamik çözünürlük, H.264/AAC ve FFprobe kalite kapısı doğrulandı.
## 1. Amaç

Video üretiminin yerel, CPU ve bellek yoğun bölümünü MoviePy/Pillow/NumPy tabanlı kare üretiminden çıkarıp tek bir FFmpeg filter graph ve tek encode geçişiyle çalıştırmak.

Ana hedefler:

- Aynı görsel kalite seviyesinde video kompozisyon süresini en az %50 azaltmak.
- Kompozisyon sırasındaki tepe bellek kullanımını en az %40 azaltmak.
- Geçici video ve ikinci H.264 encode işlemini kaldırmak.
- 9:16, 1:1, 16:9 ve özel boyutları gerçekten desteklemek.
- MoviePy bağımlılığını ve ona bağlı `numpy`, `imageio`, `imageio-ffmpeg`, `proglog`, `decorator` yükünü kaldırmak.
- Hata halinde eski üreticiye kontrollü geri dönüş sağlayarak kesintisiz geçiş yapmak.

## 2. Mevcut durum ve ölçülen sorunlar

### 2.1 Mevcut akış

Bugünkü video aşaması şöyledir:

1. `pipeline.py` TTS segmentlerini üretir.
2. Segment sesleri FFmpeg concat demuxer ile birleştirilir.
3. SRT dosyası Python ile oluşturulur.
4. `video_composer.py`, her sahnenin her karesini MoviePy/Pillow/NumPy ile üretir.
5. MoviePy, `final_video_temp.mp4` dosyasını H.264/AAC olarak encode eder.
6. FFmpeg temp videoyu tekrar decode eder, altyazıyı yakar ve videoyu ikinci kez H.264 encode eder.
7. Temp video silinir veya altyazı başarısızsa final dosya olarak taşınır.

FFmpeg bugün sistemde kurulu ve `libx264`, `libx265`, `libass` desteğine sahip. Ses concat, altyazı yakma ve `ffprobe` tabanlı doğrulamanın bir kısmı zaten FFmpeg kullanıyor. Buna rağmen ana kare üretimi MoviePy üzerindedir.

### 2.2 Kanıtlanan darboğazlar

- `video_composer.py` içinde her 1080×1920 kare Python'a alınmakta, NumPy üzerinde kırpılmakta ve Pillow LANCZOS ile yeniden boyutlandırılmaktadır.
- 49,57 saniyelik örnek videonun ilk MoviePy encode'u 139 saniye sürmüştür.
- 57,77 saniyelik örnek videonun ilk MoviePy encode'u 174 saniye sürmüştür.
- Bu değerler yalnız ilk encode için yaklaşık `2,8–3,0 × video süresi` ve hedef 30 FPS'e karşı yalnız `8–10 FPS` üretim hızıdır.
- Altyazı aşaması videoyu ikinci kez encode etmektedir; toplam disk I/O, CPU süresi ve kalite kaybı artmaktadır.
- MoviePy temp ses dosyasında gerçek bir `Permission denied → Broken pipe` hatası kaydedilmiştir. Bu hata aynı işin yeniden denenmesine ve kaynak israfına neden olmuştur.
- `compose_video()` genişlik ve yüksekliği parametre olarak almamakta; sabit `1080×1920` kullanmaktadır. UI ve job verisindeki kare/yatay/özel boyutlar yalnız görsel üretimine yansımakta, final videoya yansımamaktadır.
- Composer `threads=4` değerini sabit kullanır; VPS kapasitesine ve aynı container içindeki PHP/scheduler süreçlerine göre ayarlanamaz.
- Tüm üretim global kilitle seri çalışır. Bu güvenli olmakla birlikte ağ beklemeleri ile CPU render aşamalarını birbirinden ayırmadığı için toplam throughput'u sınırlar.
- Son örneklerde Pollinations için iş başına 75–120 saniye sabit cooldown görülmüştür. FFmpeg geçişi video kompozisyonunu hızlandırır; bu harici servis beklemesini ortadan kaldırmaz.

### 2.3 Çıktı sözleşmesi

Mevcut başarılı çıktıların ortak özellikleri:

- Video: H.264, `yuv420p`, 30 FPS
- Ses: AAC
- Çözünürlük: fiilen daima 1080×1920
- Süre: ses süresine yakın
- Ortalama toplam bitrate: yaklaşık 1,0–1,2 Mbit/s
- Dosya adı: `output/<job_id>/final_video.mp4`

Yeni motor bu sözleşmeyi, düzeltilmiş dinamik çözünürlük davranışı dışında korumalıdır.

## 3. Hedef mimari

Python yalnız orkestrasyon, doğrulama ve FFmpeg argümanlarının güvenli üretiminden sorumlu olacaktır. Piksel ve ses örnekleri Python sürecinden geçmeyecektir.

```text
script.json + images + audio.mp3 + subtitles.srt + optional BGM
                              |
                              v
                 FFmpeg graph builder (Python)
                              |
                              v
             filter_complex_script (job temp directory)
                              |
                              v
     FFmpeg: image loops -> effects -> concat -> subtitles
             voice + BGM -> trim/loop -> amix
                              |
                              v
          single H.264/AAC encode -> final_video.mp4.part
                              |
                              v
          ffprobe validation -> atomic rename -> final_video.mp4
```

### 3.1 Yeni modüller

- `python/ffmpeg_runner.py`
  - FFmpeg/FFprobe binary çözümleme
  - güvenli `subprocess.Popen` çalıştırma
  - `-progress pipe:1` ayrıştırma
  - timeout, iptal, stderr özeti ve hata sınıflandırma
  - encoder yetenek tespiti
- `python/ffmpeg_graph.py`
  - sahne girişlerini ve filter graph'ı üretme
  - efekt adı → FFmpeg filtre zinciri eşlemesi
  - video/ses concat ve BGM mix
  - altyazı filtresi ve yol kaçışları
- `python/video_composer_ffmpeg.py`
  - mevcut `compose_video()` ile uyumlu dış arayüz
  - `.part` çıktı, doğrulama ve atomik yayınlama
  - render metrikleri
- `python/media_probe.py`
  - ses/video süresi, codec, çözünürlük, FPS ve stream doğrulaması
  - MoviePy fallback'i olmayan saf `ffprobe` uygulaması

Filter graph komut satırına gömülmemeli; iş dizininde oluşturulan `filter_complex.txt` dosyası `-filter_complex_script` ile kullanılmalıdır. Böylece Windows/Docker yol kaçışları, uzun komut sınırı ve kullanıcı kaynaklı metinlerin argümanlara karışması önlenir.

## 4. Efektlerin FFmpeg karşılıkları

Her görsel girişine `-loop 1 -t <duration> -i <image>` uygulanır. Ön işleme zinciri genel olarak:

```text
scale -> crop -> setsar=1 -> fps=30 -> effect -> trim -> setpts
```

Önerilen eşlemeler:

| Mevcut efekt | FFmpeg yaklaşımı |
|---|---|
| `static` | aspect ratio koruyan `scale` + merkez `crop`, ardından `fps` |
| `ken_burns_zoom_in` | `zoompan`, doğrusal `1.00 → 1.15` |
| `ken_burns_zoom_out` | `zoompan`, doğrusal `1.15 → 1.00` |
| `zoom_in_fast` | `zoompan`, `1.00 → 1.25` |
| `zoom_out_fast` | `zoompan`, `1.25 → 1.00` |
| `pulse` | sinüs ifadeli zoom, `1.00 ↔ 1.10` |
| `pulse_strong` | sinüs ifadeli zoom, `1.00 ↔ 1.20` |
| `pan_left` / `pan_right` | büyük ölçek + zamana bağlı `crop=x` |
| `drift_left_right` | sinüs ifadeli yatay `crop=x` |
| `micro_zoom_jitter` | düşük genlikli sinüs zoom |
| `tilt_pan` | büyük ölçek + yatay ilerleme + dikey sinüs crop |
| `cinematic_push` | `zoompan`, `1.02 → 1.18` |
| `glitch_transition` | ilk/son 200 ms'de deterministik crop kaydırma; gerekirse RGB kanal kaydırma |

Efektlerin merkez, yön, genlik ve süre davranışı golden-frame testleriyle mevcut motorla karşılaştırılmalıdır. Rastgele glitch davranışı test edilebilirlik için `job_id + scene_index` tabanlı deterministik seed kullanmalıdır.

## 5. Tek geçişli FFmpeg üretimi

Bir iş için tek FFmpeg süreci aşağıdaki işleri birlikte yapmalıdır:

- Tüm sahne görsellerini loop ederek video stream'e dönüştürme
- Seçilen hareket efektini uygulama
- Her sahneyi gerçek TTS süresine göre trim etme
- Sahne videolarını concat filtresiyle birleştirme
- Konuşma sesini ana ses olarak kullanma
- BGM varsa `aloop/atrim`, gain ve `amix` uygulama
- SRT/ASS altyazıyı aynı filter graph içinde yakma
- H.264/AAC olarak yalnız bir kez encode etme
- Web oynatımı için `-movflags +faststart`
- Uyumluluk için `-pix_fmt yuv420p`, sabit 30 FPS ve stereo AAC üretme

Başlangıç CPU profili:

```text
-c:v libx264 -preset veryfast -crf 21
-pix_fmt yuv420p -r 30
-c:a aac -b:a 128k -ar 48000 -ac 2
-movflags +faststart
```

`medium` yerine `veryfast` kullanımı önce kalite/dosya boyutu benchmark'ı ile onaylanmalıdır. VPS için encoder ayarı environment/config üzerinden yönetilmelidir:

- `VIDEO_ENCODER=libx264`
- `VIDEO_PRESET=veryfast`
- `VIDEO_CRF=21`
- `VIDEO_FPS=30`
- `VIDEO_THREADS=<VPS'e göre>`
- `VIDEO_MAX_CONCURRENT_RENDERS=1`

FFmpeg listesindeki `h264_nvenc`, `h264_qsv` veya `h264_vaapi` satırları donanımın container'a erişebildiğini kanıtlamaz. Donanım encoder'ı ancak startup sırasında gerçek 1 saniyelik encode probe'u başarılıysa açılmalıdır. Tipik CPU tabanlı VPS için varsayılan `libx264` kalmalıdır.

## 6. Aşamalı uygulama planı

### Faz 0 — Ölçüm altyapısı

Süre: 0,5–1 gün

- Pipeline aşamalarına monotonic başlangıç/bitiş zamanları ekle.
- FFmpeg progress verisinden `frame`, `fps`, `speed`, `out_time` topla.
- İş sonucuna `render_metrics` alanı yaz:
  - `engine`
  - `wall_seconds`
  - `video_seconds`
  - `realtime_factor`
  - `peak_rss_mb`
  - `output_bytes`
  - `encoder`
  - `preset`
- En az üç mevcut iş için baseline oluştur: efektli, statik ve BGM'li.

Çıkış kriteri: Aynı işin eski motor performansı tekrar üretilebilir ve ölçülebilir olmalı.

### Faz 1 — FFprobe ve ses yardımcılarını saf FFmpeg'e taşıma

Süre: 0,5 gün

- `get_audio_duration()` içindeki `AudioFileClip` kullanımını `ffprobe` ile değiştir.
- `VideoValidator._get_basic_info()` MoviePy fallback'ini kaldır.
- Mevcut FFmpeg ses concat uygulamasını ortak runner'a taşı.
- Binary seçimini yalnız `FFMPEG_BIN`/`FFPROBE_BIN` ve `shutil.which` üzerinden yap.

Çıkış kriteri: Composer dışındaki hiçbir Python dosyası MoviePy import etmemeli.

### Faz 2 — FFmpeg graph builder ve efekt paritesi

Süre: 2–3 gün

- Dinamik `width`, `height`, `fps` parametrelerini composer sözleşmesine ekle.
- Tüm efekt eşlemelerini uygula.
- Eksik görsel için Python/NumPy gradient üretmek yerine FFmpeg `color`/`gradients` veya önceden hazırlanmış küçük fallback asset kullan.
- Tüm sahneleri tek filter graph içinde concat et.
- Ses süresi ile sahne toplamı arasındaki fark için açık politika uygula:
  - video uzunsa `trim`
  - video kısaysa son kareyi `tpad=stop_mode=clone` ile uzat

Çıkış kriteri: 12 efekt, üç aspect ratio ve fallback görsel testleri geçmeli.

### Faz 3 — Ses, BGM ve altyazıyı tek geçişe alma

Süre: 1–2 gün

- BGM loop, gain ve mix işlemlerini FFmpeg'e taşı.
- `amix` sonrası clipping'i önlemek için limiter veya loudness kontrolü ekle.
- SRT/ASS altyazıyı video encode zincirine dahil et.
- Mevcut `final_video_temp.mp4` ve temp audio dosyalarını kaldır.
- Çıktıyı `final_video.mp4.part` olarak üret, doğrulama başarılıysa atomik olarak taşı.

Çıkış kriteri: Tek FFmpeg süreci ve tek video encode; temp MP4 oluşmamalı.

### Faz 4 — Shadow mode ve karşılaştırma

Süre: 1–2 gün

- Config'e `videoComposer: moviepy|ffmpeg|shadow` ekle.
- Shadow modunda aynı hazır girdilerden iki motoru çalıştır; yalnız MoviePy çıktısını yayınla.
- Karşılaştır:
  - süre farkı ≤ 100 ms
  - çözünürlük/FPS/pixel format eşitliği
  - başlangıç, sahne sınırları ve final karelerinden SSIM
  - A/V sync
  - altyazı görünürlüğü ve güvenli alan
  - ses peak/loudness
  - render süresi, tepe RSS ve çıktı boyutu

Çıkış kriteri: En az 20 gerçek işte kritik fark veya crash olmaması.

### Faz 5 — Kademeli canlı geçiş

Süre: 2–3 gün gözlem

- Önce manuel üretimlerin %10'unu FFmpeg'e yönlendir.
- Sonra %50 ve %100'e çıkar.
- FFmpeg hata verirse aynı girdiler hazırsa MoviePy fallback'e yalnız bir kez izin ver.
- Her aşamada hata oranı, p95 render süresi, peak RSS ve kuyruk bekleme süresini izle.
- Kabul sınırı aşılırsa feature flag ile anında MoviePy'ye dön.

Çıkış kriteri: 100 işte başarı oranı eski motorun altında değil; p95 render süresi ve RSS hedefleri sağlanıyor.

### Faz 6 — MoviePy'yi kaldırma ve scheduler iyileştirmesi

Süre: 1 gün

- `video_composer.py` eski uygulamasını kaldır veya kısa süreli legacy modüle taşı.
- `moviepy` ve yalnız ona bağlı paketleri requirements dosyalarından kaldır; lock dosyasını yeniden üret.
- MoviePy'ye özel temp dizini ve compositor lock kodunu sadeleştir.
- Global üretim kilidini iki kaynağa ayırmayı ayrıca değerlendir:
  - API/ağ aşamaları: kontrollü paralel
  - FFmpeg render aşaması: `VIDEO_MAX_CONCURRENT_RENDERS`

Çıkış kriteri: `rg moviepy python` sonucu boş; temiz Docker build ve uçtan uca üretim başarılı.

## 7. Test matrisi

Zorunlu otomasyon:

- Boyutlar: 1080×1920, 1080×1080, 1920×1080, bir düşük çözünürlüklü test profili
- Görsel kaynaklar: PNG, JPEG, görselden küçük/büyük hedef, eksik görsel
- Efektler: desteklenen her efekt ve bilinmeyen efekt fallback'i
- Ses: tek segment, çok segment, mono/stereo, kısa/uzun BGM, BGM yok
- Altyazı: Türkçe karakterler, emoji davranışı, boş SRT, uzun satır, özel font
- Süre: kesirli segment süreleri, ses/video toplamı farklılığı
- Yol: boşluk ve Türkçe karakter içeren dosya yolu
- İptal: çalışan FFmpeg sürecini durdurma ve `.part` temizliği
- Hata: disk dolu, bozuk görsel, bozuk ses, bilinmeyen encoder
- Yeniden deneme: yarım çıktı varken aynı işi tekrar başlatma
- Platform validasyonu: YouTube Shorts, Instagram Reels ve TikTok yükleme öncesi doğrulama

## 8. Kabul kriterleri

Performans:

- Aynı donanım ve aynı girdilerde p50 kompozisyon süresi en az %50 düşmeli.
- Hedef p95 realtime factor `≤ 1,0`; ideal hedef `≤ 0,75`.
- Tepe RSS en az %40 düşmeli.
- Geçici MP4 nedeniyle oluşan ek disk kullanımı ortadan kalkmalı.
- CPU sınırı konduğunda web arayüzü ve scheduler health check gecikmemeli.

Kalite ve doğruluk:

- Final video yalnız bir video stream ve bir audio stream içermeli.
- Codec H.264/AAC, pixel format `yuv420p`, FPS 30 olmalı.
- Final süre ile ana ses süresi farkı en fazla 100 ms olmalı.
- İstenen job boyutu final çıktıya yansımalı ve her iki boyut çift sayı olmalı.
- Siyah kenar, bozuk crop, altyazı taşması ve duyulabilir clipping olmamalı.
- `ffprobe` doğrulaması geçmeden job `done` durumuna alınmamalı.

Güvenilirlik:

- FFmpeg hata kodu ve stderr özeti job loguna yazılmalı.
- Başarısız iş final dosyayı overwrite etmemeli.
- İptal/timeout sonrası child process ve `.part` dosyası kalmamalı.
- Fallback en fazla bir kez çalışmalı; sonsuz retry olmamalı.

## 9. Operasyon ve kaynak kontrolü

- Compose dosyasına doğrudan rastgele CPU/RAM limiti koymak yerine önce VPS kapasitesi ölçülmeli.
- Başlangıç için tek eşzamanlı render korunmalı; FFmpeg thread sayısı VPS mantıksal çekirdeğinin yaklaşık yarısıyla sınırlandırılmalı.
- Container'a `nice`/CPU quota uygulanacaksa PHP-FPM ve scheduler aynı container'da olduğundan tüm servisi kısmak yerine FFmpeg child process önceliği düşürülmeli.
- Disk alanı iş başlamadan kontrol edilmeli. Tahmini ihtiyaç final dosya + güvenlik payı olmalı; ikinci temp MP4 artık hesaplanmamalı.
- `-nostdin`, explicit timeout ve process-group termination kullanılmalı.
- FFmpeg komutunda `shell=True` kullanılmamalı.
- Loglarda API anahtarları veya tam hassas environment dökümü bulunmamalı.

## 10. Tahmini sonuç

Mevcut ölçümde yalnız MoviePy'nin ilk encode'u 50–58 saniyelik video için 139–174 saniye sürüyor; ardından altyazı için ikinci encode yapılıyor. Piksel işlemlerinin FFmpeg C filtrelerine alınması ve ikinci encode'un kaldırılması büyük olasılıkla en yüksek kazanımı sağlayacaktır.

İlk canlı hedef olarak toplam video kompozisyon aşamasında %50–70 süre azalması makuldür; kesin değer Faz 0 ve Faz 4 benchmark'larıyla belirlenmelidir. Genel uçtan uca üretim süresindeki iyileşme daha düşük olacaktır, çünkü son işlerde gözlenen 75–120 saniyelik görsel servis cooldown süreleri FFmpeg kapsamı dışındadır.

## 11. Önerilen uygulama sırası

1. Metrikleri ve saf FFprobe yardımcılarını ekle.
2. Dinamik boyut destekli FFmpeg graph builder'ı geliştir.
3. Tüm efektleri, BGM'yi ve altyazıyı tek encode'a al.
4. Shadow modda 20 gerçek iş karşılaştır.
5. Feature flag ile %10 → %50 → %100 geç.
6. MoviePy bağımlılığını kaldır.
7. Sonraki ayrı optimizasyon olarak görsel üretimi cooldown ve global üretim kilidini ele al.
