<?php
session_start();
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$payment_success = false;
$order_id = null;
$transaction_id = null;
$amount = null;

// Get parameters from eSewa return URL
if (isset($_GET['pid']) && isset($_GET['amt']) && isset($_GET['refId'])) {
    $product_id = $_GET['pid'];  // Format: ORDER_123_1234567890
    $amount = $_GET['amt'];
    $transaction_id = $_GET['refId'];
    
    // Extract order_id from product_id
    if (preg_match('/ORDER_(\d+)_/', $product_id, $matches)) {
        $order_id = $matches[1];
    }
    
    if ($order_id) {
        // Verify transaction with eSewa server
        $verified = verifyWithEsewa($amount, $transaction_id, $product_id);
        
        if ($verified) {
            // Update order status
            $stmt = $db->prepare("UPDATE orders SET payment_status = 'paid', status = 'processing', esewa_txn_id = ? WHERE id = ? AND payment_status != 'paid'");
            $stmt->execute([$transaction_id, $order_id]);
            $payment_success = true;
        }
    }
}

// Fallback using session
if (!$payment_success && isset($_SESSION['esewa_order_id'])) {
    $order_id = $_SESSION['esewa_order_id'];
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND payment_status = 'paid'");
    $stmt->execute([$order_id]);
    if ($stmt->rowCount() > 0) {
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        $payment_success = true;
        $amount = $order['total_amount'];
        $transaction_id = $order['esewa_txn_id'];
    }
}

function verifyWithEsewa($amount, $refId, $product_id) {
    $url = "https://uat.esewa.com.np/epay/transrec";
    $merchant_code = "EPAYTEST";
    
    $data = array(
        'amt' => $amount,
        'rid' => $refId,
        'pid' => $product_id,
        'scd' => $merchant_code
    );
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    // Check if response contains "Success"
    return (strpos($response, "Success") !== false);
}

// Get order details for display
$order_number = '';
if ($order_id) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order_data = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($order_data) {
        $order_number = $order_data['order_number'];
        if (!$amount) $amount = $order_data['total_amount'];
    }
}

// Clear session
unset($_SESSION['esewa_product_id']);
unset($_SESSION['esewa_order_id']);
unset($_SESSION['esewa_amount']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Success - ShopVerse</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }
        
        .success-card {
            max-width: 500px;
            width: 100%;
            background: white;
            border-radius: 24px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
            animation: fadeInUp 0.5s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: #2ecc71;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .success-icon i {
            font-size: 40px;
            color: white;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            padding: 12px 25px;
            border-radius: 50px;
            margin: 5px;
        }
        
        .btn-outline {
            border: 2px solid #667eea;
            background: transparent;
            color: #667eea;
            padding: 10px 25px;
            border-radius: 50px;
            margin: 5px;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-outline:hover {
            background: #667eea;
            color: white;
        }
    </style>
</head>
<body>
    <div class="success-card">
        <?php if($payment_success): ?>
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            <h2 class="text-success mt-3">Payment Successful!</h2>
            <p class="text-muted">Your payment has been processed successfully.</p>
            
            <div class="alert alert-info mt-4 text-start">
                <strong><i class="fas fa-receipt"></i> Order Details:</strong><br>
                Order Number: <?php echo $order_number; ?><br>
                Transaction ID: <?php echo $transaction_id; ?><br>
                Amount Paid: NPR <?php echo number_format($amount, 2); ?>
            </div>
            
            <div class="mt-4">
                <a href="orders.php" class="btn btn-primary">
                    <i class="fas fa-box"></i> View My Orders
                </a>
                <a href="shop.php" class="btn-outline mt-2 d-inline-block">
                    <i class="fas fa-shopping-bag"></i> Continue Shopping
                </a>
            </div>
        <?php else: ?>
            <i class="fas fa-spinner fa-spin fa-4x text-primary mb-3"></i>
            <h2>Processing Payment...</h2>
            <p>Please wait while we verify your payment.</p>
            <div class="alert alert-warning">
                <i class="fas fa-info-circle"></i> If payment was successful, please check your orders page.
            </div>
            <a href="orders.php" class="btn btn-primary">Check Orders</a>
        <?php endif; ?>
    </div>
    
    <?php if($payment_success): ?>
    <script>
        // Confetti effect
        setTimeout(function() {
            window.location.href = 'orders.php';
        }, 3000);
    </script>
    <?php endif; ?>
</body>
</html>