<?php
require_once __DIR__ . '/config/functions.php';

if (current_user()) {
    redirect('index.php');
}

$email = '';
$error = '';
$submitted = false;

try {
    ensure_password_resets_table();
} catch (Throwable $ex) {
    $error = 'Password recovery is temporarily unavailable. Please try again later.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    verify_csrf();
    $email = normalise_email((string)($_POST['email'] ?? ''));
    $submitted = true;

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $error = 'Enter a valid email address.';
        $submitted = false;
    } else {
        try {
            $stmt = db()->prepare('SELECT user_id, full_name, email FROM users WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $token = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $token);
                $expiresAt = date('Y-m-d H:i:s', time() + 3600);
                $pdo = db();
                $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? OR expires_at < NOW()')->execute([(int)$user['user_id']]);
                $pdo->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)')->execute([(int)$user['user_id'], $tokenHash, $expiresAt]);

                $resetUrl = url('reset_password.php?token=' . rawurlencode($token));
                $subject = 'Norbooz Crochet password reset';
                $message = "Hello {$user['full_name']},\n\nUse this link to choose a new Norbooz Crochet password:\n{$resetUrl}\n\nThis link expires in one hour. If you did not request this, you can ignore this email.\n";
                $headers = "From: norboozcrochet25@gmail.com\r\n" . "Reply-To: norboozcrochet25@gmail.com\r\n" . "Content-Type: text/plain; charset=UTF-8\r\n";
                @mail($user['email'], $subject, $message, $headers);
            }
        } catch (Throwable $ex) {
            $error = 'Password recovery is temporarily unavailable. Please try again later.';
            $submitted = false;
        }
    }
}

page_header('Forgot Password');
?>
<section class="form-card narrow">
    <h1>Forgot your password?</h1>
    <p>Enter the email address on your account and we will send you a secure password reset link.</p>

    <?php if ($error): ?><div class="alert alert-error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <?php if ($submitted && !$error): ?>
        <div class="alert alert-success" role="status">If an account uses that email, a password reset link has been sent. Check your inbox and spam folder.</div>
    <?php endif; ?>

    <form method="post">
        <?= csrf_input() ?>
        <label for="email">Email</label>
        <input id="email" type="email" name="email" maxlength="190" value="<?= e($email) ?>" autocomplete="email" required>
        <button class="button" type="submit">Send reset link</button>
    </form>
    <p class="small-text"><a href="<?= e(url('login.php')) ?>">Back to login</a></p>
</section>
<?php page_footer(); ?>
