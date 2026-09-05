<?php
require_once __DIR__ . '/config/functions.php';
require_login();
if (current_user()['role'] !== 'customer') redirect('admin.php');
$sql = "SELECT o.order_id, o.order_date, o.status, o.total_amount, o.phone, o.address, o.custom_note,
        GROUP_CONCAT(CONCAT(p.name, ' x ', oi.quantity, ' @ $', FORMAT(oi.unit_price, 2)) ORDER BY oi.order_item_id SEPARATOR ' | ') AS items
        FROM orders o
        JOIN order_items oi ON oi.order_id = o.order_id
        JOIN products p ON p.product_id = oi.product_id
        WHERE o.user_id = ?
        GROUP BY o.order_id
        ORDER BY o.order_date DESC, o.order_id DESC";
$stmt = db()->prepare($sql);
$stmt->execute([current_user()['user_id']]);
$orders = $stmt->fetchAll();
page_header('My Orders');
?>
<section class="page-heading"><h1>My Orders</h1><p>Only orders belonging to your logged-in account are displayed.</p></section>
<?php if (!$orders): ?><div class="empty-state"><h2>No orders yet</h2><p>Browse the product catalogue and place your first order.</p><a class="button" href="<?= e(url('products.php')) ?>">Browse products</a></div>
<?php else: ?><div class="table-wrap"><table><thead><tr><th scope="col">Order</th><th scope="col">Date</th><th scope="col">Items</th><th scope="col">Total</th><th scope="col">Status</th><th scope="col">Delivery details</th></tr></thead><tbody>
<?php foreach ($orders as $order): ?><tr><td>#<?= (int)$order['order_id'] ?></td><td><?= e(date('d M Y', strtotime($order['order_date']))) ?></td><td><?= e($order['items']) ?><?php if ($order['custom_note']): ?><div class="small-text">Note: <?= e($order['custom_note']) ?></div><?php endif; ?></td><td><?= money($order['total_amount']) ?></td><td><span class="status status-<?= e($order['status']) ?>"><?= e(format_status($order['status'])) ?></span></td><td><?= e($order['phone']) ?><br><?= nl2br(e($order['address'])) ?></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
<?php page_footer(); ?>
