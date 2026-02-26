/**
 * Calendar Settings v3.1
 * Handles save, day-pill toggles, colour hex labels, radio pills, sortable list.
 */
(function($){
'use strict';

var SettingsPage = {

    /* ─── Bootstrap ─── */
    init: function(){
        this.bindDayPills();
        this.bindRadioPills();
        this.bindColorHex();
        this.initSortable();
        $('#hm-settings-save').on('click', $.proxy(this.save, this));
    },

    /* ─── Day Pills ─── */
    bindDayPills: function(){
        $(document).on('change', '.hs-wd', function(){
            $(this).closest('.hm-pill').toggleClass('on', this.checked);
        });
    },

    /* ─── Radio Pills ─── */
    bindRadioPills: function(){
        $(document).on('change', '.hm-radio-pills input[type="radio"]', function(){
            $(this).closest('.hm-radio-pills').find('.hm-pill').removeClass('on');
            $(this).closest('.hm-pill').addClass('on');
        });
    },

    /* ─── Colour hex labels ─── */
    bindColorHex: function(){
        $(document).on('input', '.hm-color-inp', function(){
            var id = this.id;
            var hex = $(this).val();
            $('.hm-color-hex[data-for="'+id+'"]').text(hex);
        });
    },

    /* ─── Sortable dispenser list ─── */
    initSortable: function(){
        var $list = $('#hs-sortList');
        if (!$list.length) return;
        // Use HTML5 drag-and-drop (no jQuery UI dependency)
        $list.on('dragstart', '.hm-sort-item', function(e){
            $(this).addClass('hm-sort-dragging');
            e.originalEvent.dataTransfer.effectAllowed = 'move';
            e.originalEvent.dataTransfer.setData('text/plain', '');
        });
        $list.on('dragend', '.hm-sort-item', function(){
            $(this).removeClass('hm-sort-dragging');
        });
        $list.on('dragover', '.hm-sort-item', function(e){
            e.preventDefault();
            var $drag = $list.find('.hm-sort-dragging');
            if ($drag[0] !== this) {
                var rect = this.getBoundingClientRect();
                var mid = rect.top + rect.height / 2;
                if (e.originalEvent.clientY < mid) {
                    $(this).before($drag);
                } else {
                    $(this).after($drag);
                }
            }
        });
        $list.find('.hm-sort-item').attr('draggable', true);
    },

    /* ─── Save ─── */
    save: function(e){
        e.preventDefault();
        var $btn = $('#hm-settings-save');
        $btn.prop('disabled', true).text('Saving…');

        // Build data from form (serialises text/select/hidden + checked checkboxes)
        var data = $('#hm-settings-form').serialize();
        data += '&action=hm_save_settings';

        // Working days — collect checked day pill values, join with comma
        var days = [];
        $('.hs-wd:checked').each(function(){ days.push($(this).val()); });
        data += '&working_days=' + encodeURIComponent(days.join(','));

        // Map numeric days → day names for backward compat (enabled_days)
        var map = {'0':'sun','1':'mon','2':'tue','3':'wed','4':'thu','5':'fri','6':'sat'};
        var names = days.map(function(d){ return map[d] || ''; }).filter(Boolean);
        data += '&enabled_days=' + encodeURIComponent(names.join(','));

        // Calendar order — collect dispenser IDs in DOM order
        var order = [];
        $('#hs-sortList .hm-sort-item').each(function(){ order.push($(this).data('id')); });
        if (order.length) data += '&calendar_order=' + encodeURIComponent(order.join(','));

        // Nonce
        if (window.HM && HM.nonce) {
            data += '&nonce=' + encodeURIComponent(HM.nonce);
        }

        $.ajax({
            url: (window.HM && HM.ajax_url) || '/wp-admin/admin-ajax.php',
            method: 'POST',
            data: data,
            success: function(resp){
                $btn.prop('disabled', false);
                if (resp && resp.success) {
                    $btn.text('✓ Saved');
                    setTimeout(function(){ $btn.text('Save Settings'); }, 2000);
                } else {
                    var msg = (resp && resp.data && resp.data.message) ? resp.data.message : 'Save failed.';
                    alert(msg);
                    $btn.text('Save Settings');
                }
            },
            error: function(xhr){
                $btn.prop('disabled', false).text('Save Settings');
                var msg = 'Error saving settings.';
                if (xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    msg = xhr.responseJSON.data.message;
                }
                alert(msg);
            }
        });
    }
};

$(function(){
    if ($('#hm-settings-form').length) SettingsPage.init();
});

})(jQuery);
