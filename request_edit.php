<?php
// Customer can edit or withdraw (update / delete) their own custom request while it is still "new".
require_once __DIR__ . '/config/functions.php';
require_customer();

$user = current_user();
$types = request_types();
$requestId = filter_var($_GET['id'] ?? $_POST['request_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$requestId) {
    not_found('Request not found.');
}

// Owner check: a customer can only ever load their own request (prevents IDOR).
$stmt = db()->prepare('SELECT * FROM custom_requests WHERE request_id = ? AND user_id = ?');
$stmt->execute([$requestId, $user['user_id']]);
$request = $stmt->fetch();
if (!$request) {
    not_found('Request not found.');
}
$editable = $request['status'] === 'new';
$errors = [];

$form = [
    'request_type' => (string)$request['request_type'], 'title' => (string)$request['title'],
    'description' => (string)$request['description'], 'color_preferences' => (string)$request['color_preferences'],
    'size_details' => (string)$request['size_details'], 'quantity' => (string)$request['quantity'],
    'budget' => $request['budget'] !== null ? (string)$request['budget'] : '',
    'needed_by' => (string)($request['needed_by'] ?? ''), 'phone' => (string)$request['phone'],
    'delivery_address' => (string)$request['delivery_address'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    if (!$editable) {
        $errors[] = 'This request has already been picked up, so it can no longer be changed here. Please contact us to make changes.';
    } elseif ($action === 'delete') {
        db()->prepare("DELETE FROM custom_requests WHERE request_id = ? AND user_id = ? AND status = 'new'")->execute([$requestId, $user['user_id']]);
        delete_private_upload($request['inspiration_path']);
        send_email(SHOP_EMAIL, "Custom request #$requestId withdrawn", "{$user['full_name']} withdrew custom request #$requestId (\"{$request['title']}\").");
        flash('success', 'Your custom request was withdrawn and deleted.');
        redirect('my_orders.php');
    } elseif ($action === 'update') {
        foreach ($form as $field => $value) {
            $form[$field] = trim((string)($_POST[$field] ?? ''));
        }
        $quantity = filter_var($form['quantity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        $budget = $form['budget'] === '' ? null : filter_var($form['budget'], FILTER_VALIDATE_FLOAT);
        $neededBy = null;

        if (!in_array($form['request_type'], $types, true)) {
            $errors[] = 'Choose the type of piece you would like.';
        }
        if (!text_length_ok($form['title'], 2, 120)) {
            $errors[] = 'Give your request a short name (2-120 characters).';
        }
        if (!text_length_ok($form['description'], 10, 3000)) {
            $errors[] = 'Describe your idea in 10 to 3,000 characters.';
        }
        if (!text_length_ok($form['color_preferences'], 2, 500)) {
            $errors[] = 'Tell us your preferred colours.';
        }
        if (!text_length_ok($form['size_details'], 1, 120)) {
            $errors[] = 'Tell us the size you have in mind.';
        }
        if ($quantity === false) {
            $errors[] = 'Quantity must be between 1 and 100.';
        }
        if ($budget !== null && ($budget === false || $budget < 0 || $budget > 99999)) {
            $errors[] = 'Enter a valid budget or leave it blank.';
        }
        if ($form['needed_by'] !== '') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $form['needed_by']);
            if (!$date || $date->format('Y-m-d') !== $form['needed_by'] || $date < new DateTimeImmutable('today')) {
                $errors[] = 'The preferred date must be a valid date in the future.';
            } else {
                $neededBy = $form['needed_by'];
            }
        }
        if (!valid_phone($form['phone'])) {
            $errors[] = 'Enter a contact phone number (8-20 digits).';
        }
        if (!text_length_ok($form['delivery_address'], 3, 255)) {
            $errors[] = 'Add a delivery address, or write "Pickup".';
        }

        $photo = $request['inspiration_path'];
        $oldPhoto = null;
        if (!$errors && isset($_FILES['inspiration']) && $_FILES['inspiration']['error'] !== UPLOAD_ERR_NO_FILE) {
            try {
                $photo = store_private_upload($_FILES['inspiration']);
                $oldPhoto = $request['inspiration_path'];
            } catch (RuntimeException $ex) {
                $errors[] = $ex->getMessage();
            }
        } elseif (!$errors && isset($_POST['remove_photo']) && $photo) {
            $oldPhoto = $photo;
            $photo = null;
        }

        if (!$errors) {
            $stmt = db()->prepare(
                "UPDATE custom_requests SET request_type = ?, title = ?, description = ?, color_preferences = ?, size_details = ?,
                        quantity = ?, budget = ?, needed_by = ?, phone = ?, delivery_address = ?, inspiration_path = ?, updated_at = NOW()
                 WHERE request_id = ? AND user_id = ? AND status = 'new'"
            );
            $stmt->execute([
                $form['request_type'], $form['title'], $form['description'], $form['color_preferences'], $form['size_details'],
                $quantity, $budget, $neededBy, $form['phone'], $form['delivery_address'], $photo, $requestId, $user['user_id'],
            ]);
            delete_private_upload($oldPhoto);
            send_email(SHOP_EMAIL, "Custom request #$requestId was edited", "{$user['full_name']} edited custom request #$requestId (\"{$form['title']}\").\n\nSee it: " . absolute_url('admin_requests.php#request-' . $requestId));
            flash('success', 'Your custom request was updated.');
            redirect('my_orders.php');
        }
    }
}

page_header('Edit custom request');
?>
<nav class="breadcrumb" aria-label="Breadcrumb"><a href="<?= e(url('my_orders.php')) ?>">My orders</a> <span aria-hidden="true">/</span> <span aria-current="page">Request #<?= (int)$requestId ?></span></nav>
<section class="page-heading">
    <p class="eyebrow">Custom request #<?= (int)$requestId ?></p>
    <h1>Edit your request</h1>
    <p><?= $editable ? 'You can change or withdraw this request until we start reviewing it.' : 'We have started on this request, so it can no longer be changed here.' ?></p>
</section>

<?= render_errors($errors) ?>

<div class="form-card">
<?php if (!$editable): ?>
    <p>Status: <?= status_badge($request['status']) ?></p>
    <p>Please <a href="<?= e(url('contact.php')) ?>">contact us</a> if you need to change something.</p>
<?php else: ?>
    <form method="post" enctype="multipart/form-data">
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="request_id" value="<?= (int)$requestId ?>">
        <div class="form-grid">
            <div>
                <label for="request_type">What would you like made?</label>
                <select id="request_type" name="request_type" required>
                    <?php foreach ($types as $type): ?><option value="<?= e($type) ?>" <?= $form['request_type'] === $type ? 'selected' : '' ?>><?= e($type) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div><label for="title">Request name</label><input id="title" name="title" maxlength="120" value="<?= e($form['title']) ?>" required></div>
            <div class="full"><label for="description">Describe your idea</label><textarea id="description" name="description" rows="6" maxlength="3000" required><?= e($form['description']) ?></textarea></div>
            <div><label for="color_preferences">Colours</label><input id="color_preferences" name="color_preferences" maxlength="500" value="<?= e($form['color_preferences']) ?>" required></div>
            <div><label for="size_details">Size</label><input id="size_details" name="size_details" maxlength="120" value="<?= e($form['size_details']) ?>" required></div>
            <div><label for="quantity">Quantity</label><input id="quantity" name="quantity" type="number" min="1" max="100" value="<?= e($form['quantity']) ?>" required></div>
            <div><label for="budget">Budget in AUD <span class="hint">(optional)</span></label><input id="budget" name="budget" type="number" min="0" max="99999" step="0.01" value="<?= e($form['budget']) ?>"></div>
            <div><label for="needed_by">Needed by <span class="hint">(optional)</span></label><input id="needed_by" name="needed_by" type="date" min="<?= e(date('Y-m-d')) ?>" value="<?= e($form['needed_by']) ?>"></div>
            <div><label for="phone">Phone</label><input id="phone" name="phone" type="tel" maxlength="20" value="<?= e($form['phone']) ?>" required></div>
            <div class="full"><label for="delivery_address">Delivery address, or "Pickup"</label><input id="delivery_address" name="delivery_address" maxlength="255" value="<?= e($form['delivery_address']) ?>" required></div>
            <div class="full">
                <label for="inspiration">Inspiration photo <span class="hint">(<?= $request['inspiration_path'] ? 'a photo is saved; choose a file to replace it' : 'optional' ?>, JPG/PNG/WEBP up to <?= (int)MAX_UPLOAD_MB ?> MB)</span></label>
                <input id="inspiration" name="inspiration" type="file" accept="image/jpeg,image/png,image/webp,image/gif">
                <?php if ($request['inspiration_path']): ?><label class="checkbox"><input type="checkbox" name="remove_photo" value="1"> Remove my saved photo</label><?php endif; ?>
            </div>
            <div class="full"><button class="button" type="submit" data-once>Save changes</button> <a class="button button-secondary" href="<?= e(url('my_orders.php')) ?>">Cancel</a></div>
        </div>
    </form>

    <details class="danger-zone">
        <summary>Withdraw this request</summary>
        <p class="small-text">This permanently deletes the request and its photo. It cannot be undone.</p>
        <form method="post" data-confirm="Withdraw and delete this custom request?">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="request_id" value="<?= (int)$requestId ?>">
            <button class="button button-danger" type="submit">Delete request</button>
        </form>
    </details>
<?php endif; ?>
</div>
<?php page_footer(); ?>
