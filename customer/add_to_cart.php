<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'customer') {
    // Check if AJAX request
    $is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Please login to add items to cart',
            'redirect' => '../login.php'
        ]);
        exit();
    }
    
    // Store the product info to add after login
    $_SESSION['pending_product_id'] = isset($_GET['product_id']) ? intval($_GET['product_id']) : (isset($_POST['product_id']) ? intval($_POST['product_id']) : 0);
    $_SESSION['pending_quantity'] = isset($_GET['quantity']) ? intval($_GET['quantity']) : (isset($_POST['quantity']) ? intval($_POST['quantity']) : 1);
    $_SESSION['pending_redirect'] = isset($_GET['redirect']) ? $_GET['redirect'] : (isset($_POST['redirect']) ? $_POST['redirect'] : 'index.php');
    
    header("Location: ../login.php?error=please_login_to_add_to_cart");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get product ID from URL (support both GET and POST)
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : (isset($_POST['product_id']) ? intval($_POST['product_id']) : 0);
$quantity = isset($_GET['quantity']) ? intval($_GET['quantity']) : (isset($_POST['quantity']) ? intval($_POST['quantity']) : 1);
$redirect_page = isset($_GET['redirect']) ? $_GET['redirect'] : (isset($_POST['redirect']) ? $_POST['redirect'] : 'cart.php');

// Ensure quantity is at least 1
if ($quantity < 1) {
    $quantity = 1;
}

// Check if AJAX request
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($product_id > 0) {
    // Check if product exists and has stock
    $stmt = $db->prepare("SELECT id, name, price, stock, image FROM products WHERE id = ? AND status = 'active'");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product) {
        // Validate quantity against stock
        if ($quantity > $product['stock']) {
            $error_msg = "Sorry, only {$product['stock']} {$product['name']}(s) available in stock.";
            
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $error_msg]);
                exit();
            }
            
            $_SESSION['cart_error'] = $error_msg;
            header("Location: " . $redirect_page);
            exit();
        }
        
        $user_id = $_SESSION['user_id'];
        
        // Check if product already in cart
        $stmt = $db->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $product_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $cart_count = 0;
        $message = '';
        
        try {
            if ($existing) {
                // Update quantity - check if new total exceeds stock
                $new_quantity = $existing['quantity'] + $quantity;
                if ($new_quantity > $product['stock']) {
                    $error_msg = "Cannot add {$quantity} more. You already have {$existing['quantity']} in cart. Maximum {$product['stock']} available.";
                    
                    if ($is_ajax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => $error_msg]);
                        exit();
                    }
                    
                    $_SESSION['cart_error'] = $error_msg;
                    header("Location: " . $redirect_page);
                    exit();
                }
                
                $stmt = $db->prepare("UPDATE cart SET quantity = ? WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$new_quantity, $user_id, $product_id]);
                $message = "{$product['name']} quantity updated to {$new_quantity} in cart!";
            } else {
                // Add new item to cart
                $stmt = $db->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $product_id, $quantity]);
                $message = "{$product['name']} added to cart!";
            }
            
            // Get updated cart count
            $stmt = $db->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $cart_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?: 0;
            
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => $message,
                    'cart_count' => (int)$cart_count,
                    'cart_total' => (int)$cart_count,
                    'product_name' => $product['name']
                ]);
                exit();
            }
            
            $_SESSION['cart_success'] = $message;
            $_SESSION['cart_count'] = $cart_count;
            
        } catch (PDOException $e) {
            error_log("Cart Error: " . $e->getMessage());
            $error_msg = "Database error occurred. Please try again.";
            
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $error_msg]);
                exit();
            }
            
            $_SESSION['cart_error'] = $error_msg;
        }
    } else {
        $error_msg = "Product not found or unavailable.";
        
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $error_msg]);
            exit();
        }
        
        $_SESSION['cart_error'] = $error_msg;
    }
} else {
    $error_msg = "Invalid product selected.";
    
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $error_msg]);
        exit();
    }
    
    $_SESSION['cart_error'] = $error_msg;
}

// Redirect back to the page user came from
if (!empty($redirect_page)) {
    header("Location: " . $redirect_page);
} else {
    header("Location: cart.php");
}
exit();
?>