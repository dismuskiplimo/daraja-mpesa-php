<?php
    // include Mpesa class, mpesa credentials, and db connection
    require_once "./includes/classes/Mpesa.php";
    require_once "./includes/mpesa_credentials.php";
    require_once "./includes/dbconn.php";
    
    // process form once submitted
    if(isset($_POST['submit'])){
        // get the phone and amount
        $phone = $_POST['phone'];
        $amount = $_POST['amount'];

        // instantiate parameters
        $callback_url = "https://localhost/daraja-mpesa-php/callback.php";
        $account_reference = "DONATION";
        $transaction_description = "Donation ACC";

        // instantiate the mpesa 
        $mpesa = new Mpesa(
            MPESA_SHORTCODE, 
            MPESA_CONSUMER_KEY,
            MPESA_CONSUMER_SECRET,
            MPESA_PASSKEY,
            MPESA_TRANSACTION_TYPE,
            MPESA_ENVIRONMENT
        );

        // attempt stk push
        try{
            $mpesa->request_stk_push($phone, $amount, $callback_url, $account_reference, $transaction_description);

            // get the merchant request ID and Checkout Request Id
            $merchant_request_id = $result->MerchantRequestID;
            $checkout_request_id = $result->CheckoutRequestID;

            // save the transaction
            $statement = $conn->prepare("INSERT INTO transactions(merchant_request_id, merchant_request_id) VALUES(?,?)");

            // execute the statement
            try{
                $statement->execute([$merchant_request_id, $checkout_request_id]);

                // redirect to thank you page
                header("Location: /thank-you.php");
            }

            catch(Exception $e){
                die($e);
            }
        }

        catch(Exception $e){
            die($e);
        }
    }

    // close the connection
    $conn = null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="./css/style.css">
    <title>MPESA</title>
</head>
<body>

<form action="" method = "POST">
    <h1>Make Payment</h1>
    <div>
        <label for="phone">Phone Number (Format: 2547xxxxxxxx. e.g. 254712345678)</label>
        <input id = "phone" name = "phone" type="text" placeholder="2547XXXXXXXX" required>
    </div>

    <div>
        <label for="amount">Amount (Min: 1)</label>
        <input id = "amount" name = "amount" type="number" min="1" placeholder="amount" required>
    </div>

    <button type = "submit">Make Payment</button>
</form>
    
</body>
</html>