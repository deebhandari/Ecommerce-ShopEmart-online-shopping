<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'customer') {
    header("Location: ../login.php");
    exit();
}
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($order_id <= 0) {
    header("Location: orders.php");
    exit();
}

// Get order details - verify it belongs to the customer
$stmt = $db->prepare("SELECT o.*, u.name as customer_name, u.email, u.phone, u.address 
                      FROM orders o 
                      JOIN users u ON o.user_id = u.id 
                      WHERE o.id = ? AND o.user_id = ?");
$stmt->execute([$order_id, $_SESSION['user_id']]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Order not found or unauthorized access");
}

// Get order items
$stmt = $db->prepare("SELECT oi.*, p.name as product_name 
                      FROM order_items oi 
                      JOIN products p ON oi.product_id = p.id 
                      WHERE oi.order_id = ?");
$stmt->execute([$order_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Store settings
$store_name = "ShopEMart";
$store_address = "Kathmandu, Nepal";
$store_phone = "+977 9800000000";
$store_email = "support@SHOPEMart.com";

// Calculate totals from items
$subtotal = 0;
foreach ($items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

$tax_rate = 0.13; // 13% VAT
$tax = $subtotal * $tax_rate;
$delivery_charge = 150; // Fixed delivery charge
$grand_total = $subtotal + $tax + $delivery_charge;
$payment_method = ucfirst($order['payment_method']);

// Fix: Proper payment status display logic
$payment_status_display = '';
$payment_status_class = '';

if ($order['payment_method'] == 'cod') {
    $payment_status_display = 'Cash on Delivery';
    $payment_status_class = 'status-cod';
} elseif ($order['payment_method'] == 'esewa') {
    if ($order['payment_status'] == 'paid') {
        $payment_status_display = 'Paid via eSewa';
        $payment_status_class = 'status-paid';
    } else {
        $payment_status_display = 'Pending (eSewa)';
        $payment_status_class = 'status-pending';
    }
} elseif ($order['payment_method'] == 'khalti') {
    if ($order['payment_status'] == 'paid') {
        $payment_status_display = 'Paid via Khalti';
        $payment_status_class = 'status-paid';
    } else {
        $payment_status_display = 'Pending (Khalti)';
        $payment_status_class = 'status-pending';
    }
} else {
    $payment_status_display = ucfirst($order['payment_status']);
    $payment_status_class = $order['payment_status'] == 'paid' ? 'status-paid' : 'status-pending';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?php echo $order['order_number']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Print Styles - FIXED for proper size */
        @media print {
            * {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            
            .no-print { display: none !important; }
            .print-only { display: block !important; }
            
            body {
                background: white !important;
                padding: 0 !important;
                margin: 0 !important;
                font-size: 11pt !important;
                line-height: 1.4 !important;
            }
            
            .container {
                max-width: 100% !important;
                padding: 0 !important;
                margin: 0 !important;
            }
            
            .invoice-container {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 20px 25px !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                border: none !important;
                background: white !important;
            }
            
            .invoice-header {
                padding-bottom: 12px !important;
                margin-bottom: 15px !important;
                border-bottom-width: 2px !important;
            }
            
            .invoice-title h2 {
                font-size: 18pt !important;
            }
            
            .invoice-title small {
                font-size: 9pt !important;
            }
            
            .invoice-number h5 {
                font-size: 12pt !important;
            }
            
            .invoice-number p {
                font-size: 9pt !important;
            }
            
            .company-details h5 {
                font-size: 12pt !important;
            }
            
            .company-details p {
                font-size: 9pt !important;
                margin: 1px 0 !important;
            }
            
            .customer-details {
                padding: 10px 15px !important;
                background: #f8f9fa !important;
                border-left-width: 3px !important;
            }
            
            .customer-details h6 {
                font-size: 10pt !important;
            }
            
            .customer-details p {
                font-size: 9pt !important;
                margin: 1px 0 !important;
            }
            
            .table-invoice {
                margin: 15px 0 !important;
                font-size: 10pt !important;
            }
            
            .table-invoice thead th {
                padding: 8px 12px !important;
                font-size: 10pt !important;
                background: #667eea !important;
                color: white !important;
            }
            
            .table-invoice tbody td {
                padding: 6px 12px !important;
                font-size: 10pt !important;
            }
            
            .table-invoice tfoot td {
                padding: 6px 12px !important;
                font-size: 10pt !important;
            }
            
            .grand-total td {
                padding: 8px 12px !important;
                font-size: 12pt !important;
                background: #667eea !important;
                color: white !important;
            }
            
            .breakdown-row td {
                padding: 4px 12px !important;
                font-size: 9pt !important;
            }
            
            .payment-status {
                font-size: 8pt !important;
                padding: 2px 10px !important;
            }
            
            .thank-you-message {
                padding: 15px !important;
                margin-top: 15px !important;
            }
            
            .thank-you-message h5 {
                font-size: 12pt !important;
            }
            
            .thank-you-message p {
                font-size: 9pt !important;
            }
            
            .invoice-footer {
                padding-top: 12px !important;
                margin-top: 15px !important;
                font-size: 8pt !important;
            }
            
            .watermark {
                display: none !important;
            }
            
            .btn-print, .btn-back {
                display: none !important;
            }
            
            /* Ensure table fits on page */
            .table-invoice {
                width: 100% !important;
            }
            
            .table-invoice th, 
            .table-invoice td {
                white-space: nowrap !important;
            }
            
            /* Page break control */
            .invoice-container {
                page-break-after: avoid !important;
            }
        }
        
        /* Screen Styles */
        @media screen {
            .print-only { display: none; }
            body { 
                background: #f1f3f6; 
                padding: 20px; 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            }
        }
        
        .invoice-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            padding: 40px;
            position: relative;
        }
        
        .invoice-header {
            border-bottom: 3px solid #667eea;
            padding-bottom: 20px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .invoice-title h2 {
            color: #667eea;
            font-weight: 700;
            margin: 0;
        }
        
        .invoice-title small {
            color: #999;
            font-size: 0.85rem;
        }
        
        .invoice-number {
            text-align: right;
        }
        
        .invoice-number h5 {
            color: #333;
            font-weight: 600;
        }
        
        .invoice-number p {
            color: #666;
            margin: 0;
            font-size: 0.9rem;
        }
        
        .company-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .company-details h5 {
            color: #667eea;
            font-weight: 700;
        }
        
        .company-details p {
            color: #666;
            margin: 2px 0;
            font-size: 0.9rem;
        }
        
        .customer-details {
            background: #f8f9fa;
            padding: 15px 20px;
            border-radius: 10px;
            border-left: 4px solid #667eea;
            min-width: 250px;
        }
        
        .customer-details h6 {
            color: #667eea;
            font-weight: 600;
        }
        
        .customer-details p {
            margin: 2px 0;
            font-size: 0.9rem;
            color: #555;
        }
        
        .table-invoice {
            margin: 25px 0;
        }
        
        .table-invoice thead th {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 12px 15px;
            font-weight: 500;
            border: none;
        }
        
        .table-invoice tbody td {
            padding: 12px 15px;
            vertical-align: middle;
            border-bottom: 1px solid #eee;
        }
        
        .table-invoice tfoot td {
            padding: 10px 15px;
            font-weight: 600;
        }
        
        .grand-total {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            font-size: 1.2rem;
        }
        
        .grand-total td {
            padding: 15px !important;
        }
        
        .payment-status {
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-paid { 
            background: #d4edda; 
            color: #155724; 
        }
        .status-pending { 
            background: #fff3cd; 
            color: #856404; 
        }
        .status-cod { 
            background: #d1ecf1; 
            color: #0c5460; 
        }
        
        .invoice-footer {
            border-top: 2px solid #eee;
            padding-top: 20px;
            margin-top: 30px;
            text-align: center;
            color: #999;
            font-size: 0.85rem;
        }
        
        .btn-print {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            padding: 10px 25px;
            border-radius: 50px;
            color: white;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102,126,234,0.4);
        }
        
        .btn-back {
            background: #6c757d;
            border: none;
            padding: 10px 25px;
            border-radius: 50px;
            color: white;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(108,117,125,0.4);
        }
        
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 8rem;
            opacity: 0.03;
            pointer-events: none;
            z-index: 0;
            font-weight: 900;
            color: #667eea;
        }
        
        .thank-you-message {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .thank-you-message i {
            color: #667eea;
            font-size: 2rem;
        }
        
        .breakdown-row {
            background: #f8f9fa;
        }
        
        .breakdown-row td {
            padding: 8px 15px !important;
        }
        
        @media (max-width: 768px) {
            .invoice-container { padding: 20px; }
            .invoice-header { flex-direction: column; align-items: flex-start; }
            .invoice-number { text-align: left; margin-top: 10px; }
            .company-info { flex-direction: column; }
            .customer-details { margin-top: 15px; }
        }
    </style>
</head>
<body>
    <div class="watermark">INVOICE</div>
    
    <div class="container">
        <div class="invoice-container">
            <!-- Invoice Header -->
            <div class="invoice-header">
                <div class="invoice-title">
                    <h2><i class="fas fa-file-invoice"></i> INVOICE</h2>
                    <small>Tax Invoice / Receipt</small>
                </div>
                <div class="invoice-number">
                    <h5>#<?php echo $order['order_number']; ?></h5>
                    <p><i class="far fa-calendar-alt"></i> <?php echo date('F j, Y, g:i a', strtotime($order['order_date'])); ?></p>
                </div>
            </div>
            
            <!-- Company & Customer Info -->
            <div class="company-info">
                <div class="company-details">
                    <h5><i class="fas fa-store"></i> <?php echo $store_name; ?></h5>
                    <p><i class="fas fa-map-marker-alt"></i> <?php echo $store_address; ?></p>
                    <p><i class="fas fa-phone"></i> <?php echo $store_phone; ?></p>
                    <p><i class="fas fa-envelope"></i> <?php echo $store_email; ?></p>
                </div>
                <div class="customer-details">
                    <h6><i class="fas fa-user"></i> Bill To:</h6>
                    <p><strong><?php echo htmlspecialchars($order['customer_name']); ?></strong></p>
                    <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($order['email']); ?></p>
                    <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($order['phone']); ?></p>
                    <p><i class="fas fa-map-marker-alt"></i> <?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
                </div>
            </div>
            
            <!-- Order Items Table -->
            <table class="table table-invoice">
                <thead>
                    <tr>
                        <th style="width:5%">#</th>
                        <th style="width:45%">Product</th>
                        <th style="width:15%">Quantity</th>
                        <th style="width:15%">Unit Price</th>
                        <th style="width:20%" class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $i = 1; foreach($items as $item): ?>
                    <tr>
                        <td><?php echo $i++; ?></td>
                        <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                        <td><?php echo $item['quantity']; ?></td>
                        <td>RS <?php echo number_format($item['price'], 2); ?></td>
                        <td class="text-end">RS <?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <!-- Subtotal -->
                    <tr class="breakdown-row">
                        <td colspan="4" class="text-end"><strong>Subtotal:</strong></td>
                        <td class="text-end">RS <?php echo number_format($subtotal, 2); ?></td>
                    </tr>
                    
                    <!-- VAT (13%) -->
                    <tr class="breakdown-row">
                        <td colspan="4" class="text-end"><strong>VAT (13%):</strong></td>
                        <td class="text-end">RS <?php echo number_format($tax, 2); ?></td>
                    </tr>
                    
                    <!-- Delivery Charge -->
                    <tr class="breakdown-row">
                        <td colspan="4" class="text-end"><strong>Delivery Charge:</strong></td>
                        <td class="text-end">RS <?php echo number_format($delivery_charge, 2); ?></td>
                    </tr>
                    
                    <!-- Grand Total -->
                    <tr class="grand-total">
                        <td colspan="4" class="text-end"><strong>Grand Total:</strong></td>
                        <td class="text-end"><strong>RS <?php echo number_format($grand_total, 2); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
            
            <!-- Payment & Status - FIXED with proper display -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <p><strong>Payment Method:</strong> <?php echo $payment_method; ?></p>
                    <p><strong>Payment Status:</strong> 
                        <span class="payment-status <?php echo $payment_status_class; ?>">
                            <?php echo $payment_status_display; ?>
                        </span>
                    </p>
                </div>
                <div class="col-md-6 text-end">
                    <p><strong>Order Status:</strong> 
                        <span class="badge bg-<?php echo $order['status'] == 'completed' ? 'success' : ($order['status'] == 'processing' ? 'info' : ($order['status'] == 'cancelled' ? 'danger' : 'warning')); ?>">
                            <?php echo ucfirst($order['status']); ?>
                        </span>
                    </p>
                    <?php if(!empty($order['esewa_txn_id'])): ?>
                    <p><strong>Transaction ID:</strong> <?php echo htmlspecialchars($order['esewa_txn_id']); ?></p>
                    <?php endif; ?>
                    <?php if(!empty($order['khalti_txn_id'])): ?>
                    <p><strong>Transaction ID:</strong> <?php echo htmlspecialchars($order['khalti_txn_id']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Thank You Message -->
            <div class="thank-you-message">
                <i class="fas fa-heart"></i>
                <h5 class="mt-2">Thank You for Your Purchase!</h5>
                <p class="text-muted">We appreciate your business. Please keep this invoice for your records.</p>
            </div>
            
            <!-- Action Buttons -->
            <div class="text-center mt-4 no-print">
                <button onclick="window.print()" class="btn btn-print">
                    <i class="fas fa-print"></i> Print Invoice
                </button>
                <a href="orders.php" class="btn btn-back">
                    <i class="fas fa-arrow-left"></i> Back to Orders
                </a>
            </div>
            
            <!-- Footer -->
            <div class="invoice-footer">
                <p>Thank you for shopping with <?php echo $store_name; ?>!</p>
                <p class="small">This is a system generated invoice. No signature required.</p>
                <p>&copy; <?php echo date('Y'); ?> <?php echo $store_name; ?>. All rights reserved.</p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto print if parameter is set
        <?php if(isset($_GET['print'])): ?>
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 1000);
        };
        <?php endif; ?>
    </script>
</body>
</html>