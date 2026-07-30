<?php
session_start();
require_once '../config/database.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'customer') {
    http_response_code(401);
    exit('Unauthorized');
}

$database = new Database();
$db = $database->getConnection();

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$user_id = $_SESSION['user_id'];
$is_modal = isset($_GET['modal']) ? true : false; // For modal view

if ($order_id <= 0) {
    echo '<div class="alert alert-danger">Invalid order ID</div>';
    exit();
}

try {
    // Get order details
    $stmt = $db->prepare("SELECT o.*, u.name as customer_name, u.email, u.phone, u.address 
                          FROM orders o 
                          JOIN users u ON o.user_id = u.id 
                          WHERE o.id = ? AND o.user_id = ?");
    $stmt->execute([$order_id, $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo '<div class="alert alert-danger">Order not found</div>';
        exit();
    }

    // Get order items
    $stmt = $db->prepare("SELECT oi.*, p.name as product_name, p.image, p.category 
                          FROM order_items oi 
                          JOIN products p ON oi.product_id = p.id 
                          WHERE oi.order_id = ?");
    $stmt->execute([$order_id]);
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

    // Get tracking history
    $tracking_history = [];
    try {
        $stmt = $db->prepare("SELECT * FROM tracking_history WHERE order_id = ? ORDER BY created_at DESC");
        $stmt->execute([$order_id]);
        $tracking_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Tracking table might not exist yet
    }

    // Get unread tracking updates
    $tracking_updates = [];
    $unread_count = 0;
    try {
        $stmt = $db->prepare("SELECT * FROM tracking_updates WHERE order_id = ? AND is_read = FALSE");
        $stmt->execute([$order_id]);
        $tracking_updates = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $unread_count = count($tracking_updates);
        
        // Mark updates as read
        if ($unread_count > 0) {
            $stmt = $db->prepare("UPDATE tracking_updates SET is_read = TRUE WHERE order_id = ?");
            $stmt->execute([$order_id]);
        }
    } catch (Exception $e) {
        // Tracking updates table might not exist
    }

} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error loading order details. Please try again.</div>';
    exit();
}

// Helper functions
function getStatusBadge($status) {
    $badges = [
        'pending' => 'badge-warning',
        'processing' => 'badge-primary',
        'completed' => 'badge-success',
        'cancelled' => 'badge-danger'
    ];
    $icons = [
        'pending' => 'fa-clock',
        'processing' => 'fa-spinner fa-pulse',
        'completed' => 'fa-check-circle',
        'cancelled' => 'fa-times-circle'
    ];
    $class = isset($badges[$status]) ? $badges[$status] : 'badge-secondary';
    $icon = isset($icons[$status]) ? $icons[$status] : 'fa-question-circle';
    return '<span class="badge-status ' . $class . '"><i class="fas ' . $icon . '"></i> ' . ucfirst($status) . '</span>';
}

function getPaymentStatusBadge($order) {
    if ($order['payment_method'] == 'cod') {
        if ($order['status'] == 'completed') {
            return '<span class="badge-status badge-paid"><i class="fas fa-check-circle"></i> Paid on Delivery</span>';
        } elseif ($order['status'] == 'cancelled') {
            return '<span class="badge-status badge-cancelled"><i class="fas fa-times-circle"></i> Cancelled</span>';
        } else {
            return '<span class="badge-status badge-pending-payment"><i class="fas fa-clock"></i> Pay on Delivery</span>';
        }
    } else {
        if ($order['payment_status'] == 'paid') {
            return '<span class="badge-status badge-paid"><i class="fas fa-check-circle"></i> Paid via eSewa</span>';
        } elseif ($order['status'] == 'cancelled') {
            return '<span class="badge-status badge-cancelled"><i class="fas fa-times-circle"></i> Cancelled</span>';
        } else {
            return '<span class="badge-status badge-pending-payment"><i class="fas fa-clock"></i> Pending</span>';
        }
    }
}

function getTrackingIcon($status) {
    $icons = [
        'placed' => 'fa-shopping-cart',
        'payment_confirmed' => 'fa-credit-card',
        'payment_pending' => 'fa-clock',
        'order_confirmed' => 'fa-check-circle',
        'processing' => 'fa-cogs',
        'shipped' => 'fa-shipping-fast',
        'out_for_delivery' => 'fa-truck',
        'delivered' => 'fa-home',
        'cancelled' => 'fa-times-circle'
    ];
    return isset($icons[$status]) ? $icons[$status] : 'fa-info-circle';
}

function getTrackingLabel($status) {
    $labels = [
        'placed' => 'Order Placed',
        'payment_confirmed' => 'Payment Confirmed',
        'payment_pending' => 'Payment Pending',
        'order_confirmed' => 'Order Confirmed',
        'processing' => 'Processing',
        'shipped' => 'Shipped',
        'out_for_delivery' => 'Out for Delivery',
        'delivered' => 'Delivered',
        'cancelled' => 'Cancelled'
    ];
    return isset($labels[$status]) ? $labels[$status] : ucfirst(str_replace('_', ' ', $status));
}

function getTrackingClass($status, $order_status, $is_latest = false) {
    if ($order_status == 'cancelled' && $status == 'cancelled') {
        return 'cancelled';
    }
    if ($is_latest && $order_status != 'completed' && $order_status != 'cancelled') {
        return 'active';
    }
    return 'completed';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details #<?php echo htmlspecialchars($order['order_number']); ?></title>
    <?php if(!$is_modal): ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <?php endif; ?>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: <?php echo $is_modal ? 'transparent' : '#f8f9fa'; ?>;
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: <?php echo $is_modal ? '0' : '20px'; ?>;
        }
        
        .order-details-wrapper {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        .order-header {
            background: linear-gradient(135deg, #ff6600, #ff8533);
            color: white;
            padding: 20px 25px;
            border-radius: 15px 15px 0 0;
            margin-bottom: 0;
        }
        
        .order-header h4 {
            margin: 0;
            font-weight: 600;
        }
        
        .order-header .order-number {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .order-details-card {
            background: white;
            border-radius: 0 0 15px 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }
        
        .order-info-grid {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .info-row:last-child { border-bottom: none; }
        
        .info-label { 
            font-weight: 600; 
            color: #6c757d;
            font-size: 0.9rem;
        }
        .info-value { 
            color: #333;
            font-weight: 500;
            text-align: right;
        }
        
        .badge-status {
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            white-space: nowrap;
        }
        
        .badge-warning { background: #f39c12; color: white; }
        .badge-primary { background: #3498db; color: white; }
        .badge-success { background: #2ecc71; color: white; }
        .badge-danger { background: #e74c3c; color: white; }
        .badge-paid { background: #2ecc71; color: white; }
        .badge-pending-payment { background: #f39c12; color: white; }
        .badge-cancelled { background: #e74c3c; color: white; }
        .badge-secondary { background: #95a5a6; color: white; }
        .badge-info { background: #3498db; color: white; }
        
        /* Tracking Timeline */
        .tracking-timeline {
            padding: 10px 0;
        }
        
        .tracking-item {
            display: flex;
            gap: 20px;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
            position: relative;
            transition: all 0.3s;
        }
        
        .tracking-item:last-child {
            border-bottom: none;
        }
        
        .tracking-item .tracking-icon {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 18px;
            color: white;
            transition: all 0.3s;
            position: relative;
            z-index: 2;
        }
        
        .tracking-item .tracking-icon.completed {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
        }
        
        .tracking-item .tracking-icon.active {
            background: linear-gradient(135deg, #ff6600, #ff8533);
            animation: pulse-icon 1.5s infinite;
        }
        
        .tracking-item .tracking-icon.pending {
            background: #e0e0e0;
            color: #999;
        }
        
        .tracking-item .tracking-icon.cancelled {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }
        
        @keyframes pulse-icon {
            0% {
                box-shadow: 0 0 0 0 rgba(255,102,0,0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(255,102,0,0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(255,102,0,0);
            }
        }
        
        .tracking-item .tracking-content {
            flex: 1;
        }
        
        .tracking-item .tracking-content .title {
            font-weight: 600;
            color: #333;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .tracking-item .tracking-content .title .badge-current {
            font-size: 10px;
            background: #ff6600;
            color: white;
            padding: 2px 10px;
            border-radius: 50px;
        }
        
        .tracking-item .tracking-content .time {
            font-size: 12px;
            color: #6c757d;
        }
        
        .tracking-item .tracking-content .description {
            font-size: 14px;
            color: #6c757d;
            margin-top: 3px;
        }
        
        .tracking-item .tracking-line {
            position: absolute;
            left: 22px;
            top: 60px;
            width: 2px;
            height: calc(100% - 60px);
            background: #e0e0e0;
        }
        
        .tracking-item:last-child .tracking-line {
            display: none;
        }
        
        /* Tracking Updates Alert */
        .tracking-updates-alert {
            background: linear-gradient(135deg, #fff3e0, #fff8e1);
            border-left: 4px solid #ff6600;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 20px;
            animation: slideDown 0.5s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .tracking-updates-alert .update-item {
            padding: 5px 0;
            border-bottom: 1px solid #f0e0d0;
        }
        
        .tracking-updates-alert .update-item:last-child {
            border-bottom: none;
        }
        
        .tracking-updates-alert .update-icon {
            color: #ff6600;
            margin-right: 8px;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 10px;
            border: 2px solid #f0f0f0;
        }
        
        .product-image-placeholder {
            width: 60px;
            height: 60px;
            background: #f8f9fa;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #f0f0f0;
        }
        
        .total-amount {
            font-size: 1.8rem;
            font-weight: 700;
            color: #ff6600;
        }
        
        .btn-invoice {
            background: linear-gradient(135deg, #ff6600, #ff8533);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 50px;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-invoice:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(255,102,0,0.4);
            color: white;
        }
        
        .btn-print-invoice {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 50px;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-print-invoice:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102,126,234,0.4);
            color: white;
        }
        
        .btn-secondary-custom {
            background: #6c757d;
            color: white;
            border: none;
            padding: 10px 24px;
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
        
        .btn-cancel-order {
            background: #dc3545;
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 50px;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-cancel-order:hover {
            background: #c82333;
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-track {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 50px;
            font-weight: 500;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-track:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(40,167,69,0.4);
            color: white;
        }
        
        /* Original Timeline (keep for backward compatibility) */
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline-item {
            position: relative;
            padding-bottom: 25px;
            padding-left: 20px;
            opacity: 0.8;
        }
        .timeline-item:last-child { padding-bottom: 0; }
        .timeline-item.active { opacity: 1; }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -20px;
            top: 5px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #dee2e6;
            border: 3px solid #fff;
            box-shadow: 0 0 0 2px #dee2e6;
            transition: all 0.3s;
        }
        .timeline-item.active::before {
            background: #ff6600;
            box-shadow: 0 0 0 2px #ff6600;
        }
        .timeline-item.completed::before {
            background: #2ecc71;
            box-shadow: 0 0 0 2px #2ecc71;
        }
        .timeline-item.cancelled::before {
            background: #e74c3c;
            box-shadow: 0 0 0 2px #e74c3c;
        }
        .timeline-item::after {
            content: '';
            position: absolute;
            left: -14px;
            top: 20px;
            width: 2px;
            height: calc(100% - 5px);
            background: #e0e0e0;
        }
        .timeline-item:last-child::after { display: none; }
        .timeline-item .timeline-title {
            font-weight: 600;
            color: #333;
        }
        .timeline-item .timeline-time {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .timeline-item .timeline-desc {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        /* Table */
        .table thead th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 0.85rem;
            border-bottom: 2px solid #e0e0e0;
            padding: 12px 15px;
        }
        
        .table tbody td {
            padding: 12px 15px;
            vertical-align: middle;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .table tfoot td {
            padding: 12px 15px;
            font-weight: 500;
        }
        
        .price-npr {
            font-weight: 600;
            color: #333;
        }
        
        .price-npr i {
            font-size: 12px;
            margin-right: 2px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .order-header {
                padding: 15px;
            }
            .order-info-grid, .order-details-card {
                padding: 15px;
            }
            .info-row {
                flex-direction: column;
                padding: 8px 0;
            }
            .info-value {
                text-align: left;
                margin-top: 2px;
            }
            .product-image {
                width: 40px;
                height: 40px;
            }
            .total-amount {
                font-size: 1.3rem;
            }
            .btn-invoice, .btn-print-invoice, .btn-secondary-custom, 
            .btn-cancel-order, .btn-track {
                width: 100%;
                justify-content: center;
                margin-bottom: 8px;
            }
            .d-flex.flex-wrap.gap-2 {
                flex-direction: column;
            }
            .tracking-item {
                gap: 12px;
                padding: 12px 0;
            }
            .tracking-item .tracking-icon {
                width: 35px;
                height: 35px;
                font-size: 14px;
            }
            .tracking-item .tracking-line {
                left: 17px;
                top: 50px;
            }
        }
        
        /* Print Styles */
        @media print {
            .no-print { display: none !important; }
            body { background: white; padding: 0; }
            .order-info-grid, .order-details-card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
            .btn-invoice, .btn-print-invoice, .btn-secondary-custom, 
            .btn-cancel-order, .btn-track {
                display: none !important;
            }
            .tracking-item .tracking-icon {
                background: #dee2e6 !important;
                color: #333 !important;
            }
        }
    </style>
</head>
<body>
    <div class="order-details-wrapper">
        <!-- Order Header -->
        <div class="order-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap">
                <div>
                    <h4><i class="fas fa-receipt me-2"></i> Order Details</h4>
                    <div class="order-number">Order #<?php echo htmlspecialchars($order['order_number']); ?></div>
                </div>
                <div class="mt-2 mt-sm-0 d-flex gap-2 align-items-center flex-wrap">
                    <?php echo getStatusBadge($order['status']); ?>
                    <?php if($order['status'] != 'cancelled' && $order['status'] != 'completed'): ?>
                        <a href="track_order.php?id=<?php echo $order['id']; ?>" class="btn btn-success btn-sm">
                            <i class="fas fa-truck"></i> Track
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Tracking Updates Alert -->
        <?php if($unread_count > 0 && !empty($tracking_updates)): ?>
            <div class="tracking-updates-alert no-print">
                <h6><i class="fas fa-bell tracking-update-icon"></i> <?php echo $unread_count; ?> New Tracking Update(s)</h6>
                <?php foreach($tracking_updates as $update): ?>
                    <div class="update-item">
                        <i class="fas fa-info-circle update-icon"></i>
                        <?php echo htmlspecialchars($update['message']); ?>
                        <small class="text-muted float-end">
                            <?php echo date('h:i A', strtotime($update['created_at'])); ?>
                        </small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <!-- Order Information -->
        <div class="order-info-grid">
            <div class="row">
                <div class="col-md-6">
                    <h6 class="mb-3 text-primary"><i class="fas fa-info-circle me-2"></i>Order Information</h6>
                    <div class="info-row">
                        <span class="info-label">Order Number:</span>
                        <span class="info-value">#<?php echo htmlspecialchars($order['order_number']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Order Date:</span>
                        <span class="info-value"><?php echo date('F j, Y, g:i a', strtotime($order['order_date'])); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Payment Method:</span>
                        <span class="info-value">
                            <?php if($order['payment_method'] == 'cod'): ?>
                                <i class="fas fa-money-bill-wave text-success"></i> Cash on Delivery
                            <?php else: ?>
                                <i class="fas fa-wallet text-primary"></i> eSewa
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Payment Status:</span>
                        <span class="info-value">
                            <?php echo getPaymentStatusBadge($order); ?>
                        </span>
                    </div>
                    <?php if($order['esewa_txn_id']): ?>
                    <div class="info-row">
                        <span class="info-label">Transaction ID:</span>
                        <span class="info-value">
                            <code class="small"><?php echo htmlspecialchars($order['esewa_txn_id']); ?></code>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if(!empty($order['tracking_id'])): ?>
                    <div class="info-row">
                        <span class="info-label">Tracking ID:</span>
                        <span class="info-value">
                            <code class="small"><?php echo htmlspecialchars($order['tracking_id']); ?></code>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="col-md-6">
                    <h6 class="mb-3 text-primary"><i class="fas fa-truck me-2"></i>Shipping Information</h6>
                    <div class="info-row">
                        <span class="info-label">Order Status:</span>
                        <span class="info-value">
                            <?php echo getStatusBadge($order['status']); ?>
                        </span>
                    </div>
                    <?php if(!empty($order['estimated_delivery'])): ?>
                    <div class="info-row">
                        <span class="info-label">Estimated Delivery:</span>
                        <span class="info-value">
                            <i class="fas fa-calendar-check text-success"></i>
                            <?php echo date('F j, Y', strtotime($order['estimated_delivery'])); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if(!empty($order['shipped_date'])): ?>
                    <div class="info-row">
                        <span class="info-label">Shipped Date:</span>
                        <span class="info-value">
                            <i class="fas fa-shipping-fast text-info"></i>
                            <?php echo date('F j, Y, g:i a', strtotime($order['shipped_date'])); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if(!empty($order['delivered_date'])): ?>
                    <div class="info-row">
                        <span class="info-label">Delivered Date:</span>
                        <span class="info-value">
                            <i class="fas fa-check-circle text-success"></i>
                            <?php echo date('F j, Y, g:i a', strtotime($order['delivered_date'])); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if($order['status'] == 'processing'): ?>
                        <?php if($order['payment_method'] == 'cod'): ?>
                        <div class="info-row">
                            <span class="info-label">Delivery Status:</span>
                            <span class="info-value text-success">
                                <i class="fas fa-truck"></i> Order confirmed! Waiting for delivery.
                            </span>
                        </div>
                        <?php elseif($order['payment_method'] == 'esewa' && $order['payment_status'] == 'paid'): ?>
                        <div class="info-row">
                            <span class="info-label">Payment Status:</span>
                            <span class="info-value text-success">
                                <i class="fas fa-check-circle"></i> Payment confirmed! Processing order.
                            </span>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="info-label">Shipping Address:</span>
                        <span class="info-value">
                            <?php 
                            $address = !empty($order['shipping_address']) ? $order['shipping_address'] : $order['address'];
                            echo nl2br(htmlspecialchars($address)); 
                            ?>
                        </span>
                    </div>
                    <?php if(!empty($order['phone'])): ?>
                    <div class="info-row">
                        <span class="info-label">Contact Number:</span>
                        <span class="info-value"><?php echo htmlspecialchars($order['phone']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Tracking Timeline (Enhanced) -->
        <?php if(!empty($tracking_history)): ?>
        <div class="order-details-card">
            <h6 class="mb-3 text-primary"><i class="fas fa-history me-2"></i>Tracking Timeline</h6>
            <div class="tracking-timeline">
                <?php 
                $total_history = count($tracking_history);
                foreach($tracking_history as $index => $track): 
                    $is_latest = ($index == 0);
                    $status_class = getTrackingClass($track['status'], $order['status'], $is_latest);
                ?>
                <div class="tracking-item">
                    <div class="tracking-icon <?php echo $status_class; ?>">
                        <i class="fas <?php echo getTrackingIcon($track['status']); ?>"></i>
                    </div>
                    <div class="tracking-content">
                        <div class="title">
                            <?php echo getTrackingLabel($track['status']); ?>
                            <?php if($is_latest && $order['status'] != 'completed' && $order['status'] != 'cancelled'): ?>
                                <span class="badge-current">Current</span>
                            <?php endif; ?>
                            <?php if($order['status'] == 'completed' && $track['status'] == 'delivered'): ?>
                                <span class="badge bg-success text-white">Delivered</span>
                            <?php endif; ?>
                            <?php if($order['status'] == 'cancelled' && $track['status'] == 'cancelled'): ?>
                                <span class="badge bg-danger text-white">Cancelled</span>
                            <?php endif; ?>
                        </div>
                        <div class="time">
                            <i class="far fa-clock"></i> 
                            <?php echo date('F j, Y, g:i A', strtotime($track['created_at'])); ?>
                        </div>
                        <?php if(!empty($track['location'])): ?>
                            <div class="description">
                                <i class="fas fa-map-marker-alt text-danger"></i> 
                                <?php echo htmlspecialchars($track['location']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if(!empty($track['notes'])): ?>
                            <div class="description">
                                <?php echo htmlspecialchars($track['notes']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if($index < $total_history - 1): ?>
                        <div class="tracking-line"></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Order Items -->
        <div class="order-details-card">
            <h6 class="mb-3 text-primary"><i class="fas fa-boxes me-2"></i>Order Items</h6>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th style="width: 40%;">Product</th>
                            <th style="width: 15%;">Quantity</th>
                            <th style="width: 20%;">Unit Price</th>
                            <th style="width: 25%;">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $subtotal = 0;
                        foreach($items as $item): 
                            $item_total = $item['price'] * $item['quantity'];
                            $subtotal += $item_total;
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <?php 
                                    $image_path = '';
                                    if(!empty($item['image'])) {
                                        $possible_paths = [
                                            "../uploads/" . $item['image'],
                                            "uploads/" . $item['image'],
                                            "/uploads/" . $item['image']
                                        ];
                                        foreach($possible_paths as $path) {
                                            if(file_exists($path)) {
                                                $image_path = $path;
                                                break;
                                            }
                                        }
                                    }
                                    ?>
                                    <?php if($image_path): ?>
                                        <img src="<?php echo $image_path; ?>" class="product-image me-3" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                    <?php else: ?>
                                        <div class="product-image-placeholder me-3">
                                            <i class="fas fa-box-open text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                                        <?php if(!empty($item['category'])): ?>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($item['category']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?php echo $item['quantity']; ?></span>
                            </td>
                            <td>
                                <span class="price-npr">
                                    <i class="fas fa-rupee-sign"></i> <?php echo number_format($item['price'], 2); ?>
                                </span>
                            </td>
                            <td>
                                <strong class="price-npr">
                                    <i class="fas fa-rupee-sign"></i> <?php echo number_format($item_total, 2); ?>
                                </strong>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <!-- Subtotal -->
                        <tr>
                            <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                            <td><strong><i class="fas fa-rupee-sign"></i> <?php echo number_format($subtotal, 2); ?></strong></td>
                        </tr>
                        <!-- VAT (13%) -->
                        <tr>
                            <td colspan="3" class="text-end"><strong>VAT (13%):</strong></td>
                            <td><i class="fas fa-rupee-sign"></i> <?php echo number_format($subtotal * 0.13, 2); ?></td>
                        </tr>
                        <!-- Delivery Charge -->
                        <tr>
                            <td colspan="3" class="text-end"><strong>Delivery Charge:</strong></td>
                            <td>
                                <i class="fas fa-rupee-sign"></i> <?php echo number_format($delivery_charge, 2); ?>
                            </td>
                        </tr>
                        <!-- Grand Total -->
                        <tr class="table-primary">
                            <td colspan="3" class="text-end"><strong>Total Amount:</strong></td>
                            <td>
                                <span class="total-amount">
                                    <i class="fas fa-rupee-sign"></i> <?php echo number_format($subtotal + ($subtotal * 0.13) + $delivery_charge, 2); ?>
                                </span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
        
        <!-- Legacy Order Timeline (Keep for backward compatibility) -->
        <div class="order-details-card">
            <h6 class="mb-3 text-primary"><i class="fas fa-history me-2"></i>Order Status Timeline</h6>
            <div class="timeline">
                <!-- Order Placed -->
                <div class="timeline-item active">
                    <div class="timeline-title">Order Placed</div>
                    <div class="timeline-time"><?php echo date('F j, Y, g:i a', strtotime($order['order_date'])); ?></div>
                    <div class="timeline-desc">Order has been received and confirmed</div>
                </div>
                
                <!-- Processing -->
                <?php if($order['status'] != 'pending' && $order['status'] != 'cancelled'): ?>
                <div class="timeline-item <?php echo $order['status'] == 'processing' ? 'active' : 'completed'; ?>">
                    <div class="timeline-title">Order Processing</div>
                    <div class="timeline-time"><?php echo date('F j, Y, g:i a', strtotime($order['order_date'] . ' +2 hours')); ?></div>
                    <div class="timeline-desc">Order is being processed and prepared for delivery</div>
                </div>
                <?php endif; ?>
                
                <!-- Completed -->
                <?php if($order['status'] == 'completed'): ?>
                <div class="timeline-item completed">
                    <div class="timeline-title">Order Completed</div>
                    <div class="timeline-time"><?php echo date('F j, Y, g:i a', strtotime($order['order_date'] . ' +3 days')); ?></div>
                    <div class="timeline-desc text-success">
                        <i class="fas fa-check-circle"></i> Order has been delivered successfully
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Cancelled -->
                <?php if($order['status'] == 'cancelled'): ?>
                <div class="timeline-item cancelled">
                    <div class="timeline-title">Order Cancelled</div>
                    <div class="timeline-time"><?php echo date('F j, Y, g:i a'); ?></div>
                    <div class="timeline-desc text-danger">
                        <i class="fas fa-times-circle"></i> Order has been cancelled
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="d-flex flex-wrap gap-2 mt-3 no-print">
            <?php if($order['status'] == 'pending'): ?>
                <button onclick="cancelOrder(<?php echo $order_id; ?>)" class="btn-cancel-order">
                    <i class="fas fa-times"></i> Cancel Order
                </button>
            <?php endif; ?>
            
            <?php if($order['status'] != 'cancelled'): ?>
                <a href="track_order.php?id=<?php echo $order_id; ?>" class="btn-track">
                    <i class="fas fa-truck"></i> Track Order
                </a>
            <?php endif; ?>
            
            <a href="invoice.php?id=<?php echo $order_id; ?>" target="_blank" class="btn-invoice">
                <i class="fas fa-file-invoice"></i> View Invoice
            </a>
            
            <a href="invoice.php?id=<?php echo $order_id; ?>&print=1" target="_blank" class="btn-print-invoice">
                <i class="fas fa-print"></i> Print Invoice
            </a>
            
            <?php if($order['status'] == 'completed'): ?>
                <button onclick="reorder(<?php echo $order_id; ?>)" class="btn btn-success">
                    <i class="fas fa-redo"></i> Reorder
                </button>
            <?php endif; ?>
            
            <?php if(!$is_modal): ?>
                <button onclick="window.history.back()" class="btn-secondary-custom">
                    <i class="fas fa-arrow-left"></i> Back to Orders
                </button>
            <?php endif; ?>
        </div>
        
        <!-- Support Section -->
        <div class="order-details-card no-print mt-3">
            <div class="text-center">
                <i class="fas fa-headset text-primary" style="font-size: 1.5rem;"></i>
                <h6 class="mt-2">Need Help with Your Order?</h6>
                <p class="text-muted small">
                    For any queries regarding your order, please contact our support team.
                </p>
                <div class="d-flex justify-content-center gap-3 flex-wrap">
                    <a href="#" class="btn btn-link text-primary">
                        <i class="fas fa-envelope"></i> support@shopverse.com
                    </a>
                    <span class="text-muted">|</span>
                    <a href="#" class="btn btn-link text-primary">
                        <i class="fas fa-phone"></i> +977-1-2345678
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <?php if(!$is_modal): ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <?php endif; ?>
    
    <script>
        // Cancel Order Function
        function cancelOrder(orderId) {
            if (!confirm('Are you sure you want to cancel this order? This action cannot be undone.')) {
                return;
            }
            
            const btn = document.querySelector('.btn-cancel-order');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cancelling...';
            
            fetch('cancel_order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `order_id=${orderId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Failed to cancel order. Please try again.');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-times"></i> Cancel Order';
                }
            })
            .catch(error => {
                alert('Error cancelling order. Please try again.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-times"></i> Cancel Order';
            });
        }
        
        // Reorder Function
        function reorder(orderId) {
            if (!confirm('Do you want to add all items from this order to your cart?')) {
                return;
            }
            
            const btn = document.querySelector('.btn-success');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            
            fetch('reorder.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `order_id=${orderId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'cart.php';
                } else {
                    alert(data.message || 'Failed to reorder. Please try again.');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-redo"></i> Reorder';
                }
            })
            .catch(error => {
                alert('Error reordering. Please try again.');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-redo"></i> Reorder';
            });
        }
        
        // Auto-refresh tracking updates every 60 seconds if order is active
        <?php if($order['status'] == 'processing' || $order['status'] == 'pending'): ?>
        setTimeout(function() {
            location.reload();
        }, 60000);
        <?php endif; ?>
    </script>
</body>
</html> 