-- Norbooz Crochet Web Information System
-- Import this file in phpMyAdmin before opening the application.
CREATE DATABASE IF NOT EXISTS norbooz_crochet_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE norbooz_crochet_db;

SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
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

-- Assessment demonstration administrator.
-- Email: admin@norboozcrochet.local   Password: Admin@12345
INSERT INTO users (full_name,email,password_hash,phone,address,role) VALUES
('Norbooz Crochet Owner','admin@norboozcrochet.local','$2y$12$lzDlNIN8ysbWi70ybt0qG.G/lOyBupC4GuDhnslPEQnW51zo2u3j6','0400 000 000','Demo business address','admin');

INSERT INTO products (name,category,description,price,stock_qty,image_path,is_active) VALUES
('Mini Crochet Bear','Plushies','Soft handmade bear suitable as a small gift or display item.',28.00,6,'assets/images/bear.svg',1),
('Crochet Bunny','Plushies','Handmade bunny with soft yarn and stitched facial details.',32.00,4,'assets/images/bunny.svg',1),
('Classic Beanie','Beanies','Warm crochet beanie available in different colours by request.',35.00,5,'assets/images/beanie.svg',1),
('Baby Blanket','Blankets','Crochet baby blanket made in a simple textured stitch pattern.',65.00,3,'assets/images/blanket.svg',1),
('Market Tote Bag','Bags','Reusable crochet tote bag for light everyday items.',48.00,2,'assets/images/bag.svg',1),
('Granny Square Throw','Throws','Decorative crochet throw assembled from colourful granny squares.',95.00,1,'assets/images/throw.svg',1);
