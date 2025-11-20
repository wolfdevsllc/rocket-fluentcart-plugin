<?php
/**
 * Rocket API Authentication Handler
 *
 * Manages authentication with Rocket.net API
 */

defined('ABSPATH') || exit;

class Rocket_API_Auth {

    /**
     * Login to Rocket API and get token
     *
     * @return string|false
     */
    public static function login() {
        $email = RFC_Helper::get_option('rocket_email');
        $password = RFC_Helper::get_option('rocket_password');

        if (!$email || !$password) {
            RFC_Helper::log('Rocket API credentials not configured', 'error');
            return false;
        }

        $body = array(
            'username' => $email,
            'password' => $password,
        );

        $headers = array(
            'Accept: application/json',
            'Content-Type: application/json',
        );

        $response = Rocket_API_Base::make_request('login', 'POST', $headers, json_encode($body));

        if ($response['error']) {
            RFC_Helper::log('Rocket login failed: ' . $response['message'], 'error');
            return false;
        }

        $data = Rocket_API_Base::parse_response($response);

        if (is_wp_error($data)) {
            RFC_Helper::log('Rocket login parse error: ' . $data->get_error_message(), 'error');
            return false;
        }

        if (isset($data['token']) && $data['token']) {
            return $data['token'];
        }

        RFC_Helper::log('Rocket login response missing token', 'error');
        return false;
    }

    /**
     * Refresh auth token
     *
     * @return bool
     */
    public static function refresh_token() {
        RFC_Helper::log('Refreshing Rocket API token', 'info');

        $token = self::login();

        if (!$token) {
            // Clear stored token on failure
            self::clear_token();
            return false;
        }

        // Encrypt and store token
        $encrypted = Rocket_API_Encryption::encrypt_token($token);

        if (!$encrypted) {
            RFC_Helper::log('Failed to encrypt token', 'error');
            return false;
        }

        update_option('rfc_rocket_auth_token', $encrypted);

        RFC_Helper::log('Rocket API token refreshed successfully', 'info');

        return true;
    }

    /**
     * Get current auth token (decrypted)
     *
     * @return string|false
     */
    public static function get_token() {
        $encrypted_token = get_option('rfc_rocket_auth_token');

        if (!$encrypted_token) {
            // No token stored, try to login
            $refreshed = self::refresh_token();
            if ($refreshed) {
                $encrypted_token = get_option('rfc_rocket_auth_token');
            } else {
                return false;
            }
        }

        // Decrypt token
        $token = Rocket_API_Encryption::decrypt_token($encrypted_token);

        if (!$token) {
            RFC_Helper::log('Failed to decrypt token, refreshing', 'warning');
            // Token decryption failed, refresh
            $refreshed = self::refresh_token();
            if ($refreshed) {
                $encrypted_token = get_option('rfc_rocket_auth_token');
                $token = Rocket_API_Encryption::decrypt_token($encrypted_token);
            }
        }

        return $token;
    }

    /**
     * Test API connection
     *
     * @return array
     */
    public static function test_connection() {
        $token = self::get_token();

        if (!$token) {
            return array(
                'success' => false,
                'message' => 'Failed to authenticate with Rocket.net',
            );
        }

        // Try to fetch sites list to verify token works
        $response = Rocket_API_Base::authenticated_request('sites', 'GET', null, false);

        if ($response['error']) {
            return array(
                'success' => false,
                'message' => 'Connection failed: ' . $response['message'],
            );
        }

        $data = Rocket_API_Base::parse_response($response);

        if (is_wp_error($data)) {
            return array(
                'success' => false,
                'message' => 'API error: ' . $data->get_error_message(),
            );
        }

        return array(
            'success' => true,
            'message' => 'Successfully connected to Rocket.net',
            'data' => $data,
        );
    }

    /**
     * Clear stored token
     */
    public static function clear_token() {
        delete_option('rfc_rocket_auth_token');
        Rocket_API_Encryption::clear_keys();
        RFC_Helper::log('Rocket API token cleared', 'info');
    }

    /**
     * Check if credentials are configured
     *
     * @return bool
     */
    public static function has_credentials() {
        $email = RFC_Helper::get_option('rocket_email');
        $password = RFC_Helper::get_option('rocket_password');

        return !empty($email) && !empty($password);
    }
}
