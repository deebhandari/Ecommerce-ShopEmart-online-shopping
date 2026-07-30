<?php

class EsewaConfig
{
    private static $test_mode = true;

    private static $merchant_code = "EPAYTEST";
    private static $secret_key = "8gBm/:&EnhH.1/q";

    private static $test_payment_url =
        "https://rc-epay.esewa.com.np/api/epay/main/v2/form";

    private static $live_payment_url =
        "https://epay.esewa.com.np/api/epay/main/v2/form";

    public static function getMerchantCode()
    {
        return self::$merchant_code;
    }

    public static function getSecretKey()
    {
        return self::$secret_key;
    }

    public static function getPaymentUrl()
    {
        return self::$test_mode
            ? self::$test_payment_url
            : self::$live_payment_url;
    }

    public static function generateSignature($data)
    {
        return base64_encode(
            hash_hmac(
                'sha256',
                $data,
                self::$secret_key,
                true
            )
        );
    }
}
?>