/* HearMed — Auto Back Button
   Inserts AFTER hearmed.js renders content (which wipes #hm-app innerHTML).
   Shows on all pages except the main calendar. */
(function() {
  var attempts = 0;

  function tryInsert() {
    var app = document.getElementById('hm-app');
    if (!app) return;

    // Skip calendar
    if (app.getAttribute('data-view') === 'calendar') return;

    // Look for a header that hearmed.js has rendered
    var header = app.querySelector('.hm-admin-hd')
              || app.querySelector('.hm-page-header')
              || app.querySelector('.hm-sc');

    // Also try: first h2 inside #hm-app
    var h2 = app.querySelector('h2');

    var target = header || (h2 ? h2.parentNode : null);

    if (!target && attempts < 20) {
      // hearmed.js hasn't rendered yet, retry
      attempts++;
      setTimeout(tryInsert, 200);
      return;
    }

    if (!target) return;

    // Don't double-insert
    if (app.querySelector('.hm-back-btn')) return;

    var btn = document.createElement('a');
    btn.href = 'javascript:void(0)';
    btn.className = 'hm-back-btn';
    btn.innerHTML = '&#8592;';
    btn.title = 'Back';
    btn.onclick = function(e) { e.preventDefault(); history.back(); };

    if (header) {
      header.insertBefore(btn, header.firstChild);
    } else if (h2) {
      h2.parentNode.insertBefore(btn, h2);
    }
  }

  // Start trying after DOM is ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() { setTimeout(tryInsert, 500); });
  } else {
    setTimeout(tryInsert, 500);
  }
})();