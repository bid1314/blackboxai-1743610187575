<?php
/**
 * Admin Settings Handler
 */

class PDW_Admin_Settings {
    private $settings_page = 'pdw-settings';
    private $option_group = 'pdw_options';

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_init', [$this, 'init_settings']);
    }

    /**
     * Add admin menu items
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Print Designer Settings', 'print-designer-woo'),
            __('Print Designer', 'print-designer-woo'),
            'manage_woocommerce',
            $this->settings_page,
            [$this, 'render_settings_page']
        );
    }

    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting($this->option_group, 'pdw_canvas_width');
        register_setting($this->option_group, 'pdw_canvas_height');
        register_setting($this->option_group, 'pdw_max_upload_size');
        register_setting($this->option_group, 'pdw_allowed_file_types');
        register_setting($this->option_group, 'pdw_enable_mockups');
        register_setting($this->option_group, 'pdw_enable_clipart');
        register_setting($this->option_group, 'pdw_enable_text_effects');
        register_setting($this->option_group, 'pdw_auto_save_interval');

        // General Settings
        add_settings_section(
            'pdw_general_settings',
            __('General Settings', 'print-designer-woo'),
            [$this, 'render_general_section'],
            $this->settings_page
        );

        // Canvas Settings
        add_settings_field(
            'pdw_canvas_dimensions',
            __('Canvas Dimensions', 'print-designer-woo'),
            [$this, 'render_canvas_dimensions'],
            $this->settings_page,
            'pdw_general_settings'
        );

        // Upload Settings
        add_settings_field(
            'pdw_upload_settings',
            __('Upload Settings', 'print-designer-woo'),
            [$this, 'render_upload_settings'],
            $this->settings_page,
            'pdw_general_settings'
        );

        // Feature Settings
        add_settings_field(
            'pdw_feature_settings',
            __('Features', 'print-designer-woo'),
            [$this, 'render_feature_settings'],
            $this->settings_page,
            'pdw_general_settings'
        );

        // Auto-save Settings
        add_settings_field(
            'pdw_autosave_settings',
            __('Auto-save', 'print-designer-woo'),
            [$this, 'render_autosave_settings'],
            $this->settings_page,
            'pdw_general_settings'
        );
    }

    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <form action="options.php" method="post">
                <?php
                settings_fields($this->option_group);
                do_settings_sections($this->settings_page);
                submit_button();
                ?>
            </form>

            <hr>

            <h2><?php _e('Mockup Templates', 'print-designer-woo'); ?></h2>
            <div class="pdw-mockup-templates">
                <?php $this->render_mockup_templates(); ?>
            </div>

            <h2><?php _e('System Information', 'print-designer-woo'); ?></h2>
            <div class="pdw-system-info">
                <?php $this->render_system_info(); ?>
            </div>
        </div>

        <style>
            .pdw-field-group {
                margin-bottom: 20px;
            }
            .pdw-field-group label {
                display: block;
                margin-bottom: 5px;
                font-weight: 600;
            }
            .pdw-field-group input[type="number"] {
                width: 100px;
            }
            .pdw-field-group .description {
                color: #666;
                font-style: italic;
                margin-top: 5px;
            }
            .pdw-mockup-templates {
                margin: 20px 0;
                padding: 20px;
                background: #fff;
                border: 1px solid #ccd0d4;
            }
            .pdw-system-info {
                margin: 20px 0;
                padding: 20px;
                background: #fff;
                border: 1px solid #ccd0d4;
            }
            .pdw-system-info table {
                width: 100%;
                border-collapse: collapse;
            }
            .pdw-system-info th,
            .pdw-system-info td {
                padding: 10px;
                border: 1px solid #eee;
            }
            .pdw-system-info th {
                width: 30%;
                background: #f8f9fa;
            }
        </style>
        <?php
    }

    /**
     * Render general section description
     */
    public function render_general_section() {
        echo '<p>' . __('Configure the main settings for the Print Designer plugin.', 'print-designer-woo') . '</p>';
    }

    /**
     * Render canvas dimensions fields
     */
    public function render_canvas_dimensions() {
        $width = get_option('pdw_canvas_width', 600);
        $height = get_option('pdw_canvas_height', 800);
        ?>
        <div class="pdw-field-group">
            <label><?php _e('Width (px)', 'print-designer-woo'); ?></label>
            <input type="number" name="pdw_canvas_width" value="<?php echo esc_attr($width); ?>" min="100" max="2000">
        </div>
        <div class="pdw-field-group">
            <label><?php _e('Height (px)', 'print-designer-woo'); ?></label>
            <input type="number" name="pdw_canvas_height" value="<?php echo esc_attr($height); ?>" min="100" max="2000">
        </div>
        <p class="description">
            <?php _e('Set the default canvas dimensions for the designer.', 'print-designer-woo'); ?>
        </p>
        <?php
    }

    /**
     * Render upload settings fields
     */
    public function render_upload_settings() {
        $max_size = get_option('pdw_max_upload_size', 5);
        $allowed_types = get_option('pdw_allowed_file_types', ['jpg', 'jpeg', 'png', 'svg']);
        if (!is_array($allowed_types)) {
            $allowed_types = explode(',', $allowed_types);
        }
        ?>
        <div class="pdw-field-group">
            <label><?php _e('Max Upload Size (MB)', 'print-designer-woo'); ?></label>
            <input type="number" name="pdw_max_upload_size" value="<?php echo esc_attr($max_size); ?>" min="1" max="50">
        </div>
        <div class="pdw-field-group">
            <label><?php _e('Allowed File Types', 'print-designer-woo'); ?></label>
            <div>
                <label>
                    <input type="checkbox" name="pdw_allowed_file_types[]" value="jpg" 
                        <?php checked(in_array('jpg', $allowed_types)); ?>>
                    JPG
                </label>
                <label>
                    <input type="checkbox" name="pdw_allowed_file_types[]" value="jpeg" 
                        <?php checked(in_array('jpeg', $allowed_types)); ?>>
                    JPEG
                </label>
                <label>
                    <input type="checkbox" name="pdw_allowed_file_types[]" value="png" 
                        <?php checked(in_array('png', $allowed_types)); ?>>
                    PNG
                </label>
                <label>
                    <input type="checkbox" name="pdw_allowed_file_types[]" value="svg" 
                        <?php checked(in_array('svg', $allowed_types)); ?>>
                    SVG
                </label>
            </div>
        </div>
        <?php
    }

    /**
     * Render feature settings fields
     */
    public function render_feature_settings() {
        $enable_mockups = get_option('pdw_enable_mockups', true);
        $enable_clipart = get_option('pdw_enable_clipart', true);
        $enable_text_effects = get_option('pdw_enable_text_effects', true);
        ?>
        <div class="pdw-field-group">
            <label>
                <input type="checkbox" name="pdw_enable_mockups" value="1" 
                    <?php checked($enable_mockups); ?>>
                <?php _e('Enable Mockup Generation', 'print-designer-woo'); ?>
            </label>
        </div>
        <div class="pdw-field-group">
            <label>
                <input type="checkbox" name="pdw_enable_clipart" value="1" 
                    <?php checked($enable_clipart); ?>>
                <?php _e('Enable Clipart Library', 'print-designer-woo'); ?>
            </label>
        </div>
        <div class="pdw-field-group">
            <label>
                <input type="checkbox" name="pdw_enable_text_effects" value="1" 
                    <?php checked($enable_text_effects); ?>>
                <?php _e('Enable Text Effects', 'print-designer-woo'); ?>
            </label>
        </div>
        <?php
    }

    /**
     * Render auto-save settings fields
     */
    public function render_autosave_settings() {
        $interval = get_option('pdw_auto_save_interval', 5);
        ?>
        <div class="pdw-field-group">
            <label><?php _e('Auto-save Interval (minutes)', 'print-designer-woo'); ?></label>
            <input type="number" name="pdw_auto_save_interval" value="<?php echo esc_attr($interval); ?>" min="1" max="60">
            <p class="description">
                <?php _e('Set to 0 to disable auto-save.', 'print-designer-woo'); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Render mockup templates section
     */
    private function render_mockup_templates() {
        $templates = get_posts([
            'post_type' => 'pdw_mockup',
            'posts_per_page' => -1,
        ]);

        if (empty($templates)) {
            echo '<p>' . __('No mockup templates found.', 'print-designer-woo') . '</p>';
            return;
        }

        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th>' . __('Template', 'print-designer-woo') . '</th>';
        echo '<th>' . __('Preview', 'print-designer-woo') . '</th>';
        echo '<th>' . __('Actions', 'print-designer-woo') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($templates as $template) {
            $preview_url = get_the_post_thumbnail_url($template->ID, 'thumbnail');
            echo '<tr>';
            echo '<td>' . esc_html($template->post_title) . '</td>';
            echo '<td>' . ($preview_url ? '<img src="' . esc_url($preview_url) . '" style="max-width: 100px;">' : '') . '</td>';
            echo '<td>';
            echo '<a href="' . esc_url(get_edit_post_link($template->ID)) . '" class="button">' . __('Edit', 'print-designer-woo') . '</a> ';
            echo '<a href="' . esc_url(get_delete_post_link($template->ID)) . '" class="button" onclick="return confirm(\'' . __('Are you sure?', 'print-designer-woo') . '\')">' . __('Delete', 'print-designer-woo') . '</a>';
            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    /**
     * Render system information
     */
    private function render_system_info() {
        global $wpdb;
        ?>
        <table>
            <tr>
                <th><?php _e('WordPress Version', 'print-designer-woo'); ?></th>
                <td><?php echo get_bloginfo('version'); ?></td>
            </tr>
            <tr>
                <th><?php _e('WooCommerce Version', 'print-designer-woo'); ?></th>
                <td><?php echo WC()->version; ?></td>
            </tr>
            <tr>
                <th><?php _e('PHP Version', 'print-designer-woo'); ?></th>
                <td><?php echo PHP_VERSION; ?></td>
            </tr>
            <tr>
                <th><?php _e('MySQL Version', 'print-designer-woo'); ?></th>
                <td><?php echo $wpdb->db_version(); ?></td>
            </tr>
            <tr>
                <th><?php _e('Max Upload Size', 'print-designer-woo'); ?></th>
                <td><?php echo size_format(wp_max_upload_size()); ?></td>
            </tr>
            <tr>
                <th><?php _e('Memory Limit', 'print-designer-woo'); ?></th>
                <td><?php echo WP_MEMORY_LIMIT; ?></td>
            </tr>
            <tr>
                <th><?php _e('GD/Imagick', 'print-designer-woo'); ?></th>
                <td>
                    <?php 
                    if (extension_loaded('gd')) {
                        echo 'GD (' . gdversion() . ')';
                    }
                    if (extension_loaded('imagick')) {
                        $imagick = new Imagick();
                        echo 'Imagick (' . $imagick->getVersion()['versionString'] . ')';
                    }
                    ?>
                </td>
            </tr>
        </table>
        <?php
    }
}