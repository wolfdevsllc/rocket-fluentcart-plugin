<?php
/**
 * Helper Functions
 *
 * Utility functions for the plugin
 */

defined('ABSPATH') || exit;

class RFC_Helper {

    /**
     * Format disk space value
     *
     * @param int $value Value in MB
     * @return string
     */
    public static function format_disk_space($value) {
        $value = absint($value);

        if ($value < 1024) {
            return $value . ' MB';
        } elseif ($value < 1048576) { // 1024 * 1024
            return round($value / 1024, 2) . ' GB';
        } else {
            return round($value / 1048576, 2) . ' TB';
        }
    }

    /**
     * Format bandwidth value
     *
     * @param int $value Value in MB
     * @return string
     */
    public static function format_bandwidth($value) {
        return self::format_disk_space($value);
    }

    /**
     * Format number with separators
     *
     * @param int $number
     * @return string
     */
    public static function format_number($number) {
        return number_format($number, 0, '.', ',');
    }

    /**
     * Get current customer ID
     *
     * @return int|false
     */
    public static function get_current_customer_id() {
        if (!is_user_logged_in()) {
            return false;
        }

        $user_id = get_current_user_id();

        // Try to get FluentCart customer by user ID
        global $wpdb;
        $table = $wpdb->prefix . 'fct_customers';

        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table} WHERE user_id = %d LIMIT 1",
            $user_id
        ));

        return $customer ? absint($customer->id) : false;
    }

    /**
     * Get customer by ID
     *
     * @param int $customer_id
     * @return object|null
     */
    public static function get_customer($customer_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'fct_customers';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $customer_id
        ));
    }

    /**
     * Get product meta value
     *
     * @param int $product_id
     * @param string $meta_key
     * @param mixed $default
     * @return mixed
     */
    public static function get_product_meta($product_id, $meta_key, $default = '') {
        $value = get_post_meta($product_id, '_rocket_' . $meta_key, true);
        return $value !== '' ? $value : $default;
    }

    /**
     * Check if product has Rocket enabled
     *
     * @param int $product_id
     * @return bool
     */
    public static function is_rocket_product($product_id) {
        return self::get_product_meta($product_id, 'enabled') === 'yes';
    }

    /**
     * Get Rocket configuration for product
     *
     * @param int $product_id
     * @return array
     */
    public static function get_product_rocket_config($product_id) {
        return array(
            'enabled' => self::get_product_meta($product_id, 'enabled') === 'yes',
            'sites_count' => absint(self::get_product_meta($product_id, 'sites_count', 1)),
            'disk_space' => absint(self::get_product_meta($product_id, 'disk_space', 0)),
            'bandwidth' => absint(self::get_product_meta($product_id, 'bandwidth', 0)),
            'visitors' => absint(self::get_product_meta($product_id, 'visitors', 0)),
            'plugins_install' => self::get_product_meta($product_id, 'plugins_install', ''),
        );
    }

    /**
     * Get FluentCart product
     *
     * @param int $product_id
     * @return WP_Post|null
     */
    public static function get_product($product_id) {
        $product = get_post($product_id);

        if ($product && $product->post_type === 'fluent-products') {
            return $product;
        }

        return null;
    }

    /**
     * Get FluentCart order
     *
     * @param int $order_id
     * @return object|null
     */
    public static function get_order($order_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'fct_orders';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $order_id
        ));
    }

    /**
     * Get order items
     *
     * @param int $order_id
     * @return array
     */
    public static function get_order_items($order_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'fct_order_items';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE order_id = %d",
            $order_id
        ));
    }

    /**
     * Check if order has Rocket products
     *
     * @param int $order_id
     * @return bool
     */
    public static function order_has_rocket_products($order_id) {
        $items = self::get_order_items($order_id);

        foreach ($items as $item) {
            if (self::is_rocket_product($item->post_id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get Rocket products from order
     *
     * @param int $order_id
     * @return array
     */
    public static function get_order_rocket_products($order_id) {
        $items = self::get_order_items($order_id);
        $rocket_products = array();

        foreach ($items as $item) {
            if (self::is_rocket_product($item->post_id)) {
                $rocket_products[] = array(
                    'item' => $item,
                    'product' => self::get_product($item->post_id),
                    'config' => self::get_product_rocket_config($item->post_id),
                );
            }
        }

        return $rocket_products;
    }

    /**
     * Calculate total sites for order item
     *
     * @param object $item Order item
     * @param int $product_id
     * @return int
     */
    public static function calculate_total_sites($item, $product_id) {
        $sites_per_product = absint(self::get_product_meta($product_id, 'sites_count', 1));
        $quantity = isset($item->quantity) ? absint($item->quantity) : 1;

        return $sites_per_product * $quantity;
    }

    /**
     * Check if subscription is active
     *
     * @param int $order_id
     * @return bool
     */
    public static function is_subscription_active($order_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'fct_subscriptions';

        $subscription = $wpdb->get_row($wpdb->prepare(
            "SELECT status FROM {$table} WHERE parent_order_id = %d LIMIT 1",
            $order_id
        ));

        if (!$subscription) {
            // No subscription, consider it as one-time purchase (always active)
            return true;
        }

        // Check if subscription status is active
        $active_statuses = array('active', 'trialing');
        return in_array($subscription->status, $active_statuses);
    }

    /**
     * Get subscription by order ID
     *
     * @param int $order_id
     * @return object|null
     */
    public static function get_subscription_by_order($order_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'fct_subscriptions';

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE parent_order_id = %d LIMIT 1",
            $order_id
        ));
    }

    /**
     * Log debug message
     *
     * @param mixed $message
     * @param string $type
     */
    public static function log($message, $type = 'info') {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $log_message = sprintf(
            '[Rocket FluentCart] [%s] %s',
            strtoupper($type),
            is_array($message) || is_object($message) ? print_r($message, true) : $message
        );

        error_log($log_message);
    }

    /**
     * Sanitize array recursively
     *
     * @param array $array
     * @return array
     */
    public static function sanitize_array($array) {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = self::sanitize_array($value);
            } else {
                $array[$key] = sanitize_text_field($value);
            }
        }

        return $array;
    }

    /**
     * Get plugin option with rfc_ prefix
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get_option($key, $default = false) {
        return get_option('rfc_' . $key, $default);
    }

    /**
     * Update plugin option with rfc_ prefix
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public static function update_option($key, $value) {
        return update_option('rfc_' . $key, $value);
    }

    /**
     * Delete plugin option with rfc_ prefix
     *
     * @param string $key
     * @return bool
     */
    public static function delete_option($key) {
        return delete_option('rfc_' . $key);
    }

    /**
     * Check if user can manage Rocket sites
     *
     * @return bool
     */
    public static function current_user_can_manage_rocket() {
        return current_user_can('manage_options');
    }

    /**
     * Get available Rocket locations (cached)
     *
     * @return array
     */
    public static function get_rocket_locations() {
        $transient_key = 'rfc_rocket_locations';
        $locations = get_transient($transient_key);

        // Clear cache if old string-based format is detected
        if ($locations !== false && !empty($locations)) {
            $first_location = reset($locations);
            if (isset($first_location['id']) && !is_numeric($first_location['id'])) {
                delete_transient($transient_key);
                $locations = false;
            }
        }

        if ($locations !== false) {
            return $locations;
        }

        // Default locations based on modified working WooCommerce plugin (IDs must be integers)
        // These IDs are verified working with the Rocket partner API
        $default_locations = array(
            array('id' => 21, 'name' => 'US - Ashburn'),
            array('id' => 22, 'name' => 'US - Phoenix'),
            array('id' => 12, 'name' => 'US - Dallas'),
            array('id' => 4, 'name' => 'GB-UKM - London'),
            array('id' => 7, 'name' => 'DE - Frankfurt'),
            array('id' => 8, 'name' => 'NL - Amsterdam'),
            array('id' => 16, 'name' => 'AU - Sydney'),
            array('id' => 20, 'name' => 'SG - Singapore'),
        );

        // Try to fetch from API
        $api_locations = Rocket_API_Sites::get_locations();

        if (!is_wp_error($api_locations) && !empty($api_locations)) {
            set_transient($transient_key, $api_locations, DAY_IN_SECONDS);
            return $api_locations;
        }

        if (is_wp_error($api_locations)) {
            self::log('Failed to fetch Rocket locations: ' . $api_locations->get_error_message(), 'error');
        }

        // Return default locations
        set_transient($transient_key, $default_locations, HOUR_IN_SECONDS);
        return $default_locations;
    }

    /**
     * Format date for display
     *
     * @param string $date
     * @param string $format
     * @return string
     */
    public static function format_date($date, $format = 'F j, Y g:i a') {
        if (empty($date) || $date === '0000-00-00 00:00:00') {
            return '-';
        }

        return date_i18n($format, strtotime($date));
    }

    /**
     * Get status label HTML
     *
     * @param string $status
     * @return string
     */
    public static function get_status_label($status) {
        $labels = array(
            'active' => '<span class="rfc-status rfc-status-active">Active</span>',
            'cancelled' => '<span class="rfc-status rfc-status-cancelled">Cancelled</span>',
            'suspended' => '<span class="rfc-status rfc-status-suspended">Suspended</span>',
            'pending' => '<span class="rfc-status rfc-status-pending">Pending</span>',
        );

        return isset($labels[$status]) ? $labels[$status] : '<span class="rfc-status">' . esc_html(ucfirst($status)) . '</span>';
    }

    /**
     * Get asset version with cache busting using filemtime
     *
     * @param string $file_path Relative path from plugin directory
     * @return string Version string (filemtime or RFC_VERSION as fallback)
     */
    public static function get_asset_version($file_path) {
        $full_path = RFC_PLUGIN_DIR . $file_path;

        if (file_exists($full_path)) {
            return filemtime($full_path);
        }

        return RFC_VERSION;
    }

    /**
     * Get hosting plans page URL
     *
     * @return string
     */
    public static function get_hosting_plans_url() {
        $url = get_option('rfc_hosting_plans_url', '');

        // If not set, try to find shop page
        if (empty($url)) {
            // Try WooCommerce shop page
            if (function_exists('wc_get_page_id')) {
                $shop_page_id = wc_get_page_id('shop');
                if ($shop_page_id > 0) {
                    $url = get_permalink($shop_page_id);
                }
            }

            // Fallback to home page + /shop/
            if (empty($url)) {
                $url = home_url('/shop/');
            }
        } else {
            // Convert relative path to full URL
            if (strpos($url, '/') === 0) {
                $url = home_url($url);
            }
        }

        return esc_url($url);
    }

    /**
     * Get My Sites page URL
     *
     * @return string
     */
    public static function get_my_sites_url() {
        $url = get_option('rfc_my_sites_url', '');

        // If not set, try FluentCart customer dashboard
        if (empty($url) && class_exists('\FluentCart\App\Services\URL')) {
            $url = \FluentCart\App\Services\URL::getCustomerDashboardUrl('my-sites');
        }

        // Fallback to current page or home
        if (empty($url)) {
            $url = is_page() ? get_permalink() : home_url('/my-account/');
        } else {
            // Convert relative path to full URL
            if (strpos($url, '/') === 0) {
                $url = home_url($url);
            }
        }

        return esc_url($url);
    }
}
