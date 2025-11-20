<?php
/**
 * Admin Settings Page
 *
 * Handles Rocket.net API settings and configuration
 */

defined('ABSPATH') || exit;

class RFC_Admin_Settings {

    /**
     * Instance
     *
     * @var RFC_Admin_Settings
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return RFC_Admin_Settings
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_rfc_test_connection', array($this, 'ajax_test_connection'));
    }

    /**
     * Add settings page to FluentCart menu
     */
    public function add_settings_page() {
        // Try to add under FluentCart menu
        // FluentCart uses 'fluent-cart' as parent slug
        $parent_slug = 'fluent-cart';

        // Check if FluentCart menu exists, otherwise create our own
        global $menu;
        $fluent_cart_exists = false;

        if (is_array($menu)) {
            foreach ($menu as $item) {
                if (isset($item[2]) && $item[2] === 'fluent-cart') {
                    $fluent_cart_exists = true;
                    break;
                }
            }
        }

        // If FluentCart menu doesn't exist, create standalone menu
        if (!$fluent_cart_exists) {
            add_menu_page(
                __('Rocket Settings', 'rocket-fluentcart'),
                __('Rocket Settings', 'rocket-fluentcart'),
                'manage_options',
                'rocket-settings',
                array($this, 'render_settings_page'),
                'dashicons-cloud',
                30
            );
        } else {
            add_submenu_page(
                $parent_slug,
                __('Rocket.net Settings', 'rocket-fluentcart'),
                __('Rocket Settings', 'rocket-fluentcart'),
                'manage_options',
                'rocket-settings',
                array($this, 'render_settings_page')
            );
        }
    }

    /**
     * Register settings
     */
    public function register_settings() {
        // API Credentials Section
        add_settings_section(
            'rfc_api_credentials',
            __('API Credentials', 'rocket-fluentcart'),
            array($this, 'api_credentials_section_callback'),
            'rocket-settings'
        );

        // API Email
        register_setting('rfc_settings', 'rfc_rocket_email', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_email',
        ));

        add_settings_field(
            'rfc_rocket_email',
            __('Rocket.net Email', 'rocket-fluentcart'),
            array($this, 'text_field_callback'),
            'rocket-settings',
            'rfc_api_credentials',
            array(
                'label_for' => 'rfc_rocket_email',
                'description' => __('Your Rocket.net account email', 'rocket-fluentcart'),
                'type' => 'email',
            )
        );

        // API Password
        register_setting('rfc_settings', 'rfc_rocket_password', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
        ));

        add_settings_field(
            'rfc_rocket_password',
            __('Rocket.net Password', 'rocket-fluentcart'),
            array($this, 'password_field_callback'),
            'rocket-settings',
            'rfc_api_credentials',
            array(
                'label_for' => 'rfc_rocket_password',
                'description' => __('Your Rocket.net account password', 'rocket-fluentcart'),
            )
        );

        // Page URLs Section
        add_settings_section(
            'rfc_page_urls',
            __('Page URLs', 'rocket-fluentcart'),
            array($this, 'page_urls_section_callback'),
            'rocket-settings'
        );

        // Hosting Plans Page URL
        register_setting('rfc_settings', 'rfc_hosting_plans_url', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_url_or_path'),
        ));

        add_settings_field(
            'rfc_hosting_plans_url',
            __('Hosting Plans Page URL', 'rocket-fluentcart'),
            array($this, 'url_field_callback'),
            'rocket-settings',
            'rfc_page_urls',
            array(
                'label_for' => 'rfc_hosting_plans_url',
                'description' => __('URL to your hosting plans/shop page. Used in the "View Hosting Plans" button when customer has no sites.', 'rocket-fluentcart'),
                'placeholder' => home_url('/shop/'),
            )
        );

        // My Sites Page URL
        register_setting('rfc_settings', 'rfc_my_sites_url', array(
            'type' => 'string',
            'sanitize_callback' => array($this, 'sanitize_url_or_path'),
        ));

        add_settings_field(
            'rfc_my_sites_url',
            __('My Sites Page URL', 'rocket-fluentcart'),
            array($this, 'url_field_callback'),
            'rocket-settings',
            'rfc_page_urls',
            array(
                'label_for' => 'rfc_my_sites_url',
                'description' => __('URL to your My Sites dashboard page. Used in the "Back to My Sites" button in the site management panel.', 'rocket-fluentcart'),
                'placeholder' => class_exists('\FluentCart\App\Services\URL') ? \FluentCart\App\Services\URL::getCustomerDashboardUrl('my-sites') : home_url('/my-account/'),
            )
        );

        // Branding Colors
        add_settings_section(
            'rfc_branding',
            __('Branding Colors', 'rocket-fluentcart'),
            array($this, 'branding_section_callback'),
            'rocket-settings'
        );

        $color_fields = array(
            'primary_color' => __('Primary Color', 'rocket-fluentcart'),
            'secondary_color' => __('Secondary Color', 'rocket-fluentcart'),
            'accent_color' => __('Accent Color', 'rocket-fluentcart'),
            'background_color' => __('Background Color', 'rocket-fluentcart'),
            'text_color' => __('Text Color', 'rocket-fluentcart'),
            'link_color' => __('Link Color', 'rocket-fluentcart'),
            'button_color' => __('Button Color', 'rocket-fluentcart'),
            'button_text_color' => __('Button Text Color', 'rocket-fluentcart'),
        );

        foreach ($color_fields as $field => $label) {
            register_setting('rfc_settings', 'rfc_' . $field, array(
                'type' => 'string',
                'sanitize_callback' => 'sanitize_hex_color',
            ));

            add_settings_field(
                'rfc_' . $field,
                $label,
                array($this, 'color_field_callback'),
                'rocket-settings',
                'rfc_branding',
                array(
                    'label_for' => 'rfc_' . $field,
                )
            );
        }
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle settings update
        if (isset($_GET['settings-updated'])) {
            // Clear token on credentials update
            if (isset($_POST['rfc_rocket_email']) || isset($_POST['rfc_rocket_password'])) {
                Rocket_API_Auth::clear_token();
            }

            add_settings_error(
                'rfc_messages',
                'rfc_message',
                __('Settings saved successfully', 'rocket-fluentcart'),
                'success'
            );
        }

        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <?php settings_errors('rfc_messages'); ?>

            <div class="rfc-settings-container">
                <form method="post" action="options.php">
                    <?php
                    settings_fields('rfc_settings');
                    do_settings_sections('rocket-settings');
                    submit_button(__('Save Settings', 'rocket-fluentcart'));
                    ?>
                </form>

                <div class="rfc-test-connection">
                    <h3><?php _e('Test Connection', 'rocket-fluentcart'); ?></h3>
                    <p><?php _e('Click the button below to test your Rocket.net API connection.', 'rocket-fluentcart'); ?></p>
                    <button type="button" id="rfc-test-connection-btn" class="button button-secondary">
                        <?php _e('Test Connection', 'rocket-fluentcart'); ?>
                    </button>
                    <div id="rfc-test-result" style="margin-top: 10px;"></div>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
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
                $btn.prop('disabled', true).text('<?php _e('Testing...', 'rocket-fluentcart'); ?>');
                $result.html('<span class="spinner is-active" style="float:none; margin:0;"></span>');

                // Make AJAX request
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'rfc_test_connection',
                        nonce: '<?php echo wp_create_nonce('rfc_test_connection'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $result.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                        } else {
                            $result.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        $result.html('<div class="notice notice-error inline"><p>Connection test failed: ' + error + '</p></div>');
                    },
                    complete: function() {
                        $btn.prop('disabled', false).text('<?php _e('Test Connection', 'rocket-fluentcart'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Section callbacks
     */
    public function api_credentials_section_callback() {
        echo '<p>' . __('Enter your Rocket.net API credentials. These are used to authenticate with the Rocket.net API.', 'rocket-fluentcart') . '</p>';
    }

    public function page_urls_section_callback() {
        echo '<p>' . __('Configure page URLs used in the customer dashboard.', 'rocket-fluentcart') . '</p>';
    }

    public function branding_section_callback() {
        echo '<p>' . __('Customize the colors for the customer dashboard.', 'rocket-fluentcart') . '</p>';
    }

    /**
     * Field callbacks
     */
    public function text_field_callback($args) {
        $value = get_option($args['label_for'], '');
        $type = isset($args['type']) ? $args['type'] : 'text';
        ?>
        <input type="<?php echo esc_attr($type); ?>"
               id="<?php echo esc_attr($args['label_for']); ?>"
               name="<?php echo esc_attr($args['label_for']); ?>"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text">
        <?php if (isset($args['description'])): ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    public function url_field_callback($args) {
        $value = get_option($args['label_for'], '');
        $placeholder = isset($args['placeholder']) ? $args['placeholder'] : '';
        ?>
        <input type="text"
               id="<?php echo esc_attr($args['label_for']); ?>"
               name="<?php echo esc_attr($args['label_for']); ?>"
               value="<?php echo esc_attr($value); ?>"
               placeholder="<?php echo esc_attr($placeholder); ?>"
               class="regular-text">
        <?php if (isset($args['description'])): ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
        <p class="description" style="margin-top: 4px; color: #646970;">
            <?php _e('You can enter a full URL (https://example.com/page/) or a relative path (/page/)', 'rocket-fluentcart'); ?>
        </p>
        <?php
    }

    public function password_field_callback($args) {
        $value = get_option($args['label_for'], '');
        $display_value = $value ? str_repeat('*', 20) : '';
        ?>
        <input type="password"
               id="<?php echo esc_attr($args['label_for']); ?>"
               name="<?php echo esc_attr($args['label_for']); ?>"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text"
               placeholder="<?php echo $display_value ? $display_value : ''; ?>">
        <?php if (isset($args['description'])): ?>
            <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php endif; ?>
        <?php
    }

    public function checkbox_field_callback($args) {
        $value = get_option($args['label_for'], 'yes');
        ?>
        <label>
            <input type="checkbox"
                   id="<?php echo esc_attr($args['label_for']); ?>"
                   name="<?php echo esc_attr($args['label_for']); ?>"
                   value="yes"
                   <?php checked($value, 'yes'); ?>>
            <?php if (isset($args['description'])): ?>
                <?php echo esc_html($args['description']); ?>
            <?php endif; ?>
        </label>
        <?php
    }

    public function color_field_callback($args) {
        $value = get_option($args['label_for'], '');
        ?>
        <input type="text"
               id="<?php echo esc_attr($args['label_for']); ?>"
               name="<?php echo esc_attr($args['label_for']); ?>"
               value="<?php echo esc_attr($value); ?>"
               class="rfc-color-picker">
        <?php
    }

    /**
     * Enqueue scripts
     */
    public function enqueue_scripts($hook) {
        // Check if we're on the rocket settings page
        // Hook suffix can be different depending on whether it's a submenu or main menu
        $valid_hooks = array(
            'fluent-cart_page_rocket-settings',  // When under FluentCart menu
            'toplevel_page_rocket-settings',     // When standalone menu
            'rocket-settings'                     // Alternative format
        );

        if (!in_array($hook, $valid_hooks) && strpos($hook, 'rocket-settings') === false) {
            return;
        }

        // Enqueue WordPress color picker
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');

        // Enqueue custom scripts
        wp_enqueue_script(
            'rfc-admin-settings',
            RFC_PLUGIN_URL . 'assets/js/admin/settings.js',
            array('jquery', 'wp-color-picker'),
            RFC_VERSION,
            true
        );

        wp_localize_script('rfc-admin-settings', 'rfcSettings', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rfc_test_connection'),
        ));

        // Enqueue custom styles
        wp_enqueue_style(
            'rfc-admin-settings',
            RFC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            RFC_VERSION
        );
    }

    /**
     * Sanitize URL or path field
     * Accepts both full URLs (https://example.com/page/) and relative paths (/page/)
     *
     * @param string $value
     * @return string
     */
    public function sanitize_url_or_path($value) {
        $value = trim($value);

        if (empty($value)) {
            return '';
        }

        // If it starts with / it's a relative path
        if (strpos($value, '/') === 0) {
            // Remove any dangerous characters but keep slashes
            $value = preg_replace('/[^a-zA-Z0-9\/_\-.]/', '', $value);
            return $value;
        }

        // Otherwise treat it as a full URL
        return esc_url_raw($value);
    }

    /**
     * AJAX test connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('rfc_test_connection', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }

        $result = Rocket_API_Auth::test_connection();

        if ($result['success']) {
            wp_send_json_success(array('message' => $result['message']));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }
}
