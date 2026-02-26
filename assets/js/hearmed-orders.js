/**
 * HearMed Portal — Orders JS
 * Handles: Create Order modal — product search, line items, totals, PRSI, submit
 *
 * Depends on: HM.ajax_url, HM.nonce, HM.toast(), jQuery
 * Blueprint 01 — Section 1
 */

(function ($) {
    'use strict';

    // -----------------------------------------------------------------------
    // STATE
    // -----------------------------------------------------------------------
    var OrderModal = {
        patientId   : null,
        patientName : null,
        lineIndex   : 0,   // ever-incrementing counter for unique line IDs

        // Cached DOM refs (set on open)
        $bg     : null,
        $lines  : null,
    };

    // -----------------------------------------------------------------------
    // PUBLIC API — attach to HM namespace so patient profile can call it
    // -----------------------------------------------------------------------
    window.HM = window.HM || {};

    /**
     * Open the Create Order modal.
     * @param {number} patientId
     * @param {string} patientName
     */
    HM.openOrderModal = function (patientId, patientName) {
        OrderModal.patientId   = patientId;
        OrderModal.patientName = patientName;
        OrderModal.lineIndex   = 0;

        var $bg = $('#hm-order-modal-bg');
        OrderModal.$bg    = $bg;
        OrderModal.$lines = $bg.find('#hm-order-lines');

        // Reset form
        $bg.find('#hm-order-patient-id').val(patientId);
        $bg.find('#hm-order-patient-name').text(patientName);
        $bg.find('#hm-order-clinic').val('');
        $bg.find('#hm-order-prsi').prop('checked', false);
        $bg.find('#hm-order-prsi-note').hide();
        $bg.find('#hm-order-notes').val('');
        $bg.find('#hm-order-dup-warning').hide();
        OrderModal.$lines.empty();

        // Reset totals
        OrderModal.recalcTotals();

        // Add first empty line
        OrderModal.addLine();

        // Show modal
        $bg.fadeIn(180);
        $bg.find('.hm-modal').css({ transform: 'translateY(-12px)', opacity: 0 })
           .animate({ opacity: 1 }, 200)
           .css('transform', 'translateY(0)');
        document.body.style.overflow = 'hidden';
    };

    /**
     * Close the modal.
     */
    HM.closeOrderModal = function () {
        $('#hm-order-modal-bg').fadeOut(160);
        document.body.style.overflow = '';
    };

    // -----------------------------------------------------------------------
    // LINE ITEM MANAGEMENT
    // -----------------------------------------------------------------------
    OrderModal.addLine = function () {
        var idx  = ++OrderModal.lineIndex;
        var tpl  = document.getElementById('hm-order-line-tpl');
        var node = tpl.content.cloneNode(true);
        var $el  = $(node.querySelector('.hm-order-line'));
        $el.attr('data-line-index', idx);

        OrderModal.$lines.append($el);

        // Wire up events on this new line
        var $line = OrderModal.$lines.find('[data-line-index="' + idx + '"]');
        OrderModal.wireLineEvents($line);
    };

    OrderModal.removeLine = function ($line) {
        if (OrderModal.$lines.find('.hm-order-line').length <= 1) {
            HM.toast('At least one line item is required.', 'error');
            return;
        }
        $line.fadeOut(150, function () {
            $(this).remove();
            OrderModal.recalcTotals();
        });
    };

    // -----------------------------------------------------------------------
    // PRODUCT SEARCH (autocomplete)
    // -----------------------------------------------------------------------
    OrderModal.wireLineEvents = function ($line) {
        var $search   = $line.find('.hm-line-product-search');
        var $results  = $line.find('.hm-line-product-results');
        var $prodId   = $line.find('.hm-line-product-id');
        var $prodName = $line.find('.hm-line-product-name');
        var $costPx   = $line.find('.hm-line-cost-price');
        var $selected = $line.find('.hm-line-product-selected');
        var $badge    = $line.find('.hm-line-product-badge');
        var $clear    = $line.find('.hm-line-product-clear');
        var searchTimer;

        // ── Product search input ──────────────────────────────────────────
        $search.on('input', function () {
            clearTimeout(searchTimer);
            var q = $.trim($(this).val());
            if (q.length < 2) {
                $results.hide().empty();
                return;
            }
            searchTimer = setTimeout(function () {
                OrderModal.searchProducts(q, $results, function (product) {
                    OrderModal.selectProduct($line, product);
                });
            }, 280);
        });

        $search.on('keydown', function (e) {
            if (e.key === 'Escape') $results.hide().empty();
        });

        // Hide dropdown on outside click
        $(document).on('click.hm-product-search', function (e) {
            if (!$(e.target).closest($line.find('.hm-line-product-search').parent()).length) {
                $results.hide();
            }
        });

        // ── Clear product selection ───────────────────────────────────────
        $clear.on('click', function () {
            $prodId.val('');
            $prodName.val('');
            $costPx.val(0);
            $selected.hide();
            $search.val('').show().focus();
            $line.find('.hm-line-price').val('');
            $line.find('.hm-line-vat').val('0');
            OrderModal.recalcLine($line);
        });

        // ── Recalc on any value change ────────────────────────────────────
        $line.on('change input', '.hm-line-qty, .hm-line-price, .hm-line-discount, .hm-line-discount-type, .hm-line-vat', function () {
            OrderModal.recalcLine($line);
        });

        // ── Remove line ───────────────────────────────────────────────────
        $line.find('.hm-line-remove').on('click', function () {
            OrderModal.removeLine($line);
        });
    };

    OrderModal.searchProducts = function (q, $results, onSelect) {
        $results.html('<div style="padding:10px 14px;color:#94a3b8;font-size:.85rem;">Searching…</div>').show();

        $.post(HM.ajax_url, {
            action  : 'hm_search_products',
            nonce   : HM.nonce,
            q       : q,
        }, function (r) {
            $results.empty();
            if (!r.success || !r.data.length) {
                $results.html('<div style="padding:10px 14px;color:#94a3b8;font-size:.85rem;">No products found.</div>');
                return;
            }
            r.data.forEach(function (p) {
                var sub = [p.manufacturer, p.style, p.range].filter(Boolean).join(' · ');
                var $item = $(
                    '<div class="hm-product-item" style="padding:9px 14px;cursor:pointer;border-bottom:1px solid #f1f5f9;">' +
                        '<div style="font-weight:600;font-size:.875rem;color:#1e293b;">' + escHtml(p.name) + '</div>' +
                        (sub ? '<div style="font-size:.78rem;color:#94a3b8;margin-top:2px;">' + escHtml(sub) + '</div>' : '') +
                        '<div style="font-size:.8rem;color:var(--hm-teal);margin-top:2px;">€' + (p.retail_price || 0).toFixed(2) + '</div>' +
                    '</div>'
                );
                $item.on('mouseenter', function () { $(this).css('background', '#f8fafc'); });
                $item.on('mouseleave', function () { $(this).css('background', ''); });
                $item.on('click', function () {
                    $results.hide().empty();
                    onSelect(p);
                });
                $results.append($item);
            });
        }).fail(function () {
            $results.html('<div style="padding:10px 14px;color:#e53e3e;font-size:.85rem;">Search failed. Try again.</div>');
        });
    };

    OrderModal.selectProduct = function ($line, product) {
        $line.find('.hm-line-product-id').val(product.id);
        $line.find('.hm-line-product-name').val(product.name);
        $line.find('.hm-line-cost-price').val(product.cost_price || 0);

        // Pre-fill price + VAT from product data
        if (product.retail_price) {
            $line.find('.hm-line-price').val(product.retail_price.toFixed(2));
        }
        if (product.vat_rate !== undefined) {
            $line.find('.hm-line-vat').val(product.vat_rate);
        }

        // Show selected badge, hide search input
        var label = [product.manufacturer, product.name, product.style].filter(Boolean).join(' · ');
        $line.find('.hm-line-product-badge').text(label);
        $line.find('.hm-line-product-selected').show();
        $line.find('.hm-line-product-search').hide();

        OrderModal.recalcLine($line);
    };

    // -----------------------------------------------------------------------
    // CALCULATIONS
    // -----------------------------------------------------------------------
    OrderModal.recalcLine = function ($line) {
        var qty          = Math.max(1, parseFloat($line.find('.hm-line-qty').val()) || 1);
        var unitPrice    = parseFloat($line.find('.hm-line-price').val()) || 0;
        var discountVal  = parseFloat($line.find('.hm-line-discount').val()) || 0;
        var discountType = $line.find('.hm-line-discount-type').val();
        var vatRate      = parseFloat($line.find('.hm-line-vat').val()) || 0;
        var costPrice    = parseFloat($line.find('.hm-line-cost-price').val()) || 0;

        var gross = unitPrice * qty;

        var discountEur = (discountType === 'pct')
            ? (discountVal / 100) * gross
            : discountVal;

        var net       = Math.max(0, gross - discountEur);
        var vatAmount = net * (vatRate / 100);
        var lineTotal = net + vatAmount;

        $line.find('.hm-line-total').text('€' + lineTotal.toFixed(2));

        // Gross margin (only rendered in DOM for finance roles)
        var $marginEl = $line.find('.hm-line-margin-pct');
        if ($marginEl.length && lineTotal > 0 && costPrice > 0) {
            var totalCost = costPrice * qty;
            var margin    = ((lineTotal - totalCost) / lineTotal) * 100;
            $marginEl.text(margin.toFixed(1) + '%')
                     .css('color', margin >= 40 ? '#16a34a' : margin >= 20 ? '#d97706' : '#dc2626');
        }

        OrderModal.recalcTotals();
    };

    OrderModal.recalcTotals = function () {
        var subtotal      = 0;
        var totalDiscount = 0;
        var totalVat      = 0;
        var totalCost     = 0;

        OrderModal.$lines.find('.hm-order-line').each(function () {
            var $l        = $(this);
            var qty       = Math.max(1, parseFloat($l.find('.hm-line-qty').val()) || 1);
            var unitPrice = parseFloat($l.find('.hm-line-price').val()) || 0;
            var discVal   = parseFloat($l.find('.hm-line-discount').val()) || 0;
            var discType  = $l.find('.hm-line-discount-type').val();
            var vatRate   = parseFloat($l.find('.hm-line-vat').val()) || 0;
            var costPx    = parseFloat($l.find('.hm-line-cost-price').val()) || 0;

            var gross   = unitPrice * qty;
            var discEur = (discType === 'pct') ? (discVal / 100) * gross : discVal;
            var net     = Math.max(0, gross - discEur);
            var vat     = net * (vatRate / 100);

            subtotal      += gross;
            totalDiscount += discEur;
            totalVat      += vat;
            totalCost     += costPx * qty;
        });

        // PRSI
        var prsiChecked = $('#hm-order-prsi').is(':checked');
        var prsiAmount  = 0;
        if (prsiChecked) {
            var ears = [];
            OrderModal.$lines.find('.hm-line-ear').each(function () {
                var v = $(this).val();
                if (v) ears.push(v);
            });
            var earCount = 0;
            ears.forEach(function (e) { earCount += (e === 'Binaural') ? 2 : 1; });
            prsiAmount = Math.min(500 * earCount, 1000);
        }

        var grandPrePrsi = subtotal - totalDiscount + totalVat;
        var grand        = Math.max(0, grandPrePrsi - prsiAmount);

        $('#hm-total-subtotal').text('€' + subtotal.toFixed(2));
        $('#hm-total-discount').text('−€' + totalDiscount.toFixed(2));
        $('#hm-total-vat').text('€' + totalVat.toFixed(2));
        $('#hm-total-prsi').text(prsiChecked ? '−€' + prsiAmount.toFixed(2) : '—');
        $('#hm-total-grand').text('€' + grand.toFixed(2));

        // Gross margin total (finance roles only — element may not exist)
        var $margEl = $('#hm-total-margin');
        if ($margEl.length && grand > 0 && totalCost > 0) {
            var gm = ((grand - totalCost) / grand) * 100;
            $margEl.text(gm.toFixed(1) + '%')
                   .css('color', gm >= 40 ? '#16a34a' : gm >= 20 ? '#d97706' : '#dc2626');
        }
    };

    // -----------------------------------------------------------------------
    // SUBMIT
    // -----------------------------------------------------------------------
    OrderModal.submit = function () {
        // Validate
        var clinicId = $('#hm-order-clinic').val();
        if (!clinicId) {
            HM.toast('Please select a clinic.', 'error');
            return;
        }

        var lines = [];
        var valid = true;

        OrderModal.$lines.find('.hm-order-line').each(function () {
            var $l       = $(this);
            var prodId   = $l.find('.hm-line-product-id').val();
            var prodName = $l.find('.hm-line-product-name').val();
            var ear      = $l.find('.hm-line-ear').val();

            if (!prodId || !ear) {
                HM.toast('Each line item needs a product and ear selection.', 'error');
                valid = false;
                return false; // break
            }

            lines.push({
                product_id     : prodId,
                product_name   : prodName,
                ear            : ear,
                qty            : $l.find('.hm-line-qty').val(),
                unit_price     : $l.find('.hm-line-price').val(),
                discount       : $l.find('.hm-line-discount').val(),
                discount_type  : $l.find('.hm-line-discount-type').val(),
                vat_rate       : $l.find('.hm-line-vat').val(),
                cost_price     : $l.find('.hm-line-cost-price').val(),
            });
        });

        if (!valid || !lines.length) return;

        // Disable submit while in flight
        var $btn   = $('#hm-order-submit');
        var $label = $('#hm-order-submit-label');
        var $spin  = $('#hm-order-submit-spinner');
        $btn.prop('disabled', true);
        $label.hide();
        $spin.show();

        $.post(HM.ajax_url, {
            action          : 'hm_create_order',
            nonce           : $('#hm-order-nonce').val(),
            patient_id      : OrderModal.patientId,
            clinic_id       : clinicId,
            prsi_applicable : $('#hm-order-prsi').is(':checked') ? 1 : 0,
            notes           : $('#hm-order-notes').val(),
            line_items      : lines,
        }, function (r) {
            $btn.prop('disabled', false);
            $label.show();
            $spin.hide();

            if (r.success) {
                if (r.data.duplicate_flag) {
                    // Show warning but still close — order was saved
                    $('#hm-order-dup-warning').show();
                    setTimeout(function () {
                        HM.closeOrderModal();
                        HM.toast('Order ' + r.data.order_number + ' submitted — duplicate flag added.', 'success');
                        $(document).trigger('hm:orderCreated', [r.data]);
                    }, 2000);
                } else {
                    HM.closeOrderModal();
                    HM.toast('Order ' + r.data.order_number + ' submitted for approval.', 'success');
                    $(document).trigger('hm:orderCreated', [r.data]);
                }
            } else {
                HM.toast(r.data.msg || 'Error submitting order. Please try again.', 'error');
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            $label.show();
            $spin.hide();
            HM.toast('Network error. Please check your connection and try again.', 'error');
        });
    };

    // -----------------------------------------------------------------------
    // DOCUMENT READY — wire static modal controls
    // -----------------------------------------------------------------------
    $(document).ready(function () {

        // Close buttons
        $(document).on('click', '#hm-order-modal-close, #hm-order-cancel', function () {
            HM.closeOrderModal();
        });

        // Click outside modal to close
        $(document).on('click', '#hm-order-modal-bg', function (e) {
            if ($(e.target).is('#hm-order-modal-bg')) {
                HM.closeOrderModal();
            }
        });

        // Escape key
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && $('#hm-order-modal-bg').is(':visible')) {
                HM.closeOrderModal();
            }
        });

        // Add line button
        $(document).on('click', '#hm-order-add-line', function () {
            OrderModal.addLine();
        });

        // PRSI toggle
        $(document).on('change', '#hm-order-prsi', function () {
            $('#hm-order-prsi-note').toggle(this.checked);
            OrderModal.recalcTotals();
        });

        // Submit
        $(document).on('click', '#hm-order-submit', function () {
            OrderModal.submit();
        });

        // ── "Create Order" trigger from patient profile ───────────────────
        // Patient profile injects a button like:
        // <button class="hm-btn hm-btn--primary hm-btn--sm hm-create-order"
        //         data-patient-id="123" data-patient-name="John Doe">
        //   + Create Order
        // </button>
        $(document).on('click', '.hm-create-order', function () {
            var pid  = $(this).data('patient-id');
            var name = $(this).data('patient-name');
            HM.openOrderModal(pid, name);
        });
    });

    // -----------------------------------------------------------------------
    // UTILITY
    // -----------------------------------------------------------------------
    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

})(jQuery);

// ==========================================================================
// ORDER STATUS PAGE — table, filters, actions
// ==========================================================================

(function ($) {
    'use strict';

    var OrdersPage = {
        page      : 1,
        filters   : { status: '', clinic_id: '', search: '', date_from: '', date_to: '' },
        debounce  : null,
    };

    // Status → badge class mapping
    var STATUS_BADGE = {
        'Awaiting Approval': 'hm-badge--amber',
        'Approved'         : 'hm-badge-teal',
        'Ordered'          : 'hm-badge-teal',
        'Received'         : 'hm-badge--green',
        'Fitting Scheduled': 'hm-badge-teal',
        'Fitted'           : 'hm-badge--green',
        'Cancelled'        : 'hm-badge--red',
    };

    function eur(n) { return '€' + parseFloat(n || 0).toFixed(2); }
    function fmtDate(dt) {
        if (!dt) return '—';
        var d = new Date(dt);
        return d.toLocaleDateString('en-IE', {day:'2-digit', month:'short', year:'numeric'});
    }
    function esc(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function loadOrders() {
        if (!$('#hm-orders-tbody').length) return;

        $('#hm-orders-loading').show();
        $('#hm-orders-table-wrap').hide();
        $('#hm-orders-empty').hide();
        $('#hm-orders-pagination').hide();

        $.post(HM.ajax_url, $.extend({ action: 'hm_get_orders', nonce: HM.nonce, paged: OrdersPage.page }, OrdersPage.filters),
        function (r) {
            $('#hm-orders-loading').hide();
            if (!r.success) { HM.toast(r.data.msg || 'Could not load orders', 'error'); return; }

            var orders = r.data.orders;
            if (!orders || !orders.length) {
                $('#hm-orders-empty').show();
                return;
            }

            var rows = '';
            orders.forEach(function (o) {
                var badge   = '<span class="hm-badge ' + (STATUS_BADGE[o.status] || 'hm-badge--grey') + '">' + esc(o.status) + '</span>';
                var dup     = o.duplicate_flag ? '<span class="hm-badge hm-badge--red" title="Possible duplicate" style="margin-left:4px;">⚠ DUP</span>' : '';
                var actions = buildOrderActions(o);
                rows += '<tr data-order-id="' + o.id + '">' +
                    '<td>' + esc(o.patient_name) + '</td>' +
                    '<td><code>' + esc(o.patient_number) + '</code></td>' +
                    '<td><a href="#" class="hm-order-detail-link" data-id="' + o.id + '" style="color:var(--hm-teal);text-decoration:none;">' + esc(o.order_number) + '</a></td>' +
                    '<td style="max-width:200px;white-space:normal;">' + esc(o.product_summary) + '</td>' +
                    '<td>' + eur(o.grand_total) + '</td>' +
                    '<td>' + (o.prsi_applicable ? '<span class="hm-badge hm-badge-teal">PRSI −' + eur(o.prsi_amount) + '</span>' : '—') + '</td>' +
                    '<td>' + badge + dup + '</td>' +
                    '<td style="white-space:nowrap;">' + fmtDate(o.created_at) + '</td>' +
                    '<td>' + actions + '</td>' +
                    '</tr>';
            });

            $('#hm-orders-tbody').html(rows);
            $('#hm-orders-table-wrap').show();

            // Pagination
            var total    = r.data.total;
            var perPage  = r.data.per_page;
            var pages    = Math.ceil(total / perPage);
            if (pages > 1) {
                buildPagination('#hm-orders-pagination', OrdersPage.page, pages, function(p) {
                    OrdersPage.page = p;
                    loadOrders();
                });
                $('#hm-orders-pagination').show();
            }
        }).fail(function () {
            $('#hm-orders-loading').hide();
            HM.toast('Network error loading orders.', 'error');
        });
    }

    function buildOrderActions(o) {
        var out = '';
        var isAdmin = HM.is_admin;

        if (o.status === 'Awaiting Approval' && isAdmin) {
            out += '<a href="' + (window.HM.approvals_url || '#') + '" class="hm-btn hm-btn--secondary hm-btn--sm">Go to Approvals</a> ';
        }
        if (o.status === 'Approved' && isAdmin) {
            out += '<button class="hm-btn hm-btn--primary hm-btn--sm hm-mark-ordered-btn" data-id="' + o.id + '" data-num="' + esc(o.order_number) + '">Mark Ordered</button> ';
        }
        if (o.status === 'Ordered') {
            out += '<button class="hm-btn hm-btn--primary hm-btn--sm hm-mark-received-btn" data-id="' + o.id + '" data-num="' + esc(o.order_number) + '">Mark Received</button> ';
        }
        return out || '<span style="color:#94a3b8;font-size:.8rem;">—</span>';
    }

    function buildPagination(selector, current, total, onClick) {
        var $el  = $(selector);
        var html = '';
        for (var i = 1; i <= total; i++) {
            var cls = (i === current) ? 'hm-page-btn active' : 'hm-page-btn';
            html += '<button class="' + cls + '" data-page="' + i + '">' + i + '</button>';
        }
        $el.html(html);
        $el.off('click').on('click', '.hm-page-btn', function () {
            onClick(parseInt($(this).data('page')));
        });
    }

    // ── Order Status Page — DOM ready ──────────────────────────────────────
    $(document).ready(function () {
        if (!$('#hm-orders-tbody').length) return;

        loadOrders();

        // Status pills
        $(document).on('click', '.hm-status-pill', function () {
            $('.hm-status-pill').removeClass('active hm-btn--primary').addClass('hm-btn--secondary');
            $(this).addClass('active hm-btn--primary').removeClass('hm-btn--secondary');
            OrdersPage.filters.status = $(this).data('status');
            OrdersPage.page = 1;
            loadOrders();
        });

        // Clinic filter
        $(document).on('change', '#hm-orders-clinic', function () {
            OrdersPage.filters.clinic_id = $(this).val();
            OrdersPage.page = 1;
            loadOrders();
        });

        // Date filters
        $(document).on('change', '#hm-orders-date-from', function () {
            OrdersPage.filters.date_from = $(this).val();
            OrdersPage.page = 1;
            loadOrders();
        });
        $(document).on('change', '#hm-orders-date-to', function () {
            OrdersPage.filters.date_to = $(this).val();
            OrdersPage.page = 1;
            loadOrders();
        });

        // Search (debounced)
        $(document).on('input', '#hm-orders-search', function () {
            var q = $(this).val();
            clearTimeout(OrdersPage.debounce);
            OrdersPage.debounce = setTimeout(function () {
                OrdersPage.filters.search = q;
                OrdersPage.page = 1;
                loadOrders();
            }, 350);
        });

        // "Create Order" button with no patient context → search modal
        $(document).on('click', '#hm-order-new-btn', function () {
            HM.openOrderModal(0, '');
        });

        // ── Mark Ordered modal ──────────────────────────────────────────
        $(document).on('click', '.hm-mark-ordered-btn', function () {
            $('#hm-mark-ordered-id').val($(this).data('id'));
            $('#hm-mark-ordered-num').text($(this).data('num'));
            $('#hm-mark-ordered-modal-bg').fadeIn(150);
        });
        $(document).on('click', '#hm-mark-ordered-close, #hm-mark-ordered-cancel', function () {
            $('#hm-mark-ordered-modal-bg').fadeOut(150);
        });
        $(document).on('click', '#hm-mark-ordered-confirm', function () {
            var id = $('#hm-mark-ordered-id').val();
            $(this).prop('disabled', true).text('Saving…');
            $.post(HM.ajax_url, { action: 'hm_update_order_status', nonce: HM.nonce, order_id: id, new_status: 'Ordered' },
            function (r) {
                $('#hm-mark-ordered-confirm').prop('disabled', false).text('Mark as Ordered');
                $('#hm-mark-ordered-modal-bg').fadeOut(150);
                if (r.success) { HM.toast('Order marked as Ordered.', 'success'); loadOrders(); }
                else HM.toast(r.data.msg || 'Error updating order.', 'error');
            });
        });

        // ── Mark Received modal ─────────────────────────────────────────
        $(document).on('click', '.hm-mark-received-btn', function () {
            $('#hm-receive-order-id').val($(this).data('id'));
            $('#hm-receive-order-num').text($(this).data('num'));
            $('#hm-receive-modal-bg').fadeIn(150);
        });
        $(document).on('click', '#hm-receive-modal-close, #hm-receive-cancel', function () {
            $('#hm-receive-modal-bg').fadeOut(150);
        });
        $(document).on('click', '#hm-receive-confirm', function () {
            var id = $('#hm-receive-order-id').val();
            $(this).prop('disabled', true).text('Confirming…');
            $.post(HM.ajax_url, { action: 'hm_update_order_status', nonce: HM.nonce, order_id: id, new_status: 'Received' },
            function (r) {
                $('#hm-receive-confirm').prop('disabled', false).text('Confirm Receipt');
                $('#hm-receive-modal-bg').fadeOut(150);
                if (r.success) { HM.toast('Order marked as Received — added to Awaiting Fitting.', 'success'); loadOrders(); }
                else HM.toast(r.data.msg || 'Error updating order.', 'error');
            });
        });

        // ── Order detail modal ──────────────────────────────────────────
        $(document).on('click', '.hm-order-detail-link', function (e) {
            e.preventDefault();
            var id = $(this).data('id');
            $('#hm-order-detail-body').html('<div class="hm-loading"><div class="hm-loading-dots"><div class="hm-loading-dot"></div><div class="hm-loading-dot"></div><div class="hm-loading-dot"></div></div><div class="hm-loading-text">Loading…</div></div>');
            $('#hm-order-detail-modal-bg').fadeIn(150);
            $.post(HM.ajax_url, { action: 'hm_get_order_detail', nonce: HM.nonce, order_id: id },
            function (r) {
                if (!r.success) { $('#hm-order-detail-body').html('<p style="color:#dc2626;">Could not load order.</p>'); return; }
                var o   = r.data;
                var bad = '<span class="hm-badge ' + (STATUS_BADGE[o.status] || 'hm-badge--grey') + '">' + esc(o.status) + '</span>';
                var lines = (o.line_items || []).map(function(li) {
                    return '<tr><td>' + esc(li.product_name) + '</td><td>' + esc(li.ear) + '</td><td>' + (li.qty||1) + '</td>' +
                           '<td>' + eur(li.unit_price) + '</td><td>−' + eur(li.discount) + '</td><td>' + eur(li.line_total) + '</td></tr>';
                }).join('');
                var margin = o.gross_margin_percent !== null
                    ? '<div class="hm-form-group"><label class="hm-label">Gross Margin</label><div>' + parseFloat(o.gross_margin_percent).toFixed(1) + '%</div></div>'
                    : '';
                var html =
                    '<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:18px;font-size:.875rem;">' +
                        '<div><div class="hm-label">Status</div>' + bad + '</div>' +
                        '<div><div class="hm-label">Patient</div><div>' + esc(o.patient_name) + ' · ' + esc(o.patient_number) + '</div></div>' +
                        '<div><div class="hm-label">Clinic</div><div>' + esc(o.clinic_name) + '</div></div>' +
                        '<div><div class="hm-label">Dispenser</div><div>' + esc(o.dispenser_name) + '</div></div>' +
                        '<div><div class="hm-label">Order #</div><div>' + esc(o.order_number) + '</div></div>' +
                        '<div><div class="hm-label">Submitted</div><div>' + fmtDate(o.created_at) + '</div></div>' +
                    '</div>' +
                    '<table class="hm-table" style="margin-bottom:14px;"><thead><tr><th>Product</th><th>Ear</th><th>Qty</th><th>Unit</th><th>Disc</th><th>Total</th></tr></thead><tbody>' + lines + '</tbody></table>' +
                    '<div style="display:flex;justify-content:flex-end;"><div style="min-width:220px;font-size:.875rem;">' +
                        '<div style="display:flex;justify-content:space-between;padding:4px 0;"><span style="color:#64748b;">Subtotal</span><span>' + eur(o.subtotal) + '</span></div>' +
                        '<div style="display:flex;justify-content:space-between;padding:4px 0;"><span style="color:#64748b;">Discount</span><span>−' + eur(o.discount) + '</span></div>' +
                        '<div style="display:flex;justify-content:space-between;padding:4px 0;"><span style="color:#64748b;">VAT</span><span>' + eur(o.vat_total) + '</span></div>' +
                        (o.prsi_applicable ? '<div style="display:flex;justify-content:space-between;padding:4px 0;"><span style="color:#64748b;">PRSI</span><span style="color:var(--hm-teal);">−' + eur(o.prsi_amount) + '</span></div>' : '') +
                        '<div style="display:flex;justify-content:space-between;padding:6px 0;font-weight:700;"><span>Grand Total</span><span>' + eur(o.grand_total) + '</span></div>' +
                    '</div></div>' +
                    margin +
                    (o.notes ? '<div style="margin-top:12px;padding:10px 14px;background:#f8fafc;border-radius:8px;font-size:.875rem;"><strong>Notes: </strong>' + esc(o.notes) + '</div>' : '') +
                    (o.duplicate_flag ? '<div style="margin-top:10px;padding:10px;background:#fff7ed;border:1px solid #f59e0b;border-radius:8px;font-size:.85rem;color:#92400e;">⚠️ <strong>Duplicate flag:</strong> ' + esc(o.duplicate_flag_reason) + '</div>' : '');

                $('#hm-order-detail-title').text('Order ' + o.order_number);
                $('#hm-order-detail-body').html(html);
            });
        });
        $(document).on('click', '#hm-order-detail-close', function () {
            $('#hm-order-detail-modal-bg').fadeOut(150);
        });
        $(document).on('click', '#hm-order-detail-modal-bg', function (e) {
            if ($(e.target).is('#hm-order-detail-modal-bg')) $('#hm-order-detail-modal-bg').fadeOut(150);
        });

        // Reload after order created
        $(document).on('hm:orderCreated', function () { loadOrders(); });
    });

})(jQuery);


// ==========================================================================
// APPROVAL QUEUE PAGE
// ==========================================================================

(function ($) {
    'use strict';

    function eur(n)    { return '€' + parseFloat(n || 0).toFixed(2); }
    function fmtDate(dt) {
        if (!dt) return '—';
        return new Date(dt).toLocaleDateString('en-IE', {day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit'});
    }
    function esc(str) {
        return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    function marginColor(pct) {
        if (pct < 15)  return '#dc2626';
        if (pct < 25)  return '#d97706';
        return '#16a34a';
    }

    function loadApprovals() {
        if (!$('#hm-approvals-list').length) return;

        $('#hm-approvals-loading').show();
        $('#hm-approvals-list').hide().empty();
        $('#hm-approvals-empty').hide();

        $.post(HM.ajax_url, { action: 'hm_get_pending_orders', nonce: HM.nonce }, function (r) {
            $('#hm-approvals-loading').hide();
            if (!r.success) { HM.toast(r.data.msg || 'Could not load approvals.', 'error'); return; }

            var orders = r.data;
            if (!orders || !orders.length) {
                $('#hm-approvals-empty').show();
                return;
            }

            orders.forEach(function (o) { renderApprovalCard(o); });
            $('#hm-approvals-list').show();
        }).fail(function () {
            $('#hm-approvals-loading').hide();
            HM.toast('Network error loading approvals.', 'error');
        });
    }

    function renderApprovalCard(o) {
        var tpl  = document.getElementById('hm-approval-card-tpl');
        if (!tpl) return;
        var node = tpl.content.cloneNode(true);
        var $card = $(node.querySelector('.hm-approval-card'));
        $card.attr('data-order-id', o.id);

        // Header fields
        $card.find('.hm-apc-order-num').text(o.order_number);
        $card.find('.hm-apc-patient').text(o.patient_name + (o.patient_number ? ' · ' + o.patient_number : ''));
        $card.find('.hm-apc-total').text(eur(o.grand_total));

        // Flags
        var flagHtml = '';
        (o.flags || []).forEach(function (f) {
            var cls = f.level === 'red' ? 'hm-badge--red' : 'hm-badge--amber';
            flagHtml += '<span class="hm-badge ' + cls + '" title="' + esc(f.msg) + '" style="font-size:.75rem;">' + esc(f.msg) + '</span> ';
        });
        $card.find('.hm-apc-flags').html(flagHtml);

        // Meta
        $card.find('.hm-apc-dispenser').text(o.dispenser_name);
        $card.find('.hm-apc-clinic').text(o.clinic_name);
        $card.find('.hm-apc-date').text(fmtDate(o.created_at));
        $card.find('.hm-apc-prsi').text(o.prsi_applicable ? 'Yes — ' + eur(o.prsi_amount) : 'No');

        // Line items
        var lines = '';
        (o.line_items || []).forEach(function (li) {
            var margin = '';
            if (li.cost_price > 0 && li.line_total > 0) {
                var totalCost = li.cost_price * (li.qty || 1);
                var m = ((li.line_total - totalCost) / li.line_total) * 100;
                margin = '<strong style="color:' + marginColor(m) + ';">' + m.toFixed(1) + '%</strong>';
            } else {
                margin = '<span style="color:#94a3b8;">—</span>';
            }
            lines += '<tr>' +
                '<td>' + esc(li.product_name) + '</td>' +
                '<td>' + esc(li.manufacturer) + '</td>' +
                '<td>' + esc(li.style) + (li.range ? ' / ' + esc(li.range) : '') + '</td>' +
                '<td>' + esc(li.ear) + '</td>' +
                '<td>' + (li.qty || 1) + '</td>' +
                '<td>' + eur(li.unit_price) + '</td>' +
                '<td>−' + eur(li.discount) + '</td>' +
                '<td>' + (li.vat_rate || 0) + '%</td>' +
                '<td>' + eur(li.line_total) + '</td>' +
                '<td class="hm-margin-col">' + margin + '</td>' +
                '</tr>';
        });
        $card.find('.hm-apc-lines-tbody').html(lines);
        $card.find('.hm-margin-col').show();

        // Totals
        $card.find('.hm-apc-subtotal').text(eur(o.subtotal));
        $card.find('.hm-apc-discount').text('−' + eur(o.discount));
        $card.find('.hm-apc-vat').text(eur(o.vat_total));
        if (o.prsi_applicable) {
            $card.find('.hm-apc-prsi-row').show();
            $card.find('.hm-apc-prsi-val').text('−' + eur(o.prsi_amount));
        } else {
            $card.find('.hm-apc-prsi-row').hide();
        }
        $card.find('.hm-apc-grand').text(eur(o.grand_total));

        var mPct = parseFloat(o.gross_margin_percent || 0);
        $card.find('.hm-apc-margin').text(mPct.toFixed(1) + '%').css('color', marginColor(mPct));

        // Notes
        if (o.notes) {
            $card.find('.hm-apc-notes').text(o.notes);
            $card.find('.hm-apc-notes-wrap').show();
        }

        // Expand/collapse
        $card.find('[data-toggle="expand"]').on('click', function () {
            var $body = $card.find('.hm-apc-body');
            var $icon = $card.find('.hm-expand-icon');
            $body.slideToggle(200);
            $icon.css('transform', $body.is(':visible') ? 'rotate(180deg)' : '');
        });
        // Auto-expand first card
        if ($('#hm-approvals-list').children().length === 0) {
            $card.find('.hm-apc-body').show();
            $card.find('.hm-expand-icon').css('transform','rotate(180deg)');
        }

        // Approve
        $card.find('.hm-apc-approve-btn').on('click', function () {
            var $btn = $(this);
            if (!confirm('Approve order ' + o.order_number + '?\nFinance will be notified to place the order.')) return;
            $btn.prop('disabled', true).text('Approving…');
            $.post(HM.ajax_url, { action: 'hm_approve_order', nonce: HM.nonce, order_id: o.id }, function (r) {
                if (r.success) {
                    $card.animate({opacity:0, height:0, marginBottom:0}, 300, function () {
                        $card.remove();
                        HM.toast('Order ' + o.order_number + ' approved — Finance notified.', 'success');
                        if (!$('#hm-approvals-list').children(':visible').length) $('#hm-approvals-empty').show();
                    });
                } else {
                    $btn.prop('disabled', false).text('Approve');
                    HM.toast(r.data.msg || 'Error approving order.', 'error');
                }
            });
        });

        // Deny — open modal
        $card.find('.hm-apc-deny-btn').on('click', function () {
            $('#hm-deny-order-id').val(o.id);
            $('#hm-deny-reason').val('');
            $('#hm-deny-modal-bg').fadeIn(150);
        });

        $('#hm-approvals-list').append($card);
    }

    $(document).ready(function () {
        if (!$('#hm-approvals-list').length) return;

        loadApprovals();

        $('#hm-approvals-refresh').on('click', loadApprovals);

        // Deny modal
        $(document).on('click', '#hm-deny-modal-close, #hm-deny-cancel', function () {
            $('#hm-deny-modal-bg').fadeOut(150);
        });
        $(document).on('click', '#hm-deny-modal-bg', function (e) {
            if ($(e.target).is('#hm-deny-modal-bg')) $('#hm-deny-modal-bg').fadeOut(150);
        });
        $(document).on('click', '#hm-deny-confirm', function () {
            var id     = $('#hm-deny-order-id').val();
            var reason = $.trim($('#hm-deny-reason').val());
            if (!reason) { HM.toast('Please enter a denial reason.', 'error'); return; }

            $(this).prop('disabled', true).text('Denying…');
            $.post(HM.ajax_url, { action: 'hm_deny_order', nonce: HM.nonce, order_id: id, reason: reason }, function (r) {
                $('#hm-deny-confirm').prop('disabled', false).text('Deny Order');
                $('#hm-deny-modal-bg').fadeOut(150);
                if (r.success) {
                    var $card = $('[data-order-id="' + id + '"]');
                    $card.animate({opacity:0, height:0, marginBottom:0}, 300, function () {
                        $card.remove();
                        HM.toast('Order denied — dispenser notified.', 'success');
                        if (!$('#hm-approvals-list').children(':visible').length) $('#hm-approvals-empty').show();
                    });
                } else {
                    HM.toast(r.data.msg || 'Error denying order.', 'error');
                }
            });
        });
    });

})(jQuery);


// ==========================================================================
// AWAITING FITTING PAGE
// ==========================================================================

(function ($) {
    'use strict';

    function eur(n) { return '€' + parseFloat(n || 0).toFixed(2); }
    function fmtDate(d) {
        if (!d) return null;
        return new Date(d).toLocaleDateString('en-IE', {day:'2-digit', month:'short', year:'numeric'});
    }
    function esc(s) { return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    function loadAwaitingFitting() {
        if (!$('#hm-af-tbody').length) return;

        $('#hm-af-loading').show();
        $('#hm-af-tbody').html('<tr><td colspan="9" style="text-align:center;padding:2rem;color:#94a3b8;">Loading…</td></tr>');
        $('#hm-af-empty').hide();

        $.post(HM.ajax_url, {
            action   : 'hm_get_awaiting_fitting',
            nonce    : HM.nonce,
            clinic_id: $('#hm-af-clinic').val()  || '',
            date_from: $('#hm-af-date-from').val() || '',
            date_to  : $('#hm-af-date-to').val()   || '',
        }, function (r) {
            $('#hm-af-loading').hide();
            if (!r.success) { HM.toast('Could not load Awaiting Fitting.', 'error'); return; }

            var rows = r.data;
            if (!rows || !rows.length) {
                $('#hm-af-tbody').html('');
                $('#hm-af-empty').show();
                return;
            }

            var today  = new Date(); today.setHours(0,0,0,0);
            var html   = '';
            rows.forEach(function (row) {
                var fittingCell;
                if (row.fitting_date) {
                    var fd   = new Date(row.fitting_date);
                    var past = fd < today;
                    fittingCell = '<span style="color:' + (past ? '#dc2626' : '#151B33') + ';">' +
                        fmtDate(row.fitting_date) + (past ? ' ⚠️' : '') + '</span>';
                } else {
                    fittingCell = '<span style="color:#d97706;">Not scheduled</span>';
                }

                html += '<tr>' +
                    '<td>' + esc(row.patient_name) + '</td>' +
                    '<td><code>' + esc(row.patient_number) + '</code></td>' +
                    '<td>' + esc(row.clinic_name) + '</td>' +
                    '<td>' + esc(row.dispenser_name) + '</td>' +
                    '<td>' + esc(row.product_description) + '</td>' +
                    '<td>' + eur(row.total_price) + '</td>' +
                    '<td>' + (row.prsi_applicable ? '<span class="hm-badge hm-badge-teal">−' + eur(row.prsi_amount) + '</span>' : '—') + '</td>' +
                    '<td>' + fittingCell + '</td>' +
                    '<td><button class="hm-btn hm-btn-danger hm-btn--sm hm-prefit-cancel-btn" data-order-id="' + row.order_id + '">Pre-Fit Cancel</button></td>' +
                    '</tr>';
            });
            $('#hm-af-tbody').html(html);
        }).fail(function () {
            $('#hm-af-loading').hide();
            HM.toast('Network error.', 'error');
        });
    }

    $(document).ready(function () {
        if (!$('#hm-af-tbody').length) return;

        loadAwaitingFitting();

        $(document).on('change', '#hm-af-clinic, #hm-af-date-from, #hm-af-date-to', loadAwaitingFitting);
        $(document).on('click', '#hm-af-refresh', loadAwaitingFitting);

        // Pre-Fit Cancel modal
        $(document).on('click', '.hm-prefit-cancel-btn', function () {
            $('#hm-prefit-order-id').val($(this).data('order-id'));
            $('#hm-prefit-reason').val('');
            $('#hm-prefit-cancel-modal-bg').fadeIn(150);
        });
        $(document).on('click', '#hm-prefit-cancel-close, #hm-prefit-cancel-dismiss', function () {
            $('#hm-prefit-cancel-modal-bg').fadeOut(150);
        });
        $(document).on('click', '#hm-prefit-cancel-confirm', function () {
            var id     = $('#hm-prefit-order-id').val();
            var reason = $.trim($('#hm-prefit-reason').val());
            if (!reason) { HM.toast('A cancellation reason is required.', 'error'); return; }
            $(this).prop('disabled', true).text('Cancelling…');
            $.post(HM.ajax_url, { action: 'hm_prefit_cancel', nonce: HM.nonce, order_id: id, reason: reason }, function (r) {
                $('#hm-prefit-cancel-confirm').prop('disabled', false).text('Confirm Cancellation');
                $('#hm-prefit-cancel-modal-bg').fadeOut(150);
                if (r.success) { HM.toast('Order cancelled and removed from Awaiting Fitting.', 'success'); loadAwaitingFitting(); }
                else HM.toast(r.data.msg || 'Error cancelling.', 'error');
            });
        });
    });

})(jQuery);
