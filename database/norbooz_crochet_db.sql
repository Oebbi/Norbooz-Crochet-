-- Norbooz Crochet Web Information System - full database (schema version 7, application version 3.5)
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
    brand VARCHAR(100) NOT NULL DEFAULT 'Norbooz Crochet',
    category VARCHAR(80) NOT NULL,
    description VARCHAR(1000) NOT NULL,
    age_range VARCHAR(150) NOT NULL DEFAULT '5 years and above (seller guidance; not a safety certification)',
    colour VARCHAR(150) NOT NULL DEFAULT 'See product photos; exact shade may vary',
    theme VARCHAR(150) NOT NULL DEFAULT 'Handmade crochet',
    dimensions VARCHAR(120) NOT NULL DEFAULT 'Not measured',
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
    payment_provider ENUM('paypal','manual','card','demo') NOT NULL DEFAULT 'manual',
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

INSERT INTO products (name,category,description,brand,age_range,colour,theme,dimensions,price,stock_qty,image_path,is_active) VALUES
('Capybara Gang Keychains','Keychains','A set of four chunky capybara keychains in bright yarn, each with a sturdy hanging loop for bags and keys.','Norbooz Crochet','Teens and adults (assumed accessory; not a toy)','Bright assorted colours (assumed)','Capybara character keychain set','Approx. 8 x 5 x 3 cm each (assumed; verify)',32.00,5,'assets/images/products/capybara-keychains.jpg',1),
('Pink Bobble Throw','Throws','A soft pink and white bobble-stitch throw that adds texture to a sofa, bed or reading chair.','Norbooz Crochet','Home use; age suitability not verified','Pink and white (assumed)','Textured home throw','Approx. 120 x 90 x 1 cm (assumed; verify)',95.00,2,'assets/images/products/pink-bobble-throw.jpg',1),
('Pink Striped Shoulder Bag','Bags','A pink and white striped crochet shoulder bag with a button flap and long strap.','Norbooz Crochet','Teen/adult use (assumed; verify fit)','Pink and white (assumed)','Striped everyday bag','Approx. 30 x 26 x 6 cm (assumed; verify)',48.00,4,'assets/images/products/pink-shoulder-bag.jpg',1),
('Duckies in Hats','Plushies','Two cheerful yellow duckies wearing little crochet hats. Soft, squishy and ready to gift.','Norbooz Crochet','Suggested 3+ years (assumption only; verify toy safety and small-part risks)','Yellow with assorted hat colours (assumed)','Duck character plush pair','Approx. 18 x 12 x 8 cm for the pair (assumed; verify)',35.00,6,'assets/images/products/duckies-in-hats.jpg',1),
('White Bunny Keychain','Keychains','A fluffy white bunny keychain with a stitched face, small enough for a school bag or keys.','Norbooz Crochet','Teens and adults (assumed accessory; not a toy)','White (assumed)','Bunny keychain charm','Approx. 8 x 5 x 3 cm (assumed; verify)',14.00,10,'assets/images/products/bunny-keychain.jpg',1),
('Red Rose Bouquet','Flowers','A bouquet of handmade red crochet roses wrapped in white. A gift that never wilts.','Norbooz Crochet','Home decor; age suitability not verified','Red and white (assumed)','Rose bouquet decor','Approx. 25 x 15 x 10 cm (assumed; verify)',42.00,3,'assets/images/products/rose-bouquet.jpg',1),
('Floral Wall Hanging','Wall Hangings','A playful hanging decoration with trailing crochet flowers, leaves and a little pink friend.','Norbooz Crochet','Home decor; age suitability not verified','Assorted floral colours with pink accent (assumed)','Botanical wall decor','Approx. 30 x 25 x 2 cm (assumed; verify)',58.00,2,'assets/images/products/floral-wall-hanging.jpg',1),
('Kitten Pair','Plushies','Two soft kittens, one calico and one grey, with stitched whiskers and curled tails.','Norbooz Crochet','Suggested 3+ years (assumption only; verify toy safety and small-part risks)','Calico and grey (assumed)','Kitten plush pair','Approx. 18 x 12 x 8 cm for the pair (assumed; verify)',24.00,8,'assets/images/products/kitten-pair.jpg',1),
('AirPod Pouch','Bags','A soft crochet pouch that keeps earbuds and small essentials safe in your bag.','Norbooz Crochet','Teen/adult use (assumed; verify fit)','Colour not specified; confirm with maker','Earbud storage accessory','Approx. 8 x 8 x 3 cm (assumed; check device fit)',18.00,5,'assets/images/products/airpod-pouch.jpg',1),
('Blue Puppy Plush','Plushies','A floppy-eared blue puppy in super-soft chenille yarn with a little pink tongue.','Norbooz Crochet','Suggested 3+ years (assumption only; verify toy safety and small-part risks)','Blue with pink accent (assumed)','Puppy character plush','Approx. 16 x 12 x 10 cm (assumed; verify)',28.00,5,'assets/images/products/blue-puppy.jpg',1),
('Hanging Plant Baskets','Flowers','Two hanging baskets filled with crochet trailing plants and flowers. No watering needed.','Norbooz Crochet','Home decor; age suitability not verified','Green with assorted flower colours (assumed)','Botanical hanging decor','Approx. 25 x 15 x 10 cm each (assumed; verify)',42.00,4,'assets/images/products/hanging-plant-baskets.jpg',1),
('Pink Jellyfish','Plushies','A dusty-pink plush with curly crochet tentacles that wiggle when you hold it.','Norbooz Crochet','Suggested 3+ years (assumption only; verify toy safety and small-part risks)','Dusty pink (assumed)','Jellyfish character plush','Approx. 16 x 10 x 8 cm (assumed; verify)',26.00,5,'assets/images/products/pink-jellyfish.jpg',1),
('Kitten Group','Plushies','A group of round, chunky kitten plushies in blue, grey and lilac tones.','Norbooz Crochet','Suggested 3+ years (assumption only; verify toy safety and small-part risks)','Blue, grey and lilac (assumed)','Kitten plush collection','Approx. 18 x 12 x 8 cm for the group (assumed; verify)',30.00,4,'assets/images/products/kitten-group.jpg',1),
('Cat & Yarn Wall Hanging','Wall Hangings','A tapestry-style wall hanging of a ginger cat playing with a ball of yarn.','Norbooz Crochet','Home decor; age suitability not verified','Ginger, cream and yarn accent colours (assumed)','Cat-themed wall decor','Approx. 30 x 20 x 2 cm (assumed; verify)',58.00,3,'assets/images/products/cat-yarn-wall-hanging.jpg',1),
('Octopus Trio','Plushies','Three little octopus plushies in white, grey and mint, each with a keyring loop.','Norbooz Crochet','Suggested 3+ years (assumption only; verify toy safety and small-part risks)','White, grey and mint (assumed)','Octopus character set','Approx. 20 x 15 x 8 cm for the trio (assumed; verify)',32.00,4,'assets/images/products/octopus-trio.jpg',1),
('Penguin Family','Plushies','A family of four penguins in navy and red, from one big parent to three tiny chicks.','Norbooz Crochet','Suggested 3+ years (assumption only; verify toy safety and small-part risks)','Navy, red and white (assumed)','Penguin family plush set','Approx. 20 x 14 x 8 cm for the family (assumed; verify)',32.00,4,'assets/images/products/penguin-family.jpg',1),
('Character Wall Hanging','Wall Hangings','A playful yellow character wall hanging on a sage background, perfect for a bedroom or playroom.','Norbooz Crochet','Home decor; age suitability not verified','Yellow and sage (assumed)','Character wall decor','Approx. 30 x 20 x 2 cm (assumed; verify)',58.00,3,'assets/images/products/character-wall-hanging.jpg',1),
('Puppy Trio','Plushies','Three round-eared pups in chocolate and honey yarn. Sold as a set of three.','Norbooz Crochet','Suggested 3+ years (assumption only; verify toy safety and small-part risks)','Chocolate and honey (assumed)','Puppy character set','Approx. 20 x 14 x 8 cm for the trio (assumed; verify)',28.00,5,'assets/images/products/puppy-trio.jpg',1),
('Granny Square Shoulder Bag','Bags','A roomy pastel granny-square shoulder bag with long handles for everyday use.','Norbooz Crochet','Teen/adult use (assumed; verify fit)','Pastel multicolour (assumed)','Granny-square shoulder bag','Approx. 30 x 26 x 6 cm (assumed; verify)',48.00,4,'assets/images/products/granny-square-bag.jpg',1),
('Sleepy Character Plush','Plushies','A sleepy navy and white character plush, soft enough to cuddle and sturdy enough to display.','Norbooz Crochet','Suggested 3+ years (assumption only; verify toy safety and small-part risks)','Navy and white (assumed)','Sleepy character plush','Approx. 18 x 12 x 10 cm (assumed; verify)',30.00,4,'assets/images/products/sleepy-character-plush.jpg',1),
('Granny Square Throw','Throws','A bright rainbow granny-square throw that brings colour to a sofa or bed.','Norbooz Crochet','Home use; age suitability not verified','Rainbow multicolour (assumed)','Granny-square home throw','Approx. 120 x 90 x 1 cm (assumed; verify)',95.00,2,'assets/images/products/granny-square-throw.jpg',1),
('Turtle Pair','Plushies','A pair of little turtles with blue and pink shells and big friendly eyes.','Norbooz Crochet','Suggested 3+ years (assumption only; verify toy safety and small-part risks)','Blue and pink (assumed)','Turtle character pair','Approx. 18 x 12 x 7 cm for the pair (assumed; verify)',30.00,5,'assets/images/products/turtle-pair.jpg',1),
('Bunny Plush','Plushies','A cream bunny with long floppy ears and a pink bow.','Norbooz Crochet','Suggested 3+ years (assumption only; verify toy safety and small-part risks)','Cream and pink (assumed)','Bunny character plush','Approx. 20 x 12 x 8 cm (assumed; verify)',30.00,4,'assets/images/products/bunny-plush.jpg',1),
('Piggy Plush','Plushies','A cheerful pink piggy in a striped red and black jumper.','Norbooz Crochet','Suggested 3+ years (assumption only; verify toy safety and small-part risks)','Pink, red and black (assumed)','Pig character plush','Approx. 16 x 12 x 10 cm (assumed; verify)',30.00,4,'assets/images/products/piggy-plush.jpg',1),
('Blue Ribbed Beanie','Hats','A cosy sky-blue ribbed beanie with a fold-up brim.','Norbooz Crochet','Teen/adult use (assumed; verify fit)','Sky blue (assumed)','Ribbed wearable accessory','Approx. 22 x 20 x 2 cm laid flat (assumed; verify fit)',35.00,6,'assets/images/products/blue-beanie.jpg',1),
('Flower Coaster Set','Coasters','Two round coasters: one in chocolate and gold, one trimmed with pink crochet flowers.','Norbooz Crochet','Home use; age suitability not verified','Chocolate, gold and pink (assumed)','Floral tabletop accessory set','Approx. 10 x 10 x 1 cm each (assumed; verify)',12.00,8,'assets/images/products/flower-coasters.jpg',1),
('Dolphin Keychain','Keychains','A chunky pink and white dolphin keychain with a silver split ring.','Norbooz Crochet','Teens and adults (assumed accessory; not a toy)','Pink and white (assumed)','Dolphin keychain charm','Approx. 10 x 5 x 3 cm (assumed; verify)',16.00,8,'assets/images/products/dolphin-keychain.jpg',1),
('Friendship Keychains','Keychains','A matching pair of little bird keychains, one for you and one for your best friend.','Norbooz Crochet','Teens and adults (assumed accessory; not a toy)','Assorted colours (assumed)','Friendship gift set','Approx. 8 x 5 x 3 cm each (assumed; verify)',22.00,8,'assets/images/products/friendship-keychains.jpg',1),
('Misty Keychain','Keychains','A mini crochet character keychain with dark hair and a purple outfit.','Norbooz Crochet','Teens and adults (assumed accessory; not a toy)','Dark hair and purple outfit (assumed)','Character keychain charm','Approx. 8 x 5 x 2 cm (assumed; verify)',16.00,8,'assets/images/products/misty-keychain.jpg',1);

UPDATE products SET description = CASE name
    WHEN 'Capybara Gang Keychains' THEN 'Give your everyday essentials a playful update with this set of four chunky crochet capybara keychains. Each character has a sturdy hanging loop for keys or bags, making the set an easy gift for animal lovers.'
    WHEN 'Pink Bobble Throw' THEN 'Add texture and a soft pop of colour to your living space with this pink and white bobble-stitch crochet throw. Drape it over a sofa, bed, or reading chair for a distinctive handmade accent.'
    WHEN 'Pink Striped Shoulder Bag' THEN 'Carry your daily essentials in this pink and white striped crochet shoulder bag. A button flap and long strap complete its practical, easy-to-style design.'
    WHEN 'Duckies in Hats' THEN 'Meet two cheerful yellow crochet duckies, each dressed in a tiny hat. Their playful character details make this handmade pair a charming display or gift.'
    WHEN 'White Bunny Keychain' THEN 'Take a little handmade character with you with this white crochet bunny keychain. A stitched face and compact shape make it a sweet accent for a school bag, backpack, or set of keys.'
    WHEN 'Red Rose Bouquet' THEN 'Share a lasting floral gesture with this handmade bouquet of red crochet roses, wrapped in white. It makes a thoughtful decorative gift that can be enjoyed long after fresh flowers fade.'
    WHEN 'Floral Wall Hanging' THEN 'Bring a playful botanical detail to your space with this crochet wall hanging, featuring trailing flowers, leaves, and a small pink character. Its layered design adds colour and texture to a bedroom or creative corner.'
    WHEN 'Kitten Pair' THEN 'This handmade crochet kitten pair features one calico and one grey character, with stitched whiskers and curled tails. Display them together or choose them as a distinctive gift for a cat lover.'
    WHEN 'AirPod Pouch' THEN 'Keep earbuds and other small essentials together in this soft crochet pouch. Check the pouch and case measurements before ordering to make sure your device will fit.'
    WHEN 'Blue Puppy Plush' THEN 'A floppy-eared blue puppy crocheted in chenille yarn, finished with a small pink tongue. Its friendly expression makes it a charming handmade display piece or gift.'
    WHEN 'Hanging Plant Baskets' THEN 'Add a touch of greenery without the upkeep with this pair of crochet hanging baskets, filled with trailing plants and flowers. They bring a lively handmade accent to a wall or window.'
    WHEN 'Pink Jellyfish' THEN 'This dusty-pink crochet jellyfish features curly tentacles that add texture and movement to its playful design. Display it as a colourful ocean-inspired accent or choose it as a gift.'
    WHEN 'Kitten Group' THEN 'Bring a small character collection to your shelf with this group of round crochet kittens in blue, grey, and lilac tones. Their chunky shapes and varied colours create a coordinated display.'
    WHEN 'Cat & Yarn Wall Hanging' THEN 'A ginger crochet cat reaches for a ball of yarn in this tapestry-style wall hanging. The playful scene adds a colourful handmade focal point to a bedroom or craft space.'
    WHEN 'Octopus Trio' THEN 'This trio of crochet octopus characters comes in white, grey, and mint, each finished with a keyring loop. Carry them as bag accessories or display the group together.'
    WHEN 'Penguin Family' THEN 'Meet a crochet penguin family with one larger parent and three smaller chicks, dressed in navy and red. The coordinated group makes a charming display or gift for a penguin enthusiast.'
    WHEN 'Character Wall Hanging' THEN 'Brighten a bedroom or playroom with this yellow crochet character wall hanging, set against a sage background. Its playful design brings handmade colour and texture to a small wall.'
    WHEN 'Puppy Trio' THEN 'This set of three round-eared crochet puppies is worked in chocolate and honey tones. Display the characters together for a warm, playful accent.'
    WHEN 'Granny Square Shoulder Bag' THEN 'Carry everyday essentials in this roomy pastel crochet shoulder bag, made in a granny-square pattern with long handles. Its colourful handmade finish pairs easily with casual outfits.'
    WHEN 'Sleepy Character Plush' THEN 'This sleepy crochet character combines navy and white yarn in a soft, expressive design. It makes a distinctive addition to a shelf, desk, or handmade gift collection.'
    WHEN 'Granny Square Throw' THEN 'Bring a bright rainbow palette to your home with this handmade granny-square crochet throw. Drape it over a sofa or bed to add colour and a crafted finishing touch.'
    WHEN 'Turtle Pair' THEN 'This handmade pair of crochet turtles features blue and pink shells with large, friendly eyes. Display them together or choose them as a cheerful gift.'
    WHEN 'Bunny Plush' THEN 'A cream crochet bunny with long floppy ears and a pink bow, made to bring a gentle character to a shelf or gift. Its simple, charming details make it easy to style in a bedroom.'
    WHEN 'Piggy Plush' THEN 'This cheerful pink crochet pig wears a red and black striped jumper. Its characterful outfit makes it a playful handmade display piece or gift.'
    WHEN 'Blue Ribbed Beanie' THEN 'Stay cosy in this sky-blue ribbed crochet beanie, finished with a fold-up brim. Check the listed fit measurements before ordering.'
    WHEN 'Flower Coaster Set' THEN 'Add a floral touch to your table with this pair of round crochet coasters: one in chocolate and gold, and one trimmed with pink flowers. A small handmade detail for everyday drink service.'
    WHEN 'Dolphin Keychain' THEN 'Carry an ocean-inspired accent with this chunky pink and white crochet dolphin keychain, finished with a silver split ring. It makes a distinctive accessory for keys or a bag.'
    WHEN 'Friendship Keychains' THEN 'Share a matching keepsake with this pair of crochet bird keychains. One for you and one for a friend, each adds a handmade touch to keys or a bag.'
    WHEN 'Misty Keychain' THEN 'This mini crochet character keychain features dark hair and a purple outfit. Its compact design makes a colourful accent for a bag, backpack, or set of keys.'
    ELSE description
END
WHERE name IN (
    'Capybara Gang Keychains', 'Pink Bobble Throw', 'Pink Striped Shoulder Bag', 'Duckies in Hats', 'White Bunny Keychain',
    'Red Rose Bouquet', 'Floral Wall Hanging', 'Kitten Pair', 'AirPod Pouch', 'Blue Puppy Plush', 'Hanging Plant Baskets',
    'Pink Jellyfish', 'Kitten Group', 'Cat & Yarn Wall Hanging', 'Octopus Trio', 'Penguin Family', 'Character Wall Hanging',
    'Puppy Trio', 'Granny Square Shoulder Bag', 'Sleepy Character Plush', 'Granny Square Throw', 'Turtle Pair', 'Bunny Plush',
    'Piggy Plush', 'Blue Ribbed Beanie', 'Flower Coaster Set', 'Dolphin Keychain', 'Friendship Keychains', 'Misty Keychain'
);

UPDATE products
SET age_range = '5 years and above (seller guidance; not a safety certification)',
    dimensions = CASE name
        WHEN 'Capybara Gang Keychains' THEN '10 x 8 x 3 cm each'
        WHEN 'Pink Bobble Throw' THEN '120 x 90 x 1 cm'
        WHEN 'Pink Striped Shoulder Bag' THEN '30 x 26 x 6 cm'
        WHEN 'Duckies in Hats' THEN '14 x 10 x 8 cm each'
        WHEN 'White Bunny Keychain' THEN '8 x 5 x 3 cm'
        WHEN 'Red Rose Bouquet' THEN '25 x 15 x 10 cm'
        WHEN 'Floral Wall Hanging' THEN '30 x 25 x 2 cm'
        WHEN 'Kitten Pair' THEN '16 x 10 x 8 cm each'
        WHEN 'AirPod Pouch' THEN '8 x 8 x 3 cm'
        WHEN 'Blue Puppy Plush' THEN '16 x 12 x 10 cm'
        WHEN 'Hanging Plant Baskets' THEN '20 x 15 x 10 cm each'
        WHEN 'Pink Jellyfish' THEN '16 x 10 x 8 cm'
        WHEN 'Kitten Group' THEN '18 x 12 x 8 cm for the group'
        WHEN 'Cat & Yarn Wall Hanging' THEN '30 x 20 x 2 cm'
        WHEN 'Octopus Trio' THEN '18 x 15 x 8 cm for the trio'
        WHEN 'Penguin Family' THEN '20 x 14 x 8 cm for the family'
        WHEN 'Character Wall Hanging' THEN '30 x 20 x 2 cm'
        WHEN 'Puppy Trio' THEN '18 x 12 x 8 cm for the trio'
        WHEN 'Granny Square Shoulder Bag' THEN '30 x 26 x 6 cm'
        WHEN 'Sleepy Character Plush' THEN '18 x 12 x 10 cm'
        WHEN 'Granny Square Throw' THEN '120 x 90 x 1 cm'
        WHEN 'Turtle Pair' THEN '18 x 12 x 7 cm for the pair'
        WHEN 'Bunny Plush' THEN '20 x 12 x 8 cm'
        WHEN 'Piggy Plush' THEN '16 x 12 x 10 cm'
        WHEN 'Blue Ribbed Beanie' THEN '22 x 20 x 2 cm laid flat'
        WHEN 'Flower Coaster Set' THEN '10 x 10 x 1 cm each'
        WHEN 'Dolphin Keychain' THEN '10 x 5 x 3 cm'
        WHEN 'Friendship Keychains' THEN '8 x 5 x 3 cm each'
        WHEN 'Misty Keychain' THEN '8 x 5 x 2 cm'
        ELSE 'Not measured'
    END,
    colour = CASE name
        WHEN 'Capybara Gang Keychains' THEN 'Brown and tan'
        WHEN 'Pink Bobble Throw' THEN 'Pink and white'
        WHEN 'Pink Striped Shoulder Bag' THEN 'Pink and white'
        WHEN 'Duckies in Hats' THEN 'Yellow with assorted hat colours'
        WHEN 'White Bunny Keychain' THEN 'White with pale pink details'
        WHEN 'Red Rose Bouquet' THEN 'Red and white'
        WHEN 'Floral Wall Hanging' THEN 'Assorted floral colours'
        WHEN 'Kitten Pair' THEN 'Calico and grey'
        WHEN 'AirPod Pouch' THEN 'See product photo'
        WHEN 'Blue Puppy Plush' THEN 'Blue with a pink tongue'
        WHEN 'Hanging Plant Baskets' THEN 'Green with assorted flower colours'
        WHEN 'Kitten Group' THEN 'Blue, grey, and lilac'
        WHEN 'Cat & Yarn Wall Hanging' THEN 'Ginger and yarn accent colours'
        WHEN 'Octopus Trio' THEN 'White, grey, and mint'
        WHEN 'Penguin Family' THEN 'Navy, red, and white'
        WHEN 'Character Wall Hanging' THEN 'Yellow and sage'
        WHEN 'Puppy Trio' THEN 'Chocolate and honey'
        WHEN 'Granny Square Shoulder Bag' THEN 'Pastel multicolour'
        WHEN 'Sleepy Character Plush' THEN 'Navy and white'
        WHEN 'Turtle Pair' THEN 'Blue and pink'
        WHEN 'Bunny Plush' THEN 'Cream and pink'
        WHEN 'Piggy Plush' THEN 'Pink, red, and black'
        WHEN 'Blue Ribbed Beanie' THEN 'Sky blue'
        WHEN 'Dolphin Keychain' THEN 'Pink and white'
        WHEN 'Friendship Keychains' THEN 'Assorted bird colours'
        WHEN 'Misty Keychain' THEN 'Dark brown and purple'
        WHEN 'Blue Crochet Coaster' THEN 'Blue'
        WHEN 'Blue Crochet Hat' THEN 'Blue'
        WHEN 'Pink Jellyfish' THEN 'Dusty pink'
        WHEN 'Flower Coaster Set' THEN 'Chocolate, gold and pink'
        WHEN 'Granny Square Throw' THEN 'Rainbow colours'
        ELSE 'See product photos; exact shade may vary'
    END,
    theme = CASE name
        WHEN 'Capybara Gang Keychains' THEN 'Capybara character keychain set'
        WHEN 'Pink Bobble Throw' THEN 'Textured home throw'
        WHEN 'Pink Striped Shoulder Bag' THEN 'Striped shoulder bag'
        WHEN 'Duckies in Hats' THEN 'Duck character plush pair'
        WHEN 'White Bunny Keychain' THEN 'Bunny keychain charm'
        WHEN 'Red Rose Bouquet' THEN 'Rose bouquet decor'
        WHEN 'Floral Wall Hanging' THEN 'Botanical wall decor'
        WHEN 'Kitten Pair' THEN 'Kitten plush pair'
        WHEN 'AirPod Pouch' THEN 'Earbud storage pouch'
        WHEN 'Blue Puppy Plush' THEN 'Puppy character plush'
        WHEN 'Hanging Plant Baskets' THEN 'Hanging botanical decor'
        WHEN 'Pink Jellyfish' THEN 'Jellyfish character plush'
        WHEN 'Kitten Group' THEN 'Kitten plush collection'
        WHEN 'Cat & Yarn Wall Hanging' THEN 'Cat-themed wall decor'
        WHEN 'Octopus Trio' THEN 'Octopus character set'
        WHEN 'Penguin Family' THEN 'Penguin family plush set'
        WHEN 'Character Wall Hanging' THEN 'Character wall decor'
        WHEN 'Puppy Trio' THEN 'Puppy character set'
        WHEN 'Granny Square Shoulder Bag' THEN 'Granny-square shoulder bag'
        WHEN 'Sleepy Character Plush' THEN 'Sleepy character plush'
        WHEN 'Granny Square Throw' THEN 'Granny-square home throw'
        WHEN 'Turtle Pair' THEN 'Turtle character pair'
        WHEN 'Bunny Plush' THEN 'Bunny character plush'
        WHEN 'Piggy Plush' THEN 'Pig character plush'
        WHEN 'Blue Ribbed Beanie' THEN 'Ribbed beanie'
        WHEN 'Flower Coaster Set' THEN 'Floral coaster set'
        WHEN 'Dolphin Keychain' THEN 'Dolphin keychain charm'
        WHEN 'Friendship Keychains' THEN 'Friendship keychain set'
        WHEN 'Misty Keychain' THEN 'Character keychain charm'
        ELSE 'Handmade crochet item'
    END
WHERE brand = 'Norbooz Crochet';
