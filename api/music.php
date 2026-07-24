<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$baseDir = dirname(__DIR__);
require_once __DIR__ . '/music_helpers.php';

function respond($payload, $code = 200) {
    http_response_code($code);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $library = loadMusicLibrary($baseDir);
    $categories = getScriptCategories($baseDir);
    respond([
        'success' => true,
        'tracks' => $library['tracks'],
        'categories' => $categories
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    $isMultipart = stripos($contentType, 'multipart/form-data') !== false;
    $input = $isMultipart ? $_POST : json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }

    $action = $input['action'] ?? 'add';
    $library = loadMusicLibrary($baseDir);
    $tracks = $library['tracks'] ?? [];

    if ($action === 'upload_files') {
        if (!isset($_FILES['files'])) {
            respond(['success' => false, 'error' => 'Yüklenecek dosya bulunamadı'], 400);
        }

        $categoryId = normalizeMusicCategory($input['categoryId'] ?? 'genel');
        $volumeDb = (float)($input['volumeDb'] ?? -22.0);
        $active = isset($input['active']) ? (bool)$input['active'] : true;

        $allowedExtensions = ['mp3', 'wav', 'm4a', 'aac', 'ogg', 'flac'];
        $files = $_FILES['files'];
        $uploaded = [];
        $errors = [];
        $musicDir = $baseDir . '/assets/music';
        if (!is_dir($musicDir)) {
            mkdir($musicDir, 0777, true);
        }

        $names = is_array($files['name']) ? $files['name'] : [$files['name']];
        $tmpNames = is_array($files['tmp_name']) ? $files['tmp_name'] : [$files['tmp_name']];
        $uploadErrors = is_array($files['error']) ? $files['error'] : [$files['error']];

        foreach ($names as $index => $originalName) {
            $err = $uploadErrors[$index] ?? UPLOAD_ERR_NO_FILE;
            if ($err !== UPLOAD_ERR_OK) {
                $errors[] = $originalName . ' yüklenemedi';
                continue;
            }
            $tmp = $tmpNames[$index] ?? '';
            $ext = strtolower(pathinfo((string)$originalName, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExtensions, true)) {
                $errors[] = $originalName . ' desteklenmeyen dosya türü';
                continue;
            }

            $safeBase = pathinfo((string)$originalName, PATHINFO_FILENAME);
            $safeBase = preg_replace('/[^a-zA-Z0-9\-_]+/u', '-', $safeBase);
            $safeBase = trim((string)$safeBase, '-');
            if ($safeBase === '') {
                $safeBase = 'track';
            }
            $storedName = $safeBase . '-' . substr(uniqid('', true), -8) . '.' . $ext;
            $targetPath = $musicDir . '/' . $storedName;
            if (!move_uploaded_file($tmp, $targetPath)) {
                $errors[] = $originalName . ' kaydedilemedi';
                continue;
            }

            $relativeFile = 'assets/music/' . $storedName;
            $track = [
                'id' => uniqid('track_', true),
                'name' => str_replace('-', ' ', $safeBase),
                'file' => $relativeFile,
                'categoryId' => $categoryId,
                'volumeDb' => $volumeDb,
                'active' => $active,
                'createdAt' => date('c'),
                'updatedAt' => date('c')
            ];
            $tracks[] = $track;
            $uploaded[] = $track;
        }

        if (empty($uploaded)) {
            respond(['success' => false, 'error' => 'Hiç dosya eklenemedi', 'details' => $errors], 400);
        }

        $library['tracks'] = $tracks;
        saveMusicLibrary($baseDir, $library);
        respond([
            'success' => true,
            'uploaded' => $uploaded,
            'errors' => $errors,
            'tracks' => $library['tracks']
        ]);
    }

    if ($action === 'toggle_active') {
        $id = trim((string)($input['id'] ?? ''));
        if ($id === '') {
            respond(['success' => false, 'error' => 'Track ID gerekli'], 400);
        }
        $updated = false;
        foreach ($tracks as &$track) {
            if (($track['id'] ?? '') === $id) {
                $track['active'] = !((bool)($track['active'] ?? true));
                $track['updatedAt'] = date('c');
                $updated = true;
                break;
            }
        }
        unset($track);
        if (!$updated) {
            respond(['success' => false, 'error' => 'Track bulunamadı'], 404);
        }
        $library['tracks'] = $tracks;
        saveMusicLibrary($baseDir, $library);
        respond(['success' => true, 'tracks' => $library['tracks']]);
    }

    if ($action === 'add') {
        $name = trim((string)($input['name'] ?? ''));
        $categoryId = normalizeMusicCategory($input['categoryId'] ?? 'genel');
        $file = normalizeMusicFilePath($input['file'] ?? '');
        $volumeDb = (float)($input['volumeDb'] ?? -22.0);
        $active = isset($input['active']) ? (bool)$input['active'] : true;

        if ($name === '' || $file === null) {
            respond(['success' => false, 'error' => 'İsim ve geçerli dosya yolu gerekli (assets/music/...)'], 400);
        }

        $fullPath = $baseDir . '/' . str_replace('/', DIRECTORY_SEPARATOR, $file);
        if (!file_exists($fullPath)) {
            respond(['success' => false, 'error' => 'Müzik dosyası bulunamadı'], 400);
        }

        $tracks[] = [
            'id' => uniqid('track_', true),
            'name' => $name,
            'file' => $file,
            'categoryId' => $categoryId,
            'volumeDb' => $volumeDb,
            'active' => $active,
            'createdAt' => date('c'),
            'updatedAt' => date('c')
        ];
        $library['tracks'] = $tracks;
        saveMusicLibrary($baseDir, $library);
        respond(['success' => true, 'tracks' => $library['tracks']]);
    }

    respond(['success' => false, 'error' => 'Geçersiz action'], 400);
}

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        respond(['success' => false, 'error' => 'Geçersiz istek'], 400);
    }
    $id = trim((string)($input['id'] ?? ''));
    if ($id === '') {
        respond(['success' => false, 'error' => 'Track ID gerekli'], 400);
    }

    $library = loadMusicLibrary($baseDir);
    $before = count($library['tracks'] ?? []);
    $toDeleteFile = null;
    $library['tracks'] = array_values(array_filter($library['tracks'] ?? [], function($track) use ($id, &$toDeleteFile) {
        if (($track['id'] ?? '') === $id) {
            $toDeleteFile = $track['file'] ?? null;
            return false;
        }
        return true;
    }));
    if (count($library['tracks']) === $before) {
        respond(['success' => false, 'error' => 'Track bulunamadı'], 404);
    }
    if (is_string($toDeleteFile) && strpos($toDeleteFile, 'assets/music/') === 0) {
        $fullPath = $baseDir . '/' . str_replace('/', DIRECTORY_SEPARATOR, $toDeleteFile);
        if (file_exists($fullPath) && is_file($fullPath) && is_writable($fullPath)) {
            unlink($fullPath);
        }
    }
    saveMusicLibrary($baseDir, $library);
    respond(['success' => true, 'tracks' => $library['tracks']]);
}

respond(['success' => false, 'error' => 'Method desteklenmiyor'], 405);
