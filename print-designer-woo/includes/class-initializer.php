<?php
/**
 * Plugin Initializer
 */

class PDW_Initializer {
    /**
     * Run activation tasks
     */
    public static function activate() {
        // Create database tables if needed
        self::create_tables();
        
        // Register custom post type
        self::register_post_types();
        
        // Set default options
        self::set_default_options();
        
        // Clear permalinks
        flush_rewrite_rules();
    }

    /**
     * Run deactivation tasks
     */
    public static function deactivate() {
        // Clear any scheduled hooks
        wp_clear_scheduled_hooks('pdw_cleanup_temp_files');
        
        // Clear permalinks
        flush_rewrite_rules();
    }

    /**
     * Create required database tables
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Table for storing mockup templates
        $table_mockups = $wpdb->prefix . 'pdw_mockups';
        $sql_mockups = "CREATE TABLE IF NOT EXISTS $table_mockups (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            template_data longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Table for storing design presets
        $table_presets = $wpdb->prefix . 'pdw_presets';
        $sql_presets = "CREATE TABLE IF NOT EXISTS $table_presets (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            category varchar(50) NOT NULL,
            preset_data longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_mockups);
        dbDelta($sql_presets);
    }

    /**
     * Register custom post types
     */
    private static function register_post_types() {
        // Register saved designs post type
        register_post_type('pdw_saved_design', [
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title', 'author'],
            'labels' => [
                'name' => __('Saved Designs', 'print-designer-woo'),
                'singular_name' => __('Saved Design', 'print-designer-woo'),
            ]
        ]);

        // Register mockup templates post type
        register_post_type('pdw_mockup', [
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title', 'thumbnail'],
            'labels' => [
                'name' => __('Mockup Templates', 'print-designer-woo'),
                'singular_name' => __('Mockup Template', 'print-designer-woo'),
            ]
        ]);
    }

    /**
     * Set default plugin options
     */
    private static function set_default_options() {
        $default_options = [
            'canvas_width' => 600,
            'canvas_height' => 800,
            'max_upload_size' => 5, // MB
            'allowed_file_types' => ['jpg', 'jpeg', 'png', 'svg'],
            'enable_mockups' => true,
            'enable_clipart' => true,
            'enable_text_effects' => true,
            'auto_save_interval' => 5, // minutes
        ];

        foreach ($default_options as $key => $value) {
            if (get_option("pdw_$key") === false) {
                update_option("pdw_$key", $value);
            }
        }
    }

    /**
     * Check plugin dependencies
     */
    public static function check_dependencies() {
        $dependencies_met = true;

        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>' . 
                     __('Print Designer requires PHP 7.4 or higher.', 'print-designer-woo') . 
                     '</p></div>';
            });
            $dependencies_met = false;
        }

        // Check WordPress version
        if (version_compare($GLOBALS['wp_version'], '5.8', '<')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>' . 
                     __('Print Designer requires WordPress 5.8 or higher.', 'print-designer-woo') . 
                     '</p></div>';
            });
            $dependencies_met = false;
        }

        // Check WooCommerce
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>' . 
                     __('Print Designer requires WooCommerce to be installed and active.', 'print-designer-woo') . 
                     '</p></div>';
            });
            $dependencies_met = false;
        }

        return $dependencies_met;
    }

    /**
     * Schedule cleanup tasks
     */
    public static function schedule_tasks() {
        if (!wp_next_scheduled('pdw_cleanup_temp_files')) {
            wp_schedule_event(time(), 'daily', 'pdw_cleanup_temp_files');
        }
    }

    /**
     * Clean up temporary files
     */
    public static function cleanup_temp_files() {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/pdw-temp';

        if (is_dir($temp_dir)) {
            $files = glob($temp_dir . '/*');
            $now = time();

            foreach ($files as $file) {
                // Delete files older than 24 hours
                if ($now - filemtime($file) > 86400) {
                    @unlink($file);
                }
            }
        }
    }
}