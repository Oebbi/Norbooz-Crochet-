-- Norbooz Crochet Web Information System
-- Import this file in phpMyAdmin before opening the application.
CREATE DATABASE IF NOT EXISTS norbooz_crochet_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE norbooz_crochet_db;

SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS custom_requests;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(30) NOT NULL,
    address VARCHAR(255) NOT NULL,
    role ENUM('customer','admin') NOT NULL DEFAULT 'customer',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE password_resets (
    reset_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE products (
    product_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    category VARCHAR(80) NOT NULL,
    description VARCHAR(500) NOT NULL,
    price DECIMAL(10,2) NOT NULL CHECK (price >= 0),
    stock_qty INT UNSIGNED NOT NULL DEFAULT 0,
    image_path VARCHAR(255) DEFAULT 'assets/images/product-placeholder.svg',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE orders (
    order_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    order_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending','in_progress','ready','completed','cancelled') NOT NULL DEFAULT 'pending',
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    phone VARCHAR(30) NOT NULL,
    address VARCHAR(255) NOT NULL,
    custom_note VARCHAR(500) NULL,
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

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
    status ENUM('new','reviewing','quoted','accepted','declined') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_custom_requests_user FOREIGN KEY (user_id) REFERENCES users(user_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Assessment demonstration administrator.
-- Email: admin@norboozcrochet.local   Password: Admin@12345
INSERT INTO users (full_name,email,password_hash,phone,address,role) VALUES
('Norbooz Crochet Owner','admin@norboozcrochet.local','$2y$12$lzDlNIN8ysbWi70ybt0qG.G/lOyBupC4GuDhnslPEQnW51zo2u3j6','0400 000 000','Demo business address','admin');

INSERT INTO products (name,category,description,price,stock_qty,image_path,is_active) VALUES
('Crochet Capybara','Plushies','Soft handmade capybara plushie with stitched facial details.',32.00,5,'assets/images/products/Capibara-gang.png.png',1),
('Granny Square Throw','Throws','Decorative granny-square throw for a sofa, chair or thoughtful gift.',95.00,2,'assets/images/products/throw.png.png',1),
('Granny Square Tote','Bags','Reusable crochet tote bag with a colourful handmade finish.',48.00,4,'assets/images/products/Bag.png.png',1),
('Crochet Duckies','Plushies','A cheerful collection of soft handmade crochet duckies.',35.00,6,'assets/images/products/Baby-duckies.png.png',1),
('Mini Flower Keychain','Keychains','Small crochet flower keychain for bags, keys and gifts.',14.00,10,'assets/images/products/mifi-keychain.png.png',1),
('Crochet Tulip Bouquet','Flowers','Handmade crochet tulip arrangement designed as a lasting gift.',42.00,3,'assets/images/products/boquet.png.png',1),
('Boho Crochet Wall Hanging','Wall Hangings','Textured decorative wall hanging for a warm handmade interior.',58.00,2,'assets/images/products/wall-hanger.png.png',1),
('Crochet Kitty Set','Plushies','A playful set of soft handmade crochet kittens.',24.00,8,'assets/images/products/2-kitten.png.png',1),
('Crochet AirPod Pouch','Bags','A soft handmade crochet pouch for earbuds and small essentials.',18.00,5,'assets/images/products/Airpod-pouch.png.png',1),
('Crochet Doggy Pair','Plushies','A cheerful pair of soft handmade crochet dog plushies.',28.00,5,'assets/images/products/Baby-doggies.png.png',1),
('Hanging Flower Bouquet','Flowers','A colourful handmade crochet flower arrangement for home decor.',42.00,4,'assets/images/products/Hanging-flowers.png.png',1),
('Crochet Jellyfish','Plushies','A playful handmade crochet jellyfish plushie.',26.00,5,'assets/images/products/Jelly-fish.png.png',1),
('Crochet Kitten Group','Plushies','A handmade group of soft crochet kitten plushies.',30.00,4,'assets/images/products/Kitten-group.png.png',1),
('Floral Crochet Wall Hanger','Wall Hangings','A textured handmade floral wall hanging for a warm interior.',58.00,3,'assets/images/products/kitten-wall-hanger.png.png',1),
('Crochet Octopus Group','Plushies','A colourful group of handmade crochet octopus plushies.',32.00,4,'assets/images/products/octopus-group.png.png',1),
('Crochet Penguin Group','Plushies','A charming group of handmade crochet penguin plushies.',32.00,4,'assets/images/products/penguin-group.png.png',1),
('Character Crochet Wall Hanger','Wall Hangings','A playful handmade crochet wall hanging for a bedroom or playroom.',58.00,3,'assets/images/products/pikachu-wall-hanger.png.png',1),
('Crochet Puppy Pair','Plushies','A soft pair of handmade crochet puppy plushies.',28.00,5,'assets/images/products/puppies.png.png',1),
('Crochet Shoulder Bag','Bags','A practical handmade crochet shoulder bag for everyday use.',48.00,4,'assets/images/products/shoulder-bag.png.png',1),
('Crochet Character Plush','Plushies','A soft handmade crochet character plushie for gifting and collecting.',30.00,4,'assets/images/products/snorlax.png.png',1),
('Colourful Crochet Throw','Throws','A bright handmade crochet throw for cosy home styling.',95.00,2,'assets/images/products/throw-2.png.png',1),
('Crochet Turtle Group','Plushies','A delightful group of handmade crochet turtle plushies.',30.00,5,'assets/images/products/Turtles.png.png',1),
('Crochet Bunny Plush','Plushies','A soft handmade crochet bunny plushie with floppy ears.',30.00,4,'assets/images/products/Bunny .png',1),
('Crochet Piggy Plush','Plushies','A cheerful handmade crochet pig plushie.',30.00,4,'assets/images/products/Piggy.png',1),
('Blue Crochet Hat','Hats','A cosy handmade blue crochet hat for everyday wear.',35.00,6,'assets/images/products/Hat.png',1),
('Blue Crochet Coaster','Coasters','A handmade blue crochet coaster for protecting tables in style.',12.00,8,'assets/images/products/Coaster.png',1),
('Crochet Dolphin Keychain','Keychains','A handmade crochet dolphin keychain for bags, keys and gifts.',16.00,8,'assets/images/products/dolphi key chain.png',1),
('Friendship Crochet Keychains','Keychains','A colourful handmade pair of crochet keychains for friends.',22.00,8,'assets/images/products/friendship key  chain.png',1),
('Misty Crochet Keychain','Keychains','A handmade crochet keychain with a soft, colourful finish.',16.00,8,'assets/images/products/mistty-Keychain.png.png',1);
