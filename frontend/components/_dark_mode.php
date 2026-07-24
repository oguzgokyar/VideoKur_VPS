<style>
  html.dark { color-scheme: dark; }
  html.dark body { background-color: #0f172a !important; }
  html.dark .bg-white { background-color: #1e293b !important; }
  html.dark .bg-gray-50 { background-color: #0f172a !important; }
  html.dark .bg-gray-100 { background-color: #1e293b !important; }
  html.dark .bg-gray-200 { background-color: #334155 !important; }
  html.dark .bg-gray-900 { background-color: #020617 !important; }
  html.dark .border-gray-100, html.dark .border-gray-200, html.dark .border-gray-300 { border-color: #334155 !important; }
  html.dark .border-b, html.dark .border-r, html.dark .border-t, html.dark .border-l { border-color: #334155 !important; }
  html.dark .border { border-color: #334155 !important; }
  html.dark .divide-gray-100 > * + * { border-color: #334155 !important; }
  html.dark .text-gray-900 { color: #f1f5f9 !important; }
  html.dark .text-gray-800 { color: #f1f5f9 !important; }
  html.dark .text-gray-700 { color: #e2e8f0 !important; }
  html.dark .text-gray-600 { color: #cbd5e1 !important; }
  html.dark .text-gray-500 { color: #94a3b8 !important; }
  html.dark .text-gray-400 { color: #64748b !important; }
  html.dark header { background-color: #1e293b !important; border-color: #334155 !important; }
  html.dark aside { background-color: #1e293b !important; border-color: #334155 !important; }
  html.dark footer { background-color: #1e293b !important; border-color: #334155 !important; }
  html.dark .shadow, html.dark .shadow-sm, html.dark .shadow-md, html.dark .shadow-lg, html.dark .shadow-xl { box-shadow: 0 1px 6px 0 rgba(0,0,0,.5) !important; }
  html.dark .hover\:bg-gray-100:hover { background-color: #334155 !important; }
  html.dark .hover\:bg-gray-200:hover { background-color: #475569 !important; }
  html.dark .hover\:bg-gray-50:hover { background-color: #334155 !important; }
  /* Status backgrounds */
  html.dark .bg-blue-50 { background-color: #1e3a5f !important; }
  html.dark .bg-purple-50 { background-color: #2d1b4e !important; }
  html.dark .bg-green-50 { background-color: #14532d !important; }
  html.dark .bg-red-50 { background-color: #450a0a !important; }
  html.dark .bg-yellow-50 { background-color: #422006 !important; }
  html.dark .bg-pink-50 { background-color: #500724 !important; }
  html.dark .bg-indigo-50 { background-color: #1e1b4b !important; }
  html.dark .bg-cyan-50 { background-color: #083344 !important; }
  html.dark .bg-red-100 { background-color: #450a0a !important; }
  html.dark .bg-pink-100 { background-color: #500724 !important; }
  html.dark .bg-green-100 { background-color: #14532d !important; }
  html.dark .bg-blue-100 { background-color: #1e3a5f !important; }
  html.dark .bg-yellow-100 { background-color: #422006 !important; }
  html.dark .bg-indigo-100 { background-color: #1e1b4b !important; }
  html.dark .bg-cyan-100 { background-color: #083344 !important; }
  /* Form elements */
  html.dark input, html.dark select, html.dark textarea { background-color: #0f172a !important; border-color: #334155 !important; color: #e2e8f0 !important; }
  html.dark input::placeholder, html.dark textarea::placeholder { color: #64748b !important; }
  html.dark input:focus, html.dark select:focus, html.dark textarea:focus { border-color: #6366f1 !important; }
  html.dark .ring-2 { --tw-ring-color: #334155; }
  /* Platform text colors */
  html.dark .text-red-600 { color: #f87171 !important; }
  html.dark .text-red-700 { color: #fca5a5 !important; }
  html.dark .text-pink-600 { color: #f472b6 !important; }
  html.dark .text-pink-700 { color: #f9a8d4 !important; }
  html.dark .text-pink-800 { color: #fbcfe8 !important; }
  html.dark .text-green-600 { color: #4ade80 !important; }
  html.dark .text-green-700 { color: #86efac !important; }
  html.dark .text-green-800 { color: #86efac !important; }
  html.dark .text-blue-600 { color: #60a5fa !important; }
  html.dark .text-blue-700 { color: #93c5fd !important; }
  html.dark .text-blue-800 { color: #93c5fd !important; }
  html.dark .text-yellow-600 { color: #facc15 !important; }
  html.dark .text-yellow-700 { color: #fde047 !important; }
  html.dark .text-yellow-800 { color: #fef08a !important; }
  html.dark .text-yellow-900 { color: #fef9c3 !important; }
  html.dark .text-indigo-600 { color: #818cf8 !important; }
  html.dark .text-cyan-700 { color: #67e8f9 !important; }
  html.dark code { background-color: #334155 !important; color: #e2e8f0 !important; }
  /* Modals */
  html.dark .bg-black\/60 { background-color: rgba(0,0,0,0.75) !important; }
  /* Borders for status cards */
  html.dark .border-green-300 { border-color: #22c55e !important; }
  html.dark .border-yellow-300 { border-color: #eab308 !important; }
  html.dark .border-red-300 { border-color: #ef4444 !important; }
  html.dark .border-indigo-500 { border-color: #6366f1 !important; }
  /* Hovers */
  html.dark .hover\:bg-indigo-50:hover { background-color: #312e81 !important; }
  html.dark .hover\:bg-red-50:hover { background-color: #450a0a !important; }
  html.dark .hover\:bg-blue-50:hover { background-color: #1e3a5f !important; }
</style>
<script>
  // Dark mode persistence - check on page load
  (function() {
    const darkMode = localStorage.getItem('darkMode');
    if (darkMode === '1') {
      document.documentElement.classList.add('dark');
    } else if (darkMode === null) {
      // Check system preference if not set
      if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
        document.documentElement.classList.add('dark');
        localStorage.setItem('darkMode', '1');
      }
    }
  })();
</script>
