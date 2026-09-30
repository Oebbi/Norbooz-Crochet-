<?php
/**
 * Norbooz Crochet - application configuration.
 *
 * Do NOT edit passwords in this file for a live website.
 * Copy config/local.example.php to config/local.php and put the real values there.
 * config/local.php is excluded from Git (.gitignore) so secrets never reach the repository.
 */

$localConfig = __DIR__ . '/local.php';
if (is_file($localConfig)) {
    require $localConfig;
}

// Each setting is only defined here if config/local.php has not already defined it.
$defaults = [
    // Database (standard XAMPP defaults)
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => 3306,
    'DB_NAME' => 'norbooz_crochet_db',
    'DB_USER' => 'root',
    'DB_PASS' => '',

    // Shop details
    'APP_NAME' => 'Norbooz Crochet',
    'SHOP_EMAIL' => 'norboozcrochet25@gmail.com',
    'SHOP_LOCATION' => 'Canberra, ACT',

    // Absolute public address used in emails, e.g. https://norboozcrochet.com.au
    // Leave blank on XAMPP; the address is then worked out from the request.
    'APP_URL' => '',

    // 'log'  = write emails to storage/mail.log (local testing, no mail server needed)
    // 'mail' = send with PHP mail() (use on a real web host)
    'MAIL_MODE' => 'log',

    // Delivery options offered at checkout (fees in AUD)
    'PICKUP_ENABLED' => true,
    'POSTAGE_FEE' => 12.00,
    'FREE_POSTAGE_OVER' => 100.00,

    // Shown to the customer after they place an order. No card data is ever collected by the site.
    'PAYMENT_INSTRUCTIONS' => 'We will email you within one business day to confirm your order and send PayID / bank transfer details. Your item is reserved for 3 days while we wait for payment.',

    // Security
    'SESSION_IDLE_MINUTES' => 30,
    'LOGIN_MAX_FAILURES' => 5,        // per email address
    'LOGIN_MAX_IP_FAILURES' => 20,    // per IP address
    'LOGIN_WINDOW_MINUTES' => 15,
    'MAX_UPLOAD_MB' => 8,
    'LOW_STOCK_LEVEL' => 2,
];

foreach ($defaults as $name => $value) {
    if (!defined($name)) {
        define($name, $value);
    }
}
unset($defaults, $name, $value, $localConfig);

// Base path of the site, e.g. "/norbooz_crochet" on XAMPP or "" at a domain root.
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$basePath = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
// Pages inside sub-folders (tests) should still link to the site root.
$basePath = preg_replace('#/(tests|database|config)$#', '', $basePath);
if ($basePath === '.' || $basePath === '/') {
    $basePath = '';
}
// PHP gives us the decoded path. A folder such as "norbooz_crochet 2" (what macOS calls a second
// download) must be URL-encoded again, otherwise the session cookie path never matches the address
// the browser requests, the session is lost on every page and every form fails its CSRF check.
$basePath = implode('/', array_map('rawurlencode', explode('/', $basePath)));
if (!defined('BASE_URL')) {
    define('BASE_URL', $basePath);
}
unset($scriptName, $basePath);

define('APP_ROOT', dirname(__DIR__));
define('STORAGE_DIR', APP_ROOT . '/storage');
define('PRODUCT_IMAGE_DIR', 'assets/images/products');

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . (int)DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    return $pdo;
}
