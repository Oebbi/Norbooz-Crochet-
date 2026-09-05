NORBOOZ CROCHET WEB INFORMATION SYSTEM - ICT312 ASSIGNMENT 2

TECHNOLOGY
HTML5, CSS3, JavaScript, PHP and MySQL. Designed for XAMPP on Windows.

QUICK INSTALL
1. Copy the norbooz_crochet folder to C:\xampp\htdocs\norbooz_crochet
2. Start Apache and MySQL in XAMPP.
3. Open phpMyAdmin and import database/norbooz_crochet_db.sql
4. If your MySQL user/password is not root/blank, edit config/config.php.
5. Open http://localhost/norbooz_crochet/install_check.php and confirm PASS.
6. Open http://localhost/norbooz_crochet/

DEMO ADMIN LOGIN
Email: admin@norboozcrochet.local
Password: Admin@12345
This credential is for assessment demonstration only.

CUSTOMER FLOW
Register -> Login -> Products -> Place Order -> My Orders -> Logout.

ADMIN FLOW
Login with admin credential -> Admin Panel -> add/update products and stock -> update order status.

SECURITY CONTROLS
Password hashing, PDO prepared statements, server-side validation, role checks, secure session settings,
CSRF tokens, output encoding and prototype login-attempt lockout. HTTPS is required for any real deployment.

TESTS
Run `php tests/smoke_test.php` from the project folder for helper-function smoke tests.
Run `php -l filename.php` for syntax checks. Final end-to-end database testing must be completed in XAMPP.
