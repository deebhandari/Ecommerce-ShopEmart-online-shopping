<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    exit('Unauthorized');
}
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($order_id > 0) {
    // Get order info
    $stmt = $db->prepare("SELECT o.*, u.name as customer_name, u.email, u.phone, u.address 
                          FROM orders o 
                          JOIN users u ON o.user_id = u.id 
                          WHERE o.id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get order items
    $stmt = $db->prepare("SELECT oi.*, p.name as product_name, p.image 
                          FROM order_items oi 
                          JOIN products p ON oi.product_id = p.id 
                          WHERE oi.order_id = ?");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if ($order):
?>
<!DOCTYPE html>
<html>
<head>
    <style>
        .order-details-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            color: white;
        }
        .order-info-grid {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            font-weight: 600;
            color: #555;
        }
        .info-value {
            color: #333;
        }
        .badge-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .badge-pending { background: #f39c12; color: white; }
        .badge-processing { background: #3498db; color: white; }
        .badge-completed { background: #2ecc71; color: white; }
        .badge-cancelled { background: #e74c3c; color: white; }
        .badge-paid { background: #2ecc71; color: white; }
        .badge-cod_pending { background: #fd7e14; color: white; }
        .badge-pending-payment { background: #f39c12; color: white; }
        .product-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 10px;
        }
        .total-amount {
            font-size: 1.5rem;
            font-weight: bold;
            color: #667eea;
        }
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline-item {
            position: relative;
            padding-bottom: 20px;
        }
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -20px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #667eea;
        }
        .timeline-item::after {
            content: '';
            position: absolute;
            left: -15px;
            top: 17px;
            width: 2px;
            height: calc(100% - 10px);
            background: #e0e0e0;
        }
        .timeline-item:last-child::after {
            display: none;
        }
        .timeline-date {
            font-size: 11px;
            color: #999;
        }
    </style>
</head>
<body>
    <!-- Header Card -->
    <div class="order-details-card">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-receipt fa-2x mb-2"></i>
                <h3 class="mb-0">Order #<?php echo htmlspecialchars($order['order_number']); ?></h3>
                <p class="mb-0 opacity-75">Placed on <?php echo date('F j, Y', strtotime($order['order_date'])); ?></p>
            </div>
            <div class="text-end">
                <div class="mb-2">
                    <span class="badge-status badge-<?php echo $order['status']; ?>">
                        <i class="fas <?php echo $order['status'] == 'processing' ? 'fa-spinner fa-pulse' : ($order['status'] == 'completed' ? 'fa-check-circle' : 'fa-clock'); ?>"></i>
                        <?php echo ucfirst($order['status']); ?>
                    </span>
                </div>
                <small class="opacity-75"><?php echo date('h:i A', strtotime($order['order_date'])); ?></small>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Order Information -->
        <div class="col-md-6">
            <div class="order-info-grid">
                <h5 class="mb-3">
                    <i class="fas fa-info-circle text-primary"></i> Order Information
                </h5>
                <div class="info-row">
                    <span class="info-label">Order Number:</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['order_number']); ?></span>
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
                            <i class="fas fa-wallet text-info"></i> eSewa
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Payment Status:</span>
                    <span class="info-value">
                        <?php if($order['payment_method'] == 'cod'): ?>
                            <?php if($order['status'] == 'completed'): ?>
                                <span class="badge-status badge-paid"><i class="fas fa-check-circle"></i> Paid on Delivery</span>
                            <?php else: ?>
                                <span class="badge-status badge-cod_pending"><i class="fas fa-clock"></i> Pending (COD)</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="badge-status <?php echo $order['payment_status'] == 'paid' ? 'badge-paid' : 'badge-pending-payment'; ?>">
                                <?php echo ucfirst($order['payment_status']); ?>
                            </span>
                        <?php endif; ?>
                    </span>
                </div>
                <?php if($order['esewa_txn_id']): ?>
                <div class="info-row">
                    <span class="info-label">Transaction ID:</span>
                    <span class="info-value"><code><?php echo htmlspecialchars($order['esewa_txn_id']); ?></code></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Customer Information -->
        <div class="col-md-6">
            <div class="order-info-grid">
                <h5 class="mb-3">
                    <i class="fas fa-user text-primary"></i> Customer Information
                </h5>
                <div class="info-row">
                    <span class="info-label">Full Name:</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email Address:</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['email']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone Number:</span>
                    <span class="info-value"><?php echo htmlspecialchars($order['phone']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Shipping Address:</span>
                    <span class="info-value"><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Order Items -->
    <div class="order-info-grid">
        <h5 class="mb-3">
            <i class="fas fa-boxes text-primary"></i> Order Items
        </h5>
        <div class="table-responsive">
            <table class="table table-hover">
                <thead class="table-light">
                    <tr>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($items as $item): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <?php if(!empty($item['image']) && file_exists("../uploads/" . $item['image'])): ?>
                                    <img src="../uploads/<?php echo $item['image']; ?>" class="product-image me-3" alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                <?php else: ?>
                                    <div class="product-image bg-light d-flex align-items-center justify-content-center me-3">
                                        <i class="fas fa-box-open text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                <strong><?php echo htmlspecialchars($item['product_name']); ?></strong>
                            </div>
                        </td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td>$<?php echo number_format($item['price'], 2); ?></td>
                        <td><strong>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-active">
                        <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                        <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                    </tr>
                    <tr class="table-active">
                        <td colspan="3" class="text-end"><strong>Shipping:</strong></td>
                        <td><span class="text-success">FREE</span></td>
                    </tr>
                    <?php if($order['payment_method'] != 'cod'): ?>
                    <tr class="table-active">
                        <td colspan="3" class="text-end"><strong>Tax (13% VAT):</strong></td>
                        <td>$<?php echo number_format($order['total_amount'] * 0.13, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="table-primary">
                        <td colspan="3" class="text-end"><strong>Total Amount:</strong></td>
                        <td>
                            <strong class="total-amount">$<?php echo number_format($order['total_amount'], 2); ?></strong>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    
    <!-- Order Timeline -->
    <div class="order-info-grid">
        <h5 class="mb-3">
            <i class="fas fa-history text-primary"></i> Order Timeline
        </h5>
        <div class="timeline">
            <div class="timeline-item">
                <div><strong>Order Placed</strong></div>
                <div class="timeline-date"><?php echo date('F j, Y, g:i a', strtotime($order['order_date'])); ?></div>
                <div class="small text-muted">Order has been received and is pending confirmation</div>
            </div>
            
            <?php if($order['status'] != 'pending'): ?>
            <div class="timeline-item">
                <div><strong>Order Processing</strong></div>
                <div class="timeline-date"><?php echo date('F j, Y, g:i a', strtotime($order['order_date'] . ' +1 hour')); ?></div>
                <div class="small text-muted">Order is being processed and prepared for shipping</div>
            </div>
            <?php endif; ?>
            
            <?php if($order['status'] == 'completed'): ?>
            <div class="timeline-item">
                <div><strong>Order Completed</strong></div>
                <div class="timeline-date"><?php echo date('F j, Y, g:i a', strtotime($order['order_date'] . ' +2 days')); ?></div>
                <div class="small text-muted">Order has been delivered successfully</div>
            </div>
            <?php endif; ?>
            
            <?php if($order['status'] == 'cancelled'): ?>
            <div class="timeline-item">
                <div><strong>Order Cancelled</strong></div>
                <div class="timeline-date"><?php echo date('F j, Y, g:i a'); ?></div>
                <div class="small text-danger">Order has been cancelled</div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Action Buttons -->
    <div class="mt-3 text-end">
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm me-2">
            <i class="fas fa-print"></i> Print Invoice
        </button>
        <button class="btn btn-primary btn-sm" onclick="window.location.href='orders.php'">
            <i class="fas fa-arrow-left"></i> Back to Orders
        </button>
    </div>
</body>
</html>
<?php
    else:
        echo '<div class="alert alert-danger text-center py-5">
                <i class="fas fa-exclamation-circle fa-3x mb-3 d-block"></i>
                <h5>Order Not Found</h5>
                <p>The order you are looking for does not exist.</p>
              </div>';
    endif;
} else {
    echo '<div class="alert alert-danger text-center py-5">
            <i class="fas fa-exclamation-triangle fa-3x mb-3 d-block"></i>
            <h5>Invalid Order ID</h5>
            <p>Please provide a valid order ID.</p>
          </div>';
}
?>