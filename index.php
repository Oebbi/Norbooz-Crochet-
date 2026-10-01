<?php
require_once __DIR__ . '/config/functions.php';

$featured = [];
$counts = [];
try {
    $featured = db()->query(
        'SELECT product_id, name, category, price, stock_qty, image_path
         FROM products
         WHERE is_active = 1 AND stock_qty > 0
         ORDER BY product_id DESC
         LIMIT 8'
    )->fetchAll();
    foreach (db()->query('SELECT category, COUNT(*) AS total FROM products WHERE is_active = 1 GROUP BY category')->fetchAll() as $row) {
        $name = canonical_product_category((string)$row['category']);
        $counts[$name] = ($counts[$name] ?? 0) + (int)$row['total'];
    }
} catch (Throwable $ex) {
    flash('error', 'The product catalogue is not available yet. Import database/norbooz_crochet_db.sql and open install_check.php.');
}

page_header('Handmade crochet gifts and custom pieces');
?>
<section class="hero">
    <div class="hero-copy">
        <p class="eyebrow">Handmade in <?= e(SHOP_LOCATION) ?></p>
        <h1>Thoughtful crochet pieces for everyday living.</h1>
        <p class="hero-text">Plushies, throws, bags, hats, keychains, flowers, wall hangings and coasters, each made by hand in small batches. Choose something from the shop or ask for a piece made just for you.</p>
        <div class="actions">
            <a class="button" href="<?= e(url('products.php')) ?>">Shop the collection</a>
            <a class="button button-secondary" href="<?= e(url('customize.php')) ?>">Request a custom piece</a>
        </div>
        <ul class="trust-row" aria-label="Why shop with us">
            <li>Handmade to order</li>
            <li>Pickup or Australia-wide post</li>
            <li>Track every order online</li>
        </ul>
    </div>
    <div class="hero-photo-wrap">
        <img class="hero-photo" src="<?= e(url('assets/images/hero.jpg')) ?>" alt="A collection of handmade crochet bunnies and plushies" width="952" height="1100">
    </div>
</section>

<section class="section" id="collections" aria-labelledby="category-heading">
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
                <img src="<?= e(url(category_image($category))) ?>" alt="" loading="lazy" width="600" height="600">
                <span class="category-name"><?= e($category) ?></span>
                <span class="category-count"><?= (int)($counts[$category] ?? 0) ?> item<?= (int)($counts[$category] ?? 0) === 1 ? '' : 's' ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<?php if ($featured): ?>
<section class="section" aria-labelledby="featured-heading">
    <div class="section-heading-row">
        <div>
            <p class="eyebrow">Fresh from the hook</p>
            <h2 id="featured-heading">New arrivals</h2>
        </div>
        <a class="text-link" href="<?= e(url('products.php?sort=newest')) ?>">See everything new</a>
    </div>
    <div class="product-grid">
        <?php foreach ($featured as $product) { include __DIR__ . '/partials/product_card.php'; } ?>
    </div>
</section>
<?php endif; ?>

<section class="section steps-section" aria-labelledby="custom-heading">
    <div class="section-heading-row">
        <div>
            <p class="eyebrow">Made just for you</p>
            <h2 id="custom-heading">How custom orders work</h2>
        </div>
        <a class="text-link" href="<?= e(url('customize.php')) ?>">Start a request</a>
    </div>
    <ol class="feature-grid steps">
        <li class="card"><span class="feature-number">01</span><h3>Tell us your idea</h3><p>Describe the piece, colours and size, and add an inspiration photo if you have one.</p></li>
        <li class="card"><span class="feature-number">02</span><h3>Get a quote</h3><p>We reply with a price and timeline. You can see the reply any time under My orders.</p></li>
        <li class="card"><span class="feature-number">03</span><h3>We make it by hand</h3><p>Once you accept, your piece is made, then posted or ready for pickup in Canberra.</p></li>
    </ol>
</section>
<?php page_footer(); ?>
