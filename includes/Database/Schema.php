<?php
/**
 * Database Schema Manager
 *
 * Handles database table creation and updates
 */

defined('ABSPATH') || exit;

class RFC_Database_Schema {

    /**
     * Create all plugin database tables
     */
    public static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $db_version = get_option('rfc_db_version');

        // Only create tables if not already created or version changed
        if ($db_version === RFC_DB_VERSION) {
            return;
        }

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Create allocations table
        self::create_allocations_table($charset_collate);

        // Create sites table
        self::create_sites_table($charset_collate);

        // Update database version
        update_option('rfc_db_version', RFC_DB_VERSION);
    }

    /**
     * Create site allocations table
     *
     * @param string $charset_collate
     */
    private static function create_allocations_table($charset_collate) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'fc_rocket_allocations';

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            customer_id bigint(20) unsigned NOT NULL,
            order_id bigint(20) unsigned NOT NULL,
            product_id bigint(20) unsigned NOT NULL,
            total_sites int(11) unsigned NOT NULL DEFAULT 0,
            used_sites int(11) unsigned NOT NULL DEFAULT 0,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY order_id (order_id),
            KEY product_id (product_id),
            KEY status (status)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * Create sites table
     *
     * @param string $charset_collate
     */
    private static function create_sites_table($charset_collate) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'fc_rocket_sites';

        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            allocation_id bigint(20) unsigned NOT NULL,
            order_id bigint(20) unsigned NOT NULL,
            customer_id bigint(20) unsigned NOT NULL,
            site_name varchar(255) NOT NULL,
            rocket_site_id varchar(255) DEFAULT NULL,
            rocket_site_data longtext DEFAULT NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            deleted_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY allocation_id (allocation_id),
            KEY order_id (order_id),
            KEY customer_id (customer_id),
            KEY rocket_site_id (rocket_site_id),
            KEY status (status),
            KEY deleted_at (deleted_at)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * Get allocations table name
     *
     * @return string
     */
    public static function get_allocations_table() {
        global $wpdb;
        return $wpdb->prefix . 'fc_rocket_allocations';
    }

    /**
     * Get sites table name
     *
     * @return string
     */
    public static function get_sites_table() {
        global $wpdb;
        return $wpdb->prefix . 'fc_rocket_sites';
    }

    /**
     * Drop all plugin tables (for uninstall)
     */
    public static function drop_tables() {
        global $wpdb;

        $allocations_table = self::get_allocations_table();
        $sites_table = self::get_sites_table();

        $wpdb->query("DROP TABLE IF EXISTS {$sites_table}");
        $wpdb->query("DROP TABLE IF EXISTS {$allocations_table}");

        delete_option('rfc_db_version');
    }

    /**
     * Get allocation by ID
     *
     * @param int $allocation_id
     * @return object|null
     */
    public static function get_allocation($allocation_id) {
        global $wpdb;
        $table = self::get_allocations_table();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $allocation_id
        ));
    }

    /**
     * Get allocations by customer ID
     *
     * @param int $customer_id
     * @param string $status
     * @return array
     */
    public static function get_customer_allocations($customer_id, $status = 'active') {
        global $wpdb;
        $table = self::get_allocations_table();

        if ($status === 'all') {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE customer_id = %d ORDER BY created_at DESC",
                $customer_id
            ));
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE customer_id = %d AND status = %s ORDER BY created_at DESC",
            $customer_id,
            $status
        ));
    }

    /**
     * Get allocations by order ID
     *
     * @param int $order_id
     * @return array
     */
    public static function get_order_allocations($order_id) {
        global $wpdb;
        $table = self::get_allocations_table();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE order_id = %d",
            $order_id
        ));
    }

    /**
     * Create new allocation
     *
     * @param array $data
     * @return int|false Allocation ID or false on failure
     */
    public static function create_allocation($data) {
        global $wpdb;
        $table = self::get_allocations_table();

        $defaults = array(
            'customer_id' => 0,
            'order_id' => 0,
            'product_id' => 0,
            'total_sites' => 0,
            'used_sites' => 0,
            'status' => 'active',
        );

        $data = wp_parse_args($data, $defaults);

        $result = $wpdb->insert(
            $table,
            array(
                'customer_id' => absint($data['customer_id']),
                'order_id' => absint($data['order_id']),
                'product_id' => absint($data['product_id']),
                'total_sites' => absint($data['total_sites']),
                'used_sites' => absint($data['used_sites']),
                'status' => sanitize_text_field($data['status']),
            ),
            array('%d', '%d', '%d', '%d', '%d', '%s')
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update allocation
     *
     * @param int $allocation_id
     * @param array $data
     * @return bool
     */
    public static function update_allocation($allocation_id, $data) {
        global $wpdb;
        $table = self::get_allocations_table();

        $allowed_fields = array('total_sites', 'used_sites', 'status');
        $update_data = array();
        $format = array();

        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                if ($field === 'status') {
                    $update_data[$field] = sanitize_text_field($data[$field]);
                    $format[] = '%s';
                } else {
                    $update_data[$field] = absint($data[$field]);
                    $format[] = '%d';
                }
            }
        }

        if (empty($update_data)) {
            return false;
        }

        return $wpdb->update(
            $table,
            $update_data,
            array('id' => $allocation_id),
            $format,
            array('%d')
        );
    }

    /**
     * Delete allocation
     *
     * @param int $allocation_id
     * @return bool
     */
    public static function delete_allocation($allocation_id) {
        global $wpdb;
        $table = self::get_allocations_table();

        // First, delete all sites in this allocation
        $sites = self::get_allocation_sites($allocation_id, true);
        foreach ($sites as $site) {
            self::hard_delete_site($site->id);
        }

        // Then delete the allocation
        return $wpdb->delete(
            $table,
            array('id' => $allocation_id),
            array('%d')
        );
    }

    /**
     * Get sites by allocation ID
     *
     * @param int $allocation_id
     * @param bool $include_deleted
     * @return array
     */
    public static function get_allocation_sites($allocation_id, $include_deleted = false) {
        global $wpdb;
        $table = self::get_sites_table();

        if ($include_deleted) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE allocation_id = %d ORDER BY created_at DESC",
                $allocation_id
            ));
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE allocation_id = %d AND deleted_at IS NULL ORDER BY created_at DESC",
            $allocation_id
        ));
    }

    /**
     * Get sites by customer ID
     *
     * @param int $customer_id
     * @param bool $include_deleted
     * @return array
     */
    public static function get_customer_sites($customer_id, $include_deleted = false) {
        global $wpdb;
        $table = self::get_sites_table();

        if ($include_deleted) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE customer_id = %d ORDER BY created_at DESC",
                $customer_id
            ));
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE customer_id = %d AND deleted_at IS NULL ORDER BY created_at DESC",
            $customer_id
        ));
    }

    /**
     * Get site by ID
     *
     * @param int $site_id
     * @return object|null
     */
    public static function get_site($site_id) {
        global $wpdb;
        $table = self::get_sites_table();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $site_id
        ));
    }

    /**
     * Get site by Rocket site ID
     *
     * @param string $rocket_site_id
     * @return object|null
     */
    public static function get_site_by_rocket_id($rocket_site_id) {
        global $wpdb;
        $table = self::get_sites_table();

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE rocket_site_id = %s",
            $rocket_site_id
        ));
    }

    /**
     * Create new site
     *
     * @param array $data
     * @return int|false Site ID or false on failure
     */
    public static function create_site($data) {
        global $wpdb;
        $table = self::get_sites_table();

        $defaults = array(
            'allocation_id' => 0,
            'order_id' => 0,
            'customer_id' => 0,
            'site_name' => '',
            'rocket_site_id' => null,
            'rocket_site_data' => null,
            'status' => 'active',
        );

        $data = wp_parse_args($data, $defaults);

        // Convert array to JSON if needed
        if (is_array($data['rocket_site_data'])) {
            $data['rocket_site_data'] = json_encode($data['rocket_site_data']);
        }

        $result = $wpdb->insert(
            $table,
            array(
                'allocation_id' => absint($data['allocation_id']),
                'order_id' => absint($data['order_id']),
                'customer_id' => absint($data['customer_id']),
                'site_name' => sanitize_text_field($data['site_name']),
                'rocket_site_id' => $data['rocket_site_id'] ? sanitize_text_field($data['rocket_site_id']) : null,
                'rocket_site_data' => $data['rocket_site_data'],
                'status' => sanitize_text_field($data['status']),
            ),
            array('%d', '%d', '%d', '%s', '%s', '%s', '%s')
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Update site
     *
     * @param int $site_id
     * @param array $data
     * @return bool
     */
    public static function update_site($site_id, $data) {
        global $wpdb;
        $table = self::get_sites_table();

        $allowed_fields = array('site_name', 'rocket_site_id', 'rocket_site_data', 'status');
        $update_data = array();
        $format = array();

        foreach ($allowed_fields as $field) {
            if (isset($data[$field])) {
                if ($field === 'rocket_site_data' && is_array($data[$field])) {
                    $update_data[$field] = json_encode($data[$field]);
                    $format[] = '%s';
                } else {
                    $update_data[$field] = sanitize_text_field($data[$field]);
                    $format[] = '%s';
                }
            }
        }

        if (empty($update_data)) {
            return false;
        }

        return $wpdb->update(
            $table,
            $update_data,
            array('id' => $site_id),
            $format,
            array('%d')
        );
    }

    /**
     * Soft delete site
     *
     * @param int $site_id
     * @return bool
     */
    public static function delete_site($site_id) {
        global $wpdb;
        $table = self::get_sites_table();

        return $wpdb->update(
            $table,
            array('deleted_at' => current_time('mysql')),
            array('id' => $site_id),
            array('%s'),
            array('%d')
        );
    }

    /**
     * Permanently delete site
     *
     * @param int $site_id
     * @return bool
     */
    public static function hard_delete_site($site_id) {
        global $wpdb;
        $table = self::get_sites_table();

        return $wpdb->delete(
            $table,
            array('id' => $site_id),
            array('%d')
        );
    }

    /**
     * Get all allocations with pagination and filters
     *
     * @param array $args
     * @return array
     */
    public static function get_all_allocations($args = array()) {
        global $wpdb;
        $table = self::get_allocations_table();

        $defaults = array(
            'per_page' => 20,
            'page' => 1,
            'status' => 'all',
            'search' => '',
            'orderby' => 'created_at',
            'order' => 'DESC',
        );

        $args = wp_parse_args($args, $defaults);

        $where = array('1=1');
        $params = array();

        if ($args['status'] !== 'all') {
            $where[] = 'status = %s';
            $params[] = $args['status'];
        }

        if (!empty($args['search'])) {
            $where[] = '(order_id = %d OR customer_id = %d OR product_id = %d)';
            $search_id = absint($args['search']);
            $params[] = $search_id;
            $params[] = $search_id;
            $params[] = $search_id;
        }

        $where_clause = implode(' AND ', $where);
        $offset = ($args['page'] - 1) * $args['per_page'];

        // Get total count
        $count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}";
        if (!empty($params)) {
            $count_sql = $wpdb->prepare($count_sql, $params);
        }
        $total = $wpdb->get_var($count_sql);

        // Get results
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        $sql = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY {$orderby} LIMIT %d OFFSET %d";

        $query_params = array_merge($params, array($args['per_page'], $offset));
        $results = $wpdb->get_results($wpdb->prepare($sql, $query_params));

        return array(
            'allocations' => $results,
            'total' => $total,
            'pages' => ceil($total / $args['per_page']),
            'current_page' => $args['page'],
        );
    }
}
