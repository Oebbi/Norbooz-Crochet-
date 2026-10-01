<?php
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/payments.php';

if (is_admin()) {
    redirect('admin.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    $return = safe_next($_POST['return'] ?? '', 'cart.php');

    if ($action === 'add') {
        $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $quantity = filter_var($_POST['quantity'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 99]]);
        $stmt = db()->prepare('SELECT name, stock_qty FROM products WHERE product_id = ? AND is_active = 1');
        $stmt->execute([(int)$productId]);
        $product = $stmt->fetch();

        if (!$productId || !$quantity || !$product) {
            flash('error', 'That product could not be added.');
        } elseif ((int)$product['stock_qty'] < 1) {
            flash('error', $product['name'] . ' is sold out.');
        } else {
            $wanted = (int)(cart()[$productId] ?? 0) + $quantity;
            $final = min($wanted, (int)$product['stock_qty']);
            cart_set((int)$productId, $final);
            flash($final < $wanted ? 'info' : 'success', $final < $wanted
                ? 'Only ' . $product['stock_qty'] . ' of ' . $product['name'] . ' are available, so your cart has ' . $final . '.'
                : $product['name'] . ' was added to your cart.');
        }
        redirect($return);
    }

    if ($action === 'update') {
        $quantities = is_array($_POST['quantity'] ?? null) ? $_POST['quantity'] : [];
        foreach ($quantities as $id => $qty) {
            $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $qty = filter_var($qty, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 99]]);
            if ($id && $qty !== false && array_key_exists($id, cart())) {
                cart_set($id, $qty);
            }
        }
        flash('success', 'Your cart was updated.');
        redirect('cart.php');
    }

    if ($action === 'remove') {
        cart_set((int)($_POST['product_id'] ?? 0), 0);
        flash('success', 'The item was removed from your cart.');
        redirect('cart.php');
    }
    redirect('cart.php');
}

[$lines, $subtotal, $notices] = cart_lines();
$paymentOptions = online_payment_options();
foreach ($notices as $notice) {
    flash('info', $notice);
}

page_header('Your cart');
?>
<section class="page-heading">
    <p class="eyebrow">Checkout step 1 of 2</p>
    <h1>Your cart</h1>
</section>

<?php if (!$lines): ?>
    <div class="empty-state">
        <h2>Your cart is empty</h2>
        <p>Browse the shop and add something handmade.</p>
        <a class="button" href="<?= e(url('products.php')) ?>">Browse products</a>
    </div>
<?php else: ?>
<div class="checkout-layout">
    <form method="post" class="cart-form" id="cart-form">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="update">
        <div class="cart-list">
            <?php foreach ($lines as $line):
                $id = (int)$line['product_id']; ?>
                <article class="cart-row" data-unit="<?= (int)$line['unit_cents'] ?>">
                    <a href="<?= e(url('product.php?id=' . $id)) ?>" tabindex="-1" aria-hidden="true"><img src="<?= e(url(product_image($line['image_path'], $line['category'], true))) ?>" alt="" width="96" height="96"></a>
                    <div class="cart-row-info">
                        <h2><a href="<?= e(url('product.php?id=' . $id)) ?>"><?= e($line['name']) ?></a></h2>
                        <p class="small-text"><?= money($line['price']) ?> each &middot; <?= (int)$line['stock_qty'] ?> available</p>
                    </div>
                    <div class="qty-wrap">
                        <label for="qty-<?= $id ?>">Qty</label>
                        <input class="quantity-input" id="qty-<?= $id ?>" type="number" name="quantity[<?= $id ?>]" min="0" max="<?= (int)$line['stock_qty'] ?>" value="<?= (int)$line['quantity'] ?>">
                    </div>
                    <strong class="line-total"><?= money($line['line_cents'] / 100) ?></strong>
                    <button class="link-button remove-button" type="submit" form="remove-<?= $id ?>" aria-label="Remove: <?= e($line['name']) ?>">Remove</button>
                </article>
            <?php endforeach; ?>
        </div>
        <div class="actions">
            <button class="button button-secondary" type="submit">Update quantities</button>
            <a class="text-link" href="<?= e(url('products.php')) ?>">Continue shopping</a>
        </div>
    </form>
    <?php foreach ($lines as $line): ?>
        <form id="remove-<?= (int)$line['product_id'] ?>" method="post" class="hidden"><?= csrf_input() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="product_id" value="<?= (int)$line['product_id'] ?>"></form>
    <?php endforeach; ?>

    <aside class="summary-card" aria-labelledby="summary-heading">
        <h2 id="summary-heading">Order summary</h2>
        <dl class="summary-lines">
            <div><dt>Subtotal</dt><dd id="cart-subtotal"><?= money($subtotal / 100) ?></dd></div>
            <div><dt>Delivery</dt><dd>Chosen at checkout</dd></div>
        </dl>
        <p class="small-text"><?= PICKUP_ENABLED ? 'Free pickup in Canberra. ' : '' ?>Post <?= money(POSTAGE_FEE) ?><?= FREE_POSTAGE_OVER > 0 ? ', free over ' . money(FREE_POSTAGE_OVER) : '' ?>.</p>
        <a class="button button-block" href="<?= e(url('checkout.php')) ?>"><?= current_user() ? 'Continue to checkout' : 'Log in to check out' ?></a>
        <p class="small-text"><?= isset($paymentOptions['paypal']) ? 'Secure PayPal payment is available at checkout, along with PayID or bank transfer.' : (isset($paymentOptions['demo_apple_pay']) ? 'Local sample payment options are available at checkout. They take no payment.' : 'We confirm your order by email and send PayID or bank transfer details.') ?></p>
    </aside>
</div>
<?php endif; ?>
<?php page_footer(); ?>
