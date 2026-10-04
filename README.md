# Norbooz Crochet

An online shop for Norbooz Crochet, a small handmade crochet business in Canberra.
Customers browse the catalogue, order ready-made pieces, request custom pieces and track their orders.
The owner manages products, stock, orders and custom-request quotes from an admin panel.

Built with HTML5, CSS3, JavaScript, PHP 8.1+ and MySQL/MariaDB (ICT312 Advanced Web Information Systems, Assessment 2).

## Quick start (XAMPP on Windows or macOS)

1. Download or clone this repository (or unzip the ZIP) into `C:\xampp\htdocs` (macOS: `/Applications/XAMPP/htdocs`).
   For the URLs below, name the folder `norbooz_crochet` with no spaces. If you keep another folder name,
   replace `norbooz_crochet` in each URL with that name. Do not merge it with an older copy.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open http://localhost/phpmyadmin, choose **Import**, and import `database/norbooz_crochet_db.sql`.
4. Open http://localhost/norbooz_crochet/install_check.php. Every required check should say **PASS**.
5. Open http://localhost/norbooz_crochet/

Administrator: `admin@norboozcrochet.local`, temporary password `Admin@12345`.
The admin panel shows a warning until this password is changed on the Account page.

**Already have an older database with real data (schema version 2 to 6)?** Copy the new files, back up the database,
then open http://localhost/norbooz_crochet/upgrade.php instead of re-importing.

The full steps, including going live on a web host, are in the *Installation Manual*.

## Features

| Customers | Shop owner (admin) |
|---|---|
| Shop with search, family filter, sort, in-stock filter and pages | Dashboard: new orders, requests to answer, low stock, monthly sales, best sellers |
| Product pages with photos, an About this item bullet list, product specifications and related items | Orders: filter, search, CSV export, status workflow with audit history and customer emails |
| Cart and checkout with pickup or post; pay by PayID/bank transfer or online (PayPal when configured; a payment simulator on localhost) | Products: add/edit/hide/delete, photo upload with automatic resizing, quick stock edit |
| Order tracking with progress steps; pay later or cancel while unpaid | Custom requests: view brief and photo, send quote and reply |
| Custom-request form with private photo upload; edit or withdraw while new; see quotes | Customers list with order history |
| Account page: edit details, change password, download data, delete account | Installation check and database upgrade tools |

## Security and privacy

- Passwords hashed with bcrypt (`password_hash`), minimum 10 characters with a letter and a number
- PDO prepared statements for every query that uses input; emulated prepares off
- CSRF token on every form that changes data; logout is POST-only
- Output encoding with `htmlspecialchars` everywhere (XSS)
- Role checks on every admin page; customers can only see their own orders and photos (IDOR protection)
- Login throttling stored in the database: 5 failures per email or 20 per IP in 15 minutes
- Session: strict mode, HttpOnly, SameSite=Lax, Secure on HTTPS, ID regenerated on login, 30-minute idle timeout
- Headers: Content-Security-Policy, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy, HSTS on HTTPS
- Uploads: type checked by content, size limited, re-encoded (strips hidden data), random names; customer photos stored outside the web folder
- Order safety: row locking, server-side prices and totals in cents, stock check inside a transaction, duplicate-submit token
- Privacy (Australian Privacy Principles): privacy policy, data download (APP 12), correction (APP 13), account deletion by de-identification (APP 11.2), marketing opt-in only
- No card data stored; PayPal handles hosted online payments, with PayID/bank transfer as a manual option
- Online payments: amount, currency and order checked on the server before an order is marked paid; PayPal webhooks are signature-verified;
  the cancel link carries a one-time token; unpaid orders expire and release their stock; refunds are recorded with an audit entry

## Project structure

```
index.php, products.php, product.php      Home, shop, product page
cart.php, checkout.php, order_detail.php  Ordering
payment_*.php, config/payments.php        Online payments: PayPal return, cancel, webhook; localhost simulator
my_orders.php, customize.php, account.php Customer area
admin*.php                                Admin panel
config/config.php                         Settings (override in config/local.php, not committed)
config/functions.php                      Shared helpers: security, cart, images, email, layout
partials/product_card.php                 Product card used on several pages
assets/css, assets/js, assets/images      Styles, scripts, optimised product photos
database/                                 Full schema + sample data, restricted DB user, test-data cleanup
storage/                                  Private uploads and local email log (blocked from the web)
tests/                                    Unit tests and end-to-end tests
upgrade.php, install_check.php            Upgrade and diagnostics (local machine or admin only)
```

## Accessibility

The site targets **WCAG 2.2 level AA** and works from small phones (320 px) to large desktops.
Every page was checked at 12 screen widths for sideways scrolling, text contrast, labels and names,
heading order, landmarks, 24 px touch targets, visible keyboard focus, 200% text size and text spacing.
It also respects reduced motion, high-contrast preferences and Windows forced colours, and all
features work with the keyboard alone (a "Skip to main content" link is the first Tab stop).

## Tests

```bash
php tests/smoke_test.php                                             # 43 unit tests, no database needed
php tests/integration_test.php http://localhost/norbooz_crochet     # 147 end-to-end tests (uses the database, cleans up after itself)
```

On Windows use `C:\xampp\php\php.exe` instead of `php`. The latest results are saved to `tests/last_results.md`.

## Online payments

| Option | When it is offered | What happens |
|---|---|---|
| PayID or bank transfer | Always | The order is placed; the owner emails payment details and moves the order on once paid. |
| PayPal | Only when `PAYPAL_CLIENT_ID`, `PAYPAL_CLIENT_SECRET` and `PAYPAL_WEBHOOK_ID` are set in `config/local.php` | The order is created as **Awaiting payment** with its stock reserved, and the customer is sent to PayPal. The order becomes **Paid** only after the server confirms the capture (amount, currency and order must match). A signature-verified webhook is the backup if the customer closes the browser. |
| Payment simulator ("Apple Pay / Afterpay / PayPal sample") | Only when the site is opened on `localhost` | Runs the same order and payment-state code as PayPal, but on a local page with **Approve** and **Cancel** buttons. No provider is contacted and no money is taken. It lets the full payment life cycle be demonstrated and tested on XAMPP without PayPal keys. It is never offered on a public address. |

An online order that is still unpaid can be paid later or cancelled by the customer, cancelled by the owner, and is
cancelled automatically after `PAYMENT_EXPIRY_HOURS` (default 24) so the stock returns. A paid online order cannot be
cancelled directly: the owner refunds it in PayPal, then uses **Record a refund** on the order page.

Card checkout through Stripe was trialled in version 3.3 and removed in 3.4; the `card` value in the database only keeps old test orders readable.

## Configuration for a live site

Copy `config/local.example.php` to `config/local.php` and set the database account, `APP_URL`,
`MAIL_MODE = 'mail'` and the payment instructions. To turn on PayPal:

1. Back up the database and run `upgrade.php` (schema version 7).
2. Create a REST app in the PayPal developer dashboard and put its sandbox credentials in the untracked `config/local.php`.
3. Register the webhook `https://your-domain/payment_webhook.php?provider=paypal` for `PAYMENT.CAPTURE.COMPLETED` and
   `CHECKOUT.ORDER.VOIDED`, and copy its ID into `PAYPAL_WEBHOOK_ID`.
4. Test a full purchase, a cancelled payment and a refund in sandbox mode, then switch the credentials and `PAYPAL_MODE` to `live`.

Never put provider secrets in the repository or in a message.
