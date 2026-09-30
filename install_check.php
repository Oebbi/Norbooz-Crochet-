<?php
require_once __DIR__ . '/config/functions.php';

$checks = [];
$checks[] = ['PHP version >= 8.0', version_compare(PHP_VERSION, '8.0', '>=')];
$checks[] = ['PDO extension loaded', extension_loaded('pdo')];
$checks[] = ['PDO MySQL extension loaded', extension_loaded('pdo_mysql')];

try {
    $pdo = db();
    $checks[] = ['Database connection', true];

    foreach (['users', 'products', 'orders', 'order_items', 'custom_requests', 'password_resets'] as $table) {
        $pdo->query('SELECT 1 FROM ' . $table . ' LIMIT 1');
        $checks[] = ['Table: ' . $table, true];
    }

    $admin = $pdo->prepare("SELECT password_hash FROM users WHERE email = 'admin@norboozcrochet.local' AND role = 'admin' LIMIT 1");
    $admin->execute();
    $adminHash = $admin->fetchColumn();
    $checks[] = ['Demo administrator exists', is_string($adminHash) && password_verify('Admin@12345', $adminHash)];
    $checks[] = ['Sample products loaded', (int)$pdo->query('SELECT COUNT(*) FROM products')->fetchColumn() >= 1];
} catch (Throwable $ex) {
    $checks[] = ['Database connection/tables', false];
}

$allPassed = !in_array(false, array_column($checks, 1), true);
page_header('Installation Check');
?>
<section class="page-heading">
    <h1>Installation Check</h1>
    <p>Run this page after importing the SQL file. All checks should show PASS before the system is demonstrated or submitted.</p>
</section>

<div class="alert <?= $allPassed ? 'alert-success' : 'alert-info' ?>">
    <?= $allPassed ? 'Installation looks ready.' : 'One or more checks failed. Follow the README installation steps and check XAMPP/MySQL.' ?>
</div>

<div class="table-wrap">
    <table>
        <thead><tr><th>Check</th><th>Result</th></tr></thead>
        <tbody>
            <?php foreach ($checks as [$label, $ok]): ?>
                <tr><td><?= e($label) ?></td><td><strong><?= $ok ? 'PASS' : 'FAIL' ?></strong></td></tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="small-text">Assessment helper only. No database password or server error details are displayed.</p>
<?php page_footer(); ?>
