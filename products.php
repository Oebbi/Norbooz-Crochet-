<?php
require_once __DIR__ . '/config/functions.php';

try {
    $stmt = db()->query(
        'SELECT product_id, name, category, description, price, stock_qty, image_path
         FROM products
         WHERE is_active = 1
         ORDER BY name'
    );
    $products = $stmt->fetchAll();
} catch (Throwable $ex) {
    $products = [];
    flash('error', 'Products could not be loaded. If this is a new installation, import the SQL database first.');
}

page_header('Products');
?>
<section class="page-heading">
    <h1>Products</h1>
    <p>Browse current crochet products, prices and stock availability.</p>
</section>

<?php if ($products): ?>
    <div class="catalog-tools">
        <label for="product-search">Search products</label>
        <input id="product-search" type="search" placeholder="Search by name, category or description" autocomplete="off">
    </div>
<?php endif; ?>

<?php if (!$products): ?>
    <div class="empty-state">
        <h2>No products available</h2>
        <p>Please check the database installation or add products from the Admin Panel.</p>
    </div>
<?php else: ?>
    <div class="product-grid" id="product-grid">
        <?php foreach ($products as $product): ?>
            <article class="product-card" data-search="<?= e(strtolower($product['name'] . ' ' . $product['category'] . ' ' . $product['description'])) ?>">
                <img src="<?= e(url(safe_product_image_path((string)$product['image_path']))) ?>" alt="<?= e($product['name']) ?> crochet product">
                <div class="product-body">
                    <p class="tag"><?= e($product['category']) ?></p>
                    <h2><?= e($product['name']) ?></h2>
                    <p><?= e($product['description']) ?></p>
                    <div class="product-meta">
                        <strong><?= money($product['price']) ?></strong>
                        <span><?= (int)$product['stock_qty'] > 0 ? (int)$product['stock_qty'] . ' in stock' : 'Out of stock' ?></span>
                    </div>

                    <?php if ((int)$product['stock_qty'] > 0): ?>
                        <?php if (current_user() && current_user()['role'] === 'customer'): ?>
                            <a class="button" href="<?= e(url('order.php?product_id=' . (int)$product['product_id'])) ?>">Order this product</a>
                        <?php elseif (!current_user()): ?>
                            <a class="button button-secondary" href="<?= e(url('login.php')) ?>">Log in to order</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="stock-unavailable">Currently unavailable</span>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    <p id="no-search-results" class="empty-state hidden">No products match your search.</p>
<?php endif; ?>
<?php page_footer(); ?>
