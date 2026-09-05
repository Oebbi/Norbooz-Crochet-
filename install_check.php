<?php
require_once __DIR__ . '/config/functions.php';
$checks = [];
$checks[] = ['PHP version >= 8.0', version_compare(PHP_VERSION, '8.0', '>=')];
$checks[] = ['PDO extension loaded', extension_loaded('pdo')];
$checks[] = ['PDO MySQL extension loaded', extension_loaded('pdo_mysql')];
try {
    $pdo = db();
    $checks[] = ['Database connection', true];
    foreach (['users','products','orders','order_items'] as $table) {
        $pdo->query('SELECT 1 FROM ' . $table . ' LIMIT 1');
        $checks[] = ['Table: ' . $table, true];
    }
} catch (Throwable $ex) {
    $checks[] = ['Database connection/tables', false];
}
page_header('Installation Check');
?>
<section class="page-heading"><h1>Installation Check</h1><p>Use this page after importing the SQL file. All checks should show PASS before marking.</p></section>
<div class="table-wrap"><table><thead><tr><th>Check</th><th>Result</th></tr></thead><tbody><?php foreach ($checks as [$label,$ok]): ?><tr><td><?= e($label) ?></td><td><strong><?= $ok ? 'PASS' : 'FAIL' ?></strong></td></tr><?php endforeach; ?></tbody></table></div>
<p class="small-text">For assessment only. This page does not expose credentials or sensitive database details.</p>
<?php page_footer(); ?>
