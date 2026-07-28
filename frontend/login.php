<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

$hasUsers = count(videokur_read_users()) > 0;
$error = '';
$returnTo = (string)($_GET['return_to'] ?? $_POST['return_to'] ?? '/dashboard.php');
if (!str_starts_with($returnTo, '/') || str_starts_with($returnTo, '//')) {
    $returnTo = '/dashboard.php';
}

if (videokur_current_user() !== null) {
    header('Location: ' . $returnTo, true, 302);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if (!videokur_csrf_valid((string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Oturum doğrulanamadı. Sayfayı yenileyip tekrar deneyin.';
    } elseif (videokur_login_retry_after($username) > 0) {
        http_response_code(429);
        $error = 'Çok fazla başarısız deneme. Lütfen daha sonra tekrar deneyin.';
    } elseif (videokur_login($username, $password)) {
        header('Location: ' . $returnTo, true, 303);
        exit;
    } else {
        usleep(random_int(150000, 350000));
        $error = 'Kullanıcı adı veya parola hatalı.';
    }
}

header('Cache-Control: no-store, no-cache, must-revalidate');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Giriş — VideoKur</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-6">
  <main class="w-full max-w-md">
    <section class="rounded-2xl border border-slate-800 bg-slate-900 p-8 shadow-2xl">
      <div class="mb-8">
        <img src="/assets/vk-icon.png" alt="VideoKur" class="h-12 w-12 rounded-xl object-cover shadow-lg">
        <h1 class="mt-5 text-2xl font-bold">VideoKur’a giriş yapın</h1>
        <p class="mt-2 text-sm text-slate-400">Yönetim paneline yalnızca yetkili kullanıcılar erişebilir.</p>
      </div>

      <?php if (!$hasUsers): ?>
        <div class="mb-6 rounded-lg border border-amber-700/60 bg-amber-950/50 p-4 text-sm text-amber-200">
          Henüz yönetici hesabı oluşturulmamış.
          <code class="mt-2 block break-all rounded bg-slate-950 p-2 text-xs text-slate-200">php /app/scripts/create_admin.php</code>
        </div>
      <?php endif; ?>

      <?php if ($error !== ''): ?>
        <div role="alert" class="mb-5 rounded-lg border border-red-700/60 bg-red-950/50 p-3 text-sm text-red-200">
          <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <form method="post" action="/login.php" class="space-y-5">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(videokur_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="return_to" value="<?= htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8') ?>">
        <div>
          <label for="username" class="mb-2 block text-sm font-medium">Kullanıcı adı</label>
          <input id="username" name="username" type="text" required autofocus autocomplete="username"
                 class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30">
        </div>
        <div>
          <label for="password" class="mb-2 block text-sm font-medium">Parola</label>
          <input id="password" name="password" type="password" required autocomplete="current-password"
                 class="w-full rounded-lg border border-slate-700 bg-slate-950 px-4 py-3 outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30">
        </div>
        <button type="submit" <?= !$hasUsers ? 'disabled' : '' ?>
                class="w-full rounded-lg bg-blue-600 px-4 py-3 font-semibold hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50">
          Güvenli giriş
        </button>
      </form>
    </section>
  </main>
</body>
</html>
