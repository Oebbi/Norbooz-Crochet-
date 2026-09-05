<?php
require_once __DIR__ . '/config/functions.php';

if (current_user()) {
    redirect(current_user()['role'] === 'admin' ? 'admin.php' : 'index.php');
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = normalise_email((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } else {
        $remaining = login_is_blocked($email);
        if ($remaining > 0) {
            $error = 'Too many unsuccessful login attempts. Please wait about ' . max(1, (int)ceil($remaining / 60)) . ' minute(s) and try again.';
        } else {
            try {
                $stmt = db()->prepare('SELECT user_id, full_name, email, password_hash, role FROM users WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password_hash'])) {
                    clear_login_failures($email);
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = (int)$user['user_id'];
                    $_SESSION['full_name'] = (string)$user['full_name'];
                    $_SESSION['role'] = (string)$user['role'];
                    flash('success', 'Welcome back, ' . $user['full_name'] . '.');
                    redirect($user['role'] === 'admin' ? 'admin.php' : 'products.php');
                }

                record_login_failure($email);
                $error = 'Email or password is incorrect.';
            } catch (Throwable $ex) {
                $error = 'Login is temporarily unavailable. Check the database installation.';
            }
        }
    }
}

page_header('Login');
?>
<section class="form-card narrow">
    <h1>Login</h1>
    <p>Use a customer account or the assessment administrator account.</p>
    <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>

    <form method="post">
        <?= csrf_input() ?>
        <label for="email">Email</label>
        <input id="email" type="email" name="email" maxlength="190" value="<?= e($email) ?>" autocomplete="email" required>

        <label for="password">Password</label>
        <input id="password" type="password" name="password" maxlength="72" autocomplete="current-password" required>

        <button class="button" type="submit">Log in</button>
    </form>

    <p class="small-text">Demo administrator: <strong>admin@norboozcrochet.local</strong> / <strong>Admin@12345</strong>. Change this credential before any real deployment.</p>
</section>
<?php page_footer(); ?>
