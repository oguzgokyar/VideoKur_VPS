<?php
/**
 * YouTube Channels API - Unified Multi-Channel Management
 * 
 * Endpoints:
 * - GET  ?action=list                    - List all channels with APIs
 * - GET  ?action=get&id=channel_001      - Get single channel details
 * - POST ?action=add_channel             - Add new channel
 * - POST ?action=delete_channel          - Delete channel and all its APIs
 * - POST ?action=add_api                 - Add API to channel
 * - POST ?action=login_api               - Initiate OAuth login
 * - POST ?action=delete_api              - Delete API from channel
 * - POST ?action=update_api              - Update API settings
 * - POST ?action=update_quota            - Update API quota usage
 * - POST ?action=set_default_channel     - Set default channel
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$dataDir = __DIR__ . '/../data';
$channelsFile = $dataDir . '/youtube_channels.json';

// Load channels data
function loadChannels() {
    global $channelsFile;
    if (!file_exists($channelsFile)) {
        return ['channels' => []];
    }
    $content = file_get_contents($channelsFile);
    return json_decode($content, true) ?: ['channels' => []];
}

// Save channels data
function saveChannels($data) {
    global $channelsFile;
    file_put_contents($channelsFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// Generate unique ID
function generateId($prefix = 'channel') {
    return $prefix . '_' . substr(uniqid(), -6);
}

// Find channel by ID
function findChannel($channelId) {
    $data = loadChannels();
    foreach ($data['channels'] as $index => $channel) {
        if ($channel['id'] === $channelId) {
            return ['channel' => $channel, 'index' => $index];
        }
    }
    return null;
}

// Find API in channel
function findApi($channelId, $apiId) {
    $result = findChannel($channelId);
    if (!$result) return null;
    
    $channel = $result['channel'];
    foreach ($channel['apis'] as $index => $api) {
        if ($api['api_id'] === $apiId) {
            return ['api' => $api, 'channel_index' => $result['index'], 'api_index' => $index];
        }
    }
    return null;
}

// Get request action
$inputData = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputData = json_decode(file_get_contents('php://input'), true);
}
$action = $_GET['action'] ?? ($inputData['action'] ?? 'list');

try {
    switch ($action) {
        // ============================================================
        // LIST - Get all channels with APIs
        // ============================================================
        case 'list':
            $data = loadChannels();
            
            // Reset daily quotas if needed (new day check)
            $needsSave = false;
            $currentDate = date('Y-m-d');
            
            foreach ($data['channels'] as &$channel) {
                foreach ($channel['apis'] as &$api) {
                    $lastResetDate = $api['last_quota_reset'] ?? null;
                    
                    // If last reset was on a different day, reset quota
                    if ($lastResetDate !== $currentDate) {
                        $api['quota_used_today'] = 0;
                        $api['last_quota_reset'] = $currentDate;
                        $needsSave = true;
                    }
                }
                unset($api); // PHP reference bug fix
            }
            unset($channel); // PHP reference bug fix
            
            // Save if quotas were reset
            if ($needsSave) {
                saveChannels($data);
            }
            
            // Calculate totals per channel
            foreach ($data['channels'] as &$channel) {
                $totalQuotaUsed = 0;
                $totalQuotaLimit = 0;
                $activeApiCount = 0;
                
                foreach ($channel['apis'] as $api) {
                    $totalQuotaUsed += $api['quota_used_today'] ?? 0;
                    $totalQuotaLimit += $api['daily_quota'] ?? 10000;
                    if ($api['is_active'] && $api['is_authenticated']) {
                        $activeApiCount++;
                    }
                }
                
                $channel['total_quota_used'] = $totalQuotaUsed;
                $channel['total_quota_limit'] = $totalQuotaLimit;
                $channel['active_api_count'] = $activeApiCount;
                $channel['api_count'] = count($channel['apis']);
            }
            unset($channel); // PHP reference bug fix
            
            echo json_encode([
                'success' => true,
                'channels' => $data['channels'],
                'total_channels' => count($data['channels'])
            ]);
            break;
        
        // ============================================================
        // GET - Get single channel details
        // ============================================================
        case 'get':
            $channelId = $_GET['id'] ?? '';
            
            if (empty($channelId)) {
                throw new Exception('Channel ID gerekli');
            }
            
            $result = findChannel($channelId);
            if (!$result) {
                throw new Exception('Channel bulunamadı');
            }
            
            echo json_encode([
                'success' => true,
                'channel' => $result['channel']
            ]);
            break;
        
        // ============================================================
        // ADD_CHANNEL - Add new channel
        // ============================================================
        case 'add_channel':
            $input = $inputData;
            
            $channelTitle = $input['channel_title'] ?? '';
            $channelId = $input['channel_id'] ?? '';
            
            if (empty($channelTitle)) {
                throw new Exception('Channel title gerekli');
            }
            
            $data = loadChannels();
            
            // Check if channel already exists
            foreach ($data['channels'] as $ch) {
                if ($ch['channel_id'] === $channelId && !empty($channelId)) {
                    throw new Exception('Bu channel zaten ekli');
                }
            }
            
            // Create new channel
            $newChannel = [
                'id' => generateId('channel'),
                'channel_id' => $channelId,
                'channel_title' => $channelTitle,
                'channel_url' => !empty($channelId) ? "https://youtube.com/channel/{$channelId}" : '',
                'thumbnail' => $input['thumbnail'] ?? '',
                'subscriber_count' => $input['subscriber_count'] ?? 0,
                'video_count' => $input['video_count'] ?? 0,
                'description' => $input['description'] ?? '',
                'is_default' => count($data['channels']) === 0, // First channel is default
                'is_active' => true,
                'connected_at' => date('c'),
                'apis' => []
            ];
            
            $data['channels'][] = $newChannel;
            saveChannels($data);
            
            echo json_encode([
                'success' => true,
                'message' => 'Channel eklendi',
                'channel' => $newChannel
            ]);
            break;
        
        // ============================================================
        // DELETE_CHANNEL - Delete channel and all its APIs
        // ============================================================
        case 'delete_channel':
            $input = $inputData;
            $channelId = $input['channel_id'] ?? '';
            
            if (empty($channelId)) {
                throw new Exception('Channel ID gerekli');
            }
            
            $data = loadChannels();
            $channelIndex = null;
            $channel = null;
            
            foreach ($data['channels'] as $i => $ch) {
                if ($ch['id'] === $channelId) {
                    $channelIndex = $i;
                    $channel = $ch;
                    break;
                }
            }
            
            if ($channelIndex === null) {
                throw new Exception('Channel bulunamadı');
            }
            
            // Delete all API credential files
            $deletedFiles = [];
            if (!empty($channel['apis'])) {
                foreach ($channel['apis'] as $api) {
                    $clientSecretsFile = $api['client_secrets_file'] ?? '';
                    if (!empty($clientSecretsFile)) {
                        $filePath = $dataDir . '/' . $clientSecretsFile;
                        if (file_exists($filePath)) {
                            unlink($filePath);
                            $deletedFiles[] = $clientSecretsFile;
                        }
                    }
                    // Delete token file if exists
                    $tokenFile = $api['token_file'] ?? '';
                    if (!empty($tokenFile)) {
                        $tokenPath = $dataDir . '/youtube_credentials/' . $tokenFile;
                        if (file_exists($tokenPath)) {
                            unlink($tokenPath);
                            $deletedFiles[] = $tokenFile;
                        }
                    }
                }
            }
            
            // Remove channel from array
            array_splice($data['channels'], $channelIndex, 1);
            saveChannels($data);
            
            echo json_encode([
                'success' => true,
                'message' => 'Channel ve tüm API\'leri silindi',
                'deleted_files' => $deletedFiles
            ]);
            break;
        
        // ============================================================
        // ADD_API - Add API to channel
        // ============================================================
        case 'add_api':
            $input = $inputData;
            
            $channelId = $input['channel_id'] ?? '';
            $apiName = $input['name'] ?? '';
            $clientSecrets = $input['client_secrets'] ?? null;
            $clientSecretsFile = $input['client_secrets_file'] ?? '';
            
            if (empty($channelId) || empty($apiName)) {
                throw new Exception('Channel ID ve API adı gerekli');
            }
            
            // Client secrets ya dosya adı ya da JSON data olabilir
            if (empty($clientSecrets) && empty($clientSecretsFile)) {
                throw new Exception('Client secrets gerekli');
            }
            
            $data = loadChannels();
            $result = findChannel($channelId);
            
            if (!$result) {
                throw new Exception('Channel bulunamadı');
            }
            
            // Project ID'yi client_secrets'tan çıkar
            $projectId = $input['project_id'] ?? '';
            if (empty($projectId) && $clientSecrets) {
                if (isset($clientSecrets['installed']['project_id'])) {
                    $projectId = $clientSecrets['installed']['project_id'];
                } elseif (isset($clientSecrets['web']['project_id'])) {
                    $projectId = $clientSecrets['web']['project_id'];
                }
            }
            if (empty($projectId)) {
                $projectId = generateId('project');
            }
            
            // D) Duplicate project_id kontrolü - aynı kanalda aynı project_id ile API var mı?
            $existingApis = $data['channels'][$result['index']]['apis'] ?? [];
            foreach ($existingApis as $existingApi) {
                if ($existingApi['project_id'] === $projectId) {
                    throw new Exception("Bu project_id ($projectId) zaten bu kanalda kayıtlı. Farklı bir API kullanın veya mevcut olanı silin.");
                }
            }
            
            // Client secrets dosyasını youtube_credentials klasörüne kaydet
            $credentialsDir = $dataDir . '/youtube_credentials';
            if (!is_dir($credentialsDir)) {
                mkdir($credentialsDir, 0755, true);
            }
            
            $secretsFileName = '';
            if ($clientSecrets) {
                $secretsFileName = 'client_secrets_' . $projectId . '.json';
                $secretsPath = $credentialsDir . '/' . $secretsFileName;
                file_put_contents($secretsPath, json_encode($clientSecrets, JSON_PRETTY_PRINT));
                // Dosya adını youtube_credentials/ prefix ile kaydet
                $secretsFileName = 'youtube_credentials/' . $secretsFileName;
            } else {
                $secretsFileName = $clientSecretsFile;
            }
            
            // Generate new api_id
            $apiId = generateId('api');
            
            // D) Token dosya adını önceden belirle (OAuth sonrası bu formatta oluşacak)
            $expectedTokenFile = $projectId . '_' . $channelId . '_' . $apiId . '_token.json';
            
            // Create new API
            $newApi = [
                'api_id' => $apiId,
                'name' => $apiName,
                'project_id' => $projectId,
                'client_secrets_file' => $secretsFileName,
                'token_file' => '',  // OAuth sonrası dolacak, beklenen format: $expectedTokenFile
                'google_account_email' => $input['google_account_email'] ?? '',
                'is_authenticated' => false,
                'is_active' => false,
                'daily_quota' => intval($input['daily_quota'] ?? 10000),
                'quota_used_today' => 0,
                'upload_count_today' => 0,
                'last_upload' => null,
                'last_reset' => date('c'),
                'last_quota_reset' => date('Y-m-d'),
                'created_at' => date('c'),
                'notes' => $input['notes'] ?? ''
            ];
            
            $data['channels'][$result['index']]['apis'][] = $newApi;
            saveChannels($data);
            
            echo json_encode([
                'success' => true,
                'message' => 'API eklendi: ' . $apiName,
                'api' => $newApi,
                'oauth_url' => '/api/youtube_oauth.php?channel_id=' . $channelId . '&api_id=' . $apiId
            ]);
            break;
        
        // ============================================================
        // LOGIN_API - Initiate OAuth login for API
        // ============================================================
        case 'login_api':
            $input = $inputData;
            
            $channelId = $input['channel_id'] ?? '';
            $apiId = $input['api_id'] ?? '';
            
            if (empty($channelId) || empty($apiId)) {
                throw new Exception('Channel ID ve API ID gerekli');
            }
            
            $result = findApi($channelId, $apiId);
            if (!$result) {
                throw new Exception('API bulunamadı');
            }
            
            // Return OAuth URL (will be handled by Python backend)
            echo json_encode([
                'success' => true,
                'message' => 'OAuth başlatılacak',
                'oauth_url' => '/api/youtube_oauth.php?channel_id=' . $channelId . '&api_id=' . $apiId
            ]);
            break;
        
        // ============================================================
        // DELETE_API - Delete API from channel
        // ============================================================
        case 'delete_api':
            $input = $inputData;
            
            $channelId = $input['channel_id'] ?? '';
            $apiId = $input['api_id'] ?? '';
            
            if (empty($channelId) || empty($apiId)) {
                throw new Exception('Channel ID ve API ID gerekli');
            }
            
            $data = loadChannels();
            $result = findApi($channelId, $apiId);
            
            if (!$result) {
                throw new Exception('API bulunamadı');
            }
            
            $api = $result['api'];
            $deletedFiles = [];
            $credentialsDir = $dataDir . '/youtube_credentials';
            
            // 1. Client secrets dosyasını sil
            $clientSecretsFile = $api['client_secrets_file'] ?? '';
            if (!empty($clientSecretsFile)) {
                $filePath = $dataDir . '/' . $clientSecretsFile;
                if (file_exists($filePath)) {
                    unlink($filePath);
                    $deletedFiles[] = basename($filePath);
                }
            }
            
            // 2. Token dosyasını sil (kayıtlı olan)
            $tokenFile = $api['token_file'] ?? '';
            if (!empty($tokenFile)) {
                $tokenPath = $credentialsDir . '/' . $tokenFile;
                if (file_exists($tokenPath)) {
                    unlink($tokenPath);
                    $deletedFiles[] = $tokenFile;
                }
            }
            
            // 3. İlişkili olabilecek diğer token dosyalarını da temizle
            // Format: {project_id}_{channel_id}_{api_id}_token.json
            $projectId = $api['project_id'] ?? '';
            if (!empty($projectId)) {
                $pattern = $credentialsDir . '/' . $projectId . '_' . $channelId . '_*_token.json';
                $relatedTokens = glob($pattern);
                foreach ($relatedTokens as $relatedToken) {
                    if (file_exists($relatedToken)) {
                        unlink($relatedToken);
                        $deletedFiles[] = basename($relatedToken);
                    }
                }
                
                // Ayrıca project_id ile başlayan tüm token'ları da kontrol et
                $pattern2 = $credentialsDir . '/' . $projectId . '_*_token.json';
                $allProjectTokens = glob($pattern2);
                foreach ($allProjectTokens as $projToken) {
                    if (file_exists($projToken) && !in_array(basename($projToken), $deletedFiles)) {
                        unlink($projToken);
                        $deletedFiles[] = basename($projToken);
                    }
                }
            }
            
            // 4. API kaydını sil
            array_splice($data['channels'][$result['channel_index']]['apis'], $result['api_index'], 1);
            saveChannels($data);
            
            echo json_encode([
                'success' => true,
                'message' => 'API ve tüm ilişkili dosyalar silindi',
                'deleted_files' => $deletedFiles
            ]);
            break;
        
        // ============================================================
        // UPDATE_API - Update API settings
        // ============================================================
        case 'update_api':
            $input = $inputData;
            
            $channelId = $input['channel_id'] ?? '';
            $apiId = $input['api_id'] ?? '';
            
            if (empty($channelId) || empty($apiId)) {
                throw new Exception('Channel ID ve API ID gerekli');
            }
            
            $data = loadChannels();
            $result = findApi($channelId, $apiId);
            
            if (!$result) {
                throw new Exception('API bulunamadı');
            }
            
            // Update fields
            $api = &$data['channels'][$result['channel_index']]['apis'][$result['api_index']];
            
            if (isset($input['name'])) $api['name'] = $input['name'];
            if (isset($input['is_active'])) $api['is_active'] = $input['is_active'];
            if (isset($input['is_authenticated'])) $api['is_authenticated'] = $input['is_authenticated'];
            if (isset($input['google_account_email'])) $api['google_account_email'] = $input['google_account_email'];
            if (isset($input['daily_quota'])) $api['daily_quota'] = $input['daily_quota'];
            if (isset($input['notes'])) $api['notes'] = $input['notes'];
            
            saveChannels($data);
            
            echo json_encode([
                'success' => true,
                'message' => 'API güncellendi',
                'api' => $api
            ]);
            break;
        
        // ============================================================
        // UPDATE_QUOTA - Update API quota usage
        // ============================================================
        case 'update_quota':
            $input = $inputData;
            
            $channelId = $input['channel_id'] ?? '';
            $apiId = $input['api_id'] ?? '';
            $quotaUsed = $input['quota_used'] ?? 0;
            
            if (empty($channelId) || empty($apiId)) {
                throw new Exception('Channel ID ve API ID gerekli');
            }
            
            $data = loadChannels();
            $result = findApi($channelId, $apiId);
            
            if (!$result) {
                throw new Exception('API bulunamadı');
            }
            
            $api = &$data['channels'][$result['channel_index']]['apis'][$result['api_index']];
            $api['quota_used_today'] = $quotaUsed;
            $api['last_upload'] = date('c');
            $api['upload_count_today'] = ($api['upload_count_today'] ?? 0) + 1;
            
            saveChannels($data);
            
            echo json_encode([
                'success' => true,
                'message' => 'Quota güncellendi'
            ]);
            break;
        
        // ============================================================
        // UPDATE_CHANNEL_CATEGORY - Update channel default category
        // ============================================================
        case 'update_channel_category':
            $input = $inputData;
            $channelId = $input['channel_id'] ?? '';
            $categoryId = $input['category_id'] ?? '28';
            
            if (empty($channelId)) {
                throw new Exception('Channel ID gerekli');
            }
            
            $data = loadChannels();
            $found = false;
            
            foreach ($data['channels'] as &$channel) {
                if ($channel['id'] === $channelId) {
                    $channel['default_category_id'] = $categoryId;
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                throw new Exception('Channel bulunamadı');
            }
            
            saveChannels($data);
            
            echo json_encode([
                'success' => true,
                'message' => 'Kategori güncellendi'
            ]);
            break;
        
        // ============================================================
        // SET_DEFAULT_CHANNEL - Set default channel
        // ============================================================
        case 'set_default_channel':
            $input = $inputData;
            $channelId = $input['channel_id'] ?? '';
            
            if (empty($channelId)) {
                throw new Exception('Channel ID gerekli');
            }
            
            $data = loadChannels();
            $found = false;
            
            // Remove default from all channels
            foreach ($data['channels'] as &$channel) {
                $channel['is_default'] = ($channel['id'] === $channelId);
                if ($channel['id'] === $channelId) {
                    $found = true;
                }
            }
            
            if (!$found) {
                throw new Exception('Channel bulunamadı');
            }
            
            saveChannels($data);
            
            echo json_encode([
                'success' => true,
                'message' => 'Varsayılan channel güncellendi'
            ]);
            break;
        
        default:
            throw new Exception('Geçersiz action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

