<?php
require_once __DIR__ . '/config/functions.php';
require_admin();

$pdo = db();
$low = (int)LOW_STOCK_LEVEL;

$stats = $pdo->query(
    "SELECT
        (SELECT COUNT(*) FROM orders WHERE status = 'pending') AS pending,
        (SELECT COUNT(*) FROM orders WHERE status IN ('in_progress','ready')) AS in_work,
        (SELECT COUNT(*) FROM custom_requests WHERE status IN ('new','reviewing')) AS open_requests,
        (SELECT COUNT(*) FROM products WHERE is_active = 1 AND stock_qty <= $low) AS low_stock,
        (SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE status <> 'cancelled' AND order_date >= DATE_FORMAT(NOW(), '%Y-%m-01')) AS month_sales,
        (SELECT COUNT(*) FROM orders WHERE status <> 'cancelled' AND order_date >= DATE_FORMAT(NOW(), '%Y-%m-01')) AS month_orders,
        (SELECT COUNT(*) FROM users WHERE role = 'customer' AND is_deleted = 0) AS customers"
)->fetch();

$recent = $pdo->query(
    'SELECT o.order_id, o.order_date, o.status, o.total_amount, u.full_name
     FROM orders o JOIN users u ON u.user_id = o.user_id
     ORDER BY o.order_date DESC, o.order_id DESC LIMIT 6'
)->fetchAll();

$lowStock = $pdo->query("SELECT product_id, name, stock_qty FROM products WHERE is_active = 1 AND stock_qty <= $low ORDER BY stock_qty, name LIMIT 8")->fetchAll();

$top = $pdo->query(
    "SELECT p.product_id, p.name, SUM(oi.quantity) AS sold
     FROM order_items oi JOIN orders o ON o.order_id = oi.order_id JOIN products p ON p.product_id = oi.product_id
     WHERE o.status <> 'cancelled'
     GROUP BY p.product_id, p.name ORDER BY sold DESC LIMIT 5"
)->fetchAll();

$adminPw = $pdo->prepare('SELECT password_changed_at FROM users WHERE user_id = ?');
$adminPw->execute([current_user()['user_id']]);
$defaultPassword = $adminPw->fetchColumn() === null;

page_header('Admin dashboard');
admin_nav();
?>
<section class="page-heading">
    <h1>Dashboard</h1>
    <p><?= e(date('l j F Y')) ?></p>
</section>

<?php if ($defaultPassword): ?>
    <div class="alert alert-error" role="alert">Security: you are using the temporary administrator password from the installation. <a href="<?= e(url('account.php')) ?>">Change it now</a> before the site goes live.</div>
<?php endif; ?>

<div class="stats">
    <a class="stat" href="<?= e(url('admin_orders.php?status=pending')) ?>"><strong><?= (int)$stats['pending'] ?></strong><span>New orders to confirm</span></a>
    <a class="stat" href="<?= e(url('admin_orders.php?status=active')) ?>"><strong><?= (int)$stats['in_work'] ?></strong><span>In progress or ready</span></a>
    <a class="stat" href="<?= e(url('admin_requests.php?status=open')) ?>"><strong><?= (int)$stats['open_requests'] ?></strong><span>Custom requests to answer</span></a>
    <a class="stat" href="<?= e(url('admin_products.php?filter=low')) ?>"><strong><?= (int)$stats['low_stock'] ?></strong><span>Products low on stock</span></a>
    <div class="stat"><strong><?= money($stats['month_sales']) ?></strong><span><?= (int)$stats['month_orders'] ?> orders this month</span></div>
    <a class="stat" href="<?= e(url('admin_customers.php')) ?>"><strong><?= (int)$stats['customers'] ?></strong><span>Customers</span></a>
</div>

<div class="admin-columns">
    <section class="admin-section" aria-labelledby="recent-heading">
        <div class="section-heading-row"><h2 id="recent-heading">Latest orders</h2><a class="text-link" href="<?= e(url('admin_orders.php')) ?>">All orders</a></div>
        <?php if (!$recent): ?><p>No orders yet.</p><?php else: ?>
        <div class="table-wrap" role="region" aria-label="Latest orders table (scrolls sideways on small screens)" tabindex="0"><table>
            <thead><tr><th scope="col">Order</th><th scope="col">Customer</th><th scope="col">Date</th><th scope="col">Total</th><th scope="col">Status</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $order): ?>
                <tr>
                    <td><a href="<?= e(url('admin_order.php?id=' . (int)$order['order_id'])) ?>">#<?= (int)$order['order_id'] ?></a></td>
                    <td><?= e($order['full_name']) ?></td>
                    <td><?= e(format_date($order['order_date'], false)) ?></td>
                    <td><?= money($order['total_amount']) ?></td>
                    <td><?= status_badge($order['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </section>

    <div>
        <section class="admin-section" aria-labelledby="low-heading">
            <h2 id="low-heading">Low stock</h2>
            <?php if (!$lowStock): ?><p>All active products have more than <?= $low ?> in stock.</p><?php else: ?>
            <ul class="plain-list">
                <?php foreach ($lowStock as $item): ?>
                    <li><a href="<?= e(url('admin_product_edit.php?id=' . (int)$item['product_id'])) ?>"><?= e($item['name']) ?></a> <span class="badge<?= (int)$item['stock_qty'] === 0 ? ' badge-muted' : '' ?>"><?= (int)$item['stock_qty'] ?> left</span></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </section>
        <section class="admin-section" aria-labelledby="top-heading">
            <h2 id="top-heading">Best sellers</h2>
            <?php if (!$top): ?><p>No sales yet.</p><?php else: ?>
            <ol class="plain-list">
                <?php foreach ($top as $item): ?><li><?= e($item['name']) ?> <span class="small-text"><?= (int)$item['sold'] ?> sold</span></li><?php endforeach; ?>
            </ol>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php page_footer(); ?>
