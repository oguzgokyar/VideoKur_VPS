<?php

function meta_now_iso() {
    return gmdate('Y-m-d\TH:i:s\Z');
}

function meta_meta_dir($dataDir) {
    return $dataDir . '/social_credentials/meta';
}

function meta_apps_file($dataDir) {
    return meta_meta_dir($dataDir) . '/meta_apps.json';
}

function meta_connections_file($dataDir) {
    return meta_meta_dir($dataDir) . '/meta_connections.json';
}

function meta_settings_file($dataDir) {
    return meta_meta_dir($dataDir) . '/meta_settings.json';
}

function meta_oauth_state_file($dataDir) {
    return meta_meta_dir($dataDir) . '/oauth_state.json';
}

function meta_legacy_config_file($dataDir) {
    return meta_meta_dir($dataDir) . '/meta_config.json';
}

function meta_legacy_token_file($dataDir) {
    return meta_meta_dir($dataDir) . '/meta_token.json';
}

function meta_legacy_accounts_file($dataDir) {
    return meta_meta_dir($dataDir) . '/meta_accounts.json';
}

function meta_ensure_storage($dataDir) {
    $metaDir = meta_meta_dir($dataDir);
    if (!is_dir($metaDir)) {
        mkdir($metaDir, 0777, true);
    }
}

function meta_read_json($file, $default = []) {
    if (!file_exists($file)) {
        return $default;
    }

    $raw = file_get_contents($file);
    if ($raw === false || $raw === '') {
        return $default;
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return $default;
    }
    return $data;
}

function meta_write_json($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function meta_random_id($prefix = 'meta') {
    return $prefix . '_' . bin2hex(random_bytes(6));
}

function meta_base64url_encode($value) {
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function meta_base64url_decode($value) {
    $padding = strlen($value) % 4;
    if ($padding > 0) {
        $value .= str_repeat('=', 4 - $padding);
    }
    return base64_decode(strtr($value, '-_', '+/'));
}

function meta_permissions() {
    return [
        'instagram_basic',
        'instagram_content_publish',
        'pages_show_list',
        'pages_read_engagement',
        'business_management',
        'publish_video',
        'pages_manage_posts'
    ];
}

function meta_default_redirect_uri() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    return $protocol . '://' . $host . '/api/meta_oauth.php';
}

function meta_mask_text($value, $keepStart = 4, $keepEnd = 4) {
    $value = (string)$value;
    if ($value === '') return '';
    if (strlen($value) <= ($keepStart + $keepEnd + 2)) {
        return str_repeat('*', strlen($value));
    }
    return substr($value, 0, $keepStart) . str_repeat('*', strlen($value) - $keepStart - $keepEnd) . substr($value, -$keepEnd);
}

function meta_default_apps_data() {
    return [
        'active_app_id' => null,
        'apps' => []
    ];
}

function meta_default_connections_data() {
    return [
        'connections' => []
    ];
}

function meta_default_settings_data() {
    return [
        'active_connection_id' => null,
        'defaults' => [
            'instagram_account_id' => null,
            'facebook_page_id' => null
        ],
        'accounts' => [
            'instagram' => [],
            'facebook' => []
        ],
        'feature_flags' => [
            'meta_web_ui_enabled' => false
        ]
    ];
}

function meta_load_apps($dataDir) {
    meta_ensure_storage($dataDir);
    $data = meta_read_json(meta_apps_file($dataDir), meta_default_apps_data());
    if (!isset($data['apps']) || !is_array($data['apps'])) {
        $data['apps'] = [];
    }
    if (!array_key_exists('active_app_id', $data)) {
        $data['active_app_id'] = null;
    }

    if (empty($data['apps'])) {
        $legacy = meta_read_json(meta_legacy_config_file($dataDir), []);
        if (!empty($legacy['app_id']) && !empty($legacy['app_secret'])) {
            $legacyId = 'legacy_app';
            $data['apps'][] = [
                'id' => $legacyId,
                'label' => 'Legacy Meta App',
                'app_id' => (string)$legacy['app_id'],
                'app_secret' => (string)$legacy['app_secret'],
                'redirect_uri' => $legacy['redirect_uri'] ?? meta_default_redirect_uri(),
                'created_at' => meta_now_iso(),
                'updated_at' => meta_now_iso()
            ];
            $data['active_app_id'] = $legacyId;
        }
    }

    return $data;
}

function meta_save_apps($dataDir, $data) {
    if (!isset($data['apps']) || !is_array($data['apps'])) {
        $data['apps'] = [];
    }
    if (!array_key_exists('active_app_id', $data)) {
        $data['active_app_id'] = null;
    }
    meta_write_json(meta_apps_file($dataDir), $data);
}

function meta_find_app($appsData, $appRef) {
    foreach ($appsData['apps'] ?? [] as $app) {
        if (($app['id'] ?? '') === $appRef) {
            return $app;
        }
    }
    return null;
}

function meta_get_active_app($appsData) {
    $activeId = $appsData['active_app_id'] ?? null;
    if ($activeId) {
        $active = meta_find_app($appsData, $activeId);
        if ($active) return $active;
    }
    return ($appsData['apps'][0] ?? null);
}

function meta_sanitize_app($app) {
    return [
        'id' => $app['id'] ?? null,
        'label' => $app['label'] ?? '',
        'app_id' => $app['app_id'] ?? '',
        'app_id_masked' => meta_mask_text($app['app_id'] ?? ''),
        'redirect_uri' => $app['redirect_uri'] ?? meta_default_redirect_uri(),
        'created_at' => $app['created_at'] ?? null,
        'updated_at' => $app['updated_at'] ?? null
    ];
}

function meta_load_connections($dataDir) {
    meta_ensure_storage($dataDir);
    $data = meta_read_json(meta_connections_file($dataDir), meta_default_connections_data());
    if (!isset($data['connections']) || !is_array($data['connections'])) {
        $data['connections'] = [];
    }
    return $data;
}

function meta_save_connections($dataDir, $data) {
    if (!isset($data['connections']) || !is_array($data['connections'])) {
        $data['connections'] = [];
    }
    meta_write_json(meta_connections_file($dataDir), $data);
}

function meta_load_settings($dataDir) {
    meta_ensure_storage($dataDir);
    $settings = meta_read_json(meta_settings_file($dataDir), meta_default_settings_data());
    $defaults = meta_default_settings_data();

    if (!isset($settings['defaults']) || !is_array($settings['defaults'])) {
        $settings['defaults'] = $defaults['defaults'];
    } else {
        $settings['defaults'] = array_merge($defaults['defaults'], $settings['defaults']);
    }

    if (!isset($settings['accounts']) || !is_array($settings['accounts'])) {
        $settings['accounts'] = $defaults['accounts'];
    } else {
        if (!isset($settings['accounts']['instagram']) || !is_array($settings['accounts']['instagram'])) {
            $settings['accounts']['instagram'] = [];
        }
        if (!isset($settings['accounts']['facebook']) || !is_array($settings['accounts']['facebook'])) {
            $settings['accounts']['facebook'] = [];
        }
    }

    if (!array_key_exists('active_connection_id', $settings)) {
        $settings['active_connection_id'] = null;
    }

    if (!isset($settings['feature_flags']) || !is_array($settings['feature_flags'])) {
        $settings['feature_flags'] = $defaults['feature_flags'];
    } else {
        $settings['feature_flags'] = array_merge($defaults['feature_flags'], $settings['feature_flags']);
    }

    return $settings;
}

function meta_save_settings($dataDir, $settings) {
    $defaults = meta_default_settings_data();
    if (!isset($settings['defaults']) || !is_array($settings['defaults'])) {
        $settings['defaults'] = $defaults['defaults'];
    }
    if (!isset($settings['accounts']) || !is_array($settings['accounts'])) {
        $settings['accounts'] = $defaults['accounts'];
    }
    if (!isset($settings['feature_flags']) || !is_array($settings['feature_flags'])) {
        $settings['feature_flags'] = $defaults['feature_flags'];
    }
    if (!array_key_exists('active_connection_id', $settings)) {
        $settings['active_connection_id'] = null;
    }
    meta_write_json(meta_settings_file($dataDir), $settings);
}

function meta_store_oauth_state($dataDir, $nonce, $payload) {
    $store = meta_read_json(meta_oauth_state_file($dataDir), ['states' => []]);
    if (!isset($store['states']) || !is_array($store['states'])) {
        $store['states'] = [];
    }

    $now = time();
    foreach ($store['states'] as $key => $state) {
        if (($state['created_at'] ?? 0) < ($now - 3600)) {
            unset($store['states'][$key]);
        }
    }

    $store['states'][$nonce] = [
        'created_at' => $now,
        'payload' => $payload
    ];
    meta_write_json(meta_oauth_state_file($dataDir), $store);
}

function meta_consume_oauth_state($dataDir, $nonce, $ttlSeconds = 900) {
    $store = meta_read_json(meta_oauth_state_file($dataDir), ['states' => []]);
    $entry = $store['states'][$nonce] ?? null;
    if (!is_array($entry)) {
        return null;
    }

    unset($store['states'][$nonce]);
    meta_write_json(meta_oauth_state_file($dataDir), $store);

    if (($entry['created_at'] ?? 0) < (time() - $ttlSeconds)) {
        return null;
    }
    return $entry['payload'] ?? null;
}

function meta_http_request($url, $method = 'GET', $params = [], $headers = [], $timeout = 40) {
    $method = strtoupper($method);
    if ($method === 'GET' && !empty($params)) {
        $query = http_build_query($params);
        $url .= (strpos($url, '?') === false ? '?' : '&') . $query;
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $response = curl_exec($ch);
    $curlErr = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false) {
        return ['success' => false, 'status' => $status, 'error' => $curlErr ?: 'HTTP request failed'];
    }

    $json = json_decode($response, true);
    return [
        'success' => true,
        'status' => $status,
        'json' => is_array($json) ? $json : null,
        'raw' => $response
    ];
}

function meta_graph_base_url() {
    return 'https://graph.facebook.com/v18.0';
}

function meta_auth_dialog_url() {
    return 'https://www.facebook.com/v18.0/dialog/oauth';
}

function meta_token_url() {
    return 'https://graph.facebook.com/v18.0/oauth/access_token';
}

function meta_exchange_code_for_token($app, $code) {
    $response = meta_http_request(meta_token_url(), 'GET', [
        'client_id' => $app['app_id'],
        'client_secret' => $app['app_secret'],
        'redirect_uri' => $app['redirect_uri'] ?? meta_default_redirect_uri(),
        'code' => $code
    ]);

    if (!$response['success']) {
        return ['success' => false, 'error' => $response['error'] ?? 'Token request failed'];
    }

    $data = $response['json'] ?? [];
    if (isset($data['error'])) {
        $err = $data['error']['message'] ?? json_encode($data['error']);
        return ['success' => false, 'error' => $err];
    }
    if (empty($data['access_token'])) {
        return ['success' => false, 'error' => 'access_token dönmedi'];
    }

    return ['success' => true, 'token' => $data];
}

function meta_exchange_long_lived_token($app, $shortToken) {
    $response = meta_http_request(meta_token_url(), 'GET', [
        'grant_type' => 'fb_exchange_token',
        'client_id' => $app['app_id'],
        'client_secret' => $app['app_secret'],
        'fb_exchange_token' => $shortToken
    ]);

    if (!$response['success']) {
        return ['success' => false, 'error' => $response['error'] ?? 'Long-lived token request failed'];
    }

    $data = $response['json'] ?? [];
    if (isset($data['error'])) {
        $err = $data['error']['message'] ?? json_encode($data['error']);
        return ['success' => false, 'error' => $err];
    }
    if (empty($data['access_token'])) {
        return ['success' => false, 'error' => 'long-lived access_token dönmedi'];
    }

    return ['success' => true, 'token' => $data];
}

function meta_fetch_account_owner($accessToken) {
    $response = meta_http_request(meta_graph_base_url() . '/me', 'GET', [
        'access_token' => $accessToken,
        'fields' => 'id,name'
    ]);

    if (!$response['success']) {
        return ['success' => false, 'error' => $response['error'] ?? 'Kullanıcı bilgisi alınamadı'];
    }

    $data = $response['json'] ?? [];
    if (isset($data['error'])) {
        $err = $data['error']['message'] ?? json_encode($data['error']);
        return ['success' => false, 'error' => $err];
    }

    return [
        'success' => true,
        'owner' => [
            'id' => $data['id'] ?? null,
            'name' => $data['name'] ?? null
        ]
    ];
}

function meta_fetch_accounts_for_token($accessToken) {
    $ownerResult = meta_fetch_account_owner($accessToken);
    if (!$ownerResult['success']) {
        return $ownerResult;
    }

    $response = meta_http_request(meta_graph_base_url() . '/me/accounts', 'GET', [
        'access_token' => $accessToken,
        'fields' => 'id,name,access_token,instagram_business_account'
    ]);

    if (!$response['success']) {
        return ['success' => false, 'error' => $response['error'] ?? 'Sayfalar alınamadı'];
    }

    $data = $response['json'] ?? [];
    if (isset($data['error'])) {
        $err = $data['error']['message'] ?? json_encode($data['error']);
        return ['success' => false, 'error' => $err];
    }

    $facebook = [];
    $instagram = [];
    $igSeen = [];
    $pages = $data['data'] ?? [];

    foreach ($pages as $page) {
        $pageId = $page['id'] ?? null;
        if (!$pageId) continue;

        $facebook[] = [
            'id' => $pageId,
            'name' => $page['name'] ?? ('Page ' . $pageId),
            'type' => 'page',
            'access_token' => $page['access_token'] ?? null
        ];

        $igRef = $page['instagram_business_account']['id'] ?? null;
        if (!$igRef || isset($igSeen[$igRef])) {
            continue;
        }

        $igResp = meta_http_request(meta_graph_base_url() . '/' . $igRef, 'GET', [
            'access_token' => $accessToken,
            'fields' => 'id,username,name,profile_picture_url,followers_count'
        ]);

        $igData = $igResp['json'] ?? [];
        if (!$igResp['success'] || isset($igData['error'])) {
            continue;
        }

        $instagram[] = [
            'id' => $igData['id'] ?? $igRef,
            'username' => $igData['username'] ?? null,
            'name' => $igData['name'] ?? null,
            'profile_picture' => $igData['profile_picture_url'] ?? null,
            'followers' => $igData['followers_count'] ?? null,
            'page_id' => $pageId,
            'page_access_token' => $page['access_token'] ?? null
        ];
        $igSeen[$igRef] = true;
    }

    return [
        'success' => true,
        'owner' => $ownerResult['owner'],
        'accounts' => [
            'instagram' => $instagram,
            'facebook' => $facebook
        ]
    ];
}

function meta_sanitize_connection($connection) {
    $instagramCount = count($connection['accounts']['instagram'] ?? []);
    $facebookCount = count($connection['accounts']['facebook'] ?? []);
    return [
        'id' => $connection['id'] ?? null,
        'app_ref' => $connection['app_ref'] ?? null,
        'app_label' => $connection['app_label'] ?? null,
        'owner_id' => $connection['owner_id'] ?? null,
        'owner_name' => $connection['owner_name'] ?? null,
        'label' => $connection['label'] ?? null,
        'is_active' => ($connection['is_active'] ?? true) ? true : false,
        'created_at' => $connection['created_at'] ?? null,
        'updated_at' => $connection['updated_at'] ?? null,
        'last_sync_at' => $connection['last_sync_at'] ?? null,
        'expires_at' => $connection['expires_at'] ?? null,
        'instagram_count' => $instagramCount,
        'facebook_count' => $facebookCount
    ];
}

function meta_merge_account_setting($settings, $platform, $accountId) {
    $platformSettings = $settings['accounts'][$platform] ?? [];
    if (!is_array($platformSettings)) return [];
    return $platformSettings[$accountId] ?? [];
}

function meta_rebuild_aggregated_accounts($dataDir, $connectionsData = null, $settings = null) {
    if ($connectionsData === null) {
        $connectionsData = meta_load_connections($dataDir);
    }
    if ($settings === null) {
        $settings = meta_load_settings($dataDir);
    }

    $accounts = ['instagram' => [], 'facebook' => []];
    $seen = ['instagram' => [], 'facebook' => []];

    foreach ($connectionsData['connections'] ?? [] as $connection) {
        if (($connection['is_active'] ?? true) === false) {
            continue;
        }

        $connectionId = $connection['id'] ?? null;
        $connectionLabel = $connection['label'] ?? ($connection['owner_name'] ?? $connectionId);

        foreach (($connection['accounts']['instagram'] ?? []) as $ig) {
            $id = $ig['id'] ?? null;
            if (!$id || isset($seen['instagram'][$id])) continue;
            $setting = meta_merge_account_setting($settings, 'instagram', $id);
            $accounts['instagram'][] = [
                'id' => $id,
                'username' => $ig['username'] ?? null,
                'name' => $ig['name'] ?? ($ig['username'] ?? null),
                'profile_picture' => $ig['profile_picture'] ?? null,
                'followers' => $ig['followers'] ?? ($ig['followers_count'] ?? null),
                'page_id' => $ig['page_id'] ?? null,
                'page_access_token' => $ig['page_access_token'] ?? null,
                'connection_id' => $connectionId,
                'connection_label' => $connectionLabel,
                'is_active' => array_key_exists('is_active', $setting) ? (bool)$setting['is_active'] : true,
                'is_default' => (($settings['defaults']['instagram_account_id'] ?? null) === $id),
                'label' => $setting['label'] ?? null,
                'updated_at' => meta_now_iso()
            ];
            $seen['instagram'][$id] = true;
        }

        foreach (($connection['accounts']['facebook'] ?? []) as $fb) {
            $id = $fb['id'] ?? null;
            if (!$id || isset($seen['facebook'][$id])) continue;
            $setting = meta_merge_account_setting($settings, 'facebook', $id);
            $accounts['facebook'][] = [
                'id' => $id,
                'name' => $fb['name'] ?? ('Page ' . $id),
                'type' => $fb['type'] ?? 'page',
                'access_token' => $fb['access_token'] ?? null,
                'connection_id' => $connectionId,
                'connection_label' => $connectionLabel,
                'is_active' => array_key_exists('is_active', $setting) ? (bool)$setting['is_active'] : true,
                'is_default' => (($settings['defaults']['facebook_page_id'] ?? null) === $id),
                'label' => $setting['label'] ?? null,
                'updated_at' => meta_now_iso()
            ];
            $seen['facebook'][$id] = true;
        }
    }

    meta_write_json(meta_legacy_accounts_file($dataDir), $accounts);
    return $accounts;
}

function meta_normalize_social_accounts_contract($accounts) {
    $normalized = [
        'youtube' => $accounts['youtube'] ?? [],
        'tiktok' => $accounts['tiktok'] ?? [],
        'instagram' => [],
        'facebook' => []
    ];

    foreach ($accounts['instagram'] ?? [] as $ig) {
        $id = $ig['id'] ?? ($ig['account_id'] ?? null);
        if (!$id) continue;
        $normalized['instagram'][] = [
            'id' => $id,
            'username' => $ig['username'] ?? null,
            'name' => $ig['name'] ?? ($ig['username'] ?? null),
            'followers' => $ig['followers'] ?? ($ig['followers_count'] ?? null),
            'page_id' => $ig['page_id'] ?? null
        ];
    }

    foreach ($accounts['facebook'] ?? [] as $fb) {
        $id = $fb['id'] ?? ($fb['account_id'] ?? null);
        if (!$id) continue;
        $normalized['facebook'][] = [
            'id' => $id,
            'name' => $fb['name'] ?? ($fb['username'] ?? null),
            'type' => $fb['type'] ?? 'page'
        ];
    }

    return $normalized;
}

function meta_select_active_connection($connectionsData, $settings) {
    $activeId = $settings['active_connection_id'] ?? null;
    if ($activeId) {
        foreach ($connectionsData['connections'] ?? [] as $connection) {
            if (($connection['id'] ?? '') === $activeId && ($connection['is_active'] ?? true)) {
                return $connection;
            }
        }
    }

    foreach ($connectionsData['connections'] ?? [] as $connection) {
        if (($connection['is_active'] ?? true)) {
            return $connection;
        }
    }

    return null;
}

function meta_write_legacy_compat($dataDir, $activeApp, $activeConnection, $accounts) {
    if (is_array($accounts)) {
        meta_write_json(meta_legacy_accounts_file($dataDir), [
            'instagram' => $accounts['instagram'] ?? [],
            'facebook' => $accounts['facebook'] ?? []
        ]);
    }

    if (is_array($activeApp) && !empty($activeApp['app_id']) && !empty($activeApp['app_secret'])) {
        meta_write_json(meta_legacy_config_file($dataDir), [
            'app_id' => $activeApp['app_id'],
            'app_secret' => $activeApp['app_secret'],
            'redirect_uri' => $activeApp['redirect_uri'] ?? meta_default_redirect_uri()
        ]);
    }

    if (is_array($activeConnection) && !empty($activeConnection['access_token'])) {
        meta_write_json(meta_legacy_token_file($dataDir), [
            'access_token' => $activeConnection['access_token'],
            'token_type' => $activeConnection['token_type'] ?? 'bearer',
            'expires_at' => $activeConnection['expires_at'] ?? null,
            'updated_at' => meta_now_iso()
        ]);
    }
}

function meta_refresh_connection_snapshot($connection) {
    $token = $connection['access_token'] ?? null;
    if (!$token) {
        return ['success' => false, 'error' => 'Connection access token bulunamadı'];
    }

    $fetched = meta_fetch_accounts_for_token($token);
    if (!$fetched['success']) {
        return $fetched;
    }

    $connection['owner_id'] = $fetched['owner']['id'] ?? ($connection['owner_id'] ?? null);
    $connection['owner_name'] = $fetched['owner']['name'] ?? ($connection['owner_name'] ?? null);
    $connection['accounts'] = $fetched['accounts'];
    $connection['last_sync_at'] = meta_now_iso();
    $connection['updated_at'] = meta_now_iso();

    return ['success' => true, 'connection' => $connection, 'accounts' => $fetched['accounts']];
}

function meta_migrate_legacy_if_needed($dataDir) {
    $connectionsFile = meta_connections_file($dataDir);
    if (file_exists($connectionsFile)) {
        return;
    }

    $legacyToken = meta_read_json(meta_legacy_token_file($dataDir), []);
    $legacyAccounts = meta_read_json(meta_legacy_accounts_file($dataDir), ['instagram' => [], 'facebook' => []]);

    $hasToken = !empty($legacyToken['access_token']);
    $hasAccounts = !empty($legacyAccounts['instagram']) || !empty($legacyAccounts['facebook']);
    if (!$hasToken && !$hasAccounts) {
        return;
    }

    $connection = [
        'id' => 'legacy_connection',
        'app_ref' => 'legacy_app',
        'app_label' => 'Legacy Meta App',
        'label' => 'Legacy Connection',
        'owner_id' => null,
        'owner_name' => 'Legacy',
        'access_token' => $legacyToken['access_token'] ?? null,
        'token_type' => $legacyToken['token_type'] ?? 'bearer',
        'expires_at' => $legacyToken['expires_at'] ?? null,
        'created_at' => meta_now_iso(),
        'updated_at' => meta_now_iso(),
        'last_sync_at' => null,
        'is_active' => true,
        'accounts' => [
            'instagram' => $legacyAccounts['instagram'] ?? [],
            'facebook' => $legacyAccounts['facebook'] ?? []
        ]
    ];

    meta_save_connections($dataDir, ['connections' => [$connection]]);

    $settings = meta_load_settings($dataDir);
    if (empty($settings['active_connection_id'])) {
        $settings['active_connection_id'] = $connection['id'];
    }
    meta_save_settings($dataDir, $settings);
}

function meta_get_config($dataDir) {
    $configPath = $dataDir . '/config.json';
    return meta_read_json($configPath, []);
}

function meta_is_web_ui_enabled($dataDir) {
    $config = meta_get_config($dataDir);
    if (array_key_exists('metaWebUiEnabled', $config)) {
        return (bool)$config['metaWebUiEnabled'];
    }
    $settings = meta_load_settings($dataDir);
    return (bool)($settings['feature_flags']['meta_web_ui_enabled'] ?? false);
}

