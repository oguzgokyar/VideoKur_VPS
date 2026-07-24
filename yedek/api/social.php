<?php
/**
 * Social Media API Endpoint
 * Handles multi-platform social media operations
 */

header('Content-Type: application/json; charset=utf-8');

// Load config
$configPath = __DIR__ . '/../data/config.json';
$config = file_exists($configPath) ? json_decode(file_get_contents($configPath), true) : [];

// Get base paths
$baseDir = dirname(__DIR__);
$dataDir = $baseDir . '/data';
$pythonDir = $baseDir . '/python';

// Handle request
$method = $_SERVER['REQUEST_METHOD'];
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// Get action
$action = $input['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'schedule':
        scheduleUpload($input, $dataDir);
        break;
    
    case 'schedule_multi':
        scheduleMultiPlatform($input, $dataDir);
        break;
    
    case 'get_queue':
        getQueue($dataDir);
        break;
    
    case 'get_history':
        getHistory($dataDir, $input);
        break;
    
    case 'cancel':
        cancelUpload($input, $dataDir);
        break;
    
    case 'get_job_status':
        getJobStatus($input, $dataDir);
        break;
    
    case 'get_platforms':
        getPlatforms($dataDir);
        break;
    
    case 'get_accounts':
        getAccounts($dataDir);
        break;
    
    case 'optimize_metadata':
        optimizeMetadata($input, $pythonDir, $config);
        break;
    
    case 'get_stats':
        getStats($dataDir);
        break;
    
    case 'requeue':
        requeueVideo($input, $dataDir, $baseDir);
        break;
    
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Unknown action: ' . $action]);
}

/**
 * Schedule upload to multiple platforms
 */
function scheduleMultiPlatform($input, $dataDir) {
    $required = ['job_id', 'video_path', 'platforms', 'metadata'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['error' => "Missing required field: $field"]);
            return;
        }
    }
    
    $queueFile = $dataDir . '/social_queue.json';
    $queue = file_exists($queueFile) ? json_decode(file_get_contents($queueFile), true) : ['queue' => []];
    
    // Generate queue ID
    $queueId = 'social_' . bin2hex(random_bytes(8));
    
    // Validate platforms
    $validPlatforms = ['youtube', 'tiktok', 'instagram', 'facebook'];
    $platforms = array_filter($input['platforms'], function($p) use ($validPlatforms) {
        return in_array(strtolower($p), $validPlatforms);
    });
    $platforms = array_map('strtolower', $platforms);
    
    if (empty($platforms)) {
        http_response_code(400);
        echo json_encode(['error' => 'No valid platforms specified']);
        return;
    }
    
    // Create platform status
    $platformStatus = [];
    foreach ($platforms as $platform) {
        $platformStatus[$platform] = [
            'status' => 'pending',
            'post_id' => null,
            'post_url' => null,
            'error' => null,
            'uploaded_at' => null
        ];
    }
    
    // Create queue item
    $item = [
        'queue_id' => $queueId,
        'job_id' => $input['job_id'],
        'video_path' => $input['video_path'],
        'platforms' => array_values($platforms),
        'platform_status' => $platformStatus,
        'scheduled_time' => $input['scheduled_time'] ?? date('c'),
        'status' => 'pending',
        'priority' => $input['priority'] ?? 0,
        'metadata' => $input['metadata'],
        'platform_metadata' => $input['platform_metadata'] ?? [],
        'created_at' => date('c'),
        'retry_count' => 0,
        'last_error' => null
    ];
    
    $queue['queue'][] = $item;
    file_put_contents($queueFile, json_encode($queue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo json_encode([
        'success' => true,
        'queue_id' => $queueId,
        'platforms' => $platforms,
        'scheduled_time' => $item['scheduled_time']
    ]);
}

/**
 * Schedule single platform upload (backward compatible)
 */
function scheduleUpload($input, $dataDir) {
    $platform = $input['platform'] ?? 'youtube';
    $input['platforms'] = [$platform];
    scheduleMultiPlatform($input, $dataDir);
}

/**
 * Get queue items
 */
function getQueue($dataDir) {
    $queueFile = $dataDir . '/social_queue.json';
    $queue = file_exists($queueFile) ? json_decode(file_get_contents($queueFile), true) : ['queue' => []];
    
    // Filter by platform if specified
    $platform = $_GET['platform'] ?? null;
    
    $items = $queue['queue'];
    if ($platform) {
        $items = array_filter($items, function($item) use ($platform) {
            return in_array($platform, $item['platforms']);
        });
    }
    
    echo json_encode([
        'success' => true,
        'queue' => array_values($items),
        'count' => count($items)
    ]);
}

/**
 * Get upload history
 */
function getHistory($dataDir, $input) {
    $historyFile = $dataDir . '/social_history.json';
    $history = file_exists($historyFile) ? json_decode(file_get_contents($historyFile), true) : ['history' => []];
    
    $limit = $input['limit'] ?? $_GET['limit'] ?? 50;
    $platform = $input['platform'] ?? $_GET['platform'] ?? null;
    
    $items = $history['history'];
    
    if ($platform) {
        $items = array_filter($items, function($item) use ($platform) {
            return in_array($platform, $item['platforms'] ?? []);
        });
    }
    
    $items = array_slice(array_values($items), 0, $limit);
    
    echo json_encode([
        'success' => true,
        'history' => $items,
        'count' => count($items)
    ]);
}

/**
 * Cancel scheduled upload
 */
function cancelUpload($input, $dataDir) {
    $queueId = $input['queue_id'] ?? '';
    
    if (empty($queueId)) {
        http_response_code(400);
        echo json_encode(['error' => 'queue_id required']);
        return;
    }
    
    $queueFile = $dataDir . '/social_queue.json';
    $queue = file_exists($queueFile) ? json_decode(file_get_contents($queueFile), true) : ['queue' => []];
    
    $found = false;
    foreach ($queue['queue'] as $i => $item) {
        if ($item['queue_id'] === $queueId) {
            array_splice($queue['queue'], $i, 1);
            $found = true;
            break;
        }
    }
    
    if ($found) {
        file_put_contents($queueFile, json_encode($queue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo json_encode(['success' => true, 'message' => 'Upload cancelled']);
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'Queue item not found']);
    }
}

/**
 * Get job upload status
 */
function getJobStatus($input, $dataDir) {
    $jobId = $input['job_id'] ?? $_GET['job_id'] ?? '';
    
    if (empty($jobId)) {
        http_response_code(400);
        echo json_encode(['error' => 'job_id required']);
        return;
    }
    
    // Check queue
    $queueFile = $dataDir . '/social_queue.json';
    $queue = file_exists($queueFile) ? json_decode(file_get_contents($queueFile), true) : ['queue' => []];
    
    foreach ($queue['queue'] as $item) {
        if ($item['job_id'] === $jobId) {
            echo json_encode([
                'success' => true,
                'found' => true,
                'source' => 'queue',
                'status' => $item
            ]);
            return;
        }
    }
    
    // Check history
    $historyFile = $dataDir . '/social_history.json';
    $history = file_exists($historyFile) ? json_decode(file_get_contents($historyFile), true) : ['history' => []];
    
    foreach ($history['history'] as $item) {
        if ($item['job_id'] === $jobId) {
            echo json_encode([
                'success' => true,
                'found' => true,
                'source' => 'history',
                'status' => $item
            ]);
            return;
        }
    }
    
    echo json_encode([
        'success' => true,
        'found' => false
    ]);
}

/**
 * Get available platforms and their status
 */
function getPlatforms($dataDir) {
    $credsDir = $dataDir . '/social_credentials';
    
    $platforms = [
        'youtube' => [
            'name' => 'YouTube',
            'icon' => '📺',
            'configured' => file_exists($dataDir . '/youtube_credentials/default_token.pickle'),
            'description' => 'YouTube Shorts'
        ],
        'tiktok' => [
            'name' => 'TikTok',
            'icon' => '🎵',
            'configured' => file_exists($credsDir . '/tiktok/tiktok_token.json'),
            'description' => 'TikTok Video'
        ],
        'instagram' => [
            'name' => 'Instagram',
            'icon' => '📸',
            'configured' => file_exists($credsDir . '/meta/meta_token.json'),
            'description' => 'Instagram Reels'
        ],
        'facebook' => [
            'name' => 'Facebook',
            'icon' => '📘',
            'configured' => file_exists($credsDir . '/meta/meta_token.json'),
            'description' => 'Facebook Reels'
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'platforms' => $platforms
    ]);
}

/**
 * Get connected accounts for each platform
 */
function getAccounts($dataDir) {
    $accounts = [
        'youtube' => [],
        'tiktok' => [],
        'instagram' => [],
        'facebook' => []
    ];
    
    // YouTube channels
    $channelsFile = $dataDir . '/youtube_channels.json';
    if (file_exists($channelsFile)) {
        $data = json_decode(file_get_contents($channelsFile), true);
        $accounts['youtube'] = $data['channels'] ?? [];
    }
    
    // Meta accounts (Instagram + Facebook)
    $metaAccountsFile = $dataDir . '/social_credentials/meta/meta_accounts.json';
    if (file_exists($metaAccountsFile)) {
        $data = json_decode(file_get_contents($metaAccountsFile), true);
        $accounts['instagram'] = $data['instagram'] ?? [];
        $accounts['facebook'] = $data['facebook'] ?? [];
    }
    
    // TikTok - TODO: Add when implemented
    
    echo json_encode([
        'success' => true,
        'accounts' => $accounts
    ]);
}

/**
 * Optimize metadata for specific platform using AI
 */
function optimizeMetadata($input, $pythonDir, $config) {
    $platform = $input['platform'] ?? 'all';
    $title = $input['title'] ?? '';
    $script = $input['script'] ?? '';
    $tags = $input['tags'] ?? [];
    
    if (empty($title) && empty($script)) {
        http_response_code(400);
        echo json_encode(['error' => 'title or script required']);
        return;
    }
    
    // Use Python optimizer
    $pythonPath = 'python';
    $scriptPath = $pythonDir . '/social/platform_optimizer.py';
    
    // For now, return rule-based optimization
    // Full implementation would call Python script
    
    $optimized = [];
    $platforms = $platform === 'all' ? ['tiktok', 'instagram', 'facebook'] : [$platform];
    
    foreach ($platforms as $p) {
        $optimized[$p] = generatePlatformMetadata($p, $title, $script, $tags);
    }
    
    echo json_encode([
        'success' => true,
        'optimized' => $optimized
    ]);
}

/**
 * Simple rule-based metadata generation
 */
function generatePlatformMetadata($platform, $title, $script, $tags) {
    $title = trim(str_replace(['-', '_'], ' ', $title));
    $script = trim($script);
    
    switch ($platform) {
        case 'tiktok':
            return [
                'caption' => "🔥 " . $title . "\n\n" . substr($script, 0, 100) . "...\n\n💬 Yorumlarını bekliyorum!",
                'hashtags' => array_merge(['fyp', 'viral', 'keşfet'], array_slice($tags, 0, 5)),
                'hook' => "Bunu bilmen lazım! 👀"
            ];
        
        case 'instagram':
            return [
                'caption' => "✨ " . $title . "\n\n" . substr($script, 0, 150) . "...\n\n📌 Kaydet!\n💬 Fikrini yaz!\n👥 Arkadaşını etiketle!",
                'hashtags' => array_merge(['reels', 'instagram', 'keşfet'], array_slice($tags, 0, 20)),
                'hook' => "Kaydır ve öğren ✨"
            ];
        
        case 'facebook':
            return [
                'caption' => "🤔 " . $title . "\n\n" . substr($script, 0, 200) . "...\n\n💭 Sen ne düşünüyorsun? Yorumlarda paylaş!",
                'hashtags' => array_merge(['reels', 'viral', 'gündem'], array_slice($tags, 0, 8)),
                'hook' => "Bunu duydun mu? 🤔"
            ];
        
        default:
            return [
                'caption' => $title . "\n\n" . $script,
                'hashtags' => $tags,
                'hook' => $title
            ];
    }
}

/**
 * Get upload statistics
 */
function getStats($dataDir) {
    $historyFile = $dataDir . '/social_history.json';
    $history = file_exists($historyFile) ? json_decode(file_get_contents($historyFile), true) : ['history' => []];
    
    $stats = [
        'youtube' => ['success' => 0, 'failed' => 0],
        'tiktok' => ['success' => 0, 'failed' => 0],
        'instagram' => ['success' => 0, 'failed' => 0],
        'facebook' => ['success' => 0, 'failed' => 0]
    ];
    
    foreach ($history['history'] as $item) {
        foreach ($item['platform_status'] ?? [] as $platform => $status) {
            if (isset($stats[$platform])) {
                if ($status['status'] === 'success') {
                    $stats[$platform]['success']++;
                } elseif ($status['status'] === 'failed') {
                    $stats[$platform]['failed']++;
                }
            }
        }
    }
    
    // Calculate totals
    $total = [
        'success' => array_sum(array_column($stats, 'success')),
        'failed' => array_sum(array_column($stats, 'failed'))
    ];
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'total' => $total,
        'history_count' => count($history['history'])
    ]);
}

/**
 * Re-queue a video for publishing
 * Allows re-adding completed/failed videos back to the social queue
 */
function requeueVideo($input, $dataDir, $baseDir) {
    $jobId = $input['job_id'] ?? '';
    $queueId = $input['queue_id'] ?? '';  // Target queue ID (from queues.json)
    $platforms = $input['platforms'] ?? ['youtube'];
    
    if (empty($jobId)) {
        http_response_code(400);
        echo json_encode(['error' => 'job_id gerekli']);
        return;
    }
    
    // Load job data
    $jobFile = $dataDir . '/jobs/' . $jobId . '.json';
    if (!file_exists($jobFile)) {
        http_response_code(404);
        echo json_encode(['error' => 'Job bulunamadı: ' . $jobId]);
        return;
    }
    
    $job = json_decode(file_get_contents($jobFile), true);
    
    // Check if video exists
    $videoPath = $baseDir . '/output/' . $jobId . '/final_video.mp4';
    if (!file_exists($videoPath)) {
        http_response_code(404);
        echo json_encode(['error' => 'Video dosyası bulunamadı']);
        return;
    }
    
    // Load queue settings for scheduling
    $queuesFile = $dataDir . '/queues.json';
    $queuesData = file_exists($queuesFile) ? json_decode(file_get_contents($queuesFile), true) : ['queues' => []];
    
    $targetQueue = null;
    foreach ($queuesData['queues'] as $q) {
        if ($q['id'] === $queueId) {
            $targetQueue = $q;
            break;
        }
    }
    
    // Load social queue
    $queueFile = $dataDir . '/social_queue.json';
    $queue = file_exists($queueFile) ? json_decode(file_get_contents($queueFile), true) : ['queue' => []];
    
    // Generate new queue item ID
    $newQueueId = 'social_' . bin2hex(random_bytes(8));
    
    // Validate platforms
    $validPlatforms = ['youtube', 'tiktok', 'instagram', 'facebook'];
    $platforms = array_filter($platforms, function($p) use ($validPlatforms) {
        return in_array(strtolower($p), $validPlatforms);
    });
    $platforms = array_map('strtolower', $platforms);
    
    if (empty($platforms)) {
        $platforms = ['youtube'];
    }
    
    // Build platform status
    $platformStatus = [];
    foreach ($platforms as $p) {
        $platformStatus[$p] = [
            'status' => 'pending',
            'post_id' => null,
            'post_url' => null,
            'error' => null,
            'uploaded_at' => null
        ];
    }
    
    // Get metadata from job
    $metadata = [
        'title' => $job['title'] ?? 'Video',
        'description' => $job['description'] ?? '',
        'tags' => $job['tags'] ?? []
    ];
    
    // Calculate scheduled time based on queue settings
    $scheduledTime = date('c');  // Default: now
    if ($targetQueue) {
        $schedule = $targetQueue['schedule'] ?? [];
        $platformSettings = $targetQueue['platform_settings']['youtube'] ?? [];
        
        // Use start_time if set
        $startTime = $platformSettings['startTime'] ?? $schedule['start_time'] ?? null;
        $intervalMinutes = intval($platformSettings['intervalMinutes'] ?? $schedule['interval_minutes'] ?? 60);
        
        if ($startTime) {
            // Parse start time and set for today or tomorrow
            $today = new DateTime('now', new DateTimeZone('Europe/Istanbul'));
            list($hour, $minute) = explode(':', $startTime);
            $scheduled = clone $today;
            $scheduled->setTime((int)$hour, (int)$minute, 0);
            
            // If time has passed, use next interval or tomorrow
            if ($scheduled <= $today) {
                $scheduled->modify("+{$intervalMinutes} minutes");
            }
            
            $scheduledTime = $scheduled->format('c');
        }
    }
    
    // Create queue item
    $queueItem = [
        'queue_id' => $newQueueId,
        'job_id' => $jobId,
        'video_path' => $videoPath,
        'platforms' => $platforms,
        'platform_status' => $platformStatus,
        'scheduled_time' => $input['scheduled_time'] ?? $scheduledTime,
        'status' => 'pending',
        'priority' => intval($input['priority'] ?? 0),
        'metadata' => $metadata,
        'platform_metadata' => [],
        'created_at' => date('c'),
        'retry_count' => 0,
        'last_error' => null,
        'requeued' => true,
        'original_queue_id' => $queueId
    ];
    
    // Add to social_queue
    $queue['queue'][] = $queueItem;
    
    // Save social_queue
    file_put_contents($queueFile, json_encode($queue, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Also add to queues.json if target queue specified
    if ($targetQueue && $queueId) {
        // Check if video already in queue
        $alreadyInQueue = false;
        foreach ($targetQueue['videos'] ?? [] as $v) {
            if ($v['job_id'] === $jobId) {
                $alreadyInQueue = true;
                break;
            }
        }
        
        if (!$alreadyInQueue) {
            // Build platform status for queue entry
            $queuePlatformStatus = [];
            foreach ($platforms as $p) {
                $queuePlatformStatus[$p] = 'pending';
            }
            
            // Calculate position (after published videos)
            $pendingVideos = array_filter($targetQueue['videos'] ?? [], function($v) {
                $status = $v['status'] ?? 'queued';
                return in_array($status, ['queued', 'pending']);
            });
            $position = count($pendingVideos) + 1;
            
            // Create video entry for queues.json
            $videoEntry = [
                'job_id' => $jobId,
                'added_at' => date('c'),
                'status' => 'queued',
                'platform_status' => $queuePlatformStatus,
                'position' => $position,
                'scheduled_time' => $queueItem['scheduled_time'],
                'retry_count' => 0,
                'last_error' => null,
                'requeued' => true
            ];
            
            // Update queues.json
            foreach ($queuesData['queues'] as &$q) {
                if ($q['id'] === $queueId) {
                    if (!isset($q['videos'])) {
                        $q['videos'] = [];
                    }
                    $q['videos'][] = $videoEntry;
                    break;
                }
            }
            unset($q);
            
            file_put_contents($queuesFile, json_encode($queuesData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }
    
    // Update job status
    $job['social_upload'] = [
        'queue_id' => $newQueueId,
        'status' => 'pending',
        'platforms' => $platformStatus,
        'requeued_at' => date('c')
    ];
    
    // Also update queue_status in job
    if ($targetQueue && $queueId) {
        $job['queue_status'] = [
            'queue_id' => $queueId,
            'queue_name' => $targetQueue['name'] ?? 'Unknown',
            'status' => 'queued',
            'added_at' => date('c')
        ];
    }
    
    file_put_contents($jobFile, json_encode($job, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo json_encode([
        'success' => true,
        'queue_id' => $newQueueId,
        'scheduled_time' => $queueItem['scheduled_time'],
        'platforms' => $platforms,
        'message' => 'Video kuyruğa eklendi'
    ]);
}
