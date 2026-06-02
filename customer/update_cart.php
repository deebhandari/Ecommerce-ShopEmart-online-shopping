<?php
session_start();
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$cart_id = isset($_POST['cart_id']) ? intval($_POST['cart_id']) : 0;
$quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;

if ($cart_id > 0) {
    // Get cart item to check product stock
    $stmt = $db->prepare("SELECT c.*, p.stock FROM cart c 
                          JOIN products p ON c.product_id = p.id 
                          WHERE c.id = ?");
    $stmt->execute([$cart_id]);
    $cart_item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($cart_item) {
        $max_stock = $cart_item['stock'];
        
        // Validate quantity against stock
        if ($quantity > $max_stock) {
            // Auto-correct to max stock
            $quantity = $max_stock;
            $_SESSION['cart_error'] = "Only {$max_stock} items available. Quantity adjusted.";
        }
        
        if ($quantity <= 0) {
            // Remove item if quantity is 0 or negative
            $stmt = $db->prepare("DELETE FROM cart WHERE id = ?");
            $stmt->execute([$cart_id]);
        } else {
            // Update quantity
            $stmt = $db->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
            $stmt->execute([$quantity, $cart_id]);
        }
    }
}

header("Location: cart.php");
exit();
?>
