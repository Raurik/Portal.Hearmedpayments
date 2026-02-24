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
        // For now, just a static preview (can be made dynamic)
        var html = '';
        html += '<div class="hm-settings-appt-card">';
        html += '<div class="hm-settings-appt-name">Joe Bloggs</div>';
        html += '<div class="hm-settings-appt-badges">';
        html += '<span class="hm-settings-badge">C</span>';
        html += '<span class="hm-settings-badge">R</span>';
        html += '<span class="hm-settings-badge">VM</span>';
        html += '</div>';
        html += '<div class="hm-settings-appt-time">09:00</div>';
        html += '<div class="hm-settings-appt-meta">Follow up · Cosgrove\'s Pharmacy</div>';
        html += '</div>';
        $('.hm-settings-preview').html(html);
    }
};

$(function(){
    if($('.hm-settings-main').length) SettingsPage.init();
});

})(jQuery);
