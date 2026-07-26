<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$baseDir = dirname(__DIR__);
$python = getenv('PYTHON_BIN') ?: '/opt/videokur-venv/bin/python';
$ffmpeg = getenv('FFMPEG_BIN') ?: '/usr/bin/ffmpeg';
$paths = [
    'data' => getenv('DATA_DIR') ?: $baseDir . '/data',
    'output' => getenv('OUTPUT_DIR') ?: $baseDir . '/output',
    'logs' => getenv('LOG_DIR') ?: $baseDir . '/logs',
];

$checks = [
    'php' => true,
    'curl_extension' => extension_loaded('curl'),
    'mbstring_extension' => extension_loaded('mbstring'),
    'python' => is_file($python) && is_executable($python),
    'ffmpeg' => is_file($ffmpeg) && is_executable($ffmpeg),
];

foreach ($paths as $name => $path) {
    $checks[$name . '_directory'] = is_dir($path);
    $checks[$name . '_writable'] = is_dir($path) && is_writable($path);
}

$healthy = !in_array(false, $checks, true);
http_response_code($healthy ? 200 : 503);
echo json_encode([
    'status' => $healthy ? 'ok' : 'error',
    'environment' => getenv('APP_ENV') ?: 'unknown',
    'checks' => $checks,
    'timestamp' => gmdate('c'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
