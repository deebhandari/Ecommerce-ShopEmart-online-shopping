<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get page parameter for navigation
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// Fetch featured products for home page
$query = "SELECT * FROM products WHERE status = 'active' ORDER BY id DESC LIMIT 8";
$stmt = $db->prepare($query);
$stmt->execute();
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all products for products page
$all_products_query = "SELECT * FROM products WHERE status = 'active' ORDER BY id DESC";
$all_products_stmt = $db->prepare($all_products_query);
$all_products_stmt->execute();
$all_products = $all_products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get cart count for badge
$cart_count = 0;
if (isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'customer') {
    $cart_stmt = $db->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
    $cart_stmt->execute([$_SESSION['user_id']]);
    $cart_count = $cart_stmt->fetchColumn() ?: 0;
}

// If page is customercart, include the cart page content
if ($page == 'customercart') {
    include 'customer/cart.php';
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
    <title>ShopVerse - Online Shopping System</title>
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
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
        }
        
        /* Toast Notification */
        .toast-notification {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 9999;
            animation: slideInRight 0.3s ease;
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
        
        /* Navigation Bar */
        .navbar {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-size: 1.8rem;
            font-weight: bold;
            color: white !important;
        }
        
        .navbar-brand i {
            color: #667eea;
        }
        
        .nav-link {
            color: white !important;
            font-weight: 500;
            margin: 0 5px;
            transition: all 0.3s;
            border-radius: 8px;
        }
        
        .nav-link:hover {
            background: rgba(102,126,234,0.3);
            transform: translateY(-2px);
        }
        
        .nav-link.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }
        
        /* Cart Badge */
        .cart-badge {
            position: relative;
            top: -8px;
            right: 5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 10px;
            font-weight: bold;
        }
        
        .nav-link .badge {
            margin-left: 5px;
            font-size: 11px;
        }
        
        .dropdown-menu {
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: none;
            margin-top: 10px;
        }
        
        .dropdown-item {
            padding: 10px 20px;
            transition: all 0.3s;
        }
        
        .dropdown-item:hover {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .dropdown-item i {
            width: 25px;
        }
        
        /* Product Card */
        .product-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border: none;
            border-radius: 15px;
            overflow: hidden;
            background: white;
            height: 100%;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
        }
        
        .product-image-container {
            height: 200px;
            overflow: hidden;
            background: #f5f5f5;
            position: relative;
        }
        
        .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .product-card:hover .product-image {
            transform: scale(1.1);
        }
        
        .product-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        
        .product-placeholder i {
            font-size: 4rem;
            color: #667eea;
        }
        
        /* Quantity Input */
        .quantity-input {
            width: 60px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 5px;
            font-size: 0.85rem;
        }
        
        .quantity-input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn-add-cart {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            transition: all 0.3s;
        }
        
        .btn-add-cart:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40,167,69,0.3);
        }
        
        .btn-login-buy {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            transition: all 0.3s;
        }
        
        .btn-login-buy:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }
        
        /* Hero Section */
        .hero-section {
            position: relative;
            border-radius: 20px;
            margin-bottom: 50px;
            overflow: hidden;
            min-height: 550px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: white;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('assets/images/hero-banner.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            filter: brightness(0.6);
            z-index: 0;
        }
        
        .hero-section::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(102,126,234,0.3) 0%, rgba(118,75,162,0.3) 100%);
            z-index: 1;
        }
        
        .hero-section.no-image::before {
            background-image: none;
        }
        
        .hero-section.no-image {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .hero-section.no-image::after {
            display: none;
        }
        
        .hero-content {
            position: relative;
            z-index: 2;
            padding: 80px 20px;
        }
        
        .hero-content h1 {
            font-size: 3.5rem;
            font-weight: bold;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            animation: fadeInDown 0.8s ease;
        }
        
        .hero-content p {
            font-size: 1.3rem;
            margin-bottom: 30px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
            animation: fadeInUp 0.8s ease 0.2s both;
        }
        
        .hero-content .btn {
            animation: fadeIn 0.8s ease 0.4s both;
        }
        
        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102,126,234,0.5);
        }
        
        .section-title {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
        }
        
        .section-title h2 {
            font-size: 2.5rem;
            font-weight: bold;
            color: #2c3e50;
            display: inline-block;
            position: relative;
            padding-bottom: 15px;
        }
        
        .section-title h2::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 4px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 2px;
        }
        
        /* About Section */
        .about-section .card, .contact-section .card {
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: transform 0.3s;
            border: none;
        }
        
        .about-section .card:hover, .contact-section .card:hover {
            transform: translateY(-5px);
        }
        
        .stat-box {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
        }
        
        .stat-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }
        
        .stat-box i {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .stat-box h3 {
            font-size: 2rem;
            font-weight: bold;
            margin: 10px 0;
            color: #667eea;
        }
        
        /* Features Section */
        .feature-card {
            text-align: center;
            padding: 30px;
            background: white;
            border-radius: 15px;
            transition: all 0.3s;
            height: 100%;
        }
        
        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .feature-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .feature-icon i {
            font-size: 2rem;
            color: white;
        }
        
        /* Newsletter Section */
        .newsletter-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 50px;
            margin: 50px 0;
            text-align: center;
            color: white;
        }
        
        .newsletter-input {
            border-radius: 50px;
            border: none;
            padding: 12px 20px;
            width: 100%;
            max-width: 400px;
        }
        
        .newsletter-btn {
            border-radius: 50px;
            padding: 12px 30px;
            background: #2c3e50;
            color: white;
            border: none;
            font-weight: bold;
        }
        
        footer {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            color: white;
            padding: 40px 0 20px;
            margin-top: 60px;
        }
        
        footer a {
            color: #bdc3c7;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        footer a:hover {
            color: #667eea;
        }
        
        .social-icons a {
            display: inline-block;
            width: 35px;
            height: 35px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            text-align: center;
            line-height: 35px;
            margin-right: 10px;
            transition: all 0.3s;
        }
        
        .social-icons a:hover {
            background: #667eea;
            transform: translateY(-3px);
        }
        
        @media (max-width: 768px) {
            .hero-content h1 {
                font-size: 2rem;
            }
            .hero-content p {
                font-size: 1rem;
            }
            .hero-section {
                min-height: 400px;
            }
            .product-image-container {
                height: 180px;
            }
            .section-title h2 {
                font-size: 1.8rem;
            }
            .newsletter-section {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
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
    
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="index.php?page=home">
                <i class="fas fa-store"></i> ShopVerse
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page == 'home') ? 'active' : ''; ?>" href="?page=home">
                            <i class="fas fa-home"></i> Home
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page == 'about') ? 'active' : ''; ?>" href="?page=about">
                            <i class="fas fa-info-circle"></i> About
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page == 'products') ? 'active' : ''; ?>" href="?page=products">
                            <i class="fas fa-box-open"></i> Products
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page == 'contact') ? 'active' : ''; ?>" href="?page=contact">
                            <i class="fas fa-envelope"></i> Contact
                        </a>
                    </li>
                    
                    <?php if(isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'customer'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($page == 'customercart') ? 'active' : ''; ?>" href="?page=customercart">
                                <i class="fas fa-shopping-cart"></i> Cart
                                <?php if($cart_count > 0): ?>
                                    <span class="badge bg-danger rounded-pill"><?php echo $cart_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php elseif(!isset($_SESSION['user_id'])): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">
                                <i class="fas fa-shopping-cart"></i> Cart
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <?php if($_SESSION['user_type'] == 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="admin/dashboard.php">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                    <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="customer/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                                    <li><a class="dropdown-item" href="customer/shop.php"><i class="fas fa-shopping-bag"></i> Shop</a></li>
                                    <li><a class="dropdown-item" href="?page=customercart"><i class="fas fa-shopping-cart"></i> Cart</a></li>
                                    <li><a class="dropdown-item" href="customer/orders.php"><i class="fas fa-history"></i> Orders</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                                </ul>
                            </li>
                        <?php endif; ?>
                    <?php else: ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                                <i class="fas fa-sign-in-alt"></i> Login
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="login.php?role=customer"><i class="fas fa-user"></i> Login as Customer</a></li>
                                <li><a class="dropdown-item" href="login.php?role=admin"><i class="fas fa-user-shield"></i> Login as Admin</a></li>
                            </ul>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="register.php">
                                <i class="fas fa-user-plus"></i> Register
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Dynamic Page Content -->
        <?php if($page == 'home'): ?>
            <!-- Hero Section -->
            <?php 
            $hero_image_exists = file_exists('assets/images/hero-banner.jpg');
            ?>
            <div class="hero-section <?php echo !$hero_image_exists ? 'no-image' : ''; ?>">
                <div class="hero-content">
                    <h1>Welcome to ShopVerse</h1>
                    <p>Your one-stop destination for amazing products at unbeatable prices!</p>
                    <a href="?page=products" class="btn btn-primary btn-lg">
                        Start Shopping <i class="fas fa-arrow-right ms-2"></i>
                    </a>
                </div>
            </div>

            <!-- Features Section -->
            <div class="row mb-5">
                <div class="col-md-3 mb-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-truck"></i>
                        </div>
                        <h5>Free Shipping</h5>
                        <small class="text-muted">On orders over $50</small>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                        <h5>Secure Payment</h5>
                        <small class="text-muted">100% secure transactions</small>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-undo-alt"></i>
                        </div>
                        <h5>Easy Returns</h5>
                        <small class="text-muted">30-day return policy</small>
                    </div>
                </div>
                <div class="col-md-3 mb-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-headset"></i>
                        </div>
                        <h5>24/7 Support</h5>
                        <small class="text-muted">Dedicated support team</small>
                    </div>
                </div>
            </div>

            <!-- Featured Products -->
            <div class="section-title">
                <h2>Featured Products</h2>
                <p class="text-muted">Check out our handpicked collection</p>
            </div>
            
            <!-- ============================================ -->
            <!-- UPDATED PRODUCT CARD SECTION WITH BOTH BUTTONS -->
            <!-- ============================================ -->
            <div class="row">
                <?php if(count($products) > 0): ?>
                    <?php foreach($products as $product): ?>
                        <div class="col-md-3 mb-4">
                            <div class="card product-card h-100">
                                <div class="product-image-container">
                                    <?php 
                                    $image_path = "";
                                    $has_image = false;
                                    
                                    if(!empty($product['image'])) {
                                        // Check multiple possible paths
                                        if(file_exists("uploads/" . $product['image'])) {
                                            $image_path = "uploads/" . $product['image'];
                                            $has_image = true;
                                        } elseif(file_exists("../uploads/" . $product['image'])) {
                                            $image_path = "../uploads/" . $product['image'];
                                            $has_image = true;
                                        }
                                    }
                                    ?>
                                    
                                    <?php if($has_image): ?>
                                        <img src="<?php echo $image_path; ?>" class="product-image" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                    <?php else: ?>
                                        <div class="product-placeholder">
                                            <i class="fas fa-box-open"></i>
                                            <span class="d-block small mt-2">No Image</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="card-body text-center">
                                    <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                    <p class="card-text text-muted small"><?php echo substr(htmlspecialchars($product['description']), 0, 60); ?>...</p>
                                    <h4 class="text-primary">$<?php echo number_format($product['price'], 2); ?></h4>
                                    
                                    <!-- Stock Status Badge -->
                                    <?php if($product['stock'] <= 5 && $product['stock'] > 0): ?>
                                        <span class="badge bg-warning text-dark mb-2 d-block">
                                            <i class="fas fa-exclamation-triangle"></i> Only <?php echo $product['stock']; ?> left!
                                        </span>
                                    <?php elseif($product['stock'] > 0): ?>
                                        <span class="badge bg-success mb-2 d-block">
                                            <i class="fas fa-check-circle"></i> In Stock
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger mb-2 d-block">
                                            <i class="fas fa-times-circle"></i> Out of Stock
                                        </span>
                                    <?php endif; ?>
                                    
                                    <!-- LOGGED IN CUSTOMER - Show Add to Cart with Quantity -->
                                    <?php if(isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'customer'): ?>
                                        <?php if($product['stock'] > 0): ?>
                                            <form action="customer/add_to_cart.php" method="GET" class="d-grid gap-2">
                                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                <input type="hidden" name="redirect" value="index.php?page=home">
                                                <div class="d-flex gap-2">
                                                    <input type="number" name="quantity" value="1" min="1" 
                                                           max="<?php echo $product['stock']; ?>" 
                                                           class="form-control quantity-input text-center"
                                                           style="width: 70px;">
                                                    <button type="submit" class="btn btn-success btn-add-cart flex-grow-1">
                                                        <i class="fas fa-cart-plus"></i> Add to Cart
                                                    </button>
                                                </div>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn btn-secondary btn-sm w-100" disabled>Out of Stock</button>
                                        <?php endif; ?>
                                    
                                    <!-- LOGGED IN ADMIN - Show Admin Mode -->
                                    <?php elseif(isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'admin'): ?>
                                        <button class="btn btn-secondary btn-sm w-100" disabled>
                                            <i class="fas fa-user-shield"></i> Admin Mode
                                        </button>
                                    
                                    <!-- NON-LOGGED USERS - Show BOTH Buttons -->
                                    <?php else: ?>
                                        <?php if($product['stock'] > 0): ?>
                                            <div class="d-grid gap-2">
                                                <!-- Login to Buy Button -->
                                                <a href="login.php?redirect=index.php&product_id=<?php echo $product['id']; ?>&quantity=1" 
                                                   class="btn btn-login-buy btn-sm text-white w-100">
                                                    <i class="fas fa-lock"></i> Login to Buy
                                                </a>
                                                
                                                <!-- Add to Cart Button (stores in session, redirects to login) -->
                                                <a href="customer/add_to_cart.php?product_id=<?php echo $product['id']; ?>&quantity=1&redirect=index.php?page=home" 
                                                   class="btn btn-success btn-sm w-100">
                                                    <i class="fas fa-cart-plus"></i> Add to Cart
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <button class="btn btn-secondary btn-sm w-100" disabled>Out of Stock</button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="col-12">
                        <div class="alert alert-info text-center">No products available yet. Please check back later!</div>
                    </div>
                <?php endif; ?>
            </div>
            <!-- ============================================ -->
            <!-- END OF PRODUCT CARD SECTION -->
            <!-- ============================================ -->
            
            <!-- Newsletter Section -->
            <div class="newsletter-section">
                <h3><i class="fas fa-envelope me-2"></i> Subscribe to Our Newsletter</h3>
                <p class="mb-4">Get the latest updates on new products and special offers</p>
                <form class="d-flex flex-wrap justify-content-center gap-2">
                    <input type="email" class="newsletter-input" placeholder="Enter your email address" required>
                    <button type="submit" class="newsletter-btn">Subscribe</button>
                </form>
            </div>

        <?php elseif($page == 'about'): ?>
            <!-- About Page Content -->
            <div class="about-section">
                <div class="section-title">
                    <h2>About ShopVerse</h2>
                </div>
                
                <div class="row align-items-center mb-5">
                    <div class="col-md-6">
                        <?php if(file_exists('assets/images/about-us.jpg')): ?>
                            <img src="assets/images/about-us.jpg" class="img-fluid rounded-3 shadow" alt="About ShopVerse">
                        <?php else: ?>
                            <div class="rounded-3 shadow d-flex align-items-center justify-content-center" 
                                 style="height: 400px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <div class="text-center text-white">
                                    <i class="fas fa-store fa-5x mb-3"></i>
                                    <h3>ShopVerse</h3>
                                    <p>Your Trusted Shopping Partner</p>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <h3>Our Story</h3>
                        <p>ShopVerse was founded in 2024 with a simple mission: to provide the best online shopping experience with quality products at affordable prices. We believe in customer satisfaction and strive to bring the best deals to your doorstep.</p>
                        <p>With a wide range of products from electronics to fashion, sports to books, we have something for everyone. Our team works tirelessly to ensure fast delivery and secure payments.</p>
                        <div class="row mt-4">
                            <div class="col-4">
                                <div class="stat-box">
                                    <i class="fas fa-users"></i>
                                    <h3>10K+</h3>
                                    <p>Happy Customers</p>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="stat-box">
                                    <i class="fas fa-box-open"></i>
                                    <h3>500+</h3>
                                    <p>Products</p>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="stat-box">
                                    <i class="fas fa-truck"></i>
                                    <h3>24/7</h3>
                                    <p>Fast Delivery</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <div class="col-md-4 mb-4">
                        <div class="card text-center p-4 h-100">
                            <i class="fas fa-shipping-fast fa-3x text-primary mb-3"></i>
                            <h4>Free Shipping</h4>
                            <p class="text-muted">Free shipping on orders over $50. Fast delivery across the country.</p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card text-center p-4 h-100">
                            <i class="fas fa-lock fa-3x text-primary mb-3"></i>
                            <h4>Secure Payment</h4>
                            <p class="text-muted">100% secure payments with eSewa and Cash on Delivery options.</p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card text-center p-4 h-100">
                            <i class="fas fa-headset fa-3x text-primary mb-3"></i>
                            <h4>24/7 Support</h4>
                            <p class="text-muted">Dedicated customer support team available round the clock.</p>
                        </div>
                    </div>
                </div>
                
                <div class="row mt-5">
                    <div class="col-md-12">
                        <div class="card bg-primary text-white text-center p-5">
                            <h3>Our Mission</h3>
                            <p class="lead">To provide quality products at affordable prices with excellent customer service.</p>
                            <div>
                                <a href="?page=contact" class="btn btn-light btn-lg mt-3">
                                    <i class="fas fa-envelope"></i> Contact Us
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif($page == 'products'): ?>
            <!-- Products Page -->
            <div class="products-section">
                <div class="section-title">
                    <h2>All Products</h2>
                    <p class="text-muted">Browse our complete collection of quality products</p>
                </div>
                
                <div class="row">
                    <?php if(count($all_products) > 0): ?>
                        <?php foreach($all_products as $product): ?>
                            <div class="col-md-3 mb-4">
                                <div class="card product-card">
                                    <div class="product-image-container">
                                        <?php 
                                        $image_path = "";
                                        $has_image = false;
                                        
                                        if(!empty($product['image'])) {
                                            $full_path = "uploads/" . $product['image'];
                                            if(file_exists($full_path)) {
                                                $image_path = $full_path;
                                                $has_image = true;
                                            }
                                        }
                                        ?>
                                        
                                        <?php if($has_image): ?>
                                            <img src="<?php echo $image_path; ?>" class="product-image" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                        <?php else: ?>
                                            <div class="product-placeholder">
                                                <i class="fas fa-box-open"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="card-body text-center">
                                        <h5 class="card-title"><?php echo htmlspecialchars($product['name']); ?></h5>
                                        <p class="card-text text-muted small"><?php echo substr(htmlspecialchars($product['description']), 0, 50); ?>...</p>
                                        <h4 class="text-primary">$<?php echo number_format($product['price'], 2); ?></h4>
                                        
                                        <?php if($product['stock'] <= 5 && $product['stock'] > 0): ?>
                                            <span class="badge bg-warning text-dark mb-2">
                                                <i class="fas fa-exclamation-triangle"></i> Only <?php echo $product['stock']; ?> left!
                                            </span>
                                        <?php elseif($product['stock'] > 0): ?>
                                            <span class="badge bg-success mb-2">
                                                <i class="fas fa-check-circle"></i> In Stock
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger mb-2">
                                                <i class="fas fa-times-circle"></i> Out of Stock
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if(isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'customer'): ?>
                                            <?php if($product['stock'] > 0): ?>
                                                <form action="customer/add_to_cart.php" method="GET" class="d-grid gap-2">
                                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                    <input type="hidden" name="redirect" value="index.php?page=products">
                                                    <div class="d-flex gap-2">
                                                        <input type="number" name="quantity" value="1" min="1" 
                                                               max="<?php echo $product['stock']; ?>" 
                                                               class="form-control quantity-input text-center"
                                                               style="width: 70px;">
                                                        <button type="submit" class="btn btn-success btn-add-cart flex-grow-1">
                                                            <i class="fas fa-cart-plus"></i> Add
                                                        </button>
                                                    </div>
                                                </form>
                                            <?php else: ?>
                                                <button class="btn btn-secondary btn-sm w-100" disabled>Out of Stock</button>
                                            <?php endif; ?>
                                        <?php elseif(isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'admin'): ?>
                                            <button class="btn btn-secondary btn-sm w-100" disabled>Admin Mode</button>
                                        <?php else: ?>
                                            <a href="login.php" class="btn btn-outline-primary btn-sm w-100">Login to Buy</a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info text-center">No products available yet!</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif($page == 'contact'): ?>
            <!-- Contact Page -->
            <div class="contact-section">
                <div class="section-title">
                    <h2>Contact Us</h2>
                    <p class="text-muted">We'd love to hear from you! Get in touch with us.</p>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card p-4">
                            <h3><i class="fas fa-envelope text-primary"></i> Send us a Message</h3>
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <input type="text" name="name" class="form-control" placeholder="Your Full Name" required>
                                </div>
                                <div class="mb-3">
                                    <input type="email" name="email" class="form-control" placeholder="Your Email Address" required>
                                </div>
                                <div class="mb-3">
                                    <input type="text" name="subject" class="form-control" placeholder="Subject" required>
                                </div>
                                <div class="mb-3">
                                    <textarea name="message" class="form-control" rows="5" placeholder="Your Message" required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-paper-plane"></i> Send Message
                                </button>
                            </form>
                            <?php if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['name'])): ?>
                                <div class="alert alert-success mt-3">
                                    <i class="fas fa-check-circle"></i> Thank you for contacting us! We will get back to you soon.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <div class="card p-4">
                            <h3><i class="fas fa-address-card text-primary"></i> Contact Information</h3>
                            <hr>
                            <div class="mb-3">
                                <i class="fas fa-map-marker-alt fa-2x text-primary float-start me-3"></i>
                                <div>
                                    <h5>Address</h5>
                                    <p class="text-muted">123 Shopping Street, Kathmandu, Nepal</p>
                                </div>
                            </div>
                            <div class="mb-3">
                                <i class="fas fa-phone fa-2x text-primary float-start me-3"></i>
                                <div>
                                    <h5>Phone</h5>
                                    <p class="text-muted">+977 9800000000<br>+977 9800000001</p>
                                </div>
                            </div>
                            <div class="mb-3">
                                <i class="fas fa-envelope fa-2x text-primary float-start me-3"></i>
                                <div>
                                    <h5>Email</h5>
                                    <p class="text-muted">support@shopverse.com<br>info@shopverse.com</p>
                                </div>
                            </div>
                            <div class="mt-3">
                                <h5>Follow Us</h5>
                                <div class="social-icons">
                                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                                    <a href="#"><i class="fab fa-instagram"></i></a>
                                    <a href="#"><i class="fab fa-twitter"></i></a>
                                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card mt-4 p-4">
                            <h5><i class="fas fa-clock text-primary"></i> Business Hours</h5>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check-circle text-success me-2"></i> Monday - Friday: 9:00 AM - 6:00 PM</li>
                                <li><i class="fas fa-check-circle text-success me-2"></i> Saturday: 10:00 AM - 4:00 PM</li>
                                <li><i class="fas fa-times-circle text-danger me-2"></i> Sunday: Closed</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-3">
                    <h5><i class="fas fa-store"></i> ShopVerse</h5>
                    <p class="text-muted">Your trusted online shopping destination for quality products at affordable prices.</p>
                    <div class="social-icons">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div class="col-md-2 mb-3">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="?page=home"><i class="fas fa-chevron-right me-1"></i> Home</a></li>
                        <li><a href="?page=about"><i class="fas fa-chevron-right me-1"></i> About Us</a></li>
                        <li><a href="?page=products"><i class="fas fa-chevron-right me-1"></i> Products</a></li>
                        <li><a href="?page=contact"><i class="fas fa-chevron-right me-1"></i> Contact</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-3">
                    <h5>Customer Service</h5>
                    <ul class="list-unstyled">
                        <li><a href="#"><i class="fas fa-chevron-right me-1"></i> FAQ</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right me-1"></i> Shipping Info</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right me-1"></i> Returns Policy</a></li>
                        <li><a href="#"><i class="fas fa-chevron-right me-1"></i> Terms & Conditions</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-3">
                    <h5>Contact Info</h5>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-phone me-2"></i> +977 9800000000</li>
                        <li><i class="fas fa-envelope me-2"></i> support@shopverse.com</li>
                        <li><i class="fas fa-map-marker-alt me-2"></i> Kathmandu, Nepal</li>
                    </ul>
                </div>
            </div>
            <hr class="bg-secondary">
            <div class="text-center text-muted">
                <p>&copy; <?php echo date('Y'); ?> ShopVerse. All rights reserved.</p>
                <small>Powered by PHP & MySQL | Secure Payments with eSewa & COD</small>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Quantity input validation
        document.querySelectorAll('.quantity-input').forEach(input => {
            input.addEventListener('change', function() {
                let max = parseInt(this.getAttribute('max'));
                let value = parseInt(this.value);
                if (value > max) {
                    this.value = max;
                    alert('Only ' + max + ' items available in stock!');
                }
                if (value < 1 || isNaN(value)) {
                    this.value = 1;
                }
            });
        });
    </script>
</body>
</html>