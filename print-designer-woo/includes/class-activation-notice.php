<?php
/**
 * Activation Notice Handler
 */

class PDW_Activation_Notice {
    public function __construct() {
        add_action('admin_notices', [$this, 'show_activation_notice']);
        add_action('admin_init', [$this, 'dismiss_activation_notice']);
    }

    /**
     * Show activation notice
     */
    public function show_activation_notice() {
        // Check if notice has been dismissed
        if (get_option('pdw_activation_notice_dismissed')) {
            return;
        }

        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            $this->show_woocommerce_missing_notice();
            return;
        }

        // Show setup guide notice
        ?>
        <div class="notice notice-info is-dismissible pdw-activation-notice">
            <h3><?php _e('Welcome to Print Designer for WooCommerce!', 'print-designer-woo'); ?></h3>
            <p><?php _e('Follow these steps to get started:', 'print-designer-woo'); ?></p>
            <ol>
                <li>
                    <?php 
                    printf(
                        __('Configure plugin settings in %sWooCommerce > Print Designer%s', 'print-designer-woo'),
                        '<a href="' . admin_url('admin.php?page=pdw-settings') . '">',
                        '</a>'
                    ); 
                    ?>
                </li>
                <li><?php _e('Enable the designer on your products (Product Edit > Print Designer tab)', 'print-designer-woo'); ?></li>
                <li><?php _e('Set up mockup templates for your products', 'print-designer-woo'); ?></li>
                <li><?php _e('Test the designer on your product pages', 'print-designer-woo'); ?></li>
            </ol>
            <p>
                <?php 
                printf(
                    __('Need help? Check out our %sdocumentation%s.', 'print-designer-woo'),
                    '<a href="https://github.com/yourusername/print-designer-woo" target="_blank">',
                    '</a>'
                ); 
                ?>
            </p>
            <p>
                <a href="<?php echo admin_url('admin.php?page=pdw-settings'); ?>" class="button button-primary">
                    <?php _e('Configure Settings', 'print-designer-woo'); ?>
                </a>
                <a href="<?php echo wp_nonce_url(add_query_arg('pdw-dismiss-activation-notice', '1'), 'pdw_dismiss_activation_notice'); ?>" class="button">
                    <?php _e('Dismiss Notice', 'print-designer-woo'); ?>
                </a>
            </p>
        </div>

        <style>
            .pdw-activation-notice {
                padding: 20px;
            }
            .pdw-activation-notice h3 {
                margin-top: 0;
            }
            .pdw-activation-notice ol {
                margin: 20px 0;
                padding-left: 20px;
            }
            .pdw-activation-notice li {
                margin-bottom: 10px;
            }
            .pdw-activation-notice .button {
                margin-right: 10px;
            }
        </style>
        <?php
    }

    /**
     * Show WooCommerce missing notice
     */
    private function show_woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <?php 
                printf(
                    __('Print Designer requires WooCommerce to be installed and activated. Please %sinstall WooCommerce%s first.', 'print-designer-woo'),
                    '<a href="' . admin_url('plugin-install.php?s=woocommerce&tab=search&type=term') . '">',
                    '</a>'
                ); 
                ?>
            </p>
        </div>
        <?php
    }

    /**
     * Dismiss activation notice
     */
    public function dismiss_activation_notice() {
        if (
            isset($_GET['pdw-dismiss-activation-notice']) && 
            check_admin_referer('pdw_dismiss_activation_notice')
        ) {
            update_option('pdw_activation_notice_dismissed', true);
            wp_safe_redirect(remove_query_arg('pdw-dismiss-activation-notice'));
            exit;
        }
    }

    /**
     * Reset notice dismissal on plugin update
     */
    public static function reset_notice_on_update($upgrader_object, $options) {
        if (
            $options['action'] === 'update' && 
            $options['type'] === 'plugin' && 
            isset($options['plugins'])
        ) {
            // Check if our plugin was updated
            $our_plugin = plugin_basename(PDW_PLUGIN_DIR . 'print-designer-woo.php');
            if (in_array($our_plugin, $options['plugins'])) {
                delete_option('pdw_activation_notice_dismissed');
            }
        }
    }
}

// Reset notice on plugin update
add_action('upgrader_process_complete', ['PDW_Activation_Notice', 'reset_notice_on_update'], 10, 2);