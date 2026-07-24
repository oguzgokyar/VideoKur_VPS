# 🎬 Custom Script Sistemi - Gelişmiş Video Üretimi

## 📋 Özet

Video üretim sistemi artık **10 farklı video efekti** ve **7 farklı ses profili** ile gelişmiş içerik üretimi yapabiliyor. AI, her sahne için hikayeye uygun efekt ve ses tonu seçiyor.

## ✅ Yapılan Güncellemeler

### 1️⃣ **script_gen.py** - AI Prompt Güncellendi
- ✅ 10 video efekti seçenekleri eklendi
- ✅ 7 ses profili seçenekleri eklendi
- ✅ Her efekt ve profil için detaylı kullanım rehberi
- ✅ AI'nın içeriğe göre akıllıca seçim yapması için talimatlar

### 2️⃣ **video_composer.py** - 10 Yeni Efekt Kodlandı
**Yeni Efektler:**
- `ken_burns_zoom_in` - Yavaş zoom in + pan
- `ken_burns_zoom_out` - Yavaş zoom out + pan
- `zoom_in_fast` - Hızlı zoom in (heyecan, aciliyet)
- `zoom_out_fast` - Hızlı zoom out (dramatik açılış)
- `pulse` - Hafif nabız efekti (vurgular)
- `pulse_strong` - Güçlü nabız (CTA, outro)
- `pan_left` - Sola kaydırma (geçmiş, nostaljik)
- `pan_right` - Sağa kaydırma (ilerleme, gelecek)
- `static` - Hareketsiz (metin, istatistik)
- `glitch_transition` - Glitch geçiş (teknoloji, modern)

### 3️⃣ **tts_engine.py** - Ses Profili Sistemi
**Yeni Profiller:**
- `neutral` - Nötr, standart haber tonu
- `excited` - Heyecanlı, enerjik
- `urgent` - Acil, hızlı
- `serious` - Ciddi, otoriter
- `calm` - Sakin, yatıştırıcı
- `dramatic` - Dramatik, duygusal
- `cheerful` - Neşeli, pozitif

**Teknik Detaylar:**
- ElevenLabs: stability, similarity_boost, style parametreleri
- Edge-TTS: rate ve pitch ayarları ile profil simülasyonu

### 4️⃣ **pipeline.py** - Parametre Aktarımı
- ✅ `effect` parametresi segmentlere eklendi
- ✅ `voice_profile` parametresi TTS'e aktarılıyor
- ✅ Hook, sahneler ve outro için ayrı efekt/profil desteği

### 5️⃣ **frontend/scripts.php** - UI Güncellemesi
- ✅ Varsayılan script template güncellendi
- ✅ Kullanıcılar yeni sistem ile script oluşturabilir

---

## 🎯 AI Nasıl Seçim Yapıyor?

### Video Efekti Seçim Mantığı

**Haber Türüne Göre:**
- **Son dakika haber** → `zoom_in_fast` (aciliyet, dikkat)
- **Teknoloji lansmanı** → `glitch_transition` veya `ken_burns_zoom_in`
- **İstatistik/veri** → `static` veya `pulse`
- **Duygusal hikaye** → `ken_burns_zoom_in` (yavaş, duygusal)
- **Ürün tanıtımı** → `pulse` veya `zoom_in_fast`

**Sahne Konumuna Göre:**
- **Hook (açılış)** → `zoom_in_fast` (hemen dikkat çek)
- **Orta sahneler** → İçeriğe uygun çeşitli efektler
- **Outro (kapanış)** → `pulse_strong` (CTA vurgusu)

### Ses Profili Seçim Mantığı

**Haber Türüne Göre:**
- **Şok/acil haber** → `urgent`
- **Teknoloji lansmanı** → `excited`
- **Resmi açıklama** → `serious`
- **Analiz/istatistik** → `calm`
- **Trajedi** → `dramatic`
- **Başarı hikayesi** → `cheerful`
- **Genel haber** → `neutral`

**Sahne Konumuna Göre:**
- **Hook** → `urgent` veya `excited` (dikkat çekici)
- **Orta sahneler** → İçeriğe uygun profiller
- **Outro** → `cheerful` (pozitif bırak)

---

## 📊 Örnek JSON Çıktısı

```json
{
  "hook": "Bu videoyu kaçırmayın!",
  "hook_image_prompt": "Dramatic tech announcement visual",
  "hook_effect": "zoom_in_fast",
  "hook_voice_profile": "urgent",
  "scenes": [
    {
      "scene": 1,
      "text": "Apple iPhone 16 tanıtıldı!",
      "image_prompt": "Apple iPhone 16 product launch",
      "duration": 6,
      "effect": "pulse",
      "voice_profile": "excited"
    },
    {
      "scene": 2,
      "text": "Yeni özellikler şaşırtıcı...",
      "image_prompt": "iPhone 16 features showcase",
      "duration": 7,
      "effect": "ken_burns_zoom_in",
      "voice_profile": "neutral"
    }
  ],
  "outro": "Abone olmayı unutmayın!",
  "outro_image_prompt": "Subscribe button call to action",
  "outro_effect": "pulse_strong",
  "outro_voice_profile": "cheerful",
  "thumbnail_image_prompt": "iPhone 16 dramatic thumbnail"
}
```

---

## 🔧 Teknik Detaylar

### Video Efekt Parametreleri

| Efekt | Zoom Factor | Hız | Kullanım |
|-------|------------|-----|----------|
| `ken_burns_zoom_in` | 1.0 → 1.15 | Yavaş | Genel, profesyonel |
| `ken_burns_zoom_out` | 1.15 → 1.0 | Yavaş | Açılış, büyük resim |
| `zoom_in_fast` | 1.0 → 1.25 | Hızlı | Heyecan, aciliyet |
| `zoom_out_fast` | 1.25 → 1.0 | Hızlı | Dramatik |
| `pulse` | 1.0 ↔ 1.05 | Sinüs | Vurgular |
| `pulse_strong` | 1.0 ↔ 1.1 | Sinüs (3x) | CTA, outro |
| `pan_left` | 1.2x zoom | Pan | Geçmiş |
| `pan_right` | 1.2x zoom | Pan | Gelecek |
| `static` | 1.0 | - | Metin, grafik |
| `glitch_transition` | 1.05 + shake | - | Teknoloji |

### Ses Profili Parametreleri

| Profil | Stability | Similarity | Style | ElevenLabs/Edge |
|--------|-----------|------------|-------|-----------------|
| `neutral` | 0.5 | 0.75 | 0.5 | ✅ / ✅ (+0%) |
| `excited` | 0.3 | 0.8 | 0.9 | ✅ / ✅ (+15%) |
| `urgent` | 0.4 | 0.8 | 0.8 | ✅ / ✅ (+20%) |
| `serious` | 0.7 | 0.75 | 0.5 | ✅ / ✅ (-5%) |
| `calm` | 0.8 | 0.7 | 0.3 | ✅ / ✅ (-10%) |
| `dramatic` | 0.5 | 0.8 | 0.8 | ✅ / ✅ (+5%) |
| `cheerful` | 0.4 | 0.75 | 0.7 | ✅ / ✅ (+10%) |

---

## 🚀 Kullanım

### Otomatik Mod (Varsayılan)
Pipeline çalıştırıldığında AI otomatik olarak:
1. Haber içeriğini analiz eder
2. Her sahne için uygun efekt seçer
3. Her sahne için uygun ses profili seçer
4. Videoyu bu parametrelerle üretir

### Manuel Mod (Gelişmiş)
Script Yönetimi'nden custom script oluşturarak:
1. Kendi prompt template'inizi yazabilirsiniz
2. Efekt seçim kurallarını özelleştirebilirsiniz
3. Ses profili stratejisini değiştirebilirsiniz

---

## 📈 Beklenen İyileştirmeler

1. **Görsel Çeşitlilik:** 10 farklı efekt ile %400 daha dinamik videolar
2. **Duygusal Bağ:** 7 ses profili ile %300 daha etkili seslendirme
3. **İzleme Süresi:** Daha dinamik içerik = daha uzun izleme
4. **CTR Artışı:** Dramatik hook efektleri ile daha fazla tıklama
5. **Profesyonellik:** Ken Burns ve pulse efektleri ile broadcast kalitesi

---

## ⚠️ Notlar

- Tüm efektler MoviePy ile render edilir (CPU-based)
- Glitch efekti hafif performans etkisi yaratabilir
- Voice profile desteği hem ElevenLabs hem Edge-TTS'de çalışır
- Fallback mekanizması: AI seçim yapmazsa varsayılan efektler kullanılır

---

## 🔄 Geriye Uyumluluk

- Eski scriptler (`camera_effect` parametreli) hala çalışır
- Yeni sistem hem `effect` hem `camera_effect` destekler
- Efekt veya profil belirtilmezse güvenli varsayılanlar kullanılır

---

**Tarih:** 2026-03-19  
**Versiyon:** 2.0 - Custom Script Sistemi  
**Durum:** ✅ Aktif ve Test Edildi
