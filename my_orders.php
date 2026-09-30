<?php
require_once __DIR__ . '/config/functions.php';
require_customer();

$userId = current_user()['user_id'];

$orders = db()->prepare(
    "SELECT o.order_id, o.order_date, o.status, o.total_amount, o.delivery_method,
            COUNT(oi.order_item_id) AS line_count, COALESCE(SUM(oi.quantity), 0) AS item_count,
            MIN(p.image_path) AS image_path, MIN(p.category) AS category,
            GROUP_CONCAT(p.name ORDER BY oi.order_item_id SEPARATOR ', ') AS item_names
     FROM orders o
     JOIN order_items oi ON oi.order_id = o.order_id
     JOIN products p ON p.product_id = oi.product_id
     WHERE o.user_id = ?
     GROUP BY o.order_id, o.order_date, o.status, o.total_amount, o.delivery_method
     ORDER BY o.order_date DESC, o.order_id DESC"
);
$orders->execute([$userId]);
$orders = $orders->fetchAll();

$requests = db()->prepare('SELECT * FROM custom_requests WHERE user_id = ? ORDER BY created_at DESC, request_id DESC');
$requests->execute([$userId]);
$requests = $requests->fetchAll();

page_header('My orders');
?>
<section class="page-heading">
    <p class="eyebrow">Your account</p>
    <h1>My orders</h1>
    <p>Only orders and requests that belong to your account are shown here.</p>
</section>

<section class="section-tight" aria-labelledby="orders-heading">
    <h2 id="orders-heading">Shop orders</h2>
    <?php if (!$orders): ?>
        <div class="empty-state">
            <h3>No orders yet</h3>
            <p>When you place an order it will appear here with its progress.</p>
            <a class="button" href="<?= e(url('products.php')) ?>">Browse products</a>
        </div>
    <?php else: ?>
        <div class="order-cards">
            <?php foreach ($orders as $order): ?>
                <a class="order-card" href="<?= e(url('order_detail.php?id=' . (int)$order['order_id'])) ?>">
                    <img src="<?= e(url(product_image($order['image_path'], (string)$order['category'], true))) ?>" alt="" width="72" height="72">
                    <div>
                        <strong>Order #<?= (int)$order['order_id'] ?></strong>
                        <span class="small-text"><?= e(format_date($order['order_date'], false)) ?> &middot; <?= (int)$order['item_count'] ?> item<?= (int)$order['item_count'] === 1 ? '' : 's' ?> &middot; <?= $order['delivery_method'] === 'pickup' ? 'Pickup' : 'Post' ?></span>
                        <span class="small-text truncate"><?= e($order['item_names']) ?></span>
                    </div>
                    <div class="order-card-end"><?= status_badge($order['status']) ?><strong><?= money($order['total_amount']) ?></strong></div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="section-tight" aria-labelledby="requests-heading">
    <div class="section-heading-row"><h2 id="requests-heading">Custom requests</h2><a class="text-link" href="<?= e(url('customize.php')) ?>">New request</a></div>
    <?php if (!$requests): ?>
        <p>You have not sent any custom requests yet.</p>
    <?php else: ?>
        <div class="request-cards">
            <?php foreach ($requests as $request): ?>
                <article class="card request-card">
                    <div class="request-card-head">
                        <div><strong>#<?= (int)$request['request_id'] ?> <?= e($request['title']) ?></strong><span class="small-text"><?= e($request['request_type']) ?> &middot; <?= (int)$request['quantity'] ?> item(s) &middot; sent <?= e(format_date($request['created_at'], false)) ?></span></div>
                        <?= status_badge($request['status']) ?>
                    </div>
                    <p><?= nl2br(e($request['description'])) ?></p>
                    <?php if ($request['quoted_price'] !== null || $request['admin_response']): ?>
                        <div class="reply">
                            <strong>Reply from Norbooz Crochet</strong>
                            <?php if ($request['quoted_price'] !== null): ?><p>Quoted price: <strong><?= money($request['quoted_price']) ?></strong></p><?php endif; ?>
                            <?php if ($request['admin_response']): ?><p><?= nl2br(e($request['admin_response'])) ?></p><?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p class="small-text">We usually reply within two business days.</p>
                    <?php endif; ?>
                    <?php if ($request['status'] === 'new'): ?>
                        <p><a class="button button-small button-secondary" href="<?= e(url('request_edit.php?id=' . (int)$request['request_id'])) ?>">Edit or withdraw</a></p>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php page_footer(); ?>
