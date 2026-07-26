<?php

declare(strict_types=1);

$dataDirectory = rtrim((string) (getenv('DATA_DIR') ?: '/app/data'), '/');
$configFile = $dataDirectory . '/config.json';
if (!is_file($configFile)) {
    exit(0);
}

$config = json_decode((string) file_get_contents($configFile), true);
if (!is_array($config)) {
    fwrite(STDERR, "VideoKur config migration: config.json geçerli JSON değil.\n");
    exit(1);
}

$currentModel = trim((string) ($config['geminiModel'] ?? ''));
if ($currentModel !== '' && !str_starts_with($currentModel, 'gemini-2.')) {
    exit(0);
}

$config['geminiModel'] = 'gemini-3.6-flash';
$temporary = $configFile . '.tmp.' . getmypid();
$json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if ($json === false || file_put_contents($temporary, $json . PHP_EOL, LOCK_EX) === false) {
    @unlink($temporary);
    fwrite(STDERR, "VideoKur config migration: geçici dosya yazılamadı.\n");
    exit(1);
}

@chmod($temporary, 0660);
if (!rename($temporary, $configFile)) {
    @unlink($temporary);
    fwrite(STDERR, "VideoKur config migration: config.json güncellenemedi.\n");
    exit(1);
}

fwrite(STDOUT, "Gemini modeli gemini-3.6-flash olarak güncellendi.\n");
