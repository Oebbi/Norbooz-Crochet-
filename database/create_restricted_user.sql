-- OPTIONAL production-style database account for the application.
-- Run as a MySQL administrator, then update config/config.php to use these credentials.
CREATE USER IF NOT EXISTS 'norbooz_app'@'localhost' IDENTIFIED BY 'ChangeThisBeforeUse!';
GRANT SELECT, INSERT, UPDATE, DELETE ON norbooz_crochet_db.* TO 'norbooz_app'@'localhost';
FLUSH PRIVILEGES;
