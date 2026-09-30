<?php
// Run from the project folder with: php tests/smoke_test.php
require_once __DIR__ . '/../config/functions.php';

$tests = [];
$tests['HTML output is encoded'] = e('<script>alert(1)</script>') === '&lt;script&gt;alert(1)&lt;/script&gt;';
$tests['Email is normalised'] = normalise_email('  TEST@Example.COM ') === 'test@example.com';
$token = csrf_token();
$tests['CSRF token generated'] = is_string($token) && strlen($token) === 64;
$tests['Valid order status accepted'] = is_valid_order_status('ready') === true;
$tests['Invalid order status rejected'] = is_valid_order_status('unknown') === false;
$tests['Money formatted'] = money(12.5) === '$12.50';
$tests['Safe local image accepted'] = safe_product_image_path('assets/images/bear.svg') === 'assets/images/bear.svg';
$tests['Nested product photo accepted'] = safe_product_image_path('assets/images/products/capybara.jpg') === 'assets/images/products/capybara.jpg';
$tests['Unsafe image path replaced'] = safe_product_image_path('https://example.com/a.png') === 'assets/images/product-placeholder.svg';
$tests['Final category list contains eight families'] = count(product_categories()) === 8;
$tests['Old Beanies category maps to Hats'] = canonical_product_category('Beanies') === 'Hats';

$failed = 0;
foreach ($tests as $name => $ok) {
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$ok) {
        $failed++;
    }
}

exit($failed ? 1 : 0);
