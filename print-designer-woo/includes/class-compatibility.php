<?php
/**
 * Plugin Compatibility Handler
 */

class PDW_Compatibility {
    /**
     * Known compatible/incompatible themes
     */
    const THEME_COMPATIBILITY = [
        'compatible' => [
            'storefront',
            'astra',
            'oceanwp',
            'generatepress',
            'flatsome',
            'divi',
        ],
        'incompatible' => [
            // List themes with known issues
        ]
    ];

    /**
     * Known compatible/incompatible plugins
     */
    const PLUGIN_COMPATIBILITY = [
        'compatible' => [
            'woocommerce',
            'elementor',
            'gutenberg',
        ],
        'incompatible' => [
            // List plugins with known conflicts
        ]
    ];

    public function __construct() {
        add_action('admin_init', [$this, 'check_compatibility']);
        add_action('admin_notices', [$this, 'display_compatibility_notices']);
        
        // Theme-specific adjustments
        add_action('wp_enqueue_scripts', [$this, 'theme_adjustments']);
        
        // Plugin-specific compatibility fixes
        add_action('plugins_loaded', [$this, 'plugin_compatibility_fixes']);
    }

    /**
     * Check compatibility with current theme and plugins
     */
    public function check_compatibility() {
        $current_theme = wp_get_theme();
        $active_plugins = get_option('active_plugins');
        $compatibility_issues = [];

        // Check theme compatibility
        if (in_array($current_theme->get_template(), self::THEME_COMPATIBILITY['incompatible'])) {
            $compatibility_issues['theme'] = sprintf(
                __('The current theme "%s" is known to have compatibility issues with Print Designer.', 'print-designer-woo'),
                $current_theme->get('Name')
            );
        }

        // Check plugin compatibility
        foreach ($active_plugins as $plugin) {
            $plugin_slug = explode('/', $plugin)[0];
            if (in_array($plugin_slug, self::PLUGIN_COMPATIBILITY['incompatible'])) {
                $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
                $compatibility_issues['plugins'][] = sprintf(
                    __('The plugin "%s" may conflict with Print Designer.', 'print-designer-woo'),
                    $plugin_data['Name']
                );
            }
        }

        if (!empty($compatibility_issues)) {
            update_option('pdw_compatibility_issues', $compatibility_issues);
        } else {
            delete_option('pdw_compatibility_issues');
        }
    }

    /**
     * Display compatibility notices
     */
    public function display_compatibility_notices() {
        $compatibility_issues = get_option('pdw_compatibility_issues');
        
        if (empty($compatibility_issues)) {
            return;
        }

        if (isset($compatibility_issues['theme'])) {
            echo '<div class="notice notice-warning">';
            echo '<p><strong>' . __('Print Designer Compatibility Warning:', 'print-designer-woo') . '</strong></p>';
            echo '<p>' . esc_html($compatibility_issues['theme']) . '</p>';
            echo '<p>' . __('Consider switching to a fully compatible theme for the best experience.', 'print-designer-woo') . '</p>';
            echo '</div>';
        }

        if (isset($compatibility_issues['plugins'])) {
            echo '<div class="notice notice-warning">';
            echo '<p><strong>' . __('Print Designer Plugin Conflicts:', 'print-designer-woo') . '</strong></p>';
            echo '<ul>';
            foreach ($compatibility_issues['plugins'] as $plugin_issue) {
                echo '<li>' . esc_html($plugin_issue) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
    }

    /**
     * Apply theme-specific adjustments
     */
    public function theme_adjustments() {
        $current_theme = wp_get_theme()->get_template();

        switch ($current_theme) {
            case 'storefront':
                $this->adjust_storefront();
                break;
            case 'astra':
                $this->adjust_astra();
                break;
            case 'oceanwp':
                $this->adjust_oceanwp();
                break;
            case 'flatsome':
                $this->adjust_flatsome();
                break;
            case 'divi':
                $this->adjust_divi();
                break;
        }
    }

    /**
     * Storefront theme adjustments
     */
    private function adjust_storefront() {
        // Add custom CSS for Storefront compatibility
        wp_add_inline_style('print-designer-woo', '
            .pdw-designer-container {
                z-index: 999;
            }
            .storefront-primary-navigation {
                z-index: 1000;
            }
        ');
    }

    /**
     * Astra theme adjustments
     */
    private function adjust_astra() {
        // Add custom CSS for Astra compatibility
        wp_add_inline_style('print-designer-woo', '
            .pdw-designer-container {
                z-index: 9;
            }
            #ast-mobile-header {
                z-index: 10;
            }
        ');
    }

    /**
     * OceanWP theme adjustments
     */
    private function adjust_oceanwp() {
        // Add custom CSS for OceanWP compatibility
        wp_add_inline_style('print-designer-woo', '
            .pdw-designer-container {
                z-index: 999;
            }
            #site-header {
                z-index: 1000;
            }
        ');
    }

    /**
     * Flatsome theme adjustments
     */
    private function adjust_flatsome() {
        // Add custom CSS for Flatsome compatibility
        wp_add_inline_style('print-designer-woo', '
            .pdw-designer-container {
                z-index: 29;
            }
            #masthead {
                z-index: 30;
            }
        ');
    }

    /**
     * Divi theme adjustments
     */
    private function adjust_divi() {
        // Add custom CSS for Divi compatibility
        wp_add_inline_style('print-designer-woo', '
            .pdw-designer-container {
                z-index: 99999;
            }
            #main-header {
                z-index: 100000;
            }
        ');
    }

    /**
     * Apply plugin compatibility fixes
     */
    public function plugin_compatibility_fixes() {
        if (is_plugin_active('elementor/elementor.php')) {
            $this->fix_elementor_compatibility();
        }

        if (is_plugin_active('js_composer/js_composer.php')) {
            $this->fix_wpbakery_compatibility();
        }

        if (is_plugin_active('woocommerce-product-addons/woocommerce-product-addons.php')) {
            $this->fix_product_addons_compatibility();
        }
    }

    /**
     * Fix Elementor compatibility issues
     */
    private function fix_elementor_compatibility() {
        // Ensure our scripts load after Elementor
        add_action('wp_enqueue_scripts', function() {
            if (is_product()) {
                wp_dequeue_script('print-designer-woo');
                wp_enqueue_script(
                    'print-designer-woo',
                    PDW_PLUGIN_URL . 'assets/js/designer.js',
                    ['jquery', 'elementor-frontend'],
                    PDW_VERSION,
                    true
                );
            }
        }, 100);
    }

    /**
     * Fix WPBakery compatibility issues
     */
    private function fix_wpbakery_compatibility() {
        // Handle WPBakery-specific issues
        add_action('wp_enqueue_scripts', function() {
            if (is_product()) {
                wp_add_inline_style('print-designer-woo', '
                    .pdw-designer-container {
                        position: relative !important;
                    }
                ');
            }
        });
    }

    /**
     * Fix WooCommerce Product Add-ons compatibility
     */
    private function fix_product_addons_compatibility() {
        // Ensure our price calculations work with Product Add-ons
        add_filter('woocommerce_product_addons_price_before_calc', function($price, $product) {
            if (isset($_POST['pdw_design_data'])) {
                $price_adjustment = PDW_Helper::get_design_price_adjustment($_POST['pdw_design_data']);
                $price += $price_adjustment;
            }
            return $price;
        }, 10, 2);
    }

    /**
     * Check if current theme is fully compatible
     */
    public static function is_theme_compatible() {
        $current_theme = wp_get_theme()->get_template();
        return in_array($current_theme, self::THEME_COMPATIBILITY['compatible']);
    }

    /**
     * Get compatibility status
     */
    public static function get_compatibility_status() {
        $current_theme = wp_get_theme();
        $active_plugins = get_option('active_plugins');
        
        $status = [
            'theme' => [
                'name' => $current_theme->get('Name'),
                'version' => $current_theme->get('Version'),
                'compatible' => self::is_theme_compatible(),
            ],
            'plugins' => []
        ];

        foreach ($active_plugins as $plugin) {
            if (!is_file(WP_PLUGIN_DIR . '/' . $plugin)) {
                continue;
            }

            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
            $plugin_slug = explode('/', $plugin)[0];
            
            $status['plugins'][] = [
                'name' => $plugin_data['Name'],
                'version' => $plugin_data['Version'],
                'compatible' => in_array($plugin_slug, self::PLUGIN_COMPATIBILITY['compatible']),
                'conflicting' => in_array($plugin_slug, self::PLUGIN_COMPATIBILITY['incompatible'])
            ];
        }

        return $status;
    }
}