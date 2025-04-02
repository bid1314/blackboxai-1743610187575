# Print Designer for WooCommerce

A powerful product customization plugin that allows customers to design and personalize products in WooCommerce stores.

## Features

- Interactive design interface using Fabric.js
- Text customization with various fonts and effects
- Image upload and manipulation
- Clipart library
- Product mockup generation
- Design saving and loading
- Integration with WooCommerce products and variations
- Design data stored with orders
- Responsive design interface
- Admin configuration panel

## Requirements

- WordPress 5.8 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- GD or Imagick PHP extension for image processing

## Installation

1. Download the plugin zip file
2. Go to WordPress admin > Plugins > Add New > Upload Plugin
3. Upload the zip file and activate the plugin
4. Go to WooCommerce > Print Designer to configure settings

## Usage

### For Store Owners

1. Enable the designer for products:
   - Edit a product in WooCommerce
   - Go to the "Print Designer" tab
   - Check "Enable Print Designer"
   - Select a mockup template if desired
   - Save the product

2. Configure designer settings:
   - Go to WooCommerce > Print Designer
   - Set canvas dimensions
   - Configure upload settings
   - Enable/disable features
   - Manage mockup templates

### For Customers

1. Visit a product page with Print Designer enabled
2. Click "Start Designing" or "Customize"
3. Use the design tools:
   - Add text with custom fonts and colors
   - Upload images
   - Add clipart
   - Arrange and transform elements
4. Save designs for later use
5. Add customized product to cart

## Development

### File Structure

```
print-designer-woo/
├── assets/
│   ├── css/
│   │   └── designer.css
│   └── js/
│       └── designer.js
├── includes/
│   ├── class-admin-settings.php
│   ├── class-design-storage.php
│   ├── class-designer-interface.php
│   ├── class-initializer.php
│   └── class-mockup-generator.php
├── languages/
├── print-designer-woo.php
└── README.md
```

### Hooks and Filters

The plugin provides several hooks and filters for customization:

```php
// Filter design data before saving
add_filter('pdw_before_save_design', function($design_data, $product_id) {
    // Modify design data
    return $design_data;
}, 10, 2);

// Action after design is saved
add_action('pdw_after_save_design', function($design_id, $product_id) {
    // Do something with saved design
}, 10, 2);

// Filter mockup generation settings
add_filter('pdw_mockup_settings', function($settings, $product_id) {
    // Modify mockup settings
    return $settings;
}, 10, 2);
```

### Extending the Designer

The designer interface can be extended with custom tools and features:

```php
// Add custom tool
add_filter('pdw_designer_tools', function($tools) {
    $tools['my_tool'] = [
        'icon' => 'fas fa-star',
        'label' => 'My Tool',
        'action' => 'myCustomFunction'
    ];
    return $tools;
});

// Add custom JavaScript
add_action('pdw_after_designer_init', function() {
    ?>
    <script>
    function myCustomFunction() {
        // Custom tool functionality
    }
    </script>
    <?php
});
```

## Support

For support, bug reports, and feature requests:
- Create an issue on GitHub
- Contact support at support@example.com

## License

This plugin is licensed under the GPL v2 or later.

```
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
```

## Credits

- Built with [Fabric.js](http://fabricjs.com/)
- Icons by [Font Awesome](https://fontawesome.com/)
- Original concept inspired by [print-designer](https://github.com/lmanukyan/print-designer)