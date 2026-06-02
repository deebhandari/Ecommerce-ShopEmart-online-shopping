<?php
session_start();
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$cart_id = $_GET['cart_id'];

$stmt = $db->prepare("DELETE FROM cart WHERE id = ?");
$stmt->execute([$cart_id]);

header("Location: cart.php");
exit();
?>