<?php
require_once __DIR__ . '/config/functions.php';

$types = request_types();
$user = current_user();
$errors = [];

// "Request one like this" links pre-fill the form from an existing product.
$basedOn = null;
$basedOnId = filter_var($_GET['based_on'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($basedOnId) {
    $stmt = db()->prepare('SELECT product_id, name, category FROM products WHERE product_id = ? AND is_active = 1');
    $stmt->execute([$basedOnId]);
    $basedOn = $stmt->fetch() ?: null;
}
$typeFromCategory = [
    'Plushies' => 'Plushie', 'Throws' => 'Throw or blanket', 'Bags' => 'Bag', 'Hats' => 'Hat',
    'Keychains' => 'Keychain', 'Flowers' => 'Flowers', 'Wall Hangings' => 'Wall hanging', 'Coasters' => 'Coasters',
];

$form = [
    'request_type' => $basedOn ? ($typeFromCategory[canonical_product_category($basedOn['category'])] ?? '') : '',
    'title' => $basedOn ? 'Custom ' . $basedOn['name'] : '',
    'description' => $basedOn ? 'Based on "' . $basedOn['name'] . '" (product #' . (int)$basedOn['product_id'] . '). ' : '',
    'color_preferences' => '', 'size_details' => '', 'quantity' => '1', 'budget' => '', 'needed_by' => '',
    'phone' => '', 'delivery_address' => '',
];

if ($user && !is_admin()) {
    $profile = db()->prepare('SELECT phone, address FROM users WHERE user_id = ?');
    $profile->execute([$user['user_id']]);
    $profile = $profile->fetch() ?: [];
    $form['phone'] = (string)($profile['phone'] ?? '');
    $form['delivery_address'] = (string)($profile['address'] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_customer();
    verify_csrf();
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

    $inspiration = null;
    if (!$errors && isset($_FILES['inspiration']) && $_FILES['inspiration']['error'] !== UPLOAD_ERR_NO_FILE) {
        try {
            $inspiration = store_private_upload($_FILES['inspiration']);
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
    }

    if (!$errors) {
        db()->prepare(
            'INSERT INTO custom_requests
             (user_id, request_type, title, description, color_preferences, size_details, quantity, budget, needed_by, phone, delivery_address, inspiration_path)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $user['user_id'], $form['request_type'], $form['title'], $form['description'], $form['color_preferences'],
            $form['size_details'], $quantity, $budget, $neededBy, $form['phone'], $form['delivery_address'], $inspiration,
        ]);
        $requestId = (int)db()->lastInsertId();
        send_email(SHOP_EMAIL, "New custom request #$requestId: {$form['title']}", "{$user['full_name']} sent a custom request.\n\nType: {$form['request_type']}\nQuantity: $quantity\nColours: {$form['color_preferences']}\nSize: {$form['size_details']}\nBudget: " . ($budget !== null ? money($budget) : '-') . "\nNeeded by: " . ($neededBy ?? '-') . "\n\n{$form['description']}\n\nReply: " . absolute_url('admin_requests.php#request-' . $requestId));
        flash('success', 'Thank you! Your custom request has been sent. You can follow our reply under My orders.');
        redirect('my_orders.php');
    }
}

page_header('Custom orders', 'Request a custom handmade crochet piece in your colours and size.');
?>
<section class="page-heading">
    <p class="eyebrow">Made for you</p>
    <h1>Request a custom crochet piece</h1>
    <p>Share your idea and we will reply with a quote and timeline before anything is made. There is no obligation to go ahead.</p>
</section>

<?= render_errors($errors) ?>

<section class="customization-layout" aria-label="Custom order request">
    <div class="customization-intro">
        <h2>How it works</h2>
        <ol class="numbered-steps">
            <li><strong>Describe the piece</strong> and who it is for.</li>
            <li><strong>Choose colours, size and quantity.</strong> An inspiration photo helps, but is optional.</li>
            <li><strong>We reply with a quote</strong>, visible under My orders and by email.</li>
        </ol>
        <?php if ($basedOn): ?><p class="callout small-text">Starting from <a href="<?= e(url('product.php?id=' . (int)$basedOn['product_id'])) ?>"><?= e($basedOn['name']) ?></a>.</p><?php endif; ?>
    </div>

    <div class="form-card">
        <?php if (!$user): ?>
            <h2>Log in to send a request</h2>
            <p>We need an account so we can reply to you and keep your request private.</p>
            <div class="actions">
                <a class="button" href="<?= e(url('login.php?next=' . rawurlencode(current_request_path()))) ?>">Log in</a>
                <a class="button button-secondary" href="<?= e(url('register.php?next=' . rawurlencode(current_request_path()))) ?>">Create an account</a>
            </div>
        <?php elseif (is_admin()): ?>
            <p>Custom requests are sent by customers. Manage them in <a href="<?= e(url('admin_requests.php')) ?>">Admin &rsaquo; Custom requests</a>.</p>
        <?php else: ?>
        <form method="post" enctype="multipart/form-data">
            <?= csrf_input() ?>
            <div class="form-grid">
                <div>
                    <label for="request_type">What would you like made?</label>
                    <select id="request_type" name="request_type" required>
                        <option value="">Choose a type</option>
                        <?php foreach ($types as $type): ?>
                            <option value="<?= e($type) ?>" <?= $form['request_type'] === $type ? 'selected' : '' ?>><?= e($type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="title">Request name</label>
                    <input id="title" name="title" maxlength="120" value="<?= e($form['title']) ?>" placeholder="e.g. Blue whale plushie" required>
                </div>
                <div class="full">
                    <label for="description">Describe your idea</label>
                    <textarea id="description" name="description" rows="6" maxlength="3000" placeholder="Design, details, who it is for, and anything else that matters." required><?= e($form['description']) ?></textarea>
                </div>
                <div>
                    <label for="color_preferences">Colours</label>
                    <input id="color_preferences" name="color_preferences" maxlength="500" value="<?= e($form['color_preferences']) ?>" placeholder="e.g. Sage green, cream and soft pink" required>
                </div>
                <div>
                    <label for="size_details">Size</label>
                    <input id="size_details" name="size_details" maxlength="120" value="<?= e($form['size_details']) ?>" placeholder="e.g. About 20 cm tall" required>
                </div>
                <div>
                    <label for="quantity">Quantity</label>
                    <input id="quantity" name="quantity" type="number" min="1" max="100" value="<?= e($form['quantity']) ?>" required>
                </div>
                <div>
                    <label for="budget">Budget in AUD <span class="hint">(optional)</span></label>
                    <input id="budget" name="budget" type="number" min="0" max="99999" step="0.01" value="<?= e($form['budget']) ?>">
                </div>
                <div>
                    <label for="needed_by">Needed by <span class="hint">(optional)</span></label>
                    <input id="needed_by" name="needed_by" type="date" min="<?= e(date('Y-m-d')) ?>" value="<?= e($form['needed_by']) ?>">
                </div>
                <div>
                    <label for="phone">Phone</label>
                    <input id="phone" name="phone" type="tel" maxlength="20" value="<?= e($form['phone']) ?>" required>
                </div>
                <div class="full">
                    <label for="delivery_address">Delivery address, or "Pickup"</label>
                    <input id="delivery_address" name="delivery_address" maxlength="255" value="<?= e($form['delivery_address']) ?>" required>
                </div>
                <div class="full">
                    <label for="inspiration">Inspiration photo <span class="hint">(optional, JPG/PNG/WEBP up to <?= (int)MAX_UPLOAD_MB ?> MB)</span></label>
                    <input id="inspiration" name="inspiration" type="file" accept="image/jpeg,image/png,image/webp,image/gif">
                    <span class="hint">Only upload photos you have permission to share. Photos are kept private and only seen by the maker.</span>
                </div>
                <div class="full"><button class="button" type="submit" data-once>Send request</button></div>
            </div>
        </form>
        <?php endif; ?>
    </div>
</section>
<?php page_footer(); ?>
