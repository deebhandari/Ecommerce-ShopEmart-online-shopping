<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'customer') {
    header("Location: ../login.php");
    exit();
}
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Get user info
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Get wishlist count
$wishlist_stmt = $db->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
$wishlist_stmt->execute([$user_id]);
$wishlist_count = $wishlist_stmt->fetchColumn() ?: 0;

// Get order statistics with better error handling
try {
    $order_count = $db->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
    $order_count->execute([$user_id]);
    $total_orders = $order_count->fetchColumn() ?: 0;

    $total_spent = $db->prepare("SELECT SUM(total_amount) FROM orders WHERE user_id = ? AND payment_status = 'paid'");
    $total_spent->execute([$user_id]);
    $total_spent_amount = $total_spent->fetchColumn() ?: 0;

    $cart_count = $db->prepare("SELECT SUM(quantity) FROM cart WHERE user_id = ?");
    $cart_count->execute([$user_id]);
    $cart_items = $cart_count->fetchColumn() ?: 0;

    $recent_orders = $db->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY order_date DESC LIMIT 5");
    $recent_orders->execute([$user_id]);
    $recent_orders_list = $recent_orders->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate breakdown for each order
    foreach ($recent_orders_list as &$order) {
        $stmt_items = $db->prepare("SELECT oi.*, p.name as product_name 
                                   FROM order_items oi 
                                   JOIN products p ON oi.product_id = p.id 
                                   WHERE oi.order_id = ?");
        $stmt_items->execute([$order['id']]);
        $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
        
        $subtotal = 0;
        foreach ($items as $item) {
            $subtotal += $item['price'] * $item['quantity'];
        }
        $order['subtotal'] = $subtotal;
        $order['tax'] = $subtotal * 0.13; // 13% VAT
        $order['delivery_charge'] = 150;
        $order['grand_total'] = $subtotal + $order['tax'] + $order['delivery_charge'];
    }

} catch (PDOException $e) {
    // Log error and set default values
    error_log("Dashboard Error: " . $e->getMessage());
    $total_orders = 0;
    $total_spent_amount = 0;
    $cart_items = 0;
    $recent_orders_list = [];
}

// Update profile with CSRF protection
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Security validation failed. Please try again.";
    } else {
        // Update profile
        if (isset($_POST['update_profile'])) {
            $name = trim(htmlspecialchars($_POST['name']));
            $phone = trim(htmlspecialchars($_POST['phone']));
            $address = trim(htmlspecialchars($_POST['address']));
            
            if (empty($name)) {
                $error = "Name is required";
            } elseif (strlen($name) > 100) {
                $error = "Name is too long (max 100 characters)";
            } elseif (!empty($phone) && !preg_match('/^[0-9+\-\s()]{10,15}$/', $phone)) {
                $error = "Invalid phone number format";
            } else {
                try {
                    $stmt = $db->prepare("UPDATE users SET name = ?, phone = ?, address = ? WHERE id = ?");
                    if ($stmt->execute([$name, $phone, $address, $user_id])) {
                        $success = "Profile updated successfully!";
                        // Refresh user data
                        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                        $stmt->execute([$user_id]);
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                        $_SESSION['user_name'] = $user['name'];
                    } else {
                        $error = "Failed to update profile";
                    }
                } catch (PDOException $e) {
                    error_log("Profile Update Error: " . $e->getMessage());
                    $error = "Database error occurred. Please try again.";
                }
            }
        }

        // Change password with better validation
        if (isset($_POST['change_password'])) {
            $current_password = $_POST['current_password'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if (password_verify($current_password, $user['password'])) {
                if ($new_password == $confirm_password) {
                    if (strlen($new_password) >= 8) {
                        // Check password strength
                        if (preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $new_password)) {
                            try {
                                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                                $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                                if ($stmt->execute([$hashed_password, $user_id])) {
                                    $success = "Password changed successfully!";
                                } else {
                                    $error = "Failed to change password";
                                }
                            } catch (PDOException $e) {
                                error_log("Password Change Error: " . $e->getMessage());
                                $error = "Database error occurred. Please try again.";
                            }
                        } else {
                            $error = "Password must contain at least one uppercase letter, one lowercase letter, and one number";
                        }
                    } else {
                        $error = "New password must be at least 8 characters";
                    }
                } else {
                    $error = "New passwords do not match";
                }
            } else {
                $error = "Current password is incorrect";
                // Log failed attempt for security monitoring
                error_log("Failed password change attempt for user ID: " . $user_id);
            }
        }

        // Profile image upload with improved validation
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $filename = $_FILES['profile_image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $file_size = $_FILES['profile_image']['size'];
            
            // Validate file type with mime type check
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $_FILES['profile_image']['tmp_name']);
            finfo_close($finfo);
            
            $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            
            if (!in_array($ext, $allowed) || !in_array($mime_type, $allowed_mimes)) {
                $error = "Invalid file format. Allowed: JPG, PNG, GIF, WEBP";
            } elseif ($file_size > 2 * 1024 * 1024) {
                $error = "File size too large. Max 2MB allowed.";
            } else {
                try {
                    // Create uploads directory if not exists with proper permissions
                    $upload_dir = "../uploads/profile/";
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    // Generate unique filename
                    $image_name = 'profile_' . $user_id . '_' . time() . '.' . $ext;
                    $upload_path = $upload_dir . $image_name;
                    
                    // Optimize image
                    if ($ext !== 'gif') {
                        // Resize and optimize image
                        $image = null;
                        switch ($ext) {
                            case 'jpg':
                            case 'jpeg':
                                $image = imagecreatefromjpeg($_FILES['profile_image']['tmp_name']);
                                break;
                            case 'png':
                                $image = imagecreatefrompng($_FILES['profile_image']['tmp_name']);
                                break;
                            case 'webp':
                                $image = imagecreatefromwebp($_FILES['profile_image']['tmp_name']);
                                break;
                        }
                        
                        if ($image) {
                            // Resize to max 500x500
                            $width = imagesx($image);
                            $height = imagesy($image);
                            $max_size = 500;
                            
                            if ($width > $max_size || $height > $max_size) {
                                $ratio = min($max_size / $width, $max_size / $height);
                                $new_width = $width * $ratio;
                                $new_height = $height * $ratio;
                                
                                $resized = imagecreatetruecolor($new_width, $new_height);
                                imagecopyresampled($resized, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
                                
                                // Save optimized image
                                imagejpeg($resized, $upload_path, 85);
                                imagedestroy($resized);
                            } else {
                                move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path);
                            }
                            imagedestroy($image);
                        } else {
                            move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path);
                        }
                    } else {
                        move_uploaded_file($_FILES['profile_image']['tmp_name'], $upload_path);
                    }
                    
                    // Delete old profile image if exists
                    if (!empty($user['profile_image']) && file_exists($upload_dir . $user['profile_image'])) {
                        unlink($upload_dir . $user['profile_image']);
                    }
                    
                    $stmt = $db->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
                    $stmt->execute([$image_name, $user_id]);
                    $success = "Profile image updated successfully!";
                    
                    // Refresh user data
                    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    error_log("Image Upload Error: " . $e->getMessage());
                    $error = "Failed to upload image. Please try again.";
                }
            }
        }
    }
}

// Get profile image path
$profile_image_path = '';
if (!empty($user['profile_image']) && file_exists("../uploads/profile/" . $user['profile_image'])) {
    $profile_image_path = "../uploads/profile/" . $user['profile_image'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - shopEmart</title>
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
            background: #f1f3f6;
            font-family: 'Poppins', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Navigation */
        .navbar-daraz {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            box-shadow: 0 2px 20px rgba(0,0,0,0.2);
            padding: 0.8rem 0;
        }
        
        .navbar-brand {
            font-size: 1.8rem;
            font-weight: 700;
            color: #ff6600 !important;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .navbar-brand .logo-img {
            height: 45px;
            width: auto;
            border-radius: 8px;
        }
        
        .brand-name {
            color: white;
        }
        
        .brand-name span {
            color: #ff6600;
        }
        
        .nav-link {
            color: white !important;
            font-weight: 500;
            transition: all 0.3s;
            padding: 8px 15px;
            border-radius: 8px;
            position: relative;
        }
        
        .nav-link:hover {
            background: rgba(255,102,0,0.2);
            color: #ff6600 !important;
            transform: translateY(-2px);
        }
        
        .nav-link .badge {
            position: relative;
            top: -8px;
            left: -2px;
        }
        
        /* Toast Notification */
        .toast-notification {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 9999;
            animation: slideInRight 0.3s ease;
            max-width: 350px;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        /* Profile Card */
        .profile-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            overflow: hidden;
            border: none;
            transition: transform 0.3s;
        }
        
        .profile-card:hover {
            transform: translateY(-5px);
        }
        
        .profile-header {
            background: linear-gradient(135deg, #ff6600, #ff8533);
            padding: 30px;
            text-align: center;
            color: white;
            position: relative;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            overflow: hidden;
            position: relative;
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-avatar i {
            font-size: 60px;
            color: #ff6600;
        }
        
        .upload-btn {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0,0,0,0.6);
            padding: 5px;
            color: white;
            font-size: 0.7rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .upload-btn:hover {
            background: rgba(0,0,0,0.8);
        }
        
        .profile-body {
            padding: 25px;
        }
        
        .info-item {
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .info-item i {
            width: 30px;
            color: #ff6600;
        }
        
        /* Stats Cards */
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            transition: all 0.3s;
            border-left: 4px solid;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .stat-card .stat-icon {
            font-size: 2rem;
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            opacity: 0.2;
        }
        
        .stat-card.orders { border-left-color: #3498db; }
        .stat-card.orders .stat-icon { color: #3498db; }
        
        .stat-card.cart { border-left-color: #2ecc71; }
        .stat-card.cart .stat-icon { color: #2ecc71; }
        
        .stat-card.spent { border-left-color: #ff6600; }
        .stat-card.spent .stat-icon { color: #ff6600; }
        
        .stat-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
        }
        
        .stat-card p {
            color: #666;
            margin: 0;
            font-size: 0.85rem;
        }
        
        /* Quick Actions */
        .quick-actions {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        }
        
        .quick-btn {
            padding: 10px 20px;
            border-radius: 50px;
            transition: all 0.3s;
            margin: 5px;
        }
        
        .quick-btn:hover {
            transform: translateY(-2px);
        }
        
        /* Recent Orders */
        .recent-orders-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        }
        
        .badge-status {
            padding: 5px 12px;
            border-radius: 50px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-pending { background: #f39c12; color: white; }
        .badge-processing { background: #3498db; color: white; }
        .badge-completed { background: #2ecc71; color: white; }
        .badge-cancelled { background: #e74c3c; color: white; }
        
        .price-breakdown {
            font-size: 0.75rem;
            color: #6c757d;
            display: block;
            margin-top: 2px;
        }
        
        .price-breakdown .label {
            font-weight: 500;
        }
        
        /* Modal */
        .modal-content {
            border-radius: 16px;
            border: none;
            overflow: hidden;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #ff6600, #ff8533);
            color: white;
            border-radius: 0;
        }
        
        .modal-header .btn-close {
            filter: brightness(0) invert(1);
        }
        
        .btn-save {
            background: linear-gradient(135deg, #ff6600, #ff8533);
            border: none;
            padding: 10px 25px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255,102,0,0.4);
        }
        
        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, #ff6600, #ff8533);
            border-radius: 12px;
            padding: 30px;
            color: white;
            margin-bottom: 30px;
        }
        
        .welcome-banner .btn-light {
            border-radius: 50px;
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .profile-card {
                margin-bottom: 20px;
            }
            .welcome-banner h1 {
                font-size: 1.5rem;
            }
            .stat-card h3 {
                font-size: 1.5rem;
            }
            .navbar-brand {
                font-size: 1.2rem;
            }
            .navbar-brand .logo-img {
                height: 35px;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-daraz">
        <div class="container">
            <a class="navbar-brand" href="../index.php">
                <img src="../assets/images/ss.jpg" alt="ShopVerse Logo" class="logo-img">
                <div class="brand-name">Shop<span>Emart</span></div>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link" href="shop.php">
                            <i class="fas fa-shopping-bag"></i> Shop
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="cart.php">
                            <i class="fas fa-shopping-cart"></i> Cart 
                            <?php if($cart_items > 0): ?>
                                <span class="badge bg-danger rounded-pill ms-1"><?php echo $cart_items; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <!-- Wishlist Link -->
                    <li class="nav-item">
                        <a class="nav-link" href="wishlist.php">
                            <i class="fas fa-heart text-danger"></i> Wishlist
                            <?php if($wishlist_count > 0): ?>
                                <span class="badge bg-danger rounded-pill ms-1"><?php echo $wishlist_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="orders.php">
                            <i class="fas fa-history"></i> Orders
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    
    <!-- Toast Notifications -->
    <?php if($success): ?>
    <div class="toast-notification">
        <div class="alert alert-success alert-dismissible fade show shadow-lg border-0 rounded-3">
            <i class="fas fa-check-circle me-2"></i> <?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    <script>
        setTimeout(() => {
            document.querySelector('.toast-notification')?.remove();
        }, 5000);
    </script>
    <?php endif; ?>
    
    <?php if($error): ?>
    <div class="toast-notification">
        <div class="alert alert-danger alert-dismissible fade show shadow-lg border-0 rounded-3">
            <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
    <script>
        setTimeout(() => {
            document.querySelector('.toast-notification')?.remove();
        }, 5000);
    </script>
    <?php endif; ?>
    
    <div class="container mt-4">
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-hand-peace"></i> Welcome back, <?php echo htmlspecialchars($user['name']); ?>!</h1>
                    <p class="mb-0">We're glad to see you again. Check out new arrivals and exclusive deals!</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="shop.php" class="btn btn-light btn-lg">
                        <i class="fas fa-gift"></i> Shop Now
                    </a>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Left Column - Profile Card with Image Upload -->
            <div class="col-lg-4 mb-4">
                <div class="profile-card">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?php if($profile_image_path): ?>
                                <img src="<?php echo $profile_image_path; ?>" alt="<?php echo htmlspecialchars($user['name']); ?>" loading="lazy">
                            <?php else: ?>
                                <i class="fas fa-user-circle"></i>
                            <?php endif; ?>
                            <!-- Image Upload Overlay -->
                            <form method="POST" enctype="multipart/form-data" id="uploadForm">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <label class="upload-btn" for="profile_image">
                                    <i class="fas fa-camera"></i> Change Photo
                                    <input type="file" name="profile_image" id="profile_image" accept="image/*" style="display: none;" onchange="document.getElementById('uploadForm').submit();">
                                </label>
                            </form>
                        </div>
                        <h3><?php echo htmlspecialchars($user['name']); ?></h3>
                        <p class="mb-0"><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                    <div class="profile-body">
                        <div class="info-item">
                            <i class="fas fa-phone"></i> 
                            <strong>Phone:</strong> <?php echo htmlspecialchars($user['phone'] ?: 'Not provided'); ?>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-map-marker-alt"></i> 
                            <strong>Address:</strong> <?php echo htmlspecialchars($user['address'] ?: 'Not provided'); ?>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-calendar-alt"></i> 
                            <strong>Member Since:</strong> <?php echo date('F j, Y', strtotime($user['created_at'])); ?>
                        </div>
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                                <i class="fas fa-edit"></i> Edit Profile
                            </button>
                            <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                                <i class="fas fa-key"></i> Change Password
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Column - Stats and Actions -->
            <div class="col-lg-8">
                <!-- Stats Row -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="stat-card orders">
                            <div class="stat-icon"><i class="fas fa-shopping-bag"></i></div>
                            <h3><?php echo $total_orders; ?></h3>
                            <p><i class="fas fa-box"></i> Total Orders</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card cart">
                            <div class="stat-icon"><i class="fas fa-shopping-cart"></i></div>
                            <h3><?php echo $cart_items; ?></h3>
                            <p><i class="fas fa-shopping-cart"></i> Items in Cart</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-card spent">
                            <div class="stat-icon"><i class="fas fa-rupee-sign"></i></div>
                            <h3>₹<?php echo number_format($total_spent_amount, 2); ?></h3>
                            <p><i class="fas fa-chart-line"></i> Total Spent</p>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="quick-actions mb-4">
                    <h5 class="mb-3"><i class="fas fa-bolt text-primary"></i> Quick Actions</h5>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="shop.php" class="btn btn-primary quick-btn">
                            <i class="fas fa-search"></i> Continue Shopping
                        </a>
                        <a href="cart.php" class="btn btn-success quick-btn">
                            <i class="fas fa-shopping-cart"></i> View Cart
                        </a>
                        <a href="wishlist.php" class="btn btn-danger quick-btn">
                            <i class="fas fa-heart"></i> My Wishlist
                            <?php if($wishlist_count > 0): ?>
                                <span class="badge bg-light text-dark ms-1"><?php echo $wishlist_count; ?></span>
                            <?php endif; ?>
                        </a>
                        <a href="orders.php" class="btn btn-info quick-btn text-white">
                            <i class="fas fa-truck"></i> Track Orders
                        </a>
                    </div>
                </div>
                
                <!-- Recent Orders -->
                <div class="recent-orders-card">
                    <h5 class="mb-3"><i class="fas fa-clock text-primary"></i> Recent Orders</h5>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Date</th>
                                    <th>Subtotal</th>
                                    <th>VAT (13%)</th>
                                    <th>Delivery</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($recent_orders_list) > 0): ?>
                                    <?php foreach($recent_orders_list as $order): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                            <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                            <td>
                                                ₹<?php echo number_format($order['subtotal'] ?? $order['total_amount'], 2); ?>
                                            </td>
                                            <td>
                                                <span style="font-size: 0.85rem;">
                                                    ₹<?php echo number_format($order['tax'] ?? ($order['total_amount'] * 0.13), 2); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span style="font-size: 0.85rem;">
                                                    ₹<?php echo number_format($order['delivery_charge'] ?? 150, 2); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong>₹<?php echo number_format($order['grand_total'] ?? $order['total_amount'], 2); ?></strong>
                                            </td>
                                            <td>
                                                <span class="badge-status badge-<?php echo htmlspecialchars($order['status']); ?>">
                                                    <?php echo ucfirst(htmlspecialchars($order['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="orders.php?view=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                    View
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">
                                            <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                            No orders yet. <a href="shop.php">Start Shopping</a>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if(count($recent_orders_list) > 0): ?>
                        <div class="text-end mt-3">
                            <a href="orders.php" class="btn btn-link">View All Orders <i class="fas fa-arrow-right"></i></a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Profile Modal -->
    <div class="modal fade" id="editProfileModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-edit"></i> Edit Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email (Cannot be changed)</label>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>" placeholder="+91 98765 43210">
                            <small class="text-muted">Format: Minimum 10 digits</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Shipping Address</label>
                            <textarea name="address" class="form-control" rows="3" placeholder="Enter your full address"><?php echo htmlspecialchars($user['address']); ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_profile" class="btn btn-save text-white">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-key"></i> Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" required>
                            <small class="text-muted">
                                <i class="fas fa-info-circle"></i> 
                                Minimum 8 characters, must contain uppercase, lowercase, and number
                            </small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="change_password" class="btn btn-save text-white">Update Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto hide toast notifications
        setTimeout(() => {
            document.querySelectorAll('.toast-notification').forEach(toast => {
                toast.style.transition = 'opacity 0.5s';
                toast.style.opacity = '0';
                setTimeout(() => toast.remove(), 500);
            });
        }, 5000);
        
        // Profile image upload preview
        document.getElementById('profile_image')?.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Show loading state
                const avatar = document.querySelector('.profile-avatar');
                avatar.innerHTML = '<div class="spinner-border text-white" role="status"><span class="visually-hidden">Loading...</span></div>';
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = document.createElement('img');
                    img.src = e.target.result;
                    img.alt = 'Profile Preview';
                    avatar.innerHTML = '';
                    avatar.appendChild(img);
                };
                reader.onerror = function() {
                    avatar.innerHTML = '<i class="fas fa-user-circle"></i>';
                };
                reader.readAsDataURL(file);
            }
        });
        
        // Form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const password = this.querySelector('input[name="new_password"]');
                const confirm = this.querySelector('input[name="confirm_password"]');
                
                if (password && confirm && password.value !== confirm.value) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                }
            });
        });
    </script>
</body>
</html>