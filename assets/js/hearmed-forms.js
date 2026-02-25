/**
 * HearMed Forms — Signature Capture & Form Handling
 * Handles Wacom STU tablet (primary) with mouse/touch canvas fallback.
 *
 * Global: window.hmForms
 * Requires: window._hmFormsAjax, _hmFormsNonce, _hmFormsPatientId
 */
(function () {
    'use strict';

    const ajax      = window._hmFormsAjax      || '';
    const nonce     = window._hmFormsNonce     || '';
    const patientId = window._hmFormsPatientId || 0;

    // ─── State ───────────────────────────────────────────────────────────
    let currentTemplate     = null;   // { id, name, form_type, requires_signature, fields_schema }
    let fieldValues         = {};     // { fieldId: value }
    let signatureImage      = null;   // base64 PNG string
    let signatureBiometric  = null;   // JSON string from Wacom (null if mouse)
    let sigCanvas           = null;   // <canvas> element
    let sigCtx              = null;   // 2D context
    let isDrawing           = false;
    let viewFormId          = null;   // ID of form currently in view modal

    // ─── Modal helpers ───────────────────────────────────────────────────

    function showModal(id) {
        const el = document.getElementById(id);
        if (el) el.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }

    function hideModal(id) {
        const el = document.getElementById(id);
        if (el) el.style.display = 'none';
        document.body.style.overflow = '';
    }

    // ─── Template Picker ─────────────────────────────────────────────────

    function openPicker() {
        showModal('hm-picker-modal');
    }

    function closePicker() {
        hideModal('hm-picker-modal');
    }

    // ─── Load Form Template ───────────────────────────────────────────────

    function loadForm(templateId) {
        closePicker();
        signatureImage     = null;
        signatureBiometric = null;
        fieldValues        = {};
        currentTemplate    = null;

        const body = document.getElementById('hm-form-modal-body');
        const foot = document.getElementById('hm-form-modal-footer');
        const btn  = document.getElementById('hm-submit-form-btn');
        const msg  = document.getElementById('hm-form-msg');

        body.innerHTML   = '<div class="hm-loading">Loading form…</div>';
        foot.style.display = 'none';
        msg.style.display  = 'none';

        document.getElementById('hm-form-modal-title').textContent = 'Loading…';
        showModal('hm-form-modal');

        fetch(ajax, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'hm_load_form_template',
                nonce,
                template_id: templateId,
                patient_id:  patientId,
            }),
        })
        .then(r => r.json())
        .then(d => {
            if (!d.success) { body.innerHTML = '<div class="hm-notice hm-notice--error">' + d.data + '</div>'; return; }

            currentTemplate = d.data;
            document.getElementById('hm-form-modal-title').textContent = d.data.name;
            body.innerHTML = buildFormBody(d.data);
            foot.style.display = 'flex';
            btn.disabled = true;

            initFieldBindings(d.data.fields_schema);

            if (d.data.requires_signature) {
                initSignaturePad();
            }

            checkSubmitReady();
        })
        .catch(() => {
            body.innerHTML = '<div class="hm-notice hm-notice--error">Network error. Please try again.</div>';
        });
    }

    // ─── Build Form HTML ──────────────────────────────────────────────────

    function buildFormBody(tpl) {
        let html = '<div class="hm-form-document">';
        html += tpl.html; // Already has placeholders resolved + signature block if needed

        // If there are extra fields_schema inputs, render them in a separate section
        if (tpl.fields_schema && tpl.fields_schema.length > 0) {
            // Filter out checkboxes that are already inline in the HTML (marketing, gdpr)
            const extraFields = tpl.fields_schema.filter(f =>
                !['gdpr_consent','marketing_email','marketing_phone','marketing_sms',
                  'treatment_consent','gdpr_confirm'].includes(f.id)
            );
            if (extraFields.length > 0) {
                html += '<div class="hm-form-fields">';
                extraFields.forEach(f => { html += buildFieldInput(f); });
                html += '</div>';
            }
        }

        html += '</div>';
        return html;
    }

    function buildFieldInput(field) {
        const req = field.required ? '<span class="hm-required">*</span>' : '';
        let inp   = '';

        if (field.type === 'textarea') {
            inp = `<textarea class="hm-input hm-input--textarea hm-field-input"
                             id="hm-field-${field.id}" data-id="${field.id}"
                             rows="3" ${field.required ? 'required' : ''}></textarea>`;
        } else if (field.type === 'select' && field.options) {
            const opts = field.options.map(o =>
                `<option value="${esc(o)}">${esc(o)}</option>`
            ).join('');
            inp = `<select class="hm-input hm-field-input" id="hm-field-${field.id}" data-id="${field.id}">
                       <option value="">— Select —</option>${opts}
                   </select>`;
        } else if (field.type === 'checkbox') {
            return `<div class="hm-form-field hm-form-field--check">
                        <label class="hm-checkbox-label">
                            <input type="checkbox" class="hm-field-input" id="hm-field-${field.id}" data-id="${field.id}">
                            ${esc(field.label)} ${req}
                        </label>
                    </div>`;
        } else {
            inp = `<input type="text" class="hm-input hm-field-input"
                          id="hm-field-${field.id}" data-id="${field.id}"
                          ${field.required ? 'required' : ''}>`;
        }

        return `<div class="hm-form-field">
                    <label class="hm-label" for="hm-field-${field.id}">${esc(field.label)} ${req}</label>
                    ${inp}
                </div>`;
    }

    // ─── Field Bindings ───────────────────────────────────────────────────

    function initFieldBindings(schema) {
        // Bind schema-driven fields
        document.querySelectorAll('#hm-form-modal-body .hm-field-input').forEach(el => {
            el.addEventListener('change', () => {
                const id = el.dataset.id;
                if (!id) return;
                if (el.type === 'checkbox') {
                    fieldValues[id] = el.checked;
                    // Sync gdpr_consent / treatment_consent flags
                    if (id === 'gdpr_confirm') fieldValues['gdpr_consent'] = el.checked;
                } else {
                    fieldValues[id] = el.value;
                }
                checkSubmitReady();
            });
            el.addEventListener('input', () => {
                const id = el.dataset.id;
                if (id && el.type !== 'checkbox') {
                    fieldValues[id] = el.value;
                    checkSubmitReady();
                }
            });
        });

        // Bind inline consent checkboxes (marketing, GDPR)
        ['marketing_email','marketing_phone','marketing_sms','gdpr_confirm','treatment_consent'].forEach(cid => {
            const el = document.querySelector(`[data-field="${cid}"], #hm-field-${cid}`);
            if (el) {
                el.addEventListener('change', () => {
                    fieldValues[cid] = el.checked;
                    if (cid === 'gdpr_confirm') fieldValues['gdpr_consent'] = el.checked;
                    checkSubmitReady();
                });
            }
        });
    }

    function checkSubmitReady() {
        const btn = document.getElementById('hm-submit-form-btn');
        if (!btn || !currentTemplate) return;

        const schema = currentTemplate.fields_schema || [];

        // Check required fields
        const requiredMet = schema.every(f => {
            if (!f.required) return true;
            const val = fieldValues[f.id];
            if (f.type === 'checkbox') return val === true;
            return val && String(val).trim().length > 0;
        });

        // Signature required?
        const sigMet = !currentTemplate.requires_signature || signatureImage !== null;

        btn.disabled = !(requiredMet && sigMet);
    }

    // ─── Signature Pad ────────────────────────────────────────────────────

    function initSignaturePad() {
        sigCanvas = document.getElementById('hm-sig-canvas');
        if (!sigCanvas) return;
        sigCtx = sigCanvas.getContext('2d');

        const statusEl = document.getElementById('hm-sig-device');

        // Try Wacom STU first
        if (typeof window.STUCapture !== 'undefined') {
            setDeviceStatus('connecting');
            tryWacomSTU(statusEl);
        } else {
            setDeviceStatus('fallback');
            initMouseSignature();
        }
    }

    function setDeviceStatus(state) {
        const el = document.getElementById('hm-sig-device');
        if (!el) return;
        const labels = {
            checking:   '🟡 Checking signature device…',
            connecting: '🟡 Connecting to Wacom pad…',
            connected:  '🟢 Wacom STU pad connected — sign below',
            fallback:   '⚪ Sign below using mouse or touch',
        };
        el.textContent = labels[state] || '';
    }

    function tryWacomSTU(statusEl) {
        try {
            const tablet = new window.STUCapture.Tablet();
            tablet.connect().then(() => {
                setDeviceStatus('connected');

                const points = [];
                tablet.onData = (data) => {
                    if (!sigCanvas) return;
                    if (data.sw) { // pen down
                        points.push({ x: data.x, y: data.y, p: data.pressure, t: Date.now() });
                        const rect   = sigCanvas.getBoundingClientRect();
                        const scaleX = rect.width  / tablet.capability.tabletMaxX;
                        const scaleY = rect.height / tablet.capability.tabletMaxY;
                        const cx     = data.x * scaleX;
                        const cy     = data.y * scaleY;

                        if (points.length === 1) {
                            sigCtx.beginPath();
                            sigCtx.moveTo(cx, cy);
                        } else {
                            sigCtx.lineTo(cx, cy);
                            sigCtx.strokeStyle = '#151B33';
                            sigCtx.lineWidth   = 2;
                            sigCtx.lineCap     = 'round';
                            sigCtx.stroke();
                        }
                        hidePlaceholder();
                    }
                };

                tablet.onDisconnect = () => setDeviceStatus('fallback');

                // Capture image on pen-up
                sigCanvas.addEventListener('wacom-penup', () => {
                    if (points.length > 5) {
                        signatureImage     = sigCanvas.toDataURL('image/png');
                        signatureBiometric = JSON.stringify({ points, device: 'Wacom STU' });
                        checkSubmitReady();
                    }
                });

            }).catch(() => {
                setDeviceStatus('fallback');
                initMouseSignature();
            });
        } catch (e) {
            setDeviceStatus('fallback');
            initMouseSignature();
        }
    }

    function initMouseSignature() {
        if (!sigCanvas) return;

        sigCtx.strokeStyle = '#151B33';
        sigCtx.lineWidth   = 2;
        sigCtx.lineCap     = 'round';
        sigCtx.lineJoin    = 'round';

        const getPos = (e) => {
            const rect = sigCanvas.getBoundingClientRect();
            const src  = e.touches ? e.touches[0] : e;
            return {
                x: src.clientX - rect.left,
                y: src.clientY - rect.top,
            };
        };

        const onStart = (e) => {
            e.preventDefault();
            isDrawing = true;
            const pos = getPos(e);
            sigCtx.beginPath();
            sigCtx.moveTo(pos.x, pos.y);
            hidePlaceholder();
        };

        const onMove = (e) => {
            e.preventDefault();
            if (!isDrawing) return;
            const pos = getPos(e);
            sigCtx.lineTo(pos.x, pos.y);
            sigCtx.stroke();
        };

        const onEnd = (e) => {
            e.preventDefault();
            if (!isDrawing) return;
            isDrawing = false;
            signatureImage     = sigCanvas.toDataURL('image/png');
            signatureBiometric = null; // no biometric from mouse
            checkSubmitReady();
        };

        sigCanvas.addEventListener('mousedown',  onStart);
        sigCanvas.addEventListener('mousemove',  onMove);
        sigCanvas.addEventListener('mouseup',    onEnd);
        sigCanvas.addEventListener('mouseleave', onEnd);
        sigCanvas.addEventListener('touchstart', onStart, { passive: false });
        sigCanvas.addEventListener('touchmove',  onMove,  { passive: false });
        sigCanvas.addEventListener('touchend',   onEnd,   { passive: false });
    }

    function hidePlaceholder() {
        const ph = document.getElementById('hm-sig-placeholder');
        if (ph) ph.style.display = 'none';
    }

    function clearSignature() {
        if (!sigCanvas || !sigCtx) return;
        sigCtx.clearRect(0, 0, sigCanvas.width, sigCanvas.height);
        signatureImage     = null;
        signatureBiometric = null;
        const ph = document.getElementById('hm-sig-placeholder');
        if (ph) ph.style.display = 'flex';
        checkSubmitReady();
    }

    function closeForm() {
        hideModal('hm-form-modal');
        currentTemplate = null;
        signatureImage  = null;
        fieldValues     = {};
    }

    // ─── Submit Form ──────────────────────────────────────────────────────

    function submitForm() {
        if (!currentTemplate) return;
        const btn = document.getElementById('hm-submit-form-btn');
        const msg = document.getElementById('hm-form-msg');
        btn.disabled    = true;
        btn.textContent = 'Saving…';
        msg.style.display = 'none';

        // Collect rendered HTML snapshot
        const docEl       = document.querySelector('#hm-form-modal-body .hm-form-document');
        const renderedHtml = docEl ? docEl.innerHTML : '';

        // Extract consent flags from fieldValues or inline checkboxes
        const gdprConsent      = fieldValues['gdpr_consent']      || fieldValues['gdpr_confirm'] || false;
        const treatmentConsent = fieldValues['treatment_consent']  || false;
        const marketingEmail   = fieldValues['marketing_email']    || false;
        const marketingPhone   = fieldValues['marketing_phone']    || false;
        const marketingSms     = fieldValues['marketing_sms']      || false;

        const params = new URLSearchParams({
            action:               'hm_submit_form',
            nonce,
            patient_id:           patientId,
            template_id:          currentTemplate.id,
            form_type:            currentTemplate.form_type,
            form_title:           currentTemplate.name,
            form_data:            JSON.stringify(fieldValues),
            signature_image:      signatureImage    || '',
            signature_biometric:  signatureBiometric || '',
            gdpr_consent:         gdprConsent      ? '1' : '',
            treatment_consent:    treatmentConsent  ? '1' : '',
            marketing_email:      marketingEmail    ? '1' : '',
            marketing_phone:      marketingPhone    ? '1' : '',
            marketing_sms:        marketingSms      ? '1' : '',
            rendered_html:        renderedHtml,
        });

        fetch(ajax, { method: 'POST', body: params })
        .then(r => r.json())
        .then(d => {
            msg.style.display = 'block';
            if (d.success) {
                msg.className   = 'hm-notice hm-notice--success';
                msg.textContent = d.data.message || 'Form saved.';
                setTimeout(() => {
                    closeForm();
                    window.location.reload();
                }, 1200);
            } else {
                msg.className   = 'hm-notice hm-notice--error';
                msg.textContent = d.data || 'Error saving form.';
                btn.disabled    = false;
                btn.textContent = 'Submit & Save';
            }
        })
        .catch(() => {
            msg.style.display = 'block';
            msg.className     = 'hm-notice hm-notice--error';
            msg.textContent   = 'Network error. Please try again.';
            btn.disabled      = false;
            btn.textContent   = 'Submit & Save';
        });
    }

    // ─── View Submitted Form ──────────────────────────────────────────────

    function viewForm(formId) {
        viewFormId = formId;
        const body = document.getElementById('hm-view-modal-body');
        body.innerHTML = '<div class="hm-loading">Loading…</div>';
        showModal('hm-view-modal');

        fetch(ajax, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'hm_get_form_view', nonce, form_id: formId }),
        })
        .then(r => r.json())
        .then(d => {
            if (!d.success) { body.innerHTML = '<p class="hm-notice hm-notice--error">' + d.data + '</p>'; return; }
            const f = d.data;

            let html = '<div class="hm-doc-view-wrap">';
            html += `<div class="hm-doc-header">
                        <strong>${esc(f.title)}</strong> — ${esc(f.patient_name)}<br>
                        <span class="hm-muted">Submitted ${esc(f.date)} by ${esc(f.signed_by || '—')}</span>
                        ${!f.is_valid ? '<span class="hm-badge hm-badge--red" style="margin-left:8px;">Superseded</span>' : ''}
                     </div>`;

            // Show rendered HTML snapshot if available
            if (f.rendered_html) {
                html += '<div class="hm-doc-content">' + f.rendered_html + '</div>';
            } else if (f.form_data && Object.keys(f.form_data).length > 0) {
                html += '<div class="hm-doc-content hm-doc-fields">';
                Object.entries(f.form_data).forEach(([k, v]) => {
                    if (!v && v !== false) return;
                    html += `<div class="hm-doc-field">
                                <span class="hm-doc-field__key">${esc(k.replace(/_/g,' '))}</span>
                                <span class="hm-doc-field__val">${esc(String(v))}</span>
                             </div>`;
                });
                html += '</div>';
            }

            // Signature
            if (f.signature_url) {
                html += `<div class="hm-doc-signature">
                            <div class="hm-sig-label">Signature</div>
                            <img src="${esc(f.signature_url)}" alt="Signature" class="hm-sig-img">
                         </div>`;
            }

            html += '</div>';
            body.innerHTML = html;
        })
        .catch(() => {
            body.innerHTML = '<p class="hm-notice hm-notice--error">Network error.</p>';
        });
    }

    function closeView() {
        hideModal('hm-view-modal');
        viewFormId = null;
    }

    function printForm() {
        const body = document.getElementById('hm-view-modal-body');
        if (!body) return;
        const w = window.open('', '_blank');
        w.document.write(`<!DOCTYPE html><html><head><title>Form Record</title>
            <style>body{font-family:Arial,sans-serif;padding:1.5rem;max-width:820px;margin:0 auto;color:#151B33;}
            .hm-muted{color:#64748b;font-size:0.85rem;} img{max-width:100%;border:1px solid #e2e8f0;}
            .hm-badge--red{color:#dc2626;font-weight:bold;}</style></head><body>`);
        w.document.write(body.innerHTML);
        w.document.write('</body></html>');
        w.document.close();
        w.print();
    }

    // ─── Invalidate Form ──────────────────────────────────────────────────

    function invalidateForm(formId) {
        if (!confirm('Mark this form as superseded / invalid? This cannot be undone.')) return;
        fetch(ajax, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ action: 'hm_invalidate_form', nonce, form_id: formId }),
        })
        .then(r => r.json())
        .then(d => {
            if (d.success) window.location.reload();
            else alert('Error: ' + d.data);
        });
    }

    // ─── Utility ─────────────────────────────────────────────────────────

    function esc(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // ─── Public API ───────────────────────────────────────────────────────

    window.hmForms = {
        openPicker,
        closePicker,
        loadForm,
        closeForm,
        clearSignature,
        submitForm,
        viewForm,
        closeView,
        printForm,
        invalidateForm,
    };

})();