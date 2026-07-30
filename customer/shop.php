<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'customer') {
    header("Location: ../login.php");
    exit();
}
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$category = isset($_GET['category']) ? $_GET['category'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$sql = "SELECT * FROM products WHERE status = 'active'";
$params = [];
if ($category) {
    $sql .= " AND category LIKE ?";
    $params[] = "%$category%";
}
if ($search) {
    $sql .= " AND (name LIKE ? OR description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql .= " ORDER BY id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get cart count for badge
$cart_count_stmt = $db->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
$cart_count_stmt->execute([$_SESSION['user_id']]);
$cart_count = $cart_count_stmt->fetchColumn() ?: 0;

// Get wishlist count for badge
$wishlist_count_stmt = $db->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
$wishlist_count_stmt->execute([$_SESSION['user_id']]);
$wishlist_count = $wishlist_count_stmt->fetchColumn() ?: 0;

// Get user's wishlist product IDs for heart toggle
$wishlist_stmt = $db->prepare("SELECT product_id FROM wishlist WHERE user_id = ?");
$wishlist_stmt->execute([$_SESSION['user_id']]);
$wishlist_products = $wishlist_stmt->fetchAll(PDO::FETCH_COLUMN, 0);
$wishlist_product_ids = array_flip($wishlist_products); // For quick lookup

// Display messages
$success_message = isset($_SESSION['cart_success']) ? $_SESSION['cart_success'] : '';
$error_message = isset($_SESSION['cart_error']) ? $_SESSION['cart_error'] : '';
$wishlist_success = isset($_SESSION['wishlist_success']) ? $_SESSION['wishlist_success'] : '';
$wishlist_error = isset($_SESSION['wishlist_error']) ? $_SESSION['wishlist_error'] : '';
unset($_SESSION['cart_success']);
unset($_SESSION['cart_error']);
unset($_SESSION['wishlist_success']);
unset($_SESSION['wishlist_error']);

// Get all categories for sidebar
$categories = $db->query("SELECT DISTINCT category FROM products WHERE status = 'active' AND category IS NOT NULL AND category != ''")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop - ShopEMart</title>
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
        }
        
        /* Navigation */
        .navbar-daraz {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            padding: 0.8rem 0;
            box-shadow: 0 2px 20px rgba(0,0,0,0.2);
        }
        
        /* Logo Styles */
        .navbar-brand {
            display: flex !important;
            align-items: center;
            text-decoration: none;
            padding: 0;
        }
        
        .logo-img {
            height: 45px;
            width: auto;
            border-radius: 8px;
            transition: transform 0.3s ease;
        }
        
        .navbar-brand:hover .logo-img {
            transform: scale(1.05);
        }
        
        .logo-text {
            display: flex;
            flex-direction: column;
            line-height: 1.1;
        }
        
        .brand-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: 0.5px;
        }
        
        .brand-name span {
            color: #ff6600;
        }
        
        .brand-tagline {
            font-size: 0.6rem;
            color: #a0a0b0;
            letter-spacing: 1px;
            font-weight: 300;
            text-transform: uppercase;
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
        .nav-link.active {
            background: #ff6600;
            color: white !important;
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
        
        /* Category Sidebar */
        .category-filter {
            position: sticky;
            top: 20px;
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            overflow: hidden;
        }
        
        .category-header {
            background: linear-gradient(135deg, #ff6600, #ff8533);
            color: white;
            padding: 15px 20px;
        }
        
        .category-header h5 {
            font-weight: 600;
        }
        
        .category-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .category-list li {
            margin: 0;
        }
        
        .category-list a {
            text-decoration: none;
            padding: 12px 20px;
            display: block;
            transition: all 0.3s;
            color: #555;
            border-left: 3px solid transparent;
            font-size: 0.9rem;
        }
        
        .category-list a:hover {
            background: #fff8f0;
            color: #ff6600;
            border-left-color: #ff6600;
            padding-left: 25px;
        }
        
        .category-list a.active {
            background: #fff8f0;
            color: #ff6600;
            border-left-color: #ff6600;
            font-weight: 600;
        }
        
        .category-list a i {
            width: 25px;
            margin-right: 10px;
        }
        
        /* Product Cards */
        .product-card {
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            margin-bottom: 25px;
            border: none;
            border-radius: 12px;
            overflow: hidden;
            background: white;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            position: relative;
        }
        
        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
        }
        
        .product-img {
            height: 200px;
            width: 100%;
            object-fit: cover;
            transition: transform 0.5s cubic-bezier(0.165, 0.84, 0.44, 1);
        }
        
        .product-card:hover .product-img {
            transform: scale(1.08);
        }
        
        .product-placeholder {
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        
        .product-placeholder i {
            font-size: 4rem;
            color: #ff6600;
        }
        
        .card-body {
            padding: 1.25rem;
        }
        
        .product-title {
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #1a1a2e;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            height: 45px;
        }
        
        .product-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: #ff6600;
            margin: 10px 0;
        }
        
        .product-price i {
            font-size: 0.9rem;
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
            border-color: #ff6600;
        }
        
        .btn-add-cart {
            background: linear-gradient(135deg, #ff6600, #ff8533);
            border: none;
            transition: all 0.3s;
            color: white;
            font-weight: 600;
        }
        
        .btn-add-cart:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255,102,0,0.3);
        }
        
        .btn-view {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            transition: all 0.3s;
            color: white;
            font-weight: 600;
        }
        
        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.3);
        }
        
        /* Wishlist Heart Button */
        .btn-wishlist {
            background: transparent;
            border: none;
            transition: all 0.3s;
            padding: 5px 10px;
            border-radius: 50%;
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 10;
            background: rgba(255,255,255,0.9);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-wishlist:hover {
            transform: scale(1.1);
        }
        
        .btn-wishlist .heart-icon {
            font-size: 1.2rem;
            transition: all 0.3s;
        }
        
        .btn-wishlist .heart-icon.in-wishlist {
            color: #e74c3c;
        }
        
        .btn-wishlist .heart-icon.not-in-wishlist {
            color: #999;
        }
        
        .btn-wishlist:hover .heart-icon.not-in-wishlist {
            color: #e74c3c;
        }
        
        /* Footer */
        footer {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            margin-top: 60px;
            color: white;
        }
        
        .price-npr {
            font-weight: 700;
            color: #ff6600;
        }
        
        .price-npr i {
            font-size: 13px;
            margin-right: 2px;
        }
        
        .badge-stock {
            font-size: 0.7rem;
            padding: 3px 10px;
            border-radius: 50px;
            font-weight: 600;
        }
        
        .badge-stock.in-stock { background: #d4edda; color: #155724; }
        .badge-stock.low-stock { background: #fff3cd; color: #856404; }
        .badge-stock.out-stock { background: #f8d7da; color: #721c24; }
        
        /* Special Offer Card */
        .offer-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        }
        
        .wishlist-badge {
            position: relative;
            top: -8px;
            left: -2px;
            font-size: 0.7rem;
        }
        
        @media (max-width: 768px) {
            .product-img, .product-placeholder {
                height: 180px;
            }
            .category-filter {
                margin-bottom: 20px;
                position: relative;
            }
            .logo-img {
                height: 35px;
            }
            .brand-name {
                font-size: 1.2rem;
            }
            .brand-tagline {
                font-size: 0.5rem;
            }
            .btn-wishlist {
                width: 35px;
                height: 35px;
                top: 8px;
                right: 8px;
            }
            .btn-wishlist .heart-icon {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-daraz">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="../index.php">
                <img src="../assets/images/ss.jpg" alt="ShopVerse Logo" class="logo-img me-2">
                <div class="logo-text">
                    <div class="brand-name">Shop<span>Emart</span></div>
                    <div class="brand-tagline">Your Trusted Store</div>
                </div>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="shop.php">
                            <i class="fas fa-shopping-bag"></i> Shop
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="wishlist.php">
                            <i class="fas fa-heart"></i> Wishlist
                            <?php if($wishlist_count > 0): ?>
                                <span class="badge bg-danger rounded-pill wishlist-badge"><?php echo $wishlist_count; ?></span>
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
    
    <div class="container mt-4">
        <div class="row">
            <!-- Sidebar with categories -->
            <div class="col-md-3 mb-4">
                <div class="card category-filter">
                    <div class="category-header">
                        <h5 class="mb-0"><i class="fas fa-th-list me-2"></i> Categories</h5>
                    </div>
                    <div class="card-body p-0">
                        <ul class="category-list">
                            <li>
                                <a href="shop.php" class="<?php echo !$category ? 'active' : ''; ?>">
                                    <i class="fas fa-th-large"></i> All Products
                                </a>
                            </li>
                            <?php foreach($categories as $cat): ?>
                                <li>
                                    <a href="?category=<?php echo urlencode($cat); ?>" class="<?php echo $category == $cat ? 'active' : ''; ?>">
                                        <i class="fas fa-tag"></i> <?php echo htmlspecialchars($cat); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
                
                <!-- Special Offer Card -->
                <div class="offer-card mt-4">
                    <div class="text-center">
                        <i class="fas fa-truck fa-2x text-primary mb-2"></i>
                        <h6>Free Shipping</h6>
                        <small class="text-muted">On orders over NPR 5000</small>
                        <hr>
                        <i class="fas fa-shield-alt fa-2x text-success mb-2"></i>
                        <h6>Secure Payment</h6>
                        <small class="text-muted">eSewa & COD available</small>
                        <hr>
                        <i class="fas fa-headset fa-2x text-primary mb-2"></i>
                        <h6>24/7 Support</h6>
                        <small class="text-muted">Dedicated support team</small>
                    </div>
                </div>
            </div>
            
            <!-- Products Grid -->
            <div class="col-md-9">
                <!-- Header & Search -->
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="mb-1"><i class="fas fa-box-open text-primary"></i> Our Products</h2>
                        <?php if($category): ?>
                            <p class="text-muted mb-0">
                                <i class="fas fa-tag"></i> Showing: <strong><?php echo htmlspecialchars($category); ?></strong>
                                <a href="shop.php" class="ms-2 text-decoration-none text-primary">(Clear filter)</a>
                            </p>
                        <?php endif; ?>
                        <?php if($search): ?>
                            <p class="text-muted mb-0">
                                <i class="fas fa-search"></i> Search results for: <strong>"<?php echo htmlspecialchars($search); ?>"</strong>
                                <a href="shop.php" class="ms-2 text-decoration-none text-primary">(Clear search)</a>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="mt-2 mt-md-0">
                        <form method="GET" class="d-flex">
                            <?php if($category): ?>
                                <input type="hidden" name="category" value="<?php echo htmlspecialchars($category); ?>">
                            <?php endif; ?>
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Search products..." 
                                       value="<?php echo htmlspecialchars($search); ?>"
                                       style="border-radius: 50px 0 0 50px;">
                                <button type="submit" class="btn btn-primary" style="border-radius: 0 50px 50px 0; background: linear-gradient(135deg, #ff6600, #ff8533); border: none;">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Products Count -->
                <div class="mb-3">
                    <p class="text-muted small">
                        <i class="fas fa-box"></i> Found <strong><?php echo count($products); ?></strong> products
                    </p>
                </div>
                
                <!-- Products Grid -->
                <div class="row">
                    <?php if(count($products) > 0): ?>
                        <?php foreach($products as $product): ?>
                            <div class="col-md-4 mb-4">
                                <div class="card product-card h-100">
                                    <!-- Wishlist Heart Button -->
                                    <?php 
                                    $in_wishlist = isset($wishlist_product_ids[$product['id']]);
                                    ?>
                                    <form action="add_to_wishlist.php" method="GET" class="position-absolute" style="top: 10px; right: 10px; z-index: 10;">
                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                        <input type="hidden" name="redirect" value="shop.php">
                                        <button type="submit" class="btn-wishlist" title="<?php echo $in_wishlist ? 'Remove from Wishlist' : 'Add to Wishlist'; ?>">
                                            <i class="fas fa-heart heart-icon <?php echo $in_wishlist ? 'in-wishlist' : 'not-in-wishlist'; ?>"></i>
                                        </button>
                                    </form>
                                    
                                    <!-- Product Image -->
                                    <?php 
                                    $image_path = '';
                                    $has_image = false;
                                    
                                    if (!empty($product['image'])) {
                                        if (file_exists("../uploads/" . $product['image'])) {
                                            $image_path = "../uploads/" . $product['image'];
                                            $has_image = true;
                                        } elseif (file_exists("uploads/" . $product['image'])) {
                                            $image_path = "uploads/" . $product['image'];
                                            $has_image = true;
                                        }
                                    }
                                    ?>
                                    
                                    <?php if($has_image): ?>
                                        <a href="product_details.php?id=<?php echo $product['id']; ?>">
                                            <img src="<?php echo $image_path; ?>" class="product-img" 
                                                 alt="<?php echo htmlspecialchars($product['name']); ?>">
                                        </a>
                                    <?php else: ?>
                                        <div class="product-placeholder">
                                            <i class="fas fa-box-open"></i>
                                            <span class="d-block small mt-2">No Image</span>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="card-body text-center">
                                        <a href="product_details.php?id=<?php echo $product['id']; ?>" class="text-decoration-none text-dark">
                                            <h6 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h6>
                                        </a>
                                        
                                        <div class="product-price">
                                            <i class="fas fa-rupee-sign"></i> <?php echo number_format($product['price'], 2); ?>
                                        </div>
                                        
                                        <?php if($product['stock'] > 0): ?>
                                            <?php if($product['stock'] <= 5): ?>
                                                <span class="badge-stock low-stock">
                                                    <i class="fas fa-exclamation-triangle"></i> Only <?php echo $product['stock']; ?> left!
                                                </span>
                                            <?php else: ?>
                                                <span class="badge-stock in-stock">
                                                    <i class="fas fa-check-circle"></i> In Stock
                                                </span>
                                            <?php endif; ?>
                                            
                                            <div class="d-grid gap-2 mt-2">
                                                <div class="d-flex gap-1">
                                                    <form action="add_to_cart.php" method="GET" class="flex-grow-1 d-flex">
                                                        <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                        <input type="hidden" name="redirect" value="shop.php">
                                                        <input type="number" name="quantity" value="1" min="1" 
                                                               max="<?php echo $product['stock']; ?>" 
                                                               class="form-control quantity-input me-1"
                                                               style="width: 60px;">
                                                        <button type="submit" class="btn btn-add-cart btn-sm flex-grow-1">
                                                            <i class="fas fa-cart-plus"></i> Add
                                                        </button>
                                                    </form>
                                                </div>
                                                <a href="product_details.php?id=<?php echo $product['id']; ?>" class="btn btn-view btn-sm">
                                                    <i class="fas fa-eye"></i> View Details
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <span class="badge-stock out-stock">
                                                <i class="fas fa-times-circle"></i> Out of Stock
                                            </span>
                                            <button class="btn btn-secondary btn-sm w-100 mt-2" disabled>
                                                <i class="fas fa-ban"></i> Not Available
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info text-center py-5">
                                <i class="fas fa-search fa-4x mb-3 d-block text-muted"></i>
                                <h4>No products found</h4>
                                <p>Try adjusting your search or browse all products.</p>
                                <a href="shop.php" class="btn btn-primary mt-2">
                                    <i class="fas fa-sync-alt"></i> View All Products
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6 mx-auto text-center">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> ShopEMart - Your Trusted Online Shopping Destination</p>
                    <small class="text-muted">
                        <i class="fas fa-lock"></i> Secure Payments | 
                        <i class="fas fa-truck"></i> Fast Delivery | 
                        <i class="fas fa-headset"></i> 24/7 Support
                    </small>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto hide toast notifications
        setTimeout(() => {
            document.querySelectorAll('.toast-notification').forEach(toast => {
                toast.style.transition = 'opacity 0.5s';
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 500);
            });
        }, 5000);
        
        // Quantity input validation
        document.querySelectorAll('.quantity-input').forEach(input => {
            input.addEventListener('change', function() {
                let max = parseInt(this.getAttribute('max'));
                let value = parseInt(this.value);
                if (value > max) {
                    this.value = max;
                    alert('Only ' + max + ' items available in stock!');
                }
                if (value < 1) {
                    this.value = 1;
                }
            });
        });
    </script>
</body>
</html>