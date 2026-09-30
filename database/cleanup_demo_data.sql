-- Removes TEST customer data before the shop goes live (for example after the assessment demonstration).
-- Keeps the administrator account and the product catalogue.
-- Make a backup first (phpMyAdmin > Export). Then import this file.
USE norbooz_crochet_db;
START TRANSACTION;
DELETE h FROM order_status_history h JOIN orders o ON o.order_id = h.order_id JOIN users u ON u.user_id = o.user_id WHERE u.role = 'customer';
DELETE oi FROM order_items oi JOIN orders o ON o.order_id = oi.order_id JOIN users u ON u.user_id = o.user_id WHERE u.role = 'customer';
DELETE o FROM orders o JOIN users u ON u.user_id = o.user_id WHERE u.role = 'customer';
DELETE cr FROM custom_requests cr JOIN users u ON u.user_id = cr.user_id WHERE u.role = 'customer';
DELETE FROM password_resets;
DELETE FROM login_attempts;
DELETE FROM users WHERE role = 'customer';
COMMIT;
-- Stock levels are NOT reset automatically. Check stock in Admin > Products after running this.
-- Also delete the files in storage/uploads/ (customer inspiration photos) and storage/mail.log.
