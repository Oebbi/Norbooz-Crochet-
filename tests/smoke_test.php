<?php
/**
 * Unit tests for helper functions. No web server or database needed.
 * Run from the project folder:  php tests/smoke_test.php
 */
require_once __DIR__ . '/../config/functions.php';
require_once __DIR__ . '/../config/payments.php';

$previousRemoteAddress = $_SERVER['REMOTE_ADDR'] ?? null;
$previousHost = $_SERVER['HTTP_HOST'] ?? null;
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = 'localhost';
$localPaymentOptions = online_payment_options();
$_SERVER['REMOTE_ADDR'] = '192.0.2.1';
$_SERVER['HTTP_HOST'] = 'shop.example.com';
$publicPaymentOptions = online_payment_options();
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
$proxiedPublicPaymentOptions = online_payment_options();
if ($previousRemoteAddress === null) {
    unset($_SERVER['REMOTE_ADDR']);
} else {
    $_SERVER['REMOTE_ADDR'] = $previousRemoteAddress;
}
if ($previousHost === null) {
    unset($_SERVER['HTTP_HOST']);
} else {
    $_SERVER['HTTP_HOST'] = $previousHost;
}

$tests = [];
$tests['HTML output is encoded'] = e('<script>alert(1)</script>') === '&lt;script&gt;alert(1)&lt;/script&gt;';
$tests['Quotes are encoded in attributes'] = e('" onmouseover="x') === '&quot; onmouseover=&quot;x';
$tests['Email is normalised'] = normalise_email('  TEST@Example.COM ') === 'test@example.com';
$tests['CSRF token is 64 hex characters'] = (bool)preg_match('/^[a-f0-9]{64}$/', csrf_token());
$tests['Money is calculated in cents'] = to_cents('19.99') * 3 === 5997 && cents_to_decimal(5997) === '59.97';
$tests['Money is formatted'] = money(12.5) === '$12.50';
$tests['Phone validation accepts Australian formats'] = valid_phone('0412 345 678') && valid_phone('+61 2 6123 4567') && !valid_phone('abc') && !valid_phone('12');
$tests['Weak password rejected'] = password_problems('password', 'password') !== [];
$tests['Strong password accepted'] = password_problems('Crochet2025', 'Crochet2025') === [];
$tests['Mismatched passwords rejected'] = in_array('Passwords do not match.', password_problems('Crochet2025', 'Crochet2026'), true);
$tests['Valid status accepted'] = is_valid_order_status('ready');
$tests['Invalid status rejected'] = !is_valid_order_status('shipped');
$tests['Pending can move to In progress'] = can_change_status('pending', 'in_progress');
$tests['Completed orders are final'] = allowed_status_transitions('completed') === [];
$tests['Cancelled order can only be re-opened to Pending'] = allowed_status_transitions('cancelled') === ['pending'];
$tests['Pickup is free'] = delivery_fee_cents('pickup', 1000) === 0;
$tests['Postage charged under threshold'] = delivery_fee_cents('post', 1000) === to_cents(POSTAGE_FEE);
$tests['Free postage over threshold'] = FREE_POSTAGE_OVER <= 0 || delivery_fee_cents('post', to_cents(FREE_POSTAGE_OVER)) === 0;
$tests['Local image path accepted'] = safe_product_image_path('assets/images/products/bunny-plush.jpg') === 'assets/images/products/bunny-plush.jpg';
$tests['Remote image URL rejected'] = safe_product_image_path('https://example.com/a.png') === 'assets/images/product-placeholder.svg';
$tests['Path traversal rejected'] = safe_product_image_path('assets/images/../../config/config.php') === 'assets/images/product-placeholder.svg';
$tests['Legacy photo name resolves to optimised photo'] = product_image('assets/images/products/Capibara-gang.png.png') === 'assets/images/products/capybara-keychains.jpg';
$tests['Legacy name with spaces resolves'] = product_image('assets/images/products/Bunny .png') === 'assets/images/products/bunny-plush.jpg';
$tests['Old demo SVG resolves to a real photo'] = product_image('assets/images/products/demo-hat.svg', 'Hats') === 'assets/images/products/blue-beanie.jpg';
$tests['Thumbnail used on grids'] = product_image('assets/images/products/bunny-plush.jpg', '', true) === 'assets/images/products/bunny-plush-thumb.jpg';
$tests['Missing photo falls back to category photo'] = product_image('assets/images/products/does-not-exist.jpg', 'Bags') === category_image('Bags');
$tests['Every category photo exists'] = count(array_filter(product_categories(), fn($c) => is_file(APP_ROOT . '/' . category_image($c)))) === 8;
$tests['Eight product families'] = count(product_categories()) === 8;
$tests['Old Beanies category maps to Hats'] = canonical_product_category('Beanies') === 'Hats';
$tests['Local redirect allowed'] = safe_next('order_detail.php?id=5') === 'order_detail.php?id=5';
$tests['External redirect blocked'] = safe_next('https://evil.example') === 'index.php' && safe_next('//evil.example') === 'index.php';
$tests['Local checkout offers no-charge payment samples'] = isset($localPaymentOptions['demo_apple_pay'], $localPaymentOptions['demo_afterpay'], $localPaymentOptions['demo_paypal']);
$tests['Public checkout never offers demo payment samples'] = !isset($publicPaymentOptions['demo_apple_pay'], $publicPaymentOptions['demo_afterpay'], $publicPaymentOptions['demo_paypal']);
$tests['Public hostname never exposes local demo through a proxy'] = !isset($proxiedPublicPaymentOptions['demo_apple_pay'], $proxiedPublicPaymentOptions['demo_afterpay'], $proxiedPublicPaymentOptions['demo_paypal']);
$tests['HTTP localhost is allowed for private test payment return URLs'] = is_local_http_url('http://localhost/norbooz_crochet') && is_local_http_url('http://127.0.0.1:8080/shop');
$tests['Public HTTP is not accepted as a local test payment URL'] = !is_local_http_url('http://shop.example.com') && !is_local_http_url('https://localhost/shop');

$failed = 0;
foreach ($tests as $name => $ok) {
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    $failed += $ok ? 0 : 1;
}
echo PHP_EOL . (count($tests) - $failed) . ' / ' . count($tests) . ' passed.' . PHP_EOL;
exit($failed ? 1 : 0);
