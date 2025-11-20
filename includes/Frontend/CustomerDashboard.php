<?php
/**
 * Customer Dashboard
 *
 * Handles My Sites dashboard for customers
 */

defined('ABSPATH') || exit;

class RFC_Frontend_CustomerDashboard {

    /**
     * Instance
     *
     * @var RFC_Frontend_CustomerDashboard
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return RFC_Frontend_CustomerDashboard
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
        // Register shortcode
        add_shortcode('rocket_my_sites', array($this, 'render_my_sites'));

        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // AJAX handlers
        add_action('wp_ajax_rfc_load_sites', array($this, 'ajax_load_sites'));
        add_action('wp_ajax_rfc_get_control_panel_token', array($this, 'ajax_get_control_panel_token'));
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        // Only enqueue on pages with shortcode or specific pages
        if (!is_singular() && !is_page()) {
            return;
        }

        global $post;
        if ($post && (has_shortcode($post->post_content, 'rocket_my_sites') || is_page('my-sites'))) {
            wp_enqueue_style(
                'rfc-frontend',
                RFC_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                RFC_Helper::get_asset_version('assets/css/frontend.css')
            );

            wp_enqueue_script(
                'rfc-frontend',
                RFC_PLUGIN_URL . 'assets/js/frontend/frontend.js',
                array('jquery'),
                RFC_Helper::get_asset_version('assets/js/frontend/frontend.js'),
                true
            );

            // Get manage site page URL (use current page if shortcode exists, otherwise homepage)
            $manage_page_url = get_permalink();

            wp_localize_script('rfc-frontend', 'rfcFrontend', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('rfc_frontend'),
                'manage_page_url' => $manage_page_url,
                'strings' => array(
                    'loading' => __('Loading...', 'rocket-fluentcart'),
                    'error' => __('An error occurred. Please try again.', 'rocket-fluentcart'),
                    'success' => __('Success!', 'rocket-fluentcart'),
                    'confirm_create' => __('Are you sure you want to create this site?', 'rocket-fluentcart'),
                ),
            ));
        }
    }

    /**
     * Render My Sites dashboard
     *
     * @param array $atts
     * @return string
     */
    public function render_my_sites($atts = array()) {
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return '<div class="rfc-notice rfc-notice-warning">' .
                   __('Please log in to view your sites.', 'rocket-fluentcart') .
                   '</div>';
        }

        // Get customer ID
        $customer_id = RFC_Helper::get_current_customer_id();

        if (!$customer_id) {
            return '<div class="rfc-notice rfc-notice-warning">' .
                   __('No customer account found. Please contact support.', 'rocket-fluentcart') .
                   '</div>';
        }

        // Get allocations
        $allocations = RFC_Database_Schema::get_customer_allocations($customer_id, 'all');

        ob_start();
        ?>
        <div class="rfc-my-sites-dashboard">
            <?php if (empty($allocations)): ?>
                <div class="rfc-empty-state">
                    <div class="rfc-empty-state-icon">🚀</div>
                    <h3><?php _e('No hosting sites yet', 'rocket-fluentcart'); ?></h3>
                    <p><?php _e('Purchase a hosting plan to get started.', 'rocket-fluentcart'); ?></p>
                    <a href="<?php echo esc_url(RFC_Helper::get_hosting_plans_url()); ?>" class="rfc-btn rfc-btn-primary">
                        <?php _e('View Hosting Plans', 'rocket-fluentcart'); ?>
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($allocations as $allocation): ?>
                    <?php $this->render_allocation_section($allocation); ?>
                <?php endforeach; ?>

                <!-- Render create site modal (hidden by default) -->
                <?php
                if (!empty($allocations)) {
                    // Find an allocation with available sites for the modal template
                    foreach ($allocations as $allocation) {
                        $available = $allocation->total_sites - $allocation->used_sites;
                        if ($available > 0) {
                            do_action('rfc_site_creation_form', $allocation->id);
                            break; // Only need one modal
                        }
                    }
                }
                ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render allocation section
     *
     * @param object $allocation
     */
    private function render_allocation_section($allocation) {
        $order = RFC_Helper::get_order($allocation->order_id);
        $product = RFC_Helper::get_product($allocation->product_id);
        $sites = RFC_Database_Schema::get_allocation_sites($allocation->id);
        $available_sites = $allocation->total_sites - $allocation->used_sites;
        $is_active = $allocation->status === 'active';
        $config = RFC_Helper::get_product_rocket_config($allocation->product_id);

        ?>
        <div class="rfc-allocation-section" data-allocation-id="<?php echo esc_attr($allocation->id); ?>">
            <!-- Allocation Header -->
            <div class="rfc-allocation-header">
                <div class="rfc-allocation-info">
                    <h3 class="rfc-allocation-title"><?php echo esc_html($product->post_title); ?></h3>
                    <div class="rfc-allocation-meta">
                        <?php
                        echo sprintf(__('Order #%s', 'rocket-fluentcart'), $allocation->order_id);
                        echo ' | ';
                        echo sprintf(__('%d of %d sites used', 'rocket-fluentcart'), $allocation->used_sites, $allocation->total_sites);
                        ?>
                    </div>
                </div>
                <div class="rfc-allocation-actions">
                    <?php if ($is_active && $available_sites > 0): ?>
                        <button class="rfc-btn rfc-btn-primary rfc-create-site-btn"
                                data-allocation-id="<?php echo esc_attr($allocation->id); ?>">
                            <?php _e('+ Create Site', 'rocket-fluentcart'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sites Table -->
            <?php if (!empty($sites)): ?>
                <div class="rfc-sites-table-wrap">
                    <table class="rfc-sites-table">
                        <thead>
                            <tr>
                                <th><?php _e('Site Name', 'rocket-fluentcart'); ?></th>
                                <th><?php _e('Created', 'rocket-fluentcart'); ?></th>
                                <th class="rfc-table-actions"><?php _e('Actions', 'rocket-fluentcart'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sites as $site): ?>
                                <?php $this->render_site_row($site, $is_active); ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render individual site row
     *
     * @param object $site
     * @param bool $is_active
     */
    private function render_site_row($site, $is_active) {
        $site_data = $site->rocket_site_data ? json_decode($site->rocket_site_data, true) : array();
        $site_url = isset($site_data['url']) ? $site_data['url'] : '';
        $location = isset($site_data['location']) ? $site_data['location'] : '';
        $can_manage = $is_active && $site->status === 'active';

        ?>
        <tr class="rfc-site-row <?php echo $can_manage ? '' : 'rfc-site-inactive'; ?>" data-site-id="<?php echo esc_attr($site->id); ?>">
            <td class="rfc-site-name-cell">
                <div class="rfc-site-name"><?php echo esc_html($site->site_name); ?></div>
                <?php if ($site_url): ?>
                    <div class="rfc-site-url">
                        <a href="<?php echo esc_url($site_url); ?>" target="_blank" rel="noopener" class="rfc-site-link">
                            <?php echo esc_html($site_url); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </td>
            <td class="rfc-site-date-cell">
                <?php echo RFC_Helper::format_date($site->created_at, 'M j, Y'); ?>
            </td>
            <td class="rfc-site-actions-cell">
                <?php if ($can_manage):
                    // Use FluentCart customer portal URL for manage link
                    $manage_url = \FluentCart\App\Services\URL::getCustomerDashboardUrl('my-sites') . '?manage-site=' . $site->rocket_site_id;
                ?>
                    <a href="<?php echo esc_url($manage_url); ?>" class="rfc-btn rfc-btn-small">
                        <?php _e('Manage', 'rocket-fluentcart'); ?>
                    </a>
                <?php else: ?>
                    <span class="rfc-text-muted">—</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    /**
     * AJAX: Load sites for allocation
     */
    public function ajax_load_sites() {
        check_ajax_referer('rfc_frontend', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Unauthorized', 'rocket-fluentcart')));
        }

        $allocation_id = isset($_POST['allocation_id']) ? absint($_POST['allocation_id']) : 0;

        if (!$allocation_id) {
            wp_send_json_error(array('message' => __('Invalid allocation', 'rocket-fluentcart')));
        }

        $sites = RFC_Database_Schema::get_allocation_sites($allocation_id);

        wp_send_json_success(array('sites' => $sites));
    }

    /**
     * AJAX: Get control panel access token
     */
    public function ajax_get_control_panel_token() {
        check_ajax_referer('rfc_frontend', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Unauthorized', 'rocket-fluentcart')));
        }

        $site_id = isset($_POST['site_id']) ? absint($_POST['site_id']) : 0;

        if (!$site_id) {
            wp_send_json_error(array('message' => __('Invalid site', 'rocket-fluentcart')));
        }

        // Get site
        $site = RFC_Database_Schema::get_site($site_id);

        if (!$site || !$site->rocket_site_id) {
            wp_send_json_error(array('message' => __('Site not found', 'rocket-fluentcart')));
        }

        // Verify ownership
        $customer_id = RFC_Helper::get_current_customer_id();
        if ($site->customer_id != $customer_id) {
            wp_send_json_error(array('message' => __('Unauthorized', 'rocket-fluentcart')));
        }

        // Check if subscription is active
        if (!RFC_Helper::is_subscription_active($site->order_id)) {
            wp_send_json_error(array('message' => __('Subscription is not active', 'rocket-fluentcart')));
        }

        // Generate access token
        $token = Rocket_API_Sites::generate_access_token($site->rocket_site_id, 400);

        if (is_wp_error($token)) {
            wp_send_json_error(array('message' => $token->get_error_message()));
        }

        // Get control panel URL
        $control_panel_url = Rocket_API_Sites::get_control_panel_url($site->rocket_site_id, $token);

        wp_send_json_success(array(
            'url' => $control_panel_url,
            'token' => $token,
        ));
    }
}

/**
 * Helper function for displaying sites dashboard
 *
 * @param array $atts
 * @return string
 */
function rocket_display_my_sites($atts = array()) {
    return RFC_Frontend_CustomerDashboard::instance()->render_my_sites($atts);
}
