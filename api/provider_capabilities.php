<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

require_once __DIR__ . '/script_profile_helpers.php';
$baseDir = dirname(__DIR__);
$capabilities = vp_provider_capabilities($baseDir);

if (($_GET['include'] ?? '') === 'elevenlabs_voices') {
    $config = vp_load_credentials($baseDir);
    $keys = vp_clean_key_list($config['elevenKeys'] ?? [], $config['elevenKey'] ?? '');
    $voices = [];
    $error = null;
    if ($keys) {
        $ch = curl_init('https://api.elevenlabs.io/v1/voices');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['xi-api-key: ' . $keys[0]],
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        if ($code === 200) {
            $decoded = json_decode((string)$body, true);
            foreach ($decoded['voices'] ?? [] as $voice) {
                $id = trim((string)($voice['voice_id'] ?? ''));
                if ($id !== '') $voices[] = ['id' => $id, 'name' => trim((string)($voice['name'] ?? $id))];
            }
        } else {
            $error = $curlError !== '' ? $curlError : 'ElevenLabs ses listesi alınamadı (HTTP ' . $code . ').';
        }
    }
    foreach ($capabilities['voiceover'] as &$provider) {
        if (($provider['id'] ?? '') === 'elevenlabs') {
            $provider['voices'] = $voices;
            if ($error) $provider['voicesError'] = $error;
        }
    }
    unset($provider);
}

echo json_encode(['success' => true, 'capabilities' => $capabilities], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
