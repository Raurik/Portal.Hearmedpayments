/**
 * HearMed Portal - Core JavaScript v4.0
 * 
 * Provides:
 * - AJAX API wrapper
 * - Modal system
 * - Toast notifications
 * - Utility functions
 * 
 * Global object: HM (defined by wp_localize_script)
 */

(function($) {
    'use strict';
    
    // Extend HM object with core functionality
    window.HM = window.HM || {};
    
    /**
     * AJAX API - Unified wrapper for all AJAX requests
     * 
     * Usage: HM.api('get_patients', { clinic_id: 1 }).then(response => { ... })
     */
    HM.api = function(action, data, options) {
        data = data || {};
        options = options || {};
        
        // Add nonce
        data.nonce = HM.nonce;
        data.action = 'hm_' + action;
        
        // Default options
        const defaults = {
            method: 'POST',
            url: HM.ajax_url,
            data: data,
            dataType: 'json',
        };
        
        const settings = $.extend({}, defaults, options);
        
        return $.ajax(settings)
            .then(function(response) {
                if (response.success) {
                    return response.data;
                } else {
                    throw new Error(response.data?.message || 'Request failed');
                }
            })
            .catch(function(error) {
                console.error('AJAX Error:', error);
                HM.toast(error.message || 'An error occurred', 'error');
                throw error;
            });
    };
    
    /**
     * Toast notification system
     * 
     * Usage: HM.toast('Patient saved successfully', 'success')
     */
    HM.toast = function(message, type) {
        type = type || 'info';
        
        const toast = $('<div class="hm-toast hm-toast-' + type + '">' + message + '</div>');
        
        // Create container if it doesn't exist
        if (!$('.hm-toast-container').length) {
            $('body').append('<div class="hm-toast-container"></div>');
        }
        
        $('.hm-toast-container').append(toast);
        
        // Animate in
        setTimeout(function() {
            toast.addClass('show');
        }, 10);
        
        // Remove after 3 seconds
        setTimeout(function() {
            toast.removeClass('show');
            setTimeout(function() {
                toast.remove();
            }, 300);
        }, 3000);
    };
    
    /**
     * Modal system
     * 
     * Usage: HM.modal(content, options)
     */
    HM.modal = function(content, options) {
        options = options || {};
        
        const defaults = {
            title: '',
            width: '600px',
            closeButton: true,
            onClose: null,
        };
        
        const settings = $.extend({}, defaults, options);
        
        // Create modal HTML
        const modal = $(`
            <div class="hm-modal-overlay">
                <div class="hm-modal" style="max-width: ${settings.width}">
                    ${settings.title ? '<div class="hm-modal-header">' + settings.title + '</div>' : ''}
                    <div class="hm-modal-body">${content}</div>
                    ${settings.closeButton ? '<button class="hm-modal-close">&times;</button>' : ''}
                </div>
            </div>
        `);
        
        $('body').append(modal);
        
        // Close on overlay click
        modal.on('click', function(e) {
            if ($(e.target).hasClass('hm-modal-overlay') || $(e.target).hasClass('hm-modal-close')) {
                HM.closeModal(modal, settings.onClose);
            }
        });
        
        // Close on Escape key
        $(document).on('keyup.hmmodal', function(e) {
            if (e.key === 'Escape') {
                HM.closeModal(modal, settings.onClose);
            }
        });
        
        return modal;
    };
    
    /**
     * Close modal
     */
    HM.closeModal = function(modal, callback) {
        modal = modal || $('.hm-modal-overlay');
        
        modal.fadeOut(200, function() {
            modal.remove();
            $(document).off('keyup.hmmodal');
            if (callback) callback();
        });
    };
    
    /**
     * Confirm dialog
     * 
     * Usage: HM.confirm('Are you sure?', callback)
     */
    HM.confirm = function(message, onConfirm, onCancel) {
        const content = `
            <p>${message}</p>
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button class="hm-btn" data-action="cancel">Cancel</button>
                <button class="hm-btn hm-btn-primary" data-action="confirm">Confirm</button>
            </div>
        `;
        
        const modal = HM.modal(content, { title: 'Confirm' });
        
        modal.on('click', '[data-action="confirm"]', function() {
            HM.closeModal(modal);
            if (onConfirm) onConfirm();
        });
        
        modal.on('click', '[data-action="cancel"]', function() {
            HM.closeModal(modal);
            if (onCancel) onCancel();
        });
    };
    
    /**
     * Loading indicator
     */
    HM.showLoading = function(element) {
        element = element || $('#hm-app');
        element.append('<div class="hm-loading-overlay"><div class="hm-spinner"></div></div>');
    };
    
    HM.hideLoading = function(element) {
        element = element || $('#hm-app');
        element.find('.hm-loading-overlay').remove();
    };
    
    /**
     * Format money
     */
    HM.formatMoney = function(amount, currency) {
        currency = currency || '€';
        return currency + parseFloat(amount).toFixed(2);
    };
    
    /**
     * Format date
     */
    HM.formatDate = function(date, format) {
        format = format || 'd/m/Y';
        // Simple date formatting (extend as needed)
        const d = new Date(date);
        const day = ('0' + d.getDate()).slice(-2);
        const month = ('0' + (d.getMonth() + 1)).slice(-2);
        const year = d.getFullYear();
        
        return format
            .replace('d', day)
            .replace('m', month)
            .replace('Y', year);
    };
    
    /**
     * Debounce function
     */
    HM.debounce = function(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    };
    
    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        // Add loading indicator to AJAX requests
        $(document).ajaxStart(function() {
            // Optional: Show global loading indicator
        });
        
        $(document).ajaxStop(function() {
            // Optional: Hide global loading indicator
        });
        
        // Handle AJAX errors globally
        $(document).ajaxError(function(event, jqxhr, settings, thrownError) {
            if (jqxhr.status === 403) {
                HM.toast('Session expired. Please log in again.', 'error');
            }
        });
    });
    
})(jQuery);

/**
 * Global inline "Add New" handler for <select data-entity="xxx">.
 * Opens a quick-add modal with fields appropriate for the entity type.
 * Saves via AJAX and inserts the new <option> into all matching selects.
 */
(function() {
    // Define the fields needed for each entity quick-add
    var entitySchemas = {
        manufacturer: {
            title: 'Add Manufacturer',
            fields: [
                {key:'name', label:'Manufacturer Name *', type:'text', required:true, placeholder:'e.g. Oticon'}
            ]
        },
        clinic: {
            title: 'Add Clinic',
            fields: [
                {key:'name',    label:'Clinic Name *', type:'text', required:true, placeholder:'e.g. Dublin North'},
                {key:'address', label:'Address',       type:'text', placeholder:'Street address'},
                {key:'phone',   label:'Phone',         type:'text', placeholder:'e.g. 01 234 5678'},
                {key:'email',   label:'Email',         type:'email',placeholder:'clinic@example.com'}
            ]
        },
        role: {
            title: 'Add Role',
            fields: [
                {key:'name', label:'Role Name *', type:'text', required:true, placeholder:'e.g. Clinical Assistant'}
            ]
        },
        appointment_type: {
            title: 'Add Appointment Type',
            fields: [
                {key:'name',     label:'Type Name *',         type:'text',   required:true, placeholder:'e.g. Follow-Up'},
                {key:'duration', label:'Duration (minutes)',   type:'number', placeholder:'30'},
                {key:'colour',   label:'Block Colour',        type:'color',  defaultVal:'#0BB4C4'}
            ]
        },
        resource_type: {
            title: 'Add Resource Type',
            fields: [
                {key:'name', label:'Type Name *', type:'text', required:true, placeholder:'e.g. Audiometer'}
            ]
        },
        ha_style: {
            title: 'Add Style',
            fields: [
                {key:'name', label:'Style Name *', type:'text', required:true, placeholder:'e.g. BTE'}
            ]
        },
        power_type: {
            title: 'Add Power Type',
            fields: [
                {key:'name', label:'Power Type *', type:'text', required:true, placeholder:'e.g. Rechargeable'}
            ]
        },
        speaker_power: {
            title: 'Add Speaker Power',
            fields: [
                {key:'name', label:'Speaker Power *', type:'text', required:true, placeholder:'e.g. Power 100'}
            ]
        },
        bundled_category: {
            title: 'Add Category',
            fields: [
                {key:'name', label:'Category Name *', type:'text', required:true, placeholder:'e.g. Dome'}
            ]
        }
    };

    var _activeSel = null; // store the select that triggered the modal

    // Build modal DOM once
    function ensureModal() {
        if (document.getElementById('hm-quickadd-modal')) return;
        var html = '<div class="hm-modal-bg" id="hm-quickadd-modal">' +
            '<div class="hm-modal" style="width:460px">' +
            '<div class="hm-modal-hd"><h3 id="hm-qa-title">Add New</h3>' +
            '<button class="hm-modal-x" id="hm-qa-close">&times;</button></div>' +
            '<div class="hm-modal-body" id="hm-qa-body"></div>' +
            '<div class="hm-modal-ft">' +
            '<button class="hm-btn" id="hm-qa-cancel">Cancel</button>' +
            '<button class="hm-btn hm-btn-teal" id="hm-qa-save">Save</button>' +
            '</div></div></div>';
        var div = document.createElement('div');
        div.innerHTML = html;
        document.body.appendChild(div.firstChild);

        document.getElementById('hm-qa-close').addEventListener('click', closeModal);
        document.getElementById('hm-qa-cancel').addEventListener('click', closeModal);
        document.getElementById('hm-qa-save').addEventListener('click', doSave);
    }

    function closeModal() {
        var m = document.getElementById('hm-quickadd-modal');
        if (m) m.classList.remove('open');
        if (_activeSel) { _activeSel.value = ''; _activeSel = null; }
    }

    function openModal(sel, entity) {
        ensureModal();
        _activeSel = sel;
        var schema = entitySchemas[entity];
        if (!schema) { sel.value = ''; return; }

        document.getElementById('hm-qa-title').textContent = schema.title;
        var body = document.getElementById('hm-qa-body');
        body.innerHTML = '';
        body.setAttribute('data-entity', entity);

        schema.fields.forEach(function(f) {
            var grp = document.createElement('div');
            grp.className = 'hm-form-group';
            grp.style.marginBottom = '12px';

            var lbl = document.createElement('label');
            lbl.textContent = f.label;
            grp.appendChild(lbl);

            var inp = document.createElement('input');
            inp.type = f.type || 'text';
            inp.setAttribute('data-key', f.key);
            inp.className = 'hm-qa-field';
            if (f.placeholder) inp.placeholder = f.placeholder;
            if (f.required) inp.required = true;
            if (f.defaultVal) inp.value = f.defaultVal;
            if (f.type === 'color') { inp.style.height = '38px'; inp.style.padding = '2px'; }
            grp.appendChild(inp);

            body.appendChild(grp);
        });

        document.getElementById('hm-quickadd-modal').classList.add('open');
        // Focus first field
        var first = body.querySelector('input');
        if (first) setTimeout(function() { first.focus(); }, 100);
    }

    function doSave() {
        var body = document.getElementById('hm-qa-body');
        var entity = body.getAttribute('data-entity');
        var fields = body.querySelectorAll('.hm-qa-field');
        var payload = { action: 'hm_quick_add', nonce: HM.nonce, entity: entity };
        var valid = true;

        fields.forEach(function(inp) {
            var key = inp.getAttribute('data-key');
            payload[key] = inp.value.trim();
            if (inp.required && !payload[key]) {
                inp.style.borderColor = '#ef4444';
                valid = false;
            } else {
                inp.style.borderColor = '';
            }
        });

        if (!valid) return;

        var btn = document.getElementById('hm-qa-save');
        btn.textContent = 'Saving…'; btn.disabled = true;

        jQuery.post(HM.ajax_url, payload, function(r) {
            btn.textContent = 'Save'; btn.disabled = false;

            if (!r.success) {
                alert(r.data || 'Error adding item');
                return;
            }

            var newId    = r.data.id;
            var newName  = r.data.name;
            var roleKey  = r.data.role_name || null;

            // Insert new option into ALL selects with same data-entity
            var siblings = document.querySelectorAll('select[data-entity="' + entity + '"]');
            siblings.forEach(function(s) {
                var exists = false;
                for (var i = 0; i < s.options.length; i++) {
                    if (s.options[i].value == (roleKey || newId)) { exists = true; break; }
                }
                if (!exists) {
                    var opt = document.createElement('option');
                    opt.value = roleKey || newId;
                    opt.textContent = newName;
                    if (s.getAttribute('data-name-attr')) {
                        opt.setAttribute('data-name', newName);
                    }
                    var addNewOpt = s.querySelector('option[value="__add_new__"]');
                    if (addNewOpt) {
                        s.insertBefore(opt, addNewOpt);
                    } else {
                        s.appendChild(opt);
                    }
                }
            });

            // Set the value on the select that triggered this
            if (_activeSel) {
                _activeSel.value = roleKey || newId;
                _activeSel.dispatchEvent(new Event('change', {bubbles: true}));
            }
            _activeSel = null;

            // Close modal
            document.getElementById('hm-quickadd-modal').classList.remove('open');

            if (typeof HM.toast === 'function') {
                HM.toast(newName + ' added', 'success');
            }
        });
    }

    // Delegated change handler on all selects
    document.addEventListener('change', function(e) {
        var sel = e.target;
        if (sel.tagName !== 'SELECT' || sel.value !== '__add_new__') return;

        var entity = sel.getAttribute('data-entity');
        if (!entity) { sel.value = ''; return; }

        openModal(sel, entity);
    });
})();
