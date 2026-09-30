<?php
require_once __DIR__ . '/config/functions.php';
require_customer();

$user = current_user();
[$lines, $subtotal, $notices] = cart_lines();
foreach ($notices as $notice) {
    flash('info', $notice);
}
if (!$lines) {
    flash('info', 'Your cart is empty. Add a product before checking out.');
    redirect('cart.php');
}

$profile = db()->prepare('SELECT phone, address FROM users WHERE user_id = ?');
$profile->execute([$user['user_id']]);
$profile = $profile->fetch() ?: ['phone' => '', 'address' => ''];

$errors = [];
$form = [
    'delivery_method' => PICKUP_ENABLED ? (string)($_POST['delivery_method'] ?? 'post') : 'post',
    'phone' => trim((string)($_POST['phone'] ?? $profile['phone'])),
    'address' => trim((string)($_POST['address'] ?? $profile['address'])),
    'custom_note' => trim((string)($_POST['custom_note'] ?? '')),
];

// One-time checkout token stops the same order being submitted twice (double click / refresh).
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_SESSION['checkout_token'])) {
    $_SESSION['checkout_token'] = bin2hex(random_bytes(16));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $checkoutToken = (string)($_POST['checkout_token'] ?? '');
    if (!hash_equals((string)($_SESSION['checkout_token'] ?? ''), $checkoutToken)) {
        flash('info', 'This order was already submitted. Check My orders before trying again.');
        redirect('my_orders.php');
    }

    if (!in_array($form['delivery_method'], PICKUP_ENABLED ? ['pickup', 'post'] : ['post'], true)) {
        $errors[] = 'Choose a delivery method.';
    }
    if (!valid_phone($form['phone'])) {
        $errors[] = 'Enter a contact phone number (8-20 digits).';
    }
    if ($form['delivery_method'] === 'post' && !text_length_ok($form['address'], 8, 255)) {
        $errors[] = 'Enter your full postal address.';
    }
    if (mb_strlen($form['custom_note']) > 500) {
        $errors[] = 'Order notes must be 500 characters or fewer.';
    }
    if (!isset($_POST['accept_terms'])) {
        $errors[] = 'Please confirm you have read how payment and delivery work.';
    }

    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();

            // Lock every product row in id order, then re-check price, availability and stock.
            $items = [];
            $subtotalCents = 0;
            $cart = cart();
            ksort($cart);
            $lock = $pdo->prepare('SELECT product_id, name, price, stock_qty, is_active FROM products WHERE product_id = ? FOR UPDATE');
            foreach ($cart as $productId => $quantity) {
                $lock->execute([(int)$productId]);
                $product = $lock->fetch();
                if (!$product || !(int)$product['is_active']) {
                    throw new RuntimeException('A product in your cart is no longer available. Please review your cart.');
                }
                if ((int)$quantity > (int)$product['stock_qty']) {
                    throw new RuntimeException($product['name'] . ' has only ' . (int)$product['stock_qty'] . ' left. Please update your cart.');
                }
                $unit = to_cents($product['price']);
                $items[] = ['product_id' => (int)$productId, 'name' => $product['name'], 'quantity' => (int)$quantity, 'unit_cents' => $unit];
                $subtotalCents += $unit * (int)$quantity;
            }

            $feeCents = delivery_fee_cents($form['delivery_method'], $subtotalCents);
            $totalCents = $subtotalCents + $feeCents;
            $address = $form['delivery_method'] === 'pickup' ? 'Pickup - ' . SHOP_LOCATION : $form['address'];

            $pdo->prepare(
                "INSERT INTO orders (user_id, order_date, status, subtotal_amount, delivery_method, delivery_fee, total_amount, phone, address, custom_note)
                 VALUES (?, NOW(), 'pending', ?, ?, ?, ?, ?, ?, ?)"
            )->execute([
                $user['user_id'], cents_to_decimal($subtotalCents), $form['delivery_method'], cents_to_decimal($feeCents),
                cents_to_decimal($totalCents), $form['phone'], $address, $form['custom_note'] !== '' ? $form['custom_note'] : null,
            ]);
            $orderId = (int)$pdo->lastInsertId();

            $insertItem = $pdo->prepare('INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)');
            $reduceStock = $pdo->prepare('UPDATE products SET stock_qty = stock_qty - ? WHERE product_id = ? AND stock_qty >= ?');
            foreach ($items as $item) {
                $insertItem->execute([$orderId, $item['product_id'], $item['quantity'], cents_to_decimal($item['unit_cents'])]);
                $reduceStock->execute([$item['quantity'], $item['product_id'], $item['quantity']]);
                if ($reduceStock->rowCount() !== 1) {
                    throw new RuntimeException($item['name'] . ' just sold out. Please review your cart.');
                }
            }
            $pdo->prepare("INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, note) VALUES (?, NULL, 'pending', ?, 'Order placed')")
                ->execute([$orderId, $user['user_id']]);

            $pdo->commit();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Checkout failed: ' . $ex->getMessage());
            $errors[] = $ex instanceof RuntimeException ? $ex->getMessage() : 'Your order could not be placed. Nothing was charged. Please try again.';
        }

        if (!$errors) {
            unset($_SESSION['checkout_token']);
            cart_clear();

            // Remember contact details for next time if the customer's profile was empty.
            if ($profile['phone'] === '' || $profile['address'] === '') {
                db()->prepare("UPDATE users SET phone = IF(phone = '', ?, phone), address = IF(address = '', ?, address) WHERE user_id = ?")
                    ->execute([$form['phone'], $form['address'], $user['user_id']]);
            }

            $email = db()->prepare('SELECT email FROM users WHERE user_id = ?');
            $email->execute([$user['user_id']]);
            $email = (string)$email->fetchColumn();
            $itemText = implode("\n", array_map(fn($i) => '- ' . $i['name'] . ' x ' . $i['quantity'] . ' @ ' . money($i['unit_cents'] / 100), $items));
            $summary = "Order #$orderId\n$itemText\n\nSubtotal: " . money($subtotalCents / 100)
                . "\nDelivery (" . ($form['delivery_method'] === 'pickup' ? 'pickup' : 'post') . '): ' . money($feeCents / 100)
                . "\nTotal: " . money($totalCents / 100);
            send_email($email, "Order #$orderId received", "Hi {$user['full_name']},\n\nThank you for your order!\n\n$summary\n\nWhat happens next:\n" . PAYMENT_INSTRUCTIONS . "\n\nTrack your order: " . absolute_url('order_detail.php?id=' . $orderId));
            send_email(SHOP_EMAIL, "New order #$orderId from {$user['full_name']}", "$summary\n\nPhone: {$form['phone']}\nAddress: $address\nNote: " . ($form['custom_note'] ?: '-') . "\n\nManage: " . absolute_url('admin_order.php?id=' . $orderId));

            flash('success', 'Thank you! Order #' . $orderId . ' has been placed.');
            redirect('order_detail.php?id=' . $orderId . '&placed=1');
        }
        $_SESSION['checkout_token'] = bin2hex(random_bytes(16));
        [$lines, $subtotal] = cart_lines();
    }
}

$postFee = delivery_fee_cents('post', $subtotal);
page_header('Checkout');
?>
<section class="page-heading">
    <p class="eyebrow">Checkout step 2 of 2</p>
    <h1>Delivery and contact details</h1>
</section>

<?= render_errors($errors) ?>

<form method="post" class="checkout-layout" id="checkout-form" data-subtotal="<?= (int)$subtotal ?>">
    <?= csrf_input() ?>
    <input type="hidden" name="checkout_token" value="<?= e($_SESSION['checkout_token']) ?>">
    <div class="form-card">
        <fieldset>
            <legend>How would you like to receive your order?</legend>
            <?php if (PICKUP_ENABLED): ?>
                <label class="radio-card"><input type="radio" name="delivery_method" value="pickup" data-fee="0" <?= $form['delivery_method'] === 'pickup' ? 'checked' : '' ?>>
                    <span><strong>Pickup in <?= e(SHOP_LOCATION) ?></strong><span class="small-text">Free. We will email a pickup time.</span></span></label>
            <?php endif; ?>
            <label class="radio-card"><input type="radio" name="delivery_method" value="post" data-fee="<?= $postFee ?>" <?= $form['delivery_method'] === 'post' ? 'checked' : '' ?>>
                <span><strong>Australia Post</strong><span class="small-text"><?= $postFee === 0 ? 'Free for this order' : money($postFee / 100) . (FREE_POSTAGE_OVER > 0 ? ', free over ' . money(FREE_POSTAGE_OVER) : '') ?></span></span></label>
        </fieldset>

        <label for="phone">Contact phone</label>
        <input id="phone" name="phone" type="tel" maxlength="20" value="<?= e($form['phone']) ?>" autocomplete="tel" required>

        <label for="address">Postal address <span class="hint">(not needed for pickup)</span></label>
        <textarea id="address" name="address" rows="3" maxlength="255" autocomplete="street-address"><?= e($form['address']) ?></textarea>

        <label for="custom_note">Notes for the maker <span class="hint">(optional)</span></label>
        <textarea id="custom_note" name="custom_note" rows="3" maxlength="500" placeholder="Gift message, colour preference or anything else we agreed"><?= e($form['custom_note']) ?></textarea>

        <label class="checkbox"><input type="checkbox" name="accept_terms" value="1" required> I understand no payment is taken online: Norbooz Crochet will email me to confirm the order and send payment details.</label>
    </div>

    <aside class="summary-card" aria-labelledby="summary-heading">
        <h2 id="summary-heading">Your order</h2>
        <ul class="summary-items">
            <?php foreach ($lines as $line): ?>
                <li><span><?= e($line['name']) ?> &times; <?= (int)$line['quantity'] ?></span><span><?= money($line['line_cents'] / 100) ?></span></li>
            <?php endforeach; ?>
        </ul>
        <dl class="summary-lines">
            <div><dt>Subtotal</dt><dd><?= money($subtotal / 100) ?></dd></div>
            <div><dt>Delivery</dt><dd id="delivery-fee"><?= money(delivery_fee_cents($form['delivery_method'], $subtotal) / 100) ?></dd></div>
            <div class="summary-total"><dt>Total</dt><dd id="order-total"><?= money(($subtotal + delivery_fee_cents($form['delivery_method'], $subtotal)) / 100) ?></dd></div>
        </dl>
        <button class="button button-block" type="submit" data-once>Place order</button>
        <a class="text-link small" href="<?= e(url('cart.php')) ?>">Edit cart</a>
    </aside>
</form>
<?php page_footer(); ?>
