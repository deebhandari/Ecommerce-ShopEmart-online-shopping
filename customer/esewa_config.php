<?php
// eSewa Configuration for ShopVerse
class EsewaConfig {
    // Set to false for live production
    public static $TEST_MODE = true;
    
    // Test Credentials (Sandbox)
    public static $TEST_MERCHANT_CODE = "EPAYTEST";
    public static $TEST_SECRET_KEY = "8gBm/:&EnhH.1,q";
    
    // Test URLs
    public static $TEST_PAYMENT_URL = "https://uat.esewa.com.np/epay/main";
    public static $TEST_VERIFICATION_URL = "https://uat.esewa.com.np/epay/transrec";
    
    // Live Credentials (Replace after merchant approval)
    public static $LIVE_MERCHANT_CODE = "YOUR_LIVE_CODE";
    public static $LIVE_SECRET_KEY = "YOUR_LIVE_SECRET";
    public static $LIVE_PAYMENT_URL = "https://esewa.com.np/epay/main";
    public static $LIVE_VERIFICATION_URL = "https://esewa.com.np/epay/transrec";
    
    public static function getMerchantCode() {
        return self::$TEST_MODE ? self::$TEST_MERCHANT_CODE : self::$LIVE_MERCHANT_CODE;
    }
    
    public static function getPaymentUrl() {
        return self::$TEST_MODE ? self::$TEST_PAYMENT_URL : self::$LIVE_PAYMENT_URL;
    }
    
    public static function getVerificationUrl() {
        return self::$TEST_MODE ? self::$TEST_VERIFICATION_URL : self::$LIVE_VERIFICATION_URL;
    }
    
    public static function isTestMode() {
        return self::$TEST_MODE;
    }
}
?>