<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Dashboard Statistics
$user_count = $db->query("SELECT COUNT(*) FROM users WHERE user_type='customer'")->fetchColumn();
$product_count = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
$order_count = $db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pending_users = $db->query("SELECT COUNT(*) FROM users WHERE status='pending'")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard - ShopEMart</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

<style>

body{
    background:#f4f6f9;
}

/* Navbar */
.navbar-dark{
    background:#2c3e50 !important;
}

/* Logo */
.logo-img{
    width:55px;
    height:55px;
    object-fit:cover;
    border-radius:50%;
    border:2px solid #fff;
}

.logo-text{
    line-height:1;
    margin-left:10px;
}

.brand-name{
    font-size:28px;
    font-weight:bold;
    color:#fff;
}

.brand-name span{
    color:#ff9800;
}

.brand-tagline{
    color:#ddd;
    font-size:11px;
    letter-spacing:2px;
}

/* Sidebar */
.sidebar{
    min-height:100vh;
    background:#2c3e50;
}

.sidebar a{
    color:#fff;
    text-decoration:none;
    padding:15px 20px;
    display:block;
    transition:.3s;
}

.sidebar a:hover{
    background:#34495e;
    padding-left:30px;
}

/* Cards */
.stats-card{
    border-radius:12px;
    padding:25px;
    color:#fff;
    margin-bottom:20px;
    transition:.3s;
}

.stats-card:hover{
    transform:translateY(-5px);
    box-shadow:0 10px 20px rgba(0,0,0,.2);
}

</style>

</head>

<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark shadow">
    <div class="container-fluid">

        <!-- ShopEMart Logo -->
        <a class="navbar-brand d-flex align-items-center" href="dashboard.php">

            <img src="../assets/images/ss.jpg"
                 class="logo-img"
                 alt="ShopEMart Logo">

            <div class="logo-text">
                <div class="brand-name">
                    Shop<span>EMart</span>
                </div>

                <div class="brand-tagline">
                    SHOP SMARTER
                </div>
            </div>

        </a>

        <div class="ms-auto text-white">
            <i class="fas fa-user-circle"></i>
            Welcome,
            <strong><?php echo $_SESSION['user_name']; ?></strong>
        </div>

    </div>
</nav>

<div class="container-fluid">

<div class="row">

<!-- Sidebar -->

<div class="col-md-2 sidebar p-0">

    <h4 class="text-white text-center py-3">
        Admin Panel
    </h4>

    <a href="dashboard.php">
        <i class="fas fa-tachometer-alt"></i> Dashboard
    </a>

    <a href="users.php">
        <i class="fas fa-users"></i> Users
    </a>

    <a href="products.php">
        <i class="fas fa-box"></i> Products
    </a>

    <a href="orders.php">
        <i class="fas fa-shopping-cart"></i> Orders
    </a>

    <a href="../logout.php">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>

</div>

<!-- Content -->

<div class="col-md-10 p-4">

<h2 class="mb-4">
    Welcome, <?php echo $_SESSION['user_name']; ?>!
</h2>

<div class="row">

<div class="col-md-3">

<div class="stats-card"
style="background:linear-gradient(135deg,#667eea,#764ba2);">

<h2><?php echo $user_count; ?></h2>

<p>Total Customers</p>

</div>

</div>

<div class="col-md-3">

<div class="stats-card"
style="background:linear-gradient(135deg,#f093fb,#f5576c);">

<h2><?php echo $product_count; ?></h2>

<p>Total Products</p>

</div>

</div>

<div class="col-md-3">

<div class="stats-card"
style="background:linear-gradient(135deg,#4facfe,#00f2fe);">

<h2><?php echo $order_count; ?></h2>

<p>Total Orders</p>

</div>

</div>

<div class="col-md-3">

<div class="stats-card"
style="background:linear-gradient(135deg,#fa709a,#fee140);">

<h2><?php echo $pending_users; ?></h2>

<p>Pending Approvals</p>

</div>

</div>

</div>

</div>

</div>

</div>

</body>
</html>