<?php
/**
 * File Path: includes/class-msf-admin.php
 * Admin Dashboard Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class MSF_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'Multistep Form',
            'Multistep Form',
            'manage_options',
            'multistep-form',
            array($this, 'submissions_page'),
            'dashicons-list-view',
            20
        );
        
        add_submenu_page(
            'multistep-form',
            'Submissions',
            'Submissions',
            'manage_options',
            'multistep-form',
            array($this, 'submissions_page')
        );
        
        add_submenu_page(
            'multistep-form',
            'Settings',
            'Settings',
            'manage_options',
            'multistep-form-settings',
            array($this, 'settings_page')
        );
    }
    
    public function register_settings() {
        register_setting('msf_settings_group', 'msf_stripe_publishable_key');
        register_setting('msf_settings_group', 'msf_stripe_secret_key');
        register_setting('msf_settings_group', 'msf_admin_email');
        register_setting('msf_settings_group', 'msf_pricing');
        register_setting('msf_settings_group', 'msf_addon_pricing');
        register_setting('msf_settings_group', 'msf_payment_gateway');
        register_setting('msf_settings_group', 'msf_qbo_client_id');
        register_setting('msf_settings_group', 'msf_qbo_client_secret');
        register_setting('msf_settings_group', 'msf_qbo_base_url');
        register_setting('msf_settings_group', 'msf_qbo_service_item_id');
    }
    
    public function submissions_page() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'msf_submissions';
        
        // Handle delete action
        if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $wpdb->delete($table_name, array('id' => $id), array('%d'));
            echo '<div class="notice notice-success"><p>Submission deleted successfully.</p></div>';
        }
        
        // Get all submissions
        $submissions = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
        
        ?>
        <div class="wrap">
            <h1>Form Submissions</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Cleaning Type</th>
                        <th>Service Date</th>
                        <th>Time</th>
                        <th>Price</th>
                        <th>Payment Status</th>
                        <th>Date Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($submissions): ?>
                        <?php foreach ($submissions as $submission): ?>
                            <tr>
                                <td><?php echo esc_html($submission->id); ?></td>
                                <td><?php echo esc_html($submission->first_name . ' ' . $submission->last_name); ?></td>
                                <td><?php echo esc_html($submission->email); ?></td>
                                <td><?php echo esc_html($submission->cleaning_type); ?></td>
                                <td><?php echo esc_html($submission->service_date); ?></td>
                                <td><?php echo esc_html(date('g:i A', strtotime($submission->service_start_time)) . ' - ' . date('g:i A', strtotime($submission->service_end_time))); ?></td>
                                <td>$<?php echo esc_html($submission->total_price); ?></td>
                                <td><?php echo esc_html(ucfirst($submission->payment_status)); ?></td>
                                <td><?php echo esc_html($submission->created_at); ?></td>
                                <td>
                                    <a href="?page=multistep-form&action=view&id=<?php echo $submission->id; ?>">View</a> | 
                                    <a href="?page=multistep-form&action=delete&id=<?php echo $submission->id; ?>" onclick="return confirm('Are you sure?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10">No submissions found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
        
        // View single submission
        if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $submission = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
            
            if ($submission) {
                ?>
                <div class="wrap" style="margin-top: 20px;">
                    <h2>Submission Details</h2>
                    <table class="form-table">
                        <tr>
                            <th>First Name</th>
                            <td><?php echo esc_html($submission->first_name); ?></td>
                        </tr>
                        <tr>
                            <th>Last Name</th>
                            <td><?php echo esc_html($submission->last_name); ?></td>
                        </tr>
                        <tr>
                            <th>Phone</th>
                            <td><?php echo esc_html($submission->phone); ?></td>
                        </tr>
                        <tr>
                            <th>Email</th>
                            <td><?php echo esc_html($submission->email); ?></td>
                        </tr>
                        <tr>
                            <th>street</th>
                            <td><?php echo esc_html($submission->street); ?></td>
                        </tr>
                        <tr>
                            <th>City</th>
                            <td><?php echo esc_html($submission->city); ?></td>
                        </tr>
                        <tr>
                            <th>Zipcode</th>
                            <td><?php echo esc_html($submission->zipcode); ?></td>
                        </tr>
                        <tr>
                            <th>Cleaning Type</th>
                            <td><?php echo esc_html($submission->cleaning_type); ?></td>
                        </tr>
                        <tr>
                            <th>Service Date</th>
                            <td><?php echo esc_html($submission->service_date); ?></td>
                        </tr>
                        <tr>
                            <th>Service Time</th>
                            <td><?php echo esc_html(date('g:i A', strtotime($submission->service_start_time)) . ' - ' . date('g:i A', strtotime($submission->service_end_time))); ?></td>
                        </tr>
                        <tr>
                            <th>Square Footage</th>
                            <td><?php echo esc_html($submission->square_footage); ?> sq ft</td>
                        </tr>
                        <tr>
                            <th>Workers Needed</th>
                            <td><?php echo esc_html($submission->workers); ?></td>
                        </tr>
                        <tr>
                            <th>Add-Ons</th>
                            <td>
                                <?php 
                                $addons = array();
                                if ($submission->addon_oven) $addons[] = 'Inside Oven Cleaning';
                                if ($submission->addon_fridge) $addons[] = 'Inside Fridge Cleaning';
                                echo $addons ? implode(', ', $addons) : 'None';
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th>Base Price</th>
                            <td>$<?php echo esc_html($submission->base_price); ?></td>
                        </tr>
                        <tr>
                            <th>Add-on Price</th>
                            <td>$<?php echo esc_html($submission->addon_price); ?></td>
                        </tr>
                        <tr>
                            <th>Total Price</th>
                            <td><strong>$<?php echo esc_html($submission->total_price); ?></strong></td>
                        </tr>
                        <tr>
                            <th>Payment Status</th>
                            <td><?php echo esc_html(ucfirst($submission->payment_status)); ?></td>
                        </tr>
                        <tr>
                            <th>Payment Intent ID</th>
                            <td><?php echo esc_html($submission->payment_intent_id); ?></td>
                        </tr>
                    </table>
                </div>
                <?php
            }
        }
    }
    
    public function settings_page() {
        $pricing = json_decode(get_option('msf_pricing'), true);
        $addon_pricing = json_decode(get_option('msf_addon_pricing'), true);
        
        // Handle QuickBooks disconnect
        if (isset($_POST['disconnect_qbo']) && check_admin_referer('msf_qbo_disconnect', 'msf_qbo_disconnect_nonce')) {
            delete_option('msf_qbo_access_token');
            delete_option('msf_qbo_refresh_token');
            delete_option('msf_qbo_realm_id');
            delete_option('msf_qbo_token_expires');
            echo '<div class="notice notice-success"><p>Disconnected from QuickBooks. Please connect again.</p></div>';
        }
        
        // Show QuickBooks connection success
        if (isset($_GET['qbo_status']) && $_GET['qbo_status'] === 'success') {
            echo '<div class="notice notice-success"><p>Successfully connected to QuickBooks!</p></div>';
        }
        
        if (isset($_POST['submit']) && check_admin_referer('msf_settings_update', 'msf_settings_nonce')) {
            // Update Stripe keys
            update_option('msf_stripe_publishable_key', sanitize_text_field($_POST['msf_stripe_publishable_key']));
            update_option('msf_stripe_secret_key', sanitize_text_field($_POST['msf_stripe_secret_key']));
            update_option('msf_admin_email', sanitize_email($_POST['msf_admin_email']));
            
            // Update payment gateway
            update_option('msf_payment_gateway', sanitize_text_field($_POST['msf_payment_gateway']));
            
            // Update QuickBooks settings
            update_option('msf_qbo_client_id', sanitize_text_field($_POST['msf_qbo_client_id']));
            update_option('msf_qbo_client_secret', sanitize_text_field($_POST['msf_qbo_client_secret']));
            update_option('msf_qbo_base_url', sanitize_text_field($_POST['msf_qbo_base_url']));
            update_option('msf_qbo_service_item_id', sanitize_text_field($_POST['msf_qbo_service_item_id']));
            
            // Update pricing
            $new_pricing = array();
            foreach ($_POST['pricing'] as $type => $values) {
                $new_pricing[$type] = array(
                    'price' => floatval($values['price']),
                    'minimum' => floatval($values['minimum'])
                );
            }
            update_option('msf_pricing', json_encode($new_pricing));
            
            // Update addon pricing
            $new_addon_pricing = array(
                'oven' => floatval($_POST['addon_oven']),
                'fridge' => floatval($_POST['addon_fridge'])
            );
            update_option('msf_addon_pricing', json_encode($new_addon_pricing));
            
            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
            
            // Refresh pricing
            $pricing = $new_pricing;
            $addon_pricing = $new_addon_pricing;
        }
        ?>
        <div class="wrap">
            <h1>Multistep Form Settings</h1>
            <form method="post" action="">
                <?php wp_nonce_field('msf_settings_update', 'msf_settings_nonce'); ?>
                
                <h2>Payment Gateway</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label>Select Payment Gateway</label></th>
                        <td>
                            <label style="display: inline-block; margin-right: 20px;">
                                <input type="radio" name="msf_payment_gateway" value="stripe" 
                                       <?php checked(get_option('msf_payment_gateway', 'stripe'), 'stripe'); ?>>
                                Stripe
                            </label>
                            <label style="display: inline-block;">
                                <input type="radio" name="msf_payment_gateway" value="quickbooks" 
                                       <?php checked(get_option('msf_payment_gateway'), 'quickbooks'); ?>>
                                QuickBooks
                            </label>
                        </td>
                    </tr>
                </table>
                
                <h2>Stripe Configuration</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="msf_stripe_publishable_key">Stripe Publishable Key</label></th>
                        <td>
                            <input type="text" id="msf_stripe_publishable_key" name="msf_stripe_publishable_key" 
                                   value="<?php echo esc_attr(get_option('msf_stripe_publishable_key')); ?>" 
                                   class="regular-text" />
                            <p class="description">Enter your Stripe publishable key (starts with pk_)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="msf_stripe_secret_key">Stripe Secret Key</label></th>
                        <td>
                            <input type="text" id="msf_stripe_secret_key" name="msf_stripe_secret_key" 
                                   value="<?php echo esc_attr(get_option('msf_stripe_secret_key')); ?>" 
                                   class="regular-text" />
                            <p class="description">Enter your Stripe secret key (starts with sk_)</p>
                        </td>
                    </tr>
                </table>
                
                <h2>QuickBooks Configuration</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="msf_qbo_client_id">QuickBooks Client ID</label></th>
                        <td>
                            <input type="text" id="msf_qbo_client_id" name="msf_qbo_client_id" 
                                   value="<?php echo esc_attr(get_option('msf_qbo_client_id')); ?>" 
                                   class="regular-text" />
                            <p class="description">Enter your QuickBooks app Client ID</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="msf_qbo_client_secret">QuickBooks Client Secret</label></th>
                        <td>
                            <input type="text" id="msf_qbo_client_secret" name="msf_qbo_client_secret" 
                                   value="<?php echo esc_attr(get_option('msf_qbo_client_secret')); ?>" 
                                   class="regular-text" />
                            <p class="description">Enter your QuickBooks app Client Secret</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="msf_qbo_base_url">Environment</label></th>
                        <td>
                            <select name="msf_qbo_base_url" id="msf_qbo_base_url">
                                <option value="Development" <?php selected(get_option('msf_qbo_base_url', 'Development'), 'Development'); ?>>
                                    Development (Sandbox)
                                </option>
                                <option value="Production" <?php selected(get_option('msf_qbo_base_url'), 'Production'); ?>>
                                    Production
                                </option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="msf_qbo_service_item_id">Service Item ID</label></th>
                        <td>
                            <input type="text" id="msf_qbo_service_item_id" name="msf_qbo_service_item_id" 
                                   value="<?php echo esc_attr(get_option('msf_qbo_service_item_id', '1')); ?>" 
                                   class="regular-text" />
                            <p class="description">The QuickBooks Item ID to use for sales receipts (default: 1)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Redirect URI</th>
                        <td>
                            <code><?php echo esc_html(site_url('/msf-qbo-callback/')); ?></code>
                            <p class="description">Add this URL to your QuickBooks App "Keys & OAuth" → "Redirect URIs"</p>
                        </td>
                    </tr>
                </table>
                
                <?php if (get_option('msf_qbo_client_id') && get_option('msf_qbo_client_secret')): ?>
                    <?php if (get_option('msf_qbo_access_token')): ?>
                        <div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 4px; margin: 15px 0;">
                            <span style="color: #155724; font-weight: bold; font-size: 1.1em;">✓ QuickBooks Connected</span>
                            <p style="margin: 10px 0;">You are connected to QuickBooks.</p>
                            <form method="post" style="display:inline;">
                                <?php wp_nonce_field('msf_qbo_disconnect', 'msf_qbo_disconnect_nonce'); ?>
                                <button type="submit" name="disconnect_qbo" class="button">Disconnect QuickBooks</button>
                            </form>
                            <a href="<?php echo esc_url(MSF_QuickBooks::get_auth_url()); ?>" class="button button-primary" style="margin-left: 5px;">
                                Re-Connect QuickBooks
                            </a>
                        </div>
                    <?php else: ?>
                        <div style="margin: 15px 0;">
                            <a href="<?php echo esc_url(MSF_QuickBooks::get_auth_url()); ?>" class="button button-primary">
                                Connect to QuickBooks
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <h2>Email Settings</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="msf_admin_email">Admin Email</label></th>
                        <td>
                            <input type="email" id="msf_admin_email" name="msf_admin_email" 
                                   value="<?php echo esc_attr(get_option('msf_admin_email')); ?>" 
                                   class="regular-text" />
                            <p class="description">Email address to receive notifications</p>
                        </td>
                    </tr>
                </table>
                
                <h2>Cleaning Type Pricing (Per Square Foot)</h2>
                <table class="form-table">
                    <?php foreach ($pricing as $type => $values): ?>
                    <tr>
                        <th scope="row"><?php echo esc_html($type); ?></th>
                        <td>
                            <label>Price per sq ft: $
                                <input type="number" step="0.01" name="pricing[<?php echo esc_attr($type); ?>][price]" 
                                       value="<?php echo esc_attr($values['price']); ?>" style="width: 100px;" />
                            </label>
                            &nbsp;&nbsp;&nbsp;
                            <label>Minimum: $
                                <input type="number" step="1" name="pricing[<?php echo esc_attr($type); ?>][minimum]" 
                                       value="<?php echo esc_attr($values['minimum']); ?>" style="width: 100px;" />
                            </label>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                
                <h2>Add-On Services Pricing</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="addon_oven">Inside Oven Cleaning</label></th>
                        <td>
                            $<input type="number" id="addon_oven" name="addon_oven" 
                                   value="<?php echo esc_attr($addon_pricing['oven']); ?>" 
                                   class="small-text" min="0" step="1" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="addon_fridge">Inside Fridge Cleaning</label></th>
                        <td>
                            $<input type="number" id="addon_fridge" name="addon_fridge" 
                                   value="<?php echo esc_attr($addon_pricing['fridge']); ?>" 
                                   class="small-text" min="0" step="1" />
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <hr>
            <h2>Usage</h2>
            <p>Use the following shortcode to display the multistep form:</p>
            <code>[multistep_form]</code>
        </div>
        <?php
    }
}