<?php
session_start();

require_once 'esewa_config.php';

$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;
$amount = isset($_GET['amount']) ? floatval($_GET['amount']) : 0;

if ($order_id <= 0 || $amount <= 0) {
    die("Invalid Order");
}

$amount = number_format($amount, 2, '.', '');

$transaction_uuid = "TXN_" . time() . "_" . $order_id;

$_SESSION['esewa_order_id'] = $order_id;

$signature_string =
    "total_amount={$amount},transaction_uuid={$transaction_uuid},product_code=EPAYTEST";

$signature = EsewaConfig::generateSignature($signature_string);

$success_url = "http://localhost/test/customer/payment_success.php";
$failure_url = "http://localhost/test/customer/payment_failure.php";
?>

<!DOCTYPE html>
<html>
<head>
    <title>Pay with eSewa</title>
</head>
<body onload="document.getElementById('esewaForm').submit();">

<h3>Redirecting to eSewa...</h3>

<form id="esewaForm"
      action="<?php echo EsewaConfig::getPaymentUrl(); ?>"
      method="POST">

    <input type="hidden" name="amount" value="<?php echo $amount; ?>">
    <input type="hidden" name="tax_amount" value="0">
    <input type="hidden" name="total_amount" value="<?php echo $amount; ?>">
    <input type="hidden" name="transaction_uuid" value="<?php echo $transaction_uuid; ?>">
    <input type="hidden" name="product_code" value="EPAYTEST">
    <input type="hidden" name="product_service_charge" value="0">
    <input type="hidden" name="product_delivery_charge" value="0">

    <input type="hidden" name="success_url" value="<?php echo $success_url; ?>">
    <input type="hidden" name="failure_url" value="<?php echo $failure_url; ?>">

    <input type="hidden"
           name="signed_field_names"
           value="total_amount,transaction_uuid,product_code">

    <input type="hidden"
           name="signature"
           value="<?php echo $signature; ?>">

</form>

</body>
</html>