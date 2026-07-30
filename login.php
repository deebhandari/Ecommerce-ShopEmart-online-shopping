<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();
$error = '';
$selected_role = isset($_GET['role']) ? $_GET['role'] : '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $role = $_POST['role'];
    
    $query = "SELECT * FROM users WHERE email = :email";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (password_verify($password, $user['password'])) {
            // Check if role matches
            if ($user['user_type'] != $role) {
                $error = "Invalid role selected. Please select the correct role for this account.";
            } elseif ($user['status'] == 'active') {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_type'] = $user['user_type'];
                
                if ($user['user_type'] == 'admin') {
                    header("Location: admin/dashboard.php");
                } else {
                    header("Location: customer/dashboard.php");
                }
                exit();
            } else {
                $error = "Your account is pending admin approval.";
            }
        } else {
            $error = "Invalid credentials";
        }
    } else {
        $error = "User not found";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - ShopEMart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .login-container { 
            max-width: 450px; 
            margin: 80px auto; 
        }
        
        .card { 
            border-radius: 20px; 
            box-shadow: 0 20px 60px rgba(0,0,0,0.2);
            overflow: hidden;
            border: none;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            padding: 30px;
            border: none;
        }
        
        .card-header h2 {
            margin: 0;
            font-size: 28px;
        }
        
        .card-header p {
            margin: 10px 0 0;
            opacity: 0.9;
        }
        
        .role-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .role-btn {
            flex: 1;
            padding: 12px;
            border: 2px solid #e0e0e0;
            background: white;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .role-btn i {
            font-size: 24px;
            display: block;
            margin-bottom: 8px;
        }
        
        .role-btn span {
            font-size: 14px;
            font-weight: 600;
        }
        
        .role-btn:hover {
            border-color: #667eea;
            background: #f0f0ff;
            transform: translateY(-2px);
        }
        
        .role-btn.active {
            border-color: #667eea;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .role-btn.active i,
        .role-btn.active span {
            color: white;
        }
        
        .form-control {
            border-radius: 12px;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102,126,234,0.25);
        }
        
        .btn-login { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            border: none;
            padding: 12px;
            border-radius: 12px;
            font-weight: bold;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102,126,234,0.4);
        }
        
        .register-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
        
        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
        
        .alert {
            border-radius: 12px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-container">
            <div class="card">
                <div class="card-header">
                    <h2><i class="fas fa-store"></i> ShopEMart</h2>
                    <p>Login to your account</p>
                </div>
                <div class="card-body p-4">
                    <?php if($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="loginForm">
                        <!-- Role Selection Buttons -->
                        <div class="role-buttons">
                            <div class="role-btn <?php echo $selected_role == 'customer' ? 'active' : ''; ?>" 
                                 onclick="selectRole('customer')">
                                <i class="fas fa-user"></i>
                                <span>Customer</span>
                            </div>
                            <div class="role-btn <?php echo $selected_role == 'admin' ? 'active' : ''; ?>" 
                                 onclick="selectRole('admin')">
                                <i class="fas fa-user-shield"></i>
                                <span>Admin</span>
                            </div>
                        </div>
                        
                        <input type="hidden" name="role" id="selectedRole" value="<?php echo $selected_role ? $selected_role : 'customer'; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-envelope"></i> Email Address
                            </label>
                            <input type="email" name="email" class="form-control" 
                                   placeholder="Enter your email" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">
                                <i class="fas fa-lock"></i> Password
                            </label>
                            <input type="password" name="password" class="form-control" 
                                   placeholder="Enter your password" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-login w-100">
                            <i class="fas fa-sign-in-alt"></i> Login as <span id="loginRoleText">Customer</span>
                        </button>
                        
                        <div class="register-link">
                            <p class="mb-0">Don't have an account? <a href="register.php">Register Now</a></p>
                            <small class="text-muted">Registration requires admin approval</small>
                        </div>
                    </form>
                </div>
            </div>
    
    <script>
        function selectRole(role) {
            // Update hidden input
            document.getElementById('selectedRole').value = role;
            
            // Update active class on buttons
            const buttons = document.querySelectorAll('.role-btn');
            buttons.forEach(btn => {
                btn.classList.remove('active');
            });
            
            if (role === 'customer') {
                buttons[0].classList.add('active');
                document.getElementById('loginRoleText').innerText = 'Customer';
            } else {
                buttons[1].classList.add('active');
                document.getElementById('loginRoleText').innerText = 'Admin';
            }
        }
        
        // Form validation before submit
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const role = document.getElementById('selectedRole').value;
            const email = document.querySelector('input[name="email"]').value;
            const password = document.querySelector('input[name="password"]').value;
            
            if (!email || !password) {
                e.preventDefault();
                alert('Please enter both email and password');
                return false;
            }
        });
        
        
    </script>
</body>
</html>