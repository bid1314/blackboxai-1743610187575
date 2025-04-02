<?php
/**
 * Plugin Updater and Migration Handler
 */

class PDW_Updater {
    /**
     * Current database version
     */
    private $current_version;

    /**
     * Latest database version
     */
    const LATEST_VERSION = '1.0.0';

    public function __construct() {
        $this->current_version = get_option('pdw_db_version', '0.0.0');
        
        // Run migrations if needed
        if (version_compare($this->current_version, self::LATEST_VERSION, '<')) {
            $this->run_migrations();
        }
    }

    /**
     * Run database migrations
     */
    private function run_migrations() {
        // Get all migration methods
        $migrations = $this->get_migration_methods();
        
        // Sort migrations by version
        uksort($migrations, 'version_compare');
        
        // Run each migration that's newer than current version
        foreach ($migrations as $version => $method) {
            if (version_compare($this->current_version, $version, '<')) {
                PDW_Helper::log("Running migration to version {$version}");
                
                try {
                    $this->$method();
                    update_option('pdw_db_version', $version);
                    $this->current_version = $version;
                } catch (Exception $e) {
                    PDW_Helper::log("Migration to version {$version} failed: " . $e->getMessage(), 'error');
                    break;
                }
            }
        }
    }

    /**
     * Get available migration methods
     */
    private function get_migration_methods() {
        return [
            '1.0.0' => 'migrate_to_1_0_0'
        ];
    }

    /**
     * Migration to version 1.0.0
     */
    private function migrate_to_1_0_0() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Create mockups table
        $table_mockups = $wpdb->prefix . 'pdw_mockups';
        $sql_mockups = "CREATE TABLE IF NOT EXISTS $table_mockups (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            template_data longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // Create presets table
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

        // Create required directories
        $this->create_required_directories();

        // Set default options
        $this->set_default_options();
    }

    /**
     * Create required directories
     */
    private function create_required_directories() {
        // Create temp directory
        PDW_Helper::ensure_temp_directory();

        // Create logs directory
        $log_dir = WP_CONTENT_DIR . '/pdw-logs';
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
            
            // Protect logs directory
            file_put_contents($log_dir . '/.htaccess', "Deny from all");
            file_put_contents($log_dir . '/index.php', '<?php // Silence is golden');
        }
    }

    /**
     * Set default options
     */
    private function set_default_options() {
        $default_options = [
            'pdw_canvas_width' => 600,
            'pdw_canvas_height' => 800,
            'pdw_max_upload_size' => 5,
            'pdw_allowed_file_types' => ['jpg', 'jpeg', 'png', 'svg'],
            'pdw_enable_mockups' => true,
            'pdw_enable_clipart' => true,
            'pdw_enable_text_effects' => true,
            'pdw_auto_save_interval' => 5,
        ];

        foreach ($default_options as $key => $value) {
            if (get_option($key) === false) {
                update_option($key, $value);
            }
        }
    }

    /**
     * Check if update is needed
     */
    public static function needs_update() {
        $current_version = get_option('pdw_db_version', '0.0.0');
        return version_compare($current_version, self::LATEST_VERSION, '<');
    }

    /**
     * Handle plugin updates
     */
    public static function handle_update($upgrader_object, $options) {
        if (
            $options['action'] === 'update' && 
            $options['type'] === 'plugin' && 
            isset($options['plugins'])
        ) {
            // Check if our plugin was updated
            $our_plugin = plugin_basename(PDW_PLUGIN_DIR . 'print-designer-woo.php');
            if (in_array($our_plugin, $options['plugins'])) {
                // Run migrations
                new self();

                // Clear any cached data
                wp_cache_flush();

                // Reset activation notice
                delete_option('pdw_activation_notice_dismissed');

                // Log update
                PDW_Helper::log('Plugin updated to version ' . PDW_VERSION);
            }
        }
    }

    /**
     * Clean up old data
     */
    public static function cleanup() {
        global $wpdb;

        // Delete temporary files older than 24 hours
        PDW_Helper::clean_temp_files(24);

        // Delete old logs (keep last 30 days)
        $log_dir = WP_CONTENT_DIR . '/pdw-logs';
        if (is_dir($log_dir)) {
            $files = glob($log_dir . '/*.log');
            $now = time();
            
            foreach ($files as $file) {
                if ($now - filemtime($file) > 30 * 24 * 3600) {
                    @unlink($file);
                }
            }
        }

        // Delete orphaned design data
        $wpdb->query("
            DELETE FROM {$wpdb->postmeta} 
            WHERE meta_key LIKE '_pdw_%' 
            AND post_id NOT IN (
                SELECT ID FROM {$wpdb->posts}
            )
        ");
    }

    /**
     * Check system requirements
     */
    public static function check_requirements() {
        $requirements = PDW_Helper::check_system_requirements();
        $all_met = true;
        $messages = [];

        foreach ($requirements as $requirement => $data) {
            if (!$data['status']) {
                $all_met = false;
                $messages[] = sprintf(
                    __('%s requirement not met. Required: %s, Current: %s', 'print-designer-woo'),
                    ucfirst($requirement),
                    $data['required'],
                    $data['current']
                );
            }
        }

        return [
            'met' => $all_met,
            'messages' => $messages
        ];
    }

    /**
     * Get plugin info
     */
    public static function get_plugin_info() {
        return [
            'version' => PDW_VERSION,
            'db_version' => get_option('pdw_db_version', '0.0.0'),
            'wp_version' => get_bloginfo('version'),
            'wc_version' => WC()->version,
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_upload_size' => size_format(wp_max_upload_size()),
            'debug_mode' => WP_DEBUG ? 'Enabled' : 'Disabled',
            'environment' => wp_get_environment_type()
        ];
    }
}

// Handle plugin updates
add_action('upgrader_process_complete', ['PDW_Updater', 'handle_update'], 10, 2);

// Schedule cleanup
if (!wp_next_scheduled('pdw_cleanup')) {
    wp_schedule_event(time(), 'daily', 'pdw_cleanup');
}
add_action('pdw_cleanup', ['PDW_Updater', 'cleanup']);