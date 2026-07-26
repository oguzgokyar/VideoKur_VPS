<?php
/** Scheduler control for the shared Docker/Supervisor runtime. */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$baseDir = dirname(__DIR__);
$logDir = getenv('LOG_DIR') ?: $baseDir . '/logs';
$programs = [
    'production' => 'production-scheduler',
    'social' => 'social-scheduler',
    'content' => 'content-scheduler',
];
$logFiles = [
    'production' => $logDir . '/production-scheduler.log',
    'social' => $logDir . '/social-scheduler.log',
    'content' => $logDir . '/content-scheduler.log',
];

function supervisorCommand(array $args): array {
    $command = '/usr/bin/supervisorctl';
    foreach ($args as $arg) { $command .= ' ' . escapeshellarg($arg); }
    $output = [];
    $code = 0;
    exec($command . ' 2>&1', $output, $code);
    return ['success' => $code === 0, 'code' => $code, 'output' => $output];
}

function programStatus(string $program): array {
    $result = supervisorCommand(['status', $program]);
    $line = trim(implode(' ', $result['output']));
    $running = preg_match('/\bRUNNING\b/', $line) === 1;
    $pid = null;
    if (preg_match('/\bpid\s+(\d+)/', $line, $matches)) { $pid = (int)$matches[1]; }
    $state = $running ? 'RUNNING' : 'UNKNOWN';
    if (!$running && preg_match('/\b(STOPPED|FATAL|BACKOFF|EXITED|STARTING)\b/', $line, $matches)) { $state = $matches[1]; }
    return ['running' => $running, 'pid' => $pid, 'state' => $state, 'detail' => $line];
}

function recentLogLines(array $files, int $limit): array {
    $lines = [];
    foreach ($files as $type => $file) {
        if (!is_file($file) || !is_readable($file)) { continue; }
        $content = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach (array_slice($content, -$limit) as $line) { $lines[] = '[' . $type . '] ' . $line; }
    }
    return array_slice($lines, -$limit);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'status';
    if ($action === 'status') {
        $status = [];
        foreach ($programs as $type => $program) { $status[$type] = programStatus($program); }
        $status['last_updated'] = date('c');
        echo json_encode(['success' => true, 'status' => $status], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'logs') {
        $limit = max(1, min(500, (int)($_GET['lines'] ?? 100)));
        $logs = recentLogLines($logFiles, $limit);
        echo json_encode(['success' => true, 'logs' => $logs, 'count' => count($logs)], JSON_UNESCAPED_UNICODE);
        exit;
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Geçersiz işlem']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Geçersiz JSON']);
        exit;
    }
    $action = $input['action'] ?? '';
    $type = $input['type'] ?? 'production';
    if ($action === 'clear_logs') {
        foreach ($logFiles as $file) { if (is_file($file) && is_writable($file)) { file_put_contents($file, ''); } }
        echo json_encode(['success' => true, 'message' => 'Loglar temizlendi']);
        exit;
    }
    if (!isset($programs[$type]) || !in_array($action, ['start', 'stop', 'restart'], true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Geçersiz scheduler veya işlem']);
        exit;
    }
    $result = supervisorCommand([$action, $programs[$type]]);
    if (!$result['success']) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => trim(implode("\n", $result['output'])) ?: 'Supervisor işlemi başarısız']);
        exit;
    }
    echo json_encode(['success' => true, 'message' => ucfirst($type) . ' scheduler işlemi tamamlandı', 'status' => programStatus($programs[$type])], JSON_UNESCAPED_UNICODE);
    exit;
}
http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);
