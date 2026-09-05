<?php
// Run with: php tests/smoke_test.php
require_once __DIR__ . '/../config/functions.php';
$tests = [];
$tests['HTML output is encoded'] = e('<script>alert(1)</script>') === '&lt;script&gt;alert(1)&lt;/script&gt;';
$token = csrf_token();
$tests['CSRF token generated'] = is_string($token) && strlen($token) === 64;
$tests['Valid order status accepted'] = is_valid_order_status('ready') === true;
$tests['Invalid order status rejected'] = is_valid_order_status('unknown') === false;
$tests['Money formatted'] = money(12.5) === '$12.50';
$failed = 0;
foreach ($tests as $name => $ok) {
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $name . PHP_EOL;
    if (!$ok) $failed++;
}
exit($failed ? 1 : 0);
