<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Update order status
if (isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $status = $_POST['status'];
    
    $stmt = $db->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$status, $order_id]);
    
    // If order is completed, also mark payment as completed for COD orders
    if ($status == 'completed') {
        $stmt = $db->prepare("UPDATE orders SET payment_status = 'completed' WHERE id = ? AND payment_method = 'cod'");
        $stmt->execute([$order_id]);
    }
    
    $_SESSION['success'] = "Order status updated successfully!";
    header("Location: orders.php");
    exit();
}

// Delete order
if (isset($_GET['delete'])) {
    $order_id = $_GET['delete'];
    
    $stmt = $db->prepare("DELETE FROM order_items WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $stmt = $db->prepare("DELETE FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    
    $_SESSION['success'] = "Order deleted successfully!";
    header("Location: orders.php");
    exit();
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$payment_filter = isset($_GET['payment']) ? $_GET['payment'] : '';

// Build query with filters
$sql = "SELECT o.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        WHERE 1=1";
$params = [];

if ($status_filter && $status_filter != 'all') {
    $sql .= " AND o.status = ?";
    $params[] = $status_filter;
}

if ($payment_filter && $payment_filter != 'all') {
    $sql .= " AND o.payment_method = ?";
    $params[] = $payment_filter;
}

if ($search) {
    $sql .= " AND (o.order_number LIKE ? OR u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY o.order_date DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get order statistics
$total_orders = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pending_orders = $db->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$processing_orders = $db->query("SELECT COUNT(*) FROM orders WHERE status = 'processing'")->fetchColumn();
$completed_orders = $db->query("SELECT COUNT(*) FROM orders WHERE status = 'completed'")->fetchColumn();
$cancelled_orders = $db->query("SELECT COUNT(*) FROM orders WHERE status = 'cancelled'")->fetchColumn();
$total_revenue = $db->query("SELECT SUM(total_amount) FROM orders WHERE status = 'completed'")->fetchColumn() ?: 0;
$cod_pending = $db->query("SELECT SUM(total_amount) FROM orders WHERE payment_method = 'cod' AND status != 'completed'")->fetchColumn() ?: 0;

$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : '';
unset($_SESSION['success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - ShopEMart Admin</title>
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
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
        }
        
        /* Modern Sidebar */
        .sidebar {
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            position: sticky;
            top: 0;
            transition: all 0.3s;
            box-shadow: 5px 0 20px rgba(0,0,0,0.1);
        }
        
        .sidebar h4 {
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 15px;
            font-weight: 600;
            letter-spacing: 1px;
        }
        
        .sidebar a {
            color: #bdc3c7;
            transition: all 0.3s;
            border-left: 3px solid transparent;
            padding: 12px 20px;
            display: block;
            text-decoration: none;
            margin: 5px 0;
            border-radius: 0 10px 10px 0;
        }
        
        .sidebar a:hover {
            background: rgba(102,126,234,0.2);
            color: white;
            border-left-color: #667eea;
            transform: translateX(5px);
        }
        
        .sidebar a.active {
            background: linear-gradient(90deg, rgba(102,126,234,0.3) 0%, rgba(102,126,234,0) 100%);
            color: white;
            border-left-color: #667eea;
        }
        
        .sidebar a i {
            width: 30px;
            margin-right: 10px;
        }
        
        /* Modern Stats Cards */
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--color);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 35px rgba(0,0,0,0.15);
        }
        
        .stat-card .icon {
            position: absolute;
            right: 20px;
            bottom: 20px;
            font-size: 3rem;
            opacity: 0.15;
        }
        
        .stat-card.total { --color: #3498db; }
        .stat-card.pending { --color: #f39c12; }
        .stat-card.processing { --color: #3498db; }
        .stat-card.completed { --color: #2ecc71; }
        .stat-card.cancelled { --color: #e74c3c; }
        .stat-card.revenue { --color: #9b59b6; }
        
        .stat-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            background: linear-gradient(135deg, var(--color), #333);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-card p {
            color: #666;
            margin: 5px 0 0;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        /* Filter Bar */
        .filter-bar {
            background: white;
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
        }
        
        .btn-filter {
            border-radius: 50px;
            padding: 8px 20px;
            margin: 0 3px;
            transition: all 0.3s;
            font-weight: 500;
        }
        
        .btn-filter:hover {
            transform: translateY(-2px);
        }
        
        .btn-filter.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
        }
        
        /* Table Container */
        .table-container {
            background: white;
            border-radius: 20px;
            padding: 0;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            font-weight: 500;
            border: none;
        }
        
        .table tbody tr {
            transition: all 0.3s;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .table tbody tr:hover {
            background: #f8f9ff;
            transform: scale(1.01);
        }
        
        /* Badges */
        .badge-status {
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            letter-spacing: 0.5px;
        }
        
        .badge-pending { background: #f39c12; color: white; }
        .badge-processing { background: #3498db; color: white; }
        .badge-completed { background: #2ecc71; color: white; }
        .badge-cancelled { background: #e74c3c; color: white; }
        .badge-paid { background: #2ecc71; color: white; }
        .badge-pending-payment { background: #f39c12; color: white; }
        .badge-cod { background: linear-gradient(135deg, #fd7e14, #e67e22); color: white; }
        .badge-esewa { background: linear-gradient(135deg, #0B4F6C, #0a3d52); color: white; }
        
        /* Buttons */
        .btn-view {
            background: #3498db;
            color: white;
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 11px;
            border: none;
            transition: all 0.3s;
        }
        
        .btn-view:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .btn-update {
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 11px;
            border: none;
            transition: all 0.3s;
        }
        
        .btn-update:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46,204,113,0.3);
        }
        
        .btn-delete {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 11px;
            border: none;
            transition: all 0.3s;
        }
        
        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231,76,60,0.3);
        }
        
        .status-select {
            padding: 5px 10px;
            border-radius: 50px;
            border: 1px solid #ddd;
            font-size: 11px;
            font-weight: 500;
            background: white;
            cursor: pointer;
        }
        
        /* Modal */
        .modal-content {
            border-radius: 20px;
            overflow: hidden;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 20px 25px;
        }
        
        .modal-body {
            padding: 25px;
        }
        
        /* Animation */
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
        
        .animate-fade {
            animation: fadeInUp 0.5s ease forwards;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                min-height: auto;
                position: relative;
            }
            .stat-card h3 {
                font-size: 1.3rem;
            }
            .table-container {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar p-0">
                <h4 class="text-white text-center py-3">
                    <i class="fas fa-store"></i> ShopEMart
                </h4>
                <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="users.php"><i class="fas fa-users"></i> Users</a>
                <a href="products.php"><i class="fas fa-box"></i> Products</a>
                <a href="orders.php" class="active"><i class="fas fa-shopping-cart"></i> Orders</a>
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <!-- Header -->
                <div class="d-flex justify-content-between align-items-center mb-4 animate-fade">
                    <div>
                        <h2 class="mb-0">
                            <i class="fas fa-shopping-cart text-primary"></i> Manage Orders
                        </h2>
                        <p class="text-muted mt-2">View and manage all customer orders</p>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-primary px-3 py-2">
                            <i class="fas fa-chart-line"></i> Live Dashboard
                        </span>
                    </div>
                </div>
                
                <!-- Success Message -->
                <?php if($success_message): ?>
                    <div class="alert alert-success alert-dismissible fade show animate-fade" role="alert">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="row animate-fade">
                    <div class="col-md-2">
                        <div class="stat-card total">
                            <h3><?php echo $total_orders; ?></h3>
                            <p><i class="fas fa-chart-line"></i> Total Orders</p>
                            <div class="icon"><i class="fas fa-shopping-cart"></i></div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card pending">
                            <h3><?php echo $pending_orders; ?></h3>
                            <p><i class="fas fa-clock"></i> Pending</p>
                            <div class="icon"><i class="fas fa-hourglass-half"></i></div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card processing">
                            <h3><?php echo $processing_orders; ?></h3>
                            <p><i class="fas fa-spinner fa-pulse"></i> Processing</p>
                            <div class="icon"><i class="fas fa-cog"></i></div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card completed">
                            <h3><?php echo $completed_orders; ?></h3>
                            <p><i class="fas fa-check-circle"></i> Completed</p>
                            <div class="icon"><i class="fas fa-truck"></i></div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card cancelled">
                            <h3><?php echo $cancelled_orders; ?></h3>
                            <p><i class="fas fa-times-circle"></i> Cancelled</p>
                            <div class="icon"><i class="fas fa-ban"></i></div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card revenue">
                            <h3>RS<?php echo number_format($total_revenue, 2); ?></h3>
                            <p><i class="fas fa-rupee-sign"></i> Revenue</p>
                            <div class="icon"><i class="fas fa-chart-line"></i></div>
                        </div>
                    </div>
                </div>
                
                <!-- Filter Bar -->
                <div class="filter-bar animate-fade">
                    <div class="row align-items-center">
                        <div class="col-md-5 mb-2 mb-md-0">
                            <label class="text-muted small mb-1">Filter by Status</label>
                            <div class="btn-group flex-wrap">
                                <a href="orders.php" class="btn btn-filter btn-sm <?php echo !$status_filter ? 'active' : ''; ?>">All</a>
                                <a href="?status=pending" class="btn btn-filter btn-sm <?php echo $status_filter == 'pending' ? 'active' : ''; ?>">Pending</a>
                                <a href="?status=processing" class="btn btn-filter btn-sm <?php echo $status_filter == 'processing' ? 'active' : ''; ?>">Processing</a>
                                <a href="?status=completed" class="btn btn-filter btn-sm <?php echo $status_filter == 'completed' ? 'active' : ''; ?>">Completed</a>
                                <a href="?status=cancelled" class="btn btn-filter btn-sm <?php echo $status_filter == 'cancelled' ? 'active' : ''; ?>">Cancelled</a>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2 mb-md-0">
                            <label class="text-muted small mb-1">Filter by Payment</label>
                            <div class="btn-group flex-wrap">
                                <a href="orders.php" class="btn btn-filter btn-sm <?php echo !$payment_filter ? 'active' : ''; ?>">All</a>
                                <a href="?payment=esewa" class="btn btn-filter btn-sm <?php echo $payment_filter == 'esewa' ? 'active' : ''; ?>"><i class="fas fa-wallet"></i> eSewa</a>
                                <a href="?payment=cod" class="btn btn-filter btn-sm <?php echo $payment_filter == 'cod' ? 'active' : ''; ?>"><i class="fas fa-money-bill-wave"></i> COD</a>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="text-muted small mb-1">Search Orders</label>
                            <form method="GET" class="d-flex">
                                <?php if($status_filter): ?>
                                    <input type="hidden" name="status" value="<?php echo $status_filter; ?>">
                                <?php endif; ?>
                                <?php if($payment_filter): ?>
                                    <input type="hidden" name="payment" value="<?php echo $payment_filter; ?>">
                                <?php endif; ?>
                                <div class="input-group">
                                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Order #, Customer..." value="<?php echo htmlspecialchars($search); ?>">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        <i class="fas fa-search"></i>
                                    </button>
                                    <?php if($search): ?>
                                        <a href="orders.php<?php echo $status_filter ? '?status='.$status_filter : ''; ?><?php echo $payment_filter ? '&payment='.$payment_filter : ''; ?>" class="btn btn-secondary btn-sm">
                                            <i class="fas fa-times"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Orders Table -->
                <div class="table-container animate-fade">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><i class="fas fa-hashtag"></i> Order #</th>
                                    <th><i class="fas fa-user"></i> Customer</th>
                                    <th><i class="fas fa-rupee-sign"></i> Total</th>
                                    <th><i class="fas fa-credit-card"></i> Payment</th>
                                    <th><i class="fas fa-check-circle"></i> Payment Status</th>
                                    <th><i class="fas fa-truck"></i> Order Status</th>
                                    <th><i class="fas fa-calendar"></i> Date</th>
                                    <th><i class="fas fa-cog"></i> Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($orders) > 0): ?>
                                    <?php foreach($orders as $order): ?>
                                        <tr>
                                            <td>
                                                <strong>#<?php echo htmlspecialchars(substr($order['order_number'], -10)); ?></strong>
                                             </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($order['customer_email']); ?></small>
                                             </td>
                                            <td><strong>RS<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                            <td>
                                                <?php if($order['payment_method'] == 'cod'): ?>
                                                    <span class="badge-status badge-cod">
                                                        <i class="fas fa-money-bill-wave"></i> COD
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge-status badge-esewa">
                                                        <i class="fas fa-wallet"></i> eSewa
                                                    </span>
                                                <?php endif; ?>
                                             </td>
                                            <td>
                                                <?php if($order['payment_method'] == 'cod'): ?>
                                                    <?php if($order['status'] == 'completed'): ?>
                                                        <span class="badge-status badge-paid">
                                                            <i class="fas fa-check-circle"></i> Paid
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge-status badge-pending-payment">
                                                            <i class="fas fa-clock"></i> Pending
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
                                             </td>
                                            <td>
                                                <?php echo date('M d, Y', strtotime($order['order_date'])); ?>
                                                <br><small class="text-muted"><?php echo date('h:i A', strtotime($order['order_date'])); ?></small>
                                             </td>
                                            <td>
                                                <div class="d-flex flex-wrap gap-1">
                                                    <button class="btn-view btn-sm" onclick="viewOrderDetails(<?php echo $order['id']; ?>)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <form method="POST" style="display:inline-block">
                                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                                        <select name="status" class="status-select" style="width: 100px;">
                                                            <option value="pending" <?php echo $order['status']=='pending'?'selected':''; ?>>Pending</option>
                                                            <option value="processing" <?php echo $order['status']=='processing'?'selected':''; ?>>Processing</option>
                                                            <option value="completed" <?php echo $order['status']=='completed'?'selected':''; ?>>Completed</option>
                                                            <option value="cancelled" <?php echo $order['status']=='cancelled'?'selected':''; ?>>Cancelled</option>
                                                        </select>
                                                        <button type="submit" name="update_status" class="btn-update btn-sm">
                                                            <i class="fas fa-save"></i>
                                                        </button>
                                                    </form>
                                                    <a href="?delete=<?php echo $order['id']; ?>" class="btn-delete btn-sm" 
                                                       onclick="return confirm('⚠️ Delete order #<?php echo $order['order_number']; ?>?\n\nThis action cannot be undone!')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                             </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-5">
                                            <i class="fas fa-inbox fa-4x text-muted mb-3 d-block"></i>
                                            <h5>No orders found</h5>
                                            <p class="text-muted">Try adjusting your filters or search criteria</p>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Order Details Modal -->
    <div class="modal fade" id="orderModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-receipt me-2"></i> Order Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="orderDetailsContent">
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary"></div>
                        <p class="mt-3">Loading order details...</p>
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
                <div class="text-center py-5">
                    <div class="spinner-border text-primary"></div>
                    <p class="mt-3">Loading order details...</p>
                </div>
            `;
            
            fetch(`order_details.php?id=${orderId}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.text();
                })
                .then(data => {
                    document.getElementById('orderDetailsContent').innerHTML = data;
                    const modal = new bootstrap.Modal(document.getElementById('orderModal'));
                    modal.show();
                })
                .catch(error => {
                    document.getElementById('orderDetailsContent').innerHTML = `
                        <div class="alert alert-danger text-center">
                            <i class="fas fa-exclamation-circle fa-2x mb-3 d-block"></i>
                            <h5>Error Loading Order Details</h5>
                            <p>Please try again or refresh the page.</p>
                        </div>
                    `;
                    const modal = new bootstrap.Modal(document.getElementById('orderModal'));
                    modal.show();
                });
        }
    </script>
</body>
</html>