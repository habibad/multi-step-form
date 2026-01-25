<?php
/**
 * File Path: includes/class-msf-email.php
 * Email Notification Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class MSF_Email {
    
    public function send_admin_notification($submission_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'msf_submissions';
        $submission = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $submission_id));
        
        if (!$submission) {
            return false;
        }
        
        $admin_email = get_option('msf_admin_email', get_option('admin_email'));
        $subject = 'New Cleaning Service Booking - ' . $submission->cleaning_type;
        
        // Prepare addons text
        $addons = array();
        if ($submission->addon_oven) $addons[] = 'Inside Oven Cleaning';
        if ($submission->addon_fridge) $addons[] = 'Inside Fridge Cleaning';
        $addons_text = $addons ? implode(', ', $addons) : 'None';
        
        $message = '<html><body>';
        $message .= '<h2>New Cleaning Service Booking</h2>';
        $message .= '<h3>Personal Details</h3>';
        $message .= '<p><strong>Name:</strong> ' . $submission->first_name . ' ' . $submission->last_name . '</p>';
        $message .= '<p><strong>Email:</strong> ' . $submission->email . '</p>';
        $message .= '<p><strong>Phone:</strong> ' . $submission->phone . '</p>';
        $message .= '<p><strong>Location:</strong> ' . $submission->city . ', ' . $submission->street . ' ' . $submission->zipcode . '</p>';
        
        $message .= '<h3>Service Information</h3>';
        $message .= '<p><strong>Cleaning Type:</strong> ' . $submission->cleaning_type . '</p>';
        $message .= '<p><strong>Service Date:</strong> ' . $submission->service_date . '</p>';
        $message .= '<p><strong>Square Footage:</strong> ' . $submission->square_footage . ' sq ft</p>';
        $message .= '<p><strong>Workers Needed:</strong> ' . $submission->workers . '</p>';
        $message .= '<p><strong>Add-on Services:</strong> ' . $addons_text . '</p>';
        
        $message .= '<h3>Pricing Details</h3>';
        $message .= '<p><strong>Base Price:</strong> $' . number_format($submission->base_price, 2) . '</p>';
        $message .= '<p><strong>Add-on Price:</strong> $' . number_format($submission->addon_price, 2) . '</p>';
        $message .= '<p><strong>Total Price:</strong> $' . number_format($submission->total_price, 2) . '</p>';
        
        $message .= '<h3>Payment Details</h3>';
        $message .= '<p><strong>Payment Status:</strong> ' . ucfirst($submission->payment_status) . '</p>';
        $message .= '<p><strong>Payment Intent ID:</strong> ' . $submission->payment_intent_id . '</p>';
        
        $message .= '<p><a href="' . admin_url('admin.php?page=multistep-form&action=view&id=' . $submission_id) . '">View Full Details in Dashboard</a></p>';
        $message .= '</body></html>';
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        return wp_mail($admin_email, $subject, $message, $headers);
    }
    
    public function send_user_confirmation($to_email, $data) {
        $subject = 'Booking Confirmation - ' . $data['cleaning_type'];
        
        $message = '<html><body>';
        $message .= '<h2>Booking Confirmation</h2>';
        $message .= '<p>Dear ' . $data['first_name'] . ' ' . $data['last_name'] . ',</p>';
        $message .= '<p>Thank you for booking with us! We have received your booking request.</p>';
        
        $message .= '<h3>Service Details</h3>';
        $message .= '<p><strong>Service:</strong> ' . $data['cleaning_type'] . '</p>';
        $message .= '<p><strong>Date:</strong> ' . $data['service_date'] . '</p>';
        $message .= '<p><strong>Total Amount:</strong> $' . number_format($data['total_price'], 2) . '</p>';
        
        $message .= '<p>We look forward to serving you!</p>';
        $message .= '</body></html>';
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        return wp_mail($to_email, $subject, $message, $headers);
    }

    /**
     * Handle async email sending via WP Cron
     */
    public static function handle_cron_emails($submission_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'msf_submissions';
        $submission = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $submission_id));
        
        if (!$submission) {
            error_log("MSF Async Email Error: Submission ID $submission_id not found.");
            return;
        }

        $email_handler = new self();
        
        // 1. Send Admin Notification
        $admin_sent = $email_handler->send_admin_notification($submission_id);
        
        // 2. Send User Confirmation
        $user_data = array(
            'first_name'    => $submission->first_name,
            'last_name'     => $submission->last_name,
            'cleaning_type' => $submission->cleaning_type,
            'service_date'  => $submission->service_date,
            'total_price'   => $submission->total_price
        );
        
        $user_sent = $email_handler->send_user_confirmation($submission->email, $user_data);
        
        if (!$admin_sent || !$user_sent) {
            error_log("MSF Async Email Warning: Some emails failed for submission ID $submission_id.");
        }
    }
}