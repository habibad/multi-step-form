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
        $addon_prices = json_decode(get_option('msf_addon_pricing'), true);
        
        $addons = array();
        if ($submission->addon_oven) {
            $price = isset($addon_prices['oven']) ? $addon_prices['oven'] : 0;
            $addons[] = 'Inside Oven Cleaning ($' . number_format($price, 2) . ')';
        }
        if ($submission->addon_fridge) {
            $price = isset($addon_prices['fridge']) ? $addon_prices['fridge'] : 0;
            $addons[] = 'Inside Fridge Cleaning ($' . number_format($price, 2) . ')';
        }
        $addons_text = $addons ? implode(', ', $addons) : 'None';
        
        $body = '
            <h2 style="color: #333; font-size: 20px; margin-bottom: 15px;">New Booking Received</h2>
            
            <div style="background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <h3 style="color: #0073aa; font-size: 16px; margin-top: 0; border-bottom: 1px solid #ddd; padding-bottom: 10px;">Personal Details</h3>
                <p style="margin: 5px 0;"><strong>Name:</strong> ' . esc_html($submission->first_name . ' ' . $submission->last_name) . '</p>
                <p style="margin: 5px 0;"><strong>Email:</strong> <a href="mailto:' . esc_attr($submission->email) . '" style="color: #0073aa;">' . esc_html($submission->email) . '</a></p>
                <p style="margin: 5px 0;"><strong>Phone:</strong> ' . esc_html($submission->phone) . '</p>
                <p style="margin: 5px 0;"><strong>Location:</strong> ' . esc_html($submission->city . ', ' . $submission->street . ' ' . $submission->zipcode) . '</p>
            </div>
            
            <div style="background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <h3 style="color: #0073aa; font-size: 16px; margin-top: 0; border-bottom: 1px solid #ddd; padding-bottom: 10px;">Service Information</h3>
                <p style="margin: 5px 0;"><strong>Cleaning Type:</strong> ' . esc_html($submission->cleaning_type) . '</p>
                <p style="margin: 5px 0;"><strong>Service Date:</strong> ' . esc_html(date('F j, Y', strtotime($submission->service_date))) . '</p>
                <p style="margin: 5px 0;"><strong>Square Footage:</strong> ' . esc_html($submission->square_footage) . ' sq ft</p>
                <p style="margin: 5px 0;"><strong>Workers Needed:</strong> ' . esc_html($submission->workers) . '</p>
                <p style="margin: 5px 0;"><strong>Add-on Services:</strong> ' . esc_html($addons_text) . '</p>
            </div>
            
            <div style="background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <h3 style="color: #0073aa; font-size: 16px; margin-top: 0; border-bottom: 1px solid #ddd; padding-bottom: 10px;">Pricing Details</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 5px 0; color: #666;">Base Price:</td>
                        <td style="padding: 5px 0; text-align: right;">$' . number_format($submission->base_price, 2) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0; color: #666;">Add-on Price:</td>
                        <td style="padding: 5px 0; text-align: right;">$' . number_format($submission->addon_price, 2) . '</td>
                    </tr>
                    <tr style="border-top: 1px solid #ccc; font-weight: bold;">
                        <td style="padding: 10px 0; color: #333;">Total Price:</td>
                        <td style="padding: 10px 0; text-align: right; color: #0073aa; font-size: 16px;">$' . number_format($submission->total_price, 2) . '</td>
                    </tr>
                </table>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="' . admin_url('admin.php?page=multistep-form&action=view&id=' . $submission_id) . '" style="background-color: #0073aa; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;">View in Dashboard</a>
            </div>
        ';
        
        $message = $this->get_styled_email_template('New Booking Notification', $body);
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        return wp_mail($admin_email, $subject, $message, $headers);
    }
    
    public function send_user_confirmation($to_email, $data) {
        $subject = 'Booking Confirmation - ' . $data['cleaning_type'];
        
        $body = '
            <h2 style="color: #333; font-size: 20px; margin-bottom: 15px;">Booking Confirmed!</h2>
            <p style="font-size: 16px; color: #555;">Dear ' . esc_html($data['first_name']) . ',</p>
            <p style="color: #666; line-height: 1.5;">Thank you for choosing our service. Your booking has been successfully received. We are getting everything ready for your appointment.</p>
            
            <div style="background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin: 20px 0;">
                <h3 style="color: #0073aa; font-size: 16px; margin-top: 0; border-bottom: 1px solid #ddd; padding-bottom: 10px;">Service Details</h3>
                <p style="margin: 5px 0;"><strong>Service:</strong> ' . esc_html($data['cleaning_type']) . '</p>
                <p style="margin: 5px 0;"><strong>Date:</strong> ' . esc_html(date('F j, Y', strtotime($data['service_date']))) . '</p>
                <p style="margin: 5px 0;"><strong>Add-on Services:</strong> ' . esc_html($data['addons_text']) . '</p>
            </div>
            
            <div style="background-color: #f9f9f9; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <h3 style="color: #0073aa; font-size: 16px; margin-top: 0; border-bottom: 1px solid #ddd; padding-bottom: 10px;">Pricing Summary</h3>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 5px 0; color: #666;">Base Price:</td>
                        <td style="padding: 5px 0; text-align: right;">$' . number_format($data['base_price'], 2) . '</td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0; color: #666;">Add-on Price:</td>
                        <td style="padding: 5px 0; text-align: right;">$' . number_format($data['addon_price'], 2) . '</td>
                    </tr>
                    <tr style="border-top: 1px solid #ccc; font-weight: bold;">
                        <td style="padding: 10px 0; color: #333;">Total Amount:</td>
                        <td style="padding: 10px 0; text-align: right; color: #0073aa; font-size: 16px;">$' . number_format($data['total_price'], 2) . '</td>
                    </tr>
                </table>
            </div>
            
            <p style="color: #666; margin-top: 20px;">We look forward to serving you!</p>
        ';
        
        $message = $this->get_styled_email_template('Booking Confirmation', $body);
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        return wp_mail($to_email, $subject, $message, $headers);
    }
    
    /**
     * Wrap content in a nice HTML email template
     */
    private function get_styled_email_template($title, $content) {
        return '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>' . esc_html($title) . '</title>
        </head>
        <body style="margin: 0; padding: 0; background-color: #f0f0f1; font-family: Helvetica, Arial, sans-serif;">
            <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #f0f0f1; padding: 20px 0;">
                <tr>
                    <td align="center">
                        <table border="0" cellpadding="0" cellspacing="0" width="600" style="background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                            <!-- Header -->
                            <tr>
                                <td align="center" style="background-color: #0073aa; padding: 30px 0;">
                                    <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: bold;">' . esc_html($title) . '</h1>
                                </td>
                            </tr>
                            
                            <!-- Body -->
                            <tr>
                                <td style="padding: 40px 30px;">
                                    ' . $content . '
                                </td>
                            </tr>
                            
                            <!-- Footer -->
                            <tr>
                                <td align="center" style="background-color: #f9f9f9; padding: 20px; color: #999999; font-size: 12px; border-top: 1px solid #eeeeee;">
                                    &copy; ' . date('Y') . ' ' . get_bloginfo('name') . '. All rights reserved.
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        ';
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
        
        // Limit fields for user email
        $addon_prices = json_decode(get_option('msf_addon_pricing'), true);
        
        $addons = array();
        if ($submission->addon_oven) {
            $price = isset($addon_prices['oven']) ? $addon_prices['oven'] : 0;
            $addons[] = 'Inside Oven Cleaning ($' . number_format($price, 2) . ')';
        }
        if ($submission->addon_fridge) {
            $price = isset($addon_prices['fridge']) ? $addon_prices['fridge'] : 0;
            $addons[] = 'Inside Fridge Cleaning ($' . number_format($price, 2) . ')';
        }
        $addons_text = $addons ? implode(', ', $addons) : 'None';

        $user_data = array(
            'first_name'    => $submission->first_name,
            'last_name'     => $submission->last_name,
            'cleaning_type' => $submission->cleaning_type,
            'service_date'  => $submission->service_date,
            'base_price'    => $submission->base_price,
            'addon_price'   => $submission->addon_price,
            'total_price'   => $submission->total_price,
            'addons_text'   => $addons_text
        );
        
        $user_sent = $email_handler->send_user_confirmation($submission->email, $user_data);
        
        if (!$admin_sent || !$user_sent) {
            error_log("MSF Async Email Warning: Some emails failed for submission ID $submission_id.");
        }
    }
}