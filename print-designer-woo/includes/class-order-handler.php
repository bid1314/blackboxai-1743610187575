<?php
/**
 * Order Handler
 */

class PDW_Order_Handler {
    public function __construct() {
        // Add design data to cart item
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_design_to_cart'], 10, 3);
        
        // Display design data in cart
        add_filter('woocommerce_get_item_data', [$this, 'display_design_in_cart'], 10, 2);
        
        // Add design data to order item
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'add_design_to_order_item'], 10, 4);
        
        // Display design in order admin
        add_action('woocommerce_after_order_itemmeta', [$this, 'display_design_in_order_admin'], 10, 3);
        
        // Add design preview to order emails
        add_action('woocommerce_order_item_meta_end', [$this, 'add_design_to_email'], 10, 3);
        
        // Generate mockups after order payment
        add_action('woocommerce_payment_complete', [$this, 'generate_order_mockups']);
        add_action('woocommerce_order_status_completed', [$this, 'generate_order_mockups']);
        
        // Add design download link to order
        add_action('woocommerce_order_details_after_order_table', [$this, 'add_design_download_link']);
        
        // Handle design download
        add_action('init', [$this, 'handle_design_download']);
    }

    /**
     * Add design data to cart item
     */
    public function add_design_to_cart($cart_item_data, $product_id, $variation_id) {
        if (isset($_POST['pdw_design_data'])) {
            $cart_item_data['pdw_design_data'] = sanitize_text_field($_POST['pdw_design_data']);
            
            // Generate temporary mockup for cart preview
            if (PDW_Config::is_feature_enabled('mockups')) {
                $mockup_generator = new PDW_Mockup_Generator();
                $mockup = $mockup_generator->generate_mockup(
                    $cart_item_data['pdw_design_data'],
                    $product_id,
                    $variation_id
                );
                
                if ($mockup) {
                    $cart_item_data['pdw_mockup'] = $mockup['url'];
                }
            }
            
            // Add design price adjustment
            $price_adjustment = PDW_Helper::get_design_price_adjustment($cart_item_data['pdw_design_data']);
            if ($price_adjustment > 0) {
                $cart_item_data['pdw_price_adjustment'] = $price_adjustment;
            }
        }
        
        return $cart_item_data;
    }

    /**
     * Display design in cart
     */
    public function display_design_in_cart($item_data, $cart_item) {
        if (isset($cart_item['pdw_design_data'])) {
            $item_data[] = [
                'key' => __('Custom Design', 'print-designer-woo'),
                'value' => ''
            ];
            
            if (isset($cart_item['pdw_mockup'])) {
                $item_data[] = [
                    'key' => 'preview',
                    'display' => '<img src="' . esc_url($cart_item['pdw_mockup']) . '" style="max-width: 100px;">'
                ];
            }
            
            if (isset($cart_item['pdw_price_adjustment']) && $cart_item['pdw_price_adjustment'] > 0) {
                $item_data[] = [
                    'key' => __('Design Fee', 'print-designer-woo'),
                    'value' => wc_price($cart_item['pdw_price_adjustment'])
                ];
            }
        }
        
        return $item_data;
    }

    /**
     * Add design data to order item
     */
    public function add_design_to_order_item($item, $cart_item_key, $values, $order) {
        if (isset($values['pdw_design_data'])) {
            $item->add_meta_data('_pdw_design_data', $values['pdw_design_data']);
            
            if (isset($values['pdw_mockup'])) {
                $item->add_meta_data('_pdw_mockup', $values['pdw_mockup']);
            }
            
            if (isset($values['pdw_price_adjustment'])) {
                $item->add_meta_data('_pdw_price_adjustment', $values['pdw_price_adjustment']);
            }
        }
    }

    /**
     * Display design in order admin
     */
    public function display_design_in_order_admin($item_id, $item, $product) {
        $design_data = wc_get_order_item_meta($item_id, '_pdw_design_data', true);
        if ($design_data) {
            echo '<div class="pdw-order-design-preview">';
            echo '<strong>' . __('Custom Design', 'print-designer-woo') . '</strong><br>';
            
            $mockup = wc_get_order_item_meta($item_id, '_pdw_mockup', true);
            if ($mockup) {
                echo '<img src="' . esc_url($mockup) . '" style="max-width: 150px;"><br>';
            }
            
            $price_adjustment = wc_get_order_item_meta($item_id, '_pdw_price_adjustment', true);
            if ($price_adjustment) {
                echo '<strong>' . __('Design Fee', 'print-designer-woo') . ':</strong> ';
                echo wc_price($price_adjustment) . '<br>';
            }
            
            echo '<a href="' . admin_url('admin-ajax.php?action=pdw_download_design&item_id=' . $item_id) . '" class="button">';
            echo __('Download Design', 'print-designer-woo');
            echo '</a>';
            echo '</div>';
        }
    }

    /**
     * Add design preview to order emails
     */
    public function add_design_to_email($item_id, $item, $order) {
        $design_data = wc_get_order_item_meta($item_id, '_pdw_design_data', true);
        if ($design_data) {
            echo '<br><strong>' . __('Custom Design', 'print-designer-woo') . '</strong><br>';
            
            $mockup = wc_get_order_item_meta($item_id, '_pdw_mockup', true);
            if ($mockup) {
                echo '<img src="' . esc_url($mockup) . '" style="max-width: 150px;"><br>';
            }
        }
    }

    /**
     * Generate mockups for order items
     */
    public function generate_order_mockups($order_id) {
        if (!PDW_Config::is_feature_enabled('mockups')) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $mockup_generator = new PDW_Mockup_Generator();

        foreach ($order->get_items() as $item_id => $item) {
            $design_data = wc_get_order_item_meta($item_id, '_pdw_design_data', true);
            if (!$design_data) {
                continue;
            }

            $product_id = $item->get_product_id();
            $variation_id = $item->get_variation_id();

            $mockup = $mockup_generator->generate_mockup($design_data, $product_id, $variation_id);
            if ($mockup) {
                wc_update_order_item_meta($item_id, '_pdw_mockup', $mockup['url']);
            }
        }
    }

    /**
     * Add design download link to order page
     */
    public function add_design_download_link($order) {
        foreach ($order->get_items() as $item_id => $item) {
            $design_data = wc_get_order_item_meta($item_id, '_pdw_design_data', true);
            if ($design_data) {
                echo '<div class="pdw-design-download">';
                echo '<h3>' . __('Custom Design Files', 'print-designer-woo') . '</h3>';
                echo '<p>' . sprintf(
                    __('Download your design for %s', 'print-designer-woo'),
                    $item->get_name()
                ) . '</p>';
                
                $download_url = wp_nonce_url(
                    add_query_arg([
                        'download_design' => $item_id,
                        'order_id' => $order->get_id()
                    ]),
                    'pdw_download_design'
                );
                
                echo '<a href="' . esc_url($download_url) . '" class="button">';
                echo __('Download Design', 'print-designer-woo');
                echo '</a>';
                echo '</div>';
            }
        }
    }

    /**
     * Handle design download request
     */
    public function handle_design_download() {
        if (
            isset($_GET['download_design']) && 
            isset($_GET['order_id']) && 
            isset($_GET['_wpnonce']) && 
            wp_verify_nonce($_GET['_wpnonce'], 'pdw_download_design')
        ) {
            $item_id = $_GET['download_design'];
            $order_id = $_GET['order_id'];
            
            $order = wc_get_order($order_id);
            if (!$order || !$order->get_user_id() || $order->get_user_id() !== get_current_user_id()) {
                wp_die(__('You do not have permission to download this design.', 'print-designer-woo'));
            }
            
            $design_data = wc_get_order_item_meta($item_id, '_pdw_design_data', true);
            if (!$design_data) {
                wp_die(__('Design not found.', 'print-designer-woo'));
            }
            
            // Generate ZIP file with design assets
            $zip_file = $this->generate_design_package($design_data, $item_id);
            if (!$zip_file) {
                wp_die(__('Failed to generate design package.', 'print-designer-woo'));
            }
            
            // Send file to browser
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="design-' . $item_id . '.zip"');
            header('Content-Length: ' . filesize($zip_file));
            readfile($zip_file);
            
            // Clean up
            unlink($zip_file);
            exit;
        }
    }

    /**
     * Generate ZIP package with design assets
     */
    private function generate_design_package($design_data, $item_id) {
        $temp_dir = PDW_Config::get_temp_dir();
        $zip_file = $temp_dir . '/design-' . $item_id . '.zip';
        
        $zip = new ZipArchive();
        if ($zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }
        
        // Add design JSON
        $zip->addFromString('design.json', $design_data);
        
        // Add design preview image if available
        $mockup = wc_get_order_item_meta($item_id, '_pdw_mockup', true);
        if ($mockup) {
            $mockup_path = str_replace(PDW_Config::get_temp_url(), PDW_Config::get_temp_dir(), $mockup);
            if (file_exists($mockup_path)) {
                $zip->addFile($mockup_path, 'preview.png');
            }
        }
        
        // Add README
        $readme = "Custom Design Package\n";
        $readme .= "Generated: " . current_time('mysql') . "\n";
        $readme .= "Order Item: " . $item_id . "\n";
        $zip->addFromString('README.txt', $readme);
        
        $zip->close();
        
        return $zip_file;
    }
}