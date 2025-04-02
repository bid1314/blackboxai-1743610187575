<?php
/**
 * Design Storage Handler
 */

class PDW_Design_Storage {
    public function __construct() {
        add_action('wp_ajax_pdw_save_design', [$this, 'ajax_save_design']);
        add_action('wp_ajax_pdw_load_design', [$this, 'ajax_load_design']);
        add_action('wp_ajax_pdw_list_designs', [$this, 'ajax_list_designs']);
        add_action('woocommerce_add_order_item_meta', [$this, 'save_design_to_order'], 10, 3);
    }

    /**
     * Save design data
     */
    public function save_design($user_id, $product_id, $design_data) {
        $design = [
            'post_title' => sprintf(__('Design for product #%d', 'print-designer-woo'), $product_id),
            'post_type' => 'pdw_saved_design',
            'post_status' => 'publish',
            'post_author' => $user_id,
        ];

        $design_id = wp_insert_post($design);

        if (!is_wp_error($design_id)) {
            update_post_meta($design_id, '_product_id', $product_id);
            update_post_meta($design_id, '_design_data', $design_data);
            return $design_id;
        }

        return false;
    }

    /**
     * Load saved design
     */
    public function load_design($design_id) {
        $design = get_post($design_id);
        
        if (!$design || $design->post_type !== 'pdw_saved_design') {
            return false;
        }

        return [
            'id' => $design->ID,
            'product_id' => get_post_meta($design->ID, '_product_id', true),
            'design_data' => get_post_meta($design->ID, '_design_data', true),
            'created' => $design->post_date,
        ];
    }

    /**
     * Get user's saved designs
     */
    public function get_user_designs($user_id, $product_id = null) {
        $args = [
            'post_type' => 'pdw_saved_design',
            'author' => $user_id,
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ];

        if ($product_id) {
            $args['meta_query'] = [
                [
                    'key' => '_product_id',
                    'value' => $product_id,
                ]
            ];
        }

        $designs = get_posts($args);
        $result = [];

        foreach ($designs as $design) {
            $result[] = [
                'id' => $design->ID,
                'product_id' => get_post_meta($design->ID, '_product_id', true),
                'design_data' => get_post_meta($design->ID, '_design_data', true),
                'created' => $design->post_date,
            ];
        }

        return $result;
    }

    /**
     * AJAX handler for saving designs
     */
    public function ajax_save_design() {
        check_ajax_referer('pdw-nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('You must be logged in to save designs', 'print-designer-woo')]);
        }

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $design_data = isset($_POST['design_data']) ? $_POST['design_data'] : '';

        if (!$product_id || empty($design_data)) {
            wp_send_json_error(['message' => __('Invalid design data', 'print-designer-woo')]);
        }

        $design_id = $this->save_design(get_current_user_id(), $product_id, $design_data);

        if ($design_id) {
            wp_send_json_success([
                'design_id' => $design_id,
                'message' => __('Design saved successfully', 'print-designer-woo'),
            ]);
        } else {
            wp_send_json_error(['message' => __('Failed to save design', 'print-designer-woo')]);
        }
    }

    /**
     * AJAX handler for loading designs
     */
    public function ajax_load_design() {
        check_ajax_referer('pdw-nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('You must be logged in to load designs', 'print-designer-woo')]);
        }

        $design_id = isset($_POST['design_id']) ? intval($_POST['design_id']) : 0;

        if (!$design_id) {
            wp_send_json_error(['message' => __('Invalid design ID', 'print-designer-woo')]);
        }

        $design = $this->load_design($design_id);

        if ($design) {
            wp_send_json_success($design);
        } else {
            wp_send_json_error(['message' => __('Design not found', 'print-designer-woo')]);
        }
    }

    /**
     * AJAX handler for listing designs
     */
    public function ajax_list_designs() {
        check_ajax_referer('pdw-nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('You must be logged in to view designs', 'print-designer-woo')]);
        }

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : null;
        $designs = $this->get_user_designs(get_current_user_id(), $product_id);

        wp_send_json_success(['designs' => $designs]);
    }

    /**
     * Save design data to order
     */
    public function save_design_to_order($item_id, $values, $cart_item_key) {
        if (isset($values['design_data'])) {
            wc_add_order_item_meta($item_id, '_design_data', $values['design_data']);
        }
    }
}