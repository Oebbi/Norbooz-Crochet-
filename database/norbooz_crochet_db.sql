-- Norbooz Crochet Web Information System - full database (version 4)
-- Fresh install: import this file in phpMyAdmin (Import tab) BEFORE opening the website.
-- WARNING: this recreates every table. To keep an existing database, run upgrade.php instead (see the Installation Manual).

CREATE DATABASE IF NOT EXISTS norbooz_crochet_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE norbooz_crochet_db;

SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS order_status_history;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS custom_requests;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS=1;

-- Customers and administrators. Passwords are stored only as bcrypt hashes.
CREATE TABLE users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    address VARCHAR(255) NOT NULL,
    role ENUM('customer','admin') NOT NULL DEFAULT 'customer',
    marketing_opt_in TINYINT(1) NOT NULL DEFAULT 0,
    password_changed_at DATETIME NULL,
    is_deleted TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Single-use password reset tokens. Only a SHA-256 hash of the token is stored.
CREATE TABLE password_resets (
    reset_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- Failed/successful sign-in attempts used for brute-force protection (kept for 24 hours).
CREATE TABLE login_attempts (
    attempt_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_email_time (email, attempted_at),
    INDEX idx_login_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB;

CREATE TABLE products (
    product_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    category VARCHAR(80) NOT NULL,
    description VARCHAR(1000) NOT NULL,
    price DECIMAL(10,2) NOT NULL CHECK (price >= 0),
    stock_qty INT UNSIGNED NOT NULL DEFAULT 0,
    image_path VARCHAR(255) NOT NULL DEFAULT 'assets/images/product-placeholder.svg',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    INDEX idx_products_category (category),
    INDEX idx_products_active (is_active)
) ENGINE=InnoDB;

CREATE TABLE orders (
    order_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    order_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending','in_progress','ready','completed','cancelled') NOT NULL DEFAULT 'pending',
    subtotal_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    delivery_method ENUM('pickup','post') NOT NULL DEFAULT 'post',
    delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_status ENUM('unpaid','paid','manual','failed','refunded') NOT NULL DEFAULT 'manual',
    payment_provider ENUM('stripe','paypal','manual') NOT NULL DEFAULT 'manual',
    payment_reference VARCHAR(255) NULL,
    paid_at DATETIME NULL,
    phone VARCHAR(30) NOT NULL,
    address VARCHAR(255) NOT NULL,
    custom_note VARCHAR(500) NULL,
    updated_at DATETIME NULL,
    INDEX idx_orders_status (status),
    UNIQUE KEY uq_orders_payment_reference (payment_provider, payment_reference),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Resolves the many-to-many relationship between orders and products.
-- unit_price keeps the price paid even if the product price changes later.
CREATE TABLE order_items (
    order_item_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL,
    CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products(product_id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_quantity_positive CHECK (quantity > 0)
) ENGINE=InnoDB;

-- Audit trail: every status change, who made it and when.
CREATE TABLE order_status_history (
    history_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    old_status VARCHAR(20) NULL,
    new_status VARCHAR(20) NOT NULL,
    changed_by INT UNSIGNED NULL,
    note VARCHAR(255) NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_history_order (order_id),
    CONSTRAINT fk_history_order FOREIGN KEY (order_id) REFERENCES orders(order_id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_history_user FOREIGN KEY (changed_by) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE custom_requests (
    request_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    request_type VARCHAR(100) NOT NULL,
    title VARCHAR(120) NOT NULL,
    description TEXT NOT NULL,
    color_preferences VARCHAR(500) NOT NULL,
    size_details VARCHAR(120) NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    budget DECIMAL(10,2) NULL,
    needed_by DATE NULL,
    phone VARCHAR(30) NOT NULL,
    delivery_address VARCHAR(255) NOT NULL,
    inspiration_path VARCHAR(255) NULL,
    status ENUM('new','reviewing','quoted','accepted','declined','completed') NOT NULL DEFAULT 'new',
    quoted_price DECIMAL(10,2) NULL,
    admin_response TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL,
    CONSTRAINT fk_custom_requests_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Shop administrator account.
-- Email: admin@norboozcrochet.local   Temporary password: Admin@12345
-- The admin panel shows a warning until this password is changed (Account page).
INSERT INTO users (full_name,email,password_hash,phone,address,role) VALUES
('Norbooz Crochet Owner','admin@norboozcrochet.local','$2y$12$lzDlNIN8ysbWi70ybt0qG.G/lOyBupC4GuDhnslPEQnW51zo2u3j6','0400 000 000','Canberra ACT','admin');

INSERT INTO products (name,category,description,price,stock_qty,image_path,is_active) VALUES
('Capybara Gang Keychains','Keychains','A set of four chunky capybara keychains in bright yarn, each with a sturdy hanging loop for bags and keys.',32.00,5,'assets/images/products/capybara-keychains.jpg',1),
('Pink Bobble Throw','Throws','A soft pink and white bobble-stitch throw that adds texture to a sofa, bed or reading chair.',95.00,2,'assets/images/products/pink-bobble-throw.jpg',1),
('Pink Striped Shoulder Bag','Bags','A pink and white striped crochet shoulder bag with a button flap and long strap.',48.00,4,'assets/images/products/pink-shoulder-bag.jpg',1),
('Duckies in Hats','Plushies','Two cheerful yellow duckies wearing little crochet hats. Soft, squishy and ready to gift.',35.00,6,'assets/images/products/duckies-in-hats.jpg',1),
('White Bunny Keychain','Keychains','A fluffy white bunny keychain with a stitched face, small enough for a school bag or keys.',14.00,10,'assets/images/products/bunny-keychain.jpg',1),
('Red Rose Bouquet','Flowers','A bouquet of handmade red crochet roses wrapped in white. A gift that never wilts.',42.00,3,'assets/images/products/rose-bouquet.jpg',1),
('Floral Wall Hanging','Wall Hangings','A playful hanging decoration with trailing crochet flowers, leaves and a little pink friend.',58.00,2,'assets/images/products/floral-wall-hanging.jpg',1),
('Kitten Pair','Plushies','Two soft kittens, one calico and one grey, with stitched whiskers and curled tails.',24.00,8,'assets/images/products/kitten-pair.jpg',1),
('AirPod Pouch','Bags','A soft crochet pouch that keeps earbuds and small essentials safe in your bag.',18.00,5,'assets/images/products/airpod-pouch.jpg',1),
('Blue Puppy Plush','Plushies','A floppy-eared blue puppy in super-soft chenille yarn with a little pink tongue.',28.00,5,'assets/images/products/blue-puppy.jpg',1),
('Hanging Plant Baskets','Flowers','Two hanging baskets filled with crochet trailing plants and flowers. No watering needed.',42.00,4,'assets/images/products/hanging-plant-baskets.jpg',1),
('Pink Jellyfish','Plushies','A dusty-pink plush with curly crochet tentacles that wiggle when you hold it.',26.00,5,'assets/images/products/pink-jellyfish.jpg',1),
('Kitten Group','Plushies','A group of round, chunky kitten plushies in blue, grey and lilac tones.',30.00,4,'assets/images/products/kitten-group.jpg',1),
('Cat & Yarn Wall Hanging','Wall Hangings','A tapestry-style wall hanging of a ginger cat playing with a ball of yarn.',58.00,3,'assets/images/products/cat-yarn-wall-hanging.jpg',1),
('Octopus Trio','Plushies','Three little octopus plushies in white, grey and mint, each with a keyring loop.',32.00,4,'assets/images/products/octopus-trio.jpg',1),
('Penguin Family','Plushies','A family of four penguins in navy and red, from one big parent to three tiny chicks.',32.00,4,'assets/images/products/penguin-family.jpg',1),
('Character Wall Hanging','Wall Hangings','A playful yellow character wall hanging on a sage background, perfect for a bedroom or playroom.',58.00,3,'assets/images/products/character-wall-hanging.jpg',1),
('Puppy Trio','Plushies','Three round-eared pups in chocolate and honey yarn. Sold as a set of three.',28.00,5,'assets/images/products/puppy-trio.jpg',1),
('Granny Square Shoulder Bag','Bags','A roomy pastel granny-square shoulder bag with long handles for everyday use.',48.00,4,'assets/images/products/granny-square-bag.jpg',1),
('Sleepy Character Plush','Plushies','A sleepy navy and white character plush, soft enough to cuddle and sturdy enough to display.',30.00,4,'assets/images/products/sleepy-character-plush.jpg',1),
('Granny Square Throw','Throws','A bright rainbow granny-square throw that brings colour to a sofa or bed.',95.00,2,'assets/images/products/granny-square-throw.jpg',1),
('Turtle Pair','Plushies','A pair of little turtles with blue and pink shells and big friendly eyes.',30.00,5,'assets/images/products/turtle-pair.jpg',1),
('Bunny Plush','Plushies','A cream bunny with long floppy ears and a pink bow.',30.00,4,'assets/images/products/bunny-plush.jpg',1),
('Piggy Plush','Plushies','A cheerful pink piggy in a striped red and black jumper.',30.00,4,'assets/images/products/piggy-plush.jpg',1),
('Blue Ribbed Beanie','Hats','A cosy sky-blue ribbed beanie with a fold-up brim.',35.00,6,'assets/images/products/blue-beanie.jpg',1),
('Flower Coaster Set','Coasters','Two round coasters: one in chocolate and gold, one trimmed with pink crochet flowers.',12.00,8,'assets/images/products/flower-coasters.jpg',1),
('Dolphin Keychain','Keychains','A chunky pink and white dolphin keychain with a silver split ring.',16.00,8,'assets/images/products/dolphin-keychain.jpg',1),
('Friendship Keychains','Keychains','A matching pair of little bird keychains, one for you and one for your best friend.',22.00,8,'assets/images/products/friendship-keychains.jpg',1),
('Misty Keychain','Keychains','A mini crochet character keychain with dark hair and a purple outfit.',16.00,8,'assets/images/products/misty-keychain.jpg',1);
