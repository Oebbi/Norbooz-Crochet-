# Changelog

## Version 3.2 (September 2026)

### Accessibility (WCAG 2.2 AA) and all screen sizes
- Checked every page at 320, 360, 375, 414, 600, 740, 768, 900, 1024, 1180, 1440 and 1920 px wide: no sideways scrolling
- Strong 3 px focus ring on every link, button and form field; focused items are never hidden under the sticky header
- All tap targets are at least 24 x 24 px; form fields 44 px tall; bigger checkboxes and radio buttons
- Mobile menu: Escape closes it and returns focus; it resets when the window is widened
- Header switches to the menu button below 1120 px, so logged-in menus never overflow on tablets
- Admin tables turn into one card per row on phones; scrollable tables can be scrolled with the keyboard
- Filters no longer reload the page as soon as a dropdown changes (WCAG 3.2.2); press Apply/Filter
- Cart count read out as "Cart, 2 items"; "Add to cart" buttons keep their visible words in the spoken name (WCAG 2.5.3)
- Duplicate photo links removed from the keyboard/screen-reader order; correct heading order on the shop page
- Works with 200% text size, increased text spacing, reduced motion, high-contrast mode and Windows forced colours

## Version 3.1 (September 2026)

### Added (full CRUD coverage)
- Customers can **edit or withdraw (delete)** their own custom request while it is still New (`request_edit.php`)
- Admin can **edit contact and delivery details** on an open order, and **delete cancelled orders**
- Admin can **delete finished custom requests** (new, declined or completed) and **delete customers** (de-identified, APP 11.2)
- 18 more end-to-end tests (107 in total)

### Fixed
- Session lost when the site folder name contains a space (for example `norbooz_crochet 2`), which made every form fail with "Request expired"

## Version 3.0 (September 2026)

### Fixed
- **Product photos not showing.** Product images were 10-23 MB PNG files (294 MB in total) with inconsistent
  names such as `Bunny .png` and `boquet.png.png`, and older databases still pointed at demo SVG paths.
  Photos are now 1200 px JPGs with 600 px thumbnails (6 MB in total), clearly named, and the site
  automatically finds the right photo for old paths. `upgrade.php` rewrites old paths in existing databases.
- Product names and descriptions corrected to match their photos (for example the "Tulip Bouquet" is red roses).
- The Shop link opened the custom-request form; there was no product catalogue.
- Category links on the home page ignored the category.
- Password-reset emails contained a relative link that did not work from an email client.
- `CREATE TABLE` statements ran on every page load, which failed with the restricted database account.
- Login lockout was stored in the browser session and could be bypassed by clearing cookies.
- Demo administrator password was printed on the public login page.

### Added
- Shop catalogue with search, family filter, sorting, in-stock filter and pagination
- Product detail pages with related products
- Session cart and two-step checkout with pickup or postage, free postage threshold, duplicate-order protection
- Order detail page with progress steps, history and customer cancellation (pending orders only)
- Status workflow rules (for example Completed orders are final) and a full audit history
- Custom requests: quotes and replies visible to customers, private photo storage
- Account page: edit details, change password, download my data, delete account
- Admin: dashboard, order search/filter/CSV export, product photo upload with resizing, quick stock editing,
  delete unused products, customer list, email notifications
- Privacy policy, About & FAQ, mobile navigation, favicon
- Email notifications (logged to `storage/mail.log` on XAMPP, sent with `mail()` on a live host)
- `config/local.php` for live settings, `install_check.php` improvements, `upgrade.php`
- 31 unit tests and 89 end-to-end tests

### Security
- Database-backed login throttling per email and per IP
- Stronger password rule, session idle timeout, strict session mode, open-redirect protection
- Upload validation by file content and re-encoding; upload folders cannot run scripts
- Row locking in a consistent order, conditional stock updates and money calculated in cents

## Version 2.0
- Marketplace-style redesign, eight product families, custom request form.

## Version 1.0
- Initial PHP/MySQL implementation: registration, login, ordering, admin product and order management.
