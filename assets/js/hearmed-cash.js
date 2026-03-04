/**
 * HearMed Cash / Tender — Module JavaScript
 *
 * Loaded on pages containing [hearmed_cash] or [hearmed_till] shortcode.
 * Depends on hearmed-core.js (provides HM global with ajax_url, nonce).
 */
(function($) {
    'use strict';

    if (typeof HM === 'undefined') {
        console.error('[HM Cash] HM global not found — hearmed-core.js may not be loaded');
        return;
    }

    var ajaxUrl = HM.ajax_url || HM.ajax;
    var nonce   = HM.nonce;

    // ─────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────
    function $(sel) { return document.querySelector(sel); }
    function $$(sel) { return document.querySelectorAll(sel); }

    function showToast(msg, type) {
        if (HM && HM.toast) { HM.toast(msg, type || 'info'); }
        else { alert(msg); }
    }

    function setLoading(btn, loading) {
        if (!btn) return;
        btn.disabled = loading;
        if (loading) {
            btn._origText = btn.textContent;
            btn.textContent = 'Saving…';
        } else {
            btn.textContent = btn._origText || 'Submit';
        }
    }

    function post(action, data) {
        data.action = action;
        data.nonce  = nonce;
        return jQuery.post(ajaxUrl, data);
    }

    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    // ─────────────────────────────────────────────────
    // 1. Setup — Activate Tender
    // ─────────────────────────────────────────────────
    var activateBtn = $('#hm-cash-activate');
    if (activateBtn) {
        activateBtn.addEventListener('click', function() {
            var clinic = ($('#hm-cash-setup-clinic') || {}).value;
            var cashFloat = parseFloat(($('#hm-cash-setup-float') || {}).value) || 0;
            var cheques = parseFloat(($('#hm-cash-setup-cheques') || {}).value) || 0;

            if (!clinic) { showToast('Please select a clinic', 'error'); return; }

            setLoading(activateBtn, true);
            post('hm_activate_tender', {
                clinic_id: clinic,
                cash_float: cashFloat,
                cheques: cheques
            }).done(function(r) {
                if (r && r.success) {
                    showToast('Tender activated!', 'success');
                    setTimeout(function() { location.reload(); }, 500);
                } else {
                    showToast(r && r.data && r.data.msg ? r.data.msg : 'Activation failed', 'error');
                    setLoading(activateBtn, false);
                }
            }).fail(function() {
                showToast('Request failed', 'error');
                setLoading(activateBtn, false);
            });
        });
    }

    // ─────────────────────────────────────────────────
    // 2. Lodge Type Toggle
    // ─────────────────────────────────────────────────
    var lodgeTypeBtns = $$('.hm-lodge-type-btn');
    lodgeTypeBtns.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var type = btn.getAttribute('data-type');
            var hidden = $('#hm-lodge-type');
            if (hidden) hidden.value = type;

            lodgeTypeBtns.forEach(function(b) { b.classList.remove('active', 'hm-btn--primary'); });
            btn.classList.add('active', 'hm-btn--primary');

            var cashRow = $('#hm-lodge-cash-row');
            var chequeRow = $('#hm-lodge-cheque-row');
            var chequeCountRow = $('#hm-lodge-cheque-count-row');

            if (cashRow) cashRow.style.display = (type === 'cash' || type === 'both') ? '' : 'none';
            if (chequeRow) chequeRow.style.display = (type === 'cheque' || type === 'both') ? '' : 'none';
            if (chequeCountRow) chequeCountRow.style.display = (type === 'cheque' || type === 'both') ? '' : 'none';

            updateLodgeSummary();
        });
    });

    // ─────────────────────────────────────────────────
    // 3. Lodge Summary
    // ─────────────────────────────────────────────────
    function updateLodgeSummary() {
        var type = ($('#hm-lodge-type') || {}).value || 'cash';
        var maxCash = parseFloat(($('#hm-lodge-max-cash') || {}).value) || 0;
        var maxCheque = parseFloat(($('#hm-lodge-max-cheque') || {}).value) || 0;
        var cashAmt = (type === 'cash' || type === 'both') ? (parseFloat(($('#hm-lodge-cash-amount') || {}).value) || 0) : 0;
        var chequeAmt = (type === 'cheque' || type === 'both') ? (parseFloat(($('#hm-lodge-cheque-amount') || {}).value) || 0) : 0;

        var remainCash = maxCash - cashAmt;
        var remainCheque = maxCheque - chequeAmt;

        var summaryEl = $('#hm-lodge-summary');
        var textEl = $('#hm-lodge-summary-text');
        if (summaryEl && textEl) {
            if (cashAmt > 0 || chequeAmt > 0) {
                textEl.textContent = 'After lodgment — Cash: €' + remainCash.toFixed(2) + ' · Cheques: €' + remainCheque.toFixed(2);
                summaryEl.style.display = '';
            } else {
                summaryEl.style.display = 'none';
            }
        }
    }

    // Listen for amount changes
    var cashAmtInput = $('#hm-lodge-cash-amount');
    var chequeAmtInput = $('#hm-lodge-cheque-amount');
    if (cashAmtInput) cashAmtInput.addEventListener('input', updateLodgeSummary);
    if (chequeAmtInput) chequeAmtInput.addEventListener('input', updateLodgeSummary);

    // ─────────────────────────────────────────────────
    // 4. Lodge Submit
    // ─────────────────────────────────────────────────
    var lodgeSubmit = $('#hm-lodge-submit');
    if (lodgeSubmit) {
        lodgeSubmit.addEventListener('click', function() {
            var tenderId = ($('#hm-lodge-tender-id') || {}).value;
            var type = ($('#hm-lodge-type') || {}).value || 'cash';
            var cashAmount = parseFloat(($('#hm-lodge-cash-amount') || {}).value) || 0;
            var chequeAmount = parseFloat(($('#hm-lodge-cheque-amount') || {}).value) || 0;
            var chequeCount = parseInt(($('#hm-lodge-cheque-count') || {}).value) || 0;
            var reference = ($('#hm-lodge-reference') || {}).value || '';
            var notes = ($('#hm-lodge-notes') || {}).value || '';

            if (type === 'cash' && cashAmount <= 0) { showToast('Enter a cash amount', 'error'); return; }
            if (type === 'cheque' && chequeAmount <= 0) { showToast('Enter a cheque amount', 'error'); return; }
            if (type === 'both' && cashAmount <= 0 && chequeAmount <= 0) { showToast('Enter at least one amount', 'error'); return; }

            setLoading(lodgeSubmit, true);
            post('hm_lodge_money', {
                tender_id: tenderId,
                lodge_type: type,
                cash_amount: cashAmount,
                cheque_amount: chequeAmount,
                cheque_count: chequeCount,
                reference: reference,
                notes: notes
            }).done(function(r) {
                if (r && r.success) {
                    var lodgmentId = r.data.lodgment_id;
                    var fileInput = $('#hm-lodge-slip-file');
                    if (fileInput && fileInput.files && fileInput.files.length > 0) {
                        uploadPhoto(fileInput.files[0], 'lodge', lodgmentId).then(function() {
                            showToast('Lodgment recorded & slip uploaded', 'success');
                            goToDashboard();
                        }).catch(function() {
                            showToast('Lodgment recorded but slip upload failed', 'warning');
                            goToDashboard();
                        });
                    } else {
                        showToast('Lodgment recorded', 'success');
                        goToDashboard();
                    }
                } else {
                    showToast(r && r.data && r.data.msg ? r.data.msg : 'Lodge failed', 'error');
                    setLoading(lodgeSubmit, false);
                }
            }).fail(function() {
                showToast('Request failed', 'error');
                setLoading(lodgeSubmit, false);
            });
        });
    }

    // ─────────────────────────────────────────────────
    // 5. Petty Cash Submit
    // ─────────────────────────────────────────────────
    var pettySubmit = $('#hm-petty-submit');
    if (pettySubmit) {
        pettySubmit.addEventListener('click', function() {
            var tenderId = ($('#hm-petty-tender-id') || {}).value;
            var amount = parseFloat(($('#hm-petty-amount') || {}).value) || 0;
            var category = ($('#hm-petty-category') || {}).value || '';
            var description = ($('#hm-petty-description') || {}).value || '';
            var vendor = ($('#hm-petty-vendor') || {}).value || '';
            var notes = ($('#hm-petty-notes') || {}).value || '';

            if (amount <= 0) { showToast('Enter an amount', 'error'); return; }
            if (!description.trim()) { showToast('Description is required', 'error'); return; }

            // Require receipt photo
            var receiptInput = $('#hm-petty-receipt-file');
            if (!receiptInput || !receiptInput.files || receiptInput.files.length === 0) {
                showToast('Receipt photo is required', 'error');
                return;
            }

            setLoading(pettySubmit, true);
            post('hm_petty_cash', {
                tender_id: tenderId,
                amount: amount,
                category: category,
                description: description,
                vendor: vendor,
                notes: notes
            }).done(function(r) {
                if (r && r.success) {
                    var expenseId = r.data.expense_id;
                    uploadPhoto(receiptInput.files[0], 'expense', expenseId).then(function() {
                        showToast('Expense logged & receipt uploaded', 'success');
                        goToDashboard();
                    }).catch(function() {
                        showToast('Expense logged but receipt upload failed', 'warning');
                        goToDashboard();
                    });
                } else {
                    showToast(r && r.data && r.data.msg ? r.data.msg : 'Failed', 'error');
                    setLoading(pettySubmit, false);
                }
            }).fail(function() {
                showToast('Request failed', 'error');
                setLoading(pettySubmit, false);
            });
        });
    }

    // ─────────────────────────────────────────────────
    // 6. Photo Upload
    // ─────────────────────────────────────────────────
    function uploadPhoto(file, refType, refId) {
        return new Promise(function(resolve, reject) {
            var fd = new FormData();
            fd.append('action', 'hm_upload_receipt');
            fd.append('nonce', nonce);
            fd.append('photo', file);
            fd.append('ref_type', refType);
            fd.append('ref_id', refId);

            jQuery.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                success: function(r) {
                    if (r && r.success) resolve(r.data);
                    else reject(r);
                },
                error: function() { reject(); }
            });
        });
    }

    // ─────────────────────────────────────────────────
    // 7. Upload Zone Click Handlers
    // ─────────────────────────────────────────────────
    function setupUploadZone(zoneId, fileId) {
        var zone = $(zoneId);
        var input = $(fileId);
        if (!zone || !input) return;

        input.addEventListener('change', function() {
            if (input.files && input.files.length > 0) {
                var file = input.files[0];
                zone.classList.add('hm-cash-upload-zone--attached');
                zone.classList.remove('hm-cash-upload-zone--required');

                // Show preview
                var preview = zone.querySelector('.hm-cash-photo-preview');
                if (!preview) {
                    preview = document.createElement('div');
                    preview.className = 'hm-cash-photo-preview';
                    zone.appendChild(preview);
                }

                var thumbUrl = URL.createObjectURL(file);
                preview.innerHTML =
                    '<img src="' + thumbUrl + '" alt="Preview">' +
                    '<span class="hm-cash-photo-name">' + escHtml(file.name) + '</span>' +
                    '<span class="hm-cash-photo-size">' + formatSize(file.size) + '</span>';
            }
        });
    }

    setupUploadZone('#hm-lodge-slip-zone', '#hm-lodge-slip-file');
    setupUploadZone('#hm-petty-receipt-zone', '#hm-petty-receipt-file');

    // ─────────────────────────────────────────────────
    // 8. Manager: Confirm / Query Lodgment
    // ─────────────────────────────────────────────────
    document.addEventListener('click', function(e) {
        var confirmBtn = e.target.closest('.hm-lodge-confirm-btn');
        var queryBtn = e.target.closest('.hm-lodge-query-btn');

        if (confirmBtn) {
            var lodgmentId = confirmBtn.getAttribute('data-id');
            if (!confirm('Confirm this lodgment has been banked?')) return;
            setLoading(confirmBtn, true);
            post('hm_confirm_lodgment', {
                lodgment_id: lodgmentId,
                action_type: 'confirm'
            }).done(function(r) {
                if (r && r.success) {
                    showToast('Lodgment confirmed', 'success');
                    setTimeout(function() { location.reload(); }, 500);
                } else {
                    showToast(r && r.data && r.data.msg ? r.data.msg : 'Failed', 'error');
                    setLoading(confirmBtn, false);
                }
            });
        }

        if (queryBtn) {
            var qId = queryBtn.getAttribute('data-id');
            var reason = prompt('Enter a reason for querying this lodgment:');
            if (!reason) return;
            setLoading(queryBtn, true);
            post('hm_confirm_lodgment', {
                lodgment_id: qId,
                action_type: 'query',
                reason: reason
            }).done(function(r) {
                if (r && r.success) {
                    showToast('Lodgment queried', 'warning');
                    setTimeout(function() { location.reload(); }, 500);
                } else {
                    showToast(r && r.data && r.data.msg ? r.data.msg : 'Failed', 'error');
                    setLoading(queryBtn, false);
                }
            });
        }
    });

    // ─────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────
    function goToDashboard() {
        // Navigate back to tender dashboard (strip hm_action from URL)
        var base = location.pathname;
        location.href = base;
    }

    function escHtml(s) {
        var d = document.createElement('div');
        d.textContent = s || '';
        return d.innerHTML;
    }

})(jQuery);
