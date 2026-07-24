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
    'geminiKey' => '',
    'elevenKey' => '',
    'hfKey' => '',
    'pexelsKey' => '',
    'falKey' => '',
    'pollinationsKey' => '',
    // Multi-key arrays
    'geminiKeys' => [],
    'elevenKeys' => [],
    'falKeys' => [],
    'pollinationsKeys' => [],
    'ttsProvider' => 'elevenlabs',
    'geminiModel' => 'gemini-2.0-flash',
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
    ]
];

// GET: Config oku
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists($configFile)) {
        $saved = json_decode(file_get_contents($configFile), true) ?: [];
        echo json_encode(array_replace_recursive($defaults, $saved));
    } else {
        echo json_encode($defaults);
    }
    exit;
}

// POST: Config kaydet
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        echo json_encode(['success' => false, 'error' => 'Geçersiz JSON verisi']);
        exit;
    }
    // subtitleStyle'ı düzgün şekilde parse et
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

    $config = [
        'geminiKey' => $input['geminiKey'] ?? '',
        'elevenKey' => $input['elevenKey'] ?? '',
        'hfKey' => $input['hfKey'] ?? '',
        'pexelsKey' => $input['pexelsKey'] ?? '',
        'falKey' => $input['falKey'] ?? '',
        'pollinationsKey' => $input['pollinationsKey'] ?? '',
        // Multi-key arrays for API pool
        'geminiKeys' => array_values(array_filter($input['geminiKeys'] ?? [])),
        'elevenKeys' => array_values(array_filter($input['elevenKeys'] ?? [])),
        'falKeys' => array_values(array_filter($input['falKeys'] ?? [])),
        'pollinationsKeys' => array_values(array_filter($input['pollinationsKeys'] ?? [])),
        'ttsProvider' => $input['ttsProvider'] ?? 'elevenlabs',
        'geminiModel' => $input['geminiModel'] ?? 'gemini-2.0-flash',
        'imageService' => $input['imageService'] ?? 'pollinations',
        'pollinationsModel' => $input['pollinationsModel'] ?? 'flux',
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
            'edge_tts' => (bool)($input['servicesEnabled']['edge_tts'] ?? true)
        ],
        'subtitleStyle' => $subtitleStyle ?? $defaults['subtitleStyle']
    ];
    $written = file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    if ($written === false) {
        echo json_encode(['success' => false, 'error' => 'Dosya yazılamadı: ' . $configFile]);
    } else {
        echo json_encode(['success' => true]);
    }
    exit;
}
