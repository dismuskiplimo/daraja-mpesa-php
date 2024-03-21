<?php 
    namespace App\Payment\Gateways;

    class Mpesa{
        // MPESA Constants

        // MPESA Response Errors
        protected const TRANSACTION_ERRORS = [
            '0' => 'Success',
            '1' => 'Insufficient Funds',
            '2' => 'Less Than Minimum Transaction Value',
            '3' => 'More Than Maximum Transaction Value',
            '4' => 'Would Exceed Daily Transfer Limit',
            '5' => 'Would Exceed Minimum Balance',
            '6' => 'Unresolved Primary Party',
            '7' => 'Unresolved Receiver Party',
            '8' => 'Would Exceed Maxiumum Balance',
            '11' => 'Debit Account Invalid',
            '12' => 'Credit Account Invalid',
            '13' => 'Unresolved Debit Account',
            '14' => 'Unresolved Credit Account',
            '15' => 'Duplicate Detected',
            '17' => 'Internal Failure',
            '20' => 'Unresolved Initiator',
            '26' => 'Traffic blocking condition in place',
            '1032' => 'Request cancelled by user',
            '1037' => 'STK error. User Cannot be reached. Please ensure that the phone is offline and that the SIM card MPESA Menu is updated. To update SIM, dial *234*1*6#',
            '1025' => 'An error occurred while sending the STK push request',
            '9999' => 'An error occurred while sending the STK push request. MPESA Message Too long',
            '2001' => 'Invalid MPESA PIN. Please Try Again',
            '1019' => 'Transaction Expired. Please Try Again',
            '1001' => 'Transaction Not Completed. A Similar Transaction is Underway',
        ];
    
        // MPESA HTTP Errors
        protected const HTTP_ERRORS = [
            '400' => 'Bad Request',
            '401' => 'Unauthorized',
            '403' => 'Forbidden',
            '404' => 'Not Found',
            '405' => 'Method Not Allowed',
            '406' => 'Not Acceptable - You requested a format that isn\'t json',
            '429' => 'Too Many Requests - You\'re requesting too many kittens! Slow down!',
            '500' => 'Internal Server Error - We had a problem with our server. Try again later.',
            '503' => 'Service Unavailable - We\'re temporarily offline for maintenance. Please try again later.',
            
        ];
        
        // MPESA URLs
        // LIVE
        protected const SANDBOX_AUTHORIZATION_URL = "https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials";
        protected const SANDBOX_STKPUSH_REQUEST_URL = "https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest";
        protected const SANDBOX_STKPUSH_QUERY_URL = "https://sandbox.safaricom.co.ke/mpesa/stkpushquery/v1/query";

        // SANDBOX
        protected const LIVE_AUTHORIZATION_URL = "https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials";
        protected const LIVE_STKPUSH_REQUEST_URL = "https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest";
        protected const LIVE_STKPUSH_QUERY_URL = "https://api.safaricom.co.ke/mpesa/stkpushquery/v1/query";
        
        // MPESA Credentials
        protected $shortcode;
        protected $consumer_key;
        protected $consumer_secret;
        protected $passkey;

        // MPESA URLs
        protected $authorization_url;
        protected $stkpush_request_url;
        protected $stkpush_query_url;

        // MPESA transaction type
        protected $transaction_type;

        /**
         * Constructor
         * 
         * param 1. shortcode - Business Shortcode (paybill or till number)
         * param 2. consumer_key - API consumer key
         * param 3. consumer_secret - API consumer secret
         * Param 4. passkey - API passkey (optional)
         * param 5. transaction_type - Transaction type. One of two values ('paybill' or 'till')
         * param 6. environment - MPESA environment. One of two values ('live' or 'sandbox')
         */
        public function __construct($shortcode, $consumer_key, $consumer_secret, $passkey = '', $transaction_type = 'paybill',  $environment = "live"){
            // initialize the values
            $this->shortcode = $shortcode;
            $this->consumer_key = $consumer_key;
            $this->consumer_secret = $consumer_secret;
            $this->passkey = $passkey;
            
            // set the transaction type
            $this->set_transaction_type($transaction_type);

            // set API urls based on environment
            $this->set_api_urls($environment);
        }

        /**
         * Get the Shortcode
         * 
         * Return: Shortcode
         */
        public function get_shortcode(){
            return $this->shortcode;
        }

        /**
         * Get the Authorization URL
         * 
         * Return: Authorization URL
         */
        public function get_authorization_url(){
            return $this->authorization_url;
        }

        /**
         * Get the STK Push Query URL
         * 
         * Return: STK Push Query URL
         */
        public function get_stkpush_query_url(){
            return $this->stkpush_query_url;
        }

        /**
         * Get the STK Push Request URL
         * 
         * Return: the STK Push Request URL
         */
        public function get_stkpush_request_url(){
            return $this->stkpush_request_url;
        }

        /**
         * Generates Access Token
         */
        protected function generate_access_token(){
            $curl = curl_init();

            // set the URL
            curl_setopt($curl, CURLOPT_URL, $this->authorization_url);
            
            // enable headers
            curl_setopt($curl, CURLOPT_HEADER, true);

            // set the headers
            curl_setopt($curl, CURLOPT_HTTPHEADER, [
                'Authorization: Basic ' . base64_encode($this->consumer_key . ':' . $this->consumer_secret),
            ]);
            
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

            // return value instead of echoing out to screen
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            
            // execute the curl request
            $response = curl_exec($curl);
            
            // close the curl request
            curl_close($curl);

            // split header and body
            list($header, $body) = explode("\r\n\r\n", $response, 2);

            // decode the body
            $body = json_decode($body);

            // Throw exception if body is empty
            if(is_null($body)){
                throw new \Exception("MPESA Error. Please Contact Admin");
            }
            
            // return the access token
            return $body->access_token;
        }

        /**
         * Requests STK push on customers Phone
         * 
         * Param 1. phone - Phone number to receive STK Push
         * Param 2. amount - The amount to request (only whole numbers)
         * Param 3. callback_url - The URL to be pinged once the transaction is complete
         * Param 4. account_reference - The account reference (between 1 and 12 characters)
         * Param 5. transaction_description - Description that wil be sent to the customer (between 1 and 13 characters)
         * 
         * Return: An object containing MerchantRequestID and CheckoutRequestID if successful
         * 
         * Throws: Exception if error occurs
         */
        public function request_stk_push($phone, $amount, $callback_url, $account_reference, $transaction_description){
            
            // Generate the access token
            $access_token = $this->generate_access_token();

            // generate the timestamp
            $timestamp = $this->generate_timestamp();

            // Generate password according to Daraja Specifications
            $password = $this->generate_password($timestamp);

            // format phone number
            $phone = $this::format_phone_number($phone);

            // initialize the curl request
            $curl = curl_init();

            // set the URL
            curl_setopt($curl, CURLOPT_URL, $this->stkpush_request_url);

            // Enable the header
            curl_setopt($curl, CURLOPT_HEADER, true);
            
            // Set the headers
            curl_setopt($curl, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $access_token,
            ]);

            // Enable POST
            curl_setopt($curl, CURLOPT_POST, true);
            
            // JSON encode the post data
            $post_data = json_encode([
                'BusinessShortCode' 	=> $this->shortcode,
                'Password' 			    => $password,
                'Timestamp' 			=> $timestamp,
                'TransactionType' 	    => $this->transaction_type,
                'Amount' 				=> ceil($amount),
                'PartyA' 				=> $phone,
                'PartyB' 				=> $this->shortcode,
                'PhoneNumber' 		    => $phone,
                'CallBackURL' 		    => $callback_url,
                'AccountReference' 	    => $account_reference,
                'TransactionDesc' 	    => $transaction_description, 
            ]);

            // SET the POST fields
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);

            // SSL
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

            // Enable return transfer to prevent response from being echo'ed
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

            // Execute the CURL Request
            $response = curl_exec($curl);

            // close the curl request
            curl_close($curl);

            // split header and body
            list($header, $body) = explode("\r\n\r\n", $response, 2);

            // decode the body
            $body =  json_decode($body);

            // check if transaction was successfull
            if(is_null($body)){
                // Error occurred while making push
                throw new \Exception("Internal Server Error. Please Contact Admin");
            }

            else if(isset($body->errorCode)){
                // error occured while making push
                throw new \Exception($body->errorMessage);
            }

            else if($body->ResponseCode !== "0"){
                // error occurred while making push
                throw new \Exception($body->ResponseDescription);
            }

            // Return the body as JSON
            return $body;
        }

        /**
         * Queries the status of STK push
         * 
         * Param 1: checkout_request_id - The CheckOut request ID
         */
        public function query_stk_push($checkout_request_id){
            // Generate the access token
            $access_token = $this->generate_access_token();

            // generate the timestamp
            $timestamp = $this->generate_timestamp();

            // Generate password according to Daraja Specifications
            $password = $this->generate_password($timestamp);

            // initialize the curl request
            $curl = curl_init();

            // set the URL
            curl_setopt($curl, CURLOPT_URL, $this->stkpush_query_url);

            // Enable the header
            curl_setopt($curl, CURLOPT_HEADER, true);
            
            // Set the headers
            curl_setopt($curl, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $access_token,
            ]);

            // Enable POST
            curl_setopt($curl, CURLOPT_POST, true);
            
            // JSON encode the post data
            $post_data = json_encode([
                'BusinessShortCode' 	=> $this->shortcode,
                'Password' 			    => $password,
                'Timestamp' 			=> $timestamp,
                'CheckoutRequestID'     => $checkout_request_id,
            ]);

            // SET the POST fields
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);

            // SSL
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

            // Enable return transfer to prevent response from being echo'ed
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

            // Execute the CURL Request
            $response = curl_exec($curl);

            // close the curl request
            curl_close($curl);

            // split header and body
            list($header, $body) = explode("\r\n\r\n", $response, 2);

            // decode the body
            $body =  json_decode($body);

            if(is_null($body)){
                // Error occurred while making push
                throw new \Exception("Internal Server Error. Please Contact Admin");
            }

            else if(isset($body->errorCode)){
                // error occured while making push
                throw new \Exception($body->errorMessage);
            }

            else if($body->ResponseCode !== "0"){
                // error occurred while making push
                throw new \Exception($body->ResponseDescription);
            }
        }

        /**
         * Process MPESA Callback
         * 
         * Param 1: request - Raw request body received from API after submitting STK request
         * Throw: Exception if response is invalid or ResultCode != 0
         * Return: JSON encoded response if successfull
         */

        public function process_callback($request){
            // attempt to split the request (header, body)
            list($header, $body) = explode("\r\n\r\n", $request, 2);

            // decode the body
            $body = json_decode($body);

            // check if the body is null
            if(is_null($body)){
                throw new \Exception("Error Processing Request");
            }

            // check if resultcode is != 0
            if($body->Body->stkCallback->ResultCode != "0"){
                throw new \Exception($body->Body->stkCallback->ResultDesc);
            }

            // return parsed body
            return $body;
        }


        // UTILITY METHODS
        
        /**
         * Generates and returns a timestamp in the format YYYYMMDDHHmmss
         * 
         * RETURN formatted timestamp as string
         */
        protected function generate_timestamp(){
            return (new \DateTime())->format("YmdHis");
        }

        /**
         * Generate password according to Daraja Specifications
         * (base64.encode(Shortcode+Passkey+Timestamp))
         * 
         * Param 1: timestamp - The timestamp in the format YYYYMMDDHHmmss
         */
        protected function generate_password($timestamp){
            return base64_encode($this->shortcode . $this->passkey . $timestamp);
        }

        /**
         * Formats phone number to the required format 254xxxxxxxxx
         * 
         * Param 1: phone - The phone number to be fromatted (must be in the format 07xxxxxxxx)
         * 
         * Throws: Exeption if phone number is invalid
         * 
         * Returns: Formatted phone number
         */
        public static function format_phone_number($phone){
            if (preg_match('/^0\d{9}$/', $phone)){
                return '254' . substr($phone, 1);
            }

            throw new \Exception('Invalid Phone Number');
        }

        /**
         * Updates the urls based on the environment
         */

         protected function set_api_urls($envionment){
            if($envionment == 'live'){
                $this->authorization_url = $this::LIVE_AUTHORIZATION_URL;
                $this->stkpush_request_url = $this::LIVE_STKPUSH_REQUEST_URL;
                $this->stkpush_query_url = $this::LIVE_STKPUSH_QUERY_URL;
            }

            else{
                $this->authorization_url = $this::SANDBOX_AUTHORIZATION_URL;
                $this->stkpush_request_url = $this::SANDBOX_STKPUSH_REQUEST_URL;
                $this->stkpush_query_url = $this::SANDBOX_STKPUSH_QUERY_URL;
            }
         }

         /**
          * Set the transaction type. Only two values accepted ("paybill" or "till")
          * 
          * Throws: Exception if invalid transacion type is provided
          */

         public function set_transaction_type($transaction_type){
            if($transaction_type == 'paybill'){
                $this->transaction_type = 'CustomerPayBillOnline';
            }

            else if($transaction_type == 'till'){
                $this->transaction_type = 'CustomerBuyGoodsOnline';
            }

            else{
                throw new \Exception('Invalid Transaction Type');
            }
         }
    }