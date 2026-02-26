/**
 * HearMed Repairs — standalone repairs page
 *
 * Loaded on pages containing [hearmed_repairs] shortcode.
 * Depends on hearmed-core.js (provides HM global with ajax_url, nonce).
 */
(function($){
    'use strict';

    console.log('[HM Repairs] JS file loaded');

    if (typeof HM === 'undefined') {
        console.error('[HM Repairs] HM global not found — hearmed-core.js may not be loaded');
        return;
    }

    var ajaxUrl = HM.ajax_url || HM.ajax;
    var nonce   = HM.nonce;
    var allRepairs = [];

    console.log('[HM Repairs] Using ajaxUrl:', ajaxUrl, 'nonce:', nonce ? 'present' : 'MISSING');

    function esc(s) { return $('<span>').text(s || '').html(); }
    function fmtDate(d) {
        if (!d || d === 'null') return '—';
        var p = String(d).split('-');
        return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : d;
    }

    function loadClinics() {
        $.post(ajaxUrl, { action: 'hm_get_clinics', nonce: nonce }, function(r) {
            if (r && r.success && r.data) {
                r.data.forEach(function(c) {
                    var cid = c.id || c._ID;
                    $('#hm-repair-filter-clinic').append('<option value="' + cid + '">' + esc(c.name) + '</option>');
                });
            }
        });
    }

    function loadRepairs() {
        $('#hm-repairs-table').html('<div class="hm-empty"><div class="hm-empty-text">Loading repairs…</div></div>');
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: { action: 'hm_get_all_repairs', nonce: nonce },
            dataType: 'json',
            timeout: 15000,
            success: function(r) {
                console.log('[HM Repairs] AJAX response:', r);
                if (!r || !r.success) {
                    var msg = (r && r.data) ? (typeof r.data === 'string' ? r.data : JSON.stringify(r.data)) : 'Unknown error';
                    $('#hm-repairs-table').html('<div class="hm-empty"><div class="hm-empty-text" style="color:#dc2626;">Error: ' + esc(String(msg)) + '</div></div>');
                    return;
                }
                // Handle both response formats: direct array or {repairs:[], _diag:{}}
                var data = r.data;
                if (data && data.repairs) {
                    if (data._diag) console.log('[HM Repairs] Server diagnostics:', data._diag);
                    allRepairs = data.repairs;
                } else if (Array.isArray(data)) {
                    allRepairs = data;
                } else {
                    console.warn('[HM Repairs] Unexpected response format:', data);
                    allRepairs = [];
                }
                console.log('[HM Repairs] Loaded ' + allRepairs.length + ' repairs');
                renderStats();
                renderTable();
            },
            error: function(xhr, status, err) {
                var detail = '';
                try {
                    detail = xhr.responseText ? xhr.responseText.substring(0, 300) : err;
                } catch(e) {
                    detail = err || status;
                }
                $('#hm-repairs-table').html(
                    '<div class="hm-empty"><div class="hm-empty-text" style="color:#dc2626;">AJAX error: ' + esc(status) + ' — ' + esc(detail) + '</div></div>'
                );
            }
        });
    }

    function renderStats() {
        var booked = 0, sent = 0, received = 0, overdue = 0;
        allRepairs.forEach(function(x) {
            if (x.status === 'Booked') booked++;
            else if (x.status === 'Sent') sent++;
            else if (x.status === 'Received') received++;
            if (x.status !== 'Received' && x.days_open && x.days_open > 14) overdue++;
        });
        function card(val, label) {
            return '<div class="hm-repair-stat"><div class="hm-repair-stat-val">' + val + '</div><div class="hm-repair-stat-lbl">' + label + '</div></div>';
        }
        $('#hm-repairs-stats').html(
            card(booked, 'Booked') +
            card(sent, 'Sent') +
            card(received, 'Received') +
            card(overdue, 'Overdue (14d+)')
        );
    }

    function renderTable() {
        var status = $('#hm-repair-filter-status').val();
        var clinic = $('#hm-repair-filter-clinic').val();
        var q = $.trim($('#hm-repair-search').val()).toLowerCase();
        var filtered = allRepairs.filter(function(x) {
            if (status && x.status !== status) return false;
            if (clinic && String(x.clinic_id) !== String(clinic)) return false;
            if (q) {
                var hay = (x.repair_number || '') + ' ' + (x.patient_name || '') + ' ' + (x.patient_number || '') + ' ' + (x.product_name || '') + ' ' + (x.manufacturer_name || '');
                if (hay.toLowerCase().indexOf(q) === -1) return false;
            }
            return true;
        });

        if (!filtered.length) {
            $('#hm-repairs-table').html('<div class="hm-empty"><div class="hm-empty-text">No repairs match filters (' + allRepairs.length + ' total)</div></div>');
            return;
        }

        var h = '<table class="hm-table"><thead><tr>' +
            '<th>Repair #</th><th>Patient</th><th>Clinic</th><th>Device</th><th>Manufacturer</th>' +
            '<th>Serial</th><th>Reason</th><th>Booked</th><th>Status</th><th>Days</th>' +
            '<th>Warranty</th><th>Sent To</th><th>Dispenser</th><th></th>' +
            '</tr></thead><tbody>';

        filtered.forEach(function(x) {
            var sc = x.status === 'Booked' ? 'hm-badge--amber' : x.status === 'Sent' ? 'hm-badge--blue' : 'hm-badge--green';
            var rowClass = '';
            if (x.status !== 'Received' && x.days_open) {
                if (x.days_open > 14) rowClass = ' class="hm-repair-overdue"';
                else if (x.days_open > 10) rowClass = ' class="hm-repair-warning"';
            }
            var actions = '<button class="hm-btn hm-btn--secondary hm-btn--sm hm-r-docket" data-id="' + x._ID + '" title="Print Docket" style="padding:4px 8px;">🖨</button> ';
            if (x.status === 'Booked') actions += '<button class="hm-btn hm-btn--secondary hm-btn--sm hm-r-send" data-id="' + x._ID + '" data-name="' + esc(x.patient_name) + '">Mark Sent</button>';
            else if (x.status === 'Sent') actions += '<button class="hm-btn hm-btn--secondary hm-btn--sm hm-r-recv" data-id="' + x._ID + '">Received</button>';

            h += '<tr' + rowClass + '>' +
                '<td><code class="hm-pt-hnum">' + esc(x.repair_number || '—') + '</code></td>' +
                '<td><a href="/patients/?id=' + x.patient_id + '" style="color:#0BB4C4;">' + esc(x.patient_name) + '</a>' + (x.patient_number ? ' <span style="color:#94a3b8;font-size:11px;">' + esc(x.patient_number) + '</span>' : '') + '</td>' +
                '<td style="font-size:12px;">' + esc(x.clinic_name || '—') + '</td>' +
                '<td>' + esc(x.product_name || '—') + '</td>' +
                '<td>' + esc(x.manufacturer_name || '—') + '</td>' +
                '<td style="font-size:12px;font-family:monospace;">' + esc(x.serial_number || '—') + '</td>' +
                '<td style="font-size:13px;">' + esc(x.repair_reason || '—') + '</td>' +
                '<td>' + fmtDate(x.date_booked) + '</td>' +
                '<td><span class="hm-badge hm-badge--sm ' + sc + '">' + esc(x.status) + '</span></td>' +
                '<td style="text-align:center;">' + (x.days_open != null ? x.days_open : '—') + '</td>' +
                '<td>' + (x.under_warranty ? '<span class="hm-badge hm-badge--sm hm-badge--green">Yes</span>' : '<span class="hm-badge hm-badge--sm hm-badge--grey">' + (x.warranty_status || 'No') + '</span>') + '</td>' +
                '<td style="font-size:12px;">' + esc(x.sent_to || '—') + '</td>' +
                '<td style="font-size:12px;">' + esc(x.dispenser_name || '—') + '</td>' +
                '<td style="white-space:nowrap;">' + actions + '</td>' +
                '</tr>';
        });
        h += '</tbody></table>';
        $('#hm-repairs-table').html(h);
    }

    // Event handlers
    $(document).on('change', '#hm-repair-filter-status, #hm-repair-filter-clinic', renderTable);
    $(document).on('input', '#hm-repair-search', renderTable);

    // Print docket
    $(document).on('click', '.hm-r-docket', function() {
        var rid = $(this).data('id');
        $.post(ajaxUrl, { action: 'hm_get_repair_docket', nonce: nonce, repair_id: rid }, function(r) {
            if (r && r.success && r.data && r.data.html) {
                var w = window.open('', '_blank', 'width=900,height=700');
                if (w) { w.document.write(r.data.html); w.document.close(); }
            } else {
                alert('Could not generate docket');
            }
        });
    });

    // Mark Sent — prompt for sent_to
    $(document).on('click', '.hm-r-send', function() {
        var $b = $(this), rid = $b.data('id'), pname = $b.data('name') || '';
        var sentTo = prompt('Sending to which manufacturer / lab?\n(Patient: ' + pname + ')', '');
        if (sentTo === null) return;
        $b.prop('disabled', true).text('…');
        $.post(ajaxUrl, { action: 'hm_update_repair_status', nonce: nonce, _ID: rid, status: 'Sent', sent_to: sentTo }, function(r) {
            if (r.success) loadRepairs();
            else { alert('Error updating status'); $b.prop('disabled', false).text('Mark Sent'); }
        });
    });

    // Mark Received
    $(document).on('click', '.hm-r-recv', function() {
        var $b = $(this), rid = $b.data('id');
        $b.prop('disabled', true).text('…');
        $.post(ajaxUrl, { action: 'hm_update_repair_status', nonce: nonce, _ID: rid, status: 'Received' }, function(r) {
            if (r.success) loadRepairs();
            else { alert('Error updating status'); $b.prop('disabled', false).text('Received'); }
        });
    });

    // Boot
    $(function() {
        console.log('[HM Repairs] DOM ready, #hm-repairs-app exists:', $('#hm-repairs-app').length > 0);
        if ($('#hm-repairs-app').length) {
            loadClinics();
            loadRepairs();
        } else {
            console.warn('[HM Repairs] #hm-repairs-app not found in DOM — repairs page not active');
        }
    });

})(jQuery);
