<?php
/**
 * Site Allocations Admin Page
 *
 * Displays overview of all site allocations
 */

defined('ABSPATH') || exit;

class RFC_Admin_SiteAllocations {

    /**
     * Instance
     *
     * @var RFC_Admin_SiteAllocations
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return RFC_Admin_SiteAllocations
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
        add_action('admin_menu', array($this, 'add_allocations_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        // AJAX handlers
        add_action('wp_ajax_rfc_delete_allocation', array($this, 'ajax_delete_allocation'));
        add_action('wp_ajax_rfc_save_allocation', array($this, 'ajax_save_allocation'));
        add_action('wp_ajax_rfc_get_allocation', array($this, 'ajax_get_allocation'));
    }

    /**
     * Add allocations page to FluentCart menu
     */
    public function add_allocations_page() {
        // Check if FluentCart menu exists
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

        // Add as submenu if FluentCart exists, otherwise under Rocket Settings
        $parent_slug = $fluent_cart_exists ? 'fluent-cart' : 'rocket-settings';

        add_submenu_page(
            $parent_slug,
            __('Site Allocations', 'rocket-fluentcart'),
            __('Site Allocations', 'rocket-fluentcart'),
            'manage_options',
            'rocket-allocations',
            array($this, 'render_allocations_page')
        );
    }

    /**
     * Enqueue scripts
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'fluent-cart_page_rocket-allocations') {
            return;
        }

        wp_enqueue_script('jquery');

        wp_enqueue_style(
            'rfc-admin-allocations',
            RFC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            RFC_VERSION
        );
    }

    /**
     * Render allocations page
     */
    public function render_allocations_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        // Get filter parameters
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;

        // Get allocations
        $results = RFC_Database_Schema::get_all_allocations(array(
            'status' => $status,
            'search' => $search,
            'page' => $paged,
            'per_page' => 20,
        ));

        $allocations = $results['allocations'];
        $total = $results['total'];
        $pages = $results['pages'];

        // Calculate stats
        $stats = $this->calculate_stats();

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php _e('Site Allocations', 'rocket-fluentcart'); ?></h1>
            <button type="button" class="page-title-action rfc-create-allocation-btn">
                <?php _e('Create Allocation', 'rocket-fluentcart'); ?>
            </button>
            <hr class="wp-header-end">

            <!-- Stats Cards -->
            <div class="rfc-stats-cards" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
                <div class="rfc-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                    <div style="font-size: 32px; font-weight: bold; color: #2196f3;">
                        <?php echo absint($stats['total_allocations']); ?>
                    </div>
                    <div style="color: #666; margin-top: 5px;">Total Allocations</div>
                </div>

                <div class="rfc-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                    <div style="font-size: 32px; font-weight: bold; color: #4caf50;">
                        <?php echo absint($stats['total_sites']); ?>
                    </div>
                    <div style="color: #666; margin-top: 5px;">Sites Created</div>
                </div>

                <div class="rfc-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                    <div style="font-size: 32px; font-weight: bold; color: #ff9800;">
                        <?php echo absint($stats['available_sites']); ?>
                    </div>
                    <div style="color: #666; margin-top: 5px;">Sites Available</div>
                </div>

                <div class="rfc-stat-card" style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 4px;">
                    <div style="font-size: 32px; font-weight: bold; color: #f44336;">
                        <?php echo absint($stats['cancelled_allocations']); ?>
                    </div>
                    <div style="color: #666; margin-top: 5px;">Cancelled</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="rfc-filters">
                <form method="get">
                    <input type="hidden" name="page" value="rocket-allocations">

                    <label for="filter-status"><?php _e('Status:', 'rocket-fluentcart'); ?></label>
                    <select name="status" id="filter-status">
                        <option value="all" <?php selected($status, 'all'); ?>><?php _e('All', 'rocket-fluentcart'); ?></option>
                        <option value="active" <?php selected($status, 'active'); ?>><?php _e('Active', 'rocket-fluentcart'); ?></option>
                        <option value="cancelled" <?php selected($status, 'cancelled'); ?>><?php _e('Cancelled', 'rocket-fluentcart'); ?></option>
                    </select>

                    <label for="search-input"><?php _e('Search:', 'rocket-fluentcart'); ?></label>
                    <input type="text"
                           name="s"
                           id="search-input"
                           value="<?php echo esc_attr($search); ?>"
                           placeholder="<?php esc_attr_e('Order ID, Customer ID...', 'rocket-fluentcart'); ?>">

                    <button type="submit" class="button"><?php _e('Filter', 'rocket-fluentcart'); ?></button>

                    <?php if ($status !== 'all' || $search): ?>
                        <a href="<?php echo admin_url('admin.php?page=rocket-allocations'); ?>" class="button">
                            <?php _e('Clear', 'rocket-fluentcart'); ?>
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Allocations Table -->
            <?php if (empty($allocations)): ?>
                <div class="rfc-empty-state">
                    <div class="rfc-empty-state-icon">📊</div>
                    <h3><?php _e('No allocations found', 'rocket-fluentcart'); ?></h3>
                    <p><?php _e('Allocations will appear here when customers purchase hosting products.', 'rocket-fluentcart'); ?></p>
                </div>
            <?php else: ?>
                <table class="wp-list-table widefat fixed striped rfc-allocations-table">
                    <thead>
                        <tr>
                            <th><?php _e('ID', 'rocket-fluentcart'); ?></th>
                            <th><?php _e('Customer', 'rocket-fluentcart'); ?></th>
                            <th><?php _e('Order', 'rocket-fluentcart'); ?></th>
                            <th><?php _e('Product', 'rocket-fluentcart'); ?></th>
                            <th><?php _e('Total Sites', 'rocket-fluentcart'); ?></th>
                            <th><?php _e('Used Sites', 'rocket-fluentcart'); ?></th>
                            <th><?php _e('Available', 'rocket-fluentcart'); ?></th>
                            <th><?php _e('Status', 'rocket-fluentcart'); ?></th>
                            <th><?php _e('Created', 'rocket-fluentcart'); ?></th>
                            <th><?php _e('Actions', 'rocket-fluentcart'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allocations as $allocation): ?>
                            <?php $this->render_allocation_row($allocation); ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <?php if ($pages > 1): ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <?php
                            echo paginate_links(array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => __('&laquo; Previous'),
                                'next_text' => __('Next &raquo;'),
                                'total' => $pages,
                                'current' => $paged,
                            ));
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Create/Edit Allocation Modal -->
            <?php $this->render_allocation_modal(); ?>
        </div>
        <?php
    }

    /**
     * Render allocation row
     *
     * @param object $allocation
     */
    private function render_allocation_row($allocation) {
        $customer = RFC_Helper::get_customer($allocation->customer_id);
        $order = RFC_Helper::get_order($allocation->order_id);
        $product = RFC_Helper::get_product($allocation->product_id);
        $sites = RFC_Database_Schema::get_allocation_sites($allocation->id);
        $available = $allocation->total_sites - $allocation->used_sites;

        ?>
        <tr>
            <td><strong>#<?php echo absint($allocation->id); ?></strong></td>
            <td>
                <?php if ($customer): ?>
                    <?php echo esc_html($customer->full_name ?: $customer->email); ?>
                    <br><small><?php echo esc_html($customer->email); ?></small>
                <?php else: ?>
                    <em><?php _e('N/A', 'rocket-fluentcart'); ?></em>
                <?php endif; ?>
            </td>
            <td>
                <a href="<?php echo admin_url('admin.php?page=fluent-cart#/orders/' . $allocation->order_id); ?>" target="_blank">
                    #<?php echo absint($allocation->order_id); ?>
                </a>
            </td>
            <td>
                <?php if ($product): ?>
                    <a href="<?php echo get_edit_post_link($product->ID); ?>" target="_blank">
                        <?php echo esc_html($product->post_title); ?>
                    </a>
                <?php else: ?>
                    <em><?php _e('N/A', 'rocket-fluentcart'); ?></em>
                <?php endif; ?>
            </td>
            <td><?php echo absint($allocation->total_sites); ?></td>
            <td><?php echo absint($allocation->used_sites); ?></td>
            <td>
                <strong style="color: <?php echo $available > 0 ? '#4caf50' : '#999'; ?>">
                    <?php echo absint($available); ?>
                </strong>
            </td>
            <td><?php echo RFC_Helper::get_status_label($allocation->status); ?></td>
            <td><?php echo RFC_Helper::format_date($allocation->created_at, 'M j, Y'); ?></td>
            <td>
                <button type="button"
                        class="button button-small"
                        onclick="rfcToggleAllocationDetails(<?php echo absint($allocation->id); ?>)">
                    <?php _e('View Sites', 'rocket-fluentcart'); ?>
                </button>
                <button type="button"
                        class="button button-small rfc-edit-allocation"
                        data-allocation-id="<?php echo absint($allocation->id); ?>">
                    <?php _e('Edit', 'rocket-fluentcart'); ?>
                </button>
                <button type="button"
                        class="button button-small button-link-delete rfc-delete-allocation"
                        data-allocation-id="<?php echo absint($allocation->id); ?>"
                        data-order-id="<?php echo absint($allocation->order_id); ?>"
                        style="color: #b32d2e;">
                    <?php _e('Delete', 'rocket-fluentcart'); ?>
                </button>
            </td>
        </tr>
        <tr id="allocation-details-<?php echo absint($allocation->id); ?>" class="rfc-allocation-details-row" style="display: none;">
            <td colspan="10">
                <?php $this->render_allocation_details($allocation, $sites); ?>
            </td>
        </tr>
        <?php
    }

    /**
     * Render allocation details
     *
     * @param object $allocation
     * @param array $sites
     */
    private function render_allocation_details($allocation, $sites) {
        $config = RFC_Helper::get_product_rocket_config($allocation->product_id);

        ?>
        <div class="rfc-allocation-details" style="padding: 20px; background: #f9f9f9;">
            <h3><?php _e('Allocation Details', 'rocket-fluentcart'); ?></h3>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
                <?php if ($config['disk_space']): ?>
                    <div>
                        <strong><?php _e('Disk Space:', 'rocket-fluentcart'); ?></strong>
                        <?php echo RFC_Helper::format_disk_space($config['disk_space']); ?>
                    </div>
                <?php endif; ?>

                <?php if ($config['bandwidth']): ?>
                    <div>
                        <strong><?php _e('Bandwidth:', 'rocket-fluentcart'); ?></strong>
                        <?php echo RFC_Helper::format_bandwidth($config['bandwidth']); ?>
                    </div>
                <?php endif; ?>

                <?php if ($config['visitors']): ?>
                    <div>
                        <strong><?php _e('Monthly Visitors:', 'rocket-fluentcart'); ?></strong>
                        <?php echo RFC_Helper::format_number($config['visitors']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <h4><?php _e('Sites', 'rocket-fluentcart'); ?> (<?php echo count($sites); ?>)</h4>

            <?php if (empty($sites)): ?>
                <p><em><?php _e('No sites created yet.', 'rocket-fluentcart'); ?></em></p>
            <?php else: ?>
                <table class="wp-list-table widefat striped" style="margin-top: 10px;">
                    <thead>
                        <tr>
                            <th><?php _e('ID', 'rocket-fluentcart'); ?></th>
                            <th><?php _e('Site Name', 'rocket-fluentcart'); ?></th>
                            <th><?php _e('Rocket Site ID', 'rocket-fluentcart'); ?></th>
                            <th><?php _e('Status', 'rocket-fluentcart'); ?></th>
                            <th><?php _e('Created', 'rocket-fluentcart'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sites as $site): ?>
                            <?php
                            $site_data = $site->rocket_site_data ? json_decode($site->rocket_site_data, true) : array();
                            $site_url = isset($site_data['url']) ? $site_data['url'] : '';
                            ?>
                            <tr>
                                <td>#<?php echo absint($site->id); ?></td>
                                <td>
                                    <strong><?php echo esc_html($site->site_name); ?></strong>
                                    <?php if ($site_url): ?>
                                        <br><a href="<?php echo esc_url($site_url); ?>" target="_blank">
                                            <?php echo esc_html($site_url); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                                <td><code><?php echo esc_html($site->rocket_site_id); ?></code></td>
                                <td><?php echo RFC_Helper::get_status_label($site->status); ?></td>
                                <td><?php echo RFC_Helper::format_date($site->created_at, 'M j, Y g:i a'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <script>
        function rfcToggleAllocationDetails(allocationId) {
            var row = document.getElementById('allocation-details-' + allocationId);
            if (row.style.display === 'none') {
                row.style.display = 'table-row';
            } else {
                row.style.display = 'none';
            }
        }
        </script>
        <?php
    }

    /**
     * Calculate stats
     *
     * @return array
     */
    private function calculate_stats() {
        global $wpdb;
        $allocations_table = RFC_Database_Schema::get_allocations_table();
        $sites_table = RFC_Database_Schema::get_sites_table();

        $stats = array(
            'total_allocations' => 0,
            'total_sites' => 0,
            'available_sites' => 0,
            'cancelled_allocations' => 0,
        );

        // Total allocations
        $stats['total_allocations'] = $wpdb->get_var("SELECT COUNT(*) FROM {$allocations_table}");

        // Total sites created
        $stats['total_sites'] = $wpdb->get_var("SELECT COUNT(*) FROM {$sites_table} WHERE deleted_at IS NULL");

        // Available sites
        $stats['available_sites'] = $wpdb->get_var("
            SELECT SUM(total_sites - used_sites)
            FROM {$allocations_table}
            WHERE status = 'active'
        ");

        // Cancelled allocations
        $stats['cancelled_allocations'] = $wpdb->get_var("
            SELECT COUNT(*)
            FROM {$allocations_table}
            WHERE status = 'cancelled'
        ");

        return $stats;
    }

    /**
     * AJAX: Delete allocation
     */
    public function ajax_delete_allocation() {
        check_ajax_referer('rfc_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'rocket-fluentcart')));
        }

        $allocation_id = isset($_POST['allocation_id']) ? absint($_POST['allocation_id']) : 0;

        if (!$allocation_id) {
            wp_send_json_error(array('message' => __('Invalid allocation ID', 'rocket-fluentcart')));
        }

        // Get allocation details for logging
        $allocation = RFC_Database_Schema::get_allocation($allocation_id);

        if (!$allocation) {
            wp_send_json_error(array('message' => __('Allocation not found', 'rocket-fluentcart')));
        }

        // Delete the allocation (and all its sites)
        $result = RFC_Database_Schema::delete_allocation($allocation_id);

        if ($result) {
            RFC_Helper::log(sprintf(
                'Allocation #%d deleted (Order #%d, Customer #%d)',
                $allocation_id,
                $allocation->order_id,
                $allocation->customer_id
            ), 'info');

            wp_send_json_success(array(
                'message' => __('Allocation deleted successfully', 'rocket-fluentcart')
            ));
        } else {
            wp_send_json_error(array('message' => __('Failed to delete allocation', 'rocket-fluentcart')));
        }
    }

    /**
     * Render allocation modal
     */
    private function render_allocation_modal() {
        // Get all customers for dropdown
        global $wpdb;
        $customers_table = $wpdb->prefix . 'fct_customers';

        // Detect customer name columns
        $customer_columns = $wpdb->get_col("SHOW COLUMNS FROM {$customers_table}");
        $name_field = in_array('full_name', $customer_columns) ? 'full_name' :
                     (in_array('first_name', $customer_columns) ? 'CONCAT(first_name, " ", COALESCE(last_name, ""))' : 'email');

        $customers = $wpdb->get_results("SELECT id, {$name_field} as full_name, email FROM {$customers_table} ORDER BY id DESC LIMIT 1000");

        // Get all orders for dropdown
        $orders_table = $wpdb->prefix . 'fct_orders';

        // Detect order hash column
        $order_columns = $wpdb->get_col("SHOW COLUMNS FROM {$orders_table}");
        $hash_field = in_array('hash', $order_columns) ? 'hash' :
                     (in_array('order_hash', $order_columns) ? 'order_hash' : 'id');

        $orders = $wpdb->get_results("SELECT id, {$hash_field} as hash, customer_id FROM {$orders_table} ORDER BY id DESC LIMIT 1000");

        // Get all products (only published posts)
        $products = get_posts(array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        ?>
        <div id="rfc-allocation-modal" class="rfc-modal" style="display: none;">
            <div class="rfc-modal-backdrop"></div>
            <div class="rfc-modal-content">
                <div class="rfc-modal-header">
                    <h2 id="rfc-modal-title"><?php _e('Create Allocation', 'rocket-fluentcart'); ?></h2>
                    <button type="button" class="rfc-modal-close" aria-label="Close">&times;</button>
                </div>
                <div class="rfc-modal-body">
                    <form id="rfc-allocation-form">
                        <input type="hidden" id="allocation_id" name="allocation_id" value="">

                        <table class="form-table">
                            <tr>
                                <th><label for="customer_id"><?php _e('Customer', 'rocket-fluentcart'); ?> <span class="required">*</span></label></th>
                                <td>
                                    <select id="customer_id" name="customer_id" class="regular-text" required>
                                        <option value=""><?php _e('Select Customer', 'rocket-fluentcart'); ?></option>
                                        <?php foreach ($customers as $customer): ?>
                                            <option value="<?php echo absint($customer->id); ?>">
                                                <?php echo esc_html($customer->full_name ?: $customer->email); ?> (<?php echo esc_html($customer->email); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="order_id"><?php _e('Order', 'rocket-fluentcart'); ?> <span class="required">*</span></label></th>
                                <td>
                                    <select id="order_id" name="order_id" class="regular-text" required>
                                        <option value=""><?php _e('Select Order', 'rocket-fluentcart'); ?></option>
                                        <?php foreach ($orders as $order): ?>
                                            <option value="<?php echo absint($order->id); ?>" data-customer-id="<?php echo absint($order->customer_id); ?>">
                                                #<?php echo absint($order->id); ?> - <?php echo esc_html($order->hash); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description"><?php _e('Orders will be filtered by selected customer', 'rocket-fluentcart'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="product_id"><?php _e('Product', 'rocket-fluentcart'); ?> <span class="required">*</span></label></th>
                                <td>
                                    <select id="product_id" name="product_id" class="regular-text" required>
                                        <option value=""><?php _e('Select Product', 'rocket-fluentcart'); ?></option>
                                        <?php foreach ($products as $product): ?>
                                            <option value="<?php echo absint($product->ID); ?>">
                                                <?php echo esc_html($product->post_title); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="total_sites"><?php _e('Total Sites', 'rocket-fluentcart'); ?> <span class="required">*</span></label></th>
                                <td>
                                    <input type="number" id="total_sites" name="total_sites" class="regular-text" min="1" required>
                                    <p class="description"><?php _e('Maximum number of sites this allocation allows', 'rocket-fluentcart'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="used_sites"><?php _e('Used Sites', 'rocket-fluentcart'); ?></label></th>
                                <td>
                                    <input type="number" id="used_sites" name="used_sites" class="regular-text" min="0" value="0">
                                    <p class="description"><?php _e('Number of sites already created (usually 0 for new allocations)', 'rocket-fluentcart'); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="status"><?php _e('Status', 'rocket-fluentcart'); ?> <span class="required">*</span></label></th>
                                <td>
                                    <select id="status" name="status" class="regular-text" required>
                                        <option value="active"><?php _e('Active', 'rocket-fluentcart'); ?></option>
                                        <option value="cancelled"><?php _e('Cancelled', 'rocket-fluentcart'); ?></option>
                                        <option value="suspended"><?php _e('Suspended', 'rocket-fluentcart'); ?></option>
                                    </select>
                                </td>
                            </tr>
                        </table>

                        <div id="rfc-allocation-messages"></div>
                    </form>
                </div>
                <div class="rfc-modal-footer">
                    <button type="button" class="button rfc-modal-close"><?php _e('Cancel', 'rocket-fluentcart'); ?></button>
                    <button type="button" class="button button-primary" id="rfc-save-allocation-btn">
                        <?php _e('Save Allocation', 'rocket-fluentcart'); ?>
                    </button>
                </div>
            </div>
        </div>

        <style>
        .rfc-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 100000;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .rfc-modal-backdrop {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
        }
        .rfc-modal-content {
            position: relative;
            background: #fff;
            border-radius: 4px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
            width: 90%;
            max-width: 800px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
        }
        .rfc-modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .rfc-modal-header h2 {
            margin: 0;
            font-size: 20px;
        }
        .rfc-modal-header .rfc-modal-close {
            background: none;
            border: none;
            font-size: 28px;
            line-height: 1;
            cursor: pointer;
            color: #666;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .rfc-modal-header .rfc-modal-close:hover {
            color: #000;
        }
        .rfc-modal-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }
        .rfc-modal-footer {
            padding: 16px 24px;
            border-top: 1px solid #ddd;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        .required {
            color: #dc3232;
        }
        #rfc-allocation-messages .notice {
            margin: 16px 0 0 0;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Handle create allocation button
            $('.rfc-create-allocation-btn').on('click', function() {
                $('#rfc-modal-title').text('<?php _e('Create Allocation', 'rocket-fluentcart'); ?>');
                $('#rfc-allocation-form')[0].reset();
                $('#allocation_id').val('');
                $('#rfc-allocation-messages').html('');
                $('#rfc-allocation-modal').fadeIn(200);
            });

            // Handle edit allocation button
            $('.rfc-edit-allocation').on('click', function() {
                var allocationId = $(this).data('allocation-id');
                $('#rfc-modal-title').text('<?php _e('Edit Allocation', 'rocket-fluentcart'); ?>');
                $('#rfc-allocation-messages').html('');

                // Load allocation data
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rfc_get_allocation',
                        nonce: '<?php echo wp_create_nonce('rfc_admin'); ?>',
                        allocation_id: allocationId
                    },
                    success: function(response) {
                        if (response.success) {
                            var allocation = response.data.allocation;
                            $('#allocation_id').val(allocation.id);
                            $('#customer_id').val(allocation.customer_id);
                            $('#order_id').val(allocation.order_id);
                            $('#product_id').val(allocation.product_id);
                            $('#total_sites').val(allocation.total_sites);
                            $('#used_sites').val(allocation.used_sites);
                            $('#status').val(allocation.status);
                            $('#rfc-allocation-modal').fadeIn(200);
                        } else {
                            alert(response.data.message || 'Failed to load allocation');
                        }
                    },
                    error: function() {
                        alert('An error occurred. Please try again.');
                    }
                });
            });

            // Handle modal close
            $('.rfc-modal-close, .rfc-modal-backdrop').on('click', function() {
                $('#rfc-allocation-modal').fadeOut(200);
            });

            // Prevent modal content clicks from closing
            $('.rfc-modal-content').on('click', function(e) {
                e.stopPropagation();
            });

            // Filter orders by selected customer
            $('#customer_id').on('change', function() {
                var customerId = $(this).val();
                $('#order_id option').each(function() {
                    var $option = $(this);
                    if ($option.val() === '') {
                        $option.show();
                        return;
                    }
                    if (!customerId || $option.data('customer-id') == customerId) {
                        $option.show();
                    } else {
                        $option.hide();
                    }
                });
                $('#order_id').val('');
            });

            // Handle save allocation
            $('#rfc-save-allocation-btn').on('click', function() {
                var $btn = $(this);
                var $form = $('#rfc-allocation-form');
                var $messages = $('#rfc-allocation-messages');

                // Basic validation
                if (!$form[0].checkValidity()) {
                    $form[0].reportValidity();
                    return;
                }

                // Disable button
                $btn.prop('disabled', true).text('<?php _e('Saving...', 'rocket-fluentcart'); ?>');
                $messages.html('');

                // Collect form data
                var formData = {
                    action: 'rfc_save_allocation',
                    nonce: '<?php echo wp_create_nonce('rfc_admin'); ?>',
                    allocation_id: $('#allocation_id').val(),
                    customer_id: $('#customer_id').val(),
                    order_id: $('#order_id').val(),
                    product_id: $('#product_id').val(),
                    total_sites: $('#total_sites').val(),
                    used_sites: $('#used_sites').val(),
                    status: $('#status').val()
                };

                // Send AJAX request
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        if (response.success) {
                            $messages.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        } else {
                            $messages.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                            $btn.prop('disabled', false).text('<?php _e('Save Allocation', 'rocket-fluentcart'); ?>');
                        }
                    },
                    error: function() {
                        $messages.html('<div class="notice notice-error"><p>An error occurred. Please try again.</p></div>');
                        $btn.prop('disabled', false).text('<?php _e('Save Allocation', 'rocket-fluentcart'); ?>');
                    }
                });
            });

            // Handle delete allocation
            $('.rfc-delete-allocation').on('click', function(e) {
                e.preventDefault();

                var $btn = $(this);
                var allocationId = $btn.data('allocation-id');
                var orderId = $btn.data('order-id');

                if (!confirm('Are you sure you want to delete this allocation? This will also delete all sites in this allocation. This action cannot be undone.')) {
                    return;
                }

                // Disable button
                $btn.prop('disabled', true).text('Deleting...');

                // Send AJAX request
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'rfc_delete_allocation',
                        nonce: '<?php echo wp_create_nonce('rfc_admin'); ?>',
                        allocation_id: allocationId
                    },
                    success: function(response) {
                        if (response.success) {
                            // Remove the row
                            $btn.closest('tr').fadeOut(function() {
                                $(this).next('.rfc-allocation-details-row').remove();
                                $(this).remove();
                            });

                            // Show success message
                            if (typeof wp !== 'undefined' && wp.data) {
                                wp.data.dispatch('core/notices').createNotice(
                                    'success',
                                    response.data.message,
                                    { isDismissible: true }
                                );
                            }
                        } else {
                            alert(response.data.message || 'Failed to delete allocation');
                            $btn.prop('disabled', false).text('Delete');
                        }
                    },
                    error: function() {
                        alert('An error occurred. Please try again.');
                        $btn.prop('disabled', false).text('Delete');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * AJAX: Get allocation
     */
    public function ajax_get_allocation() {
        check_ajax_referer('rfc_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'rocket-fluentcart')));
        }

        $allocation_id = isset($_POST['allocation_id']) ? absint($_POST['allocation_id']) : 0;

        if (!$allocation_id) {
            wp_send_json_error(array('message' => __('Invalid allocation ID', 'rocket-fluentcart')));
        }

        $allocation = RFC_Database_Schema::get_allocation($allocation_id);

        if (!$allocation) {
            wp_send_json_error(array('message' => __('Allocation not found', 'rocket-fluentcart')));
        }

        wp_send_json_success(array(
            'allocation' => $allocation
        ));
    }

    /**
     * AJAX: Save allocation (create or update)
     */
    public function ajax_save_allocation() {
        check_ajax_referer('rfc_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized', 'rocket-fluentcart')));
        }

        $allocation_id = isset($_POST['allocation_id']) ? absint($_POST['allocation_id']) : 0;
        $customer_id = isset($_POST['customer_id']) ? absint($_POST['customer_id']) : 0;
        $order_id = isset($_POST['order_id']) ? absint($_POST['order_id']) : 0;
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $total_sites = isset($_POST['total_sites']) ? absint($_POST['total_sites']) : 0;
        $used_sites = isset($_POST['used_sites']) ? absint($_POST['used_sites']) : 0;
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'active';

        // Validation
        if (!$customer_id || !$order_id || !$product_id || !$total_sites) {
            wp_send_json_error(array('message' => __('All required fields must be filled', 'rocket-fluentcart')));
        }

        if ($used_sites > $total_sites) {
            wp_send_json_error(array('message' => __('Used sites cannot exceed total sites', 'rocket-fluentcart')));
        }

        $data = array(
            'customer_id' => $customer_id,
            'order_id' => $order_id,
            'product_id' => $product_id,
            'total_sites' => $total_sites,
            'used_sites' => $used_sites,
            'status' => $status,
        );

        if ($allocation_id) {
            // Update existing allocation
            $result = RFC_Database_Schema::update_allocation($allocation_id, $data);

            if ($result !== false) {
                RFC_Helper::log(sprintf(
                    'Allocation #%d updated (Order #%d, Total: %d, Used: %d, Status: %s)',
                    $allocation_id,
                    $order_id,
                    $total_sites,
                    $used_sites,
                    $status
                ), 'info');

                wp_send_json_success(array(
                    'message' => __('Allocation updated successfully', 'rocket-fluentcart'),
                    'allocation_id' => $allocation_id
                ));
            } else {
                wp_send_json_error(array('message' => __('Failed to update allocation', 'rocket-fluentcart')));
            }
        } else {
            // Create new allocation
            $result = RFC_Database_Schema::create_allocation($data);

            if ($result) {
                RFC_Helper::log(sprintf(
                    'Allocation created: #%d (Order #%d, Customer #%d, Total: %d sites)',
                    $result,
                    $order_id,
                    $customer_id,
                    $total_sites
                ), 'info');

                wp_send_json_success(array(
                    'message' => __('Allocation created successfully', 'rocket-fluentcart'),
                    'allocation_id' => $result
                ));
            } else {
                wp_send_json_error(array('message' => __('Failed to create allocation', 'rocket-fluentcart')));
            }
        }
    }
}
