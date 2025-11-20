<?php
/**
 * Product MetaBox
 *
 * Adds Rocket hosting configuration to FluentCart products
 */

defined('ABSPATH') || exit;

class RFC_Admin_ProductMetaBox {

    /**
     * Instance
     *
     * @var RFC_Admin_ProductMetaBox
     */
    private static $instance = null;

    /**
     * Get instance
     *
     * @return RFC_Admin_ProductMetaBox
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
        add_action('add_meta_boxes', array($this, 'add_metabox'));
        add_action('save_post_fluent-products', array($this, 'save_metabox'), 10, 2);
    }

    /**
     * Add metabox to FluentCart products
     */
    public function add_metabox() {
        add_meta_box(
            'rfc_product_hosting',
            __('Rocket.net Hosting Configuration', 'rocket-fluentcart'),
            array($this, 'render_metabox'),
            'fluent-products',
            'normal',
            'high'
        );
    }

    /**
     * Render metabox content
     *
     * @param WP_Post $post
     */
    public function render_metabox($post) {
        // Add nonce for security
        wp_nonce_field('rfc_product_metabox', 'rfc_product_metabox_nonce');

        // Get current values
        $enabled = get_post_meta($post->ID, '_rocket_enabled', true);
        $sites_count = get_post_meta($post->ID, '_rocket_sites_count', true);
        $disk_space = get_post_meta($post->ID, '_rocket_disk_space', true);
        $bandwidth = get_post_meta($post->ID, '_rocket_bandwidth', true);
        $visitors = get_post_meta($post->ID, '_rocket_visitors', true);
        $plugins_install = get_post_meta($post->ID, '_rocket_plugins_install', true);

        // Set defaults
        $enabled = $enabled ? $enabled : 'no';
        $sites_count = $sites_count ? $sites_count : '1';
        $disk_space = $disk_space ? $disk_space : '';
        $bandwidth = $bandwidth ? $bandwidth : '';
        $visitors = $visitors ? $visitors : '';
        $plugins_install = $plugins_install ? $plugins_install : '';

        ?>
        <div class="rfc-product-metabox">
            <!-- Enable Rocket Hosting -->
            <div class="rfc-field-group">
                <label>
                    <input type="checkbox"
                           name="rocket_enabled"
                           value="yes"
                           <?php checked($enabled, 'yes'); ?>
                           id="rocket_enabled">
                    <strong><?php _e('Enable Rocket.net Hosting for this product', 'rocket-fluentcart'); ?></strong>
                </label>
                <p class="description">
                    <?php _e('Check this box to enable Rocket.net hosting site allocation for this product.', 'rocket-fluentcart'); ?>
                </p>
            </div>

            <div id="rocket-fields-container" style="<?php echo $enabled === 'yes' ? '' : 'display:none;'; ?>">
                <hr>

                <!-- Number of Sites -->
                <div class="rfc-field-group">
                    <label for="rocket_sites_count">
                        <strong><?php _e('Number of Sites Per Product', 'rocket-fluentcart'); ?> <span class="required">*</span></strong>
                    </label>
                    <input type="number"
                           name="rocket_sites_count"
                           id="rocket_sites_count"
                           value="<?php echo esc_attr($sites_count); ?>"
                           min="1"
                           step="1"
                           required>
                    <p class="description">
                        <?php _e('Number of sites allocated per product purchase. Will be multiplied by order quantity.', 'rocket-fluentcart'); ?>
                        <br>
                        <em><?php _e('Example: If set to 5 and customer orders quantity 2, they get 10 total sites.', 'rocket-fluentcart'); ?></em>
                    </p>
                </div>

                <!-- Disk Space -->
                <div class="rfc-field-group">
                    <label for="rocket_disk_space">
                        <strong><?php _e('Disk Space (MB)', 'rocket-fluentcart'); ?></strong>
                    </label>
                    <input type="number"
                           name="rocket_disk_space"
                           id="rocket_disk_space"
                           value="<?php echo esc_attr($disk_space); ?>"
                           min="0"
                           step="1"
                           placeholder="e.g., 10240">
                    <p class="description">
                        <?php _e('Disk space quota per site in MB. Leave empty for unlimited.', 'rocket-fluentcart'); ?>
                        <br>
                        <em><?php _e('1024 MB = 1 GB, 10240 MB = 10 GB, 102400 MB = 100 GB', 'rocket-fluentcart'); ?></em>
                    </p>
                </div>

                <!-- Bandwidth -->
                <div class="rfc-field-group">
                    <label for="rocket_bandwidth">
                        <strong><?php _e('Bandwidth (MB)', 'rocket-fluentcart'); ?></strong>
                    </label>
                    <input type="number"
                           name="rocket_bandwidth"
                           id="rocket_bandwidth"
                           value="<?php echo esc_attr($bandwidth); ?>"
                           min="0"
                           step="1"
                           placeholder="e.g., 51200">
                    <p class="description">
                        <?php _e('Monthly bandwidth limit per site in MB. Leave empty for unlimited.', 'rocket-fluentcart'); ?>
                        <br>
                        <em><?php _e('51200 MB = 50 GB, 102400 MB = 100 GB', 'rocket-fluentcart'); ?></em>
                    </p>
                </div>

                <!-- Monthly Visitors -->
                <div class="rfc-field-group">
                    <label for="rocket_visitors">
                        <strong><?php _e('Monthly Visitors', 'rocket-fluentcart'); ?></strong>
                    </label>
                    <input type="number"
                           name="rocket_visitors"
                           id="rocket_visitors"
                           value="<?php echo esc_attr($visitors); ?>"
                           min="0"
                           step="1"
                           placeholder="e.g., 25000">
                    <p class="description">
                        <?php _e('Estimated monthly visitors (for display purposes only).', 'rocket-fluentcart'); ?>
                    </p>
                </div>

                <!-- Plugins to Install -->
                <div class="rfc-field-group">
                    <label for="rocket_plugins_install">
                        <strong><?php _e('WordPress Plugins to Auto-Install', 'rocket-fluentcart'); ?></strong>
                    </label>
                    <textarea name="rocket_plugins_install"
                              id="rocket_plugins_install"
                              rows="4"
                              placeholder="woocommerce, contact-form-7, yoast-seo"><?php echo esc_textarea($plugins_install); ?></textarea>
                    <p class="description">
                        <?php _e('Comma-separated list of WordPress.org plugin slugs to auto-install on site creation.', 'rocket-fluentcart'); ?>
                        <br>
                        <em><?php _e('Example: woocommerce, elementor, wordpress-seo', 'rocket-fluentcart'); ?></em>
                    </p>
                </div>

                <hr>

                <!-- Preview -->
                <div class="rfc-field-group">
                    <h4><?php _e('Configuration Preview', 'rocket-fluentcart'); ?></h4>
                    <div id="rocket-config-preview" style="background: #f0f0f1; padding: 15px; border-radius: 4px;">
                        <p><strong><?php _e('Sites per product:', 'rocket-fluentcart'); ?></strong> <span id="preview-sites"><?php echo esc_html($sites_count); ?></span></p>
                        <p><strong><?php _e('Disk Space:', 'rocket-fluentcart'); ?></strong> <span id="preview-disk"><?php echo $disk_space ? RFC_Helper::format_disk_space($disk_space) : __('Unlimited', 'rocket-fluentcart'); ?></span></p>
                        <p><strong><?php _e('Bandwidth:', 'rocket-fluentcart'); ?></strong> <span id="preview-bandwidth"><?php echo $bandwidth ? RFC_Helper::format_bandwidth($bandwidth) : __('Unlimited', 'rocket-fluentcart'); ?></span></p>
                        <p><strong><?php _e('Monthly Visitors:', 'rocket-fluentcart'); ?></strong> <span id="preview-visitors"><?php echo $visitors ? RFC_Helper::format_number($visitors) : __('N/A', 'rocket-fluentcart'); ?></span></p>
                    </div>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Toggle fields visibility
            $('#rocket_enabled').on('change', function() {
                if ($(this).is(':checked')) {
                    $('#rocket-fields-container').slideDown();
                } else {
                    $('#rocket-fields-container').slideUp();
                }
            });

            // Update preview in real-time
            function updatePreview() {
                var sites = $('#rocket_sites_count').val() || '1';
                var disk = $('#rocket_disk_space').val();
                var bandwidth = $('#rocket_bandwidth').val();
                var visitors = $('#rocket_visitors').val();

                $('#preview-sites').text(sites);

                if (disk) {
                    var diskFormatted = formatDiskSpace(parseInt(disk));
                    $('#preview-disk').text(diskFormatted);
                } else {
                    $('#preview-disk').text('<?php _e('Unlimited', 'rocket-fluentcart'); ?>');
                }

                if (bandwidth) {
                    var bwFormatted = formatDiskSpace(parseInt(bandwidth));
                    $('#preview-bandwidth').text(bwFormatted);
                } else {
                    $('#preview-bandwidth').text('<?php _e('Unlimited', 'rocket-fluentcart'); ?>');
                }

                if (visitors) {
                    $('#preview-visitors').text(parseInt(visitors).toLocaleString());
                } else {
                    $('#preview-visitors').text('<?php _e('N/A', 'rocket-fluentcart'); ?>');
                }
            }

            function formatDiskSpace(mb) {
                if (mb < 1024) {
                    return mb + ' MB';
                } else if (mb < 1048576) {
                    return (mb / 1024).toFixed(2) + ' GB';
                } else {
                    return (mb / 1048576).toFixed(2) + ' TB';
                }
            }

            $('#rocket_sites_count, #rocket_disk_space, #rocket_bandwidth, #rocket_visitors').on('input', updatePreview);
        });
        </script>
        <?php
    }

    /**
     * Save metabox data
     *
     * @param int $post_id
     * @param WP_Post $post
     */
    public function save_metabox($post_id, $post) {
        // Check nonce
        if (!isset($_POST['rfc_product_metabox_nonce']) ||
            !wp_verify_nonce($_POST['rfc_product_metabox_nonce'], 'rfc_product_metabox')) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save enabled status
        $enabled = isset($_POST['rocket_enabled']) ? 'yes' : 'no';
        update_post_meta($post_id, '_rocket_enabled', $enabled);

        // If not enabled, clear all other fields
        if ($enabled === 'no') {
            delete_post_meta($post_id, '_rocket_sites_count');
            delete_post_meta($post_id, '_rocket_disk_space');
            delete_post_meta($post_id, '_rocket_bandwidth');
            delete_post_meta($post_id, '_rocket_visitors');
            delete_post_meta($post_id, '_rocket_plugins_install');
            return;
        }

        // Save sites count (required)
        $sites_count = isset($_POST['rocket_sites_count']) ? absint($_POST['rocket_sites_count']) : 1;
        if ($sites_count < 1) {
            $sites_count = 1;
        }
        update_post_meta($post_id, '_rocket_sites_count', $sites_count);

        // Save disk space
        $disk_space = isset($_POST['rocket_disk_space']) ? absint($_POST['rocket_disk_space']) : 0;
        update_post_meta($post_id, '_rocket_disk_space', $disk_space);

        // Save bandwidth
        $bandwidth = isset($_POST['rocket_bandwidth']) ? absint($_POST['rocket_bandwidth']) : 0;
        update_post_meta($post_id, '_rocket_bandwidth', $bandwidth);

        // Save visitors
        $visitors = isset($_POST['rocket_visitors']) ? absint($_POST['rocket_visitors']) : 0;
        update_post_meta($post_id, '_rocket_visitors', $visitors);

        // Save plugins to install
        $plugins_install = isset($_POST['rocket_plugins_install']) ? sanitize_textarea_field($_POST['rocket_plugins_install']) : '';
        update_post_meta($post_id, '_rocket_plugins_install', $plugins_install);

        // Log the save
        RFC_Helper::log(sprintf(
            'Rocket configuration saved for product #%d: %d sites, %s disk, %s bandwidth',
            $post_id,
            $sites_count,
            $disk_space ? RFC_Helper::format_disk_space($disk_space) : 'unlimited',
            $bandwidth ? RFC_Helper::format_bandwidth($bandwidth) : 'unlimited'
        ), 'info');
    }
}
