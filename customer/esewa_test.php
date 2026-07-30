<?php
require_once 'esewa_config.php';
?>

<!DOCTYPE html>
<html>
<head>
    <title>eSewa Test</title>
</head>
<body>

<h1>eSewa Payment Gateway Test</h1>

<h3><?php echo EsewaConfig::isTestMode() ? 'TEST MODE' : 'LIVE MODE'; ?></h3>

<p>Amount: Rs 100</p>

<a href="esewa_payment.php?order_id=1&amount=100">
    <button>
        Pay Rs 100
    </button>
</a>

<hr>

<h3>Sandbox Login</h3>

<?php
$test = EsewaConfig::getTestCredentials();
?>

<p>Mobile: <?php echo $test['mobile']; ?></p>
<p>Password: <?php echo $test['password']; ?></p>
<p>MPIN: <?php echo $test['mpin']; ?></p>
<p>OTP: <?php echo $test['otp']; ?></p>

</body>
</html>