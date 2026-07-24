<?php
/**
 * Production Queue API
 * Manages video production queue
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$DATA_DIR = __DIR__ . '/../data';
$PROD_QUEUE_FILE = $DATA_DIR . '/production_queue.json';
$JOBS_DIR = $DATA_DIR . '/jobs';
$QUEUES_FILE = $DATA_DIR . '/queues.json';

// Helper: Load production queue
function loadProductionQueue() {
    global $PROD_QUEUE_FILE;
    if (!file_exists($PROD_QUEUE_FILE)) {
        return [
            'production_queue' => [],
            'current_production' => null,
            'max_concurrent' => 1,
            'metadata' => [
                'last_updated' => date('c'),
                'total_produced' => 0,
                'total_failed' => 0
            ]
        ];
    }
    return json_decode(file_get_contents($PROD_QUEUE_FILE), true) ?: [];
}

// Helper: Save production queue
function saveProductionQueue($data) {
    global $PROD_QUEUE_FILE;
    $data['metadata']['last_updated'] = date('c');
    file_put_contents($PROD_QUEUE_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Helper: Load queues
function loadQueues() {
    global $QUEUES_FILE;
    if (!file_exists($QUEUES_FILE)) {
        return ['queues' => []];
    }
    return json_decode(file_get_contents($QUEUES_FILE), true) ?: ['queues' => []];
}

// Helper: Check if queue is active
function isQueueActive($queue_id) {
    $queues_data = loadQueues();
    foreach ($queues_data['queues'] as $queue) {
        if ($queue['id'] === $queue_id) {
            return $queue['is_active'] ?? true;
        }
    }
    return false;
}

// GET: Get production queue status
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'list';
    
    if ($action === 'list') {
        $data = loadProductionQueue();
        
        // Enhance with job info
        foreach ($data['production_queue'] as &$item) {
            $job_file = $JOBS_DIR . '/' . $item['job_id'] . '.json';
            if (file_exists($job_file)) {
                $job_data = json_decode(file_get_contents($job_file), true);
                $item['job_title'] = $job_data['title'] ?? 'Untitled';
                $item['job_status'] = $job_data['status'] ?? 'unknown';
            } else {
                $item['job_title'] = 'Unknown';
                $item['job_status'] = 'unknown';
            }
            
            $item['queue_active'] = isQueueActive($item['queue_id']);
        }
        
        echo json_encode([
            'success' => true,
            'production_queue' => $data['production_queue'],
            'current_production' => $data['current_production'],
            'metadata' => $data['metadata']
        ]);
        exit;
    }
    
    if ($action === 'stats') {
        $data = loadProductionQueue();
        
        $waiting_count = 0;
        $producing_count = 0;
        $done_count = 0;
        $failed_count = 0;
        
        foreach ($data['production_queue'] as $item) {
            switch ($item['status']) {
                case 'waiting':
                    $waiting_count++;
                    break;
                case 'producing':
                    $producing_count++;
                    break;
                case 'done':
                    $done_count++;
                    break;
                case 'failed':
                    $failed_count++;
                    break;
            }
        }
        
        echo json_encode([
            'success' => true,
            'stats' => [
                'waiting' => $waiting_count,
                'producing' => $producing_count,
                'done' => $done_count,
                'failed' => $failed_count,
                'total' => count($data['production_queue']),
                'current_production' => $data['current_production']
            ]
        ]);
        exit;
    }
    
    if ($action === 'by_queue') {
        $queue_id = $_GET['queue_id'] ?? '';
        
        if (empty($queue_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'queue_id gerekli']);
            exit;
        }
        
        $data = loadProductionQueue();
        $items = array_filter($data['production_queue'], function($item) use ($queue_id) {
            return $item['queue_id'] === $queue_id;
        });
        
        echo json_encode([
            'success' => true,
            'items' => array_values($items)
        ]);
        exit;
    }
}

// POST: Add to production queue
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? 'add';
    
    if ($action === 'add') {
        $job_id = $input['job_id'] ?? '';
        $queue_id = $input['queue_id'] ?? '';
        $priority = intval($input['priority'] ?? 0);
        
        if (empty($job_id) || empty($queue_id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'job_id ve queue_id gerekli']);
            exit;
        }
        
        $data = loadProductionQueue();
        
        // Check if already in queue
        foreach ($data['production_queue'] as $item) {
            if ($item['job_id'] === $job_id) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Zaten kuyrukta',
                    'prod_queue_id' => $item['prod_queue_id']
                ]);
                exit;
            }
        }
        
        // Add to queue
        $prod_queue_id = 'prod_' . bin2hex(random_bytes(8));
        
        $item = [
            'prod_queue_id' => $prod_queue_id,
            'job_id' => $job_id,
            'queue_id' => $queue_id,
            'status' => 'waiting',
            'priority' => $priority,
            'added_at' => date('c'),
            'started_at' => null,
            'completed_at' => null,
            'error' => null
        ];
        
        $data['production_queue'][] = $item;
        saveProductionQueue($data);
        
        echo json_encode([
            'success' => true,
            'prod_queue_id' => $prod_queue_id,
            'message' => 'Üretim kuyruğuna eklendi'
        ]);
        exit;
    }
}

// DELETE: Remove from production queue
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true);
    $prod_queue_id = $input['prod_queue_id'] ?? '';
    
    if (empty($prod_queue_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'prod_queue_id gerekli']);
        exit;
    }
    
    $data = loadProductionQueue();
    
    $original_count = count($data['production_queue']);
    $data['production_queue'] = array_filter($data['production_queue'], function($item) use ($prod_queue_id) {
        return $item['prod_queue_id'] !== $prod_queue_id;
    });
    $data['production_queue'] = array_values($data['production_queue']);
    
    if (count($data['production_queue']) < $original_count) {
        saveProductionQueue($data);
        echo json_encode(['success' => true, 'message' => 'Kuyruktan çıkarıldı']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Öğe bulunamadı']);
    }
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'Geçersiz istek']);
