<?php
$show_status = $show_status ?? false;
$page_titles = [
  'dashboard' => '📹 Videolar',
  'videos' => '📹 Videolar',
  'queues' => '📦 Kuyruklar',
  'create' => '➕ Yeni Video',
  'content' => '📥 İçerikler',
  'scripts' => '📝 Script Yönetimi',
  'settings' => '⚙️ Ayarlar',
  'accounts' => '🔗 Hesaplar',
  'accounts_youtube' => '📺 YouTube Hesapları',
  'accounts_meta' => '📘 Meta Hesapları'
];
$current_title = $page_titles[$active_page ?? ''] ?? 'Dashboard';
?>
<header class="bg-white dark:bg-slate-800 shadow-md dark:shadow-slate-900/50 flex items-center justify-between px-6 py-3 z-30 border-b border-gray-100 dark:border-slate-700">
  <div class="flex items-center gap-4">
    <button class="md:hidden text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white" @click="sidebarOpen = !sidebarOpen">
      <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>
    <div class="flex items-center gap-3">
      <div class="w-8 h-8 bg-red-600 rounded-lg flex items-center justify-center">
        <svg class="w-5 h-5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M19.615 3.184c-3.604-.246-11.631-.245-15.23 0C.488 3.45.029 5.804 0 12c.029 6.185.484 8.549 4.385 8.816 3.6.245 11.626.246 15.23 0C23.512 20.55 23.971 18.196 24 12c-.029-6.185-.484-8.549-4.385-8.816zM9 16V8l8 4-8 4z"/></svg>
      </div>
      <div class="hidden sm:block">
        <span class="text-lg font-bold text-gray-800 dark:text-white">Shorts Otomasyon</span>
      </div>
    </div>
    <div class="hidden sm:flex items-center">
      <span class="text-gray-300 dark:text-slate-600 mx-3">|</span>
      <h1 class="text-lg font-semibold text-gray-700 dark:text-gray-200"><?php echo $current_title; ?></h1>
    </div>
  </div>
  <div class="flex items-center gap-3">
    <?php if ($active_page === 'settings'): ?>
    <button 
      type="button"
      @click="testAllInTab()"
      class="inline-flex items-center gap-2 px-4 py-2 bg-gray-700 hover:bg-gray-800 text-white rounded-lg font-semibold text-sm transition shadow-sm"
    >
      <span>🔍</span>
      <span class="hidden sm:inline">Test Et</span>
    </button>
    <button 
      type="button"
      @click="saveConfig()"
      class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold text-sm transition shadow-sm"
    >
      <span>💾</span>
      <span class="hidden sm:inline">Kaydet</span>
    </button>
    <?php endif; ?>
    <?php if ($active_page === 'queues'): ?>
    <button 
      @click="openCreateModal()"
      class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold text-sm transition shadow-sm"
    >
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
      <span class="hidden sm:inline">Yeni Kuyruk</span>
    </button>
    <?php endif; ?>
    <?php if ($active_page === 'content'): ?>
    <button 
      @click="openAddUrlModal()"
      class="inline-flex items-center gap-2 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium text-sm transition"
    >
      <span>➕</span>
      <span class="hidden sm:inline">URL Ekle</span>
    </button>
    <button 
      @click="openManageSourcesModal()"
      class="inline-flex items-center gap-2 px-3 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg font-medium text-sm transition"
    >
      <span>📡</span>
      <span class="hidden sm:inline">RSS Kaynakları</span>
    </button>
    <?php endif; ?>
    <?php if ($active_page === 'scripts'): ?>
    <button 
      @click="openNewScriptModal()"
      class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold text-sm transition shadow-sm"
    >
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      <span class="hidden sm:inline">Yeni Script</span>
    </button>
    <?php endif; ?>
    <?php if ($show_status): ?>
    <template x-if="job">
      <div class="flex items-center gap-2">
        <span class="text-xs px-2.5 py-1 rounded-full font-semibold" :class="getStatusColor(job.status)" x-text="getStatusLabel(job.status)"></span>
        <template x-if="isActive">
          <svg class="w-4 h-4 text-blue-500 anim-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
        </template>
      </div>
    </template>
    <?php else: ?>
    <template x-if="typeof autoRefresh !== 'undefined'">
      <div class="flex items-center gap-2 text-xs text-gray-400">
        <span class="inline-block w-2 h-2 rounded-full bg-green-400 anim-pulse-dot"></span> Otomatik yenileme
      </div>
    </template>
    <?php endif; ?>
    <button @click="toggleDark()" class="p-2 rounded-lg text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-slate-700 transition" :title="darkMode?'Açık Mod':'Koyu Mod'">
      <template x-if="!darkMode"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg></template>
      <template x-if="darkMode"><svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M12 8a4 4 0 100 8 4 4 0 000-8z"/></svg></template>
    </button>
  </div>
</header>
