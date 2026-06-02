<?php
session_start();
require_once '../config/database.php';
require_once 'esewa_config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$amount = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;

// Verify order
if ($order_id > 0) {
    $stmt = $db->prepare("SELECT * FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        header("Location: orders.php?error=invalid_order");
        exit();
    }
} else {
    header("Location: checkout.php?error=no_order");
    exit();
}

// Generate unique product ID for eSewa
$product_id = "ORDER_" . $order_id . "_" . time();

// Store in session for verification
$_SESSION['esewa_product_id'] = $product_id;
$_SESSION['esewa_order_id'] = $order_id;
$_SESSION['esewa_amount'] = $amount;

// Get URLs
$protocol = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$script_path = rtrim(dirname($_SERVER['PHP_SELF']), '/');
$base_url = $protocol . $host . $script_path;

$success_url = $base_url . "/payment_success.php";
$failure_url = $base_url . "/payment_failure.php";
$merchant_code = EsewaConfig::getMerchantCode();
$payment_url = EsewaConfig::getPaymentUrl();
$is_test = EsewaConfig::isTestMode();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eSewa Payment - ShopVerse</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }
        
        .payment-container {
            max-width: 480px;
            width: 100%;
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
        
        .payment-card {
            background: white;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
        }
        
        .payment-header {
            background: linear-gradient(135deg, #0B4F6C 0%, #0a3d52 100%);
            padding: 30px;
            text-align: center;
            color: white;
        }
        
        .payment-header i {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        
        .payment-header h2 {
            margin: 0;
            font-size: 1.8rem;
        }
        
        .mode-badge {
            display: inline-block;
            background: #ffc107;
            color: #856404;
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: bold;
            margin-top: 10px;
        }
        
        .payment-body {
            padding: 30px;
        }
        
        .order-summary {
            background: #f8f9fa;
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            margin-bottom: 25px;
        }
        
        .order-summary h5 {
            color: #666;
            margin-bottom: 10px;
        }
        
        .amount-display {
            font-size: 2.5rem;
            font-weight: bold;
            color: #0B4F6C;
        }
        
        .info-box {
            background: #e8f4fd;
            border-radius: 12px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .info-box i {
            color: #0B4F6C;
            margin-right: 8px;
        }
        
        .test-credentials {
            background: #fef3c7;
            border-radius: 12px;
            padding: 15px;
            font-size: 0.85rem;
        }
        
        .test-credentials code {
            background: #fff;
            padding: 2px 6px;
            border-radius: 6px;
            font-size: 0.8rem;
        }
        
        .btn-pay {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            padding: 14px;
            border-radius: 50px;
            font-weight: bold;
            font-size: 1.1rem;
            width: 100%;
            transition: all 0.3s;
        }
        
        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(40,167,69,0.4);
        }
        
        .btn-cancel {
            background: #6c757d;
            border: none;
            padding: 12px;
            border-radius: 50px;
            width: 100%;
            margin-top: 12px;
            transition: all 0.3s;
        }
        
        .btn-cancel:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .loader {
            display: inline-block;
            width: 40px;
            height: 40px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 20px 0;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .redirect-message {
            text-align: center;
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="payment-card">
            <div class="payment-header">
                <i class="fas fa-wallet"></i>
                <h2>eSewa Payment Gateway</h2>
                <?php if($is_test): ?>
                    <div class="mode-badge">
                        <i class="fas fa-flask"></i> TEST MODE
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="payment-body">
                <div class="order-summary">
                    <h5><i class="fas fa-receipt"></i> Order Summary</h5>
                    <p><strong>Order Number:</strong> <?php echo $order['order_number']; ?></p>
                    <p><strong>Order Date:</strong> <?php echo date('F j, Y', strtotime($order['order_date'])); ?></p>
                    <hr>
                    <div class="amount-display">
                        NPR <?php echo number_format($amount, 2); ?>
                    </div>
                </div>
                
                <div id="redirectSection" class="redirect-message">
                    <div class="loader"></div>
                    <p class="mt-3 text-muted">Redirecting to eSewa secure payment gateway...</p>
                    <p class="small text-muted">Please wait while we process your request</p>
                </div>
                
                <div class="info-box">
                    <i class="fas fa-shield-alt"></i>
                    <strong>Secure Payment</strong>
                    <p class="small mb-0 mt-1">Your payment information is encrypted and secure</p>
                </div>
                
                <?php if($is_test): ?>
                <div class="test-credentials">
                    <i class="fas fa-info-circle"></i>
                    <strong>Test Credentials</strong>
                    <hr class="my-2">
                    <div class="row">
                        <div class="col-6">
                            <small>Mobile:</small><br>
                            <code>9806800001</code>
                        </div>
                        <div class="col-6">
                            <small>Password:</small><br>
                            <code>Nepal@123</code>
                        </div>
                        <div class="col-6 mt-2">
                            <small>MPIN:</small><br>
                            <code>1122</code>
                        </div>
                        <div class="col-6 mt-2">
                            <small>OTP:</small><br>
                            <code>123456</code>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- eSewa Form - Using the working API -->
                <form id="esewaForm" action="<?php echo $payment_url; ?>" method="POST">
                    <input type="hidden" name="tAmt" value="<?php echo $amount; ?>">
                    <input type="hidden" name="amt" value="<?php echo $amount; ?>">
                    <input type="hidden" name="txAmt" value="0">
                    <input type="hidden" name="psc" value="0">
                    <input type="hidden" name="pdc" value="0">
                    <input type="hidden" name="scd" value="<?php echo $merchant_code; ?>">
                    <input type="hidden" name="pid" value="<?php echo $product_id; ?>">
                    <input type="hidden" name="su" value="<?php echo $success_url; ?>">
                    <input type="hidden" name="fu" value="<?php echo $failure_url; ?>">
                </form>
                
                <button onclick="submitPayment()" class="btn btn-pay text-white">
                    <i class="fas fa-credit-card"></i> Pay with eSewa
                </button>
                
                <a href="checkout.php" class="btn btn-cancel text-white text-center text-decoration-none d-block">
                    <i class="fas fa-times"></i> Cancel Payment
                </a>
            </div>
        </div>
    </div>
    
    <script>
        function submitPayment() {
            const redirectSection = document.getElementById('redirectSection');
            redirectSection.innerHTML = '<div class="loader"></div><p class="mt-3">Processing your payment...</p>';
            
            // Submit the form
            document.getElementById('esewaForm').submit();
        }
        
        // Auto submit after 2 seconds
        setTimeout(function() {
            document.getElementById('esewaForm').submit();
        }, 2000);
    </script>
</body>
</html>