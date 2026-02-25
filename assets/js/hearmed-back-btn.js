/* HearMed Auto Back Button
   Adds a small top-left back link on subsection pages.
   Parent routes are preferred over browser history. */
(function() {
  var attempts = 0;

  function normalizePath(pathname) {
    if (!pathname) return '/';
    var path = pathname.replace(/\/+$/, '');
    return path === '' ? '/' : path;
  }

  function hasExistingBackAnchor(app) {
    var links = app.querySelectorAll('a');
    for (var i = 0; i < links.length; i++) {
      var text = (links[i].textContent || '').trim().toLowerCase();
      if (text.indexOf('back') !== -1) return true;
    }
    return false;
  }

  function getParentUrl() {
    var url = new URL(window.location.href);
    var path = normalizePath(url.pathname);
    var query = url.searchParams;
    var origin = url.origin;

    if (path.indexOf('/admin-console') === 0) {
      return path === '/admin-console' ? null : origin + '/admin-console/';
    }

    var sectionRoots = ['patients', 'reports', 'orders', 'accounting', 'calendar', 'repairs', 'kpi', 'cash', 'approvals', 'notifications', 'team-chat'];
    for (var i = 0; i < sectionRoots.length; i++) {
      var root = sectionRoots[i];
      var rootPath = '/' + root;
      if (path === rootPath) {
        if (query.toString()) return origin + rootPath + '/';
        return null;
      }
      if (path.indexOf(rootPath + '/') === 0) {
        return origin + rootPath + '/';
      }
    }

    if (query.has('hm_action') || query.has('action') || query.has('view') || query.has('id') || query.has('patient_id') || query.has('order_id') || query.has('report')) {
      return origin + path + '/';
    }

    return null;
  }

  function tryInsert() {
    var app = document.getElementById('hm-app');
    if (!app) return;

    if (app.querySelector('.hm-back-btn') || hasExistingBackAnchor(app)) return;

    var parentUrl = getParentUrl();
    if (!parentUrl) return;

    var header = app.querySelector('.hm-admin-hd')
      || app.querySelector('.hm-page-header')
      || app.querySelector('.hm-page-hd')
      || app.querySelector('.hm-sc');

    var h2 = app.querySelector('h2, h1');
    var target = header || (h2 ? h2.parentNode : null);

    if (!target && attempts < 20) {
      attempts++;
      setTimeout(tryInsert, 200);
      return;
    }

    if (!target) return;

    var btn = document.createElement('a');
    btn.href = parentUrl;
    btn.className = 'hm-back-btn';
    btn.textContent = '← Back';
    btn.title = 'Back';

    target.insertBefore(btn, target.firstChild);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      setTimeout(tryInsert, 250);
    });
  } else {
    setTimeout(tryInsert, 250);
  }
})();