<?php
session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed - ShopVerse</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }
        
        .failure-card {
            max-width: 500px;
            width: 100%;
            background: white;
            border-radius: 24px;
            padding: 40px;
            text-align: center;
            box-shadow: 0 20px 50px rgba(0,0,0,0.3);
            animation: fadeInUp 0.5s ease;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .error-icon {
            width: 80px;
            height: 80px;
            background: #e74c3c;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .error-icon i {
            font-size: 40px;
            color: white;
        }
        
        .reasons-box {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 15px;
            text-align: left;
            margin: 20px 0;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            padding: 12px 25px;
            border-radius: 50px;
            margin: 5px;
        }
    </style>
</head>
<body>
    <div class="failure-card">
        <div class="error-icon">
            <i class="fas fa-times"></i>
        </div>
        <h2 class="text-danger mt-3">Payment Failed!</h2>
        <p>Your payment could not be completed.</p>
        
        <div class="reasons-box">
            <strong><i class="fas fa-info-circle"></i> Possible Reasons:</strong>
            <ul class="mt-2 mb-0">
                <li>Insufficient balance in eSewa account</li>
                <li>Incorrect MPIN or password entered</li>
                <li>Transaction was cancelled by user</li>
                <li>Network connection issue</li>
            </ul>
        </div>
        
        <div class="alert alert-warning">
            <i class="fas fa-info-circle"></i> No amount has been deducted from your account.
        </div>
        
        <div class="mt-4">
            <a href="checkout.php" class="btn btn-primary">
                <i class="fas fa-redo"></i> Try Again
            </a>
            <a href="cart.php" class="btn btn-outline-secondary mt-2 d-block">
                <i class="fas fa-shopping-cart"></i> Back to Cart
            </a>
        </div>
    </div>
</body>
</html>