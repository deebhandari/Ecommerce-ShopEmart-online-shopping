<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized');
}

$database = new Database();
$db = $database->getConnection();

$order_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

$query = "SELECT oi.*, p.name FROM order_items oi 
          JOIN products p ON oi.product_id = p.id 
          WHERE oi.order_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$order_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<table class="table">
    <thead>
        <tr><th>Product</th><th>Quantity</th><th>Price</th><th>Total</th></tr>
    </thead>
    <tbody>
        <?php foreach($items as $item): ?>
        <tr>
            <td><?php echo htmlspecialchars($item['name']); ?></td>
            <td><?php echo $item['quantity']; ?></td>
            <td>$<?php echo number_format($item['price'], 2); ?></td>
            <td>$<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>