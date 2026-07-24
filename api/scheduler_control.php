<?php
/**
 * Scheduler Control API
 * Start/stop/status for production and social schedulers
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$BASE_DIR = realpath(__DIR__ . '/..');
$DATA_DIR = $BASE_DIR . '/data';
$SCHEDULER_STATUS_FILE = $DATA_DIR . '/scheduler_status.json';
$SCHEDULER_LOG_FILE = $DATA_DIR . '/scheduler.log';

// Helper: Load scheduler status
function loadSchedulerStatus() {
    global $SCHEDULER_STATUS_FILE;
    if (!file_exists($SCHEDULER_STATUS_FILE)) {
        return [
            'production' => ['running' => false, 'pid' => null, 'started_at' => null],
            'social' => ['running' => false, 'pid' => null, 'started_at' => null],
            'last_updated' => date('c')
        ];
    }
    return json_decode(file_get_contents($SCHEDULER_STATUS_FILE), true) ?: [];
}

// Helper: Save scheduler status
function saveSchedulerStatus($data) {
    global $SCHEDULER_STATUS_FILE;
    $data['last_updated'] = date('c');
    file_put_contents($SCHEDULER_STATUS_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Helper: Check if process is running
function isProcessRunning($pid) {
    if (!$pid) return false;
    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $output = [];
        $returnCode = 0;
        exec("tasklist /FI \"PID eq $pid\" 2>NUL", $output, $returnCode);
        foreach ($output as $line) {
            if (strpos($line, (string)$pid) !== false) {
                return true;
            }
        }

        // Some locked-down Windows environments deny tasklist. PowerShell's
        // Get-Process often still works and keeps the web UI status accurate.
        $psOutput = [];
        $psCode = 0;
        exec('powershell -NoProfile -Command "if (Get-Process -Id ' . intval($pid) . ' -ErrorAction SilentlyContinue) { Write-Output RUNNING }" 2>NUL', $psOutput, $psCode);
        foreach ($psOutput as $line) {
            if (trim($line) === 'RUNNING') {
                return true;
            }
        }
        return false;
    } else {
        return file_exists("/proc/$pid");
    }
}
// Helper: Get recent logs
function getRecentLogs($lines = 100) {
    global $SCHEDULER_LOG_FILE;
    if (!file_exists($SCHEDULER_LOG_FILE)) {
        return [];
    }
    
    $content = file_get_contents($SCHEDULER_LOG_FILE);
    $allLines = explode("\n", $content);
    $recent = array_slice($allLines, -$lines);
    return array_filter($recent, function($l) { return trim($l) !== ''; });
}

// Helper: Clear logs
function clearLogs() {
    global $SCHEDULER_LOG_FILE;
    file_put_contents($SCHEDULER_LOG_FILE, '');
}

// Helper: Start scheduler process
function startScheduler($type) {
    global $BASE_DIR, $SCHEDULER_LOG_FILE;
    
    $pythonCmd = 'python';
    
    if ($type === 'production') {
        $script = $BASE_DIR . '/python/scheduler/production_scheduler.py';
    } elseif ($type === 'social') {
        $script = $BASE_DIR . '/python/scheduler/social_scheduler.py';
    } else {
        return ['success' => false, 'error' => 'Invalid scheduler type'];
    }
    
    if (!file_exists($script)) {
        return ['success' => false, 'error' => "Script not found: $script"];
    }
    
    // Start scheduler with logging
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows: Use Python subprocess to start in background
        $logPath = str_replace('/', '\\', $SCHEDULER_LOG_FILE);
        $scriptPath = str_replace('/', '\\', $script);
        $baseDir = str_replace('/', '\\', $BASE_DIR);
        
        // Create wrapper script that starts scheduler and returns PID
        $wrapperScript = <<<PYTHON
import sys
import subprocess
import os

os.chdir(r'$baseDir')

# Set UTF-8 encoding for subprocess
env = os.environ.copy()
env['PYTHONIOENCODING'] = 'utf-8'

log_file = open(r'$logPath', 'a', encoding='utf-8')

# CREATE_NO_WINDOW = 0x08000000
process = subprocess.Popen(
    [sys.executable, r'$scriptPath'],
    stdout=log_file,
    stderr=subprocess.STDOUT,
    env=env,
    creationflags=0x08000000
)
print(process.pid)
log_file.close()
PYTHON;
        
        $wrapperFile = $BASE_DIR . '/data/start_' . $type . '.py';
        file_put_contents($wrapperFile, $wrapperScript);
        
        // Run wrapper and capture PID
        $output = [];
        $returnCode = 0;
        exec("$pythonCmd \"$wrapperFile\" 2>&1", $output, $returnCode);
        
        $pid = null;
        if (!empty($output[0]) && is_numeric(trim($output[0]))) {
            $pid = (int)trim($output[0]);
        }
        
        // Clean up wrapper (delay to ensure it's done)
        usleep(100000);
        @unlink($wrapperFile);
        
        if ($returnCode !== 0 || !$pid) {
            $error = implode("\n", $output);
            return ['success' => false, 'error' => "Başlatma hatası: $error"];
        }
        
    } else {
        // Linux: Start with nohup
        $cmd = "nohup $pythonCmd \"$script\" >> \"$SCHEDULER_LOG_FILE\" 2>&1 & echo $!";
        $pid = (int)shell_exec($cmd);
    }
    
    return ['success' => true, 'pid' => $pid];
}

// Helper: Stop scheduler process
function stopScheduler($pid) {
    if (!$pid) return false;
    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        exec("taskkill /PID $pid /F 2>NUL");
    } else {
        exec("kill -9 $pid 2>/dev/null");
    }
    
    return true;
}

// Helper: Kill orphan scheduler processes of same type
function stopOtherSchedulers($type, $exceptPid = null) {
    if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        return;
    }
    $scriptName = $type === 'social' ? 'social_scheduler.py' : 'production_scheduler.py';
    $output = [];
    exec('wmic process where "name=\'python.exe\' and commandline like \'%' . $scriptName . '%\'" get processid 2>NUL', $output);
    foreach ($output as $line) {
        $pid = trim($line);
        if (!is_numeric($pid) || (int)$pid <= 0) {
            continue;
        }
        $pid = (int)$pid;
        if ($exceptPid && $pid === (int)$exceptPid) {
            continue;
        }
        exec("taskkill /PID $pid /F 2>NUL");
    }
}

// GET: Status and logs
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'status';
    
    switch ($action) {
        case 'status':
            $status = loadSchedulerStatus();
            
            // Verify processes are actually running
            foreach (['production', 'social'] as $type) {
                if (isset($status[$type]) && $status[$type]['running'] && !isProcessRunning($status[$type]['pid'])) {
                    $status[$type]['running'] = false;
                    $status[$type]['pid'] = null;
                    $status[$type]['stopped_at'] = date('c');
                }
            }
            
            saveSchedulerStatus($status);
            echo json_encode(['success' => true, 'status' => $status]);
            break;
            
        case 'logs':
            $lines = intval($_GET['lines'] ?? 100);
            $logs = getRecentLogs($lines);
            echo json_encode(['success' => true, 'logs' => array_values($logs), 'count' => count($logs)]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    exit;
}

// POST: Control schedulers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';
    $type = $input['type'] ?? 'production'; // production or social
    
    $status = loadSchedulerStatus();
    
    // Ensure type key exists
    if (!isset($status[$type])) {
        $status[$type] = ['running' => false, 'pid' => null, 'started_at' => null];
    }
    
    switch ($action) {
        case 'start':
            if ($status[$type]['running'] && isProcessRunning($status[$type]['pid'])) {
                echo json_encode(['success' => false, 'error' => ucfirst($type) . ' scheduler zaten çalışıyor']);
                break;
            }

            // Safety: stop orphan schedulers before start (single instance guarantee)
            stopOtherSchedulers($type);
            usleep(300000);
            
            $result = startScheduler($type);
            
            if ($result['success']) {
                $status[$type] = [
                    'running' => true,
                    'pid' => $result['pid'],
                    'started_at' => date('c')
                ];
                saveSchedulerStatus($status);
                
                 // Log start (suppress warnings)
                 global $SCHEDULER_LOG_FILE;
                 @file_put_contents(
                     $SCHEDULER_LOG_FILE, 
                     "[" . date('Y-m-d H:i:s') . "] 🚀 $type scheduler başlatıldı (PID: {$result['pid']})\n",
                     FILE_APPEND | LOCK_EX
                 );
                
                echo json_encode(['success' => true, 'message' => ucfirst($type) . ' scheduler başlatıldı', 'pid' => $result['pid']]);
            } else {
                echo json_encode($result);
            }
            break;
            
        case 'stop':
            if (!$status[$type]['running']) {
                echo json_encode(['success' => false, 'error' => ucfirst($type) . ' scheduler zaten durdurulmuş']);
                break;
            }
            
            $pid = $status[$type]['pid'];
            stopScheduler($pid);
            
            $status[$type] = [
                'running' => false,
                'pid' => null,
                'stopped_at' => date('c')
            ];
            saveSchedulerStatus($status);
            
            // Log stop (suppress warnings)
            $logMessage = "[" . date('Y-m-d H:i:s') . "] ⏹️ $type scheduler durduruldu\n";
            @file_put_contents($SCHEDULER_LOG_FILE, $logMessage, FILE_APPEND | LOCK_EX);
            
            echo json_encode(['success' => true, 'message' => ucfirst($type) . ' scheduler durduruldu']);
            break;
            
        case 'restart':
            // Stop existing scheduler if running
            if ($status[$type]['running'] && $status[$type]['pid']) {
                $oldPid = $status[$type]['pid'];
                stopScheduler($oldPid);
                usleep(500000); // Wait 500ms for clean stop
            }

            // Also kill any other schedulers of same type that might be running
            stopOtherSchedulers($type);
            usleep(1000000); // Wait 1s
            
            // Clear old logs for fresh start
            clearLogs();
            
            // Start new scheduler
            $result = startScheduler($type);
            
            if ($result['success']) {
                $status[$type] = [
                    'running' => true,
                    'pid' => $result['pid'],
                    'started_at' => date('c'),
                    'restarted' => true
                ];
                saveSchedulerStatus($status);
                
                // Log restart
                global $SCHEDULER_LOG_FILE;
                @file_put_contents(
                    $SCHEDULER_LOG_FILE, 
                    "[" . date('Y-m-d H:i:s') . "] 🔄 $type scheduler yeniden başlatıldı (PID: {$result['pid']})\n",
                    FILE_APPEND | LOCK_EX
                );
                
                echo json_encode([
                    'success' => true, 
                    'message' => ucfirst($type) . ' scheduler yeniden başlatıldı', 
                    'pid' => $result['pid']
                ]);
            } else {
                echo json_encode($result);
            }
            break;
            
        case 'clear_logs':
            clearLogs();
            echo json_encode(['success' => true, 'message' => 'Loglar temizlendi']);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'error' => 'Method not allowed']);

