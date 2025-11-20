<?php
/**
 * Plugin Name: Rocket.net Reseller with FluentCart
 * Plugin URI: https://wolfdevs.com
 * Description: Sell Rocket.net hosting through FluentCart with on-demand site creation and management
 * Version: 0.9
 * Author: WolfDevs
 * Author URI: https://wolfdevs.com
 * Text Domain: rocket-fluentcart
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined('ABSPATH') || exit;

// Define plugin constants
define('RFC_VERSION', '0.9');
define('RFC_PLUGIN_FILE', __FILE__);
define('RFC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('RFC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('RFC_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('RFC_DB_VERSION', '1.0');

/**
 * Main Rocket FluentCart Plugin Class
 */
final class Rocket_FluentCart_Plugin {

    /**
     * Plugin instance
     *
     * @var Rocket_FluentCart_Plugin
     */
    private static $instance = null;

    /**
     * Get plugin instance
     *
     * @return Rocket_FluentCart_Plugin
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
        $this->init_hooks();
        $this->includes();
        $this->init_components();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        register_activation_hook(RFC_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(RFC_PLUGIN_FILE, array($this, 'deactivate'));

        add_action('plugins_loaded', array($this, 'check_dependencies'), 10);
        add_action('init', array($this, 'load_textdomain'));
    }

    /**
     * Include required files
     */
    private function includes() {
        // Helper functions
        require_once RFC_PLUGIN_DIR . 'includes/Helpers/Helper.php';

        // Database
        require_once RFC_PLUGIN_DIR . 'includes/Database/Schema.php';

        // API
        require_once RFC_PLUGIN_DIR . 'includes/API/class-rocket-api-base.php';
        require_once RFC_PLUGIN_DIR . 'includes/API/class-rocket-api-auth.php';
        require_once RFC_PLUGIN_DIR . 'includes/API/class-rocket-api-sites.php';
        require_once RFC_PLUGIN_DIR . 'includes/API/class-rocket-api-encryption.php';
        require_once RFC_PLUGIN_DIR . 'includes/API/class-rocket-api-request.php';

        // Admin
        if (is_admin()) {
            require_once RFC_PLUGIN_DIR . 'includes/Admin/Settings.php';
            require_once RFC_PLUGIN_DIR . 'includes/Admin/ProductMetaBox.php';
            require_once RFC_PLUGIN_DIR . 'includes/Admin/SiteAllocations.php';
        }

        // Frontend
        require_once RFC_PLUGIN_DIR . 'includes/Frontend/CustomerDashboard.php';
        require_once RFC_PLUGIN_DIR . 'includes/Frontend/SiteCreation.php';
        require_once RFC_PLUGIN_DIR . 'includes/Frontend/ManageSite.php';

        // Integrations
        require_once RFC_PLUGIN_DIR . 'includes/Integrations/FluentCart.php';
    }

    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Admin components
        if (is_admin()) {
            RFC_Admin_Settings::instance();
            RFC_Admin_ProductMetaBox::instance();
            RFC_Admin_SiteAllocations::instance();
        }

        // Frontend components
        RFC_Frontend_CustomerDashboard::instance();
        RFC_Frontend_SiteCreation::instance();

        // FluentCart integration
        RFC_Integration_FluentCart::instance();
    }

    /**
     * Check plugin dependencies
     */
    public function check_dependencies() {
        // Check if FluentCart is active
        if (!defined('FLUENTCART_PLUGIN_PATH')) {
            add_action('admin_notices', array($this, 'fluentcart_missing_notice'));
            return false;
        }

        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            add_action('admin_notices', array($this, 'php_version_notice'));
            return false;
        }

        // Check for libsodium (required for encryption)
        if (!function_exists('sodium_crypto_box_keypair')) {
            add_action('admin_notices', array($this, 'libsodium_missing_notice'));
            return false;
        }

        return true;
    }

    /**
     * FluentCart missing notice
     */
    public function fluentcart_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php _e('Rocket.net Reseller with FluentCart', 'rocket-fluentcart'); ?></strong>
                <?php _e('requires FluentCart to be installed and activated.', 'rocket-fluentcart'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * PHP version notice
     */
    public function php_version_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php _e('Rocket.net Reseller with FluentCart', 'rocket-fluentcart'); ?></strong>
                <?php printf(__('requires PHP version 7.4 or higher. You are running PHP %s.', 'rocket-fluentcart'), PHP_VERSION); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Libsodium missing notice
     */
    public function libsodium_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong><?php _e('Rocket.net Reseller with FluentCart', 'rocket-fluentcart'); ?></strong>
                <?php _e('requires the Sodium PHP extension for secure token encryption. Please contact your hosting provider.', 'rocket-fluentcart'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Check dependencies before activation
        if (!$this->check_dependencies()) {
            deactivate_plugins(RFC_PLUGIN_BASENAME);
            wp_die(__('This plugin requires FluentCart and PHP 7.4+ to be activated.', 'rocket-fluentcart'));
        }

        // Create database tables
        RFC_Database_Schema::create_tables();

        // Set default options
        $this->set_default_options();

        // Store plugin version
        update_option('rfc_version', RFC_VERSION);
        update_option('rfc_db_version', RFC_DB_VERSION);

        // Flush rewrite rules to register manage-site endpoint
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up transients
        delete_transient('rfc_rocket_api_token');

        // Clear any scheduled events
        wp_clear_scheduled_hook('rfc_token_refresh');
    }

    /**
     * Set default plugin options
     */
    private function set_default_options() {
        $defaults = array(
            'rocket_api_url' => 'https://api.rocket.net',
            'enable_control_panel' => 'yes',
            'primary_color' => '#0073aa',
            'secondary_color' => '#23282d',
            'accent_color' => '#00a0d2',
            'background_color' => '#f1f1f1',
            'text_color' => '#23282d',
            'link_color' => '#0073aa',
            'button_color' => '#0073aa',
            'button_text_color' => '#ffffff',
        );

        foreach ($defaults as $key => $value) {
            if (get_option('rfc_' . $key) === false) {
                update_option('rfc_' . $key, $value);
            }
        }
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'rocket-fluentcart',
            false,
            dirname(RFC_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Get plugin option
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get_option($key, $default = false) {
        return get_option('rfc_' . $key, $default);
    }

    /**
     * Update plugin option
     *
     * @param string $key
     * @param mixed $value
     * @return bool
     */
    public static function update_option($key, $value) {
        return update_option('rfc_' . $key, $value);
    }
}

/**
 * Initialize the plugin
 */
function rocket_fluentcart() {
    return Rocket_FluentCart_Plugin::instance();
}

// Start the plugin
rocket_fluentcart();
