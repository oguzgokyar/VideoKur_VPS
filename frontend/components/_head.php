<?php
$page_title = $page_title ?? 'YouTube Shorts Otomasyon';
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" sizes="512x512" href="/assets/vk-icon.png">
<link rel="apple-touch-icon" href="/assets/vk-icon.png">
<title><?= htmlspecialchars($page_title) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
<?php include __DIR__ . '/_dark_mode.php'; ?>
<?php if (function_exists('videokur_csrf_token')): ?>
<meta name="csrf-token" content="<?= htmlspecialchars(videokur_csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
<script>
(() => {
  const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const nativeFetch = window.fetch.bind(window);
  window.fetch = (input, init = {}) => {
    const requestUrl = typeof input === 'string' ? input : input.url;
    const method = String(init.method || (typeof input !== 'string' ? input.method : 'GET')).toUpperCase();
    const sameOrigin = requestUrl.startsWith('/') || requestUrl.startsWith(window.location.origin);
    if (sameOrigin && !['GET', 'HEAD', 'OPTIONS'].includes(method) && token) {
      const headers = new Headers(init.headers || {});
      headers.set('X-CSRF-Token', token);
      init = { ...init, headers };
    }
    return nativeFetch(input, init);
  };
})();
</script>
<?php endif; ?>