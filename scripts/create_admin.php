<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Bu araç yalnızca komut satırından çalıştırılabilir.\n");
    exit(1);
}

function prompt(string $label): string
{
    fwrite(STDOUT, $label);
    return trim((string)fgets(STDIN));
}

function promptPassword(string $label): string
{
    fwrite(STDOUT, $label);
    $hide = DIRECTORY_SEPARATOR !== '\\' && function_exists('shell_exec');
    if ($hide) {
        shell_exec('stty -echo');
    }
    $value = rtrim((string)fgets(STDIN), "\r\n");
    if ($hide) {
        shell_exec('stty echo');
        fwrite(STDOUT, PHP_EOL);
    }
    return $value;
}

$username = prompt('Yönetici kullanıcı adı: ');
$password = promptPassword('Parola (en az 8 karakter): ');
$confirmation = promptPassword('Parolayı tekrar girin: ');

if (!preg_match('/^[A-Za-z0-9._-]{3,64}$/', $username)) {
    fwrite(STDERR, "Kullanıcı adı 3-64 karakter olmalı; harf, sayı, nokta, alt çizgi ve tire kullanılabilir.\n");
    exit(1);
}
if (strlen($password) < 8 || strlen($password) > 1024) {
    fwrite(STDERR, "Parola 8-1024 karakter olmalıdır.\n");
    exit(1);
}
if (!hash_equals($password, $confirmation)) {
    fwrite(STDERR, "Parolalar eşleşmiyor.\n");
    exit(1);
}

$path = videokur_auth_file();
$directory = dirname($path);
if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
    fwrite(STDERR, "Auth dizini oluşturulamadı.\n");
    exit(1);
}

$users = videokur_read_users();
$replaced = false;
foreach ($users as &$user) {
    if (is_array($user) && strtolower((string)($user['username'] ?? '')) === strtolower($username)) {
        $user = [
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => 'admin',
            'active' => true,
            'updated_at' => date(DATE_ATOM),
        ];
        $replaced = true;
        break;
    }
}
unset($user);
if (!$replaced) {
    $users[] = [
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role' => 'admin',
        'active' => true,
        'created_at' => date(DATE_ATOM),
    ];
}

$temporary = $path . '.tmp.' . bin2hex(random_bytes(6));
$payload = json_encode(['version' => 1, 'users' => $users], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
if (file_put_contents($temporary, $payload . PHP_EOL, LOCK_EX) === false || !rename($temporary, $path)) {
    @unlink($temporary);
    fwrite(STDERR, "Kullanıcı dosyası güvenli şekilde yazılamadı.\n");
    exit(1);
}
@chmod($directory, 0700);
@chmod($path, 0600);
fwrite(STDOUT, $replaced ? "Yönetici parolası güncellendi.\n" : "Yönetici hesabı oluşturuldu.\n");
