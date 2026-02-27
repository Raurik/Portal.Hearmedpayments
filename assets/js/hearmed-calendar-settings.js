/**
 * Calendar Settings v3.1
 * Live preview, day pills, radio pills, colour hex labels, sortable list, AJAX save.
 */
(function($){
'use strict';

var STATUS_MAP={
    Confirmed:{bg:'#eff6ff',color:'#1e40af',border:'#bfdbfe'},
    Arrived:{bg:'#ecfdf5',color:'#065f46',border:'#a7f3d0'},
    'In Progress':{bg:'#fff7ed',color:'#9a3412',border:'#fed7aa'},
    Completed:{bg:'#f9fafb',color:'#6b7280',border:'#e5e7eb'},
    'No Show':{bg:'#fef2f2',color:'#991b1b',border:'#fecaca'},
    Late:{bg:'#fffbeb',color:'#92400e',border:'#fde68a'},
    Pending:{bg:'#f5f3ff',color:'#5b21b6',border:'#ddd6fe'},
    Cancelled:{bg:'#fef2f2',color:'#991b1b',border:'#fecaca'},
};

var SettingsPage = {

    previewStatus: 'Confirmed',

    init: function(){
        this.bindDayPills();
        this.bindRadioPills();
        this.bindColorHex();
        this.initSortable();
        this.bindPreviewStatus();
        this.bindPreviewUpdaters();
        this.renderPreview();
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

    /* ─── Status selector for preview ─── */
    bindPreviewStatus: function(){
        var self = this;
        $(document).on('click', '.hm-prev-status', function(){
            $('.hm-prev-status').removeClass('on');
            $(this).addClass('on');
            self.previewStatus = $(this).data('status');
            self.renderPreview();
        });
    },

    /* ─── Update preview when card style/banner/colours change ─── */
    bindPreviewUpdaters: function(){
        var self = this;
        $(document).on('change', '#hs-cardStyle, #hs-bannerStyle, #hs-bannerSize', function(){
            self.renderPreview();
        });
        $(document).on('input', '.hm-color-inp', function(){
            self.renderPreview();
        });
    },

    /* ─── Live Preview Card ─── */
    renderPreview: function(){
        var $target = $('#hm-preview-card');
        if (!$target.length) return;

        var cs = $('#hs-cardStyle').val() || 'tinted';
        var bs = $('#hs-bannerStyle').val() || 'default';
        var bz = $('#hs-bannerSize').val() || 'default';
        var status = this.previewStatus;
        var col = '#0BB4C4'; // example appointment type colour
        var font = $('#hs-apptFont').val() || '#ffffff';
        var metaCol = $('#hs-apptMeta').val() || '#38bdf8';

        var isCancelled = status === 'Cancelled';
        var isNoShow = status === 'No Show';

        // Card style rendering
        var bgStyle = '', fontColor = font, borderExtra = '';
        if (cs === 'solid') {
            bgStyle = 'background:'+col;
            fontColor = font;
        } else if (cs === 'tinted') {
            var r=parseInt(col.slice(1,3),16), g=parseInt(col.slice(3,5),16), b=parseInt(col.slice(5,7),16);
            bgStyle = 'background:rgba('+r+','+g+','+b+',0.12);border-left:3.5px solid '+col;
            fontColor = col;
        } else if (cs === 'outline') {
            bgStyle = 'background:#fff;border:1.5px solid '+col+';border-left:3.5px solid '+col;
            fontColor = col;
        } else if (cs === 'minimal') {
            bgStyle = 'background:transparent;border-left:3px solid '+col;
            fontColor = '#334155';
        }

        // Banner
        var bannerH = '';
        if (bs !== 'none') {
            var hMap = {small:'16px',default:'20px',large:'26px'};
            var hPx = hMap[bz] || '20px';
            var bannerBg = col;
            if (bs === 'gradient') bannerBg = 'linear-gradient(90deg,'+col+','+col+'88)';
            else if (bs === 'stripe') bannerBg = 'repeating-linear-gradient(135deg,'+col+','+col+' 4px,'+col+'cc 4px,'+col+'cc 8px)';
            bannerH = '<div style="height:'+hPx+';'+
                (bs==='gradient'||bs==='stripe'?'background:'+bannerBg:'background:'+bannerBg)+
                ';border-radius:6px 6px 0 0"></div>';
        }

        // Status badge
        var st = STATUS_MAP[status] || STATUS_MAP.Confirmed;
        var badge = '<span style="display:inline-block;padding:1px 8px;border-radius:9999px;font-size:10px;font-weight:600;background:'+st.bg+';color:'+st.color+';border:1px solid '+st.border+'">'+status+'</span>';

        // Cancelled / No Show overlay
        var overlayClass = '';
        if (isCancelled) overlayClass = ' hm-prev--cancelled';
        else if (isNoShow) overlayClass = ' hm-prev--noshow';

        var h = '<div class="hm-prev-card'+overlayClass+'" style="'+bgStyle+';border-radius:6px;padding:0;position:relative;overflow:hidden">';
        h += bannerH;
        h += '<div style="padding:8px 10px">';
        h += '<div style="font-size:11px;font-weight:600;color:'+(cs==='solid'?font:col)+';margin-bottom:2px">Follow-up</div>';
        h += '<div style="font-size:13px;font-weight:700;color:'+fontColor+'">Jane Smith</div>';
        h += '<div style="font-size:11px;color:'+(cs==='solid'?metaCol:'#94a3b8')+'">09:30 – 10:00</div>';
        h += '<div style="margin-top:4px">'+badge+'</div>';
        h += '</div>';
        if (isCancelled || isNoShow) {
            h += '<div class="hm-prev-overlay">'+(isCancelled?'Cancelled':'No Show')+'</div>';
        }
        h += '</div>';

        $target.html(h);
    },

    /* ─── Sortable dispenser list ─── */
    initSortable: function(){
        var $list = $('#hs-sortList');
        if (!$list.length) return;
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
        $btn.prop('disabled', true).text('Saving\u2026');

        var data = {};
        data.action = 'hm_save_settings';

        // Nonce
        if (window.HM && HM.nonce) data.nonce = HM.nonce;

        // Text / select fields — explicitly serialize
        var textFields = [
            'start_time', 'end_time', 'time_interval', 'slot_height', 'default_view',
            'card_style', 'banner_style', 'banner_size', 'outcome_style',
            'appt_bg_color', 'appt_font_color', 'appt_badge_color', 'appt_badge_font_color', 'appt_meta_color',
            'indicator_color', 'today_highlight_color', 'grid_line_color', 'cal_bg_color'
        ];
        textFields.forEach(function(name){
            var $el = $('[name="'+name+'"]');
            if ($el.length) data[name] = $el.val();
        });

        // Checkbox fields — send '1' or '0' explicitly
        var checkboxFields = [
            'require_cancel_reason', 'hide_cancelled', 'require_reschedule_note',
            'prevent_location_mismatch', 'apply_clinic_colour',
            'show_appointment_type', 'show_time', 'show_clinic', 'show_dispenser_initials',
            'show_status_badge', 'show_badges', 'display_full_name', 'show_time_inline', 'hide_end_time'
        ];
        checkboxFields.forEach(function(name){
            var $el = $('input[name="'+name+'"]');
            data[name] = $el.length && $el.is(':checked') ? '1' : '0';
        });

        // Working days
        var days = [];
        $('.hs-wd:checked').each(function(){ days.push($(this).val()); });
        data.working_days = days.join(',');

        // Enabled days (backward compat)
        var map = {'0':'sun','1':'mon','2':'tue','3':'wed','4':'thu','5':'fri','6':'sat'};
        data.enabled_days = days.map(function(d){ return map[d] || ''; }).filter(Boolean).join(',');

        // Calendar order
        var order = [];
        $('#hs-sortList .hm-sort-item').each(function(){ order.push($(this).data('id')); });
        if (order.length) data.calendar_order = order.join(',');

        $.ajax({
            url: (window.HM && HM.ajax_url) || '/wp-admin/admin-ajax.php',
            method: 'POST',
            data: data,
            success: function(resp){
                $btn.prop('disabled', false);
                if (resp && resp.success) {
                    $btn.text('\u2713 Saved');
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