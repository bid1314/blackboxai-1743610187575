<?php
/**
 * AJAX Request Handler
 */

class PDW_Ajax_Handler {
    public function __construct() {
        // Designer actions
        add_action('wp_ajax_pdw_save_design', [$this, 'save_design']);
        add_action('wp_ajax_pdw_load_design', [$this, 'load_design']);
        add_action('wp_ajax_pdw_list_designs', [$this, 'list_designs']);
        add_action('wp_ajax_pdw_delete_design', [$this, 'delete_design']);
        add_action('wp_ajax_pdw_generate_mockup', [$this, 'generate_mockup']);
        
        // Image handling
        add_action('wp_ajax_pdw_upload_image', [$this, 'upload_image']);
        add_action('wp_ajax_pdw_get_clipart', [$this, 'get_clipart']);
        
        // Product related
        add_action('wp_ajax_pdw_get_product_data', [$this, 'get_product_data']);
        add_action('wp_ajax_nopriv_pdw_get_product_data', [$this, 'get_product_data']);
    }

    /**
     * Verify nonce and user capabilities
     */
    private function verify_request($nonce_action = 'pdw-nonce') {
        // Check nonce
        if (!check_ajax_referer($nonce_action, 'nonce', false)) {
            wp_send_json_error([
                'message' => __('Invalid security token.', 'print-designer-woo')
            ]);
        }

        // Check user login for actions requiring authentication
        if (!is_user_logged_in() && $nonce_action !== 'pdw-public-nonce') {
            wp_send_json_error([
                'message' => __('You must be logged in to perform this action.', 'print-designer-woo')
            ]);
        }
    }

    /**
     * Save design
     */
    public function save_design() {
        $this->verify_request();

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $design_data = isset($_POST['design_data']) ? $_POST['design_data'] : '';
        $design_name = isset($_POST['design_name']) ? sanitize_text_field($_POST['design_name']) : '';

        if (!$product_id || empty($design_data)) {
            wp_send_json_error([
                'message' => __('Invalid design data.', 'print-designer-woo')
            ]);
        }

        // Save design using storage class
        $design_storage = new PDW_Design_Storage();
        $design_id = $design_storage->save_design(
            get_current_user_id(),
            $product_id,
            $design_data,
            $design_name
        );

        if ($design_id) {
            wp_send_json_success([
                'design_id' => $design_id,
                'message' => __('Design saved successfully.', 'print-designer-woo')
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to save design.', 'print-designer-woo')
            ]);
        }
    }

    /**
     * Load design
     */
    public function load_design() {
        $this->verify_request();

        $design_id = isset($_POST['design_id']) ? intval($_POST['design_id']) : 0;

        if (!$design_id) {
            wp_send_json_error([
                'message' => __('Invalid design ID.', 'print-designer-woo')
            ]);
        }

        $design_storage = new PDW_Design_Storage();
        $design = $design_storage->load_design($design_id);

        if ($design) {
            wp_send_json_success($design);
        } else {
            wp_send_json_error([
                'message' => __('Design not found.', 'print-designer-woo')
            ]);
        }
    }

    /**
     * List user's saved designs
     */
    public function list_designs() {
        $this->verify_request();

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : null;

        $design_storage = new PDW_Design_Storage();
        $designs = $design_storage->get_user_designs(get_current_user_id(), $product_id);

        wp_send_json_success([
            'designs' => $designs
        ]);
    }

    /**
     * Delete saved design
     */
    public function delete_design() {
        $this->verify_request();

        $design_id = isset($_POST['design_id']) ? intval($_POST['design_id']) : 0;

        if (!$design_id) {
            wp_send_json_error([
                'message' => __('Invalid design ID.', 'print-designer-woo')
            ]);
        }

        $design_storage = new PDW_Design_Storage();
        $deleted = $design_storage->delete_design($design_id);

        if ($deleted) {
            wp_send_json_success([
                'message' => __('Design deleted successfully.', 'print-designer-woo')
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to delete design.', 'print-designer-woo')
            ]);
        }
    }

    /**
     * Generate product mockup
     */
    public function generate_mockup() {
        $this->verify_request();

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $variation_id = isset($_POST['variation_id']) ? intval($_POST['variation_id']) : 0;
        $design_data = isset($_POST['design_data']) ? $_POST['design_data'] : '';

        if (!$product_id || empty($design_data)) {
            wp_send_json_error([
                'message' => __('Invalid data for mockup generation.', 'print-designer-woo')
            ]);
        }

        $mockup_generator = new PDW_Mockup_Generator();
        $mockup = $mockup_generator->generate_mockup($design_data, $product_id, $variation_id);

        if ($mockup) {
            wp_send_json_success([
                'mockup_url' => $mockup['url']
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to generate mockup.', 'print-designer-woo')
            ]);
        }
    }

    /**
     * Handle image upload
     */
    public function upload_image() {
        $this->verify_request();

        if (!isset($_FILES['image'])) {
            wp_send_json_error([
                'message' => __('No image file provided.', 'print-designer-woo')
            ]);
        }

        $file = $_FILES['image'];
        
        // Validate file type
        if (!PDW_Helper::validate_file_type($file['type'])) {
            wp_send_json_error([
                'message' => __('Invalid file type.', 'print-designer-woo')
            ]);
        }

        // Validate file size
        $max_size = PDW_Config::get_upload_settings()['max_size'] * 1024 * 1024;
        if ($file['size'] > $max_size) {
            wp_send_json_error([
                'message' => sprintf(
                    __('File size exceeds maximum limit of %s.', 'print-designer-woo'),
                    PDW_Helper::format_bytes($max_size)
                )
            ]);
        }

        // Create temporary directory if it doesn't exist
        PDW_Helper::ensure_temp_directory();

        // Generate unique filename
        $extension = PDW_Helper::get_extension_from_mime($file['type']);
        $filename = PDW_Helper::generate_unique_filename($extension);
        $filepath = PDW_Config::get_temp_dir() . '/' . $filename;

        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            wp_send_json_success([
                'url' => PDW_Config::get_temp_url() . '/' . $filename,
                'filename' => $filename
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Failed to upload image.', 'print-designer-woo')
            ]);
        }
    }

    /**
     * Get clipart library items
     */
    public function get_clipart() {
        $this->verify_request();

        $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : '';
        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;

        // Query clipart items
        $args = [
            'post_type' => 'pdw_clipart',
            'posts_per_page' => 20,
            'paged' => $page,
            'orderby' => 'title',
            'order' => 'ASC'
        ];

        if ($category) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'pdw_clipart_category',
                    'field' => 'slug',
                    'terms' => $category
                ]
            ];
        }

        if ($search) {
            $args['s'] = $search;
        }

        $query = new WP_Query($args);
        $items = [];

        foreach ($query->posts as $post) {
            $items[] = [
                'id' => $post->ID,
                'title' => $post->post_title,
                'url' => wp_get_attachment_url(get_post_thumbnail_id($post->ID)),
                'category' => wp_get_post_terms($post->ID, 'pdw_clipart_category', ['fields' => 'names'])
            ];
        }

        wp_send_json_success([
            'items' => $items,
            'total_pages' => $query->max_num_pages
        ]);
    }

    /**
     * Get product data for designer
     */
    public function get_product_data() {
        $this->verify_request('pdw-public-nonce');

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

        if (!$product_id) {
            wp_send_json_error([
                'message' => __('Invalid product ID.', 'print-designer-woo')
            ]);
        }

        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error([
                'message' => __('Product not found.', 'print-designer-woo')
            ]);
        }

        $data = [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'type' => $product->get_type(),
            'price' => $product->get_price(),
            'image' => wp_get_attachment_url($product->get_image_id()),
            'variations' => [],
            'mockup_template' => PDW_Helper::get_product_mockup_template($product_id)
        ];

        // Get variation data for variable products
        if ($product->is_type('variable')) {
            $variations = $product->get_available_variations();
            foreach ($variations as $variation) {
                $data['variations'][] = [
                    'id' => $variation['variation_id'],
                    'attributes' => $variation['attributes'],
                    'price' => $variation['display_price'],
                    'image' => $variation['image']['url'],
                    'mockup_template' => PDW_Helper::get_product_mockup_template(
                        $product_id,
                        $variation['variation_id']
                    )
                ];
            }
        }

        wp_send_json_success($data);
    }
}