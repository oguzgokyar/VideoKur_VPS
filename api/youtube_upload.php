<?php
/**
 * YouTube Video Upload API
 * Handles video uploads to YouTube
 * 
 * Note: Channel/API management is in youtube_channels.php
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$dataDir = __DIR__ . '/../data';
$channelsFile = $dataDir . '/youtube_channels.json';
$pythonCmd = 'python';
$pythonDir = __DIR__ . '/../python';

// Read JSON input
$jsonInput = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST)) {
    $jsonInput = json_decode(file_get_contents('php://input'), true);
}

$action = $_GET['action'] ?? $jsonInput['action'] ?? '';

// POST: Upload video
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($action === 'upload' || $action === '')) {
    $input = $jsonInput ?: [];
    
    $jobId = $input['job_id'] ?? '';
    $videoPath = $input['video_path'] ?? '';
    $channelId = $input['channel_id'] ?? '';
    $apiId = $input['api_id'] ?? '';
    $metadata = $input['metadata'] ?? [];
    $scheduledTime = $input['scheduled_time'] ?? '';
    
    if (empty($jobId) || empty($videoPath)) {
        echo json_encode(['success' => false, 'error' => 'job_id ve video_path gerekli']);
        exit;
    }
    
    // Video path relative ise full path yap
    if (strpos($videoPath, 'output/') === 0 || strpos($videoPath, '/output/') === 0) {
        $fullPath = __DIR__ . '/../' . ltrim($videoPath, '/');
    } else {
        $fullPath = __DIR__ . '/../' . $videoPath;
    }
    
    if (!file_exists($fullPath)) {
        echo json_encode(['success' => false, 'error' => 'Video dosyası bulunamadı: ' . $videoPath]);
        exit;
    }
    
    // Find API credentials if channelId provided
    $projectId = '';
    if (!empty($channelId) && file_exists($channelsFile)) {
        $channelsData = json_decode(file_get_contents($channelsFile), true);
        foreach ($channelsData['channels'] ?? [] as $ch) {
            if ($ch['id'] === $channelId) {
                // Find active API
                foreach ($ch['apis'] ?? [] as $api) {
                    if ($api['is_active'] && $api['is_authenticated']) {
                        if (empty($apiId) || $api['api_id'] === $apiId) {
                            $projectId = $api['project_id'];
                            break 2;
                        }
                    }
                }
            }
        }
    }
    
    // Thumbnail path - jobId'den belirle
    $thumbnailPath = '';
    $possibleThumbnails = [
        __DIR__ . '/../output/' . $jobId . '/thumbnail.jpg',
        __DIR__ . '/../output/' . $jobId . '/thumbnail.png'
    ];
    foreach ($possibleThumbnails as $path) {
        if (file_exists($path)) {
            $thumbnailPath = $path;
            break;
        }
    }
    
    // Prepare metadata
    $title = $metadata['title'] ?? '';
    $description = $metadata['description'] ?? '';
    $category = $metadata['category_id'] ?? '28';
    $privacy = $metadata['privacy_status'] ?? 'public';
    $tags = isset($metadata['tags']) ? implode(',', $metadata['tags']) : '';

    // AI metadata generation if title is empty
    $needsOptimize = (empty($title) || $title === 'YouTube Short' || $title === 'Video');
    if ($needsOptimize) {
        $configFile = $dataDir . '/config.json';
        $config = file_exists($configFile) ? json_decode(file_get_contents($configFile), true) : [];

        $metadataCmd = sprintf(
            'cd "%s" && python metadata_cli.py --job-id "%s" --platform youtube --base-dir "%s" 2>NUL',
            $pythonDir,
            $jobId,
            escapeshellarg(dirname($dataDir))
        );
        $metaOutput = [];
        exec($metadataCmd, $metaOutput, $metaCode);

        if ($metaCode === 0 && !empty($metaOutput)) {
            $generatedMeta = json_decode(implode('', $metaOutput), true);
            if (!empty($generatedMeta['title'])) {
                $title = $generatedMeta['title'];
                $description = $generatedMeta['description'] ?? $description;
                if (!empty($generatedMeta['tags'])) {
                    $tags = implode(',', $generatedMeta['tags']);
                }
            }
        }
    }

    // Fallback defaults
    if (empty($title)) { $title = 'YouTube Short'; }
    if (empty($tags)) { $tags = '#Shorts'; }
    
    // Build upload command
    $cmd = sprintf(
        'cd "%s" && %s -m youtube.uploader "%s" "%s" "%s" "%s" "%s" "%s" "%s" "%s" "%s" 2>&1',
        $pythonDir,
        $pythonCmd,
        $fullPath,
        str_replace('"', '\"', $title),
        str_replace('"', '\"', $description),
        $privacy,
        $category,
        $tags,
        $thumbnailPath,
        $scheduledTime,
        $projectId
    );
    
    // Execute upload
    exec($cmd, $output, $returnCode);
    
    if ($returnCode === 0) {
        // Parse output
        $videoId = null;
        $videoUrl = null;
        $thumbnailUploaded = false;
        
        foreach ($output as $line) {
            if (strpos($line, 'Video ID:') !== false) {
                $videoId = trim(str_replace('Video ID:', '', $line));
            }
            if (strpos($line, 'URL:') !== false) {
                $videoUrl = trim(str_replace('URL:', '', $line));
            }
            if (strpos($line, 'Thumbnail: uploaded') !== false) {
                $thumbnailUploaded = true;
            }
        }
        
        // Update job JSON
        $jobFile = $dataDir . '/jobs/' . $jobId . '.json';
        if (file_exists($jobFile)) {
            $job = json_decode(file_get_contents($jobFile), true);
            $job['youtube_upload'] = [
                'status' => 'uploaded',
                'video_id' => $videoId,
                'video_url' => $videoUrl,
                'channel_id' => $channelId,
                'thumbnail_uploaded' => $thumbnailUploaded,
                'uploaded_at' => gmdate('Y-m-d\TH:i:s\Z'),
                'metadata' => [
                    'title' => $title,
                    'description' => $description,
                    'tags' => $metadata['tags'] ?? [],
                    'category_id' => $category,
                    'privacy_status' => $privacy
                ]
            ];
            file_put_contents($jobFile, json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        
        echo json_encode([
            'success' => true,
            'video_id' => $videoId,
            'video_url' => $videoUrl,
            'thumbnail_uploaded' => $thumbnailUploaded,
            'message' => 'Video başarıyla yüklendi'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Yükleme başarısız: ' . implode("\n", array_slice($output, -5)),
            'output' => $output
        ]);
    }
    exit;
}

echo json_encode(['error' => 'Invalid request']);
