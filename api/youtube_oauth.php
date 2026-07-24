<?php
/**
 * YouTube OAuth API Endpoint
 * Web-based OAuth flow for unified channel-API system
 * 
 * Flow:
 * 1. User visits ?channel_id=X&api_id=Y
 * 2. If no code: redirect to Google OAuth
 * 3. If code: exchange for token, save, redirect back
 */

session_start();

$dataDir = __DIR__ . '/../data';
$channelsFile = $dataDir . '/youtube_channels.json';
$credentialsDir = $dataDir . '/youtube_credentials';

// Ensure credentials directory exists
if (!is_dir($credentialsDir)) {
    mkdir($credentialsDir, 0755, true);
}

// Load channels data
function loadChannels($file) {
    if (!file_exists($file)) {
        return ['channels' => []];
    }
    return json_decode(file_get_contents($file), true) ?: ['channels' => []];
}

// Save channels data
function saveChannels($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Find API in channel
function findApi($data, $channelId, $apiId) {
    foreach ($data['channels'] as $cIndex => $channel) {
        if ($channel['id'] === $channelId) {
            foreach ($channel['apis'] as $aIndex => $api) {
                if ($api['api_id'] === $apiId) {
                    return [
                        'channel' => $channel,
                        'api' => $api,
                        'channel_index' => $cIndex,
                        'api_index' => $aIndex
                    ];
                }
            }
        }
    }
    return null;
}

// Load client secrets
function loadClientSecrets($path) {
    if (!file_exists($path)) {
        return null;
    }
    return json_decode(file_get_contents($path), true);
}

// Build redirect URI
function getRedirectUri() {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    return $protocol . '://' . $host . '/api/youtube_oauth.php';
}

// Show HTML page
function showPage($title, $message, $type = 'info', $autoClose = false) {
    $bgColor = $type === 'success' ? '#10B981' : ($type === 'error' ? '#EF4444' : '#3B82F6');
    $icon = $type === 'success' ? '✅' : ($type === 'error' ? '❌' : 'ℹ️');
    
    $closeScript = $autoClose ? "<script>setTimeout(function(){ window.close(); }, 2000);</script>" : "";
    $closeMsg = $autoClose ? "<p style='margin-top:15px;font-size:12px;opacity:0.6'>Bu sekme 2 saniye içinde kapanacak...</p>" : "";
    
    echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>$title</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; background: #1a1a2e; color: white; }
        .card { background: #16213e; padding: 40px; border-radius: 12px; text-align: center; max-width: 500px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
        .icon { font-size: 48px; margin-bottom: 20px; }
        h1 { margin: 0 0 10px; font-size: 24px; }
        p { margin: 0; opacity: 0.8; line-height: 1.6; }
        .status { display: inline-block; padding: 8px 16px; border-radius: 20px; background: $bgColor; margin-top: 20px; font-size: 14px; }
    </style>
    $closeScript
</head>
<body>
    <div class="card">
        <div class="icon">$icon</div>
        <h1>$title</h1>
        <p>$message</p>
        <div class="status">$type</div>
        $closeMsg
    </div>
</body>
</html>
HTML;
    exit;
}

// ============================================================================
// MAIN FLOW
// ============================================================================

// Step 1: Check if this is OAuth callback (has 'code' parameter)
if (isset($_GET['code'])) {
    // OAuth callback - exchange code for token
    $code = $_GET['code'];
    $state = $_GET['state'] ?? '';
    
    // Parse state (contains channel_id and api_id)
    parse_str($state, $stateData);
    $channelId = $stateData['channel_id'] ?? '';
    $apiId = $stateData['api_id'] ?? '';
    
    if (empty($channelId) || empty($apiId)) {
        showPage('OAuth Hatası', 'Geçersiz state parametresi', 'error');
    }
    
    // Load API info
    $data = loadChannels($channelsFile);
    $result = findApi($data, $channelId, $apiId);
    
    if (!$result) {
        showPage('API Bulunamadı', "Channel: $channelId, API: $apiId", 'error');
    }
    
    $api = $result['api'];
    $clientSecretsPath = $dataDir . '/' . $api['client_secrets_file'];
    $secrets = loadClientSecrets($clientSecretsPath);
    
    if (!$secrets || !isset($secrets['web']) && !isset($secrets['installed'])) {
        showPage('Client Secrets Hatası', 'Geçersiz client_secrets dosyası', 'error');
    }
    
    $clientConfig = $secrets['web'] ?? $secrets['installed'];
    
    // Exchange code for token
    $tokenUrl = 'https://oauth2.googleapis.com/token';
    $postData = [
        'code' => $code,
        'client_id' => $clientConfig['client_id'],
        'client_secret' => $clientConfig['client_secret'],
        'redirect_uri' => getRedirectUri(),
        'grant_type' => 'authorization_code'
    ];
    
    $ch = curl_init($tokenUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $tokenData = json_decode($response, true);
    
    if ($httpCode !== 200 || isset($tokenData['error'])) {
        $error = $tokenData['error_description'] ?? $tokenData['error'] ?? 'Unknown error';
        showPage('Token Hatası', $error, 'error');
    }
    
    // Save token to credentials file with complete OAuth2 format
    $tokenFile = $credentialsDir . '/' . $api['project_id'] . '_' . $channelId . '_' . $apiId . '_token.json';
    $tokenData['created_at'] = time();
    
    // Add required fields for Google OAuth2 Credentials class (python/youtube/auth.py)
    $tokenData['client_id'] = $clientConfig['client_id'];
    $tokenData['client_secret'] = $clientConfig['client_secret'];
    $tokenData['token_uri'] = $clientConfig['token_uri'];
    
    file_put_contents($tokenFile, json_encode($tokenData, JSON_PRETTY_PRINT));
    
    // Update API status in youtube_channels.json
    $data['channels'][$result['channel_index']]['apis'][$result['api_index']]['is_authenticated'] = true;
    $data['channels'][$result['channel_index']]['apis'][$result['api_index']]['is_active'] = true;
    $data['channels'][$result['channel_index']]['apis'][$result['api_index']]['token_file'] = basename($tokenFile);
    saveChannels($channelsFile, $data);
    
    showPage(
        'OAuth Başarılı! ✅', 
        "API '$api[name]' başarıyla yetkilendirildi.", 
        'success',
        true  // autoClose = true
    );
}

// Step 2: Check for error callback
if (isset($_GET['error'])) {
    $error = $_GET['error_description'] ?? $_GET['error'];
    showPage('OAuth İptal Edildi', $error, 'error');
}

// Step 3: Start OAuth flow - need channel_id and api_id
$channelId = $_GET['channel_id'] ?? '';
$apiId = $_GET['api_id'] ?? '';

if (empty($channelId) || empty($apiId)) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'channel_id ve api_id parametreleri gerekli'
    ]);
    exit;
}

// Load channels and find API
$data = loadChannels($channelsFile);
$result = findApi($data, $channelId, $apiId);

if (!$result) {
    showPage('API Bulunamadı', "Channel: $channelId, API: $apiId bulunamadı", 'error');
}

$api = $result['api'];
$clientSecretsFile = $api['client_secrets_file'] ?? '';

if (empty($clientSecretsFile)) {
    showPage('Client Secrets Eksik', 'Bu API için client_secrets dosyası tanımlı değil', 'error');
}

// Full path to client secrets
$clientSecretsPath = $dataDir . '/' . $clientSecretsFile;

if (!file_exists($clientSecretsPath)) {
    showPage('Dosya Bulunamadı', "Client secrets dosyası bulunamadı: $clientSecretsFile", 'error');
}

// Load client secrets
$secrets = loadClientSecrets($clientSecretsPath);

if (!$secrets || !isset($secrets['web']) && !isset($secrets['installed'])) {
    showPage('Geçersiz Dosya', 'Client secrets dosyası geçerli bir Google OAuth dosyası değil', 'error');
}

$clientConfig = $secrets['web'] ?? $secrets['installed'];
$clientId = $clientConfig['client_id'];

// Build OAuth URL
$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth';
$params = [
    'client_id' => $clientId,
    'redirect_uri' => getRedirectUri(),
    'response_type' => 'code',
    'scope' => 'https://www.googleapis.com/auth/youtube.upload https://www.googleapis.com/auth/youtube https://www.googleapis.com/auth/youtube.force-ssl',
    'access_type' => 'offline',
    'prompt' => 'consent',
    'state' => http_build_query(['channel_id' => $channelId, 'api_id' => $apiId])
];

$oauthUrl = $authUrl . '?' . http_build_query($params);

// Redirect to Google OAuth
header('Location: ' . $oauthUrl);
exit;
