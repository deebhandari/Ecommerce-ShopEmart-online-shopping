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
            'message' => 'Please login to add items to wishlist',
            'redirect' => '../login.php'
        ]);
        exit();
    }
    
    // Store the product info to add after login
    $_SESSION['pending_wishlist_product_id'] = isset($_GET['product_id']) ? intval($_GET['product_id']) : (isset($_POST['product_id']) ? intval($_POST['product_id']) : 0);
    $_SESSION['pending_wishlist_redirect'] = isset($_GET['redirect']) ? $_GET['redirect'] : (isset($_POST['redirect']) ? $_POST['redirect'] : 'index.php');
    
    header("Location: ../login.php?error=please_login_to_add_to_wishlist");
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get product ID from URL (support both GET and POST)
$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : (isset($_POST['product_id']) ? intval($_POST['product_id']) : 0);
$redirect_page = isset($_GET['redirect']) ? $_GET['redirect'] : (isset($_POST['redirect']) ? $_POST['redirect'] : 'wishlist.php');

// Check if AJAX request
$is_ajax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if ($product_id > 0) {
    // Check if product exists
    $stmt = $db->prepare("SELECT id, name, price, stock, image FROM products WHERE id = ? AND status = 'active'");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product) {
        $user_id = $_SESSION['user_id'];
        
        // Check if product already in wishlist
        $stmt = $db->prepare("SELECT id FROM wishlist WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $product_id]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $wishlist_count = 0;
        $message = '';
        $action = '';
        
        try {
            if ($existing) {
                // Remove from wishlist (toggle)
                $stmt = $db->prepare("DELETE FROM wishlist WHERE user_id = ? AND product_id = ?");
                $stmt->execute([$user_id, $product_id]);
                $message = "{$product['name']} removed from wishlist!";
                $action = 'removed';
            } else {
                // Add to wishlist
                $stmt = $db->prepare("INSERT INTO wishlist (user_id, product_id, created_at) VALUES (?, ?, NOW())");
                $stmt->execute([$user_id, $product_id]);
                $message = "{$product['name']} added to wishlist!";
                $action = 'added';
            }
            
            // Get updated wishlist count
            $stmt = $db->prepare("SELECT COUNT(*) as total FROM wishlist WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $wishlist_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?: 0;
            
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => true,
                    'message' => $message,
                    'action' => $action,
                    'wishlist_count' => (int)$wishlist_count,
                    'product_id' => $product_id,
                    'product_name' => $product['name']
                ]);
                exit();
            }
            
            $_SESSION['wishlist_success'] = $message;
            $_SESSION['wishlist_count'] = $wishlist_count;
            
        } catch (PDOException $e) {
            error_log("Wishlist Error: " . $e->getMessage());
            $error_msg = "Database error occurred. Please try again.";
            
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $error_msg]);
                exit();
            }
            
            $_SESSION['wishlist_error'] = $error_msg;
        }
    } else {
        $error_msg = "Product not found or unavailable.";
        
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $error_msg]);
            exit();
        }
        
        $_SESSION['wishlist_error'] = $error_msg;
    }
} else {
    $error_msg = "Invalid product selected.";
    
    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $error_msg]);
        exit();
    }
    
    $_SESSION['wishlist_error'] = $error_msg;
}

// Redirect back to the page user came from
if (!empty($redirect_page)) {
    header("Location: " . $redirect_page);
} else {
    header("Location: wishlist.php");
}
exit();