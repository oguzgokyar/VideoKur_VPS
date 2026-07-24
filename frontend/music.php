<?php $page_title = 'Müzik Yönetimi - YouTube Shorts Otomasyon'; $active_page = 'music'; ?>
<!DOCTYPE html>
<html lang="tr">
<head>
  <?php include __DIR__ . '/components/_head.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.13.0/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-100 min-h-screen" x-data="musicManager()" x-init="init()">
  <div class="flex flex-col h-screen">
    <?php include __DIR__ . '/components/_header.php'; ?>
    <div class="flex flex-1 overflow-hidden">
      <?php include __DIR__ . '/components/_sidebar.php'; ?>

      <main class="flex-1 overflow-y-auto p-6 md:p-8">
        <div class="max-w-6xl mx-auto space-y-4">
          <div class="bg-white rounded-lg border border-gray-200 p-4">
            <h1 class="text-xl font-bold text-gray-800 mb-1">🎵 Müzik Yönetimi</h1>
            <p class="text-sm text-gray-500">Script kategorilerine göre tekli/çoklu müzik yükleyin.</p>
          </div>

          <div class="bg-white rounded-lg border border-gray-200 p-4">
            <h2 class="font-semibold text-gray-800 mb-3">Dosya Yükle</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-3">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Kategori</label>
                <select x-model="uploadForm.categoryId" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 outline-none">
                  <template x-for="cat in categories" :key="cat">
                    <option :value="cat" x-text="cat"></option>
                  </template>
                </select>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Ses Seviyesi (dB)</label>
                <input type="range" min="-35" max="-8" step="1" x-model.number="uploadForm.volumeDb" class="w-full accent-indigo-600">
                <p class="text-xs text-gray-500 mt-1" x-text="uploadForm.volumeDb + ' dB'"></p>
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Dosya Seçimi</label>
                <input type="file" multiple accept=".mp3,.wav,.m4a,.aac,.ogg,.flac,audio/*" @change="handleFileChange($event)" class="w-full text-sm">
                <p class="text-xs text-gray-500 mt-1">Tekli veya toplu seçim desteklenir.</p>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <button @click="uploadFiles()" :disabled="uploading || selectedFiles.length===0"
                class="px-4 py-2 rounded-md text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-sm font-medium transition">
                <span x-show="!uploading">Yükle</span>
                <span x-show="uploading">Yükleniyor...</span>
              </button>
              <span class="text-sm text-gray-600" x-text="selectedFiles.length ? (selectedFiles.length + ' dosya seçildi') : 'Dosya seçilmedi'"></span>
            </div>
            <template x-if="uploadMessage">
              <p class="text-sm mt-2" :class="uploadError ? 'text-red-600' : 'text-green-600'" x-text="uploadMessage"></p>
            </template>
          </div>

          <div class="bg-white rounded-lg border border-gray-200 p-4">
            <div class="flex items-center justify-between mb-3">
              <h2 class="font-semibold text-gray-800">Kütüphane</h2>
              <button @click="loadTracks()" class="px-3 py-1.5 rounded-md border border-gray-300 bg-white text-sm hover:bg-gray-50 transition">Yenile</button>
            </div>
            <div class="overflow-x-auto">
              <table class="w-full text-sm">
                <thead class="text-left text-gray-500 border-b">
                  <tr>
                    <th class="py-2 pr-2">Ad</th>
                    <th class="py-2 pr-2">Kategori</th>
                    <th class="py-2 pr-2">Dosya</th>
                    <th class="py-2 pr-2">dB</th>
                    <th class="py-2 pr-2">Durum</th>
                    <th class="py-2">İşlem</th>
                  </tr>
                </thead>
                <tbody>
                  <template x-for="track in tracks" :key="track.id">
                    <tr class="border-b border-gray-100">
                      <td class="py-2 pr-2" x-text="track.name"></td>
                      <td class="py-2 pr-2" x-text="track.categoryId"></td>
                      <td class="py-2 pr-2 text-xs text-gray-500" x-text="track.file"></td>
                      <td class="py-2 pr-2" x-text="track.volumeDb"></td>
                      <td class="py-2 pr-2">
                        <span class="px-2 py-0.5 rounded text-xs" :class="track.active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'" x-text="track.active ? 'Aktif' : 'Pasif'"></span>
                      </td>
                      <td class="py-2">
                        <div class="flex gap-2">
                          <button @click="toggleTrack(track.id)" class="px-2 py-1 rounded border border-gray-300 text-xs hover:bg-gray-50 transition">Durum</button>
                          <button @click="deleteTrack(track.id)" class="px-2 py-1 rounded border border-red-300 text-red-600 bg-red-50 text-xs">Sil</button>
                        </div>
                      </td>
                    </tr>
                  </template>
                  <tr x-show="tracks.length===0">
                    <td colspan="6" class="py-6 text-center text-gray-500">Kayıtlı müzik bulunamadı.</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </main>
    </div>
    <?php include __DIR__ . '/components/_footer.php'; ?>
  </div>

  <script>
    function musicManager() {
      return {
        tracks: [],
        categories: ['genel'],
        selectedFiles: [],
        uploading: false,
        uploadError: false,
        uploadMessage: '',
        uploadForm: {
          categoryId: 'genel',
          volumeDb: -22
        },
        async init() {
          await Promise.all([this.loadTracks(), this.loadCategories()]);
        },
        async loadCategories() {
          try {
            const r = await fetch('/api/scripts.php');
            const d = await r.json();
            const fromApi = (d.categories || []).map(c => (c.id || c).toString().toLowerCase());
            this.categories = fromApi.length ? fromApi : ['genel'];
            if (!this.categories.includes(this.uploadForm.categoryId)) {
              this.uploadForm.categoryId = this.categories[0];
            }
          } catch (_) {
            this.categories = ['genel'];
          }
        },
        async loadTracks() {
          const r = await fetch('/api/music.php');
          const d = await r.json();
          this.tracks = d.tracks || [];
          if (d.categories && d.categories.length) {
            this.categories = d.categories;
          }
        },
        handleFileChange(e) {
          this.selectedFiles = Array.from(e.target.files || []);
        },
        async uploadFiles() {
          if (!this.selectedFiles.length) return;
          this.uploading = true;
          this.uploadError = false;
          this.uploadMessage = '';
          try {
            const fd = new FormData();
            fd.append('action', 'upload_files');
            fd.append('categoryId', this.uploadForm.categoryId);
            fd.append('volumeDb', this.uploadForm.volumeDb);
            for (const file of this.selectedFiles) {
              fd.append('files[]', file);
            }
            const r = await fetch('/api/music.php', { method: 'POST', body: fd });
            const d = await r.json();
            if (!r.ok || !d.success) {
              this.uploadError = true;
              this.uploadMessage = d.error || 'Yükleme başarısız';
              return;
            }
            const uploadedCount = (d.uploaded || []).length;
            const errorCount = (d.errors || []).length;
            this.uploadMessage = `${uploadedCount} dosya eklendi${errorCount ? `, ${errorCount} dosya atlandı` : ''}.`;
            this.selectedFiles = [];
            await this.loadTracks();
          } catch (e) {
            this.uploadError = true;
            this.uploadMessage = 'Bağlantı hatası';
          } finally {
            this.uploading = false;
          }
        },
        async toggleTrack(id) {
          await fetch('/api/music.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'toggle_active', id })
          });
          await this.loadTracks();
        },
        async deleteTrack(id) {
          if (!confirm('Müzik silinsin mi?')) return;
          await fetch('/api/music.php', {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
          });
          await this.loadTracks();
        }
      }
    }
  </script>
</body>
</html>
