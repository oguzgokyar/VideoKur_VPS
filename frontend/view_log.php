<?php
/**
 * Job Log Viewer
 * Görüntüler: output/{job_id}/job.log
 */

$job_id = $_GET['id'] ?? '';

if (empty($job_id)) {
    die('Job ID gerekli');
}

// Sanitize job_id
$job_id = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $job_id);

$base_dir = dirname(__DIR__);
$log_file = $base_dir . "/output/{$job_id}/job.log";
$job_file = $base_dir . "/data/jobs/{$job_id}.json";

// Load job data
$job_data = null;
if (file_exists($job_file)) {
    $job_data = json_decode(file_get_contents($job_file), true);
}

// Load log
$logs = '';
$log_exists = false;
if (file_exists($log_file)) {
    $logs = file_get_contents($log_file);
    $log_exists = true;
}

// Color-code logs
function colorize_log($line) {
    $line = htmlspecialchars($line);
    
    // ERROR lines - red
    if (strpos($line, '[ERROR]') !== false || strpos($line, '❌') !== false) {
        return '<span class="text-red-400">' . $line . '</span>';
    }
    // SUCCESS lines - green
    if (strpos($line, '[SUCCESS]') !== false || strpos($line, '✅') !== false) {
        return '<span class="text-green-400">' . $line . '</span>';
    }
    // WARNING lines - yellow
    if (strpos($line, '[WARNING]') !== false || strpos($line, '⚠️') !== false) {
        return '<span class="text-yellow-400">' . $line . '</span>';
    }
    // INFO lines - blue
    if (strpos($line, '[INFO]') !== false || strpos($line, 'ℹ️') !== false) {
        return '<span class="text-blue-400">' . $line . '</span>';
    }
    // DEBUG lines - purple
    if (strpos($line, '[DEBUG]') !== false || strpos($line, '🔍') !== false) {
        return '<span class="text-purple-400">' . $line . '</span>';
    }
    
    // Default - gray
    return '<span class="text-gray-300">' . $line . '</span>';
}

$log_lines = explode("\n", $logs);
$colored_logs = array_map('colorize_log', $log_lines);
$colored_log = implode("\n", $colored_logs);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Log: <?= htmlspecialchars($job_id) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; }
        .log-container { 
            font-family: 'Courier New', monospace; 
            line-height: 1.6;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto p-8 max-w-6xl">
        <!-- Header -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6 mb-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">📋 Job Log</h1>
                    <p class="text-sm text-gray-500 mt-1">Job ID: <span class="font-mono"><?= htmlspecialchars($job_id) ?></span></p>
                </div>
                <a href="dashboard.php" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold transition">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Dashboard'a Dön
                </a>
            </div>
            
            <?php if ($job_data): ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4 pt-4 border-t">
                <div>
                    <p class="text-xs text-gray-500 mb-1">Başlık</p>
                    <p class="text-sm font-medium text-gray-800"><?= htmlspecialchars($job_data['title'] ?? 'N/A') ?></p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 mb-1">Durum</p>
                    <p class="text-sm font-medium">
                        <span class="px-2 py-1 rounded-full text-xs font-bold uppercase
                            <?= $job_data['status'] === 'done' ? 'bg-green-100 text-green-700' : '' ?>
                            <?= $job_data['status'] === 'failed' ? 'bg-red-100 text-red-700' : '' ?>
                            <?= $job_data['status'] === 'paused' ? 'bg-yellow-100 text-yellow-700' : '' ?>
                            <?= !in_array($job_data['status'], ['done','failed','paused']) ? 'bg-blue-100 text-blue-700' : '' ?>">
                            <?= htmlspecialchars($job_data['status'] ?? 'unknown') ?>
                        </span>
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-500 mb-1">Güncelleme</p>
                    <p class="text-sm text-gray-600"><?= htmlspecialchars($job_data['updated_at'] ?? 'N/A') ?></p>
                </div>
            </div>
            
            <?php if (!empty($job_data['error'])): ?>
            <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                <p class="text-xs font-semibold text-red-700 mb-1">Hata Mesajı:</p>
                <p class="text-sm text-red-600"><?= nl2br(htmlspecialchars($job_data['error'])) ?></p>
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Log Content -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-semibold text-gray-800">📝 Log Detayları</h2>
                <div class="flex gap-2 text-xs">
                    <span class="text-red-400">❌ ERROR</span>
                    <span class="text-green-400">✅ SUCCESS</span>
                    <span class="text-yellow-400">⚠️ WARNING</span>
                    <span class="text-blue-400">ℹ️ INFO</span>
                    <span class="text-purple-400">🔍 DEBUG</span>
                </div>
            </div>
            
            <?php if (!$log_exists): ?>
            <div class="bg-gray-50 rounded-lg p-8 text-center">
                <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <p class="text-gray-500 text-lg mb-2">Log dosyası bulunamadı</p>
                <p class="text-gray-400 text-sm">Bu job henüz çalıştırılmadı veya log sistemi aktif değildi.</p>
                <p class="text-gray-400 text-sm mt-2">Yeni job'lar otomatik olarak log dosyası oluşturur.</p>
            </div>
            <?php else: ?>
            <div class="bg-gray-900 rounded-lg p-6 overflow-x-auto">
                <div class="log-container text-xs">
                    <?= $colored_log ?>
                </div>
            </div>
            
            <div class="mt-4 flex items-center justify-between text-sm text-gray-500">
                <p><?= count($log_lines) ?> satır</p>
                <a href="?id=<?= urlencode($job_id) ?>" class="text-blue-600 hover:underline">🔄 Yenile</a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Actions -->
        <div class="mt-6 flex gap-3">
            <a href="dashboard.php" class="flex-1 text-center px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-semibold transition">
                ← Dashboard
            </a>
            <?php if ($job_data): ?>
            <a href="project.php?id=<?= urlencode($job_id) ?>" class="flex-1 text-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold transition">
                Proje Detayları →
            </a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
