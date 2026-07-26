<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function respond(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function envFlag(string $name, bool $default = false): bool
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        return $default;
    }

    return filter_var($value, FILTER_VALIDATE_BOOLEAN);
}

function readJsonFile(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents($path), true);
    return is_array($decoded) ? $decoded : null;
}

function writeJsonFile(string $path, array $payload): bool
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
        return false;
    }

    $temporary = $path . '.tmp.' . bin2hex(random_bytes(4));
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($json === false || file_put_contents($temporary, $json . PHP_EOL, LOCK_EX) === false) {
        @unlink($temporary);
        return false;
    }

    @chmod($temporary, 0664);
    if (!rename($temporary, $path)) {
        @unlink($temporary);
        return false;
    }

    return true;
}

function shortVersion(string $version): string
{
    return preg_match('/^[a-f0-9]{7,40}$/i', $version) ? substr($version, 0, 7) : $version;
}

function fetchLatestCommit(string $repository, string $branch, string $cacheFile, bool $refresh): array
{
    $cached = readJsonFile($cacheFile);
    if (
        !$refresh
        && $cached !== null
        && isset($cached['checked_at_unix'])
        && (time() - (int) $cached['checked_at_unix']) < 30
    ) {
        return $cached;
    }

    if (!preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $repository)) {
        throw new RuntimeException('GitHub repository ayarı geçersiz.');
    }

    $url = sprintf(
        'https://api.github.com/repos/%s/commits/%s',
        $repository,
        rawurlencode($branch)
    );

    $headers = [
        'Accept: application/vnd.github+json',
        'User-Agent: VideoKur-Updater',
        'X-GitHub-Api-Version: 2022-11-28',
    ];
    $githubToken = trim((string) (getenv('GITHUB_TOKEN') ?: ''));
    if ($githubToken !== '') {
        $headers[] = 'Authorization: Bearer ' . $githubToken;
    }

    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => $headers,
    ]);

    $body = curl_exec($curl);
    $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);

    if ($body === false || $curlError !== '') {
        throw new RuntimeException('GitHub bağlantısı kurulamadı.');
    }
    if ($httpCode !== 200) {
        throw new RuntimeException('GitHub sürüm bilgisi alınamadı (HTTP ' . $httpCode . ').');
    }

    $data = json_decode($body, true);
    $sha = is_array($data) ? trim((string) ($data['sha'] ?? '')) : '';
    if (!preg_match('/^[a-f0-9]{40}$/i', $sha)) {
        throw new RuntimeException('GitHub geçerli bir commit bilgisi döndürmedi.');
    }

    $message = trim((string) ($data['commit']['message'] ?? ''));
    $message = preg_split('/\R/', $message, 2)[0] ?? '';
    $result = [
        'sha' => $sha,
        'message' => $message,
        'date' => (string) ($data['commit']['author']['date'] ?? ''),
        'url' => (string) ($data['html_url'] ?? ''),
        'checked_at' => gmdate(DATE_ATOM),
        'checked_at_unix' => time(),
    ];
    writeJsonFile($cacheFile, $result);

    return $result;
}

$appEnvironment = trim((string) (getenv('APP_ENV') ?: 'local'));
$currentVersion = trim((string) (getenv('APP_VERSION') ?: 'local'));
$repository = trim((string) (getenv('APP_GITHUB_REPOSITORY') ?: 'oguzgokyar/VideoKur_VPS'));
$branch = trim((string) (getenv('APP_GITHUB_BRANCH') ?: 'main'));
$updateDirectory = rtrim((string) (getenv('APP_UPDATE_DIR') ?: '/app/data/update'), '/');
$updateEnabled = envFlag('APP_UPDATE_ENABLED') && $appEnvironment === 'production';
$statusFile = $updateDirectory . '/status.json';
$requestFile = $updateDirectory . '/request.json';
$cacheFile = $updateDirectory . '/github-cache.json';

$status = readJsonFile($statusFile) ?? [
    'state' => 'idle',
    'message' => 'Güncelleme bekleniyor.',
    'updated_at' => null,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'VideoKur') {
        respond(['success' => false, 'error' => 'Geçersiz güncelleme isteği.'], 403);
    }
    if (!$updateEnabled) {
        respond(['success' => false, 'error' => 'Web güncellemesi yalnızca VPS yayın ortamında kullanılabilir.'], 403);
    }
    if (($status['state'] ?? '') === 'running' || is_file($requestFile)) {
        respond(['success' => false, 'error' => 'Bir güncelleme zaten çalışıyor.'], 409);
    }

    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input) || ($input['action'] ?? '') !== 'update') {
        respond(['success' => false, 'error' => 'Geçersiz işlem.'], 400);
    }

    try {
        $latest = fetchLatestCommit($repository, $branch, $cacheFile, true);
    } catch (Throwable $error) {
        respond(['success' => false, 'error' => $error->getMessage()], 502);
    }

    if (hash_equals(strtolower($latest['sha']), strtolower($currentVersion))) {
        respond(['success' => false, 'error' => 'Uygulama zaten güncel.'], 409);
    }

    $request = [
        'request_id' => bin2hex(random_bytes(12)),
        'requested_at' => gmdate(DATE_ATOM),
        'current_version' => $currentVersion,
        'target_version' => $latest['sha'],
        'repository' => $repository,
        'branch' => $branch,
    ];
    if (!writeJsonFile($requestFile, $request)) {
        respond(['success' => false, 'error' => 'Güncelleme isteği oluşturulamadı.'], 500);
    }

    respond([
        'success' => true,
        'accepted' => true,
        'message' => 'Güncelleme başlatıldı. Sayfayı kapatmadan ilerlemeyi takip edebilirsiniz.',
        'target_version' => $latest['sha'],
        'target_short' => shortVersion($latest['sha']),
    ], 202);
}

$refresh = ($_GET['refresh'] ?? '') === '1';
$latest = null;
$checkError = null;
try {
    $latest = fetchLatestCommit($repository, $branch, $cacheFile, $refresh);
} catch (Throwable $error) {
    $checkError = $error->getMessage();
}

$latestSha = (string) ($latest['sha'] ?? '');
$currentIsCommit = (bool) preg_match('/^[a-f0-9]{40}$/i', $currentVersion);
$updateAvailable = $latestSha !== ''
    && (!$currentIsCommit || !hash_equals(strtolower($latestSha), strtolower($currentVersion)));

respond([
    'success' => true,
    'environment' => $appEnvironment,
    'repository' => $repository,
    'branch' => $branch,
    'update_enabled' => $updateEnabled,
    'current_version' => $currentVersion,
    'current_short' => shortVersion($currentVersion),
    'latest_version' => $latestSha,
    'latest_short' => $latestSha !== '' ? shortVersion($latestSha) : null,
    'latest_message' => $latest['message'] ?? null,
    'latest_date' => $latest['date'] ?? null,
    'latest_url' => $latest['url'] ?? null,
    'checked_at' => $latest['checked_at'] ?? null,
    'update_available' => $updateAvailable,
    'check_error' => $checkError,
    'deployment_status' => $status,
]);
