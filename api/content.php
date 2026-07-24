<?php
/**
 * Content API
 * 
 * İçerik havuzu CRUD operasyonları
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$CONTENT_POOL_FILE = __DIR__ . '/../data/content_pool.json';
$JOBS_DIR = __DIR__ . '/../data/jobs';
$QUEUES_FILE = __DIR__ . '/../data/queues.json';
$SOCIAL_QUEUE_FILE = __DIR__ . '/../data/social_queue.json';
$PRODUCTION_QUEUE_FILE = __DIR__ . '/../data/production_queue.json';
$CONFIG_FILE = __DIR__ . '/../data/config.json';
$SCRIPTS_FILE = __DIR__ . '/../data/scripts.json';
$BASE_DIR = dirname(__DIR__);
require_once __DIR__ . '/music_helpers.php';

// Helper functions
function loadContentPool() {
    global $CONTENT_POOL_FILE;
    
    if (!file_exists($CONTENT_POOL_FILE)) {
        return ['content' => [], 'metadata' => []];
    }
    
    $json = file_get_contents($CONTENT_POOL_FILE);
    return json_decode($json, true) ?: ['content' => [], 'metadata' => []];
}

function saveContentPool($data) {
    global $CONTENT_POOL_FILE;
    
    $data['metadata']['last_updated'] = gmdate('Y-m-d\TH:i:s\Z');
    $data['metadata']['total_items'] = count($data['content'] ?? []);
    
    file_put_contents($CONTENT_POOL_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function generateContentId($url) {
    return 'content_' . substr(md5($url), 0, 12);
}

// Kuyruk bilgilerini yükle
function loadQueue($queue_id) {
    global $QUEUES_FILE;
    
    if (!file_exists($QUEUES_FILE)) return null;
    
    $data = json_decode(file_get_contents($QUEUES_FILE), true);
    foreach ($data['queues'] ?? [] as $queue) {
        if ($queue['id'] === $queue_id) {
            return $queue;
        }
    }
    return null;
}

// Config'den varsayılan altyazı stilini al
function getDefaultSubtitleStyle() {
    global $CONFIG_FILE;
    
    $default = [
        'FontName' => 'Arial',
        'FontSize' => 24,
        'PrimaryColour' => '#FFFFFF',
        'OutlineColour' => '#000000',
        'Outline' => 2,
        'Shadow' => 1,
        'MarginV' => 60,
        'Bold' => 1,
        'preset' => 'config'
    ];
    
    if (file_exists($CONFIG_FILE)) {
        $config = json_decode(file_get_contents($CONFIG_FILE), true);
        if ($config && isset($config['subtitleStyle'])) {
            return array_merge($default, $config['subtitleStyle'], ['preset' => 'config']);
        }
    }
    
    return $default;
}

// Kuyruktan video ayarlarını çöz
function resolveVideoSettingsFromQueue($queue) {
    global $CONFIG_FILE;
    
    $settings = $queue['video_settings'] ?? null;
    
    // Varsayılan değerler
    $videoWidth = 1080;
    $videoHeight = 1920;
    $subtitleStyle = null;
    $visualThemeId = 'default';
    $visualThemePrompt = null;
    
    if ($settings) {
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
        
        $subtitleMode = $settings['subtitleMode'] ?? 'config';
        
        if ($subtitleMode === 'config') {
            $subtitleStyle = getDefaultSubtitleStyle();
        } elseif ($subtitleMode === 'preset') {
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
        } elseif ($subtitleMode === 'custom' && isset($settings['customSubtitle'])) {
            $subtitleStyle = $settings['customSubtitle'];
            $subtitleStyle['preset'] = 'custom';
        }
    }
    
    // Fallback to config
    if (!$subtitleStyle) {
        $subtitleStyle = getDefaultSubtitleStyle();
    }
    
    return [
        'videoWidth' => $videoWidth,
        'videoHeight' => $videoHeight,
        'subtitleStyle' => $subtitleStyle,
        'visualThemeId' => $visualThemeId,
        'visualThemePrompt' => $visualThemePrompt
    ];
}

function findScriptById($scriptId) {
    global $SCRIPTS_FILE;
    if (!file_exists($SCRIPTS_FILE)) {
        return null;
    }

    $data = json_decode(file_get_contents($SCRIPTS_FILE), true);
    $scripts = $data['scripts'] ?? [];
    foreach ($scripts as $script) {
        if (($script['id'] ?? '') === $scriptId) {
            return $script;
        }
    }
    return null;
}

function loadQueuesData() {
    global $QUEUES_FILE;
    if (!file_exists($QUEUES_FILE)) {
        return ['queues' => []];
    }
    $data = json_decode(file_get_contents($QUEUES_FILE), true);
    return is_array($data) ? $data : ['queues' => []];
}

function saveQueuesData($data) {
    global $QUEUES_FILE;
    file_put_contents($QUEUES_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function saveJobData($jobId, $jobData) {
    global $JOBS_DIR;
    $jobFile = $JOBS_DIR . '/' . $jobId . '.json';
    if (file_exists($jobFile)) {
        file_put_contents($jobFile, json_encode($jobData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
}

function removeJobFromSocialQueue($jobId) {
    global $SOCIAL_QUEUE_FILE;
    if (!file_exists($SOCIAL_QUEUE_FILE)) {
        return 0;
    }

    $data = json_decode(file_get_contents($SOCIAL_QUEUE_FILE), true);
    if (!is_array($data)) {
        $data = ['queue' => []];
    }

    $queue = $data['queue'] ?? [];
    $before = count($queue);
    $data['queue'] = array_values(array_filter($queue, function($item) use ($jobId) {
        return ($item['job_id'] ?? '') !== $jobId;
    }));
    $removed = $before - count($data['queue']);

    if ($removed > 0) {
        file_put_contents($SOCIAL_QUEUE_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    return $removed;
}

function removeJobFromProductionQueue($jobId) {
    global $PRODUCTION_QUEUE_FILE;
    if (!file_exists($PRODUCTION_QUEUE_FILE)) {
        return 0;
    }

    $data = json_decode(file_get_contents($PRODUCTION_QUEUE_FILE), true);
    if (!is_array($data)) {
        $data = ['queue' => []];
    }

    $removed = 0;
    foreach (['queue', 'production_queue'] as $key) {
        $items = $data[$key] ?? [];
        $before = count($items);
        $data[$key] = array_values(array_filter($items, function($item) use ($jobId) {
            return ($item['job_id'] ?? '') !== $jobId;
        }));
        $removed += ($before - count($data[$key]));
        if (!empty($data[$key])) {
            foreach ($data[$key] as $i => &$item) {
                $item['position'] = $i + 1;
            }
            unset($item);
        }
    }

    if (($data['current_job'] ?? null) === $jobId) {
        $data['current_job'] = null;
        $removed++;
    }

    if ($removed > 0) {
        if (!isset($data['metadata']) || !is_array($data['metadata'])) {
            $data['metadata'] = [];
        }
        $data['metadata']['last_updated'] = date('c');
        file_put_contents($PRODUCTION_QUEUE_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    return $removed;
}

function removeJobFromQueuesAndClearJobStatus($jobId) {
    global $JOBS_DIR;
    $data = loadQueuesData();
    $removed = 0;
    $updated = false;

    foreach ($data['queues'] as &$queue) {
        $videos = $queue['videos'] ?? [];
        $before = count($videos);
        $queue['videos'] = array_values(array_filter($videos, function($video) use ($jobId) {
            return ($video['job_id'] ?? '') !== $jobId;
        }));
        $removed += ($before - count($queue['videos']));
        if ($before !== count($queue['videos'])) {
            $updated = true;
            foreach ($queue['videos'] as $i => &$video) {
                $video['position'] = $i + 1;
            }
            unset($video);
        }
    }
    unset($queue);

    if ($updated) {
        saveQueuesData($data);
    }

    $jobFile = $JOBS_DIR . '/' . $jobId . '.json';
    if (file_exists($jobFile)) {
        $job = json_decode(file_get_contents($jobFile), true);
        if (is_array($job) && isset($job['queue_status'])) {
            unset($job['queue_status']);
            saveJobData($jobId, $job);
        }
    }

    $runtime = [
        'social_removed' => removeJobFromSocialQueue($jobId),
        'production_removed' => removeJobFromProductionQueue($jobId)
    ];

    return ['queues_removed' => $removed, 'runtime' => $runtime];
}

// GET: İçerik listesi
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    if (isset($_GET['list'])) {
        // Tüm içerikleri listele
        $pool = loadContentPool();
        $content_list = $pool['content'] ?? [];
        
        // Filtrele (status)
        if (isset($_GET['status'])) {
            $status = $_GET['status'];
            $content_list = array_filter($content_list, function($c) use ($status) {
                return ($c['status'] ?? 'pending') === $status;
            });
            $content_list = array_values($content_list); // Re-index
        }
        
        // Sırala (tarihe göre - en yeni en üstte)
        usort($content_list, function($a, $b) {
            return strtotime($b['discovered_at'] ?? '') - strtotime($a['discovered_at'] ?? '');
        });
        
        echo json_encode([
            'success' => true,
            'content' => $content_list,
            'total' => count($content_list)
        ]);
        exit;
    }
    
    if (isset($_GET['id'])) {
        // Tek içerik detayı
        $id = $_GET['id'];
        $pool = loadContentPool();
        $content_list = $pool['content'] ?? [];
        
        $content = null;
        foreach ($content_list as $c) {
            if ($c['id'] === $id) {
                $content = $c;
                break;
            }
        }
        
        if ($content) {
            echo json_encode(['success' => true, 'content' => $content]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'İçerik bulunamadı']);
        }
        exit;
    }
    
    // Varsayılan: stats
    $pool = loadContentPool();
    $content_list = $pool['content'] ?? [];
    
    $stats = [
        'total' => count($content_list),
        'pending' => 0,
        'processing' => 0,
        'completed' => 0,
        'failed' => 0
    ];
    
    foreach ($content_list as $c) {
        $status = $c['status'] ?? 'pending';
        if (isset($stats[$status])) {
            $stats[$status]++;
        }
    }
    
    echo json_encode(['success' => true, 'stats' => $stats]);
    exit;
}

// POST: Yeni işlemler
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    // Havuzu temizle
    if ($action === 'clear_all') {
        $pool = [
            'content' => [],
            'metadata' => [
                'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
                'last_updated' => gmdate('Y-m-d\TH:i:s\Z'),
                'total_items' => 0,
                'version' => '1.0'
            ]
        ];
        saveContentPool($pool);
        
        echo json_encode(['success' => true, 'message' => 'İçerik havuzu temizlendi']);
        exit;
    }
    
    // Manuel URL ekleme
    if ($action === 'add') {
        $url = $input['url'] ?? '';
        $title = $input['title'] ?? '';
        
        if (empty($url)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'URL gerekli']);
            exit;
        }
        
        // URL validation
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Geçersiz URL formatı']);
            exit;
        }
        
        $pool = loadContentPool();
        $content_list = $pool['content'] ?? [];
        
        // Duplicate kontrolü
        foreach ($content_list as $c) {
            if ($c['url'] === $url) {
                http_response_code(409);
                echo json_encode(['success' => false, 'error' => 'Bu URL zaten mevcut']);
                exit;
            }
        }
        
        // Yeni içerik oluştur
        $content_id = generateContentId($url);
        $new_content = [
            'id' => $content_id,
            'url' => $url,
            'title' => $title ?: 'Manuel Eklenen İçerik',
            'source' => 'Manuel',
            'source_type' => 'manual',
            'discovered_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'published_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'status' => 'pending',
            'processed_job_id' => null,
            'metadata' => [
                'keywords' => [],
                'category' => 'genel',
                'description' => ''
            ]
        ];
        
        $pool['content'][] = $new_content;
        saveContentPool($pool);
        
        echo json_encode(['success' => true, 'content' => $new_content]);
        exit;
    }
    
    // Batch processing
    if ($action === 'process') {
        $content_ids = $input['content_ids'] ?? [];
        
        if (empty($content_ids)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Content ID listesi gerekli']);
            exit;
        }
        
        // Python batch processor'ı çağır
        $ids_str = implode(' ', $content_ids);
        $cmd = "start /B cmd /c python python/content/batch_processor.py $ids_str";
        
        exec($cmd, $output, $return_code);
        
        echo json_encode([
            'success' => true,
            'message' => count($content_ids) . ' içerik pipeline\'a gönderildi',
            'content_ids' => $content_ids
        ]);
        exit;
    }
    
    // İçerikten Job oluştur (kuyruğa eklemek için)
    if ($action === 'create_job') {
        $content_id = $input['content_id'] ?? '';
        $queue_id = $input['queue_id'] ?? '';
        $scriptId = trim((string)($input['scriptId'] ?? ''));
        $contentType = trim((string)($input['contentType'] ?? ''));
        $musicMode = normalizeMusicMode($input['music_mode'] ?? 'off');
        $bgmVolumeDb = (float)($input['bgm_volume_db'] ?? -22.0);
        
        if (empty($content_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Content ID gerekli']);
            exit;
        }

        if ($scriptId === '') {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Script seçimi zorunlu']);
            exit;
        }

        $selectedScript = findScriptById($scriptId);
        if (!$selectedScript) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Seçilen script bulunamadı']);
            exit;
        }

        if ($contentType === '') {
            $contentType = trim((string)($selectedScript['contentType'] ?? 'genel'));
        }
        $contentType = strtolower($contentType);
        $scriptCategoryId = resolveScriptCategory($selectedScript, $contentType);
        $selectedMusic = null;
        if ($musicMode === 'auto') {
            $selectedMusic = selectMusicTrackForCategory($BASE_DIR, $scriptCategoryId);
        }
        
        // İçeriği bul
        $pool = loadContentPool();
        $content_list = $pool['content'] ?? [];
        $content = null;
        
        foreach ($content_list as &$c) {
            if ($c['id'] === $content_id) {
                $content = &$c;
                break;
            }
        }
        
        if (!$content) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'İçerik bulunamadı']);
            exit;
        }
        
        // Kuyruk bilgilerini al ve video ayarlarını çöz
        $queue = null;
        $videoSettings = [
            'videoWidth' => 1080,
            'videoHeight' => 1920,
            'subtitleStyle' => getDefaultSubtitleStyle(),
            'visualThemeId' => 'default',
            'visualThemePrompt' => null
        ];
        
        if (!empty($queue_id)) {
            $queue = loadQueue($queue_id);
            if ($queue) {
                $videoSettings = resolveVideoSettingsFromQueue($queue);
            }
        }
        
        // Job ID: uniqid formatı (eski sistemle uyumlu)
        $job_id = uniqid('job_', true);
        
        // URL'den basit başlık oluştur (content'te yoksa)
        $title = $content['title'] ?? '';
        if (empty($title)) {
            $parsedUrl = parse_url($content['url']);
            $path = $parsedUrl['path'] ?? '';
            $title = basename($path);
            $title = preg_replace('/[^a-zA-Z0-9\s-]/', ' ', urldecode($title));
            $title = ucfirst(trim($title)) ?: 'Yeni Video';
        }
        
        // Eski job formatıyla tam uyumlu job verisi
        $job_data = [
            'id' => $job_id,
            'url' => $content['url'],
            'template' => 'short_haber',
            'scriptId' => $scriptId,
            'scriptName' => $selectedScript['name'] ?? '',
            'contentType' => $contentType,
            'videoWidth' => $videoSettings['videoWidth'],
            'videoHeight' => $videoSettings['videoHeight'],
            'subtitleStyle' => $videoSettings['subtitleStyle'],
            'visual_theme_id' => $videoSettings['visualThemeId'] ?? 'default',
            'visual_theme_prompt' => $videoSettings['visualThemePrompt'] ?? null,
            'music_mode' => $musicMode,
            'bgm_category_id' => $scriptCategoryId,
            'bgm_track_id' => $selectedMusic['id'] ?? null,
            'bgm_track_name' => $selectedMusic['name'] ?? null,
            'bgm_file' => $selectedMusic['file'] ?? null,
            'bgm_volume_db' => $selectedMusic ? (float)($selectedMusic['volumeDb'] ?? $bgmVolumeDb) : $bgmVolumeDb,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'previewUrl' => '',
            'subtitles' => '',
            'error' => '',
            'title' => $title,
            'content_id' => $content_id,
            'source_type' => $content['source_type'] ?? 'manual',
            'queue_status' => $queue ? [
                'queue_id' => $queue_id,
                'queue_name' => $queue['name'] ?? '',
                'status' => 'queued',
                'added_at' => date('c')
            ] : null
        ];
        
        // Düz dosya formatında kaydet (klasör değil)
        file_put_contents($JOBS_DIR . '/' . $job_id . '.json', json_encode($job_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // Output klasörü oluştur
        $outputDir = __DIR__ . '/../output/' . $job_id;
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0777, true);
        }
        
        // Manuel sistem: Pipeline'ı direkt başlat
        $pythonCmd = 'python';
        $pythonScript = __DIR__ . '/../python/pipeline.py';
        $configFile = __DIR__ . '/../data/config.json';
        $url = $content['url'];
        $template = 'short_haber';
        
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $cmd = "start /B $pythonCmd \"$pythonScript\" \"$job_id\" \"$url\" \"$template\" \"$configFile\" > \"$outputDir/log.txt\" 2>&1";
        } else {
            $cmd = "$pythonCmd \"$pythonScript\" \"$job_id\" \"$url\" \"$template\" \"$configFile\" > \"$outputDir/log.txt\" 2>&1 &";
        }
        
        pclose(popen($cmd, 'r'));
        
        // İçerik durumunu güncelle
        $content['status'] = 'processing';
        $content['processed_job_id'] = $job_id;
        saveContentPool($pool);
        
        echo json_encode([
            'success' => true,
            'job_id' => $job_id,
            'message' => 'Video üretimi başlatıldı'
        ]);
        exit;
    }
    
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Geçersiz action']);
    exit;
}

// DELETE: İçerik silme
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? $_GET['id'] ?? '';
    
    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Content ID gerekli']);
        exit;
    }
    
    $pool = loadContentPool();
    $content_list = $pool['content'] ?? [];
    
    $found = false;
    $new_list = [];
    
    foreach ($content_list as $c) {
        if ($c['id'] === $id) {
            $found = true;
            // Sil (listeye ekleme)
        } else {
            $new_list[] = $c;
        }
    }
    
    if ($found) {
        $deletedItem = null;
        foreach ($content_list as $c) {
            if ($c['id'] === $id) {
                $deletedItem = $c;
                break;
            }
        }

        $pool['content'] = $new_list;
        saveContentPool($pool);

        $sync = null;
        $linkedJobId = $deletedItem['processed_job_id'] ?? '';
        if (!empty($linkedJobId)) {
            $sync = removeJobFromQueuesAndClearJobStatus($linkedJobId);
        }

        echo json_encode([
            'success' => true,
            'message' => 'İçerik silindi',
            'sync' => $sync
        ]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'İçerik bulunamadı']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
