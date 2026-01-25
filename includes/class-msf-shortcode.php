<?php
/**
 * File Path: includes/class-msf-shortcode.php
 * Shortcode Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class MSF_Shortcode {
    
    public function __construct() {
        add_shortcode('multistep_form', array($this, 'render_form'));
    }
    
    public function render_form($atts) {
        // Get pricing data
        $pricing = json_decode(get_option('msf_pricing'), true);
        $addon_pricing = json_decode(get_option('msf_addon_pricing'), true);
        
        ob_start();
        ?>
        <div class="msf-container">
            <div class="msf-wrapper">
                <!-- Left Side: Form -->
                <div class="msf-form-container">
                    <div class="msf-progress-bar">
                        <div class="msf-progress-step active" data-step="1">
                            <div class="msf-step-number">1</div>
                            <div class="msf-step-label">Personal Details</div>
                        </div>
                        <div class="msf-progress-step" data-step="2">
                            <div class="msf-step-number">2</div>
                            <div class="msf-step-label">Service Info</div>
                        </div>
                    </div>
                    
                    <form id="msf-multistep-form" enctype="multipart/form-data">
                        <!-- Hidden pricing data -->
                        <input type="hidden" id="pricing_data" value='<?php echo esc_attr(json_encode($pricing)); ?>'>
                        <input type="hidden" id="addon_pricing_data" value='<?php echo esc_attr(json_encode($addon_pricing)); ?>'>
                        
                        <!-- Step 1: Personal Details -->
                        <div class="msf-step msf-step-1 active">
                            <h2>Personal Details</h2>
                            
                            <div class="msf-form-row">
                                <div class="msf-form-group">
                                    <label for="first_name">First Name *</label>
                                    <input type="text" id="first_name" name="first_name" required>
                                </div>
                                <div class="msf-form-group">
                                    <label for="last_name">Last Name *</label>
                                    <input type="text" id="last_name" name="last_name" required>
                                </div>
                            </div>
                            
                            <div class="msf-form-row">
                                <div class="msf-form-group">
                                    <label for="phone">Phone *</label>
                                    <input type="tel" id="phone" name="phone" required>
                                </div>
                                <div class="msf-form-group">
                                    <label for="email">Email *</label>
                                    <input type="email" id="email" name="email" required>
                                </div>
                            </div>
                            
                            <div class="msf-form-row">
                                <div class="msf-form-group">
                                    <label for="street">Street *</label>
                                    <input type="text" id="street" name="street" required>
                                </div>
                                <div class="msf-form-group">
                                    <label for="city">City *</label>
                                    <input type="text" id="city" name="city" required>
                                </div>
                            </div>
                            
                            <div class="msf-form-group">
                                <label for="zipcode">Zipcode *</label>
                                <input type="text" id="zipcode" name="zipcode" required>
                            </div>
                            
                            <!-- <div class="msf-form-group">
                                <label for="images">Upload Images</label>
                                <input type="file" id="images" name="images[]" multiple accept="image/*">
                                <small>You can upload multiple images</small>
                            </div> -->
                            
                            <div class="msf-form-buttons">
                                <button type="button" class="msf-btn msf-btn-next">Next</button>
                            </div>
                        </div>
                        
                        <!-- Step 2: Service Information -->
                        <div class="msf-step msf-step-2">
                            <h2>Service Information</h2>
                            
                            <div class="msf-form-group">
                                <label for="cleaning_type">Cleaning Type *</label>
                                <select id="cleaning_type" name="cleaning_type" required>
                                    <option value="">Select a cleaning type</option>
                                    <option value="Standard (One-Time)">Standard (One-Time)</option>
                                    <option value="Weekly Cleaning">Weekly Cleaning</option>
                                    <option value="Biweekly Cleaning">Biweekly Cleaning</option>
                                    <option value="Deep Cleaning">Deep Cleaning</option>
                                    <option value="Move-In / Move-Out">Move-In / Move-Out</option>
                                    <option value="Office / Commercial">Office / Commercial</option>
                                </select>
                            </div>
                            
                            <div class="msf-form-group">
                                <label for="service_date">Service Date * ( Our Service time 8AM to 7PM )</label>
                                <input type="date" id="service_date" name="service_date" required>
                            </div>
                            
                            <div class="msf-form-group">
                                <label for="square_footage">Square Footage (sq ft) *</label>
                                <input type="number" id="square_footage" name="square_footage" min="1" required>
                            </div>
                            
                            <div class="msf-form-group">
                                <label for="workers">Number of Workers Needed *</label>
                                <input type="number" id="workers" name="workers" readonly>
                            </div>
                            
                            <div class="msf-addon-section">
                                <h3>Add-On Services (Optional)</h3>
                                <div class="msf-addon-options">
                                    <label class="msf-addon-checkbox">
                                        <input type="checkbox" id="addon_oven" name="addon_oven" value="1">
                                        <span>Inside Oven Cleaning (+$<?php echo $addon_pricing['oven']; ?>)</span>
                                    </label>
                                    <label class="msf-addon-checkbox">
                                        <input type="checkbox" id="addon_fridge" name="addon_fridge" value="1">
                                        <span>Inside Fridge Cleaning (+$<?php echo $addon_pricing['fridge']; ?>)</span>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="msf-price-display">
                                <h3>Price Breakdown</h3>
                                <div class="msf-price-breakdown">
                                    <div class="msf-price-item">
                                        <span>Base Price:</span>
                                        <span>$<span id="base_price">0.00</span></span>
                                    </div>
                                    <div class="msf-price-item">
                                        <span>Add-ons:</span>
                                        <span>$<span id="addon_total">0.00</span></span>
                                    </div>
                                    <div class="msf-price-item msf-price-total">
                                        <span>Total Price:</span>
                                        <span>$<span id="calculated_price">0.00</span></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div id="booking-errors" class="msf-error"></div>
                            
                            <div class="msf-form-buttons">
                                <button type="button" class="msf-btn msf-btn-prev">Previous</button>
                                <button type="submit" class="msf-btn msf-btn-submit" id="submit-payment">
                                    Submit Booking
                                </button>
                            </div>
                        </div>
                        
                    </form>
                    
                    <div class="msf-success-message" style="display: none;">
                        <div class="msf-success-icon">✓</div>
                        <h2>Booking Successful!</h2>
                        <p>Thank you for your submission. You will receive a confirmation email shortly.</p>
                    </div>
                </div>
                
                <!-- Right Side: Live Preview -->
                <div class="msf-preview-container">
                    <h3>Live Preview</h3>
                    <div class="msf-preview-content">
                        <div class="msf-preview-section">
                            <h4>Personal Details</h4>
                            <div class="msf-preview-item">
                                <span class="msf-preview-label">Name:</span>
                                <span class="msf-preview-value" id="preview-name">-</span>
                            </div>
                            <div class="msf-preview-item">
                                <span class="msf-preview-label">Email:</span>
                                <span class="msf-preview-value" id="preview-email">-</span>
                            </div>
                            <div class="msf-preview-item">
                                <span class="msf-preview-label">Phone:</span>
                                <span class="msf-preview-value" id="preview-phone">-</span>
                            </div>
                            <div class="msf-preview-item">
                                <span class="msf-preview-label">Location:</span>
                                <span class="msf-preview-value" id="preview-location">-</span>
                            </div>
                        </div>
                        
                        <div class="msf-preview-section">
                            <h4>Service Information</h4>
                            <div class="msf-preview-item">
                                <span class="msf-preview-label">Cleaning Type:</span>
                                <span class="msf-preview-value" id="preview-service">-</span>
                            </div>
                            <div class="msf-preview-item">
                                <span class="msf-preview-label">Service Date:</span>
                                <span class="msf-preview-value" id="preview-date">-</span>
                            </div>
                            <div class="msf-preview-item">
                                <span class="msf-preview-label">Square Footage:</span>
                                <span class="msf-preview-value" id="preview-footage">-</span>
                            </div>
                            <div class="msf-preview-item">
                                <span class="msf-preview-label">Workers:</span>
                                <span class="msf-preview-value" id="preview-workers">-</span>
                            </div>
                            <div class="msf-preview-item">
                                <span class="msf-preview-label">Add-ons:</span>
                                <span class="msf-preview-value" id="preview-addons">None</span>
                            </div>
                        </div>
                        
                        <div class="msf-preview-section msf-preview-total">
                            <h4>Total Price</h4>
                            <div class="msf-preview-price">$<span id="preview-price">0.00</span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php
        return ob_get_clean();
    }
}