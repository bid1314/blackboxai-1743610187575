<?php
/**
 * Plugin Configuration
 */

class PDW_Config {
    /**
     * Default canvas settings
     */
    const DEFAULT_CANVAS = [
        'width' => 600,
        'height' => 800,
        'background' => '#ffffff',
    ];

    /**
     * Upload settings
     */
    const UPLOAD_SETTINGS = [
        'max_size' => 5, // MB
        'allowed_types' => ['jpg', 'jpeg', 'png', 'svg'],
        'temp_dir' => 'pdw-temp',
    ];

    /**
     * Image processing settings
     */
    const IMAGE_SETTINGS = [
        'max_dimension' => 2000,
        'quality' => 90,
        'mockup_format' => 'png',
    ];

    /**
     * Default text settings
     */
    const TEXT_SETTINGS = [
        'default_font' => 'OpenSans',
        'min_size' => 8,
        'max_size' => 200,
        'default_color' => '#000000',
    ];

    /**
     * Available fonts
     * Add Google Fonts here to make them available in the designer
     */
    const AVAILABLE_FONTS = [
        'OpenSans' => [
            'name' => 'Open Sans',
            'url' => 'https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap',
        ],
        'Roboto' => [
            'name' => 'Roboto',
            'url' => 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap',
        ],
        'Montserrat' => [
            'name' => 'Montserrat',
            'url' => 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap',
        ],
        'Lato' => [
            'name' => 'Lato',
            'url' => 'https://fonts.googleapis.com/css2?family=Lato:wght@400;700&display=swap',
        ],
    ];

    /**
     * Default toolbar tools
     */
    const DEFAULT_TOOLS = [
        'text' => [
            'icon' => 'fas fa-text',
            'label' => 'Add Text',
            'order' => 10,
        ],
        'upload' => [
            'icon' => 'fas fa-upload',
            'label' => 'Upload Image',
            'order' => 20,
        ],
        'clipart' => [
            'icon' => 'fas fa-images',
            'label' => 'Add Clipart',
            'order' => 30,
        ],
        'save' => [
            'icon' => 'fas fa-save',
            'label' => 'Save Design',
            'order' => 40,
        ],
        'load' => [
            'icon' => 'fas fa-folder-open',
            'label' => 'Load Design',
            'order' => 50,
        ],
    ];

    /**
     * Get all plugin settings
     */
    public static function get_settings() {
        return [
            'canvas' => self::get_canvas_settings(),
            'upload' => self::get_upload_settings(),
            'image' => self::get_image_settings(),
            'text' => self::get_text_settings(),
            'fonts' => self::get_fonts(),
            'tools' => self::get_tools(),
        ];
    }

    /**
     * Get canvas settings
     */
    public static function get_canvas_settings() {
        return [
            'width' => get_option('pdw_canvas_width', self::DEFAULT_CANVAS['width']),
            'height' => get_option('pdw_canvas_height', self::DEFAULT_CANVAS['height']),
            'background' => get_option('pdw_canvas_background', self::DEFAULT_CANVAS['background']),
        ];
    }

    /**
     * Get upload settings
     */
    public static function get_upload_settings() {
        return [
            'max_size' => get_option('pdw_max_upload_size', self::UPLOAD_SETTINGS['max_size']),
            'allowed_types' => get_option('pdw_allowed_file_types', self::UPLOAD_SETTINGS['allowed_types']),
            'temp_dir' => self::UPLOAD_SETTINGS['temp_dir'],
        ];
    }

    /**
     * Get image settings
     */
    public static function get_image_settings() {
        return [
            'max_dimension' => self::IMAGE_SETTINGS['max_dimension'],
            'quality' => self::IMAGE_SETTINGS['quality'],
            'mockup_format' => self::IMAGE_SETTINGS['mockup_format'],
        ];
    }

    /**
     * Get text settings
     */
    public static function get_text_settings() {
        return [
            'default_font' => get_option('pdw_default_font', self::TEXT_SETTINGS['default_font']),
            'min_size' => self::TEXT_SETTINGS['min_size'],
            'max_size' => self::TEXT_SETTINGS['max_size'],
            'default_color' => self::TEXT_SETTINGS['default_color'],
        ];
    }

    /**
     * Get available fonts
     */
    public static function get_fonts() {
        $custom_fonts = apply_filters('pdw_custom_fonts', []);
        return array_merge(self::AVAILABLE_FONTS, $custom_fonts);
    }

    /**
     * Get toolbar tools
     */
    public static function get_tools() {
        $custom_tools = apply_filters('pdw_custom_tools', []);
        $tools = array_merge(self::DEFAULT_TOOLS, $custom_tools);
        
        // Sort tools by order
        uasort($tools, function($a, $b) {
            return $a['order'] - $b['order'];
        });
        
        return $tools;
    }

    /**
     * Get temp directory path
     */
    public static function get_temp_dir() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/' . self::UPLOAD_SETTINGS['temp_dir'];
    }

    /**
     * Get temp directory URL
     */
    public static function get_temp_url() {
        $upload_dir = wp_upload_dir();
        return $upload_dir['baseurl'] . '/' . self::UPLOAD_SETTINGS['temp_dir'];
    }

    /**
     * Check if feature is enabled
     */
    public static function is_feature_enabled($feature) {
        switch ($feature) {
            case 'mockups':
                return get_option('pdw_enable_mockups', true);
            case 'clipart':
                return get_option('pdw_enable_clipart', true);
            case 'text_effects':
                return get_option('pdw_enable_text_effects', true);
            default:
                return false;
        }
    }

    /**
     * Get auto-save interval in seconds
     */
    public static function get_autosave_interval() {
        $minutes = get_option('pdw_auto_save_interval', 5);
        return $minutes * 60;
    }
}