<?php
/**
 * Helper Functions
 */

class PDW_Helper {
    /**
     * Generate a unique filename
     */
    public static function generate_unique_filename($extension) {
        return uniqid('pdw-', true) . '.' . $extension;
    }

    /**
     * Get file extension from mime type
     */
    public static function get_extension_from_mime($mime_type) {
        $mime_map = [
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/svg+xml' => 'svg',
        ];

        return isset($mime_map[$mime_type]) ? $mime_map[$mime_type] : '';
    }

    /**
     * Validate file type
     */
    public static function validate_file_type($file_type) {
        $allowed_types = PDW_Config::get_upload_settings()['allowed_types'];
        $extension = self::get_extension_from_mime($file_type);
        
        return in_array($extension, $allowed_types);
    }

    /**
     * Format bytes to human readable size
     */
    public static function format_bytes($bytes, $precision = 2) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Clean temporary files
     */
    public static function clean_temp_files($hours = 24) {
        $temp_dir = PDW_Config::get_temp_dir();
        if (!is_dir($temp_dir)) {
            return;
        }

        $files = glob($temp_dir . '/*');
        $now = time();

        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) > $hours * 3600) {
                    @unlink($file);
                }
            }
        }
    }

    /**
     * Ensure temp directory exists
     */
    public static function ensure_temp_directory() {
        $temp_dir = PDW_Config::get_temp_dir();
        
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        // Create .htaccess to protect temp directory
        $htaccess = $temp_dir . '/.htaccess';
        if (!file_exists($htaccess)) {
            $content = "Options -Indexes\n";
            $content .= "<FilesMatch '\.(php|php5|php7|phtml|pl|py|jsp|asp|htm|shtml|sh|cgi)$'>\n";
            $content .= "Order Deny,Allow\n";
            $content .= "Deny from all\n";
            $content .= "</FilesMatch>\n";
            
            file_put_contents($htaccess, $content);
        }

        // Create index.php to prevent directory listing
        $index = $temp_dir . '/index.php';
        if (!file_exists($index)) {
            file_put_contents($index, '<?php // Silence is golden');
        }
    }

    /**
     * Get product mockup template
     */
    public static function get_product_mockup_template($product_id, $variation_id = 0) {
        $template_id = $variation_id ? 
            get_post_meta($variation_id, '_pdw_mockup_template', true) :
            get_post_meta($product_id, '_pdw_mockup_template', true);

        if (!$template_id) {
            return false;
        }

        $template = get_post($template_id);
        if (!$template || $template->post_type !== 'pdw_mockup') {
            return false;
        }

        return [
            'id' => $template->ID,
            'title' => $template->post_title,
            'image' => get_post_thumbnail_id($template->ID),
            'placement' => get_post_meta($template->ID, '_pdw_placement_data', true)
        ];
    }

    /**
     * Get available fonts for designer
     */
    public static function get_available_fonts() {
        $fonts = PDW_Config::get_fonts();
        $formatted = [];

        foreach ($fonts as $key => $font) {
            $formatted[] = [
                'id' => $key,
                'name' => $font['name'],
                'url' => $font['url']
            ];
        }

        return $formatted;
    }

    /**
     * Get toolbar tools
     */
    public static function get_toolbar_tools() {
        $tools = PDW_Config::get_tools();
        $formatted = [];

        foreach ($tools as $key => $tool) {
            $formatted[] = [
                'id' => $key,
                'icon' => $tool['icon'],
                'label' => __($tool['label'], 'print-designer-woo'),
                'order' => $tool['order']
            ];
        }

        return $formatted;
    }

    /**
     * Check if product has designer enabled
     */
    public static function is_designer_enabled($product_id) {
        return get_post_meta($product_id, '_enable_print_designer', true) === 'yes';
    }

    /**
     * Get design price adjustment
     */
    public static function get_design_price_adjustment($design_data) {
        $base_price = 0;
        
        // Decode design data
        $design = json_decode($design_data, true);
        if (!$design) {
            return $base_price;
        }

        // Add price for each object based on type
        if (isset($design['objects']) && is_array($design['objects'])) {
            foreach ($design['objects'] as $object) {
                switch ($object['type']) {
                    case 'text':
                        $base_price += 1.00; // $1 per text element
                        break;
                    case 'image':
                        $base_price += 2.00; // $2 per image
                        break;
                    case 'clipart':
                        $base_price += 0.50; // $0.50 per clipart
                        break;
                }
            }
        }

        return apply_filters('pdw_design_price_adjustment', $base_price, $design);
    }

    /**
     * Log debug message
     */
    public static function log($message, $type = 'debug') {
        if (!WP_DEBUG) {
            return;
        }

        $log_dir = WP_CONTENT_DIR . '/pdw-logs';
        if (!file_exists($log_dir)) {
            wp_mkdir_p($log_dir);
        }

        $log_file = $log_dir . '/pdw-' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        
        $log_message = sprintf(
            "[%s] [%s] %s\n",
            $timestamp,
            strtoupper($type),
            is_array($message) || is_object($message) ? print_r($message, true) : $message
        );

        error_log($log_message, 3, $log_file);
    }

    /**
     * Check system requirements
     */
    public static function check_system_requirements() {
        $requirements = [
            'php' => [
                'required' => '7.4',
                'current' => PHP_VERSION,
                'status' => version_compare(PHP_VERSION, '7.4', '>=')
            ],
            'wordpress' => [
                'required' => '5.8',
                'current' => get_bloginfo('version'),
                'status' => version_compare(get_bloginfo('version'), '5.8', '>=')
            ],
            'woocommerce' => [
                'required' => '5.0',
                'current' => WC()->version,
                'status' => version_compare(WC()->version, '5.0', '>=')
            ],
            'gd' => [
                'required' => true,
                'current' => extension_loaded('gd'),
                'status' => extension_loaded('gd')
            ],
            'memory_limit' => [
                'required' => '64M',
                'current' => ini_get('memory_limit'),
                'status' => self::check_memory_limit('64M')
            ]
        ];

        return $requirements;
    }

    /**
     * Check memory limit
     */
    private static function check_memory_limit($required) {
        $memory_limit = ini_get('memory_limit');
        
        // Convert memory limit to bytes
        $memory_limit = self::convert_to_bytes($memory_limit);
        $required = self::convert_to_bytes($required);
        
        return $memory_limit >= $required;
    }

    /**
     * Convert PHP memory value to bytes
     */
    private static function convert_to_bytes($value) {
        $value = trim($value);
        $last = strtolower($value[strlen($value)-1]);
        $value = (int)$value;
        
        switch($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }
}