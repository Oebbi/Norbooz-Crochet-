<?php
require_once __DIR__ . '/config/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $customerId = filter_var($_POST['user_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    try {
        if (!$customerId) {
            throw new RuntimeException('Customer not found.');
        }
        if (user_open_work_count(db(), $customerId) > 0) {
            throw new RuntimeException('This customer still has open orders or custom requests. Finish or cancel them first.');
        }
        deidentify_user(db(), $customerId);
        flash('success', 'The customer account was deleted and personal details removed. Order totals are kept for your records.');
    } catch (RuntimeException $ex) {
        flash('error', $ex->getMessage());
    }
    redirect('admin_customers.php');
}

$search = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 80));
$params = [];
$where = "u.role = 'customer' AND u.is_deleted = 0";
if ($search !== '') {
    $where .= ' AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $params = [$like, $like, $like];
}
$stmt = db()->prepare(
    "SELECT u.user_id, u.full_name, u.email, u.phone, u.marketing_opt_in, u.created_at,
            COUNT(o.order_id) AS orders,
            COALESCE(SUM(CASE WHEN o.status <> 'cancelled' THEN o.total_amount END), 0) AS spent,
            MAX(o.order_date) AS last_order
     FROM users u LEFT JOIN orders o ON o.user_id = u.user_id
     WHERE $where
     GROUP BY u.user_id, u.full_name, u.email, u.phone, u.marketing_opt_in, u.created_at
     ORDER BY u.created_at DESC"
);
$stmt->execute($params);
$customers = $stmt->fetchAll();

page_header('Customers');
admin_nav();
?>
<section class="page-heading"><h1>Customers</h1><p><?= count($customers) ?> customer account<?= count($customers) === 1 ? '' : 's' ?>. Only contact customers about their orders, or with news if they opted in.</p></section>

<form class="filter-bar" method="get">
    <div class="filter-search"><label for="q" class="sr-only">Search customers</label><input id="q" type="search" name="q" value="<?= e($search) ?>" placeholder="Name, email or phone"></div>
    <button class="button button-small" type="submit">Search</button>
</form>

<div class="table-wrap" role="region" aria-label="Customers table (scrolls sideways on small screens)" tabindex="0">
    <table>
        <thead><tr><th scope="col">Customer</th><th scope="col">Phone</th><th scope="col">Joined</th><th scope="col">Orders</th><th scope="col">Spent</th><th scope="col">News</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
        <tbody>
        <?php foreach ($customers as $customer): ?>
            <tr>
                <td><strong><?= e($customer['full_name']) ?></strong><br><a class="small-text" href="<?= e(url('admin_orders.php?q=' . rawurlencode($customer['email']))) ?>"><?= e($customer['email']) ?></a></td>
                <td><?= e($customer['phone']) ?></td>
                <td><?= e(format_date($customer['created_at'], false)) ?></td>
                <td><?= (int)$customer['orders'] ?><?= $customer['last_order'] ? '<br><span class="small-text">last ' . e(format_date($customer['last_order'], false)) . '</span>' : '' ?></td>
                <td><?= money($customer['spent']) ?></td>
                <td><?= (int)$customer['marketing_opt_in'] ? 'Opted in' : '-' ?></td>
                <td>
                    <form method="post" data-confirm="Delete this customer and remove their personal details? This cannot be undone.">
                        <?= csrf_input() ?>
                        <input type="hidden" name="user_id" value="<?= (int)$customer['user_id'] ?>">
                        <button class="button button-small button-danger" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php page_footer(); ?>
