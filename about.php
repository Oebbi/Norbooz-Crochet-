<?php
require_once __DIR__ . '/config/functions.php';
require_once __DIR__ . '/config/payments.php';
$paymentOptions = online_payment_options();
page_header('About & FAQ', 'About Norbooz Crochet: handmade crochet pieces from ' . SHOP_LOCATION . ', delivery, payment and care FAQ.');
?>
<section class="page-heading">
    <p class="eyebrow">Our story</p>
    <h1>About Norbooz Crochet</h1>
</section>

<section class="about-layout">
    <div class="prose">
        <p>Norbooz Crochet is a small handmade business based in <?= e(SHOP_LOCATION) ?>. Every plushie, bag, throw and flower is crocheted by hand, one stitch at a time, in small batches.</p>
        <p>We love making pieces that feel personal: a gift for a friend, a keychain that matches your bag, or a throw in the colours of your living room. If you can imagine it, we can usually make it. Send us a <a href="<?= e(url('customize.php')) ?>">custom request</a>.</p>
    </div>
    <img class="about-photo" src="<?= e(url(category_image('Plushies'))) ?>" alt="A family of crochet penguins" width="600" height="600" loading="lazy">
</section>

<section class="section" aria-labelledby="faq-heading">
    <h2 id="faq-heading">Frequently asked questions</h2>
    <div class="faq">
        <details open>
            <summary>How do I pay?</summary>
            <p><?= count($paymentOptions) > 1 ? 'Choose a configured secure online payment method at checkout, or select PayID or bank transfer. Card details are entered with the payment provider and are never stored on this website.' : 'No payment is taken on the website. After you place an order we email you within one business day to confirm it and send PayID or bank transfer details. Your items are reserved for 3 days while we wait for payment.' ?></p>
        </details>
        <details>
            <summary>How much is delivery?</summary>
            <p><?= PICKUP_ENABLED ? 'Pickup in ' . e(SHOP_LOCATION) . ' is free. ' : '' ?>Australia Post delivery is <?= money(POSTAGE_FEE) ?> per order<?= FREE_POSTAGE_OVER > 0 ? ', and free for orders over ' . money(FREE_POSTAGE_OVER) : '' ?>. Ready-made items are usually posted within 3 business days of payment.</p>
        </details>
        <details>
            <summary>How long do custom orders take?</summary>
            <p>It depends on the size of the piece: small keychains take a few days, throws can take several weeks. We give you a timeline with the quote, and nothing is made until you accept it.</p>
        </details>
        <details>
            <summary>Can I cancel an order?</summary>
            <p>Manual-payment orders can be cancelled while they are still <em>Pending</em> from <a href="<?= e(url('my_orders.php')) ?>">My orders</a>. For an order paid online, please email us so we can safely handle any refund. Once we start making an order, please contact us.</p>
        </details>
        <details>
            <summary>How do I care for crochet items?</summary>
            <p>Hand wash in cool water with gentle detergent, squeeze gently (do not wring) and dry flat in the shade. Plushies with safety eyes are not recommended for children under 3.</p>
        </details>
    </div>
</section>
<?php page_footer(); ?>
