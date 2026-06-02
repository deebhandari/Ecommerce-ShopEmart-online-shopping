Update customer/checkout.php (Add eSewa Option)<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'customer') {
    header("Location: ../login.php");
    exit();
}
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Get user info
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get cart items with stock validation
$query = "SELECT c.*, p.name, p.price, p.stock, p.image FROM cart c 
          JOIN products p ON c.product_id = p.id 
          WHERE c.user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Validate stock before checkout
$stock_valid = true;
$stock_errors = [];

foreach ($cart_items as $item) {
    if ($item['quantity'] > $item['stock']) {
        $stock_valid = false;
        $stock_errors[] = [
            'name' => $item['name'],
            'requested' => $item['quantity'],
            'available' => $item['stock']
        ];
    }
}

// If stock issues exist, redirect back to cart
if (!$stock_valid) {
    $_SESSION['checkout_error'] = "Some items in your cart have stock issues. Please adjust quantities.";
    header("Location: cart.php");
    exit();
}

// Calculate total
$total = 0;
foreach ($cart_items as $item) {
    $total += $item['price'] * $item['quantity'];
}

// Check if cart is empty
if (empty($cart_items)) {
    header("Location: cart.php?error=cart_empty");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $address = trim($_POST['address']);
    $payment_method = $_POST['payment_method'];
    
    if (empty($address)) {
        $error = "Please enter your shipping address";
    } else {
        // Double-check stock before creating order
        $stock_valid_final = true;
        foreach ($cart_items as $item) {
            $stmt = $db->prepare("SELECT stock FROM products WHERE id = ?");
            $stmt->execute([$item['product_id']]);
            $current_stock = $stmt->fetchColumn();
            
            if ($item['quantity'] > $current_stock) {
                $stock_valid_final = false;
                $error = "Sorry, {$item['name']} only has {$current_stock} items in stock. Please adjust your cart.";
                break;
            }
        }
        
        if ($stock_valid_final) {
            // Generate unique order number
            $order_number = 'ORD-' . date('Ymd') . '-' . time() . '-' . rand(1000, 9999);
            
            try {
                $db->beginTransaction();
                
                if ($payment_method == 'cod') {
                    // Cash on Delivery - Show as "Paid" to customer, but actual payment on delivery
                    $payment_status = 'cod_pending';  // Internal: waiting for cash payment on delivery
                    $order_status = 'processing';     // Order being processed
                    
                    $stmt = $db->prepare("INSERT INTO orders (order_number, user_id, total_amount, shipping_address, payment_method, payment_status, status) 
                                          VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$order_number, $user_id, $total, $address, $payment_method, $payment_status, $order_status]);
                    $order_id = $db->lastInsertId();
                    
                } else {
                    // eSewa payment - both pending initially
                    $stmt = $db->prepare("INSERT INTO orders (order_number, user_id, total_amount, shipping_address, payment_method, payment_status, status) 
                                          VALUES (?, ?, ?, ?, ?, 'pending', 'pending')");
                    $stmt->execute([$order_number, $user_id, $total, $address, $payment_method]);
                    $order_id = $db->lastInsertId();
                }
                
                // Add order items and update stock
                foreach ($cart_items as $item) {
                    // Insert order item
                    $stmt = $db->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$order_id, $item['product_id'], $item['quantity'], $item['price']]);
                    
                    // Update product stock
                    $stmt = $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
                    $stmt->execute([$item['quantity'], $item['product_id'], $item['quantity']]);
                }
                
                // Clear cart
                $stmt = $db->prepare("DELETE FROM cart WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                $db->commit();
                
                // Redirect based on payment method
                if ($payment_method == 'esewa') {
                    $_SESSION['pending_order_id'] = $order_id;
                    header("Location: esewa_payment.php?order_id=" . $order_id . "&amount=" . $total);
                    exit();
                } else {
                    // Cash on Delivery success
                    $_SESSION['order_success'] = "Order placed successfully! You will pay cash upon delivery. Your order is being processed.";
                    header("Location: orders.php?success=1");
                    exit();
                }
                
            } catch (Exception $e) {
                $db->rollBack();
                $error = "Order failed: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - ShopVerse</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .checkout-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 0;
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .checkout-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: pulse 10s ease infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        
        .checkout-form {
            background: white;
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }
        
        .order-summary {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            position: sticky;
            top: 20px;
        }
        
        .payment-option {
            border: 2px solid #e0e0e0;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }
        
        .payment-option:hover {
            border-color: #667eea;
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }
        
        .payment-option.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(102,126,234,0.05) 0%, rgba(118,75,162,0.05) 100%);
        }
        
        .payment-option input[type="radio"] {
            transform: scale(1.2);
            margin-right: 15px;
            cursor: pointer;
        }
        
        .payment-icon {
            width: 50px;
            height: 50px;
            background: #f8f9fa;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        
        .payment-icon i {
            font-size: 1.5rem;
        }
        
        .esewa-icon {
            color: #0B4F6C;
        }
        
        .cod-icon {
            color: #28a745;
        }
        
        .payment-title {
            font-size: 1.1rem;
            font-weight: bold;
            margin: 0;
        }
        
        .payment-desc {
            font-size: 0.8rem;
            color: #6c757d;
            margin: 3px 0 0 0;
        }
        
        .test-badge {
            background: #ffc107;
            color: #856404;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.7rem;
            margin-left: 10px;
        }
        
        .form-control, .form-select {
            border-radius: 12px;
            border: 2px solid #e0e0e0;
            padding: 12px 15px;
            transition: all 0.3s;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
            outline: none;
        }
        
        .btn-checkout {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 15px;
            font-size: 18px;
            font-weight: bold;
            border-radius: 50px;
            margin-top: 20px;
            transition: all 0.3s;
            width: 100%;
        }
        
        .btn-checkout:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102,126,234,0.4);
        }
        
        .product-image-small {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .total-amount {
            font-size: 1.8rem;
            font-weight: bold;
            color: #667eea;
        }
        
        .secure-badge {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 8px 15px;
            border-radius: 50px;
            font-size: 0.8rem;
        }
        
        @media (max-width: 768px) {
            .checkout-form, .order-summary {
                margin-bottom: 20px;
                padding: 20px;
            }
            .payment-option {
                padding: 15px;
            }
            .total-amount {
                font-size: 1.4rem;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <i class="fas fa-store"></i> ShopVerse
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="cart.php">
                            <i class="fas fa-arrow-left"></i> Back to Cart
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="checkout-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-credit-card"></i> Checkout</h1>
                    <p class="lead mb-0">Complete your purchase securely</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="secure-badge d-inline-block">
                        <i class="fas fa-lock"></i> 100% Secure Payment
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container mb-5">
        <?php if(isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Checkout Form - Left Column -->
            <div class="col-lg-7">
                <div class="checkout-form">
                    <h3 class="mb-4"><i class="fas fa-user-circle text-primary"></i> Shipping Information</h3>
                    
                    <form method="POST" id="checkoutForm">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" disabled>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="tel" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>" disabled>
                            </div>
                            
                            <div class="col-md-12 mb-4">
                                <label class="form-label">Shipping Address <span class="text-danger">*</span></label>
                                <textarea name="address" class="form-control" rows="4" required placeholder="Enter your complete shipping address (Street, City, State, Zip Code)"><?php echo htmlspecialchars($user['address']); ?></textarea>
                                <small class="text-muted"><i class="fas fa-info-circle"></i> Please provide a complete address for successful delivery</small>
                            </div>
                        </div>
                        
                        <h3 class="mb-3 mt-4"><i class="fas fa-wallet text-primary"></i> Select Payment Method</h3>
                        
                        <!-- eSewa Payment Option -->
                        <div class="payment-option" onclick="selectPayment('esewa')">
                            <div class="d-flex align-items-center">
                                <input type="radio" name="payment_method" value="esewa" id="esewa">
                                <div class="payment-icon">
                                    <i class="fas fa-wallet esewa-icon"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div>
                                        <span class="payment-title">eSewa Digital Wallet</span>
                                        <span class="test-badge"><i class="fas fa-flask"></i> Test Mode</span>
                                    </div>
                                    <p class="payment-desc">Pay instantly using your eSewa account. Secure and fast digital payment.</p>
                                    <div class="small text-muted mt-1">
                                        <i class="fas fa-info-circle"></i> Test ID: 9806800001 | MPIN: 1122 | OTP: 123456
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Cash on Delivery Option -->
                        <div class="payment-option" onclick="selectPayment('cod')">
                            <div class="d-flex align-items-center">
                                <input type="radio" name="payment_method" value="cod" id="cod" checked>
                                <div class="payment-icon">
                                    <i class="fas fa-money-bill-wave cod-icon"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div>
                                        <span class="payment-title">Cash on Delivery</span>
                                        <span class="test-badge" style="background: #28a745; color: white;"><i class="fas fa-truck"></i> Available</span>
                                    </div>
                                    <p class="payment-desc">Pay with cash when your order is delivered to your doorstep.</p>
                                    <div class="small text-success mt-1">
                                        <i class="fas fa-check-circle"></i> No additional charges for COD
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-checkout">
                            <i class="fas fa-check-circle"></i> Place Order
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Order Summary - Right Column -->
            <div class="col-lg-5">
                <div class="order-summary">
                    <h3 class="mb-4"><i class="fas fa-shopping-bag text-primary"></i> Order Summary</h3>
                    
                    <div class="order-items mb-3" style="max-height: 300px; overflow-y: auto;">
                        <?php foreach($cart_items as $item): ?>
                            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                                <div class="d-flex align-items-center">
                                    <?php if(!empty($item['image']) && file_exists("../uploads/" . $item['image'])): ?>
                                        <img src="../uploads/<?php echo $item['image']; ?>" class="product-image-small me-3" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                    <?php else: ?>
                                        <div class="product-image-small bg-light d-flex align-items-center justify-content-center me-3">
                                            <i class="fas fa-box-open text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                        <br>
                                        <small class="text-muted">Qty: <?php echo $item['quantity']; ?></small>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <strong>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></strong>
                                    <br>
                                    <small class="text-muted">$<?php echo number_format($item['price'], 2); ?> each</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between mb-2">
                        <span>Subtotal:</span>
                        <strong>$<?php echo number_format($total, 2); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Shipping:</span>
                        <strong class="text-success">FREE</strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Tax (13% VAT):</span>
                        <strong>$<?php echo number_format($total * 0.13, 2); ?></strong>
                    </div>
                    
                    <hr>
                    
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="h5 mb-0">Total Amount:</span>
                        <span class="total-amount">$<?php echo number_format($total, 2); ?></span>
                    </div>
                    
                    <div class="mt-4 pt-3 text-center border-top">
                        <small class="text-muted">
                            <i class="fas fa-shield-alt"></i> Your payment information is secure
                        </small>
                        <div class="mt-2">
                            <i class="fab fa-cc-visa fa-lg mx-1 text-muted"></i>
                            <i class="fab fa-cc-mastercard fa-lg mx-1 text-muted"></i>
                            <i class="fab fa-cc-amex fa-lg mx-1 text-muted"></i>
                            <i class="fas fa-wallet fa-lg mx-1 text-muted"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectPayment(method) {
            document.getElementById(method).checked = true;
            
            // Update selected style
            document.querySelectorAll('.payment-option').forEach(option => {
                option.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
        }
        
        // Form validation
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            const address = document.querySelector('textarea[name="address"]').value.trim();
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            
            if (!address) {
                e.preventDefault();
                alert('⚠️ Please enter your shipping address');
                return false;
            }
            
            if (!paymentMethod) {
                e.preventDefault();
                alert('⚠️ Please select a payment method');
                return false;
            }
            
            // Show loading state
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing Order...';
            submitBtn.disabled = true;
            
            // Allow form to submit (timeout for visual feedback)
            setTimeout(() => {
                // Form will submit naturally
            }, 100);
        });
        
        // Highlight default selected payment (COD)
        document.addEventListener('DOMContentLoaded', function() {
            if (document.getElementById('cod').checked) {
                document.querySelector('.payment-option').classList.add('selected');
            }
        });
    </script>
</body>
</html>