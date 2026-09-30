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
        <h1>Thoughtful crochet pieces for everyday living.</h1>
        <p class="hero-text">Discover plushies, throws, bags, hats, keychains, flowers, wall hangings and coasters designed to feel personal, practical and handmade.</p>
        <div class="actions">
            <a class="button" href="#collections">Browse collection</a>
            <a class="button button-secondary" href="<?= e(url('products.php')) ?>">Start a custom order</a>
        </div>
        <div class="trust-row" aria-label="Store features">
            <span>Handmade</span>
            <span>Free custom notes</span>
            <span>Clear stock</span>
            <span>Fast order updates</span>
        </div>
    </div>
    <div class="hero-photo-wrap">
        <img class="hero-photo" src="<?= e(url('assets/images/hero-photo.jpg.png')) ?>" alt="Handmade crochet plushies and products">
        <div class="hero-photo-note">
            <strong>New arrivals</strong>
            <span>Handmade favourites for gifting, home styling and cosy daily essentials.</span>
        </div>
    </div>
</section>

<section class="section" id="collections" aria-labelledby="category-heading">
    <div class="section-heading-row">
        <div>
            <p class="eyebrow">Browse the collection</p>
            <h2 id="category-heading">Shop by product family</h2>
        </div>
        <a class="text-link" href="<?= e(url('products.php')) ?>">Request a custom piece</a>
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
        <a class="text-link" href="<?= e(url('products.php')) ?>">Make something personal</a>
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
            <p class="eyebrow">Why customers shop here</p>
            <h2 id="why-heading">Made to feel thoughtful and personal</h2>
        </div>
    </div>
    <div class="feature-grid">
        <article class="card"><span class="feature-number">01</span><h3>Unique handmade pieces</h3><p>Every item is designed to feel warm, personal and different from mass-produced alternatives.</p></article>
        <article class="card"><span class="feature-number">02</span><h3>Easy order customisation</h3><p>Customers can add notes and track their order progress without friction.</p></article>
        <article class="card"><span class="feature-number">03</span><h3>Small-batch quality</h3><p>Thoughtful materials and simple browsing make each order feel more personal and intentional.</p></article>
    </div>
</section>
<?php page_footer(); ?>
