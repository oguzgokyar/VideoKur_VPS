<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$dataDir = __DIR__ . '/../data';
$scriptsFile = $dataDir . '/scripts.json';

if (!file_exists($scriptsFile)) {
    file_put_contents($scriptsFile, json_encode(['scripts' => [], 'categories' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function normalizeVideoType($videoType) {
    $v = trim(strtolower((string)$videoType));
    return in_array($v, ['short', 'square', 'wide'], true) ? $v : 'short';
}

function normalizeCategoryId($value) {
    $raw = trim(strtolower((string)$value));
    if ($raw === '') return 'genel';
    $normalized = preg_replace('/[^a-z0-9\-_]+/u', '-', $raw);
    $normalized = trim($normalized, '-');
    return $normalized !== '' ? $normalized : 'genel';
}

function loadScriptData() {
    global $scriptsFile;
    $data = json_decode(file_get_contents($scriptsFile), true);
    if (!is_array($data)) {
        $data = [];
    }
    $scripts = $data['scripts'] ?? [];
    $categories = $data['categories'] ?? [];

    foreach ($scripts as &$script) {
        $script['videoType'] = normalizeVideoType($script['videoType'] ?? 'short');
        $script['contentType'] = trim((string)($script['contentType'] ?? 'genel'));
        $script['categoryId'] = normalizeCategoryId($script['categoryId'] ?? $script['contentType'] ?? 'genel');
        unset($script['isDefault']);
    }
    unset($script);

    $indexed = [];
    foreach ($categories as $category) {
        $id = normalizeCategoryId($category['id'] ?? $category['name'] ?? '');
        if ($id === '') continue;
        if (!isset($indexed[$id])) {
            $indexed[$id] = [
                'id' => $id,
                'name' => trim((string)($category['name'] ?? $id)),
                'active' => isset($category['active']) ? (bool)$category['active'] : true,
                'createdAt' => $category['createdAt'] ?? date('c'),
                'updatedAt' => $category['updatedAt'] ?? date('c')
            ];
        }
    }
    foreach ($scripts as $script) {
        $id = normalizeCategoryId($script['categoryId'] ?? $script['contentType'] ?? 'genel');
        if (!isset($indexed[$id])) {
            $indexed[$id] = [
                'id' => $id,
                'name' => trim((string)($script['contentType'] ?? $id)),
                'active' => true,
                'createdAt' => date('c'),
                'updatedAt' => date('c')
            ];
        }
    }
    if (empty($indexed)) {
        $indexed['genel'] = [
            'id' => 'genel',
            'name' => 'genel',
            'active' => true,
            'createdAt' => date('c'),
            'updatedAt' => date('c')
        ];
    }

    return [
        'scripts' => $scripts,
        'categories' => array_values($indexed)
    ];
}

function saveScriptData($data) {
    global $scriptsFile;
    file_put_contents($scriptsFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function findCategoryName($categories, $categoryId, $fallback = 'genel') {
    foreach ($categories as $category) {
        if (($category['id'] ?? '') === $categoryId) {
            return trim((string)($category['name'] ?? $fallback));
        }
    }
    return $fallback;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $data = loadScriptData();
    if (isset($_GET['id'])) {
        $id = $_GET['id'];
        foreach ($data['scripts'] as $script) {
            if (($script['id'] ?? '') === $id) {
                echo json_encode($script);
                exit;
            }
        }
        http_response_code(404);
        echo json_encode(['error' => 'Script bulunamadı']);
        exit;
    }
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'Geçersiz JSON']);
        exit;
    }

    $action = $input['action'] ?? 'create_script';
    $data = loadScriptData();
    $scripts = $data['scripts'];
    $categories = $data['categories'];

    if ($action === 'create_category') {
        $name = trim((string)($input['name'] ?? ''));
        if ($name === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Kategori adı gerekli']);
            exit;
        }
        $id = normalizeCategoryId($input['id'] ?? $name);
        foreach ($categories as $category) {
            if (($category['id'] ?? '') === $id) {
                http_response_code(409);
                echo json_encode(['error' => 'Kategori zaten var']);
                exit;
            }
        }
        $categories[] = [
            'id' => $id,
            'name' => $name,
            'active' => true,
            'createdAt' => date('c'),
            'updatedAt' => date('c')
        ];
        saveScriptData(['scripts' => $scripts, 'categories' => $categories]);
        echo json_encode(['success' => true, 'categories' => $categories]);
        exit;
    }

    $name = trim((string)($input['name'] ?? ''));
    $description = trim((string)($input['description'] ?? ''));
    $prompt = trim((string)($input['prompt'] ?? ''));
    $videoType = normalizeVideoType($input['videoType'] ?? 'short');
    $maxDuration = intval($input['maxDuration'] ?? 55);
    $categoryId = normalizeCategoryId($input['categoryId'] ?? $input['contentType'] ?? 'genel');
    $categoryName = findCategoryName($categories, $categoryId, $categoryId);

    if ($name === '' || $prompt === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Ad ve prompt gerekli']);
        exit;
    }

    $newScript = [
        'id' => uniqid('script_', true),
        'name' => $name,
        'description' => $description,
        'contentType' => $categoryName,
        'categoryId' => $categoryId,
        'videoType' => $videoType,
        'maxDuration' => $maxDuration,
        'prompt' => $prompt,
        'createdAt' => date('c'),
        'updatedAt' => date('c')
    ];
    $scripts[] = $newScript;
    saveScriptData(['scripts' => $scripts, 'categories' => $categories]);
    echo json_encode(['success' => true, 'script' => $newScript]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'Geçersiz JSON']);
        exit;
    }

    $action = $input['action'] ?? 'update_script';
    $data = loadScriptData();
    $scripts = $data['scripts'];
    $categories = $data['categories'];

    if ($action === 'update_category') {
        $id = normalizeCategoryId($input['id'] ?? '');
        $name = trim((string)($input['name'] ?? ''));
        if ($id === '' || $name === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Kategori id ve adı gerekli']);
            exit;
        }
        $updated = false;
        foreach ($categories as &$category) {
            if (($category['id'] ?? '') === $id) {
                $category['name'] = $name;
                $category['updatedAt'] = date('c');
                $updated = true;
                break;
            }
        }
        unset($category);
        if (!$updated) {
            http_response_code(404);
            echo json_encode(['error' => 'Kategori bulunamadı']);
            exit;
        }
        foreach ($scripts as &$script) {
            if (($script['categoryId'] ?? '') === $id) {
                $script['contentType'] = $name;
                $script['updatedAt'] = date('c');
            }
        }
        unset($script);
        saveScriptData(['scripts' => $scripts, 'categories' => $categories]);
        echo json_encode(['success' => true]);
        exit;
    }

    $id = $input['id'] ?? '';
    if ($id === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Script ID gerekli']);
        exit;
    }

    $found = false;
    foreach ($scripts as &$script) {
        if (($script['id'] ?? '') === $id) {
            $found = true;
            if (isset($input['name'])) $script['name'] = trim((string)$input['name']);
            if (isset($input['description'])) $script['description'] = trim((string)$input['description']);
            if (isset($input['videoType'])) $script['videoType'] = normalizeVideoType($input['videoType']);
            if (isset($input['prompt'])) $script['prompt'] = trim((string)$input['prompt']);
            if (isset($input['maxDuration'])) $script['maxDuration'] = intval($input['maxDuration']);
            if (isset($input['categoryId']) || isset($input['contentType'])) {
                $categoryId = normalizeCategoryId($input['categoryId'] ?? $input['contentType']);
                $script['categoryId'] = $categoryId;
                $script['contentType'] = findCategoryName($categories, $categoryId, $categoryId);
            }
            if (isset($input['contentType']) && !isset($input['categoryId'])) {
                $script['contentType'] = trim((string)$input['contentType']);
                $script['categoryId'] = normalizeCategoryId($script['contentType']);
            }
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

    saveScriptData(['scripts' => $scripts, 'categories' => $categories]);
    echo json_encode(['success' => true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['error' => 'Geçersiz JSON']);
        exit;
    }

    $action = $input['action'] ?? 'delete_script';
    $data = loadScriptData();
    $scripts = $data['scripts'];
    $categories = $data['categories'];

    if ($action === 'delete_category') {
        $id = normalizeCategoryId($input['id'] ?? '');
        if ($id === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Kategori id gerekli']);
            exit;
        }
        foreach ($scripts as $script) {
            if (($script['categoryId'] ?? '') === $id) {
                http_response_code(409);
                echo json_encode(['error' => 'Bu kategori scriptlerde kullanılıyor']);
                exit;
            }
        }
        $before = count($categories);
        $categories = array_values(array_filter($categories, function($category) use ($id) {
            return ($category['id'] ?? '') !== $id;
        }));
        if ($before === count($categories)) {
            http_response_code(404);
            echo json_encode(['error' => 'Kategori bulunamadı']);
            exit;
        }
        saveScriptData(['scripts' => $scripts, 'categories' => $categories]);
        echo json_encode(['success' => true, 'categories' => $categories]);
        exit;
    }

    $id = $input['id'] ?? '';
    if ($id === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Script ID gerekli']);
        exit;
    }
    $before = count($scripts);
    $scripts = array_values(array_filter($scripts, function($script) use ($id) {
        return ($script['id'] ?? '') !== $id;
    }));
    if ($before === count($scripts)) {
        http_response_code(404);
        echo json_encode(['error' => 'Script bulunamadı']);
        exit;
    }
    saveScriptData(['scripts' => $scripts, 'categories' => $categories]);
    echo json_encode(['success' => true]);
    exit;
}
