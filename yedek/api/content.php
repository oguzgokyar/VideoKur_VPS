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
$CONFIG_FILE = __DIR__ . '/../data/config.json';

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
    
    if ($settings) {
        $videoWidth = $settings['videoWidth'] ?? 1080;
        $videoHeight = $settings['videoHeight'] ?? 1920;
        
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
        'subtitleStyle' => $subtitleStyle
    ];
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
        
        if (empty($content_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Content ID gerekli']);
            exit;
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
            'subtitleStyle' => getDefaultSubtitleStyle()
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
            'scriptId' => '',
            'videoWidth' => $videoSettings['videoWidth'],
            'videoHeight' => $videoSettings['videoHeight'],
            'subtitleStyle' => $videoSettings['subtitleStyle'],
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
        
        // İçerik durumunu güncelle
        $content['status'] = 'processing';
        $content['processed_job_id'] = $job_id;
        saveContentPool($pool);
        
        // Production queue'ya ekle
        $dataDir = __DIR__ . '/../data';
        $prodQueueFile = $dataDir . '/production_queue.json';
        $prodQueueData = file_exists($prodQueueFile) 
            ? json_decode(file_get_contents($prodQueueFile), true) 
            : ['production_queue' => [], 'current_production' => null, 'max_concurrent' => 1, 'metadata' => []];
        
        $prodItem = [
            'prod_queue_id' => 'prod_' . bin2hex(random_bytes(8)),
            'job_id' => $job_id,
            'queue_id' => $queue_id,
            'status' => 'waiting',
            'priority' => 0,
            'added_at' => date('c'),
            'started_at' => null,
            'completed_at' => null,
            'error' => null
        ];
        
        $prodQueueData['production_queue'][] = $prodItem;
        $prodQueueData['metadata']['last_updated'] = date('c');
        file_put_contents($prodQueueFile, json_encode($prodQueueData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        echo json_encode([
            'success' => true,
            'job_id' => $job_id,
            'message' => 'Job oluşturuldu ve üretim kuyruğuna eklendi'
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
        $pool['content'] = $new_list;
        saveContentPool($pool);
        echo json_encode(['success' => true, 'message' => 'İçerik silindi']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'İçerik bulunamadı']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
