<?php
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();
$error = '';
$success = '';

// Initialize form data variables
$name = $email = $phone = $address = '';
$name_err = $email_err = $password_err = $phone_err = $address_err = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get and sanitize input
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    $is_valid = true;
    
    // Validate Name (3-50 characters, letters and spaces only)
    if (empty($name)) {
        $name_err = "Name is required";
        $is_valid = false;
    } elseif (!preg_match("/^[a-zA-Z\s]{3,50}$/", $name)) {
        $name_err = "Name must be 3-50 characters and contain only letters and spaces";
        $is_valid = false;
    }
    
    // Validate Email
    if (empty($email)) {
        $email_err = "Email is required";
        $is_valid = false;
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email address";
        $is_valid = false;
    } elseif (!preg_match("/^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/", $email)) {
        $email_err = "Email format is invalid";
        $is_valid = false;
    }
    
    // Validate Password (8-30 characters, at least one uppercase, one lowercase, one number)
    if (empty($password)) {
        $password_err = "Password is required";
        $is_valid = false;
    } elseif (strlen($password) < 8) {
        $password_err = "Password must be at least 8 characters";
        $is_valid = false;
    } elseif (strlen($password) > 30) {
        $password_err = "Password must be less than 30 characters";
        $is_valid = false;
    } elseif (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/", $password)) {
        $password_err = "Password must contain at least one uppercase, one lowercase, and one number";
        $is_valid = false;
    } elseif ($password != $confirm_password) {
        $password_err = "Passwords do not match";
        $is_valid = false;
    }
    
    // Validate Phone (10 digits, starting with 6,7,8,9)
    if (empty($phone)) {
        $phone_err = "Phone number is required";
        $is_valid = false;
    } elseif (!preg_match("/^[6-9][0-9]{9}$/", $phone)) {
        $phone_err = "Phone must be 10 digits and start with 6,7,8, or 9";
        $is_valid = false;
    }
    
    // Validate Address (optional but if provided must be valid)
    if (!empty($address) && strlen($address) < 10) {
        $address_err = "Address must be at least 10 characters if provided";
        $is_valid = false;
    }
    
    if ($is_valid) {
        try {
            // Check if email exists
            $check_query = "SELECT id FROM users WHERE email = :email";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                $email_err = "Email already registered. Please use a different email.";
            } else {
                // Check if phone exists
                $check_phone_query = "SELECT id FROM users WHERE phone = :phone";
                $check_phone_stmt = $db->prepare($check_phone_query);
                $check_phone_stmt->bindParam(':phone', $phone);
                $check_phone_stmt->execute();
                
                if ($check_phone_stmt->rowCount() > 0) {
                    $phone_err = "Phone number already registered";
                } else {
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    $query = "INSERT INTO users (name, email, password, phone, address, status, user_type, created_at) 
                              VALUES (:name, :email, :password, :phone, :address, 'pending', 'customer', NOW())";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':email', $email);
                    $stmt->bindParam(':password', $hashed_password);
                    $stmt->bindParam(':phone', $phone);
                    $stmt->bindParam(':address', $address);
                    
                    if ($stmt->execute()) {
                        $success = "Registration successful! Please wait for admin approval.";
                        // Clear form data
                        $name = $email = $phone = $address = '';
                    } else {
                        $error = "Registration failed. Please try again.";
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - ShopEMart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
        .register-container { max-width: 500px; margin: 50px auto; }
        .card { border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        .btn-register { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none; color: white; }
        .btn-register:hover { transform: translateY(-2px); background: linear-gradient(135deg, #5a6fd6 0%, #6a3f8f 100%); color: white; }
        .error-message { color: #dc3545; font-size: 0.875em; margin-top: 0.25rem; }
        .is-invalid { border-color: #dc3545; }
        .password-strength {
            height: 4px;
            margin-top: 5px;
            border-radius: 2px;
            transition: all 0.3s;
        }
        .strength-weak { width: 33%; background: #dc3545; }
        .strength-medium { width: 66%; background: #ffc107; }
        .strength-strong { width: 100%; background: #28a745; }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-container">
            <div class="card">
                <div class="card-body p-5">
                    <h2 class="text-center mb-4">
                        <i class="fas fa-user-plus me-2"></i>Create Account
                    </h2>
                    
                    <?php if($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if($success): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
                            <a href="login.php" class="alert-link">Login here</a>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="" id="registerForm" novalidate>
                        <div class="mb-3">
                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control <?php echo $name_err ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($name); ?>" 
                                   placeholder="Enter your full name" required>
                            <div class="invalid-feedback"><?php echo $name_err; ?></div>
                            
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control <?php echo $email_err ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($email); ?>" 
                                   placeholder="Enter your email" required>
                            <div class="invalid-feedback"><?php echo $email_err; ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" name="password" class="form-control <?php echo $password_err ? 'is-invalid' : ''; ?>" 
                                       id="password" placeholder="Enter password (8-30 chars)" required>
                                <button type="button" class="btn btn-outline-secondary" id="togglePassword">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="invalid-feedback"><?php echo $password_err; ?></div>
                            <div class="password-strength" id="passwordStrength"></div>
                            
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                            <input type="password" name="confirm_password" class="form-control <?php echo $password_err ? 'is-invalid' : ''; ?>" 
                                   id="confirm_password" placeholder="Confirm your password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                            <input type="tel" name="phone" class="form-control <?php echo $phone_err ? 'is-invalid' : ''; ?>" 
                                   value="<?php echo htmlspecialchars($phone); ?>" 
                                   placeholder="Enter 10-digit phone number" required>
                            <div class="invalid-feedback"><?php echo $phone_err; ?></div>
                            
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control <?php echo $address_err ? 'is-invalid' : ''; ?>" 
                                      rows="2" placeholder="Enter your address (optional)"><?php echo htmlspecialchars($address); ?></textarea>
                            <div class="invalid-feedback"><?php echo $address_err; ?></div>
                            
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-register w-100">
                            <i class="fas fa-user-plus me-2"></i>Register
                        </button>
                        <p class="text-center mt-3">
                            Already have an account? <a href="login.php">Login</a>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('passwordStrength');
            
            let strength = 0;
            if (password.length >= 8) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.length >= 12) strength++;
            
            strengthBar.className = 'password-strength';
            if (password.length === 0) {
                strengthBar.style.width = '0';
                return;
            }
            
            if (strength <= 2) {
                strengthBar.classList.add('strength-weak');
            } else if (strength === 3 || strength === 4) {
                strengthBar.classList.add('strength-medium');
            } else {
                strengthBar.classList.add('strength-strong');
            }
        });
        
        // Real-time validation
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirm = document.getElementById('confirm_password').value;
            
            if (password !== confirm) {
                e.preventDefault();
                document.getElementById('confirm_password').classList.add('is-invalid');
            }
        });
        
        // Real-time password match check
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            if (this.value !== password) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
    </script>
</body>
</html>