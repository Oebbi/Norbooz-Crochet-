<?php
require_once __DIR__ . '/config/functions.php';

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$errors = [];
$reset = null;

try {
    if (preg_match('/^[a-f0-9]{64}$/', $token)) {
        $stmt = db()->prepare(
            'SELECT r.reset_id, r.user_id, u.email, u.full_name FROM password_resets r JOIN users u ON u.user_id = r.user_id
             WHERE r.token_hash = ? AND r.used_at IS NULL AND r.expires_at > NOW() AND u.is_deleted = 0 LIMIT 1'
        );
        $stmt->execute([hash('sha256', $token)]);
        $reset = $stmt->fetch() ?: null;
    }
} catch (Throwable $ex) {
    $errors[] = 'Password reset is temporarily unavailable. Please request a new link later.';
}

if (!$reset && !$errors) {
    $errors[] = 'This reset link is invalid, already used or expired. Please request a new one.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $reset && !$errors) {
    verify_csrf();
    $errors = password_problems((string)($_POST['password'] ?? ''), (string)($_POST['confirm_password'] ?? ''));

    if (!$errors) {
        $pdo = db();
        try {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE users SET password_hash = ?, password_changed_at = NOW() WHERE user_id = ?')
                ->execute([password_hash((string)$_POST['password'], PASSWORD_DEFAULT), (int)$reset['user_id']]);
            $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE reset_id = ?')->execute([(int)$reset['reset_id']]);
            $pdo->prepare('DELETE FROM password_resets WHERE user_id = ? AND reset_id <> ?')->execute([(int)$reset['user_id'], (int)$reset['reset_id']]);
            $pdo->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$reset['email']]);
            $pdo->commit();
            send_email($reset['email'], 'Your password was changed', "Hi {$reset['full_name']},\n\nYour Norbooz Crochet password was just changed. If this was not you, contact us immediately at " . SHOP_EMAIL . '.');
            if (current_user()) {
                $_SESSION = [];
                session_regenerate_id(true);
            }
            flash('success', 'Your password has been changed. Please log in with your new password.');
            redirect('login.php');
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = 'Your password could not be changed. Please request a new link.';
        }
    }
}

page_header('Reset password');
?>
<section class="form-card narrow">
    <h1>Choose a new password</h1>
    <?= render_errors($errors) ?>
    <?php if (!$reset): ?>
        <p><a class="button" href="<?= e(url('forgot_password.php')) ?>">Request a new link</a></p>
    <?php else: ?>
        <p>Resetting the password for <strong><?= e($reset['email']) ?></strong>.</p>
        <form method="post">
            <?= csrf_input() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <label for="password">New password <span class="hint">(at least 10 characters, with a letter and a number)</span></label>
            <input id="password" type="password" name="password" minlength="10" maxlength="72" autocomplete="new-password" required>
            <label for="confirm_password">Confirm new password</label>
            <input id="confirm_password" type="password" name="confirm_password" minlength="10" maxlength="72" autocomplete="new-password" required>
            <button class="button button-block" type="submit">Change password</button>
        </form>
    <?php endif; ?>
</section>
<?php page_footer(); ?>
