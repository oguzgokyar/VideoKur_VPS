<?php
/**
 * Content Sources API
 * 
 * RSS feed kaynak yönetimi
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$SOURCES_FILE = __DIR__ . '/../data/content_sources.json';

// Helper functions
function loadSources() {
    global $SOURCES_FILE;
    
    if (!file_exists($SOURCES_FILE)) {
        return ['sources' => [], 'metadata' => []];
    }
    
    $json = file_get_contents($SOURCES_FILE);
    return json_decode($json, true) ?: ['sources' => [], 'metadata' => []];
}

function saveSources($data) {
    global $SOURCES_FILE;
    
    $data['metadata']['last_updated'] = gmdate('Y-m-d\TH:i:s\Z');
    $data['metadata']['total_sources'] = count($data['sources'] ?? []);
    
    file_put_contents($SOURCES_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function generateSourceId() {
    return 'source_' . bin2hex(random_bytes(6));
}

// GET: Kaynak listesi veya fetch
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';
    
    // Feed'leri çek
    if ($action === 'fetch') {
        $source_id = $_GET['source_id'] ?? null;
        $limit = intval($_GET['limit'] ?? 20);
        
        // Python feed parser'ı çağır
        $pythonPath = 'python';
        $scriptPath = __DIR__ . '/../python/content/feed_parser.py';
        
        $cmd = "$pythonPath \"$scriptPath\"";
        if ($source_id && $source_id !== 'all') {
            $cmd .= " --source-id \"$source_id\"";
        }
        $cmd .= " --limit $limit";
        
        // Windows'ta çalıştır
        $output = [];
        $return_code = 0;
        exec($cmd . " 2>&1", $output, $return_code);
        
        if ($return_code === 0) {
            // Başarılı - yeni eklenen içerik sayısını bul
            $outputStr = implode("\n", $output);
            preg_match('/Added (\d+) new/', $outputStr, $matches);
            $newItems = isset($matches[1]) ? intval($matches[1]) : 0;
            
            echo json_encode([
                'success' => true,
                'new_items' => $newItems,
                'message' => "$newItems yeni içerik eklendi"
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Feed çekme hatası',
                'output' => $output
            ]);
        }
        exit;
    }
    
    // Kaynak listesi
    $data = loadSources();
    $sources = $data['sources'] ?? [];
    
    // Filtrele (enabled)
    if (isset($_GET['enabled'])) {
        $enabled = $_GET['enabled'] === '1' || $_GET['enabled'] === 'true';
        $sources = array_filter($sources, function($s) use ($enabled) {
            return ($s['enabled'] ?? true) === $enabled;
        });
        $sources = array_values($sources);
    }
    
    echo json_encode([
        'success' => true,
        'sources' => $sources,
        'total' => count($sources)
    ]);
    exit;
}

// POST: Yeni kaynak ekle
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $name = $input['name'] ?? '';
    $url = $input['url'] ?? '';
    $category = $input['category'] ?? 'genel';
    $keywords = $input['keywords'] ?? [];
    
    if (empty($name) || empty($url)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'İsim ve URL gerekli']);
        exit;
    }
    
    // URL validation
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Geçersiz URL formatı']);
        exit;
    }
    
    $data = loadSources();
    $sources = $data['sources'] ?? [];
    
    // Duplicate kontrolü
    foreach ($sources as $s) {
        if ($s['url'] === $url) {
            http_response_code(409);
            echo json_encode(['success' => false, 'error' => 'Bu RSS URL zaten mevcut']);
            exit;
        }
    }
    
    // Yeni kaynak oluştur
    $new_source = [
        'id' => generateSourceId(),
        'name' => $name,
        'url' => $url,
        'type' => 'rss',
        'category' => $category,
        'enabled' => true,
        'check_interval_minutes' => 30,
        'last_checked' => null,
        'keywords' => is_array($keywords) ? $keywords : explode(',', $keywords),
        'auto_approve' => false,
        'created_at' => gmdate('Y-m-d\TH:i:s\Z')
    ];
    
    $data['sources'][] = $new_source;
    saveSources($data);
    
    echo json_encode(['success' => true, 'source' => $new_source]);
    exit;
}

// PATCH: Kaynak güncelle
if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? '';
    
    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Source ID gerekli']);
        exit;
    }
    
    $data = loadSources();
    $sources = $data['sources'] ?? [];
    
    $found = false;
    
    foreach ($sources as &$source) {
        if ($source['id'] === $id) {
            $found = true;
            
            // Güncellenebilir alanlar
            if (isset($input['name'])) $source['name'] = $input['name'];
            if (isset($input['url'])) $source['url'] = $input['url'];
            if (isset($input['enabled'])) $source['enabled'] = (bool)$input['enabled'];
            if (isset($input['category'])) $source['category'] = $input['category'];
            if (isset($input['keywords'])) {
                $source['keywords'] = is_array($input['keywords']) ? $input['keywords'] : explode(',', $input['keywords']);
            }
            if (isset($input['check_interval_minutes'])) $source['check_interval_minutes'] = (int)$input['check_interval_minutes'];
            if (isset($input['auto_approve'])) $source['auto_approve'] = (bool)$input['auto_approve'];
            
            $source['updated_at'] = gmdate('Y-m-d\TH:i:s\Z');
            
            break;
        }
    }
    unset($source); // Break reference
    
    if ($found) {
        $data['sources'] = $sources;
        saveSources($data);
        echo json_encode(['success' => true, 'message' => 'Kaynak güncellendi']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Kaynak bulunamadı']);
    }
    exit;
}

// DELETE: Kaynak sil
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? $_GET['id'] ?? '';
    
    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Source ID gerekli']);
        exit;
    }
    
    $data = loadSources();
    $sources = $data['sources'] ?? [];
    
    $found = false;
    $new_list = [];
    
    foreach ($sources as $s) {
        if ($s['id'] === $id) {
            $found = true;
        } else {
            $new_list[] = $s;
        }
    }
    
    if ($found) {
        $data['sources'] = $new_list;
        saveSources($data);
        echo json_encode(['success' => true, 'message' => 'Kaynak silindi']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Kaynak bulunamadı']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
