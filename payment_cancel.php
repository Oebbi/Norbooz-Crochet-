<?php
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/payments.php';
require_customer();

$provider = (string)($_GET['provider'] ?? '');
$orderId = filter_var($_GET['order_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$orderId || $provider !== 'paypal') {
    not_found('That order could not be found.');
}

$stmt = db()->prepare('SELECT payment_reference, payment_status FROM orders WHERE order_id = ? AND user_id = ? AND payment_provider = ?');
$stmt->execute([$orderId, current_user()['user_id'], $provider]);
$order = $stmt->fetch();
if (!$order) {
    not_found('That order could not be found.');
}

try {
    cancel_unpaid_order((int)$orderId, (int)current_user()['user_id'], 'Online payment was cancelled');
    flash('info', 'Payment was cancelled and the order reservation released.');
} catch (Throwable $ex) {
    flash('error', 'We could not cancel this order. Please contact us before trying again.');
}
redirect('order_detail.php?id=' . (int)$orderId);