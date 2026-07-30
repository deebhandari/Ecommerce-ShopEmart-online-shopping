<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'customer') {
    header("Location: ../login.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

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

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$tracking_id = isset($_GET['tracking_id']) ? $_GET['tracking_id'] : '';

if ($order_id <= 0 && empty($tracking_id)) {
    header("Location: orders.php");
    exit();
}

// Get order details
try {
    if ($order_id > 0) {
        $stmt = $db->prepare("SELECT o.*, u.name as customer_name, u.email, u.phone, u.address 
                              FROM orders o 
                              JOIN users u ON o.user_id = u.id 
                              WHERE o.id = ? AND o.user_id = ?");
        $stmt->execute([$order_id, $user_id]);
    } else {
        $stmt = $db->prepare("SELECT o.*, u.name as customer_name, u.email, u.phone, u.address 
                              FROM orders o 
                              JOIN users u ON o.user_id = u.id 
                              WHERE o.tracking_id = ? AND o.user_id = ?");
        $stmt->execute([$tracking_id, $user_id]);
    }
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        $_SESSION['order_error'] = 'Order not found or you do not have permission to track this order.';
        header("Location: orders.php");
        exit();
    }
    
    // Get order items to calculate breakdown
    $stmt = $db->prepare("SELECT oi.*, p.name as product_name 
                          FROM order_items oi 
                          JOIN products p ON oi.product_id = p.id 
                          WHERE oi.order_id = ?");
    $stmt->execute([$order['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    $tax_rate = 0.13; // 13% VAT
    $tax = $subtotal * $tax_rate;
    $delivery_charge = 150; // Fixed delivery charge
    $grand_total = $subtotal + $tax + $delivery_charge;
    
    // Get order items count
    $stmt = $db->prepare("SELECT COUNT(*) as item_count, SUM(quantity) as total_items 
                          FROM order_items WHERE order_id = ?");
    $stmt->execute([$order['id']]);
    $items_count = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get tracking location if available
    $tracking_location = null;
    $tracking_status = null;
    try {
        $stmt = $db->prepare("SELECT * FROM tracking_history WHERE order_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$order['id']]);
        $track = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($track) {
            $tracking_status = $track['status'];
            $tracking_location = $track['location'] ?? null;
        }
    } catch (Exception $e) {
        // Tracking table might not exist
    }
    
} catch (Exception $e) {
    $_SESSION['order_error'] = 'Error loading order details. Please try again.';
    header("Location: orders.php");
    exit();
}

// Function to get payment status badge
function getPaymentStatusBadge($order) {
    // For COD orders
    if ($order['payment_method'] == 'cod') {
        if ($order['status'] == 'completed' || $order['status'] == 'processing') {
            return '<span class="badge-status badge-success"><i class="fas fa-check-circle"></i> Paid on Delivery</span>';
        } elseif ($order['status'] == 'cancelled') {
            return '<span class="badge-status badge-danger"><i class="fas fa-times-circle"></i> Cancelled</span>';
        } else {
            return '<span class="badge-status badge-warning"><i class="fas fa-clock"></i> Pay on Delivery</span>';
        }
    } 
    // For eSewa orders
    else {
        if ($order['payment_status'] == 'paid') {
            return '<span class="badge-status badge-success"><i class="fas fa-check-circle"></i> Paid via eSewa</span>';
        } elseif ($order['status'] == 'cancelled') {
            return '<span class="badge-status badge-danger"><i class="fas fa-times-circle"></i> Cancelled</span>';
        } else {
            return '<span class="badge-status badge-warning"><i class="fas fa-clock"></i> Pending</span>';
        }
    }
}

// Define tracking statuses and their progress
function getTrackingStatuses($order_status, $payment_status, $payment_method) {
    $statuses = [];
    
    // 1. Order Placed
    $statuses[] = [
        'key' => 'placed',
        'label' => 'Order Placed',
        'icon' => 'fa-shopping-cart',
        'description' => 'Your order has been successfully placed',
        'completed' => true,
        'time' => 'Just now'
    ];
    
    // 2. Payment Confirmed (for eSewa) or Order Confirmed (for COD)
    if ($payment_method == 'esewa') {
        if ($payment_status == 'paid') {
            $statuses[] = [
                'key' => 'payment_confirmed',
                'label' => 'Payment Confirmed',
                'icon' => 'fa-credit-card',
                'description' => 'Your payment has been confirmed via eSewa',
                'completed' => true,
                'time' => '1 hour ago'
            ];
        } else {
            $statuses[] = [
                'key' => 'payment_pending',
                'label' => 'Payment Pending',
                'icon' => 'fa-clock',
                'description' => 'Waiting for payment confirmation from eSewa',
                'completed' => false,
                'time' => 'Awaiting payment'
            ];
        }
    } else {
        // COD - Order Confirmed
        if ($order_status != 'pending' && $order_status != 'cancelled') {
            $statuses[] = [
                'key' => 'order_confirmed',
                'label' => 'Order Confirmed',
                'icon' => 'fa-check-circle',
                'description' => 'Your order has been confirmed and is being prepared',
                'completed' => true,
                'time' => '2 hours ago'
            ];
        } else {
            $statuses[] = [
                'key' => 'order_confirmed',
                'label' => 'Order Confirmed',
                'icon' => 'fa-clock',
                'description' => 'Your order is being confirmed by the seller',
                'completed' => false,
                'time' => 'Awaiting confirmation'
            ];
        }
    }
    
    // 3. Processing
    if ($order_status == 'processing' || $order_status == 'completed') {
        $statuses[] = [
            'key' => 'processing',
            'label' => 'Processing',
            'icon' => 'fa-cogs',
            'description' => 'Your order is being processed and packed',
            'completed' => true,
            'time' => '4 hours ago'
        ];
    } else if ($order_status == 'pending') {
        $statuses[] = [
            'key' => 'processing',
            'label' => 'Processing',
            'icon' => 'fa-clock',
            'description' => 'Order is pending and will be processed soon',
            'completed' => false,
            'time' => 'Pending'
        ];
    } else {
        $statuses[] = [
            'key' => 'processing',
            'label' => 'Processing',
            'icon' => 'fa-clock',
            'description' => 'Order processing will begin once confirmed',
            'completed' => false,
            'time' => 'Not yet'
        ];
    }
    
    // 4. Shipping
    if ($order_status == 'processing' || $order_status == 'completed') {
        $statuses[] = [
            'key' => 'shipping',
            'label' => 'Out for Delivery',
            'icon' => 'fa-truck',
            'description' => 'Your order has been dispatched and is on the way',
            'completed' => $order_status == 'completed' ? true : false,
            'time' => $order_status == 'completed' ? '1 day ago' : 'Today'
        ];
    } else if ($order_status == 'pending') {
        $statuses[] = [
            'key' => 'shipping',
            'label' => 'Out for Delivery',
            'icon' => 'fa-clock',
            'description' => 'Will be dispatched once order is processed',
            'completed' => false,
            'time' => 'Not yet'
        ];
    } else {
        $statuses[] = [
            'key' => 'shipping',
            'label' => 'Out for Delivery',
            'icon' => 'fa-clock',
            'description' => 'Order shipping will begin when ready',
            'completed' => false,
            'time' => 'Not yet'
        ];
    }
    
    // 5. Delivered
    if ($order_status == 'completed') {
        $statuses[] = [
            'key' => 'delivered',
            'label' => 'Delivered',
            'icon' => 'fa-home',
            'description' => 'Your order has been successfully delivered!',
            'completed' => true,
            'time' => 'Today'
        ];
    } else if ($order_status == 'cancelled') {
        $statuses[] = [
            'key' => 'cancelled',
            'label' => 'Cancelled',
            'icon' => 'fa-times-circle',
            'description' => 'Your order has been cancelled',
            'completed' => true,
            'time' => 'Today'
        ];
    } else {
        $statuses[] = [
            'key' => 'delivered',
            'label' => 'Delivered',
            'icon' => 'fa-clock',
            'description' => 'Delivery is pending',
            'completed' => false,
            'time' => 'Not yet'
        ];
    }
    
    return $statuses;
}

// Get tracking history (if you have a tracking table)
function getTrackingHistory($db, $order_id) {
    try {
        $stmt = $db->prepare("SELECT * FROM tracking_history WHERE order_id = ? ORDER BY created_at DESC");
        $stmt->execute([$order_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

$tracking_statuses = getTrackingStatuses($order['status'], $order['payment_status'], $order['payment_method']);
$tracking_history = getTrackingHistory($db, $order['id']);

// Calculate progress percentage
$completed_count = 0;
foreach ($tracking_statuses as $status) {
    if ($status['completed']) {
        $completed_count++;
    }
}
$total_statuses = count($tracking_statuses);
$progress_percentage = ($completed_count / $total_statuses) * 100;

// Get estimated delivery date
$estimated_delivery = date('F j, Y', strtotime($order['order_date'] . ' + 5 days'));
if ($order['status'] == 'completed') {
    $estimated_delivery = 'Delivered on ' . date('F j, Y', strtotime($order['order_date'] . ' + 3 days'));
} elseif ($order['status'] == 'cancelled') {
    $estimated_delivery = 'Order Cancelled';
}

// Store location (default: Kathmandu)
$store_lat = 27.7172;
$store_lng = 85.3240;
$store_address = 'Kathmandu, Nepal';

// Customer delivery location (use address from order)
$customer_address = !empty($order['shipping_address']) ? $order['shipping_address'] : $order['address'];
// For demo purposes, generate approximate coordinates based on order ID
// In production, you would use geocoding API to get actual coordinates
$customer_lat = $store_lat + (($order['id'] % 100) / 1000) + 0.01;
$customer_lng = $store_lng + (($order['id'] % 50) / 1000) + 0.01;

// Determine delivery status for map
$delivery_status = $order['status'];
$delivery_color = '#ff6600';
if ($delivery_status == 'completed') {
    $delivery_color = '#2ecc71';
} elseif ($delivery_status == 'cancelled') {
    $delivery_color = '#e74c3c';
} elseif ($delivery_status == 'processing') {
    $delivery_color = '#3498db';
} elseif ($delivery_status == 'pending') {
    $delivery_color = '#f39c12';
}

// Get delivery progress percentage for map animation
$map_progress = $progress_percentage;
if ($delivery_status == 'completed') {
    $map_progress = 100;
} elseif ($delivery_status == 'cancelled') {
    $map_progress = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Order #<?php echo htmlspecialchars($order['order_number']); ?> - ShopVerse</title>
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
        
        /* Tracking Container */
        .tracking-container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 15px;
        }
        
        .tracking-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 20px;
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
        
        /* Order Header */
        .order-header {
            background: linear-gradient(135deg, #ff6600, #ff8533);
            color: white;
            padding: 25px 30px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        
        .order-header h2 {
            font-weight: 700;
            margin: 0;
        }
        
        .order-header .order-number {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .order-header .badge-status {
            font-size: 14px;
            padding: 8px 18px;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .badge-success { background: #2ecc71; color: white; }
        .badge-danger { background: #e74c3c; color: white; }
        .badge-primary { background: #3498db; color: white; }
        .badge-warning { background: #f39c12; color: white; }
        
        /* Map Container */
        .map-container {
            position: relative;
            width: 100%;
            height: 400px;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #e0e0e0;
        }
        
        #trackingMap {
            width: 100%;
            height: 100%;
            z-index: 1;
        }
        
        .map-overlay {
            position: absolute;
            bottom: 15px;
            left: 15px;
            right: 15px;
            background: rgba(255,255,255,0.95);
            padding: 12px 18px;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.15);
            z-index: 2;
            backdrop-filter: blur(5px);
        }
        
        .map-overlay .delivery-status {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .map-overlay .delivery-status .status-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            animation: pulse-dot 1.5s infinite;
        }
        
        @keyframes pulse-dot {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.3); opacity: 0.7; }
            100% { transform: scale(1); opacity: 1; }
        }
        
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
        
        .map-legend .legend-item .dot.customer {
            background: #3498db;
        }
        
        .map-legend .legend-item .dot.delivery {
            background: #2ecc71;
        }
        
        /* Tracking Progress */
        .tracking-progress {
            position: relative;
            padding: 20px 0;
        }
        
        .progress-bar-track {
            position: relative;
            height: 8px;
            background: #e0e0e0;
            border-radius: 10px;
            margin: 30px 0 40px 0;
            overflow: visible;
        }
        
        .progress-bar-track .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #ff6600, #ff8533);
            border-radius: 10px;
            transition: width 1s ease;
            width: <?php echo $progress_percentage; ?>%;
            position: relative;
        }
        
        .progress-bar-track .progress-fill::after {
            content: '<?php echo round($progress_percentage); ?>%';
            position: absolute;
            right: -10px;
            top: -30px;
            font-size: 14px;
            font-weight: 600;
            color: #ff6600;
        }
        
        /* Tracking Steps */
        .tracking-steps {
            display: flex;
            justify-content: space-between;
            position: relative;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .tracking-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            flex: 1;
            min-width: 60px;
            position: relative;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .tracking-step .step-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: #999;
            transition: all 0.5s ease;
            position: relative;
            z-index: 2;
            border: 3px solid #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .tracking-step .step-icon i {
            transition: all 0.3s;
        }
        
        .tracking-step .step-icon.completed {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
            box-shadow: 0 4px 15px rgba(46,204,113,0.4);
        }
        
        .tracking-step .step-icon.active {
            background: linear-gradient(135deg, #ff6600, #ff8533);
            color: white;
            box-shadow: 0 4px 20px rgba(255,102,0,0.4);
            animation: pulse 1.5s infinite;
        }
        
        .tracking-step .step-icon.cancelled {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            box-shadow: 0 4px 15px rgba(231,76,60,0.4);
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(255,102,0,0.4);
            }
            70% {
                box-shadow: 0 0 0 15px rgba(255,102,0,0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(255,102,0,0);
            }
        }
        
        .tracking-step .step-label {
            margin-top: 10px;
            font-size: 12px;
            font-weight: 600;
            color: #6c757d;
            text-align: center;
            transition: all 0.3s;
        }
        
        .tracking-step .step-label.active {
            color: #ff6600;
        }
        
        .tracking-step .step-label.completed {
            color: #2ecc71;
        }
        
        .tracking-step .step-label.cancelled {
            color: #e74c3c;
        }
        
        .tracking-step .step-time {
            font-size: 10px;
            color: #999;
            margin-top: 3px;
            text-align: center;
        }
        
        .tracking-step .step-description {
            font-size: 11px;
            color: #999;
            text-align: center;
            margin-top: 2px;
            display: none;
        }
        
        .tracking-step:hover .step-description {
            display: block;
        }
        
        /* Info Cards */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .info-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }
        
        .info-item .label {
            font-size: 12px;
            color: #6c757d;
            font-weight: 500;
        }
        
        .info-item .value {
            font-size: 18px;
            font-weight: 600;
            color: #333;
            margin-top: 5px;
        }
        
        .info-item .value i {
            color: #ff6600;
            margin-right: 5px;
        }
        
        /* Order Summary */
        .order-summary {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .order-summary .row {
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .order-summary .row:last-child {
            border-bottom: none;
        }
        
        .order-summary .label {
            font-weight: 500;
            color: #6c757d;
        }
        
        .order-summary .value {
            font-weight: 600;
            color: #333;
        }
        
        .order-summary .grand-total {
            font-size: 1.2rem;
            color: #ff6600;
        }
        
        /* Tracking History */
        .tracking-history {
            margin-top: 20px;
        }
        
        .history-item {
            display: flex;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .history-item:last-child {
            border-bottom: none;
        }
        
        .history-item .icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f0f0f0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
            flex-shrink: 0;
        }
        
        .history-item .icon.completed {
            background: #d4edda;
            color: #155724;
        }
        
        .history-item .content {
            flex: 1;
        }
        
        .history-item .content .title {
            font-weight: 600;
            color: #333;
        }
        
        .history-item .content .time {
            font-size: 12px;
            color: #6c757d;
        }
        
        .history-item .content .description {
            font-size: 13px;
            color: #6c757d;
        }
        
        /* Buttons */
        .btn-primary-custom {
            background: linear-gradient(135deg, #ff6600, #ff8533);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 50px;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(255,102,0,0.4);
            color: white;
        }
        
        .btn-secondary-custom {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 50px;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-secondary-custom:hover {
            background: #5a6268;
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-outline-custom {
            background: transparent;
            color: #ff6600;
            border: 2px solid #ff6600;
            padding: 10px 25px;
            border-radius: 50px;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-outline-custom:hover {
            background: #ff6600;
            color: white;
            transform: translateY(-2px);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .tracking-container {
                padding: 0 10px;
            }
            
            .tracking-card {
                padding: 15px;
            }
            
            .order-header {
                padding: 15px 20px;
            }
            
            .order-header h2 {
                font-size: 1.2rem;
            }
            
            .map-container {
                height: 300px;
            }
            
            .tracking-steps {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .tracking-step {
                flex-direction: row;
                width: 100%;
                gap: 15px;
            }
            
            .tracking-step .step-icon {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }
            
            .tracking-step .step-label {
                text-align: left;
                margin-top: 0;
            }
            
            .tracking-step .step-time {
                text-align: left;
            }
            
            .tracking-step .step-description {
                display: block;
            }
            
            .progress-bar-track {
                display: none;
            }
            
            .info-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .tracking-history .history-item {
                flex-direction: column;
                gap: 5px;
            }
            
            .map-legend {
                gap: 10px;
            }
        }
        
        @media (max-width: 480px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .map-container {
                height: 250px;
            }
            
            .btn-primary-custom,
            .btn-secondary-custom,
            .btn-outline-custom {
                width: 100%;
                justify-content: center;
            }
        }
        
        /* Print Styles */
        @media print {
            .navbar-daraz { display: none !important; }
            .no-print { display: none !important; }
            .tracking-card { box-shadow: none !important; border: 1px solid #ddd; }
            .order-header { background: #ff6600 !important; }
            body { background: white !important; }
            .map-container { height: 300px; }
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
                    <div class="brand-tagline">Your Trusted Store</div>
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
                        <a class="nav-link" href="map.php">
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
    
    <div class="tracking-container">
        <!-- Order Header -->
        <div class="order-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h2><i class="fas fa-truck me-2"></i> Track Order</h2>
                    <div class="order-number">
                        <i class="fas fa-hashtag"></i> 
                        <?php echo htmlspecialchars($order['order_number']); ?>
                    </div>
                </div>
                <div class="mt-2 mt-sm-0">
                    <?php if($order['status'] == 'completed'): ?>
                        <span class="badge-status badge-success">
                            <i class="fas fa-check-circle"></i> Delivered
                        </span>
                    <?php elseif($order['status'] == 'cancelled'): ?>
                        <span class="badge-status badge-danger">
                            <i class="fas fa-times-circle"></i> Cancelled
                        </span>
                    <?php elseif($order['status'] == 'processing'): ?>
                        <span class="badge-status badge-primary">
                            <i class="fas fa-spinner fa-pulse"></i> In Transit
                        </span>
                    <?php else: ?>
                        <span class="badge-status badge-warning">
                            <i class="fas fa-clock"></i> Pending
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Live Tracking Map -->
        <div class="tracking-card">
            <h5 class="mb-3"><i class="fas fa-map-marked-alt text-primary"></i> Live Tracking</h5>
            <div class="map-container">
                <div id="trackingMap"></div>
                <div class="map-overlay">
                    <div class="delivery-status">
                        <span class="status-dot" style="background: <?php echo $delivery_color; ?>;"></span>
                        <span><strong>Status:</strong> <?php echo ucfirst($delivery_status); ?></span>
                        <span class="text-muted">|</span>
                        <span><i class="fas fa-store"></i> Store: <?php echo $store_address; ?></span>
                        <span class="text-muted">|</span>
                        <span><i class="fas fa-home"></i> Delivery: <?php echo htmlspecialchars(substr($customer_address, 0, 30)) . (strlen($customer_address) > 30 ? '...' : ''); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Map Legend -->
            <div class="map-legend mt-3">
                <span class="legend-item">
                    <span class="dot store"></span> Store Location
                </span>
                <span class="legend-item">
                    <span class="dot customer"></span> Delivery Address
                </span>
                <span class="legend-item">
                    <span class="dot delivery"></span> Delivery Route
                </span>
                <span class="legend-item">
                    <i class="fas fa-truck" style="color: #ff6600;"></i> Current Location
                </span>
            </div>
        </div>
        
        <!-- Tracking Progress -->
        <div class="tracking-card">
            <h5 class="mb-4"><i class="fas fa-location-dot text-primary"></i> Tracking Progress</h5>
            
            <!-- Progress Bar -->
            <div class="progress-bar-track">
                <div class="progress-fill" style="width: <?php echo $order['status'] == 'completed' ? '100' : ($order['status'] == 'cancelled' ? '0' : $progress_percentage); ?>%"></div>
            </div>
            
            <!-- Tracking Steps -->
            <div class="tracking-steps">
                <?php 
                $current_step = 0;
                foreach($tracking_statuses as $index => $status):
                    $step_class = '';
                    if($status['completed']) {
                        $step_class = 'completed';
                    } elseif($index == $current_step) {
                        $step_class = 'active';
                    }
                    if($order['status'] == 'cancelled' && $status['key'] == 'cancelled') {
                        $step_class = 'cancelled';
                    }
                ?>
                <div class="tracking-step">
                    <div class="step-icon <?php echo $step_class; ?>">
                        <i class="fas <?php echo $status['icon']; ?>"></i>
                    </div>
                    <div>
                        <div class="step-label <?php echo $step_class; ?>">
                            <?php echo $status['label']; ?>
                        </div>
                        <div class="step-time">
                            <?php echo $status['time']; ?>
                        </div>
                        <div class="step-description">
                            <?php echo $status['description']; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Order Details -->
        <div class="tracking-card">
            <h5 class="mb-3"><i class="fas fa-info-circle text-primary"></i> Order Information</h5>
            
            <div class="info-grid">
                <div class="info-item">
                    <div class="label">Order Date</div>
                    <div class="value">
                        <i class="fas fa-calendar"></i>
                        <?php echo date('M d, Y', strtotime($order['order_date'])); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="label">Total Items</div>
                    <div class="value">
                        <i class="fas fa-box"></i>
                        <?php echo $items_count['total_items'] ?? 0; ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="label">Subtotal</div>
                    <div class="value">
                        <i class="fas fa-rupee-sign"></i>
                        <?php echo number_format($subtotal, 2); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="label">VAT (13%)</div>
                    <div class="value" style="font-size: 14px;">
                        <i class="fas fa-percent"></i>
                        <?php echo number_format($tax, 2); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="label">Delivery Charge</div>
                    <div class="value" style="font-size: 14px;">
                        <i class="fas fa-truck"></i>
                        <?php echo number_format($delivery_charge, 2); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="label">Grand Total</div>
                    <div class="value">
                        <i class="fas fa-rupee-sign"></i>
                        <?php echo number_format($grand_total, 2); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="label">Payment Method</div>
                    <div class="value" style="font-size: 14px;">
                        <?php if($order['payment_method'] == 'cod'): ?>
                            <i class="fas fa-money-bill-wave"></i> Cash on Delivery
                        <?php else: ?>
                            <i class="fas fa-wallet"></i> eSewa
                        <?php endif; ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="label">Payment Status</div>
                    <div class="value" style="font-size: 14px;">
                        <?php echo getPaymentStatusBadge($order); ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="label">Estimated Delivery</div>
                    <div class="value" style="font-size: 14px;">
                        <i class="fas fa-clock"></i>
                        <?php echo $estimated_delivery; ?>
                    </div>
                </div>
                <div class="info-item">
                    <div class="label">Tracking ID</div>
                    <div class="value" style="font-size: 14px;">
                        <i class="fas fa-barcode"></i>
                        <?php echo !empty($order['tracking_id']) ? htmlspecialchars($order['tracking_id']) : 'N/A'; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tracking History -->
        <?php if(!empty($tracking_history)): ?>
        <div class="tracking-card">
            <h5 class="mb-3"><i class="fas fa-history text-primary"></i> Tracking History</h5>
            <div class="tracking-history">
                <?php foreach($tracking_history as $history): ?>
                <div class="history-item">
                    <div class="icon completed">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="content">
                        <div class="title"><?php echo htmlspecialchars($history['status']); ?></div>
                        <div class="time"><?php echo date('M d, Y h:i A', strtotime($history['created_at'])); ?></div>
                        <div class="description"><?php echo htmlspecialchars($history['notes'] ?? ''); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Order Summary with Breakdown -->
        <div class="tracking-card">
            <h5 class="mb-3"><i class="fas fa-receipt text-primary"></i> Order Summary</h5>
            <div class="order-summary">
                <div class="row">
                    <div class="col-6 label">Subtotal</div>
                    <div class="col-6 value text-end">RS <?php echo number_format($subtotal, 2); ?></div>
                </div>
                <div class="row">
                    <div class="col-6 label">VAT (13%)</div>
                    <div class="col-6 value text-end">RS <?php echo number_format($tax, 2); ?></div>
                </div>
                <div class="row">
                    <div class="col-6 label">Delivery Charge</div>
                    <div class="col-6 value text-end">RS <?php echo number_format($delivery_charge, 2); ?></div>
                </div>
                <div class="row" style="border-bottom: 2px solid #ff6600; padding-bottom: 10px; margin-bottom: 10px;">
                    <div class="col-6 label" style="font-size: 1.1rem;">Grand Total</div>
                    <div class="col-6 value text-end grand-total">RS <?php echo number_format($grand_total, 2); ?></div>
                </div>
                <div class="row">
                    <div class="col-6 label">Payment Method</div>
                    <div class="col-6 value text-end">
                        <?php if($order['payment_method'] == 'cod'): ?>
                            Cash on Delivery
                        <?php else: ?>
                            eSewa
                        <?php endif; ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6 label">Payment Status</div>
                    <div class="col-6 value text-end">
                        <?php if($order['payment_method'] == 'cod'): ?>
                            <?php if($order['status'] == 'completed' || $order['status'] == 'processing'): ?>
                                <span class="text-success"><i class="fas fa-check-circle"></i> Paid on Delivery</span>
                            <?php elseif($order['status'] == 'cancelled'): ?>
                                <span class="text-danger"><i class="fas fa-times-circle"></i> Cancelled</span>
                            <?php else: ?>
                                <span class="text-warning"><i class="fas fa-clock"></i> Pay on Delivery</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php if($order['payment_status'] == 'paid'): ?>
                                <span class="text-success"><i class="fas fa-check-circle"></i> Paid via eSewa</span>
                            <?php elseif($order['status'] == 'cancelled'): ?>
                                <span class="text-danger"><i class="fas fa-times-circle"></i> Cancelled</span>
                            <?php else: ?>
                                <span class="text-warning"><i class="fas fa-clock"></i> Pending</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="d-flex flex-wrap gap-2 no-print">
            <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn-primary-custom">
                <i class="fas fa-eye"></i> View Order Details
            </a>
            <a href="invoice.php?id=<?php echo $order['id']; ?>" target="_blank" class="btn-outline-custom">
                <i class="fas fa-file-invoice"></i> View Invoice
            </a>
            <a href="orders.php" class="btn-secondary-custom">
                <i class="fas fa-arrow-left"></i> Back to Orders
            </a>
        </div>
        
        <!-- Help/Support -->
        <div class="tracking-card no-print">
            <div class="text-center">
                <i class="fas fa-headset text-primary" style="font-size: 2rem;"></i>
                <h6 class="mt-2">Need Help?</h6>
                <p class="text-muted small">
                    For any queries regarding your order, please contact our support team.
                </p>
                <a href="#" class="btn btn-link text-primary">
                    <i class="fas fa-envelope"></i> support@shopverse.com
                </a>
                <span class="mx-2 text-muted">|</span>
                <a href="#" class="btn btn-link text-primary">
                    <i class="fas fa-phone"></i> +977-1-2345678
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize map
        let map;
        let routeLine;
        let deliveryMarker;
        let storeMarker;
        let customerMarker;
        
        // Store location
        const storeLat = <?php echo $store_lat; ?>;
        const storeLng = <?php echo $store_lng; ?>;
        const storeAddress = '<?php echo addslashes($store_address); ?>';
        
        // Customer location
        const customerLat = <?php echo $customer_lat; ?>;
        const customerLng = <?php echo $customer_lng; ?>;
        const customerAddress = '<?php echo addslashes($customer_address); ?>';
        
        // Delivery status
        const deliveryStatus = '<?php echo $delivery_status; ?>';
        const deliveryColor = '<?php echo $delivery_color; ?>';
        const mapProgress = <?php echo $map_progress; ?>;
        
        function initMap() {
            // Calculate center point between store and customer
            const centerLat = (storeLat + customerLat) / 2;
            const centerLng = (storeLng + customerLng) / 2;
            
            // Create map
            map = L.map('trackingMap').setView([centerLat, centerLng], 12);
            
            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);
            
            // Store marker
            const storeIcon = L.divIcon({
                html: '<i class="fas fa-store" style="font-size: 24px; color: #ff6600; background: white; padding: 8px; border-radius: 50%; box-shadow: 0 2px 10px rgba(0,0,0,0.3);"></i>',
                className: 'custom-div-icon',
                iconSize: [40, 40],
                iconAnchor: [20, 20]
            });
            
            storeMarker = L.marker([storeLat, storeLng], {
                icon: storeIcon
            }).addTo(map);
            
            storeMarker.bindPopup(`
                <div style="text-align: center;">
                    <h6 class="mb-1"><i class="fas fa-store text-primary"></i> ShopVerse Store</h6>
                    <p class="mb-0 small">${storeAddress}</p>
                    <small class="text-muted">Pickup Location</small>
                </div>
            `);
            
            // Customer marker
            const customerIcon = L.divIcon({
                html: '<i class="fas fa-home" style="font-size: 22px; color: #3498db; background: white; padding: 8px; border-radius: 50%; box-shadow: 0 2px 10px rgba(0,0,0,0.3);"></i>',
                className: 'custom-div-icon',
                iconSize: [38, 38],
                iconAnchor: [19, 19]
            });
            
            customerMarker = L.marker([customerLat, customerLng], {
                icon: customerIcon
            }).addTo(map);
            
            customerMarker.bindPopup(`
                <div style="text-align: center;">
                    <h6 class="mb-1"><i class="fas fa-home text-primary"></i> Delivery Address</h6>
                    <p class="mb-0 small">${customerAddress}</p>
                    <small class="text-muted">${deliveryStatus === 'completed' ? 'Delivered' : 'Delivery Location'}</small>
                </div>
            `);
            
            // Draw route from store to customer
            drawRoute();
            
            // Add delivery marker (moving along route based on progress)
            addDeliveryMarker();
            
            // Fit bounds to show both markers
            const bounds = L.latLngBounds([
                [storeLat, storeLng],
                [customerLat, customerLng]
            ]);
            map.fitBounds(bounds, {
                padding: [50, 50],
                maxZoom: 14
            });
        }
        
        function drawRoute() {
            // Calculate intermediate points for a curved route
            const points = [];
            const steps = 20;
            
            for (let i = 0; i <= steps; i++) {
                const t = i / steps;
                // Add some curve to the route
                const lat = storeLat + (customerLat - storeLat) * t;
                const lng = storeLng + (customerLng - storeLng) * t;
                // Add slight curve (adjust based on direction)
                const curveOffset = Math.sin(t * Math.PI) * 0.02;
                const angle = Math.atan2(customerLng - storeLng, customerLat - storeLat);
                const latOffset = -Math.sin(angle) * curveOffset;
                const lngOffset = Math.cos(angle) * curveOffset;
                points.push([lat + latOffset, lng + lngOffset]);
            }
            
            // Draw route line
            routeLine = L.polyline(points, {
                color: '#ff6600',
                weight: 4,
                opacity: 0.7,
                dashArray: '10, 10',
                lineJoin: 'round',
                lineCap: 'round'
            }).addTo(map);
            
            // Add animation to route
            animateRoute();
        }
        
        function animateRoute() {
            if (routeLine && mapProgress > 0 && mapProgress < 100) {
                // Animate the dash offset
                let offset = 0;
                setInterval(() => {
                    offset = (offset + 1) % 20;
                    routeLine.setStyle({
                        dashOffset: offset
                    });
                }, 100);
            }
        }
        
        function addDeliveryMarker() {
            // Calculate delivery position based on progress
            const progress = mapProgress / 100;
            const lat = storeLat + (customerLat - storeLat) * progress;
            const lng = storeLng + (customerLng - storeLng) * progress;
            
            const deliveryIcon = L.divIcon({
                html: `<i class="fas fa-truck" style="font-size: 22px; color: ${deliveryColor}; background: white; padding: 6px; border-radius: 50%; box-shadow: 0 2px 10px rgba(0,0,0,0.3); border: 2px solid ${deliveryColor};"></i>`,
                className: 'custom-div-icon',
                iconSize: [34, 34],
                iconAnchor: [17, 17]
            });
            
            deliveryMarker = L.marker([lat, lng], {
                icon: deliveryIcon
            }).addTo(map);
            
            // Show popup based on status
            let popupMessage = '';
            if (deliveryStatus === 'completed') {
                popupMessage = '✅ Order Delivered!';
            } else if (deliveryStatus === 'cancelled') {
                popupMessage = '❌ Order Cancelled';
            } else if (deliveryStatus === 'processing') {
                popupMessage = '🚚 On the way!';
            } else if (deliveryStatus === 'pending') {
                popupMessage = '⏳ Preparing for delivery';
            } else {
                popupMessage = `📦 ${deliveryStatus.charAt(0).toUpperCase() + deliveryStatus.slice(1)}`;
            }
            
            deliveryMarker.bindPopup(`
                <div style="text-align: center;">
                    <h6 class="mb-1"><i class="fas fa-truck" style="color: ${deliveryColor};"></i> Current Location</h6>
                    <p class="mb-0 small">${popupMessage}</p>
                    <small class="text-muted">${Math.round(mapProgress)}% complete</small>
                </div>
            `);
            
            // Animate delivery marker if in transit
            if (deliveryStatus === 'processing' || deliveryStatus === 'shipped') {
                animateDeliveryMarker();
            }
        }
        
        function animateDeliveryMarker() {
            let progress = mapProgress / 100;
            const step = 0.005;
            
            setInterval(() => {
                if (progress < 1 && deliveryStatus !== 'completed' && deliveryStatus !== 'cancelled') {
                    progress += step;
                    if (progress > 1) progress = 1;
                    
                    const lat = storeLat + (customerLat - storeLat) * progress;
                    const lng = storeLng + (customerLng - storeLng) * progress;
                    
                    deliveryMarker.setLatLng([lat, lng]);
                }
            }, 2000);
        }
        
        // Center map on delivery location
        function centerOnDelivery() {
            if (deliveryMarker) {
                map.setView(deliveryMarker.getLatLng(), 14);
                deliveryMarker.openPopup();
            }
        }
        
        // Toggle route visibility
        function toggleRoute() {
            if (routeLine) {
                if (map.hasLayer(routeLine)) {
                    map.removeLayer(routeLine);
                } else {
                    map.addLayer(routeLine);
                }
            }
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
        
        // Auto-refresh tracking every 30 seconds if order is in transit
        <?php if($order['status'] == 'processing' || $order['status'] == 'pending'): ?>
        setTimeout(function() {
            location.reload();
        }, 30000);
        <?php endif; ?>
        
        // Animate progress bar on load
        document.addEventListener('DOMContentLoaded', function() {
            const progressFill = document.querySelector('.progress-fill');
            if (progressFill) {
                const targetWidth = progressFill.style.width;
                progressFill.style.width = '0%';
                setTimeout(() => {
                    progressFill.style.width = targetWidth;
                }, 100);
            }
        });
    </script>
</body>
</html>