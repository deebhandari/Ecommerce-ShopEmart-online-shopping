<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get page parameter for navigation
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// Search functionality
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? trim($_GET['category']) : '';

// Fetch products with filters
$query = "SELECT * FROM products WHERE status = 'active'";
$params = [];

if (!empty($search_term)) {
    $query .= " AND (name LIKE ? OR description LIKE ? OR category LIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
}

if (!empty($category_filter)) {
    $query .= " AND category = ?";
    $params[] = $category_filter;
}

$query .= " ORDER BY id DESC LIMIT 12";
$stmt = $db->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all products for products page
$all_products_query = "SELECT * FROM products WHERE status = 'active'";
$all_params = [];

if (!empty($search_term)) {
    $all_products_query .= " AND (name LIKE ? OR description LIKE ? OR category LIKE ?)";
    $all_params[] = "%$search_term%";
    $all_params[] = "%$search_term%";
    $all_params[] = "%$search_term%";
}

if (!empty($category_filter)) {
    $all_products_query .= " AND category = ?";
    $all_params[] = $category_filter;
}

$all_products_query .= " ORDER BY id DESC";
$all_products_stmt = $db->prepare($all_products_query);
$all_products_stmt->execute($all_params);
$all_products = $all_products_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get cart count for badge
$cart_count = 0;
if (isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'customer') {
    $cart_stmt = $db->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
    $cart_stmt->execute([$_SESSION['user_id']]);
    $cart_count = $cart_stmt->fetchColumn() ?: 0;
}

// Get wishlist count for badge
$wishlist_count = 0;
if (isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'customer') {
    $wishlist_stmt = $db->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
    $wishlist_stmt->execute([$_SESSION['user_id']]);
    $wishlist_count = $wishlist_stmt->fetchColumn() ?: 0;
}

// Get wishlist product IDs for heart toggle
$wishlist_product_ids = [];
if (isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'customer') {
    $wishlist_ids_stmt = $db->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
    $wishlist_ids_stmt->execute([$_SESSION['user_id']]);
    $wishlist_product_ids = $wishlist_ids_stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    $wishlist_product_ids = array_flip($wishlist_product_ids);
}

// If page is customercart, include the cart page content
if ($page == 'customercart') {
    include 'customer/cart.php';
    exit();
}

// Display messages
$success_message = isset($_SESSION['cart_success']) ? $_SESSION['cart_success'] : '';
$error_message = isset($_SESSION['cart_error']) ? $_SESSION['cart_error'] : '';
$wishlist_success = isset($_SESSION['wishlist_success']) ? $_SESSION['wishlist_success'] : '';
$wishlist_error = isset($_SESSION['wishlist_error']) ? $_SESSION['wishlist_error'] : '';
unset($_SESSION['cart_success']);
unset($_SESSION['cart_error']);
unset($_SESSION['wishlist_success']);
unset($_SESSION['wishlist_error']);

// Function to get product image path
function getProductImage($image_name) {
    if (empty($image_name)) return null;
    
    $paths = [
        "uploads/" . $image_name,
        "../uploads/" . $image_name,
        "../../uploads/" . $image_name
    ];
    
    foreach ($paths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    return null;
}

// =============================================
// HERO BANNERS WITH REAL IMAGES (CORRECTED)
// =============================================

$hero_banners = [
    [
        'title' => '🛒 Welcome to ShopEMart',
        'subtitle' => 'Discover amazing deals and shop your favorite products',
        'color' => 'linear-gradient(135deg, rgba(255,99,71,0.85) 0%, rgba(255,165,0,0.85) 100%)',
        'icon' => 'fa-shopping-cart',
        'image' => 'assets/images/time.jpg'
    ],
    [
        'title' => '🔥 Electronics Sale',
        'subtitle' => 'Up to 50% off on latest gadgets',
        'color' => 'linear-gradient(135deg, rgba(102,126,234,0.85) 0%, rgba(118,75,162,0.85) 100%)',
        'icon' => 'fa-laptop',
        'image' => 'assets/images/hero-electronics.jpg'
    ],
    [
        'title' => '👗 Fashion Collection',
        'subtitle' => 'New arrivals at unbeatable prices',
        'color' => 'linear-gradient(135deg, rgba(240,147,251,0.85) 0%, rgba(245,87,108,0.85) 100%)',
        'icon' => 'fa-tshirt',
        'image' => 'assets/images/hero-fashion.jpg'
    ],
    [
        'title' => '⚽ Sports Equipment',
        'subtitle' => 'Gear up for your next adventure',
        'color' => 'linear-gradient(135deg, rgba(79,172,254,0.85) 0%, rgba(0,242,254,0.85) 100%)',
        'icon' => 'fa-futbol',
        'image' => 'assets/images/hero-sports.jpg'
    ],
    [
        'title' => '🏠 Home Decor',
        'subtitle' => 'Transform your living space',
        'color' => 'linear-gradient(135deg, rgba(67,233,123,0.85) 0%, rgba(56,249,215,0.85) 100%)',
        'icon' => 'fa-home',
        'image' => 'assets/images/hero-home.jpg'
    ],
    [
        'title' => '📚 Books & Stationery',
        'subtitle' => 'Knowledge at your fingertips',
        'color' => 'linear-gradient(135deg, rgba(250,112,154,0.85) 0%, rgba(254,225,64,0.85) 100%)',
        'icon' => 'fa-book',
        'image' => 'assets/images/hero-books.jpg'
    ],
    [
        'title' => '💄 Beauty & Care',
        'subtitle' => 'Pamper yourself with the best beauty products',
        'color' => 'linear-gradient(135deg, rgba(255,107,107,0.85) 0%, rgba(255,159,67,0.85) 100%)',
        'icon' => 'fa-spa',
        'image' => 'assets/images/beauty.jpg'
    ],
    [
        'title' => '🎮 Gaming Zone',
        'subtitle' => 'Level up your gaming experience',
        'color' => 'linear-gradient(135deg, rgba(168,192,255,0.85) 0%, rgba(63,43,150,0.85) 100%)',
        'icon' => 'fa-gamepad',
        'image' => 'assets/images/pet.jpg'
    ],
    [
        'title' => '🧸 Kids & Toys',
        'subtitle' => 'Fun and educational toys for your little ones',
        'color' => 'linear-gradient(135deg, rgba(255,183,77,0.85) 0%, rgba(255,87,34,0.85) 100%)',
        'icon' => 'fa-toy',
        'image' => 'assets/images/toy.jpg'
    ],
    [
        'title' => '👜 Bags & Accessories',
        'subtitle' => 'Complete your look with stylish accessories',
        'color' => 'linear-gradient(135deg, rgba(236,64,122,0.85) 0%, rgba(156,39,176,0.85) 100%)',
        'icon' => 'fa-shopping-bag',
        'image' => 'assets/images/bag.jpg'
    ],
    [
        'title' => '🍎 Fresh Fruits',
        'subtitle' => 'Healthy and fresh fruits delivered to your door',
        'color' => 'linear-gradient(135deg, rgba(76,175,80,0.85) 0%, rgba(139,195,74,0.85) 100%)',
        'icon' => 'fa-apple-alt',
        'image' => 'assets/images/fruitset.jpg'
    ],
    [
        'title' => '🎁 Gift Ideas',
        'subtitle' => 'Perfect gifts for every occasion',
        'color' => 'linear-gradient(135deg, rgba(233,30,99,0.85) 0%, rgba(255,64,129,0.85) 100%)',
        'icon' => 'fa-gift',
        'image' => 'assets/images/giftset.jpg'
    ]
];

// Select random hero banner
$current_hero = $hero_banners[array_rand($hero_banners)];

// Check if hero image exists
$hero_image_path = $current_hero['image'];
$has_hero_image = file_exists($hero_image_path);

// For About page image
$about_image_exists = file_exists('assets/images/aa.jpg') || 
                      file_exists('assets/images/about-shop.jpg') || 
                      file_exists('assets/images/about.jpg');

// Check if payment images exist
$esewa_image = file_exists('assets/images/payment/esewa.png') ? 'assets/images/payment/esewa.png' : '';
$cod_image = file_exists('assets/images/payment/cod.png') ? 'assets/images/payment/cod.png' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ShopEMart - Online Shopping System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- SweetAlert2 for better notifications -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f1f3f6;
        }

        /* ============================================ */
        /* DARAZ STYLE NAVIGATION */
        /* ============================================ */
        .navbar-daraz {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            padding: 0.8rem 0;
            box-shadow: 0 2px 20px rgba(0,0,0,0.2);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-brand {
            font-size: 1.8rem;
            font-weight: 700;
            color: #f1e5dd !important;
        }
        .navbar-brand i {
            color: #ff6600;
        }

        /* Brand Logo Styles */
        .navbar-brand-custom {
            display: flex;
            align-items: center;
            text-decoration: none;
            padding: 5px 0;
        }
        .navbar-brand-custom img {
            height: 50px;
            width: auto;
            margin-right: 12px;
            transition: transform 0.3s ease;
            border-radius: 8px;
        }
        .navbar-brand-custom:hover img {
            transform: scale(1.05);
        }
        .navbar-brand-custom .brand-name {
            font-size: 1.6rem;
            font-weight: 700;
            color: #f1e5dd;
            letter-spacing: 0.5px;
        }
        .navbar-brand-custom .brand-name span {
            color: #ff6600;
        }
        .navbar-brand-custom .tagline {
            font-size: 0.65rem;
            color: rgba(255,255,255,0.6);
            font-weight: 300;
            letter-spacing: 0.3px;
            margin-top: -2px;
        }

        .nav-link {
            color: rgba(255,255,255,0.85) !important;
            font-weight: 500;
            transition: all 0.3s;
            padding: 8px 16px;
            border-radius: 8px;
            position: relative;
            font-size: 0.95rem;
        }
        .nav-link:hover {
            background: rgba(255,102,0,0.15);
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

        .btn-cart-daraz {
            background: #ff6600;
            border: none;
            color: white !important;
            padding: 8px 20px;
            border-radius: 50px;
            font-weight: 500;
            transition: all 0.3s;
        }
        .btn-cart-daraz:hover {
            background: #e55d00;
            transform: scale(1.05);
            color: white !important;
        }

        /* ============================================ */
        /* SEARCH BAR (Daraz Style) */
        /* ============================================ */
        .search-bar-daraz {
            background: white;
            border-radius: 12px;
            padding: 4px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            max-width: 650px;
            margin: 0 auto;
            transition: all 0.3s;
        }
        .search-bar-daraz:focus-within {
            box-shadow: 0 4px 30px rgba(255,102,0,0.15);
            transform: scale(1.01);
        }

        .search-bar-daraz input {
            border: none;
            padding: 14px 20px;
            border-radius: 12px 0 0 12px;
            font-size: 14px;
            outline: none;
            width: 100%;
        }

        .search-bar-daraz button {
            background: linear-gradient(135deg, #ff6600, #ff8533);
            border: none;
            padding: 14px 30px;
            border-radius: 0 12px 12px 0;
            color: white;
            font-weight: 600;
            transition: all 0.3s;
        }
        .search-bar-daraz button:hover {
            background: #e55d00;
        }

        /* ============================================ */
        /* HERO BANNER WITH REAL IMAGES */
        /* ============================================ */
        .hero-banner-daraz {
            border-radius: 16px;
            padding: 50px 40px;
            margin-bottom: 30px;
            color: white;
            position: relative;
            overflow: hidden;
            min-height: 380px;
            display: flex;
            align-items: center;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            transition: all 0.5s ease;
        }

        .hero-banner-daraz .hero-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.4);
            z-index: 1;
            border-radius: 16px;
        }

        .hero-banner-daraz .hero-content {
            position: relative;
            z-index: 2;
            max-width: 60%;
        }

        .hero-banner-daraz .hero-icon {
            font-size: 4rem;
            margin-bottom: 15px;
            display: inline-block;
            animation: floatIcon 3s ease-in-out infinite;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        @keyframes floatIcon {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        .hero-banner-daraz h1 {
            font-size: 2.8rem;
            font-weight: 700;
            text-shadow: 2px 2px 8px rgba(0,0,0,0.3);
        }

        .hero-banner-daraz p {
            font-size: 1.2rem;
            opacity: 0.95;
            text-shadow: 1px 1px 4px rgba(0,0,0,0.2);
        }

        .hero-banner-daraz .btn-shop {
            background: white;
            border: none;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
            color: #333;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .hero-banner-daraz .btn-shop:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }

        .hero-banner-daraz .btn-explore {
            background: transparent;
            border: 2px solid white;
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
            backdrop-filter: blur(5px);
        }
        .hero-banner-daraz .btn-explore:hover {
            background: white;
            color: #333;
            transform: translateY(-3px);
        }

        .hero-banner-daraz .hero-badge {
            display: inline-block;
            background: rgba(255,255,255,0.2);
            padding: 5px 20px;
            border-radius: 50px;
            font-size: 0.8rem;
            backdrop-filter: blur(10px);
            margin-bottom: 15px;
            border: 1px solid rgba(255,255,255,0.1);
        }

        .hero-dots {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
        }
        .hero-dots .dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255,255,255,0.4);
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            padding: 0;
        }
        .hero-dots .dot.active {
            background: white;
            transform: scale(1.3);
        }
        .hero-dots .dot:hover {
            background: rgba(255,255,255,0.8);
        }

        /* ============================================ */
        /* PRODUCT CARDS */
        /* ============================================ */
        .product-card-daraz {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            height: 100%;
            position: relative;
        }
        .product-card-daraz:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
        }

        .product-card-daraz .image-container {
            height: 220px;
            overflow: hidden;
            background: #f8f9fa;
            position: relative;
        }
        .product-card-daraz .image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s cubic-bezier(0.165, 0.84, 0.44, 1);
        }
        .product-card-daraz:hover .image-container img {
            transform: scale(1.08);
        }

        .product-card-daraz .discount-tag {
            position: absolute;
            top: 10px;
            left: 10px;
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            padding: 3px 12px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
            box-shadow: 0 2px 10px rgba(231,76,60,0.3);
        }

        /* Wishlist Heart Button - Modern E-commerce Style */
        .btn-wishlist-card {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(255,255,255,0.9);
            border: none;
            border-radius: 50%;
            width: 38px;
            height: 38px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 12px rgba(0,0,0,0.12);
            transition: all 0.3s cubic-bezier(0.165, 0.84, 0.44, 1);
            z-index: 5;
            cursor: pointer;
            text-decoration: none;
            backdrop-filter: blur(4px);
        }
        .btn-wishlist-card:hover {
            transform: scale(1.15);
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            background: white;
        }
        .btn-wishlist-card .heart-icon {
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }
        .btn-wishlist-card .heart-icon.in-wishlist {
            color: #e74c3c;
            animation: heartPop 0.3s ease;
        }
        .btn-wishlist-card .heart-icon.not-in-wishlist {
            color: #999;
        }
        .btn-wishlist-card:hover .heart-icon.not-in-wishlist {
            color: #e74c3c;
            transform: scale(1.1);
        }

        @keyframes heartPop {
            0% { transform: scale(1); }
            50% { transform: scale(1.4); }
            100% { transform: scale(1); }
        }

        .product-card-daraz .body {
            padding: 15px;
        }

        .product-card-daraz .title {
            font-size: 0.9rem;
            font-weight: 500;
            color: #1a1a2e;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            height: 45px;
        }

        .product-card-daraz .price {
            font-size: 1.2rem;
            font-weight: 700;
            color: #ff6600;
        }

        .product-card-daraz .price-original {
            font-size: 0.8rem;
            color: #999;
            text-decoration: line-through;
            margin-left: 5px;
        }

        .product-card-daraz .rating {
            font-size: 0.8rem;
            color: #666;
        }

        .product-card-daraz .rating i {
            color: #f59e0b;
        }

        /* ============================================ */
        /* ACTION BUTTONS - PROPER SIZE MATCHING */
        /* ============================================ */
        .product-card-daraz .btn-add {
            background: linear-gradient(135deg, #ff6600, #ff8533);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            flex: 1;
            text-align: center;
            min-height: 38px;
        }
        .product-card-daraz .btn-add:hover {
            background: #e55d00;
            transform: scale(1.05);
            color: white;
        }
        
        .product-card-daraz .btn-add:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .product-card-daraz .btn-view {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            color: white;
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            flex: 1;
            text-align: center;
            min-height: 38px;
        }
        .product-card-daraz .btn-view:hover {
            transform: scale(1.05);
            color: white;
        }

        /* ============================================ */
        /* SECTION TITLE */
        /* ============================================ */
        .section-title-daraz {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1a1a2e;
            position: relative;
            padding-bottom: 12px;
            margin-bottom: 25px;
        }
        .section-title-daraz::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 4px;
            background: linear-gradient(135deg, #ff6600, #ff8533);
            border-radius: 2px;
        }
        .section-title-daraz.text-center::after {
            left: 50%;
            transform: translateX(-50%);
        }

        /* ============================================ */
        /* ABOUT US IMAGE */
        /* ============================================ */
        .about-image-container {
            height: 400px;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 30px rgba(0,0,0,0.1);
        }

        .about-image-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .about-image-container:hover img {
            transform: scale(1.05);
        }

        /* ============================================ */
        /* TOAST NOTIFICATION */
        /* ============================================ */
        .toast-container {
            position: fixed;
            top: 90px;
            right: 20px;
            z-index: 9999;
        }

        .toast-custom {
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            padding: 16px 24px;
            margin-bottom: 10px;
            min-width: 300px;
            animation: slideInRight 0.5s ease;
            border-left: 4px solid #28a745;
        }

        .toast-custom.error {
            border-left-color: #dc3545;
        }

        .toast-custom .toast-title {
            font-weight: 600;
            font-size: 1rem;
        }

        .toast-custom .toast-message {
            color: #666;
            font-size: 0.9rem;
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
        /* PAYMENT METHODS IN FOOTER */
        /* ============================================ */
        .payment-method-img {
            height: 40px;
            width: auto;
            border-radius: 4px;
            transition: transform 0.3s ease;
            border: 1px solid rgba(255,255,255,0.1);
            padding: 4px 8px;
            background: white;
            object-fit: contain;
        }
        .payment-method-img:hover {
            transform: scale(1.1);
            border-color: #ff6600;
        }

        footer .text-muted {
            color: rgba(255,255,255,0.6) !important;
        }

        footer h5 {
            color: white;
            font-weight: 600;
            margin-bottom: 15px;
        }

        footer ul li {
            margin-bottom: 8px;
        }

        footer ul li a:hover {
            color: #ff6600 !important;
        }

        /* ============================================ */
        /* RESPONSIVE */
        /* ============================================ */
        @media (max-width: 991px) {
            .navbar-daraz .navbar-collapse {
                background: rgba(26, 26, 46, 0.98);
                padding: 20px;
                border-radius: 12px;
                margin-top: 10px;
                backdrop-filter: blur(10px);
                border: 1px solid rgba(255,255,255,0.05);
            }
            .nav-link {
                padding: 10px 16px;
            }
        }

        @media (max-width: 768px) {
            .navbar-brand-custom .brand-name {
                font-size: 1.2rem;
            }
            .navbar-brand-custom img {
                height: 40px;
            }
            .navbar-brand-custom .tagline {
                font-size: 0.55rem;
            }
            .search-bar-daraz {
                max-width: 100%;
            }
            .product-card-daraz .image-container {
                height: 180px;
            }
            .hero-banner-daraz {
                padding: 30px 20px;
                min-height: 280px;
            }
            .hero-banner-daraz h1 {
                font-size: 1.8rem;
            }
            .hero-banner-daraz .hero-icon {
                font-size: 2.5rem;
            }
            .hero-banner-daraz .hero-content {
                max-width: 100%;
            }
            .about-image-container {
                height: 250px;
            }
            .payment-method-img {
                height: 30px;
            }
            .product-card-daraz .body {
                padding: 12px;
            }
            .product-card-daraz .btn-add,
            .product-card-daraz .btn-view {
                font-size: 0.75rem;
                padding: 6px 12px;
                min-height: 32px;
            }
        }

        @media (max-width: 576px) {
            footer .row > div {
                text-align: center;
            }
            .payment-method-img {
                height: 28px;
            }
            .hero-banner-daraz {
                min-height: 220px;
                padding: 20px 15px;
            }
            .hero-banner-daraz h1 {
                font-size: 1.4rem;
            }
            .hero-banner-daraz p {
                font-size: 0.9rem;
            }
            .hero-banner-daraz .btn-shop,
            .hero-banner-daraz .btn-explore {
                padding: 8px 20px;
                font-size: 0.85rem;
            }
            .section-title-daraz {
                font-size: 1.2rem;
            }
        }

        /* Dropdown menu styling */
        .dropdown-menu {
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.15);
            border: none;
            padding: 8px 0;
        }
        .dropdown-menu .dropdown-item {
            padding: 8px 20px;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .dropdown-menu .dropdown-item:hover {
            background: rgba(255,102,0,0.08);
            color: #ff6600;
        }
        .dropdown-menu .dropdown-item i {
            width: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- ============================================ -->
    <!-- DARAZ STYLE NAVIGATION WITH BRAND LOGO -->
    <!-- ============================================ -->
    <nav class="navbar navbar-expand-lg navbar-daraz">
        <div class="container">
            <!-- Brand Logo with Image -->
            <a class="navbar-brand-custom" href="index.php?page=home">
                <img src="assets/images/ss.jpg" alt="ShopEMart Logo">
                <div class="logo-text">
                    <div class="brand-name">Shop<span>EMart</span></div>
                    <small class="tagline">Shop Smarter, Live Better</small>
                </div>
            </a>
            
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <!-- Home -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page == 'home') ? 'active' : ''; ?>" href="?page=home">
                            <i class="fas fa-home"></i> Home
                        </a>
                    </li>
                    
                    <!-- About -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page == 'about') ? 'active' : ''; ?>" href="?page=about">
                            <i class="fas fa-info-circle"></i> About
                        </a>
                    </li>
                    
                    <!-- Products -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page == 'products') ? 'active' : ''; ?>" href="?page=products">
                            <i class="fas fa-box-open"></i> Products
                        </a>
                    </li>
                    
                    <!-- Contact -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page == 'contact') ? 'active' : ''; ?>" href="?page=contact">
                            <i class="fas fa-envelope"></i> Contact
                        </a>
                    </li>

                    <!-- Wishlist - Only for logged in customers -->
                    <?php if(isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'customer'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="customer/wishlist.php">
                                <i class="fas fa-heart text-danger"></i> Wishlist
                                <?php if($wishlist_count > 0): ?>
                                    <span class="badge bg-danger rounded-pill ms-1"><?php echo $wishlist_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endif; ?>

                    <!-- Cart -->
                    <?php if(isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'customer'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($page == 'customercart') ? 'active' : ''; ?>" href="?page=customercart">
                                <i class="fas fa-shopping-cart"></i> Cart
                                <?php if($cart_count > 0): ?>
                                    <span class="badge bg-danger rounded-pill ms-1" id="cartBadge"><?php echo $cart_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="login.php">
                                <i class="fas fa-shopping-cart"></i> Cart
                            </a>
                        </li>
                    <?php endif; ?>

                    <!-- User Dropdown or Login/Register -->
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <?php if($_SESSION['user_type'] == 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="admin/dashboard.php">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" role="button" aria-expanded="false">
                                    <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li><a class="dropdown-item" href="customer/dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                                    <li><a class="dropdown-item" href="customer/shop.php"><i class="fas fa-shopping-bag"></i> Shop</a></li>
                                    <li><a class="dropdown-item" href="customer/wishlist.php"><i class="fas fa-heart text-danger"></i> Wishlist</a></li>
                                    <li><a class="dropdown-item" href="?page=customercart"><i class="fas fa-shopping-cart"></i> Cart</a></li>
                                    <li><a class="dropdown-item" href="customer/orders.php"><i class="fas fa-history"></i> Orders</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                                </ul>
                            </li>
                        <?php endif; ?>
                    <?php else: ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" role="button" aria-expanded="false">
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

    <!-- ============================================ -->
    <!-- DARAZ STYLE SEARCH BAR -->
    <!-- ============================================ -->
    <div class="container mt-3">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <form method="GET" class="search-bar-daraz d-flex" action="index.php">
                    <input type="hidden" name="page" value="products">
                    <input type="text" name="search" placeholder="Search in ShopEMart..." value="<?php echo htmlspecialchars($search_term); ?>">
                    <button type="submit"><i class="fas fa-search"></i> Search</button>
                </form>
            </div>
        </div>
    </div>

    <div class="container mt-4">
        <!-- Display Success/Error Messages -->
        <?php if($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if($wishlist_success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-heart text-danger"></i> <?php echo htmlspecialchars($wishlist_success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if($wishlist_error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($wishlist_error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Dynamic Page Content -->
        <?php if($page == 'home'): ?>
            <!-- ============================================ -->
            <!-- ATTRACTIVE HERO BANNER WITH REAL IMAGES -->
            <!-- ============================================ -->
            <div id="heroBanner" class="hero-banner-daraz" 
                 style="background: <?php echo $has_hero_image ? 'url(' . $hero_image_path . ') center/cover no-repeat' : $current_hero['color']; ?>;">
                <div class="hero-overlay"></div>
                <div class="hero-content">
                    <div class="hero-badge">
                        <i class="fas fa-fire"></i> HOT DEALS
                    </div>
                    <div class="hero-icon">
                        <i class="fas <?php echo $current_hero['icon']; ?>"></i>
                    </div>
                    <h1 class="display-4 fw-bold"><?php echo $current_hero['title']; ?></h1>
                    <p class="lead"><?php echo $current_hero['subtitle']; ?></p>
                    <div class="mt-4">
                        <a href="?page=products" class="btn btn-shop me-2">
                            <i class="fas fa-shopping-bag"></i> Start Shopping
                        </a>
                        <a href="?page=products" class="btn btn-explore">
                            <i class="fas fa-arrow-right"></i> Explore
                        </a>
                    </div>
                    <!-- Hero Dots -->
                    <div class="hero-dots">
                        <?php foreach($hero_banners as $index => $banner): ?>
                            <button class="dot <?php echo $index == array_search($current_hero, $hero_banners) ? 'active' : ''; ?>" 
                                    onclick="changeHero(<?php echo $index; ?>)"></button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- ============================================ -->
            <!-- PRODUCTS SECTION -->
            <!-- ============================================ -->
            <div class="row">
                <div class="col-lg-12">
                    <h2 class="section-title-daraz">
                        <i class="fas fa-star text-warning"></i> Featured Products
                    </h2>

                    <?php if($search_term): ?>
                        <div class="alert alert-info alert-dismissible fade show">
                            <i class="fas fa-search"></i> Showing results for: <strong>"<?php echo htmlspecialchars($search_term); ?>"</strong>
                            <a href="?page=products" class="float-end text-decoration-none fw-bold">Clear search</a>
                        </div>
                    <?php endif; ?>

                    <?php if($category_filter): ?>
                        <div class="alert alert-info alert-dismissible fade show">
                            <i class="fas fa-tag"></i> Category: <strong><?php echo htmlspecialchars($category_filter); ?></strong>
                            <a href="?page=products" class="float-end text-decoration-none fw-bold">Clear filter</a>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <?php if(count($all_products) > 0): ?>
                            <?php foreach($all_products as $product):
                                $discount = $product['price'] * 1.15;
                                $discount_percent = 15;
                                $image_path = getProductImage($product['image']);
                                $in_wishlist = isset($wishlist_product_ids[$product['id']]);
                            ?>
                                <div class="col-6 col-md-3 mb-4">
                                    <div class="product-card-daraz">
                                        <!-- Wishlist Heart Button - Modern E-commerce Style -->
                                        <?php if(isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'customer'): ?>
                                            <form action="customer/add_to_wishlist.php" method="GET" class="position-absolute" style="top: 10px; right: 10px; z-index: 10;">
                                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                <input type="hidden" name="redirect" value="index.php?page=home">
                                                <button type="submit" class="btn-wishlist-card" title="<?php echo $in_wishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>">
                                                    <i class="fas fa-heart heart-icon <?php echo $in_wishlist ? 'in-wishlist' : 'not-in-wishlist'; ?>"></i>
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <!-- Show heart icon for non-logged in users that links to login -->
                                            <a href="login.php" class="btn-wishlist-card" title="Login to add to wishlist">
                                                <i class="far fa-heart heart-icon not-in-wishlist"></i>
                                            </a>
                                        <?php endif; ?>

                                        <a href="customer/product_details.php?id=<?php echo $product['id']; ?>" class="text-decoration-none text-dark">
                                            <div class="image-container">
                                                <?php if($image_path): ?>
                                                    <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" loading="lazy">
                                                <?php else: ?>
                                                    <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                                                        <div class="text-center">
                                                            <i class="fas fa-box-open fa-3x d-block"></i>
                                                            <small>No Image</small>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                <span class="discount-tag"><?php echo $discount_percent; ?>% OFF</span>
                                            </div>
                                        </a>
                                        <div class="body">
                                            <a href="customer/product_details.php?id=<?php echo $product['id']; ?>" class="text-decoration-none text-dark">
                                                <div class="title"><?php echo htmlspecialchars($product['name']); ?></div>
                                            </a>
                                            <div class="rating mt-1">
                                                <?php for($i=1; $i<=5; $i++): ?>
                                                    <i class="fas fa-star"></i>
                                                <?php endfor; ?>
                                                <span class="ms-1">4.5 (1.2K)</span>
                                            </div>
                                            <div class="price">
                                                Rs. <?php echo number_format($product['price'], 2); ?>
                                                <span class="price-original">Rs. <?php echo number_format($discount, 2); ?></span>
                                            </div>
                                            <div class="d-flex gap-1 mt-2">
                                                <?php if(isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'customer'): ?>
                                                    <?php if($product['stock'] > 0): ?>
                                                        <button type="button" 
                                                                class="btn-add btn-sm add-to-cart-btn" 
                                                                data-product-id="<?php echo $product['id']; ?>"
                                                                data-product-name="<?php echo htmlspecialchars($product['name']); ?>">
                                                            <i class="fas fa-cart-plus"></i> Add
                                                        </button>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Out of Stock</span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <a href="login.php" class="btn-add btn-sm"><i class="fas fa-lock"></i> Buy</a>
                                                <?php endif; ?>
                                                <a href="customer/product_details.php?id=<?php echo $product['id']; ?>" class="btn-view btn-sm"><i class="fas fa-eye"></i> View</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="alert alert-info text-center py-5">
                                    <i class="fas fa-search fa-4x mb-3 d-block text-muted"></i>
                                    <h4>No products found</h4>
                                    <p class="text-muted">Try adjusting your search or browse all products.</p>
                                    <a href="?page=products" class="btn btn-primary mt-2"><i class="fas fa-sync-alt"></i> View All Products</a>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        <?php elseif($page == 'about'): ?>
            <!-- ============================================ -->
            <!-- ABOUT US PAGE WITH IMAGE -->
            <!-- ============================================ -->
            <div class="about-section">
                <h2 class="section-title-daraz">About ShopEMart</h2>

                <div class="row align-items-center mb-5">
                    <div class="col-md-6">
                        <div class="about-image-container">
                            <?php if(file_exists('assets/images/bb.jpg')): ?>
                                <img src="assets/images/bb.jpg" alt="About ShopEMart">
                            <?php elseif(file_exists('assets/images/about-shop.jpg')): ?>
                                <img src="assets/images/about-shop.jpg" alt="About ShopEMart">
                            <?php elseif(file_exists('assets/images/about.jpg')): ?>
                                <img src="assets/images/about.jpg" alt="About ShopEMart">
                            <?php else: ?>
                                <div class="d-flex align-items-center justify-content-center h-100" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <div class="text-center text-white p-5">
                                        <i class="fas fa-store fa-5x mb-3"></i>
                                        <h3>ShopEMart</h3>
                                        <p>Your Trusted Shopping Partner</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h3>Our Story</h3>
                        <p>ShopEMart was founded in 2024 with a simple mission: to provide the best online shopping experience with quality products at affordable prices. We believe in customer satisfaction and strive to bring the best deals to your doorstep.</p>
                        <p>With a wide range of products from electronics to fashion, sports to books, we have something for everyone. Our team works tirelessly to ensure fast delivery and secure payments.</p>
                        <div class="row mt-4">
                            <div class="col-4 text-center">
                                <div class="p-3 bg-light rounded shadow-sm">
                                    <i class="fas fa-users fa-2x text-primary"></i>
                                    <h4 class="mt-2">10K+</h4>
                                    <p class="text-muted">Customers</p>
                                </div>
                            </div>
                            <div class="col-4 text-center">
                                <div class="p-3 bg-light rounded shadow-sm">
                                    <i class="fas fa-box-open fa-2x text-primary"></i>
                                    <h4 class="mt-2">500+</h4>
                                    <p class="text-muted">Products</p>
                                </div>
                            </div>
                            <div class="col-4 text-center">
                                <div class="p-3 bg-light rounded shadow-sm">
                                    <i class="fas fa-truck fa-2x text-primary"></i>
                                    <h4 class="mt-2">24/7</h4>
                                    <p class="text-muted">Delivery</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-4 mb-4">
                        <div class="card text-center p-4 h-100 shadow-sm">
                            <i class="fas fa-shipping-fast fa-3x text-primary mb-3"></i>
                            <h4>Free Shipping</h4>
                            <p class="text-muted">Free shipping on orders over RS 5000</p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card text-center p-4 h-100 shadow-sm">
                            <i class="fas fa-lock fa-3x text-primary mb-3"></i>
                            <h4>Secure Payment</h4>
                            <p class="text-muted">eSewa & COD options available</p>
                        </div>
                    </div>
                    <div class="col-md-4 mb-4">
                        <div class="card text-center p-4 h-100 shadow-sm">
                            <i class="fas fa-headset fa-3x text-primary mb-3"></i>
                            <h4>24/7 Support</h4>
                            <p class="text-muted">Dedicated customer support</p>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif($page == 'products'): ?>
            <!-- Products Page -->
            <div class="products-section">
                <h2 class="section-title-daraz">
                    <i class="fas fa-box-open text-primary"></i> All Products
                </h2>

                <!-- Category Filter -->
                <div class="mb-4">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="?page=products" class="btn <?php echo empty($category_filter) ? 'btn-primary' : 'btn-outline-secondary'; ?> btn-sm">All</a>
                        <?php
                        // Fetch distinct categories from products
                        $cat_stmt = $db->query("SELECT DISTINCT category FROM products WHERE status = 'active' AND category IS NOT NULL AND category != '' ORDER BY category");
                        $categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);
                        foreach($categories as $cat):
                        ?>
                            <a href="?page=products&category=<?php echo urlencode($cat); ?>" 
                               class="btn <?php echo ($category_filter == $cat) ? 'btn-primary' : 'btn-outline-secondary'; ?> btn-sm">
                                <?php echo htmlspecialchars($cat); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <?php if($search_term): ?>
                            <div class="alert alert-info alert-dismissible fade show">
                                <i class="fas fa-search"></i> Showing results for: <strong>"<?php echo htmlspecialchars($search_term); ?>"</strong>
                                <a href="?page=products" class="float-end text-decoration-none fw-bold">Clear search</a>
                            </div>
                        <?php endif; ?>

                        <?php if($category_filter): ?>
                            <div class="alert alert-info alert-dismissible fade show">
                                <i class="fas fa-tag"></i> Category: <strong><?php echo htmlspecialchars($category_filter); ?></strong>
                                <a href="?page=products" class="float-end text-decoration-none fw-bold">Clear filter</a>
                            </div>
                        <?php endif; ?>

                        <div class="row">
                            <?php if(count($all_products) > 0): ?>
                                <?php foreach($all_products as $product):
                                    $discount = $product['price'] * 1.15;
                                    $discount_percent = 15;
                                    $image_path = getProductImage($product['image']);
                                    $in_wishlist = isset($wishlist_product_ids[$product['id']]);
                                ?>
                                    <div class="col-6 col-md-3 mb-4">
                                        <div class="product-card-daraz">
                                            <!-- Wishlist Heart Button -->
                                            <?php if(isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'customer'): ?>
                                                <form action="customer/add_to_wishlist.php" method="GET" class="position-absolute" style="top: 10px; right: 10px; z-index: 10;">
                                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                    <input type="hidden" name="redirect" value="index.php?page=products">
                                                    <button type="submit" class="btn-wishlist-card" title="<?php echo $in_wishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>">
                                                        <i class="fas fa-heart heart-icon <?php echo $in_wishlist ? 'in-wishlist' : 'not-in-wishlist'; ?>"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <a href="login.php" class="btn-wishlist-card" title="Login to add to wishlist">
                                                    <i class="far fa-heart heart-icon not-in-wishlist"></i>
                                                </a>
                                            <?php endif; ?>

                                            <a href="customer/product_details.php?id=<?php echo $product['id']; ?>" class="text-decoration-none text-dark">
                                                <div class="image-container">
                                                    <?php if($image_path): ?>
                                                        <img src="<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($product['name']); ?>" loading="lazy">
                                                    <?php else: ?>
                                                        <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                                                            <div class="text-center">
                                                                <i class="fas fa-box-open fa-3x d-block"></i>
                                                                <small>No Image</small>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                    <span class="discount-tag"><?php echo $discount_percent; ?>% OFF</span>
                                                </div>
                                            </a>
                                            <div class="body">
                                                <a href="customer/product_details.php?id=<?php echo $product['id']; ?>" class="text-decoration-none text-dark">
                                                    <div class="title"><?php echo htmlspecialchars($product['name']); ?></div>
                                                </a>
                                                <div class="rating mt-1">
                                                    <?php for($i=1; $i<=5; $i++): ?>
                                                        <i class="fas fa-star"></i>
                                                    <?php endfor; ?>
                                                    <span class="ms-1">4.5 (1.2K)</span>
                                                </div>
                                                <div class="price">
                                                    Rs. <?php echo number_format($product['price'], 2); ?>
                                                    <span class="price-original">Rs. <?php echo number_format($discount, 2); ?></span>
                                                </div>
                                                <div class="d-flex gap-1 mt-2">
                                                    <?php if(isset($_SESSION['user_id']) && $_SESSION['user_type'] == 'customer'): ?>
                                                        <?php if($product['stock'] > 0): ?>
                                                            <button type="button" 
                                                                    class="btn-add btn-sm add-to-cart-btn" 
                                                                    data-product-id="<?php echo $product['id']; ?>"
                                                                    data-product-name="<?php echo htmlspecialchars($product['name']); ?>">
                                                                <i class="fas fa-cart-plus"></i> Add
                                                            </button>
                                                        <?php else: ?>
                                                            <span class="badge bg-danger">Out of Stock</span>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <a href="login.php" class="btn-add btn-sm"><i class="fas fa-lock"></i> Buy</a>
                                                    <?php endif; ?>
                                                    <a href="customer/product_details.php?id=<?php echo $product['id']; ?>" class="btn-view btn-sm"><i class="fas fa-eye"></i> View</a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <div class="alert alert-info text-center py-5">
                                        <i class="fas fa-search fa-4x mb-3 d-block text-muted"></i>
                                        <h4>No products found</h4>
                                        <p class="text-muted">Try adjusting your search or browse all products.</p>
                                        <a href="?page=products" class="btn btn-primary mt-2"><i class="fas fa-sync-alt"></i> View All Products</a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif($page == 'contact'): ?>
            <!-- Contact Page -->
            <div class="contact-section">
                <h2 class="section-title-daraz">
                    <i class="fas fa-envelope text-primary"></i> Contact Us
                </h2>
                <p class="text-muted mb-4">We'd love to hear from you!</p>

                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card p-4 shadow-sm">
                            <h4><i class="fas fa-envelope text-primary"></i> Send Message</h4>
                            <form method="POST" action="customer/contact.php">
                                <div class="mb-3">
                                    <input type="text" name="name" class="form-control" placeholder="Your Name" required>
                                </div>
                                <div class="mb-3">
                                    <input type="email" name="email" class="form-control" placeholder="Your Email" required>
                                </div>
                                <div class="mb-3">
                                    <input type="text" name="subject" class="form-control" placeholder="Subject" required>
                                </div>
                                <div class="mb-3">
                                    <textarea name="message" class="form-control" rows="5" placeholder="Your Message" required></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Send Message</button>
                            </form>
                        </div>
                    </div>
                    <div class="col-md-6 mb-4">
                        <div class="card p-4 shadow-sm">
                            <h4><i class="fas fa-address-card text-primary"></i> Contact Info</h4>
                            <hr>
                            <p><i class="fas fa-map-marker-alt text-primary me-2"></i> Kathmandu, Nepal</p>
                            <p><i class="fas fa-phone text-primary me-2"></i> +977 9800000000</p>
                            <p><i class="fas fa-envelope text-primary me-2"></i> support@shopemart.com</p>
                        </div>
                        <div class="card mt-4 p-4 shadow-sm">
                            <h5><i class="fas fa-clock text-primary"></i> Business Hours</h5>
                            <p class="mb-1">Mon-Fri: 9:00 AM - 6:00 PM</p>
                            <p class="mb-1">Sat: 10:00 AM - 4:00 PM</p>
                            <p>Sun: Closed</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- ============================================ -->
    <!-- FOOTER WITH PAYMENT METHODS -->
    <!-- ============================================ -->
    <footer class="bg-dark text-white py-4 mt-5">
        <div class="container">
            <div class="row">
                <!-- Brand -->
                <div class="col-md-3 mb-3">
                    <h5><i class="fas fa-store"></i> ShopEMart</h5>
                    <p class="text-muted">Your trusted online shopping destination.</p>
                </div>

                <!-- Quick Links -->
                <div class="col-md-2 mb-3">
                    <h5>Quick Links</h5>
                    <ul class="list-unstyled">
                        <li><a href="?page=home" class="text-muted text-decoration-none">Home</a></li>
                        <li><a href="?page=about" class="text-muted text-decoration-none">About</a></li>
                        <li><a href="?page=products" class="text-muted text-decoration-none">Products</a></li>
                        <li><a href="?page=contact" class="text-muted text-decoration-none">Contact</a></li>
                    </ul>
                </div>

                <!-- Customer Service -->
                <div class="col-md-2 mb-3">
                    <h5>Customer Service</h5>
                    <ul class="list-unstyled">
                        <li><a href="#" class="text-muted text-decoration-none">FAQ</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Shipping Info</a></li>
                        <li><a href="#" class="text-muted text-decoration-none">Returns Policy</a></li>
                    </ul>
                </div>

                <!-- Contact Info -->
                <div class="col-md-3 mb-3">
                    <h5>Contact Info</h5>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-phone me-2"></i> +977 9800000000</li>
                        <li><i class="fas fa-envelope me-2"></i> support@shopemart.com</li>
                        <li><i class="fas fa-map-marker-alt me-2"></i> Kathmandu, Nepal</li>
                    </ul>
                </div>

                <!-- Payment Methods -->
                <div class="col-md-2 mb-3">
                    <h5>Payment Methods</h5>
                    <div class="d-flex align-items-center gap-3 mt-3">
                        <?php if(!empty($esewa_image)): ?>
                            <img src="<?php echo $esewa_image; ?>" 
                                 alt="eSewa" 
                                 class="payment-method-img">
                        <?php else: ?>
                            <div class="text-center">
                                <i class="fas fa-wallet fa-2x text-success"></i>
                                <small class="d-block text-muted">eSewa</small>
                            </div>
                        <?php endif; ?>

                        <?php if(!empty($cod_image)): ?>
                            <img src="<?php echo $cod_image; ?>" 
                                 alt="Cash on Delivery" 
                                 class="payment-method-img">
                        <?php else: ?>
                            <div class="text-center">
                                <i class="fas fa-money-bill-wave fa-2x text-warning"></i>
                                <small class="d-block text-muted">COD</small>
                            </div>
                        <?php endif; ?>
                    </div>
                    <small class="text-muted d-block mt-2">
                        <i class="fas fa-lock me-1"></i> Secure Payment
                    </small>
                </div>
            </div>

            <hr>
            <div class="text-center text-muted">
                <p>&copy; <?php echo date('Y'); ?> ShopEMart. All rights reserved.</p>
                <small>Powered by PHP & MySQL | Secure Payments</small>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // ============================================
        // HERO BANNER ROTATION
        // ============================================
        const heroBanners = <?php echo json_encode($hero_banners); ?>;
        let currentHeroIndex = <?php echo array_search($current_hero, $hero_banners); ?>;
        let heroInterval;

        function changeHero(index) {
            const hero = heroBanners[index];
            const banner = document.getElementById('heroBanner');
            
            banner.style.opacity = '0.7';
            setTimeout(() => {
                const img = new Image();
                img.src = hero.image;
                img.onload = function() {
                    banner.style.background = 'url(' + hero.image + ') center/cover no-repeat';
                };
                img.onerror = function() {
                    banner.style.background = hero.color;
                };
                
                banner.querySelector('.hero-icon i').className = 'fas ' + hero.icon;
                banner.querySelector('h1').textContent = hero.title;
                banner.querySelector('p').textContent = hero.subtitle;
                
                document.querySelectorAll('.hero-dots .dot').forEach((dot, i) => {
                    dot.classList.toggle('active', i === index);
                });
                
                banner.style.opacity = '1';
            }, 300);
            
            currentHeroIndex = index;
            clearInterval(heroInterval);
            startHeroRotation();
        }

        function startHeroRotation() {
            heroInterval = setInterval(() => {
                let nextIndex = (currentHeroIndex + 1) % heroBanners.length;
                changeHero(nextIndex);
            }, 5000);
        }

        setTimeout(startHeroRotation, 3000);

        // ============================================
        // AJAX ADD TO CART
        // ============================================
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.add-to-cart-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const productId = this.dataset.productId;
                    const productName = this.dataset.productName;
                    const button = this;
                    
                    button.disabled = true;
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
                    
                    fetch('customer/add_to_cart.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: 'product_id=' + productId + '&quantity=1'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showToast('✅ Added to Cart', data.message || productName + ' added to cart!', 'success');
                            updateCartCount(data.cart_count);
                            button.innerHTML = '<i class="fas fa-check"></i> Added!';
                            setTimeout(() => {
                                button.innerHTML = '<i class="fas fa-cart-plus"></i> Add';
                                button.disabled = false;
                            }, 2000);
                        } else {
                            if (data.redirect) {
                                Swal.fire({
                                    icon: 'warning',
                                    title: 'Login Required',
                                    text: data.message || 'Please login to add items to cart',
                                    confirmButtonText: 'Login Now',
                                    confirmButtonColor: '#ff6600'
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        window.location.href = data.redirect || 'login.php';
                                    }
                                });
                                button.innerHTML = '<i class="fas fa-cart-plus"></i> Add';
                                button.disabled = false;
                            } else {
                                showToast('❌ Error', data.message || 'Failed to add product to cart.', 'error');
                                button.innerHTML = '<i class="fas fa-cart-plus"></i> Add';
                                button.disabled = false;
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast('❌ Error', 'Something went wrong. Please try again.', 'error');
                        button.innerHTML = '<i class="fas fa-cart-plus"></i> Add';
                        button.disabled = false;
                    });
                });
            });
        });

        // ============================================
        // TOAST NOTIFICATION SYSTEM
        // ============================================
        function showToast(title, message, type = 'success') {
            const container = document.getElementById('toastContainer') || createToastContainer();
            
            const toast = document.createElement('div');
            toast.className = `toast-custom ${type}`;
            toast.innerHTML = `
                <div class="toast-title">${title}</div>
                <div class="toast-message">${message}</div>
            `;
            
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                toast.style.transition = 'all 0.5s ease';
                setTimeout(() => {
                    toast.remove();
                }, 500);
            }, 4000);
        }

        function createToastContainer() {
            const container = document.createElement('div');
            container.id = 'toastContainer';
            container.className = 'toast-container';
            document.body.appendChild(container);
            return container;
        }

        // ============================================
        // UPDATE CART COUNT
        // ============================================
        function updateCartCount(count) {
            const cartBadge = document.getElementById('cartBadge');
            if (cartBadge) {
                if (count > 0) {
                    cartBadge.textContent = count;
                    cartBadge.style.display = 'inline-block';
                } else {
                    cartBadge.style.display = 'none';
                }
            }
        }

        // ============================================
        // QUANTITY INPUT VALIDATION
        // ============================================
        document.querySelectorAll('.quantity-input').forEach(input => {
            input.addEventListener('change', function() {
                let max = parseInt(this.getAttribute('max'));
                let value = parseInt(this.value);
                if (value > max) {
                    this.value = max;
                    showToast('⚠️ Stock Limit', 'Only ' + max + ' items available in stock!', 'error');
                }
                if (value < 1 || isNaN(value)) {
                    this.value = 1;
                }
            });
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            document.querySelectorAll('.alert').forEach(alert => {
                if (alert.classList.contains('alert-dismissible')) {
                    const closeBtn = alert.querySelector('.btn-close');
                    if (closeBtn) {
                        closeBtn.click();
                    }
                }
            });
        }, 5000);
    </script>
</body>
</html>