<?php
require_once __DIR__ . '/config/functions.php';
require_admin();

$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'add_product' || $action === 'update_product') {
            $productId = (int)($_POST['product_id'] ?? 0);
            $name = trim((string)($_POST['name'] ?? ''));
            $category = trim((string)($_POST['category'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
            $stock = filter_var($_POST['stock_qty'] ?? null, FILTER_VALIDATE_INT);
            $image = safe_product_image_path((string)($_POST['image_path'] ?? ''));
            $active = isset($_POST['is_active']) ? 1 : 0;

            if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
                throw new RuntimeException('Product name must be between 2 and 120 characters.');
            }
            if (!in_array($category, product_categories(), true)) {
                throw new RuntimeException('Choose one of the approved Norbooz Crochet product families.');
            }
            if ($description === '' || mb_strlen($description) > 500) {
                throw new RuntimeException('Enter a description of 500 characters or fewer.');
            }
            if ($price === false || $price < 0 || $price > 999999.99) {
                throw new RuntimeException('Enter a valid non-negative product price.');
            }
            if ($stock === false || $stock < 0 || $stock > 100000) {
                throw new RuntimeException('Enter a valid non-negative stock quantity.');
            }

            if ($action === 'add_product') {
                $stmt = $pdo->prepare(
                    'INSERT INTO products (name, category, description, price, stock_qty, image_path, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$name, $category, $description, $price, $stock, $image, $active]);
                flash('success', 'Product added successfully.');
                redirect('admin.php');
            }

            if ($productId <= 0) {
                throw new RuntimeException('Invalid product identifier.');
            }

            $stmt = $pdo->prepare(
                'UPDATE products
                 SET name = ?, category = ?, description = ?, price = ?, stock_qty = ?, image_path = ?, is_active = ?
                 WHERE product_id = ?'
            );
            $stmt->execute([$name, $category, $description, $price, $stock, $image, $active, $productId]);
            flash('success', 'Product #' . $productId . ' updated successfully.');
            redirect('admin.php');
        }

        if ($action === 'update_order') {
            $orderId = (int)($_POST['order_id'] ?? 0);
            $newStatus = (string)($_POST['status'] ?? '');

            if ($orderId <= 0 || !is_valid_order_status($newStatus)) {
                throw new RuntimeException('Invalid order status update.');
            }

            $pdo->beginTransaction();

            $orderStmt = $pdo->prepare('SELECT status FROM orders WHERE order_id = ? FOR UPDATE');
            $orderStmt->execute([$orderId]);
            $order = $orderStmt->fetch();
            if (!$order) {
                throw new RuntimeException('Order not found.');
            }

            $oldStatus = (string)$order['status'];

            if ($oldStatus !== $newStatus) {
                $itemStmt = $pdo->prepare(
                    'SELECT oi.product_id, oi.quantity, p.name, p.stock_qty
                     FROM order_items oi
                     JOIN products p ON p.product_id = oi.product_id
                     WHERE oi.order_id = ?
                     FOR UPDATE'
                );
                $itemStmt->execute([$orderId]);
                $items = $itemStmt->fetchAll();

                // Cancelling an active order returns its reserved stock.
                if ($oldStatus !== 'cancelled' && $newStatus === 'cancelled') {
                    $restore = $pdo->prepare('UPDATE products SET stock_qty = stock_qty + ? WHERE product_id = ?');
                    foreach ($items as $item) {
                        $restore->execute([(int)$item['quantity'], (int)$item['product_id']]);
                    }
                }

                // Re-opening a cancelled order reserves stock again only if enough is available.
                if ($oldStatus === 'cancelled' && $newStatus !== 'cancelled') {
                    foreach ($items as $item) {
                        if ((int)$item['quantity'] > (int)$item['stock_qty']) {
                            throw new RuntimeException(
                                'Cannot re-open this order because there is not enough stock for ' . $item['name'] . '.'
                            );
                        }
                    }
                    $reserve = $pdo->prepare('UPDATE products SET stock_qty = stock_qty - ? WHERE product_id = ?');
                    foreach ($items as $item) {
                        $reserve->execute([(int)$item['quantity'], (int)$item['product_id']]);
                    }
                }

                $updateOrder = $pdo->prepare('UPDATE orders SET status = ? WHERE order_id = ?');
                $updateOrder->execute([$newStatus, $orderId]);
            }

            $pdo->commit();
            flash('success', 'Order #' . $orderId . ' status updated to ' . format_status($newStatus) . '.');
            redirect('admin.php');
        }

        throw new RuntimeException('Unknown administrator action.');
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = $ex instanceof RuntimeException
            ? $ex->getMessage()
            : 'The administrator update could not be completed.';
    }
}

$summary = [
    'active_products' => (int)$pdo->query('SELECT COUNT(*) FROM products WHERE is_active = 1')->fetchColumn(),
    'low_stock' => (int)$pdo->query('SELECT COUNT(*) FROM products WHERE is_active = 1 AND stock_qty <= 2')->fetchColumn(),
    'pending_orders' => (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('pending','in_progress')")->fetchColumn(),
];

$products = $pdo->query('SELECT * FROM products ORDER BY product_id DESC')->fetchAll();

$orderSql = "SELECT o.order_id, o.order_date, o.status, o.total_amount, o.phone, o.address, o.custom_note,
            u.full_name, u.email,
            GROUP_CONCAT(CONCAT(p.name, ' x ', oi.quantity) ORDER BY oi.order_item_id SEPARATOR ', ') AS items
            FROM orders o
            JOIN users u ON u.user_id = o.user_id
            JOIN order_items oi ON oi.order_id = o.order_id
            JOIN products p ON p.product_id = oi.product_id
            GROUP BY o.order_id, o.order_date, o.status, o.total_amount, o.phone, o.address, o.custom_note, u.full_name, u.email
            ORDER BY o.order_date DESC, o.order_id DESC";
$orders = $pdo->query($orderSql)->fetchAll();

page_header('Admin Panel');
?>
<section class="page-heading">
    <h1>Administrator Panel</h1>
    <p>Manage product details, stock, availability and customer order status.</p>
</section>

<?php if ($errors): ?>
    <div class="alert alert-error" role="alert">
        <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<div class="stats">
    <div class="stat"><strong><?= $summary['active_products'] ?></strong><span>Active products</span></div>
    <div class="stat"><strong><?= $summary['low_stock'] ?></strong><span>Low-stock products</span></div>
    <div class="stat"><strong><?= $summary['pending_orders'] ?></strong><span>Pending/in-progress orders</span></div>
</div>

<section class="admin-section" aria-labelledby="add-product">
    <h2 id="add-product">Add product</h2>
    <form method="post" class="form-grid">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="add_product">

        <div><label for="name">Name</label><input id="name" name="name" maxlength="120" required></div>
        <div><label for="category">Product family</label><select id="category" name="category" required><option value="">Choose category</option><?php foreach (product_categories() as $categoryOption): ?><option value="<?= e($categoryOption) ?>"><?= e($categoryOption) ?></option><?php endforeach; ?></select></div>
        <div><label for="price">Price ($)</label><input id="price" type="number" step="0.01" min="0" max="999999.99" name="price" required></div>
        <div><label for="stock_qty">Stock</label><input id="stock_qty" type="number" min="0" max="100000" name="stock_qty" required></div>
        <div class="full"><label for="description">Description</label><textarea id="description" name="description" rows="3" maxlength="500" required></textarea></div>
        <div class="full"><label for="image_path">Image path</label><input id="image_path" name="image_path" value="assets/images/product-placeholder.svg"><span class="hint">For real photos, copy the image to assets/images/products/ and enter a path such as assets/images/products/capybara.jpg</span></div>
        <div class="full checkbox-row"><label class="checkbox"><input type="checkbox" name="is_active" checked> Active product</label></div>
        <div class="full"><button class="button" type="submit">Add product</button></div>
    </form>
</section>

<section class="admin-section" aria-labelledby="manage-products">
    <h2 id="manage-products">Product and stock management</h2>
    <p class="small-text">Edit any product field, change stock, or clear Active to hide a product from customers.</p>

    <div class="admin-product-grid">
        <?php foreach ($products as $product): ?>
            <form method="post" class="admin-product-card">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="update_product">
                <input type="hidden" name="product_id" value="<?= (int)$product['product_id'] ?>">

                <div class="admin-product-title">
                    <img src="<?= e(url(safe_product_image_path((string)$product['image_path']))) ?>" alt="">
                    <div><strong>#<?= (int)$product['product_id'] ?> <?= e($product['name']) ?></strong><div class="small-text"><?= (int)$product['stock_qty'] ?> in stock</div></div>
                </div>

                <label for="p-name-<?= (int)$product['product_id'] ?>">Name</label>
                <input id="p-name-<?= (int)$product['product_id'] ?>" name="name" maxlength="120" value="<?= e($product['name']) ?>" required>

                <label for="p-category-<?= (int)$product['product_id'] ?>">Product family</label>
                <select id="p-category-<?= (int)$product['product_id'] ?>" name="category" required>
                    <?php $currentCategory = canonical_product_category((string)$product['category']); ?>
                    <?php foreach (product_categories() as $categoryOption): ?>
                        <option value="<?= e($categoryOption) ?>" <?= $currentCategory === $categoryOption ? 'selected' : '' ?>><?= e($categoryOption) ?></option>
                    <?php endforeach; ?>
                </select>

                <label for="p-description-<?= (int)$product['product_id'] ?>">Description</label>
                <textarea id="p-description-<?= (int)$product['product_id'] ?>" name="description" rows="3" maxlength="500" required><?= e($product['description']) ?></textarea>

                <div class="two-col">
                    <div><label for="p-price-<?= (int)$product['product_id'] ?>">Price ($)</label><input id="p-price-<?= (int)$product['product_id'] ?>" type="number" name="price" step="0.01" min="0" max="999999.99" value="<?= e($product['price']) ?>" required></div>
                    <div><label for="p-stock-<?= (int)$product['product_id'] ?>">Stock</label><input id="p-stock-<?= (int)$product['product_id'] ?>" type="number" name="stock_qty" min="0" max="100000" value="<?= (int)$product['stock_qty'] ?>" required></div>
                </div>

                <label for="p-image-<?= (int)$product['product_id'] ?>">Image path</label>
                <input id="p-image-<?= (int)$product['product_id'] ?>" name="image_path" value="<?= e($product['image_path']) ?>">

                <label class="checkbox"><input type="checkbox" name="is_active" <?= (int)$product['is_active'] ? 'checked' : '' ?>> Active</label>
                <button class="button button-small" type="submit">Save product</button>
            </form>
        <?php endforeach; ?>
    </div>
</section>

<section class="admin-section" aria-labelledby="manage-orders">
    <h2 id="manage-orders">Order management</h2>
    <p class="small-text">Cancelling an order restores its stock. Re-opening a cancelled order is allowed only when enough stock remains.</p>

    <?php if (!$orders): ?>
        <p>No orders have been placed.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Items & note</th>
                        <th>Contact / delivery</th>
                        <th>Total</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td>#<?= (int)$order['order_id'] ?></td>
                            <td><?= e($order['full_name']) ?><br><span class="small-text"><?= e($order['email']) ?></span></td>
                            <td><?= e($order['items']) ?><?php if ($order['custom_note']): ?><div class="small-text">Note: <?= e($order['custom_note']) ?></div><?php endif; ?></td>
                            <td><?= e($order['phone']) ?><br><?= nl2br(e($order['address'])) ?></td>
                            <td><?= money($order['total_amount']) ?></td>
                            <td><?= e(date('d M Y, g:i a', strtotime($order['order_date']))) ?></td>
                            <td>
                                <form method="post" class="status-form">
                                    <?= csrf_input() ?>
                                    <input type="hidden" name="action" value="update_order">
                                    <input type="hidden" name="order_id" value="<?= (int)$order['order_id'] ?>">
                                    <label class="sr-only" for="status-<?= (int)$order['order_id'] ?>">Status for order <?= (int)$order['order_id'] ?></label>
                                    <select id="status-<?= (int)$order['order_id'] ?>" name="status">
                                        <?php foreach (['pending', 'in_progress', 'ready', 'completed', 'cancelled'] as $status): ?>
                                            <option value="<?= e($status) ?>" <?= $order['status'] === $status ? 'selected' : '' ?>><?= e(format_status($status)) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="button button-small" type="submit">Update</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php page_footer(); ?>
