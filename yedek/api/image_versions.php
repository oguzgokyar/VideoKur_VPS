<?php
/**
 * Image Versions API
 * Görsel sürümlerini listele ve aktif sürümü değiştir
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$dataDir   = __DIR__ . '/../data';
$outputDir = __DIR__ . '/../output';

// GET: Bir job için tüm sürümleri getir
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $jobId  = $_GET['job_id'] ?? '';
    $imgKey = $_GET['key']    ?? ''; // scene_1, hook, outro, thumbnail

    if (empty($jobId)) {
        echo json_encode(['error' => 'job_id gerekli']); exit;
    }

    $versionsFile = "$outputDir/$jobId/image_versions.json";
    if (!file_exists($versionsFile)) {
        echo json_encode(['versions' => []]); exit;
    }

    $versions = json_decode(file_get_contents($versionsFile), true) ?: [];

    if ($imgKey) {
        // Tek key için dön
        $list = $versions[$imgKey] ?? [];
        // URL'lere dönüştür
        $result = array_map(fn($v) => [
            'path'   => $v['path'],
            'url'    => '/output/' . $jobId . '/' . basename(dirname($v['path'])) . '/' . basename($v['path']),
            'active' => $v['active']
        ], $list);
        // Dosyalar gerçekten var mı kontrol et
        $result = array_values(array_filter($result, fn($v) => file_exists($v['path'])));
        echo json_encode(['versions' => $result]);
    } else {
        // Tüm keyler
        $result = [];
        foreach ($versions as $key => $list) {
            $filtered = array_map(function($v) use ($jobId) {
                $path = $v['path'];
                // URL hesapla: output/{jobId}/images/scene_1.png -> /output/{jobId}/images/scene_1.png
                $baseName = basename($path);
                $parentDir = basename(dirname($path));
                // output/{jobId} altındaki dosyalar
                $url = '/output/' . $jobId . '/' . ($parentDir === $jobId ? '' : $parentDir . '/') . $baseName;
                return ['path' => $path, 'url' => $url, 'active' => $v['active']];
            }, $list);
            $filtered = array_values(array_filter($filtered, fn($v) => file_exists($v['path'])));
            if (!empty($filtered)) $result[$key] = $filtered;
        }
        echo json_encode(['versions' => $result]);
    }
    exit;
}

// POST: Aktif sürümü değiştir
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input  = json_decode(file_get_contents('php://input'), true);
    $jobId  = $input['job_id']  ?? '';
    $imgKey = $input['key']     ?? '';  // scene_1, hook, outro, thumbnail
    $path   = $input['path']    ?? '';  // Seçilen dosyanın yolu

    if (empty($jobId) || empty($imgKey) || empty($path)) {
        echo json_encode(['error' => 'job_id, key ve path gerekli']); exit;
    }

    $versionsFile = "$outputDir/$jobId/image_versions.json";
    if (!file_exists($versionsFile)) {
        echo json_encode(['error' => 'Sürüm dosyası bulunamadı']); exit;
    }

    $versions = json_decode(file_get_contents($versionsFile), true) ?: [];
    if (!isset($versions[$imgKey])) {
        echo json_encode(['error' => "Key bulunamadı: $imgKey"]); exit;
    }

    // Seçilen path kontrolü
    if (!file_exists($path)) {
        echo json_encode(['error' => 'Dosya bulunamadı: ' . $path]); exit;
    }

    // Aktif görseli seçilen versiyonla değiştir (canonical path'e kopyala)
    // Canonical path: active olan görselin path'ini bul
    $canonicalPath = null;
    foreach ($versions[$imgKey] as $v) {
        if ($v['active']) { $canonicalPath = $v['path']; break; }
    }
    // Eğer canonical path yok, key'e göre belirle
    if (!$canonicalPath) {
        if ($imgKey === 'thumbnail') {
            $canonicalPath = "$outputDir/$jobId/thumbnail.jpg";
        } elseif ($imgKey === 'hook') {
            $canonicalPath = "$outputDir/$jobId/images/hook.png";
        } elseif ($imgKey === 'outro') {
            $canonicalPath = "$outputDir/$jobId/images/outro.png";
        } else {
            // scene_N
            $sceneNum = str_replace('scene_', '', $imgKey);
            $canonicalPath = "$outputDir/$jobId/images/scene_{$sceneNum}.png";
        }
    }

    // Backup canonical'ı da yap
    // Seçilen path canonical değilse, canonical'ı güncelle
    if (realpath($path) !== realpath($canonicalPath)) {
        // Seçilen versiyonu canonical'a kopyala
        copy($path, $canonicalPath);
    }

    // versions.json'da active'i güncelle
    foreach ($versions[$imgKey] as &$v) {
        $v['active'] = (realpath($v['path']) === realpath($canonicalPath));
    }
    file_put_contents($versionsFile, json_encode($versions, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo json_encode(['success' => true, 'active_path' => $canonicalPath]);
    exit;
}

echo json_encode(['error' => 'Geçersiz istek']);
