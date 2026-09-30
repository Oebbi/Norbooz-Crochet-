<?php
require_once __DIR__ . '/config/functions.php';
require_admin();

$pdo = db();
$id = filter_var($_GET['id'] ?? $_POST['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
$errors = [];
$product = [
    'name' => '', 'category' => '', 'description' => '', 'price' => '', 'stock_qty' => '1',
    'image_path' => 'assets/images/product-placeholder.svg', 'is_active' => 1,
];

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE product_id = ?');
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) {
        not_found('Product not found.');
    }
    $timesOrdered = $pdo->prepare('SELECT COUNT(*) FROM order_items WHERE product_id = ?');
    $timesOrdered->execute([$id]);
    $timesOrdered = (int)$timesOrdered->fetchColumn();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (($_POST['action'] ?? '') === 'delete' && $id) {
        if ($timesOrdered > 0) {
            flash('error', 'This product appears in ' . $timesOrdered . ' order(s), so it cannot be deleted. Untick "Visible in shop" to hide it instead.');
            redirect('admin_product_edit.php?id=' . $id);
        }
        $pdo->prepare('DELETE FROM products WHERE product_id = ?')->execute([$id]);
        flash('success', 'Product deleted.');
        redirect('admin_products.php');
    }

    $product['name'] = trim((string)($_POST['name'] ?? ''));
    $product['category'] = trim((string)($_POST['category'] ?? ''));
    $product['description'] = trim((string)($_POST['description'] ?? ''));
    $product['price'] = trim((string)($_POST['price'] ?? ''));
    $product['stock_qty'] = trim((string)($_POST['stock_qty'] ?? ''));
    $product['is_active'] = isset($_POST['is_active']) ? 1 : 0;
    $typedPath = trim((string)($_POST['image_path'] ?? ''));

    $price = filter_var($product['price'], FILTER_VALIDATE_FLOAT);
    $stock = filter_var($product['stock_qty'], FILTER_VALIDATE_INT);
    if (!text_length_ok($product['name'], 2, 120)) {
        $errors[] = 'Product name must be 2-120 characters.';
    }
    if (!in_array($product['category'], product_categories(), true)) {
        $errors[] = 'Choose a product family.';
    }
    if (!text_length_ok($product['description'], 10, 1000)) {
        $errors[] = 'Description must be 10-1,000 characters.';
    }
    if ($price === false || $price < 0 || $price > 99999) {
        $errors[] = 'Enter a price between $0 and $99,999.';
    }
    if ($stock === false || $stock < 0 || $stock > 100000) {
        $errors[] = 'Stock must be a whole number of 0 or more.';
    }

    // A new photo upload takes priority over the typed path.
    if (!$errors && isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        try {
            $product['image_path'] = store_product_image($_FILES['image']);
        } catch (RuntimeException $ex) {
            $errors[] = $ex->getMessage();
        }
    } elseif ($typedPath !== '' && $typedPath !== $product['image_path']) {
        $safe = safe_product_image_path($typedPath);
        if ($safe === 'assets/images/product-placeholder.svg' && $typedPath !== $safe) {
            $errors[] = 'The image path must point to a file inside assets/images, for example assets/images/products/bunny-plush.jpg.';
        } elseif (!is_file(APP_ROOT . '/' . $safe)) {
            $errors[] = 'No image file was found at ' . $safe . '. Check the spelling (it is case-sensitive on most web hosts) or upload the photo instead.';
        } else {
            $product['image_path'] = $safe;
        }
    }

    if (!$errors) {
        $values = [$product['name'], $product['category'], $product['description'], cents_to_decimal(to_cents($price)), $stock, $product['image_path'], $product['is_active']];
        if ($id) {
            $pdo->prepare('UPDATE products SET name = ?, category = ?, description = ?, price = ?, stock_qty = ?, image_path = ?, is_active = ?, updated_at = NOW() WHERE product_id = ?')
                ->execute(array_merge($values, [$id]));
            flash('success', 'Saved "' . $product['name'] . '".');
        } else {
            $pdo->prepare('INSERT INTO products (name, category, description, price, stock_qty, image_path, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute($values);
            $id = (int)$pdo->lastInsertId();
            flash('success', 'Added "' . $product['name'] . '".');
        }
        redirect('admin_product_edit.php?id=' . $id);
    }
}

$category = $product['category'] !== '' ? canonical_product_category((string)$product['category']) : '';
page_header($id ? 'Edit ' . $product['name'] : 'Add product');
admin_nav();
?>
<nav class="breadcrumb" aria-label="Breadcrumb"><a href="<?= e(url('admin_products.php')) ?>">Products</a> <span aria-hidden="true">/</span> <span aria-current="page"><?= $id ? e($product['name']) : 'New product' ?></span></nav>
<section class="page-heading"><h1><?= $id ? 'Edit product' : 'Add a product' ?></h1>
<?php if ($id): ?><p><a class="text-link" href="<?= e(url('product.php?id=' . $id)) ?>">View in shop</a><?= (int)$product['is_active'] ? '' : ' (currently hidden)' ?></p><?php endif; ?></section>

<?= render_errors($errors) ?>

<form method="post" enctype="multipart/form-data" class="checkout-layout">
    <?= csrf_input() ?>
    <?php if ($id): ?><input type="hidden" name="product_id" value="<?= (int)$id ?>"><?php endif; ?>
    <div class="form-card">
        <div class="form-grid">
            <div class="full"><label for="name">Name</label><input id="name" name="name" maxlength="120" value="<?= e($product['name']) ?>" required></div>
            <div><label for="category">Product family</label>
                <select id="category" name="category" required>
                    <option value="">Choose</option>
                    <?php foreach (product_categories() as $option): ?><option value="<?= e($option) ?>" <?= $category === $option ? 'selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?>
                </select></div>
            <div><label for="price">Price (AUD)</label><input id="price" type="number" step="0.01" min="0" max="99999" name="price" value="<?= e($product['price']) ?>" required></div>
            <div><label for="stock_qty">Stock</label><input id="stock_qty" type="number" min="0" max="100000" name="stock_qty" value="<?= e($product['stock_qty']) ?>" required></div>
            <div class="checkbox-cell"><label class="checkbox"><input type="checkbox" name="is_active" value="1" <?= (int)$product['is_active'] ? 'checked' : '' ?>> Visible in shop</label></div>
            <div class="full"><label for="description">Description</label><textarea id="description" name="description" rows="5" maxlength="1000" required><?= e($product['description']) ?></textarea></div>
        </div>
        <button class="button" type="submit"><?= $id ? 'Save changes' : 'Add product' ?></button>
    </div>

    <aside class="summary-card">
        <h2>Photo</h2>
        <img class="edit-preview" id="image-preview" src="<?= e(url(product_image($product['image_path'], $category))) ?>" alt="Current product photo">
        <label for="image">Upload a new photo</label>
        <input id="image" type="file" name="image" accept="image/jpeg,image/png,image/webp" data-preview="image-preview">
        <p class="hint">JPG, PNG or WEBP up to <?= (int)MAX_UPLOAD_MB ?> MB. Photos are resized automatically for fast loading. Square photos in daylight look best.</p>
        <details>
            <summary class="small-text">Advanced: use an existing file</summary>
            <label for="image_path">Image path</label>
            <input id="image_path" name="image_path" value="<?= e($product['image_path']) ?>">
            <p class="hint">A file already inside assets/images/products, e.g. assets/images/products/bunny-plush.jpg</p>
        </details>
    </aside>
</form>

<?php if ($id): ?>
<section class="admin-section danger-zone">
    <h2>Delete product</h2>
    <?php if ($timesOrdered > 0): ?>
        <p>This product is part of <?= $timesOrdered ?> order(s), so it is kept for the order records. Untick <em>Visible in shop</em> to hide it.</p>
    <?php else: ?>
        <form method="post" data-confirm="Delete this product permanently?">
            <?= csrf_input() ?>
            <input type="hidden" name="product_id" value="<?= (int)$id ?>">
            <input type="hidden" name="action" value="delete">
            <button class="button button-danger" type="submit">Delete product</button>
        </form>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php page_footer(); ?>
