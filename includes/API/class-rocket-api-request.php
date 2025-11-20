<?php
/**
 * Rocket API Request Wrapper
 *
 * Convenient wrapper for common API operations
 */

defined('ABSPATH') || exit;

class Rocket_API_Request {

    /**
     * GET request
     *
     * @param string $endpoint
     * @param array $params
     * @return array|WP_Error
     */
    public function get($endpoint, $params = array()) {
        if (!empty($params)) {
            $endpoint .= '?' . http_build_query($params);
        }

        $response = Rocket_API_Base::authenticated_request($endpoint, 'GET');

        if ($response['error']) {
            return new WP_Error('api_error', $response['message']);
        }

        return Rocket_API_Base::parse_response($response);
    }

    /**
     * POST request
     *
     * @param string $endpoint
     * @param array $body
     * @return array|WP_Error
     */
    public function post($endpoint, $body = array()) {
        $response = Rocket_API_Base::authenticated_request($endpoint, 'POST', $body);

        if ($response['error']) {
            return new WP_Error('api_error', $response['message']);
        }

        return Rocket_API_Base::parse_response($response);
    }

    /**
     * PUT request
     *
     * @param string $endpoint
     * @param array $body
     * @return array|WP_Error
     */
    public function put($endpoint, $body = array()) {
        $response = Rocket_API_Base::authenticated_request($endpoint, 'PUT', $body);

        if ($response['error']) {
            return new WP_Error('api_error', $response['message']);
        }

        return Rocket_API_Base::parse_response($response);
    }

    /**
     * DELETE request
     *
     * @param string $endpoint
     * @return array|WP_Error
     */
    public function delete($endpoint) {
        $response = Rocket_API_Base::authenticated_request($endpoint, 'DELETE');

        if ($response['error']) {
            return new WP_Error('api_error', $response['message']);
        }

        return Rocket_API_Base::parse_response($response);
    }

    /**
     * Test connection
     *
     * @return bool
     */
    public function test_connection() {
        $result = Rocket_API_Auth::test_connection();
        return $result['success'];
    }
}
