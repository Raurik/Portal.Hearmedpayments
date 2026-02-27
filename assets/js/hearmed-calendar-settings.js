/**
 * Calendar Settings v3.2
 * Live preview, day pills, radio pills, colour hex labels, sortable list,
 * tint opacity slider, per-status badge colours, AJAX save.
 */
(function($){
'use strict';

var STATUS_DEFAULTS={
    'Not Confirmed':{bg:'#fefce8',color:'#854d0e',border:'#fde68a'},
    Confirmed:{bg:'#eff6ff',color:'#1e40af',border:'#bfdbfe'},
    Arrived:{bg:'#ecfdf5',color:'#065f46',border:'#a7f3d0'},
    'In Progress':{bg:'#fff7ed',color:'#9a3412',border:'#fed7aa'},
    Completed:{bg:'#f9fafb',color:'#6b7280',border:'#e5e7eb'},
    'No Show':{bg:'#fef2f2',color:'#991b1b',border:'#fecaca'},
    Late:{bg:'#fffbeb',color:'#92400e',border:'#fde68a'},
    Pending:{bg:'#f5f3ff',color:'#5b21b6',border:'#ddd6fe'},
    Cancelled:{bg:'#fef2f2',color:'#991b1b',border:'#fecaca'},
    Rescheduled:{bg:'#f0f9ff',color:'#0c4a6e',border:'#bae6fd'},
};

var SettingsPage = {

    previewStatus: 'Confirmed',

    init: function(){
        this.bindDayPills();
        this.bindRadioPills();
        this.bindColorHex();
        this.bindTintSlider();
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

    /* ─── Tint opacity slider ─── */
    bindTintSlider: function(){
        var self = this;
        $(document).on('input', '#hs-tintOpacity', function(){
            $('#hm-tint-val').text(this.value + '%');
            self.renderPreview();
        });
    },

    /* ─── Read current status badge colours from inputs ─── */
    getStatusBadgeColours: function(){
        var map = {};
        var statuses = ['Not Confirmed','Confirmed','Arrived','In Progress','Completed','No Show','Late','Pending','Cancelled','Rescheduled'];
        statuses.forEach(function(s){
            var slug = s.toLowerCase().replace(/ /g, '_');
            map[s] = {
                bg:     $('input[name="sbadge_'+slug+'_bg"]').val()     || STATUS_DEFAULTS[s].bg,
                color:  $('input[name="sbadge_'+slug+'_color"]').val()  || STATUS_DEFAULTS[s].color,
                border: $('input[name="sbadge_'+slug+'_border"]').val() || STATUS_DEFAULTS[s].border
            };
        });
        return map;
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

    /* ─── Update preview when ANY appearance / content setting changes ─── */
    bindPreviewUpdaters: function(){
        var self = this;
        // Dropdowns
        $(document).on('change', '#hs-cardStyle, #hs-bannerStyle, #hs-bannerSize, #hs-slotH', function(){
            self.renderPreview();
        });
        // Colour pickers (including badge colour inputs) — listen to both input + change
        $(document).on('input change', '.hm-color-inp', function(){
            self.renderPreview();
        });
        // Card Content toggles (Card 6)
        $(document).on('change', 'input[name="show_appointment_type"], input[name="show_time"], input[name="show_clinic"], input[name="show_dispenser_initials"], input[name="show_status_badge"], input[name="show_badges"], input[name="display_full_name"], input[name="show_time_inline"], input[name="hide_end_time"]', function(){
            self.renderPreview();
        });
        // Live badge preview update
        $(document).on('input', '.hm-badge-inp', function(){
            var $row = $(this).closest('.hm-status-badge-row');
            var bg = $row.find('input[name$="_bg"]').val();
            var color = $row.find('input[name$="_color"]').val();
            var border = $row.find('input[name$="_border"]').val();
            $row.find('.hm-prev-badge').css({background:bg, color:color, 'border-color':border});
        });
    },

    /* ─── Helper: is checkbox checked? ─── */
    _cb: function(name){
        var $el = $('input[name="'+name+'"]');
        return $el.length && $el.is(':checked');
    },

    /* ─── Live Preview Card ─── */
    renderPreview: function(){
        var $target = $('#hm-preview-card');
        if (!$target.length) return;

        var self = this;
        var cs = $('#hs-cardStyle').val() || 'tinted';
        var bs = $('#hs-bannerStyle').val() || 'default';
        var bz = $('#hs-bannerSize').val() || 'default';
        var slotH = $('#hs-slotH').val() || 'regular';
        var status = this.previewStatus;
        var col = $('#hs-apptBg').val() || '#0BB4C4';
        var font = $('#hs-apptFont').val() || '#ffffff';
        var nameCol = $('#hs-apptName').val() || '#ffffff';
        var timeCol = $('#hs-apptTime').val() || '#38bdf8';
        var metaCol = $('#hs-apptMeta').val() || '#38bdf8';
        var badgeBg = $('#hs-apptBadge').val() || '#3b82f6';
        var badgeFont = $('#hs-apptBadgeFont').val() || '#ffffff';
        var borderCol = $('#hs-borderColor').val() || col;
        var tintPct = parseInt($('#hs-tintOpacity').val()) || 12;

        // Read Card Content toggles
        var showApptType = self._cb('show_appointment_type');
        var showTime = self._cb('show_time');
        var showClinic = self._cb('show_clinic');
        var showDispIni = self._cb('show_dispenser_initials');
        var showStatusBadge = self._cb('show_status_badge');
        var showBadges = self._cb('show_badges');
        var showTimeInline = self._cb('show_time_inline');
        var hideEndTime = self._cb('hide_end_time');
        var displayFull = self._cb('display_full_name');

        var isCancelled = status === 'Cancelled';
        var isNoShow = status === 'No Show';

        // Map slot height to pixel height for the preview card
        var htMap = {compact:57, regular:75, large:97};
        var cardH = htMap[slotH] || 75;

        // Card style rendering
        var bgStyle = '';
        if (cs === 'solid') {
            bgStyle = 'background:'+col;
        } else if (cs === 'tinted') {
            var r=parseInt(col.slice(1,3),16), g=parseInt(col.slice(3,5),16), b=parseInt(col.slice(5,7),16);
            var tAlpha = (tintPct / 100).toFixed(2);
            bgStyle = 'background:rgba('+r+','+g+','+b+','+tAlpha+');border-left:3.5px solid '+col;
        } else if (cs === 'outline') {
            bgStyle = 'background:#fff;border:1.5px solid '+(borderCol||col)+';border-left:3.5px solid '+col;
        } else if (cs === 'minimal') {
            bgStyle = 'background:transparent;border-left:3px solid '+col;
        }

        // Text colours — always use configured pickers so preview is responsive
        var ptColor = font;
        var svcColor = nameCol;
        var tmColor = timeCol;
        var mtColor = metaCol;

        // Banner (only shown for outcome-bearing statuses: Completed)
        var bannerH = '';
        var hasOutcome = (status === 'Completed');
        if (bs !== 'none' && hasOutcome) {
            var hMap = {small:'12px',default:'16px',large:'20px'};
            var hPx = hMap[bz] || '16px';
            var bannerBg = '#10b981';
            if (bs === 'gradient') bannerBg = 'linear-gradient(90deg,#10b981,#10b98188)';
            else if (bs === 'stripe') bannerBg = 'repeating-linear-gradient(135deg,#10b981,#10b981 4px,#10b981cc 4px,#10b981cc 8px)';
            bannerH = '<div style="height:'+hPx+';background:'+bannerBg+';font-size:8px;color:#fff;font-weight:700;letter-spacing:.3px;text-transform:uppercase;line-height:'+hPx+';padding:0 6px;white-space:nowrap;overflow:hidden">Aided</div>';
        }

        // Status badge — use per-status colours from inputs
        var st = self.getStatusBadgeColours()[status] || STATUS_DEFAULTS[status] || STATUS_DEFAULTS.Confirmed;

        // Cancelled / No Show overlay
        var overlayClass = '';
        if (isCancelled) overlayClass = ' hm-prev--cancelled';
        else if (isNoShow) overlayClass = ' hm-prev--noshow';

        // Patient name
        var ptName = displayFull ? 'Jane Smith' : 'Jane';
        var timePre = showTimeInline ? '09:30 ' : '';

        // Build card
        var h = '<div class="hm-prev-card'+overlayClass+'" style="'+bgStyle+';border-radius:6px;position:relative;overflow:hidden;height:'+cardH+'px">';
        h += bannerH;
        h += '<div style="padding:3px 6px;overflow:hidden">';
        if (showApptType) h += '<div style="font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.2px;color:'+svcColor+';line-height:1.3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">Follow-up</div>';
        h += '<div style="font-size:11px;font-weight:700;color:'+ptColor+';line-height:1.3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'+timePre+ptName+'</div>';
        if (showTime && cardH > 44) {
            var timeStr = hideEndTime ? '09:30' : '09:30 – 10:00';
            h += '<div style="font-size:9px;font-weight:600;color:'+tmColor+';line-height:1.3">'+timeStr+'</div>';
        }
        // Badges row
        if (showBadges && cardH > 50) {
            var badges = '';
            if (showStatusBadge) badges += '<span style="display:inline-block;padding:0 5px;border-radius:9999px;font-size:7px;font-weight:700;line-height:14px;background:'+st.bg+';color:'+st.color+';border:1px solid '+st.border+'">'+status+'</span>';
            if (showDispIni) badges += '<span style="display:inline-block;padding:0 5px;border-radius:9999px;font-size:7px;font-weight:700;line-height:14px;background:'+badgeBg+';color:'+badgeFont+'">JS</span>';
            if (badges) h += '<div style="display:flex;gap:3px;margin-top:1px">'+badges+'</div>';
        }
        if (showClinic && cardH > 56) h += '<div style="font-size:8px;color:'+metaCol+';line-height:1.3;white-space:nowrap">Main Clinic</div>';
        h += '</div>';
        if (isCancelled || isNoShow) h += '<div class="hm-prev-overlay">'+(isCancelled?'Cancelled':'No Show')+'</div>';
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
            'appt_bg_color', 'appt_font_color', 'appt_name_color', 'appt_time_color',
            'appt_badge_color', 'appt_badge_font_color', 'appt_meta_color',
            'border_color', 'tint_opacity',
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

        // Status badge colours — serialize as JSON
        data.status_badge_colours = JSON.stringify(this.getStatusBadgeColours());

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