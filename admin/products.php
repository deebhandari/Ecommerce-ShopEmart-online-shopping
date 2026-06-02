<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] != 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Handle delete
if (isset($_GET['delete'])) {
    // Get product image to delete file
    $stmt = $db->prepare("SELECT image FROM products WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($product && $product['image'] && file_exists("../uploads/" . $product['image'])) {
        unlink("../uploads/" . $product['image']);
    }
    
    $stmt = $db->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    
    $_SESSION['success'] = "Product deleted successfully!";
    header("Location: products.php");
    exit();
}

// Handle add/edit with image upload
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    $category = $_POST['category'];
    $image_name = null;
    
    // Validate required fields
    if (empty($name) || $price <= 0) {
        $error = "Please fill all required fields correctly.";
    } else {
        // Handle image upload
        if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $filename = $_FILES['image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $file_size = $_FILES['image']['size'];
            
            if (!in_array($ext, $allowed)) {
                $error = "Invalid file format. Allowed: JPG, PNG, GIF, WEBP";
            } elseif ($file_size > 2 * 1024 * 1024) {
                $error = "File size too large. Max 2MB allowed.";
            } else {
                // Create uploads directory if not exists
                if (!file_exists("../uploads")) {
                    mkdir("../uploads", 0777, true);
                }
                
                // Generate unique filename
                $image_name = time() . '_' . rand(1000, 9999) . '.' . $ext;
                $upload_path = "../uploads/" . $image_name;
                
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_path)) {
                    // Image uploaded successfully
                } else {
                    $error = "Failed to upload image.";
                    $image_name = null;
                }
            }
        }
        
        if (!isset($error)) {
            if (isset($_POST['product_id']) && $_POST['product_id'] > 0) {
                // Update existing product
                if ($image_name) {
                    // Delete old image
                    $stmt = $db->prepare("SELECT image FROM products WHERE id = ?");
                    $stmt->execute([$_POST['product_id']]);
                    $old = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($old && $old['image'] && file_exists("../uploads/" . $old['image'])) {
                        unlink("../uploads/" . $old['image']);
                    }
                    $stmt = $db->prepare("UPDATE products SET name=?, description=?, price=?, stock=?, category=?, image=? WHERE id=?");
                    $stmt->execute([$name, $description, $price, $stock, $category, $image_name, $_POST['product_id']]);
                } else {
                    $stmt = $db->prepare("UPDATE products SET name=?, description=?, price=?, stock=?, category=? WHERE id=?");
                    $stmt->execute([$name, $description, $price, $stock, $category, $_POST['product_id']]);
                }
                $_SESSION['success'] = "Product updated successfully!";
            } else {
                // Insert new product
                $stmt = $db->prepare("INSERT INTO products (name, description, price, stock, category, image, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
                $stmt->execute([$name, $description, $price, $stock, $category, $image_name]);
                $_SESSION['success'] = "Product added successfully!";
            }
            header("Location: products.php");
            exit();
        }
    }
}

$products = $db->query("SELECT * FROM products ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$edit_product = null;
if (isset($_GET['edit'])) {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_product = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get success/error messages
$success_message = isset($_SESSION['success']) ? $_SESSION['success'] : '';
$error_message = isset($error) ? $error : '';
unset($_SESSION['success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - ShopVerse Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #f0f2f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .sidebar {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            position: sticky;
            top: 0;
        }
        
        .sidebar h4 {
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 15px;
        }
        
        .sidebar a {
            color: #bdc3c7;
            transition: all 0.3s;
            border-left: 3px solid transparent;
            padding: 12px 20px;
            display: block;
            text-decoration: none;
        }
        
        .sidebar a:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: #667eea;
            padding-left: 25px;
        }
        
        .sidebar a.active {
            background: rgba(102,126,234,0.2);
            color: white;
            border-left-color: #667eea;
        }
        
        .sidebar a i {
            width: 25px;
        }
        
        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 8px;
        }
        
        .image-preview {
            max-width: 150px;
            max-height: 150px;
            margin-top: 10px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .current-image {
            border: 2px solid #28a745;
            padding: 5px;
            background: #f8f9fa;
        }
        
        .toast-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            animation: slideInRight 0.3s ease;
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
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 1px solid #ddd;
            padding: 10px 15px;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102,126,234,0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            border-radius: 50px;
            padding: 10px 25px;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }
        
        .table-container {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
        }
        
        .table thead th {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            font-weight: 500;
            border: none;
        }
        
        .btn-sm {
            border-radius: 8px;
            margin: 2px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar p-0">
                <h4 class="text-white text-center py-3">
                    <i class="fas fa-store"></i> ShopVerse
                </h4>
                <a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                <a href="users.php"><i class="fas fa-users"></i> Users</a>
                <a href="products.php" class="active"><i class="fas fa-box"></i> Products</a>
                <a href="orders.php"><i class="fas fa-shopping-cart"></i> Orders</a>
                <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 p-4">
                <!-- Toast Notifications -->
                <?php if($success_message): ?>
                <div class="toast-notification">
                    <div class="alert alert-success alert-dismissible fade show shadow-lg border-0 rounded-3">
                        <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
                <script>
                    setTimeout(() => {
                        document.querySelector('.toast-notification')?.remove();
                    }, 3000);
                </script>
                <?php endif; ?>
                
                <?php if($error_message): ?>
                <div class="toast-notification">
                    <div class="alert alert-danger alert-dismissible fade show shadow-lg border-0 rounded-3">
                        <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                </div>
                <script>
                    setTimeout(() => {
                        document.querySelector('.toast-notification')?.remove();
                    }, 4000);
                </script>
                <?php endif; ?>
                
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2>
                        <i class="fas fa-box text-primary"></i> Manage Products
                    </h2>
                </div>
                
                <!-- Add/Edit Product Form -->
                <div class="card mb-4 border-0 shadow-sm">
                    <div class="card-header bg-white border-0 pt-4 pb-0">
                        <h5 class="mb-0">
                            <i class="fas fa-<?php echo $edit_product ? 'edit' : 'plus'; ?> text-primary me-2"></i>
                            <?php echo $edit_product ? 'Edit Product' : 'Add New Product'; ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <?php if($edit_product): ?>
                                <input type="hidden" name="product_id" value="<?php echo $edit_product['id']; ?>">
                            <?php endif; ?>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Product Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control" value="<?php echo htmlspecialchars($edit_product['name'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Category</label>
                                    <select name="category" class="form-select">
                                        <option value="">Select Category</option>
                                        <option value="Electronics" <?php echo (isset($edit_product['category']) && $edit_product['category'] == 'Electronics') ? 'selected' : ''; ?>>📱 Electronics</option>
                                        <option value="Fashion" <?php echo (isset($edit_product['category']) && $edit_product['category'] == 'Fashion') ? 'selected' : ''; ?>>👕 Fashion</option>
                                        <option value="Sports" <?php echo (isset($edit_product['category']) && $edit_product['category'] == 'Sports') ? 'selected' : ''; ?>>⚽ Sports</option>
                                        <option value="Books" <?php echo (isset($edit_product['category']) && $edit_product['category'] == 'Books') ? 'selected' : ''; ?>>📚 Books</option>
                                        <option value="Home" <?php echo (isset($edit_product['category']) && $edit_product['category'] == 'Home') ? 'selected' : ''; ?>>🏠 Home & Living</option>
                                    </select>
                                </div>
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea name="description" class="form-control" rows="3" placeholder="Product description..."><?php echo htmlspecialchars($edit_product['description'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Price ($) <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" name="price" class="form-control" value="<?php echo $edit_product['price'] ?? ''; ?>" required>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <label class="form-label">Stock Quantity <span class="text-danger">*</span></label>
                                    <input type="number" name="stock" class="form-control" value="<?php echo $edit_product['stock'] ?? ''; ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Product Image</label>
                                    <input type="file" name="image" class="form-control" accept="image/*" onchange="previewImage(this)">
                                    <small class="text-muted">Supported formats: JPG, PNG, GIF, WEBP (Max 2MB)</small>
                                    
                                    <?php if(isset($edit_product['image']) && $edit_product['image']): ?>
                                        <div class="mt-3 p-2 bg-light rounded">
                                            <p class="mb-1"><i class="fas fa-image text-primary"></i> Current Image:</p>
                                            <img src="../uploads/<?php echo $edit_product['image']; ?>" class="current-image image-preview" alt="Current product image">
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div id="imagePreviewContainer" style="display: none;" class="mt-3 p-2 bg-light rounded">
                                        <p><i class="fas fa-eye text-primary"></i> New Image Preview:</p>
                                        <img id="imagePreview" class="image-preview" alt="Preview">
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i> <?php echo $edit_product ? 'Update Product' : 'Add Product'; ?>
                                    </button>
                                    <?php if($edit_product): ?>
                                        <a href="products.php" class="btn btn-secondary ms-2">
                                            <i class="fas fa-times me-2"></i> Cancel
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Products Table -->
                <div class="table-container">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($products) > 0): ?>
                                    <?php foreach($products as $p): ?>
                                        <tr>
                                            <td><?php echo $p['id']; ?></td>
                                            <td>
                                                <?php if($p['image'] && file_exists("../uploads/" . $p['image'])): ?>
                                                    <img src="../uploads/<?php echo $p['image']; ?>" class="product-image" alt="<?php echo htmlspecialchars($p['name']); ?>">
                                                <?php else: ?>
                                                    <div class="product-image bg-light d-flex align-items-center justify-content-center rounded">
                                                        <i class="fas fa-image fa-2x text-muted"></i>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($p['name']); ?>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($p['description']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($p['category']); ?></td>
                                            <td><strong class="text-primary">$<?php echo number_format($p['price'], 2); ?></strong></td>
                                            <td>
                                                <?php if($p['stock'] <= 5 && $p['stock'] > 0): ?>
                                                    <span class="badge bg-warning text-dark">⚠️ <?php echo $p['stock']; ?> left</span>
                                                <?php elseif($p['stock'] <= 0): ?>
                                                    <span class="badge bg-danger">❌ Out of Stock</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">✓ <?php echo $p['stock']; ?> in stock</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $p['status'] == 'active' ? 'success' : 'secondary'; ?>">
                                                    <?php echo $p['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="?edit=<?php echo $p['id']; ?>" class="btn btn-warning btn-sm" title="Edit Product">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="?delete=<?php echo $p['id']; ?>" class="btn btn-danger btn-sm" title="Delete Product" 
                                                   onclick="return confirm('⚠️ Are you sure you want to delete "<?php echo addslashes($p['name']); ?>"?\n\nThis action cannot be undone!')">
                                                    <i class="fas fa-trash-alt"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-5">
                                            <i class="fas fa-box-open fa-3x text-muted mb-3 d-block"></i>
                                            <h5>No products found</h5>
                                            <p class="text-muted">Click "Add New Product" to create your first product.</p>
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
    
    <script>
        function previewImage(input) {
            const previewContainer = document.getElementById('imagePreviewContainer');
            const preview = document.getElementById('imagePreview');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    previewContainer.style.display = 'block';
                }
                
                reader.readAsDataURL(input.files[0]);
            } else {
                previewContainer.style.display = 'none';
            }
        }
        
        // Auto-hide toast notifications
        setTimeout(() => {
            document.querySelectorAll('.toast-notification').forEach(toast => {
                toast.remove();
            });
        }, 3000);
    </script>
</body>
</html>