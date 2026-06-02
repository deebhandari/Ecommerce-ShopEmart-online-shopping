orders.php     <?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'customer') {
    header("Location: ../login.php");
    exit();
}
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

$query = "SELECT o.*, 
          (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) as item_count
          FROM orders o WHERE o.user_id = ? 
          ORDER BY o.order_date DESC";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - ShopVerse</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .orders-container {
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }
        
        .badge-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-pending { background: #f39c12; color: white; }
        .badge-processing { background: #3498db; color: white; }
        .badge-completed { background: #2ecc71; color: white; }
        .badge-cancelled { background: #e74c3c; color: white; }
        
        .badge-paid { background: #2ecc71; color: white; }
        .badge-pending-payment { background: #f39c12; color: white; }
        
        .payment-info {
            font-size: 12px;
            margin-top: 5px;
        }
        
        .btn-view {
            background: #3498db;
            color: white;
            padding: 5px 12px;
            border-radius: 8px;
            font-size: 12px;
            border: none;
        }
        
        .btn-view:hover {
            background: #2980b9;
            color: white;
        }
        
        @media (max-width: 768px) {
            .orders-container {
                padding: 15px;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="../index.php">ShopVerse</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="shop.php">Shop</a>
                <a class="nav-link active" href="orders.php">Orders</a>
                <a class="nav-link" href="../logout.php">Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="container mt-4">
        <div class="orders-container">
            <h2 class="mb-4"><i class="fas fa-history text-primary"></i> My Orders</h2>
            
            <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> 
                    <?php 
                    if(isset($_SESSION['order_success'])) {
                        echo $_SESSION['order_success'];
                        unset($_SESSION['order_success']);
                    } else {
                        echo "Order placed successfully!";
                    }
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if(count($orders) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Order #</th>
                                <th>Date</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Payment</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($orders as $order): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo $order['order_number']; ?></strong>
                                    </td>
                                    <td><?php echo date('M d, Y H:i', strtotime($order['order_date'])); ?></td>
                                    <td><?php echo $order['item_count']; ?></td>
                                    <td><strong>$<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                    <td>
                                        <?php if($order['payment_method'] == 'cod'): ?>
                                            <?php if($order['payment_status'] == 'pending'): ?>
                                                <span class="badge-status badge-pending-payment">
                                                    <i class="fas fa-money-bill-wave"></i> Pending (Pay on Delivery)
                                                </span>
                                                <div class="payment-info text-muted">
                                                    <small>You will pay when order arrives</small>
                                                </div>
                                            <?php else: ?>
                                                <span class="badge-status badge-paid">
                                                    <i class="fas fa-check-circle"></i> Paid
                                                </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge-status <?php echo $order['payment_status'] == 'paid' ? 'badge-paid' : 'badge-pending-payment'; ?>">
                                                <?php echo ucfirst($order['payment_status']); ?>
                                            </span>
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
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                    <h4>No orders yet</h4>
                    <p>You haven't placed any orders yet.</p>
                    <a href="shop.php" class="btn btn-primary">Start Shopping</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Order Details Modal -->
    <div class="modal fade" id="orderModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-receipt"></i> Order Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="orderDetailsContent">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary"></div>
                        <p class="mt-2">Loading order details...</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewOrderDetails(orderId) {
            document.getElementById('orderDetailsContent').innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-2">Loading order details...</p>
                </div>
            `;
            
            fetch(`order_details.php?id=${orderId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('orderDetailsContent').innerHTML = data;
                    new bootstrap.Modal(document.getElementById('orderModal')).show();
                })
                .catch(error => {
                    document.getElementById('orderDetailsContent').innerHTML = '<div class="alert alert-danger">Error loading order details</div>';
                });
        }
    </script>
</body>
</html>