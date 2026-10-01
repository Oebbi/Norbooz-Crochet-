<?php
require_once __DIR__ . '/config/functions.php';

$next = safe_next($_GET['next'] ?? $_POST['next'] ?? '', '');
if (current_user()) {
    redirect($next ?: (is_admin() ? 'admin.php' : 'products.php'));
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = normalise_email((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        $error = 'Enter your email address and password.';
    } else {
        try {
            $wait = login_block_seconds($email);
            if ($wait > 0) {
                $error = 'Too many unsuccessful attempts. For your security, please wait about ' . max(1, (int)ceil($wait / 60)) . ' minute(s) and try again, or reset your password.';
            } else {
                $stmt = db()->prepare('SELECT user_id, full_name, email, password_hash, role FROM users WHERE email = ? AND is_deleted = 0 LIMIT 1');
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                // password_verify runs even for unknown emails so response time does not reveal which emails exist.
                $hash = $user['password_hash'] ?? '$2y$12$lzDlNIN8ysbWi70ybt0qG.G/lOyBupC4GuDhnslPEQnW51zo2u3j6';
                $valid = password_verify($password, $hash) && $user;

                if ($valid) {
                    record_login_attempt($email, true);
                    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                        db()->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $user['user_id']]);
                    }
                    sign_in_user($user);
                    flash('success', 'Welcome back, ' . $user['full_name'] . '.');
                    redirect($next ?: ($user['role'] === 'admin' ? 'admin.php' : (cart_count() ? 'cart.php' : 'products.php')));
                }
                record_login_attempt($email, false);
                $error = 'Email or password is incorrect.';
            }
        } catch (Throwable $ex) {
            error_log('Login error: ' . $ex->getMessage());
            $error = 'Login is temporarily unavailable. Please try again shortly.';
        }
    }
}

page_header('Log in');
?>
<section class="form-card narrow">
    <h1>Log in</h1>
    <p>Log in to check out, track your orders and send custom requests.</p>
    <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>

    <form method="post">
        <?= csrf_input() ?>
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" maxlength="190" value="<?= e($email) ?>" autocomplete="email" required autofocus>

        <label for="password">Password</label>
        <input id="password" type="password" name="password" maxlength="72" autocomplete="current-password" required>

        <button class="button button-block" type="submit">Log in</button>
    </form>
    <p class="form-links"><a href="<?= e(url('forgot_password.php')) ?>">Forgot your password?</a> <span>New here? <a href="<?= e(url('register.php' . ($next ? '?next=' . rawurlencode($next) : ''))) ?>">Create an account</a></span></p>
</section>
<?php page_footer(); ?>
