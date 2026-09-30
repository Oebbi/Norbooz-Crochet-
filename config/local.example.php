<?php
/**
 * Copy this file to config/local.php and change the values for your server.
 * config/local.php is ignored by Git, so real passwords are never committed.
 * Only include the settings you want to change; everything else uses config/config.php.
 */

// Database - use the restricted account from database/create_restricted_user.sql on a live site.
define('DB_HOST', 'localhost');
define('DB_NAME', 'norbooz_crochet_db');
define('DB_USER', 'norbooz_app');
define('DB_PASS', 'put-a-long-random-password-here');

// Public address of the live site (no trailing slash). Used in password-reset and order emails.
define('APP_URL', 'https://www.example.com.au');

// Send real emails on the live host.
define('MAIL_MODE', 'mail');

// Checkout options
// define('PICKUP_ENABLED', true);
// define('POSTAGE_FEE', 12.00);
// define('FREE_POSTAGE_OVER', 100.00);
// define('PAYMENT_INSTRUCTIONS', 'Pay by PayID to 04xx xxx xxx (Norbooz Crochet) using your order number as the reference.');
