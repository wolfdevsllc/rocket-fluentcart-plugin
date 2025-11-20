<?php
/**
 * Rocket API Sites Handler
 *
 * Handles site CRUD operations with Rocket.net API
 */

defined('ABSPATH') || exit;

class Rocket_API_Sites {

    /**
     * Create new site on Rocket
     *
     * @param array $site_data
     * @return array|WP_Error
     */
    public static function create_site($site_data) {
        $defaults = array(
            'domain' => '',
            'name' => '',
            'location' => 21, // Default to US - Ashburn (must be integer)
            'admin_username' => 'admin',
            'admin_password' => wp_generate_password(16, true, true),
            'admin_email' => '',
            'multisite' => false,
            'install_plugins' => array(),
            'quota' => null, // Disk space in MB
            'bwlimit' => null, // Bandwidth in MB
            'label' => '', // Site label for Rocket dashboard
        );

        $site_data = wp_parse_args($site_data, $defaults);

        // Validate required fields
        if (empty($site_data['domain']) || empty($site_data['name']) || empty($site_data['admin_email'])) {
            return new WP_Error('invalid_data', 'Missing required site data');
        }

        // Prepare install_plugins (must be a string, not array)
        $install_plugins = '';
        if (!empty($site_data['install_plugins'])) {
            if (is_array($site_data['install_plugins'])) {
                $install_plugins = implode(',', array_filter($site_data['install_plugins']));
            } else {
                $install_plugins = $site_data['install_plugins'];
            }
        }

        // Prepare request body - ORDER MATTERS! Match working WooCommerce plugin exactly
        $body = array(
            'domain' => sanitize_text_field($site_data['domain']),
            'multisite' => false,
            'name' => sanitize_text_field($site_data['name']),
            'location' => absint($site_data['location']),
            'admin_username' => sanitize_text_field($site_data['admin_username']),
            'admin_password' => $site_data['admin_password'],
            'admin_email' => sanitize_email($site_data['admin_email']),
            'install_plugins' => $install_plugins,
            'label' => !empty($site_data['label']) ? sanitize_text_field($site_data['label']) : sanitize_text_field($site_data['name']),
        );

        // Add quota (disk space) if provided
        if ($site_data['quota']) {
            $body['quota'] = absint($site_data['quota']);
        }

        // Add bwlimit (bandwidth) if provided
        if ($site_data['bwlimit']) {
            $body['bwlimit'] = absint($site_data['bwlimit']);
        }

        // Apply filter for customization
        $body = apply_filters('rfc_create_site_data', $body, $site_data);

        RFC_Helper::log('Creating Rocket site: ' . $site_data['name'], 'info');
        RFC_Helper::log('Request body JSON: ' . json_encode($body, JSON_PRETTY_PRINT), 'info');

        // Debug each field
        foreach ($body as $key => $value) {
            $type = gettype($value);
            $val_display = is_bool($value) ? ($value ? 'true' : 'false') : var_export($value, true);
            RFC_Helper::log("  $key: ($type) $val_display", 'info');
        }

        // Make API request
        $response = Rocket_API_Base::authenticated_request('partner/sites', 'POST', $body);

        // Log HTTP status code and raw response for debugging
        if (isset($response['http_code'])) {
            RFC_Helper::log('Rocket API HTTP Status: ' . $response['http_code'], 'info');
        }
        if (isset($response['response'])) {
            RFC_Helper::log('Rocket API Raw Response: ' . $response['response'], 'info');
        }

        if ($response['error']) {
            $error_msg = $response['message'];
            if (isset($response['http_code'])) {
                $error_msg .= ' (HTTP ' . $response['http_code'] . ')';
            }
            RFC_Helper::log('Site creation failed: ' . $error_msg, 'error');
            return new WP_Error('api_error', $error_msg);
        }

        $data = Rocket_API_Base::parse_response($response);

        if (is_wp_error($data)) {
            RFC_Helper::log('Site creation parse error: ' . $data->get_error_message(), 'error');
            return $data;
        }

        // Log the actual response for debugging
        RFC_Helper::log('Rocket API Response: ' . json_encode($data), 'info');

        if (isset($data['success']) && $data['success'] && isset($data['result'])) {
            RFC_Helper::log('Site created successfully: ' . $data['result']['id'], 'info');
            return $data['result'];
        }

        // Check alternative response format (some APIs return data directly)
        if (isset($data['id'])) {
            RFC_Helper::log('Site created successfully (alternative format): ' . $data['id'], 'info');
            return $data;
        }

        RFC_Helper::log('Site creation response missing result. Full response: ' . json_encode($data), 'error');
        return new WP_Error('invalid_response', 'Invalid API response. Check debug log for details.');
    }

    /**
     * Get site by ID
     *
     * @param string $site_id
     * @return array|WP_Error
     */
    public static function get_site($site_id) {
        $response = Rocket_API_Base::authenticated_request('sites/' . $site_id, 'GET');

        if ($response['error']) {
            return new WP_Error('api_error', $response['message']);
        }

        $data = Rocket_API_Base::parse_response($response);

        if (is_wp_error($data)) {
            return $data;
        }

        if (isset($data['result'])) {
            return $data['result'];
        }

        return new WP_Error('invalid_response', 'Invalid API response');
    }

    /**
     * List all sites
     *
     * @param array $args
     * @return array|WP_Error
     */
    public static function list_sites($args = array()) {
        $defaults = array(
            'page' => 1,
            'per_page' => 20,
        );

        $args = wp_parse_args($args, $defaults);

        $query = http_build_query($args);
        $endpoint = 'sites' . ($query ? '?' . $query : '');

        $response = Rocket_API_Base::authenticated_request($endpoint, 'GET');

        if ($response['error']) {
            return new WP_Error('api_error', $response['message']);
        }

        $data = Rocket_API_Base::parse_response($response);

        if (is_wp_error($data)) {
            return $data;
        }

        return $data;
    }

    /**
     * Delete site
     *
     * @param string $site_id
     * @return bool|WP_Error
     */
    public static function delete_site($site_id) {
        RFC_Helper::log('Deleting Rocket site: ' . $site_id, 'info');

        $response = Rocket_API_Base::authenticated_request('sites/' . $site_id, 'DELETE');

        if ($response['error']) {
            RFC_Helper::log('Site deletion failed: ' . $response['message'], 'error');
            return new WP_Error('api_error', $response['message']);
        }

        $data = Rocket_API_Base::parse_response($response);

        if (is_wp_error($data)) {
            return $data;
        }

        if (isset($data['success']) && $data['success']) {
            RFC_Helper::log('Site deleted successfully: ' . $site_id, 'info');
            return true;
        }

        return new WP_Error('deletion_failed', 'Failed to delete site');
    }

    /**
     * Generate access token for site management
     *
     * @param string $site_id
     * @param int $ttl Time to live in seconds (default: 400)
     * @return string|WP_Error
     */
    public static function generate_access_token($site_id, $ttl = 400) {
        $body = array(
            'ttl' => absint($ttl),
        );

        $response = Rocket_API_Base::authenticated_request(
            'sites/' . $site_id . '/access_token',
            'POST',
            $body
        );

        if ($response['error']) {
            return new WP_Error('api_error', $response['message']);
        }

        $data = Rocket_API_Base::parse_response($response);

        if (is_wp_error($data)) {
            return $data;
        }

        // Log response for debugging
        RFC_Helper::log('Access token API response: ' . json_encode($data), 'info');

        // Token is in result.token, not at root level
        if (isset($data['result']['token'])) {
            return $data['result']['token'];
        }

        // Also check root level for backward compatibility
        if (isset($data['token'])) {
            return $data['token'];
        }

        RFC_Helper::log('Access token not found in response: ' . json_encode($data), 'error');
        return new WP_Error('invalid_response', 'Access token not found in response');
    }

    /**
     * Get available locations
     *
     * @return array|WP_Error
     */
    public static function get_locations() {
        $response = Rocket_API_Base::authenticated_request('locations', 'GET');

        if ($response['error']) {
            RFC_Helper::log('Locations API error: ' . $response['message'], 'error');
            return new WP_Error('api_error', $response['message']);
        }

        $data = Rocket_API_Base::parse_response($response);

        if (is_wp_error($data)) {
            return $data;
        }

        // Log the locations response for debugging
        RFC_Helper::log('Locations API response: ' . json_encode($data), 'info');

        if (isset($data['locations'])) {
            return $data['locations'];
        }

        if (isset($data['result']) && is_array($data['result'])) {
            return $data['result'];
        }

        // Return default locations based on modified working WooCommerce plugin (IDs must be integers)
        // These IDs are verified working with the Rocket partner API
        return array(
            array('id' => 21, 'name' => 'US - Ashburn'),
            array('id' => 22, 'name' => 'US - Phoenix'),
            array('id' => 12, 'name' => 'US - Dallas'),
            array('id' => 4, 'name' => 'GB-UKM - London'),
            array('id' => 7, 'name' => 'DE - Frankfurt'),
            array('id' => 8, 'name' => 'NL - Amsterdam'),
            array('id' => 16, 'name' => 'AU - Sydney'),
            array('id' => 20, 'name' => 'SG - Singapore'),
        );
    }

    /**
     * Get control panel URL for site
     *
     * @param string $site_id
     * @param string $access_token
     * @return string
     */
    public static function get_control_panel_url($site_id, $access_token) {
        $base_url = RFC_Helper::get_option('rocket_control_panel_url', 'https://my.rocket.net');
        return add_query_arg(
            array(
                'site' => $site_id,
                'token' => $access_token,
            ),
            trailingslashit($base_url) . 'manage'
        );
    }

    /**
     * Update site settings
     *
     * @param string $site_id
     * @param array $settings
     * @return bool|WP_Error
     */
    public static function update_site($site_id, $settings) {
        $response = Rocket_API_Base::authenticated_request(
            'sites/' . $site_id,
            'PUT',
            $settings
        );

        if ($response['error']) {
            return new WP_Error('api_error', $response['message']);
        }

        $data = Rocket_API_Base::parse_response($response);

        if (is_wp_error($data)) {
            return $data;
        }

        if (isset($data['success']) && $data['success']) {
            return true;
        }

        return new WP_Error('update_failed', 'Failed to update site');
    }
}
