<?php
define('ERROR_LOG_FILE', __DIR__ . '/error.log');
define('PROBLEM_LOG_FILE', __DIR__ . '/problems.log');

// reCAPTCHA Configuration
// Google reCAPTCHA v2 Test Keys (always work for testing)
// For production, replace with your actual keys from https://www.google.com/recaptcha/admin
define('RECAPTCHA_SITE_KEY', '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI'); // Test site key
define('RECAPTCHA_SECRET_KEY', '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe'); // Test secret key

// SSL Configuration
define('SSL_CA_BUNDLE', __DIR__ . '/cacert.pem');

// Alternative SSL paths for different environments
if (!file_exists(SSL_CA_BUNDLE)) {
    // Try common CA bundle locations
    $alternative_paths = [
        'C:/php-8.3.23/extras/ssl/cacert.pem',
        'C:/php-8.3.23/extras/cacert.pem',
        'C:/php-8.3.23/cacert.pem',
        __DIR__ . '/cacert.pem'
    ];
    
    foreach ($alternative_paths as $path) {
        if (file_exists($path)) {
            define('SSL_CA_BUNDLE', $path);
            break;
        }
    }
} 