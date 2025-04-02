<?php
/**
 * Plugin Name: Print Designer for WooCommerce
 * Plugin URI: https://github.com/yourusername/print-designer-woo
 * Description: Product customization tool integrated with WooCommerce
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yoursite.com
 * Text Domain: print-designer-woo
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('PDW_VERSION', '1.0.0');
define('PDW_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PDW_PLUGIN_URL', plugin_dir_url(__FILE__));

// Autoloader
spl_autoload_register(function ($class) {
    $prefix = 'PDW_';
    $base_dir = PDW_PLUGIN_DIR . 'includes/';

    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative_class = substr($class, strlen($prefix));
    $file = $base_dir . 'class-' . strtolower(str_replace('_', '-', $relative_class)) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

// Main plugin class
class PrintDesignerWoo {
    private static $instance = null;
    private $design_storage = null;
    private $designer_interface = null;
    private $mockup_generator = null;
    private $admin_settings = null;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function __construct() {
        register_activation_hook(__FILE__, ['PDW_Initializer', 'activate']);
        register_deactivation_hook(__FILE__, ['PDW_Initializer', 'deactivate']);

        add_action('plugins_loaded', [$this, 'init_plugin']);
    }

    public function init_plugin() {
        // Check dependencies
        if (!PDW_Initializer::check_dependencies()) {
            return;
        }

        // Initialize components
        $this->init_components();

        // Setup hooks
        $this->init_hooks();
    }

    private function init_components() {
        $this->design_storage = new PDW_Design_Storage();
        $this->designer_interface = new PDW_Designer_Interface();
        $this->mockup_generator = new PDW_Mockup_Generator();
        
        if (is_admin()) {
            $this->admin_settings = new PDW_Admin_Settings();
        }
    }

    private function init_hooks() {
        add_action('init', [$this, 'init']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        
        // WooCommerce specific hooks
        add_filter('woocommerce_product_data_tabs', [$this, 'add_product_data_tab']);
        add_action('woocommerce_product_data_panels', [$this, 'add_product_data_panel']);
        add_action('woocommerce_process_product_meta', [$this, 'save_product_settings']);

        // Schedule cleanup task
        if (!wp_next_scheduled('pdw_cleanup_temp_files')) {
            wp_schedule_event(time(), 'daily', 'pdw_cleanup_temp_files');
        }
        add_action('pdw_cleanup_temp_files', ['PDW_Initializer', 'cleanup_temp_files']);
    }

    public function init() {
        load_plugin_textdomain('print-designer-woo', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    public function enqueue_scripts() {
        if (is_product() && get_post_meta(get_the_ID(), '_enable_print_designer', true) === 'yes') {
            wp_enqueue_style(
                'print-designer-woo',
                PDW_PLUGIN_URL . 'assets/css/designer.css',
                [],
                PDW_VERSION
            );

            wp_enqueue_script(
                'print-designer-woo',
                PDW_PLUGIN_URL . 'assets/js/designer.js',
                ['jquery'],
                PDW_VERSION,
                true
            );

            wp_localize_script('print-designer-woo', 'pdwSettings', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('pdw-nonce'),
                'isLoggedIn' => is_user_logged_in(),
                'loginUrl' => wp_login_url(get_permalink()),
                'canvasWidth' => get_option('pdw_canvas_width', 600),
                'canvasHeight' => get_option('pdw_canvas_height', 800),
                'maxUploadSize' => get_option('pdw_max_upload_size', 5) * 1024 * 1024,
                'allowedFileTypes' => get_option('pdw_allowed_file_types', ['jpg', 'jpeg', 'png', 'svg']),
                'autoSaveInterval' => get_option('pdw_auto_save_interval', 5) * 60,
                'enableMockups' => get_option('pdw_enable_mockups', true),
                'enableClipart' => get_option('pdw_enable_clipart', true),
                'enableTextEffects' => get_option('pdw_enable_text_effects', true),
                'i18n' => [
                    'saveDesign' => __('Save Design', 'print-designer-woo'),
                    'loadDesign' => __('Load Design', 'print-designer-woo'),
                    'addToCart' => __('Add to Cart', 'print-designer-woo'),
                    'uploadImage' => __('Upload Image', 'print-designer-woo'),
                    'addText' => __('Add Text', 'print-designer-woo'),
                    'deleteElement' => __('Delete Element', 'print-designer-woo'),
                ]
            ]);
        }
    }

    public function add_product_data_tab($tabs) {
        $tabs['print_designer'] = [
            'label' => __('Print Designer', 'print-designer-woo'),
            'target' => 'print_designer_product_data',
            'class' => ['show_if_simple', 'show_if_variable'],
        ];
        return $tabs;
    }

    public function add_product_data_panel() {
        echo '<div id="print_designer_product_data" class="panel woocommerce_options_panel">';
        woocommerce_wp_checkbox([
            'id' => '_enable_print_designer',
            'label' => __('Enable Print Designer', 'print-designer-woo'),
            'description' => __('Enable product customization with Print Designer', 'print-designer-woo')
        ]);
        
        woocommerce_wp_select([
            'id' => '_pdw_mockup_template',
            'label' => __('Mockup Template', 'print-designer-woo'),
            'description' => __('Select the mockup template for this product', 'print-designer-woo'),
            'options' => $this->get_mockup_templates_options()
        ]);
        echo '</div>';
    }

    private function get_mockup_templates_options() {
        $options = ['' => __('Select a template', 'print-designer-woo')];
        
        $templates = get_posts([
            'post_type' => 'pdw_mockup',
            'posts_per_page' => -1,
        ]);

        foreach ($templates as $template) {
            $options[$template->ID] = $template->post_title;
        }

        return $options;
    }

    public function save_product_settings($post_id) {
        $enable_designer = isset($_POST['_enable_print_designer']) ? 'yes' : 'no';
        update_post_meta($post_id, '_enable_print_designer', $enable_designer);

        if (isset($_POST['_pdw_mockup_template'])) {
            update_post_meta($post_id, '_pdw_mockup_template', sanitize_text_field($_POST['_pdw_mockup_template']));
        }
    }
}

// Initialize plugin
function print_designer_woo() {
    return PrintDesignerWoo::instance();
}

print_designer_woo();