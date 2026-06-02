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
    <title>Shop - ShopVerse</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Navigation */
        .navbar {
            background: linear-gradient(135deg, #2c3e50, #1a252f);
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        /* Product Cards */
        .product-card {
            transition: all 0.3s ease;
            margin-bottom: 25px;
            border: none;
            border-radius: 15px;
            overflow: hidden;
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .product-img {
            height: 200px;
            width: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }
        
        .product-card:hover .product-img {
            transform: scale(1.05);
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
            color: #667eea;
        }
        
        .card-body {
            padding: 1.25rem;
        }
        
        .product-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .product-price {
            font-size: 1.3rem;
            font-weight: bold;
            color: #667eea;
            margin: 10px 0;
        }
        
        /* Category Sidebar */
        .category-filter {
            position: sticky;
            top: 20px;
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .category-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
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
            padding: 12px 15px;
            display: block;
            transition: all 0.3s;
            color: #555;
            border-left: 3px solid transparent;
        }
        
        .category-list a:hover {
            background: #f0f0ff;
            color: #667eea;
            border-left-color: #667eea;
            padding-left: 20px;
        }
        
        .category-list a.active {
            background: linear-gradient(90deg, rgba(102,126,234,0.1) 0%, rgba(102,126,234,0) 100%);
            color: #667eea;
            border-left-color: #667eea;
            font-weight: 600;
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
        
        /* Quantity Input */
        .quantity-input {
            width: 60px;
            text-align: center;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 5px;
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
        
        /* Footer */
        footer {
            background: linear-gradient(135deg, #2c3e50, #1a252f);
            margin-top: 60px;
        }
        
        @media (max-width: 768px) {
            .product-img, .product-placeholder {
                height: 180px;
            }
            .category-filter {
                margin-bottom: 20px;
                position: relative;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
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
    
    <div class="container mt-4">
        <div class="row">
            <!-- Sidebar with categories -->
            <div class="col-md-3 mb-4">
                <div class="card category-filter">
                    <div class="category-header">
                        <h5 class="mb-0"><i class="fas fa-filter me-2"></i> Categories</h5>
                    </div>
                    <div class="card-body p-0">
                        <ul class="category-list">
                            <li>
                                <a href="shop.php" class="<?php echo !$category ? 'active' : ''; ?>">
                                    <i class="fas fa-th-large me-2"></i> All Products
                                </a>
                            </li>
                            <li>
                                <a href="?category=Electronics" class="<?php echo $category == 'Electronics' ? 'active' : ''; ?>">
                                    <i class="fas fa-mobile-alt me-2"></i> Electronics
                                </a>
                            </li>
                            <li>
                                <a href="?category=Fashion" class="<?php echo $category == 'Fashion' ? 'active' : ''; ?>">
                                    <i class="fas fa-tshirt me-2"></i> Fashion
                                </a>
                            </li>
                            <li>
                                <a href="?category=Sports" class="<?php echo $category == 'Sports' ? 'active' : ''; ?>">
                                    <i class="fas fa-futbol me-2"></i> Sports
                                </a>
                            </li>
                            <li>
                                <a href="?category=Books" class="<?php echo $category == 'Books' ? 'active' : ''; ?>">
                                    <i class="fas fa-book me-2"></i> Books
                                </a>
                            </li>
                            <li>
                                <a href="?category=Home" class="<?php echo $category == 'Home' ? 'active' : ''; ?>">
                                    <i class="fas fa-home me-2"></i> Home & Living
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- Special Offer Card -->
                <div class="card mt-4 border-0 shadow-sm">
                    <div class="card-body text-center">
                        <i class="fas fa-tag fa-2x text-primary mb-2"></i>
                        <h6>Free Shipping</h6>
                        <small class="text-muted">On orders over $50</small>
                        <hr>
                        <i class="fas fa-shield-alt fa-2x text-success mb-2"></i>
                        <h6>Secure Payment</h6>
                        <small class="text-muted">100% secure transactions</small>
                    </div>
                </div>
            </div>
            
            <!-- Products Grid -->
            <div class="col-md-9">
                <!-- Header & Search -->
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="mb-1">Our Products</h2>
                        <?php if($category): ?>
                            <p class="text-muted mb-0">
                                <i class="fas fa-tag"></i> Showing: <strong><?php echo htmlspecialchars($category); ?></strong>
                                <a href="shop.php" class="ms-2 text-decoration-none">(Clear filter)</a>
                            </p>
                        <?php endif; ?>
                        <?php if($search): ?>
                            <p class="text-muted mb-0">
                                <i class="fas fa-search"></i> Search results for: <strong>"<?php echo htmlspecialchars($search); ?>"</strong>
                                <a href="shop.php" class="ms-2 text-decoration-none">(Clear search)</a>
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
                                <button type="submit" class="btn btn-primary" style="border-radius: 0 50px 50px 0;">
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
                                    <!-- Product Image -->
                                    <?php 
                                    $image_path = '';
                                    $has_image = false;
                                    
                                    if (!empty($product['image'])) {
                                        $full_path = "../uploads/" . $product['image'];
                                        if (file_exists($full_path)) {
                                            $image_path = $full_path;
                                            $has_image = true;
                                        }
                                    }
                                    ?>
                                    
                                    <?php if($has_image): ?>
                                        <img src="<?php echo $image_path; ?>" class="product-img" 
                                             alt="<?php echo htmlspecialchars($product['name']); ?>">
                                    <?php else: ?>
                                        <div class="product-placeholder">
                                            <i class="fas fa-box-open"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="card-body text-center">
                                        <h6 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h6>
                                        <p class="small text-muted">
                                            <?php echo substr(htmlspecialchars($product['description']), 0, 50); ?>
                                            <?php echo strlen($product['description']) > 50 ? '...' : ''; ?>
                                        </p>
                                        <div class="product-price">
                                            $<?php echo number_format($product['price'], 2); ?>
                                        </div>
                                        
                                        <?php if($product['stock'] > 0): ?>
                                            <?php if($product['stock'] <= 5): ?>
                                                <span class="badge bg-warning text-dark mb-2">
                                                    <i class="fas fa-exclamation-triangle"></i> Only <?php echo $product['stock']; ?> left!
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success mb-2">
                                                    <i class="fas fa-check-circle"></i> In Stock
                                                </span>
                                            <?php endif; ?>
                                            
                                            <form action="add_to_cart.php" method="GET" class="d-grid gap-2">
                                                <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                                <input type="hidden" name="redirect" value="shop.php">
                                                <div class="input-group">
                                                    <input type="number" name="quantity" value="1" min="1" 
                                                           max="<?php echo $product['stock']; ?>" 
                                                           class="form-control quantity-input text-center"
                                                           style="max-width: 70px;">
                                                    <button type="submit" class="btn btn-success btn-add-cart flex-grow-1">
                                                        <i class="fas fa-cart-plus"></i> Add to Cart
                                                    </button>
                                                </div>
                                            </form>
                                        <?php else: ?>
                                            <span class="badge bg-danger mb-2">
                                                <i class="fas fa-times-circle"></i> Out of Stock
                                            </span>
                                            <button class="btn btn-secondary btn-sm w-100" disabled>
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
    <footer class="text-white text-center py-4">
        <div class="container">
            <div class="row">
                <div class="col-md-6 mx-auto">
                    <p class="mb-0">&copy; <?php echo date('Y'); ?> ShopVerse - Your Trusted Online Shopping Destination</p>
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
                toast.remove();
            });
        }, 3000);
        
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