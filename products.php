<?php
require_once __DIR__ . '/config/functions.php';
require_login();

$pdo = db();
$errors = [];
$user = current_user();
$form = [
    'request_type' => '',
    'title' => '',
    'description' => '',
    'color_preferences' => '',
    'size_details' => '',
    'quantity' => '1',
    'budget' => '',
    'needed_by' => '',
    'phone' => '',
    'delivery_address' => '',
];

try {
    ensure_custom_requests_table();
} catch (Throwable $ex) {
    $errors[] = 'The customization service is not ready. Please ask the administrator to import the latest database schema.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$errors) {
    verify_csrf();
    foreach ($form as $field => $value) {
        $form[$field] = trim((string)($_POST[$field] ?? ''));
    }

    $quantity = filter_var($form['quantity'], FILTER_VALIDATE_INT);
    $budget = $form['budget'] === '' ? null : filter_var($form['budget'], FILTER_VALIDATE_FLOAT);
    $allowedTypes = ['Plushie', 'Throw or blanket', 'Bag', 'Hat', 'Keychain', 'Flowers', 'Wall hanging', 'Other'];

    if (!in_array($form['request_type'], $allowedTypes, true)) {
        $errors[] = 'Choose the type of custom piece you would like.';
    }
    if ($form['title'] === '' || mb_strlen($form['title']) > 120) {
        $errors[] = 'Add a short name for your custom request (120 characters or fewer).';
    }
    if ($form['description'] === '' || mb_strlen($form['description']) > 3000) {
        $errors[] = 'Describe your custom order in 3,000 characters or fewer.';
    }
    if ($form['color_preferences'] === '' || mb_strlen($form['color_preferences']) > 500) {
        $errors[] = 'Tell us your preferred colours (500 characters or fewer).';
    }
    if ($form['size_details'] === '' || mb_strlen($form['size_details']) > 120) {
        $errors[] = 'Tell us the preferred size or dimensions.';
    }
    if ($quantity === false || $quantity < 1 || $quantity > 100) {
        $errors[] = 'Quantity must be between 1 and 100.';
    }
    if ($budget !== null && ($budget === false || $budget < 0 || $budget > 999999.99)) {
        $errors[] = 'Enter a valid budget or leave it blank.';
    }
    if ($form['needed_by'] !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $form['needed_by'])) {
        $errors[] = 'Enter a valid preferred completion date.';
    }
    if ($form['phone'] === '' || mb_strlen($form['phone']) > 30) {
        $errors[] = 'Add a phone number (30 characters or fewer).';
    }
    if ($form['delivery_address'] === '' || mb_strlen($form['delivery_address']) > 255) {
        $errors[] = 'Add a delivery or collection address (255 characters or fewer).';
    }

    $inspirationPath = null;
    if (isset($_FILES['inspiration']) && $_FILES['inspiration']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['inspiration'];
        $allowedMimeTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
        $mimeType = is_string($file['tmp_name']) && is_uploaded_file($file['tmp_name']) ? (string)(mime_content_type($file['tmp_name']) ?: '') : '';

        if ($file['error'] !== UPLOAD_ERR_OK || (int)$file['size'] > 5 * 1024 * 1024 || !isset($allowedMimeTypes[$mimeType])) {
            $errors[] = 'Inspiration images must be JPG, PNG, WEBP or GIF files up to 5 MB.';
        } else {
            $uploadDirectory = __DIR__ . '/assets/images/custom_requests';
            if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0755, true) && !is_dir($uploadDirectory)) {
                $errors[] = 'The inspiration image could not be stored.';
            } else {
                $fileName = bin2hex(random_bytes(16)) . '.' . $allowedMimeTypes[$mimeType];
                if (!move_uploaded_file($file['tmp_name'], $uploadDirectory . '/' . $fileName)) {
                    $errors[] = 'The inspiration image could not be uploaded.';
                } else {
                    $inspirationPath = 'assets/images/custom_requests/' . $fileName;
                }
            }
        }
    }

    if (!$errors) {
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO custom_requests
                (user_id, request_type, title, description, color_preferences, size_details, quantity, budget, needed_by, phone, delivery_address, inspiration_path)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $user['user_id'], $form['request_type'], $form['title'], $form['description'],
                $form['color_preferences'], $form['size_details'], $quantity, $budget, $form['needed_by'] ?: null,
                $form['phone'], $form['delivery_address'], $inspirationPath,
            ]);
            flash('success', 'Your custom request has been sent. We will review it and contact you with the next steps.');
            redirect('products.php');
        } catch (Throwable $ex) {
            $errors[] = 'Your custom request could not be submitted. Please try again.';
        }
    }
}

page_header('Customization');
?>
<section class="page-heading customization-heading">
    <p class="eyebrow">Made for you</p>
    <h1>Design your custom crochet piece</h1>
    <p>Share the idea in your head, and give us the details we need to turn it into a handmade piece. We will confirm availability, pricing and timing before anything is made.</p>
</section>

<?php if ($errors): ?>
    <div class="alert alert-error" role="alert">
        <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
    </div>
<?php endif; ?>

<section class="customization-layout" aria-label="Custom order request">
    <div class="customization-intro">
        <span class="feature-number">01</span>
        <h2>Start with the feeling</h2>
        <p>Tell us what you want made, who it is for, and any details that make it yours. An inspiration photo can help, but it is completely optional.</p>
        <dl class="customization-points">
            <div><dt>01</dt><dd>Describe the piece and its purpose.</dd></div>
            <div><dt>02</dt><dd>Choose colours, size and quantity.</dd></div>
            <div><dt>03</dt><dd>We reply with a quote and timeline.</dd></div>
        </dl>
    </div>

    <div class="form-card customization-form-card">
        <form method="post" enctype="multipart/form-data">
            <?= csrf_input() ?>
            <div class="form-grid">
                <div>
                    <label for="request_type">What would you like made? *</label>
                    <select id="request_type" name="request_type" required>
                        <option value="">Choose a piece type</option>
                        <?php foreach (['Plushie', 'Throw or blanket', 'Bag', 'Hat', 'Keychain', 'Flowers', 'Wall hanging', 'Other'] as $type): ?>
                            <option value="<?= e($type) ?>" <?= $form['request_type'] === $type ? 'selected' : '' ?>><?= e($type) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="title">Request name *</label>
                    <input id="title" name="title" maxlength="120" value="<?= e($form['title']) ?>" placeholder="e.g. Blue whale plushie" required>
                </div>
                <div class="full">
                    <label for="description">Describe your custom order *</label>
                    <textarea id="description" name="description" rows="6" maxlength="3000" placeholder="Tell us about the design, details, recipient, style or anything else that matters." required><?= e($form['description']) ?></textarea>
                </div>
                <div>
                    <label for="color_preferences">Colours and materials *</label>
                    <input id="color_preferences" name="color_preferences" maxlength="500" value="<?= e($form['color_preferences']) ?>" placeholder="e.g. Sage green, cream and soft pink" required>
                </div>
                <div>
                    <label for="size_details">Size or dimensions *</label>
                    <input id="size_details" name="size_details" maxlength="120" value="<?= e($form['size_details']) ?>" placeholder="e.g. About 20 cm tall" required>
                </div>
                <div>
                    <label for="quantity">Quantity *</label>
                    <input id="quantity" name="quantity" type="number" min="1" max="100" value="<?= e($form['quantity']) ?>" required>
                </div>
                <div>
                    <label for="budget">Budget (optional)</label>
                    <input id="budget" name="budget" type="number" min="0" max="999999.99" step="0.01" value="<?= e($form['budget']) ?>" placeholder="AUD">
                </div>
                <div>
                    <label for="needed_by">Preferred date (optional)</label>
                    <input id="needed_by" name="needed_by" type="date" value="<?= e($form['needed_by']) ?>">
                </div>
                <div class="full">
                    <label for="inspiration">Inspiration image (optional)</label>
                    <input id="inspiration" name="inspiration" type="file" accept="image/jpeg,image/png,image/webp,image/gif">
                    <span class="hint">JPG, PNG, WEBP or GIF up to 5 MB. Please only upload images you have permission to share.</span>
                </div>
                <div>
                    <label for="phone">Best phone number *</label>
                    <input id="phone" name="phone" maxlength="30" value="<?= e($form['phone']) ?>" required>
                </div>
                <div>
                    <label for="delivery_address">Delivery or collection address *</label>
                    <input id="delivery_address" name="delivery_address" maxlength="255" value="<?= e($form['delivery_address']) ?>" required>
                </div>
                <div class="full"><button class="button" type="submit">Send custom request</button></div>
            </div>
        </form>
    </div>
</section>
<?php page_footer(); ?>
