<?php
require_once __DIR__ . '/config/functions.php';

if (current_user()) {
    redirect('index.php');
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

    if (mb_strlen($values['full_name']) < 2 || mb_strlen($values['full_name']) > 100) {
        $errors[] = 'Enter a full name between 2 and 100 characters.';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL) || strlen($values['email']) > 190) {
        $errors[] = 'Enter a valid email address.';
    }
    if ($values['phone'] === '' || mb_strlen($values['phone']) > 30) {
        $errors[] = 'Enter a valid contact phone number.';
    }
    if ($values['address'] === '' || mb_strlen($values['address']) > 255) {
        $errors[] = 'Enter a delivery address of 255 characters or fewer.';
    }
    if (strlen($password) < 8 || strlen($password) > 72) {
        $errors[] = 'Password must be between 8 and 72 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        try {
            $stmt = db()->prepare(
                "INSERT INTO users (full_name, email, password_hash, phone, address, role)
                 VALUES (?, ?, ?, ?, ?, 'customer')"
            );
            $stmt->execute([
                $values['full_name'],
                $values['email'],
                password_hash($password, PASSWORD_DEFAULT),
                $values['phone'],
                $values['address'],
            ]);
            flash('success', 'Account created successfully. Please log in.');
            redirect('login.php');
        } catch (PDOException $ex) {
            if ((string)$ex->getCode() === '23000') {
                $errors[] = 'An account with that email already exists.';
            } else {
                $errors[] = 'Registration could not be completed. Please check the database installation and try again.';
            }
        }
    }
}

page_header('Register');
?>
<section class="form-card narrow">
    <h1>Create an account</h1>
    <p>Only information needed for account and order management is collected.</p>

    <?php if ($errors): ?>
        <div class="alert alert-error" role="alert">
            <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <form method="post" novalidate>
        <?= csrf_input() ?>
        <label for="full_name">Full name</label>
        <input id="full_name" name="full_name" maxlength="100" value="<?= e($values['full_name']) ?>" autocomplete="name" required>

        <label for="email">Email</label>
        <input id="email" type="email" name="email" maxlength="190" value="<?= e($values['email']) ?>" autocomplete="email" required>

        <label for="phone">Phone</label>
        <input id="phone" name="phone" maxlength="30" value="<?= e($values['phone']) ?>" autocomplete="tel" required>

        <label for="address">Delivery address</label>
        <textarea id="address" name="address" rows="3" maxlength="255" autocomplete="street-address" required><?= e($values['address']) ?></textarea>

        <label for="password">Password <span class="hint">(8-72 characters)</span></label>
        <input id="password" type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" required>

        <label for="confirm_password">Confirm password</label>
        <input id="confirm_password" type="password" name="confirm_password" minlength="8" maxlength="72" autocomplete="new-password" required>

        <button class="button" type="submit">Create account</button>
    </form>
    <p class="small-text">Privacy: this prototype does not collect payment-card information. Demonstration accounts should use fictional data.</p>
</section>
<?php page_footer(); ?>
