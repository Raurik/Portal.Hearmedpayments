// Dedicated JS for Calendar Settings page
(function($){
'use strict';

var SettingsPage = {
    init: function(){
        // Update preview on any input change
        $(document).on('change', '#hm-settings-form input, #hm-settings-form select', this.updatePreview);
        this.updatePreview();
        // Save button AJAX
        $('#hm-settings-save').on('click', this.saveSettings);
    },
    updatePreview: function(){
        var start = $('#hs-start').val() || '09:00';
        var fullName = $('#hs-fullName').prop('checked');
        var name = fullName ? 'Joe Bloggs' : 'Joe';
        var outcome = $('input[name="outcome_style"]:checked').val() || 'default';
        var bg = $('#hs-appt-bg').val() || '#0BB4C4';
        var font = $('#hs-appt-font').val() || '#ffffff';
        var badge = $('#hs-appt-badge').val() || '#3b82f6';
        var meta = $('#hs-appt-meta').val() || '#38bdf8';

        var $card = $('#hs-preview-card');
        if ($card.length) {
            $card.css({background: bg});
            $('#hs-preview-name').text(name).css('color', font);
            $('#hs-preview-time').text(start).css('color', badge);
            $('#hs-preview-meta').text('Follow up · Cosgrove\'s Pharmacy').css('color', meta);
            $card.find('.hm-badge').css({'background': badge, 'color': '#fff'});
            $card.removeClass('outcome-default outcome-small outcome-tag outcome-popover');
            $card.addClass('outcome-' + outcome);
        }
    },
    saveSettings: function(e){
        e.preventDefault();
        var $btn = $('#hm-settings-save');
        $btn.prop('disabled', true).text('Saving...');

        var data = $('#hm-settings-form').serialize();
        data += '&action=hm_save_settings';

        // Add nonce if available
        if (window.HM && HM.nonce) {
            data += '&nonce=' + encodeURIComponent(HM.nonce);
        }

        // Perform AJAX request
        $.ajax({
            url: (window.HM && HM.ajax_url) || '/wp-admin/admin-ajax.php',
            method: 'POST',
            data: data,
            success: function (resp) {
                $btn.prop('disabled', false).text('Save Settings');
                if (resp && resp.success) {
                    $btn.text('Saved!');
                    setTimeout(function () {
                        $btn.text('Save Settings');
                    }, 1200);
                } else {
                    alert('Failed to save settings. Please try again.');
                }
            },
            error: function () {
                $btn.prop('disabled', false).text('Save Settings');
                alert('An error occurred while saving settings. Please try again.');
            },
        });
    }
};

$(function(){
    if($('#hm-settings-form').length) SettingsPage.init();
});

})(jQuery);
