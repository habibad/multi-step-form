<?php
/**
 * File Path: includes/class-msf-quickbooks.php
 * QuickBooks Payment Handler
 */

if (!defined('ABSPATH')) {
    exit;
}

class MSF_QuickBooks {
    
    public function __construct() {
        add_action('init', array($this, 'handle_qbo_callback'));
    }
    
    /**
     * Handle QuickBooks OAuth callback
     */
    public function handle_qbo_callback() {
        if (strpos($_SERVER['REQUEST_URI'], 'msf-qbo-callback') !== false && isset($_GET['code'])) {
            
            if (!isset($_GET['realmId'])) {
                wp_die('Missing realmId parameter');
            }
            
            // Check if QuickBooks SDK is available
            if (!class_exists('QuickBooksOnline\API\DataService\DataService')) {
                wp_die('QuickBooks SDK not found. Please install it via Composer.');
            }
            
            try {
                $dataService = \QuickBooksOnline\API\DataService\DataService::Configure([
                    'auth_mode' => 'oauth2',
                    'ClientID' => get_option('msf_qbo_client_id'),
                    'ClientSecret' => get_option('msf_qbo_client_secret'),
                    'RedirectURI' => site_url('/msf-qbo-callback/'),
                    'baseUrl' => get_option('msf_qbo_base_url', 'Development'),
                    'minorVersion' => 65
                ]);
                
                $OAuth2LoginHelper = $dataService->getOAuth2LoginHelper();
                $accessToken = $OAuth2LoginHelper->exchangeAuthorizationCodeForToken(
                    sanitize_text_field($_GET['code']), 
                    sanitize_text_field($_GET['realmId'])
                );
                $dataService->updateOAuth2Token($accessToken);
                
                update_option('msf_qbo_access_token', $accessToken->getAccessToken());
                update_option('msf_qbo_refresh_token', $accessToken->getRefreshToken());
                update_option('msf_qbo_realm_id', sanitize_text_field($_GET['realmId']));
                update_option('msf_qbo_token_expires', time() + 3600);
                
                wp_redirect(admin_url('admin.php?page=multistep-form-settings&qbo_status=success'));
                exit;
            } catch (Exception $e) {
                error_log('MSF QBO Token Exchange Error: ' . $e->getMessage());
                wp_die('Error connecting to QuickBooks: ' . esc_html($e->getMessage()));
            }
        }
    }
    
    /**
     * Generate QuickBooks authorization URL
     */
    public static function get_auth_url() {
        if (!class_exists('QuickBooksOnline\API\DataService\DataService')) {
            return '';
        }
        
        try {
            $dataService = \QuickBooksOnline\API\DataService\DataService::Configure([
                'auth_mode' => 'oauth2',
                'ClientID' => get_option('msf_qbo_client_id'),
                'ClientSecret' => get_option('msf_qbo_client_secret'),
                'RedirectURI' => site_url('/msf-qbo-callback/'),
                'scope' => 'com.intuit.quickbooks.accounting com.intuit.quickbooks.payment',
                'baseUrl' => get_option('msf_qbo_base_url', 'Development'),
                'minorVersion' => 65
            ]);
            return $dataService->getOAuth2LoginHelper()->getAuthorizationCodeURL();
        } catch (Exception $e) {
            error_log('MSF QBO Auth URL Error: ' . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Process QuickBooks payment
     */
    public static function process_payment($payment_data) {
        if (!class_exists('QuickBooksOnline\API\DataService\DataService')) {
            return array(
                'success' => false,
                'message' => 'QuickBooks SDK not found. Please install it via Composer.'
            );
        }
        
        try {
            // Ensure we have a token
            $refreshToken = get_option('msf_qbo_refresh_token');
            if (!$refreshToken) {
                return array(
                    'success' => false,
                    'message' => 'Not connected to QuickBooks. Please configure in settings.'
                );
            }
            
            // Configure DataService
            $dataService = \QuickBooksOnline\API\DataService\DataService::Configure([
                'auth_mode' => 'oauth2',
                'ClientID' => get_option('msf_qbo_client_id'),
                'ClientSecret' => get_option('msf_qbo_client_secret'),
                'accessTokenKey' => get_option('msf_qbo_access_token'),
                'refreshTokenKey' => $refreshToken,
                'QBORealmID' => get_option('msf_qbo_realm_id'),
                'baseUrl' => get_option('msf_qbo_base_url', 'Development'),
                'minorVersion' => 65
            ]);
            
            // Refresh token if needed
            $OAuth2LoginHelper = $dataService->getOAuth2LoginHelper();
            $token_expires = get_option('msf_qbo_token_expires', 0);
            if (time() >= ($token_expires - 300)) {
                $refreshedToken = $OAuth2LoginHelper->refreshToken();
                $dataService->updateOAuth2Token($refreshedToken);
                update_option('msf_qbo_access_token', $refreshedToken->getAccessToken());
                update_option('msf_qbo_refresh_token', $refreshedToken->getRefreshToken());
                update_option('msf_qbo_token_expires', time() + 3600);
            }
            
            // Process the charge via Payments API
            $chargeData = array(
                'amount' => $payment_data['amount'],
                'currency' => 'USD',
                'card' => array(
                    'number' => $payment_data['card_number'],
                    'expMonth' => $payment_data['exp_month'],
                    'expYear' => $payment_data['exp_year'],
                    'cvc' => $payment_data['cvc'],
                    'name' => $payment_data['card_holder_name'],
                    'address' => array(
                        'postalCode' => $payment_data['zip_code'],
                        'streetAddress' => $payment_data['billing_address']
                    )
                ),
                'context' => array(
                    'mobile' => 'false',
                    'isEcommerce' => 'true'
                )
            );
            
            $chargeResult = self::api_charge($chargeData);
            
            if (is_wp_error($chargeResult)) {
                throw new Exception($chargeResult->get_error_message());
            }
            
            // Check for errors
            if (isset($chargeResult['errors'])) {
                $err = $chargeResult['errors'][0];
                $errCode = $err['code'] ?? '';
                $errDetail = $err['detail'] ?? $err['message'] ?? print_r($err, true);
                
                if ($errCode === 'AuthorizationFailed' || stripos($errDetail, 'AuthorizationFailed') !== false) {
                    throw new Exception("Authorization Error: You must grant 'Payments' permission. Please reconnect QuickBooks in settings.");
                }
                
                throw new Exception("Payment Failed ($errCode): " . $errDetail);
            }
            
            if (isset($chargeResult['code']) && $chargeResult['code'] === 'AuthorizationFailed') {
                throw new Exception("Authorization Error: You must grant 'Payments' permission. Please reconnect QuickBooks in settings.");
            }
            
            if (isset($chargeResult['Fault'])) {
                $err = $chargeResult['Fault']['Error'][0] ?? array('Message' => 'Unknown Fault');
                throw new Exception("QBO Fault: " . ($err['Detail'] ?? $err['Message']));
            }
            
            if (empty($chargeResult['status'])) {
                throw new Exception("Invalid API Response from QuickBooks.");
            }
            
            if ($chargeResult['status'] !== 'CAPTURED' && $chargeResult['status'] !== 'AUTHORIZED') {
                throw new Exception("Payment status: " . $chargeResult['status']);
            }
            
            // Payment successful
            $transactionId = $chargeResult['id'];
            
            // Create Sales Receipt in QuickBooks
            $customer_name = $payment_data['customer_name'];
            $customer_email = $payment_data['customer_email'];
            
            // Find or create customer
            $customer_name_escaped = str_replace("'", "\\'", $customer_name);
            $query = "SELECT * FROM Customer WHERE DisplayName = '{$customer_name_escaped}' MAXRESULTS 1";
            $customers = $dataService->Query($query);
            
            if ($customers && count($customers) > 0) {
                $customerObj = $customers[0];
            } else {
                $customerObj = \QuickBooksOnline\API\Facades\Customer::create([
                    "DisplayName" => $customer_name,
                    "PrimaryEmailAddr" => ["Address" => $customer_email]
                ]);
                $customerObj = $dataService->Add($customerObj);
            }
            
            // Create Sales Receipt
            $service_item_id = get_option('msf_qbo_service_item_id', '1');
            $salesReceiptObj = \QuickBooksOnline\API\Facades\SalesReceipt::create([
                "Line" => [
                    [
                        "Amount" => $payment_data['amount'],
                        "DetailType" => "SalesItemLineDetail",
                        "SalesItemLineDetail" => [
                            "ItemRef" => ["value" => $service_item_id]
                        ]
                    ]
                ],
                "CustomerRef" => ["value" => $customerObj->Id],
                "BillEmail" => ["Address" => $customer_email],
                "PaymentRefNum" => $transactionId,
                "PrivateNote" => "Cleaning Service - " . $payment_data['cleaning_type'] . ". Transaction ID: " . $transactionId
            ]);
            
            $dataService->Add($salesReceiptObj);
            
            return array(
                'success' => true,
                'transaction_id' => $transactionId
            );
            
        } catch (Exception $e) {
            error_log('MSF QBO Payment Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => $e->getMessage()
            );
        }
    }
    
    /**
     * Make API call to QuickBooks Payments API
     */
    private static function api_charge($body) {
        $accessToken = get_option('msf_qbo_access_token');
        $baseUrl = get_option('msf_qbo_base_url', 'Development') === 'Production' 
            ? 'https://api.intuit.com' 
            : 'https://sandbox.api.intuit.com';
            
        $url = $baseUrl . '/quickbooks/v4/payments/charges';
        $requestId = wp_generate_uuid4();
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
                'Request-Id' => $requestId,
                'Accept' => 'application/json'
            ),
            'body' => json_encode($body),
            'timeout' => 45
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $code = wp_remote_retrieve_response_code($response);
        $bodyContent = wp_remote_retrieve_body($response);
        
        if ($code >= 400) {
            error_log("MSF QBO API Error ($code): " . $bodyContent);
        }
        
        $decoded = json_decode($bodyContent, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array('errors' => array(array('detail' => "Invalid JSON Response ($code): " . substr($bodyContent, 0, 200))));
        }
        
        return $decoded;
    }
}

// Initialize QuickBooks handler
new MSF_QuickBooks();