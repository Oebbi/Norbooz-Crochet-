<?php
require_once __DIR__ . '/config/functions.php';

$next = safe_next($_GET['next'] ?? $_POST['next'] ?? '', '');
if (current_user()) {
    redirect($next ?: 'index.php');
}

$errors = [];
$values = ['full_name' => '', 'email' => '', 'phone' => '', 'address' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($values as $key => $_) {
        $values[$key] = trim((string)($_POST[$key] ?? ''));
    }
    $values['email'] = normalise_email($values['email']);
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if (!text_length_ok($values['full_name'], 2, 100)) {
        $errors[] = 'Enter your name (2-100 characters).';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || strlen($values['email']) > 190) {
        $errors[] = 'Enter a valid email address.';
    }
    if (!valid_phone($values['phone'])) {
        $errors[] = 'Enter a contact phone number (8-20 digits).';
    }
    if (mb_strlen($values['address']) > 255) {
        $errors[] = 'Address must be 255 characters or fewer.';
    }
    $errors = array_merge($errors, password_problems($password, $confirm));
    if (!isset($_POST['privacy'])) {
        $errors[] = 'Please confirm you have read the privacy policy.';
    }

    if (!$errors) {
        try {
            db()->prepare(
                "INSERT INTO users (full_name, email, password_hash, phone, address, role, marketing_opt_in, password_changed_at)
                 VALUES (?, ?, ?, ?, ?, 'customer', ?, NOW())"
            )->execute([
                $values['full_name'], $values['email'], password_hash($password, PASSWORD_DEFAULT),
                $values['phone'], $values['address'], isset($_POST['marketing']) ? 1 : 0,
            ]);
            $user = ['user_id' => (int)db()->lastInsertId(), 'full_name' => $values['full_name'], 'role' => 'customer'];
            sign_in_user($user);
            send_email($values['email'], 'Welcome to Norbooz Crochet', "Hi {$values['full_name']},\n\nYour account has been created. You can now check out, track orders and send custom requests.\n\nIf you did not create this account, please reply to this email.");
            flash('success', 'Welcome, ' . $values['full_name'] . '! Your account is ready.');
            redirect($next ?: (cart_count() ? 'cart.php' : 'products.php'));
        } catch (PDOException $ex) {
            // 1062 = MySQL/MariaDB duplicate key (the email column is UNIQUE).
            $errors[] = (int)($ex->errorInfo[1] ?? 0) === 1062
                ? 'An account with that email already exists. Log in or reset your password.'
                : 'Your account could not be created. Please try again.';
        }
    }
}

page_header('Create an account');
?>
<section class="form-card narrow">
    <h1>Create an account</h1>
    <p>We only ask for what we need to make and deliver your order.</p>
    <?= render_errors($errors) ?>

    <form method="post">
        <?= csrf_input() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <label for="full_name">Full name</label>
        <input id="full_name" name="full_name" maxlength="100" value="<?= e($values['full_name']) ?>" autocomplete="name" required>

        <label for="email">Email</label>
        <input id="email" type="email" name="email" maxlength="190" value="<?= e($values['email']) ?>" autocomplete="email" required>

        <label for="phone">Phone</label>
        <input id="phone" type="tel" name="phone" maxlength="20" value="<?= e($values['phone']) ?>" autocomplete="tel" required>

        <label for="address">Postal address <span class="hint">(optional, you can add it at checkout)</span></label>
        <textarea id="address" name="address" rows="2" maxlength="255" autocomplete="street-address"><?= e($values['address']) ?></textarea>

        <label for="password">Password <span class="hint">(at least 10 characters, with a letter and a number)</span></label>
        <input id="password" type="password" name="password" minlength="10" maxlength="72" autocomplete="new-password" required>

        <label for="confirm_password">Confirm password</label>
        <input id="confirm_password" type="password" name="confirm_password" minlength="10" maxlength="72" autocomplete="new-password" required>

        <label class="checkbox"><input type="checkbox" name="privacy" value="1" required> I have read the <a href="<?= e(url('privacy.php')) ?>" target="_blank" rel="noopener">privacy policy</a>.</label>
        <label class="checkbox"><input type="checkbox" name="marketing" value="1"> Email me about new products (optional, unsubscribe any time).</label>

        <button class="button button-block" type="submit">Create account</button>
    </form>
    <p class="form-links">Already have an account? <a href="<?= e(url('login.php' . ($next ? '?next=' . rawurlencode($next) : ''))) ?>">Log in</a></p>
</section>
<?php page_footer(); ?>
