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
        register_setting('msf_settings_group', 'msf_admin_email');
        register_setting('msf_settings_group', 'msf_pricing');
        register_setting('msf_settings_group', 'msf_addon_pricing');
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
        
        // View single submission
        if (isset($_GET['action']) && $_GET['action'] === 'view' && isset($_GET['id'])) {
            $id = intval($_GET['id']);
            $submission = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
            
            if ($submission) {
                $status_color = $submission->payment_status === 'completed' ? '#46b450' : '#ffb900';
                ?>
                <div class="wrap">
                    <h1 class="wp-heading-inline">Submission #<?php echo esc_html($submission->id); ?></h1>
                    <a href="?page=multistep-form" class="page-title-action">Back to Submissions</a>
                    <hr class="wp-header-end">
                    
                    <div id="poststuff">
                        <div id="post-body" class="metabox-holder columns-2">
                            
                            <!-- Main Content Column -->
                            <div id="post-body-content">
                                <!-- Customer Info -->
                                <div class="postbox">
                                    <div class="postbox-header"><h2 class="hndle">Customer Information</h2></div>
                                    <div class="inside">
                                        <table class="form-table" style="margin-top: 0;">
                                            <tr>
                                                <th scope="row">Full Name:</th>
                                                <td><?php echo esc_html($submission->first_name . ' ' . $submission->last_name); ?></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Email Address:</th>
                                                <td><a href="mailto:<?php echo esc_attr($submission->email); ?>"><?php echo esc_html($submission->email); ?></a></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Phone Number:</th>
                                                <td><?php echo esc_html($submission->phone); ?></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Service Address:</th>
                                                <td>
                                                    <?php echo esc_html($submission->street); ?><br>
                                                    <?php echo esc_html($submission->city . ', ' . $submission->zipcode); ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                
                                <!-- Service Info -->
                                <div class="postbox">
                                    <div class="postbox-header"><h2 class="hndle">Service Details</h2></div>
                                    <div class="inside">
                                        <table class="form-table" style="margin-top: 0;">
                                            <tr>
                                                <th scope="row">Cleaning Type:</th>
                                                <td><strong><?php echo esc_html($submission->cleaning_type); ?></strong></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Preferred Date:</th>
                                                <td><?php echo esc_html(date('F j, Y', strtotime($submission->service_date))); ?></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Property Size:</th>
                                                <td><?php echo esc_html($submission->square_footage); ?> sq ft</td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Workers Assigned:</th>
                                                <td><?php echo esc_html($submission->workers); ?></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Add-On Services:</th>
                                                <td>
                                                    <?php 
                                                    $addons = array();
                                                    if ($submission->addon_oven) $addons[] = 'Inside Oven Cleaning';
                                                    if ($submission->addon_fridge) $addons[] = 'Inside Fridge Cleaning';
                                                    
                                                    if (!empty($addons)) {
                                                        echo '<ul style="margin: 0; padding-left: 15px; list-style-type: disc;">';
                                                        foreach ($addons as $addon) {
                                                            echo '<li>' . esc_html($addon) . '</li>';
                                                        }
                                                        echo '</ul>';
                                                    } else {
                                                        echo 'None';
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Sidebar Column -->
                            <div id="postbox-container-1" class="postbox-container">
                                <div class="postbox">
                                    <div class="postbox-header"><h2 class="hndle">Payment Information</h2></div>
                                    <div class="inside">
                                        <p><strong>Total Amount:</strong> <span style="font-size: 1.2em; font-weight: bold;">$<?php echo esc_html($submission->total_price); ?></span></p>
                                        
                                        <p><strong>Base Price:</strong> $<?php echo esc_html($submission->base_price); ?></p>
                                        <p><strong>Add-ons Price:</strong> $<?php echo esc_html($submission->addon_price); ?></p>
                                        
                                        <hr>
                                        
                                        <p><strong>Status:</strong> 
                                            <span style="background: <?php echo $status_color; ?>; color: #fff; padding: 3px 8px; border-radius: 3px; font-weight: bold; text-transform: uppercase; font-size: 11px;">
                                                <?php echo esc_html($submission->payment_status); ?>
                                            </span>
                                        </p>
                                        
                                        <?php if ($submission->payment_intent_id): ?>
                                            <p><strong>Transaction ID:</strong><br><code style="word-break: break-all;"><?php echo esc_html($submission->payment_intent_id); ?></code></p>
                                        <?php endif; ?>
                                        
                                        <hr>
                                        
                                        <p><strong>Submitted On:</strong><br>
                                        <?php echo esc_html(date('F j, Y \a\t g:i a', strtotime($submission->created_at))); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                        </div>
                    </div>
                </div>
                <?php
            } else {
                echo '<div class="notice notice-error"><p>Submission not found.</p></div>';
            }
            return; // Stop execution here for single view
        }
        
        // List view (default)
        $submissions = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Form Submissions</h1>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th width="50">ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Cleaning Type</th>
                        <th>Service Date</th>
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
                                <td><a href="mailto:<?php echo esc_attr($submission->email); ?>"><?php echo esc_html($submission->email); ?></a></td>
                                <td><?php echo esc_html($submission->cleaning_type); ?></td>
                                <td><?php echo esc_html($submission->service_date); ?></td>
                                <td>$<?php echo esc_html($submission->total_price); ?></td>
                                <td>
                                    <?php if ($submission->payment_status === 'completed'): ?>
                                        <span style="color: #46b450; font-weight: bold;">Completed</span>
                                    <?php else: ?>
                                        <span style="color: #ffb900; font-weight: bold;"><?php echo esc_html(ucfirst($submission->payment_status)); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(date('Y-m-d H:i', strtotime($submission->created_at))); ?></td>
                                <td>
                                    <a href="?page=multistep-form&action=view&id=<?php echo $submission->id; ?>" class="button button-small">View</a>
                                    <a href="?page=multistep-form&action=delete&id=<?php echo $submission->id; ?>" class="button button-small button-link-delete" onclick="return confirm('Are you sure?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9">No submissions found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
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
            // Update admin email
            update_option('msf_admin_email', sanitize_email($_POST['msf_admin_email']));
            
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