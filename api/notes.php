<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function notesResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function safeNotePath(string $value): string
{
    $value = trim(str_replace('\\', '/', $value), ' /');
    if (
        $value === ''
        || str_contains($value, '..')
        || !preg_match('/^[A-Za-z0-9][A-Za-z0-9._\/-]*\.md$/', $value)
    ) {
        return '';
    }

    foreach (explode('/', $value) as $part) {
        if ($part === '' || $part === '.' || !preg_match('/^[A-Za-z0-9._-]+$/', $part)) {
            return '';
        }
    }

    return $value;
}

function notePath(string $root, string $relative): string
{
    return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
}

function notesInput(): array
{
    $decoded = json_decode((string) file_get_contents('php://input'), true);
    return is_array($decoded) ? $decoded : [];
}

function writeNoteAtomically(string $target, string $content): bool
{
    $directory = dirname($target);
    if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
        return false;
    }

    $temporary = $target . '.tmp.' . bin2hex(random_bytes(4));
    if (file_put_contents($temporary, $content, LOCK_EX) === false) {
        @unlink($temporary);
        return false;
    }
    @chmod($temporary, 0660);
    if (!rename($temporary, $target)) {
        @unlink($temporary);
        return false;
    }

    return true;
}

$dataDirectory = rtrim((string) (getenv('DATA_DIR') ?: (__DIR__ . '/../data')), '/\\');
$notesDirectory = rtrim((string) (getenv('NOTES_DIR') ?: ($dataDirectory . '/notes')), '/\\');
if (!is_dir($notesDirectory) && !mkdir($notesDirectory, 0770, true) && !is_dir($notesDirectory)) {
    notesResponse(['error' => 'Kalıcı not klasörü oluşturulamadı.'], 500);
}
$notesRoot = realpath($notesDirectory);
if ($notesRoot === false) {
    notesResponse(['error' => 'Kalıcı not klasörüne erişilemiyor.'], 500);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    $name = safeNotePath((string) ($_GET['file'] ?? ''));
    if ($name !== '') {
        $path = notePath($notesRoot, $name);
        $realPath = realpath($path);
        if (
            $realPath === false
            || !str_starts_with($realPath, $notesRoot . DIRECTORY_SEPARATOR)
            || !is_file($realPath)
        ) {
            notesResponse(['error' => 'Not bulunamadı.'], 404);
        }

        notesResponse([
            'file' => $name,
            'content' => (string) file_get_contents($realPath),
            'updatedAt' => date(DATE_ATOM, (int) filemtime($realPath)),
        ]);
    }

    $notes = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($notesRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if (!$file->isFile() || strtolower($file->getExtension()) !== 'md') {
            continue;
        }
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($notesRoot) + 1));
        $notes[] = [
            'file' => $relative,
            'title' => $file->getBasename('.md'),
            'folder' => dirname($relative) === '.' ? 'Notlar' : dirname($relative),
            'updatedAt' => date(DATE_ATOM, $file->getMTime()),
        ];
    }
    usort($notes, static fn(array $left, array $right): int => strcasecmp($left['file'], $right['file']));
    notesResponse(['notes' => $notes]);
}

if ($method === 'POST' || $method === 'PUT') {
    $input = notesInput();
    $oldName = safeNotePath((string) ($input['oldFile'] ?? ''));
    $name = safeNotePath((string) ($input['file'] ?? ''));
    $content = (string) ($input['content'] ?? '');
    if ($name === '') {
        notesResponse(['error' => 'Geçerli bir .md yolu girin. Alt klasörler / ile ayrılabilir.'], 422);
    }
    if (mb_strlen($content) > 500000) {
        notesResponse(['error' => 'Not 500 KB sınırını aşamaz.'], 413);
    }

    $target = notePath($notesRoot, $name);
    if ($method === 'POST' && file_exists($target)) {
        notesResponse(['error' => 'Bu dosya zaten var.'], 409);
    }
    if ($method === 'PUT') {
        $oldPath = $oldName !== '' ? notePath($notesRoot, $oldName) : '';
        if ($oldPath === '' || !is_file($oldPath)) {
            notesResponse(['error' => 'Düzenlenecek not bulunamadı.'], 404);
        }
        if ($oldName !== $name && file_exists($target)) {
            notesResponse(['error' => 'Yeni dosya adı zaten kullanılıyor.'], 409);
        }
    }

    if (!writeNoteAtomically($target, $content)) {
        notesResponse(['error' => 'Not kaydedilemedi.'], 500);
    }
    if ($method === 'PUT' && $oldName !== $name) {
        @unlink(notePath($notesRoot, $oldName));
    }

    notesResponse(['success' => true, 'file' => $name]);
}

if ($method === 'DELETE') {
    $name = safeNotePath((string) ($_GET['file'] ?? ''));
    if ($name === '') {
        notesResponse(['error' => 'Geçerli not yolu gerekli.'], 422);
    }
    $path = notePath($notesRoot, $name);
    if (!is_file($path)) {
        notesResponse(['error' => 'Not bulunamadı.'], 404);
    }
    if (!unlink($path)) {
        notesResponse(['error' => 'Not silinemedi.'], 500);
    }

    notesResponse(['success' => true]);
}

header('Allow: GET, POST, PUT, DELETE');
notesResponse(['error' => 'Method not allowed'], 405);
