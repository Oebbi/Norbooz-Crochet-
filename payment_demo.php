<?php
/**
 * Payment simulator (localhost only).
 *
 * Stands in for a hosted payment page such as PayPal so the whole online-payment life cycle
 * can be shown and tested on XAMPP without provider keys: the order already exists as
 * "unpaid" with its stock reserved; approving marks it paid (same code path as the PayPal
 * webhook), cancelling releases the stock. No provider is contacted and no money moves.
 */
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/payments.php';
require_customer();

$user = current_user();
$orderId = filter_var($_GET['order_id'] ?? $_POST['order_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!is_payment_demo_request() || !$orderId) {
    not_found('That sample payment is not available.');
}

// Owner check: customers can only ever pay for their own order.
$stmt = db()->prepare("SELECT order_id, status, payment_status, payment_reference, total_amount FROM orders WHERE order_id = ? AND user_id = ? AND payment_provider = 'demo'");
$stmt->execute([$orderId, $user['user_id']]);
$order = $stmt->fetch();
if (!$order) {
    not_found('That sample payment is not available.');
}
if ($order['status'] !== 'pending' || $order['payment_status'] !== 'unpaid') {
    flash('info', 'Order #' . (int)$orderId . ' is no longer waiting for payment.');
    redirect('order_detail.php?id=' . (int)$orderId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $decision = (string)($_POST['decision'] ?? '');
    try {
        if ($decision === 'approve') {
            mark_order_paid((int)$orderId, 'demo', (string)$order['payment_reference']);
            unset($_SESSION['payment_demo_label'][$orderId]);
            flash('success', 'Simulated payment approved for order #' . (int)$orderId . '. No money was taken.');
            redirect('order_detail.php?id=' . (int)$orderId . '&placed=1');
        }
        if ($decision === 'cancel') {
            cancel_unpaid_order((int)$orderId, (int)$user['user_id'], 'Online payment was cancelled');
            unset($_SESSION['payment_demo_label'][$orderId]);
            flash('info', 'Payment was cancelled and the items were returned to stock.');
            redirect('order_detail.php?id=' . (int)$orderId);
        }
        flash('error', 'Choose Approve or Cancel.');
    } catch (Throwable $ex) {
        error_log('Payment simulator failed for order #' . (int)$orderId . ': ' . $ex->getMessage());
        flash('error', $ex instanceof RuntimeException ? $ex->getMessage() : 'The simulated payment could not be completed.');
    }
    redirect('order_detail.php?id=' . (int)$orderId);
}

$method = (string)($_SESSION['payment_demo_label'][$orderId] ?? 'Sample payment');
page_header('Payment simulator');
?>
<section class="form-card narrow">
    <p class="eyebrow">Payment simulator &middot; localhost only</p>
    <h1><?= e($method) ?> checkout</h1>
    <div class="alert alert-info" role="status">This page stands in for the payment provider while the site runs on localhost. No provider is contacted and <strong>no money is taken</strong>. On the live site customers are sent to PayPal instead.</div>
    <dl class="summary-lines">
        <div><dt>Order</dt><dd>#<?= (int)$orderId ?></dd></div>
        <div><dt>Payment method</dt><dd><?= e($method) ?> (simulated)</dd></div>
        <div><dt>Reference</dt><dd><?= e($order['payment_reference']) ?></dd></div>
        <div class="summary-total"><dt>Amount to approve</dt><dd><?= money($order['total_amount']) ?></dd></div>
    </dl>
    <p class="small-text">Order #<?= (int)$orderId ?> has been created as <strong>Unpaid</strong> and its items are reserved. Approving marks it paid; cancelling returns the items to stock.</p>
    <div class="actions">
        <form method="post" class="inline-form">
            <?= csrf_input() ?>
            <input type="hidden" name="order_id" value="<?= (int)$orderId ?>">
            <input type="hidden" name="decision" value="approve">
            <button class="button" type="submit" data-once>Approve simulated payment</button>
        </form>
        <form method="post" class="inline-form">
            <?= csrf_input() ?>
            <input type="hidden" name="order_id" value="<?= (int)$orderId ?>">
            <input type="hidden" name="decision" value="cancel">
            <button class="button button-secondary" type="submit">Cancel payment</button>
        </form>
    </div>
</section>
<?php page_footer(); ?>
