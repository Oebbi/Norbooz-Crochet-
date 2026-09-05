<?php
require_once __DIR__ . '/config/functions.php';
page_header('Home');
?>
<section class="hero">
  <div>
    <p class="eyebrow">Handmade with care</p>
    <h1>Simple crochet gifts, organised in one place.</h1>
    <p>Browse handmade plushies, beanies, blankets, bags and customised gifts. Create an account to place an order and follow its progress.</p>
    <div class="actions">
      <a class="button" href="<?= e(url('products.php')) ?>">Browse products</a>
      <?php if (!current_user()): ?><a class="button button-secondary" href="<?= e(url('register.php')) ?>">Create account</a><?php endif; ?>
    </div>
  </div>
  <img src="<?= e(url('assets/images/hero.svg')) ?>" alt="Illustration of a crochet basket with yarn and handmade items">
</section>
<section aria-labelledby="why-heading" class="section">
  <h2 id="why-heading">Why use the Norbooz Crochet system?</h2>
  <div class="feature-grid">
    <article class="card"><h3>Clear product information</h3><p>See product names, categories, prices and stock availability before ordering.</p></article>
    <article class="card"><h3>Order tracking</h3><p>Logged-in customers can view whether an order is pending, in progress, ready or completed.</p></article>
    <article class="card"><h3>Protected administration</h3><p>The owner can manage products, stock and order status from a role-protected administrator area.</p></article>
  </div>
</section>
<?php page_footer(); ?>
