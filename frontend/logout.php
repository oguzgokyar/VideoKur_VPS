<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !videokur_csrf_valid((string)($_POST['csrf_token'] ?? ''))) {
    http_response_code(405);
    header('Allow: POST');
    exit('Geçersiz istek');
}
videokur_logout();
header('Location: /login.php', true, 303);
exit;
