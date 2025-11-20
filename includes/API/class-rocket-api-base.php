<?php
/**
 * Rocket API Base Request Handler
 *
 * Handles low-level API requests to Rocket.net
 */

defined('ABSPATH') || exit;

class Rocket_API_Base {

    /**
     * API base URL
     *
     * @var string
     */
    private static $api_url = 'https://api.rocket.net/v1/';

    /**
     * Make API request using cURL
     *
     * @param string $endpoint
     * @param string $method
     * @param array $headers
     * @param mixed $body
     * @return array
     */
    public static function make_request($endpoint, $method = 'GET', $headers = array(), $body = null) {
        try {
            $url = self::$api_url . ltrim($endpoint, '/');

            // Initialize cURL
            $curl = curl_init();

            // Set cURL options
            $curl_options = array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_HTTPHEADER => $headers,
            );

            // Add body for POST/PUT/PATCH requests
            if (in_array($method, array('POST', 'PUT', 'PATCH')) && $body !== null) {
                $curl_options[CURLOPT_POSTFIELDS] = $body;
            }

            curl_setopt_array($curl, $curl_options);

            // Execute request
            $response = curl_exec($curl);
            $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);

            curl_close($curl);

            // Log request
            self::log_request($url, $method, $http_code, $response, $error);

            if ($error) {
                return array(
                    'error' => true,
                    'message' => $error,
                    'http_code' => $http_code,
                );
            }

            return array(
                'error' => false,
                'response' => $response,
                'http_code' => $http_code,
            );
        } catch (Exception $e) {
            RFC_Helper::log('API request exception: ' . $e->getMessage(), 'error');
            return array(
                'error' => true,
                'message' => $e->getMessage(),
            );
        }
    }

    /**
     * Make authenticated API request
     *
     * @param string $endpoint
     * @param string $method
     * @param array $body
     * @param bool $retry_on_401
     * @return array
     */
    public static function authenticated_request($endpoint, $method = 'GET', $body = null, $retry_on_401 = true) {
        // Get auth token
        $token = Rocket_API_Auth::get_token();

        if (!$token) {
            return array(
                'error' => true,
                'message' => 'No authentication token available',
            );
        }

        // Prepare headers
        $headers = array(
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        );

        // Encode body to JSON if array
        if (is_array($body)) {
            $body = json_encode($body);
        }

        // Make request
        $response = self::make_request($endpoint, $method, $headers, $body);

        // Handle 401 Unauthorized - refresh token and retry
        if (!$response['error'] && isset($response['http_code']) && $response['http_code'] == 401 && $retry_on_401) {
            RFC_Helper::log('Received 401, refreshing token and retrying', 'info');

            // Refresh token
            $refreshed = Rocket_API_Auth::refresh_token();

            if ($refreshed) {
                // Retry request once
                return self::authenticated_request($endpoint, $method, $body, false);
            }
        }

        return $response;
    }

    /**
     * Log API request
     *
     * @param string $url
     * @param string $method
     * @param int $http_code
     * @param string $response
     * @param string $error
     */
    private static function log_request($url, $method, $http_code, $response, $error) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $message = sprintf(
            'Rocket API Request: %s %s | HTTP Code: %d',
            $method,
            $url,
            $http_code
        );

        if ($error) {
            $message .= ' | Error: ' . $error;
        }

        // Don't log full response in production, just success/failure
        if ($response) {
            $decoded = json_decode($response, true);
            if (isset($decoded['success'])) {
                $message .= ' | Success: ' . ($decoded['success'] ? 'Yes' : 'No');
            }
        }

        RFC_Helper::log($message, $error ? 'error' : 'info');
    }

    /**
     * Parse API response
     *
     * @param array $response
     * @return array|WP_Error
     */
    public static function parse_response($response) {
        if ($response['error']) {
            return new WP_Error('api_error', $response['message']);
        }

        $decoded = json_decode($response['response'], true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Failed to parse API response');
        }

        return $decoded;
    }

    /**
     * Check if response is successful
     *
     * @param array $response
     * @return bool
     */
    public static function is_successful($response) {
        return !$response['error'] && isset($response['http_code']) && $response['http_code'] >= 200 && $response['http_code'] < 300;
    }
}
