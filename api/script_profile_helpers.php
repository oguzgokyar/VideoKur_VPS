<?php

const VIDEO_PROFILE_SCHEMA_VERSION = 2;

function vp_normalize_video_type($value) {
    $value = strtolower(trim((string)$value));
    return in_array($value, ['short', 'square', 'wide'], true) ? $value : 'short';
}

function vp_video_dimensions($videoType) {
    $videoType = vp_normalize_video_type($videoType);
    if ($videoType === 'square') return [1080, 1080];
    if ($videoType === 'wide') return [1920, 1080];
    return [1080, 1920];
}

function vp_normalize_category_id($value) {
    $raw = strtolower(trim((string)$value));
    if ($raw === '') return 'genel';
    $normalized = preg_replace('/[^a-z0-9\-_]+/u', '-', $raw);
    $normalized = trim((string)$normalized, '-');
    return $normalized !== '' ? $normalized : 'genel';
}

function vp_read_json($path, $fallback = []) {
    if (!is_file($path)) return $fallback;
    $decoded = json_decode((string)file_get_contents($path), true);
    return is_array($decoded) ? $decoded : $fallback;
}

function vp_atomic_write_json($path, $data) {
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        return false;
    }
    $encoded = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) return false;

    $temp = $path . '.tmp.' . getmypid() . '.' . bin2hex(random_bytes(4));
    if (file_put_contents($temp, $encoded, LOCK_EX) === false) return false;
    if (DIRECTORY_SEPARATOR === '\\' && file_exists($path)) {
        $backupTarget = $path . '.replace';
        @unlink($backupTarget);
        if (!@rename($path, $backupTarget)) {
            @unlink($temp);
            return false;
        }
        if (!@rename($temp, $path)) {
            @rename($backupTarget, $path);
            @unlink($temp);
            return false;
        }
        @unlink($backupTarget);
        return true;
    }
    return @rename($temp, $path);
}

function vp_load_credentials($baseDir) {
    return vp_read_json($baseDir . '/data/config.json', []);
}

function vp_clean_key_list($keys, $single = '') {
    $result = [];
    foreach (is_array($keys) ? $keys : [] as $key) {
        $key = trim((string)$key);
        if ($key !== '' && !in_array($key, $result, true)) $result[] = $key;
    }
    $single = trim((string)$single);
    if ($single !== '' && !in_array($single, $result, true)) $result[] = $single;
    return $result;
}

function vp_provider_capabilities($baseDir) {
    $config = vp_load_credentials($baseDir);
    $gemini = vp_clean_key_list($config['geminiKeys'] ?? [], $config['geminiKey'] ?? '');
    $eleven = vp_clean_key_list($config['elevenKeys'] ?? [], $config['elevenKey'] ?? '');
    $fal = vp_clean_key_list($config['falKeys'] ?? [], $config['falKey'] ?? '');
    $pollinations = vp_clean_key_list($config['pollinationsKeys'] ?? [], $config['pollinationsKey'] ?? '');
    $hf = trim((string)($config['hfKey'] ?? ''));
    $pexels = trim((string)($config['pexelsKey'] ?? ''));

    return [
        'prompt' => [
            ['id' => 'gemini', 'label' => 'Gemini', 'available' => !empty($gemini), 'supportsModel' => true,
                'models' => ['gemini-3.6-flash', 'gemini-3.5-flash', 'gemini-3.5-flash-lite', 'gemini-3.1-flash-lite', 'gemini-3.1-pro-preview', 'gemini-3-flash-preview']],
            ['id' => 'pollinations', 'label' => 'Pollinations Text', 'available' => true, 'supportsModel' => true,
                'models' => ['openai', 'openai-fast', 'mistral', 'qwen-coder', 'deepseek']],
        ],
        'visual' => [
            ['id' => 'fal', 'label' => 'Fal.ai', 'available' => !empty($fal), 'supportsModel' => true,
                'models' => ['fal-ai/flux/schnell']],
            ['id' => 'pollinations', 'label' => 'Pollinations', 'available' => !empty($pollinations), 'supportsModel' => true,
                'models' => ['flux', 'sana', 'grok-imagine', 'gptimage', 'zimage', 'qwen-image']],
            ['id' => 'huggingface', 'label' => 'HuggingFace', 'available' => $hf !== '', 'supportsModel' => true,
                'models' => ['black-forest-labs/FLUX.1-schnell']],
            ['id' => 'pexels', 'label' => 'Pexels', 'available' => $pexels !== '', 'supportsModel' => false, 'models' => []],
        ],
        'voiceover' => [
            ['id' => 'elevenlabs', 'label' => 'ElevenLabs', 'available' => !empty($eleven), 'supportsModel' => true, 'requiresVoice' => true,
                'models' => ['eleven_multilingual_v2', 'eleven_turbo_v2_5', 'eleven_flash_v2_5']],
            ['id' => 'edge-tts', 'label' => 'Edge TTS', 'available' => true, 'supportsModel' => false, 'requiresVoice' => true,
                'models' => [], 'voices' => [
                    ['id' => 'tr-TR-EmelNeural', 'name' => 'Emel (Kadın)'],
                    ['id' => 'tr-TR-AhmetNeural', 'name' => 'Ahmet (Erkek)'],
                ]],
        ],
    ];
}

function vp_first_available_provider($capabilities, $group, $preferred = '') {
    foreach ($capabilities[$group] ?? [] as $provider) {
        if (($provider['id'] ?? '') === $preferred && !empty($provider['available'])) return $provider;
    }
    foreach ($capabilities[$group] ?? [] as $provider) {
        if (!empty($provider['available'])) return $provider;
    }
    return null;
}

function vp_default_subtitle_style() {
    return [
        'FontName' => 'Arial',
        'FontSize' => 24,
        'PrimaryColour' => '#FFFFFF',
        'OutlineColour' => '#000000',
        'BackColour' => '#000000',
        'BorderStyle' => 3,
        'Outline' => 2,
        'Shadow' => 1,
        'MarginV' => 80,
        'MarginL' => 40,
        'MarginR' => 40,
        'Alignment' => 2,
        'Bold' => 1,
    ];
}

function vp_build_defaults($baseDir) {
    $legacy = vp_load_credentials($baseDir);
    $caps = vp_provider_capabilities($baseDir);
    $promptProvider = vp_first_available_provider($caps, 'prompt', $legacy['scriptProvider'] ?? 'gemini');
    $visualProvider = vp_first_available_provider($caps, 'visual', $legacy['imageService'] ?? 'pollinations');
    $voiceProvider = vp_first_available_provider($caps, 'voiceover', $legacy['ttsProvider'] ?? 'edge-tts');

    $promptId = $promptProvider['id'] ?? '';
    $visualId = $visualProvider['id'] ?? '';
    $voiceId = $voiceProvider['id'] ?? 'edge-tts';
    $subtitleStyle = is_array($legacy['subtitleStyle'] ?? null)
        ? array_replace(vp_default_subtitle_style(), $legacy['subtitleStyle'])
        : vp_default_subtitle_style();

    return [
        'prompt' => [
            'provider' => $promptId,
            'model' => $promptId === 'gemini'
                ? (string)($legacy['geminiModel'] ?? ($promptProvider['models'][0] ?? ''))
                : (string)($legacy['pollinationsTextModel'] ?? ($promptProvider['models'][0] ?? '')),
            'text' => '',
        ],
        'visual' => [
            'enabled' => true,
            'provider' => $visualId,
            'model' => $visualId === 'pollinations'
                ? (string)($legacy['pollinationsModel'] ?? ($visualProvider['models'][0] ?? ''))
                : (string)($visualProvider['models'][0] ?? ''),
            'options' => ['steps' => (int)($legacy['falSteps'] ?? 4)],
        ],
        'voiceover' => [
            'enabled' => true,
            'provider' => $voiceId,
            'model' => $voiceId === 'elevenlabs' ? 'eleven_multilingual_v2' : '',
            'voiceId' => $voiceId === 'edge-tts' ? 'tr-TR-EmelNeural' : '',
            'fallbackProvider' => '',
            'fallbackVoiceId' => '',
        ],
        'music' => [
            'mode' => 'off',
            'trackIds' => [],
            'volumeDb' => -22.0,
        ],
        'subtitles' => [
            'enabled' => true,
            'maxWordsPerLine' => 5,
            'style' => $subtitleStyle,
        ],
    ];
}

function vp_normalize_profile($script, $baseDir, $categoryName = null) {
    $defaults = vp_build_defaults($baseDir);
    $isV2 = (int)($script['schemaVersion'] ?? 0) === VIDEO_PROFILE_SCHEMA_VERSION
        && is_array($script['prompt'] ?? null);

    $prompt = $isV2 ? array_replace($defaults['prompt'], $script['prompt']) : $defaults['prompt'];
    if (!$isV2) $prompt['text'] = trim((string)($script['prompt'] ?? ''));
    $visual = array_replace_recursive($defaults['visual'], is_array($script['visual'] ?? null) ? $script['visual'] : []);
    $voiceover = array_replace($defaults['voiceover'], is_array($script['voiceover'] ?? null) ? $script['voiceover'] : []);
    $music = array_replace($defaults['music'], is_array($script['music'] ?? null) ? $script['music'] : []);
    $subtitles = array_replace_recursive($defaults['subtitles'], is_array($script['subtitles'] ?? null) ? $script['subtitles'] : []);

    $musicMode = strtolower(trim((string)($music['mode'] ?? 'off')));
    if (!in_array($musicMode, ['off', 'single', 'rotate'], true)) $musicMode = 'off';
    $trackIds = [];
    foreach (is_array($music['trackIds'] ?? null) ? $music['trackIds'] : [] as $trackId) {
        $trackId = trim((string)$trackId);
        if ($trackId !== '' && !in_array($trackId, $trackIds, true)) $trackIds[] = $trackId;
    }

    $categoryId = vp_normalize_category_id($script['categoryId'] ?? $script['contentType'] ?? 'genel');
    return [
        'schemaVersion' => VIDEO_PROFILE_SCHEMA_VERSION,
        'id' => trim((string)($script['id'] ?? '')),
        'name' => trim((string)($script['name'] ?? '')),
        'description' => trim((string)($script['description'] ?? '')),
        'categoryId' => $categoryId,
        'contentType' => trim((string)($categoryName ?? $script['contentType'] ?? $categoryId)),
        'videoType' => vp_normalize_video_type($script['videoType'] ?? 'short'),
        'maxDuration' => max(10, min(3600, (int)($script['maxDuration'] ?? 55))),
        'prompt' => [
            'provider' => trim((string)($prompt['provider'] ?? '')),
            'model' => trim((string)($prompt['model'] ?? '')),
            'text' => trim((string)($prompt['text'] ?? '')),
        ],
        'visual' => [
            'enabled' => (bool)($visual['enabled'] ?? true),
            'provider' => trim((string)($visual['provider'] ?? '')),
            'model' => trim((string)($visual['model'] ?? '')),
            'options' => is_array($visual['options'] ?? null) ? $visual['options'] : [],
        ],
        'voiceover' => [
            'enabled' => (bool)($voiceover['enabled'] ?? true),
            'provider' => trim((string)($voiceover['provider'] ?? '')),
            'model' => trim((string)($voiceover['model'] ?? '')),
            'voiceId' => trim((string)($voiceover['voiceId'] ?? '')),
            'fallbackProvider' => trim((string)($voiceover['fallbackProvider'] ?? '')),
            'fallbackVoiceId' => trim((string)($voiceover['fallbackVoiceId'] ?? '')),
        ],
        'music' => [
            'mode' => $musicMode,
            'trackIds' => $trackIds,
            'volumeDb' => max(-60.0, min(0.0, (float)($music['volumeDb'] ?? -22.0))),
        ],
        'subtitles' => [
            'enabled' => (bool)($subtitles['enabled'] ?? true),
            'maxWordsPerLine' => max(1, min(12, (int)($subtitles['maxWordsPerLine'] ?? 5))),
            'style' => array_replace(vp_default_subtitle_style(), is_array($subtitles['style'] ?? null) ? $subtitles['style'] : []),
        ],
        'createdAt' => $script['createdAt'] ?? date('c'),
        'updatedAt' => $script['updatedAt'] ?? date('c'),
    ];
}

function vp_category_name($categories, $categoryId) {
    foreach ($categories as $category) {
        if (($category['id'] ?? '') === $categoryId) return trim((string)($category['name'] ?? $categoryId));
    }
    return $categoryId;
}

function vp_normalize_categories($categories, $scripts = []) {
    $indexed = [];
    foreach (is_array($categories) ? $categories : [] as $category) {
        $id = vp_normalize_category_id($category['id'] ?? $category['name'] ?? '');
        $indexed[$id] = [
            'id' => $id,
            'name' => trim((string)($category['name'] ?? $id)),
            'active' => (bool)($category['active'] ?? true),
            'createdAt' => $category['createdAt'] ?? date('c'),
            'updatedAt' => $category['updatedAt'] ?? date('c'),
        ];
    }
    foreach ($scripts as $script) {
        $id = vp_normalize_category_id($script['categoryId'] ?? $script['contentType'] ?? 'genel');
        if (!isset($indexed[$id])) {
            $indexed[$id] = ['id' => $id, 'name' => trim((string)($script['contentType'] ?? $id)), 'active' => true, 'createdAt' => date('c'), 'updatedAt' => date('c')];
        }
    }
    if (!$indexed) $indexed['genel'] = ['id' => 'genel', 'name' => 'Genel', 'active' => true, 'createdAt' => date('c'), 'updatedAt' => date('c')];
    return array_values($indexed);
}

function vp_load_script_data($baseDir, $migrate = true) {
    $path = $baseDir . '/data/scripts.json';
    $raw = vp_read_json($path, ['scripts' => [], 'categories' => []]);
    $categories = vp_normalize_categories($raw['categories'] ?? [], $raw['scripts'] ?? []);
    $scripts = [];
    $changed = false;
    foreach ($raw['scripts'] ?? [] as $script) {
        $categoryId = vp_normalize_category_id($script['categoryId'] ?? $script['contentType'] ?? 'genel');
        $normalized = vp_normalize_profile($script, $baseDir, vp_category_name($categories, $categoryId));
        if ((int)($script['schemaVersion'] ?? 0) !== VIDEO_PROFILE_SCHEMA_VERSION || !is_array($script['prompt'] ?? null)) $changed = true;
        $scripts[] = $normalized;
    }
    $data = ['schemaVersion' => VIDEO_PROFILE_SCHEMA_VERSION, 'scripts' => $scripts, 'categories' => $categories];

    if ($migrate && $changed) {
        $backup = $baseDir . '/data/scripts.pre_v2.json';
        if (!file_exists($backup)) vp_atomic_write_json($backup, $raw);
        vp_atomic_write_json($path, $data);
    }
    return $data;
}

function vp_save_script_data($baseDir, $data) {
    return vp_atomic_write_json($baseDir . '/data/scripts.json', [
        'schemaVersion' => VIDEO_PROFILE_SCHEMA_VERSION,
        'scripts' => array_values($data['scripts'] ?? []),
        'categories' => array_values($data['categories'] ?? []),
    ]);
}

function vp_find_script($baseDir, $scriptId) {
    foreach (vp_load_script_data($baseDir, true)['scripts'] as $script) {
        if (($script['id'] ?? '') === $scriptId) return $script;
    }
    return null;
}

function vp_find_capability($capabilities, $group, $providerId) {
    foreach ($capabilities[$group] ?? [] as $provider) {
        if (($provider['id'] ?? '') === $providerId) return $provider;
    }
    return null;
}

function vp_validate_profile($profile, $baseDir) {
    $errors = [];
    if (trim((string)($profile['name'] ?? '')) === '') $errors[] = 'Script adı gerekli.';
    if (trim((string)($profile['prompt']['text'] ?? '')) === '') $errors[] = 'Prompt metni gerekli.';
    $caps = vp_provider_capabilities($baseDir);

    foreach ([['prompt', 'prompt'], ['visual', 'visual'], ['voiceover', 'voiceover']] as [$field, $group]) {
        if ($field !== 'prompt' && empty($profile[$field]['enabled'])) continue;
        $providerId = trim((string)($profile[$field]['provider'] ?? ''));
        $cap = vp_find_capability($caps, $group, $providerId);
        if (!$cap || empty($cap['available'])) {
            $errors[] = ucfirst($field) . ' için kullanılabilir bir servis seçin.';
            continue;
        }
        if (!empty($cap['supportsModel']) && trim((string)($profile[$field]['model'] ?? '')) === '') {
            $errors[] = $cap['label'] . ' için model seçin.';
        }
        if (!empty($cap['requiresVoice']) && trim((string)($profile[$field]['voiceId'] ?? '')) === '') {
            $errors[] = $cap['label'] . ' için ses seçin veya ses kimliği girin.';
        }
    }

    $mode = $profile['music']['mode'] ?? 'off';
    $trackIds = $profile['music']['trackIds'] ?? [];
    if (($mode === 'single' || $mode === 'rotate') && empty($trackIds)) $errors[] = 'Müzik modu için en az bir parça seçin.';
    if ($mode === 'single' && count($trackIds) > 1) $errors[] = 'Tekli müzik modunda yalnızca bir parça seçilebilir.';
    return $errors;
}

function vp_public_summary($profile) {
    return [
        'id' => $profile['id'],
        'name' => $profile['name'],
        'description' => $profile['description'],
        'categoryId' => $profile['categoryId'],
        'contentType' => $profile['contentType'],
        'videoType' => $profile['videoType'],
        'maxDuration' => $profile['maxDuration'],
        'visualLabel' => !empty($profile['visual']['enabled']) ? trim($profile['visual']['provider'] . ' / ' . $profile['visual']['model'], ' /') : 'Kapalı',
        'voiceLabel' => !empty($profile['voiceover']['enabled']) ? trim($profile['voiceover']['provider'] . ' / ' . $profile['voiceover']['voiceId'], ' /') : 'Kapalı',
        'musicMode' => $profile['music']['mode'] ?? 'off',
        'subtitleEnabled' => (bool)($profile['subtitles']['enabled'] ?? true),
    ];
}

