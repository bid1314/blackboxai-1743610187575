<?php
/**
 * REST API Handler
 */

class PDW_API_Handler {
    /**
     * API namespace
     */
    const API_NAMESPACE = 'print-designer/v1';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        // Designs endpoints
        register_rest_route(self::API_NAMESPACE, '/designs', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_designs'],
                'permission_callback' => [$this, 'check_user_permission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'save_design'],
                'permission_callback' => [$this, 'check_user_permission'],
            ]
        ]);

        register_rest_route(self::API_NAMESPACE, '/designs/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_design'],
                'permission_callback' => [$this, 'check_user_permission'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'delete_design'],
                'permission_callback' => [$this, 'check_user_permission'],
            ]
        ]);

        // Clipart endpoints
        register_rest_route(self::API_NAMESPACE, '/clipart', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_clipart'],
                'permission_callback' => '__return_true',
            ]
        ]);

        register_rest_route(self::API_NAMESPACE, '/clipart/categories', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_clipart_categories'],
                'permission_callback' => '__return_true',
            ]
        ]);

        // Product endpoints
        register_rest_route(self::API_NAMESPACE, '/products/(?P<id>\d+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_product_data'],
                'permission_callback' => '__return_true',
            ]
        ]);

        // Mockup endpoints
        register_rest_route(self::API_NAMESPACE, '/mockups/generate', [
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'generate_mockup'],
                'permission_callback' => [$this, 'check_user_permission'],
            ]
        ]);
    }

    /**
     * Check user permission
     */
    public function check_user_permission(WP_REST_Request $request) {
        return is_user_logged_in();
    }

    /**
     * Get user's saved designs
     */
    public function get_designs(WP_REST_Request $request) {
        $product_id = $request->get_param('product_id');
        $page = $request->get_param('page') ?: 1;
        $per_page = $request->get_param('per_page') ?: 10;

        $design_storage = new PDW_Design_Storage();
        $designs = $design_storage->get_user_designs(
            get_current_user_id(),
            $product_id,
            $page,
            $per_page
        );

        return rest_ensure_response($designs);
    }

    /**
     * Get single design
     */
    public function get_design(WP_REST_Request $request) {
        $design_id = $request->get_param('id');

        $design_storage = new PDW_Design_Storage();
        $design = $design_storage->load_design($design_id);

        if (!$design) {
            return new WP_Error(
                'design_not_found',
                __('Design not found', 'print-designer-woo'),
                ['status' => 404]
            );
        }

        return rest_ensure_response($design);
    }

    /**
     * Save design
     */
    public function save_design(WP_REST_Request $request) {
        $product_id = $request->get_param('product_id');
        $design_data = $request->get_param('design_data');
        $design_name = $request->get_param('design_name');

        if (!$product_id || !$design_data) {
            return new WP_Error(
                'missing_data',
                __('Missing required data', 'print-designer-woo'),
                ['status' => 400]
            );
        }

        $design_storage = new PDW_Design_Storage();
        $design_id = $design_storage->save_design(
            get_current_user_id(),
            $product_id,
            $design_data,
            $design_name
        );

        if (!$design_id) {
            return new WP_Error(
                'save_failed',
                __('Failed to save design', 'print-designer-woo'),
                ['status' => 500]
            );
        }

        return rest_ensure_response([
            'id' => $design_id,
            'message' => __('Design saved successfully', 'print-designer-woo')
        ]);
    }

    /**
     * Delete design
     */
    public function delete_design(WP_REST_Request $request) {
        $design_id = $request->get_param('id');

        $design_storage = new PDW_Design_Storage();
        $deleted = $design_storage->delete_design($design_id);

        if (!$deleted) {
            return new WP_Error(
                'delete_failed',
                __('Failed to delete design', 'print-designer-woo'),
                ['status' => 500]
            );
        }

        return rest_ensure_response([
            'message' => __('Design deleted successfully', 'print-designer-woo')
        ]);
    }

    /**
     * Get clipart items
     */
    public function get_clipart(WP_REST_Request $request) {
        $args = [
            'category' => $request->get_param('category'),
            'search' => $request->get_param('search'),
            'page' => $request->get_param('page') ?: 1,
            'per_page' => $request->get_param('per_page') ?: 20
        ];

        $clipart = PDW_Clipart_Handler::get_clipart_items($args);
        return rest_ensure_response($clipart);
    }

    /**
     * Get clipart categories
     */
    public function get_clipart_categories() {
        $categories = PDW_Clipart_Handler::get_categories();
        return rest_ensure_response($categories);
    }

    /**
     * Get product data
     */
    public function get_product_data(WP_REST_Request $request) {
        $product_id = $request->get_param('id');
        $product = wc_get_product($product_id);

        if (!$product) {
            return new WP_Error(
                'product_not_found',
                __('Product not found', 'print-designer-woo'),
                ['status' => 404]
            );
        }

        if (!PDW_Helper::is_designer_enabled($product_id)) {
            return new WP_Error(
                'designer_disabled',
                __('Designer is not enabled for this product', 'print-designer-woo'),
                ['status' => 400]
            );
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

        if ($product->is_type('variable')) {
            foreach ($product->get_available_variations() as $variation) {
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

        return rest_ensure_response($data);
    }

    /**
     * Generate mockup
     */
    public function generate_mockup(WP_REST_Request $request) {
        $product_id = $request->get_param('product_id');
        $variation_id = $request->get_param('variation_id');
        $design_data = $request->get_param('design_data');

        if (!$product_id || !$design_data) {
            return new WP_Error(
                'missing_data',
                __('Missing required data', 'print-designer-woo'),
                ['status' => 400]
            );
        }

        $mockup_generator = new PDW_Mockup_Generator();
        $mockup = $mockup_generator->generate_mockup($design_data, $product_id, $variation_id);

        if (!$mockup) {
            return new WP_Error(
                'generation_failed',
                __('Failed to generate mockup', 'print-designer-woo'),
                ['status' => 500]
            );
        }

        return rest_ensure_response([
            'url' => $mockup['url']
        ]);
    }
}