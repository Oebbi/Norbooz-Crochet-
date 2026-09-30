<?php
require_once __DIR__ . '/config/functions.php';

if (current_user()) {
    redirect('account.php');
}

$email = '';
$error = '';
$submitted = false;
$devLink = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = normalise_email((string)($_POST['email'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $error = 'Enter a valid email address.';
    } else {
        try {
            // Rate limit reset requests using the same table as logins.
            if (login_block_seconds($email) > 0) {
                throw new RuntimeException('Too many attempts. Please wait a few minutes and try again.');
            }
            record_login_attempt($email, false);

            $stmt = db()->prepare('SELECT user_id, full_name, email FROM users WHERE email = ? AND is_deleted = 0 LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $pdo = db();
                $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? OR expires_at < NOW()')->execute([(int)$user['user_id']]);
                $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, NOW() + INTERVAL 1 HOUR)')
                    ->execute([(int)$user['user_id'], hash('sha256', $token)]);

                $resetUrl = absolute_url('reset_password.php?token=' . $token);
                send_email($user['email'], 'Reset your password', "Hi {$user['full_name']},\n\nUse this link to choose a new password:\n$resetUrl\n\nThe link works once and expires in one hour. If you did not ask for this, you can ignore this email; your password has not changed.");

                // On a local XAMPP test machine without a mail server, show the link so the flow can be demonstrated.
                if (MAIL_MODE === 'log' && is_local_request()) {
                    $devLink = $resetUrl;
                }
            }
            // The same message is shown whether or not the account exists (prevents account discovery).
            $submitted = true;
        } catch (RuntimeException $ex) {
            $error = $ex->getMessage();
        } catch (Throwable $ex) {
            error_log('Password reset error: ' . $ex->getMessage());
            $error = 'Password recovery is temporarily unavailable. Please try again later.';
        }
    }
}

page_header('Forgot password');
?>
<section class="form-card narrow">
    <h1>Forgot your password?</h1>
    <p>Enter the email address on your account and we will send a secure reset link.</p>

    <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if ($submitted): ?>
        <div class="alert alert-success" role="status">If an account uses that email, a reset link is on its way. Check your inbox and spam folder. The link expires in one hour.</div>
        <?php if ($devLink): ?>
            <div class="alert alert-info"><strong>Local test mode:</strong> email is written to storage/mail.log instead of being sent. <a href="<?= e($devLink) ?>">Open the reset link</a>.</div>
        <?php endif; ?>
    <?php endif; ?>

    <form method="post">
        <?= csrf_input() ?>
        <label for="email">Email</label>
        <input id="email" type="email" name="email" maxlength="190" value="<?= e($email) ?>" autocomplete="email" required>
        <button class="button button-block" type="submit">Send reset link</button>
    </form>
    <p class="form-links"><a href="<?= e(url('login.php')) ?>">Back to log in</a></p>
</section>
<?php page_footer(); ?>
