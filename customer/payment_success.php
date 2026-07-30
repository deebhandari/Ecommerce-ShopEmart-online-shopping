<?php

session_start();

require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$order_id = null;
$transaction_code = '';
$payment_success = false;

if (isset($_GET['data'])) {

    $decoded =
        json_decode(
            base64_decode($_GET['data']),
            true
        );

    if (
        $decoded &&
        isset($decoded['status']) &&
        $decoded['status'] === 'COMPLETE'
    ) {

        $transaction_uuid =
            $decoded['transaction_uuid'];

        $transaction_code =
            $decoded['transaction_code'];

        $parts =
            explode('_', $transaction_uuid);

        if (count($parts) >= 3) {
            $order_id = end($parts);
        }

        if ($order_id) {

            $stmt = $db->prepare("
                UPDATE orders
                SET payment_status='paid',
                    status='processing',
                    esewa_txn_id=?
                WHERE id=?
            ");

            $stmt->execute([
                $transaction_code,
                $order_id
            ]);

            $payment_success = true;
        }
    }
}

unset($_SESSION['esewa_order_id']);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Payment Success</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<div class="container mt-5">

    <div class="alert alert-success text-center">

        <h2>Payment Successful</h2>

        <p>
            Your payment has been completed successfully.
        </p>

        <p>
            Transaction ID:
            <?php echo htmlspecialchars($transaction_code); ?>
        </p>

        <a href="orders.php"
           class="btn btn-primary">
           View Orders
        </a>

    </div>

</div>

</body>
</html>