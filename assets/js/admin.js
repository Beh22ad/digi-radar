jQuery(document).ready(function ($) {
    $('#digi-radar-manual-update').on('click', function () {
        var $btn = $(this);
        var $message = $('#digi-radar-update-message');

        $btn.prop('disabled', true); // Disable the button during the update

        $.ajax({
            url: digi_radar_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'digi_radar_manual_update',
                nonce: digi_radar_admin.nonce,
            },
            success: function (response) {
                if (response.success) {
                    $message.show(); // Show the success message
                } else {
                    alert('ربات با موفقیت اجرا شد');
                }
            },
            error: function () {
                alert('ربات با موفقیت اجرا شد');
            },
            complete: function () {
               // $btn.prop('disabled', false); // Re-enable the button
            },
        });
    });
});