/**
 * File Path: assets/js/script.js
 * Main JavaScript for Multistep Form
 */

(function($) {
    'use strict';
    
    let currentStep = 1;
    let formData = {};
    let stripe, cardElement;
    let pricingData = {};
    let addonPricingData = {};
    let paymentGateway = 'stripe';
    
    $(document).ready(function() {
        // Get pricing data from hidden fields
        try {
            pricingData = JSON.parse($('#pricing_data').val());
            addonPricingData = JSON.parse($('#addon_pricing_data').val());
            paymentGateway = $('#payment_gateway').val() || 'stripe';
        } catch(e) {
            console.error('Error parsing pricing data:', e);
        }
        
        // Initialize Stripe only if it's the selected gateway
        if (paymentGateway === 'stripe' && typeof msfAjax !== 'undefined' && msfAjax.stripeKey) {
            stripe = Stripe(msfAjax.stripeKey);
            const elements = stripe.elements();
            cardElement = elements.create('card', {
                style: {
                    base: {
                        fontSize: '16px',
                        color: '#32325d',
                        fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
                        '::placeholder': {
                            color: '#aab7c4'
                        }
                    },
                    invalid: {
                        color: '#fa755a',
                        iconColor: '#fa755a'
                    }
                }
            });
            cardElement.mount('#card-element');
            
            cardElement.on('change', function(event) {
                const displayError = document.getElementById('card-errors');
                if (event.error) {
                    displayError.textContent = event.error.message;
                } else {
                    displayError.textContent = '';
                }
            });
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
        $('#service_start_time, #service_end_time').on('change', function() {
            validateTime();
            updatePreviewTime();
        });
        $('#square_footage').on('input', function() {
            calculateWorkers();
            calculatePrice();
            updatePreviewFootage();
        });
        $('#addon_oven, #addon_fridge').on('change', function() {
            calculatePrice();
            updatePreviewAddons();
        });
        
        // QuickBooks card formatting
        if (paymentGateway === 'quickbooks') {
            $('#qbo_card_number').on('input', formatCardNumber);
            $('#qbo_card_exp').on('input', formatCardExpiry);
            $('#qbo_card_cvc').on('input', formatCardCVC);
        }
        
        // Navigation buttons
        $('.msf-btn-next').on('click', nextStep);
        $('.msf-btn-prev').on('click', prevStep);
        
        // Form submission
        $('#msf-multistep-form').on('submit', handleSubmit);
    });
    
    function validateTime() {
        const startTime = $('#service_start_time').val();
        const endTime = $('#service_end_time').val();
        
        if (!startTime || !endTime) return true;
        
        // Convert to minutes for easier comparison
        const startMinutes = timeToMinutes(startTime);
        const endMinutes = timeToMinutes(endTime);
        const minTime = timeToMinutes('07:00');
        const maxTime = timeToMinutes('20:00');
        
        let isValid = true;
        let errorMsg = '';
        
        if (startMinutes < minTime || startMinutes > maxTime) {
            isValid = false;
            errorMsg = 'Start time must be between 7:00 AM and 8:00 PM';
        } else if (endMinutes < minTime || endMinutes > maxTime) {
            isValid = false;
            errorMsg = 'End time must be between 7:00 AM and 8:00 PM';
        } else if (endMinutes <= startMinutes) {
            isValid = false;
            errorMsg = 'End time must be after start time';
        }
        
        if (!isValid) {
            alert(errorMsg);
            $('#service_end_time').val('');
            return false;
        }
        
        return true;
    }
    
    function timeToMinutes(time) {
        const [hours, minutes] = time.split(':').map(Number);
        return hours * 60 + minutes;
    }
    
    function nextStep() {
        if (validateStep(currentStep)) {
            $('.msf-step-' + currentStep).removeClass('active');
            $('.msf-progress-step[data-step="' + currentStep + '"]').removeClass('active').addClass('completed');
            
            currentStep++;
            
            $('.msf-step-' + currentStep).addClass('active');
            $('.msf-progress-step[data-step="' + currentStep + '"]').addClass('active');
            
            if (currentStep === 3) {
                populateReview();
            }
            
            scrollToTop();
        }
    }
    
    function prevStep() {
        $('.msf-step-' + currentStep).removeClass('active');
        $('.msf-progress-step[data-step="' + currentStep + '"]').removeClass('active');
        
        currentStep--;
        
        $('.msf-step-' + currentStep).addClass('active');
        $('.msf-progress-step[data-step="' + currentStep + '"]').removeClass('completed').addClass('active');
        
        scrollToTop();
    }
    
    function validateStep(step) {
        let isValid = true;
        const currentStepEl = $('.msf-step-' + step);
        
        currentStepEl.find('input[required], select[required]').each(function() {
            if (!this.checkValidity()) {
                isValid = false;
                $(this).addClass('error');
                this.reportValidity();
                return false;
            } else {
                $(this).removeClass('error');
            }
        });
        
        // Additional validation for step 2
        if (step === 2 && isValid) {
            if (!validateTime()) {
                isValid = false;
            }
        }
        
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
    
    function updatePreviewTime() {
        const startTime = $('#service_start_time').val();
        const endTime = $('#service_end_time').val();
        
        if (startTime && endTime) {
            const start = formatTime(startTime);
            const end = formatTime(endTime);
            $('#preview-time').text(start + ' - ' + end);
        } else {
            $('#preview-time').text('-');
        }
    }
    
    function formatTime(time) {
        const [hours, minutes] = time.split(':');
        const hour = parseInt(hours);
        const ampm = hour >= 12 ? 'PM' : 'AM';
        const displayHour = hour > 12 ? hour - 12 : (hour === 0 ? 12 : hour);
        return displayHour + ':' + minutes + ' ' + ampm;
    }
    
    function updatePreviewFootage() {
        const footage = $('#square_footage').val();
        $('#preview-footage').text(footage ? footage + ' sq ft' : '-');
    }
    
    function updatePreviewWorkers() {
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
    
    function populateReview() {
        const addons = [];
        if ($('#addon_oven').is(':checked')) {
            addons.push('Inside Oven Cleaning (+$' + addonPricingData.oven + ')');
        }
        if ($('#addon_fridge').is(':checked')) {
            addons.push('Inside Fridge Cleaning (+$' + addonPricingData.fridge + ')');
        }
        
        const startTime = formatTime($('#service_start_time').val());
        const endTime = formatTime($('#service_end_time').val());
        
        const reviewHtml = `
            <div class="msf-review-group">
                <h4>Personal Information</h4>
                <p><strong>Name:</strong> ${$('#first_name').val()} ${$('#last_name').val()}</p>
                <p><strong>Email:</strong> ${$('#email').val()}</p>
                <p><strong>Phone:</strong> ${$('#phone').val()}</p>
                <p><strong>Address:</strong> ${$('#city').val()}, ${$('#street').val()} ${$('#zipcode').val()}</p>
            </div>
            <div class="msf-review-group">
                <h4>Service Details</h4>
                <p><strong>Cleaning Type:</strong> ${$('#cleaning_type').val()}</p>
                <p><strong>Service Date:</strong> ${$('#service_date').val()}</p>
                <p><strong>Service Time:</strong> ${startTime} - ${endTime}</p>
                <p><strong>Square Footage:</strong> ${$('#square_footage').val()} sq ft</p>
                <p><strong>Workers Needed:</strong> ${$('#workers').val()}</p>
                <p><strong>Add-on Services:</strong> ${addons.length > 0 ? addons.join(', ') : 'None'}</p>
            </div>
            <div class="msf-review-group">
                <h4>Price Breakdown</h4>
                <p><strong>Base Price:</strong> $${$('#base_price').text()}</p>
                <p><strong>Add-ons:</strong> $${$('#addon_total').text()}</p>
            </div>
            <div class="msf-review-group msf-review-total">
                <h4>Total Price</h4>
                <p class="msf-review-price">$${$('#calculated_price').text()}</p>
            </div>
        `;
        
        $('#msf-review-content').html(reviewHtml);
    }
    
    async function handleSubmit(e) {
        e.preventDefault();
        
        if (paymentGateway === 'stripe') {
            await handleStripeSubmit();
        } else {
            await handleQuickBooksSubmit();
        }
    }
    
    async function handleStripeSubmit() {
        if (!stripe || !cardElement) {
            alert('Payment system is not initialized. Please contact support.');
            return;
        }
        
        const submitButton = $('#submit-payment');
        submitButton.prop('disabled', true).text('Processing...');
        
        // Create payment method
        const {error, paymentMethod} = await stripe.createPaymentMethod({
            type: 'card',
            card: cardElement,
            billing_details: {
                name: $('#first_name').val() + ' ' + $('#last_name').val(),
                email: $('#email').val(),
                phone: $('#phone').val()
            }
        });
        
        if (error) {
            $('#card-errors').text(error.message);
            submitButton.prop('disabled', false).text('Pay $' + $('#final_price').text());
            return;
        }
        
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
        formDataObj.append('service_start_time', $('#service_start_time').val());
        formDataObj.append('service_end_time', $('#service_end_time').val());
        formDataObj.append('square_footage', $('#square_footage').val());
        formDataObj.append('workers', $('#workers').val());
        formDataObj.append('addon_oven', $('#addon_oven').is(':checked') ? 1 : 0);
        formDataObj.append('addon_fridge', $('#addon_fridge').is(':checked') ? 1 : 0);
        formDataObj.append('base_price', $('#base_price').text());
        formDataObj.append('addon_price', $('#addon_total').text());
        formDataObj.append('total_price', $('#calculated_price').text());
        formDataObj.append('payment_method_id', paymentMethod.id);
        
        // Add images
        const files = $('#images')[0].files;
        for (let i = 0; i < files.length; i++) {
            formDataObj.append('images[]', files[i]);
        }
        
        // Submit to server
        $.ajax({
            url: msfAjax.ajaxurl,
            type: 'POST',
            data: formDataObj,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    if (response.data.requires_action) {
                        handleCardAction(response.data.payment_intent_client_secret);
                    } else {
                        showSuccess();
                    }
                } else {
                    alert('Error: ' + response.data.message);
                    submitButton.prop('disabled', false).text('Pay $' + $('#final_price').text());
                }
            },
            error: function() {
                alert('An error occurred. Please try again.');
                submitButton.prop('disabled', false).text('Pay $' + $('#final_price').text());
            }
        });
    }
    
    async function handleQuickBooksSubmit() {
        const submitButton = $('#submit-payment');
        submitButton.prop('disabled', true).text('Processing...');
        
        // Validate QuickBooks fields
        const cardNumber = $('#qbo_card_number').val().replace(/\s/g, '');
        const cardExp = $('#qbo_card_exp').val();
        const cardCvc = $('#qbo_card_cvc').val();
        
        if (!cardNumber || cardNumber.length < 13) {
            $('#qbo-errors').text('Please enter a valid card number');
            submitButton.prop('disabled', false).text('Pay $' + $('#final_price').text());
            return;
        }
        
        if (!cardExp || !cardExp.match(/^\d{2}\/\d{4}$/)) {
            $('#qbo-errors').text('Please enter expiry in MM/YYYY format');
            submitButton.prop('disabled', false).text('Pay $' + $('#final_price').text());
            return;
        }
        
        if (!cardCvc || cardCvc.length < 3) {
            $('#qbo-errors').text('Please enter a valid CVC');
            submitButton.prop('disabled', false).text('Pay $' + $('#final_price').text());
            return;
        }
        
        // Clear errors
        $('#qbo-errors').text('');
        
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
        formDataObj.append('service_start_time', $('#service_start_time').val());
        formDataObj.append('service_end_time', $('#service_end_time').val());
        formDataObj.append('square_footage', $('#square_footage').val());
        formDataObj.append('workers', $('#workers').val());
        formDataObj.append('addon_oven', $('#addon_oven').is(':checked') ? 1 : 0);
        formDataObj.append('addon_fridge', $('#addon_fridge').is(':checked') ? 1 : 0);
        formDataObj.append('base_price', $('#base_price').text());
        formDataObj.append('addon_price', $('#addon_total').text());
        formDataObj.append('total_price', $('#calculated_price').text());
        formDataObj.append('qbo_card_number', cardNumber);
        formDataObj.append('qbo_card_exp', cardExp);
        formDataObj.append('qbo_card_cvc', cardCvc);
        formDataObj.append('qbo_billing_address', $('#qbo_billing_address').val() || '');
        
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
                    $('#qbo-errors').text(response.data.message || 'Payment failed. Please try again.');
                    submitButton.prop('disabled', false).text('Pay $' + $('#final_price').text());
                }
            },
            error: function() {
                $('#qbo-errors').text('An error occurred. Please try again.');
                submitButton.prop('disabled', false).text('Pay $' + $('#final_price').text());
            }
        });
    }
    
    function formatCardNumber(e) {
        let value = e.target.value.replace(/\s/g, '');
        let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
        e.target.value = formattedValue;
    }
    
    function formatCardExpiry(e) {
        let value = e.target.value.replace(/\D/g, '');
        if (value.length >= 2) {
            value = value.substring(0, 2) + '/' + value.substring(2, 6);
        }
        e.target.value = value;
    }
    
    function formatCardCVC(e) {
        e.target.value = e.target.value.replace(/\D/g, '').substring(0, 4);
    }
    
    async function handleCardAction(clientSecret) {
        const {error, paymentIntent} = await stripe.handleCardAction(clientSecret);
        
        if (error) {
            alert('Payment failed: ' + error.message);
            $('#submit-payment').prop('disabled', false).text('Pay $' + $('#final_price').text());
        } else if (paymentIntent.status === 'succeeded') {
            showSuccess();
        }
    }
    
    function showSuccess() {
        $('#msf-multistep-form').hide();
        $('.msf-success-message').fadeIn();
        
        // Reset form after 5 seconds
        setTimeout(function() {
            location.reload();
        }, 5000);
    }
    
    function scrollToTop() {
        $('.msf-container').animate({scrollTop: 0}, 300);
        $('html, body').animate({scrollTop: $('.msf-container').offset().top - 50}, 300);
    }
    
})(jQuery);
