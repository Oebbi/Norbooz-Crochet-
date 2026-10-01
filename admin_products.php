<?php
require_once __DIR__ . '/config/functions.php';
require_admin();

// Quick stock / visibility update from the list.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $stock = filter_var($_POST['stock_qty'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100000]]);
    if ($id && $stock !== false) {
        db()->prepare('UPDATE products SET stock_qty = ?, is_active = ?, updated_at = NOW() WHERE product_id = ?')
            ->execute([$stock, isset($_POST['is_active']) ? 1 : 0, $id]);
        flash('success', 'Product #' . $id . ' updated.');
    } else {
        flash('error', 'Enter a stock quantity of 0 or more.');
    }
    redirect('admin_products.php?' . http_build_query(array_filter(['q' => $_POST['q'] ?? '', 'filter' => $_POST['filter'] ?? ''])));
}

$search = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 80));
$filter = in_array($_GET['filter'] ?? '', ['low', 'hidden', 'noimage'], true) ? (string)$_GET['filter'] : '';

$where = ['1 = 1'];
$params = [];
if ($search !== '') {
    $where[] = '(name LIKE ? OR category LIKE ?)';
    $like = '%' . addcslashes($search, '%_\\') . '%';
    array_push($params, $like, $like);
}
if ($filter === 'low') {
    $where[] = 'is_active = 1 AND stock_qty <= ' . (int)LOW_STOCK_LEVEL;
} elseif ($filter === 'hidden') {
    $where[] = 'is_active = 0';
}
$stmt = db()->prepare('SELECT * FROM products WHERE ' . implode(' AND ', $where) . ' ORDER BY is_active DESC, category, name');
$stmt->execute($params);
$products = $stmt->fetchAll();
if ($filter === 'noimage') {
    $products = array_values(array_filter($products, fn($p) => !product_image_exists($p['image_path'])));
}
$missingImages = count(array_filter(db()->query('SELECT image_path FROM products')->fetchAll(PDO::FETCH_COLUMN), fn($p) => !product_image_exists($p)));

page_header('Products');
admin_nav();
?>
<section class="page-heading section-heading-row">
    <div><h1>Products</h1><p><?= count($products) ?> shown. Hidden products stay in old orders but are not shown in the shop.</p></div>
    <a class="button" href="<?= e(url('admin_product_edit.php')) ?>">Add product</a>
</section>

<?php if ($missingImages): ?>
    <div class="alert alert-info"><?= $missingImages ?> product<?= $missingImages === 1 ? ' has' : 's have' ?> an image path that does not match a file, so a similar photo is shown instead. <a href="<?= e(url('admin_products.php?filter=noimage')) ?>">Show them</a> and upload a photo.</div>
<?php endif; ?>

<form class="filter-bar" method="get">
    <div class="filter-search"><label for="q" class="sr-only">Search products</label><input id="q" type="search" name="q" value="<?= e($search) ?>" placeholder="Search by name or family"></div>
    <div><label for="filter" class="sr-only">Show</label>
        <select id="filter" name="filter">
            <option value="">All products</option>
            <option value="low" <?= $filter === 'low' ? 'selected' : '' ?>>Low stock</option>
            <option value="hidden" <?= $filter === 'hidden' ? 'selected' : '' ?>>Hidden</option>
            <option value="noimage" <?= $filter === 'noimage' ? 'selected' : '' ?>>Missing photo</option>
        </select></div>
    <button class="button button-small" type="submit">Filter</button>
</form>

<div class="table-wrap" role="region" aria-label="Products table (scrolls sideways on small screens)" tabindex="0">
    <table class="product-table">
        <thead><tr><th scope="col">Product</th><th scope="col">Family</th><th scope="col">Price</th><th scope="col">Stock and visibility</th><th scope="col"><span class="sr-only">Edit</span></th></tr></thead>
        <tbody>
        <?php foreach ($products as $product): $pid = (int)$product['product_id']; ?>
            <tr class="<?= (int)$product['is_active'] ? '' : 'row-muted' ?>">
                <td><div class="table-product"><img src="<?= e(url(product_image($product['image_path'], (string)$product['category'], true))) ?>" alt="" width="56" height="56"><div><strong><?= e($product['name']) ?></strong><br><span class="small-text">#<?= $pid ?><?= product_image_exists($product['image_path']) ? '' : ' &middot; photo missing' ?></span></div></div></td>
                <td><?= e(canonical_product_category((string)$product['category'])) ?></td>
                <td><?= money($product['price']) ?></td>
                <td>
                    <form method="post" class="stock-form">
                        <?= csrf_input() ?>
                        <input type="hidden" name="product_id" value="<?= $pid ?>">
                        <input type="hidden" name="q" value="<?= e($search) ?>">
                        <input type="hidden" name="filter" value="<?= e($filter) ?>">
                        <label class="sr-only" for="stock-<?= $pid ?>">Stock for <?= e($product['name']) ?></label>
                        <input id="stock-<?= $pid ?>" type="number" name="stock_qty" min="0" max="100000" value="<?= (int)$product['stock_qty'] ?>" class="input-narrow">
                        <label class="checkbox"><input type="checkbox" name="is_active" value="1" <?= (int)$product['is_active'] ? 'checked' : '' ?>> Visible</label>
                        <button class="button button-small button-secondary" type="submit">Save</button>
                    </form>
                </td>
                <td><a class="button button-small" href="<?= e(url('admin_product_edit.php?id=' . $pid)) ?>">Edit</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php page_footer(); ?>
