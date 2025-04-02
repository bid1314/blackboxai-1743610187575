<?php
/**
 * Designer Interface Handler
 */

class PDW_Designer_Interface {
    public function __construct() {
        add_action('woocommerce_before_add_to_cart_button', [$this, 'render_designer']);
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_design_to_cart'], 10, 3);
        add_filter('woocommerce_get_item_data', [$this, 'display_design_data_in_cart'], 10, 2);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_designer_assets']);
    }

    /**
     * Check if product has designer enabled
     */
    public function is_designer_enabled($product_id) {
        return get_post_meta($product_id, '_enable_print_designer', true) === 'yes';
    }

    /**
     * Enqueue designer assets
     */
    public function enqueue_designer_assets() {
        if (!is_product() || !$this->is_designer_enabled(get_the_ID())) {
            return;
        }

        // Enqueue print designer assets
        wp_enqueue_style(
            'fabric-js',
            'https://cdnjs.cloudflare.com/ajax/libs/fabric.js/4.5.0/fabric.min.css'
        );

        wp_enqueue_script(
            'fabric-js',
            'https://cdnjs.cloudflare.com/ajax/libs/fabric.js/4.5.0/fabric.min.js',
            [],
            '4.5.0',
            true
        );

        // Enqueue our designer interface
        wp_enqueue_style(
            'pdw-designer',
            PDW_PLUGIN_URL . 'assets/css/designer.css',
            ['fabric-js'],
            PDW_VERSION
        );

        wp_enqueue_script(
            'pdw-designer',
            PDW_PLUGIN_URL . 'assets/js/designer.js',
            ['jquery', 'fabric-js'],
            PDW_VERSION,
            true
        );

        // Pass necessary data to JavaScript
        $product = wc_get_product(get_the_ID());
        wp_localize_script('pdw-designer', 'pdwDesignerSettings', [
            'productId' => $product->get_id(),
            'productTitle' => $product->get_title(),
            'variations' => $this->get_variation_data($product),
            'designerConfig' => [
                'canvasWidth' => 600,
                'canvasHeight' => 800,
                'maxUploadSize' => wp_max_upload_size(),
                'allowedFileTypes' => ['image/jpeg', 'image/png', 'image/svg+xml'],
            ],
            'i18n' => [
                'addText' => __('Add Text', 'print-designer-woo'),
                'addImage' => __('Add Image', 'print-designer-woo'),
                'uploadImage' => __('Upload Image', 'print-designer-woo'),
                'changeColor' => __('Change Color', 'print-designer-woo'),
                'saveDesign' => __('Save Design', 'print-designer-woo'),
                'loadDesign' => __('Load Design', 'print-designer-woo'),
                'deleteElement' => __('Delete Element', 'print-designer-woo'),
                'bringForward' => __('Bring Forward', 'print-designer-woo'),
                'sendBackward' => __('Send Backward', 'print-designer-woo'),
            ]
        ]);
    }

    /**
     * Get variation data for product
     */
    private function get_variation_data($product) {
        if (!$product->is_type('variable')) {
            return [];
        }

        $variations = [];
        foreach ($product->get_available_variations() as $variation) {
            $variations[] = [
                'id' => $variation['variation_id'],
                'attributes' => $variation['attributes'],
                'price' => $variation['display_price'],
                'image' => $variation['image']['url'],
            ];
        }
        return $variations;
    }

    /**
     * Render the designer interface
     */
    public function render_designer() {
        if (!$this->is_designer_enabled(get_the_ID())) {
            return;
        }

        ?>
        <div id="pdw-designer-container" class="pdw-designer-container">
            <div class="pdw-toolbar">
                <button type="button" class="pdw-tool" data-tool="text">
                    <i class="fas fa-text"></i> <?php _e('Add Text', 'print-designer-woo'); ?>
                </button>
                <button type="button" class="pdw-tool" data-tool="upload">
                    <i class="fas fa-upload"></i> <?php _e('Upload Image', 'print-designer-woo'); ?>
                </button>
                <button type="button" class="pdw-tool" data-tool="clipart">
                    <i class="fas fa-images"></i> <?php _e('Add Clipart', 'print-designer-woo'); ?>
                </button>
                <button type="button" class="pdw-tool" data-tool="save">
                    <i class="fas fa-save"></i> <?php _e('Save Design', 'print-designer-woo'); ?>
                </button>
                <button type="button" class="pdw-tool" data-tool="load">
                    <i class="fas fa-folder-open"></i> <?php _e('Load Design', 'print-designer-woo'); ?>
                </button>
            </div>

            <div class="pdw-workspace">
                <div class="pdw-canvas-container">
                    <canvas id="pdw-canvas"></canvas>
                </div>
                <div class="pdw-properties-panel">
                    <!-- Properties panel content will be dynamically populated -->
                </div>
            </div>

            <input type="hidden" name="pdw_design_data" id="pdw_design_data" />
        </div>
        <?php
    }

    /**
     * Add design data to cart item
     */
    public function add_design_to_cart($cart_item_data, $product_id, $variation_id) {
        if (isset($_POST['pdw_design_data'])) {
            $cart_item_data['design_data'] = sanitize_text_field($_POST['pdw_design_data']);
        }
        return $cart_item_data;
    }

    /**
     * Display design data in cart
     */
    public function display_design_data_in_cart($item_data, $cart_item) {
        if (isset($cart_item['design_data'])) {
            $item_data[] = [
                'key' => __('Custom Design', 'print-designer-woo'),
                'value' => __('Yes', 'print-designer-woo')
            ];
        }
        return $item_data;
    }
}