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

// Handle remove from wishlist
if (isset($_GET['remove']) && isset($_GET['wishlist_id'])) {
    $wishlist_id = intval($_GET['wishlist_id']);
    $stmt = $db->prepare("DELETE FROM wishlist WHERE id = ? AND user_id = ?");
    $stmt->execute([$wishlist_id, $user_id]);
    $_SESSION['wishlist_success'] = "Item removed from wishlist successfully!";
    header("Location: wishlist.php");
    exit();
}

// Handle clear wishlist
if (isset($_GET['clear_all'])) {
    $stmt = $db->prepare("DELETE FROM wishlist WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $_SESSION['wishlist_success'] = "Wishlist cleared successfully!";
    header("Location: wishlist.php");
    exit();
}

// Get wishlist items with product information
$query = "SELECT w.*, p.name, p.price, p.stock, p.image, p.description 
          FROM wishlist w 
          JOIN products p ON w.product_id = p.id 
          WHERE w.user_id = ? 
          ORDER BY w.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$wishlist_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get cart count for badge
$cart_count_stmt = $db->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
$cart_count_stmt->execute([$user_id]);
$cart_count = $cart_count_stmt->fetchColumn() ?: 0;

// Display messages
$success_message = isset($_SESSION['wishlist_success']) ? $_SESSION['wishlist_success'] : '';
$error_message = isset($_SESSION['wishlist_error']) ? $_SESSION['wishlist_error'] : '';
unset($_SESSION['wishlist_success']);
unset($_SESSION['wishlist_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Wishlist - ShopEmart</title>
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
        
        /* Wishlist Container */
        .wishlist-container {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            margin: 30px 0;
        }
        
        .wishlist-header {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            padding: 25px 30px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .wishlist-header .heart-icon {
            font-size: 2.5rem;
            animation: pulse 1.5s ease-in-out infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        /* Wishlist Cards */
        .wishlist-card {
            transition: all 0.4s cubic-bezier(0.165, 0.84, 0.44, 1);
            margin-bottom: 20px;
            border: none;
            border-radius: 15px;
            overflow: hidden;
            background: white;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            position: relative;
        }
        
        .wishlist-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
        }
        
        .wishlist-card .product-img {
            height: 180px;
            width: 100%;
            object-fit: cover;
            transition: transform 0.5s cubic-bezier(0.165, 0.84, 0.44, 1);
        }
        
        .wishlist-card:hover .product-img {
            transform: scale(1.05);
        }
        
        .wishlist-card .product-placeholder {
            height: 180px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        
        .wishlist-card .product-placeholder i {
            font-size: 3.5rem;
            color: #e74c3c;
        }
        
        .wishlist-card .card-body {
            padding: 1.25rem;
        }
        
        .wishlist-card .product-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #1a1a2e;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            height: 48px;
        }
        
        .wishlist-card .product-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: #ff6600;
            margin: 8px 0;
        }
        
        .wishlist-card .product-price i {
            font-size: 0.9rem;
        }
        
        .wishlist-card .btn-remove {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none;
            padding: 6px 15px;
            border-radius: 50px;
            font-size: 0.75rem;
            transition: all 0.3s;
            color: white;
        }
        
        .wishlist-card .btn-remove:hover {
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(220,53,69,0.3);
            color: white;
        }
        
        .wishlist-card .btn-add-cart {
            background: linear-gradient(135deg, #ff6600, #ff8533);
            border: none;
            transition: all 0.3s;
            color: white;
            font-weight: 600;
            padding: 6px 15px;
            border-radius: 50px;
            font-size: 0.75rem;
        }
        
        .wishlist-card .btn-add-cart:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255,102,0,0.3);
            color: white;
        }
        
        .wishlist-card .btn-add-cart:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .wishlist-card .btn-view {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            transition: all 0.3s;
            color: white;
            font-weight: 600;
            padding: 6px 15px;
            border-radius: 50px;
            font-size: 0.75rem;
        }
        
        .wishlist-card .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.3);
            color: white;
        }
        
        .wishlist-card .badge-stock {
            font-size: 0.7rem;
            padding: 3px 10px;
            border-radius: 50px;
            font-weight: 600;
        }
        
        .wishlist-card .badge-stock.in-stock { background: #d4edda; color: #155724; }
        .wishlist-card .badge-stock.low-stock { background: #fff3cd; color: #856404; }
        .wishlist-card .badge-stock.out-stock { background: #f8d7da; color: #721c24; }
        
        .wishlist-card .heart-favorite {
            position: absolute;
            top: 15px;
            right: 15px;
            background: rgba(255,255,255,0.9);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            color: #e74c3c;
            font-size: 1.2rem;
        }
        
        .wishlist-card .added-date {
            font-size: 0.7rem;
            color: #999;
            margin-top: 5px;
        }
        
        .wishlist-empty {
            text-align: center;
            padding: 60px 20px;
        }
        
        .wishlist-empty i {
            font-size: 5rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        .btn-clear-all {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none;
            padding: 8px 20px;
            border-radius: 50px;
            transition: all 0.3s;
            color: white;
            font-weight: 600;
        }
        
        .btn-clear-all:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220,53,69,0.3);
            color: white;
        }
        
        /* Trust Badges */
        .trust-badges {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .wishlist-container {
                padding: 15px;
            }
            .wishlist-card .product-img,
            .wishlist-card .product-placeholder {
                height: 150px;
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
            .wishlist-header .heart-icon {
                font-size: 1.8rem;
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
                        <a class="nav-link active" href="wishlist.php">
                            <i class="fas fa-heart"></i> Wishlist
                            <?php if(count($wishlist_items) > 0): ?>
                                <span class="badge bg-danger rounded-pill ms-1"><?php echo count($wishlist_items); ?></span>
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
    
    <div class="container">
        <div class="wishlist-container">
            <div class="wishlist-header">
                <div class="d-flex flex-wrap justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-0">
                            <i class="fas fa-heart heart-icon me-2"></i> My Wishlist
                        </h2>
                        <p class="mb-0 mt-2 opacity-75">Save your favorite items and shop later</p>
                    </div>
                    <div class="text-end mt-2 mt-md-0">
                        <span class="badge bg-light text-dark px-3 py-2 rounded-pill me-2">
                            <i class="fas fa-heart text-danger"></i> <?php echo count($wishlist_items); ?> Items
                        </span>
                        <?php if(count($wishlist_items) > 0): ?>
                            <a href="wishlist.php?clear_all=1" class="btn-clear-all btn-sm" 
                               onclick="return confirm('Are you sure you want to clear your entire wishlist?')">
                                <i class="fas fa-trash-alt"></i> Clear All
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if(count($wishlist_items) > 0): ?>
                <div class="row">
                    <?php foreach($wishlist_items as $item): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card wishlist-card h-100">
                                <!-- Favorite Heart Badge -->
                                <div class="heart-favorite">
                                    <i class="fas fa-heart"></i>
                                </div>
                                
                                <!-- Product Image -->
                                <?php 
                                $image_path = '';
                                $has_image = false;
                                
                                if (!empty($item['image'])) {
                                    if (file_exists("../uploads/" . $item['image'])) {
                                        $image_path = "../uploads/" . $item['image'];
                                        $has_image = true;
                                    } elseif (file_exists("uploads/" . $item['image'])) {
                                        $image_path = "uploads/" . $item['image'];
                                        $has_image = true;
                                    }
                                }
                                ?>
                                
                                <?php if($has_image): ?>
                                    <a href="product_details.php?id=<?php echo $item['product_id']; ?>">
                                        <img src="<?php echo $image_path; ?>" class="product-img" 
                                             alt="<?php echo htmlspecialchars($item['name']); ?>">
                                    </a>
                                <?php else: ?>
                                    <div class="product-placeholder">
                                        <i class="fas fa-box-open"></i>
                                        <span class="d-block small mt-2">No Image</span>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="card-body d-flex flex-column">
                                    <a href="product_details.php?id=<?php echo $item['product_id']; ?>" 
                                       class="text-decoration-none text-dark">
                                        <h6 class="product-title"><?php echo htmlspecialchars($item['name']); ?></h6>
                                    </a>
                                    
                                    <div class="product-price">
                                        <i class="fas fa-rupee-sign"></i> <?php echo number_format($item['price'], 2); ?>
                                    </div>
                                    
                                    <!-- Stock Status -->
                                    <?php if($item['stock'] > 0): ?>
                                        <?php if($item['stock'] <= 5): ?>
                                            <span class="badge-stock low-stock">
                                                <i class="fas fa-exclamation-triangle"></i> Only <?php echo $item['stock']; ?> left!
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-stock in-stock">
                                                <i class="fas fa-check-circle"></i> In Stock
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge-stock out-stock">
                                            <i class="fas fa-times-circle"></i> Out of Stock
                                        </span>
                                    <?php endif; ?>
                                    
                                    <!-- Added Date -->
                                    <div class="added-date">
                                        <i class="far fa-clock"></i> Added: <?php echo date('M d, Y', strtotime($item['created_at'])); ?>
                                    </div>
                                    
                                    <!-- Action Buttons -->
                                    <div class="mt-3 d-flex gap-2 flex-wrap">
                                        <a href="product_details.php?id=<?php echo $item['product_id']; ?>" 
                                           class="btn btn-view btn-sm flex-grow-1">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        
                                        <?php if($item['stock'] > 0): ?>
                                            <form action="add_to_cart.php" method="GET" class="flex-grow-1">
                                                <input type="hidden" name="product_id" value="<?php echo $item['product_id']; ?>">
                                                <input type="hidden" name="quantity" value="1">
                                                <input type="hidden" name="redirect" value="wishlist.php">
                                                <button type="submit" class="btn btn-add-cart btn-sm w-100">
                                                    <i class="fas fa-cart-plus"></i> Add to Cart
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn btn-add-cart btn-sm w-100" disabled>
                                                <i class="fas fa-ban"></i> Out of Stock
                                            </button>
                                        <?php endif; ?>
                                        
                                        <a href="wishlist.php?remove=1&wishlist_id=<?php echo $item['id']; ?>" 
                                           class="btn btn-remove btn-sm"
                                           onclick="return confirm('Remove this item from your wishlist?')">
                                            <i class="fas fa-trash-alt"></i> Remove
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Trust Badges -->
                <div class="trust-badges text-center">
                    <small class="text-muted">
                        <i class="fas fa-heart text-danger me-1"></i> Save favorites &nbsp;|&nbsp;
                        <i class="fas fa-bell me-1 text-warning"></i> Price drop alerts &nbsp;|&nbsp;
                        <i class="fas fa-shopping-cart me-1 text-success"></i> Quick add to cart &nbsp;|&nbsp;
                        <i class="fas fa-share-alt me-1 text-primary"></i> Share with friends
                    </small>
                </div>
            <?php else: ?>
                <div class="wishlist-empty">
                    <i class="fas fa-heart"></i>
                    <h4 class="mt-3">Your wishlist is empty</h4>
                    <p class="text-muted">Start adding your favorite products to your wishlist!</p>
                    <a href="shop.php" class="btn btn-primary rounded-pill px-4 mt-2" 
                       style="background: linear-gradient(135deg, #e74c3c, #c0392b); border: none;">
                        <i class="fas fa-store"></i> Start Shopping
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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