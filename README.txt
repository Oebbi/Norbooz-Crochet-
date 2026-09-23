NORBOOZ CROCHET WEB INFORMATION SYSTEM
ICT312 - Advanced Web Information Systems - Assignment 2

PURPOSE
This folder contains the complete implementation of the revised Norbooz Crochet proposal.
The system uses HTML5, CSS3, JavaScript, PHP and MySQL and is intended to run in XAMPP.

CORE PAGES
1. index.php       - Home
2. products.php    - Product catalogue and stock availability
3. register.php    - Customer registration
4. login.php       - Customer/administrator login
5. order.php       - Place an order
6. my_orders.php   - Customer order history and status
7. admin.php       - Product, stock and order administration

Additional support files include logout.php, install_check.php, configuration helpers,
SQL database scripts, CSS, JavaScript, images, tests and Git version-control history.

QUICK INSTALL IN XAMPP (WINDOWS)
1. Extract/copy the folder named norbooz_crochet_v2 into:
      C:\xampp\htdocs\norbooz_crochet_v2
2. Open XAMPP Control Panel and start Apache and MySQL.
3. Open phpMyAdmin in the browser:
      http://localhost/phpmyadmin
4. Select Import and import:
      database/norbooz_crochet_db.sql
5. The default XAMPP database configuration is already set in config/config.php:
      host: 127.0.0.1
      database: norbooz_crochet_db
      user: root
      password: blank
   If your XAMPP MySQL credentials are different, edit config/config.php.
6. Open:
      http://localhost/norbooz_crochet_v2/install_check.php
   Every check should show PASS.
7. Open the working system:
      http://localhost/norbooz_crochet_v2/

DEMO ADMINISTRATOR
Email:    admin@norboozcrochet.local
Password: Admin@12345
This account is included only for assessment demonstration. Change it before any real deployment.

CUSTOMER TEST FLOW
1. Register a new customer account.
2. Log in.
3. Browse Products.
4. Choose quantities on Place Order and submit.
5. Open My Orders and confirm the order details/status.
6. Log out.

ADMIN TEST FLOW
1. Log in using the demo administrator account.
2. Open Admin Panel.
3. Add a product.
4. Edit product name/category/description/price/stock/image/active status.
5. Update an order from Pending to In Progress, Ready and Completed.
6. Test Cancelled: stock is returned when an active order is cancelled.
7. Test re-opening a cancelled order: the system reserves stock again only when enough stock exists.

SECURITY / PRIVACY CONTROLS IMPLEMENTED
- password_hash() and password_verify() for passwords
- PDO prepared statements for queries containing user data
- server-side validation of registration, order and admin inputs
- HTML output encoding to reduce XSS risk
- CSRF tokens on state-changing POST requests, including logout
- authentication and role checks for protected pages
- session ID regeneration after successful login
- HttpOnly, SameSite and HTTPS-aware Secure session cookies
- security response headers including CSP, frame protection and nosniff
- five-attempt / five-minute prototype login protection in the browser session
- local-only product image paths
- no online payment-card collection
- fictional demonstration data
- SQL/demo cleanup procedure in database/cleanup_demo_data.sql
- .htaccess rules to restrict direct access to config/database/test files in Apache

HTTPS
Local XAMPP normally runs over HTTP. For a real deployment, install a valid TLS certificate and
serve the site over HTTPS. The application enables HSTS automatically when it is reached through HTTPS.

VERSION CONTROL
The project includes a Git repository and GIT_HISTORY.txt. To inspect the history:
      git log --oneline
Version-control use is part of the ICT312 assessment requirement.

TESTS
PHP syntax test (PowerShell or Command Prompt from the project directory):
      php -l index.php
      php -l products.php
      php -l register.php
      php -l login.php
      php -l order.php
      php -l my_orders.php
      php -l admin.php
      php -l logout.php
      php -l install_check.php

Helper smoke tests:
      php tests/smoke_test.php

IMPORTANT BEFORE SUBMISSION
The marking computer must be able to run the whole system. Test the ZIP on a clean XAMPP setup,
import the SQL file, run install_check.php, and manually complete both customer and admin flows.
