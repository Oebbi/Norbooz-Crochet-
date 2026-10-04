<?php
require_once __DIR__ . '/config/functions.php';

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!$id) {
    not_found('That product does not exist.');
}

$stmt = db()->prepare('SELECT * FROM products WHERE product_id = ? AND is_active = 1');
$stmt->execute([$id]);
$product = $stmt->fetch();
if (!$product) {
    not_found('This product is no longer available.');
}

$category = canonical_product_category((string)$product['category']);
$stock = (int)$product['stock_qty'];
$inCart = (int)(cart()[$id] ?? 0);
$descriptionItems = preg_split('/(?:\R+|(?<=[.!?])\s+)/u', trim((string)$product['description'])) ?: [];

$related = db()->prepare(
    'SELECT product_id, name, category, price, stock_qty, image_path FROM products
     WHERE is_active = 1 AND product_id <> ? AND category IN (' . implode(',', array_fill(0, count(category_database_values($category)), '?')) . ')
     ORDER BY stock_qty > 0 DESC, product_id DESC LIMIT 4'
);
$related->execute(array_merge([$id], category_database_values($category)));
$related = $related->fetchAll();

page_header($product['name'], mb_substr((string)$product['description'], 0, 155));
?>
<nav class="breadcrumb" aria-label="Breadcrumb">
    <a href="<?= e(url('products.php')) ?>">Shop</a> <span aria-hidden="true">/</span>
    <a href="<?= e(category_url($category)) ?>"><?= e($category) ?></a> <span aria-hidden="true">/</span>
    <span aria-current="page"><?= e($product['name']) ?></span>
</nav>

<section class="product-detail">
    <div class="product-detail-image" data-product-zoom>
        <button class="product-zoom-trigger" type="button" data-zoom-trigger aria-label="Zoom product image" aria-pressed="false">
            <img src="<?= e(url(product_image($product['image_path'], $category))) ?>" alt="<?= e($product['name']) ?>, handmade crochet <?= e(strtolower($category)) ?>">
            <span class="product-zoom-lens" aria-hidden="true"></span>
            <span class="product-zoom-label" data-zoom-label aria-hidden="true">Zoom image</span>
        </button>
        <div class="product-zoom-preview" data-zoom-preview aria-hidden="true"></div>
    </div>
    <div class="product-detail-info">
        <p class="eyebrow"><?= e($category) ?></p>
        <h1><?= e($product['name']) ?></h1>
        <p class="price"><?= money($product['price']) ?></p>
        <p class="stock-line <?= $stock < 1 ? 'is-out' : ($stock <= LOW_STOCK_LEVEL ? 'is-low' : '') ?>">
            <?= $stock < 1 ? 'Sold out' : ($stock <= LOW_STOCK_LEVEL ? 'Only ' . $stock . ' left' : 'In stock') ?>
        </p>
        <h2 class="product-specs-heading">Product details</h2>
        <dl class="detail-list product-specs">
            <div><dt>Brand</dt><dd><?= e($product['brand']) ?></dd></div>
            <div><dt>Age range</dt><dd><?= e($product['age_range']) ?></dd></div>
            <div><dt>Colour</dt><dd><?= e($product['colour']) ?></dd></div>
            <div><dt>Theme</dt><dd><?= e($product['theme']) ?></dd></div>
            <div><dt>Item dimensions (L x W x H)</dt><dd><?= e($product['dimensions']) ?></dd></div>
        </dl>
        <p class="small-text">Sizes are approximate. Every piece is made by hand, so measurements and shades can vary slightly from the photo.</p>
        <h2 class="product-specs-heading">About this item</h2>
        <ul class="product-about">
            <?php foreach ($descriptionItems as $descriptionItem): ?>
                <?php if (trim($descriptionItem) !== ''): ?><li><?= e(trim($descriptionItem)) ?></li><?php endif; ?>
            <?php endforeach; ?>
        </ul>

        <?php if (is_admin()): ?>
            <a class="button" href="<?= e(url('admin_product_edit.php?id=' . $id)) ?>">Edit this product</a>
        <?php elseif ($stock > 0): ?>
            <form method="post" action="<?= e(url('cart.php')) ?>" class="add-to-cart">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= (int)$id ?>">
                <input type="hidden" name="return" value="product.php?id=<?= (int)$id ?>">
                <div class="qty-wrap">
                    <label for="quantity">Quantity</label>
                    <input id="quantity" type="number" name="quantity" min="1" max="<?= $stock ?>" value="1" required>
                </div>
                <button class="button" type="submit">Add to cart</button>
            </form>
            <?php if ($inCart): ?><p class="small-text"><?= $inCart ?> already in <a href="<?= e(url('cart.php')) ?>">your cart</a>.</p><?php endif; ?>
        <?php else: ?>
            <a class="button" href="<?= e(url('customize.php?based_on=' . $id)) ?>">Request one like this</a>
        <?php endif; ?>

        <ul class="detail-points">
            <li>Handmade by Norbooz Crochet in <?= e(SHOP_LOCATION) ?></li>
            <li><?= PICKUP_ENABLED ? 'Free pickup in Canberra, or ' : '' ?>Australia-wide post <?= money(POSTAGE_FEE) ?><?= FREE_POSTAGE_OVER > 0 ? ' (free over ' . money(FREE_POSTAGE_OVER) . ')' : '' ?></li>
            <li>Want a different colour or size? <a href="<?= e(url('customize.php?based_on=' . $id)) ?>">Ask for a custom version</a></li>
        </ul>
    </div>
</section>

<?php if ($related): ?>
<section class="section" aria-labelledby="related-heading">
    <div class="section-heading-row"><h2 id="related-heading">More <?= e(strtolower($category)) ?></h2><a class="text-link" href="<?= e(category_url($category)) ?>">View all</a></div>
    <div class="product-grid">
        <?php foreach ($related as $product) { include __DIR__ . '/partials/product_card.php'; } ?>
    </div>
</section>
<?php endif; ?>
<?php page_footer(); ?>
