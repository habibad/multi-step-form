<?php
/**
 * File Path: includes/class-msf-ajax.php
 * AJAX Request Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class MSF_Ajax {
    
    public function __construct() {
        add_action('wp_ajax_msf_process_payment', array($this, 'process_payment'));
        add_action('wp_ajax_nopriv_msf_process_payment', array($this, 'process_payment'));
    }
    
    public function process_payment() {
        check_ajax_referer('msf_nonce', 'nonce');
        
        global $wpdb;
        
        // Get form data
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $phone = sanitize_text_field($_POST['phone']);
        $email = sanitize_email($_POST['email']);
        $street = sanitize_text_field($_POST['street']);
        $city = sanitize_text_field($_POST['city']);
        $zipcode = sanitize_text_field($_POST['zipcode']);
        $cleaning_type = sanitize_text_field($_POST['cleaning_type']);
        $service_date = sanitize_text_field($_POST['service_date']);
        
        // Use dummy times since we removed start/end time from the form
        // but the database schema still requires them (NOT NULL).
        $service_start_time = '00:00:00';
        $service_end_time = '00:00:00';
        
        $square_footage = intval($_POST['square_footage']);
        $workers = intval($_POST['workers']);
        $addon_oven = isset($_POST['addon_oven']) ? 1 : 0;
        $addon_fridge = isset($_POST['addon_fridge']) ? 1 : 0;
        $base_price = floatval($_POST['base_price']);
        $addon_price = floatval($_POST['addon_price']);
        $total_price = floatval($_POST['total_price']);
        
        // Save to database
        $table_name = $wpdb->prefix . 'msf_submissions';
        $inserted = $wpdb->insert(
            $table_name,
            array(
                'first_name' => $first_name,
                'last_name' => $last_name,
                'phone' => $phone,
                'email' => $email,
                'street' => $street,
                'city' => $city,
                'zipcode' => $zipcode,
                'cleaning_type' => $cleaning_type,
                'service_date' => $service_date,
                'service_start_time' => $service_start_time,
                'service_end_time' => $service_end_time,
                'square_footage' => $square_footage,
                'workers' => $workers,
                'addon_oven' => $addon_oven,
                'addon_fridge' => $addon_fridge,
                'base_price' => $base_price,
                'addon_price' => $addon_price,
                'total_price' => $total_price
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%f', '%f', '%f')
        );
        
        if ($inserted) {
            // Schedule async email sending (improves performance)
            wp_schedule_single_event(time(), 'msf_async_send_emails', array($wpdb->insert_id));
            
            wp_send_json_success(array('message' => 'Booking successful!'));
        } else {
            wp_send_json_error(array('message' => 'Failed to save submission.'));
        }
    }
}