<?php
require_once __DIR__ . '/config/functions.php';
require_login();

$user = current_user();
$pdo = db();
$errors = [];
$action = (string)($_POST['action'] ?? '');

$load = $pdo->prepare('SELECT user_id, full_name, email, phone, address, role, marketing_opt_in, password_hash, password_changed_at, created_at FROM users WHERE user_id = ?');
$load->execute([$user['user_id']]);
$account = $load->fetch();
if (!$account) {
    $_SESSION = [];
    redirect('login.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if ($action === 'profile') {
        $name = trim((string)($_POST['full_name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));
        if (!text_length_ok($name, 2, 100)) {
            $errors[] = 'Enter your name (2-100 characters).';
        }
        if (!valid_phone($phone)) {
            $errors[] = 'Enter a contact phone number (8-20 digits).';
        }
        if (mb_strlen($address) > 255) {
            $errors[] = 'Address must be 255 characters or fewer.';
        }
        if (!$errors) {
            $pdo->prepare('UPDATE users SET full_name = ?, phone = ?, address = ?, marketing_opt_in = ? WHERE user_id = ?')
                ->execute([$name, $phone, $address, isset($_POST['marketing']) ? 1 : 0, $user['user_id']]);
            $_SESSION['full_name'] = $name;
            flash('success', 'Your details were saved.');
            redirect('account.php');
        }
    }

    if ($action === 'password') {
        if (!password_verify((string)($_POST['current_password'] ?? ''), $account['password_hash'])) {
            $errors[] = 'Your current password is incorrect.';
        }
        $errors = array_merge($errors, password_problems((string)($_POST['new_password'] ?? ''), (string)($_POST['confirm_password'] ?? '')));
        if (!$errors && password_verify((string)$_POST['new_password'], $account['password_hash'])) {
            $errors[] = 'Choose a password different from your current one.';
        }
        if (!$errors) {
            $pdo->prepare('UPDATE users SET password_hash = ?, password_changed_at = NOW() WHERE user_id = ?')
                ->execute([password_hash((string)$_POST['new_password'], PASSWORD_DEFAULT), $user['user_id']]);
            session_regenerate_id(true);
            send_email($account['email'], 'Your password was changed', "Hi {$account['full_name']},\n\nYour password was just changed. If this was not you, contact us immediately at " . SHOP_EMAIL . '.');
            flash('success', 'Your password was changed.');
            redirect('account.php');
        }
    }

    // Australian Privacy Principle 12: people can access the personal information held about them.
    if ($action === 'export') {
        $orders = $pdo->prepare('SELECT order_id, order_date, status, subtotal_amount, delivery_method, delivery_fee, total_amount, phone, address, custom_note FROM orders WHERE user_id = ? ORDER BY order_id');
        $orders->execute([$user['user_id']]);
        $orders = $orders->fetchAll();
        $itemsStmt = $pdo->prepare('SELECT p.name, oi.quantity, oi.unit_price FROM order_items oi JOIN products p ON p.product_id = oi.product_id WHERE oi.order_id = ?');
        foreach ($orders as &$order) {
            $itemsStmt->execute([$order['order_id']]);
            $order['items'] = $itemsStmt->fetchAll();
        }
        unset($order);
        $requests = $pdo->prepare('SELECT request_id, request_type, title, description, color_preferences, size_details, quantity, budget, needed_by, phone, delivery_address, status, quoted_price, admin_response, created_at FROM custom_requests WHERE user_id = ?');
        $requests->execute([$user['user_id']]);
        $export = [
            'exported_at' => date('c'),
            'account' => array_diff_key($account, ['password_hash' => true]),
            'orders' => $orders,
            'custom_requests' => $requests->fetchAll(),
        ];
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="norbooz-my-data-' . date('Ymd') . '.json"');
        echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // Australian Privacy Principle 11.2: de-identify personal information that is no longer needed.
    if ($action === 'delete' && $account['role'] === 'customer') {
        if (!password_verify((string)($_POST['confirm_delete_password'] ?? ''), $account['password_hash'])) {
            $errors[] = 'Enter your password to confirm account deletion.';
        } else {
            if (user_open_work_count($pdo, (int)$user['user_id']) > 0) {
                $errors[] = 'You have orders or custom requests still in progress. Please wait until they are finished, or contact us, before deleting your account.';
            } else {
                deidentify_user($pdo, (int)$user['user_id']);
                $_SESSION = [];
                session_regenerate_id(true);
                flash('success', 'Your account and personal details have been deleted.');
                redirect('index.php');
            }
        }
    }
}

$defaultAdminPassword = $account['role'] === 'admin' && $account['password_changed_at'] === null;
page_header('My account');
?>
<section class="page-heading">
    <p class="eyebrow">Your account</p>
    <h1>Account settings</h1>
    <p>Signed in as <strong><?= e($account['email']) ?></strong>. Member since <?= e(format_date($account['created_at'], false)) ?>.</p>
</section>

<?php if ($defaultAdminPassword): ?>
    <div class="alert alert-error" role="alert">You are still using the temporary administrator password from the installation. Change it below before the site goes live.</div>
<?php endif; ?>
<?= render_errors($errors) ?>

<div class="account-grid">
    <section class="form-card" aria-labelledby="profile-heading">
        <h2 id="profile-heading">Your details</h2>
        <form method="post">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="profile">
            <label for="full_name">Full name</label>
            <input id="full_name" name="full_name" maxlength="100" value="<?= e($account['full_name']) ?>" autocomplete="name" required>
            <label for="phone">Phone</label>
            <input id="phone" type="tel" name="phone" maxlength="20" value="<?= e($account['phone']) ?>" autocomplete="tel" required>
            <label for="address">Postal address</label>
            <textarea id="address" name="address" rows="3" maxlength="255" autocomplete="street-address"><?= e($account['address']) ?></textarea>
            <label class="checkbox"><input type="checkbox" name="marketing" value="1" <?= (int)$account['marketing_opt_in'] ? 'checked' : '' ?>> Email me about new products</label>
            <button class="button" type="submit">Save details</button>
        </form>
    </section>

    <section class="form-card" aria-labelledby="password-heading">
        <h2 id="password-heading">Change password</h2>
        <form method="post">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="password">
            <label for="current_password">Current password</label>
            <input id="current_password" type="password" name="current_password" autocomplete="current-password" required>
            <label for="new_password">New password <span class="hint">(10+ characters, a letter and a number)</span></label>
            <input id="new_password" type="password" name="new_password" minlength="10" maxlength="72" autocomplete="new-password" required>
            <label for="confirm_password">Confirm new password</label>
            <input id="confirm_password" type="password" name="confirm_password" minlength="10" maxlength="72" autocomplete="new-password" required>
            <button class="button" type="submit">Change password</button>
        </form>
    </section>

    <?php if ($account['role'] === 'customer'): ?>
    <section class="form-card" aria-labelledby="privacy-heading">
        <h2 id="privacy-heading">Your data</h2>
        <p>Download a copy of everything we hold about you, or delete your account. See our <a href="<?= e(url('privacy.php')) ?>">privacy policy</a>.</p>
        <form method="post">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="export">
            <button class="button button-secondary" type="submit">Download my data (JSON)</button>
        </form>
        <details class="danger-zone">
            <summary>Delete my account</summary>
            <p class="small-text">Your name, email, phone, address and custom-request details are removed. Order totals are kept without your details for the shop's tax records. This cannot be undone.</p>
            <form method="post" data-confirm="Permanently delete your account?">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="delete">
                <label for="confirm_delete_password">Enter your password to confirm</label>
                <input id="confirm_delete_password" type="password" name="confirm_delete_password" autocomplete="current-password" required>
                <button class="button button-danger" type="submit">Delete my account</button>
            </form>
        </details>
    </section>
    <?php endif; ?>
</div>
<?php page_footer(); ?>
