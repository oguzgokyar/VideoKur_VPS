<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$configFile = __DIR__ . '/../data/config.json';
$dataDir = __DIR__ . '/../data';
if (!is_dir($dataDir)) { mkdir($dataDir, 0777, true); }

$defaults = [
    'youtubeBaseUrl' => '',
    'geminiKey' => '',
    'elevenKey' => '',
    'cartesiaKey' => '',
    'hfKey' => '',
    'pexelsKey' => '',
    'falKey' => '',
    'pollinationsKey' => '',
    'geminiKeys' => [],
    'elevenKeys' => [],
    'cartesiaKeys' => [],
    'falKeys' => [],
    'pollinationsKeys' => [],
    'ttsProvider' => 'elevenlabs',
    'geminiModel' => 'gemini-3.6-flash',
    'imageService' => 'pollinations',
    'pollinationsModel' => 'flux',
    'pollinationsTextModel' => 'openai',
    'scriptProvider' => 'gemini',
    'falWidth' => 768,
    'falHeight' => 768,
    'falSteps' => 4,
    'toolsEnabled' => [
        'scriptGen' => true,
        'imageGen' => true,
        'ttsGen' => true,
        'videoCompose' => true
    ],
    'servicesEnabled' => [
        'fal_image' => true,
        'pollinations_image' => true,
        'huggingface_image' => true,
        'pexels_image' => true,
        'gemini_script' => true,
        'pollinations_text' => true,
        'elevenlabs_tts' => true,
        'cartesia_tts' => true,
        'edge_tts' => true
    ],
    'subtitleStyle' => [
        'FontName' => 'Arial',
        'FontSize' => 24,
        'PrimaryColour' => '#FFFFFF',
        'OutlineColour' => '#000000',
        'BorderStyle' => 3,
        'Outline' => 3,
        'Shadow' => 1,
        'MarginV' => 100,
        'MarginL' => 40,
        'MarginR' => 40,
        'Alignment' => 2,
        'Bold' => 1
    ],
    'metaWebUiEnabled' => false,
    'socialStaging' => [
        'enabled' => false,
        'provider' => 'r2',
        'bucket' => '',
        'region' => 'auto',
        'endpointUrl' => '',
        'accessKeyId' => '',
        'secretAccessKey' => '',
        'publicBaseUrl' => '',
        'prefix' => 'instagram',
        'cleanupAfterUpload' => true
    ]
];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists($configFile)) {
        $saved = json_decode(file_get_contents($configFile), true) ?: [];
        $result = array_replace_recursive($defaults, $saved);
        foreach (['ttsProvider','geminiModel','imageService','pollinationsModel','pollinationsTextModel','scriptProvider','falWidth','falHeight','falSteps','toolsEnabled','servicesEnabled','subtitleStyle'] as $key) unset($result[$key]);
        echo json_encode($result);
    } else {
        $result = $defaults;
        foreach (['ttsProvider','geminiModel','imageService','pollinationsModel','pollinationsTextModel','scriptProvider','falWidth','falHeight','falSteps','toolsEnabled','servicesEnabled','subtitleStyle'] as $key) unset($result[$key]);
        echo json_encode($result);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        echo json_encode(['success' => false, 'error' => 'Geçersiz JSON verisi']);
        exit;
    }

    $existing = [];
    if (file_exists($configFile)) {
        $existing = json_decode(file_get_contents($configFile), true) ?: [];
    }

    $subtitleStyle = null;
    if (isset($input['subtitleStyle']) && is_array($input['subtitleStyle'])) {
        $subtitleStyle = [
            'FontName' => $input['subtitleStyle']['FontName'] ?? 'Arial',
            'FontSize' => (int)($input['subtitleStyle']['FontSize'] ?? 24),
            'PrimaryColour' => $input['subtitleStyle']['PrimaryColour'] ?? '#FFFFFF',
            'OutlineColour' => $input['subtitleStyle']['OutlineColour'] ?? '#000000',
            'BorderStyle' => (int)($input['subtitleStyle']['BorderStyle'] ?? 3),
            'Outline' => (int)($input['subtitleStyle']['Outline'] ?? 3),
            'Shadow' => (int)($input['subtitleStyle']['Shadow'] ?? 1),
            'MarginV' => (int)($input['subtitleStyle']['MarginV'] ?? 100),
            'MarginL' => (int)($input['subtitleStyle']['MarginL'] ?? 40),
            'MarginR' => (int)($input['subtitleStyle']['MarginR'] ?? 40),
            'Alignment' => (int)($input['subtitleStyle']['Alignment'] ?? 2),
            'Bold' => (int)($input['subtitleStyle']['Bold'] ?? 1)
        ];
    }

    $normalizeKeys = function($keys) {
        if (!is_array($keys)) {
            return [];
        }
        $clean = [];
        foreach ($keys as $key) {
            $key = trim((string)$key);
            if ($key !== '' && !in_array($key, $clean, true)) {
                $clean[] = $key;
            }
        }
        return $clean;
    };

    $geminiKeys = $normalizeKeys($input['geminiKeys'] ?? []);
    $elevenKeys = $normalizeKeys($input['elevenKeys'] ?? []);
    $cartesiaKeys = $normalizeKeys($input['cartesiaKeys'] ?? []);
    $falKeys = $normalizeKeys($input['falKeys'] ?? []);
    $pollinationsKeys = $normalizeKeys($input['pollinationsKeys'] ?? []);
    $pollinationsModel = trim((string)($input['pollinationsModel'] ?? 'flux'));
    $youtubeBaseUrl = rtrim(trim((string)($input['youtubeBaseUrl'] ?? ($existing['youtubeBaseUrl'] ?? ''))), '/');
    if ($youtubeBaseUrl !== '' && !filter_var($youtubeBaseUrl, FILTER_VALIDATE_URL)) {
        echo json_encode(['success' => false, 'error' => 'YouTube OAuth uygulama adresi geçerli bir URL olmalıdır.']);
        exit;
    }
    if ($youtubeBaseUrl !== '' && !in_array(strtolower((string)parse_url($youtubeBaseUrl, PHP_URL_SCHEME)), ['http', 'https'], true)) {
        echo json_encode(['success' => false, 'error' => 'YouTube OAuth uygulama adresi http veya https ile başlamalıdır.']);
        exit;
    }
    if ($pollinationsModel === '' || !preg_match('/^[A-Za-z0-9._:-]{1,120}$/', $pollinationsModel)) {
        echo json_encode(['success' => false, 'error' => 'Pollinations model adı boş olamaz; yalnızca harf, sayı, nokta, alt çizgi, tire ve iki nokta kullanın.']);
        exit;
    }
    $knownConfig = [
        'youtubeBaseUrl' => $youtubeBaseUrl,
        'geminiKey' => $geminiKeys[0] ?? '',
        'elevenKey' => $elevenKeys[0] ?? trim((string)($input['elevenKey'] ?? '')),
        'cartesiaKey' => $cartesiaKeys[0] ?? trim((string)($input['cartesiaKey'] ?? '')),
        'hfKey' => trim((string)($input['hfKey'] ?? '')),
        'pexelsKey' => trim((string)($input['pexelsKey'] ?? '')),
        'falKey' => $falKeys[0] ?? trim((string)($input['falKey'] ?? '')),
        'pollinationsKey' => $pollinationsKeys[0] ?? trim((string)($input['pollinationsKey'] ?? '')),
        'geminiKeys' => $geminiKeys,
        'elevenKeys' => $elevenKeys,
        'cartesiaKeys' => $cartesiaKeys,
        'falKeys' => $falKeys,
        'pollinationsKeys' => $pollinationsKeys,
        'ttsProvider' => $input['ttsProvider'] ?? 'elevenlabs',
        'geminiModel' => $input['geminiModel'] ?? 'gemini-3.6-flash',
        'imageService' => $input['imageService'] ?? 'pollinations',
        'pollinationsModel' => $pollinationsModel,
        'pollinationsTextModel' => $input['pollinationsTextModel'] ?? 'openai',
        'scriptProvider' => $input['scriptProvider'] ?? 'gemini',
        'falWidth' => (int)($input['falWidth'] ?? 768),
        'falHeight' => (int)($input['falHeight'] ?? 768),
        'falSteps' => (int)($input['falSteps'] ?? 4),
        'toolsEnabled' => [
            'scriptGen' => (bool)($input['toolsEnabled']['scriptGen'] ?? true),
            'imageGen' => (bool)($input['toolsEnabled']['imageGen'] ?? true),
            'ttsGen' => (bool)($input['toolsEnabled']['ttsGen'] ?? true),
            'videoCompose' => (bool)($input['toolsEnabled']['videoCompose'] ?? true)
        ],
        'servicesEnabled' => [
            'fal_image' => (bool)($input['servicesEnabled']['fal_image'] ?? true),
            'pollinations_image' => (bool)($input['servicesEnabled']['pollinations_image'] ?? true),
            'huggingface_image' => (bool)($input['servicesEnabled']['huggingface_image'] ?? true),
            'pexels_image' => (bool)($input['servicesEnabled']['pexels_image'] ?? true),
            'gemini_script' => (bool)($input['servicesEnabled']['gemini_script'] ?? true),
            'pollinations_text' => (bool)($input['servicesEnabled']['pollinations_text'] ?? true),
            'elevenlabs_tts' => (bool)($input['servicesEnabled']['elevenlabs_tts'] ?? true),
            'cartesia_tts' => (bool)($input['servicesEnabled']['cartesia_tts'] ?? true),
            'edge_tts' => (bool)($input['servicesEnabled']['edge_tts'] ?? true)
        ],
        'subtitleStyle' => $subtitleStyle ?? $defaults['subtitleStyle']
    ];
    $config = array_replace_recursive($existing, $knownConfig);

    if (array_key_exists('metaWebUiEnabled', $input)) {
        $config['metaWebUiEnabled'] = (bool)$input['metaWebUiEnabled'];
    }
    if (array_key_exists('socialStaging', $input) && is_array($input['socialStaging'])) {
        $baseStaging = is_array($config['socialStaging'] ?? null) ? $config['socialStaging'] : $defaults['socialStaging'];
        $config['socialStaging'] = array_replace($baseStaging, $input['socialStaging']);
    }

    foreach (['ttsProvider','geminiModel','imageService','pollinationsModel','pollinationsTextModel','scriptProvider','falWidth','falHeight','falSteps','toolsEnabled','servicesEnabled','subtitleStyle'] as $key) unset($config[$key]);
    $written = file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    if ($written === false) {
        echo json_encode(['success' => false, 'error' => 'Dosya yazılamadı: ' . $configFile]);
    } else {
        echo json_encode(['success' => true]);
    }
    exit;
}
