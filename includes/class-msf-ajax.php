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
        
        // Process QuickBooks payment
        $this->process_quickbooks_payment($first_name, $last_name, $phone, $email, $street, $city, $zipcode, 
                                          $cleaning_type, $service_date, $service_start_time, $service_end_time,
                                          $square_footage, $workers, $addon_oven, $addon_fridge, 
                                          $base_price, $addon_price, $total_price);
    }
    
    private function process_quickbooks_payment($first_name, $last_name, $phone, $email, $street, $city, $zipcode,
                                               $cleaning_type, $service_date, $service_start_time, $service_end_time,
                                               $square_footage, $workers, $addon_oven, $addon_fridge,
                                               $base_price, $addon_price, $total_price) {
        global $wpdb;
        
        // Get QuickBooks payment data
        $card_number = preg_replace('/\D/', '', $_POST['qbo_card_number']);
        $card_exp = sanitize_text_field($_POST['qbo_card_exp']);
        $card_cvc = sanitize_text_field($_POST['qbo_card_cvc']);
        $billing_address = sanitize_text_field($_POST['qbo_billing_address']);
        
        // Parse expiry
        $exp_parts = explode('/', $card_exp);
        if (count($exp_parts) !== 2) {
            wp_send_json_error(array('message' => 'Invalid Expiry Date format. Use MM/YYYY.'));
        }
        
        $exp_month = trim($exp_parts[0]);
        $exp_year = trim($exp_parts[1]);
        
        // Normalize year to 4 digits
        if (strlen($exp_year) === 2) {
            $exp_year = '20' . $exp_year;
        }
        
        // Prepare payment data
        $payment_data = array(
            'amount' => $total_price,
            'card_number' => $card_number,
            'exp_month' => $exp_month,
            'exp_year' => $exp_year,
            'cvc' => $card_cvc,
            'card_holder_name' => $first_name . ' ' . $last_name,
            'zip_code' => $zipcode,
            'billing_address' => $billing_address,
            'customer_name' => $first_name . ' ' . $last_name,
            'customer_email' => $email,
            'cleaning_type' => $cleaning_type
        );
        
        // Process QuickBooks payment
        $result = MSF_QuickBooks::process_payment($payment_data);
        
        if ($result['success']) {
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
                    'total_price' => $total_price,
                    'payment_status' => 'completed',
                    'payment_intent_id' => $result['transaction_id']
                ),
                array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%f', '%f', '%f', '%s', '%s')
            );
            
            if ($inserted) {
                // Send emails
                $email_handler = new MSF_Email();
                $email_handler->send_admin_notification($wpdb->insert_id);
                $email_handler->send_user_confirmation($email, array(
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'cleaning_type' => $cleaning_type,
                    'service_date' => $service_date,
                    'total_price' => $total_price
                ));
                
                wp_send_json_success(array('message' => 'Payment successful!'));
            } else {
                wp_send_json_error(array('message' => 'Failed to save submission.'));
            }
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }
}