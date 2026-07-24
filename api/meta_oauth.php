<?php
require_once __DIR__ . '/meta_helpers.php';

$dataDir = __DIR__ . '/../data';
meta_ensure_storage($dataDir);
meta_migrate_legacy_if_needed($dataDir);

function meta_oauth_json($payload, $status = 200) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function meta_oauth_page($title, $message, $type = 'info', $autoClose = false) {
    $color = $type === 'success' ? '#10B981' : ($type === 'error' ? '#EF4444' : '#3B82F6');
    $icon = $type === 'success' ? '✅' : ($type === 'error' ? '❌' : 'ℹ️');
    $closeScript = $autoClose ? "<script>
        if (window.opener) {
            window.opener.postMessage({ source: 'meta_oauth', status: '$type' }, '*');
        }
        setTimeout(function(){ window.close(); }, 1500);
    </script>" : "";
    $closeText = $autoClose ? "<p style='opacity:.7;margin-top:12px;'>Bu pencere kapanacak...</p>" : "";

    echo "<!doctype html>
<html lang='tr'>
<head>
  <meta charset='utf-8'>
  <meta name='viewport' content='width=device-width,initial-scale=1'>
  <title>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</title>
  <style>
    body{font-family:Arial,Helvetica,sans-serif;background:#0f172a;color:#f8fafc;margin:0;display:flex;justify-content:center;align-items:center;min-height:100vh}
    .card{max-width:560px;width:92%;background:#1e293b;border-radius:14px;padding:30px;box-shadow:0 16px 40px rgba(0,0,0,.35)}
    .icon{font-size:42px;margin-bottom:8px}
    .badge{display:inline-block;padding:6px 12px;border-radius:999px;background:$color;color:white;font-size:12px;margin-top:14px}
    p{line-height:1.6;opacity:.95}
  </style>
  $closeScript
</head>
<body>
  <div class='card'>
    <div class='icon'>$icon</div>
    <h2 style='margin:0 0 8px'>" . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . "</h2>
    <p>" . nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) . "</p>
    <span class='badge'>$type</span>
    $closeText
  </div>
</body>
</html>";
    exit;
}

function meta_oauth_build_state($appRef, $nonce) {
    return meta_base64url_encode(json_encode([
        'app_ref' => $appRef,
        'nonce' => $nonce,
        'issued_at' => time()
    ]));
}

function meta_oauth_parse_state($stateRaw) {
    $decoded = meta_base64url_decode($stateRaw);
    if (!$decoded) return null;
    $data = json_decode($decoded, true);
    return is_array($data) ? $data : null;
}

// OAuth callback
if (isset($_GET['code']) || isset($_GET['error'])) {
    $error = $_GET['error'] ?? null;
    if ($error) {
        $errorMessage = $_GET['error_description'] ?? $error;
        meta_oauth_page('Meta OAuth başarısız', $errorMessage, 'error');
    }

    $code = $_GET['code'] ?? '';
    $stateRaw = $_GET['state'] ?? '';
    if ($code === '' || $stateRaw === '') {
        meta_oauth_page('Meta OAuth hatası', 'Eksik code/state parametresi.', 'error');
    }

    $state = meta_oauth_parse_state($stateRaw);
    if (!$state) {
        meta_oauth_page('Meta OAuth hatası', 'State çözümlenemedi.', 'error');
    }

    $nonce = $state['nonce'] ?? '';
    $appRef = $state['app_ref'] ?? '';
    if ($nonce === '' || $appRef === '') {
        meta_oauth_page('Meta OAuth hatası', 'State içeriği geçersiz.', 'error');
    }

    $storedState = meta_consume_oauth_state($dataDir, $nonce, 900);
    if (!$storedState || (($storedState['app_ref'] ?? null) !== $appRef)) {
        meta_oauth_page('Meta OAuth hatası', 'State doğrulaması başarısız veya süre aşıldı.', 'error');
    }

    $appsData = meta_load_apps($dataDir);
    $app = meta_find_app($appsData, $appRef);
    if (!$app) {
        meta_oauth_page('Meta OAuth hatası', 'Seçilen Meta app bulunamadı.', 'error');
    }

    $short = meta_exchange_code_for_token($app, $code);
    if (!$short['success']) {
        meta_oauth_page('Token alınamadı', $short['error'] ?? 'Bilinmeyen hata', 'error');
    }

    $shortToken = $short['token']['access_token'] ?? null;
    if (!$shortToken) {
        meta_oauth_page('Token alınamadı', 'Short-lived token boş döndü.', 'error');
    }

    $long = meta_exchange_long_lived_token($app, $shortToken);
    $tokenData = $long['success'] ? ($long['token'] ?? []) : ($short['token'] ?? []);
    $accessToken = $tokenData['access_token'] ?? $shortToken;
    $expiresIn = intval($tokenData['expires_in'] ?? 3600);
    $expiresAt = gmdate('Y-m-d\TH:i:s\Z', time() + max($expiresIn, 300));

    $fetched = meta_fetch_accounts_for_token($accessToken);
    if (!$fetched['success']) {
        meta_oauth_page('Hesaplar alınamadı', $fetched['error'] ?? 'Meta hesap çekimi başarısız', 'error');
    }

    $ownerId = $fetched['owner']['id'] ?? null;
    $ownerName = $fetched['owner']['name'] ?? 'Meta User';

    $connectionsData = meta_load_connections($dataDir);
    $existingIndex = null;
    foreach ($connectionsData['connections'] as $idx => $connection) {
        if (($connection['app_ref'] ?? '') === $appRef && ($connection['owner_id'] ?? '') === $ownerId && $ownerId !== null) {
            $existingIndex = $idx;
            break;
        }
    }

    if ($existingIndex === null) {
        $connectionId = meta_random_id('meta_conn');
        $connection = [
            'id' => $connectionId,
            'app_ref' => $appRef,
            'app_label' => $app['label'] ?? null,
            'label' => ($ownerName ?: 'Meta Connection'),
            'owner_id' => $ownerId,
            'owner_name' => $ownerName,
            'access_token' => $accessToken,
            'token_type' => $tokenData['token_type'] ?? 'bearer',
            'expires_at' => $expiresAt,
            'created_at' => meta_now_iso(),
            'updated_at' => meta_now_iso(),
            'last_sync_at' => meta_now_iso(),
            'is_active' => true,
            'accounts' => $fetched['accounts']
        ];
        $connectionsData['connections'][] = $connection;
    } else {
        $connection = $connectionsData['connections'][$existingIndex];
        $connection['app_label'] = $app['label'] ?? ($connection['app_label'] ?? null);
        $connection['owner_id'] = $ownerId ?? ($connection['owner_id'] ?? null);
        $connection['owner_name'] = $ownerName ?? ($connection['owner_name'] ?? null);
        $connection['access_token'] = $accessToken;
        $connection['token_type'] = $tokenData['token_type'] ?? ($connection['token_type'] ?? 'bearer');
        $connection['expires_at'] = $expiresAt;
        $connection['updated_at'] = meta_now_iso();
        $connection['last_sync_at'] = meta_now_iso();
        $connection['is_active'] = true;
        $connection['accounts'] = $fetched['accounts'];
        $connectionsData['connections'][$existingIndex] = $connection;
        $connectionId = $connection['id'];
    }

    meta_save_connections($dataDir, $connectionsData);

    $appsData['active_app_id'] = $appRef;
    meta_save_apps($dataDir, $appsData);

    $settings = meta_load_settings($dataDir);
    $settings['active_connection_id'] = $connectionId;
    // OAuth ile bağlantı başarıyla kurulunca güvenli kesme için flag'i aç.
    $settings['feature_flags']['meta_web_ui_enabled'] = true;
    meta_save_settings($dataDir, $settings);

    // Optional config flag (cutover control)
    $configPath = $dataDir . '/config.json';
    $config = meta_read_json($configPath, []);
    $config['metaWebUiEnabled'] = true;
    meta_write_json($configPath, $config);

    $accounts = meta_rebuild_aggregated_accounts($dataDir, $connectionsData, $settings);
    $activeConnection = meta_select_active_connection($connectionsData, $settings);
    meta_write_legacy_compat($dataDir, $app, $activeConnection, $accounts);

    $igCount = count($fetched['accounts']['instagram'] ?? []);
    $fbCount = count($fetched['accounts']['facebook'] ?? []);
    $message = "Bağlantı başarıyla tamamlandı.\nInstagram: {$igCount} hesap\nFacebook: {$fbCount} sayfa";
    meta_oauth_page('Meta OAuth başarılı', $message, 'success', true);
}

$action = $_GET['action'] ?? 'start';
if ($action !== 'start') {
    meta_oauth_json(['success' => false, 'error' => 'Geçersiz action'], 400);
}

$appRef = trim((string)($_GET['app_id'] ?? $_GET['app_ref'] ?? ''));
$appsData = meta_load_apps($dataDir);

if ($appRef === '') {
    $active = meta_get_active_app($appsData);
    if ($active) {
        $appRef = $active['id'];
    }
}

if ($appRef === '') {
    meta_oauth_json(['success' => false, 'error' => 'OAuth başlatmak için önce Meta app tanımlayın.'], 400);
}

$app = meta_find_app($appsData, $appRef);
if (!$app) {
    meta_oauth_json(['success' => false, 'error' => 'Meta app bulunamadı'], 404);
}
if (empty($app['app_id']) || empty($app['app_secret'])) {
    meta_oauth_json(['success' => false, 'error' => 'App ID / App Secret eksik'], 400);
}

$nonce = bin2hex(random_bytes(12));
meta_store_oauth_state($dataDir, $nonce, [
    'app_ref' => $appRef,
    'created_at' => time()
]);

$state = meta_oauth_build_state($appRef, $nonce);
$params = [
    'client_id' => $app['app_id'],
    'redirect_uri' => $app['redirect_uri'] ?? meta_default_redirect_uri(),
    'scope' => implode(',', meta_permissions()),
    'response_type' => 'code',
    'state' => $state
];
$oauthUrl = meta_auth_dialog_url() . '?' . http_build_query($params);

meta_oauth_json([
    'success' => true,
    'oauth_url' => $oauthUrl,
    'app_id_ref' => $appRef
]);

