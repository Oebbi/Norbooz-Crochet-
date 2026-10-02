<?php
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/payments.php';
require_customer();

$provider = (string)($_GET['provider'] ?? '');
$orderId = filter_var($_GET['order_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$reference = (string)($_GET['token'] ?? '');
if (!$orderId || $provider !== 'paypal' || $reference === '') {
    not_found('That payment could not be found.');
}

try {
    complete_provider_payment($provider, (int)$orderId, $reference);
    flash('success', 'Payment received for order #' . (int)$orderId . '.');
} catch (Throwable $ex) {
    error_log('Payment confirmation failed for order #' . (int)$orderId . ': ' . $ex->getMessage());
    flash('error', 'We could not confirm payment yet. Do not pay again; contact us with your order number if the charge appears on your account.');
}
redirect('order_detail.php?id=' . (int)$orderId);