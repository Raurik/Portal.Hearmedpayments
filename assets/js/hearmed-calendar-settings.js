// Dedicated JS for Calendar Settings page
(function($){
'use strict';

var SettingsPage = {
    init: function(){
        // Example: update preview on any input change
        $(document).on('change', '.hm-settings-main input, .hm-settings-main select', this.updatePreview);
        this.updatePreview();
    },
    updatePreview: function(){
        var start = $('#hs-start').val() || '09:00';
        var fullName = $('#hs-fullName').prop('checked');
        var name = fullName ? 'Joe Bloggs' : 'Joe';
        var outcome = $('input[name="hs-outcome"]:checked').val() || 'default';

        // Update preview elements if present
        var $card = $('#hs-preview-card');
        if ($card.length) {
            $('#hs-preview-name').text(name);
            $('#hs-preview-time').text(start);
            // adjust meta text (static for fallback)
            $('#hs-preview-meta').text('Follow up · Cosgrove\'s Pharmacy');
            // toggle small class for outcome variants (if needed)
            $card.removeClass('outcome-default outcome-small outcome-tag outcome-popover');
            $card.addClass('outcome-' + outcome);
        } else {
            var html = '';
            html += '<div class="hm-settings-appt-card">';
            html += '<div class="hm-settings-appt-name">' + name + '</div>';
            html += '<div class="hm-settings-appt-badges">';
            html += '<span class="hm-settings-badge">C</span>';
            html += '<span class="hm-settings-badge">R</span>';
            html += '<span class="hm-settings-badge">VM</span>';
            html += '</div>';
            html += '<div class="hm-settings-appt-time">' + start + '</div>';
            html += '<div class="hm-settings-appt-meta">Follow up · Cosgrove\'s Pharmacy</div>';
            html += '</div>';
            $('.hm-settings-preview').html(html);
        }
    }
};

$(function(){
    if($('.hm-settings-main').length) SettingsPage.init();
});

})(jQuery);
