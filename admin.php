<?php
require_once __DIR__ . '/config/functions.php';
require_admin();
$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    try {
        if ($action === 'add_product') {
            $name = trim((string)($_POST['name'] ?? ''));
            $category = trim((string)($_POST['category'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
            $stock = filter_var($_POST['stock_qty'] ?? null, FILTER_VALIDATE_INT);
            $image = trim((string)($_POST['image_path'] ?? 'assets/images/product-placeholder.svg'));
            if ($name === '' || $category === '' || $description === '') throw new RuntimeException('Complete all product text fields.');
            if ($price === false || $price < 0) throw new RuntimeException('Enter a valid non-negative price.');
            if ($stock === false || $stock < 0) throw new RuntimeException('Enter a valid non-negative stock quantity.');
            $stmt = $pdo->prepare('INSERT INTO products (name, category, description, price, stock_qty, image_path, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)');
            $stmt->execute([$name, $category, $description, $price, $stock, $image ?: 'assets/images/product-placeholder.svg']);
            flash('success', 'Product added.');
            redirect('admin.php');
        }
        if ($action === 'update_product') {
            $id = (int)($_POST['product_id'] ?? 0);
            $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
            $stock = filter_var($_POST['stock_qty'] ?? null, FILTER_VALIDATE_INT);
            $active = isset($_POST['is_active']) ? 1 : 0;
            if ($id <= 0 || $price === false || $price < 0 || $stock === false || $stock < 0) throw new RuntimeException('Invalid product update values.');
            $stmt = $pdo->prepare('UPDATE products SET price = ?, stock_qty = ?, is_active = ? WHERE product_id = ?');
            $stmt->execute([$price, $stock, $active, $id]);
            flash('success', 'Product updated.');
            redirect('admin.php');
        }
        if ($action === 'update_order') {
            $orderId = (int)($_POST['order_id'] ?? 0);
            $status = (string)($_POST['status'] ?? '');
            if ($orderId <= 0 || !is_valid_order_status($status)) throw new RuntimeException('Invalid order status update.');
            $stmt = $pdo->prepare('UPDATE orders SET status = ? WHERE order_id = ?');
            $stmt->execute([$status, $orderId]);
            flash('success', 'Order #' . $orderId . ' status updated.');
            redirect('admin.php');
        }
        throw new RuntimeException('Unknown administrator action.');
    } catch (Throwable $ex) {
        $errors[] = $ex instanceof RuntimeException ? $ex->getMessage() : 'The administrator update could not be completed.';
    }
}

$summary = [
    'active_products'=>(int)$pdo->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn(),
    'low_stock'=>(int)$pdo->query('SELECT COUNT(*) FROM products WHERE is_active = 1 AND stock_qty <= 2')->fetchColumn(),
    'pending_orders'=>(int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending','in_progress')")->fetchColumn(),
];
$products = $pdo->query('SELECT * FROM products ORDER BY product_id DESC')->fetchAll();
$orderSql = "SELECT o.order_id, o.order_date, o.status, o.total_amount, u.full_name, u.email,
            GROUP_CONCAT(CONCAT(p.name, ' x ', oi.quantity) ORDER BY oi.order_item_id SEPARATOR ', ') AS items
            FROM orders o JOIN users u ON u.user_id=o.user_id JOIN order_items oi ON oi.order_id=o.order_id JOIN products p ON p.product_id=oi.product_id
            GROUP BY o.order_id ORDER BY o.order_date DESC, o.order_id DESC";
$orders = $pdo->query($orderSql)->fetchAll();
page_header('Admin Panel');
?>
<section class="page-heading"><h1>Administrator Panel</h1><p>Manage products, stock and order status. Access is restricted to the administrator role.</p></section>
<?php if ($errors): ?><div class="alert alert-error" role="alert"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<div class="stats"><div class="stat"><strong><?= $summary['active_products'] ?></strong><span>Active products</span></div><div class="stat"><strong><?= $summary['low_stock'] ?></strong><span>Low-stock products</span></div><div class="stat"><strong><?= $summary['pending_orders'] ?></strong><span>Pending/in-progress orders</span></div></div>
<section class="admin-section" aria-labelledby="add-product"><h2 id="add-product">Add product</h2><form method="post" class="form-grid"><?= csrf_input() ?><input type="hidden" name="action" value="add_product">
<div><label for="name">Name</label><input id="name" name="name" required></div><div><label for="category">Category</label><input id="category" name="category" required></div><div><label for="price">Price</label><input id="price" type="number" step="0.01" min="0" name="price" required></div><div><label for="stock_qty">Stock</label><input id="stock_qty" type="number" min="0" name="stock_qty" required></div><div class="full"><label for="description">Description</label><textarea id="description" name="description" rows="2" required></textarea></div><div class="full"><label for="image_path">Image path</label><input id="image_path" name="image_path" value="assets/images/product-placeholder.svg"></div><div class="full"><button class="button" type="submit">Add product</button></div>
</form></section>
<section class="admin-section" aria-labelledby="manage-products"><h2 id="manage-products">Product and stock management</h2><div class="table-wrap"><table><thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Active</th><th>Action</th></tr></thead><tbody>
<?php foreach ($products as $p): ?><tr><td><?= e($p['name']) ?></td><td><?= e($p['category']) ?></td><td colspan="4"><form method="post" class="inline-form"><?= csrf_input() ?><input type="hidden" name="action" value="update_product"><input type="hidden" name="product_id" value="<?= (int)$p['product_id'] ?>"><label class="sr-only" for="price-<?= (int)$p['product_id'] ?>">Price for <?= e($p['name']) ?></label><input id="price-<?= (int)$p['product_id'] ?>" type="number" name="price" step="0.01" min="0" value="<?= e((string)$p['price']) ?>"><label class="sr-only" for="stock-<?= (int)$p['product_id'] ?>">Stock for <?= e($p['name']) ?></label><input id="stock-<?= (int)$p['product_id'] ?>" type="number" name="stock_qty" min="0" value="<?= (int)$p['stock_qty'] ?>"><label class="checkbox"><input type="checkbox" name="is_active" <?= (int)$p['is_active'] ? 'checked' : '' ?>> Active</label><button class="button button-small" type="submit">Update</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<section class="admin-section" aria-labelledby="manage-orders"><h2 id="manage-orders">Order management</h2><?php if (!$orders): ?><p>No orders have been placed.</p><?php else: ?><div class="table-wrap"><table><thead><tr><th>Order</th><th>Customer</th><th>Items</th><th>Total</th><th>Date</th><th>Status</th></tr></thead><tbody>
<?php foreach ($orders as $o): ?><tr><td>#<?= (int)$o['order_id'] ?></td><td><?= e($o['full_name']) ?><br><span class="small-text"><?= e($o['email']) ?></span></td><td><?= e($o['items']) ?></td><td><?= money($o['total_amount']) ?></td><td><?= e(date('d M Y', strtotime($o['order_date']))) ?></td><td><form method="post" class="inline-form"><?= csrf_input() ?><input type="hidden" name="action" value="update_order"><input type="hidden" name="order_id" value="<?= (int)$o['order_id'] ?>"><label class="sr-only" for="status-<?= (int)$o['order_id'] ?>">Status for order <?= (int)$o['order_id'] ?></label><select id="status-<?= (int)$o['order_id'] ?>" name="status"><?php foreach (['pending','in_progress','ready','completed','cancelled'] as $s): ?><option value="<?= e($s) ?>" <?= $o['status']===$s?'selected':'' ?>><?= e(format_status($s)) ?></option><?php endforeach; ?></select><button class="button button-small" type="submit">Update</button></form></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></section>
<?php page_footer(); ?>
