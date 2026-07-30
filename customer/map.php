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

// Get user info
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get all orders with details
try {
    $stmt = $db->prepare("SELECT o.*, 
                          (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count,
                          (SELECT SUM(quantity) FROM order_items WHERE order_id = o.id) as total_items
                          FROM orders o 
                          WHERE o.user_id = ? 
                          ORDER BY o.order_date DESC");
    $stmt->execute([$user_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate breakdown for each order
    foreach ($orders as &$order) {
        $stmt_items = $db->prepare("SELECT oi.*, p.name as product_name 
                                   FROM order_items oi 
                                   JOIN products p ON oi.product_id = p.id 
                                   WHERE oi.order_id = ?");
        $stmt_items->execute([$order['id']]);
        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }
        $order['subtotal'] = $subtotal;
        $order['tax'] = $subtotal * 0.13;
        $order['delivery_charge'] = 150;
        $order['grand_total'] = $subtotal + $order['tax'] + $order['delivery_charge'];
        
        // Get tracking history
        try {
            $stmt_track = $db->prepare("SELECT * FROM tracking_history WHERE order_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt_track->execute([$order['id']]);
            $track = $stmt_track->fetch(PDO::FETCH_ASSOC);
            if ($track) {
                $order['track_status'] = $track['status'];
                $order['track_location'] = $track['location'] ?? '';
                $order['track_notes'] = $track['notes'] ?? '';
                $order['track_time'] = date('h:i A', strtotime($track['created_at']));
            }
        } catch (Exception $e) {
            // Tracking table might not exist
        }
    }
    
} catch (PDOException $e) {
    error_log("Map Error: " . $e->getMessage());
    $orders = [];
}

// Get cart count for badge
try {
    $cart_count_stmt = $db->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
    $cart_count_stmt->execute([$user_id]);
    $cart_count = $cart_count_stmt->fetchColumn() ?: 0;
} catch (PDOException $e) {
    $cart_count = 0;
}

// Get wishlist count
try {
    $wishlist_stmt = $db->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
    $wishlist_stmt->execute([$user_id]);
    $wishlist_count = $wishlist_stmt->fetchColumn() ?: 0;
} catch (PDOException $e) {
    $wishlist_count = 0;
}

// Store location (default: Kathmandu)
$store_lat = 27.7172;
$store_lng = 85.3240;
$store_address = "Kathmandu, Nepal";
$store_name = "ShopEMart Store";

// Generate order locations based on order ID and status
function generateOrderLocation($order_id, $status, $base_lat, $base_lng) {
    // Create variation based on order ID
    $variation = ($order_id * 7 + 13) % 1000 / 10000;
    $lat = $base_lat + (($order_id % 100) / 5000) + $variation;
    $lng = $base_lng + (($order_id % 50) / 5000) + $variation * 0.5;
    
    // If order is completed, delivery location is final
    if ($status == 'completed') {
        $lat = $base_lat + 0.05 + (($order_id % 100) / 5000);
        $lng = $base_lng + 0.05 + (($order_id % 50) / 5000);
    }
    // If order is cancelled, location is near store
    elseif ($status == 'cancelled') {
        $lat = $base_lat + (($order_id % 20) / 10000);
        $lng = $base_lng + (($order_id % 15) / 10000);
    }
    
    return ['lat' => $lat, 'lng' => $lng];
}

// Prepare orders data for map
$orders_data = [];
foreach ($orders as $order) {
    $delivery_address = !empty($order['shipping_address']) ? $order['shipping_address'] : $user['address'];
    
    // Generate coordinates for delivery
    $coords = generateOrderLocation($order['id'], $order['status'], $store_lat, $store_lng);
    
    // Determine status color
    $status_color = '#f39c12'; // pending
    if ($order['status'] == 'processing') $status_color = '#3498db';
    elseif ($order['status'] == 'shipped') $status_color = '#2ecc71';
    elseif ($order['status'] == 'completed') $status_color = '#27ae60';
    elseif ($order['status'] == 'cancelled') $status_color = '#e74c3c';
    
    // Calculate progress percentage
    $progress = 0;
    if ($order['status'] == 'completed') $progress = 100;
    elseif ($order['status'] == 'cancelled') $progress = 0;
    elseif ($order['status'] == 'processing') $progress = 50;
    elseif ($order['status'] == 'shipped') $progress = 75;
    elseif ($order['status'] == 'pending') $progress = 25;
    
    $orders_data[] = [
        'id' => $order['id'],
        'order_number' => $order['order_number'],
        'status' => $order['status'],
        'status_color' => $status_color,
        'total' => $order['grand_total'] ?? $order['total_amount'],
        'subtotal' => $order['subtotal'] ?? 0,
        'tax' => $order['tax'] ?? 0,
        'delivery_charge' => $order['delivery_charge'] ?? 150,
        'address' => $delivery_address,
        'date' => date('M d, Y', strtotime($order['order_date'])),
        'time' => date('h:i A', strtotime($order['order_date'])),
        'items' => $order['item_count'] ?? 0,
        'total_items' => $order['total_items'] ?? 0,
        'lat' => $coords['lat'],
        'lng' => $coords['lng'],
        'progress' => $progress,
        'track_status' => $order['track_status'] ?? '',
        'track_location' => $order['track_location'] ?? '',
        'track_notes' => $order['track_notes'] ?? '',
        'track_time' => $order['track_time'] ?? ''
    ];
}

// Get statistics
$total_orders = count($orders);
$active_orders = array_filter($orders, function($o) {
    return $o['status'] != 'completed' && $o['status'] != 'cancelled';
});
$completed_orders = array_filter($orders, function($o) {
    return $o['status'] == 'completed';
});
$cancelled_orders = array_filter($orders, function($o) {
    return $o['status'] == 'cancelled';
});
$in_transit = array_filter($orders, function($o) {
    return $o['status'] == 'processing' || $o['status'] == 'shipped';
});

// Get total spent
try {
    $stmt = $db->prepare("SELECT SUM(total_amount) FROM orders WHERE user_id = ? AND payment_status = 'paid'");
    $stmt->execute([$user_id]);
    $total_spent = $stmt->fetchColumn() ?: 0;
} catch (PDOException $e) {
    $total_spent = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Map - ShopEMart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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
        
        /* Main Container */
        .map-container-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            padding: 15px;
        }
        
        /* Stats Cards */
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            transition: all 0.3s;
            height: 100%;
            text-align: center;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 25px rgba(0,0,0,0.12);
        }
        
        .stat-card .stat-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .stat-card .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #333;
        }
        
        .stat-card .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        /* Map Card */
        .map-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 25px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .map-card .map-header {
            padding: 20px 25px;
            background: linear-gradient(135deg, #ff6600, #ff8533);
            color: white;
        }
        
        .map-card .map-header h5 {
            margin: 0;
            font-weight: 600;
        }
        
        #map {
            height: 550px;
            width: 100%;
            z-index: 1;
        }
        
        .map-controls {
            padding: 15px 20px;
            background: #f8f9fa;
            border-top: 1px solid #e0e0e0;
        }
        
        /* Orders List */
        .orders-list-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }
        
        .orders-list-card .list-header {
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }
        
        .order-item {
            padding: 15px;
            border-radius: 10px;
            border: 1px solid #f0f0f0;
            margin-bottom: 10px;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .order-item:hover {
            border-color: #ff6600;
            background: #fff8f0;
            transform: translateX(5px);
        }
        
        .order-item .order-number {
            font-weight: 600;
            color: #ff6600;
        }
        
        .order-item .order-status {
            font-size: 12px;
            padding: 3px 10px;
            border-radius: 50px;
            font-weight: 500;
        }
        
        .order-item .order-address {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .order-item .order-total {
            font-weight: 600;
            color: #333;
        }
        
        .badge-status {
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-pending { background: #f39c12; color: white; }
        .badge-processing { background: #3498db; color: white; }
        .badge-shipped { background: #2ecc71; color: white; }
        .badge-completed { background: #27ae60; color: white; }
        .badge-cancelled { background: #e74c3c; color: white; }
        
        /* Map Legend */
        .map-legend {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            padding: 10px 0;
        }
        
        .map-legend .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.85rem;
        }
        
        .map-legend .legend-item .dot {
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .map-legend .legend-item .dot.store {
            background: #ff6600;
        }
        
        .map-legend .legend-item .dot.pending {
            background: #f39c12;
        }
        
        .map-legend .legend-item .dot.processing {
            background: #3498db;
        }
        
        .map-legend .legend-item .dot.shipped {
            background: #2ecc71;
        }
        
        .map-legend .legend-item .dot.completed {
            background: #27ae60;
        }
        
        .map-legend .legend-item .dot.cancelled {
            background: #e74c3c;
        }
        
        /* Modal styles */
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
        
        /* Responsive */
        @media (max-width: 768px) {
            .brand-name {
                font-size: 1.2rem;
            }
            .logo-img {
                height: 35px;
            }
            #map {
                height: 350px;
            }
            .stat-card .stat-number {
                font-size: 1.5rem;
            }
            .map-legend {
                gap: 10px;
            }
            .order-item {
                padding: 10px;
            }
        }
        
        @media (max-width: 576px) {
            #map {
                height: 280px;
            }
            .map-card .map-header {
                padding: 15px;
            }
            .map-controls {
                padding: 10px 15px;
            }
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
        
        /* Custom marker style for leaflet */
        .custom-div-icon {
            background: transparent;
            border: none;
        }
        
        .custom-div-icon i {
            display: flex;
            align-items: center;
            justify-content: center;
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
                        <a class="nav-link" href="wishlist.php">
                            <i class="fas fa-heart text-danger"></i> Wishlist
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
                    <li class="nav-item">
                        <a class="nav-link" href="orders.php">
                            <i class="fas fa-history"></i> Orders
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="map.php">
                            <i class="fas fa-map-marked-alt"></i> Map
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
    
    <div class="map-container-wrapper">
        <!-- Statistics -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-icon text-primary"><i class="fas fa-box"></i></div>
                    <div class="stat-number"><?php echo $total_orders; ?></div>
                    <div class="stat-label">Total Orders</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-icon text-warning"><i class="fas fa-clock"></i></div>
                    <div class="stat-number"><?php echo count($active_orders); ?></div>
                    <div class="stat-label">Active Orders</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-icon text-success"><i class="fas fa-check-circle"></i></div>
                    <div class="stat-number"><?php echo count($completed_orders); ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="stat-card">
                    <div class="stat-icon text-danger"><i class="fas fa-rupee-sign"></i></div>
                    <div class="stat-number">₹<?php echo number_format($total_spent, 0); ?></div>
                    <div class="stat-label">Total Spent</div>
                </div>
            </div>
        </div>
        
        <!-- Map Section -->
        <div class="map-card">
            <div class="map-header d-flex justify-content-between align-items-center flex-wrap">
                <h5><i class="fas fa-map-marked-alt me-2"></i> Order Tracking Map</h5>
                <div>
                    <span class="badge bg-light text-dark me-2">
                        <i class="fas fa-store text-primary"></i> <?php echo $store_name; ?>
                    </span>
                    <?php if(count($active_orders) > 0): ?>
                        <span class="badge bg-success">
                            <i class="fas fa-shipping-fast"></i> <?php echo count($active_orders); ?> active
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div id="map"></div>
            <div class="map-controls">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div class="map-legend">
                        <span class="legend-item">
                            <span class="dot store"></span> Store
                        </span>
                        <span class="legend-item">
                            <span class="dot pending"></span> Pending
                        </span>
                        <span class="legend-item">
                            <span class="dot processing"></span> Processing
                        </span>
                        <span class="legend-item">
                            <span class="dot shipped"></span> Shipped
                        </span>
                        <span class="legend-item">
                            <span class="dot completed"></span> Completed
                        </span>
                        <span class="legend-item">
                            <span class="dot cancelled"></span> Cancelled
                        </span>
                    </div>
                    <div class="no-print">
                        <button class="btn btn-sm btn-outline-primary" onclick="centerMap()">
                            <i class="fas fa-crosshairs"></i> Center
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" onclick="refreshMap()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Orders List -->
        <div class="orders-list-card">
            <div class="list-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-list-ul text-primary me-2"></i> All Orders</h5>
                <span class="badge bg-primary rounded-pill"><?php echo $total_orders; ?> orders</span>
            </div>
            
            <?php if($total_orders > 0): ?>
                <?php foreach($orders_data as $order): ?>
                    <div class="order-item" onclick="focusOrder(<?php echo $order['id']; ?>, <?php echo $order['lat']; ?>, <?php echo $order['lng']; ?>)">
                        <div class="row align-items-center">
                            <div class="col-md-3">
                                <div class="order-number">#<?php echo htmlspecialchars($order['order_number']); ?></div>
                                <div class="order-address">
                                    <i class="fas fa-calendar-alt me-1"></i> 
                                    <?php echo $order['date']; ?>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <span class="badge-status badge-<?php echo $order['status']; ?>">
                                    <i class="fas <?php 
                                        echo $order['status'] == 'processing' ? 'fa-spinner fa-pulse' : 
                                            ($order['status'] == 'completed' ? 'fa-check-circle' : 
                                            ($order['status'] == 'cancelled' ? 'fa-times-circle' : 'fa-clock')); 
                                    ?>"></i>
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </div>
                            <div class="col-md-3">
                                <div class="order-address">
                                    <i class="fas fa-map-marker-alt text-danger me-1"></i>
                                    <?php echo htmlspecialchars(substr($order['address'], 0, 35)) . (strlen($order['address']) > 35 ? '...' : ''); ?>
                                </div>
                            </div>
                            <div class="col-md-2 text-end">
                                <div class="order-total">
                                    ₹<?php echo number_format($order['total'], 2); ?>
                                </div>
                                <small class="text-muted"><?php echo $order['total_items']; ?> items</small>
                            </div>
                            <div class="col-md-2 text-end">
                                <a href="track_order.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-truck"></i> Track
                                </a>
                                <button class="btn btn-sm btn-outline-secondary" onclick="event.stopPropagation(); showOrderDetails(<?php echo $order['id']; ?>)">
                                    <i class="fas fa-info-circle"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-box-open text-muted" style="font-size: 3rem;"></i>
                    <h5 class="mt-3">No Orders Yet</h5>
                    <p class="text-muted">You haven't placed any orders yet. Start shopping now!</p>
                    <a href="shop.php" class="btn btn-primary rounded-pill">
                        <i class="fas fa-shopping-bag"></i> Start Shopping
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Order Details Modal -->
    <div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-receipt me-2"></i> Order Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
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
    
    <script>
        // Map variables
        let map;
        let markers = [];
        let storeMarker;
        let orderMarkers = [];
        let popupContent = '';
        const storeLat = <?php echo $store_lat; ?>;
        const storeLng = <?php echo $store_lng; ?>;
        const storeAddress = '<?php echo addslashes($store_address); ?>';
        const storeName = '<?php echo addslashes($store_name); ?>';
        
        // Orders data
        const ordersData = <?php echo json_encode($orders_data); ?>;
        
        // Initialize map
        function initMap() {
            // Create map centered on store
            map = L.map('map').setView([storeLat, storeLng], 12);
            
            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);
            
            // Add store marker
            addStoreMarker();
            
            // Add order markers
            addOrderMarkers();
            
            // Fit bounds to show all markers
            fitMapBounds();
        }
        
        // Add store marker
        function addStoreMarker() {
            const storeIcon = L.divIcon({
                html: '<i class="fas fa-store" style="font-size: 28px; color: #ff6600; background: white; padding: 8px; border-radius: 50%; box-shadow: 0 2px 10px rgba(0,0,0,0.3); border: 2px solid #ff6600;"></i>',
                className: 'custom-div-icon',
                iconSize: [44, 44],
                iconAnchor: [22, 22]
            });
            
            storeMarker = L.marker([storeLat, storeLng], {
                icon: storeIcon
            }).addTo(map);
            
            storeMarker.bindPopup(`
                <div style="text-align: center; min-width: 150px;">
                    <h6 class="mb-1"><i class="fas fa-store text-primary"></i> ${storeName}</h6>
                    <p class="mb-0 small">${storeAddress}</p>
                    <small class="text-muted">📍 Pickup Location</small>
                </div>
            `);
        }
        
        // Add order markers
        function addOrderMarkers() {
            ordersData.forEach(order => {
                // Determine icon color based on status
                let iconColor = order.status_color;
                let iconHtml = '';
                
                if (order.status === 'completed') {
                    iconHtml = `<i class="fas fa-check-circle" style="font-size: 20px; color: white; background: ${iconColor}; padding: 8px; border-radius: 50%; box-shadow: 0 2px 10px rgba(0,0,0,0.3); border: 2px solid white;"></i>`;
                } else if (order.status === 'cancelled') {
                    iconHtml = `<i class="fas fa-times-circle" style="font-size: 20px; color: white; background: ${iconColor}; padding: 8px; border-radius: 50%; box-shadow: 0 2px 10px rgba(0,0,0,0.3); border: 2px solid white;"></i>`;
                } else if (order.status === 'processing' || order.status === 'shipped') {
                    iconHtml = `<i class="fas fa-truck" style="font-size: 20px; color: white; background: ${iconColor}; padding: 8px; border-radius: 50%; box-shadow: 0 2px 10px rgba(0,0,0,0.3); border: 2px solid white; animation: pulse 1.5s infinite;"></i>`;
                } else {
                    iconHtml = `<i class="fas fa-box" style="font-size: 20px; color: white; background: ${iconColor}; padding: 8px; border-radius: 50%; box-shadow: 0 2px 10px rgba(0,0,0,0.3); border: 2px solid white;"></i>`;
                }
                
                const orderIcon = L.divIcon({
                    html: iconHtml,
                    className: 'custom-div-icon',
                    iconSize: [36, 36],
                    iconAnchor: [18, 18]
                });
                
                const marker = L.marker([order.lat, order.lng], {
                    icon: orderIcon
                }).addTo(map);
                
                // Create popup content
                const popupContent = `
                    <div style="min-width: 220px;">
                        <h6 class="mb-1">Order #${order.order_number}</h6>
                        <p class="mb-0 small">
                            <span class="badge bg-${order.status === 'pending' ? 'warning' : (order.status === 'processing' ? 'info' : (order.status === 'shipped' ? 'success' : (order.status === 'completed' ? 'success' : 'danger')))}">
                                ${order.status.charAt(0).toUpperCase() + order.status.slice(1)}
                            </span>
                        </p>
                        <p class="mb-0 small"><i class="fas fa-rupee-sign"></i> Total: ₹${order.total.toFixed(2)}</p>
                        <p class="mb-0 small"><i class="fas fa-box"></i> ${order.total_items} items</p>
                        <p class="mb-0 small"><i class="fas fa-map-marker-alt"></i> ${order.address.substring(0, 40)}${order.address.length > 40 ? '...' : ''}</p>
                        ${order.track_status ? `<p class="mb-0 small text-muted"><i class="fas fa-info-circle"></i> ${order.track_status}</p>` : ''}
                        <div class="mt-2">
                            <a href="track_order.php?id=${order.id}" class="btn btn-primary btn-sm">Track Order</a>
                            <button class="btn btn-outline-secondary btn-sm" onclick="showOrderDetails(${order.id})">Details</button>
                        </div>
                    </div>
                `;
                
                marker.bindPopup(popupContent);
                
                // Store marker reference
                orderMarkers.push({
                    marker: marker,
                    orderId: order.id,
                    lat: order.lat,
                    lng: order.lng,
                    status: order.status
                });
            });
        }
        
        // Fit map bounds to show all markers
        function fitMapBounds() {
            const allMarkers = [];
            
            // Add store location
            allMarkers.push([storeLat, storeLng]);
            
            // Add order marker locations
            orderMarkers.forEach(order => {
                allMarkers.push([order.lat, order.lng]);
            });
            
            if (allMarkers.length > 0) {
                const bounds = L.latLngBounds(allMarkers);
                map.fitBounds(bounds, {
                    padding: [50, 50],
                    maxZoom: 13
                });
            }
        }
        
        // Center map on store
        function centerMap() {
            map.setView([storeLat, storeLng], 13);
            if (storeMarker) {
                storeMarker.openPopup();
            }
        }
        
        // Refresh map
        function refreshMap() {
            location.reload();
        }
        
        // Focus on specific order
        function focusOrder(orderId, lat, lng) {
            map.setView([lat, lng], 14);
            
            // Find and open the marker popup
            const markerData = orderMarkers.find(m => m.orderId === orderId);
            if (markerData) {
                markerData.marker.openPopup();
            }
        }
        
        // Show order details in modal
        function showOrderDetails(orderId) {
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
                    const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
                    modal.show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('orderDetailsContent').innerHTML = `
                        <div class="alert alert-danger text-center py-4">
                            <i class="fas fa-exclamation-circle fa-3x mb-3 d-block text-danger"></i>
                            <h5>Error Loading Order Details</h5>
                            <p class="text-muted">Unable to load order details. Please try again.</p>
                            <button class="btn btn-outline-danger btn-sm mt-2" onclick="showOrderDetails(${orderId})">
                                <i class="fas fa-sync-alt"></i> Retry
                            </button>
                        </div>
                    `;
                    const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
                    modal.show();
                });
        }
        
        // Initialize map when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initMap();
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            if (map) {
                setTimeout(() => {
                    map.invalidateSize();
                }, 300);
            }
        });
        
        // Auto-refresh map every 60 seconds if there are active orders
        <?php if(count($active_orders) > 0): ?>
        setInterval(function() {
            if (!document.hidden) {
                fetch(window.location.href)
                    .then(response => response.text())
                    .then(html => {
                        // Update orders list without full page reload
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        const newOrdersList = doc.querySelector('.orders-list-card');
                        if (newOrdersList) {
                            document.querySelector('.orders-list-card').innerHTML = newOrdersList.innerHTML;
                        }
                    })
                    .catch(error => console.error('Error refreshing orders:', error));
            }
        }, 60000);
        <?php endif; ?>
        
        // Add pulse animation for active markers
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse {
                0% { transform: scale(1); }
                50% { transform: scale(1.1); }
                100% { transform: scale(1); }
            }
        `;
        document.head.appendChild(style);
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>