/**
 * Rocket FluentCart Frontend JavaScript
 */
(function($) {
    'use strict';

    var RFC_Frontend = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initModal();
        },

        /**
         * Bind events
         */
        bindEvents: function() {
            // Create site button
            $(document).on('click', '.rfc-create-site-btn', this.openCreateSiteModal);

            // Manage site button
            $(document).on('click', '.rfc-manage-site-btn', this.manageSite);

            // Modal close
            $(document).on('click', '.rfc-modal-close, .rfc-modal-overlay', this.closeModal);

            // Form submit
            $(document).on('submit', '#rfc-create-site-form', this.handleSiteCreation);
        },

        /**
         * Initialize modal
         */
        initModal: function() {
            // Add modal to body if it doesn't exist
            if ($('#rfc-create-site-modal').length === 0) {
                $('body').append('<div id="rfc-create-site-modal-container"></div>');
            }
        },

        /**
         * Open create site modal
         */
        openCreateSiteModal: function(e) {
            e.preventDefault();

            var $btn = $(this);
            var allocationId = $btn.data('allocation-id');

            // Load modal content via AJAX or render it
            RFC_Frontend.renderCreateSiteModal(allocationId);
        },

        /**
         * Render create site modal
         */
        renderCreateSiteModal: function(allocationId) {
            // Use the PHP-rendered form (always present on page)
            var $modal = $('.rfc-create-site-form-wrapper');

            if ($modal.length === 0) {
                console.error('Create site modal not found. Make sure the PHP template is rendering.');
                return;
            }

            // Update allocation ID in the form
            $modal.find('input[name="allocation_id"]').val(allocationId);

            // Show modal
            $modal.addClass('active');
            $('body').css('overflow', 'hidden');
        },

        /**
         * Close modal
         */
        closeModal: function(e) {
            if (e) {
                e.preventDefault();
            }

            var $modal = $('.rfc-create-site-form-wrapper');

            // Check if success screen is showing
            if ($modal.find('.rfc-success-screen').length > 0) {
                // User closed success screen, reload page
                window.location.reload();
                return;
            }

            $modal.removeClass('active');
            $('body').css('overflow', '');

            // Clear form and show it again
            var $form = $('#rfc-create-site-form');
            $form.show()[0].reset();
            $('#rfc-create-site-messages').html('');

            // Remove any success screens
            $('.rfc-success-screen').remove();
        },

        /**
         * Handle site creation
         */
        handleSiteCreation: function(e) {
            e.preventDefault();

            var $form = $(this);
            var $submitBtn = $('#rfc-create-site-submit');
            var $messages = $('#rfc-create-site-messages');

            // Get form data
            var formData = {
                action: 'rfc_create_site',
                nonce: rfcFrontend.nonce,
                allocation_id: $form.find('[name="allocation_id"]').val(),
                site_name: $form.find('[name="site_name"]').val(),
                site_domain: $form.find('[name="site_domain"]').val(),
                site_location: $form.find('[name="site_location"]').val(),
                admin_email: $form.find('[name="admin_email"]').val()
            };

            // Disable submit button
            $submitBtn.prop('disabled', true).text('Creating Site...');
            $messages.html('');

            // Make AJAX request
            $.ajax({
                url: rfcFrontend.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        // Hide the form and show success screen in modal body
                        $form.hide();

                        var successScreen = `
                            <div class="rfc-success-screen">
                                <div class="rfc-success-icon">✓</div>
                                <h3>Site Created Successfully!</h3>
                                <div class="rfc-credentials-box">
                                    <p class="rfc-credentials-title">Save Your Login Credentials</p>
                                    <div class="rfc-credential-row">
                                        <strong>Site URL:</strong>
                                        <a href="${response.data.site.url}" target="_blank" rel="noopener">${response.data.site.url}</a>
                                    </div>
                                    <div class="rfc-credential-row">
                                        <strong>Username:</strong>
                                        <code>admin</code>
                                    </div>
                                    <div class="rfc-credential-row">
                                        <strong>Password:</strong>
                                        <code>${response.data.site.admin_password}</code>
                                    </div>
                                    <p class="rfc-warning-text">⚠️ Please save these credentials now. You won't be able to see the password again.</p>
                                </div>
                                <button type="button" class="rfc-btn rfc-btn-primary rfc-modal-close rfc-close-success">
                                    Got it, Close
                                </button>
                            </div>
                        `;

                        // Append to modal body instead of form messages
                        $form.parent('.rfc-modal-body').append(successScreen);
                    } else {
                        $messages.html('<div class="rfc-notice rfc-notice-error"><p>' + response.data.message + '</p></div>');
                        $submitBtn.prop('disabled', false).text('Create Site');
                    }
                },
                error: function() {
                    $messages.html('<div class="rfc-notice rfc-notice-error"><p>An error occurred. Please try again.</p></div>');
                    $submitBtn.prop('disabled', false).text('Create Site');
                }
            });
        },

        /**
         * Manage site
         */
        manageSite: function(e) {
            e.preventDefault();

            var $btn = $(this);
            var siteId = $btn.data('site-id');
            var rocketSiteId = $btn.data('rocket-site-id');

            // Disable button
            $btn.prop('disabled', true).text('Loading...');

            // Get access token
            $.ajax({
                url: rfcFrontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'rfc_get_control_panel_token',
                    nonce: rfcFrontend.nonce,
                    site_id: siteId
                },
                success: function(response) {
                    if (response.success) {
                        // Open control panel in new window
                        window.open(response.data.url, '_blank', 'noopener,noreferrer');
                    } else {
                        alert(response.data.message || 'Failed to generate access token');
                    }
                },
                error: function() {
                    alert('An error occurred. Please try again.');
                },
                complete: function() {
                    $btn.prop('disabled', false).text('Manage');
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        RFC_Frontend.init();
    });

})(jQuery);
