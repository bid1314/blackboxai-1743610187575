<?php
/**
 * Print Designer for WooCommerce Uninstaller
 *
 * Uninstalling Print Designer deletes user designs, settings, and cleans up the database.
 */

// Exit if uninstall not called from WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Load main plugin file to get constants
require_once 'print-designer-woo.php';

/**
 * Clean up all plugin data
 */
function pdw_uninstall() {
    global $wpdb;

    // Delete all saved designs
    $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'pdw_saved_design'");
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE post_id NOT IN (SELECT id FROM {$wpdb->posts})");

    // Delete all mockup templates
    $wpdb->query("DELETE FROM {$wpdb->posts} WHERE post_type = 'pdw_mockup'");

    // Drop custom tables
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}pdw_mockups");
    $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}pdw_presets");

    // Delete all plugin options
    delete_option('pdw_canvas_width');
    delete_option('pdw_canvas_height');
    delete_option('pdw_max_upload_size');
    delete_option('pdw_allowed_file_types');
    delete_option('pdw_enable_mockups');
    delete_option('pdw_enable_clipart');
    delete_option('pdw_enable_text_effects');
    delete_option('pdw_auto_save_interval');

    // Delete product meta
    delete_post_meta_by_key('_enable_print_designer');
    delete_post_meta_by_key('_pdw_mockup_template');

    // Clear any scheduled hooks
    wp_clear_scheduled_hooks('pdw_cleanup_temp_files');

    // Delete temporary files directory
    pdw_delete_temp_directory();

    // Clear any cached data
    wp_cache_flush();
}

/**
 * Delete temporary files directory
 */
function pdw_delete_temp_directory() {
    $upload_dir = wp_upload_dir();
    $temp_dir = $upload_dir['basedir'] . '/pdw-temp';

    if (is_dir($temp_dir)) {
        // Get all files in directory
        $files = glob($temp_dir . '/*');
        
        // Delete all files
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        
        // Remove directory
        rmdir($temp_dir);
    }
}

// Run uninstall functions
pdw_uninstall();