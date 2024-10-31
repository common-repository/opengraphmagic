<?php
/*
Plugin Name: OpenGraphMagic
Plugin URI:  https://opengraphmagic.com/
Description: OpenGraphMagic is a WordPress plugin designed to automatically generate stunning featured images and OpenGraph social images for posts and pages. These images will be screenshots of the respective posts and pages, cached in a directory with a specified Time-To-Live (TTL) to optimize performance and save resources.
Version:     1.0.5
Tags: seo, opengraph, social, ogp
Requires at least: 5.4
Tested up to: 6.6.2
Requires PHP: 7.4
Author:      50Saas, LLC
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Copyright (c)2024 50SAAS LLC. All rights reserved.
Redistribution and use in source and binary forms, with or without modification, are not permitted.
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Create the image folder
function opengraphmagic_create_images_folder()
{
    $uploads_dir = trailingslashit(wp_upload_dir()['basedir']) . 'opengraphmagic-images';
    return wp_mkdir_p($uploads_dir);
}

// Plugin activation tasks
function opengraphmagic_plugin_activate()
{
    // Create the image folder
    opengraphmagic_create_images_folder();
    if (!get_option('opengraphmagic_rewrite_rules_flag')) {
        add_option('opengraphmagic_rewrite_rules_flag', 1);
    }

    // Mark the plugin as activated
    add_option('opengraphmagic_activated', true);

    // Set an option to trigger the redirect
    add_option('opengraphmagic_show_thank_you', true);
}

// Defines the function opengraphmagic_add_endpoint which handles specific plugin tasks
function opengraphmagic_add_endpoint()
{
    add_rewrite_tag('%opengraphmagic_post_id%', '([^&]+)');
    add_rewrite_rule('^og-image/([0-9]+)/?$', 'index.php?opengraphmagic_post_id=$matches[1]', 'top');
    if (get_option('opengraphmagic_rewrite_rules_flag') == 1) {
        flush_rewrite_rules(false);
        delete_option('opengraphmagic_rewrite_rules_flag');
    }
}

// Performs plugin init tasks
function opengraphmagic_init(): void
{
    // Require necessary files
    require_once "includes/admin-form.php";
    require_once "includes/image-generators/image-generator-contract.php";
    require_once "includes/image-generators/screenshot-one.php";
    require_once "includes/image-generators/pikwy.php";
    require_once "includes/opengraph-image.php";
}

// Redirect to thank you page after plugin activation
function opengraphmagic_redirect_to_thank_you_page()
{
    if (get_option('opengraphmagic_show_thank_you', false)) {
        delete_option('opengraphmagic_show_thank_you');
        wp_redirect(admin_url('admin.php?page=opengraphmagic-thank-you'));
        exit;
    }
}

// Add "Settings" link just below the plugin name in the plugins list
function opengraphmagic_add_settings_link($links)
{
    $settings_link = '<a href="' . admin_url('admin.php?page=opengraph-magic') . '">' . __('Settings') . '</a>';
    array_push($links, $settings_link);
    return $links;
}

// Hook into the admin menu to register your 'thank you' page:
function opengraphmagic_register_thank_you_page()
{
    add_menu_page(
        'OpenGraphMagic Thank You',
        '',  // Set to an empty string, but this alone may not be enough
        'manage_options',
        'opengraphmagic-thank-you',
        'opengraphmagic_display_thank_you_page'
    );

    // Remove the menu item to hide it from the admin menu
    remove_menu_page('opengraphmagic-thank-you');
}

// Display the thank you page
function opengraphmagic_display_thank_you_page()
{
    include plugin_dir_path(__FILE__) . 'thank-you.php';
}

//----------------------------------------------

// Initializes plugin functionalities
add_action('plugins_loaded', 'opengraphmagic_init', 11);

// Registers the activation hook
register_activation_hook(__FILE__, 'opengraphmagic_plugin_activate');

// Register the init hook
add_action('init', 'opengraphmagic_add_endpoint');

// Redirect to thank you page after plugin activation
add_action('admin_init', 'opengraphmagic_redirect_to_thank_you_page');

// Add "Settings" link just below the plugin name in the plugins list
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'opengraphmagic_add_settings_link');

// Hook into the admin menu to register your 'thank you' page:
add_action('admin_menu', 'opengraphmagic_register_thank_you_page');