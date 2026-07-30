<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

// Database connection
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Get user info
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get selected items from session or all cart items
$selectedIds = $_SESSION['checkout_item_ids'] ?? [];

// If no specific items selected, get all cart items
if (empty($selectedIds)) {
    $stmt = $db->prepare("SELECT product_id FROM cart WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $allCartIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($allCartIds)) {
        header("Location: cart.php");
        exit();
    }
    $_SESSION['checkout_item_ids'] = $allCartIds;
    $selectedIds = $allCartIds;
}

// Get cart items with stock validation
$placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
$query = "SELECT c.*, p.name, p.price, p.stock, p.image, (c.quantity * p.price) as subtotal 
          FROM cart c 
          JOIN products p ON c.product_id = p.id 
          WHERE c.user_id = ? AND c.product_id IN ($placeholders)";
$stmt = $db->prepare($query);

// Bind parameters dynamically
$params = array_merge([$user_id], $selectedIds);
foreach ($params as $i => $param) {
    $stmt->bindValue($i + 1, $param);
}
$stmt->execute();
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If cart is empty after filtering
if (empty($cart_items)) {
    header("Location: cart.php");
    exit();
}

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

// ============================================
// CALCULATE TOTALS WITH VAT
// ============================================
$subtotal = array_sum(array_column($cart_items, 'subtotal'));

// VAT Rate: 13%
$vat_rate = 0.13;
$vat_amount = $subtotal * $vat_rate;

// Delivery Charge: Free for orders above NPR 2000, otherwise NPR 150
$delivery_charge = ($subtotal >= 2000) ? 0 : 150;

// Grand Total = Subtotal + VAT + Delivery Charge
$grand_total = $subtotal + $vat_amount + $delivery_charge;

// Check if cart is empty
if (empty($cart_items)) {
    header("Location: cart.php?error=cart_empty");
    exit();
}

// Initialize error array
$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['full_name'])) {
    // Check if already processing an order
    if (isset($_SESSION['pending_order_id']) && isset($_SESSION['order_form_submitted'])) {
        header("Location: esewa_payment.php?order_id=" . $_SESSION['pending_order_id'] . "&amount=" . $_SESSION['pending_amount']);
        exit();
    }
    
    // Sanitize and validate inputs
    $full_name = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $notes = trim($_POST['notes'] ?? '');
    $payment_method = $_POST['payment_method'] ?? 'esewa';
    
    // Validation
    if (strlen($full_name) < 2) {
        $errors['full_name'] = 'Full name is required.';
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Valid email is required.';
    }
    
    if (!preg_match('/^(98|97|96)\d{8}$/', $phone)) {
        $errors['phone'] = 'Valid Nepal phone number required (98XXXXXXXX).';
    }
    
    if (empty($address)) {
        $errors['address'] = 'Delivery address is required.';
    }
    
    if (empty($city)) {
        $errors['city'] = 'City is required.';
    }
    
    // If no validation errors, process the order
    if (empty($errors)) {
        try {
            // Double-check stock before creating order
            foreach ($cart_items as $item) {
                $stmt = $db->prepare("SELECT stock FROM products WHERE id = ?");
                $stmt->execute([$item['product_id']]);
                $current_stock = $stmt->fetchColumn();
                
                if ($item['quantity'] > $current_stock) {
                    $error = "Sorry, {$item['name']} only has {$current_stock} items in stock. Please adjust your cart.";
                    throw new Exception($error);
                }
            }
            
            $db->beginTransaction();
            
            // Generate unique order number
            $order_number = 'ORD-' . date('Ymd') . '-' . time() . '-' . rand(1000, 9999);
            
            // Combine address and city for shipping_address
            $shippingAddress = $address;
            if (!empty($city)) {
                $shippingAddress .= ', ' . $city;
            }
            
            // Insert order - using only columns that exist in your table
            $stmt = $db->prepare("INSERT INTO orders (order_number, user_id, total_amount, payment_method, payment_status, status, shipping_address) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $order_number,
                $user_id,
                $grand_total,
                $payment_method,
                'pending',
                'pending',
                $shippingAddress
            ]);
            $order_id = $db->lastInsertId();
            
            // Add order items and update stock
            foreach ($cart_items as $item) {
                // Insert order item
                $stmt = $db->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
                $stmt->execute([$order_id, $item['product_id'], $item['quantity'], $item['price']]);
                
                // Update product stock
                $stmt = $db->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
                $stmt->execute([$item['quantity'], $item['product_id'], $item['quantity']]);
            }
            
            // Clear cart (only selected items)
            $placeholders_clear = implode(',', array_fill(0, count($selectedIds), '?'));
            $stmt = $db->prepare("DELETE FROM cart WHERE user_id = ? AND product_id IN ($placeholders_clear)");
            $params_clear = array_merge([$user_id], $selectedIds);
            foreach ($params_clear as $i => $param) {
                $stmt->bindValue($i + 1, $param);
            }
            $stmt->execute();
            
            $db->commit();
            
            // Store pending order info in session
            $_SESSION['pending_order_id'] = $order_id;
            $_SESSION['pending_amount'] = $grand_total;
            $_SESSION['order_form_submitted'] = true;
            $_SESSION['checkout_item_ids'] = []; // Clear selected items
            
            // Redirect to payment
            if ($payment_method == 'esewa') {
                header("Location: esewa_payment.php?order_id=" . $order_id . "&amount=" . $grand_total);
                exit();
            } else {
                // Cash on Delivery
                $_SESSION['order_success'] = "Order placed successfully! You will pay cash upon delivery.";
                header("Location: orders.php?success=1");
                exit();
            }
            
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Order failed: " . $e->getMessage();
        }
    }
}

// Page title for header
$page_title = "Checkout";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - ShopEmart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #f0f2f5;
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Navigation */
        .navbar-daraz {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            padding: 0.8rem 0;
            box-shadow: 0 2px 20px rgba(0,0,0,0.2);
        }
        
        .navbar-brand {
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .logo-img {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #ff6600;
            transition: transform 0.3s ease;
        }
        
        .navbar-brand:hover .logo-img {
            transform: scale(1.1) rotate(-5deg);
        }
        
        .logo-text .brand-name {
            font-size: 1.6rem;
            font-weight: 800;
            color: white;
            letter-spacing: -0.5px;
        }
        
        .logo-text .brand-name span {
            color: #ff6600;
        }
        
        .nav-link {
            color: white !important;
            font-weight: 500;
            transition: all 0.3s;
            padding: 8px 15px;
            border-radius: 8px;
        }
        .nav-link:hover {
            background: rgba(255,102,0,0.2);
            color: #ff6600 !important;
            transform: translateY(-2px);
        }
        
        /* Checkout Header */
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
        
        .checkout-header h1 {
            font-weight: 700;
            position: relative;
            z-index: 1;
        }
        
        .checkout-header p {
            position: relative;
            z-index: 1;
            opacity: 0.9;
        }
        
        .secure-badge {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            padding: 10px 20px;
            border-radius: 50px;
            font-size: 0.85rem;
            position: relative;
            z-index: 1;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        /* Main Content */
        .checkout-form {
            background: white;
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
        }
        
        .order-summary {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.08);
            position: sticky;
            top: 20px;
        }
        
        .section-title {
            font-weight: 600;
            font-size: 1.1rem;
            color: #1a1a2e;
            margin-bottom: 20px;
        }
        
        .section-title i {
            color: #667eea;
        }
        
        /* Form Styles */
        .form-control, .form-select {
            border-radius: 12px;
            border: 2px solid #e8ecf1;
            padding: 12px 15px;
            transition: all 0.3s;
            font-family: 'Poppins', sans-serif;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102,126,234,0.1);
            outline: none;
        }
        
        .form-control.is-invalid, .form-select.is-invalid {
            border-color: #dc3545;
        }
        
        .invalid-feedback {
            font-size: 0.8rem;
        }
        
        .form-control:disabled {
            background: #f8f9fa;
            cursor: not-allowed;
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-label {
            font-weight: 500;
            font-size: 0.9rem;
            color: #2d3436;
        }
        
        .form-label .text-danger {
            color: #e74c3c;
        }
        
        /* Payment Methods - Modern Card Style */
        .payment-methods {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 10px;
        }
        
        .payment-card {
            display: flex;
            align-items: center;
            padding: 20px 25px;
            background: white;
            border: 2px solid #e8ecf1;
            border-radius: 16px;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            position: relative;
            overflow: hidden;
        }
        
        .payment-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(102,126,234,0.03) 0%, rgba(118,75,162,0.03) 100%);
            opacity: 0;
            transition: opacity 0.4s ease;
        }
        
        .payment-card:hover {
            border-color: #667eea;
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(102,126,234,0.15);
        }
        
        .payment-card:hover::before {
            opacity: 1;
        }
        
        .payment-card.selected {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(102,126,234,0.05) 0%, rgba(118,75,162,0.05) 100%);
            box-shadow: 0 8px 30px rgba(102,126,234,0.15);
        }
        
        .payment-card.selected::after {
            content: '✓';
            position: absolute;
            top: 12px;
            right: 20px;
            color: #667eea;
            font-weight: 700;
            font-size: 1.2rem;
        }
        
        .payment-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        
        .payment-logo-wrapper {
            width: 70px;
            height: 70px;
            min-width: 70px;
            border-radius: 14px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            transition: all 0.3s ease;
            border: 1px solid #e8ecf1;
            padding: 8px;
        }
        
        .payment-card:hover .payment-logo-wrapper {
            border-color: #667eea;
            transform: scale(1.05);
        }
        
        .payment-logo {
            width: 100%;
            height: 100%;
            max-width: 55px;
            max-height: 50px;
            object-fit: contain;
        }
        
        .payment-icon {
            font-size: 2rem;
        }
        
        .payment-icon.esewa {
            color: #0B4F6C;
        }
        
        .payment-icon.cod {
            color: #28a745;
        }
        
        .payment-info {
            flex: 1;
        }
        
        .payment-info h5 {
            font-weight: 600;
            font-size: 1rem;
            margin: 0 0 3px 0;
            color: #1a1a2e;
        }
        
        .payment-info p {
            font-size: 0.85rem;
            color: #636e72;
            margin: 0;
        }
        
        .payment-badge {
            display: inline-block;
            padding: 2px 12px;
            border-radius: 50px;
            font-size: 0.65rem;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .payment-badge.available {
            background: #28a745;
            color: white;
        }
        
        .payment-badge.popular {
            background: #e74c3c;
            color: white;
        }
        
        .payment-detail {
            font-size: 0.75rem;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .payment-detail i {
            margin-right: 5px;
        }
        
        /* Product Image Small */
        .product-image-small {
            width: 55px;
            height: 55px;
            object-fit: cover;
            border-radius: 10px;
        }
        
        .product-placeholder-small {
            width: 55px;
            height: 55px;
            background: #f8f9fa;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #b2bec3;
        }
        
        /* Order Items Scroll */
        .order-items {
            max-height: 320px;
            overflow-y: auto;
        }
        
        .order-items::-webkit-scrollbar {
            width: 4px;
        }
        
        .order-items::-webkit-scrollbar-track {
            background: #f1f2f6;
            border-radius: 10px;
        }
        
        .order-items::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 10px;
        }
        
        .order-item {
            padding: 12px 0;
            border-bottom: 1px solid #f1f2f6;
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        /* Totals */
        .total-line {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 0.95rem;
        }
        
        .total-line .label {
            color: #2d3436;
        }
        
        .total-line .value {
            font-weight: 600;
        }
        
        .total-line .value.text-success {
            color: #28a745 !important;
        }
        
        .total-line.total {
            font-size: 1.2rem;
            font-weight: 700;
            padding-top: 15px;
            border-top: 2px solid #f1f2f6;
        }
        
        .total-line.total .value {
            color: #667eea;
            font-size: 1.4rem;
        }
        
        .total-line.vat-line .value {
            color: #e67e22;
        }
        
        .total-line.delivery-line .value {
            color: #2ecc71;
        }
        
        /* Checkout Button */
        .btn-checkout {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 16px;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 50px;
            margin-top: 25px;
            transition: all 0.3s ease;
            width: 100%;
            color: white;
        }
        
        .btn-checkout:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(102,126,234,0.4);
            color: white;
        }
        
        .btn-checkout:active {
            transform: translateY(0);
        }
        
        .btn-checkout:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        /* Trust Badges */
        .trust-badges {
            display: flex;
            justify-content: center;
            gap: 25px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #f1f2f6;
        }
        
        .trust-badge {
            text-align: center;
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        .trust-badge i {
            font-size: 1.2rem;
            display: block;
            margin-bottom: 5px;
            color: #667eea;
        }
        
        /* Footer */
        footer {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            margin-top: 60px;
            color: white;
            padding: 30px 0;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .checkout-header {
                padding: 25px 0;
            }
            
            .checkout-form, .order-summary {
                padding: 20px;
            }
            
            .payment-card {
                padding: 15px 18px;
            }
            
            .payment-logo-wrapper {
                width: 55px;
                height: 55px;
                min-width: 55px;
                margin-right: 15px;
            }
            
            .payment-logo {
                max-width: 45px;
                max-height: 40px;
            }
            
            .payment-info h5 {
                font-size: 0.9rem;
            }
            
            .payment-info p {
                font-size: 0.75rem;
            }
            
            .payment-badge {
                font-size: 0.55rem;
                padding: 1px 8px;
            }
            
            .total-line.total .value {
                font-size: 1.1rem;
            }
            
            .trust-badges {
                gap: 15px;
                flex-wrap: wrap;
            }
            
            .logo-text .brand-name {
                font-size: 1.3rem;
            }
            
            .logo-img {
                width: 35px;
                height: 35px;
            }
        }
        
        @media (max-width: 576px) {
            .checkout-header h1 {
                font-size: 1.5rem;
            }
            
            .payment-card {
                padding: 12px 15px;
            }
            
            .payment-logo-wrapper {
                width: 45px;
                height: 45px;
                min-width: 45px;
                margin-right: 12px;
            }
            
            .payment-logo {
                max-width: 35px;
                max-height: 32px;
            }
            
            .order-summary {
                margin-top: 20px;
            }
        }
        
        /* VAT & Delivery Info Tooltip */
        .info-tooltip {
            cursor: help;
            color: #6c757d;
            font-size: 0.8rem;
            margin-left: 4px;
        }
        
        .info-tooltip:hover {
            color: #667eea;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-daraz">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <img src="../assets/images/ss.jpg" alt="ShopVerse Logo" class="logo-img">
                <div class="logo-text">
                    <div class="brand-name">Shop<span>Emart</span></div>
                </div>
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
    
    <!-- Checkout Header -->
    <div class="checkout-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-7">
                    <h1><i class="fas fa-credit-card me-2"></i> Checkout</h1>
                    <p class="lead mb-0">Complete your purchase securely</p>
                </div>
                <div class="col-md-5 text-end">
                    <div class="secure-badge d-inline-block">
                        <i class="fas fa-lock me-2"></i> 100% Secure Payment
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container mb-5">
        <?php if(isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Checkout Form - Left Column -->
            <div class="col-lg-7">
                <div class="checkout-form">
                    <div class="section-title">
                        <i class="fas fa-user-circle me-2"></i> Shipping Information
                    </div>
                    
                    <form method="POST" id="checkoutForm">
                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control <?php echo isset($errors['full_name']) ? 'is-invalid' : ''; ?>" 
                                       value="<?php echo htmlspecialchars($_POST['full_name'] ?? $user['name'] ?? ''); ?>" required>
                                <?php if(isset($errors['full_name'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['full_name']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control <?php echo isset($errors['email']) ? 'is-invalid' : ''; ?>" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? $user['email'] ?? ''); ?>" required>
                                <?php if(isset($errors['email'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" name="phone" class="form-control <?php echo isset($errors['phone']) ? 'is-invalid' : ''; ?>" 
                                       placeholder="98XXXXXXXX" value="<?php echo htmlspecialchars($_POST['phone'] ?? $user['phone'] ?? ''); ?>" required>
                                <?php if(isset($errors['phone'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['phone']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Shipping Address <span class="text-danger">*</span></label>
                                <input type="text" name="address" class="form-control <?php echo isset($errors['address']) ? 'is-invalid' : ''; ?>" 
                                       placeholder="Street, Area, Landmark" value="<?php echo htmlspecialchars($_POST['address'] ?? $user['address'] ?? ''); ?>" required>
                                <?php if(isset($errors['address'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['address']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">City <span class="text-danger">*</span></label>
                                <select name="city" class="form-select <?php echo isset($errors['city']) ? 'is-invalid' : ''; ?>" required>
                                    <option value="">Select city...</option>
                                    <?php 
                                    $cities = ['Kathmandu', 'Lalitpur', 'Bhaktapur', 'Pokhara', 'Biratnagar', 'Birgunj', 'Butwal', 'Dharan', 'Hetauda', 'Itahari', 'Janakpur', 'Nepalgunj'];
                                    $selected_city = $_POST['city'] ?? $user['city'] ?? '';
                                    foreach ($cities as $c): ?>
                                        <option value="<?php echo $c; ?>" <?php echo ($selected_city === $c) ? 'selected' : ''; ?>>
                                            <?php echo $c; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if(isset($errors['city'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['city']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Order Notes (Optional)</label>
                                <textarea name="notes" class="form-control" rows="2" placeholder="Special delivery instructions..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <div class="section-title mt-4">
                            <i class="fas fa-wallet me-2"></i> Select Payment Method
                        </div>
                        
                        <!-- Payment Methods -->
                        <div class="payment-methods">
                            <!-- eSewa -->
                            <label class="payment-card selected" onclick="selectPayment('esewa')">
                                <input type="radio" name="payment_method" value="esewa" id="esewa" checked>
                                <div class="payment-logo-wrapper">
                                    <img src="../assets/images/esewa-logo.jpg" alt="eSewa" class="payment-logo">
                                </div>
                                <div class="payment-info">
                                    <div>
                                        <h5>eSewa Digital Wallet</h5>
                                        <span class="payment-badge popular"><i class="fas fa-fire me-1"></i> Most Used</span>
                                    </div>
                                    <p>Pay instantly using your eSewa account. Secure and fast digital payment.</p>
                                    <div class="payment-detail text-success">
                                        <i class="fas fa-check-circle"></i> Secure & Instant Payment
                                    </div>
                                </div>
                            </label>
                            
                            <!-- Cash on Delivery -->
                            <label class="payment-card" onclick="selectPayment('cod')">
                                <input type="radio" name="payment_method" value="cod" id="cod">
                                <div class="payment-logo-wrapper">
                                    <img src="../assets/images/cod-logo.jpg" alt="Cash on Delivery" class="payment-logo">
                                </div>
                                <div class="payment-info">
                                    <div>
                                        <h5>Cash on Delivery</h5>
                                        <span class="payment-badge available"><i class="fas fa-check-circle me-1"></i> Available</span>
                                    </div>
                                    <p>Pay with cash when your order is delivered to your doorstep.</p>
                                    <div class="payment-detail text-success">
                                        <i class="fas fa-check-circle"></i> No additional charges for COD
                                    </div>
                                </div>
                            </label>
                        </div>
                        
                        <button type="submit" class="btn-checkout" id="placeOrderBtn">
                            <i class="fas fa-lock me-2"></i> Place Order
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Order Summary - Right Column -->
            <div class="col-lg-5">
                <div class="order-summary">
                    <div class="section-title">
                        <i class="fas fa-shopping-bag me-2"></i> Order Summary
                    </div>
                    
                    <div class="order-items">
                        <?php foreach($cart_items as $item): ?>
                            <div class="order-item d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <?php if(!empty($item['image']) && file_exists("../uploads/" . $item['image'])): ?>
                                        <img src="../uploads/<?php echo $item['image']; ?>" class="product-image-small me-3" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                    <?php else: ?>
                                        <div class="product-placeholder-small me-3">
                                            <i class="fas fa-box"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="fw-semibold" style="font-size: 0.9rem;"><?php echo htmlspecialchars($item['name']); ?></div>
                                        <small class="text-muted">Qty: <?php echo $item['quantity']; ?></small>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <div class="fw-bold text-primary">
                                        NPR <?php echo number_format($item['subtotal'], 2); ?>
                                    </div>
                                    <small class="text-muted">
                                        NPR <?php echo number_format($item['price'], 2); ?> each
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <hr>
                    
                    <!-- ============================================ -->
                    <!-- TOTALS WITH VAT AND DELIVERY CHARGE          -->
                    <!-- ============================================ -->
                    
                    <!-- Subtotal -->
                    <div class="total-line">
                        <span class="label">Subtotal</span>
                        <span class="value">NPR <?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    
                    <!-- VAT (13%) -->
                    <div class="total-line vat-line">
                        <span class="label">
                            VAT (13%)
                            <i class="fas fa-info-circle info-tooltip" title="Value Added Tax (13%) applied as per government regulations."></i>
                        </span>
                        <span class="value">NPR <?php echo number_format($vat_amount, 2); ?></span>
                    </div>
                    
                    <!-- Delivery Charge -->
                    <div class="total-line delivery-line">
                        <span class="label">
                            Delivery Charge
                            <?php if($delivery_charge === 0): ?>
                                <i class="fas fa-info-circle info-tooltip" title="Free delivery on orders above NPR 2,000."></i>
                            <?php else: ?>
                                <i class="fas fa-info-circle info-tooltip" title="Standard delivery charge for orders below NPR 2,000."></i>
                            <?php endif; ?>
                        </span>
                        <?php if($delivery_charge === 0): ?>
                            <span class="value text-success">FREE</span>
                        <?php else: ?>
                            <span class="value">NPR <?php echo number_format($delivery_charge, 2); ?></span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Grand Total -->
                    <div class="total-line total">
                        <span class="label">Total Amount</span>
                        <span class="value">NPR <?php echo number_format($grand_total, 2); ?></span>
                    </div>
                    
                    <!-- ============================================ -->
                    <!-- TERMS & CONDITIONS                           -->
                    <!-- ============================================ -->
                    <div class="mt-3 pt-2" style="border-top: 1px solid #f1f2f6;">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="termsCheck" required>
                            <label class="form-check-label" for="termsCheck" style="font-size: 0.8rem; color: #6c757d;">
                                I agree to the 
                                <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal" style="color: #667eea; text-decoration: none; font-weight: 500;">
                                    Terms & Conditions
                                </a>
                                and 
                                <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal" style="color: #667eea; text-decoration: none; font-weight: 500;">
                                    Privacy Policy
                                </a>
                            </label>
                        </div>
                        <div class="mt-2" style="font-size: 0.7rem; color: #adb5bd; text-align: center;">
                            <i class="fas fa-shield-alt me-1"></i> Your information is secure and will only be used for order processing.
                        </div>
                    </div>
                    
                    <!-- Trust Badges -->
                    <div class="trust-badges">
                        <div class="trust-badge">
                            <i class="fas fa-shield-alt"></i>
                            Secure Payment
                        </div>
                        <div class="trust-badge">
                            <i class="fas fa-truck"></i>
                            Fast Delivery
                        </div>
                        <div class="trust-badge">
                            <i class="fas fa-headset"></i>
                            24/7 Support
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- ============================================ -->
    <!-- TERMS & CONDITIONS MODAL                     -->
    <!-- ============================================ -->
    <div class="modal fade" id="termsModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <h5 class="modal-title"><i class="fas fa-file-contract me-2"></i> Terms & Conditions</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6 class="fw-bold">1. General</h6>
                    <p>By placing an order on ShopEmart, you agree to these terms and conditions. These terms apply to all orders placed through our website.</p>
                    
                    <h6 class="fw-bold mt-3">2. Pricing & Payments</h6>
                    <ul>
                        <li>All prices are listed in Nepalese Rupees (NPR).</li>
                        <li>VAT of 13% is applicable on all orders as per government regulations.</li>
                        <li>Delivery charges are calculated based on order value.</li>
                        <li>Free delivery is provided for orders above NPR 2,000.</li>
                        <li>Payment can be made via eSewa or Cash on Delivery (COD).</li>
                    </ul>
                    
                    <h6 class="fw-bold mt-3">3. Order Processing</h6>
                    <ul>
                        <li>Orders are processed within 24-48 hours of confirmation.</li>
                        <li>You will receive a confirmation email and SMS with your order details.</li>
                        <li>In case of stock unavailability, we will contact you for alternatives or cancellation.</li>
                    </ul>
                    
                    <h6 class="fw-bold mt-3">4. Delivery</h6>
                    <ul>
                        <li>We deliver to all major cities across Nepal.</li>
                        <li>Standard delivery time is 3-5 business days.</li>
                        <li>We are not responsible for delays caused by weather, traffic, or other unforeseen circumstances.</li>
                    </ul>
                    
                    <h6 class="fw-bold mt-3">5. Cancellation & Returns</h6>
                    <ul>
                        <li>Orders can be cancelled within 24 hours of placing the order.</li>
                        <li>Returns are accepted within 7 days of delivery for defective or incorrect products.</li>
                        <li>Products must be in original packaging and unused condition for returns.</li>
                    </ul>
                    
                    <h6 class="fw-bold mt-3">6. Privacy</h6>
                    <p>Your personal information is collected and used solely for order processing and delivery purposes. We do not share your information with third parties.</p>
                    
                    <h6 class="fw-bold mt-3">7. Contact</h6>
                    <p>For any inquiries, contact us at support@shopemart.com or call us at +977-1-4XXXXXX.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                        <i class="fas fa-check me-1"></i> I Understand
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Privacy Policy Modal -->
    <div class="modal fade" id="privacyModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                    <h5 class="modal-title"><i class="fas fa-shield-alt me-2"></i> Privacy Policy</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6 class="fw-bold">Information We Collect</h6>
                    <ul>
                        <li><strong>Personal Information:</strong> Name, email address, phone number, shipping address.</li>
                        <li><strong>Payment Information:</strong> Payment details are processed through secure payment gateways. We do not store your payment information.</li>
                        <li><strong>Order Information:</strong> Products you purchase, order history, and delivery preferences.</li>
                    </ul>
                    
                    <h6 class="fw-bold mt-3">How We Use Your Information</h6>
                    <ul>
                        <li>To process and deliver your orders</li>
                        <li>To send order confirmations and updates</li>
                        <li>To provide customer support</li>
                        <li>To improve our services and user experience</li>
                    </ul>
                    
                    <h6 class="fw-bold mt-3">Information Security</h6>
                    <ul>
                        <li>We implement industry-standard security measures to protect your data.</li>
                        <li>All transactions are encrypted using SSL technology.</li>
                        <li>We do not sell or share your personal information with third parties.</li>
                    </ul>
                    
                    <h6 class="fw-bold mt-3">Your Rights</h6>
                    <ul>
                        <li>You can access, update, or delete your personal information at any time.</li>
                        <li>You can opt-out of promotional communications.</li>
                        <li>You can request a copy of the data we hold about you.</li>
                    </ul>
                    
                    <h6 class="fw-bold mt-3">Contact</h6>
                    <p>If you have questions about our privacy policy, please contact us at privacy@shopemart.com</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
                        <i class="fas fa-check me-1"></i> I Understand
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-6 mx-auto text-center">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> ShopEmart - Your Trusted Online Shopping Destination</p>
                    <small class="text-muted">
                        <i class="fas fa-lock me-1"></i> Secure Payments | 
                        <i class="fas fa-truck me-1"></i> Fast Delivery | 
                        <i class="fas fa-headset me-1"></i> 24/7 Support
                    </small>
                </div>
            </div>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function selectPayment(method) {
            // Uncheck all radio buttons
            document.querySelectorAll('input[name="payment_method"]').forEach(input => {
                input.checked = false;
            });
            
            // Check the selected one
            document.getElementById(method).checked = true;
            
            // Update selected style
            document.querySelectorAll('.payment-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Add selected class to parent
            const selectedCard = document.getElementById(method).closest('.payment-card');
            if (selectedCard) {
                selectedCard.classList.add('selected');
            }
        }
        
        // Form validation and loading state
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            const address = document.querySelector('input[name="address"]').value.trim();
            const city = document.querySelector('select[name="city"]').value;
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            const termsCheck = document.getElementById('termsCheck');
            
            if (!address) {
                e.preventDefault();
                alert('⚠️ Please enter your shipping address');
                return false;
            }
            
            if (!city) {
                e.preventDefault();
                alert('⚠️ Please select your city');
                return false;
            }
            
            if (!paymentMethod) {
                e.preventDefault();
                alert('⚠️ Please select a payment method');
                return false;
            }
            
            if (!termsCheck.checked) {
                e.preventDefault();
                alert('⚠️ Please agree to the Terms & Conditions to proceed.');
                return false;
            }
            
            // Show loading state
            const submitBtn = document.getElementById('placeOrderBtn');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Processing Order...';
            submitBtn.disabled = true;
        });
        
        // Highlight default selected payment (eSewa)
        document.addEventListener('DOMContentLoaded', function() {
            // eSewa is checked by default
            const esewaCard = document.getElementById('esewa').closest('.payment-card');
            if (esewaCard) {
                esewaCard.classList.add('selected');
            }
        });
    </script>
</body>
</html>