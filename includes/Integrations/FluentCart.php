<?php
/**
 * FluentCart Integration
 *
 * Handles integration with FluentCart events and hooks
 */

defined('ABSPATH') || exit;

class RFC_Integration_FluentCart {

    /**
     * Instance
     *
     * @var RFC_Integration_FluentCart
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return RFC_Integration_FluentCart
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
        // Order paid hook - create allocations
        add_action('fluent_cart/order_paid', array($this, 'handle_order_paid'), 10, 1);

        // Also listen to order status changed to completed
        add_action('fluent_cart/order_status_changed_to_completed', array($this, 'handle_order_completed'), 10, 1);

        // Subscription cancelled hook - suspend access
        add_action('fluent_cart/subscription_canceled', array($this, 'handle_subscription_cancelled'), 10, 1);

        // Cart validation - ensure single product
        add_filter('fluent_cart/before_checkout_process', array($this, 'validate_single_rocket_product'), 10, 2);

        // Add order meta display
        add_action('fluent_cart/order_details_after_items', array($this, 'display_order_allocation_info'), 10, 1);

        // Add to order emails
        add_action('fluent_cart/email_order_details', array($this, 'add_allocation_to_email'), 20, 2);

        // Register "My Sites" tab in customer portal
        // Use init hook with priority 11 to ensure FluentCart is loaded (FluentCart uses priority 10)
        add_action('init', array($this, 'register_customer_dashboard_tab'), 11);

        // Exclude my-sites from Permalink Manager
        add_filter('permalink_manager_excluded_uris', array($this, 'exclude_my_sites_from_permalink_manager'), 10, 1);

        // Disable Permalink Manager canonical redirect for my-sites
        add_filter('permalink_manager_disable_redirect', array($this, 'disable_permalink_manager_redirect_for_my_sites'), 10, 2);
    }

    /**
     * Handle order paid event
     *
     * @param object $event_data Event data from FluentCart
     */
    public function handle_order_paid($event_data) {
        // Get order from event
        $order = isset($event_data['order']) ? $event_data['order'] : null;

        if (!$order) {
            RFC_Helper::log('Order paid event triggered but no order found', 'warning');
            return;
        }

        $order_id = $order->id;

        RFC_Helper::log('Processing order paid event for order #' . $order_id, 'info');

        // Check if order already has allocations
        $existing_allocations = RFC_Database_Schema::get_order_allocations($order_id);
        if (!empty($existing_allocations)) {
            RFC_Helper::log('Order #' . $order_id . ' already has allocations, skipping', 'info');
            return;
        }

        // Get order items
        $items = RFC_Helper::get_order_items($order_id);

        if (empty($items)) {
            RFC_Helper::log('No items found for order #' . $order_id, 'warning');
            return;
        }

        // Get customer ID
        $customer_id = $order->customer_id;

        // Process each item
        foreach ($items as $item) {
            // Check if this product has Rocket enabled
            if (!RFC_Helper::is_rocket_product($item->post_id)) {
                continue;
            }

            RFC_Helper::log('Processing Rocket product #' . $item->post_id . ' in order #' . $order_id, 'info');

            // Get product configuration
            $config = RFC_Helper::get_product_rocket_config($item->post_id);

            // Calculate total sites (sites per product × quantity)
            $total_sites = RFC_Helper::calculate_total_sites($item, $item->post_id);

            // Create allocation
            $allocation_id = RFC_Database_Schema::create_allocation(array(
                'customer_id' => $customer_id,
                'order_id' => $order_id,
                'product_id' => $item->post_id,
                'total_sites' => $total_sites,
                'used_sites' => 0,
                'status' => 'active',
            ));

            if ($allocation_id) {
                RFC_Helper::log(sprintf(
                    'Created allocation #%d for order #%d: %d sites',
                    $allocation_id,
                    $order_id,
                    $total_sites
                ), 'info');

                // Store allocation info in order meta for email display
                $this->store_allocation_in_order_meta($order_id, $allocation_id, $config, $total_sites);

                // Trigger action for developers
                do_action('rfc_allocation_created', $allocation_id, $order_id, $customer_id);
            } else {
                RFC_Helper::log('Failed to create allocation for order #' . $order_id, 'error');
            }
        }
    }

    /**
     * Handle order completed event
     *
     * @param array $event_data Event data from FluentCart
     */
    public function handle_order_completed($event_data) {
        // Extract order from event data
        $order = isset($event_data['order']) ? $event_data['order'] : null;

        // If no order in the event data, try to get it from the ID
        if (!$order && isset($event_data['order_id'])) {
            $order = RFC_Helper::get_order($event_data['order_id']);
        }

        if (!$order) {
            RFC_Helper::log('Order completed event triggered but no order found', 'warning');
            return;
        }

        // Call the same handler as order_paid
        $this->handle_order_paid(array('order' => $order));
    }

    /**
     * Handle subscription cancelled event
     *
     * @param object $event_data Event data from FluentCart
     */
    public function handle_subscription_cancelled($event_data) {
        // Get subscription and order
        $subscription = isset($event_data['subscription']) ? $event_data['subscription'] : null;
        $order = isset($event_data['order']) ? $event_data['order'] : null;

        if (!$order) {
            RFC_Helper::log('Subscription cancelled event triggered but no order found', 'warning');
            return;
        }

        $order_id = $order->id;

        RFC_Helper::log('Processing subscription cancellation for order #' . $order_id, 'info');

        // Get allocations for this order
        $allocations = RFC_Database_Schema::get_order_allocations($order_id);

        if (empty($allocations)) {
            RFC_Helper::log('No allocations found for order #' . $order_id, 'info');
            return;
        }

        // Update each allocation to cancelled status
        foreach ($allocations as $allocation) {
            // Update allocation status
            RFC_Database_Schema::update_allocation($allocation->id, array(
                'status' => 'cancelled',
            ));

            // Get all sites for this allocation and suspend them
            $sites = RFC_Database_Schema::get_allocation_sites($allocation->id);

            foreach ($sites as $site) {
                // Update site status to suspended
                RFC_Database_Schema::update_site($site->id, array(
                    'status' => 'suspended',
                ));

                RFC_Helper::log(sprintf(
                    'Suspended site #%d (Rocket ID: %s) due to subscription cancellation',
                    $site->id,
                    $site->rocket_site_id
                ), 'info');
            }

            RFC_Helper::log('Allocation #' . $allocation->id . ' marked as cancelled', 'info');

            // Trigger action for developers
            do_action('rfc_allocation_cancelled', $allocation->id, $order_id);
        }
    }

    /**
     * Validate single Rocket product per order
     *
     * @param array $cart_data
     * @param object $checkout_data
     * @return array
     */
    public function validate_single_rocket_product($cart_data, $checkout_data) {
        if (!isset($cart_data['items']) || empty($cart_data['items'])) {
            return $cart_data;
        }

        $rocket_products = array();

        // Count Rocket products in cart
        foreach ($cart_data['items'] as $item) {
            if (isset($item['product_id']) && RFC_Helper::is_rocket_product($item['product_id'])) {
                $rocket_products[] = $item['product_id'];
            }
        }

        // If more than one Rocket product, throw error
        if (count($rocket_products) > 1) {
            throw new Exception(
                __('You can only purchase one Rocket.net hosting product per order. Please remove additional hosting products from your cart.', 'rocket-fluentcart')
            );
        }

        return $cart_data;
    }

    /**
     * Display allocation info on order details page
     *
     * @param object $order
     */
    public function display_order_allocation_info($order) {
        $allocations = RFC_Database_Schema::get_order_allocations($order->id);

        if (empty($allocations)) {
            return;
        }

        foreach ($allocations as $allocation) {
            $product = RFC_Helper::get_product($allocation->product_id);
            $config = RFC_Helper::get_product_rocket_config($allocation->product_id);

            ?>
            <div class="rfc-order-allocation-info" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd;">
                <h4><?php _e('Rocket.net Hosting Allocation', 'rocket-fluentcart'); ?></h4>
                <p><strong><?php _e('Product:', 'rocket-fluentcart'); ?></strong> <?php echo esc_html($product->post_title); ?></p>
                <p><strong><?php _e('Total Sites Allocated:', 'rocket-fluentcart'); ?></strong> <?php echo absint($allocation->total_sites); ?></p>
                <p><strong><?php _e('Sites Created:', 'rocket-fluentcart'); ?></strong> <?php echo absint($allocation->used_sites); ?></p>
                <p><strong><?php _e('Sites Available:', 'rocket-fluentcart'); ?></strong> <?php echo absint($allocation->total_sites - $allocation->used_sites); ?></p>
                <p><strong><?php _e('Status:', 'rocket-fluentcart'); ?></strong> <?php echo RFC_Helper::get_status_label($allocation->status); ?></p>

                <?php if ($config['disk_space']): ?>
                    <p><strong><?php _e('Disk Space per Site:', 'rocket-fluentcart'); ?></strong> <?php echo RFC_Helper::format_disk_space($config['disk_space']); ?></p>
                <?php endif; ?>

                <?php if ($config['bandwidth']): ?>
                    <p><strong><?php _e('Bandwidth per Site:', 'rocket-fluentcart'); ?></strong> <?php echo RFC_Helper::format_bandwidth($config['bandwidth']); ?></p>
                <?php endif; ?>

                <?php if ($config['visitors']): ?>
                    <p><strong><?php _e('Monthly Visitors per Site:', 'rocket-fluentcart'); ?></strong> <?php echo RFC_Helper::format_number($config['visitors']); ?></p>
                <?php endif; ?>

                <?php if ($allocation->status === 'active'): ?>
                    <p style="margin-top: 15px;">
                        <a href="<?php echo esc_url(home_url('/my-sites/')); ?>" class="button button-primary">
                            <?php _e('Manage Your Sites', 'rocket-fluentcart'); ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>
            <?php
        }
    }

    /**
     * Add allocation info to order emails
     *
     * @param object $order
     * @param string $email_type
     */
    public function add_allocation_to_email($order, $email_type) {
        // Only show in receipt/confirmation emails
        if (!in_array($email_type, array('receipt', 'confirmation', 'order_paid'))) {
            return;
        }

        $allocations = RFC_Database_Schema::get_order_allocations($order->id);

        if (empty($allocations)) {
            return;
        }

        foreach ($allocations as $allocation) {
            $product = RFC_Helper::get_product($allocation->product_id);
            $config = RFC_Helper::get_product_rocket_config($allocation->product_id);

            ?>
            <div style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd;">
                <h3 style="margin-top: 0;"><?php _e('Rocket.net Hosting Details', 'rocket-fluentcart'); ?></h3>
                <p><strong><?php _e('Product:', 'rocket-fluentcart'); ?></strong> <?php echo esc_html($product->post_title); ?></p>
                <p><strong><?php _e('Sites Allocated:', 'rocket-fluentcart'); ?></strong> <?php echo absint($allocation->total_sites); ?></p>

                <?php if ($config['disk_space']): ?>
                    <p><strong><?php _e('Disk Space per Site:', 'rocket-fluentcart'); ?></strong> <?php echo RFC_Helper::format_disk_space($config['disk_space']); ?></p>
                <?php endif; ?>

                <?php if ($config['bandwidth']): ?>
                    <p><strong><?php _e('Bandwidth per Site:', 'rocket-fluentcart'); ?></strong> <?php echo RFC_Helper::format_bandwidth($config['bandwidth']); ?></p>
                <?php endif; ?>

                <?php if ($config['visitors']): ?>
                    <p><strong><?php _e('Monthly Visitors per Site:', 'rocket-fluentcart'); ?></strong> <?php echo RFC_Helper::format_number($config['visitors']); ?></p>
                <?php endif; ?>

                <p style="margin-top: 15px;">
                    <strong><?php _e('Next Steps:', 'rocket-fluentcart'); ?></strong><br>
                    <?php echo sprintf(
                        __('Visit your %sMy Sites dashboard%s to create and manage your hosting sites.', 'rocket-fluentcart'),
                        '<a href="' . esc_url(home_url('/my-sites/')) . '">',
                        '</a>'
                    ); ?>
                </p>
            </div>
            <?php
        }
    }

    /**
     * Store allocation info in order meta
     *
     * @param int $order_id
     * @param int $allocation_id
     * @param array $config
     * @param int $total_sites
     */
    private function store_allocation_in_order_meta($order_id, $allocation_id, $config, $total_sites) {
        global $wpdb;
        $table = $wpdb->prefix . 'fct_order_meta';

        $meta_data = array(
            'allocation_id' => $allocation_id,
            'total_sites' => $total_sites,
            'disk_space' => $config['disk_space'],
            'bandwidth' => $config['bandwidth'],
            'visitors' => $config['visitors'],
        );

        // Check if meta table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table) {
            $wpdb->insert(
                $table,
                array(
                    'order_id' => $order_id,
                    'meta_key' => '_rocket_allocation',
                    'meta_value' => json_encode($meta_data),
                ),
                array('%d', '%s', '%s')
            );
        }
    }

    /**
     * Register "My Sites" tab in FluentCart customer dashboard
     */
    public function register_customer_dashboard_tab() {
        RFC_Helper::log('register_customer_dashboard_tab called', 'info');

        // Register menu item
        add_filter('fluent_cart/global_customer_menu_items', array($this, 'add_my_sites_menu_item'), 10, 1);

        // Register custom endpoint - use priority 1 to ensure it's registered early
        add_filter('fluent_cart/customer_portal/custom_endpoints', array($this, 'add_my_sites_endpoint'), 1, 1);

        RFC_Helper::log('My Sites filters registered', 'info');

        // Check if we need to flush rewrite rules
        $flushed = get_option('rfc_fluentcart_rewrite_flushed', false);
        if (!$flushed) {
            RFC_Helper::log('Flushing rewrite rules for FluentCart endpoints', 'info');
            flush_rewrite_rules();
            update_option('rfc_fluentcart_rewrite_flushed', true);
        }
    }

    /**
     * Add My Sites menu item
     */
    public function add_my_sites_menu_item($items) {
        RFC_Helper::log('add_my_sites_menu_item called with items: ' . json_encode(array_keys($items)), 'info');

        // Check if FluentCart URL class exists
        if (!class_exists('\FluentCart\App\Services\URL')) {
            RFC_Helper::log('FluentCart URL class not found', 'warning');
            return $items;
        }

        // Add "My Sites" menu item before "profile"
        $profileKey = array_search('profile', array_keys($items));

        $newItem = array(
            'my-sites' => array(
                'label' => __('My Sites', 'rocket-fluentcart'),
                'css_class' => 'fct-menu-item-my-sites',
                'link' => \FluentCart\App\Services\URL::getCustomerDashboardUrl('my-sites')
            )
        );

        if ($profileKey !== false) {
            $items = array_slice($items, 0, $profileKey, true) +
                     $newItem +
                     array_slice($items, $profileKey, null, true);
        } else {
            $items = array_merge($items, $newItem);
        }

        RFC_Helper::log('Menu items after adding My Sites: ' . json_encode(array_keys($items)), 'info');

        return $items;
    }

    /**
     * Add My Sites endpoint
     */
    public function add_my_sites_endpoint($endpoints) {
        // Log the backtrace to see where this is being called from
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        $caller = isset($backtrace[3]) ? $backtrace[3]['function'] : 'unknown';

        RFC_Helper::log('add_my_sites_endpoint called from: ' . $caller, 'info');
        RFC_Helper::log('Existing endpoints: ' . json_encode(array_keys($endpoints)), 'info');

        $endpoints['my-sites'] = array(
            'render_callback' => array($this, 'render_my_sites_tab')
        );

        RFC_Helper::log('Endpoints after adding my-sites: ' . json_encode(array_keys($endpoints)), 'info');

        return $endpoints;
    }

    /**
     * Render "My Sites" tab content
     */
    public function render_my_sites_tab() {
        RFC_Helper::log('render_my_sites_tab callback executed', 'info');

        // Ensure frontend CSS and JS are enqueued
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

        wp_localize_script('rfc-frontend', 'rfcFrontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('rfc_frontend'),
            'manage_page_url' => \FluentCart\App\Services\URL::getCustomerDashboardUrl('my-sites'),
            'strings' => array(
                'loading' => __('Loading...', 'rocket-fluentcart'),
                'error' => __('An error occurred. Please try again.', 'rocket-fluentcart'),
                'success' => __('Success!', 'rocket-fluentcart'),
                'confirm_create' => __('Are you sure you want to create this site?', 'rocket-fluentcart'),
            ),
        ));

        // Wrap the My Sites content in FluentCart's dashboard structure
        echo '<div class="fc-purchase-history-route-wrap">';
        echo do_shortcode('[rocket_my_sites]');
        echo '</div>';
    }
}
