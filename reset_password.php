<?php
require_once __DIR__ . '/config/functions.php';

if (current_user()) {
    redirect('index.php');
}

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$errors = [];
$reset = null;

try {
    ensure_password_resets_table();
    if (preg_match('/^[a-f0-9]{64}$/', $token)) {
        $stmt = db()->prepare(
            'SELECT reset_id, user_id FROM password_resets
             WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute([hash('sha256', $token)]);
        $reset = $stmt->fetch();
    }
} catch (Throwable $ex) {
    $errors[] = 'Password reset is temporarily unavailable. Please request a new link later.';
}

if (!$reset && !$errors) {
    $errors[] = 'This password reset link is invalid or has expired. Request a new link and try again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset && !$errors) {
    verify_csrf();
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    if (strlen($password) < 8 || strlen($password) > 72) {
        $errors[] = 'Password must be between 8 and 72 characters.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }

    if (!$errors) {
        try {
            $pdo = db();
            $pdo->beginTransaction();
            $update = $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
            $update->execute([password_hash($password, PASSWORD_DEFAULT), (int)$reset['user_id']]);
            $markUsed = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE reset_id = ?');
            $markUsed->execute([(int)$reset['reset_id']]);
            $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? AND reset_id <> ?')->execute([(int)$reset['user_id'], (int)$reset['reset_id']]);
            $pdo->commit();
            flash('success', 'Your password has been changed. Please log in with your new password.');
            redirect('login.php');
        } catch (Throwable $ex) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }
            $errors[] = 'Your password could not be changed. Please request a new reset link.';
        }
    }
}

page_header('Reset Password');
?>
<section class="form-card narrow">
    <h1>Choose a new password</h1>
    <p>Use a password between 8 and 72 characters.</p>

    <?php if ($errors): ?>
        <div class="alert alert-error" role="alert">
            <ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php if (!$reset): ?><p><a href="<?= e(url('forgot_password.php')) ?>">Request another reset link</a></p><?php endif; ?>
    <?php elseif ($reset): ?>
        <form method="post">
            <?= csrf_input() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <label for="password">New password</label>
            <input id="password" type="password" name="password" minlength="8" maxlength="72" autocomplete="new-password" required>
            <label for="confirm_password">Confirm new password</label>
            <input id="confirm_password" type="password" name="confirm_password" minlength="8" maxlength="72" autocomplete="new-password" required>
            <button class="button" type="submit">Change password</button>
        </form>
    <?php endif; ?>
</section>
<?php page_footer(); ?>
