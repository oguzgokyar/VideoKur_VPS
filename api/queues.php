<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$dataDir = __DIR__ . '/../data';
$queuesFile = $dataDir . '/queues.json';
$jobsDir = $dataDir . '/jobs';
$contentPoolFile = $dataDir . '/content_pool.json';
$schedulerStatusFile = $dataDir . '/scheduler_status.json';
$schedulerErrorsFile = $dataDir . '/scheduler_errors.json';
$baseDir = dirname(__DIR__);
require_once __DIR__ . '/music_helpers.php';

// Kuyruk verilerini yükle
function loadQueues() {
    global $queuesFile;
    if (!file_exists($queuesFile)) {
        return ['queues' => []];
    }
    $content = file_get_contents($queuesFile);
    return json_decode($content, true) ?: ['queues' => []];
}

// Kuyruk verilerini kaydet
function saveQueues($data) {
    global $queuesFile;
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return false;
    }
    return file_put_contents($queuesFile, $json, LOCK_EX) !== false;
}

function normalizeBooleanSetting($value, $default = false) {
    if ($value === null) return $default;
    if (is_bool($value)) return $value;
    if (is_int($value) || is_float($value)) return ((int)$value) !== 0;
    if (is_string($value)) {
        $v = strtolower(trim($value));
        if (in_array($v, ['1', 'true', 'yes', 'on', 'enabled'], true)) return true;
        if (in_array($v, ['0', 'false', 'no', 'off', 'disabled', ''], true)) return false;
    }
    return $default;
}

function normalizePlatformSettings($platformSettings) {
    if (!is_array($platformSettings)) {
        return [];
    }

    foreach ($platformSettings as $platform => &$settings) {
        if (!is_array($settings)) {
            continue;
        }

        if ($platform === 'instagram') {
            $accountId = $settings['accountId'] ?? $settings['account_id'] ?? null;
            if ($accountId !== null) {
                $settings['accountId'] = (string)$accountId;
            }
            unset($settings['account_id']);

            $type = strtolower((string)($settings['type'] ?? 'reel'));
            $settings['type'] = $type === 'story' ? 'story' : 'reel';

            $shareToFeedRaw = $settings['shareToFeed'] ?? $settings['share_to_feed'] ?? null;
            $settings['shareToFeed'] = normalizeBooleanSetting($shareToFeedRaw, $settings['type'] !== 'story');
            unset($settings['share_to_feed']);
        } elseif ($platform === 'facebook') {
            $pageId = $settings['pageId'] ?? $settings['page_id'] ?? null;
            if ($pageId !== null) {
                $settings['pageId'] = (string)$pageId;
            }
            unset($settings['page_id']);

            $type = strtolower((string)($settings['type'] ?? 'reel'));
            $settings['type'] = $type === 'video' ? 'video' : 'reel';

            $publishAsStatusRaw = $settings['publishAsStatus'] ?? $settings['publish_as_status'] ?? null;
            $settings['publishAsStatus'] = normalizeBooleanSetting($publishAsStatusRaw, false);
            unset($settings['publish_as_status']);
        }
    }
    unset($settings);

    return $platformSettings;
}

// Job bilgisini yükle
function loadJob($jobId) {
    global $jobsDir;
    // Önce klasör yapısını dene
    $jobFile = $jobsDir . '/' . $jobId . '/job.json';
    if (file_exists($jobFile)) {
        return json_decode(file_get_contents($jobFile), true);
    }
    // Sonra düz dosya yapısını dene
    $jobFile = $jobsDir . '/' . $jobId . '.json';
    if (file_exists($jobFile)) {
        return json_decode(file_get_contents($jobFile), true);
    }
    return null;
}

// Job bilgisini kaydet
function saveJob($jobId, $data) {
    global $jobsDir;
    // Önce klasör yapısını dene
    $jobFile = $jobsDir . '/' . $jobId . '/job.json';
    if (file_exists(dirname($jobFile))) {
        file_put_contents($jobFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return;
    }
    // Sonra düz dosya yapısını dene
    $jobFile = $jobsDir . '/' . $jobId . '.json';
    file_put_contents($jobFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function clearContentPoolByJobId($jobId) {
    global $contentPoolFile;
    if (!file_exists($contentPoolFile)) {
        return 0;
    }

    $pool = json_decode(file_get_contents($contentPoolFile), true);
    if (!is_array($pool)) {
        return 0;
    }

    $updated = 0;
    foreach ($pool['content'] ?? [] as &$item) {
        if (($item['processed_job_id'] ?? null) === $jobId) {
            $item['processed_job_id'] = null;
            if (($item['status'] ?? '') !== 'completed') {
                $item['status'] = 'pending';
            }
            $updated++;
        }
    }
    unset($item);

    if ($updated > 0) {
        if (!isset($pool['metadata']) || !is_array($pool['metadata'])) {
            $pool['metadata'] = [];
        }
        $pool['metadata']['last_updated'] = gmdate('Y-m-d\TH:i:s\Z');
        $pool['metadata']['total_items'] = count($pool['content'] ?? []);
        file_put_contents($contentPoolFile, json_encode($pool, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    return $updated;
}

/**
 * Runtime kuyruk dosyalarından job girişini temizle
 * - social_queue.json
 * - production_queue.json
 */
function removeJobFromRuntimeQueues($jobId) {
    global $dataDir;
    $stats = ['social_removed' => 0, 'production_removed' => 0];

    // 1) social_queue.json
    $socialQueueFile = $dataDir . '/social_queue.json';
    if (file_exists($socialQueueFile)) {
        $socialData = json_decode(file_get_contents($socialQueueFile), true);
        if (!is_array($socialData)) $socialData = ['queue' => []];
        $socialItems = $socialData['queue'] ?? [];
        $before = count($socialItems);
        $socialData['queue'] = array_values(array_filter($socialItems, function($item) use ($jobId) {
            return ($item['job_id'] ?? '') !== $jobId;
        }));
        $stats['social_removed'] = $before - count($socialData['queue']);
        if ($stats['social_removed'] > 0) {
            file_put_contents($socialQueueFile, json_encode($socialData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    // 2) production_queue.json
    $prodQueueFile = $dataDir . '/production_queue.json';
    if (file_exists($prodQueueFile)) {
        $prodData = json_decode(file_get_contents($prodQueueFile), true);
        if (!is_array($prodData)) $prodData = [];

        $removed = 0;
        foreach (['queue', 'production_queue'] as $key) {
            $items = $prodData[$key] ?? [];
            $before = count($items);
            $prodData[$key] = array_values(array_filter($items, function($item) use ($jobId) {
                return ($item['job_id'] ?? '') !== $jobId;
            }));
            $removed += ($before - count($prodData[$key]));
        }

        if (($prodData['current_job'] ?? null) === $jobId) {
            $prodData['current_job'] = null;
            $removed++;
        }

        $stats['production_removed'] = $removed;
        if ($removed > 0) {
            if (!isset($prodData['metadata']) || !is_array($prodData['metadata'])) {
                $prodData['metadata'] = [];
            }
            $prodData['metadata']['last_updated'] = date('c');
            file_put_contents($prodQueueFile, json_encode($prodData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    return $stats;
}

/**
 * Kuyruk ayarlarına göre scheduled_time hesapla
 * @param array $queue Kuyruk verisi (schedule ve platform_settings içerir)
 * @param int $position Videonun kuyruktaki pozisyonu (1, 2, 3...)
 * @param array $platforms Platform listesi (youtube, instagram, vs.)
 * @return string ISO 8601 format scheduled time
 */
function calculateScheduledTime($queue, $position, $platforms) {
    // Default: Şu an (eğer ayarlar yoksa)
    $now = new DateTime('now', new DateTimeZone('Europe/Istanbul'));
    
    // Her platform için ayarları kontrol et (ilk platformu kullan)
    $primaryPlatform = $platforms[0] ?? 'youtube';
    $platformSettings = $queue['platform_settings'][$primaryPlatform] ?? [];
    
    // Schedule type
    $scheduleType = $platformSettings['scheduleType'] ?? 'now';
    
    // Eğer "now" ise direkt şimdi döndür
    if ($scheduleType === 'now') {
        return $now->format('c');
    }
    
    // Interval-based scheduling
    if ($scheduleType === 'interval') {
        $startTime = $platformSettings['startTime'] ?? '09:00';
        $intervalMinutes = intval($platformSettings['intervalMinutes'] ?? 30);
        $dailyLimit = intval($platformSettings['dailyLimit'] ?? 0);
        
        // Start time'ı parse et
        list($hour, $minute) = explode(':', $startTime);
        $baseTime = clone $now;
        $baseTime->setTime(intval($hour), intval($minute), 0);
        
        // Position 1 → 0 offset, position 2 → 1 interval, vs.
        $intervalOffset = ($position - 1);
        
        // Eğer daily limit varsa, günleri hesapla
        if ($dailyLimit > 0) {
            $day = floor($intervalOffset / $dailyLimit);
            $positionInDay = $intervalOffset % $dailyLimit;
            
            // Gün ekle
            if ($day > 0) {
                $baseTime->modify("+{$day} day");
            }
            
            // Interval ekle
            $minutesToAdd = $positionInDay * $intervalMinutes;
            $baseTime->modify("+{$minutesToAdd} minutes");
        } else {
            // Daily limit yok, sadece interval ekle
            $minutesToAdd = $intervalOffset * $intervalMinutes;
            $baseTime->modify("+{$minutesToAdd} minutes");
        }
        
        // Eğer hesaplanan zaman geçmişte ise, yarına taşı
        if ($baseTime < $now) {
            $baseTime->modify('+1 day');
        }
        
        return $baseTime->format('c');
    }
    
    // Specific times scheduling
    if ($scheduleType === 'specific') {
        $specificTimes = $platformSettings['specificTimes'] ?? ['09:00', '15:00', '21:00'];
        $dailyLimit = intval($platformSettings['dailyLimit'] ?? count($specificTimes));
        
        if ($dailyLimit === 0) {
            $dailyLimit = count($specificTimes);
        }
        
        // Position'dan gün ve zaman dilimi hesapla
        $day = floor(($position - 1) / $dailyLimit);
        $timeIndex = ($position - 1) % $dailyLimit;
        
        // Zaman dilimini seç (varsa)
        $timeSlot = $specificTimes[$timeIndex % count($specificTimes)] ?? $specificTimes[0];
        
        // Parse et
        list($hour, $minute) = explode(':', $timeSlot);
        $scheduled = clone $now;
        $scheduled->setTime(intval($hour), intval($minute), 0);
        
        // Gün ekle
        if ($day > 0) {
            $scheduled->modify("+{$day} day");
        }
        
        // Eğer geçmişte ise yarına taşı
        if ($scheduled < $now) {
            $scheduled->modify('+1 day');
        }
        
        return $scheduled->format('c');
    }
    
    // Fallback: şimdi
    return $now->format('c');
}

function normalizeQueueVideoOrder(&$queue) {
    if (!isset($queue['videos']) || !is_array($queue['videos'])) {
        $queue['videos'] = [];
        return;
    }

    usort($queue['videos'], function($a, $b) {
        return intval($a['position'] ?? 999999) - intval($b['position'] ?? 999999);
    });

    $pendingPosition = 1;
    foreach ($queue['videos'] as $i => &$video) {
        $video['position'] = $i + 1;
        $status = $video['status'] ?? 'queued';
        if (in_array($status, ['queued', 'pending'])) {
            $video['scheduled_time'] = calculateScheduledTime($queue, $pendingPosition, $queue['platforms'] ?? ['youtube']);
            $pendingPosition++;
        }
    }
    unset($video);
}

/**
 * Pending videoları social_queue.json'a ekle (eğer zaten yoksa)
 * @param array $queue Queue verisi
 * @param string $dataDir Data dizini
 * @return int Eklenen video sayısı
 */
function addPendingVideosToSocialQueue($queue, $dataDir) {
    $socialQueueFile = $dataDir . '/social_queue.json';
    $baseDir = dirname($dataDir);
    
    // Social queue'yu yükle veya oluştur
    if (file_exists($socialQueueFile)) {
        $socialQueue = json_decode(file_get_contents($socialQueueFile), true);
    } else {
        $socialQueue = ['queue' => []];
    }
    
    // Mevcut job_id'leri topla
    $existingJobIds = [];
    foreach ($socialQueue['queue'] as $item) {
        $existingJobIds[] = $item['job_id'];
    }
    
    $added = 0;
    $platforms = $queue['platforms'] ?? ['youtube'];
    
    // Videoları position sırasına göre işle
    $videos = $queue['videos'] ?? [];
    usort($videos, function($a, $b) {
        return ($a['position'] ?? 999) - ($b['position'] ?? 999);
    });
    
    foreach ($videos as $video) {
        $jobId = $video['job_id'];
        
        // Zaten social_queue'da varsa atla
        if (in_array($jobId, $existingJobIds)) {
            continue;
        }
        
        // Job dosyasını yükle
        $job = loadJob($jobId);
        if (!$job) continue;
        
        // Video dosyası var mı kontrol et
        $videoPath = $baseDir . '/output/' . $jobId . '/final_video.mp4';
        if (!file_exists($videoPath)) {
            continue; // Video henüz üretilmemiş
        }
        
        // Her platform için durumu kontrol et
        $pendingPlatforms = [];
        foreach ($platforms as $platform) {
            $platformStatus = null;
            if (isset($job['social_upload']['platforms'][$platform])) {
                $platformStatus = $job['social_upload']['platforms'][$platform];
            }
            
            $status = is_array($platformStatus) ? ($platformStatus['status'] ?? 'pending') : ($platformStatus ?? 'pending');
            
            // Sadece pending olanları ekle (success olanları ekleme)
            if ($status === 'pending') {
                $pendingPlatforms[] = $platform;
            }
        }
        
        if (empty($pendingPlatforms)) {
            continue; // Tüm platformlar zaten tamamlanmış
        }
        
        // Platform status oluştur
        $platformStatus = [];
        foreach ($pendingPlatforms as $platform) {
            $platformStatus[$platform] = [
                'status' => 'pending',
                'post_id' => null,
                'post_url' => null,
                'error' => null,
                'uploaded_at' => null
            ];
        }
        
        // Social queue item oluştur
        // Priority: Negatif position (düşük position = yüksek öncelik)
        // Social scheduler high-to-low sıralıyor, bu yüzden position 1 → priority -1 (en yüksek)
        $position = $video['position'] ?? 999;
        
        // ⏰ Zamanlama hesapla (kuyruk ayarlarına göre)
        $scheduledTime = calculateScheduledTime($queue, $position, $pendingPlatforms);
        
        $queueItem = [
            'queue_id' => 'social_' . substr(uniqid(), -16),
            'job_id' => $jobId,
            'video_path' => $videoPath,
            'platforms' => $pendingPlatforms,
            'platform_status' => $platformStatus,
            'scheduled_time' => $scheduledTime,
            'status' => 'pending',
            'priority' => -$position, // Negatif position = düşük position önce
            'metadata' => [
                'title' => $job['title'] ?? 'Video',
                'description' => $job['description'] ?? '',
                'tags' => $job['tags'] ?? []
            ],
            'platform_metadata' => new stdClass(),
            'created_at' => date('c'),
            'updated_at' => date('c'),
            'retry_count' => 0
        ];
        
        $socialQueue['queue'][] = $queueItem;
        $existingJobIds[] = $jobId;
        $added++;
    }
    
    // Kaydet
    if ($added > 0) {
        file_put_contents($socialQueueFile, json_encode($socialQueue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    return $added;
}

// Benzersiz ID oluştur
function generateId($name) {
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
    $slug = trim($slug, '-');
    return $slug . '-' . substr(uniqid(), -6);
}

/**
 * social_queue.json'dan job_id bazında gerçek platform durumlarını oku.
 * social_history.json'u da kontrol eder (tamamlanmış işlemler oraya taşınır).
 * 
 * Döndürülen yapı: [ job_id => [ platform => [ status, post_url, ... ] ] ]
 */
function loadSocialPlatformStatus() {
    global $dataDir;
    $result = [];

    // Önce history'yi oku, sonra social_queue, sonra queues.json ile override et.
    // Böylece reset sonrası yeniden kuyruğa alınan videolarda eski failed history baskın gelmez.
    $sources = [
        ['file' => $dataDir . '/social_history.json', 'key' => 'history', 'force_override' => false],
        ['file' => $dataDir . '/social_queue.json', 'key' => 'queue', 'force_override' => true],
        ['file' => $dataDir . '/queues.json', 'key' => 'queues', 'force_override' => true, 'nested' => true]
    ];

    foreach ($sources as $source) {
        $file = $source['file'];
        if (!file_exists($file)) continue;
        $raw = json_decode(file_get_contents($file), true);
        
        // queues.json için özel işlem: nested videos array
        $items = [];
        if (isset($source['nested']) && $source['nested']) {
            foreach ($raw[$source['key']] ?? [] as $queue) {
                foreach ($queue['videos'] ?? [] as $video) {
                    $items[] = $video;
                }
            }
        } else {
            $items = $raw[$source['key']] ?? [];
        }
        
        foreach ($items as $item) {
            $jobId = $item['job_id'] ?? null;
            if (!$jobId) continue;
            $platformStatus = $item['platform_status'] ?? [];
            if (!isset($result[$jobId])) {
                $result[$jobId] = [];
            }
            foreach ($platformStatus as $platform => $ps) {
                if ($source['force_override']) {
                    $result[$jobId][$platform] = is_array($ps) ? $ps : ['status' => $ps];
                    continue;
                }

                // History merge: daha güçlü durum baskın gelsin
                $existingStatus = normalizePlatformStatusValue($result[$jobId][$platform]['status'] ?? 'pending');
                $newStatus = extractPlatformStatusValue($ps);
                // Öncelik: success > failed > processing > pending
                $priority = ['success' => 4, 'failed' => 3, 'processing' => 2, 'pending' => 1];
                $existingPrio = $priority[$existingStatus] ?? 0;
                $newPrio = $priority[$newStatus] ?? 0;
                if ($newPrio >= $existingPrio) {
                    $result[$jobId][$platform] = is_array($ps) ? $ps : ['status' => $ps];
                }
            }
        }
    }

    return $result;
}

function normalizePlatformStatusValue($status) {
    if (!is_string($status) || $status === '') {
        return 'pending';
    }
    $status = strtolower($status);
    if ($status === 'uploaded' || $status === 'published') {
        return 'success';
    }
    if ($status === 'queued') {
        return 'pending';
    }
    if ($status === 'uploading') {
        return 'processing';
    }
    return $status;
}

function extractPlatformStatusValue($platformStatus) {
    if (is_array($platformStatus)) {
        return normalizePlatformStatusValue($platformStatus['status'] ?? 'pending');
    }
    return normalizePlatformStatusValue($platformStatus ?? 'pending');
}

function getJobPlatformStatus($job, $platform) {
    if (!is_array($job)) {
        return null;
    }
    if (isset($job['social_upload']['platforms'][$platform])) {
        return $job['social_upload']['platforms'][$platform];
    }
    if ($platform === 'youtube' && isset($job['youtube_upload'])) {
        return $job['youtube_upload'];
    }
    return null;
}

function resolveEffectivePlatformPayload($video, $job, $jobId, $platform, $socialStatus) {
    $queuePlatform = $video['platform_status'][$platform] ?? null;
    $jobPlatform = getJobPlatformStatus($job, $platform);
    $socialPlatform = $socialStatus[$jobId][$platform] ?? null;

    $resolved = $socialPlatform ?? $jobPlatform ?? $queuePlatform ?? 'pending';
    if (!is_array($resolved)) {
        $resolved = ['status' => $resolved];
    }
    $resolved['status'] = extractPlatformStatusValue($resolved);
    return $resolved;
}

function loadSchedulerStatusData() {
    global $schedulerStatusFile;
    $default = [
        'production' => ['running' => false, 'pid' => null, 'started_at' => null, 'stopped_at' => null],
        'social' => ['running' => false, 'pid' => null, 'started_at' => null, 'stopped_at' => null]
    ];

    if (!file_exists($schedulerStatusFile)) {
        return $default;
    }

    $raw = json_decode(file_get_contents($schedulerStatusFile), true);
    if (!is_array($raw)) {
        return $default;
    }

    foreach (['production', 'social'] as $type) {
        if (!isset($raw[$type]) || !is_array($raw[$type])) {
            $raw[$type] = $default[$type];
            continue;
        }
        $raw[$type]['running'] = (bool)($raw[$type]['running'] ?? false);
        $raw[$type]['pid'] = $raw[$type]['pid'] ?? null;
        $raw[$type]['started_at'] = $raw[$type]['started_at'] ?? null;
        $raw[$type]['stopped_at'] = $raw[$type]['stopped_at'] ?? null;
    }

    return $raw;
}

// Scheduler hatalarını yükle (özellikle quota hataları)
function loadSchedulerErrors() {
    global $schedulerErrorsFile;
    
    if (!file_exists($schedulerErrorsFile)) {
        return ['errors' => [], 'quota_blocked' => false, 'unresolved_count' => 0];
    }
    
    $data = json_decode(file_get_contents($schedulerErrorsFile), true);
    if (!is_array($data) || !isset($data['errors'])) {
        return ['errors' => [], 'quota_blocked' => false, 'unresolved_count' => 0];
    }
    
    $errors = $data['errors'];
    $unresolvedErrors = array_filter($errors, fn($e) => !($e['resolved'] ?? false));
    
    // Son 24 saatteki hataları filtrele (daha güvenilir tarih kontrolü)
    $cutoff = new DateTime('-24 hours');
    $recentErrors = array_filter($unresolvedErrors, function($e) use ($cutoff) {
        $timestamp = $e['timestamp'] ?? '';
        if (empty($timestamp)) return false;
        
        try {
            // Parse ISO 8601 timestamp
            $errorTime = new DateTime($timestamp);
            return $errorTime > $cutoff;
        } catch (Exception $ex) {
            return false; // Invalid timestamp, exclude it
        }
    });
    
    // Quota hatalarını recent errors içinden filtrele
    $quotaErrors = array_filter($recentErrors, fn($e) => 
        stripos($e['error_message'] ?? '', 'quota') !== false || 
        stripos($e['error_message'] ?? '', 'exceeded') !== false
    );
    
    return [
        'errors' => array_values(array_slice($recentErrors, 0, 10)), // Son 10 hata
        'quota_blocked' => count($quotaErrors) > 0, // Sadece son 24 saatteki quota hataları
        'unresolved_count' => count($recentErrors), // Sadece son 24 saatteki unresolved
        'quota_error_count' => count($quotaErrors),
        'last_error' => count($recentErrors) > 0 ? reset($recentErrors) : null
    ];
}

// Config'den varsayılan video ayarlarını al
function getDefaultVideoSettings() {
    global $dataDir;
    $configFile = $dataDir . '/config.json';
    
    $defaults = [
        'dimensionPreset' => 'vertical',
        'videoWidth' => 1080,
        'videoHeight' => 1920,
        'visualThemeId' => 'default',
        'visualThemePrompt' => null,
        'subtitleMode' => 'config',
        'subtitlePreset' => 'classic',
        'customSubtitle' => null
    ];
    
    if (file_exists($configFile)) {
        $config = json_decode(file_get_contents($configFile), true);
        if ($config && isset($config['subtitleStyle'])) {
            $defaults['configSubtitle'] = $config['subtitleStyle'];
        }
    }
    
    return $defaults;
}

// Kuyruktan video ayarlarını çöz (subtitle style dahil)
function resolveVideoSettings($queue) {
    global $dataDir;
    
    $settings = $queue['video_settings'] ?? getDefaultVideoSettings();
    
    // Varsayılan değerler
    $videoWidth = $settings['videoWidth'] ?? 1080;
    $videoHeight = $settings['videoHeight'] ?? 1920;
    $visualThemeId = trim((string)($settings['visualThemeId'] ?? 'default'));
    if ($visualThemeId === '') {
        $visualThemeId = 'default';
    }
    $visualThemePrompt = trim((string)($settings['visualThemePrompt'] ?? ''));
    if ($visualThemePrompt === '') {
        $visualThemePrompt = null;
    }
    
    // Altyazı stilini çöz
    $subtitleStyle = null;
    $subtitleMode = $settings['subtitleMode'] ?? 'config';
    
    if ($subtitleMode === 'config') {
        // Config'den al
        $configFile = $dataDir . '/config.json';
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
            if ($config && isset($config['subtitleStyle'])) {
                $subtitleStyle = $config['subtitleStyle'];
                $subtitleStyle['preset'] = 'config';
            }
        }
    } elseif ($subtitleMode === 'preset') {
        // Hazır preset
        $presets = [
            'classic' => ['FontName' => 'Arial', 'FontSize' => 24, 'PrimaryColour' => '#FFFFFF', 'OutlineColour' => '#000000', 'Outline' => 2, 'MarginV' => 60, 'Bold' => 1],
            'neon' => ['FontName' => 'Arial', 'FontSize' => 26, 'PrimaryColour' => '#00FF00', 'OutlineColour' => '#000000', 'Outline' => 2, 'MarginV' => 60, 'Bold' => 1],
            'cinematic' => ['FontName' => 'Arial', 'FontSize' => 22, 'PrimaryColour' => '#F5F5DC', 'OutlineColour' => '#2C2C2C', 'Outline' => 1, 'MarginV' => 80, 'Bold' => 0],
            'bold' => ['FontName' => 'Arial', 'FontSize' => 28, 'PrimaryColour' => '#FFD700', 'OutlineColour' => '#000000', 'Outline' => 3, 'MarginV' => 50, 'Bold' => 1],
            'minimal' => ['FontName' => 'Arial', 'FontSize' => 20, 'PrimaryColour' => '#FFFFFF', 'OutlineColour' => '#333333', 'Outline' => 1, 'MarginV' => 70, 'Bold' => 0],
            'news' => ['FontName' => 'Arial', 'FontSize' => 24, 'PrimaryColour' => '#FFFFFF', 'OutlineColour' => '#CC0000', 'Outline' => 2, 'MarginV' => 55, 'Bold' => 1]
        ];
        $presetName = $settings['subtitlePreset'] ?? 'classic';
        $subtitleStyle = $presets[$presetName] ?? $presets['classic'];
        $subtitleStyle['preset'] = $presetName;
    } elseif ($subtitleMode === 'custom') {
        // Özel ayar
        $subtitleStyle = $settings['customSubtitle'] ?? null;
        if ($subtitleStyle) {
            $subtitleStyle['preset'] = 'custom';
        }
    }
    
    // Fallback
    if (!$subtitleStyle) {
        $subtitleStyle = [
            'FontName' => 'Arial',
            'FontSize' => 24,
            'PrimaryColour' => '#FFFFFF',
            'OutlineColour' => '#000000',
            'Outline' => 2,
            'Shadow' => 1,
            'MarginV' => 60,
            'Bold' => 1,
            'preset' => 'classic'
        ];
    }
    
    return [
        'videoWidth' => $videoWidth,
        'videoHeight' => $videoHeight,
        'subtitleStyle' => $subtitleStyle,
        'visualThemeId' => $visualThemeId,
        'visualThemePrompt' => $visualThemePrompt
    ];
}

// GET istekleri
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';
    $data = loadQueues();
    
    switch ($action) {
        case 'get_queue_stats':
            // Belirli bir kuyruğun istatistiklerini döndür
            $queueId = $_GET['id'] ?? '';
            
            if (empty($queueId)) {
                echo json_encode(['success' => false, 'error' => 'queue_id gerekli']);
                exit;
            }
            
            $targetQueue = null;
            foreach ($data['queues'] as $queue) {
                if ($queue['id'] === $queueId) {
                    $targetQueue = $queue;
                    break;
                }
            }
            
            if (!$targetQueue) {
                echo json_encode(['success' => false, 'error' => 'Kuyruk bulunamadı']);
                exit;
            }
            
            // Platform bazlı istatistikleri hesapla
            $socialStatus = loadSocialPlatformStatus();
            $platformStats = [];
            foreach ($targetQueue['platforms'] as $platform) {
                $platformStats[$platform] = [
                    'published' => 0,
                    'pending' => 0,
                    'failed' => 0,
                    'uploading' => 0,
                    'total' => 0
                ];
            }
            
            $lastUploadTime = null;
            $totalVideos = count($targetQueue['videos'] ?? []);
            
            foreach ($targetQueue['videos'] ?? [] as $video) {
                $jobId = $video['job_id'] ?? null;
                if (!$jobId) continue;
                $job = loadJob($jobId);
                
                foreach ($targetQueue['platforms'] as $platform) {
                    if (!isset($platformStats[$platform])) continue;
                    
                    $platformStats[$platform]['total']++;

                    $effective = resolveEffectivePlatformPayload($video, $job, $jobId, $platform, $socialStatus);
                    $statusValue = $effective['status'] ?? 'pending';

                    if ($statusValue === 'success') {
                        $platformStats[$platform]['published']++;
                        if (isset($effective['uploaded_at'])) {
                            $uploadTime = strtotime($effective['uploaded_at']);
                            if ($uploadTime && (!$lastUploadTime || $uploadTime > $lastUploadTime)) {
                                $lastUploadTime = $uploadTime;
                            }
                        }
                    } elseif ($statusValue === 'pending') {
                        $platformStats[$platform]['pending']++;
                    } elseif ($statusValue === 'failed') {
                        $platformStats[$platform]['failed']++;
                    } elseif ($statusValue === 'processing') {
                        $platformStats[$platform]['uploading']++;
                    } else {
                        $platformStats[$platform]['pending']++;
                    }
                }
            }
            
            // Sıra 1'deki video bilgisini al (current_item)
            $currentItem = null;
            $lastError = null;
            $blockedReason = null;
            
            // Videoları position'a göre sırala
            $videos = $targetQueue['videos'] ?? [];
            usort($videos, function($a, $b) {
                return ($a['position'] ?? 999) - ($b['position'] ?? 999);
            });
            
            // İlk pending veya failed videoyu bul
            foreach ($videos as $video) {
                $jobId = $video['job_id'] ?? null;
                if (!$jobId) continue;
                $job = loadJob($jobId);
                
                // Her platform için durum kontrol et
                foreach ($targetQueue['platforms'] as $platform) {
                    $effective = resolveEffectivePlatformPayload($video, $job, $jobId, $platform, $socialStatus);
                    $status = $effective['status'] ?? 'pending';
                    
                    // pending veya failed durumundaki ilk video = current_item
                    if ($status === 'pending' || $status === 'failed' || $status === 'processing') {
                        $currentItem = [
                            'job_id' => $jobId,
                            'position' => $video['position'] ?? 0,
                            'title' => $job['title'] ?? ($video['title'] ?? 'Başlık yok'),
                            'status' => $status,
                            'platform' => $platform,
                            'thumbnail' => $job['previewUrl'] ?? ($video['thumbnailUrl'] ?? null)
                        ];
                        
                        // Hata varsa al
                        if ($status === 'failed') {
                            $lastError = $effective['error'] ?? null;
                            $blockedReason = $lastError;
                        }
                        
                        break 2; // Her iki döngüden de çık
                    }
                }
            }
            
            // Production durumunu kontrol et
            $productionStatus = null;
            $productionFile = $dataDir . '/production_queue.json';
            if (file_exists($productionFile)) {
                $prodData = json_decode(file_get_contents($productionFile), true);
                $currentProdId = $prodData['current_production'] ?? null;
                
                if ($currentProdId) {
                    foreach ($prodData['production_queue'] ?? [] as $prodItem) {
                        if ($prodItem['prod_queue_id'] === $currentProdId && $prodItem['queue_id'] === $queueId) {
                            $productionStatus = [
                                'status' => $prodItem['status'] ?? 'unknown',
                                'job_id' => $prodItem['job_id'] ?? null,
                                'started_at' => $prodItem['started_at'] ?? null
                            ];
                            break;
                        }
                    }
                }
                
                // Kuyrukta bekleyen üretim var mı?
                $waitingCount = 0;
                foreach ($prodData['production_queue'] ?? [] as $prodItem) {
                    if ($prodItem['queue_id'] === $queueId && $prodItem['status'] === 'waiting') {
                        $waitingCount++;
                    }
                }
                if ($waitingCount > 0 && !$productionStatus) {
                    $productionStatus = [
                        'status' => 'waiting',
                        'waiting_count' => $waitingCount
                    ];
                }
            }
            
            echo json_encode([
                'success' => true,
                'stats' => [
                    'platforms' => $platformStats,
                    'total_videos' => $totalVideos,
                    'is_active' => $targetQueue['is_active'] ?? false,
                    'last_upload' => $lastUploadTime ? date('c', $lastUploadTime) : null,
                    'last_publish' => $targetQueue['last_publish'] ?? null,
                    'resumed_at' => $targetQueue['resumed_at'] ?? null,
                    'paused_at' => $targetQueue['paused_at'] ?? null,
                    'strict_mode' => $targetQueue['strict_mode'] ?? false,
                    'fail_threshold' => $targetQueue['fail_threshold'] ?? 3,
                    'consecutive_fails' => $targetQueue['consecutive_fails'] ?? 0,
                    'current_item' => $currentItem,
                    'last_error' => $lastError,
                    'blocked_reason' => $blockedReason,
                    'production_status' => $productionStatus,
                    'scheduler_status' => loadSchedulerStatusData(),
                    'scheduler_errors' => loadSchedulerErrors()
                ]
            ]);
            break;
        
        case 'list':
            // Her kuyruk için video detaylarını ekle
            $socialStatus = loadSocialPlatformStatus(); // Gerçek platform durumları
            $queuesWithDetails = [];
            foreach ($data['queues'] as $queue) {
                if (isset($queue['platform_settings'])) {
                    $queue['platform_settings'] = normalizePlatformSettings($queue['platform_settings']);
                }
                $videosWithDetails = [];
                foreach ($queue['videos'] ?? [] as $video) {
                    $job = loadJob($video['job_id']);
                    $jobId = $video['job_id'];
                    
                    // Thumbnail URL'sini bul
                    $thumbnailUrl = null;
                    $outputDir = __DIR__ . '/../output/' . $jobId;
                    
                    if (file_exists($outputDir . '/images/hook.png')) {
                        $thumbnailUrl = '/output/' . $jobId . '/images/hook.png';
                    } elseif (file_exists($outputDir . '/thumbnail.png')) {
                        $thumbnailUrl = '/output/' . $jobId . '/thumbnail.png';
                    }

                    // Gerçek platform durumunu social_queue'dan override et
                    $platformStatus = $video['platform_status'] ?? [];
                    if (isset($socialStatus[$jobId])) {
                        foreach ($socialStatus[$jobId] as $platform => $ps) {
                            $platformStatus[$platform] = $ps;
                        }
                    }
                    
                    $videoData = array_merge($video, [
                        'title'           => $job['title'] ?? 'İsimsiz Video',
                        'thumbnailUrl'    => $thumbnailUrl,
                        'job_status'      => $job['status'] ?? 'pending',
                        'scriptId'        => $job['scriptId'] ?? null,
                        'scriptName'      => $job['scriptName'] ?? null,
                        'contentType'     => $job['contentType'] ?? null,
                        'platform_status' => $platformStatus,
                    ]);
                    $videosWithDetails[] = $videoData;
                }
                $queue['videos'] = $videosWithDetails;
                $queuesWithDetails[] = $queue;
            }
            
            echo json_encode([
                'success' => true,
                'queues'  => $queuesWithDetails
            ]);
            break;
            
        case 'get':
            $queueId = $_GET['id'] ?? '';
            $queue = null;
            foreach ($data['queues'] as $q) {
                if ($q['id'] === $queueId) {
                    $queue = $q;
                    break;
                }
            }
            if ($queue) {
                if (isset($queue['platform_settings'])) {
                    $queue['platform_settings'] = normalizePlatformSettings($queue['platform_settings']);
                }
                // Video detaylarını ekle
                $socialStatus = loadSocialPlatformStatus(); // Gerçek platform durumları
                $videosWithDetails = [];
                foreach ($queue['videos'] ?? [] as $video) {
                    $job = loadJob($video['job_id']);
                    $jobId = $video['job_id'];
                    
                    // Thumbnail URL'sini bul (hook.png öncelikli - video kapağı)
                    $thumbnailUrl = null;
                    $outputDir = __DIR__ . '/../output/' . $jobId;
                    
                    // Önce hook.png'yi kontrol et (montaj kapağı)
                    if (file_exists($outputDir . '/images/hook.png')) {
                        $thumbnailUrl = '/output/' . $jobId . '/images/hook.png';
                    } elseif (file_exists($outputDir . '/thumbnail.png')) {
                        $thumbnailUrl = '/output/' . $jobId . '/thumbnail.png';
                    } elseif (file_exists($outputDir . '/thumbnail.jpg')) {
                        $thumbnailUrl = '/output/' . $jobId . '/thumbnail.jpg';
                    }
                    
                    // Video URL'sini bul
                    $videoUrl = null;
                    if (file_exists($outputDir . '/final_video.mp4')) {
                        $videoUrl = '/output/' . $jobId . '/final_video.mp4';
                    }

                    // Gerçek platform durumunu queues.json'dan al (artık social_queue değil)
                    $platformStatus = $video['platform_status'] ?? [];
                    if (isset($socialStatus[$jobId])) {
                        foreach ($socialStatus[$jobId] as $platform => $ps) {
                            $platformStatus[$platform] = $ps;
                        }
                    }
                    
                    // Scheduled time artık direkt video içinde
                    $scheduledTime = $video['scheduled_time'] ?? null;
                    
                    // Eğer scheduled_time null ise, şimdi hesapla
                    if ($scheduledTime === null) {
                        $scheduledTime = calculateScheduledTime($queue, $video['position'] ?? 1, $queue['platforms'] ?? ['youtube']);
                    }
                    
                    $videosWithDetails[] = array_merge($video, [
                        'title'           => $job['title'] ?? 'Video',
                        'previewUrl'      => $job['previewUrl'] ?? null,
                        'thumbnailUrl'    => $thumbnailUrl,
                        'videoUrl'        => $videoUrl,
                        'created_at'      => $job['created_at'] ?? null,
                        'queue_name'      => $queue['name'] ?? null,
                        'scheduled_at'    => $video['scheduled_at'] ?? null,
                        'scheduled_time'  => $scheduledTime,
                        'job_status'      => $job['status'] ?? 'pending',
                        'scriptId'        => $job['scriptId'] ?? null,
                        'scriptName'      => $job['scriptName'] ?? null,
                        'contentType'     => $job['contentType'] ?? null,
                        'platform_status' => $platformStatus,
                    ]);
                }
                $queue['videos'] = $videosWithDetails;
                echo json_encode(['success' => true, 'queue' => $queue]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Kuyruk bulunamadı']);
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Geçersiz action']);
    }
    exit;
}

// POST istekleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $data = loadQueues();
    
    switch ($action) {
        case 'create':
            $name = trim($input['name'] ?? '');
            $platforms = $input['platforms'] ?? [];
            $timezone = $input['timezone'] ?? 'Europe/Istanbul';
            $videoSettings = $input['video_settings'] ?? null;
            
            if (empty($name)) {
                echo json_encode(['success' => false, 'error' => 'Kuyruk ismi gerekli']);
                exit;
            }
            
            if (empty($platforms)) {
                echo json_encode(['success' => false, 'error' => 'En az bir platform seçmelisiniz']);
                exit;
            }
            
            // Video ayarları yoksa config'den varsayılanları al
            if (!$videoSettings) {
                $videoSettings = getDefaultVideoSettings();
            }
            
            // Global schedule artık sadece timezone içeriyor
            $schedule = [
                'timezone' => $timezone
            ];
            
            $queue = [
                'id' => generateId($name),
                'name' => $name,
                'platforms' => $platforms,
                'schedule' => $schedule,
                'video_settings' => $videoSettings,
                'videos' => [],
                'created_at' => date('c'),
                'last_publish' => null,
                'is_active' => true,
                'strict_mode' => false,
                'fail_threshold' => 3,
                'consecutive_fails' => 0
            ];
            
            $data['queues'][] = $queue;
            saveQueues($data);
            
            echo json_encode(['success' => true, 'queue' => $queue]);
            break;
            
        case 'update':
            $queueId = $input['queue_id'] ?? '';
            $updates = $input['updates'] ?? [];
            
            $found = false;
            foreach ($data['queues'] as &$queue) {
                if ($queue['id'] === $queueId) {
                    if (isset($updates['name'])) $queue['name'] = $updates['name'];
                    if (isset($updates['platforms'])) $queue['platforms'] = $updates['platforms'];
                    if (isset($updates['is_active'])) $queue['is_active'] = $updates['is_active'];
                    if (isset($updates['video_settings'])) $queue['video_settings'] = $updates['video_settings'];
                    if (isset($updates['platform_settings'])) $queue['platform_settings'] = normalizePlatformSettings($updates['platform_settings']);
                    if (isset($updates['strict_mode'])) $queue['strict_mode'] = $updates['strict_mode'];
                    if (isset($updates['fail_threshold'])) $queue['fail_threshold'] = $updates['fail_threshold'];
                    
                    // Global schedule artık sadece timezone içerebilir
                    if (isset($updates['schedule'])) {
                        // Sadece timezone'u kabul et, diğer schedule ayarlarını reddet
                        if (isset($updates['schedule']['timezone'])) {
                            if (!isset($queue['schedule'])) {
                                $queue['schedule'] = [];
                            }
                            $queue['schedule']['timezone'] = $updates['schedule']['timezone'];
                        }
                    }
                    
                    $found = true;
                    break;
                }
            }
            
            if ($found) {
                saveQueues($data);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Kuyruk bulunamadı']);
            }
            break;
        
        case 'pause':
            // Kuyruk durdur
            $queueId = $input['queue_id'] ?? '';
            
            $found = false;
            foreach ($data['queues'] as &$queue) {
                if ($queue['id'] === $queueId) {
                    $queue['is_active'] = false;
                    $queue['paused_at'] = date('c');
                    $found = true;
                    break;
                }
            }
            
            if ($found) {
                saveQueues($data);
                echo json_encode(['success' => true, 'message' => 'Kuyruk durduruldu']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Kuyruk bulunamadı']);
            }
            break;
        
        case 'resume':
            // Kuyruk devam ettir
            $queueId = $input['queue_id'] ?? '';
            
            $found = false;
            foreach ($data['queues'] as &$queue) {
                if ($queue['id'] === $queueId) {
                    $queue['is_active'] = true;
                    $queue['resumed_at'] = date('c');
                    unset($queue['paused_at']);
                    $found = true;
                    break;
                }
            }
            
            if ($found) {
                saveQueues($data);
                echo json_encode(['success' => true, 'message' => 'Kuyruk devam ediyor']);
            } else {
                echo json_encode(['success' => false, 'error' => 'Kuyruk bulunamadı']);
            }
            break;
        
        case 'reset_and_resume':
            // Kuyruk durumunu resetle ve devam ettir
            $queueId = $input['queue_id'] ?? '';
            $resetFailed = $input['reset_failed'] ?? true; // Hatalı videoları da sıfırla
            
            $found = false;
            $queueJobIds = [];
            $stats = ['duplicates_removed' => 0, 'positions_fixed' => 0, 'status_reset' => 0, 'jobs_reset' => 0];
            
            foreach ($data['queues'] as &$queue) {
                if ($queue['id'] === $queueId) {
                    $found = true;
                    
                    // 1. Duplicate videoları temizle
                    $uniqueVideos = [];
                    $seenJobIds = [];
                    foreach ($queue['videos'] ?? [] as $video) {
                        $jobId = $video['job_id'];
                        if (!in_array($jobId, $seenJobIds)) {
                            $seenJobIds[] = $jobId;
                            $uniqueVideos[] = $video;
                        } else {
                            $stats['duplicates_removed']++;
                        }
                    }
                    $queue['videos'] = $uniqueVideos;
                    
                    // 2. Position'ları yeniden hesapla
                    foreach ($queue['videos'] as $idx => &$video) {
                        $oldPosition = $video['position'] ?? 0;
                        $video['position'] = $idx + 1;
                        if ($oldPosition !== $video['position']) {
                            $stats['positions_fixed']++;
                        }
                        if (isset($video['job_id'])) {
                            $queueJobIds[] = $video['job_id'];
                        }
                    }
                    unset($video);
                    
                    // 2.5. Scheduled time'ları yeniden hesapla (sadece pending/queued videoları)
                    $pendingPosition = 1;
                    $stats['scheduling_reset'] = 0;
                    foreach ($queue['videos'] as &$video) {
                        $status = $video['status'] ?? 'queued';
                        if (in_array($status, ['queued', 'pending'])) {
                            $oldScheduledTime = $video['scheduled_time'] ?? null;
                            $video['scheduled_time'] = calculateScheduledTime(
                                $queue,
                                $pendingPosition,
                                $queue['platforms']
                            );
                            if ($oldScheduledTime !== $video['scheduled_time']) {
                                $stats['scheduling_reset']++;
                            }
                            $pendingPosition++;
                        }
                    }
                    unset($video);
                    
                    // 3. Job dosyalarından gerçek durumu oku ve "failed" olanları "pending"e çevir
                    foreach ($queue['videos'] as &$video) {
                        $jobId = $video['job_id'];
                        $job = loadJob($jobId);
                        $jobUpdated = false;
                        
                        if (!$job) continue;
                        
                        // Her platform için job dosyasındaki durumu kontrol et
                        foreach ($queue['platforms'] as $platform) {
                            $jobPlatformStatus = getJobPlatformStatus($job, $platform);
                            $queuePlatformStatus = $video['platform_status'][$platform] ?? 'pending';
                            $currentStatus = $jobPlatformStatus !== null
                                ? extractPlatformStatusValue($jobPlatformStatus)
                                : extractPlatformStatusValue($queuePlatformStatus);
                            
                            // processing veya failed durumlarını sıfırla
                            if ($currentStatus === 'processing' || ($resetFailed && $currentStatus === 'failed')) {
                                // Job dosyasındaki durumu sıfırla
                                if (isset($job['social_upload']['platforms'][$platform])) {
                                    $platformData = $job['social_upload']['platforms'][$platform];
                                    // String ise array'e çevir
                                    if (is_string($platformData)) {
                                        $job['social_upload']['platforms'][$platform] = ['status' => 'pending'];
                                    } else {
                                        $job['social_upload']['platforms'][$platform]['status'] = 'pending';
                                        $job['social_upload']['platforms'][$platform]['error'] = null;
                                    }
                                    $job['social_upload']['status'] = 'pending';
                                    $jobUpdated = true;
                                }

                                if ($platform === 'youtube' && isset($job['youtube_upload'])) {
                                    $job['youtube_upload']['status'] = 'pending';
                                    $job['youtube_upload']['error'] = null;
                                    if (isset($job['youtube_upload']['video_id'])) $job['youtube_upload']['video_id'] = null;
                                    if (isset($job['youtube_upload']['video_url'])) $job['youtube_upload']['video_url'] = null;
                                    $jobUpdated = true;
                                }
                                
                                // Queue'daki platform_status'u da güncelle
                                if (!isset($video['platform_status'][$platform]) || is_string($video['platform_status'][$platform])) {
                                    $video['platform_status'][$platform] = [
                                        'status' => 'pending',
                                        'error' => null,
                                        'post_id' => null,
                                        'post_url' => null,
                                        'uploaded_at' => null
                                    ];
                                } else {
                                    $video['platform_status'][$platform]['status'] = 'pending';
                                    $video['platform_status'][$platform]['error'] = null;
                                    if (isset($video['platform_status'][$platform]['post_id'])) $video['platform_status'][$platform]['post_id'] = null;
                                    if (isset($video['platform_status'][$platform]['post_url'])) $video['platform_status'][$platform]['post_url'] = null;
                                    if (isset($video['platform_status'][$platform]['uploaded_at'])) $video['platform_status'][$platform]['uploaded_at'] = null;
                                }
                                $stats['status_reset']++;
                            }
                        }
                        
                        // Video'nun overall status'unu da kontrol et
                        $videoStatus = $video['status'] ?? '';
                        $jobStatus = $job['social_upload']['status'] ?? ($job['youtube_upload']['status'] ?? '');
                        if ($videoStatus === 'processing' || $jobStatus === 'processing' ||
                            ($resetFailed && ($videoStatus === 'failed' || $jobStatus === 'failed'))) {
                            $video['status'] = 'queued';
                            if ($job && isset($job['social_upload'])) {
                                $job['social_upload']['status'] = 'pending';
                                $jobUpdated = true;
                            }
                            if ($job && isset($job['youtube_upload'])) {
                                $job['youtube_upload']['status'] = 'pending';
                                $job['youtube_upload']['error'] = null;
                                $jobUpdated = true;
                            }
                        }
                        
                        // Job dosyasını kaydet
                        if ($jobUpdated && $job) {
                            saveJob($jobId, $job);
                            $stats['jobs_reset']++;
                        }
                    }
                    unset($video);
                    
                    // 4. Kuyruğu aktifleştir
                    $queue['is_active'] = true;
                    $queue['resumed_at'] = date('c');
                    $queue['consecutive_fails'] = 0; // Fail counter'ı sıfırla
                    unset($queue['paused_at']);
                    
                    break;
                }
            }
            
            if ($found) {
                saveQueues($data);
                
                // 5. social_queue.json'daki processing/failed statuslarını da resetle
                $socialQueueFile = $dataDir . '/social_queue.json';
                if (file_exists($socialQueueFile)) {
                    $socialQueue = json_decode(file_get_contents($socialQueueFile), true);
                    if ($socialQueue && isset($socialQueue['queue'])) {
                        foreach ($socialQueue['queue'] as &$item) {
                            $itemJobId = $item['job_id'] ?? '';
                            if (!$itemJobId || !in_array($itemJobId, $queueJobIds, true)) {
                                continue;
                            }
                            $itemUpdated = false;
                            foreach ($item['platform_status'] ?? [] as &$ps) {
                                $psStatus = extractPlatformStatusValue($ps);
                                if ($psStatus === 'processing' || ($resetFailed && $psStatus === 'failed')) {
                                    if (!is_array($ps)) {
                                        $ps = ['status' => 'pending'];
                                    } else {
                                        $ps['status'] = 'pending';
                                    }
                                    $ps['error'] = null;
                                    $ps['post_id'] = null;
                                    $ps['post_url'] = null;
                                    $ps['uploaded_at'] = null;
                                    $itemUpdated = true;
                                    $stats['status_reset']++;
                                }
                            }
                            unset($ps);
                            if ($itemUpdated) {
                                $item['status'] = 'pending';
                                $item['retry_count'] = 0;
                                $item['last_error'] = null;
                                $item['updated_at'] = date('c');
                            }
                        }
                        unset($item);
                        file_put_contents($socialQueueFile, json_encode($socialQueue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    }
                }
                
                // 6. Pending videoları social_queue.json'a ekle (eğer yoksa)
                $addedToSocialQueue = 0;
                foreach ($data['queues'] as $q) {
                    if ($q['id'] === $queueId) {
                        $addedToSocialQueue = addPendingVideosToSocialQueue($q, $dataDir);
                        break;
                    }
                }
                $stats['added_to_social_queue'] = $addedToSocialQueue;
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Kuyruk resetlendi ve devam ediyor',
                    'stats' => $stats
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Kuyruk bulunamadı']);
            }
            break;
            
        case 'reset_scheduling':
            // Sadece zamanlamayı resetler (status'leri değiştirmez)
            $queueId = $input['queue_id'] ?? '';
            
            $found = false;
            $stats = ['scheduling_reset' => 0];
            
            foreach ($data['queues'] as &$queue) {
                if ($queue['id'] === $queueId) {
                    $found = true;
                    
                    // Pending/queued videoların scheduled_time'ını yeniden hesapla
                    $pendingPosition = 1;
                    foreach ($queue['videos'] as &$video) {
                        $status = $video['status'] ?? 'queued';
                        if (in_array($status, ['queued', 'pending'])) {
                            $video['scheduled_time'] = calculateScheduledTime(
                                $queue,
                                $pendingPosition,
                                $queue['platforms']
                            );
                            $stats['scheduling_reset']++;
                            $pendingPosition++;
                        }
                    }
                    unset($video);
                    
                    break;
                }
            }
            
            saveQueues($data);
            
            if (!$found) {
                echo json_encode(['success' => false, 'error' => 'Kuyruk bulunamadı']);
            } else {
                echo json_encode([
                    'success' => true,
                    'message' => "{$stats['scheduling_reset']} videonun zamanlaması güncellendi",
                    'stats' => $stats
                ]);
            }
            break;
            
        case 'delete':
            $queueId = $input['queue_id'] ?? '';
            
            $newQueues = [];
            $found = false;
            $runtimeStats = ['social_removed' => 0, 'production_removed' => 0];
            $contentSync = 0;
            foreach ($data['queues'] as $queue) {
                if ($queue['id'] === $queueId) {
                    $found = true;
                    // Videoların queue_status'unu temizle
                    foreach ($queue['videos'] ?? [] as $video) {
                        $jobId = $video['job_id'] ?? '';
                        if ($jobId !== '') {
                            $removed = removeJobFromRuntimeQueues($jobId);
                            $runtimeStats['social_removed'] += ($removed['social_removed'] ?? 0);
                            $runtimeStats['production_removed'] += ($removed['production_removed'] ?? 0);
                            $contentSync += clearContentPoolByJobId($jobId);
                        }
                        $job = loadJob($video['job_id']);
                        if ($job) {
                            unset($job['queue_status']);
                            saveJob($video['job_id'], $job);
                        }
                    }
                } else {
                    $newQueues[] = $queue;
                }
            }
            
            if ($found) {
                $data['queues'] = $newQueues;
                saveQueues($data);
                echo json_encode([
                    'success' => true,
                    'sync' => array_merge($runtimeStats, ['content_updated' => $contentSync])
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Kuyruk bulunamadı']);
            }
            break;
            
        case 'add_video':
            $queueId = $input['queue_id'] ?? '';
            $jobId = $input['job_id'] ?? '';
            
            if (empty($queueId) || empty($jobId)) {
                echo json_encode(['success' => false, 'error' => 'queue_id ve job_id gerekli']);
                exit;
            }
            
            // Job var mı kontrol et
            $job = loadJob($jobId);
            if (!$job) {
                echo json_encode(['success' => false, 'error' => 'Video bulunamadı']);
                exit;
            }
            
            $found = false;
            foreach ($data['queues'] as &$queue) {
                if ($queue['id'] === $queueId) {
                    // Video zaten kuyrukta mı kontrol et
                    foreach ($queue['videos'] ?? [] as $v) {
                        if ($v['job_id'] === $jobId) {
                            echo json_encode(['success' => false, 'error' => 'Video zaten bu kuyrukta']);
                            exit;
                        }
                    }
                    
                    // Platform durumlarını oluştur
                    $platformStatus = [];
                    foreach ($queue['platforms'] as $platform) {
                        $platformStatus[$platform] = [
                            'status' => 'pending',
                            'error' => null,
                            'post_id' => null,
                            'post_url' => null,
                            'uploaded_at' => null
                        ];
                    }
                    
                    // Position hesapla - SADECE pending/queued videoları say (published olanları atla)
                    $pendingVideos = array_filter($queue['videos'] ?? [], function($v) {
                        $status = $v['status'] ?? 'queued';
                        return in_array($status, ['queued', 'pending']);
                    });
                    $position = count($pendingVideos) + 1;
                    
                    // Scheduled time hesapla (kuyruk ayarlarına göre)
                    $scheduledTime = calculateScheduledTime($queue, $position, $queue['platforms']);
                    
                    $videoEntry = [
                        'job_id' => $jobId,
                        'added_at' => date('c'),
                        'status' => 'queued',
                        'platform_status' => $platformStatus,
                        'position' => $position,
                        'scheduled_time' => $scheduledTime,
                        'retry_count' => 0,
                        'last_error' => null
                    ];
                    
                    $queue['videos'][] = $videoEntry;
                    $found = true;
                    
                    // Job'a queue_status ekle
                    $job['queue_status'] = [
                        'queue_id' => $queueId,
                        'queue_name' => $queue['name'],
                        'status' => 'queued',
                        'added_at' => date('c')
                    ];
                    $queueVideoSettings = $queue['video_settings'] ?? [];
                    if (empty($job['visual_theme_id']) && !empty($queueVideoSettings['visualThemeId'])) {
                        $job['visual_theme_id'] = $queueVideoSettings['visualThemeId'];
                    }
                    if (empty($job['visual_theme_prompt']) && !empty($queueVideoSettings['visualThemePrompt'])) {
                        $job['visual_theme_prompt'] = $queueVideoSettings['visualThemePrompt'];
                    }
                    if (empty($job['bgm_track_id']) && (($job['music_mode'] ?? 'off') === 'auto')) {
                        $scriptCategory = resolveScriptCategory($job, $job['contentType'] ?? 'genel');
                        $selectedMusic = selectMusicTrackForCategory($baseDir, $scriptCategory);
                        if ($selectedMusic) {
                            $job['bgm_category_id'] = $selectedMusic['categoryId'];
                            $job['bgm_track_id'] = $selectedMusic['id'];
                            $job['bgm_track_name'] = $selectedMusic['name'];
                            $job['bgm_file'] = $selectedMusic['file'];
                            if (!isset($job['bgm_volume_db'])) {
                                $job['bgm_volume_db'] = (float)($selectedMusic['volumeDb'] ?? -22.0);
                            }
                        }
                    }
                    saveJob($jobId, $job);
                    
                    break;
                }
            }
            
            if ($found) {
                saveQueues($data);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Kuyruk bulunamadı']);
            }
            break;
            
        case 'remove_video':
            $queueId = $input['queue_id'] ?? '';
            $jobId = $input['job_id'] ?? $input['video_id'] ?? '';
            
            $found = false;
            $runtimeStats = ['social_removed' => 0, 'production_removed' => 0];
            $contentSync = 0;
            foreach ($data['queues'] as &$queue) {
                if ($queue['id'] === $queueId) {
                    $newVideos = [];
                    foreach ($queue['videos'] ?? [] as $video) {
                        if ($video['job_id'] === $jobId) {
                            $found = true;
                            $runtimeStats = removeJobFromRuntimeQueues($jobId);
                            $contentSync = clearContentPoolByJobId($jobId);
                            // Job'dan queue_status'u kaldır
                            $job = loadJob($jobId);
                            if ($job) {
                                unset($job['queue_status']);
                                saveJob($jobId, $job);
                            }
                        } else {
                            $newVideos[] = $video;
                        }
                    }
                    $queue['videos'] = $newVideos;
                    normalizeQueueVideoOrder($queue);
                    break;
                }
            }
            
            if ($found) {
                saveQueues($data);
                echo json_encode([
                    'success' => true,
                    'sync' => array_merge($runtimeStats, ['content_updated' => $contentSync])
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Video kuyrukta bulunamadı']);
            }
            break;
            
        case 'reorder':
            $queueId = $input['queue_id'] ?? '';
            $videoOrder = $input['video_order'] ?? []; // job_id listesi
            
            foreach ($data['queues'] as &$queue) {
                if ($queue['id'] === $queueId) {
                    $newVideos = [];
                    $pendingPosition = 1; // Pending videoların pozisyonu için sayaç
                    
                    foreach ($videoOrder as $position => $jobId) {
                        foreach ($queue['videos'] as $video) {
                            if ($video['job_id'] === $jobId) {
                                $video['position'] = $position;
                                
                                // Eğer video pending/queued durumundaysa scheduled_time'ı yeniden hesapla
                                $status = $video['status'] ?? 'queued';
                                if (in_array($status, ['queued', 'pending'])) {
                                    $video['scheduled_time'] = calculateScheduledTime(
                                        $queue, 
                                        $pendingPosition, 
                                        $queue['platforms']
                                    );
                                    $pendingPosition++;
                                }
                                
                                $newVideos[] = $video;
                                break;
                            }
                        }
                    }
                    $queue['videos'] = $newVideos;
                    break;
                }
            }
            
            saveQueues($data);
            echo json_encode(['success' => true]);
            break;
            
        case 'mark_published':
            $queueId = $input['queue_id'] ?? '';
            $jobId = $input['job_id'] ?? '';
            $platform = $input['platform'] ?? '';
            $postUrl = $input['post_url'] ?? null;
            
            foreach ($data['queues'] as &$queue) {
                if ($queue['id'] === $queueId) {
                    foreach ($queue['videos'] as &$video) {
                        if ($video['job_id'] === $jobId) {
                            $video['platform_status'][$platform] = 'published';
                            if ($postUrl) {
                                $video['post_urls'][$platform] = $postUrl;
                            }
                            
                            // Tüm platformlar yayınlandı mı kontrol et
                            $allPublished = true;
                            foreach ($video['platform_status'] as $status) {
                                if ($status !== 'published') {
                                    $allPublished = false;
                                    break;
                                }
                            }
                            if ($allPublished) {
                                $video['status'] = 'published';
                            }
                            
                            // Job'u güncelle
                            $job = loadJob($jobId);
                            if ($job) {
                                $job['queue_status']['status'] = $video['status'];
                                $job['queue_status']['platform_status'] = $video['platform_status'];
                                saveJob($jobId, $job);
                            }
                            
                            break;
                        }
                    }
                    $queue['last_publish'] = date('c');
                    break;
                }
            }
            
            saveQueues($data);
            echo json_encode(['success' => true]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Geçersiz action: ' . $action]);
    }
    exit;
}

echo json_encode(['success' => false, 'error' => 'Desteklenmeyen HTTP metodu']);
