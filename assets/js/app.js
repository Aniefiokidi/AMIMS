/* AMIMS — Shared JavaScript */

document.addEventListener('DOMContentLoaded', function () {

  // ── Hamburger / Sidebar toggle ──────────────────────────────────────
  const hamburger = document.getElementById('hamburger');
  const sidebar   = document.getElementById('sidebar');

  if (hamburger && sidebar) {
    hamburger.addEventListener('click', function () {
      sidebar.classList.toggle('open');
    });
    document.addEventListener('click', function (e) {
      if (sidebar.classList.contains('open') &&
          !sidebar.contains(e.target) &&
          e.target !== hamburger) {
        sidebar.classList.remove('open');
      }
    });
  }

  // ── Auto-dismiss alerts after 5 seconds ────────────────────────────
  document.querySelectorAll('.alert').forEach(function (el) {
    setTimeout(function () {
      el.style.opacity = '0';
      el.style.transition = 'opacity 0.5s';
      setTimeout(function () { el.remove(); }, 500);
    }, 5000);
  });

  // ── Confirm-delete links ────────────────────────────────────────────
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      const msg = el.getAttribute('data-confirm') || 'Are you sure you want to delete this record? This action cannot be undone.';
      if (!confirm(msg)) {
        e.preventDefault();
      }
    });
  });

  // ── Notification tabs ───────────────────────────────────────────────
  document.querySelectorAll('.notif-tab').forEach(function (tab) {
    tab.addEventListener('click', function () {
      document.querySelectorAll('.notif-tab').forEach(function (t) { t.classList.remove('active'); });
      tab.classList.add('active');
      const target = tab.getAttribute('data-tab');
      document.querySelectorAll('.notif-section').forEach(function (sec) {
        sec.style.display = (target === 'all' || sec.getAttribute('data-type') === target) ? '' : 'none';
      });
    });
  });

  // ── Report card selection ───────────────────────────────────────────
  document.querySelectorAll('.report-card').forEach(function (card) {
    card.addEventListener('click', function () {
      document.querySelectorAll('.report-card').forEach(function (c) { c.classList.remove('selected'); });
      card.classList.add('selected');
      const typeInput = document.getElementById('report_type');
      if (typeInput) {
        typeInput.value = card.getAttribute('data-type');
      }
    });
  });

  // ── AJAX mark notification as read ──────────────────────────────────
  document.querySelectorAll('.notif-card[data-id]').forEach(function (card) {
    card.addEventListener('click', function () {
      const id   = card.getAttribute('data-id');
      const link = card.getAttribute('data-link');
      fetch(BASE_URL + 'modules/notifications/mark_read.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'notif_id=' + encodeURIComponent(id) + '&csrf_token=' + encodeURIComponent(CSRF_TOKEN)
      }).then(function () {
        card.classList.remove('unread');
        const dot = card.querySelector('.notif-dot');
        if (dot) dot.remove();
        if (link) window.location.href = link;
      });
    });
  });

  // ── Searchable asset select (maintenance/create) ─────────────────────
  const assetSearch = document.getElementById('asset_search');
  const assetSelect = document.getElementById('asset_id');
  if (assetSearch && assetSelect) {
    const options = Array.from(assetSelect.options);
    assetSearch.addEventListener('input', function () {
      const q = assetSearch.value.toLowerCase();
      Array.from(assetSelect.options).forEach(function (opt) {
        opt.style.display = opt.text.toLowerCase().includes(q) ? '' : 'none';
      });
    });
  }

  // ── Animate stat values on load ──────────────────────────────────────
  document.querySelectorAll('.stat-value[data-target]').forEach(function (el) {
    const target = parseInt(el.getAttribute('data-target'), 10);
    if (isNaN(target)) return;
    let current = 0;
    const step = Math.max(1, Math.floor(target / 30));
    const timer = setInterval(function () {
      current = Math.min(current + step, target);
      el.textContent = current.toLocaleString();
      if (current >= target) clearInterval(timer);
    }, 30);
  });

});

// Expose for inline use
var BASE_URL   = window.BASE_URL   || '';
var CSRF_TOKEN = window.CSRF_TOKEN || '';
