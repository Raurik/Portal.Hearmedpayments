/**
 * HearMed Portal - Notifications Module JS
 *
 * Handles:
 * - Initial load of notifications + day stats
 * - 30s polling for new notifications
 * - Toast slide-in from top-right (6s auto-dismiss + progress bar)
 * - Section collapse / expand
 * - Mark read, mark all read, clear, clear section
 * - Notification log view
 *
 * Depends on: HM global (HM.ajax_url, HM.nonce), jQuery
 * Config:     window.hmNotifConfig (injected by PHP shortcode)
 */
(function($) {
    'use strict';

    if (typeof HM === 'undefined') return;

    var cfg = window.hmNotifConfig || {};
    var classes = cfg.classes || {};
    var POLL_INTERVAL = 30000; // 30 seconds
    var TOAST_DURATION = 6000; // 6 seconds
    var lastPollTime = '';
    var knownIds = {};
    var pollTimer = null;
    var isLogView = false;

    /* ═══════════════════════════════════════════════════════════════════
       INIT
       ═══════════════════════════════════════════════════════════════════ */
    $(function() {
        if (!$('.hearmed-notifications').length) return;

        loadNotifications();
        loadDayStats();
        bindEvents();

        // Start poll loop
        pollTimer = setInterval(pollForNew, POLL_INTERVAL);
    });

    /* ═══════════════════════════════════════════════════════════════════
       LOAD ALL NOTIFICATIONS
       ═══════════════════════════════════════════════════════════════════ */
    function loadNotifications() {
        $.post(HM.ajax_url, {
            action: 'hm_notifications_get',
            nonce: HM.nonce
        }, function(r) {
            if (!r.success) return;
            renderNotifications(r.data.notifications || []);
        });
    }

    function renderNotifications(notifications) {
        // Group by visual class
        var groups = {};
        $.each(classes, function(key) { groups[key] = []; });

        $.each(notifications, function(_, n) {
            knownIds[n.id] = true;
            var cls = n['class'] || 'internal';
            if (!groups[cls]) groups[cls] = [];
            groups[cls].push(n);
        });

        // Render each section
        $.each(groups, function(cls, items) {
            var $body = $('#section-' + cls);
            var $count = $('#count-' + cls);

            if (!items.length) {
                $body.html('<div class="hm-notif-empty">No notifications</div>');
                $count.text('0').removeClass('has-unread');
                return;
            }

            var unread = 0;
            var html = '';
            $.each(items, function(_, n) {
                if (!n.is_read) unread++;
                html += buildNotifRow(n);
            });

            $body.html(html);
            $count.text(unread || items.length);
            if (unread > 0) {
                $count.addClass('has-unread');
            } else {
                $count.removeClass('has-unread');
            }
        });

        // Track last timestamp
        if (notifications.length) {
            lastPollTime = notifications[0].created_at;
        }
    }

    function buildNotifRow(n) {
        var readClass = n.is_read ? '' : ' unread';
        var timeAgo = formatTimeAgo(n.created_at);
        var priorityHtml = '';
        if (n.priority === 'High') {
            priorityHtml = '<span class="hm-notif-row__priority hm-notif-row__priority--high">High</span>';
        } else if (n.priority === 'Urgent') {
            priorityHtml = '<span class="hm-notif-row__priority hm-notif-row__priority--urgent">Urgent</span>';
        }

        return '<div class="hm-notif-row' + readClass + '" ' +
            'data-class="' + esc(n['class']) + '" ' +
            'data-id="' + n.id + '" ' +
            'data-recipient="' + n.recipient_id + '" ' +
            'data-entity-type="' + esc(n.entity_type || '') + '" ' +
            'data-entity-id="' + (n.entity_id || '') + '">' +
            '<div class="hm-notif-row__content">' +
                '<div class="hm-notif-row__subject">' + esc(n.subject) + '</div>' +
                '<div class="hm-notif-row__message">' + esc(n.message) + '</div>' +
            '</div>' +
            '<div class="hm-notif-row__meta">' +
                '<div class="hm-notif-row__time">' + timeAgo + '</div>' +
                '<div class="hm-notif-row__from">' + esc(n.created_by_name) + '</div>' +
                priorityHtml +
            '</div>' +
            '<button class="hm-notif-row__dismiss" data-dismiss="' + n.recipient_id + '" title="Clear to log">&times;</button>' +
        '</div>';
    }

    /* ═══════════════════════════════════════════════════════════════════
       LOAD DAY STATS
       ═══════════════════════════════════════════════════════════════════ */
    function loadDayStats() {
        $.post(HM.ajax_url, {
            action: 'hm_notifications_day_stats',
            nonce: HM.nonce
        }, function(r) {
            if (!r.success) return;
            var d = r.data;

            setTile('appointments', d.appointments);
            setTile('tests', d.tests);
            setTile('calls', d.calls);
            setTile('repairs', d.repairs, d.repairs > 0);
            setTile('fittings', d.fittings);
            setTile('ha_not_received', d.ha_not_received, d.ha_not_received > 0);
        });
    }

    function setTile(key, val, isAlert) {
        var id = 'tile-' + key.replace(/_/g, '-');
        var $el = $('#' + id);
        if (!$el.length) return;

        $el.text(val);
        var $tile = $el.closest('.hm-day-tile');
        if (isAlert) {
            $tile.addClass('hm-day-tile--alert');
        } else {
            $tile.removeClass('hm-day-tile--alert');
        }
    }

    /* ═══════════════════════════════════════════════════════════════════
       POLLING — 30s interval
       ═══════════════════════════════════════════════════════════════════ */
    function pollForNew() {
        if (isLogView) return;

        $.post(HM.ajax_url, {
            action: 'hm_notifications_poll',
            nonce: HM.nonce,
            since: lastPollTime
        }, function(r) {
            if (!r.success) return;
            var items = r.data.notifications || [];
            if (!items.length) return;

            // Update last poll time
            lastPollTime = items[0].created_at;

            // Show toast for each truly new notification
            $.each(items, function(_, n) {
                if (knownIds[n.id]) return;
                knownIds[n.id] = true;
                showToast(n);
            });

            // Reload full list to ensure accurate counts
            loadNotifications();
            loadDayStats();
        });
    }

    /* ═══════════════════════════════════════════════════════════════════
       TOAST — top-right slide-in, 6s dismiss, progress bar
       ═══════════════════════════════════════════════════════════════════ */
    function showToast(n) {
        ensureToastContainer();

        var classLabel = (classes[n['class']] || {}).label || n['class'];

        var $toast = $('<div class="hm-notif-toast" data-class="' + esc(n['class']) + '">' +
            '<div class="hm-notif-toast__class">' + esc(classLabel) + '</div>' +
            '<div class="hm-notif-toast__subject">' + esc(n.subject) + '</div>' +
            '<div class="hm-notif-toast__message">' + esc(n.message) + '</div>' +
            '<button class="hm-notif-toast__close">&times;</button>' +
            '<div class="hm-notif-toast__progress"></div>' +
        '</div>');

        $('.hm-notif-toast-container').prepend($toast);

        // Trigger slide-in
        setTimeout(function() { $toast.addClass('show'); }, 20);

        // Auto-dismiss after 6s
        var dismissTimer = setTimeout(function() { dismissToast($toast); }, TOAST_DURATION);

        // Pause on hover
        $toast.on('mouseenter', function() {
            clearTimeout(dismissTimer);
            $toast.find('.hm-notif-toast__progress').css('animation-play-state', 'paused');
        });
        $toast.on('mouseleave', function() {
            dismissTimer = setTimeout(function() { dismissToast($toast); }, 2000);
            $toast.find('.hm-notif-toast__progress').css('animation-play-state', 'running');
        });

        // Manual dismiss
        $toast.find('.hm-notif-toast__close').on('click', function(e) {
            e.stopPropagation();
            clearTimeout(dismissTimer);
            dismissToast($toast);
        });

        // Click toast body — navigate to notifications page
        $toast.on('click', function() {
            window.location.href = '/notifications/';
        });
    }

    function dismissToast($toast) {
        $toast.addClass('hiding');
        setTimeout(function() { $toast.remove(); }, 350);
    }

    function ensureToastContainer() {
        if (!$('.hm-notif-toast-container').length) {
            $('body').append('<div class="hm-notif-toast-container"></div>');
        }
    }

    /* ═══════════════════════════════════════════════════════════════════
       EVENTS
       ═══════════════════════════════════════════════════════════════════ */
    function bindEvents() {
        var $root = $('.hearmed-notifications');

        // Section collapse/expand
        $root.on('click', '.hm-notif-section__header', function(e) {
            // Don't toggle if clicking Clear button
            if ($(e.target).closest('.hm-notif-section__clear').length) return;
            $(this).closest('.hm-notif-section').toggleClass('collapsed');
        });

        // Click notification row — mark read + navigate to entity
        $root.on('click', '.hm-notif-row', function(e) {
            if ($(e.target).closest('.hm-notif-row__dismiss').length) return;

            var $row = $(this);
            var recipientId = $row.data('recipient');
            var entityType = $row.data('entity-type');
            var entityId = $row.data('entity-id');

            // Mark as read
            if ($row.hasClass('unread')) {
                markRead(recipientId, function() {
                    $row.removeClass('unread');
                    updateSectionCount($row.data('class'));
                });
            }

            // Navigate if entity link exists
            var url = entityUrl(entityType, entityId);
            if (url) {
                window.location.href = url;
            }
        });

        // Dismiss single notification
        $root.on('click', '.hm-notif-row__dismiss', function(e) {
            e.stopPropagation();
            var $btn = $(this);
            var recipientId = $btn.data('dismiss');
            var $row = $btn.closest('.hm-notif-row');
            var cls = $row.data('class');

            clearNotification(recipientId, function() {
                $row.fadeOut(200, function() {
                    $row.remove();
                    updateSectionCount(cls);
                    checkSectionEmpty(cls);
                });
            });
        });

        // Clear entire section
        $root.on('click', '.hm-notif-section__clear', function(e) {
            e.stopPropagation();
            var section = $(this).data('clear-section');
            clearSection(section, function() {
                $('#section-' + section).html('<div class="hm-notif-empty">No notifications</div>');
                $('#count-' + section).text('0').removeClass('has-unread');
            });
        });

        // Mark all read
        $('#hm-notif-mark-all-read').on('click', function() {
            markAllRead(function() {
                $root.find('.hm-notif-row.unread').removeClass('unread');
                $root.find('.hm-notif-section__count').text(function() {
                    return $(this).text(); // keep total count, just remove unread class
                }).removeClass('has-unread');
            });
        });

        // Toggle log view
        $('#hm-notif-toggle-log').on('click', function() {
            showLogView();
        });

        // Back from log
        $('#hm-notif-back-to-live').on('click', function() {
            hideLogView();
        });
    }

    /* ═══════════════════════════════════════════════════════════════════
       AJAX ACTIONS
       ═══════════════════════════════════════════════════════════════════ */
    function markRead(recipientId, cb) {
        $.post(HM.ajax_url, {
            action: 'hm_notifications_mark_read',
            nonce: HM.nonce,
            recipient_id: recipientId
        }, function(r) {
            if (r.success && cb) cb();
        });
    }

    function markAllRead(cb) {
        $.post(HM.ajax_url, {
            action: 'hm_notifications_mark_all_read',
            nonce: HM.nonce
        }, function(r) {
            if (r.success && cb) cb();
        });
    }

    function clearNotification(recipientId, cb) {
        $.post(HM.ajax_url, {
            action: 'hm_notifications_clear',
            nonce: HM.nonce,
            recipient_id: recipientId
        }, function(r) {
            if (r.success && cb) cb();
        });
    }

    function clearSection(section, cb) {
        $.post(HM.ajax_url, {
            action: 'hm_notifications_clear_section',
            nonce: HM.nonce,
            section: section
        }, function(r) {
            if (r.success && cb) cb();
        });
    }

    /* ═══════════════════════════════════════════════════════════════════
       LOG VIEW
       ═══════════════════════════════════════════════════════════════════ */
    function showLogView() {
        isLogView = true;
        $('.hm-notif-day-panel, .hm-notif-sections, .hm-page-header').hide();
        $('#hm-notif-log').show();
        loadLog();
    }

    function hideLogView() {
        isLogView = false;
        $('#hm-notif-log').hide();
        $('.hm-notif-day-panel, .hm-notif-sections, .hm-page-header').show();
    }

    function loadLog() {
        $.post(HM.ajax_url, {
            action: 'hm_notifications_get_log',
            nonce: HM.nonce
        }, function(r) {
            if (!r.success) return;
            var items = r.data.log || [];
            var $body = $('#hm-notif-log-body');

            if (!items.length) {
                $body.html('<div class="hm-notif-empty">No cleared notifications</div>');
                return;
            }

            var html = '';
            $.each(items, function(_, n) {
                var cls = n['class'] || 'internal';
                var clsDef = classes[cls] || {};
                var bgStyle = 'background:' + (clsDef.bg || '#f8fafc') + ';color:' + (clsDef.color || '#64748b') + ';';

                html += '<div class="hm-notif-log-row">' +
                    '<span class="hm-notif-log-row__class" style="' + bgStyle + '">' + esc(clsDef.label || cls) + '</span>' +
                    '<span class="hm-notif-log-row__subject">' + esc(n.subject) + '</span>' +
                    '<span class="hm-notif-log-row__time">' + formatTimeAgo(n.dismissed_at || n.created_at) + '</span>' +
                '</div>';
            });

            $body.html(html);
        });
    }

    /* ═══════════════════════════════════════════════════════════════════
       HELPERS
       ═══════════════════════════════════════════════════════════════════ */
    function updateSectionCount(cls) {
        var $section = $('.hm-notif-section[data-class="' + cls + '"]');
        var unread = $section.find('.hm-notif-row.unread').length;
        var total = $section.find('.hm-notif-row').length;
        var $count = $('#count-' + cls);

        $count.text(unread || total);
        if (unread > 0) {
            $count.addClass('has-unread');
        } else {
            $count.removeClass('has-unread');
        }
    }

    function checkSectionEmpty(cls) {
        var $body = $('#section-' + cls);
        if (!$body.find('.hm-notif-row').length) {
            $body.html('<div class="hm-notif-empty">No notifications</div>');
        }
    }

    function entityUrl(type, id) {
        if (!type || !id) return '';
        var map = {
            'patient':     '/patients/?id=' + id,
            'order':       '/patients/?id=' + id + '&tab=orders',
            'appointment': '/calendar/',
            'repair':      '/repairs/?id=' + id,
            'invoice':     '/accounting/?invoice=' + id,
        };
        return map[type] || '';
    }

    function formatTimeAgo(dateStr) {
        if (!dateStr) return '';
        var d = new Date(dateStr);
        var now = new Date();
        var diff = Math.floor((now - d) / 1000);

        if (diff < 60) return 'Just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
        return d.toLocaleDateString('en-AU', { day: 'numeric', month: 'short' });
    }

    function esc(str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

})(jQuery);
