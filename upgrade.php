<?php
/**
 * Upgrades an existing version 2 or 3 database to version 4 WITHOUT deleting customers, products or orders.
 * Works on MariaDB (XAMPP) and MySQL 8. Safe to run more than once: each change is only made if needed.
 *
 * Run it once after copying the new files:
 *   Browser (on the XAMPP computer): http://localhost/norbooz_crochet/upgrade.php
 *   or command line:                 php upgrade.php
 * Back up the database first (phpMyAdmin > Export).
 */
require_once __DIR__ . '/config/functions.php';

$cli = PHP_SAPI === 'cli';
if (!$cli && !is_local_request()) {
    http_response_code(403);
    exit('The upgrade can only be run on the computer hosting the site, or from the command line.');
}

$log = [];
$errors = [];
$run = $cli || ($_SERVER['REQUEST_METHOD'] === 'POST');

if ($run) {
    if (!$cli) {
        verify_csrf();
    }
    try {
        $pdo = db();
        $tableExists = function (string $table) use ($pdo): bool {
            $s = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
            $s->execute([$table]);
            return (int)$s->fetchColumn() > 0;
        };
        $columnExists = function (string $table, string $column) use ($pdo): bool {
            $s = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
            $s->execute([$table, $column]);
            return (int)$s->fetchColumn() > 0;
        };
        $indexExists = function (string $table, string $index) use ($pdo): bool {
            $s = $pdo->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?');
            $s->execute([$table, $index]);
            return (int)$s->fetchColumn() > 0;
        };
        $addColumn = function (string $table, string $column, string $definition) use ($pdo, $columnExists, &$log) {
            if (!$columnExists($table, $column)) {
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
                $log[] = "Added $table.$column";
            }
        };

        // New tables (the definitions match database/norbooz_crochet_db.sql).
        $schema = (string)file_get_contents(__DIR__ . '/database/norbooz_crochet_db.sql');
        foreach (['password_resets', 'login_attempts', 'order_status_history', 'custom_requests'] as $table) {
            if (!$tableExists($table) && preg_match('/CREATE TABLE ' . $table . ' \(.*?\) ENGINE=InnoDB;/s', $schema, $m)) {
                $pdo->exec(rtrim($m[0], ';'));
                $log[] = "Created table $table";
            }
        }

        $addColumn('users', 'marketing_opt_in', 'TINYINT(1) NOT NULL DEFAULT 0');
        $addColumn('users', 'password_changed_at', 'DATETIME NULL');
        $addColumn('users', 'is_deleted', 'TINYINT(1) NOT NULL DEFAULT 0');
        $addColumn('products', 'updated_at', 'DATETIME NULL');
        $pdo->exec('ALTER TABLE products MODIFY description VARCHAR(1000) NOT NULL');
        if (!$columnExists('orders', 'subtotal_amount')) {
            $addColumn('orders', 'subtotal_amount', 'DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `status`');
            $pdo->exec('UPDATE orders SET subtotal_amount = total_amount');
            $log[] = 'Copied order totals into subtotal';
        }
        $addColumn('orders', 'delivery_method', "ENUM('pickup','post') NOT NULL DEFAULT 'post' AFTER `subtotal_amount`");
        $addColumn('orders', 'delivery_fee', 'DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `delivery_method`');
        $addColumn('orders', 'payment_status', "ENUM('unpaid','paid','manual','failed','refunded') NOT NULL DEFAULT 'manual'");
        $addColumn('orders', 'payment_provider', "ENUM('stripe','paypal','manual') NOT NULL DEFAULT 'manual'");
        $addColumn('orders', 'payment_reference', 'VARCHAR(255) NULL');
        $addColumn('orders', 'paid_at', 'DATETIME NULL');
        if (!$indexExists('orders', 'uq_orders_payment_reference')) {
            $pdo->exec('ALTER TABLE orders ADD UNIQUE KEY uq_orders_payment_reference (payment_provider, payment_reference)');
            $log[] = 'Added unique payment reference index';
        }
        $addColumn('orders', 'updated_at', 'DATETIME NULL');
        $pdo->exec("ALTER TABLE custom_requests MODIFY status ENUM('new','reviewing','quoted','accepted','declined','completed') NOT NULL DEFAULT 'new'");
        $addColumn('custom_requests', 'quoted_price', 'DECIMAL(10,2) NULL');
        $addColumn('custom_requests', 'admin_response', 'TEXT NULL');
        $addColumn('custom_requests', 'updated_at', 'DATETIME NULL');

        // Give existing orders a starting entry in the audit history.
        $added = $pdo->exec("INSERT INTO order_status_history (order_id, old_status, new_status, note, changed_at)
                             SELECT o.order_id, NULL, o.status, 'Imported from version 2', o.order_date FROM orders o
                             WHERE NOT EXISTS (SELECT 1 FROM order_status_history h WHERE h.order_id = o.order_id)");
        if ($added) {
            $log[] = "Added history for $added existing order(s)";
        }

        // Point products at the optimised photos and tidy old category names.
        $fixed = 0;
        $update = $pdo->prepare('UPDATE products SET image_path = ? WHERE product_id = ?');
        foreach ($pdo->query('SELECT product_id, image_path, category FROM products')->fetchAll() as $product) {
            if (!product_image_exists($product['image_path'])) {
                $resolved = product_image($product['image_path'], (string)$product['category']);
                if ($resolved !== 'assets/images/product-placeholder.svg' && !str_ends_with($resolved, '-thumb.jpg')) {
                    $update->execute([$resolved, $product['product_id']]);
                    $fixed++;
                } elseif (str_ends_with($resolved, '-thumb.jpg')) {
                    $update->execute([str_replace('-thumb.jpg', '.jpg', $resolved), $product['product_id']]);
                    $fixed++;
                }
            }
        }
        if ($fixed) {
            $log[] = "Updated the photo path of $fixed product(s)";
        }
        $categories = $pdo->prepare('UPDATE products SET category = ? WHERE category = ?');
        foreach (['Beanies', 'Beanie', 'Hats & Beanies', 'Blankets', 'Blanket', 'Throws & Blankets', 'Wall Hangers', 'Wall Hanger', 'Coaster', 'Flower', 'Keychain'] as $old) {
            $categories->execute([canonical_product_category($old), $old]);
        }
        $log[] = $log ? 'Upgrade complete.' : 'Database is already up to date. Nothing to change.';
    } catch (Throwable $ex) {
        $errors[] = 'Upgrade stopped: ' . $ex->getMessage() . ' (The database user needs ALTER and CREATE rights; on XAMPP use root.)';
    }
}

if ($cli) {
    echo implode(PHP_EOL, array_merge($log, $errors)) . PHP_EOL;
    exit($errors ? 1 : 0);
}

page_header('Upgrade database');
?>
<section class="form-card narrow">
    <h1>Upgrade database to version 4</h1>
    <p>Adds the new tables and columns and links products to the optimised photos. Customers, products and orders are kept.</p>
    <?= render_errors($errors) ?>
    <?php if ($log): ?><div class="alert alert-success"><ul><?php foreach ($log as $line): ?><li><?= e($line) ?></li><?php endforeach; ?></ul></div>
        <a class="button" href="<?= e(url('install_check.php')) ?>">Run the installation check</a>
    <?php else: ?>
        <p><strong>Back up first:</strong> phpMyAdmin &rsaquo; <?= e(DB_NAME) ?> &rsaquo; Export &rsaquo; Go.</p>
        <form method="post"><?= csrf_input() ?><button class="button" type="submit">Upgrade now</button></form>
    <?php endif; ?>
</section>
<?php page_footer(); ?>
