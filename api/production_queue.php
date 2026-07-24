<?php
/**
 * Production Queue API
 * Manages sequential video production queue
 */
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$dataDir = __DIR__ . '/../data';
$queueFile = $dataDir . '/production_queue.json';
$jobsDir = $dataDir . '/jobs';

// Helper: Load queue
function loadQueue() {
    global $queueFile;
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
    return $data ?: [];
}

// Helper: Save queue
function saveQueue($data) {
    global $queueFile;
    $data['metadata']['last_updated'] = date('c');
    file_put_contents($queueFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Helper: Load job data
function loadJobData($jobId) {
    global $jobsDir;
    $jobFile = "$jobsDir/$jobId.json";
    if (!file_exists($jobFile)) {
        return null;
    }
    return json_decode(file_get_contents($jobFile), true);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'status';

// GET: Get queue status
if ($method === 'GET') {
    if ($action === 'status') {
        $queue = loadQueue();
        
        // Enrich queue items with job data
        $enrichedQueue = [];
        foreach ($queue['queue'] as $item) {
            $jobData = loadJobData($item['job_id']);
            $enrichedItem = $item;
            if ($jobData) {
                $enrichedItem['job_data'] = [
                    'url' => $jobData['url'] ?? '',
                    'title' => $jobData['title'] ?? '',
                    'status' => $jobData['status'] ?? 'unknown'
                ];
            }
            $enrichedQueue[] = $enrichedItem;
        }
        
        echo json_encode([
            'success' => true,
            'current_job' => $queue['current_job'],
            'queue_length' => count($queue['queue']),
            'queue' => $enrichedQueue,
            'stats' => $queue['stats'],
            'settings' => $queue['settings']
        ]);
        exit;
    }
    
    if ($action === 'position') {
        $jobId = $_GET['job_id'] ?? '';
        if (!$jobId) {
            echo json_encode(['success' => false, 'error' => 'Missing job_id']);
            exit;
        }
        
        $queue = loadQueue();
        
        // Check if currently processing
        if ($queue['current_job'] === $jobId) {
            echo json_encode([
                'success' => true,
                'status' => 'processing',
                'position' => 0,
                'queue_length' => count($queue['queue'])
            ]);
            exit;
        }
        
        // Find in queue
        foreach ($queue['queue'] as $item) {
            if ($item['job_id'] === $jobId) {
                echo json_encode([
                    'success' => true,
                    'status' => $item['status'],
                    'position' => $item['position'],
                    'queue_length' => count($queue['queue'])
                ]);
                exit;
            }
        }
        
        echo json_encode([
            'success' => false,
            'error' => 'Job not found in queue',
            'status' => 'not_queued'
        ]);
        exit;
    }
}

// POST: Add to queue or update
if ($method === 'POST') {
    if ($action === 'add') {
        $input = json_decode(file_get_contents('php://input'), true);
        $jobId = $input['job_id'] ?? '';
        $priority = intval($input['priority'] ?? 0);
        $metadata = $input['metadata'] ?? [];
        
        if (!$jobId) {
            echo json_encode(['success' => false, 'error' => 'Missing job_id']);
            exit;
        }
        
        $queue = loadQueue();
        
        // Check if already in queue
        foreach ($queue['queue'] as $item) {
            if ($item['job_id'] === $jobId) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Job already in queue',
                    'position' => $item['position']
                ]);
                exit;
            }
        }
        
        // Check if currently processing
        if ($queue['current_job'] === $jobId) {
            echo json_encode([
                'success' => false,
                'error' => 'Job is currently being processed'
            ]);
            exit;
        }
        
        // Add to queue
        $newItem = [
            'job_id' => $jobId,
            'status' => 'waiting',
            'priority' => $priority,
            'added_at' => date('c'),
            'started_at' => null,
            'completed_at' => null,
            'retry_count' => 0,
            'last_error' => null,
            'metadata' => $metadata
        ];
        
        $queue['queue'][] = $newItem;
        
        // Update stats
        $queue['stats']['total_queued']++;
        
        // Sort by priority (desc) then added_at (asc)
        usort($queue['queue'], function($a, $b) {
            if ($a['priority'] !== $b['priority']) {
                return $b['priority'] - $a['priority'];
            }
            return strcmp($a['added_at'], $b['added_at']);
        });
        
        // Update positions
        foreach ($queue['queue'] as $i => &$item) {
            $item['position'] = $i + 1;
        }
        
        saveQueue($queue);
        
        $position = null;
        foreach ($queue['queue'] as $item) {
            if ($item['job_id'] === $jobId) {
                $position = $item['position'];
                break;
            }
        }
        
        echo json_encode([
            'success' => true,
            'job_id' => $jobId,
            'position' => $position,
            'queue_length' => count($queue['queue']),
            'message' => "Added to production queue at position $position"
        ]);
        exit;
    }
    
    if ($action === 'reorder') {
        $input = json_decode(file_get_contents('php://input'), true);
        $jobId = $input['job_id'] ?? '';
        $newPosition = intval($input['position'] ?? 0);
        
        if (!$jobId || $newPosition < 1) {
            echo json_encode(['success' => false, 'error' => 'Invalid job_id or position']);
            exit;
        }
        
        $queue = loadQueue();
        
        // Find job
        $jobIndex = null;
        foreach ($queue['queue'] as $i => $item) {
            if ($item['job_id'] === $jobId && $item['status'] === 'waiting') {
                $jobIndex = $i;
                break;
            }
        }
        
        if ($jobIndex === null) {
            echo json_encode(['success' => false, 'error' => 'Job not found or not in waiting status']);
            exit;
        }
        
        // Remove from current position
        $jobItem = array_splice($queue['queue'], $jobIndex, 1)[0];
        
        // Insert at new position
        $insertIndex = max(0, min($newPosition - 1, count($queue['queue'])));
        array_splice($queue['queue'], $insertIndex, 0, [$jobItem]);
        
        // Update positions
        foreach ($queue['queue'] as $i => &$item) {
            $item['position'] = $i + 1;
        }
        
        saveQueue($queue);
        
        echo json_encode([
            'success' => true,
            'job_id' => $jobId,
            'new_position' => $jobItem['position'],
            'message' => "Job moved to position {$jobItem['position']}"
        ]);
        exit;
    }
}

// DELETE: Remove from queue
if ($method === 'DELETE') {
    $jobId = $_GET['job_id'] ?? '';
    
    if (!$jobId) {
        echo json_encode(['success' => false, 'error' => 'Missing job_id']);
        exit;
    }
    
    $queue = loadQueue();
    
    // Check if currently processing
    if ($queue['current_job'] === $jobId) {
        echo json_encode([
            'success' => false,
            'error' => 'Cannot remove job that is currently processing'
        ]);
        exit;
    }
    
    // Remove from queue
    $originalLength = count($queue['queue']);
    $queue['queue'] = array_filter($queue['queue'], function($item) use ($jobId) {
        return $item['job_id'] !== $jobId;
    });
    $queue['queue'] = array_values($queue['queue']); // Re-index
    
    if (count($queue['queue']) === $originalLength) {
        echo json_encode([
            'success' => false,
            'error' => 'Job not found in queue'
        ]);
        exit;
    }
    
    // Update positions
    foreach ($queue['queue'] as $i => &$item) {
        $item['position'] = $i + 1;
    }
    
    saveQueue($queue);
    
    echo json_encode([
        'success' => true,
        'job_id' => $jobId,
        'message' => 'Job removed from queue'
    ]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
