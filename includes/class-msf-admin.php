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
                // Payment status removed

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
                                    <div class="postbox-header"><h2 class="hndle">Pricing Details</h2></div>
                                    <div class="inside">
                                        <p><strong>Total Amount:</strong> <span style="font-size: 1.2em; font-weight: bold;">$<?php echo esc_html($submission->total_price); ?></span></p>
                                        
                                        <p><strong>Base Price:</strong> $<?php echo esc_html($submission->base_price); ?></p>
                                        <p><strong>Add-ons Price:</strong> $<?php echo esc_html($submission->addon_price); ?></p>
                                        
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
        
        
        if (isset($_POST['submit']) && check_admin_referer('msf_settings_update', 'msf_settings_nonce')) {
            // Update admin email
            update_option('msf_admin_email', sanitize_email($_POST['msf_admin_email']));
            
            
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