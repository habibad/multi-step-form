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
        
        // Get payment gateway
        $payment_gateway = get_option('msf_payment_gateway', 'stripe');
        
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
        $service_start_time = sanitize_text_field($_POST['service_start_time']);
        $service_end_time = sanitize_text_field($_POST['service_end_time']);
        $square_footage = intval($_POST['square_footage']);
        $workers = intval($_POST['workers']);
        $addon_oven = isset($_POST['addon_oven']) ? 1 : 0;
        $addon_fridge = isset($_POST['addon_fridge']) ? 1 : 0;
        $base_price = floatval($_POST['base_price']);
        $addon_price = floatval($_POST['addon_price']);
        $total_price = floatval($_POST['total_price']);
        
        // Validate time range (7 AM to 8 PM)
        $start_hour = intval(date('H', strtotime($service_start_time)));
        $end_hour = intval(date('H', strtotime($service_end_time)));
        
        if ($start_hour < 7 || $start_hour > 20 || $end_hour < 7 || $end_hour > 20) {
            wp_send_json_error(array('message' => 'Service time must be between 7:00 AM and 8:00 PM.'));
        }
        
        if (strtotime($service_end_time) <= strtotime($service_start_time)) {
            wp_send_json_error(array('message' => 'End time must be after start time.'));
        }
        
        // Image upload handling removed (functionality disabled)
        
        // Process payment based on selected gateway
        if ($payment_gateway === 'quickbooks') {
            $this->process_quickbooks_payment($first_name, $last_name, $phone, $email, $street, $city, $zipcode, 
                                             $cleaning_type, $service_date, $service_start_time, $service_end_time,
                                             $square_footage, $workers, $addon_oven, $addon_fridge, 
                                             $base_price, $addon_price, $total_price);
        } else {
            $this->process_stripe_payment($first_name, $last_name, $phone, $email, $street, $city, $zipcode, 
                                         $cleaning_type, $service_date, $service_start_time, $service_end_time,
                                         $square_footage, $workers, $addon_oven, $addon_fridge, 
                                         $base_price, $addon_price, $total_price);
        }
    }
    
    private function process_stripe_payment($first_name, $last_name, $phone, $email, $street, $city, $zipcode,
                                           $cleaning_type, $service_date, $service_start_time, $service_end_time,
                                           $square_footage, $workers, $addon_oven, $addon_fridge,
                                           $base_price, $addon_price, $total_price) {
        global $wpdb;
        
        $payment_method_id = sanitize_text_field($_POST['payment_method_id']);
        
        // Process Stripe payment
        $stripe_secret_key = get_option('msf_stripe_secret_key');
        
        if (empty($stripe_secret_key)) {
            wp_send_json_error(array('message' => 'Stripe is not configured. Please contact the administrator.'));
        }
        
        try {
            // Initialize Stripe
            require_once MSF_PLUGIN_DIR . 'includes/stripe-php/init.php';
            \Stripe\Stripe::setApiKey($stripe_secret_key);
            
            // Create payment intent
            $payment_intent = \Stripe\PaymentIntent::create([
                'amount' => $total_price * 100, // Stripe uses cents
                'currency' => 'usd',
                'payment_method' => $payment_method_id,
                'confirmation_method' => 'manual',
                'confirm' => true,
                'return_url' => home_url(),
                'description' => 'Cleaning service booking - ' . $cleaning_type,
                'metadata' => [
                    'customer_name' => $first_name . ' ' . $last_name,
                    'customer_email' => $email,
                    'cleaning_type' => $cleaning_type,
                    'square_footage' => $square_footage
                ]
            ]);
            
            if ($payment_intent->status === 'succeeded' || $payment_intent->status === 'requires_action') {
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
                        'payment_intent_id' => $payment_intent->id
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
                        'service_start_time' => $service_start_time,
                        'service_end_time' => $service_end_time,
                        'total_price' => $total_price
                    ));
                    
                    if ($payment_intent->status === 'requires_action') {
                        wp_send_json_success(array(
                            'requires_action' => true,
                            'payment_intent_client_secret' => $payment_intent->client_secret
                        ));
                    } else {
                        wp_send_json_success(array('message' => 'Payment successful!'));
                    }
                } else {
                    wp_send_json_error(array('message' => 'Failed to save submission.'));
                }
            } else {
                wp_send_json_error(array('message' => 'Payment failed. Please try again.'));
            }
            
        } catch (\Stripe\Exception\CardException $e) {
            wp_send_json_error(array('message' => $e->getError()->message));
        } catch (Exception $e) {
            wp_send_json_error(array('message' => 'An error occurred: ' . $e->getMessage()));
        }
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
                    'service_start_time' => $service_start_time,
                    'service_end_time' => $service_end_time,
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