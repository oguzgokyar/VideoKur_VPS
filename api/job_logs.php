<?php
/**
 * Job Logs API
 * Returns job-specific log content
 */

header('Content-Type: application/json; charset=utf-8');

$job_id = $_GET['id'] ?? '';

if (empty($job_id)) {
    echo json_encode(['success' => false, 'error' => 'Job ID required']);
    exit;
}

// Sanitize job_id
$job_id = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $job_id);

$base_dir = dirname(__DIR__);
$log_file = $base_dir . "/output/{$job_id}/job.log";

if (!file_exists($log_file)) {
    echo json_encode([
        'success' => false, 
        'error' => 'Log file not found',
        'logs' => [],
        'message' => 'Bu job için henüz log dosyası oluşturulmamış.'
    ]);
    exit;
}

// Read log file
$content = file_get_contents($log_file);
$lines = explode("\n", $content);

// Filter empty lines
$logs = array_filter($lines, function($line) {
    return !empty(trim($line));
});

echo json_encode([
    'success' => true,
    'logs' => array_values($logs),
    'count' => count($logs),
    'job_id' => $job_id
]);
