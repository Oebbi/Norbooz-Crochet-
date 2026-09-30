<?php
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/payments.php';
require_customer();

$provider = (string)($_GET['provider'] ?? '');
$orderId = filter_var($_GET['order_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$orderId || !in_array($provider, ['stripe', 'paypal'], true)) {
    not_found('That order could not be found.');
}

$stmt = db()->prepare('SELECT payment_reference, payment_status FROM orders WHERE order_id = ? AND user_id = ? AND payment_provider = ?');
$stmt->execute([$orderId, current_user()['user_id'], $provider]);
$order = $stmt->fetch();
if (!$order) {
    not_found('That order could not be found.');
}

if ($provider === 'stripe' && $order['payment_reference'] && $order['payment_status'] === 'unpaid') {
    try {
        $session = stripe_request('GET', 'checkout/sessions/' . rawurlencode($order['payment_reference']));
        if (($session['payment_status'] ?? '') === 'paid') {
            mark_order_paid((int)$orderId, $provider, (string)$order['payment_reference']);
            flash('success', 'Payment received for order #' . (int)$orderId . '.');
            redirect('order_detail.php?id=' . (int)$orderId);
        }
        if (($session['status'] ?? '') === 'complete') {
            flash('info', 'Your payment is still processing. We will update your order when the provider confirms it.');
            redirect('order_detail.php?id=' . (int)$orderId);
        }
        if (($session['status'] ?? '') === 'open') {
            stripe_request('POST', 'checkout/sessions/' . rawurlencode($order['payment_reference']) . '/expire');
        }
    } catch (Throwable $ex) {
        error_log('Could not close payment session for order #' . (int)$orderId . ': ' . $ex->getMessage());
        flash('error', 'We could not safely close the payment session. Please contact us before trying again.');
        redirect('order_detail.php?id=' . (int)$orderId);
    }
}

try {
    cancel_unpaid_order((int)$orderId, (int)current_user()['user_id'], 'Online payment was cancelled');
    flash('info', 'Payment was cancelled and the order reservation released.');
} catch (Throwable $ex) {
    flash('error', 'We could not cancel this order. Please contact us before trying again.');
}
redirect('order_detail.php?id=' . (int)$orderId);