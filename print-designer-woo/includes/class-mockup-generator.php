<?php
/**
 * Mockup Generator
 */

class PDW_Mockup_Generator {
    private $image_editor;
    private $upload_dir;

    public function __construct() {
        $this->upload_dir = wp_upload_dir();
        add_action('pdw_generate_mockup', [$this, 'generate_mockup'], 10, 3);
    }

    /**
     * Generate mockup from design
     */
    public function generate_mockup($design_data, $product_id, $variation_id = 0) {
        try {
            // Get product mockup template
            $template = $this->get_mockup_template($product_id, $variation_id);
            if (!$template) {
                throw new Exception('Mockup template not found');
            }

            // Create temporary directory if it doesn't exist
            $temp_dir = $this->upload_dir['basedir'] . '/pdw-temp';
            if (!file_exists($temp_dir)) {
                wp_mkdir_p($temp_dir);
            }

            // Generate unique filename
            $filename = 'mockup-' . uniqid() . '.png';
            $temp_file = $temp_dir . '/' . $filename;

            // Load template image
            $this->image_editor = wp_get_image_editor($template['path']);
            if (is_wp_error($this->image_editor)) {
                throw new Exception('Failed to load template image');
            }

            // Apply design to template
            $this->apply_design($design_data, $template['placement']);

            // Save mockup
            $save_result = $this->image_editor->save($temp_file);
            if (is_wp_error($save_result)) {
                throw new Exception('Failed to save mockup');
            }

            return [
                'path' => $temp_file,
                'url' => $this->upload_dir['baseurl'] . '/pdw-temp/' . $filename
            ];

        } catch (Exception $e) {
            error_log('Mockup generation failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get mockup template for product
     */
    private function get_mockup_template($product_id, $variation_id) {
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

        $template_path = get_attached_file(get_post_thumbnail_id($template_id));
        if (!$template_path || !file_exists($template_path)) {
            return false;
        }

        return [
            'path' => $template_path,
            'placement' => get_post_meta($template_id, '_pdw_placement_data', true)
        ];
    }

    /**
     * Apply design to template
     */
    private function apply_design($design_data, $placement) {
        // Decode design data
        $design = json_decode($design_data, true);
        if (!$design) {
            throw new Exception('Invalid design data');
        }

        // Create temporary design image
        $design_image = $this->create_design_image($design);
        if (!$design_image) {
            throw new Exception('Failed to create design image');
        }

        // Apply transformations based on placement data
        $this->apply_placement_transformations($design_image, $placement);

        // Merge design with template
        $this->merge_images($design_image);

        // Cleanup
        @unlink($design_image);
    }

    /**
     * Create temporary image from design data
     */
    private function create_design_image($design) {
        try {
            // Create temporary file
            $temp_file = $this->upload_dir['basedir'] . '/pdw-temp/design-' . uniqid() . '.png';

            // Initialize canvas
            $canvas = imagecreatetruecolor($design['width'], $design['height']);
            imagesavealpha($canvas, true);
            $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
            imagefill($canvas, 0, 0, $transparent);

            // Draw design elements
            foreach ($design['objects'] as $object) {
                switch ($object['type']) {
                    case 'image':
                        $this->draw_image($canvas, $object);
                        break;
                    case 'text':
                        $this->draw_text($canvas, $object);
                        break;
                }
            }

            // Save temporary image
            imagepng($canvas, $temp_file);
            imagedestroy($canvas);

            return $temp_file;

        } catch (Exception $e) {
            error_log('Failed to create design image: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Draw image object on canvas
     */
    private function draw_image($canvas, $object) {
        // Load source image
        $source = imagecreatefromstring(file_get_contents($object['src']));
        if (!$source) {
            return;
        }

        // Apply transformations
        $source = $this->transform_image($source, $object);

        // Copy to canvas
        imagecopy(
            $canvas,
            $source,
            $object['left'],
            $object['top'],
            0,
            0,
            imagesx($source),
            imagesy($source)
        );

        imagedestroy($source);
    }

    /**
     * Draw text object on canvas
     */
    private function draw_text($canvas, $object) {
        // Load font
        $font = $this->get_font_path($object['fontFamily']);
        
        // Create text box
        $text_color = $this->hex_to_rgb($object['fill']);
        $color = imagecolorallocate($canvas, $text_color[0], $text_color[1], $text_color[2]);
        
        // Calculate text position
        $bbox = imagettfbbox($object['fontSize'], $object['angle'], $font, $object['text']);
        $x = $object['left'] - min($bbox[0], $bbox[2], $bbox[4], $bbox[6]);
        $y = $object['top'] - min($bbox[1], $bbox[3], $bbox[5], $bbox[7]);
        
        // Draw text
        imagettftext(
            $canvas,
            $object['fontSize'],
            $object['angle'],
            $x,
            $y,
            $color,
            $font,
            $object['text']
        );
    }

    /**
     * Apply placement transformations to design image
     */
    private function apply_placement_transformations($design_image, $placement) {
        $editor = wp_get_image_editor($design_image);
        if (is_wp_error($editor)) {
            return;
        }

        // Apply transformations
        if (isset($placement['rotation'])) {
            $editor->rotate($placement['rotation']);
        }

        if (isset($placement['scale'])) {
            $size = $editor->get_size();
            $new_width = $size['width'] * $placement['scale'];
            $new_height = $size['height'] * $placement['scale'];
            $editor->resize($new_width, $new_height, true);
        }

        $editor->save($design_image);
    }

    /**
     * Merge design image with template
     */
    private function merge_images($design_image) {
        $design_editor = wp_get_image_editor($design_image);
        if (is_wp_error($design_editor)) {
            return;
        }

        $design_size = $design_editor->get_size();
        $template_size = $this->image_editor->get_size();

        // Calculate position to center design
        $x = ($template_size['width'] - $design_size['width']) / 2;
        $y = ($template_size['height'] - $design_size['height']) / 2;

        // Merge images
        $this->image_editor->insert(
            $design_editor,
            ['x' => $x, 'y' => $y]
        );
    }

    /**
     * Get font file path
     */
    private function get_font_path($font_family) {
        $fonts_dir = PDW_PLUGIN_DIR . 'assets/fonts/';
        $font_file = $fonts_dir . $font_family . '.ttf';
        
        if (file_exists($font_file)) {
            return $font_file;
        }
        
        // Return default font if requested font not found
        return $fonts_dir . 'OpenSans-Regular.ttf';
    }

    /**
     * Convert hex color to RGB
     */
    private function hex_to_rgb($hex) {
        $hex = str_replace('#', '', $hex);
        
        if (strlen($hex) == 3) {
            $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
            $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
            $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        
        return [$r, $g, $b];
    }
}