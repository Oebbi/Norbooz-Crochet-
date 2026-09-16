<?php
require_once __DIR__ . '/config/functions.php';

$featuredProducts = [];
try {
    $featuredProducts = db()->query(
        'SELECT product_id, name, category, description, price, stock_qty, image_path
         FROM products
         WHERE is_active = 1
         ORDER BY product_id DESC
         LIMIT 8'
    )->fetchAll();
} catch (Throwable $ex) {
    // The homepage still renders before the database is installed.
}

$categoryImages = [
    'Plushies' => 'assets/images/categories/plushies.svg',
    'Throws' => 'assets/images/categories/throws.svg',
    'Bags' => 'assets/images/categories/bags.svg',
    'Hats' => 'assets/images/categories/hats.svg',
    'Keychains' => 'assets/images/categories/keychains.svg',
    'Flowers' => 'assets/images/categories/flowers.svg',
    'Wall Hangings' => 'assets/images/categories/wall-hangings.svg',
    'Coasters' => 'assets/images/categories/coasters.svg',
];

page_header('Home');
?>
<section class="hero marketplace-hero">
    <div class="hero-copy">
        <p class="eyebrow">Handmade by Norbooz Crochet</p>
        <h1>Small handmade pieces made to feel personal.</h1>
        <p class="hero-text">Browse crochet plushies, throws, bags, hats, keychains, flowers, wall hangings and coasters. Check stock, place an order and follow its progress in one simple system.</p>
        <div class="actions">
            <a class="button" href="<?= e(url('products.php')) ?>">Shop all products</a>
            <?php if (!current_user()): ?>
                <a class="button button-secondary" href="<?= e(url('register.php')) ?>">Create account</a>
            <?php elseif (current_user()['role'] === 'customer'): ?>
                <a class="button button-secondary" href="<?= e(url('my_orders.php')) ?>">Track my orders</a>
            <?php endif; ?>
        </div>
        <div class="trust-row" aria-label="Store features">
            <span>Handmade</span>
            <span>Clear stock</span>
            <span>Custom notes</span>
            <span>Order tracking</span>
        </div>
    </div>
    <div class="hero-photo-wrap">
        <img class="hero-photo" src="<?= e(url('assets/images/hero-photo.jpg')) ?>" alt="Crochet yarn and handmade items">
        <div class="hero-photo-note"><strong>Your crochet, your way.</strong><span>Add colour or customisation notes when ordering.</span></div>
    </div>
</section>

<section class="section" aria-labelledby="category-heading">
    <div class="section-heading-row">
        <div>
            <p class="eyebrow">Browse the collection</p>
            <h2 id="category-heading">Shop by product family</h2>
        </div>
        <a class="text-link" href="<?= e(url('products.php')) ?>">View all products</a>
    </div>
    <div class="category-grid">
        <?php foreach (product_categories() as $category): ?>
            <a class="category-card" href="<?= e(category_url($category)) ?>">
                <img src="<?= e(url($categoryImages[$category])) ?>" alt="" loading="lazy">
                <span><?= e($category) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<?php if ($featuredProducts): ?>
<section class="section" aria-labelledby="featured-heading">
    <div class="section-heading-row">
        <div>
            <p class="eyebrow">Fresh from the catalogue</p>
            <h2 id="featured-heading">Featured products</h2>
        </div>
        <a class="text-link" href="<?= e(url('products.php')) ?>">See the full shop</a>
    </div>
    <div class="product-grid product-grid-home">
        <?php foreach ($featuredProducts as $product): ?>
            <?php $displayCategory = canonical_product_category((string)$product['category']); ?>
            <article class="product-card">
                <a class="product-image-link" href="<?= e(url('order.php?product_id=' . (int)$product['product_id'])) ?>">
                    <img src="<?= e(url(safe_product_image_path((string)$product['image_path']))) ?>" alt="<?= e($product['name']) ?> crochet product" loading="lazy">
                </a>
                <div class="product-body">
                    <p class="product-category"><?= e($displayCategory) ?></p>
                    <h3><?= e($product['name']) ?></h3>
                    <div class="product-meta"><strong><?= money($product['price']) ?></strong><span><?= (int)$product['stock_qty'] > 0 ? 'In stock' : 'Sold out' ?></span></div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<section class="section benefits-section" aria-labelledby="why-heading">
    <div class="section-heading-row">
        <div>
            <p class="eyebrow">Simple and organised</p>
            <h2 id="why-heading">Designed for a small handmade business</h2>
        </div>
    </div>
    <div class="feature-grid">
        <article class="card"><span class="feature-number">01</span><h3>Clear product information</h3><p>Customers can see product names, categories, prices and current stock before ordering.</p></article>
        <article class="card"><span class="feature-number">02</span><h3>Easy order tracking</h3><p>Logged-in customers can follow whether an order is pending, in progress, ready or completed.</p></article>
        <article class="card"><span class="feature-number">03</span><h3>Protected administration</h3><p>The owner manages products, stock and order status from a role-protected administrator area.</p></article>
    </div>
</section>
<?php page_footer(); ?>
