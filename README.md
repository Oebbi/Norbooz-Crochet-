# Norbooz Crochet Business

A PHP and MySQL storefront for browsing handmade crochet products, placing orders, and managing products, stock, and order status.

## Run locally with XAMPP

1. Place this folder in `C:\xampp\htdocs\norbooz_crochet`.
2. Start Apache and MySQL in the XAMPP Control Panel.
3. Import [`database/norbooz_crochet_db.sql`](database/norbooz_crochet_db.sql) through phpMyAdmin.
4. Check the database settings in [`config/config.php`](config/config.php).
5. Open `http://localhost/norbooz_crochet/install_check.php` and confirm the checks pass.
6. Visit `http://localhost/norbooz_crochet/` to use the store.

## Checks

Run the project smoke tests from this directory:

```powershell
php tests/smoke_test.php
```

See [`README.txt`](README.txt) for the customer and administrator test flows and additional project details.
