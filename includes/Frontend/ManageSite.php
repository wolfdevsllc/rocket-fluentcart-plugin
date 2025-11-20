<?php
/**
 * Manage Site Frontend Handler
 *
 * Handles the embedded Rocket control panel interface with clean URLs
 * Creates endpoint: /my-sites/manage-site/{rocket_site_id}/
 */

class RFC_Frontend_ManageSite {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Add rewrite endpoint
        add_action('init', array($this, 'add_endpoints'));

        // Handle endpoint content
        add_action('template_redirect', array($this, 'handle_endpoint'));

        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Add rewrite endpoints
     */
    public function add_endpoints() {
        // Add manage-site endpoint that accepts a site ID
        add_rewrite_endpoint('manage-site', EP_ROOT | EP_PAGES);
    }

    /**
     * Handle endpoint content
     */
    public function handle_endpoint() {
        global $wp_query;

        // Debug: Log what we have
        if (is_page('my-sites') || isset($wp_query->query_vars['manage-site'])) {
            RFC_Helper::log('ManageSite endpoint check - query_vars: ' . json_encode($wp_query->query_vars), 'info');
        }

        // Check if we're on the manage-site endpoint
        if (!isset($wp_query->query_vars['manage-site'])) {
            return;
        }

        // Get Rocket site ID from URL
        $rocket_site_id = absint($wp_query->query_vars['manage-site']);
        RFC_Helper::log('ManageSite handling site ID: ' . $rocket_site_id, 'info');

        if (!$rocket_site_id) {
            wp_die(__('Invalid site ID', 'rocket-fluentcart'));
        }

        // Find site by Rocket site ID
        $site = $this->get_site_by_rocket_id($rocket_site_id);

        if (!$site) {
            wp_die(__('Site not found', 'rocket-fluentcart'));
        }

        // Verify ownership
        $customer_id = RFC_Helper::get_current_customer_id();
        if ($site->customer_id != $customer_id) {
            wp_die(__('Unauthorized access', 'rocket-fluentcart'));
        }

        // Generate access token
        $access_token = Rocket_API_Sites::generate_access_token($site->rocket_site_id);

        if (is_wp_error($access_token)) {
            RFC_Helper::log('Failed to generate access token: ' . $access_token->get_error_message(), 'error');
            wp_die(__('Failed to generate access token', 'rocket-fluentcart'));
        }

        // Set up data for template
        $this->setup_page_data($site, $access_token);

        // Load template
        $this->load_template($site, $access_token);
        exit;
    }

    /**
     * Get site by Rocket site ID
     */
    private function get_site_by_rocket_id($rocket_site_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'fc_rocket_sites';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE rocket_site_id = %d AND status = 'active'",
            $rocket_site_id
        ));
    }

    /**
     * Setup page data and enqueue assets
     */
    private function setup_page_data($site, $access_token) {
        // Enqueue scripts and styles
        wp_enqueue_script(
            'rfc-rocket-sdk-loader',
            RFC_PLUGIN_URL . 'assets/js/frontend/rocket-sdk-loader.js',
            array(),
            RFC_Helper::get_asset_version('assets/js/frontend/rocket-sdk-loader.js'),
            true
        );

        wp_enqueue_script(
            'rfc-rocket-sdk-init',
            RFC_PLUGIN_URL . 'assets/js/frontend/rocket-sdk-init.js',
            array('jquery', 'rfc-rocket-sdk-loader'),
            RFC_Helper::get_asset_version('assets/js/frontend/rocket-sdk-init.js'),
            true
        );

        wp_enqueue_style(
            'rfc-manage-site',
            RFC_PLUGIN_URL . 'assets/css/frontend/manage-site.css',
            array(),
            RFC_Helper::get_asset_version('assets/css/frontend/manage-site.css')
        );

        // Pass data to JavaScript
        $site_data = array(
            'siteId' => $site->rocket_site_id,
            'accessToken' => $access_token,
            'siteName' => $site->site_name,
        );

        wp_localize_script('rfc-rocket-sdk-init', 'rfcSiteData', $site_data);
    }

    /**
     * Load template
     */
    private function load_template($site, $access_token) {
        // Get header
        get_header();

        ?>
        <div class="rfc-manage-site-wrap">
            <div class="rfc-manage-site-header">
                <h2><?php printf(__('Managing: %s', 'rocket-fluentcart'), esc_html($site->site_name)); ?></h2>
                <a href="<?php echo esc_url(RFC_Helper::get_my_sites_url()); ?>" class="rfc-button rfc-button-secondary">
                    <?php _e('← Back to My Sites', 'rocket-fluentcart'); ?>
                </a>
            </div>

            <!-- Loader -->
            <div class="rfc-loader">
                <div class="rfc-loader-spinner"></div>
                <p><?php _e('Loading control panel...', 'rocket-fluentcart'); ?></p>
            </div>

            <!-- Rocket SDK Container -->
            <div id="rfc-rocket-container" class="rfc-rocket-container"></div>
        </div>
        <?php

        // Get footer
        get_footer();
    }

    /**
     * Enqueue scripts for manage site page
     */
    public function enqueue_scripts() {
        global $wp_query;

        // Only load if on manage-site endpoint
        if (!isset($wp_query->query_vars['manage-site'])) {
            return;
        }
    }
}

// Initialize
RFC_Frontend_ManageSite::get_instance();
