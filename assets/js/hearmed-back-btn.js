/* HearMed Auto Back Button
   Adds a small top-left teal back link on subsection pages.
   Routes to the correct parent section. */
(function() {
  var attempts = 0;

  function normalizePath(pathname) {
    if (!pathname) return '/';
    var path = pathname.replace(/\/+$/, '');
    return path === '' ? '/' : path;
  }

  /* Find the portal container — could be #hm-app, .hm-admin, .hm-page, or .hm-sc */
  function findContainer() {
    return document.getElementById('hm-app')
      || document.querySelector('.hm-admin')
      || document.querySelector('.hm-page')
      || document.querySelector('.hm-sc');
  }

  function hasExistingBack(container) {
    if (container.querySelector('.hm-back-btn')) return true;
    var links = container.querySelectorAll('a');
    for (var i = 0; i < links.length; i++) {
      var t = (links[i].textContent || '').trim();
      if (/^←\s*back/i.test(t)) return true;
    }
    return false;
  }

  function getParentUrl() {
    var url  = new URL(window.location.href);
    var path = normalizePath(url.pathname);
    var qs   = url.searchParams;
    var o    = url.origin;

    /* Admin console sub-pages → back to /admin-console/ */
    if (path.indexOf('/admin-console') === 0) {
      return path === '/admin-console' ? null : o + '/admin-console/';
    }

    /* Portal section roots */
    var roots = ['patients','reports','orders','accounting','calendar',
                 'repairs','kpi','cash','approvals','notifications','team-chat',
                 'commissions'];
    for (var i = 0; i < roots.length; i++) {
      var rp = '/' + roots[i];
      if (path === rp) {
        return qs.toString() ? o + rp + '/' : null;
      }
      if (path.indexOf(rp + '/') === 0) return o + rp + '/';
    }

    /* Query-string driven sub-views */
    if (qs.has('hm_action') || qs.has('action') || qs.has('view') ||
        qs.has('id') || qs.has('patient_id') || qs.has('order_id') ||
        qs.has('report') || qs.has('staff')) {
      return o + path + '/';
    }

    return null;
  }

  function tryInsert() {
    var container = findContainer();
    if (!container) {
      if (attempts < 25) { attempts++; setTimeout(tryInsert, 200); }
      return;
    }

    if (hasExistingBack(container)) return;

    var parentUrl = getParentUrl();
    if (!parentUrl) return;

    var header = container.querySelector('.hm-admin-hd')
      || container.querySelector('.hm-page-header')
      || container.querySelector('.hm-page-hd')
      || container.querySelector('.hm-sc-title');

    var h = container.querySelector('h2, h1');
    var target = header || (h ? h.parentNode : null) || container;

    var btn = document.createElement('a');
    btn.href = parentUrl;
    btn.className = 'hm-back-btn';
    btn.textContent = '← Back';
    btn.title = 'Back';

    target.insertBefore(btn, target.firstChild);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() { setTimeout(tryInsert, 250); });
  } else {
    setTimeout(tryInsert, 250);
  }
})();