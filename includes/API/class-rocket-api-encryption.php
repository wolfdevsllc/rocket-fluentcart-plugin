<?php
/**
 * Rocket API Encryption Handler
 *
 * Handles encryption and decryption of API tokens using libsodium
 */

defined('ABSPATH') || exit;

class Rocket_API_Encryption {

    /**
     * Generate sodium crypto keypairs and nonce
     *
     * @return array
     */
    public static function generate_crypto_data() {
        // keypair1 public and secret
        $keypair1 = sodium_crypto_box_keypair();
        $keypair1_public = sodium_crypto_box_publickey($keypair1);
        $keypair1_secret = sodium_crypto_box_secretkey($keypair1);

        // keypair2 public and secret
        $keypair2 = sodium_crypto_box_keypair();
        $keypair2_public = sodium_crypto_box_publickey($keypair2);
        $keypair2_secret = sodium_crypto_box_secretkey($keypair2);

        // sodium nonce
        $nonce = random_bytes(SODIUM_CRYPTO_BOX_NONCEBYTES);

        return array(
            'keypair1_public' => $keypair1_public,
            'keypair1_secret' => $keypair1_secret,
            'keypair2_public' => $keypair2_public,
            'keypair2_secret' => $keypair2_secret,
            'nonce' => $nonce,
        );
    }

    /**
     * Encrypt token
     *
     * @param string $token
     * @return string|false
     */
    public static function encrypt_token($token) {
        try {
            $crypto_data = self::generate_crypto_data();
            $encryption_key = sodium_crypto_box_keypair_from_secretkey_and_publickey(
                $crypto_data['keypair1_secret'],
                $crypto_data['keypair2_public']
            );
            $encrypted = sodium_crypto_box($token, $crypto_data['nonce'], $encryption_key);
            $encrypted_token = base64_encode($encrypted);

            // Save encryption keys
            update_option('rfc_rocket_token_key1', base64_encode($crypto_data['keypair1_public']));
            update_option('rfc_rocket_token_key2', base64_encode($crypto_data['keypair2_secret']));
            update_option('rfc_rocket_token_nonce', base64_encode($crypto_data['nonce']));

            return $encrypted_token;
        } catch (Exception $e) {
            RFC_Helper::log('Token encryption failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Decrypt token
     *
     * @param string $encrypted_token
     * @return string|false
     */
    public static function decrypt_token($encrypted_token) {
        try {
            $encrypted_token = base64_decode($encrypted_token);

            // Get encryption keys
            $keypair1_public = base64_decode(get_option('rfc_rocket_token_key1'));
            $keypair2_secret = base64_decode(get_option('rfc_rocket_token_key2'));
            $nonce = base64_decode(get_option('rfc_rocket_token_nonce'));

            if (!$keypair1_public || !$keypair2_secret || !$nonce) {
                return false;
            }

            $decryption_key = sodium_crypto_box_keypair_from_secretkey_and_publickey(
                $keypair2_secret,
                $keypair1_public
            );

            $decrypted = sodium_crypto_box_open($encrypted_token, $nonce, $decryption_key);

            return $decrypted !== false ? $decrypted : false;
        } catch (Exception $e) {
            RFC_Helper::log('Token decryption failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Clear stored encryption keys
     */
    public static function clear_keys() {
        delete_option('rfc_rocket_token_key1');
        delete_option('rfc_rocket_token_key2');
        delete_option('rfc_rocket_token_nonce');
        delete_option('rfc_rocket_auth_token');
    }
}
