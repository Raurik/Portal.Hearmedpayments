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
