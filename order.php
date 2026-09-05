<?php
require_once __DIR__ . '/config/functions.php';
require_login();
if (current_user()['role'] !== 'customer') redirect('admin.php');

$pdo = db();
$products = $pdo->query('SELECT product_id, name, category, price, stock_qty, image_path FROM products WHERE is_active = 1 AND stock_qty > 0 ORDER BY name')->fetchAll();
$errors = [];
$note = trim((string)($_POST['custom_note'] ?? ''));
$phone = '';
$address = '';
$stmt = $pdo->prepare('SELECT phone, address FROM users WHERE user_id = ?');
$stmt->execute([current_user()['user_id']]);
$profile = $stmt->fetch() ?: [];
$phone = trim((string)($_POST['phone'] ?? ($profile['phone'] ?? '')));
$address = trim((string)($_POST['address'] ?? ($profile['address'] ?? '')));
$selectedId = (int)($_GET['product_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $raw = $_POST['quantity'] ?? [];
    $requested = [];
    if (is_array($raw)) {
        foreach ($raw as $id => $qty) {
            $pid = (int)$id; $q = filter_var($qty, FILTER_VALIDATE_INT);
            if ($pid > 0 && $q !== false && $q > 0) $requested[$pid] = $q;
        }
    }
    if (!$requested) $errors[] = 'Enter a quantity for at least one product.';
    if ($phone === '') $errors[] = 'Enter a contact phone number.';
    if ($address === '') $errors[] = 'Enter a delivery address.';
    if (mb_strlen($note) > 500) $errors[] = 'Custom note must be 500 characters or fewer.';
    if (!$errors) {
        try {
            $pdo->beginTransaction();
            $items = []; $total = 0.0;
            $lock = $pdo->prepare('SELECT product_id, name, price, stock_qty, is_active FROM products WHERE product_id = ? FOR UPDATE');
            foreach ($requested as $pid => $qty) {
                $lock->execute([$pid]); $p = $lock->fetch();
                if (!$p || !(int)$p['is_active']) throw new RuntimeException('A selected product is no longer available.');
                if ($qty > (int)$p['stock_qty']) throw new RuntimeException($p['name'] . ' has only ' . (int)$p['stock_qty'] . ' item(s) available.');
                $price = (float)$p['price'];
                $items[] = ['id'=>$pid,'qty'=>$qty,'price'=>$price];
                $total += $price * $qty;
            }
            $orderStmt = $pdo->prepare('INSERT INTO orders (user_id, order_date, status, total_amount, phone, address, custom_note) VALUES (?, NOW(), \'pending\', ?, ?, ?, ?)');
            $orderStmt->execute([current_user()['user_id'], $total, $phone, $address, $note ?: null]);
            $orderId = (int)$pdo->lastInsertId();
            $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)');
            $stockStmt = $pdo->prepare('UPDATE products SET stock_qty = stock_qty - ? WHERE product_id = ?');
            foreach ($items as $item) {
                $itemStmt->execute([$orderId, $item['id'], $item['qty'], $item['price']]);
                $stockStmt->execute([$item['qty'], $item['id']]);
            }
            $pdo->commit();
            flash('success', 'Order #' . $orderId . ' was placed successfully.');
            redirect('my_orders.php');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = $ex instanceof RuntimeException ? $ex->getMessage() : 'The order could not be completed. Please try again.';
        }
    }
}
page_header('Place Order');
?>
<section class="page-heading"><h1>Place Order</h1><p>Enter the quantity required for one or more available products. The total is recalculated from current database prices.</p></section>
<?php if ($errors): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if (!$products): ?><div class="empty-state"><h2>No products are currently in stock</h2><p>Please check again later.</p></div><?php else: ?>
<form method="post" id="order-form"><?= csrf_input() ?>
<div class="order-list">
<?php foreach ($products as $p): ?>
<article class="order-row" data-price="<?= e((string)$p['price']) ?>">
<img src="<?= e(url($p['image_path'] ?: 'assets/images/product-placeholder.svg')) ?>" alt="<?= e($p['name']) ?> crochet product">
<div><h2><?= e($p['name']) ?></h2><p><?= e($p['category']) ?> · <?= money($p['price']) ?> · <?= (int)$p['stock_qty'] ?> in stock</p></div>
<div class="qty-wrap"><label for="qty-<?= (int)$p['product_id'] ?>">Quantity</label><input class="quantity-input" id="qty-<?= (int)$p['product_id'] ?>" type="number" name="quantity[<?= (int)$p['product_id'] ?>]" min="0" max="<?= (int)$p['stock_qty'] ?>" value="<?= $selectedId === (int)$p['product_id'] ? '1' : '0' ?>"></div>
</article>
<?php endforeach; ?>
</div>
<div class="form-card"><h2>Contact and customisation</h2>
<label for="phone">Contact phone</label><input id="phone" name="phone" value="<?= e($phone) ?>" required>
<label for="address">Delivery address</label><textarea id="address" name="address" rows="3" required><?= e($address) ?></textarea>
<label for="custom_note">Optional custom note</label><textarea id="custom_note" name="custom_note" rows="3" maxlength="500" placeholder="Colour, size or other agreed customisation details"><?= e($note) ?></textarea>
<div class="order-total" aria-live="polite">Estimated total: <strong id="estimated-total">$0.00</strong></div>
<button class="button" type="submit">Submit order</button>
</div></form>
<?php endif; ?>
<?php page_footer(); ?>
