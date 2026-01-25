/**
 * File Path: assets/js/script.js
 * Main JavaScript for Multistep Form
 */

(function($) {
    'use strict';
    
    let currentStep = 1;
    let formData = {};
    let pricingData = {};
    let addonPricingData = {};
    
    $(document).ready(function() {
        // Get pricing data from hidden fields
        try {
            pricingData = JSON.parse($('#pricing_data').val());
            addonPricingData = JSON.parse($('#addon_pricing_data').val());
        } catch(e) {
            console.error('Error parsing pricing data:', e);
        }
        
        // Set minimum date to today
        const today = new Date().toISOString().split('T')[0];
        $('#service_date').attr('min', today);
        
        // Live preview updates
        $('#first_name, #last_name').on('input', updatePreviewName);
        $('#email').on('input', updatePreviewEmail);
        $('#phone').on('input', updatePreviewPhone);
        $('#street, #city, #zipcode').on('input', updatePreviewLocation);
        $('#cleaning_type').on('change', function() {
            updatePreviewService();
            calculateWorkers();
            calculatePrice();
        });
        $('#service_date').on('change', updatePreviewDate);
        $('#square_footage').on('input', function() {
            calculateWorkers();
            calculatePrice();
            updatePreviewFootage();
        });
        $('#addon_oven, #addon_fridge').on('change', function() {
            calculatePrice();
            updatePreviewAddons();
        });
        
        
        // Navigation buttons
        $('.msf-btn-next').on('click', nextStep);
        $('.msf-btn-prev').on('click', prevStep);
        
        // Form submission
        $('#msf-multistep-form').on('submit', handleSubmit);
        
        // Initial button state
        validateCurrentStep();
        
        // Input monitoring for button state
        $('input, select').on('input change', function() {
            validateCurrentStep();
        });
    });
    
    function validateCurrentStep() {
        const isValid = validateStep(currentStep); // Helper checks HTML5 validity mostly
        const nextBtn = $('.msf-btn-next');
        const submitBtn = $('#submit-payment');
        
        // For Step 1: Control Next button
        if (currentStep === 1) {
            nextBtn.prop('disabled', !isValid);
        }
        
        // For Step 2: Control Submit button
        if (currentStep === 2) {
             // We need to check all required fields in the active step explicitly because
             // 'validateStep' helper might return true if we just look at the container without active interaction check logic sometimes,
             // but here we reuse validateStep which checks validity of inputs.
             // Also need to ensure checking validity doesn't trigger UI error messages prematurely (handled by validity API check mostly).
             // Let's refine validateStep for silent checking or just use validity.
             
             let step2Valid = true;
             $('.msf-step-2 input[required], .msf-step-2 select[required]').each(function() {
                 if (!this.checkValidity()) {
                     step2Valid = false;
                     return false;
                 }
             });
             
             submitBtn.prop('disabled', !step2Valid);
        }
    }

    function nextStep() {
        if (validateStep(currentStep)) {
            $('.msf-step-' + currentStep).removeClass('active');
            $('.msf-progress-step[data-step="' + currentStep + '"]').removeClass('active').addClass('completed');
            
            currentStep++;
            
            $('.msf-step-' + currentStep).addClass('active');
            $('.msf-progress-step[data-step="' + currentStep + '"]').addClass('active');
            
            scrollToTop();
            validateCurrentStep(); // Validate new step state
        }
    }
    
    function prevStep() {
        $('.msf-step-' + currentStep).removeClass('active');
        $('.msf-progress-step[data-step="' + currentStep + '"]').removeClass('active');
        
        currentStep--;
        
        $('.msf-step-' + currentStep).addClass('active');
        $('.msf-progress-step[data-step="' + currentStep + '"]').removeClass('completed').addClass('active');
        
        scrollToTop();
        validateCurrentStep(); // Validate previous step state
    }
    
    function validateStep(step) {
        let isValid = true;
        const currentStepEl = $('.msf-step-' + step);
        
        // This function acts as both a validator and an error shower when called on button click
        // but for disabling buttons we might want a quieter check.
        // However, standard HTML5 validity is what we rely on.
        
        currentStepEl.find('input[required], select[required]').each(function() {
            if (!this.checkValidity()) {
                isValid = false;
                // Only add error class if we are actively validating (like on next click)
                // But for button state, we just need the boolean.
                // The callers 'nextStep' imply active validation.
                return false;
            }
        });
        
        return isValid;
    }
    
    function calculateWorkers() {
        const cleaningType = $('#cleaning_type').val();
        const footage = parseInt($('#square_footage').val()) || 0;
        let workers = 0;
        
        if (!cleaningType || footage === 0) {
            $('#workers').val('');
            return;
        }
        
        // Determine workers based on cleaning type and square footage
        if (cleaningType === 'Standard (One-Time)' || 
            cleaningType === 'Weekly Cleaning' || 
            cleaningType === 'Biweekly Cleaning') {
            // For these types: 1 worker if <= 2000 sq ft, 2 workers if > 2000 sq ft
            workers = footage <= 2000 ? 1 : 2;
        } else if (cleaningType === 'Deep Cleaning' || 
                   cleaningType === 'Move-In / Move-Out' || 
                   cleaningType === 'Office / Commercial') {
            // For these types: always 2 workers
            workers = 2;
        }
        
        $('#workers').val(workers);
        updatePreviewWorkers();
    }
    
    function calculatePrice() {
        const cleaningType = $('#cleaning_type').val();
        const footage = parseInt($('#square_footage').val()) || 0;
        
        if (!cleaningType || footage === 0) {
            $('#base_price').text('0.00');
            $('#addon_total').text('0.00');
            $('#calculated_price').text('0.00');
            $('#final_price').text('0.00');
            updatePreviewPrice();
            return;
        }
        
        // Get pricing for selected cleaning type
        const pricing = pricingData[cleaningType];
        if (!pricing) return;
        
        // Calculate base price
        let basePrice = footage * pricing.price;
        
        // Apply minimum if calculated price is less than minimum
        if (basePrice < pricing.minimum) {
            basePrice = pricing.minimum;
        }
        
        // Calculate addon price
        let addonPrice = 0;
        if ($('#addon_oven').is(':checked')) {
            addonPrice += addonPricingData.oven;
        }
        if ($('#addon_fridge').is(':checked')) {
            addonPrice += addonPricingData.fridge;
        }
        
        // Calculate total
        const totalPrice = basePrice + addonPrice;
        
        $('#base_price').text(basePrice.toFixed(2));
        $('#addon_total').text(addonPrice.toFixed(2));
        $('#calculated_price').text(totalPrice.toFixed(2));
        $('#final_price').text(totalPrice.toFixed(2));
        updatePreviewPrice();
    }
    
    function updatePreviewName() {
        const firstName = $('#first_name').val();
        const lastName = $('#last_name').val();
        const fullName = (firstName + ' ' + lastName).trim() || '-';
        $('#preview-name').text(fullName);
    }
    
    function updatePreviewEmail() {
        $('#preview-email').text($('#email').val() || '-');
    }
    
    function updatePreviewPhone() {
        $('#preview-phone').text($('#phone').val() || '-');
    }
    
    function updatePreviewLocation() {
        const city = $('#city').val();
        const street = $('#street').val();
        const zipcode = $('#zipcode').val();
        let location = [];
        
        if (city) location.push(city);
        if (street) location.push(street);
        if (zipcode) location.push(zipcode);
        
        $('#preview-location').text(location.join(', ') || '-');
    }
    
    function updatePreviewService() {
        $('#preview-service').text($('#cleaning_type').val() || '-');
    }
    
    function updatePreviewDate() {
        $('#preview-date').text($('#service_date').val() || '-');
    }
    
    function updatePreviewFootage() {
        const footage = $('#square_footage').val();
        $('#preview-footage').text(footage ? footage + ' sq ft' : '-');
    }
    
    function updatePreviewWorkers() {
        $('#workers').val(parseInt($('#workers').val()) || '-');
        $('#preview-workers').text($('#workers').val() || '-');
    }
    
    function updatePreviewAddons() {
        const addons = [];
        if ($('#addon_oven').is(':checked')) {
            addons.push('Inside Oven Cleaning');
        }
        if ($('#addon_fridge').is(':checked')) {
            addons.push('Inside Fridge Cleaning');
        }
        $('#preview-addons').text(addons.length > 0 ? addons.join(', ') : 'None');
    }
    
    function updatePreviewPrice() {
        $('#preview-price').text($('#calculated_price').text());
    }
    
    function handleSubmit(e) {
        e.preventDefault();
        
        const submitButton = $('#submit-payment');
        submitButton.prop('disabled', true).text('Processing...');
        
        // Clear errors
        $('#booking-errors').text('');
        
        // Prepare form data
        const formDataObj = new FormData();
        formDataObj.append('action', 'msf_process_payment');
        formDataObj.append('nonce', msfAjax.nonce);
        formDataObj.append('first_name', $('#first_name').val());
        formDataObj.append('last_name', $('#last_name').val());
        formDataObj.append('phone', $('#phone').val());
        formDataObj.append('email', $('#email').val());
        formDataObj.append('street', $('#street').val());
        formDataObj.append('city', $('#city').val());
        formDataObj.append('zipcode', $('#zipcode').val());
        formDataObj.append('cleaning_type', $('#cleaning_type').val());
        formDataObj.append('service_date', $('#service_date').val());
        formDataObj.append('square_footage', $('#square_footage').val());
        formDataObj.append('workers', $('#workers').val());
        formDataObj.append('addon_oven', $('#addon_oven').is(':checked') ? 1 : 0);
        formDataObj.append('addon_fridge', $('#addon_fridge').is(':checked') ? 1 : 0);
        formDataObj.append('base_price', $('#base_price').text());
        formDataObj.append('addon_price', $('#addon_total').text());
        formDataObj.append('total_price', $('#calculated_price').text());
        
        // Submit to server
        $.ajax({
            url: msfAjax.ajaxurl,
            type: 'POST',
            data: formDataObj,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showSuccess();
                } else {
                    Toastify({
                        text: response.data.message || 'Submission failed. Please try again.',
                        duration: 6000,
                        gravity: "top",
                        position: "right",
                        className: "error-toast",
                        stopOnFocus: true,
                    }).showToast();
                    
                    submitButton.prop('disabled', false).text('Submit Booking');
                    validateCurrentStep();
                }
            },
            error: function() {
                Toastify({
                    text: 'An error occurred. Please try again.',
                    duration: 6000,
                    gravity: "top",
                    position: "right",
                    className: "error-toast",
                    stopOnFocus: true,
                }).showToast();
                
                submitButton.prop('disabled', false).text('Submit Booking');
                validateCurrentStep();
            }
        });
    }
    
    function showSuccess() {
        // Hide form and progress bar
        $('#msf-multistep-form').fadeOut(300, function() {
            // Update greeting with name
            const firstName = $('#first_name').val();
            const lastName = $('#last_name').val();
            if (firstName && lastName) {
                $('.msf-success-message h2').text('Hi ' + firstName + ' ' + lastName + ', Booking Successful!');
            }
            
            // Show success message
            $('.msf-progress-bar').hide();
            $('.msf-success-message').fadeIn();
        });

        Toastify({
            text: "Thank you! We will contact you within a few hours.",
            duration: 6000,
            gravity: "top",
            position: "right",
            className: "success-toast",
            stopOnFocus: true,
        }).showToast();
        
        // Reset form after 6 seconds
        setTimeout(function() {
            location.reload();
        }, 6000);
    }
    
    function scrollToTop() {
        $('.msf-container').animate({scrollTop: 0}, 300);
        $('html, body').animate({scrollTop: $('.msf-container').offset().top - 50}, 300);
    }
    
})(jQuery);
