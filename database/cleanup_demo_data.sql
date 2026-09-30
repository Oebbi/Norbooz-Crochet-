-- Data-retention cleanup for the academic prototype.
-- Make a verified backup first. This removes customer accounts and their orders but keeps the demo administrator and product catalogue.
USE norbooz_crochet_db;
START TRANSACTION;
DELETE oi FROM order_items oi JOIN orders o ON o.order_id = oi.order_id JOIN users u ON u.user_id = o.user_id WHERE u.role = 'customer';
DELETE o FROM orders o JOIN users u ON u.user_id = o.user_id WHERE u.role = 'customer';
DELETE FROM users WHERE role = 'customer';
COMMIT;
