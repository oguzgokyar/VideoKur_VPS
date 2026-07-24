<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$dataDir = __DIR__ . '/../data';
$scriptsFile = $dataDir . '/scripts.json';

// Scripts.json yoksa oluştur
if (!file_exists($scriptsFile)) {
    $defaultData = ['scripts' => []];
    file_put_contents($scriptsFile, json_encode($defaultData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function loadScripts() {
    global $scriptsFile;
    $data = json_decode(file_get_contents($scriptsFile), true);
    $scripts = $data['scripts'] ?? [];
    foreach ($scripts as &$script) {
        $script['videoType'] = normalizeVideoType($script['videoType'] ?? 'short');
    }
    unset($script);
    return $scripts;
}

function saveScripts($scripts) {
    global $scriptsFile;
    file_put_contents($scriptsFile, json_encode(['scripts' => $scripts], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function normalizeVideoType($videoType) {
    $v = trim(strtolower((string)$videoType));
    if (!in_array($v, ['short', 'square', 'wide'], true)) {
        return 'short';
    }
    return $v;
}

// GET: Tüm scriptleri listele veya tek script getir
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $scripts = loadScripts();
    
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        $found = null;
        foreach ($scripts as $script) {
            if ($script['id'] === $id) {
                $found = $script;
                break;
            }
        }
        if ($found) {
            echo json_encode($found);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Script bulunamadı']);
        }
    } else {
        echo json_encode(['scripts' => $scripts]);
    }
    exit;
}

// POST: Yeni script oluştur
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $name = trim($input['name'] ?? '');
    $description = trim($input['description'] ?? '');
    $contentType = trim($input['contentType'] ?? 'genel');
    $videoType = normalizeVideoType($input['videoType'] ?? 'short');
    $prompt = trim($input['prompt'] ?? '');
    $maxDuration = intval($input['maxDuration'] ?? 55);
    $isDefault = boolval($input['isDefault'] ?? false);
    
    if (empty($name) || empty($prompt)) {
        http_response_code(400);
        echo json_encode(['error' => 'Ad ve prompt gerekli']);
        exit;
    }
    
    $scripts = loadScripts();
    
    // Eğer yeni script varsayılan yapılıyorsa, diğerlerinin varsayılanlığını kaldır
    if ($isDefault) {
        foreach ($scripts as &$s) {
            $sVideoType = normalizeVideoType($s['videoType'] ?? 'short');
            if ($s['contentType'] === $contentType && $sVideoType === $videoType) {
                $s['isDefault'] = false;
            }
        }
        unset($s);
    }
    
    $newScript = [
        'id' => uniqid('script_', true),
        'name' => $name,
        'description' => $description,
        'contentType' => $contentType,
        'videoType' => $videoType,
        'isDefault' => $isDefault,
        'maxDuration' => $maxDuration,
        'prompt' => $prompt,
        'createdAt' => date('c'),
        'updatedAt' => date('c')
    ];
    
    $scripts[] = $newScript;
    saveScripts($scripts);
    
    echo json_encode(['success' => true, 'script' => $newScript]);
    exit;
}

// PUT: Script güncelle
if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = $input['id'] ?? '';
    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Script ID gerekli']);
        exit;
    }
    
    $scripts = loadScripts();
    $found = false;
    
    foreach ($scripts as &$script) {
        if ($script['id'] === $id) {
            $found = true;
            $nextContentType = isset($input['contentType']) ? trim($input['contentType']) : ($script['contentType'] ?? 'genel');
            $nextVideoType = isset($input['videoType']) ? normalizeVideoType($input['videoType']) : normalizeVideoType($script['videoType'] ?? 'short');
            
            // Eğer varsayılan yapılıyorsa, aynı contentType'daki diğerlerini kaldır
            if (isset($input['isDefault']) && $input['isDefault']) {
                foreach ($scripts as &$s) {
                    $sVideoType = normalizeVideoType($s['videoType'] ?? 'short');
                    if ($s['id'] !== $id && $s['contentType'] === $nextContentType && $sVideoType === $nextVideoType) {
                        $s['isDefault'] = false;
                    }
                }
                unset($s);
            }
            
            // Güncelle
            if (isset($input['name'])) $script['name'] = trim($input['name']);
            if (isset($input['description'])) $script['description'] = trim($input['description']);
            if (isset($input['contentType'])) $script['contentType'] = trim($input['contentType']);
            if (isset($input['videoType'])) $script['videoType'] = normalizeVideoType($input['videoType']);
            if (isset($input['prompt'])) $script['prompt'] = trim($input['prompt']);
            if (isset($input['maxDuration'])) $script['maxDuration'] = intval($input['maxDuration']);
            if (isset($input['isDefault'])) $script['isDefault'] = boolval($input['isDefault']);
            $script['updatedAt'] = date('c');
            
            break;
        }
    }
    unset($script);
    
    if (!$found) {
        http_response_code(404);
        echo json_encode(['error' => 'Script bulunamadı']);
        exit;
    }
    
    saveScripts($scripts);
    echo json_encode(['success' => true]);
    exit;
}

// DELETE: Script sil
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = $input['id'] ?? '';
    if (empty($id)) {
        http_response_code(400);
        echo json_encode(['error' => 'Script ID gerekli']);
        exit;
    }
    
    $scripts = loadScripts();
    $newScripts = [];
    $found = false;
    
    foreach ($scripts as $script) {
        if ($script['id'] === $id) {
            $found = true;
            continue;
        }
        $newScripts[] = $script;
    }
    
    if (!$found) {
        http_response_code(404);
        echo json_encode(['error' => 'Script bulunamadı']);
        exit;
    }
    
    saveScripts($newScripts);
    echo json_encode(['success' => true]);
    exit;
}
