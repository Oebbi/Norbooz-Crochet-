-- Least-privilege database account for the live website.
-- Run as a MySQL/MariaDB administrator (phpMyAdmin > SQL), after changing the password below.
-- Then put these details in config/local.php (see config/local.example.php).
-- The website only needs to read and change rows, so it is NOT given CREATE, DROP or ALTER rights.

CREATE USER IF NOT EXISTS 'norbooz_app'@'localhost' IDENTIFIED BY 'ChangeThisToALongRandomPassword!';
GRANT SELECT, INSERT, UPDATE, DELETE ON norbooz_crochet_db.* TO 'norbooz_app'@'localhost';
FLUSH PRIVILEGES;
