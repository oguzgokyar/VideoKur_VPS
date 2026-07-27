<?php

function normalizeMusicCategory($value) {
    $normalized = trim(strtolower((string)$value));
    return $normalized !== '' ? $normalized : 'genel';
}

function normalizeMusicMode($value) {
    $mode = trim(strtolower((string)$value));
    return in_array($mode, ['off', 'single', 'rotate'], true) ? $mode : 'off';
}

function normalizeMusicFilePath($file) {
    $path = str_replace('\\', '/', trim((string)$file));
    $path = ltrim($path, '/');
    if ($path === '' || strpos($path, '..') !== false) {
        return null;
    }
    if (strpos($path, 'assets/music/') !== 0) {
        return null;
    }
    return $path;
}

function ensureMusicLibraryFile($baseDir) {
    $assetsDir = $baseDir . '/assets';
    $musicDir = $assetsDir . '/music';
    $dataDir = $baseDir . '/data';
    $libraryFile = $dataDir . '/music_library.json';

    if (!is_dir($assetsDir)) {
        mkdir($assetsDir, 0777, true);
    }
    if (!is_dir($musicDir)) {
        mkdir($musicDir, 0777, true);
    }
    if (!file_exists($libraryFile)) {
        $initial = ['tracks' => [], 'metadata' => ['created_at' => date('c'), 'last_updated' => date('c')]];
        file_put_contents($libraryFile, json_encode($initial, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    return $libraryFile;
}

function loadMusicLibrary($baseDir) {
    $libraryFile = ensureMusicLibraryFile($baseDir);
    $data = json_decode(file_get_contents($libraryFile), true);
    if (!is_array($data)) {
        return ['tracks' => [], 'metadata' => ['last_updated' => date('c')]];
    }
    if (!isset($data['tracks']) || !is_array($data['tracks'])) {
        $data['tracks'] = [];
    }
    return $data;
}

function saveMusicLibrary($baseDir, $data) {
    $libraryFile = ensureMusicLibraryFile($baseDir);
    if (!isset($data['metadata']) || !is_array($data['metadata'])) {
        $data['metadata'] = [];
    }
    $data['metadata']['last_updated'] = date('c');
    file_put_contents($libraryFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function getScriptCategories($baseDir) {
    $scriptsFile = $baseDir . '/data/scripts.json';
    $result = [];
    if (!file_exists($scriptsFile)) {
        return ['genel'];
    }

    $data = json_decode(file_get_contents($scriptsFile), true);
    if (is_array($data)) {
        foreach (($data['categories'] ?? []) as $category) {
            $name = normalizeMusicCategory($category['id'] ?? $category['name'] ?? '');
            if ($name !== '') {
                $result[$name] = true;
            }
        }
        foreach (($data['scripts'] ?? []) as $script) {
            $name = normalizeMusicCategory($script['categoryId'] ?? $script['contentType'] ?? '');
            if ($name !== '') {
                $result[$name] = true;
            }
        }
    }

    if (empty($result)) {
        return ['genel'];
    }
    $categories = array_keys($result);
    sort($categories);
    return $categories;
}

function resolveScriptCategory($script, $fallbackContentType = 'genel') {
    $category = normalizeMusicCategory($script['categoryId'] ?? '');
    if ($category !== 'genel' || !empty($script['categoryId'])) {
        return $category;
    }
    return normalizeMusicCategory($script['contentType'] ?? $fallbackContentType);
}

function selectMusicTrackForCategory($baseDir, $categoryId) {
    $category = normalizeMusicCategory($categoryId);
    $library = loadMusicLibrary($baseDir);

    $candidates = array_values(array_filter($library['tracks'], function($track) use ($category) {
        $active = (bool)($track['active'] ?? true);
        $trackCategory = normalizeMusicCategory($track['categoryId'] ?? 'genel');
        return $active && $trackCategory === $category;
    }));

    if (empty($candidates)) {
        return null;
    }

    $selected = $candidates[array_rand($candidates)];
    $file = normalizeMusicFilePath($selected['file'] ?? '');
    if ($file === null) {
        return null;
    }

    $fullPath = $baseDir . '/' . str_replace('/', DIRECTORY_SEPARATOR, $file);
    if (!file_exists($fullPath)) {
        return null;
    }

    return [
        'id' => $selected['id'] ?? null,
        'name' => $selected['name'] ?? '',
        'categoryId' => $category,
        'file' => $file,
        'volumeDb' => (float)($selected['volumeDb'] ?? -22.0)
    ];
}

function selectMusicTrackForScript($baseDir, $scriptProfile) {
    $music = is_array($scriptProfile['music'] ?? null) ? $scriptProfile['music'] : [];
    $mode = normalizeMusicMode($music['mode'] ?? 'off');
    $trackIds = array_values(array_filter(array_map('strval', is_array($music['trackIds'] ?? null) ? $music['trackIds'] : [])));
    if ($mode === 'off' || empty($trackIds)) return null;

    $library = loadMusicLibrary($baseDir);
    $byId = [];
    foreach ($library['tracks'] as $track) {
        $id = trim((string)($track['id'] ?? ''));
        $file = normalizeMusicFilePath($track['file'] ?? '');
        if ($id === '' || $file === null || ($track['active'] ?? true) === false) continue;
        if (!file_exists($baseDir . '/' . str_replace('/', DIRECTORY_SEPARATOR, $file))) continue;
        $byId[$id] = $track;
    }
    $validIds = array_values(array_filter($trackIds, fn($id) => isset($byId[$id])));
    if (!$validIds) return null;

    $selectedId = $validIds[0];
    if ($mode === 'rotate' && count($validIds) > 1) {
        $stateFile = $baseDir . '/data/script_music_rotation.json';
        $handle = fopen($stateFile, 'c+');
        if ($handle && flock($handle, LOCK_EX)) {
            $raw = stream_get_contents($handle);
            $state = json_decode($raw ?: '{}', true);
            if (!is_array($state)) $state = [];
            $scriptId = trim((string)($scriptProfile['id'] ?? 'unknown'));
            $index = max(0, (int)($state[$scriptId]['nextIndex'] ?? 0));
            $selectedId = $validIds[$index % count($validIds)];
            $state[$scriptId] = ['nextIndex' => ($index + 1) % count($validIds), 'updatedAt' => date('c')];
            rewind($handle);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            fflush($handle);
            flock($handle, LOCK_UN);
            fclose($handle);
        } elseif ($handle) {
            fclose($handle);
        }
    }

    $track = $byId[$selectedId];
    return [
        'id' => $selectedId,
        'name' => (string)($track['name'] ?? ''),
        'categoryId' => normalizeMusicCategory($track['categoryId'] ?? 'genel'),
        'file' => normalizeMusicFilePath($track['file'] ?? ''),
        'volumeDb' => (float)($music['volumeDb'] ?? $track['volumeDb'] ?? -22.0),
    ];
}
