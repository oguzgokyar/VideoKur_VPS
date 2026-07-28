<?php
declare(strict_types=1);

const VIDEOKUR_SESSION_NAME = 'videokur_session';
const VIDEOKUR_SESSION_IDLE_TIMEOUT = 1800;
const VIDEOKUR_SESSION_ABSOLUTE_TIMEOUT = 43200;
const VIDEOKUR_LOGIN_WINDOW = 900;
const VIDEOKUR_LOGIN_MAX_ATTEMPTS = 5;

function videokur_is_https(): bool
{
    return (($_SERVER['HTTPS'] ?? '') === 'on')
        || strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function videokur_data_dir(): string
{
    return rtrim((string)(getenv('DATA_DIR') ?: dirname(__DIR__) . '/data'), '/\\');
}

function videokur_request_path(): string
{
    $path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    return is_string($path) && $path !== '' ? $path : '/';
}

function videokur_is_api_request(): bool
{
    return str_starts_with(videokur_request_path(), '/api/');
}

function videokur_public_path(): bool
{
    return in_array(videokur_request_path(), ['/login.php', '/api/health.php'], true);
}

function videokur_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_name(VIDEOKUR_SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => videokur_is_https(),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.sid_length', '48');
    ini_set('session.sid_bits_per_character', '6');
    session_start();
}

function videokur_csrf_token(): string
{
    videokur_start_session();
    if (!isset($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function videokur_csrf_valid(?string $token): bool
{
    return is_string($token) && $token !== '' && hash_equals(videokur_csrf_token(), $token);
}

function videokur_auth_file(): string
{
    return videokur_data_dir() . '/auth/users.json';
}

function videokur_read_users(): array
{
    $path = videokur_auth_file();
    if (!is_file($path) || !is_readable($path)) {
        return [];
    }
    $decoded = json_decode((string)file_get_contents($path), true);
    return is_array($decoded['users'] ?? null) ? $decoded['users'] : [];
}

function videokur_find_user(string $username): ?array
{
    foreach (videokur_read_users() as $user) {
        if (is_array($user)
            && isset($user['username'], $user['password_hash'])
            && hash_equals(strtolower((string)$user['username']), strtolower($username))
            && ($user['active'] ?? true) === true) {
            return $user;
        }
    }
    return null;
}

function videokur_attempts_file(): string
{
    return videokur_data_dir() . '/auth/login_attempts.json';
}

function videokur_client_key(string $username): string
{
    return hash('sha256', (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '|' . strtolower($username));
}

function videokur_with_attempt_store(callable $callback): mixed
{
    $directory = dirname(videokur_attempts_file());
    if (!is_dir($directory)) {
        mkdir($directory, 0700, true);
    }
    $handle = fopen(videokur_attempts_file(), 'c+');
    if ($handle === false) {
        return $callback([])['value'] ?? null;
    }
    try {
        flock($handle, LOCK_EX);
        $store = json_decode((string)stream_get_contents($handle), true);
        $store = is_array($store) ? $store : [];
        $result = $callback($store);
        $updated = is_array($result['store'] ?? null) ? $result['store'] : $store;
        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($updated, JSON_UNESCAPED_SLASHES));
        fflush($handle);
        flock($handle, LOCK_UN);
        return $result['value'] ?? null;
    } finally {
        fclose($handle);
    }
}

function videokur_login_retry_after(string $username): int
{
    $key = videokur_client_key($username);
    $now = time();
    return (int)videokur_with_attempt_store(function (array $store) use ($key, $now): array {
        foreach ($store as $storedKey => $entry) {
            if (!is_array($entry) || ($entry['first_at'] ?? 0) < $now - VIDEOKUR_LOGIN_WINDOW) {
                unset($store[$storedKey]);
            }
        }
        $entry = $store[$key] ?? null;
        $blocked = is_array($entry) && (int)($entry['count'] ?? 0) >= VIDEOKUR_LOGIN_MAX_ATTEMPTS;
        $retryAfter = $blocked ? max(1, VIDEOKUR_LOGIN_WINDOW - ($now - (int)$entry['first_at'])) : 0;
        return ['store' => $store, 'value' => $retryAfter];
    });
}

function videokur_record_failed_login(string $username): void
{
    $key = videokur_client_key($username);
    $now = time();
    videokur_with_attempt_store(function (array $store) use ($key, $now): array {
        $entry = $store[$key] ?? ['count' => 0, 'first_at' => $now];
        if (!is_array($entry) || (int)($entry['first_at'] ?? 0) < $now - VIDEOKUR_LOGIN_WINDOW) {
            $entry = ['count' => 0, 'first_at' => $now];
        }
        $entry['count'] = (int)$entry['count'] + 1;
        $entry['last_at'] = $now;
        $store[$key] = $entry;
        return ['store' => $store, 'value' => null];
    });
}

function videokur_clear_failed_logins(string $username): void
{
    $key = videokur_client_key($username);
    videokur_with_attempt_store(function (array $store) use ($key): array {
        unset($store[$key]);
        return ['store' => $store, 'value' => null];
    });
}

function videokur_login(string $username, string $password): bool
{
    if (videokur_login_retry_after($username) > 0) {
        return false;
    }
    $user = videokur_find_user($username);
    if ($user === null || !password_verify($password, (string)$user['password_hash'])) {
        if ($user === null) {
            password_verify($password, '$2y$12$F9JHn6xPWqN8W4ol3B2IneZ2KkJd/wjBljxe4l1EMHjBf1UQx81oK');
        }
        videokur_record_failed_login($username);
        return false;
    }
    videokur_clear_failed_logins($username);
    session_regenerate_id(true);
    $_SESSION['user'] = ['username' => (string)$user['username'], 'role' => (string)($user['role'] ?? 'admin')];
    $_SESSION['authenticated_at'] = time();
    $_SESSION['last_activity_at'] = time();
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return true;
}

function videokur_logout(): void
{
    videokur_start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => (bool)$params['secure'],
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
    }
    session_destroy();
}

function videokur_current_user(): ?array
{
    videokur_start_session();
    $user = $_SESSION['user'] ?? null;
    if (!is_array($user)) {
        return null;
    }
    $now = time();
    $authenticatedAt = (int)($_SESSION['authenticated_at'] ?? 0);
    $lastActivityAt = (int)($_SESSION['last_activity_at'] ?? 0);
    if ($authenticatedAt <= 0 || $lastActivityAt <= 0
        || $now - $lastActivityAt > VIDEOKUR_SESSION_IDLE_TIMEOUT
        || $now - $authenticatedAt > VIDEOKUR_SESSION_ABSOLUTE_TIMEOUT) {
        videokur_logout();
        return null;
    }
    $_SESSION['last_activity_at'] = $now;
    return $user;
}

function videokur_reject_unauthenticated(): never
{
    if (videokur_is_api_request()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode(['success' => false, 'error' => 'Oturum gerekli']);
        exit;
    }
    $returnTo = videokur_request_path();
    $query = (string)($_SERVER['QUERY_STRING'] ?? '');
    if ($query !== '') {
        $returnTo .= '?' . $query;
    }
    header('Location: /login.php?return_to=' . rawurlencode($returnTo), true, 302);
    exit;
}

function videokur_bootstrap_request(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    videokur_start_session();
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    if (videokur_public_path()) {
        return;
    }
    if (videokur_current_user() === null) {
        videokur_reject_unauthenticated();
    }
    $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if (videokur_is_api_request() && !in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)
        && !videokur_csrf_valid((string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Geçersiz güvenlik belirteci']);
        exit;
    }
}

videokur_bootstrap_request();
