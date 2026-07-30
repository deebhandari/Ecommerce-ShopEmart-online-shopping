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

// Get orders with item count and calculate breakdown
$query = "SELECT o.*, 
          (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
          FROM orders o WHERE o.user_id = ? 
          ORDER BY o.order_date DESC";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate additional details for each order
foreach ($orders as &$order) {
    // Get order items to calculate subtotal
    $stmt = $db->prepare("SELECT oi.*, p.name as product_name, p.image, p.category 
                          FROM order_items oi 
                          JOIN products p ON oi.product_id = p.id 
                          WHERE oi.order_id = ?");
    $stmt->execute([$order['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate subtotal
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    
    $order['subtotal'] = $subtotal;
    $order['tax'] = $subtotal * 0.13; // 13% VAT
    $order['delivery_charge'] = 150; // Fixed delivery charge
    $order['grand_total'] = $subtotal + $order['tax'] + $order['delivery_charge'];
}

// Get cart count for badge
try {
    $cart_count_stmt = $db->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
    $cart_count_stmt->execute([$user_id]);
    $cart_count = $cart_count_stmt->fetchColumn() ?: 0;
} catch (PDOException $e) {
    error_log("Cart Count Error: " . $e->getMessage());
    $cart_count = 0;
}

// Handle order status messages
if (isset($_SESSION['order_success'])) {
    $success_message = $_SESSION['order_success'];
    unset($_SESSION['order_success']);
} else {
    $success_message = '';
}

if (isset($_SESSION['order_error'])) {
    $error_message = $_SESSION['order_error'];
    unset($_SESSION['order_error']);
} else {
    $error_message = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - ShopEMart</title>
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
        
        .orders-container {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            margin: 30px 0;
            animation: fadeInUp 0.5s ease;
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
        
        .orders-header {
            background: linear-gradient(135deg, #ff6600, #ff8533);
            color: white;
            padding: 25px 30px;
            border-radius: 15px;
            margin-bottom: 25px;
        }
        
        .badge-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .badge-pending { background: #f39c12; color: white; }
        .badge-processing { background: #3498db; color: white; }
        .badge-completed { background: #2ecc71; color: white; }
        .badge-cancelled { background: #e74c3c; color: white; }
        
        .badge-paid { background: #2ecc71; color: white; }
        .badge-pending-payment { background: #f39c12; color: white; }
        .badge-cod-pending { background: #fd7e14; color: white; }
        .badge-esewa-pending { background: #0B4F6C; color: white; }
        
        .payment-info {
            font-size: 11px;
            margin-top: 5px;
        }
        
        .btn-view {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 6px 15px;
            border-radius: 50px;
            font-size: 12px;
            border: none;
            transition: all 0.3s;
        }
        
        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
            color: white;
        }
        
        .table thead th {
            background: #f8f9fa;
            font-weight: 600;
            border-bottom: 2px solid #e0e0e0;
            padding: 15px;
            font-size: 0.85rem;
        }
        
        .table tbody tr {
            transition: all 0.3s;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .table tbody tr:hover {
            background: #fff8f0;
        }
        
        .order-number {
            font-weight: 600;
            color: #ff6600;
        }
        
        .price-npr {
            font-weight: bold;
            color: #ff6600;
        }
        
        .price-npr i {
            font-size: 12px;
            margin-right: 2px;
        }
        
        .price-detail {
            font-size: 11px;
            color: #6c757d;
            display: block;
            margin-top: 2px;
        }
        
        .empty-orders {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-orders i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        /* Modal Styles */
        .modal-content {
            border-radius: 16px;
            border: none;
            overflow: hidden;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #ff6600, #ff8533);
            color: white;
            border-radius: 0;
        }
        
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        
        .order-detail-item {
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .order-detail-item:last-child {
            border-bottom: none;
        }
        
        .order-summary {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
        }
        
        @media (max-width: 768px) {
            .orders-container {
                padding: 15px;
                overflow-x: auto;
            }
            .orders-header h2 {
                font-size: 1.3rem;
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
            .table {
                font-size: 0.85rem;
            }
            .badge-status {
                font-size: 10px;
                padding: 3px 8px;
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
                        <a class="nav-link" href="cart.php">
                            <i class="fas fa-shopping-cart"></i> Cart
                            <?php if($cart_count > 0): ?>
                                <span class="badge bg-danger rounded-pill ms-1"><?php echo $cart_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="orders.php">
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
        }, 5000);
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
        }, 5000);
    </script>
    <?php endif; ?>
    
    <div class="container mt-4">
        <div class="orders-container">
            <div class="orders-header">
                <div class="d-flex flex-wrap justify-content-between align-items-center">
                    <div>
                        <h2 class="mb-0"><i class="fas fa-history me-2"></i> My Orders</h2>
                        <p class="mb-0 mt-2 opacity-75">Track and manage all your orders in one place</p>
                    </div>
                    <div class="mt-2 mt-md-0">
                        <span class="badge bg-light text-dark px-3 py-2 rounded-pill">
                            <i class="fas fa-box"></i> Total Orders: <?php echo count($orders); ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <?php if(count($orders) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Order #</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Subtotal</th>
                                <th>VAT (13%)</th>
                                <th>Delivery</th>
                                <th>Total (RS)</th>
                                <th>Payment Method</th>
                                <th>Payment Status</th>
                                <th>Order Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($orders as $order): ?>
                                <tr>
                                    <td>
                                        <span class="order-number">#<?php echo htmlspecialchars(substr($order['order_number'], -10)); ?></span>
                                    </td>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($order['order_date'])); ?>
                                        <br><small class="text-muted"><?php echo date('h:i A', strtotime($order['order_date'])); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark rounded-pill">
                                            <?php echo $order['item_count']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="price-npr">
                                            <i class="fas fa-rupee-sign"></i> <?php echo number_format($order['subtotal'], 2); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="price-npr" style="color: #6c757d; font-size: 0.9rem;">
                                            <i class="fas fa-rupee-sign"></i> <?php echo number_format($order['tax'], 2); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="price-npr" style="color: #6c757d; font-size: 0.9rem;">
                                            <i class="fas fa-rupee-sign"></i> <?php echo number_format($order['delivery_charge'], 2); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="price-npr" style="font-size: 1.1rem;">
                                            <i class="fas fa-rupee-sign"></i> <?php echo number_format($order['grand_total'], 2); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if($order['payment_method'] == 'cod'): ?>
                                            <span class="badge-status badge-cod-pending">
                                                <i class="fas fa-money-bill-wave"></i> Cash on Delivery
                                            </span>
                                        <?php else: ?>
                                            <span class="badge-status badge-esewa-pending">
                                                <i class="fas fa-wallet"></i> eSewa
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($order['payment_method'] == 'cod'): ?>
                                            <?php if($order['status'] == 'completed'): ?>
                                                <span class="badge-status badge-paid">
                                                    <i class="fas fa-check-circle"></i> Paid on Delivery
                                                </span>
                                            <?php elseif($order['status'] == 'cancelled'): ?>
                                                <span class="badge-status badge-cancelled">
                                                    <i class="fas fa-times-circle"></i> Cancelled
                                                </span>
                                            <?php else: ?>
                                                <span class="badge-status badge-pending-payment">
                                                    <i class="fas fa-clock"></i> Pending (Pay on Delivery)
                                                </span>
                                                <div class="payment-info text-muted">
                                                    <small><i class="fas fa-info-circle"></i> You will pay when order arrives</small>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php if($order['payment_status'] == 'paid'): ?>
                                                <span class="badge-status badge-paid">
                                                    <i class="fas fa-check-circle"></i> Paid via eSewa
                                                </span>
                                            <?php elseif($order['status'] == 'cancelled'): ?>
                                                <span class="badge-status badge-cancelled">
                                                    <i class="fas fa-times-circle"></i> Cancelled
                                                </span>
                                            <?php else: ?>
                                                <span class="badge-status badge-pending-payment">
                                                    <i class="fas fa-clock"></i> Pending
                                                </span>
                                                <div class="payment-info text-muted">
                                                    <small><i class="fas fa-info-circle"></i> Awaiting payment confirmation</small>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge-status badge-<?php echo $order['status']; ?>">
                                            <i class="fas <?php 
                                                echo $order['status'] == 'processing' ? 'fa-spinner fa-pulse' : 
                                                    ($order['status'] == 'completed' ? 'fa-check-circle' : 
                                                    ($order['status'] == 'cancelled' ? 'fa-times-circle' : 'fa-clock')); 
                                            ?>"></i>
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                        <?php if($order['status'] == 'processing' && $order['payment_method'] == 'cod'): ?>
                                            <div class="payment-info text-success">
                                                <small><i class="fas fa-truck"></i> Order confirmed! Waiting for delivery.</small>
                                            </div>
                                        <?php endif; ?>
                                        <?php if($order['status'] == 'processing' && $order['payment_method'] == 'esewa' && $order['payment_status'] == 'paid'): ?>
                                            <div class="payment-info text-success">
                                                <small><i class="fas fa-check-circle"></i> Payment confirmed! Order is being processed.</small>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-view btn-sm" onclick="viewOrderDetails(<?php echo $order['id']; ?>)">
                                            <i class="fas fa-eye"></i> View Details
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-orders">
                    <i class="fas fa-inbox"></i>
                    <h4>No orders yet</h4>
                    <p class="text-muted">You haven't placed any orders yet. Start shopping now!</p>
                    <a href="shop.php" class="btn btn-primary rounded-pill px-4 mt-2" style="background: linear-gradient(135deg, #ff6600, #ff8533); border: none;">
                        <i class="fas fa-store"></i> Start Shopping
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Order Details Modal -->
    <div class="modal fade" id="orderModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-receipt me-2"></i> Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="orderDetailsContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-3">Loading order details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewOrderDetails(orderId) {
            // Show loading state
            document.getElementById('orderDetailsContent').innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3">Loading order details...</p>
                </div>
            `;
            
            // Fetch order details
            fetch(`order_details.php?id=${orderId}&modal=1`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('orderDetailsContent').innerHTML = data;
                    // Show modal
                    const modal = new bootstrap.Modal(document.getElementById('orderModal'));
                    modal.show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('orderDetailsContent').innerHTML = `
                        <div class="alert alert-danger text-center py-4">
                            <i class="fas fa-exclamation-circle fa-3x mb-3 d-block text-danger"></i>
                            <h5>Error Loading Order Details</h5>
                            <p class="text-muted">Unable to load order details. Please try again.</p>
                            <button class="btn btn-outline-danger btn-sm mt-2" onclick="viewOrderDetails(${orderId})">
                                <i class="fas fa-sync-alt"></i> Retry
                            </button>
                        </div>
                    `;
                    const modal = new bootstrap.Modal(document.getElementById('orderModal'));
                    modal.show();
                });
        }
        
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