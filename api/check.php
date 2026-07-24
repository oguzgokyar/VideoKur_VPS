<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'POST method required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$provider = $input['provider'] ?? '';
$key = $input['key'] ?? '';

if (empty($provider)) {
    echo json_encode(['valid' => false, 'message' => 'Provider gerekli']);
    exit;
}

// Anahtarsız provider'lar
$keylessProviders = ['pollinations_image', 'pollinations_text', 'edge_tts', 'ffmpeg', 'python'];
if (empty($key) && !in_array($provider, $keylessProviders)) {
    echo json_encode(['valid' => false, 'message' => 'API anahtarı gerekli']);
    exit;
}

function checkApi($url, $headers = [], $timeout = 20) {
    // cURL varsa kullan
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_PROXY, '');
        curl_setopt($ch, CURLOPT_NOPROXY, '*');
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        return ['code' => $httpCode, 'body' => $response, 'error' => $error];
    }

    // Fallback: file_get_contents
    $opts = [
        'http' => [
            'method' => 'GET',
            'timeout' => $timeout,
            'ignore_errors' => true,
            'header' => implode("\r\n", $headers)
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ];
    $context = stream_context_create($opts);
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        return ['code' => 0, 'body' => '', 'error' => 'Bağlantı kurulamadı'];
    }

    $httpCode = 0;
    if (isset($http_response_header[0]) && preg_match('/\d{3}/', $http_response_header[0], $m)) {
        $httpCode = (int)$m[0];
    }
    return ['code' => $httpCode, 'body' => $response, 'error' => ''];
}

function checkApiPost($url, $payload, $headers = [], $timeout = 30) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_PROXY, '');
        curl_setopt($ch, CURLOPT_NOPROXY, '*');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        $allHeaders = array_merge(['Content-Type: application/json'], $headers);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        return ['code' => $httpCode, 'body' => $response, 'error' => $error];
    }
    return ['code' => 0, 'body' => '', 'error' => 'cURL gerekli'];
}

function parseApiError($body) {
    $data = json_decode($body, true);
    if (!is_array($data)) {
        return ['code' => null, 'status' => null, 'message' => null];
    }
    $err = $data['error'] ?? [];
    return [
        'code' => isset($err['code']) ? (string)$err['code'] : null,
        'status' => isset($err['status']) ? (string)$err['status'] : null,
        'message' => isset($err['message']) ? (string)$err['message'] : null
    ];
}

switch ($provider) {
    case 'gemini':
        // Pipeline ile aynı kriter: generate_content çağrısıyla test et
        $model = trim((string)($input['model'] ?? 'gemini-2.0-flash'));
        $url = "https://generativelanguage.googleapis.com/v1beta/models/" . rawurlencode($model) . ":generateContent?key=" . urlencode($key);
        $payload = [
            'contents' => [
                ['parts' => [['text' => 'ping']]]
            ]
        ];
        $result = checkApiPost($url, $payload, [], 30);
        $err = parseApiError($result['body']);
        $httpCode = (int)$result['code'];

        if ($result['error']) {
            echo json_encode([
                'valid' => false,
                'message' => 'Bağlantı hatası: ' . $result['error'],
                'http_code' => 0,
                'error_code' => 'network',
                'error_status' => 'NETWORK_ERROR'
            ]);
        } elseif ($httpCode === 200) {
            echo json_encode([
                'valid' => true,
                'message' => "Gemini API anahtarı geçerli ✓ (Model: {$model})",
                'http_code' => 200,
                'error_code' => null,
                'error_status' => 'OK'
            ]);
        } elseif ($httpCode === 403 || $err['code'] === '403' || $err['status'] === 'PERMISSION_DENIED') {
            echo json_encode([
                'valid' => false,
                'message' => '403 PERMISSION_DENIED: Bu key/model kombinasyonu erişim reddedildi.',
                'http_code' => $httpCode,
                'error_code' => '403',
                'error_status' => 'PERMISSION_DENIED'
            ]);
        } elseif ($httpCode === 429 || $err['code'] === '429') {
            echo json_encode([
                'valid' => false,
                'message' => '429 RESOURCE_EXHAUSTED: Key geçerli ama quota dolu.',
                'http_code' => $httpCode,
                'error_code' => '429',
                'error_status' => $err['status'] ?: 'RESOURCE_EXHAUSTED'
            ]);
        } elseif ($httpCode === 503 || $err['code'] === '503') {
            echo json_encode([
                'valid' => false,
                'message' => '503 UNAVAILABLE: Gemini sunucusu yoğun.',
                'http_code' => $httpCode,
                'error_code' => '503',
                'error_status' => $err['status'] ?: 'UNAVAILABLE'
            ]);
        } elseif ($httpCode === 404 || $err['code'] === '404') {
            echo json_encode([
                'valid' => false,
                'message' => "404 NOT_FOUND: Model bulunamadı ({$model}).",
                'http_code' => $httpCode,
                'error_code' => '404',
                'error_status' => $err['status'] ?: 'NOT_FOUND'
            ]);
        } else {
            $code = $err['code'] ?: ($httpCode > 0 ? (string)$httpCode : 'unknown');
            $status = $err['status'] ?: 'ERROR';
            $msg = $err['message'] ?: 'API hatası';
            echo json_encode([
                'valid' => false,
                'message' => "Gemini hatası ({$code} {$status}): {$msg}",
                'http_code' => $httpCode,
                'error_code' => $code,
                'error_status' => $status
            ]);
        }
        break;

    case 'elevenlabs':
        $result = checkApi('https://api.elevenlabs.io/v1/user', ['xi-api-key: ' . $key]);
        if ($result['error']) {
            echo json_encode(['valid' => false, 'message' => 'Bağlantı hatası: ' . $result['error']]);
        } elseif ($result['code'] === 200) {
            $data = json_decode($result['body'], true);
            $tier = $data['subscription']['tier'] ?? '';
            echo json_encode(['valid' => true, 'message' => 'ElevenLabs API geçerli ✓' . ($tier ? " (Plan: $tier)" : '')]);
        } elseif ($result['code'] === 401) {
            echo json_encode(['valid' => false, 'message' => 'Geçersiz API anahtarı']);
        } else {
            echo json_encode(['valid' => false, 'message' => 'API hatası (HTTP ' . $result['code'] . ')']);
        }
        break;

    case 'huggingface':
        // First validate the token
        $whoami = checkApi('https://huggingface.co/api/whoami-v2', ['Authorization: Bearer ' . $key]);
        if ($whoami['error']) {
            echo json_encode(['valid' => false, 'message' => 'Bağlantı hatası: ' . $whoami['error']]);
            break;
        }
        if ($whoami['code'] === 401) {
            echo json_encode(['valid' => false, 'message' => 'Geçersiz HuggingFace token (401)']);
            break;
        }
        if ($whoami['code'] !== 200) {
            echo json_encode(['valid' => false, 'message' => 'HuggingFace API hatası (HTTP ' . $whoami['code'] . ')']);
            break;
        }
        $data = json_decode($whoami['body'], true);
        $name = $data['name'] ?? '';

        // Test new router endpoint (requires Inference Providers permission)
        $routerPayload = ['inputs' => 'blue sky', 'parameters' => ['width' => 64, 'height' => 64]];
        $routerResult = checkApiPost(
            'https://router.huggingface.co/hf-inference/models/black-forest-labs/FLUX.1-schnell/v1/text-to-image',
            $routerPayload,
            ['Authorization: Bearer ' . $key],
            30
        );
        if ($routerResult['code'] === 200) {
            echo json_encode(['valid' => true, 'message' => 'HuggingFace token geçerli ✓' . ($name ? " (Kullanıcı: $name)" : '') . ' — FLUX.1-schnell API aktif ✓']);
        } elseif ($routerResult['code'] === 403) {
            echo json_encode(['valid' => true, 'message' => 'Token geçerli' . ($name ? " ($name)" : '') . ' ⚠ Inference Providers izni gerekiyor. HF ayarlarında etkinleştirin: huggingface.co/settings/billing']);
        } else {
            echo json_encode(['valid' => true, 'message' => 'Token geçerli' . ($name ? " ($name)" : '') . ' (Inference API: HTTP ' . $routerResult['code'] . ')']);
        }
        break;

    case 'pexels':
        $result = checkApi('https://api.pexels.com/v1/search?query=test&per_page=1', ['Authorization: ' . $key]);
        if ($result['error']) {
            echo json_encode(['valid' => false, 'message' => 'Bağlantı hatası: ' . $result['error']]);
        } elseif ($result['code'] === 200) {
            echo json_encode(['valid' => true, 'message' => 'Pexels API anahtarı geçerli ✓']);
        } elseif ($result['code'] === 401 || $result['code'] === 403) {
            echo json_encode(['valid' => false, 'message' => 'Geçersiz API anahtarı']);
        } else {
            echo json_encode(['valid' => false, 'message' => 'API hatası (HTTP ' . $result['code'] . ')']);
        }
        break;

    case 'fal':
        if (empty($key)) {
            echo json_encode(['valid' => false, 'message' => 'FAL_KEY gerekli. Almak için: fal.ai/dashboard/keys']);
            break;
        }
        // Test Fal.ai API with a minimal request
        $falPayload = [
            'prompt' => 'test',
            'image_size' => ['width' => 64, 'height' => 64],
            'num_inference_steps' => 1,
            'num_images' => 1
        ];
        $falResult = checkApiPost(
            'https://fal.run/fal-ai/flux/schnell',
            $falPayload,
            ['Authorization: Key ' . $key],
            30
        );
        if ($falResult['code'] === 200) {
            echo json_encode(['valid' => true, 'message' => 'Fal.ai API geçerli ✓ FLUX.1 Schnell hazır']);
        } elseif ($falResult['code'] === 401) {
            echo json_encode(['valid' => false, 'message' => 'Geçersiz API anahtarı']);
        } elseif ($falResult['code'] === 402 || strpos($falResult['body'], 'Exhausted balance') !== false) {
            echo json_encode(['valid' => false, 'message' => 'Bakiye yetersiz! Yüklemek için: fal.ai/dashboard/billing']);
        } elseif ($falResult['code'] === 403 || strpos($falResult['body'], 'locked') !== false) {
            echo json_encode(['valid' => false, 'message' => 'Hesap kilitli. Bakiye yükleyin: fal.ai/dashboard/billing']);
        } else {
            $errMsg = '';
            if (!empty($falResult['body'])) {
                $errData = json_decode($falResult['body'], true);
                $errMsg = $errData['detail'] ?? $errData['message'] ?? '';
            }
            echo json_encode(['valid' => false, 'message' => 'Fal.ai hatası (HTTP ' . $falResult['code'] . ')' . ($errMsg ? ": $errMsg" : '')]);
        }
        break;

    case 'pollinations':
        // Pollinations API key testi (yeni endpoint)
        if (empty($key)) {
            echo json_encode(['valid' => false, 'message' => 'API key gerekli. Almak için: pollinations.ai/pricing']);
            break;
        }
        $testUrl = 'https://gen.pollinations.ai/image/' . urlencode('blue sky') . '?model=flux&width=64&height=64&key=' . urlencode($key);
        $pollResult = checkApi($testUrl, [], 30);
        if ($pollResult['code'] === 200 && strlen($pollResult['body']) > 1000) {
            echo json_encode(['valid' => true, 'message' => 'Pollinations API geçerli ✓ FLUX modeli hazır']);
        } elseif ($pollResult['code'] === 401 || $pollResult['code'] === 403) {
            echo json_encode(['valid' => false, 'message' => 'Geçersiz API anahtarı']);
        } elseif ($pollResult['code'] === 500) {
            echo json_encode(['valid' => false, 'message' => 'Sunucu hatası - lütfen tekrar deneyin']);
        } else {
            echo json_encode(['valid' => false, 'message' => 'Pollinations hatası (HTTP ' . $pollResult['code'] . ')']);
        }
        break;

    case 'pollinations_image':
        $model = $input['model'] ?? 'flux';
        // Check connectivity using the fast /models endpoint first
        $modelsResult = checkApi("https://image.pollinations.ai/models", [], 10);
        if ($modelsResult['code'] !== 200) {
            echo json_encode(['valid' => false, 'message' => 'Pollinations image API erişilemiyor (HTTP ' . $modelsResult['code'] . ')']);
            break;
        }
        $availableModels = json_decode($modelsResult['body'], true) ?: [];
        // Add "flux" to list if not present (it still works even if not in models endpoint)
        $modelNote = in_array($model, $availableModels) ? $model : "$model (veya: " . implode(', ', $availableModels) . ")";
        echo json_encode(['valid' => true, 'message' => "Pollinations Görsel API çalışıyor ✓ (Mevcut modeller: " . implode(', ', $availableModels) . ")"]);
        break;

    case 'pollinations_text':
        $textModel = $input['model'] ?? 'openai';
        $payload = [
            'messages' => [
                ['role' => 'user', 'content' => 'Say "OK" in one word.']
            ],
            'model' => $textModel,
            'seed' => 42
        ];
        $result = checkApiPost("https://text.pollinations.ai/", $payload);
        if ($result['error']) {
            echo json_encode(['valid' => false, 'message' => 'Bağlantı hatası: ' . $result['error']]);
        } elseif ($result['code'] === 200 && strlen($result['body']) > 0) {
            echo json_encode(['valid' => true, 'message' => "Pollinations Text API çalışıyor ✓ (Model: $textModel)"]);
        } else {
            echo json_encode(['valid' => false, 'message' => 'Pollinations Text hatası (HTTP ' . $result['code'] . ')']);
        }
        break;

    case 'edge_tts':
        $pythonCmd = 'python';
        $testScript = 'import edge_tts; print("OK")';
        $output = [];
        $retCode = -1;
        exec("$pythonCmd -c \"$testScript\" 2>&1", $output, $retCode);
        if ($retCode === 0) {
            echo json_encode(['valid' => true, 'message' => 'Edge-TTS kurulu ve çalışıyor ✓']);
        } else {
            echo json_encode(['valid' => false, 'message' => 'Edge-TTS bulunamadı. Kurulum: pip install edge-tts']);
        }
        break;

    case 'ffmpeg':
        $output = [];
        $retCode = -1;
        exec("ffmpeg -version 2>&1", $output, $retCode);
        if ($retCode === 0) {
            $ver = isset($output[0]) ? $output[0] : '';
            echo json_encode(['valid' => true, 'message' => 'FFmpeg kurulu ✓ ' . substr($ver, 0, 60)]);
        } else {
            echo json_encode(['valid' => false, 'message' => 'FFmpeg bulunamadı. Kurulum gerekli.']);
        }
        break;

    case 'python':
        $output = [];
        $retCode = -1;
        exec("python --version 2>&1", $output, $retCode);
        if ($retCode === 0) {
            echo json_encode(['valid' => true, 'message' => 'Python kurulu ✓ ' . ($output[0] ?? '')]);
        } else {
            echo json_encode(['valid' => false, 'message' => 'Python bulunamadı.']);
        }
        break;

    default:
        echo json_encode(['valid' => false, 'message' => 'Bilinmeyen provider: ' . $provider]);
        break;
}

