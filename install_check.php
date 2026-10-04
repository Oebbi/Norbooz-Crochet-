<?php
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/payments.php';

// Installation diagnostics are only shown on the computer running the site, or to the administrator.
if (!is_local_request() && !is_admin()) {
    http_response_code(403);
    exit('Installation check is only available on the local machine or to the administrator.');
}

$checks = [];
$add = function (string $label, bool $ok, string $help = '', bool $warningOnly = false) use (&$checks) {
    $checks[] = ['label' => $label, 'ok' => $ok, 'help' => $help, 'warning' => $warningOnly];
};

$add('PHP version 8.1 or newer (found ' . PHP_VERSION . ')', version_compare(PHP_VERSION, '8.1', '>='), 'Use XAMPP 8.1 or newer.');
$add('PDO MySQL extension', extension_loaded('pdo_mysql'), 'Enable extension=pdo_mysql in php.ini.');
$onlinePaymentsConfigured = PAYPAL_CLIENT_ID !== '' && PAYPAL_CLIENT_SECRET !== '';
$add('cURL extension for online payments', !$onlinePaymentsConfigured || extension_loaded('curl'), 'Enable extension=curl in php.ini and restart Apache.', !$onlinePaymentsConfigured);
$paymentCredentialsReady = PAYPAL_CLIENT_ID !== '' && PAYPAL_CLIENT_SECRET !== '' && PAYPAL_WEBHOOK_ID !== '';
$localSandbox = PAYPAL_MODE !== 'live' && (is_local_http_url(APP_URL) || (APP_URL === '' && is_local_request()));
$add('HTTPS public URL for hosted payments', !$paymentCredentialsReady || str_starts_with(APP_URL, 'https://') || $localSandbox, 'Set APP_URL to the public HTTPS address configured with PayPal. (http://localhost is accepted only in sandbox mode.)', !$paymentCredentialsReady);
$add('mbstring extension', extension_loaded('mbstring'), 'Enable extension=mbstring in php.ini.');
$add('fileinfo extension (checks uploaded files)', extension_loaded('fileinfo'), 'Enable extension=fileinfo in php.ini.', true);
$add('GD extension (resizes uploaded photos)', extension_loaded('gd'), 'Enable extension=gd in php.ini, then restart Apache. Without it, uploads are stored at full size.', true);
$add('Product photo folder is writable', is_writable(APP_ROOT . '/' . PRODUCT_IMAGE_DIR), 'Allow the web server to write to assets/images/products.');
$add('Storage folder is writable', is_dir(STORAGE_DIR) && is_writable(STORAGE_DIR), 'Create the storage folder and allow the web server to write to it.');

$dbOk = false;
try {
    $pdo = db();
    $add('Database connection (' . DB_NAME . ')', true);
    $dbOk = true;
    foreach (['users', 'products', 'orders', 'order_items', 'order_status_history', 'custom_requests', 'password_resets', 'login_attempts'] as $table) {
        try {
            $pdo->query('SELECT 1 FROM ' . $table . ' LIMIT 1');
            $add('Table: ' . $table, true);
        } catch (Throwable $ex) {
            $add('Table: ' . $table, false, 'Import database/norbooz_crochet_db.sql (new install) or upgrade.php (existing data).');
        }
    }
    try {
        $pdo->query('SELECT delivery_method, subtotal_amount FROM orders LIMIT 1');
        $pdo->query('SELECT password_changed_at, is_deleted FROM users LIMIT 1');
        $pdo->query('SELECT payment_status, payment_provider, payment_reference, paid_at FROM orders LIMIT 1');
        $pdo->query('SELECT brand, age_range, colour, theme, dimensions FROM products LIMIT 1');
        $providerColumn = $pdo->query("SHOW COLUMNS FROM orders LIKE 'payment_provider'")->fetch();
        $providerType = (string)($providerColumn['Type'] ?? '');
        if (!str_contains($providerType, "'card'") || !str_contains($providerType, "'demo'") || str_contains($providerType, "'stripe'")) {
            throw new RuntimeException('Payment provider schema needs the version 7 migration.');
        }
        $add('Database schema is version 7', true);
    } catch (Throwable $ex) {
        $add('Database schema is version 7', false, 'Back up the database, then open upgrade.php to update the payment provider schema.');
    }
    $admins = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    $add('Administrator account exists', $admins > 0, 'Re-import the database file.');
    $default = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND password_changed_at IS NULL")->fetchColumn();
    $add('Temporary administrator password has been changed', $default === 0, 'Log in as the administrator and change the password on the Account page before going live.', true);

    $paths = $pdo->query('SELECT name, image_path FROM products')->fetchAll();
    $missing = array_filter($paths, fn($p) => !product_image_exists($p['image_path']));
    $add(count($paths) . ' products loaded, ' . (count($paths) - count($missing)) . ' with a matching photo file', count($paths) > 0 && !$missing,
        $missing ? 'Photo file not found for: ' . implode(', ', array_slice(array_column($missing, 'name'), 0, 6)) . (count($missing) > 6 ? '...' : '') . '. A similar photo is shown automatically; run upgrade.php or upload photos in Admin > Products.' : 'Import the database file.', (bool)$paths);
} catch (Throwable $ex) {
    if (!$dbOk) {
        $add('Database connection (' . DB_NAME . ')', false, 'Start MySQL in XAMPP, import database/norbooz_crochet_db.sql, and check the settings in config/config.php or config/local.php.');
    }
}

$add('Email mode: ' . MAIL_MODE, true, MAIL_MODE === 'log' ? 'Emails are written to storage/mail.log. Set MAIL_MODE to "mail" in config/local.php on the live host.' : '');
$add('HTTPS', is_https_request(), 'Local XAMPP uses HTTP. The live site must use HTTPS (free certificates are included with most hosts).', true);
$add('Live settings file config/local.php', is_file(APP_ROOT . '/config/local.php'), 'Not needed on XAMPP. On the live host, copy config/local.example.php to config/local.php.', true);

$failed = count(array_filter($checks, fn($c) => !$c['ok'] && !$c['warning']));
page_header('Installation check');
?>
<section class="page-heading">
    <h1>Installation check</h1>
    <p>Open this page after importing the database. Every required check should show PASS.</p>
</section>
<div class="alert <?= $failed ? 'alert-error' : 'alert-success' ?>"><?= $failed ? $failed . ' required check(s) failed. Follow the help text below.' : 'Installation looks ready. Open the shop or log in as the administrator.' ?></div>
<div class="table-wrap" role="region" aria-label="Installation checks table (scrolls sideways on small screens)" tabindex="0">
    <table>
        <thead><tr><th scope="col">Check</th><th scope="col">Result</th><th scope="col">If it fails</th></tr></thead>
        <tbody>
        <?php foreach ($checks as $check): ?>
            <tr>
                <td><?= e($check['label']) ?></td>
                <td><strong class="<?= $check['ok'] ? 'text-ok' : ($check['warning'] ? 'text-warn' : 'text-bad') ?>"><?= $check['ok'] ? 'PASS' : ($check['warning'] ? 'WARNING' : 'FAIL') ?></strong></td>
                <td class="small-text"><?= (!$check['ok'] || str_starts_with($check['label'], 'Email')) ? e($check['help']) : '' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="small-text">No passwords or server error details are displayed on this page.</p>
<?php page_footer(); ?>
