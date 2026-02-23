/**
 * HearMed Portal — Viewport Height Fix
 * Ensures pages display at full 100vh without header/footer forcing additional space
 */

document.addEventListener('DOMContentLoaded', function () {
    // Remove all min-height constraints that might add extra space
    var heightTargets = [
        'html', 'body', 'main',
        '.site-content', '.content', '#main',
        '.hm-app-shell', '.hm-sidebar', '.hm-stage', '.hm-topbar', '.hm-content',
        '.elementor', '.elementor-page-body',
        '#hm-app',
    ];
    
    heightTargets.forEach(function (sel) {
        var els = document.querySelectorAll(sel);
        els.forEach(function(el) {
            // Remove min-height constraints
            el.style.removeProperty('min-height');
            el.style.removeProperty('min_height');
            // Ensure full height
            el.style.height = 'auto';
        });
    });

    // Fix Elementor widgets
    var content = document.querySelector('.hm-content, main, .content');
    if (content) {
        content.querySelectorAll('.elementor-widget-wrap, .elementor-widget, .wp-block-button').forEach(function(el) {
            el.style.removeProperty('min-height');
        });
    }

    // Hide any footer text elements showing "HearMed Portal" or similar
    document.querySelectorAll('footer, [class*="footer"], [class*="copyright"], [class*="footer-text"]').forEach(function(el) {
        el.style.display = 'none';
    });

    // Ensure viewport fills entire screen
    document.documentElement.style.height = '100vh';
    document.body.style.height = '100vh';
});

// Also run on window load to catch late-loading content
window.addEventListener('load', function() {
    document.documentElement.style.height = '100vh';
    document.body.style.height = '100vh';
});
