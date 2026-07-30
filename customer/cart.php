<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'customer') {
    header("Location: ../login.php");
    exit();
}
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Get cart items with stock information
$query = "SELECT c.*, p.name, p.price, p.stock, p.image FROM cart c 
          JOIN products p ON c.product_id = p.id 
          WHERE c.user_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total = 0;
$stock_errors = [];
foreach ($cart_items as $item) {
    $subtotal = $item['price'] * $item['quantity'];
    $total += $subtotal;
    
    // Check if quantity exceeds stock
    if ($item['quantity'] > $item['stock']) {
        $stock_errors[] = [
            'product_id' => $item['product_id'],
            'product_name' => $item['name'],
            'requested' => $item['quantity'],
            'available' => $item['stock']
        ];
    }
}

// Fix stock issues automatically
if (isset($_POST['fix_stock'])) {
    foreach ($cart_items as $item) {
        if ($item['quantity'] > $item['stock']) {
            $stmt = $db->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$item['stock'], $item['id'], $user_id]);
        }
    }
    $_SESSION['cart_success'] = "Stock issues have been fixed automatically!";
    header("Location: cart.php");
    exit();
}

// Handle remove from cart
if (isset($_GET['remove']) && isset($_GET['cart_id'])) {
    $cart_id = intval($_GET['cart_id']);
    $stmt = $db->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
    $stmt->execute([$cart_id, $user_id]);
    $_SESSION['cart_success'] = "Item removed from cart successfully!";
    header("Location: cart.php");
    exit();
}

// Handle update cart (via POST)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_cart'])) {
    $has_error = false;
    foreach ($_POST['quantity'] as $cart_id => $quantity) {
        $quantity = intval($quantity);
        if ($quantity < 1) $quantity = 1;
        
        // Get max stock for this cart item
        $stmt = $db->prepare("SELECT p.stock FROM cart c JOIN products p ON c.product_id = p.id WHERE c.id = ? AND c.user_id = ?");
        $stmt->execute([$cart_id, $user_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product && $quantity <= $product['stock']) {
            $stmt = $db->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$quantity, $cart_id, $user_id]);
        } else {
            $has_error = true;
            $_SESSION['cart_error'] = "Quantity exceeds available stock for one or more items.";
        }
    }
    
    if (!$has_error) {
        $_SESSION['cart_success'] = "Cart updated successfully!";
    }
    header("Location: cart.php");
    exit();
}

// Display messages
$success_message = isset($_SESSION['cart_success']) ? $_SESSION['cart_success'] : '';
$error_message = isset($_SESSION['cart_error']) ? $_SESSION['cart_error'] : '';
unset($_SESSION['cart_success']);
unset($_SESSION['cart_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - ShopEMart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #f1f3f6;
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        
        /* Navigation */
        .navbar-daraz {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            padding: 0.8rem 0;
            box-shadow: 0 2px 20px rgba(0,0,0,0.2);
        }
        
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            padding: 5px 0;
        }
        
        .logo-img {
            height: 45px;
            width: auto;
            border-radius: 10px;
            transition: transform 0.3s;
        }
        
        .navbar-brand:hover .logo-img {
            transform: scale(1.05);
        }
        
        .logo-text {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }
        
        .brand-name {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            letter-spacing: -0.5px;
        }
        
        .brand-name span {
            color: #ff6600;
        }
        
        .brand-tagline {
            font-size: 0.7rem;
            color: rgba(255,255,255,0.6);
            letter-spacing: 2px;
            font-weight: 300;
        }
        
        .nav-link {
            color: white !important;
            font-weight: 500;
            transition: all 0.3s;
            padding: 8px 15px;
            border-radius: 8px;
            position: relative;
        }
        
        .nav-link:hover {
            background: rgba(255,102,0,0.2);
            color: #ff6600 !important;
            transform: translateY(-2px);
        }
        
        .nav-link.active {
            background: #ff6600;
            color: white !important;
        }
        
        .nav-link .badge {
            position: relative;
            top: -8px;
            left: -2px;
            font-size: 0.7rem;
        }
        
        /* Toast Notification */
        .toast-notification {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 9999;
            animation: slideInRight 0.3s ease;
            max-width: 350px;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        .cart-container {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            margin: 30px 0;
        }
        
        .cart-header {
            background: linear-gradient(135deg, #ff6600, #ff8533);
            color: white;
            padding: 25px 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .product-image {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .product-image-placeholder {
            width: 70px;
            height: 70px;
            border-radius: 12px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #999;
        }
        
        .quantity-input {
            width: 80px;
            text-align: center;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 8px;
            transition: all 0.3s;
        }
        
        .quantity-input:focus {
            border-color: #ff6600;
            outline: none;
            box-shadow: 0 0 0 3px rgba(255,102,0,0.1);
        }
        
        .quantity-input.invalid {
            border-color: #dc3545;
            background-color: #fff3f3;
        }
        
        .quantity-input.valid {
            border-color: #28a745;
            background-color: #f0fff4;
        }
        
        .stock-warning {
            font-size: 11px;
            color: #dc3545;
            margin-top: 5px;
            font-weight: 500;
        }
        
        .stock-info {
            font-size: 11px;
            color: #28a745;
            margin-top: 5px;
            font-weight: 500;
        }
        
        .error-alert {
            background: linear-gradient(135deg, #f8d7da, #fff5f5);
            border-left: 4px solid #dc3545;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .error-alert ul {
            margin: 10px 0 0 20px;
        }
        
        .error-alert li {
            margin: 5px 0;
        }
        
        .btn-fix {
            background: linear-gradient(135deg, #ffc107, #ff9800);
            color: #856404;
            border: none;
            padding: 10px 25px;
            border-radius: 50px;
            margin-top: 15px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-fix:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255,193,7,0.3);
        }
        
        .btn-checkout {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            padding: 12px 35px;
            border-radius: 50px;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .btn-checkout:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(40,167,69,0.4);
        }
        
        .btn-checkout:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-remove {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none;
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 12px;
            transition: all 0.3s;
            color: white;
        }
        
        .btn-remove:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(220,53,69,0.3);
            color: white;
        }
        
        .cart-empty {
            text-align: center;
            padding: 60px 20px;
        }
        
        .cart-empty i {
            font-size: 5rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        .table thead th {
            background: #f8f9fa;
            font-weight: 600;
            border-bottom: 2px solid #e0e0e0;
            padding: 15px;
        }
        
        .table tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.3s;
        }
        
        .table tbody tr:hover {
            background: #fff8f0;
        }
        
        .total-amount {
            font-size: 1.5rem;
            font-weight: bold;
            color: #ff6600;
        }
        
        .price-npr {
            font-weight: bold;
            color: #333;
        }
        
        .price-npr i {
            font-size: 12px;
            margin-right: 2px;
        }
        
        /* Trust Badge */
        .trust-badges {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .update-cart-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
            color: white;
        }
        
        .update-cart-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.3);
            color: white;
        }
        
        @media (max-width: 768px) {
            .cart-container {
                padding: 15px;
                overflow-x: auto;
            }
            .quantity-input {
                width: 60px;
            }
            .product-image {
                width: 50px;
                height: 50px;
            }
            .logo-img {
                height: 35px;
            }
            .brand-name {
                font-size: 1.3rem;
            }
            .brand-tagline {
                font-size: 0.6rem;
            }
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
                    <div class="brand-name">Shop<span>EMart</span></div>
                    <div class="brand-tagline">SHOP SMARTER</div>
                </div>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="shop.php">
                            <i class="fas fa-shopping-bag"></i> Shop
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="cart.php">
                            <i class="fas fa-shopping-cart"></i> Cart
                            <?php if(count($cart_items) > 0): ?>
                                <span class="badge bg-danger rounded-pill ms-1"><?php echo count($cart_items); ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="orders.php">
                            <i class="fas fa-history"></i> Orders
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Toast Notifications -->
    <?php if($success_message): ?>
    <div class="toast-notification">
        <div class="alert alert-success alert-dismissible fade show shadow-lg border-0 rounded-3">
            <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    <script>
        setTimeout(() => {
            document.querySelector('.toast-notification')?.remove();
        }, 3000);
    </script>
    <?php endif; ?>
    
    <?php if($error_message): ?>
    <div class="toast-notification">
        <div class="alert alert-danger alert-dismissible fade show shadow-lg border-0 rounded-3">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    <script>
        setTimeout(() => {
            document.querySelector('.toast-notification')?.remove();
        }, 4000);
    </script>
    <?php endif; ?>
    
    <div class="container">
        <div class="cart-container">
            <div class="cart-header">
                <div class="d-flex flex-wrap justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-0"><i class="fas fa-shopping-cart me-2"></i> Shopping Cart</h2>
                        <p class="mb-0 mt-2 opacity-75">Review and manage your items before checkout</p>
                    </div>
                    <div class="text-end mt-2 mt-md-0">
                        <span class="badge bg-light text-dark px-3 py-2 rounded-pill">
                            <i class="fas fa-box"></i> <?php echo count($cart_items); ?> Items
                        </span>
                    </div>
                </div>
            </div>
            
            <!-- Stock Error Messages -->
            <?php if(count($stock_errors) > 0): ?>
                <div class="error-alert">
                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-exclamation-triangle fa-2x me-3" style="color: #dc3545;"></i>
                        <div>
                            <strong class="h5 mb-0">Stock Issues Found!</strong>
                            <p class="mb-0 text-muted">Some items in your cart have exceeded available stock.</p>
                        </div>
                    </div>
                    <ul>
                        <?php foreach($stock_errors as $error): ?>
                            <li>
                                <strong><?php echo htmlspecialchars($error['product_name']); ?></strong>: 
                                Requested <?php echo $error['requested']; ?> items, 
                                but only <strong class="text-danger"><?php echo $error['available']; ?></strong> available.
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <form method="POST">
                        <button type="submit" name="fix_stock" class="btn btn-fix">
                            <i class="fas fa-magic"></i> Fix Automatically (Adjust to Max Available)
                        </button>
                    </form>
                </div>
            <?php endif; ?>
            
            <?php if(count($cart_items) > 0): ?>
                <form method="POST" id="cartForm">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Price (RS)</th>
                                    <th>Quantity</th>
                                    <th>Subtotal (RS)</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($cart_items as $item): 
                                    $has_stock_issue = $item['quantity'] > $item['stock'];
                                    $max_quantity = $item['stock'];
                                ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php 
                                                $image_path = '';
                                                if (!empty($item['image'])) {
                                                    if (file_exists("../uploads/" . $item['image'])) {
                                                        $image_path = "../uploads/" . $item['image'];
                                                    } elseif (file_exists("uploads/" . $item['image'])) {
                                                        $image_path = "uploads/" . $item['image'];
                                                    }
                                                }
                                                ?>
                                                <?php if($image_path): ?>
                                                    <img src="<?php echo $image_path; ?>" class="product-image me-3" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                                <?php else: ?>
                                                    <div class="product-image-placeholder me-3">
                                                        <i class="fas fa-box-open fa-2x"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <strong class="fs-6"><?php echo htmlspecialchars($item['name']); ?></strong>
                                                    <?php if($has_stock_issue): ?>
                                                        <div class="stock-warning">
                                                            <i class="fas fa-exclamation-circle"></i> Only <?php echo $item['stock']; ?> available!
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="stock-info">
                                                            <i class="fas fa-check-circle"></i> <?php echo $item['stock']; ?> in stock
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="price-npr">
                                                <i class="fas fa-rupee-sign"></i> <?php echo number_format($item['price'], 2); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <input type="number" 
                                                   name="quantity[<?php echo $item['id']; ?>]" 
                                                   value="<?php echo $item['quantity']; ?>" 
                                                   min="1" 
                                                   max="<?php echo $max_quantity; ?>"
                                                   class="quantity-input <?php echo $has_stock_issue ? 'invalid' : ''; ?>"
                                                   data-product-name="<?php echo htmlspecialchars($item['name']); ?>"
                                                   data-max-stock="<?php echo $max_quantity; ?>"
                                                   onchange="validateQuantity(this)">
                                        </td>
                                        <td>
                                            <span class="price-npr fw-bold text-primary">
                                                <i class="fas fa-rupee-sign"></i> <?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="cart.php?remove=1&cart_id=<?php echo $item['id']; ?>" 
                                               class="btn btn-remove btn-sm"
                                               onclick="return confirm('Remove this item from your cart?')">
                                                <i class="fas fa-trash-alt"></i> Remove
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th colspan="3" class="text-end">Total Amount:</th>
                                    <th colspan="2">
                                        <span class="total-amount">
                                            <i class="fas fa-rupee-sign"></i> <?php echo number_format($total, 2); ?>
                                        </span>
                                    </th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    
                    <div class="d-flex flex-wrap justify-content-between align-items-center mt-4 pt-3 border-top">
                        <div class="d-flex gap-2">
                            <a href="shop.php" class="btn btn-outline-primary rounded-pill px-4">
                                <i class="fas fa-arrow-left"></i> Continue Shopping
                            </a>
                            <button type="submit" name="update_cart" class="btn update-cart-btn">
                                <i class="fas fa-sync-alt"></i> Update Cart
                            </button>
                        </div>
                        
                        <?php if(count($stock_errors) > 0): ?>
                            <button class="btn btn-checkout text-white" disabled>
                                <i class="fas fa-lock"></i> Fix Stock Issues to Checkout
                            </button>
                        <?php else: ?>
                            <a href="checkout.php" class="btn btn-checkout text-white">
                                <i class="fas fa-credit-card"></i> Proceed to Checkout
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
                
                <!-- Trust Badges -->
                <div class="trust-badges text-center">
                    <small class="text-muted">
                        <i class="fas fa-lock me-1 text-success"></i> Secure Payment &nbsp;|&nbsp;
                        <i class="fas fa-truck me-1 text-primary"></i> Free Shipping on Orders RS 5000+ &nbsp;|&nbsp;
                        <i class="fas fa-undo-alt me-1 text-warning"></i> 7-Day Return Policy &nbsp;|&nbsp;
                        <i class="fas fa-headset me-1 text-info"></i> 24/7 Support
                    </small>
                </div>
            <?php else: ?>
                <div class="cart-empty">
                    <i class="fas fa-shopping-cart"></i>
                    <h4 class="mt-3">Your cart is empty</h4>
                    <p class="text-muted">Looks like you haven't added any items to your cart yet.</p>
                    <a href="shop.php" class="btn btn-primary rounded-pill px-4 mt-2" style="background: linear-gradient(135deg, #ff6600, #ff8533); border: none;">
                        <i class="fas fa-store"></i> Start Shopping
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function validateQuantity(input) {
            const maxStock = parseInt(input.getAttribute('data-max-stock'));
            const requestedQty = parseInt(input.value);
            const productName = input.getAttribute('data-product-name');
            
            // Validate quantity
            if (isNaN(requestedQty) || requestedQty < 1) {
                alert('Please enter a valid quantity (minimum 1)');
                input.value = 1;
                input.classList.remove('invalid');
                input.classList.add('valid');
                setTimeout(() => {
                    input.classList.remove('valid');
                }, 1500);
                return;
            }
            
            if (requestedQty > maxStock) {
                // Show alert message
                alert(`⚠️ Only ${maxStock} "${productName}" available in stock.\n\nYour quantity has been adjusted to ${maxStock}.`);
                
                // Auto-correct to max stock
                input.value = maxStock;
                input.classList.add('invalid');
                input.classList.remove('valid');
            } else {
                input.classList.remove('invalid');
                input.classList.add('valid');
                setTimeout(() => {
                    input.classList.remove('valid');
                }, 1500);
            }
        }
        
        // Add visual feedback for quantity inputs
        document.querySelectorAll('.quantity-input').forEach(input => {
            input.addEventListener('change', function() {
                const maxStock = parseInt(this.getAttribute('data-max-stock'));
                const currentValue = parseInt(this.value);
                
                if (currentValue > maxStock) {
                    this.classList.add('invalid');
                    this.classList.remove('valid');
                } else if (currentValue >= 1 && !isNaN(currentValue)) {
                    this.classList.remove('invalid');
                    this.classList.add('valid');
                    setTimeout(() => {
                        this.classList.remove('valid');
                    }, 2000);
                }
            });
            
            input.addEventListener('keyup', function() {
                const maxStock = parseInt(this.getAttribute('data-max-stock'));
                const currentValue = parseInt(this.value);
                
                if (currentValue > maxStock) {
                    this.classList.add('invalid');
                    this.classList.remove('valid');
                } else if (currentValue >= 1 && !isNaN(currentValue)) {
                    this.classList.remove('invalid');
                }
            });
        });
        
        // Auto-hide toast notifications
        setTimeout(() => {
            document.querySelectorAll('.toast-notification').forEach(toast => {
                toast.style.transition = 'opacity 0.5s';
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>