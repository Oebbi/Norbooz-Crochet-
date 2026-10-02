# Norbooz Crochet

An online shop for Norbooz Crochet, a small handmade crochet business in Canberra.
Customers browse the catalogue, order ready-made pieces, request custom pieces and track their orders.
The owner manages products, stock, orders and custom-request quotes from an admin panel.

Built with HTML5, CSS3, JavaScript, PHP 8.1+ and MySQL/MariaDB (ICT312 Advanced Web Information Systems, Assessment 2).

## Quick start (XAMPP on Windows or macOS)

1. Unzip the ZIP and move the whole `norbooz_crochet` folder into `C:\xampp\htdocs` (macOS: `/Applications/XAMPP/htdocs`).
   If an older `norbooz_crochet` folder is already there, rename or delete it first; do not merge folders.
   The folder name must have no spaces.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open http://localhost/phpmyadmin, choose **Import**, and import `database/norbooz_crochet_db.sql`.
4. Open http://localhost/norbooz_crochet/install_check.php. Every required check should say **PASS**.
5. Open http://localhost/norbooz_crochet/

Administrator: `admin@norboozcrochet.local`, temporary password `Admin@12345`.
The admin panel shows a warning until this password is changed on the Account page.

**Already have a version 2 or 3 database with real data?** Copy the new files, back up the database,
then open http://localhost/norbooz_crochet/upgrade.php instead of re-importing.

The full steps, including going live on a web host, are in the *Installation Manual*.

## Features

| Customers | Shop owner (admin) |
|---|---|
| Shop with search, family filter, sort, in-stock filter and pages | Dashboard: new orders, requests to answer, low stock, monthly sales, best sellers |
| Product pages with photos and related items | Orders: filter, search, CSV export, status workflow with audit history and customer emails |
| Cart and checkout with pickup or post; hosted Stripe/PayPal payment when configured | Products: add/edit/hide/delete, photo upload with automatic resizing, quick stock edit |
| Order tracking with progress steps; cancel while pending | Custom requests: view brief and photo, send quote and reply |
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
- No card data stored; online payments are handled by Stripe or PayPal, with PayID/bank transfer as a manual option

## Project structure

```
index.php, products.php, product.php      Home, shop, product page
cart.php, checkout.php, order_detail.php  Ordering
payment_*.php, config/payments.php        Hosted payment callbacks and webhooks
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
php tests/smoke_test.php                                             # 31 unit tests, no database needed
php tests/integration_test.php http://localhost/norbooz_crochet     # 107 end-to-end tests (uses the database, cleans up after itself)
```

On Windows use `C:\xampp\php\php.exe` instead of `php`. The latest results are saved to `tests/last_results.md`.

## Configuration for a live site

Copy `config/local.example.php` to `config/local.php` and set the database account, `APP_URL`,
`MAIL_MODE = 'mail'` and the payment instructions. Before using online payments:

1. Back up the database and run `upgrade.php` so payment state columns are added.
2. Configure Stripe test keys and PayPal sandbox credentials in the untracked `config/local.php`.
3. Register `payment_webhook.php?provider=stripe` for `checkout.session.completed`, `checkout.session.async_payment_succeeded`, `checkout.session.async_payment_failed` and `checkout.session.expired`; register `payment_webhook.php?provider=paypal` for `PAYMENT.CAPTURE.COMPLETED` and `CHECKOUT.ORDER.VOIDED`.
4. Enable Afterpay in Stripe if approved. Apple Pay is presented automatically by hosted Checkout on supported devices. Test end-to-end in sandbox before switching credentials and PayPal mode to live.

Stripe uses hosted Checkout for cards, Apple Pay and Afterpay. PayPal uses its hosted approval flow. Neither provider secret belongs in the repository or in a message.

When the shop is opened from localhost, checkout instead shows sample Apple Pay, Afterpay and PayPal choices. These open `payment_demo.php` and do not contact a provider, create an order, charge money or change stock.
