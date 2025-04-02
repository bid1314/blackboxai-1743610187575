<?php
/**
 * Clipart Library Handler
 */

class PDW_Clipart_Handler {
    public function __construct() {
        // Register clipart post type and taxonomy
        add_action('init', [$this, 'register_post_type']);
        
        // Add admin menu
        add_action('admin_menu', [$this, 'add_admin_menu']);
        
        // Add custom columns to clipart list
        add_filter('manage_pdw_clipart_posts_columns', [$this, 'add_custom_columns']);
        add_action('manage_pdw_clipart_posts_custom_column', [$this, 'render_custom_columns'], 10, 2);
        
        // Add clipart metabox
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_pdw_clipart', [$this, 'save_meta_boxes']);
    }

    /**
     * Register clipart post type and taxonomy
     */
    public function register_post_type() {
        // Register clipart post type
        register_post_type('pdw_clipart', [
            'labels' => [
                'name' => __('Clipart Library', 'print-designer-woo'),
                'singular_name' => __('Clipart', 'print-designer-woo'),
                'add_new' => __('Add New', 'print-designer-woo'),
                'add_new_item' => __('Add New Clipart', 'print-designer-woo'),
                'edit_item' => __('Edit Clipart', 'print-designer-woo'),
                'new_item' => __('New Clipart', 'print-designer-woo'),
                'view_item' => __('View Clipart', 'print-designer-woo'),
                'search_items' => __('Search Clipart', 'print-designer-woo'),
                'not_found' => __('No clipart found', 'print-designer-woo'),
                'not_found_in_trash' => __('No clipart found in trash', 'print-designer-woo'),
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'supports' => ['title', 'thumbnail'],
            'capability_type' => 'post',
            'hierarchical' => false,
            'rewrite' => false,
        ]);

        // Register clipart category taxonomy
        register_taxonomy('pdw_clipart_category', 'pdw_clipart', [
            'labels' => [
                'name' => __('Categories', 'print-designer-woo'),
                'singular_name' => __('Category', 'print-designer-woo'),
                'search_items' => __('Search Categories', 'print-designer-woo'),
                'all_items' => __('All Categories', 'print-designer-woo'),
                'parent_item' => __('Parent Category', 'print-designer-woo'),
                'parent_item_colon' => __('Parent Category:', 'print-designer-woo'),
                'edit_item' => __('Edit Category', 'print-designer-woo'),
                'update_item' => __('Update Category', 'print-designer-woo'),
                'add_new_item' => __('Add New Category', 'print-designer-woo'),
                'new_item_name' => __('New Category Name', 'print-designer-woo'),
                'menu_name' => __('Categories', 'print-designer-woo'),
            ],
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => false,
        ]);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Clipart Library', 'print-designer-woo'),
            __('Clipart Library', 'print-designer-woo'),
            'manage_woocommerce',
            'edit.php?post_type=pdw_clipart'
        );
    }

    /**
     * Add custom columns
     */
    public function add_custom_columns($columns) {
        $new_columns = [];
        foreach ($columns as $key => $value) {
            if ($key === 'title') {
                $new_columns[$key] = $value;
                $new_columns['preview'] = __('Preview', 'print-designer-woo');
            } else {
                $new_columns[$key] = $value;
            }
        }
        return $new_columns;
    }

    /**
     * Render custom columns
     */
    public function render_custom_columns($column, $post_id) {
        switch ($column) {
            case 'preview':
                if (has_post_thumbnail($post_id)) {
                    echo get_the_post_thumbnail($post_id, [50, 50]);
                }
                break;
        }
    }

    /**
     * Add meta boxes
     */
    public function add_meta_boxes() {
        add_meta_box(
            'pdw_clipart_settings',
            __('Clipart Settings', 'print-designer-woo'),
            [$this, 'render_settings_meta_box'],
            'pdw_clipart',
            'normal',
            'high'
        );
    }

    /**
     * Render settings meta box
     */
    public function render_settings_meta_box($post) {
        // Add nonce field
        wp_nonce_field('pdw_clipart_settings', 'pdw_clipart_nonce');

        // Get current values
        $price = get_post_meta($post->ID, '_pdw_clipart_price', true);
        $tags = get_post_meta($post->ID, '_pdw_clipart_tags', true);
        ?>
        <div class="pdw-meta-box-content">
            <p>
                <label for="pdw_clipart_price">
                    <?php _e('Price', 'print-designer-woo'); ?>:
                </label>
                <input type="number" 
                       id="pdw_clipart_price" 
                       name="pdw_clipart_price" 
                       value="<?php echo esc_attr($price); ?>"
                       step="0.01"
                       min="0">
                <span class="description">
                    <?php _e('Additional cost when this clipart is used in a design. Leave empty for free.', 'print-designer-woo'); ?>
                </span>
            </p>

            <p>
                <label for="pdw_clipart_tags">
                    <?php _e('Search Tags', 'print-designer-woo'); ?>:
                </label>
                <input type="text" 
                       id="pdw_clipart_tags" 
                       name="pdw_clipart_tags" 
                       value="<?php echo esc_attr($tags); ?>"
                       class="large-text">
                <span class="description">
                    <?php _e('Comma-separated list of search tags.', 'print-designer-woo'); ?>
                </span>
            </p>

            <div class="pdw-clipart-preview">
                <h4><?php _e('Preview', 'print-designer-woo'); ?></h4>
                <?php 
                if (has_post_thumbnail($post->ID)) {
                    echo get_the_post_thumbnail($post->ID, 'medium');
                } else {
                    _e('Set featured image to add clipart preview', 'print-designer-woo');
                }
                ?>
            </div>
        </div>

        <style>
            .pdw-meta-box-content label {
                display: inline-block;
                width: 100px;
                font-weight: bold;
            }
            .pdw-meta-box-content .description {
                display: block;
                margin: 5px 0 15px 100px;
                color: #666;
                font-style: italic;
            }
            .pdw-clipart-preview {
                margin-top: 20px;
                padding-top: 20px;
                border-top: 1px solid #ddd;
            }
            .pdw-clipart-preview img {
                max-width: 200px;
                height: auto;
            }
        </style>
        <?php
    }

    /**
     * Save meta box data
     */
    public function save_meta_boxes($post_id) {
        // Check nonce
        if (
            !isset($_POST['pdw_clipart_nonce']) ||
            !wp_verify_nonce($_POST['pdw_clipart_nonce'], 'pdw_clipart_settings')
        ) {
            return;
        }

        // Check autosave
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        // Save price
        if (isset($_POST['pdw_clipart_price'])) {
            $price = floatval($_POST['pdw_clipart_price']);
            update_post_meta($post_id, '_pdw_clipart_price', $price);
        }

        // Save tags
        if (isset($_POST['pdw_clipart_tags'])) {
            $tags = sanitize_text_field($_POST['pdw_clipart_tags']);
            update_post_meta($post_id, '_pdw_clipart_tags', $tags);
        }
    }

    /**
     * Get clipart items
     */
    public static function get_clipart_items($args = []) {
        $defaults = [
            'category' => '',
            'search' => '',
            'per_page' => 20,
            'page' => 1
        ];

        $args = wp_parse_args($args, $defaults);

        $query_args = [
            'post_type' => 'pdw_clipart',
            'posts_per_page' => $args['per_page'],
            'paged' => $args['page'],
            'orderby' => 'title',
            'order' => 'ASC'
        ];

        if ($args['category']) {
            $query_args['tax_query'] = [
                [
                    'taxonomy' => 'pdw_clipart_category',
                    'field' => 'slug',
                    'terms' => $args['category']
                ]
            ];
        }

        if ($args['search']) {
            $query_args['s'] = $args['search'];
        }

        $query = new WP_Query($query_args);
        $items = [];

        foreach ($query->posts as $post) {
            $items[] = self::format_clipart_item($post);
        }

        return [
            'items' => $items,
            'total' => $query->found_posts,
            'pages' => $query->max_num_pages
        ];
    }

    /**
     * Format clipart item for API response
     */
    private static function format_clipart_item($post) {
        return [
            'id' => $post->ID,
            'title' => $post->post_title,
            'url' => wp_get_attachment_url(get_post_thumbnail_id($post->ID)),
            'price' => floatval(get_post_meta($post->ID, '_pdw_clipart_price', true)),
            'tags' => get_post_meta($post->ID, '_pdw_clipart_tags', true),
            'categories' => wp_get_post_terms($post->ID, 'pdw_clipart_category', ['fields' => 'names'])
        ];
    }

    /**
     * Get clipart categories
     */
    public static function get_categories() {
        $terms = get_terms([
            'taxonomy' => 'pdw_clipart_category',
            'hide_empty' => true
        ]);

        $categories = [];
        foreach ($terms as $term) {
            $categories[] = [
                'id' => $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'count' => $term->count
            ];
        }

        return $categories;
    }
}