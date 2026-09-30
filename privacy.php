<?php
require_once __DIR__ . '/config/functions.php';
page_header('Privacy policy', 'How Norbooz Crochet collects, uses, stores and protects your personal information.');
?>
<section class="page-heading">
    <p class="eyebrow">Last updated <?= e(date('F Y', (int)@filemtime(__FILE__) ?: time())) ?></p>
    <h1>Privacy policy</h1>
    <p>This policy explains how Norbooz Crochet handles personal information, following the Australian Privacy Principles (APPs) in the <em>Privacy Act 1988</em> (Cth).</p>
</section>

<section class="prose">
    <h2>What we collect and why</h2>
    <ul>
        <li><strong>Name, email and password</strong> to create your account. Passwords are stored only as a one-way bcrypt hash; we cannot see them.</li>
        <li><strong>Phone number and postal address</strong> to contact you about an order and deliver it.</li>
        <li><strong>Order and custom-request details</strong>, including any inspiration photo you choose to upload, so we can make your item.</li>
        <li><strong>Sign-in attempts</strong> (email address, IP address and time) for up to 24 hours, to protect accounts from password-guessing attacks.</li>
    </ul>
    <p>We only collect what we need (APP 3). We do <strong>not</strong> collect or store credit card or bank card details on this website. If you choose online payment, Stripe or PayPal processes the payment under its own privacy policy.</p>

    <h2>How we use it</h2>
    <p>Your information is used only to process orders, reply to custom requests and send messages about your order. We send product news only if you opt in, and you can opt out at any time from your account page (APP 7). We never sell your information. We share delivery details with Australia Post and payment details with the provider you select at checkout (APP 6).</p>

    <h2>How we protect it</h2>
    <ul>
        <li>Pages are served over HTTPS on the live site, and session cookies are HttpOnly and SameSite.</li>
        <li>Database access uses prepared statements and a restricted database account.</li>
        <li>Inspiration photos are stored outside the public website folder and are only visible to you and the maker.</li>
        <li>Accounts are locked for a short time after repeated failed sign-ins, and you are signed out after <?= (int)SESSION_IDLE_MINUTES ?> minutes of inactivity.</li>
    </ul>

    <h2>Access, correction and deletion</h2>
    <p>You can view and correct your details at any time on your <a href="<?= e(url('account.php')) ?>">account page</a> (APP 13), download a copy of all your data (APP 12), and delete your account. When you delete your account your contact details are removed; order totals are kept without your details because businesses must keep financial records.</p>

    <h2>Contact and complaints</h2>
    <p>Email <a href="mailto:<?= e(SHOP_EMAIL) ?>"><?= e(SHOP_EMAIL) ?></a> with any privacy question or complaint and we will reply within 30 days. If you are not satisfied, you can contact the Office of the Australian Information Commissioner (oaic.gov.au).</p>
</section>
<?php page_footer(); ?>
