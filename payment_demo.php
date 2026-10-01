<?php
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/payments.php';
require_customer();

$demo = $_SESSION['payment_demo'] ?? null;
unset($_SESSION['payment_demo']);
if (!is_payment_demo_request() || !is_array($demo) || !is_demo_payment_method((string)($demo['method'] ?? ''))) {
    not_found('That sample payment is not available.');
}

$labels = [
    'demo_apple_pay' => 'Apple Pay',
    'demo_afterpay' => 'Afterpay',
    'demo_paypal' => 'PayPal',
];
$method = $labels[$demo['method']];
page_header('Sample payment');
?>
<section class="form-card narrow">
    <p class="eyebrow">Local sample only</p>
    <h1><?= e($method) ?> checkout preview</h1>
    <p>This is a demonstration of the payment step. No payment provider was contacted, no money was taken, no order was created, and stock was not changed.</p>
    <dl class="summary-lines">
        <div><dt>Sample payment method</dt><dd><?= e($method) ?></dd></div>
        <div><dt>Estimated cart total</dt><dd><?= money(((int)($demo['total_cents'] ?? 0)) / 100) ?></dd></div>
        <div class="summary-total"><dt>Amount charged</dt><dd><?= money(0) ?></dd></div>
    </dl>
    <div class="actions">
        <a class="button" href="<?= e(url('checkout.php')) ?>">Return to checkout</a>
        <a class="button button-secondary" href="<?= e(url('cart.php')) ?>">View cart</a>
    </div>
</section>
<?php page_footer(); ?>
