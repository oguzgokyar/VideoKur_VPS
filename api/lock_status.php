<?php
/**
 * Video Compositor Lock Status API
 * Returns current lock status for monitoring
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

$base_dir = dirname(__DIR__);
$lock_dir = $base_dir . '/data/.locks';
$lock_file = $lock_dir . '/video_compositor.lock';
$meta_file = $lock_dir . '/video_compositor.meta';

function get_lock_status($lock_file, $meta_file) {
    if (!file_exists($lock_file)) {
        return [
            'locked' => false,
            'holder' => null,
            'pid' => null,
            'acquired_at' => null,
            'age_seconds' => 0,
            'is_stale' => false
        ];
    }
    
    $meta = [];
    if (file_exists($meta_file)) {
        $meta = json_decode(file_get_contents($meta_file), true);
    }
    
    $acquired_at = $meta['acquired_at'] ?? null;
    $age = 0;
    $is_stale = false;
    
    if ($acquired_at) {
        $lock_time = strtotime($acquired_at);
        $age = time() - $lock_time;
        
        // Stale if older than 1 hour
        if ($age > 3600) {
            $is_stale = true;
        }
    }
    
    return [
        'locked' => true,
        'holder' => $meta['job_id'] ?? 'unknown',
        'pid' => $meta['pid'] ?? null,
        'acquired_at' => $acquired_at,
        'age_seconds' => $age,
        'is_stale' => $is_stale
    ];
}

// GET: Return lock status
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $status = get_lock_status($lock_file, $meta_file);
    echo json_encode([
        'success' => true,
        'lock_status' => $status,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// POST: Force release lock (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    
    if ($action === 'force_release') {
        $reason = $input['reason'] ?? 'Manual intervention via API';
        
        $status = get_lock_status($lock_file, $meta_file);
        
        if (!$status['locked']) {
            echo json_encode([
                'success' => false,
                'error' => 'No lock to release'
            ]);
            exit;
        }
        
        // Force remove lock files
        $removed = false;
        if (file_exists($lock_file)) {
            @unlink($lock_file);
            $removed = true;
        }
        if (file_exists($meta_file)) {
            @unlink($meta_file);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Lock force released',
            'previous_holder' => $status['holder'],
            'reason' => $reason,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    echo json_encode([
        'success' => false,
        'error' => 'Invalid action'
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
