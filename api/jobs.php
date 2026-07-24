<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$baseDir = dirname(__DIR__);
$dataDir = $baseDir . '/data';
$jobsDir = $dataDir . '/jobs';
$outputDir = $baseDir . '/output';
$configFile = $dataDir . '/config.json';
$scriptsFile = $dataDir . '/scripts.json';
$queuesFile = $dataDir . '/queues.json';
$socialQueueFile = $dataDir . '/social_queue.json';
$contentPoolFile = $dataDir . '/content_pool.json';
$pythonCmd = 'python';
require_once __DIR__ . '/music_helpers.php';

if (!is_dir($jobsDir)) { mkdir($jobsDir, 0777, true); }
if (!is_dir($outputDir)) { mkdir($outputDir, 0777, true); }

function deleteDirectoryRecursive($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    $items = array_diff(scandir($dir), ['.', '..']);
    foreach ($items as $item) {
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            deleteDirectoryRecursive($path);
        } elseif (file_exists($path)) {
            @unlink($path);
        }
    }
    return @rmdir($dir);
}

function findScriptById($scriptId) {
    global $scriptsFile;
    if (!file_exists($scriptsFile)) {
        return null;
    }

    $data = json_decode(file_get_contents($scriptsFile), true);
    $scripts = $data['scripts'] ?? [];
    foreach ($scripts as $script) {
        if (($script['id'] ?? '') === $scriptId) {
            return $script;
        }
    }
    return null;
}

function loadProductionQueueData() {
    global $dataDir;
    $queueFile = $dataDir . '/production_queue.json';
    if (!file_exists($queueFile)) {
        return [
            'queue' => [],
            'current_job' => null,
            'settings' => [
                'auto_start_next' => true,
                'max_retries' => 3,
                'retry_delay_seconds' => 60
            ],
            'stats' => [
                'total_queued' => 0,
                'total_processed' => 0,
                'total_completed' => 0,
                'total_failed' => 0,
                'last_started' => null,
                'last_completed' => null
            ],
            'metadata' => [
                'created_at' => date('c'),
                'last_updated' => date('c'),
                'version' => '1.0'
            ]
        ];
    }
    $data = json_decode(file_get_contents($queueFile), true);
    return is_array($data) ? $data : ['queue' => []];
}

function saveProductionQueueData($queueData) {
    global $dataDir;
    $queueFile = $dataDir . '/production_queue.json';
    if (!isset($queueData['metadata']) || !is_array($queueData['metadata'])) {
        $queueData['metadata'] = [];
    }
    $queueData['metadata']['last_updated'] = date('c');
    file_put_contents($queueFile, json_encode($queueData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function enqueueProductionJob($jobId, $priority = 0, $metadata = []) {
    $queue = loadProductionQueueData();
    if (!isset($queue['queue']) || !is_array($queue['queue'])) {
        $queue['queue'] = [];
    }

    if (($queue['current_job'] ?? null) === $jobId) {
        return [
            'success' => true,
            'already_processing' => true,
            'message' => 'Job is currently being processed'
        ];
    }

    foreach ($queue['queue'] as $existing) {
        if (($existing['job_id'] ?? '') === $jobId) {
            return [
                'success' => true,
                'already_queued' => true,
                'position' => $existing['position'] ?? null,
                'queue_length' => count($queue['queue']),
                'message' => 'Job already in queue'
            ];
        }
    }

    $queue['queue'][] = [
        'job_id' => $jobId,
        'status' => 'waiting',
        'priority' => intval($priority),
        'added_at' => date('c'),
        'started_at' => null,
        'completed_at' => null,
        'retry_count' => 0,
        'last_error' => null,
        'metadata' => is_array($metadata) ? $metadata : []
    ];

    if (!isset($queue['stats']) || !is_array($queue['stats'])) {
        $queue['stats'] = [];
    }
    $queue['stats']['total_queued'] = intval($queue['stats']['total_queued'] ?? 0) + 1;

    usort($queue['queue'], function($a, $b) {
        if (($a['priority'] ?? 0) !== ($b['priority'] ?? 0)) {
            return ($b['priority'] ?? 0) - ($a['priority'] ?? 0);
        }
        return strcmp((string)($a['added_at'] ?? ''), (string)($b['added_at'] ?? ''));
    });

    $position = null;
    foreach ($queue['queue'] as $i => &$item) {
        $item['position'] = $i + 1;
        if (($item['job_id'] ?? '') === $jobId) {
            $position = $item['position'];
        }
    }
    unset($item);

    saveProductionQueueData($queue);

    return [
        'success' => true,
        'position' => $position,
        'queue_length' => count($queue['queue'])
    ];
}

function loadQueuesData() {
    global $queuesFile;
    if (!file_exists($queuesFile)) {
        return ['queues' => []];
    }
    $data = json_decode(file_get_contents($queuesFile), true);
    return is_array($data) ? $data : ['queues' => []];
}

function saveQueuesData($data) {
    global $queuesFile;
    file_put_contents($queuesFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function removeJobFromQueuesJson($jobId) {
    $data = loadQueuesData();
    $removed = 0;
    $updated = false;

    foreach ($data['queues'] as &$queue) {
        $videos = $queue['videos'] ?? [];
        $before = count($videos);
        $queue['videos'] = array_values(array_filter($videos, function($video) use ($jobId) {
            return ($video['job_id'] ?? '') !== $jobId;
        }));
        $removed += ($before - count($queue['videos']));
        if ($before !== count($queue['videos'])) {
            $updated = true;
            foreach ($queue['videos'] as $i => &$video) {
                $video['position'] = $i + 1;
            }
            unset($video);
        }
    }
    unset($queue);

    if ($updated) {
        saveQueuesData($data);
    }

    return $removed;
}

function removeJobFromSocialQueue($jobId) {
    global $socialQueueFile;
    if (!file_exists($socialQueueFile)) {
        return 0;
    }
    $data = json_decode(file_get_contents($socialQueueFile), true);
    if (!is_array($data)) {
        $data = ['queue' => []];
    }
    $queue = $data['queue'] ?? [];
    $before = count($queue);
    $data['queue'] = array_values(array_filter($queue, function($item) use ($jobId) {
        return ($item['job_id'] ?? '') !== $jobId;
    }));
    $removed = $before - count($data['queue']);
    if ($removed > 0) {
        file_put_contents($socialQueueFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    return $removed;
}

function removeJobFromProductionQueue($jobId) {
    $queue = loadProductionQueueData();
    $removed = 0;

    foreach (['queue', 'production_queue'] as $key) {
        $items = $queue[$key] ?? [];
        $before = count($items);
        $queue[$key] = array_values(array_filter($items, function($item) use ($jobId) {
            return ($item['job_id'] ?? '') !== $jobId;
        }));
        $removed += ($before - count($queue[$key]));
        if (!empty($queue[$key])) {
            foreach ($queue[$key] as $i => &$item) {
                $item['position'] = $i + 1;
            }
            unset($item);
        }
    }

    if (($queue['current_job'] ?? null) === $jobId) {
        $queue['current_job'] = null;
        $removed++;
    }

    if ($removed > 0) {
        saveProductionQueueData($queue);
    }
    return $removed;
}

function clearContentPoolJobReferences($jobId) {
    global $contentPoolFile;
    if (!file_exists($contentPoolFile)) {
        return 0;
    }

    $pool = json_decode(file_get_contents($contentPoolFile), true);
    if (!is_array($pool)) {
        return 0;
    }

    $updated = 0;
    foreach ($pool['content'] ?? [] as &$item) {
        if (($item['processed_job_id'] ?? null) === $jobId) {
            $item['processed_job_id'] = null;
            if (($item['status'] ?? '') !== 'completed') {
                $item['status'] = 'pending';
            }
            $updated++;
        }
    }
    unset($item);

    if ($updated > 0) {
        if (!isset($pool['metadata']) || !is_array($pool['metadata'])) {
            $pool['metadata'] = [];
        }
        $pool['metadata']['last_updated'] = gmdate('Y-m-d\TH:i:s\Z');
        $pool['metadata']['total_items'] = count($pool['content'] ?? []);
        file_put_contents($contentPoolFile, json_encode($pool, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    return $updated;
}

function clearAllVideoProductions() {
    global $jobsDir, $outputDir, $socialQueueFile, $dataDir, $contentPoolFile;

    $jobFiles = glob("$jobsDir/*.json") ?: [];
    $jobIds = [];
    foreach ($jobFiles as $jobFile) {
        $jobIds[] = pathinfo($jobFile, PATHINFO_FILENAME);
    }

    $deletedJobFiles = 0;
    foreach ($jobFiles as $jobFile) {
        if (@unlink($jobFile)) {
            $deletedJobFiles++;
        }
    }

    $deletedOutputDirs = 0;
    $outputJobDirs = glob("$outputDir/job_*", GLOB_ONLYDIR) ?: [];
    foreach ($outputJobDirs as $dir) {
        if (deleteDirectoryRecursive($dir)) {
            $deletedOutputDirs++;
        }
    }

    $queuesRemoved = 0;
    $queuesData = loadQueuesData();
    $queuesUpdated = false;
    foreach ($queuesData['queues'] ?? [] as &$queue) {
        $videos = $queue['videos'] ?? [];
        $queuesRemoved += count($videos);
        if (!empty($videos)) {
            $queue['videos'] = [];
            $queuesUpdated = true;
        }
    }
    unset($queue);
    if ($queuesUpdated) {
        saveQueuesData($queuesData);
    }

    $socialRemoved = 0;
    if (file_exists($socialQueueFile)) {
        $socialData = json_decode(file_get_contents($socialQueueFile), true);
        if (!is_array($socialData)) {
            $socialData = [];
        }
        $socialRemoved = count($socialData['queue'] ?? []);
        $socialData['queue'] = [];
        $socialData['current_job'] = null;
        if (isset($socialData['metadata']) && is_array($socialData['metadata'])) {
            $socialData['metadata']['last_updated'] = date('c');
        }
        file_put_contents($socialQueueFile, json_encode($socialData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    $productionRemoved = 0;
    $productionData = loadProductionQueueData();
    $productionRemoved += count($productionData['queue'] ?? []);
    $productionRemoved += count($productionData['production_queue'] ?? []);
    if (!empty($productionData['current_job'])) {
        $productionRemoved++;
    }
    $productionData['queue'] = [];
    $productionData['production_queue'] = [];
    $productionData['current_job'] = null;
    saveProductionQueueData($productionData);

    $contentPoolUpdated = 0;
    if (file_exists($contentPoolFile)) {
        $pool = json_decode(file_get_contents($contentPoolFile), true);
        if (is_array($pool)) {
            foreach ($pool['content'] ?? [] as &$item) {
                if (!empty($item['processed_job_id'])) {
                    $item['processed_job_id'] = null;
                    if (($item['status'] ?? '') !== 'completed') {
                        $item['status'] = 'pending';
                    }
                    $contentPoolUpdated++;
                }
            }
            unset($item);
            if ($contentPoolUpdated > 0) {
                if (!isset($pool['metadata']) || !is_array($pool['metadata'])) {
                    $pool['metadata'] = [];
                }
                $pool['metadata']['last_updated'] = gmdate('Y-m-d\TH:i:s\Z');
                $pool['metadata']['total_items'] = count($pool['content'] ?? []);
                file_put_contents($contentPoolFile, json_encode($pool, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }
    }

    $schedulerErrorsCleared = 0;
    $schedulerErrorsFile = $dataDir . '/scheduler_errors.json';
    if (file_exists($schedulerErrorsFile)) {
        $errorsData = json_decode(file_get_contents($schedulerErrorsFile), true);
        if (!is_array($errorsData)) {
            $errorsData = [];
        }
        $schedulerErrorsCleared = count($errorsData['errors'] ?? []);
        $errorsData['errors'] = [];
        file_put_contents($schedulerErrorsFile, json_encode($errorsData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    return [
        'jobs_deleted' => $deletedJobFiles,
        'output_dirs_deleted' => $deletedOutputDirs,
        'queues_videos_removed' => $queuesRemoved,
        'social_queue_removed' => $socialRemoved,
        'production_queue_removed' => $productionRemoved,
        'content_pool_updated' => $contentPoolUpdated,
        'scheduler_errors_cleared' => $schedulerErrorsCleared
    ];
}

// Helper: Detect resume point for a job
function detectResumePoint($jobId) {
    global $outputDir;
    
    $jobOutputDir = "$outputDir/$jobId";
    
    if (!is_dir($jobOutputDir)) {
        return [
            'resume_from' => 'scraping',
            'completed_stages' => [],
            'missing_files' => ['output directory'],
            'can_resume' => true,
            'message' => 'No output files - will start from beginning',
            'progress' => '0/6',
            'progress_percent' => 0
        ];
    }
    
    $completed = [];
    $resumeFrom = 'scraping';
    $missingFiles = [];
    
    // Check scraping (news.json)
    if (file_exists("$jobOutputDir/news.json")) {
        $completed[] = 'scraping';
        $resumeFrom = 'scripting';
    } else {
        $missingFiles[] = 'news.json';
        return buildResumeResult('scraping', $completed, $missingFiles, true, 
            'Will start from scraping stage');
    }
    
    // Check scripting (script.json)
    if (file_exists("$jobOutputDir/script.json")) {
        $completed[] = 'scripting';
        $resumeFrom = 'imaging';
    } else {
        $missingFiles[] = 'script.json';
        return buildResumeResult('scripting', $completed, $missingFiles, true,
            'Will resume from script generation');
    }
    
    // Check imaging (images/*.png)
    if (is_dir("$jobOutputDir/images")) {
        $images = glob("$jobOutputDir/images/*.png");
        if (count($images) > 0) {
            $completed[] = 'imaging';
            $resumeFrom = 'tts';
        } else {
            $missingFiles[] = 'images/*.png';
            return buildResumeResult('imaging', $completed, $missingFiles, true,
                'Will resume from image generation');
        }
    } else {
        $missingFiles[] = 'images/';
        return buildResumeResult('imaging', $completed, $missingFiles, true,
            'Will resume from image generation');
    }
    
    // Check TTS (audio.mp3 or audio_segments/*.mp3)
    if (file_exists("$jobOutputDir/audio.mp3") || 
        (is_dir("$jobOutputDir/audio_segments") && count(glob("$jobOutputDir/audio_segments/*.mp3")) > 0)) {
        $completed[] = 'tts';
        $resumeFrom = 'subtitling';
    } else {
        $missingFiles[] = 'audio.mp3';
        return buildResumeResult('tts', $completed, $missingFiles, true,
            'Will resume from TTS generation');
    }
    
    // Check subtitling (subtitles.srt)
    if (file_exists("$jobOutputDir/subtitles.srt")) {
        $completed[] = 'subtitling';
        $resumeFrom = 'composing';
    } else {
        $missingFiles[] = 'subtitles.srt';
        return buildResumeResult('subtitling', $completed, $missingFiles, true,
            'Will resume from subtitle generation');
    }
    
    // Check composing (final_video.mp4)
    if (file_exists("$jobOutputDir/final_video.mp4")) {
        $completed[] = 'composing';
        return buildResumeResult('done', $completed, [], false,
            'Job already completed - video exists');
    } else {
        $missingFiles[] = 'final_video.mp4';
        return buildResumeResult('composing', $completed, $missingFiles, true,
            'Will resume from video composition (fastest!)');
    }
}

function buildResumeResult($resumeFrom, $completed, $missing, $canResume, $message) {
    $totalStages = 6;
    $completedCount = count($completed);
    
    return [
        'resume_from' => $resumeFrom,
        'completed_stages' => $completed,
        'missing_files' => $missing,
        'can_resume' => $canResume,
        'message' => $message,
        'progress' => "$completedCount/$totalStages",
        'progress_percent' => intval(($completedCount / $totalStages) * 100)
    ];
}

function mapResumeSectionForRegenerate($resumeFrom) {
    $map = [
        'scraping' => 'news',
        'scripting' => 'script',
        'imaging' => 'images',
        'tts' => 'tts',
        'subtitling' => 'subtitles',
        'composing' => 'composing',
        'video' => 'video',
        'done' => 'done'
    ];
    return $map[$resumeFrom] ?? $resumeFrom;
}


// POST: Yeni iş oluştur
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        $input = [];
    }
    $action = $input['action'] ?? '';

    if ($action === 'clear_all_videos') {
        $schedulerStatusFile = $dataDir . '/scheduler_status.json';
        $force = (bool)($input['force'] ?? false);

        if (file_exists($schedulerStatusFile) && !$force) {
            $schedulerStatus = json_decode(file_get_contents($schedulerStatusFile), true);
            $prodRunning = (bool)($schedulerStatus['production']['running'] ?? false);
            $socialRunning = (bool)($schedulerStatus['social']['running'] ?? false);
            if ($prodRunning || $socialRunning) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'error' => 'Temizlik için önce zamanlayıcıları durdurun',
                    'details' => [
                        'production_running' => $prodRunning,
                        'social_running' => $socialRunning
                    ]
                ]);
                exit;
            }
        }

        $stats = clearAllVideoProductions();
        echo json_encode([
            'success' => true,
            'message' => 'Tüm video üretimleri ve bağlı dosyalar temizlendi',
            'stats' => $stats
        ]);
        exit;
    }

    $url = trim((string)($input['url'] ?? ''));
    $template = $input['template'] ?? 'short_haber';
    $scriptId = trim((string)($input['scriptId'] ?? ''));
    $contentType = trim((string)($input['contentType'] ?? ''));
    $videoWidth = intval($input['videoWidth'] ?? 1080);
    $videoHeight = intval($input['videoHeight'] ?? 1920);
    $subtitleStyle = $input['subtitleStyle'] ?? null;
    $sourceMode = strtolower(trim((string)($input['source_mode'] ?? 'url')));
    if (!in_array($sourceMode, ['url', 'prompt'], true)) {
        $sourceMode = 'url';
    }
    $promptText = trim((string)($input['prompt_text'] ?? ''));
    $visualThemeId = trim((string)($input['visual_theme_id'] ?? 'default'));
    if ($visualThemeId === '') {
        $visualThemeId = 'default';
    }
    $visualThemePrompt = trim((string)($input['visual_theme_prompt'] ?? ''));
    $musicMode = normalizeMusicMode($input['music_mode'] ?? 'off');
    $bgmVolumeDb = (float)($input['bgm_volume_db'] ?? -22.0);

    // Ebat sınırları kontrolü
    $videoWidth = max(360, min(4096, $videoWidth));
    $videoHeight = max(360, min(4096, $videoHeight));

    if ($sourceMode === 'url') {
        if (empty($url)) {
            echo json_encode(['error' => 'URL gerekli']);
            exit;
        }
    } else {
        $promptLen = function_exists('mb_strlen') ? mb_strlen($promptText, 'UTF-8') : strlen($promptText);
        if ($promptText === '' || $promptLen < 20) {
            http_response_code(400);
            echo json_encode(['error' => 'Prompt en az 20 karakter olmalı']);
            exit;
        }
        if ($url === '') {
            $url = 'prompt://' . uniqid('job_', true);
        }
    }

    if ($scriptId === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Script seçimi zorunlu']);
        exit;
    }

    $selectedScript = findScriptById($scriptId);
    if (!$selectedScript) {
        http_response_code(400);
        echo json_encode(['error' => 'Seçilen script bulunamadı']);
        exit;
    }

    if ($contentType === '') {
        $contentType = trim((string)($selectedScript['contentType'] ?? 'genel'));
    }
    $contentType = strtolower($contentType);
    $scriptCategoryId = resolveScriptCategory($selectedScript, $contentType);

    $selectedMusic = null;
    if ($musicMode === 'auto') {
        $selectedMusic = selectMusicTrackForCategory($baseDir, $scriptCategoryId);
    }

    $jobId = uniqid('job_', true);
    
    // Başlık fallback'i oluştur
    $titleGuess = 'Yeni Video';
    if ($sourceMode === 'prompt' && $promptText !== '') {
        $normalizedPrompt = trim(preg_replace('/\s+/', ' ', $promptText));
        $titleGuess = function_exists('mb_substr') ? mb_substr($normalizedPrompt, 0, 90, 'UTF-8') : substr($normalizedPrompt, 0, 90);
    } else {
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'] ?? '';
        $titleGuess = basename($path);
        $titleGuess = preg_replace('/[^a-zA-Z0-9\s-]/', ' ', urldecode($titleGuess));
        $titleGuess = ucfirst(trim($titleGuess)) ?: 'Yeni Video';
    }
    
    $jobData = [
        'id' => $jobId,
        'url' => $url,
        'source_mode' => $sourceMode,
        'prompt_text' => $sourceMode === 'prompt' ? $promptText : null,
        'template' => $template,
        'scriptId' => $scriptId,
        'scriptName' => $selectedScript['name'] ?? '',
        'contentType' => $contentType,
        'videoWidth' => $videoWidth,
        'videoHeight' => $videoHeight,
        'subtitleStyle' => $subtitleStyle,
        'visual_theme_id' => $visualThemeId,
        'visual_theme_prompt' => $visualThemePrompt !== '' ? $visualThemePrompt : null,
        'music_mode' => $musicMode,
        'bgm_category_id' => $scriptCategoryId,
        'bgm_track_id' => $selectedMusic['id'] ?? null,
        'bgm_track_name' => $selectedMusic['name'] ?? null,
        'bgm_file' => $selectedMusic['file'] ?? null,
        'bgm_volume_db' => $selectedMusic ? (float)($selectedMusic['volumeDb'] ?? $bgmVolumeDb) : $bgmVolumeDb,
        'status' => 'pending',
        'created_at' => date('Y-m-d H:i:s'),
        'previewUrl' => '',
        'subtitles' => '',
        'error' => '',
        'title' => $titleGuess
    ];

    file_put_contents("$jobsDir/$jobId.json", json_encode($jobData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    $jobOutputDir = "$outputDir/$jobId";
    if (!is_dir($jobOutputDir)) { mkdir($jobOutputDir, 0777, true); }

    // Add to production queue (sequential processing only)
    $queueResponse = enqueueProductionJob($jobId, 0, [
        'job_id' => $jobId,
        'url' => $url,
        'template' => $template,
        'created_via' => 'web_ui'
    ]);
    if ($queueResponse['success'] ?? false) {
        echo json_encode([
            'jobId' => $jobId,
            'status' => 'queued',
            'message' => 'Video üretimi kuyruğa eklendi',
            'queue_position' => $queueResponse['position'] ?? null,
            'queue_length' => $queueResponse['queue_length'] ?? null
        ]);
        exit;
    }

    echo json_encode([
        'success' => false,
        'error' => 'Production queue API hatası: iş kuyruğa eklenemedi. Üretim fallback kapalı.',
        'jobId' => $jobId
    ]);
    exit;
}

// PATCH: Pause/Resume/Retry or Update YouTube Metadata
if ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    $input = json_decode(file_get_contents('php://input'), true);
    $jobId = $input['jobId'] ?? '';
    $action = $input['action'] ?? '';

    if (empty($jobId) || !in_array($action, ['pause', 'resume', 'retry', 'update_youtube_metadata'])) {
        echo json_encode(['error' => 'Geçersiz jobId veya action']);
        exit;
    }

    $jobFile = "$jobsDir/$jobId.json";
    if (!file_exists($jobFile)) {
        echo json_encode(['error' => 'İş bulunamadı']);
        exit;
    }

    $jobData = json_decode(file_get_contents($jobFile), true) ?: [];

    if ($action === 'pause') {
        $jobData['pausedAt'] = $jobData['status'];
        $jobData['status'] = 'paused';
    } elseif ($action === 'resume') {
        // Smart resume: Detect where to continue from
        $resumeInfo = detectResumePoint($jobId);
        
        if (!$resumeInfo['can_resume']) {
            echo json_encode([
                'success' => false,
                'error' => $resumeInfo['message'],
                'resume_info' => $resumeInfo,
                'details' => [
                    'completed_stages' => $resumeInfo['completed_stages'],
                    'missing_files' => $resumeInfo['missing_files'],
                    'job_id' => $jobId,
                    'output_dir' => "$outputDir/$jobId"
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $resumeFrom = $resumeInfo['resume_from'];
        $section = mapResumeSectionForRegenerate($resumeFrom);

        // Resume requests should flow through production queue to preserve sequential execution
        $jobData['status'] = 'waiting';
        $jobData['resume_from'] = $resumeInfo['resume_from'];
        $jobData['resume_section'] = $section;
        $jobData['resume_info'] = $resumeInfo;
        $jobData['resume_requested'] = true;
        $jobData['resumed_at'] = date('c');
        $jobData['error'] = '';
        unset($jobData['pausedAt']);
        file_put_contents($jobFile, json_encode($jobData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Queue resume through production queue file (single sequential entry point)
        $queueResponse = enqueueProductionJob($jobId, 1, [
            'resume' => true,
            'resume_from' => $resumeInfo['resume_from'],
            'resume_section' => $section,
            'resumed_at' => date('c')
        ]);
        if ($queueResponse['success'] ?? false) {
            echo json_encode([
                'success' => true,
                'message' => "Job resumed and queued from {$resumeInfo['resume_from']}",
                'resume_info' => $resumeInfo,
                'queue_position' => $queueResponse['position'] ?? null,
                'queue_length' => $queueResponse['queue_length'] ?? null
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }

        $jobData['status'] = 'failed';
        $jobData['error'] = 'Resume için production kuyruğuna eklenemedi';
        file_put_contents($jobFile, json_encode($jobData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Resume queue add failed',
            'resume_info' => $resumeInfo
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    } elseif ($action === 'retry') {
        // Retry: reset state and re-add to production queue via API
        $jobData['status'] = 'waiting';
        $jobData['error'] = '';

        $queueResponse = enqueueProductionJob($jobId, 0, [
            'retry' => true,
            'retried_at' => date('c')
        ]);
        if (!($queueResponse['success'] ?? false)) {
            $jobData['status'] = 'failed';
            $jobData['error'] = 'Retry için production kuyruğuna eklenemedi';
        }
    } elseif ($action === 'update_youtube_metadata') {
        $metadata = $input['metadata'] ?? [];
        $jobData['youtube_metadata'] = $metadata;
    }

    file_put_contents($jobFile, json_encode($jobData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo json_encode(['success' => true, 'status' => $jobData['status']]);
    exit;
}

// GET: İş durumu veya liste
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['list'])) {
        $jobs = [];
        foreach (glob("$jobsDir/*.json") as $file) {
            $job = json_decode(file_get_contents($file), true);
            if ($job && isset($job['id'])) {
                // news.json'dan gerçek başlığı almaya çalış
                $newsFile = "$outputDir/{$job['id']}/news.json";
                if (file_exists($newsFile)) {
                    $news = json_decode(file_get_contents($newsFile), true);
                    if (isset($news['title'])) {
                        $job['title'] = $news['title'];
                    }
                }
                $jobs[] = $job;
            }
        }
        usort($jobs, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        echo json_encode(['jobs' => $jobs]);
        exit;
    }

    $jobId = $_GET['jobId'] ?? '';
    if (empty($jobId)) {
        echo json_encode(['error' => 'jobId gerekli']);
        exit;
    }

    $jobFile = "$jobsDir/$jobId.json";
    if (!file_exists($jobFile)) {
        echo json_encode(['error' => 'İş bulunamadı']);
        exit;
    }

    $job = json_decode(file_get_contents($jobFile), true);
    
    // news.json'dan gerçek başlığı al
    $newsFile = "$outputDir/$jobId/news.json";
    if (file_exists($newsFile)) {
        $news = json_decode(file_get_contents($newsFile), true);
        if (isset($news['title'])) {
            $job['title'] = $news['title'];
        }
    }

    echo json_encode($job);
    exit;
}

// DELETE: İşi sil
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $jobId = $input['jobId'] ?? '';

    if (empty($jobId)) {
        echo json_encode(['error' => 'jobId gerekli']);
        exit;
    }

    $jobFile = "$jobsDir/$jobId.json";
    if (!file_exists($jobFile)) {
        echo json_encode(['error' => 'İş bulunamadı']);
        exit;
    }

    // Output klasörünü sil (tüm içeriğiyle)
    $jobOutputDir = "$outputDir/$jobId";
    if (is_dir($jobOutputDir)) {
        deleteDirectoryRecursive($jobOutputDir);
    }

    $sync = [
        'queues_removed' => removeJobFromQueuesJson($jobId),
        'social_removed' => removeJobFromSocialQueue($jobId),
        'production_removed' => removeJobFromProductionQueue($jobId),
        'content_pool_updated' => clearContentPoolJobReferences($jobId)
    ];

    // Job meta dosyasını sil
    unlink($jobFile);

    echo json_encode(['success' => true, 'message' => 'İş silindi', 'sync' => $sync]);
    exit;
}
