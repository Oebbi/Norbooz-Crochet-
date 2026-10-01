<?php
require_once __DIR__ . '/config/functions.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (($_POST['action'] ?? '') === 'delete') {
        // Delete: only requests that are finished with (new spam, declined or completed) can be removed.
        $row = db()->prepare("SELECT inspiration_path FROM custom_requests WHERE request_id = ? AND status IN ('new','declined','completed')");
        $row->execute([$requestId ?: 0]);
        $row = $row->fetch();
        if (!$row) {
            flash('error', 'Only new, declined or completed requests can be deleted. Decline the request first if you no longer want it.');
        } else {
            db()->prepare('DELETE FROM custom_requests WHERE request_id = ?')->execute([$requestId]);
            delete_private_upload($row['inspiration_path']);
            flash('success', 'Custom request #' . $requestId . ' was deleted.');
        }
        redirect('admin_requests.php?' . http_build_query(array_filter(['status' => $_POST['filter'] ?? ''])));
    }
    $status = (string)($_POST['status'] ?? '');
    $quoteRaw = trim((string)($_POST['quoted_price'] ?? ''));
    $quote = $quoteRaw === '' ? null : filter_var($quoteRaw, FILTER_VALIDATE_FLOAT);
    $response = trim(mb_substr((string)($_POST['admin_response'] ?? ''), 0, 3000));

    if (!$requestId || !in_array($status, request_statuses(), true) || ($quote !== null && ($quote === false || $quote < 0 || $quote > 99999))) {
        flash('error', 'Check the status and quoted price, then try again.');
    } else {
        db()->prepare('UPDATE custom_requests SET status = ?, quoted_price = ?, admin_response = ?, updated_at = NOW() WHERE request_id = ?')
            ->execute([$status, $quote === null ? null : cents_to_decimal(to_cents($quote)), $response !== '' ? $response : null, $requestId]);

        if (isset($_POST['notify'])) {
            $customer = db()->prepare('SELECT u.full_name, u.email, u.is_deleted, cr.title FROM custom_requests cr JOIN users u ON u.user_id = cr.user_id WHERE cr.request_id = ?');
            $customer->execute([$requestId]);
            $customer = $customer->fetch();
            if ($customer && !(int)$customer['is_deleted']) {
                send_email($customer['email'], "Update on your custom request: {$customer['title']}",
                    "Hi {$customer['full_name']},\n\nYour custom request \"{$customer['title']}\" is now: " . ucfirst($status) . '.'
                    . ($quote !== null ? "\nQuoted price: " . money($quote) : '') . ($response !== '' ? "\n\n$response" : '')
                    . "\n\nSee the details: " . absolute_url('my_orders.php'));
            }
        }
        flash('success', 'Custom request #' . $requestId . ' updated.');
    }
    redirect('admin_requests.php?' . http_build_query(array_filter(['status' => $_POST['filter'] ?? ''])) . '#request-' . (int)$requestId);
}

$filter = (string)($_GET['status'] ?? '');
$where = '1 = 1';
$params = [];
if ($filter === 'open') {
    $where = "cr.status IN ('new','reviewing','quoted','accepted')";
} elseif (in_array($filter, request_statuses(), true)) {
    $where = 'cr.status = ?';
    $params[] = $filter;
} else {
    $filter = '';
}
$stmt = db()->prepare("SELECT cr.*, u.full_name, u.email FROM custom_requests cr JOIN users u ON u.user_id = cr.user_id WHERE $where ORDER BY FIELD(cr.status, 'new', 'reviewing', 'quoted', 'accepted', 'completed', 'declined'), cr.created_at DESC");
$stmt->execute($params);
$requests = $stmt->fetchAll();

page_header('Custom requests');
admin_nav();
?>
<section class="page-heading"><h1>Custom requests</h1><p>Reply with a quote and update the status as you discuss the piece. The customer sees your reply under My orders.</p></section>

<form class="filter-bar" method="get">
    <div><label for="status" class="sr-only">Status</label>
        <select id="status" name="status">
            <option value="">All requests</option>
            <option value="open" <?= $filter === 'open' ? 'selected' : '' ?>>Open</option>
            <?php foreach (request_statuses() as $option): ?><option value="<?= e($option) ?>" <?= $filter === $option ? 'selected' : '' ?>><?= e(ucfirst($option)) ?></option><?php endforeach; ?>
        </select></div>
    <button class="button button-small" type="submit">Filter</button>
</form>

<?php if (!$requests): ?>
    <p>No custom requests match this filter.</p>
<?php endif; ?>
<div class="request-cards">
<?php foreach ($requests as $request): $rid = (int)$request['request_id']; ?>
    <article class="card request-card" id="request-<?= $rid ?>">
        <div class="request-card-head">
            <div><strong>#<?= $rid ?> <?= e($request['title']) ?></strong>
                <span class="small-text"><?= e($request['request_type']) ?> &middot; <?= (int)$request['quantity'] ?> item(s) &middot; <?= e(format_date($request['created_at'])) ?></span></div>
            <?= status_badge($request['status']) ?>
        </div>
        <div class="request-body">
            <div>
                <p><?= nl2br(e($request['description'])) ?></p>
                <dl class="detail-list">
                    <div><dt>Colours</dt><dd><?= e($request['color_preferences']) ?></dd></div>
                    <div><dt>Size</dt><dd><?= e($request['size_details']) ?></dd></div>
                    <div><dt>Budget</dt><dd><?= $request['budget'] !== null ? money($request['budget']) : 'Not given' ?></dd></div>
                    <div><dt>Needed by</dt><dd><?= $request['needed_by'] ? e(format_date($request['needed_by'], false)) : 'Flexible' ?></dd></div>
                    <div><dt>Customer</dt><dd><?= e($request['full_name']) ?>, <a href="mailto:<?= e($request['email']) ?>"><?= e($request['email']) ?></a>, <?= e($request['phone']) ?></dd></div>
                    <div><dt>Deliver to</dt><dd><?= e($request['delivery_address']) ?></dd></div>
                </dl>
                <?php if ($request['inspiration_path']): ?>
                    <a href="<?= e(url('request_image.php?id=' . $rid)) ?>" target="_blank" rel="noopener"><img class="inspiration-thumb" src="<?= e(url('request_image.php?id=' . $rid)) ?>" alt="Customer inspiration photo for request <?= $rid ?>"></a>
                <?php endif; ?>
            </div>
            <form method="post" class="reply-form">
                <?= csrf_input() ?>
                <input type="hidden" name="request_id" value="<?= $rid ?>">
                <input type="hidden" name="filter" value="<?= e($filter) ?>">
                <label for="rs-<?= $rid ?>">Status</label>
                <select id="rs-<?= $rid ?>" name="status">
                    <?php foreach (request_statuses() as $option): ?><option value="<?= e($option) ?>" <?= $request['status'] === $option ? 'selected' : '' ?>><?= e(ucfirst($option)) ?></option><?php endforeach; ?>
                </select>
                <label for="rq-<?= $rid ?>">Quoted price (AUD)</label>
                <input id="rq-<?= $rid ?>" type="number" name="quoted_price" min="0" max="99999" step="0.01" value="<?= e($request['quoted_price']) ?>">
                <label for="rr-<?= $rid ?>">Reply to customer</label>
                <textarea id="rr-<?= $rid ?>" name="admin_response" rows="4" maxlength="3000" placeholder="Timeline, questions, payment details..."><?= e($request['admin_response']) ?></textarea>
                <label class="checkbox"><input type="checkbox" name="notify" value="1" checked> Email the customer</label>
                <button class="button button-small" type="submit">Save reply</button>
            </form>
            <?php if (in_array($request['status'], ['new', 'declined', 'completed'], true)): ?>
            <form method="post" data-confirm="Permanently delete custom request #<?= $rid ?>?">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="request_id" value="<?= $rid ?>">
                <input type="hidden" name="filter" value="<?= e($filter) ?>">
                <button class="button button-small button-danger" type="submit">Delete request</button>
            </form>
            <?php endif; ?>
        </div>
    </article>
<?php endforeach; ?>
</div>
<?php page_footer(); ?>
