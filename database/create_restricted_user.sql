-- OPTIONAL production-style database account for the application.
-- Run as a MySQL administrator, then update config/config.php to use these credentials.
-- Change the example password before use.

CREATE USER IF NOT EXISTS 'norbooz_app'@'localhost' IDENTIFIED BY 'ChangeThisBeforeUse!';
CREATE USER IF NOT EXISTS 'norbooz_app'@'127.0.0.1' IDENTIFIED BY 'ChangeThisBeforeUse!';

GRANT SELECT, INSERT, UPDATE, DELETE ON norbooz_crochet_db.* TO 'norbooz_app'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON norbooz_crochet_db.* TO 'norbooz_app'@'127.0.0.1';

FLUSH PRIVILEGES;
