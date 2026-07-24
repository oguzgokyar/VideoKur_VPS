<?php
$active_page = $active_page ?? 'videos';
?>
<div x-show="sidebarOpen" @click="sidebarOpen = false" class="fixed inset-0 bg-black/30 z-20 md:hidden" x-transition.opacity></div>

<aside 
  x-data="{ sidebarCollapsedState: localStorage.getItem('sidebarCollapsed') === '1' }"
  :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
  class="fixed md:static inset-y-0 left-0 z-30 bg-white dark:bg-slate-800 border-r border-gray-200 dark:border-slate-700 transform transition-all duration-300 ease-in-out pt-16 md:pt-0 overflow-hidden"
  :style="sidebarCollapsedState ? 'width: 4rem' : 'width: 15rem'"
>
  <!-- Toggle Button (Desktop Only) -->
  <button 
    @click="sidebarCollapsedState = !sidebarCollapsedState; localStorage.setItem('sidebarCollapsed', sidebarCollapsedState ? '1' : '0')"
    class="hidden md:flex absolute top-4 -right-3 z-40 w-6 h-6 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-full items-center justify-center shadow-sm hover:shadow-md transition-all hover:scale-110"
    title="Sidebar'ı aç/kapat"
  >
    <svg 
      class="w-3 h-3 text-gray-600 dark:text-gray-300 transition-transform duration-300"
      :class="sidebarCollapsedState ? 'rotate-180' : ''"
      fill="none" 
      viewBox="0 0 24 24" 
      stroke="currentColor"
    >
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M15 19l-7-7 7-7"/>
    </svg>
  </button>

  <nav class="flex flex-col p-3 gap-1">
    <a 
      href="create.php" 
      class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all group <?= $active_page === 'create' ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 font-semibold' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700' ?>"
      :class="sidebarCollapsedState ? 'justify-center' : ''"
      :title="sidebarCollapsedState ? 'Yeni Video' : ''"
    >
      <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      <span x-show="!sidebarCollapsedState" class="whitespace-nowrap">Yeni Video</span>
      <!-- Tooltip for collapsed state -->
      <div x-show="sidebarCollapsedState" class="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity whitespace-nowrap">
        Yeni Video
      </div>
    </a>
    
    <a 
      href="content.php" 
      class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all group <?= $active_page === 'content' ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 font-semibold' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700' ?>"
      :class="sidebarCollapsedState ? 'justify-center' : ''"
      :title="sidebarCollapsedState ? 'İçerikler' : ''"
    >
      <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/></svg>
      <span x-show="!sidebarCollapsedState" class="whitespace-nowrap">İçerikler</span>
      <div x-show="sidebarCollapsedState" class="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity whitespace-nowrap">
        İçerikler
      </div>
    </a>
    
    <a 
      href="dashboard.php" 
      class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all group <?= $active_page === 'videos' ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 font-semibold' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700' ?>"
      :class="sidebarCollapsedState ? 'justify-center' : ''"
      :title="sidebarCollapsedState ? 'Videolar' : ''"
    >
      <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
      <span x-show="!sidebarCollapsedState" class="whitespace-nowrap">Videolar</span>
      <div x-show="sidebarCollapsedState" class="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity whitespace-nowrap">
        Videolar
      </div>
    </a>
    
    <a 
      href="queues.php" 
      class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all group <?= $active_page === 'queues' ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 font-semibold' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700' ?>"
      :class="sidebarCollapsedState ? 'justify-center' : ''"
      :title="sidebarCollapsedState ? 'Kuyruklar' : ''"
    >
      <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
      <span x-show="!sidebarCollapsedState" class="whitespace-nowrap">Kuyruklar</span>
      <div x-show="sidebarCollapsedState" class="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity whitespace-nowrap">
        Kuyruklar
      </div>
    </a>
    
    <a 
      href="scripts.php" 
      class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all group <?= $active_page === 'scripts' ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 font-semibold' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700' ?>"
      :class="sidebarCollapsedState ? 'justify-center' : ''"
      :title="sidebarCollapsedState ? 'Script Yönetimi' : ''"
    >
      <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      <span x-show="!sidebarCollapsedState" class="whitespace-nowrap">Script Yönetimi</span>
      <div x-show="sidebarCollapsedState" class="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity whitespace-nowrap">
        Script Yönetimi
      </div>
    </a>
    
    <a 
      href="accounts.php" 
      class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all group <?= $active_page === 'accounts' ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 font-semibold' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700' ?>"
      :class="sidebarCollapsedState ? 'justify-center' : ''"
      :title="sidebarCollapsedState ? 'Hesaplar' : ''"
    >
      <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
      <span x-show="!sidebarCollapsedState" class="whitespace-nowrap">Hesaplar</span>
      <div x-show="sidebarCollapsedState" class="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity whitespace-nowrap">
        Hesaplar
      </div>
    </a>
    
    <a 
      href="settings.php" 
      class="flex items-center gap-3 px-3 py-2.5 rounded-lg transition-all group <?= $active_page === 'settings' ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 font-semibold' : 'text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-slate-700' ?>"
      :class="sidebarCollapsedState ? 'justify-center' : ''"
      :title="sidebarCollapsedState ? 'Ayarlar' : ''"
    >
      <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37 1.066.426 2.573-.066 2.573-1.066z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      <span x-show="!sidebarCollapsedState" class="whitespace-nowrap">Ayarlar</span>
      <div x-show="sidebarCollapsedState" class="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity whitespace-nowrap">
        Ayarlar
      </div>
    </a>
    
    <?php if ($active_page === 'project'): ?>
    <div class="border-t border-gray-100 dark:border-slate-700 mt-2 pt-2">
      <span 
        class="flex items-center gap-3 px-3 py-2.5 rounded-lg bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 font-semibold text-sm group"
        :class="sidebarCollapsedState ? 'justify-center' : ''"
      >
        <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        <span x-show="!sidebarCollapsedState" class="whitespace-nowrap">Proje Detayı</span>
        <div x-show="sidebarCollapsedState" class="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity whitespace-nowrap">
          Proje Detayı
        </div>
      </span>
    </div>
    <?php endif; ?>
  </nav>
</aside>
