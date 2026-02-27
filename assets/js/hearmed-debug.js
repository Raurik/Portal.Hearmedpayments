/**
 * HearMed Debug Page — AJAX Health Checks
 *
 * Loaded only on the HearMed Debug admin page (tools_page_hearmed-debug).
 *
 * Depends on: jQuery, HMDebug (localised by admin-debug.php)
 */
/* global HMDebug */
(function ($) {
    'use strict';

    /**
     * Sensitive field keys that must be redacted in preview output.
     * Matches against lower-cased field names.
     */
    var SENSITIVE_FIELDS = [
        'first_name', 'last_name', 'name', 'patient_name',
        'email', 'patient_email', 'phone', 'patient_phone',
        'mobile', 'address', 'dob', 'date_of_birth',
        'pps', 'ppsn', 'eircode', 'iban', 'bic',
    ];

    /**
     * Redact sensitive keys from an object (shallow, first-level only).
     *
     * @param {Object} obj
     * @returns {Object}
     */
    function redactRecord(obj) {
        if (!obj || typeof obj !== 'object') return obj;
        var out = {};
        Object.keys(obj).forEach(function (k) {
            if (SENSITIVE_FIELDS.indexOf(k.toLowerCase()) !== -1) {
                out[k] = '[REDACTED]';
            } else {
                out[k] = obj[k];
            }
        });
        return out;
    }

    /**
     * Run a single AJAX health check against an existing HearMed endpoint.
     *
     * @param {string} action   – wp_ajax action name (e.g. 'hm_get_clinics')
     * @param {Object} extraData – additional POST fields
     * @param {jQuery} $row      – the .hm-ajax-row element for this check
     */
    function runCheck(action, extraData, $row) {
        var $btn    = $row.find('.hm-debug-run-btn');
        var $status = $row.find('.hm-ajax-status');
        var $result = $row.find('.hm-ajax-result');

        $btn.prop('disabled', true).text('Running…');
        $status.text('').removeClass('hm-debug-ok hm-debug-err');
        $result.hide().text('');

        var postData = $.extend({
            action: action,
            nonce:  HMDebug.hmNonce,
        }, extraData || {});

        $.post(HMDebug.ajaxUrl, postData)
            .done(function (parsed, textStatus, jqXHR) {
                $btn.prop('disabled', false).text('Run');

                var httpCode = jqXHR ? jqXHR.status : '?';
                var data     = parsed && parsed.success ? parsed.data : null;
                var count    = Array.isArray(data) ? data.length : (data ? 1 : 0);

                $status
                    .addClass('hm-debug-ok')
                    .text('OK — ' + count + ' record(s)  [HTTP ' + httpCode + ']');

                var preview = 'No data returned.';
                if (Array.isArray(data) && data.length > 0) {
                    preview = JSON.stringify(redactRecord(data[0]), null, 2);
                } else if (data && typeof data === 'object' && Object.keys(data).length > 0) {
                    preview = JSON.stringify(redactRecord(data), null, 2);
                }

                $result.text(preview).show();
            })
            .fail(function (jqXHR, textStatus) {
                $btn.prop('disabled', false).text('Run');

                var httpCode = jqXHR ? jqXHR.status : '?';
                var parsed;
                try {
                    parsed = JSON.parse(jqXHR.responseText);
                } catch (e) {
                    parsed = null;
                }

                var errMsg = (parsed && parsed.data)
                    ? (typeof parsed.data === 'string' ? parsed.data : JSON.stringify(parsed.data))
                    : textStatus;

                $status
                    .addClass('hm-debug-err')
                    .text('Failed — ' + errMsg + '  [HTTP ' + httpCode + ']');
                $result.text(jqXHR.responseText ? String(jqXHR.responseText).substring(0, 400) : '(empty response)').show();
            });
    }

    /**
     * Attach click handlers to all AJAX check buttons on page ready.
     */
    $(function () {
        // Wire up "Run All" button
        $('#hm-debug-run-all').on('click', function () {
            $('.hm-debug-run-btn').each(function () {
                $(this).trigger('click');
            });
        });

        // Individual check buttons
        $('.hm-ajax-row').each(function () {
            var $row    = $(this);
            var action  = $row.data('action');
            var extra   = {};

            // hm_get_patients: request first page only
            if (action === 'hm_get_patients') {
                extra = { page: 1 };
            }

            $row.find('.hm-debug-run-btn').on('click', function () {
                runCheck(action, extra, $row);
            });
        });
    });

}(jQuery));
