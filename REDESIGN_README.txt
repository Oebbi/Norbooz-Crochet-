NORBOOZ CROCHET - MARKETPLACE-STYLE REDESIGN
============================================

PURPOSE
This redesign gives the existing ICT312 system a professional handmade-marketplace appearance while keeping the required PHP/MySQL functions and security controls.

PRODUCT FAMILIES
1. Plushies
2. Throws
3. Bags
4. Hats
5. Keychains
6. Flowers
7. Wall Hangings
8. Coasters

SAFEST WAY TO USE THIS UPDATE
1. Back up C:\xampp\htdocs\norbooz_crochet_v2 first.
2. If your existing website and database already work, use the FRONTEND/PRESENTATION PATCH ZIP supplied with this project rather than replacing config.php or your database.
3. Keep your current config/config.php, especially if your active database is named norbooz_crochet_fresh.
4. After copying the redesigned files, restart Apache if necessary and press Ctrl+F5 in the browser.

REAL PHOTO SETUP
Homepage hero photo:
- Replace assets/images/hero-photo.jpg with your own landscape crochet photo.
- KEEP THE SAME FILENAME hero-photo.jpg and no code change is needed.
- Recommended shape: landscape 4:3 or similar, at least 1200 px wide.

Product photos:
- Put your real product photos in assets/images/products/
- Use simple names, for example:
  assets/images/products/capybara.jpg
  assets/images/products/granny-tote.jpg
  assets/images/products/flower-keychain.jpg
- Log in as administrator and put that exact path into the product's Image path field.
- JPG, JPEG, PNG, WEBP, GIF and SVG local images are accepted.

WHERE TO EDIT THE DESIGN
- Home page content: index.php
- Product catalogue layout/content: products.php
- Site header, search, category navigation and footer: config/functions.php
- Colours, spacing, product cards and responsive layout: assets/css/styles.css
- Order total browser behaviour: assets/js/app.js
- Product categories used by the system: product_categories() in config/functions.php
- Admin product management interface: admin.php

DO NOT REMOVE THESE ASSESSMENT FUNCTIONS
- Registration and secure login
- PHP password hashing
- PDO prepared statements
- CSRF protection
- Sessions and role checks
- Login-attempt protection
- Product/stock administration
- Customer orders and My Orders
- Server-side stock and total validation
- Order status management
- Local database using MySQL
- Git version control

DATABASE NOTE
The redesigned interface does not require a database schema change. Existing old labels such as Beanies and Blankets are automatically displayed as Hats and Throws. An optional_category_cleanup.sql file is included if you want to update old category text in the database.

If you perform a completely fresh installation, database/norbooz_crochet_db.sql contains eight demonstration products, one for each final product family.
