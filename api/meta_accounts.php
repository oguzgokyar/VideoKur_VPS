<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/meta_helpers.php';

$dataDir = __DIR__ . '/../data';
meta_ensure_storage($dataDir);
meta_migrate_legacy_if_needed($dataDir);

function meta_accounts_respond($payload, $status = 200) {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function meta_accounts_input() {
    $raw = file_get_contents('php://input');
    $decoded = json_decode($raw ?: '{}', true);
    return is_array($decoded) ? $decoded : [];
}

function meta_accounts_update_config_flag($dataDir, $enabled) {
    $configPath = $dataDir . '/config.json';
    $config = meta_read_json($configPath, []);
    $config['metaWebUiEnabled'] = (bool)$enabled;
    meta_write_json($configPath, $config);
}

function meta_accounts_build_dashboard($dataDir) {
    $appsData = meta_load_apps($dataDir);
    $connectionsData = meta_load_connections($dataDir);
    $settings = meta_load_settings($dataDir);
    $accounts = meta_rebuild_aggregated_accounts($dataDir, $connectionsData, $settings);

    $appsById = [];
    $apps = [];
    foreach ($appsData['apps'] as $app) {
        $appsById[$app['id']] = $app;
        $apps[] = meta_sanitize_app($app);
    }

    $connections = [];
    foreach ($connectionsData['connections'] as $connection) {
        $item = meta_sanitize_connection($connection);
        $item['app_label'] = $item['app_label'] ?: (($appsById[$item['app_ref']]['label'] ?? null));
        $connections[] = $item;
    }

    $activeApp = meta_get_active_app($appsData);

    return [
        'success' => true,
        'feature_flags' => [
            'meta_web_ui_enabled' => meta_is_web_ui_enabled($dataDir)
        ],
        'apps' => $apps,
        'active_app_id' => $appsData['active_app_id'] ?? null,
        'active_app' => $activeApp ? meta_sanitize_app($activeApp) : null,
        'connections' => $connections,
        'settings' => $settings,
        'accounts' => $accounts,
        'accounts_contract' => meta_normalize_social_accounts_contract([
            'instagram' => $accounts['instagram'] ?? [],
            'facebook' => $accounts['facebook'] ?? [],
            'youtube' => [],
            'tiktok' => []
        ]),
        'guidance' => [
            'permissions' => meta_permissions(),
            'oauth_callback_url' => meta_default_redirect_uri()
        ]
    ];
}

$input = meta_accounts_input();
$action = $input['action'] ?? ($_GET['action'] ?? 'dashboard');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($action) {
        case 'dashboard':
            meta_accounts_respond(meta_accounts_build_dashboard($dataDir));
            break;
        case 'list_apps':
            $appsData = meta_load_apps($dataDir);
            $apps = array_map('meta_sanitize_app', $appsData['apps'] ?? []);
            meta_accounts_respond(['success' => true, 'apps' => $apps, 'active_app_id' => $appsData['active_app_id'] ?? null]);
            break;
        case 'list_connections':
            $connectionsData = meta_load_connections($dataDir);
            $connections = array_map('meta_sanitize_connection', $connectionsData['connections'] ?? []);
            meta_accounts_respond(['success' => true, 'connections' => $connections]);
            break;
        case 'list_accounts':
            $connectionsData = meta_load_connections($dataDir);
            $settings = meta_load_settings($dataDir);
            $accounts = meta_rebuild_aggregated_accounts($dataDir, $connectionsData, $settings);
            meta_accounts_respond(['success' => true, 'accounts' => $accounts]);
            break;
        default:
            meta_accounts_respond(['success' => false, 'error' => 'Geçersiz action'], 400);
    }
}

switch ($action) {
    case 'save_app':
        $id = trim((string)($input['id'] ?? ''));
        $label = trim((string)($input['label'] ?? ''));
        $appId = trim((string)($input['app_id'] ?? ''));
        $appSecret = trim((string)($input['app_secret'] ?? ''));
        $redirectUri = trim((string)($input['redirect_uri'] ?? '')) ?: meta_default_redirect_uri();
        $setActive = !empty($input['set_active']);

        if ($label === '' || $appId === '') {
            meta_accounts_respond(['success' => false, 'error' => 'label ve app_id zorunlu'], 400);
        }

        $appsData = meta_load_apps($dataDir);
        $apps = $appsData['apps'] ?? [];
        $foundIndex = null;
        $existingSecret = null;

        foreach ($apps as $index => $app) {
            if (($app['id'] ?? '') === $id && $id !== '') {
                $foundIndex = $index;
                $existingSecret = $app['app_secret'] ?? null;
                break;
            }
        }

        if ($foundIndex === null && $appSecret === '') {
            meta_accounts_respond(['success' => false, 'error' => 'Yeni app için app_secret zorunlu'], 400);
        }

        if ($foundIndex !== null && $appSecret === '') {
            $appSecret = (string)$existingSecret;
        }

        $record = [
            'id' => $id !== '' ? $id : meta_random_id('meta_app'),
            'label' => $label,
            'app_id' => $appId,
            'app_secret' => $appSecret,
            'redirect_uri' => $redirectUri,
            'updated_at' => meta_now_iso()
        ];

        if ($foundIndex === null) {
            $record['created_at'] = meta_now_iso();
            $apps[] = $record;
        } else {
            $record['created_at'] = $apps[$foundIndex]['created_at'] ?? meta_now_iso();
            $apps[$foundIndex] = $record;
        }

        $appsData['apps'] = array_values($apps);
        if (($appsData['active_app_id'] ?? null) === null || $setActive) {
            $appsData['active_app_id'] = $record['id'];
        }
        meta_save_apps($dataDir, $appsData);

        // Legacy compatibility: keep active app in legacy config
        $activeApp = meta_get_active_app($appsData);
        $connectionsData = meta_load_connections($dataDir);
        $settings = meta_load_settings($dataDir);
        $activeConnection = meta_select_active_connection($connectionsData, $settings);
        $accounts = meta_read_json(meta_legacy_accounts_file($dataDir), ['instagram' => [], 'facebook' => []]);
        meta_write_legacy_compat($dataDir, $activeApp, $activeConnection, $accounts);

        meta_accounts_respond([
            'success' => true,
            'message' => 'Meta app kaydedildi',
            'app' => meta_sanitize_app($record),
            'active_app_id' => $appsData['active_app_id']
        ]);
        break;

    case 'delete_app':
        $appRef = trim((string)($input['app_id_ref'] ?? $input['id'] ?? ''));
        if ($appRef === '') {
            meta_accounts_respond(['success' => false, 'error' => 'app_id_ref gerekli'], 400);
        }

        $appsData = meta_load_apps($dataDir);
        $connectionsData = meta_load_connections($dataDir);
        foreach (($connectionsData['connections'] ?? []) as $connection) {
            if (($connection['app_ref'] ?? '') === $appRef && ($connection['is_active'] ?? true)) {
                meta_accounts_respond(['success' => false, 'error' => 'Bu app aktif bağlantılar tarafından kullanılıyor. Önce bağlantıları kaldırın.'], 400);
            }
        }

        $apps = array_values(array_filter($appsData['apps'] ?? [], function ($app) use ($appRef) {
            return ($app['id'] ?? '') !== $appRef;
        }));
        $appsData['apps'] = $apps;

        if (($appsData['active_app_id'] ?? null) === $appRef) {
            $appsData['active_app_id'] = $apps[0]['id'] ?? null;
        }
        meta_save_apps($dataDir, $appsData);

        meta_accounts_respond(['success' => true, 'message' => 'Meta app silindi', 'active_app_id' => $appsData['active_app_id']]);
        break;

    case 'set_active_app':
        $appRef = trim((string)($input['app_id_ref'] ?? $input['id'] ?? ''));
        if ($appRef === '') {
            meta_accounts_respond(['success' => false, 'error' => 'app_id_ref gerekli'], 400);
        }

        $appsData = meta_load_apps($dataDir);
        $app = meta_find_app($appsData, $appRef);
        if (!$app) {
            meta_accounts_respond(['success' => false, 'error' => 'Meta app bulunamadı'], 404);
        }

        $appsData['active_app_id'] = $appRef;
        meta_save_apps($dataDir, $appsData);

        $connectionsData = meta_load_connections($dataDir);
        $settings = meta_load_settings($dataDir);
        $activeConnection = meta_select_active_connection($connectionsData, $settings);
        $accounts = meta_read_json(meta_legacy_accounts_file($dataDir), ['instagram' => [], 'facebook' => []]);
        meta_write_legacy_compat($dataDir, $app, $activeConnection, $accounts);

        meta_accounts_respond(['success' => true, 'message' => 'Aktif Meta app güncellendi']);
        break;

    case 'refresh_connection':
        $connectionId = trim((string)($input['connection_id'] ?? ''));
        if ($connectionId === '') {
            meta_accounts_respond(['success' => false, 'error' => 'connection_id gerekli'], 400);
        }

        $connectionsData = meta_load_connections($dataDir);
        $updated = false;
        foreach ($connectionsData['connections'] as $index => $connection) {
            if (($connection['id'] ?? '') !== $connectionId) {
                continue;
            }
            $result = meta_refresh_connection_snapshot($connection);
            if (!$result['success']) {
                meta_accounts_respond(['success' => false, 'error' => $result['error'] ?? 'Connection yenilenemedi'], 400);
            }
            $connectionsData['connections'][$index] = $result['connection'];
            $updated = true;
            break;
        }

        if (!$updated) {
            meta_accounts_respond(['success' => false, 'error' => 'Connection bulunamadı'], 404);
        }

        meta_save_connections($dataDir, $connectionsData);
        $settings = meta_load_settings($dataDir);
        if (empty($settings['active_connection_id'])) {
            $settings['active_connection_id'] = $connectionId;
            meta_save_settings($dataDir, $settings);
        }

        $accounts = meta_rebuild_aggregated_accounts($dataDir, $connectionsData, $settings);
        $appsData = meta_load_apps($dataDir);
        $activeApp = meta_get_active_app($appsData);
        $activeConnection = meta_select_active_connection($connectionsData, $settings);
        meta_write_legacy_compat($dataDir, $activeApp, $activeConnection, $accounts);

        meta_accounts_respond([
            'success' => true,
            'message' => 'Connection yenilendi',
            'counts' => [
                'instagram' => count($accounts['instagram'] ?? []),
                'facebook' => count($accounts['facebook'] ?? [])
            ]
        ]);
        break;

    case 'refresh_all_connections':
        $connectionsData = meta_load_connections($dataDir);
        $refreshed = 0;
        $errors = [];
        foreach ($connectionsData['connections'] as $index => $connection) {
            if (($connection['is_active'] ?? true) === false) {
                continue;
            }
            if (empty($connection['access_token'])) {
                continue;
            }
            $result = meta_refresh_connection_snapshot($connection);
            if ($result['success']) {
                $connectionsData['connections'][$index] = $result['connection'];
                $refreshed++;
            } else {
                $errors[] = [
                    'connection_id' => $connection['id'] ?? null,
                    'error' => $result['error'] ?? 'Bilinmeyen hata'
                ];
            }
        }

        meta_save_connections($dataDir, $connectionsData);
        $settings = meta_load_settings($dataDir);
        $accounts = meta_rebuild_aggregated_accounts($dataDir, $connectionsData, $settings);
        $appsData = meta_load_apps($dataDir);
        $activeApp = meta_get_active_app($appsData);
        $activeConnection = meta_select_active_connection($connectionsData, $settings);
        meta_write_legacy_compat($dataDir, $activeApp, $activeConnection, $accounts);

        meta_accounts_respond([
            'success' => true,
            'message' => $refreshed . ' bağlantı yenilendi',
            'refreshed' => $refreshed,
            'errors' => $errors
        ]);
        break;

    case 'disconnect_connection':
        $connectionId = trim((string)($input['connection_id'] ?? ''));
        $hardDelete = !empty($input['hard_delete']);
        if ($connectionId === '') {
            meta_accounts_respond(['success' => false, 'error' => 'connection_id gerekli'], 400);
        }

        $connectionsData = meta_load_connections($dataDir);
        $found = false;
        $newConnections = [];
        foreach ($connectionsData['connections'] as $connection) {
            if (($connection['id'] ?? '') !== $connectionId) {
                $newConnections[] = $connection;
                continue;
            }
            $found = true;
            if ($hardDelete) {
                continue;
            }
            $connection['is_active'] = false;
            $connection['updated_at'] = meta_now_iso();
            $newConnections[] = $connection;
        }

        if (!$found) {
            meta_accounts_respond(['success' => false, 'error' => 'Connection bulunamadı'], 404);
        }

        $connectionsData['connections'] = $newConnections;
        meta_save_connections($dataDir, $connectionsData);

        $settings = meta_load_settings($dataDir);
        if (($settings['active_connection_id'] ?? null) === $connectionId) {
            $replacement = meta_select_active_connection($connectionsData, $settings);
            $settings['active_connection_id'] = $replacement['id'] ?? null;
            meta_save_settings($dataDir, $settings);
        }

        $accounts = meta_rebuild_aggregated_accounts($dataDir, $connectionsData, $settings);
        $appsData = meta_load_apps($dataDir);
        $activeApp = meta_get_active_app($appsData);
        $activeConnection = meta_select_active_connection($connectionsData, $settings);
        meta_write_legacy_compat($dataDir, $activeApp, $activeConnection, $accounts);

        meta_accounts_respond(['success' => true, 'message' => 'Connection bağlantısı kaldırıldı']);
        break;

    case 'set_active_connection':
        $connectionId = trim((string)($input['connection_id'] ?? ''));
        if ($connectionId === '') {
            meta_accounts_respond(['success' => false, 'error' => 'connection_id gerekli'], 400);
        }
        $connectionsData = meta_load_connections($dataDir);
        $exists = false;
        foreach ($connectionsData['connections'] as $connection) {
            if (($connection['id'] ?? '') === $connectionId && ($connection['is_active'] ?? true)) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
            meta_accounts_respond(['success' => false, 'error' => 'Aktif connection bulunamadı'], 404);
        }
        $settings = meta_load_settings($dataDir);
        $settings['active_connection_id'] = $connectionId;
        meta_save_settings($dataDir, $settings);
        meta_accounts_respond(['success' => true, 'message' => 'Aktif bağlantı güncellendi']);
        break;

    case 'set_defaults':
        $settings = meta_load_settings($dataDir);
        if (array_key_exists('instagram_account_id', $input)) {
            $settings['defaults']['instagram_account_id'] = $input['instagram_account_id'] ?: null;
        }
        if (array_key_exists('facebook_page_id', $input)) {
            $settings['defaults']['facebook_page_id'] = $input['facebook_page_id'] ?: null;
        }
        if (array_key_exists('active_connection_id', $input)) {
            $settings['active_connection_id'] = $input['active_connection_id'] ?: null;
        }
        meta_save_settings($dataDir, $settings);

        $connectionsData = meta_load_connections($dataDir);
        $accounts = meta_rebuild_aggregated_accounts($dataDir, $connectionsData, $settings);
        $appsData = meta_load_apps($dataDir);
        $activeApp = meta_get_active_app($appsData);
        $activeConnection = meta_select_active_connection($connectionsData, $settings);
        meta_write_legacy_compat($dataDir, $activeApp, $activeConnection, $accounts);

        meta_accounts_respond(['success' => true, 'message' => 'Varsayılan Meta hesap ayarları kaydedildi']);
        break;

    case 'update_account':
        $platform = strtolower(trim((string)($input['platform'] ?? '')));
        $accountId = trim((string)($input['account_id'] ?? ''));
        if (!in_array($platform, ['instagram', 'facebook'], true)) {
            meta_accounts_respond(['success' => false, 'error' => 'platform instagram veya facebook olmalı'], 400);
        }
        if ($accountId === '') {
            meta_accounts_respond(['success' => false, 'error' => 'account_id gerekli'], 400);
        }

        $settings = meta_load_settings($dataDir);
        if (!isset($settings['accounts'][$platform][$accountId])) {
            $settings['accounts'][$platform][$accountId] = [];
        }

        if (array_key_exists('is_active', $input)) {
            $settings['accounts'][$platform][$accountId]['is_active'] = (bool)$input['is_active'];
        }
        if (array_key_exists('label', $input)) {
            $label = trim((string)$input['label']);
            $settings['accounts'][$platform][$accountId]['label'] = $label !== '' ? $label : null;
        }

        meta_save_settings($dataDir, $settings);
        $connectionsData = meta_load_connections($dataDir);
        $accounts = meta_rebuild_aggregated_accounts($dataDir, $connectionsData, $settings);
        $appsData = meta_load_apps($dataDir);
        $activeApp = meta_get_active_app($appsData);
        $activeConnection = meta_select_active_connection($connectionsData, $settings);
        meta_write_legacy_compat($dataDir, $activeApp, $activeConnection, $accounts);

        meta_accounts_respond(['success' => true, 'message' => 'Hesap ayarları güncellendi']);
        break;

    case 'set_feature_flag':
        $enabled = !empty($input['meta_web_ui_enabled']);
        $settings = meta_load_settings($dataDir);
        $settings['feature_flags']['meta_web_ui_enabled'] = $enabled;
        meta_save_settings($dataDir, $settings);
        meta_accounts_update_config_flag($dataDir, $enabled);
        meta_accounts_respond(['success' => true, 'message' => 'Feature flag güncellendi', 'meta_web_ui_enabled' => $enabled]);
        break;

    default:
        meta_accounts_respond(['success' => false, 'error' => 'Geçersiz action'], 400);
}

