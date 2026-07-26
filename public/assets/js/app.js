/* FILE: /public/assets/js/app.js
   AK Cloud — lightweight vanilla JS (no CDN). Sidebar toggle, theme,
   confirm dialogs, and a small AJAX helper. */
(function () {
  'use strict';

  // Mobile sidebar toggle.
  document.addEventListener('click', function (e) {
    if (e.target.closest('.menu-btn')) {
      document.querySelector('.sidebar')?.classList.toggle('open');
    }
    // Confirm-before-submit / navigate.
    var c = e.target.closest('[data-confirm]');
    if (c && !window.confirm(c.getAttribute('data-confirm'))) {
      e.preventDefault();
      e.stopPropagation();
    }
  });

  // Dark mode toggle (persisted).
  window.toggleTheme = function () {
    var root = document.documentElement;
    var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
    root.setAttribute('data-theme', next);
    try { localStorage.setItem('akc-theme', next); } catch (_) {}
  };
  try {
    var saved = localStorage.getItem('akc-theme');
    if (saved) document.documentElement.setAttribute('data-theme', saved);
  } catch (_) {}

  // Tiny AJAX helper: akc.post(url, data).then(json).
  window.akc = {
    token: function () {
      var m = document.querySelector('meta[name="csrf-token"]');
      return m ? m.getAttribute('content') : '';
    },
    post: function (url, data) {
      var body = new FormData();
      body.append('_token', this.token());
      Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
      return fetch(url, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': this.token() },
        body: body,
      }).then(function (r) { return r.json(); });
    },
    get: function (url) {
      return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (r) { return r.json(); });
    },
  };

  // Auto-dismiss flash alerts after 6s.
  setTimeout(function () {
    document.querySelectorAll('.alert[data-auto]').forEach(function (el) { el.style.display = 'none'; });
  }, 6000);
})();
