<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'POST gerekli']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$jobId   = $input['jobId']   ?? '';
$section = $input['section'] ?? '';
$extra   = $input['extra']   ?? [];  // optional: image_service, scene_index, subtitle_style, prompt

$validSections = ['news', 'script', 'images', 'image_single', 'update_prompt', 'update_special_prompt', 'tts', 'subtitles', 'video'];

if (empty($jobId) || !in_array($section, $validSections)) {
    echo json_encode(['error' => 'Geçersiz jobId veya bölüm']);
    exit;
}

$dataDir   = __DIR__ . '/../data';
$jobsDir   = $dataDir . '/jobs';
$jobFile   = "$jobsDir/$jobId.json";

if (!file_exists($jobFile)) {
    echo json_encode(['error' => 'İş bulunamadı']);
    exit;
}

$outputDir    = __DIR__ . '/../output/' . $jobId;
$pythonScript = __DIR__ . '/../python/regenerate.py';
$configFile   = "$dataDir/config.json";
$pythonCmd    = 'python';

if (!is_dir($outputDir)) { mkdir($outputDir, 0777, true); }

// Sync job status BEFORE launching background Python so polling sees correct state immediately
$syncStatusMap = [
    'news'         => 'scraping',
    'script'       => 'scripting',
    'images'       => 'imaging',
    'image_single' => 'imaging',
    'tts'          => 'tts',
    'subtitles'    => 'subtitling',
    'video'        => 'composing',
];
if (isset($syncStatusMap[$section])) {
    $jobData = json_decode(file_get_contents($jobFile), true) ?: [];
    $jobData['status'] = $syncStatusMap[$section];
    $jobData['error']  = '';
    file_put_contents($jobFile, json_encode($jobData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$logFile   = "$outputDir/regen_log.txt";
$extraJson = json_encode($extra, JSON_UNESCAPED_UNICODE);

// For update_prompt we run synchronously (fast, no pipeline spawn needed)
if ($section === 'update_prompt') {
    $cmd = "$pythonCmd \"$pythonScript\" \"$jobId\" \"$section\" \"$configFile\" " . escapeshellarg($extraJson);
    exec($cmd . ' 2>&1', $output, $retCode);
    echo json_encode(['success' => $retCode === 0, 'jobId' => $jobId, 'section' => $section]);
    exit;
}

// update_special_prompt: hook/outro/thumbnail prompt'unu doğrudan script.json'a yaz
if ($section === 'update_special_prompt') {
    $field  = $extra['field']  ?? '';  // hook_image_prompt | outro_image_prompt | thumbnail_image_prompt
    $prompt = $extra['prompt'] ?? '';
    $allowed = ['hook_image_prompt', 'outro_image_prompt', 'thumbnail_image_prompt'];
    if (empty($field) || !in_array($field, $allowed)) {
        echo json_encode(['error' => 'Geçersiz field: ' . $field]);
        exit;
    }
    $scriptFile = "$outputDir/script.json";
    if (!file_exists($scriptFile)) {
        echo json_encode(['error' => 'script.json bulunamadı']);
        exit;
    }
    $scriptData = json_decode(file_get_contents($scriptFile), true) ?: [];
    $scriptData[$field] = $prompt;
    file_put_contents($scriptFile, json_encode($scriptData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(['success' => true, 'jobId' => $jobId, 'section' => $section, 'field' => $field]);
    exit;
}

if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
    $cmd = "start /B $pythonCmd \"$pythonScript\" \"$jobId\" \"$section\" \"$configFile\" " . escapeshellarg($extraJson) . " > \"$logFile\" 2>&1";
} else {
    $cmd = "$pythonCmd \"$pythonScript\" \"$jobId\" \"$section\" \"$configFile\" " . escapeshellarg($extraJson) . " > \"$logFile\" 2>&1 &";
}

pclose(popen($cmd, 'r'));

echo json_encode(['success' => true, 'jobId' => $jobId, 'section' => $section]);

