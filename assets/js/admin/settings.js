/**
 * Rocket FluentCart Admin Settings
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize color pickers
        if ($.fn.wpColorPicker) {
            $('.rfc-color-picker').wpColorPicker();
        }

        // Test connection button
        $('#rfc-test-connection-btn').on('click', function(e) {
            e.preventDefault();

            var $btn = $(this);
            var $result = $('#rfc-test-result');

            // Disable button and show loading
            $btn.prop('disabled', true).text('Testing...');
            $result.html('<span class="spinner is-active" style="float:none; margin:0;"></span>');

            // Make AJAX request
            $.ajax({
                url: rfcSettings.ajax_url,
                type: 'POST',
                data: {
                    action: 'rfc_test_connection',
                    nonce: rfcSettings.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                    } else {
                        $result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                    }
                },
                error: function() {
                    $result.html('<div class="notice notice-error inline"><p>Connection test failed. Please check your credentials.</p></div>');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Test Connection');
                }
            });
        });
    });

})(jQuery);
