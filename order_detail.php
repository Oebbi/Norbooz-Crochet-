<?php
require_once __DIR__ . '/config/functions.php';
require_customer();

$user = current_user();
$orderId = filter_var($_GET['id'] ?? $_POST['order_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$orderId) {
    not_found('That order does not exist.');
}

// Customers may cancel their own order while it is still pending.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    verify_csrf();
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $owner = $pdo->prepare('SELECT status FROM orders WHERE order_id = ? AND user_id = ? FOR UPDATE');
        $owner->execute([$orderId, $user['user_id']]);
        $status = $owner->fetchColumn();
        if ($status === false) {
            throw new RuntimeException('Order not found.');
        }
        if ($status !== 'pending') {
            throw new RuntimeException('This order is already being made, so it cannot be cancelled online. Please contact us.');
        }
        change_order_status($pdo, $orderId, 'cancelled', $user['user_id'], 'Cancelled by customer');
        $pdo->commit();
        send_email(SHOP_EMAIL, "Order #$orderId cancelled by customer", "{$user['full_name']} cancelled order #$orderId. Stock has been returned automatically.");
        flash('success', 'Order #' . $orderId . ' was cancelled.');
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        flash('error', $ex instanceof RuntimeException ? $ex->getMessage() : 'The order could not be cancelled.');
    }
    redirect('order_detail.php?id=' . $orderId);
}

// The user_id condition stops customers opening other people's orders by changing the id.
$stmt = db()->prepare('SELECT * FROM orders WHERE order_id = ? AND user_id = ?');
$stmt->execute([$orderId, $user['user_id']]);
$order = $stmt->fetch();
if (!$order) {
    not_found('That order does not exist.');
}

$items = db()->prepare(
    'SELECT oi.quantity, oi.unit_price, p.product_id, p.name, p.category, p.image_path
     FROM order_items oi JOIN products p ON p.product_id = oi.product_id
     WHERE oi.order_id = ? ORDER BY oi.order_item_id'
);
$items->execute([$orderId]);
$items = $items->fetchAll();

$history = db()->prepare('SELECT new_status, note, changed_at FROM order_status_history WHERE order_id = ? ORDER BY changed_at, history_id');
$history->execute([$orderId]);
$history = $history->fetchAll();

$steps = ['pending', 'in_progress', 'ready', 'completed'];
$currentStep = array_search($order['status'], $steps, true);

page_header('Order #' . $orderId);
?>
<nav class="breadcrumb" aria-label="Breadcrumb"><a href="<?= e(url('my_orders.php')) ?>">My orders</a> <span aria-hidden="true">/</span> <span aria-current="page">Order #<?= (int)$orderId ?></span></nav>

<section class="page-heading">
    <h1>Order #<?= (int)$orderId ?> <?= status_badge($order['status']) ?></h1>
    <p>Placed <?= e(format_date($order['order_date'])) ?>. <?= e(status_description($order['status'])) ?></p>
</section>

<?php if (isset($_GET['placed']) && $order['status'] === 'pending'): ?>
    <div class="callout">
        <h2>What happens next</h2>
        <p><?= e(PAYMENT_INSTRUCTIONS) ?></p>
        <p class="small-text">A confirmation has been sent to your email address.</p>
    </div>
<?php endif; ?>

<?php if ($order['status'] !== 'cancelled'): ?>
<ol class="progress-steps" aria-label="Order progress">
    <?php foreach ($steps as $index => $step): ?>
        <li class="<?= $currentStep !== false && $index <= $currentStep ? 'done' : '' ?>"<?= $index === $currentStep ? ' aria-current="step"' : '' ?>><?= e(format_status($step)) ?></li>
    <?php endforeach; ?>
</ol>
<?php endif; ?>

<div class="checkout-layout">
    <section class="form-card" aria-labelledby="items-heading">
        <h2 id="items-heading">Items</h2>
        <ul class="line-items">
            <?php foreach ($items as $item): ?>
                <li>
                    <img src="<?= e(url(product_image($item['image_path'], (string)$item['category'], true))) ?>" alt="" width="64" height="64">
                    <a href="<?= e(url('product.php?id=' . (int)$item['product_id'])) ?>"><?= e($item['name']) ?></a>
                    <span><?= (int)$item['quantity'] ?> &times; <?= money($item['unit_price']) ?></span>
                    <strong><?= money((float)$item['unit_price'] * (int)$item['quantity']) ?></strong>
                </li>
            <?php endforeach; ?>
        </ul>
        <dl class="summary-lines">
            <div><dt>Subtotal</dt><dd><?= money($order['subtotal_amount']) ?></dd></div>
            <div><dt>Delivery (<?= $order['delivery_method'] === 'pickup' ? 'pickup' : 'Australia Post' ?>)</dt><dd><?= money($order['delivery_fee']) ?></dd></div>
            <div class="summary-total"><dt>Total</dt><dd><?= money($order['total_amount']) ?></dd></div>
        </dl>
        <?php if ($order['custom_note']): ?><p><strong>Your note:</strong> <?= e($order['custom_note']) ?></p><?php endif; ?>
    </section>

    <aside class="summary-card">
        <h2>Delivery details</h2>
        <p><?= e($order['phone']) ?><br><?= nl2br(e($order['address'])) ?></p>
        <h2>History</h2>
        <ol class="timeline">
            <?php foreach ($history as $event): ?>
                <li><strong><?= e(format_status($event['new_status'])) ?></strong><span class="small-text"><?= e(format_date($event['changed_at'])) ?><?= $event['note'] ? ' &middot; ' . e($event['note']) : '' ?></span></li>
            <?php endforeach; ?>
        </ol>
        <?php if ($order['status'] === 'pending'): ?>
            <form method="post" data-confirm="Cancel order #<?= (int)$orderId ?>? This cannot be undone online.">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="order_id" value="<?= (int)$orderId ?>">
                <button class="button button-secondary button-block" type="submit">Cancel this order</button>
            </form>
        <?php endif; ?>
        <p class="small-text">Questions? Email <a href="mailto:<?= e(SHOP_EMAIL) ?>?subject=Order%20%23<?= (int)$orderId ?>"><?= e(SHOP_EMAIL) ?></a> and quote order #<?= (int)$orderId ?>.</p>
    </aside>
</div>
<?php page_footer(); ?>
