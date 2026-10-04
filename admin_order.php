<?php
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/payments.php';
require_admin();

$orderId = filter_var($_GET['id'] ?? $_POST['order_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$orderId) {
    not_found('Order not found.');
}
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $postAction = (string)($_POST['action'] ?? 'status');

    // Update: correct the contact and delivery details on an order that is still open.
    if ($postAction === 'details') {
        $phone = trim((string)($_POST['phone'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));
        $customNote = trim((string)($_POST['custom_note'] ?? ''));
        if (!valid_phone($phone)) {
            $errors[] = 'Enter a contact phone number (8-20 digits).';
        }
        if (!text_length_ok($address, 3, 255)) {
            $errors[] = 'Enter a delivery address, or "Pickup - Canberra, ACT".';
        }
        if (mb_strlen($customNote) > 500) {
            $errors[] = 'The note must be 500 characters or fewer.';
        }
        if (!$errors) {
            $stmt = db()->prepare("UPDATE orders SET phone = ?, address = ?, custom_note = ?, updated_at = NOW() WHERE order_id = ? AND status IN ('pending','in_progress','ready')");
            $stmt->execute([$phone, $address, $customNote !== '' ? $customNote : null, $orderId]);
            if ($stmt->rowCount() === 0) {
                $errors[] = 'Only pending, in progress or ready orders can be edited.';
            } else {
                flash('success', 'Order #' . $orderId . ' details were updated.');
                redirect('admin_order.php?id=' . $orderId);
            }
        }
    }

    // Delete: only cancelled orders (their stock was already returned) can be removed. Items and history cascade.
    if ($postAction === 'delete') {
        $stmt = db()->prepare("DELETE FROM orders WHERE order_id = ? AND status = 'cancelled'");
        $stmt->execute([$orderId]);
        if ($stmt->rowCount() === 0) {
            $errors[] = 'Only cancelled orders can be deleted. Cancel the order first.';
        } else {
            flash('success', 'Cancelled order #' . $orderId . ' was deleted.');
            redirect('admin_orders.php');
        }
    }
}

// Online payments: cancel an abandoned (unpaid) checkout, or record a refund made at the provider.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['action'] ?? '', ['cancel_unpaid', 'refund'], true)) {
    $paymentNote = trim(mb_substr((string)($_POST['payment_note'] ?? ''), 0, 200));
    try {
        if ($_POST['action'] === 'cancel_unpaid') {
            admin_cancel_unpaid_order($orderId, (int)current_user()['user_id'], $paymentNote);
            flash('success', 'Unpaid order #' . $orderId . ' was cancelled and its items returned to stock.');
        } else {
            if (!isset($_POST['confirm_refund'])) {
                throw new RuntimeException('Tick the box to confirm the refund has been made at the payment provider.');
            }
            mark_order_refunded($orderId, (int)current_user()['user_id'], $paymentNote);
            $customer = db()->prepare('SELECT u.full_name, u.email, u.is_deleted, o.total_amount FROM orders o JOIN users u ON u.user_id = o.user_id WHERE o.order_id = ?');
            $customer->execute([$orderId]);
            $customer = $customer->fetch();
            if ($customer && !(int)$customer['is_deleted']) {
                send_email($customer['email'], "Refund for order #$orderId", "Hi {$customer['full_name']},\n\nYour payment of " . money($customer['total_amount']) . " for order #$orderId has been refunded. Refunds usually appear within 3-5 business days."
                    . ($paymentNote !== '' ? "\n\nMessage from the maker: $paymentNote" : '') . "\n\nView your order: " . absolute_url('order_detail.php?id=' . $orderId));
            }
            flash('success', 'Order #' . $orderId . ' is marked as refunded and the customer was emailed.');
        }
        redirect('admin_order.php?id=' . $orderId);
    } catch (Throwable $ex) {
        $errors[] = $ex instanceof RuntimeException ? $ex->getMessage() : 'The payment could not be updated.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? 'status') === 'status') {
    $newStatus = (string)($_POST['status'] ?? '');
    $note = trim(mb_substr((string)($_POST['note'] ?? ''), 0, 255));
    $notify = isset($_POST['notify']);
    $pdo = db();
    try {
        if (!is_valid_order_status($newStatus)) {
            throw new RuntimeException('Choose a valid status.');
        }
        $payment = $pdo->prepare('SELECT payment_status, payment_provider FROM orders WHERE order_id = ?');
        $payment->execute([$orderId]);
        $payment = $payment->fetch();
        if (!$payment) {
            throw new RuntimeException('Order not found.');
        }
        if ($payment['payment_provider'] !== 'manual' && $payment['payment_status'] !== 'paid') {
            throw new RuntimeException('This order has not been paid. Complete or cancel its online checkout before changing fulfillment status.');
        }
        if ($payment['payment_provider'] !== 'manual' && $newStatus === 'cancelled') {
            throw new RuntimeException('This order was paid online. Refund it at the payment provider, then use "Record a refund" on this page.');
        }
        $pdo->beginTransaction();
        $order = change_order_status($pdo, $orderId, $newStatus, current_user()['user_id'], $note);
        $pdo->commit();

        if ($notify && isset($order['old_status'])) {
            $customer = db()->prepare('SELECT full_name, email, is_deleted FROM users WHERE user_id = ?');
            $customer->execute([$order['user_id']]);
            $customer = $customer->fetch();
            if ($customer && !(int)$customer['is_deleted']) {
                send_email($customer['email'], "Order #$orderId is now " . format_status($newStatus),
                    "Hi {$customer['full_name']},\n\nYour order #$orderId is now: " . format_status($newStatus) . ".\n" . status_description($newStatus)
                    . ($note !== '' ? "\n\nMessage from the maker: $note" : '') . "\n\nView your order: " . absolute_url('order_detail.php?id=' . $orderId));
            }
        }
        flash('success', 'Order #' . $orderId . ' is now ' . format_status($newStatus) . ($notify ? ' and the customer was emailed.' : '.'));
        redirect('admin_order.php?id=' . $orderId);
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = $ex instanceof RuntimeException ? $ex->getMessage() : 'The status could not be updated.';
    }
}

$stmt = db()->prepare('SELECT o.*, u.full_name, u.email FROM orders o JOIN users u ON u.user_id = o.user_id WHERE o.order_id = ?');
$stmt->execute([$orderId]);
$order = $stmt->fetch();
if (!$order) {
    not_found('Order not found.');
}

$items = db()->prepare(
    'SELECT oi.quantity, oi.unit_price, p.product_id, p.name, p.category, p.image_path, p.stock_qty
     FROM order_items oi JOIN products p ON p.product_id = oi.product_id WHERE oi.order_id = ? ORDER BY oi.order_item_id'
);
$items->execute([$orderId]);
$items = $items->fetchAll();

$history = db()->prepare(
    'SELECT h.old_status, h.new_status, h.note, h.changed_at, u.full_name, u.role
     FROM order_status_history h LEFT JOIN users u ON u.user_id = h.changed_by
     WHERE h.order_id = ? ORDER BY h.changed_at, h.history_id'
);
$history->execute([$orderId]);
$history = $history->fetchAll();

$next = allowed_status_transitions($order['status']);
if ($order['payment_provider'] !== 'manual') {
    $next = $order['payment_status'] === 'paid'
        ? array_values(array_filter($next, static fn(string $status): bool => $status !== 'cancelled'))
        : [];
}

page_header('Order #' . $orderId);
admin_nav();
?>
<nav class="breadcrumb" aria-label="Breadcrumb"><a href="<?= e(url('admin_orders.php')) ?>">Orders</a> <span aria-hidden="true">/</span> <span aria-current="page">#<?= (int)$orderId ?></span></nav>
<section class="page-heading">
    <h1>Order #<?= (int)$orderId ?> <?= status_badge($order['status']) ?></h1>
    <p>Placed <?= e(format_date($order['order_date'])) ?> by <?= e($order['full_name']) ?>.</p>
</section>

<?= render_errors($errors) ?>

<div class="checkout-layout">
    <div>
        <section class="form-card" aria-labelledby="items-heading">
            <h2 id="items-heading">Items</h2>
            <ul class="line-items">
                <?php foreach ($items as $item): ?>
                    <li>
                        <img src="<?= e(url(product_image($item['image_path'], (string)$item['category'], true))) ?>" alt="" width="64" height="64">
                        <a href="<?= e(url('admin_product_edit.php?id=' . (int)$item['product_id'])) ?>"><?= e($item['name']) ?></a>
                        <span><?= (int)$item['quantity'] ?> &times; <?= money($item['unit_price']) ?><br><span class="small-text"><?= (int)$item['stock_qty'] ?> now in stock</span></span>
                        <strong><?= money((float)$item['unit_price'] * (int)$item['quantity']) ?></strong>
                    </li>
                <?php endforeach; ?>
            </ul>
            <dl class="summary-lines">
                <div><dt>Subtotal</dt><dd><?= money($order['subtotal_amount']) ?></dd></div>
                <div><dt>Delivery (<?= $order['delivery_method'] === 'pickup' ? 'pickup' : 'post' ?>)</dt><dd><?= money($order['delivery_fee']) ?></dd></div>
                <div class="summary-total"><dt>Order total</dt><dd><?= money($order['total_amount']) ?></dd></div>
            </dl>
            <?php if ($order['custom_note']): ?><p class="callout"><strong>Customer note:</strong> <?= e($order['custom_note']) ?></p><?php endif; ?>
        </section>

        <section class="form-card" aria-labelledby="history-heading">
            <h2 id="history-heading">Audit history</h2>
            <ol class="timeline">
                <?php foreach ($history as $event): ?>
                    <li><strong><?= $event['old_status'] ? e(format_status($event['old_status'])) . ' &rarr; ' : '' ?><?= e(format_status($event['new_status'])) ?></strong>
                        <span class="small-text"><?= e(format_date($event['changed_at'])) ?> by <?= e($event['full_name'] ?? 'system') ?><?= $event['role'] === 'admin' ? ' (admin)' : '' ?><?= $event['note'] ? ' &middot; ' . e($event['note']) : '' ?></span></li>
                <?php endforeach; ?>
            </ol>
        </section>
    </div>

    <aside>
        <section class="summary-card">
            <h2>Customer</h2>
            <p><strong><?= e($order['full_name']) ?></strong><br><a href="mailto:<?= e($order['email']) ?>?subject=Norbooz%20Crochet%20order%20%23<?= (int)$orderId ?>"><?= e($order['email']) ?></a><br><?= e($order['phone']) ?></p>
            <p><?= $order['delivery_method'] === 'pickup' ? '<strong>Pickup</strong>' : '<strong>Post to:</strong>' ?><br><?= nl2br(e($order['address'])) ?></p>
        </section>

        <section class="summary-card">
            <h2>Payment</h2>
            <p><strong><?= e(payment_status_label((string)$order['payment_status'])) ?></strong><br><?= e(payment_provider_label((string)$order['payment_provider'])) ?><?= $order['paid_at'] ? '<br>Paid ' . e(format_date($order['paid_at'])) : '' ?></p>
            <?php if ($order['payment_reference']): ?><p class="small-text">Reference: <?= e($order['payment_reference']) ?></p><?php endif; ?>
            <?php if ($order['payment_provider'] !== 'manual' && $order['payment_status'] === 'unpaid' && $order['status'] === 'pending'): ?>
                <p class="small-text">The customer has not finished paying. The items stay reserved for <?= (int)payment_expiry_hours() ?> hours, then the order is cancelled automatically.</p>
                <form method="post" data-confirm="Cancel unpaid order #<?= (int)$orderId ?> and return its items to stock?">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="cancel_unpaid">
                    <input type="hidden" name="order_id" value="<?= (int)$orderId ?>">
                    <button class="button button-secondary button-block" type="submit">Cancel unpaid order</button>
                </form>
            <?php elseif ($order['payment_provider'] !== 'manual' && $order['payment_status'] === 'paid'): ?>
                <details class="danger-zone">
                    <summary>Record a refund</summary>
                    <p class="small-text">First refund the customer in your <?= $order['payment_provider'] === 'paypal' ? 'PayPal' : 'payment provider' ?> account. Then record it here: <?= $order['status'] === 'completed' ? 'the payment is marked refunded.' : 'the order is cancelled and its items return to stock.' ?></p>
                    <form method="post" data-confirm="Record a refund for order #<?= (int)$orderId ?>?">
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="refund">
                        <input type="hidden" name="order_id" value="<?= (int)$orderId ?>">
                        <label for="payment_note">Reason <span class="hint">(optional, sent to the customer)</span></label>
                        <input id="payment_note" name="payment_note" maxlength="200">
                        <label class="checkbox"><input type="checkbox" name="confirm_refund" value="1" required> I have refunded this payment at the provider</label>
                        <button class="button button-danger button-block" type="submit">Record refund</button>
                    </form>
                </details>
            <?php endif; ?>
        
        </section>

        <?php if (in_array($order['status'], ['pending', 'in_progress', 'ready'], true)): ?>
        <section class="summary-card">
            <h2>Edit details</h2>
            <form method="post">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="details">
                <input type="hidden" name="order_id" value="<?= (int)$orderId ?>">
                <label for="d-phone">Phone</label>
                <input id="d-phone" name="phone" type="tel" maxlength="20" value="<?= e($order['phone']) ?>" required>
                <label for="d-address"><?= $order['delivery_method'] === 'pickup' ? 'Pickup details' : 'Post to' ?></label>
                <textarea id="d-address" name="address" rows="3" maxlength="255" required><?= e($order['address']) ?></textarea>
                <label for="d-note">Customer note</label>
                <textarea id="d-note" name="custom_note" rows="2" maxlength="500"><?= e($order['custom_note']) ?></textarea>
                <button class="button button-secondary button-block" type="submit">Save details</button>
            </form>
        </section>
        <?php endif; ?>

        <section class="summary-card">
            <h2>Update status</h2>
            <?php if (!$next): ?>
                <p><?= $order['payment_provider'] !== 'manual' && $order['payment_status'] !== 'paid' ? ($order['status'] === 'cancelled' ? 'This online order is cancelled. It cannot be re-opened; the customer can place a new order.' : 'Online payment must be confirmed before fulfillment can begin.') : 'This order is ' . strtolower(format_status($order['status'])) . '. No further changes are allowed.' ?></p>
            <?php else: ?>
                <form method="post">
                    <?= csrf_input() ?>
                    <input type="hidden" name="order_id" value="<?= (int)$orderId ?>">
                    <label for="status">New status</label>
                    <select id="status" name="status" required>
                        <?php foreach ($next as $option): ?><option value="<?= e($option) ?>"><?= e(format_status($option)) ?></option><?php endforeach; ?>
                    </select>
                    <label for="note">Note <span class="hint">(optional, e.g. payment received, tracking number)</span></label>
                    <input id="note" name="note" maxlength="255">
                    <label class="checkbox"><input type="checkbox" name="notify" value="1" checked> Email the customer about this change</label>
                    <button class="button button-block" type="submit">Update order</button>
                </form>
                <p class="small-text">Cancelling returns the items to stock. A cancelled order can be re-opened only if enough stock is available.</p>
            <?php endif; ?>
        </section>

        <?php if ($order['status'] === 'cancelled'): ?>
        <section class="summary-card">
            <h2>Delete order</h2>
            <p class="small-text">Removes this cancelled order and its history permanently. Completed orders can never be deleted because they are your sales record.</p>
            <form method="post" data-confirm="Permanently delete cancelled order #<?= (int)$orderId ?>?">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="order_id" value="<?= (int)$orderId ?>">
                <button class="button button-danger button-block" type="submit">Delete cancelled order</button>
            </form>
        </section>
        <?php endif; ?>
    </aside>
</div>
<?php page_footer(); ?>
