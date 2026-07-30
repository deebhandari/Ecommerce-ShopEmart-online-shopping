<?php
session_start();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payment Failed</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container mt-5">

    <div class="alert alert-danger text-center">

        <h2>Payment Failed</h2>

        <p>
            Payment was cancelled or unsuccessful.
        </p>

        <a href="checkout.php"
           class="btn btn-warning">
           Try Again
        </a>

        <a href="cart.php"
           class="btn btn-secondary">
           Back to Cart
        </a>

    </div>

</div>

</body>
</html>