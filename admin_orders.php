<?php
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/payments.php';
require_admin();

$status = (string)($_GET['status'] ?? '');
$validFilters = array_merge(['', 'active'], order_statuses());
if (!in_array($status, $validFilters, true)) {
    $status = '';
}
$search = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 80));

$where = ['1 = 1'];
$params = [];
if ($status === 'active') {
    $where[] = "o.status IN ('pending','in_progress','ready')";
} elseif ($status !== '') {
    $where[] = 'o.status = ?';
    $params[] = $status;
}
if ($search !== '') {
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $where[] = '(u.full_name LIKE ? OR u.email LIKE ? OR o.order_id = ?)';
    array_push($params, $like, $like, ctype_digit(ltrim($search, '#')) ? (int)ltrim($search, '#') : 0);
}
$whereSql = implode(' AND ', $where);

$sql = "SELECT o.order_id, o.order_date, o.status, o.total_amount, o.delivery_method, o.phone, o.address, o.custom_note, o.payment_status, o.payment_provider,
               u.full_name, u.email,
               GROUP_CONCAT(CONCAT(p.name, ' x ', oi.quantity) ORDER BY oi.order_item_id SEPARATOR ', ') AS items
        FROM orders o
        JOIN users u ON u.user_id = o.user_id
        JOIN order_items oi ON oi.order_id = o.order_id
        JOIN products p ON p.product_id = oi.product_id
        WHERE $whereSql
        GROUP BY o.order_id, o.order_date, o.status, o.total_amount, o.delivery_method, o.phone, o.address, o.custom_note, o.payment_status, o.payment_provider, u.full_name, u.email
        ORDER BY o.order_date DESC, o.order_id DESC";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// CSV export of the filtered list for bookkeeping.
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="norbooz-orders-' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Order', 'Date', 'Status', 'Customer', 'Email', 'Items', 'Delivery', 'Total', 'Payment method', 'Payment status'], ',', '"', '\\');
    foreach ($orders as $order) {
        // Prefix cells that start with formula characters so spreadsheets do not execute them.
        $row = [$order['order_id'], $order['order_date'], $order['status'], $order['full_name'], $order['email'], $order['items'], $order['delivery_method'], $order['total_amount'], $order['payment_provider'], $order['payment_status']];
        $row = array_map(fn($v) => preg_match('/^[=+\-@\t\r]/', (string)$v) ? "'" . $v : $v, $row);
        fputcsv($out, $row, ',', '"', '\\');
    }
    fclose($out);
    exit;
}

page_header('Orders');
admin_nav();
$filters = ['' => 'All', 'active' => 'Open', 'pending' => 'Pending', 'in_progress' => 'In progress', 'ready' => 'Ready', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
?>
<section class="page-heading"><h1>Orders</h1><p><?= count($orders) ?> order<?= count($orders) === 1 ? '' : 's' ?> shown.</p></section>

<form class="filter-bar" method="get">
    <div class="filter-search"><label for="q" class="sr-only">Search orders</label><input id="q" type="search" name="q" value="<?= e($search) ?>" placeholder="Order number, customer name or email"></div>
    <div><label for="status" class="sr-only">Status</label>
        <select id="status" name="status">
            <?php foreach ($filters as $key => $label): ?><option value="<?= e($key) ?>" <?= $key === $status ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
        </select></div>
    <button class="button button-small" type="submit">Filter</button>
    <a class="text-link small" href="<?= e(url('admin_orders.php?' . http_build_query(array_filter(['status' => $status, 'q' => $search, 'export' => 'csv'])))) ?>">Export CSV</a>
</form>

<?php if (!$orders): ?>
    <p>No orders match this filter.</p>
<?php else: ?>
<div class="table-wrap" role="region" aria-label="Orders table (scrolls sideways on small screens)" tabindex="0">
    <table>
        <thead><tr><th scope="col">Order</th><th scope="col">Customer</th><th scope="col">Items</th><th scope="col">Delivery</th><th scope="col">Total</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
        <?php foreach ($orders as $order): ?>
            <tr>
                <td><strong>#<?= (int)$order['order_id'] ?></strong><br><span class="small-text"><?= e(format_date($order['order_date'])) ?></span></td>
                <td><?= e($order['full_name']) ?><br><span class="small-text"><?= e($order['email']) ?></span></td>
                <td><?= e($order['items']) ?><?php if ($order['custom_note']): ?><div class="small-text">Note: <?= e($order['custom_note']) ?></div><?php endif; ?></td>
                <td><?= $order['delivery_method'] === 'pickup' ? 'Pickup' : 'Post' ?></td>
                <td><?= money($order['total_amount']) ?></td>
                <td><?= status_badge($order['status']) ?><?php if ($order['payment_provider'] !== 'manual'): ?><br><span class="small-text"><?= e(payment_status_label((string)$order['payment_status'])) ?> online</span><?php endif; ?></td>
                <td><a class="button button-small button-secondary" href="<?= e(url('admin_order.php?id=' . (int)$order['order_id'])) ?>">Manage</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
<?php page_footer(); ?>
