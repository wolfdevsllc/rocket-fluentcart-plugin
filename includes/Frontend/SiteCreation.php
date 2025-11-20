<?php
/**
 * Site Creation Handler
 *
 * Handles site creation and management for customers
 */

defined('ABSPATH') || exit;

class RFC_Frontend_SiteCreation {

    /**
     * Instance
     *
     * @var RFC_Frontend_SiteCreation
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return RFC_Frontend_SiteCreation
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
        // AJAX handlers
        add_action('wp_ajax_rfc_create_site', array($this, 'ajax_create_site'));
        add_action('wp_ajax_rfc_get_locations', array($this, 'ajax_get_locations'));

        // Render site creation form via action
        add_action('rfc_site_creation_form', array($this, 'render_creation_form'));
    }

    /**
     * Render site creation form
     *
     * @param int $allocation_id
     */
    public function render_creation_form($allocation_id) {
        $allocation = RFC_Database_Schema::get_allocation($allocation_id);

        if (!$allocation) {
            echo '<div class="rfc-notice rfc-notice-error">' . __('Invalid allocation', 'rocket-fluentcart') . '</div>';
            return;
        }

        $available_sites = $allocation->total_sites - $allocation->used_sites;

        if ($available_sites <= 0) {
            echo '<div class="rfc-notice rfc-notice-warning">' . __('No sites available', 'rocket-fluentcart') . '</div>';
            return;
        }

        $config = RFC_Helper::get_product_rocket_config($allocation->product_id);
        $locations = RFC_Helper::get_rocket_locations();

        ?>
        <div class="rfc-create-site-form-wrapper" id="rfc-create-site-modal">
            <div class="rfc-modal-overlay"></div>
            <div class="rfc-modal-content">
                <div class="rfc-modal-header">
                    <h3><?php _e('Create New Site', 'rocket-fluentcart'); ?></h3>
                    <button class="rfc-modal-close rfc-modal-close-btn" type="button">&times;</button>
                </div>

                <div class="rfc-modal-body">
                    <form id="rfc-create-site-form" class="rfc-create-site-form">
                        <input type="hidden" name="allocation_id" value="<?php echo esc_attr($allocation_id); ?>">

                        <!-- Site Name -->
                        <div class="rfc-form-group">
                            <label for="site_name">
                                <?php _e('Site Name', 'rocket-fluentcart'); ?> <span class="required">*</span>
                            </label>
                            <input type="text"
                                   id="site_name"
                                   name="site_name"
                                   class="rfc-form-control"
                                   placeholder="<?php esc_attr_e('My Awesome Site', 'rocket-fluentcart'); ?>"
                                   required>
                            <p class="rfc-form-help"><?php _e('A friendly name for your site', 'rocket-fluentcart'); ?></p>
                        </div>

                        <!-- Location -->
                        <div class="rfc-form-group">
                            <label for="site_location">
                                <?php _e('Server Location', 'rocket-fluentcart'); ?> <span class="required">*</span>
                            </label>
                            <select id="site_location"
                                    name="site_location"
                                    class="rfc-form-control"
                                    required>
                                <option value=""><?php _e('Select a location...', 'rocket-fluentcart'); ?></option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo esc_attr($location['id']); ?>">
                                        <?php echo esc_html($location['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="rfc-form-help"><?php _e('Choose the server location closest to your audience', 'rocket-fluentcart'); ?></p>
                        </div>

                        <!-- Admin Email -->
                        <div class="rfc-form-group">
                            <label for="admin_email">
                                <?php _e('Admin Email', 'rocket-fluentcart'); ?> <span class="required">*</span>
                            </label>
                            <input type="email"
                                   id="admin_email"
                                   name="admin_email"
                                   class="rfc-form-control"
                                   value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>"
                                   required>
                            <p class="rfc-form-help"><?php _e('WordPress admin email address', 'rocket-fluentcart'); ?></p>
                        </div>

                        <!-- Helper Info -->
                        <div class="rfc-notice rfc-notice-info">
                            <?php _e('Your site will be created with a temporary URL. You can add a custom domain later from the site management panel.', 'rocket-fluentcart'); ?>
                        </div>

                        <!-- Messages -->
                        <div id="rfc-create-site-messages"></div>

                        <!-- Buttons -->
                        <div class="rfc-form-actions">
                            <button type="button" class="rfc-btn rfc-btn-link rfc-modal-close">
                                <?php _e('Cancel', 'rocket-fluentcart'); ?>
                            </button>
                            <button type="submit" class="rfc-btn rfc-btn-primary" id="rfc-create-site-submit">
                                <?php _e('Create Site', 'rocket-fluentcart'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Create site
     */
    public function ajax_create_site() {
        check_ajax_referer('rfc_frontend', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Unauthorized', 'rocket-fluentcart')));
        }

        // Get form data
        $allocation_id = isset($_POST['allocation_id']) ? absint($_POST['allocation_id']) : 0;
        $site_name = isset($_POST['site_name']) ? sanitize_text_field($_POST['site_name']) : '';
        $site_location = isset($_POST['site_location']) ? absint($_POST['site_location']) : 0;
        $admin_email = isset($_POST['admin_email']) ? sanitize_email($_POST['admin_email']) : '';

        // Validate
        if (!$allocation_id || !$site_name || !$site_location || !$admin_email) {
            wp_send_json_error(array('message' => __('All fields are required', 'rocket-fluentcart')));
        }

        // Get allocation
        $allocation = RFC_Database_Schema::get_allocation($allocation_id);

        if (!$allocation) {
            wp_send_json_error(array('message' => __('Invalid allocation', 'rocket-fluentcart')));
        }

        // Verify ownership
        $customer_id = RFC_Helper::get_current_customer_id();
        if ($allocation->customer_id != $customer_id) {
            wp_send_json_error(array('message' => __('Unauthorized', 'rocket-fluentcart')));
        }

        // Check if allocation is active
        if ($allocation->status !== 'active') {
            wp_send_json_error(array('message' => __('This allocation is not active', 'rocket-fluentcart')));
        }

        // Check available sites
        $available_sites = $allocation->total_sites - $allocation->used_sites;
        if ($available_sites <= 0) {
            wp_send_json_error(array('message' => __('No sites available in this allocation', 'rocket-fluentcart')));
        }

        // Check subscription status
        if (!RFC_Helper::is_subscription_active($allocation->order_id)) {
            wp_send_json_error(array('message' => __('Subscription is not active', 'rocket-fluentcart')));
        }

        // Get product configuration
        $config = RFC_Helper::get_product_rocket_config($allocation->product_id);

        // Prepare site data for Rocket API - match WooCommerce plugin format exactly
        $site_data = array(
            'multisite' => false,
            'domain' => 'wpdns.site', // Use Rocket's wildcard domain, not custom domain
            'name' => $site_name,
            'location' => absint($site_location),
            'admin_username' => 'admin',
            'admin_password' => wp_generate_password(16, true, true),
            'admin_email' => $admin_email,
            'install_plugins' => $config['plugins_install'] ? $config['plugins_install'] : '',
            'quota' => $config['disk_space'] ? absint($config['disk_space']) : null,
            'bwlimit' => $config['bandwidth'] ? absint($config['bandwidth']) : null,
            'label' => $site_name,
        );

        // Create site via Rocket API
        RFC_Helper::log('Creating site via Rocket API: ' . $site_name, 'info');

        $result = Rocket_API_Sites::create_site($site_data);

        if (is_wp_error($result)) {
            RFC_Helper::log('Site creation failed: ' . $result->get_error_message(), 'error');
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Store site in database
        $site_id = RFC_Database_Schema::create_site(array(
            'allocation_id' => $allocation_id,
            'order_id' => $allocation->order_id,
            'customer_id' => $customer_id,
            'site_name' => $site_name,
            'rocket_site_id' => $result['id'],
            'rocket_site_data' => $result,
            'status' => 'active',
        ));

        if (!$site_id) {
            RFC_Helper::log('Failed to store site in database', 'error');
            wp_send_json_error(array('message' => __('Failed to save site data', 'rocket-fluentcart')));
        }

        // Update allocation used_sites count
        RFC_Database_Schema::update_allocation($allocation_id, array(
            'used_sites' => $allocation->used_sites + 1,
        ));

        RFC_Helper::log(sprintf(
            'Site created successfully: #%d (Rocket ID: %s)',
            $site_id,
            $result['id']
        ), 'info');

        // Trigger action for developers
        do_action('rfc_site_created', $site_id, $allocation_id, $result);

        wp_send_json_success(array(
            'message' => __('Site created successfully!', 'rocket-fluentcart'),
            'site' => array(
                'id' => $site_id,
                'name' => $site_name,
                'rocket_id' => $result['id'],
                'url' => isset($result['url']) ? $result['url'] : '',
                'admin_password' => $site_data['admin_password'],
            ),
        ));
    }

    /**
     * AJAX: Get available locations
     */
    public function ajax_get_locations() {
        check_ajax_referer('rfc_frontend', 'nonce');

        $locations = RFC_Helper::get_rocket_locations();

        wp_send_json_success(array('locations' => $locations));
    }
}
