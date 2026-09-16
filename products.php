<?php
require_once __DIR__ . '/config/functions.php';

$q = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($q) > 80) {
    $q = mb_substr($q, 0, 80);
}

$requestedCategory = trim((string)($_GET['category'] ?? ''));
$selectedCategory = in_array($requestedCategory, product_categories(), true) ? $requestedCategory : '';

$products = [];
try {
    $where = ['is_active = 1'];
    $params = [];

    if ($selectedCategory !== '') {
        $categoryValues = category_database_values($selectedCategory);
        $placeholders = implode(',', array_fill(0, count($categoryValues), '?'));
        $where[] = 'category IN (' . $placeholders . ')';
        array_push($params, ...$categoryValues);
    }

    if ($q !== '') {
        $where[] = '(name LIKE ? OR category LIKE ? OR description LIKE ?)';
        $term = '%' . $q . '%';
        array_push($params, $term, $term, $term);
    }

    $sql = 'SELECT product_id, name, category, description, price, stock_qty, image_path
            FROM products
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY CASE WHEN stock_qty > 0 THEN 0 ELSE 1 END, product_id DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll();
} catch (Throwable $ex) {
    flash('error', 'Products could not be loaded. If this is a new installation, import the SQL database first.');
}

page_header('Products');
?>
<section class="shop-heading">
    <div>
        <p class="eyebrow">Norbooz Crochet shop</p>
        <h1><?= $selectedCategory !== '' ? e($selectedCategory) : 'Handmade crochet products' ?></h1>
        <p><?= $selectedCategory !== '' ? 'Browse the ' . e(strtolower($selectedCategory)) . ' collection.' : 'Browse all handmade items, check availability and order securely through your account.' ?></p>
    </div>
    <div class="shop-count" aria-live="polite"><strong><?= count($products) ?></strong><span><?= count($products) === 1 ? 'product' : 'products' ?></span></div>
</section>

<form class="catalog-tools" method="get" action="<?= e(url('products.php')) ?>" role="search">
    <?php if ($selectedCategory !== ''): ?><input type="hidden" name="category" value="<?= e($selectedCategory) ?>"><?php endif; ?>
    <label for="product-search">Search this collection</label>
    <div class="catalog-search-row">
        <input id="product-search" name="q" type="search" value="<?= e($q) ?>" placeholder="Try 'capybara', 'flower' or 'bag'" maxlength="80" autocomplete="off">
        <button class="button" type="submit">Search</button>
        <?php if ($q !== '' || $selectedCategory !== ''): ?><a class="button button-ghost" href="<?= e(url('products.php')) ?>">Clear</a><?php endif; ?>
    </div>
</form>

<nav class="filter-pills" aria-label="Product families">
    <a class="filter-pill <?= $selectedCategory === '' ? 'active' : '' ?>" href="<?= e(url('products.php')) ?>">All</a>
    <?php foreach (product_categories() as $category): ?>
        <a class="filter-pill <?= $selectedCategory === $category ? 'active' : '' ?>" href="<?= e(category_url($category)) ?>"><?= e($category) ?></a>
    <?php endforeach; ?>
</nav>

<?php if (!$products): ?>
    <div class="empty-state shop-empty">
        <h2>No matching products</h2>
        <p>Try another search or browse all product families.</p>
        <a class="button" href="<?= e(url('products.php')) ?>">View all products</a>
    </div>
<?php else: ?>
    <div class="product-grid marketplace-grid" id="product-grid">
        <?php foreach ($products as $product): ?>
            <?php
                $stock = (int)$product['stock_qty'];
                $displayCategory = canonical_product_category((string)$product['category']);
            ?>
            <article class="product-card">
                <div class="product-image-wrap">
                    <img src="<?= e(url(safe_product_image_path((string)$product['image_path']))) ?>" alt="<?= e($product['name']) ?> crochet product" loading="lazy">
                    <?php if ($stock === 0): ?><span class="product-badge sold-out">Sold out</span><?php elseif ($stock <= 2): ?><span class="product-badge">Only <?= $stock ?> left</span><?php endif; ?>
                </div>
                <div class="product-body">
                    <p class="product-category"><?= e($displayCategory) ?></p>
                    <h2><?= e($product['name']) ?></h2>
                    <p class="product-description"><?= e($product['description']) ?></p>
                    <div class="product-meta">
                        <strong><?= money($product['price']) ?></strong>
                        <span><?= $stock > 0 ? $stock . ' in stock' : 'Unavailable' ?></span>
                    </div>
                    <?php if ($stock > 0): ?>
                        <?php if (current_user() && current_user()['role'] === 'customer'): ?>
                            <a class="button product-button" href="<?= e(url('order.php?product_id=' . (int)$product['product_id'])) ?>">Order this item</a>
                        <?php elseif (!current_user()): ?>
                            <a class="button button-secondary product-button" href="<?= e(url('login.php')) ?>">Sign in to order</a>
                        <?php else: ?>
                            <a class="button button-secondary product-button" href="<?= e(url('admin.php')) ?>">Manage product</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="stock-unavailable">Currently unavailable</span>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php page_footer(); ?>
