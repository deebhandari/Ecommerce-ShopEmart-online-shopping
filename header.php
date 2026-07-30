<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ShopEMart- Online Shopping System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
        }
        
        .navbar-nav .nav-link {
            font-weight: 500;
            transition: all 0.3s;
            margin: 0 5px;
        }
        
        .navbar-nav .nav-link:hover {
            color: #667eea !important;
            transform: translateY(-2px);
        }
        
        .navbar-nav .nav-link.active {
            color: #667eea !important;
            background: rgba(102,126,234,0.1);
            border-radius: 8px;
        }
        
        .dropdown-menu {
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: none;
            margin-top: 10px;
        }
        
        .dropdown-item {
            padding: 10px 20px;
            transition: all 0.3s;
        }
        
        .dropdown-item:hover {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .dropdown-item i {
            width: 25px;
        }
        
        .navbar-brand {
            font-size: 1.5rem;
            font-weight: bold;
        }
        
        .cart-badge {
            position: relative;
            top: -8px;
            right: 5px;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 10px;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-store"></i> ShopVerse
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <!-- Home -->
                <li class="nav-item">
                    <a class="nav-link <?php echo (!isset($_GET['page']) || $_GET['page'] == 'home') ? 'active' : ''; ?>" 
                       href="index.php?page=home">
                        <i class="fas fa-home"></i> Home
                    </a>
                </li>
                
                <!-- About -->
                <li class="nav-item">
                    <a class="nav-link <?php echo (isset($_GET['page']) && $_GET['page'] == 'about') ? 'active' : ''; ?>" 
                       href="index.php?page=about">
                        <i class="fas fa-info-circle"></i> About
                    </a>
                </li>
                
                <!-- Products -->
                <li class="nav-item">
                    <a class="nav-link <?php echo (isset($_GET['page']) && $_GET['page'] == 'products') ? 'active' : ''; ?>" 
                       href="index.php?page=products">
                        <i class="fas fa-box-open"></i> Products
                    </a>
                </li>
                
                <!-- Contact -->
                <li class="nav-item">
                    <a class="nav-link <?php echo (isset($_GET['page']) && $_GET['page'] == 'contact') ? 'active' : ''; ?>" 
                       href="index.php?page=contact">
                        <i class="fas fa-envelope"></i> Contact
                    </a>
                </li>
                
                <?php if(isset($_SESSION['user_id'])): ?>
                    <?php if($_SESSION['user_type'] == 'admin'): ?>
                        <!-- Admin Dashboard -->
                        <li class="nav-item">
                            <a class="nav-link" href="admin/dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Admin Dashboard
                            </a>
                        </li>
                    <?php else: ?>
                        <!-- Customer Dropdown -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="customerDropdown" role="button" 
                               data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li>
                                    <a class="dropdown-item" href="customer/dashboard.php">
                                        <i class="fas fa-tachometer-alt"></i> Dashboard
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="customer/shop.php">
                                        <i class="fas fa-shopping-bag"></i> Shop
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="customer/cart.php">
                                        <i class="fas fa-shopping-cart"></i> Cart
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="customer/orders.php">
                                        <i class="fas fa-history"></i> Orders
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <a class="dropdown-item text-danger" href="logout.php">
                                        <i class="fas fa-sign-out-alt"></i> Logout
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- Login Dropdown -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?php echo (basename($_SERVER['PHP_SELF']) == 'login.php') ? 'active' : ''; ?>" 
                           href="#" id="loginDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-sign-in-alt"></i> Login
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="login.php?role=customer">
                                    <i class="fas fa-user"></i> Login as Customer
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="login.php?role=admin">
                                    <i class="fas fa-user-shield"></i> Login as Admin
                                </a>
                            </li>
                        </ul>
                    </li>
                    
                    <!-- Register -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo (basename($_SERVER['PHP_SELF']) == 'register.php') ? 'active' : ''; ?>" 
                           href="register.php">
                            <i class="fas fa-user-plus"></i> Register
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<div class="container mt-4">