<?php
require_once dirname(__DIR__) . '/includes/auth.php';
$page_title = 'Notlar - YouTube Shorts Otomasyon';
$active_page = 'notes';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <?php include __DIR__ . '/components/_head.php'; ?>
  <style>[x-cloak] { display: none !important; }</style>
  <script>
  function notesApp() {
    return {
      sidebarOpen: false,
      darkMode: localStorage.getItem('darkMode') === '1',
      notes: [],
      selected: '',
      content: '',
      draft: '',
      draftFile: '',
      oldFile: '',
      editing: false,
      loading: false,
      saving: false,
      error: '',

      async init() {
        await this.loadNotes();
        if (this.notes.length > 0) await this.openNote(this.notes[0].file);
      },
      async request(url, options = {}) {
        const response = await fetch(url, { cache: 'no-store', ...options });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(data.error || `İstek başarısız (HTTP ${response.status})`);
        return data;
      },
      async loadNotes() {
        this.loading = true;
        this.error = '';
        try {
          const data = await this.request('/api/notes.php');
          this.notes = Array.isArray(data.notes) ? data.notes : [];
        } catch (error) {
          this.error = error.message;
          this.notes = [];
        } finally {
          this.loading = false;
        }
      },
      async openNote(file) {
        this.editing = false;
        this.error = '';
        try {
          const data = await this.request('/api/notes.php?file=' + encodeURIComponent(file));
          this.selected = data.file;
          this.content = data.content || '';
        } catch (error) {
          this.error = error.message;
        }
      },
      newNote() {
        this.error = '';
        this.editing = true;
        this.oldFile = '';
        this.draftFile = 'yeni-not.md';
        this.draft = '# Yeni Not\n\n';
      },
      editNote() {
        if (!this.selected) return;
        this.error = '';
        this.editing = true;
        this.oldFile = this.selected;
        this.draftFile = this.selected;
        this.draft = this.content;
      },
      cancelEdit() {
        this.editing = false;
        this.error = '';
      },
      async saveNote() {
        if (this.saving) return;
        this.saving = true;
        this.error = '';
        try {
          const editingExisting = this.oldFile !== '';
          const data = await this.request('/api/notes.php', {
            method: editingExisting ? 'PUT' : 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              oldFile: this.oldFile,
              file: this.draftFile,
              content: this.draft
            })
          });
          await this.loadNotes();
          await this.openNote(data.file);
        } catch (error) {
          this.error = error.message;
        } finally {
          this.saving = false;
        }
      },
      async deleteNote() {
        if (!this.selected || !confirm('Bu Markdown notu silinsin mi?')) return;
        this.error = '';
        try {
          await this.request('/api/notes.php?file=' + encodeURIComponent(this.selected), { method: 'DELETE' });
          this.selected = '';
          this.content = '';
          await this.loadNotes();
          if (this.notes.length > 0) await this.openNote(this.notes[0].file);
        } catch (error) {
          this.error = error.message;
        }
      },
      formatDate(value) {
        if (!value) return '';
        return new Date(value).toLocaleString('tr-TR');
      },
      toggleDark() {
        this.darkMode = !this.darkMode;
        localStorage.setItem('darkMode', this.darkMode ? '1' : '0');
        document.documentElement.classList.toggle('dark', this.darkMode);
      }
    };
  }
  </script>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.0/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-50 dark:bg-slate-900 text-gray-800 dark:text-gray-100" x-data="notesApp()" x-init="init()">
  <div class="flex flex-col h-screen">
    <?php include __DIR__ . '/components/_header.php'; ?>
    <div class="flex flex-1 overflow-hidden">
      <?php include __DIR__ . '/components/_sidebar.php'; ?>

      <main class="flex-1 overflow-y-auto p-4 md:p-6">
        <div class="max-w-7xl mx-auto flex flex-col lg:flex-row gap-6">
          <section class="lg:w-80 flex-shrink-0 bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4">
            <div class="flex items-start justify-between gap-3 mb-4">
              <div>
                <h2 class="font-bold text-gray-900 dark:text-white">Markdown Notları</h2>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Kalıcı data/notes alanı</p>
              </div>
              <button type="button" @click="newNote()" class="px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-semibold transition">+ Yeni</button>
            </div>

            <p x-show="loading" class="text-sm text-gray-500 py-4">Notlar yükleniyor...</p>
            <p x-show="!loading && notes.length === 0" class="text-sm text-gray-500 py-4">Henüz not bulunmuyor.</p>
            <div class="space-y-1 max-h-[65vh] overflow-y-auto" x-cloak>
              <template x-for="note in notes" :key="note.file">
                <button type="button" @click="openNote(note.file)"
                  class="w-full text-left px-3 py-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 transition"
                  :class="selected === note.file ? 'bg-blue-50 dark:bg-blue-900/30' : ''">
                  <div class="text-xs text-gray-400 truncate" x-text="note.folder"></div>
                  <div class="text-sm font-medium truncate" x-text="note.title"></div>
                  <div class="text-xs text-gray-400" x-text="formatDate(note.updatedAt)"></div>
                </button>
              </template>
            </div>
          </section>

          <section class="flex-1 min-w-0 bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-5">
            <template x-if="editing">
              <div>
                <div class="flex flex-col sm:flex-row gap-2 mb-3">
                  <input x-model="draftFile" class="flex-1 px-3 py-2 rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-900" placeholder="klasor/not.md">
                  <button type="button" @click="saveNote()" :disabled="saving" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-lg font-semibold">
                    <span x-text="saving ? 'Kaydediliyor...' : 'Kaydet'"></span>
                  </button>
                  <button type="button" @click="cancelEdit()" class="px-4 py-2 bg-gray-200 hover:bg-gray-300 dark:bg-slate-700 dark:hover:bg-slate-600 rounded-lg">İptal</button>
                </div>
                <textarea x-model="draft" class="w-full min-h-[60vh] p-4 rounded-lg border border-gray-300 dark:border-slate-600 dark:bg-slate-900 font-mono text-sm"></textarea>
              </div>
            </template>

            <template x-if="!editing">
              <div>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                  <h2 class="font-bold text-xl break-all" x-text="selected || 'Bir not seçin'"></h2>
                  <div class="flex gap-2" x-show="selected">
                    <button type="button" @click="editNote()" class="px-3 py-2 bg-amber-500 hover:bg-amber-600 text-white rounded-lg text-sm font-semibold">Düzenle</button>
                    <button type="button" @click="deleteNote()" class="px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-sm font-semibold">Sil</button>
                  </div>
                </div>
                <pre x-show="content" x-text="content" class="whitespace-pre-wrap break-words text-sm leading-6 font-sans"></pre>
                <p x-show="!content" class="text-gray-500">Sol menüden bir not seçin veya yeni not oluşturun.</p>
              </div>
            </template>

            <p x-show="error" x-text="error" class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg text-red-700 text-sm"></p>
          </section>
        </div>
      </main>
    </div>
  </div>
</body>
</html>
