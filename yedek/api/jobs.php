<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$dataDir = __DIR__ . '/../data';
$jobsDir = $dataDir . '/jobs';
$outputDir = __DIR__ . '/../output';
$pythonCmd = 'python';

if (!is_dir($jobsDir)) { mkdir($jobsDir, 0777, true); }
if (!is_dir($outputDir)) { mkdir($outputDir, 0777, true); }

// POST: Yeni iş oluştur
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $url = $input['url'] ?? '';
    $template = $input['template'] ?? 'short_haber';
    $scriptId = $input['scriptId'] ?? '';
    $contentType = trim($input['contentType'] ?? 'haber');
    $videoWidth = intval($input['videoWidth'] ?? 1080);
    $videoHeight = intval($input['videoHeight'] ?? 1920);
    $subtitleStyle = $input['subtitleStyle'] ?? null;

    // Ebat sınırları kontrolü
    $videoWidth = max(360, min(4096, $videoWidth));
    $videoHeight = max(360, min(4096, $videoHeight));

    if (empty($url)) {
        echo json_encode(['error' => 'URL gerekli']);
        exit;
    }

    $jobId = uniqid('job_', true);
    
    // URL'den basit bir başlık oluştur
    $parsedUrl = parse_url($url);
    $path = $parsedUrl['path'] ?? '';
    $titleGuess = basename($path);
    $titleGuess = preg_replace('/[^a-zA-Z0-9\s-]/', ' ', urldecode($titleGuess));
    $titleGuess = ucfirst(trim($titleGuess)) ?: 'Yeni Video';
    
    $jobData = [
        'id' => $jobId,
        'url' => $url,
        'template' => $template,
        'scriptId' => $scriptId,
        'contentType' => $contentType,
        'videoWidth' => $videoWidth,
        'videoHeight' => $videoHeight,
        'subtitleStyle' => $subtitleStyle,
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

    // DEĞIŞTI: Pipeline başlatmak yerine production queue'ya ekle
    // Eğer queue_id varsa production queue'ya ekle, yoksa direkt başlat (eski davranış)
    $queue_id = $input['queue_id'] ?? null;
    
    if ($queue_id) {
        // Production queue'ya ekle - scheduler başlatacak
        $prodQueueData = file_exists("$dataDir/production_queue.json") 
            ? json_decode(file_get_contents("$dataDir/production_queue.json"), true) 
            : ['production_queue' => [], 'current_production' => null, 'max_concurrent' => 1, 'metadata' => []];
        
        $prodItem = [
            'prod_queue_id' => 'prod_' . bin2hex(random_bytes(8)),
            'job_id' => $jobId,
            'queue_id' => $queue_id,
            'status' => 'waiting',
            'priority' => intval($input['priority'] ?? 0),
            'added_at' => date('c'),
            'started_at' => null,
            'completed_at' => null,
            'error' => null
        ];
        
        $prodQueueData['production_queue'][] = $prodItem;
        $prodQueueData['metadata']['last_updated'] = date('c');
        file_put_contents("$dataDir/production_queue.json", json_encode($prodQueueData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        echo json_encode(['jobId' => $jobId, 'status' => 'queued', 'message' => 'Üretim kuyruğuna eklendi']);
    } else {
        // Eski davranış: Direkt pipeline başlat (queue_id yoksa)
        $pythonScript = __DIR__ . '/../python/pipeline.py';
        $configFile = "$dataDir/config.json";

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $cmd = "start /B $pythonCmd \"$pythonScript\" \"$jobId\" \"$url\" \"$template\" \"$configFile\" > \"$jobOutputDir/log.txt\" 2>&1";
        } else {
            $cmd = "$pythonCmd \"$pythonScript\" \"$jobId\" \"$url\" \"$template\" \"$configFile\" > \"$jobOutputDir/log.txt\" 2>&1 &";
        }

        pclose(popen($cmd, 'r'));
        
        echo json_encode(['jobId' => $jobId, 'status' => 'pending']);
    }
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
        $jobData['status'] = $jobData['pausedAt'] ?? 'pending';
        unset($jobData['pausedAt']);
    } elseif ($action === 'retry') {
        // Retry: Reset to waiting and re-add to production queue
        $jobData['status'] = 'waiting';
        $jobData['error'] = '';
        
        // Get queue_id from original job data or use default
        $queue_id = $jobData['queue_id'] ?? 'default';
        
        // Add back to production queue
        $prodQueueData = file_exists("$dataDir/production_queue.json") 
            ? json_decode(file_get_contents("$dataDir/production_queue.json"), true) 
            : ['production_queue' => [], 'current_production' => null, 'max_concurrent' => 1, 'metadata' => []];
        
        $prodItem = [
            'prod_queue_id' => 'prod_' . bin2hex(random_bytes(8)),
            'job_id' => $jobId,
            'queue_id' => $queue_id,
            'status' => 'waiting',
            'priority' => 0,
            'added_at' => date('c'),
            'started_at' => null,
            'completed_at' => null,
            'error' => null
        ];
        
        $prodQueueData['production_queue'][] = $prodItem;
        $prodQueueData['metadata']['last_updated'] = date('c');
        file_put_contents("$dataDir/production_queue.json", json_encode($prodQueueData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
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
            if ($job) {
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
        usort($jobs, fn($a, $b) => strcmp($b['created_at'], $a['created_at']));
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
        function deleteDirectory($dir) {
            if (!is_dir($dir)) return false;
            $items = array_diff(scandir($dir), ['.', '..']);
            foreach ($items as $item) {
                $path = $dir . DIRECTORY_SEPARATOR . $item;
                is_dir($path) ? deleteDirectory($path) : unlink($path);
            }
            return rmdir($dir);
        }
        deleteDirectory($jobOutputDir);
    }

    // Job meta dosyasını sil
    unlink($jobFile);

    echo json_encode(['success' => true, 'message' => 'İş silindi']);
    exit;
}
