<?php
session_start();
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$product_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($product_id <= 0) {
    header("Location: shop.php");
    exit();
}

$stmt = $db->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
$stmt->execute([$product_id]);
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$product) {
    die("Product not found");
}

// Get cart count
$cart_count = 0;
$wishlist_count = 0;
$in_wishlist = false;

if (isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'customer') {
    $user_id = $_SESSION['user_id'];
    
    // Get cart count
    $cart = $db->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
    $cart->execute([$user_id]);
    $cart_count = $cart->fetchColumn() ?: 0;
    
    // Get wishlist count
    $wishlist_stmt = $db->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
    $wishlist_stmt->execute([$user_id]);
    $wishlist_count = $wishlist_stmt->fetchColumn() ?: 0;
    
    // Check if product is in wishlist
    $check_wishlist = $db->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
    $check_wishlist->execute([$user_id, $product_id]);
    $in_wishlist = $check_wishlist->fetchColumn() > 0;
}

// Get related products
$related = $db->prepare("SELECT * FROM products WHERE category = ? AND id != ? AND status = 'active' LIMIT 4");
$related->execute([$product['category'], $product['id']]);
$related_products = $related->fetchAll(PDO::FETCH_ASSOC);

// Product images
$images = [];
if (!empty($product['image'])) {
    $images[] = "../uploads/" . $product['image'];
}
if (!empty($product['image2'])) {
    $images[] = "../uploads/" . $product['image2'];
}
if (!empty($product['image3'])) {
    $images[] = "../uploads/" . $product['image3'];
}

$mainImage = count($images) ? $images[0] : "../uploads/no-image.png";

// Calculate discount
$original_price = $product['price'] * 1.15;
$discount_percent = 15;

// Rating system
$avg_rating = 4.5;
$total_reviews = 3019;

function renderStars($rating, $size = '1.1rem') {
    $html = '';
    $full_stars = floor($rating);
    $half_star = $rating - $full_stars >= 0.5 ? true : false;
    
    for ($i = 1; $i <= 5; $i++) {
        if ($i <= $full_stars) {
            $html .= '<i class="fas fa-star" style="color: #f59e0b; font-size: ' . $size . ';"></i>';
        } elseif ($half_star && $i == $full_stars + 1) {
            $html .= '<i class="fas fa-star-half-alt" style="color: #f59e0b; font-size: ' . $size . ';"></i>';
        } else {
            $html .= '<i class="far fa-star" style="color: #f59e0b; font-size: ' . $size . ';"></i>';
        }
    }
    return $html;
}

// Handle Add to Cart directly on this page
$add_to_cart_success = false;
$added_product = null;
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_to_cart'])) {
    if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'customer') {
        header("Location: ../login.php");
        exit();
    }
    
    $product_id = intval($_POST['product_id']);
    $quantity = intval($_POST['quantity']);
    $user_id = $_SESSION['user_id'];
    
    if ($quantity < 1) {
        $quantity = 1;
    }
    
    // Check if product already in cart
    $check = $db->prepare("SELECT * FROM cart WHERE user_id = ? AND product_id = ?");
    $check->execute([$user_id, $product_id]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        // Update quantity
        $new_qty = $existing['quantity'] + $quantity;
        $update = $db->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
        $update->execute([$new_qty, $existing['id']]);
    } else {
        // Insert new
        $insert = $db->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
        $insert->execute([$user_id, $product_id, $quantity]);
    }
    
    $add_to_cart_success = true;
    $added_product = $product;
    $added_product['cart_quantity'] = $quantity;
    
    $_SESSION['cart_success'] = "Product added to cart successfully!";
    
    // Refresh cart count
    $cart = $db->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
    $cart->execute([$_SESSION['user_id']]);
    $cart_count = $cart->fetchColumn() ?: 0;
}

// Handle Wishlist messages
$wishlist_success = isset($_SESSION['wishlist_success']) ? $_SESSION['wishlist_success'] : '';
$wishlist_error = isset($_SESSION['wishlist_error']) ? $_SESSION['wishlist_error'] : '';
unset($_SESSION['wishlist_success']);
unset($_SESSION['wishlist_error']);

// Get user's saved location from session if exists
$user_location = isset($_SESSION['delivery_location']) ? $_SESSION['delivery_location'] : 'kathmandu';
$delivery_fee = getDeliveryFee($user_location);

// Function to get delivery fee based on location
function getDeliveryFee($location) {
    $delivery_rates = [
        'kathmandu' => 105,
        'lalitpur' => 105,
        'bhaktapur' => 105,
        'pokhara' => 150,
        'biratnagar' => 180,
        'janakpur' => 160,
        'butwal' => 170,
        'nepalgunj' => 190,
        'dhangadhi' => 210,
        'surkhet' => 200,
        'dharan' => 175,
        'hetuda' => 140,
        'hetauda' => 140,
        'birganj' => 150,
        'damauli' => 155,
        'kakarbhitta' => 185,
        'banepa' => 115,
        'dhulikhel' => 120,
        'kirtipur' => 110,
        'chandragiri' => 110,
        'tokha' => 115,
        'gokarneshwar' => 115,
        'suryabinayak' => 115,
        'madhyapur_thimi' => 115,
        'tarakeshwar' => 115,
        'nagarjun' => 115
    ];
    
    return isset($delivery_rates[$location]) ? $delivery_rates[$location] : 150;
}

// Function to get location display name
function getLocationName($location_key) {
    $locations = [
        'kathmandu' => 'Kathmandu Metro',
        'lalitpur' => 'Lalitpur Metro',
        'bhaktapur' => 'Bhaktapur',
        'pokhara' => 'Pokhara Metro',
        'biratnagar' => 'Biratnagar',
        'janakpur' => 'Janakpur',
        'butwal' => 'Butwal',
        'nepalgunj' => 'Nepalgunj',
        'dhangadhi' => 'Dhangadhi',
        'surkhet' => 'Surkhet',
        'dharan' => 'Dharan',
        'hetuda' => 'Hetauda',
        'birganj' => 'Birganj',
        'damauli' => 'Damauli',
        'kakarbhitta' => 'Kakarbhitta',
        'banepa' => 'Banepa',
        'dhulikhel' => 'Dhulikhel',
        'kirtipur' => 'Kirtipur',
        'chandragiri' => 'Chandragiri',
        'tokha' => 'Tokha',
        'gokarneshwar' => 'Gokarneshwar',
        'suryabinayak' => 'Suryabinayak',
        'madhyapur_thimi' => 'Madhyapur Thimi',
        'tarakeshwar' => 'Tarakeshwar',
        'nagarjun' => 'Nagarjun'
    ];
    
    return isset($locations[$location_key]) ? $locations[$location_key] : 'Kathmandu Metro';
}

// Handle location change via AJAX
if (isset($_POST['update_location'])) {
    $_SESSION['delivery_location'] = $_POST['location'];
    $delivery_fee = getDeliveryFee($_POST['location']);
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($product['name']); ?> - ShopEmart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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

        /* ============================================ */
        /* DARAZ STYLE NAVIGATION */
        /* ============================================ */
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

        .nav-link .badge {
            position: relative;
            top: -8px;
            left: -2px;
            font-size: 0.7rem;
        }

        /* ============================================ */
        /* PRODUCT DETAILS */
        /* ============================================ */
        .product-detail-container {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            margin: 20px 0;
        }

        .main-image {
            width: 100%;
            height: 450px;
            object-fit: contain;
            background: #f8f9fa;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            transition: all 0.3s;
        }

        .thumb {
            width: 80px;
            height: 80px;
            object-fit: cover;
            cursor: pointer;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            transition: all 0.3s;
        }
        .thumb:hover {
            border-color: #ff6600;
            transform: scale(1.05);
        }
        .thumb.active {
            border-color: #ff6600;
            box-shadow: 0 0 0 3px rgba(255,102,0,0.2);
        }

        .product-title {
            font-size: 1.6rem;
            font-weight: 600;
            color: #1a1a2e;
        }

        .product-brand {
            color: #ff6600;
            font-weight: 600;
        }

        /* Price Section */
        .price-current {
            font-size: 2.2rem;
            font-weight: 700;
            color: #ff6600;
        }

        .price-original {
            font-size: 1.1rem;
            color: #999;
            text-decoration: line-through;
            margin-left: 12px;
        }

        .discount-badge {
            background: #e74c3c;
            color: white;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 10px;
        }

        /* Rating */
        .rating-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px 20px;
            margin: 15px 0;
        }

        .rating-stars i {
            font-size: 1.1rem;
        }

        /* ============================================ */
        /* QUANTITY SECTION */
        /* ============================================ */
        .quantity-section {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px 20px;
            margin: 15px 0;
            border: 1px solid #e0e0e0;
        }

        .quantity-section .qty-label {
            font-size: 0.85rem;
            font-weight: 600;
            color: #333;
        }

        .qty-btn {
            width: 40px;
            height: 40px;
            border-radius: 50px;
            font-size: 1.2rem;
            font-weight: bold;
            border: 2px solid #e0e0e0;
            background: white;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        .qty-btn:hover {
            border-color: #ff6600;
            background: #ff6600;
            color: white;
        }

        .qty-input {
            width: 60px;
            text-align: center;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 8px;
            font-size: 1rem;
            background: white;
        }
        .qty-input:focus {
            border-color: #ff6600;
            outline: none;
        }

        /* Stock Warning */
        .stock-warning {
            color: #e74c3c;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 5px;
        }

        .stock-warning i {
            margin-right: 5px;
        }

        /* ============================================ */
        /* BUTTONS - UPDATED ARRANGEMENT */
        /* ============================================ */
        
        /* Buy Now Button - Full Width Orange */
        .btn-buy-now {
            background: linear-gradient(135deg, #ff9f00, #e68a00);
            border: none;
            padding: 16px 20px;
            border-radius: 50px;
            font-weight: 700;
            color: white;
            transition: all 0.3s;
            font-size: 1.1rem;
            width: 100%;
            display: inline-block;
            text-align: center;
            text-decoration: none;
        }
        .btn-buy-now:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(255,159,0,0.4);
            color: white;
        }

        /* Add to Cart Button - Full Width Orange */
        .btn-add-cart {
            background: linear-gradient(135deg, #ff6600, #ff8533);
            border: none;
            padding: 16px 20px;
            border-radius: 50px;
            font-weight: 700;
            color: white;
            transition: all 0.3s;
            font-size: 1.1rem;
            cursor: pointer;
            width: 100%;
            display: inline-block;
            text-align: center;
            text-decoration: none;
        }
        .btn-add-cart:hover {
            background: #e55d00;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(255,102,0,0.4);
            color: white;
        }
        
        .btn-add-cart:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        /* Wishlist Button - Full Width with Heart */
        .btn-wishlist-detail {
            background: transparent;
            border: 2px solid #e74c3c;
            padding: 16px 20px;
            border-radius: 50px;
            font-weight: 700;
            color: #e74c3c;
            transition: all 0.3s;
            font-size: 1.1rem;
            cursor: pointer;
            width: 100%;
            display: inline-block;
            text-align: center;
            text-decoration: none;
        }
        
        .btn-wishlist-detail:hover {
            background: #e74c3c;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(231, 76, 60, 0.3);
        }

        .btn-wishlist-detail.in-wishlist {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            border: none;
        }

        .btn-wishlist-detail.in-wishlist:hover {
            background: #c0392b;
            box-shadow: 0 5px 20px rgba(231, 76, 60, 0.4);
        }

        .btn-wishlist-detail i {
            margin-right: 8px;
        }

        /* Button Group Layout */
        .btn-group-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-group-actions .row-2cols {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .btn-group-actions .full-width {
            width: 100%;
        }

        /* ============================================ */
        /* SUCCESS CONFIRMATION SECTION */
        /* ============================================ */
        .confirmation-section {
            background: linear-gradient(135deg, #e8f5e9, #c8e6c9);
            border-radius: 15px;
            padding: 25px;
            margin-top: 25px;
            border-left: 5px solid #4caf50;
            animation: fadeInUp 0.5s ease;
            display: <?php echo ($add_to_cart_success) ? 'block' : 'none'; ?>;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .confirmation-section .check-icon {
            font-size: 3rem;
            color: #4caf50;
        }

        .btn-confirm {
            background: linear-gradient(135deg, #4caf50, #45a049);
            border: none;
            padding: 12px 40px;
            border-radius: 50px;
            font-weight: bold;
            transition: all 0.3s;
            color: white;
            font-size: 1.1rem;
            text-decoration: none;
            display: inline-block;
        }

        .btn-confirm:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(76,175,80,0.4);
            color: white;
        }

        .product-detail-card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .product-detail-card .label {
            font-weight: 600;
            color: #666;
            font-size: 0.85rem;
        }

        .product-detail-card .value {
            font-weight: 500;
            color: #333;
        }

        /* ============================================ */
        /* DELIVERY BOX - DARAZ STYLE */
        /* ============================================ */
        .delivery-box-daraz {
            background: #ffffff;
            border-radius: 12px;
            padding: 18px 20px;
            margin-bottom: 15px;
            border-left: 4px solid #ff6600;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        }

        .delivery-box-daraz .label {
            font-size: 0.7rem;
            color: #999;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .delivery-box-daraz .value {
            font-weight: 600;
            color: #1a1a2e;
            font-size: 1.1rem;
        }

        .delivery-box-daraz .value.price {
            font-size: 1.4rem;
            font-weight: 700;
            color: #1a1a2e;
        }

        .delivery-date {
            color: #666;
            font-size: 0.85rem;
        }

        .cod-badge {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 4px 14px;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }

        .cod-badge i {
            margin-right: 5px;
        }

        .change-link {
            color: #ff6600;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.8rem;
            transition: all 0.3s;
        }
        .change-link:hover {
            text-decoration: underline;
            color: #e55d00;
        }

        /* Location select styling */
        .location-select {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 0.9rem;
            width: 100%;
            background: white;
            transition: all 0.3s;
            cursor: pointer;
            color: #333;
        }
        .location-select:focus {
            border-color: #ff6600;
            outline: none;
            box-shadow: 0 0 0 3px rgba(255,102,0,0.1);
        }

        /* Related Products */
        .related-card-daraz {
            transition: all 0.3s;
            border: 1px solid #eee;
            border-radius: 12px;
            overflow: hidden;
            background: white;
        }
        .related-card-daraz:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border-color: #ff6600;
        }

        .related-img {
            height: 180px;
            object-fit: cover;
            width: 100%;
        }

        .related-price {
            color: #ff6600;
            font-weight: 600;
        }

        /* Voucher */
        .voucher-box {
            background: #fff8f0;
            border: 1px dashed #ff6600;
            border-radius: 10px;
            padding: 10px 15px;
            display: inline-block;
            margin-top: 10px;
        }
        .voucher-box i {
            color: #ff6600;
        }

        /* Warranty Badge */
        .warranty-badge-daraz {
            background: #fce4ec;
            color: #c62828;
            padding: 2px 10px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .rating-bar {
            flex: 1;
            height: 6px;
            background: #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
        }

        .rating-bar-fill {
            height: 100%;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            border-radius: 10px;
        }

        /* Toast Messages */
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

        /* Responsive */
        @media (max-width: 768px) {
            .main-image {
                height: 280px;
            }
            .product-title {
                font-size: 1.3rem;
            }
            .price-current {
                font-size: 1.6rem;
            }
            .thumb {
                width: 60px;
                height: 60px;
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
            .btn-group-actions .row-2cols {
                grid-template-columns: 1fr;
            }
            .btn-buy-now, .btn-add-cart, .btn-wishlist-detail {
                padding: 14px 20px;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Toast Notifications -->
    <?php if(isset($_SESSION['cart_success'])): ?>
    <div class="toast-notification">
        <div class="alert alert-success alert-dismissible fade show shadow-lg border-0 rounded-3">
            <i class="fas fa-check-circle me-2"></i> <?php echo $_SESSION['cart_success']; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    <?php unset($_SESSION['cart_success']); ?>
    <script>
        setTimeout(() => {
            document.querySelector('.toast-notification')?.remove();
        }, 3000);
    </script>
    <?php endif; ?>

    <?php if($wishlist_success): ?>
    <div class="toast-notification">
        <div class="alert alert-success alert-dismissible fade show shadow-lg border-0 rounded-3">
            <i class="fas fa-heart me-2" style="color: #e74c3c;"></i> <?php echo htmlspecialchars($wishlist_success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    <script>
        setTimeout(() => {
            document.querySelector('.toast-notification')?.remove();
        }, 3000);
    </script>
    <?php endif; ?>

    <?php if($wishlist_error): ?>
    <div class="toast-notification">
        <div class="alert alert-danger alert-dismissible fade show shadow-lg border-0 rounded-3">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo htmlspecialchars($wishlist_error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    <script>
        setTimeout(() => {
            document.querySelector('.toast-notification')?.remove();
        }, 4000);
    </script>
    <?php endif; ?>

    <!-- ============================================ -->
    <!-- DARAZ STYLE NAVIGATION WITH LOGO -->
    <!-- ============================================ -->
    <nav class="navbar navbar-expand-lg navbar-daraz">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <img src="../assets/images/ss.jpg" alt="ShopVerse Logo" class="logo-img">
                <div class="logo-text">
                    <div class="brand-name">Shop<span>Emart</span></div>
                    <div class="brand-tagline">SHOP SMARTER</div>
                </div>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link" href="wishlist.php">
                            <i class="fas fa-heart"></i> Wishlist
                            <?php if($wishlist_count > 0): ?>
                                <span class="badge bg-danger rounded-pill ms-1"><?php echo $wishlist_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="cart.php">
                            <i class="fas fa-shopping-cart"></i> Cart
                            <?php if($cart_count > 0): ?>
                                <span class="badge bg-danger rounded-pill ms-1"><?php echo $cart_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container my-4">
        <div class="row">
            <!-- Main Product -->
            <div class="col-lg-9">
                <div class="product-detail-container">
                    <div class="row">
                        <!-- Product Images -->
                        <div class="col-md-5">
                            <img src="<?php echo $mainImage; ?>" id="mainImage" class="main-image" alt="<?php echo htmlspecialchars($product['name']); ?>">
                            <div class="d-flex gap-2 mt-3 flex-wrap">
                                <?php foreach ($images as $img): ?>
                                    <img src="<?php echo $img; ?>" class="thumb" onclick="changeImage(this.src)">
                                <?php endforeach; ?>
                                <?php if (count($images) < 2): ?>
                                    <span class="text-muted small">No additional images</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Product Info -->
                        <div class="col-md-7">
                            <h1 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h1>

                            <!-- Rating -->
                            <div class="d-flex align-items-center gap-2">
                                <div class="rating-stars">
                                    <?php echo renderStars($avg_rating); ?>
                                </div>
                                <span class="fw-bold"><?php echo number_format($avg_rating, 1); ?></span>
                                <span class="text-muted small">(<?php echo number_format($total_reviews); ?> Ratings)</span>
                            </div>

                            <!-- Brand -->
                            <p class="text-muted small mt-1">
                                Brand: <span class="product-brand"><?php echo htmlspecialchars($product['category']); ?></span>
                            </p>

                            <!-- Price -->
                            <div class="d-flex align-items-center flex-wrap mt-2">
                                <span class="price-current">
                                    Rs. <?php echo number_format($product['price'], 2); ?>
                                </span>
                                <span class="price-original">
                                    Rs. <?php echo number_format($original_price, 2); ?>
                                </span>
                                <span class="discount-badge"><?php echo $discount_percent; ?>% OFF</span>
                            </div>

                            <!-- Voucher -->
                            <div class="voucher-box">
                                <i class="fas fa-ticket-alt"></i>
                                <span class="small">Save Rs. <?php echo number_format($original_price - $product['price'], 2); ?> with voucher</span>
                            </div>

                            <!-- Stock -->
                            <?php if ($product['stock'] > 0): ?>
                                <span class="badge bg-success px-3 py-2 mt-2">
                                    <i class="fas fa-check-circle"></i> In Stock (<?php echo $product['stock']; ?>)
                                </span>
                            <?php else: ?>
                                <span class="badge bg-danger px-3 py-2 mt-2">
                                    <i class="fas fa-times-circle"></i> Out of Stock
                                </span>
                            <?php endif; ?>

                            <hr>

                            <!-- Description -->
                            <h6><i class="fas fa-align-left"></i> Product Details</h6>
                            <p class="text-muted small"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>

                            <hr>

                            <!-- ============================================ -->
                            <!-- QUANTITY SECTION -->
                            <!-- ============================================ -->
                            <div class="quantity-section">
                                <div class="row align-items-center">
                                    <div class="col-md-4">
                                        <span class="qty-label"><i class="fas fa-sort-numeric-up"></i> Quantity</span>
                                    </div>
                                    <div class="col-md-8">
                                        <div class="d-flex align-items-center">
                                            <button type="button" class="qty-btn" onclick="minusQty()">−</button>
                                            <input type="number" id="qty" value="1" min="1" max="<?php echo $product['stock']; ?>" class="qty-input mx-2">
                                            <button type="button" class="qty-btn" onclick="plusQty()">+</button>
                                            <span class="text-muted small ms-3">Available: <?php echo $product['stock']; ?></span>
                                        </div>
                                    </div>
                                </div>
                                <?php if ($product['stock'] <= 10 && $product['stock'] > 0): ?>
                                    <div class="stock-warning">
                                        <i class="fas fa-exclamation-triangle"></i> Almost sold out, buy now!
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- ============================================ -->
                            <!-- BUTTONS - BUY NOW, ADD TO CART & WISHLIST -->
                            <!-- ============================================ -->
                            <?php if (isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'customer'): ?>
                                <?php if ($product['stock'] > 0): ?>
                                    <div class="btn-group-actions">
                                        <div class="row-2cols">
                                            <a href="checkout.php?product_id=<?php echo $product['id']; ?>&quantity=1" class="btn-buy-now">
                                                <i class="fas fa-bolt"></i> Buy Now
                                            </a>
                                            <form action="" method="POST" id="addToCartForm" style="margin: 0;">
                                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                <input type="hidden" name="quantity" id="qtyInput" value="1">
                                                <input type="hidden" name="add_to_cart" value="1">
                                                <button type="submit" class="btn-add-cart" id="addToCartBtn">
                                                    <i class="fas fa-cart-plus"></i> Add to Cart
                                                </button>
                                            </form>
                                        </div>
                                        <div class="full-width">
                                            <form action="add_to_wishlist.php" method="GET">
                                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                <input type="hidden" name="redirect" value="product_details.php?id=<?php echo $product['id']; ?>">
                                                <button type="submit" class="btn-wishlist-detail <?php echo $in_wishlist ? 'in-wishlist' : ''; ?>">
                                                    <i class="fas <?php echo $in_wishlist ? 'fa-heart' : 'fa-heart'; ?>"></i> 
                                                    <?php echo $in_wishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <button class="btn btn-secondary w-100 py-3 mt-3" disabled>
                                        <i class="fas fa-ban"></i> Out of Stock
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="btn-group-actions">
                                    <div class="row-2cols">
                                        <a href="../login.php" class="btn-buy-now">
                                            <i class="fas fa-lock"></i> Buy Now
                                        </a>
                                        <a href="../login.php" class="btn-add-cart">
                                            <i class="fas fa-cart-plus"></i> Add to Cart
                                        </a>
                                    </div>
                                    <div class="full-width">
                                        <a href="../login.php" class="btn-wishlist-detail">
                                            <i class="fas fa-heart"></i> Login to Add to Wishlist
                                        </a>
                                    </div>
                                </div>
                                <p class="text-center mt-3 text-muted small">
                                    Please <a href="../login.php">login</a> to purchase this product.
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ============================================ -->
                    <!-- CONFIRMATION SECTION -->
                    <!-- ============================================ -->
                    <?php if ($add_to_cart_success && $added_product): ?>
                    <div class="confirmation-section" id="confirmationSection">
                        <div class="row align-items-center">
                            <div class="col-lg-8">
                                <div class="d-flex align-items-start">
                                    <div class="me-3">
                                        <i class="fas fa-check-circle check-icon"></i>
                                    </div>
                                    <div>
                                        <h4 class="mb-2 text-success">Product Added to Cart! <i class="fas fa-check-circle text-success"></i></h4>
                                        <p class="mb-3 text-muted">Please review your product details below before confirming.</p>
                                        
                                        <div class="mt-3">
                                            <h6 class="fw-bold mb-3"><i class="fas fa-list-ul me-2"></i>Product Summary</h6>
                                            <div class="product-detail-card">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <span class="label">Product:</span>
                                                        <span class="value"><?php echo htmlspecialchars($added_product['name']); ?></span>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <span class="label">Quantity:</span>
                                                        <span class="value"><?php echo $added_product['cart_quantity']; ?></span>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <span class="label">Price:</span>
                                                        <span class="value">Rs. <?php echo number_format($added_product['price'], 2); ?></span>
                                                    </div>
                                                    <div class="col-12 mt-2">
                                                        <span class="label">Subtotal:</span>
                                                        <span class="value fw-bold text-success">Rs. <?php echo number_format($added_product['price'] * $added_product['cart_quantity'], 2); ?></span>
                                                    </div>
                                                    <?php if(!empty($added_product['description'])): ?>
                                                    <div class="col-12 mt-1">
                                                        <span class="label">Description:</span>
                                                        <span class="value"><?php echo htmlspecialchars(substr($added_product['description'], 0, 100)); ?>...</span>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="mt-3 p-3 bg-light rounded-3">
                                                <div class="d-flex justify-content-between">
                                                    <span class="fw-bold">Total Amount:</span>
                                                    <span class="fw-bold text-success" style="font-size: 1.2rem;">Rs. <?php echo number_format($added_product['price'] * $added_product['cart_quantity'], 2); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4 text-center text-lg-end mt-3 mt-lg-0">
                                <div class="d-flex flex-column gap-2">
                                    <a href="cart.php" class="btn-confirm">
                                        <i class="fas fa-check-circle me-2"></i> View Cart & Confirm
                                    </a>
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i> Click to view cart and confirm order
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Rating Breakdown -->
                    <div class="rating-section mt-4">
                        <h6><i class="fas fa-star text-warning"></i> Rating & Reviews</h6>
                        <div class="row mt-3">
                            <div class="col-md-4 text-center">
                                <div style="font-size: 2.5rem; font-weight: 700; color: #1a1a2e;"><?php echo number_format($avg_rating, 1); ?></div>
                                <div class="rating-stars">
                                    <?php echo renderStars($avg_rating, '1.2rem'); ?>
                                </div>
                                <div class="text-muted small"><?php echo number_format($total_reviews); ?> global ratings</div>
                            </div>
                            <div class="col-md-8">
                                <?php 
                                $rating_counts = [5 => 1500, 4 => 800, 3 => 450, 2 => 180, 1 => 89];
                                foreach ($rating_counts as $stars => $count): 
                                    $percent = ($count / $total_reviews) * 100;
                                ?>
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="small" style="width: 60px;"><?php echo $stars; ?> ★</span>
                                        <div class="rating-bar">
                                            <div class="rating-bar-fill" style="width: <?php echo $percent; ?>%;"></div>
                                        </div>
                                        <span class="small text-muted" style="width: 50px;"><?php echo $count; ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Related Products -->
                <?php if (count($related_products) > 0): ?>
                <h5 class="mt-4 mb-3"><i class="fas fa-th-large text-primary"></i> Related Products</h5>
                <div class="row">
                    <?php foreach ($related_products as $item): ?>
                        <div class="col-md-3 mb-3">
                            <div class="card related-card-daraz h-100">
                                <?php 
                                $rimg = '';
                                if (!empty($item['image']) && file_exists("../uploads/" . $item['image'])) {
                                    $rimg = "../uploads/" . $item['image'];
                                } elseif (!empty($item['image']) && file_exists("uploads/" . $item['image'])) {
                                    $rimg = "uploads/" . $item['image'];
                                }
                                ?>
                                <?php if ($rimg): ?>
                                    <img src="<?php echo $rimg; ?>" class="related-img" alt="<?php echo htmlspecialchars($item['name']); ?>">
                                <?php else: ?>
                                    <div class="related-img bg-light d-flex align-items-center justify-content-center">
                                        <i class="fas fa-box-open fa-3x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="card-body text-center">
                                    <h6 class="card-title small"><?php echo htmlspecialchars($item['name']); ?></h6>
                                    <p class="related-price fw-bold">Rs. <?php echo number_format($item['price'], 2); ?></p>
                                    <a href="product_details.php?id=<?php echo $item['id']; ?>" class="btn btn-outline-primary btn-sm w-100">
                                        <i class="fas fa-eye"></i> View
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- ============================================ -->
            <!-- SIDEBAR - Delivery, Return, Warranty - DARAZ STYLE -->
            <!-- ============================================ -->
            <div class="col-lg-3">
                <!-- Delivery Location - Daraz Style -->
                <div class="delivery-box-daraz">
                    <div class="d-flex align-items-center justify-content-between">
                        <h6 class="mb-0"><i class="fas fa-map-marker-alt" style="color: #ff6600;"></i> Delivery Location</h6>
                    </div>
                    
                    <form action="" method="POST" id="locationForm" class="mt-2">
                        <input type="hidden" name="update_location" value="1">
                        <select name="location" class="location-select" onchange="this.form.submit()">
                            <option value="kathmandu" <?php echo $user_location == 'kathmandu' ? 'selected' : ''; ?>>Kathmandu Metro</option>
                            <option value="lalitpur" <?php echo $user_location == 'lalitpur' ? 'selected' : ''; ?>>Lalitpur Metro</option>
                            <option value="bhaktapur" <?php echo $user_location == 'bhaktapur' ? 'selected' : ''; ?>>Bhaktapur</option>
                            <option value="pokhara" <?php echo $user_location == 'pokhara' ? 'selected' : ''; ?>>Pokhara Metro</option>
                            <option value="biratnagar" <?php echo $user_location == 'biratnagar' ? 'selected' : ''; ?>>Biratnagar</option>
                            <option value="janakpur" <?php echo $user_location == 'janakpur' ? 'selected' : ''; ?>>Janakpur</option>
                            <option value="butwal" <?php echo $user_location == 'butwal' ? 'selected' : ''; ?>>Butwal</option>
                            <option value="nepalgunj" <?php echo $user_location == 'nepalgunj' ? 'selected' : ''; ?>>Nepalgunj</option>
                            <option value="dhangadhi" <?php echo $user_location == 'dhangadhi' ? 'selected' : ''; ?>>Dhangadhi</option>
                            <option value="surkhet" <?php echo $user_location == 'surkhet' ? 'selected' : ''; ?>>Surkhet</option>
                            <option value="dharan" <?php echo $user_location == 'dharan' ? 'selected' : ''; ?>>Dharan</option>
                            <option value="hetuda" <?php echo $user_location == 'hetuda' ? 'selected' : ''; ?>>Hetauda</option>
                            <option value="birganj" <?php echo $user_location == 'birganj' ? 'selected' : ''; ?>>Birganj</option>
                            <option value="damauli" <?php echo $user_location == 'damauli' ? 'selected' : ''; ?>>Damauli</option>
                            <option value="kakarbhitta" <?php echo $user_location == 'kakarbhitta' ? 'selected' : ''; ?>>Kakarbhitta</option>
                            <option value="banepa" <?php echo $user_location == 'banepa' ? 'selected' : ''; ?>>Banepa</option>
                            <option value="dhulikhel" <?php echo $user_location == 'dhulikhel' ? 'selected' : ''; ?>>Dhulikhel</option>
                            <option value="kirtipur" <?php echo $user_location == 'kirtipur' ? 'selected' : ''; ?>>Kirtipur</option>
                            <option value="chandragiri" <?php echo $user_location == 'chandragiri' ? 'selected' : ''; ?>>Chandragiri</option>
                            <option value="tokha" <?php echo $user_location == 'tokha' ? 'selected' : ''; ?>>Tokha</option>
                            <option value="gokarneshwar" <?php echo $user_location == 'gokarneshwar' ? 'selected' : ''; ?>>Gokarneshwar</option>
                            <option value="suryabinayak" <?php echo $user_location == 'suryabinayak' ? 'selected' : ''; ?>>Suryabinayak</option>
                            <option value="madhyapur_thimi" <?php echo $user_location == 'madhyapur_thimi' ? 'selected' : ''; ?>>Madhyapur Thimi</option>
                            <option value="tarakeshwar" <?php echo $user_location == 'tarakeshwar' ? 'selected' : ''; ?>>Tarakeshwar</option>
                            <option value="nagarjun" <?php echo $user_location == 'nagarjun' ? 'selected' : ''; ?>>Nagarjun</option>
                        </select>
                    </form>
                    <div class="small text-muted mt-1">Current: <?php echo getLocationName($user_location); ?></div>
                </div>

                <!-- ============================================ -->
                <!-- DELIVERY SECTION - DARAZ STYLE -->
                <!-- ============================================ -->
                <div class="delivery-box-daraz">
                    <h6 class="mb-2"><i class="fas fa-truck" style="color: #ff6600;"></i> Delivery</h6>
                    
                    <div class="mt-1">
                        <div class="label">Standard Delivery</div>
                        <div class="value price">Rs. <?php echo number_format($delivery_fee, 2); ?></div>
                        <div class="delivery-date">
                            <i class="far fa-calendar-alt" style="color: #ff6600;"></i>
                            Get by <?php echo date('d M', strtotime('+3 days')); ?> - <?php echo date('d M', strtotime('+5 days')); ?>
                        </div>
                    </div>

                    <div class="mt-3">
                        <span class="cod-badge">
                            <i class="fas fa-check-circle"></i> Cash on Delivery Available
                        </span>
                    </div>
                </div>

                <!-- Return & Warranty - Daraz Style -->
                <div class="delivery-box-daraz">
                    <h6><i class="fas fa-rotate-left" style="color: #ff6600;"></i> Return & Warranty</h6>
                    
                    <div class="mt-2">
                        <div class="label">Returns</div>
                        <div class="value">14 Days Free Returns</div>
                    </div>
                    
                    <div class="mt-2">
                        <div class="label">Warranty</div>
                        <div class="value text-danger">Warranty not available</div>
                    </div>
                    
                    <div class="mt-2">
                        <span class="warranty-badge-daraz">
                            <i class="fas fa-shield-alt"></i> Not Covered
                        </span>
                    </div>
                </div>

                <!-- Secure Payment - Daraz Style -->
                <div class="delivery-box-daraz">
                    <h6><i class="fas fa-lock" style="color: #ff6600;"></i> Secure Payment</h6>
                    
                    <div class="mt-2">
                        <p class="small text-muted mb-1">
                            <i class="fas fa-check-circle text-success"></i> eSewa Available
                        </p>
                        <p class="small text-muted mb-1">
                            <i class="fas fa-check-circle text-success"></i> Cash on Delivery
                        </p>
                        <p class="small text-muted mb-0">
                            <i class="fas fa-lock text-primary"></i> 100% Secure
                        </p>
                    </div>
                </div>

                <!-- Seller Info - Daraz Style -->
                <div class="delivery-box-daraz">
                    <h6><i class="fas fa-store" style="color: #ff6600;"></i> Seller</h6>
                    
                    <div class="mt-2">
                        <div class="value small">ShopEmart Official Store</div>
                        <div class="small text-muted">
                            <i class="fas fa-star text-warning"></i> 4.8 (2.5K Ratings)
                        </div>
                        <div class="small text-muted">
                            <i class="fas fa-clock"></i> Online
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function changeImage(src) {
            document.getElementById('mainImage').src = src;
            document.querySelectorAll('.thumb').forEach(el => {
                el.classList.remove('active');
                if (el.src === src) {
                    el.classList.add('active');
                }
            });
        }

        function plusQty() {
            let qty = document.getElementById('qty');
            let max = parseInt(qty.max);
            if (parseInt(qty.value) < max) {
                qty.value = parseInt(qty.value) + 1;
                updateQtyInput();
            } else {
                alert('Only ' + max + ' items available in stock!');
            }
        }

        function minusQty() {
            let qty = document.getElementById('qty');
            if (parseInt(qty.value) > 1) {
                qty.value = parseInt(qty.value) - 1;
                updateQtyInput();
            }
        }

        function updateQtyInput() {
            let qty = document.getElementById('qty');
            let hiddenQty = document.getElementById('qtyInput');
            if (hiddenQty) {
                hiddenQty.value = qty.value;
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            let qty = document.getElementById('qty');
            qty.addEventListener('change', function() {
                let max = parseInt(this.max);
                let value = parseInt(this.value);
                if (value > max) {
                    this.value = max;
                    alert('Only ' + max + ' items available in stock!');
                }
                if (value < 1 || isNaN(value)) {
                    this.value = 1;
                }
                updateQtyInput();
            });

            // Add to cart form submission
            document.getElementById('addToCartForm')?.addEventListener('submit', function(e) {
                const btn = document.getElementById('addToCartBtn');
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            });

            // Scroll to confirmation section if it exists
            <?php if ($add_to_cart_success): ?>
            setTimeout(function() {
                const confirmation = document.getElementById('confirmationSection');
                if (confirmation) {
                    confirmation.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }, 500);
            <?php endif; ?>
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