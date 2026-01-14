<?php
/**
 * File Path: includes/class-msf-activator.php
 * Plugin Activation Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class MSF_Activator {
    
    public static function activate() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        $table_name = $wpdb->prefix . 'msf_submissions';
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            first_name varchar(100) NOT NULL,
            last_name varchar(100) NOT NULL,
            phone varchar(20) NOT NULL,
            email varchar(100) NOT NULL,
            street varchar(100) NOT NULL,
            city varchar(100) NOT NULL,
            zipcode varchar(20) NOT NULL,
            -- images longtext,
            cleaning_type varchar(100) NOT NULL,
            service_date date NOT NULL,
            service_start_time time NOT NULL,
            service_end_time time NOT NULL,
            square_footage int(11) NOT NULL,
            workers int(11) NOT NULL,
            addon_oven tinyint(1) DEFAULT 0,
            addon_fridge tinyint(1) DEFAULT 0,
            base_price decimal(10,2) NOT NULL,
            addon_price decimal(10,2) DEFAULT 0,
            total_price decimal(10,2) NOT NULL,
            payment_status varchar(50) DEFAULT 'pending',
            payment_intent_id varchar(255),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
        
        // Set default options
        if (!get_option('msf_stripe_publishable_key')) {
            add_option('msf_stripe_publishable_key', '');
        }
        if (!get_option('msf_stripe_secret_key')) {
            add_option('msf_stripe_secret_key', '');
        }
        if (!get_option('msf_admin_email')) {
            add_option('msf_admin_email', get_option('admin_email'));
        }
        
        // QuickBooks Options
        if (!get_option('msf_payment_gateway')) {
            add_option('msf_payment_gateway', 'stripe'); // Default to Stripe
        }
        if (!get_option('msf_qbo_client_id')) {
            add_option('msf_qbo_client_id', '');
        }
        if (!get_option('msf_qbo_client_secret')) {
            add_option('msf_qbo_client_secret', '');
        }
        if (!get_option('msf_qbo_base_url')) {
            add_option('msf_qbo_base_url', 'Development');
        }
        if (!get_option('msf_qbo_service_item_id')) {
            add_option('msf_qbo_service_item_id', '1');
        }
        
        // Pricing options based on PDF
        if (!get_option('msf_pricing')) {
            add_option('msf_pricing', json_encode(array(
                'Standard (One-Time)' => array('price' => 0.12, 'minimum' => 150),
                'Weekly Cleaning' => array('price' => 0.10, 'minimum' => 140),
                'Biweekly Cleaning' => array('price' => 0.11, 'minimum' => 145),
                'Deep Cleaning' => array('price' => 0.20, 'minimum' => 220),
                'Move-In / Move-Out' => array('price' => 0.22, 'minimum' => 250),
                'Office / Commercial' => array('price' => 0.15, 'minimum' => 200)
            )));
        }
        
        if (!get_option('msf_addon_pricing')) {
            add_option('msf_addon_pricing', json_encode(array(
                'oven' => 40,
                'fridge' => 30
            )));
        }
    }
}