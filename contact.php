<?php
require_once __DIR__ . '/config/functions.php';
page_header('Contact', 'Contact Norbooz Crochet about products, orders and custom requests.');
?>
<section class="page-heading">
    <p class="eyebrow">We are here to help</p>
    <h1>Contact Norbooz Crochet</h1>
    <p>Questions about an item, an order or a custom piece? We usually reply within one business day.</p>
</section>

<section class="feature-grid" aria-label="Ways to contact us">
    <article class="card">
        <span class="feature-number">01</span>
        <h2>Email us</h2>
        <p>Product questions, order updates and general enquiries.</p>
        <a class="contact-link" href="mailto:<?= e(SHOP_EMAIL) ?>"><?= e(SHOP_EMAIL) ?></a>
    </article>
    <article class="card">
        <span class="feature-number">02</span>
        <h2>Order support</h2>
        <p>Check progress, see delivery details or cancel a pending order.</p>
        <a class="contact-link" href="<?= e(url('my_orders.php')) ?>">View my orders</a>
    </article>
    <article class="card">
        <span class="feature-number">03</span>
        <h2>Custom requests</h2>
        <p>Tell us the piece, colours and size and we will send you a quote.</p>
        <a class="contact-link" href="<?= e(url('customize.php')) ?>">Start a custom request</a>
    </article>
</section>

<section class="callout" aria-labelledby="support-heading">
    <p class="eyebrow">Support hours</p>
    <h2 id="support-heading">Monday to Friday, 9:00 am to 5:00 pm (AEST)</h2>
    <p>For the quickest help, email from the address linked to your account and include your order number. Local pickup is in <?= e(SHOP_LOCATION) ?> by arrangement.</p>
</section>
<?php page_footer(); ?>
