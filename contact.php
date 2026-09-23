<?php
require_once __DIR__ . '/config/functions.php';

page_header('Contact');
?>
<section class="page-heading">
    <p class="eyebrow">We are here to help</p>
    <h1>Contact Norbooz Crochet</h1>
    <p>Have a question about an item, an order or a custom request? Get in touch and we will help you with the next step.</p>
</section>

<section class="contact-grid" aria-label="Contact information">
    <article class="card contact-card">
        <span class="feature-number">01</span>
        <h2>Email us</h2>
        <p>For product questions, order updates and general enquiries:</p>
        <a class="contact-link" href="mailto:norboozcrochet25@gmail.com">norboozcrochet25@gmail.com</a>
    </article>
    <article class="card contact-card">
        <span class="feature-number">02</span>
        <h2>Order support</h2>
        <p>Include your order details when asking about delivery, stock or a change to your request.</p>
        <a class="contact-link" href="<?= e(url('my_orders.php')) ?>">View my orders</a>
    </article>
    <article class="card contact-card">
        <span class="feature-number">03</span>
        <h2>Custom requests</h2>
        <p>Tell us the product type, colours and preferred size. We will confirm what is possible before you order.</p>
        <a class="contact-link" href="mailto:norboozcrochet25@gmail.com?subject=Custom%20request">Ask about a custom piece</a>
    </article>
</section>

<section class="contact-note" aria-labelledby="support-heading">
    <p class="eyebrow">Support hours</p>
    <h2 id="support-heading">Monday to Friday, 9:00 am to 5:00 pm</h2>
    <p>We usually reply within one business day. For the quickest help, use the same email address connected to your account.</p>
</section>
<?php page_footer(); ?>
