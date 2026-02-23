/**
 * HearMed Portal — Viewport Height Fix
 * Ensures pages display at full 100vh without header/footer forcing additional space
 */

// Run immediately, don't wait for DOMContentLoaded
(function() {
    function hideHeadersFooters() {
        var i;

        // Hide all header-like elements
        var headerSelectors = [
            'header', '.site-header', '.header', '.site-header-wrapper', '.theme-header',
            '#site-header', '#header', '.masthead', 'nav', '.navbar',
            '[class*="header"]', '[id*="header"]', '.wp-block-template-part[area="header"]'
        ];

        headerSelectors.forEach(function(sel) {
            try {
                var els = document.querySelectorAll(sel);
                els.forEach(function(el) {
                    if (el) {
                        el.style.display = 'none !important';
                        el.style.visibility = 'hidden';
                        el.style.height = '0';
                        el.style.overflow = 'hidden';
                    }
                });
            } catch(e) {}
        });

        // Hide all footer-like elements
        var footerSelectors = [
            'footer', '.site-footer', '.footer', '.site-footer-wrapper', '.theme-footer',
            '#site-footer', '#footer', '[class*="footer"]', '[id*="footer"]',
            '.wp-block-template-part[area="footer"]', '[class*="copyright"]',
            '[class*="footer-text"]', '[class*="footer-credit"]', '.footer-info'
        ];

        footerSelectors.forEach(function(sel) {
            try {
                var els = document.querySelectorAll(sel);
                els.forEach(function(el) {
                    if (el) {
                        el.style.display = 'none !important';
                        el.style.visibility = 'hidden';
                        el.style.height = '0';
                        el.style.overflow = 'hidden';
                    }
                });
            } catch(e) {}
        });

        // Remove min-height constraints
        var heightTargets = [
            'html', 'body', 'main',
            '.site-content', '.content', '#main',
            '.hm-app-shell', '.hm-sidebar', '.hm-stage', '.hm-topbar', '.hm-content',
            '.elementor', '.elementor-page-body',
            '#hm-app',
        ];

        heightTargets.forEach(function(sel) {
            try {
                var els = document.querySelectorAll(sel);
                els.forEach(function(el) {
                    if (el) {
                        el.style.removeProperty('min-height');
                        el.style.removeProperty('min_height');
                        el.style.height = '100vh';
                    }
                });
            } catch(e) {}
        });

        // Force show main content
        try {
            document.documentElement.style.height = '100vh';
            document.body.style.height = '100vh';
        } catch(e) {}
    }

    // Run immediately
    hideHeadersFooters();

    // Run again on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', hideHeadersFooters);
    }

    // Run again on full load
    window.addEventListener('load', hideHeadersFooters);

    // Monitor for dynamic changes
    var observer = new MutationObserver(function(mutations) {
        hideHeadersFooters();
    });

    try {
        observer.observe(document.body, {
            childList: true,
            subtree: true,
            attributes: false,
        });
    } catch(e) {}
})();

