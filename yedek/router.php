<?php
// Router: php -S localhost:8000 router.php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// API istekleri
if (preg_match('#^/api/(.+\.php)#', $uri, $m)) {
    $file = __DIR__ . '/api/' . $m[1];
    if (file_exists($file)) {
        require $file;
        return true;
    }
}

// Output dosyaları (video, resim, ses vb.)
if (preg_match('#^/output/#', $uri)) {
    $outputFile = __DIR__ . $uri;
    if (is_file($outputFile)) {
        $ext = pathinfo($outputFile, PATHINFO_EXTENSION);
        $mimeTypes = [
            'mp4'  => 'video/mp4',
            'mp3'  => 'audio/mpeg',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'json' => 'application/json',
            'srt'  => 'text/plain',
            'txt'  => 'text/plain',
        ];
        if (isset($mimeTypes[$ext])) {
            header('Content-Type: ' . $mimeTypes[$ext]);
        }
        readfile($outputFile);
        return true;
    }
}

// Frontend dosyaları - önce PHP, sonra HTML dene
if ($uri === '/') {
    $uri = '/dashboard.php';
}

// PHP dosyası varsa çalıştır
$frontendPhp = __DIR__ . '/frontend' . $uri;
if (pathinfo($frontendPhp, PATHINFO_EXTENSION) === 'php' && is_file($frontendPhp)) {
    chdir(__DIR__ . '/frontend');
    require $frontendPhp;
    return true;
}

// HTML veya statik dosya varsa sun
$frontendFile = __DIR__ . '/frontend' . $uri;
if (is_file($frontendFile)) {
    $ext = pathinfo($frontendFile, PATHINFO_EXTENSION);
    $mimeTypes = [
        'html' => 'text/html',
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'json' => 'application/json',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'mp4'  => 'video/mp4',
    ];
    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
    }
    readfile($frontendFile);
    return true;
}

// 404
http_response_code(404);
echo '404 Not Found';
return true;
