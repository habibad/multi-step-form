<?php

/**
 * Plugin Name: Service Form
 * Plugin URI: https://example.com
 * Description: A multistep service booking form with live preview
 * Version: 1.0.0
 * Author: Md Anikur Rahman
 * Author URI: https://www.linkedin.com/in/anikur-rahman/
 * Contributors: S M Masrafi (https://www.linkedin.com/in/masrafi000/)
 * License: GPL v2 or later
 * Text Domain: multistep-form
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('MSF_VERSION', '1.0.0');
define('MSF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MSF_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MSF_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Include Composer autoloader if present
$msf_autoload = MSF_PLUGIN_DIR . 'includes/vendor/autoload.php';
if (file_exists($msf_autoload)) {
    require_once $msf_autoload;
}

// Include required files
require_once MSF_PLUGIN_DIR . 'includes/class-msf-activator.php';
require_once MSF_PLUGIN_DIR . 'includes/class-msf-admin.php';
require_once MSF_PLUGIN_DIR . 'includes/class-msf-shortcode.php';
require_once MSF_PLUGIN_DIR . 'includes/class-msf-ajax.php';
require_once MSF_PLUGIN_DIR . 'includes/class-msf-email.php';

// Activation hook
register_activation_hook(__FILE__, array('MSF_Activator', 'activate'));

// Initialize the plugin
function msf_init()
{
    // Initialize admin
    if (is_admin()) {
        new MSF_Admin();
    }

    // Initialize shortcode
    new MSF_Shortcode();

    // Initialize AJAX handlers
    new MSF_Ajax();
}
// Async email handler hook
add_action('msf_async_send_emails', array('MSF_Email', 'handle_cron_emails'));

add_action('plugins_loaded', 'msf_init');

// Enqueue scripts and styles
function msf_enqueue_scripts()
{
    // Toastify
    wp_enqueue_style('toastify-css', 'https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css', array(), '1.12.0');
    wp_enqueue_script('toastify-js', 'https://cdn.jsdelivr.net/npm/toastify-js', array(), '1.12.0', true);

    wp_enqueue_style('msf-styles', MSF_PLUGIN_URL . 'assets/css/style.css', array(), MSF_VERSION);
    wp_enqueue_script('msf-script', MSF_PLUGIN_URL . 'assets/js/script.js', array('jquery', 'toastify-js'), MSF_VERSION, true);

    // Localize script with AJAX URL and nonce
    wp_localize_script('msf-script', 'msfAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('msf_nonce'),
        'stripeKey' => get_option('msf_stripe_publishable_key', '')
    ));
}
add_action('wp_enqueue_scripts', 'msf_enqueue_scripts');
