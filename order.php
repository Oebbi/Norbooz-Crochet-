<?php
require_once __DIR__ . '/config/functions.php';
require_login();

if (current_user()['role'] !== 'customer') {
    redirect('admin.php');
}

$pdo = db();
$products = $pdo->query(
    'SELECT product_id, name, category, price, stock_qty, image_path
     FROM products
     WHERE is_active = 1 AND stock_qty > 0
     ORDER BY name'
)->fetchAll();

$errors = [];
$note = trim((string)($_POST['custom_note'] ?? ''));
$postedQuantities = is_array($_POST['quantity'] ?? null) ? $_POST['quantity'] : [];
$selectedId = (int)($_GET['product_id'] ?? 0);

$stmt = $pdo->prepare('SELECT phone, address FROM users WHERE user_id = ?');
$stmt->execute([current_user()['user_id']]);
$profile = $stmt->fetch() ?: [];

$phone = trim((string)($_POST['phone'] ?? ($profile['phone'] ?? '')));
$address = trim((string)($_POST['address'] ?? ($profile['address'] ?? '')));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $requested = [];
    foreach ($postedQuantities as $id => $qty) {
        $productId = filter_var($id, FILTER_VALIDATE_INT);
        $quantity = filter_var($qty, FILTER_VALIDATE_INT);
        if ($productId !== false && $productId > 0 && $quantity !== false && $quantity > 0) {
            $requested[(int)$productId] = (int)$quantity;
        }
    }

    if (!$requested) {
        $errors[] = 'Enter a quantity for at least one product.';
    }
    if ($phone === '' || mb_strlen($phone) > 30) {
        $errors[] = 'Enter a valid contact phone number.';
    }
    if ($address === '' || mb_strlen($address) > 255) {
        $errors[] = 'Enter a delivery address of 255 characters or fewer.';
    }
    if (mb_strlen($note) > 500) {
        $errors[] = 'Custom note must be 500 characters or fewer.';
    }

    if (!$errors) {
        try {
            $pdo->beginTransaction();

            $items = [];
            $total = 0.0;
            $lockProduct = $pdo->prepare(
                'SELECT product_id, name, price, stock_qty, is_active
                 FROM products
                 WHERE product_id = ?
                 FOR UPDATE'
            );

            foreach ($requested as $productId => $quantity) {
                $lockProduct->execute([$productId]);
                $product = $lockProduct->fetch();

                if (!$product || !(int)$product['is_active']) {
                    throw new RuntimeException('A selected product is no longer available.');
                }
                if ($quantity > (int)$product['stock_qty']) {
                    throw new RuntimeException(
                        $product['name'] . ' has only ' . (int)$product['stock_qty'] . ' item(s) available.'
                    );
                }

                $unitPrice = (float)$product['price'];
                $items[] = [
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                ];
                $total += $unitPrice * $quantity;
            }

            $insertOrder = $pdo->prepare(
                "INSERT INTO orders (user_id, order_date, status, total_amount, phone, address, custom_note)
                 VALUES (?, NOW(), 'pending', ?, ?, ?, ?)"
            );
            $insertOrder->execute([
                current_user()['user_id'],
                $total,
                $phone,
                $address,
                $note !== '' ? $note : null,
            ]);
            $orderId = (int)$pdo->lastInsertId();

            $insertItem = $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, quantity, unit_price)
                 VALUES (?, ?, ?, ?)'
            );
            $reduceStock = $pdo->prepare(
                'UPDATE products SET stock_qty = stock_qty - ? WHERE product_id = ?'
            );

            foreach ($items as $item) {
                $insertItem->execute([
                    $orderId,
                    $item['product_id'],
                    $item['quantity'],
                    $item['unit_price'],
                ]);
                $reduceStock->execute([$item['quantity'], $item['product_id']]);
            }

            $pdo->commit();
            flash('success', 'Order #' . $orderId . ' was placed successfully.');
            redirect('my_orders.php');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $ex instanceof RuntimeException
                ? $ex->getMessage()
                : 'The order could not be completed. Please try again.';
        }
    }
}

page_header('Place Order');
?>
<section class="page-heading">
    <h1>Place Order</h1>
    <p>Choose one or more products. Prices and stock are rechecked by the server before the order is saved.</p>
</section>

<?php if ($errors): ?>
    <div class="alert alert-error" role="alert">
        <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<?php if (!$products): ?>
    <div class="empty-state">
        <h2>No products are currently in stock</h2>
        <p>Please check again later.</p>
    </div>
<?php else: ?>
    <form method="post" id="order-form">
        <?= csrf_input() ?>
        <div class="order-list">
            <?php foreach ($products as $product):
                $productId = (int)$product['product_id'];
                $value = '0';
                if (array_key_exists((string)$productId, $postedQuantities) || array_key_exists($productId, $postedQuantities)) {
                    $value = (string)($postedQuantities[$productId] ?? $postedQuantities[(string)$productId] ?? '0');
                } elseif ($selectedId === $productId) {
                    $value = '1';
                }
            ?>
                <article class="order-row" data-price="<?= e((string)$product['price']) ?>">
                    <img src="<?= e(url(safe_product_image_path((string)$product['image_path']))) ?>" alt="<?= e($product['name']) ?> crochet product">
                    <div>
                        <h2><?= e($product['name']) ?></h2>
                        <p><?= e($product['category']) ?> · <?= money($product['price']) ?> · <?= (int)$product['stock_qty'] ?> in stock</p>
                    </div>
                    <div class="qty-wrap">
                        <label for="qty-<?= $productId ?>">Quantity</label>
                        <input class="quantity-input" id="qty-<?= $productId ?>" type="number" name="quantity[<?= $productId ?>]" min="0" max="<?= (int)$product['stock_qty'] ?>" value="<?= e($value) ?>">
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <div class="form-card">
            <h2>Contact and customisation</h2>

            <label for="phone">Contact phone</label>
            <input id="phone" name="phone" maxlength="30" value="<?= e($phone) ?>" required>

            <label for="address">Delivery address</label>
            <textarea id="address" name="address" rows="3" maxlength="255" required><?= e($address) ?></textarea>

            <label for="custom_note">Optional custom note</label>
            <textarea id="custom_note" name="custom_note" rows="3" maxlength="500" placeholder="Colour, size or other agreed customisation details"><?= e($note) ?></textarea>

            <div class="order-total" aria-live="polite">Estimated total: <strong id="estimated-total">$0.00</strong></div>
            <p class="small-text">No payment is taken through this academic prototype.</p>
            <button class="button" type="submit">Submit order</button>
        </div>
    </form>
<?php endif; ?>
<?php page_footer(); ?>
