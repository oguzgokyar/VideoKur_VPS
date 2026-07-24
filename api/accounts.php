<?php
/**
 * Legacy Social Accounts API Endpoint
 * TikTok account management only (Instagram moved to Meta Accounts V2)
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

$dataDir = __DIR__ . '/../data';
$accountsFile = $dataDir . '/social_accounts.json';
$credentialsDir = $dataDir . '/social_credentials';

// Ensure directories exist
if (!is_dir($credentialsDir)) { 
    mkdir($credentialsDir, 0777, true); 
}

/**
 * Load accounts data
 */
function loadAccounts($file) {
    if (!file_exists($file)) {
        return [
            'instagram' => [],
            'tiktok' => []
        ];
    }
    $data = json_decode(file_get_contents($file), true);
    if (!$data) {
        return [
            'instagram' => [],
            'tiktok' => []
        ];
    }
    return $data;
}

/**
 * Save accounts data
 */
function saveAccounts($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function pruneLegacyInstagramData($file) {
    $data = loadAccounts($file);
    if (!isset($data['instagram']) || !is_array($data['instagram']) || count($data['instagram']) === 0) {
        return;
    }
    $data['instagram'] = [];
    saveAccounts($file, $data);
}

/**
 * Generate unique account ID
 */
function generateAccountId($platform, $username) {
    return $platform . '_' . md5($username . time());
}

// Read JSON input once if POST
$jsonInput = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST)) {
    $jsonInput = json_decode(file_get_contents('php://input'), true);
}

// Get action and platform from query string, form data, or JSON body
$action = $_GET['action'] ?? $_POST['action'] ?? ($jsonInput['action'] ?? '');
$platform = $_GET['platform'] ?? $_POST['platform'] ?? ($jsonInput['platform'] ?? '');

// Instagram legacy kayıtlarını temiz tut: Meta V2 dışına yazılmasın
pruneLegacyInstagramData($accountsFile);

// ==================== GET: List accounts ====================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'list') {
    $data = loadAccounts($accountsFile);
    $data['instagram'] = [];
    
    if ($platform && isset($data[$platform])) {
        if ($platform === 'instagram') {
            echo json_encode([
                'accounts' => [],
                'notice' => 'Instagram hesapları artık Meta Accounts V2 üzerinden yönetiliyor.'
            ]);
            exit;
        }
        echo json_encode(['accounts' => $data[$platform]]);
    } else {
        echo json_encode(['accounts' => $data]);
    }
    exit;
}

// ==================== POST: Connect account ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'connect') {
    $input = $jsonInput ?: json_decode(file_get_contents('php://input'), true);
    $platform = $input['platform'] ?? '';
    $username = $input['username'] ?? '';
    
    if (empty($platform) || empty($username)) {
        echo json_encode(['success' => false, 'error' => 'Platform ve kullanıcı adı gerekli']);
        exit;
    }
    
    if ($platform === 'instagram') {
        echo json_encode(['success' => false, 'error' => 'Instagram hesaplarını Hesaplar > Meta ekranından yönetin.']);
        exit;
    }

    if (!in_array($platform, ['tiktok'], true)) {
        echo json_encode(['success' => false, 'error' => 'Geçersiz platform (yalnızca tiktok desteklenir)']);
        exit;
    }
    
    $data = loadAccounts($accountsFile);
    
    // Check if account already exists
    foreach ($data[$platform] as $account) {
        if (strtolower($account['username']) === strtolower($username)) {
            echo json_encode(['success' => false, 'error' => 'Bu hesap zaten bağlı']);
            exit;
        }
    }
    
    // Create new account entry
    $accountId = generateAccountId($platform, $username);
    $isFirstAccount = count($data[$platform]) === 0;
    
    $newAccount = [
        'account_id' => $accountId,
        'username' => $username,
        'platform' => $platform,
        'is_default' => $isFirstAccount,
        'is_active' => true,
        'connected_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'followers_count' => null,
        'media_count' => null,
        'account_type' => 'personal'
    ];
    
    // Platform-specific fields
    if ($platform === 'tiktok') {
        $newAccount['likes_count'] = null;
        $newAccount['is_verified'] = false;
    }
    
    $data[$platform][] = $newAccount;
    saveAccounts($accountsFile, $data);
    
    // Create placeholder credentials file
    $credFile = $credentialsDir . '/' . $accountId . '.json';
    file_put_contents($credFile, json_encode([
        'account_id' => $accountId,
        'platform' => $platform,
        'username' => $username,
        'oauth_token' => null,
        'refresh_token' => null,
        'expires_at' => null,
        'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
        'note' => 'OAuth entegrasyonu için gerçek token burada saklanacak'
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    echo json_encode([
        'success' => true,
        'message' => ucfirst($platform) . ' hesabı başarıyla bağlandı',
        'account' => $newAccount
    ]);
    exit;
}

// ==================== POST: Disconnect account ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'disconnect') {
    $input = $jsonInput ?: json_decode(file_get_contents('php://input'), true);
    $platform = $input['platform'] ?? '';
    $accountId = $input['account_id'] ?? '';
    
    if (empty($platform) || empty($accountId)) {
        echo json_encode(['success' => false, 'error' => 'Platform ve account_id gerekli']);
        exit;
    }
    
    if ($platform === 'instagram') {
        echo json_encode(['success' => false, 'error' => 'Instagram hesaplarını Hesaplar > Meta ekranından yönetin.']);
        exit;
    }

    $data = loadAccounts($accountsFile);
    
    if (!isset($data[$platform])) {
        echo json_encode(['success' => false, 'error' => 'Geçersiz platform']);
        exit;
    }
    
    // Find and remove account
    $found = false;
    $wasDefault = false;
    $data[$platform] = array_values(array_filter($data[$platform], function($account) use ($accountId, &$found, &$wasDefault) {
        if ($account['account_id'] === $accountId) {
            $found = true;
            $wasDefault = $account['is_default'] ?? false;
            return false;
        }
        return true;
    }));
    
    if (!$found) {
        echo json_encode(['success' => false, 'error' => 'Hesap bulunamadı']);
        exit;
    }
    
    // If removed account was default, set first remaining as default
    if ($wasDefault && count($data[$platform]) > 0) {
        $data[$platform][0]['is_default'] = true;
    }
    
    saveAccounts($accountsFile, $data);
    
    // Remove credentials file
    $credFile = $credentialsDir . '/' . $accountId . '.json';
    if (file_exists($credFile)) {
        unlink($credFile);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Hesap bağlantısı kesildi'
    ]);
    exit;
}

// ==================== POST: Set default account ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'set_default') {
    $input = $jsonInput ?: json_decode(file_get_contents('php://input'), true);
    $platform = $input['platform'] ?? '';
    $accountId = $input['account_id'] ?? '';
    
    if (empty($platform) || empty($accountId)) {
        echo json_encode(['success' => false, 'error' => 'Platform ve account_id gerekli']);
        exit;
    }
    
    if ($platform === 'instagram') {
        echo json_encode(['success' => false, 'error' => 'Instagram hesaplarını Hesaplar > Meta ekranından yönetin.']);
        exit;
    }

    $data = loadAccounts($accountsFile);
    
    if (!isset($data[$platform])) {
        echo json_encode(['success' => false, 'error' => 'Geçersiz platform']);
        exit;
    }
    
    // Set all to non-default, then set target to default
    $found = false;
    foreach ($data[$platform] as &$account) {
        if ($account['account_id'] === $accountId) {
            $account['is_default'] = true;
            $found = true;
        } else {
            $account['is_default'] = false;
        }
    }
    
    if (!$found) {
        echo json_encode(['success' => false, 'error' => 'Hesap bulunamadı']);
        exit;
    }
    
    saveAccounts($accountsFile, $data);
    
    echo json_encode([
        'success' => true,
        'message' => 'Varsayılan hesap güncellendi'
    ]);
    exit;
}

// ==================== POST: Update account info ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update') {
    $input = $jsonInput ?: json_decode(file_get_contents('php://input'), true);
    $platform = $input['platform'] ?? '';
    $accountId = $input['account_id'] ?? '';
    $updates = $input['updates'] ?? [];
    
    if (empty($platform) || empty($accountId)) {
        echo json_encode(['success' => false, 'error' => 'Platform ve account_id gerekli']);
        exit;
    }
    
    if ($platform === 'instagram') {
        echo json_encode(['success' => false, 'error' => 'Instagram hesaplarını Hesaplar > Meta ekranından yönetin.']);
        exit;
    }

    $data = loadAccounts($accountsFile);
    
    if (!isset($data[$platform])) {
        echo json_encode(['success' => false, 'error' => 'Geçersiz platform']);
        exit;
    }
    
    // Find and update account
    $found = false;
    foreach ($data[$platform] as &$account) {
        if ($account['account_id'] === $accountId) {
            // Only allow updating certain fields
            $allowedFields = ['followers_count', 'media_count', 'likes_count', 'account_type', 'is_verified'];
            foreach ($allowedFields as $field) {
                if (isset($updates[$field])) {
                    $account[$field] = $updates[$field];
                }
            }
            $account['updated_at'] = gmdate('Y-m-d\TH:i:s\Z');
            $found = true;
            break;
        }
    }
    
    if (!$found) {
        echo json_encode(['success' => false, 'error' => 'Hesap bulunamadı']);
        exit;
    }
    
    saveAccounts($accountsFile, $data);
    
    echo json_encode([
        'success' => true,
        'message' => 'Hesap bilgileri güncellendi'
    ]);
    exit;
}

// ==================== GET: Get all accounts summary ====================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'summary') {
    $data = loadAccounts($accountsFile);
    
    $summary = [
        'instagram' => [
            'count' => 0,
            'default' => null
        ],
        'tiktok' => [
            'count' => count($data['tiktok']),
            'default' => null
        ]
    ];
    
    foreach ($data['tiktok'] as $account) {
        if ($account['is_default'] ?? false) {
            $summary['tiktok']['default'] = $account['username'];
            break;
        }
    }
    
    echo json_encode($summary);
    exit;
}

// Default response
echo json_encode(['error' => 'Invalid action', 'valid_actions' => ['list', 'connect', 'disconnect', 'set_default', 'update', 'summary']]);
