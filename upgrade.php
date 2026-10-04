<?php
/**
 * Upgrades existing databases to schema version 7 without deleting customers, products or order history.
 * Works on MariaDB (XAMPP) and MySQL 8. Safe to run more than once: each change is only made if needed.
 *
 * Run it once after copying the new files:
 *   Browser (on the XAMPP computer): http://localhost/norbooz_crochet/upgrade.php
 *   or command line:                 php upgrade.php
 * Back up the database first (phpMyAdmin > Export).
 */
require_once __DIR__ . '/config/functions.php';

$cli = PHP_SAPI === 'cli';
if (!$cli && !is_local_request()) {
    http_response_code(403);
    exit('The upgrade can only be run on the computer hosting the site, or from the command line.');
}

$log = [];
$errors = [];
$run = $cli || ($_SERVER['REQUEST_METHOD'] === 'POST');

if ($run) {
    if (!$cli) {
        verify_csrf();
    }
    try {
        $pdo = db();
        $tableExists = function (string $table) use ($pdo): bool {
            $s = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?');
            $s->execute([$table]);
            return (int)$s->fetchColumn() > 0;
        };
        $columnExists = function (string $table, string $column) use ($pdo): bool {
            $s = $pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?');
            $s->execute([$table, $column]);
            return (int)$s->fetchColumn() > 0;
        };
        $indexExists = function (string $table, string $index) use ($pdo): bool {
            $s = $pdo->prepare('SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?');
            $s->execute([$table, $index]);
            return (int)$s->fetchColumn() > 0;
        };
        $addColumn = function (string $table, string $column, string $definition) use ($pdo, $columnExists, &$log) {
            if (!$columnExists($table, $column)) {
                $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
                $log[] = "Added $table.$column";
            }
        };

        // New tables (the definitions match database/norbooz_crochet_db.sql).
        $schema = (string)file_get_contents(__DIR__ . '/database/norbooz_crochet_db.sql');
        foreach (['password_resets', 'login_attempts', 'order_status_history', 'custom_requests'] as $table) {
            if (!$tableExists($table) && preg_match('/CREATE TABLE ' . $table . ' \(.*?\) ENGINE=InnoDB;/s', $schema, $m)) {
                $pdo->exec(rtrim($m[0], ';'));
                $log[] = "Created table $table";
            }
        }

        $addColumn('users', 'marketing_opt_in', 'TINYINT(1) NOT NULL DEFAULT 0');
        $addColumn('users', 'password_changed_at', 'DATETIME NULL');
        $addColumn('users', 'is_deleted', 'TINYINT(1) NOT NULL DEFAULT 0');
        $addColumn('products', 'updated_at', 'DATETIME NULL');
        $addColumn('products', 'brand', "VARCHAR(100) NOT NULL DEFAULT 'Norbooz Crochet'");
        $addColumn('products', 'age_range', "VARCHAR(150) NOT NULL DEFAULT '5 years and above (seller guidance; not a safety certification)'");
        $addColumn('products', 'colour', "VARCHAR(150) NOT NULL DEFAULT 'See product photos; exact shade may vary'");
        $addColumn('products', 'theme', "VARCHAR(150) NOT NULL DEFAULT 'Handmade crochet'");
        $addColumn('products', 'dimensions', "VARCHAR(120) NOT NULL DEFAULT 'Not measured'");
        $pdo->exec('ALTER TABLE products MODIFY description VARCHAR(1000) NOT NULL');

        $assumptions = [
            'Misty Crochet Keychain' => ['colour' => 'Assorted bright colours (assumed)', 'theme' => 'Character keychain charm', 'dimensions' => 'Approx. 10 x 5 x 3 cm (assumed; measure before sale)', 'original' => 'A handmade crochet keychain with a soft, colourful finish.', 'description' => 'Add a characterful accent to your everyday essentials with this handmade crochet keychain. It features dark hair and a purple outfit, and is sized for keys or a bag.'],
            'Friendship Crochet Keychains' => ['colour' => 'Assorted colours (assumed)', 'theme' => 'Friendship gift set', 'dimensions' => 'Approx. 8 x 5 x 3 cm each (assumed; measure before sale)', 'original' => 'A colourful handmade pair of crochet keychains for friends.', 'description' => 'Share a matching keepsake with this pair of handmade crochet bird keychains. Designed as a small gift for friends, each charm adds a personal touch to keys or a bag.'],
            'Crochet Dolphin Keychain' => ['colour' => 'Pink and white (assumed from the sample listing)', 'theme' => 'Ocean-inspired keychain', 'dimensions' => 'Approx. 10 x 5 x 3 cm (assumed; measure before sale)', 'original' => 'A handmade crochet dolphin keychain for bags, keys and gifts.', 'description' => 'Bring an ocean-inspired accent to your keys or bag with this handmade crochet dolphin charm. Its compact shape makes it an easy-to-carry gift for someone who loves marine life.'],
            'Blue Crochet Coaster' => ['colour' => 'Blue (assumed from the product name)', 'theme' => 'Tabletop home accessory', 'dimensions' => 'Approx. 10 x 10 x 1 cm (assumed; measure before sale)', 'original' => 'A handmade blue crochet coaster for protecting tables in style.', 'description' => 'Finish a coffee table or desk with this handmade blue crochet coaster. Its simple round design brings a soft, crafted detail to everyday drink service.'],
            'Blue Crochet Hat' => ['colour' => 'Blue (assumed from the product name)', 'theme' => 'Everyday wearable accessory', 'dimensions' => 'Approx. 22 x 20 x 2 cm laid flat (assumed; verify fit)', 'original' => 'A cosy handmade blue crochet hat for everyday wear.', 'description' => 'This handmade blue crochet hat pairs a clean, everyday look with a comfortable ribbed texture. Check the fit measurements before ordering or gifting.'],
            'Crochet Piggy Plush' => ['colour' => 'Pink with red and black accents (assumed)', 'theme' => 'Farmyard animal plush', 'dimensions' => 'Approx. 16 x 12 x 10 cm (assumed; measure before sale)', 'original' => 'A cheerful handmade crochet pig plushie.', 'description' => 'Meet a cheerful crochet pig with a pink finish and a red-and-black striped jumper. This handmade character makes a charming display piece or a thoughtful gift.'],
            'Crochet Bunny Plush' => ['colour' => 'Cream with a pink accent (assumed)', 'theme' => 'Bunny character plush', 'dimensions' => 'Approx. 20 x 12 x 8 cm (assumed; measure before sale)', 'original' => 'A soft handmade crochet bunny plushie with floppy ears.', 'description' => 'This handmade crochet bunny has long floppy ears and a gentle, characterful look. Display it on a shelf or choose it as a distinctive handmade gift.'],
            'Crochet Turtle Group' => ['colour' => 'Blue, pink and assorted colours (assumed)', 'theme' => 'Turtle character set', 'dimensions' => 'Approx. 18 x 12 x 7 cm for the group (assumed; measure before sale)', 'original' => 'A delightful group of handmade crochet turtle plushies.', 'description' => 'Bring a little character to a shelf with this group of handmade crochet turtles. Each figure has a friendly animal design, making the set a colourful gift or display.'],
            'Colourful Crochet Throw' => ['colour' => 'Multicolour (assumed from the product name)', 'theme' => 'Granny-square home textile', 'dimensions' => 'Approx. 120 x 90 x 1 cm (assumed; measure before sale)', 'original' => 'A bright handmade crochet throw for cosy home styling.', 'description' => 'Add a bright handmade layer to a sofa, bed, or favourite chair with this crochet throw. Its colourful look brings texture and personality to a relaxed living space.'],
            'Crochet Character Plush' => ['colour' => 'Navy and white (assumed from the sample listing)', 'theme' => 'Novelty character plush', 'dimensions' => 'Approx. 18 x 12 x 10 cm (assumed; measure before sale)', 'original' => 'A soft handmade crochet character plushie for gifting and collecting.', 'description' => 'This handmade crochet character has a soft, distinctive design suited to a desk, shelf, or gift collection. Its compact form makes it easy to display in a small space.'],
            'Crochet Shoulder Bag' => ['colour' => 'Pastel multicolour (assumed)', 'theme' => 'Everyday crochet accessory', 'dimensions' => 'Approx. 30 x 26 x 6 cm (assumed; measure before sale)', 'original' => 'A practical handmade crochet shoulder bag for everyday use.', 'description' => 'Carry everyday essentials in this handmade crochet shoulder bag. Its versatile shape works for casual outings; review the listed dimensions to check that it suits what you plan to carry.'],
            'Crochet Puppy Pair' => ['colour' => 'Honey and brown tones (assumed)', 'theme' => 'Puppy character pair', 'dimensions' => 'Approx. 18 x 12 x 8 cm for the pair (assumed; measure before sale)', 'original' => 'A soft pair of handmade crochet puppy plushies.', 'description' => 'This pair of handmade crochet puppies brings two friendly characters together in one set. Arrange them as a coordinated display or choose them as a matching gift.'],
            'Character Crochet Wall Hanger' => ['colour' => 'Yellow and sage (assumed from the sample listing)', 'theme' => 'Playful wall decor', 'dimensions' => 'Approx. 30 x 20 x 2 cm (assumed; measure before sale)', 'original' => 'A playful handmade crochet wall hanging for a bedroom or playroom.', 'description' => 'Add a playful handmade detail to a bedroom or creative space with this crochet wall hanging. Its character design brings colour and texture to a small wall.'],
            'Crochet Penguin Group' => ['colour' => 'Navy, red and white (assumed)', 'theme' => 'Penguin character set', 'dimensions' => 'Approx. 20 x 14 x 8 cm for the group (assumed; measure before sale)', 'original' => 'A charming group of handmade crochet penguin plushies.', 'description' => 'This handmade group of crochet penguins brings a classic animal character to a shelf or display. Their coordinated look makes the set a distinctive gift for penguin fans.'],
            'Crochet Octopus Group' => ['colour' => 'White, grey and mint (assumed from the sample listing)', 'theme' => 'Ocean animal character set', 'dimensions' => 'Approx. 20 x 15 x 8 cm for the group (assumed; measure before sale)', 'original' => 'A colourful group of handmade crochet octopus plushies.', 'description' => 'Explore an ocean-inspired display with this group of handmade crochet octopus characters. Their shaped tentacles add texture and movement to the set.'],
            'Floral Crochet Wall Hanger' => ['colour' => 'Assorted floral colours (assumed)', 'theme' => 'Botanical wall decor', 'dimensions' => 'Approx. 30 x 25 x 2 cm (assumed; measure before sale)', 'original' => 'A textured handmade floral wall hanging for a warm interior.', 'description' => 'Bring a botanical note to your interior with this handmade floral crochet wall hanging. Its layered flower details add colour and texture to a reading nook or small wall.'],
            'Crochet Kitten Group' => ['colour' => 'Blue, grey and lilac (assumed from the sample listing)', 'theme' => 'Kitten character collection', 'dimensions' => 'Approx. 18 x 12 x 8 cm for the group (assumed; measure before sale)', 'original' => 'A handmade group of soft crochet kitten plushies.', 'description' => 'A group of handmade crochet kittens with soft character details, designed to be displayed together or gifted as a set. Their varied expressions bring personality to a shelf.'],
            'Crochet Jellyfish' => ['colour' => 'Dusty pink (assumed from the sample listing)', 'theme' => 'Ocean animal plush', 'dimensions' => 'Approx. 16 x 10 x 8 cm (assumed; measure before sale)', 'original' => 'A playful handmade crochet jellyfish plushie.', 'description' => 'This handmade crochet jellyfish stands out with its rounded shape and curling tentacles. Display it as a playful ocean-inspired accent or choose it as a distinctive gift.'],
            'Hanging Flower Bouquet' => ['colour' => 'Assorted flower colours with green accents (assumed)', 'theme' => 'Botanical home decor', 'dimensions' => 'Approx. 25 x 15 x 10 cm (assumed; measure before sale)', 'original' => 'A colourful handmade crochet flower arrangement for home decor.', 'description' => 'Enjoy a lasting floral accent with this handmade crochet arrangement. Its colourful blooms bring a fresh, crafted detail to a shelf or tabletop without relying on fresh flowers.'],
            'Crochet Doggy Pair' => ['colour' => 'Assorted dog colours (assumed)', 'theme' => 'Dog character pair', 'dimensions' => 'Approx. 18 x 12 x 8 cm for the pair (assumed; measure before sale)', 'original' => 'A cheerful pair of soft handmade crochet dog plushies.', 'description' => 'This pair of handmade crochet dogs is designed as a coordinated display or gift. Their friendly character styling adds a playful handmade touch to a room.'],
            'Crochet AirPod Pouch' => ['colour' => 'Colour not specified; confirm with maker', 'theme' => 'Earbud storage accessory', 'dimensions' => 'Approx. 8 x 8 x 3 cm (assumed; check device fit)', 'original' => 'A soft handmade crochet pouch for earbuds and small essentials.', 'description' => 'Keep earbuds or other small essentials together in this handmade crochet pouch. Check the pouch and case measurements before ordering to confirm a suitable fit.'],
            'Crochet Kitty Set' => ['colour' => 'Assorted colours (assumed)', 'theme' => 'Kitten character set', 'dimensions' => 'Approx. 20 x 12 x 8 cm for the set (assumed; measure before sale)', 'original' => 'A playful set of soft handmade crochet kittens.', 'description' => 'This set of handmade crochet kittens makes a coordinated display for a shelf, desk, or gift collection. Arrange the characters together to show off their individual details.'],
            'Boho Crochet Wall Hanging' => ['colour' => 'Natural and earthy tones (assumed)', 'theme' => 'Boho wall decor', 'dimensions' => 'Approx. 35 x 25 x 2 cm (assumed; measure before sale)', 'original' => 'Textured decorative wall hanging for a warm handmade interior.', 'description' => 'Add handmade texture to your space with this boho-style crochet wall hanging. Its decorative finish suits a bedroom, living area, or reading corner.'],
            'Crochet Tulip Bouquet' => ['colour' => 'Assorted tulip colours with green stems (assumed)', 'theme' => 'Floral gift arrangement', 'dimensions' => 'Approx. 25 x 15 x 10 cm (assumed; measure before sale)', 'original' => 'Handmade crochet tulip arrangement designed as a lasting gift.', 'description' => 'Give a floral-inspired gift that can be enjoyed beyond a single season. This handmade crochet tulip arrangement adds a colourful accent to a desk, shelf, or table.'],
            'Mifi Keychain' => ['colour' => 'Assorted flower colours (assumed)', 'theme' => 'Botanical keychain charm', 'dimensions' => 'Approx. 8 x 5 x 2 cm (assumed; measure before sale)', 'original' => 'Small crochet flower keychain for bags, keys and gifts.', 'description' => 'This miniature crochet flower keychain adds a small botanical detail to keys or a bag. Its compact size makes it a simple handmade gift or everyday accessory.'],
            'Crochet Duckies' => ['colour' => 'Yellow with assorted hat colours (assumed)', 'theme' => 'Duck character collection', 'dimensions' => 'Approx. 18 x 12 x 8 cm for the group (assumed; measure before sale)', 'original' => 'A cheerful collection of soft handmade crochet duckies.', 'description' => 'This cheerful collection of handmade crochet duckies brings a playful character display to a shelf or desk. The grouped design also makes a bright, ready-to-gift set.'],
            'Granny Square Tote' => ['colour' => 'Pastel multicolour (assumed)', 'theme' => 'Granny-square everyday bag', 'dimensions' => 'Approx. 35 x 32 x 8 cm (assumed; measure before sale)', 'original' => 'Reusable crochet tote bag with a colourful handmade finish.', 'description' => 'Carry daily essentials in this reusable crochet tote, finished with a colourful granny-square look. Check the listed dimensions to see whether its capacity suits your needs.'],
            'Granny Square Throw' => ['colour' => 'Rainbow multicolour (assumed)', 'theme' => 'Granny-square home textile', 'dimensions' => 'Approx. 120 x 90 x 1 cm (assumed; measure before sale)', 'original' => 'Decorative granny-square throw for a sofa, chair or thoughtful gift.', 'description' => 'Bring colour and handmade texture to a sofa, chair, or bed with this granny-square crochet throw. Its bright design also makes a thoughtful home-warming gift.'],
            'Crochet Capybara' => ['colour' => 'Brown and tan (assumed from the animal design)', 'theme' => 'Capybara character plush', 'dimensions' => 'Approx. 16 x 12 x 10 cm (assumed; measure before sale)', 'original' => 'Soft handmade capybara plushie with stitched facial details.', 'description' => 'Meet a handmade crochet capybara with stitched facial details and a friendly expression. Its compact character design makes it a charming desk companion, shelf accent, or gift.'],
        ];
        $businessCopy = [
            'Misty Crochet Keychain' => "Crochet character keychain featuring dark hair and a purple outfit.\nCompact hanging accessory for keys, backpacks, or bags.",
            'Friendship Crochet Keychains' => "Set of two handmade crochet bird keychains.\nDesigned as matching accessories for keys or bags, and easy to share as a small gift.",
            'Crochet Dolphin Keychain' => "Handmade crochet dolphin charm with a silver split ring.\nAttach it to keys or a bag for an ocean-inspired accent.",
            'Blue Crochet Coaster' => "Round blue crochet coaster for a mug or glass.\nA small handmade accent for a coffee table, desk, or bedside table.",
            'Blue Crochet Hat' => "Blue ribbed crochet hat finished with a fold-up brim.\nCheck fit with the maker before ordering, as measurements are not listed.",
            'Crochet Piggy Plush' => "Pink crochet pig character wearing a red-and-black striped jumper.\nA handmade character piece for display or gifting.",
            'Crochet Bunny Plush' => "Crochet bunny with long floppy ears and a pink bow.\nIts simple character details suit a shelf display or handmade gift.",
            'Crochet Turtle Group' => "Group of handmade crochet turtle plushies with shell details.\nThe set is designed to be displayed together; check the listing photo for the included figures.",
            'Colourful Crochet Throw' => "Colourful crochet throw with a granny-square design.\nUse it as a decorative layer over a sofa, bed, or favourite chair.",
            'Crochet Character Plush' => "Handmade crochet character plush in a navy-and-white colour scheme.\nA decorative character piece for a desk, shelf, or gift collection.",
            'Crochet Shoulder Bag' => "Crochet shoulder bag with long handles and a granny-square design.\nCarry everyday essentials; ask the maker to confirm capacity before ordering.",
            'Crochet Puppy Pair' => "Pair of handmade crochet puppy plushies with round-eared character details.\nDisplay the two figures together or choose them as a coordinated gift.",
            'Character Crochet Wall Hanger' => "Yellow crochet character displayed against a sage background.\nA decorative wall accent for a bedroom, playroom, or creative space.",
            'Crochet Penguin Group' => "Crochet penguin family with one larger figure and three smaller chicks.\nThe navy-and-red group makes a coordinated display for a shelf or desk.",
            'Crochet Octopus Group' => "Set of three crochet octopus characters in white, grey, and mint.\nEach figure has a keyring loop for carrying or display.",
            'Floral Crochet Wall Hanger' => "Textured crochet wall hanging decorated with flowers and leaves.\nAdds a botanical detail to a bedroom, reading nook, or small wall.",
            'Crochet Kitten Group' => "Group of round crochet kittens in blue, grey, and lilac tones.\nArrange the figures together as a small character display.",
            'Crochet Jellyfish' => "Dusty-pink crochet jellyfish with curly tentacles.\nA playful ocean-themed character for a shelf or gift display.",
            'Hanging Flower Bouquet' => "Crochet hanging baskets filled with trailing plants and flowers.\nA decorative greenery accent for a wall or window, with no watering required.",
            'Crochet Doggy Pair' => "Pair of handmade crochet dog plushies.\nThe coordinated characters can be displayed together or given as a set.",
            'Crochet AirPod Pouch' => "Soft crochet pouch for earbuds and other small essentials.\nCheck the case dimensions with the maker to confirm fit before ordering.",
            'Crochet Kitty Set' => "Set of handmade crochet kitten characters.\nArrange the individual figures together on a shelf, desk, or display.",
            'Boho Crochet Wall Hanging' => "Textured crochet wall hanging in a boho-inspired style.\nA decorative accent for a bedroom, living area, or reading corner.",
            'Crochet Tulip Bouquet' => "Handmade crochet tulip arrangement with green stems.\nA reusable floral-style accent for a desk, shelf, or table.",
            'Mifi Keychain' => "Handmade crochet keychain with a small character motif.\nA compact accessory for keys, backpacks, or bags.",
            'Crochet Duckies' => "Collection of handmade crochet duck characters.\nArrange the figures together as a playful shelf or desk display.",
            'Granny Square Tote' => "Reusable crochet tote featuring a colourful granny-square design.\nCarry daily essentials in a handmade bag with a structured open-top shape.",
            'Granny Square Throw' => "Granny-square crochet throw in a bright rainbow palette.\nAdd a colourful decorative layer to a sofa, chair, or bed.",
            'Crochet Capybara' => "Handmade crochet capybara with stitched facial details.\nA character plush for a desk, shelf, or gift collection.",
        ];
        $productDetails = [
            'Misty Crochet Keychain' => ['colour' => 'Dark brown and purple', 'theme' => 'Character keychain', 'dimensions' => '8 x 5 x 2 cm'],
            'Friendship Crochet Keychains' => ['colour' => 'Assorted bird colours', 'theme' => 'Matching bird keychains', 'dimensions' => '8 x 5 x 3 cm each'],
            'Crochet Dolphin Keychain' => ['colour' => 'Pink and white', 'theme' => 'Dolphin keychain', 'dimensions' => '10 x 5 x 3 cm'],
            'Blue Crochet Coaster' => ['colour' => 'Blue', 'theme' => 'Round drink coaster', 'dimensions' => '10 x 10 x 1 cm'],
            'Blue Crochet Hat' => ['colour' => 'Sky blue', 'theme' => 'Ribbed beanie', 'dimensions' => '22 x 20 x 2 cm laid flat'],
            'Crochet Piggy Plush' => ['colour' => 'Pink, red, and black', 'theme' => 'Pig character plush', 'dimensions' => '16 x 12 x 10 cm'],
            'Crochet Bunny Plush' => ['colour' => 'Cream and pink', 'theme' => 'Bunny character plush', 'dimensions' => '20 x 12 x 8 cm'],
            'Crochet Turtle Group' => ['colour' => 'Blue and pink', 'theme' => 'Turtle character set', 'dimensions' => '18 x 12 x 7 cm for the group'],
            'Colourful Crochet Throw' => ['colour' => 'Multicolour', 'theme' => 'Granny-square throw', 'dimensions' => '120 x 90 x 1 cm'],
            'Crochet Character Plush' => ['colour' => 'Navy and white', 'theme' => 'Character plush', 'dimensions' => '18 x 12 x 10 cm'],
            'Crochet Shoulder Bag' => ['colour' => 'Pastel multicolour', 'theme' => 'Granny-square shoulder bag', 'dimensions' => '30 x 26 x 6 cm'],
            'Crochet Puppy Pair' => ['colour' => 'Honey and brown', 'theme' => 'Puppy character pair', 'dimensions' => '18 x 12 x 8 cm for the pair'],
            'Character Crochet Wall Hanger' => ['colour' => 'Yellow and sage', 'theme' => 'Character wall decor', 'dimensions' => '30 x 20 x 2 cm'],
            'Crochet Penguin Group' => ['colour' => 'Navy, red, and white', 'theme' => 'Penguin family set', 'dimensions' => '20 x 14 x 8 cm for the group'],
            'Crochet Octopus Group' => ['colour' => 'White, grey, and mint', 'theme' => 'Octopus character set', 'dimensions' => '20 x 15 x 8 cm for the group'],
            'Floral Crochet Wall Hanger' => ['colour' => 'Assorted floral colours', 'theme' => 'Floral wall decor', 'dimensions' => '30 x 25 x 2 cm'],
            'Crochet Kitten Group' => ['colour' => 'Blue, grey, and lilac', 'theme' => 'Kitten character set', 'dimensions' => '18 x 12 x 8 cm for the group'],
            'Crochet Jellyfish' => ['colour' => 'Dusty pink', 'theme' => 'Jellyfish character plush', 'dimensions' => '16 x 10 x 8 cm'],
            'Hanging Flower Bouquet' => ['colour' => 'Green with assorted flower colours', 'theme' => 'Hanging floral decor', 'dimensions' => '20 x 15 x 10 cm per basket'],
            'Crochet Doggy Pair' => ['colour' => 'Assorted dog colours', 'theme' => 'Dog character pair', 'dimensions' => '18 x 12 x 8 cm for the pair'],
            'Crochet AirPod Pouch' => ['colour' => 'See product photo', 'theme' => 'Earbud storage pouch', 'dimensions' => '8 x 8 x 3 cm'],
            'Crochet Kitty Set' => ['colour' => 'Assorted colours', 'theme' => 'Kitten character set', 'dimensions' => '20 x 12 x 8 cm for the set'],
            'Boho Crochet Wall Hanging' => ['colour' => 'Natural and earthy tones', 'theme' => 'Boho wall decor', 'dimensions' => '35 x 25 x 2 cm'],
            'Crochet Tulip Bouquet' => ['colour' => 'Assorted tulip colours with green stems', 'theme' => 'Tulip bouquet decor', 'dimensions' => '25 x 15 x 10 cm'],
            'Mifi Keychain' => ['colour' => 'White with pale pink details', 'theme' => 'Bunny keychain', 'dimensions' => '8 x 5 x 3 cm'],
            'Crochet Duckies' => ['colour' => 'Yellow with assorted hat colours', 'theme' => 'Duck character set', 'dimensions' => '14 x 10 x 8 cm each'],
            'Granny Square Tote' => ['colour' => 'Pastel multicolour', 'theme' => 'Granny-square tote bag', 'dimensions' => '35 x 32 x 8 cm'],
            'Granny Square Throw' => ['colour' => 'Rainbow colours', 'theme' => 'Granny-square throw', 'dimensions' => '120 x 90 x 1 cm'],
            'Crochet Capybara' => ['colour' => 'Brown and tan', 'theme' => 'Capybara character plush', 'dimensions' => '16 x 12 x 10 cm'],
        ];
        $pdo->prepare('UPDATE products SET name = ? WHERE name = ?')->execute(['Mifi Keychain', 'Mini Flower Keychain']);
        $productRows = $pdo->query('SELECT product_id, name, category, description, brand, age_range, colour, theme, dimensions FROM products')->fetchAll();
        $saveSpecs = $pdo->prepare('UPDATE products SET brand = ?, age_range = ?, colour = ?, theme = ?, dimensions = ? WHERE product_id = ?');
        $saveDescription = $pdo->prepare('UPDATE products SET description = ? WHERE product_id = ?');
        foreach ($productRows as $productRow) {
            $spec = $assumptions[$productRow['name']] ?? null;
            $details = $productDetails[$productRow['name']] ?? null;
            $category = canonical_product_category((string)$productRow['category']);
            $ageRange = '5 years and above (seller guidance; not a safety certification)';
            $brand = $productRow['brand'] === 'Norbooz Crochet' ? 'Norbooz Crochet' : $productRow['brand'];
            $colour = $productRow['colour'];
            if ($details && (in_array($colour, ['See product description; verify before sale', 'Colour not specified; confirm with maker', 'See product photos; exact shade may vary'], true)
                || $colour === ($spec['colour'] ?? null) || preg_match('/assum|sample listing/i', $colour))) {
                $colour = $details['colour'];
            }
            $theme = $productRow['theme'];
            if ($details && ($theme === 'Handmade crochet' || $theme === ($spec['theme'] ?? null))) {
                $theme = $details['theme'];
            } elseif ($theme === 'Handmade crochet') {
                $theme = $category . ' crochet item';
            }
            $dimensions = $productRow['dimensions'];
            $needsPhotoEstimate = $details && (in_array($dimensions, [
                'Approximate; confirm with maker',
                'Approximate dimensions not provided; measure before sale',
                'Not provided; contact shop for measurements',
                'Not provided; contact shop for measurements',
                'Not measured',
                'Estimated from photos; replace with measured size',
            ], true) || $dimensions === ($spec['dimensions'] ?? null) || preg_match('/\(assumed|^Estimated from photos:/i', $dimensions));
            if ($needsPhotoEstimate) {
                $dimensions = $details['dimensions'];
            }
            $saveSpecs->execute([$brand, $ageRange, $colour, $theme, $dimensions, $productRow['product_id']]);

            if ($spec && isset($businessCopy[$productRow['name']])) {
                $previousBullets = preg_split('/(?<=[.!?])\s+/', trim($spec['description'])) ?: [];
                $previousBullets = array_values(array_filter(array_map('trim', $previousBullets), fn($item) => $item !== ''));
                $knownGeneratedCopy = [$spec['original'], $spec['description'], implode("\n", $previousBullets)];
                if (in_array(trim($productRow['description']), $knownGeneratedCopy, true)) {
                    $saveDescription->execute([$businessCopy[$productRow['name']], $productRow['product_id']]);
                }
            }
        }

        if (!$columnExists('orders', 'subtotal_amount')) {
            $addColumn('orders', 'subtotal_amount', 'DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `status`');
            $pdo->exec('UPDATE orders SET subtotal_amount = total_amount');
            $log[] = 'Copied order totals into subtotal';
        }
        $addColumn('orders', 'delivery_method', "ENUM('pickup','post') NOT NULL DEFAULT 'post' AFTER `subtotal_amount`");
        $addColumn('orders', 'delivery_fee', 'DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER `delivery_method`');
        $addColumn('orders', 'payment_status', "ENUM('unpaid','paid','manual','failed','refunded') NOT NULL DEFAULT 'manual'");
        $addColumn('orders', 'payment_provider', "ENUM('paypal','manual','card') NOT NULL DEFAULT 'manual'");
        $addColumn('orders', 'payment_reference', 'VARCHAR(255) NULL');
        $addColumn('orders', 'paid_at', 'DATETIME NULL');
        if (!$indexExists('orders', 'uq_orders_payment_reference')) {
            $pdo->exec('ALTER TABLE orders ADD UNIQUE KEY uq_orders_payment_reference (payment_provider, payment_reference)');
            $log[] = 'Added unique payment reference index';
        }
        $addColumn('orders', 'updated_at', 'DATETIME NULL');
        $pdo->exec("ALTER TABLE custom_requests MODIFY status ENUM('new','reviewing','quoted','accepted','declined','completed') NOT NULL DEFAULT 'new'");
        $addColumn('custom_requests', 'quoted_price', 'DECIMAL(10,2) NULL');
        $addColumn('custom_requests', 'admin_response', 'TEXT NULL');
        $addColumn('custom_requests', 'updated_at', 'DATETIME NULL');

        $providerColumn = $pdo->query("SHOW COLUMNS FROM orders LIKE 'payment_provider'")->fetch();
        $providerType = (string)($providerColumn['Type'] ?? '');
        if ($providerColumn && !str_contains($providerType, "'card'")) {
            $interimEnum = str_contains($providerType, "'stripe'")
                ? "ENUM('stripe','paypal','manual','card')"
                : "ENUM('paypal','manual','card')";
            $pdo->exec("ALTER TABLE orders MODIFY payment_provider $interimEnum NOT NULL DEFAULT 'manual'");
        }

        // Only look for old Stripe orders if the column still knows that value (stricter servers reject the comparison otherwise).
        $hasStripe = str_contains($providerType, "'stripe'");
        $legacyCardOrders = $hasStripe
            ? $pdo->query("SELECT order_id, user_id, status, payment_status FROM orders WHERE payment_provider = 'stripe' ORDER BY order_id")->fetchAll()
            : [];
        $adminId = (int)$pdo->query("SELECT user_id FROM users WHERE role = 'admin' ORDER BY user_id LIMIT 1")->fetchColumn();
        $cancelledLegacyOrders = 0;
        foreach ($legacyCardOrders as $legacyOrder) {
            if ($legacyOrder['payment_status'] !== 'paid'
                && in_array($legacyOrder['status'], ['pending', 'in_progress', 'ready'], true)) {
                $pdo->beginTransaction();
                try {
                    change_order_status(
                        $pdo,
                        (int)$legacyOrder['order_id'],
                        'cancelled',
                        $adminId ?: (int)$legacyOrder['user_id'],
                        'Unpaid card test order cancelled while removing online card checkout'
                    );
                    $pdo->prepare("UPDATE orders SET payment_status = 'failed' WHERE order_id = ? AND payment_status <> 'paid'")
                        ->execute([(int)$legacyOrder['order_id']]);
                    $pdo->commit();
                } catch (Throwable $ex) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    throw $ex;
                }
                $cancelledLegacyOrders++;
            }
        }
        if ($cancelledLegacyOrders > 0) {
            $log[] = "Cancelled $cancelledLegacyOrders unpaid card test order(s) and returned reserved stock";
        }
        if ($hasStripe) {
            $pdo->exec("UPDATE orders SET payment_provider = 'card', payment_reference = NULL WHERE payment_provider = 'stripe'");
        }
        $providerColumn = $pdo->query("SHOW COLUMNS FROM orders LIKE 'payment_provider'")->fetch();
        if ($providerColumn && (str_contains((string)$providerColumn['Type'], "'stripe'") || !str_contains((string)$providerColumn['Type'], "'card'"))) {
            $pdo->exec("ALTER TABLE orders MODIFY payment_provider ENUM('paypal','manual','card') NOT NULL DEFAULT 'manual'");
            $log[] = 'Removed the retired online card provider from the order schema';
        }
        // Schema version 7: the localhost payment simulator stores its orders with the "demo" provider.
        $providerColumn = $pdo->query("SHOW COLUMNS FROM orders LIKE 'payment_provider'")->fetch();
        if ($providerColumn && !str_contains((string)$providerColumn['Type'], "'demo'")) {
            $pdo->exec("ALTER TABLE orders MODIFY payment_provider ENUM('paypal','manual','card','demo') NOT NULL DEFAULT 'manual'");
            $log[] = 'Added the payment simulator provider to the order schema';
        }

        // Give existing orders a starting entry in the audit history.
        $added = $pdo->exec("INSERT INTO order_status_history (order_id, old_status, new_status, note, changed_at)
                             SELECT o.order_id, NULL, o.status, 'Imported from version 2', o.order_date FROM orders o
                             WHERE NOT EXISTS (SELECT 1 FROM order_status_history h WHERE h.order_id = o.order_id)");
        if ($added) {
            $log[] = "Added history for $added existing order(s)";
        }

        // Point products at the optimised photos and tidy old category names.
        $fixed = 0;
        $update = $pdo->prepare('UPDATE products SET image_path = ? WHERE product_id = ?');
        foreach ($pdo->query('SELECT product_id, image_path, category FROM products')->fetchAll() as $product) {
            if (!product_image_exists($product['image_path'])) {
                $resolved = product_image($product['image_path'], (string)$product['category']);
                if ($resolved !== 'assets/images/product-placeholder.svg' && !str_ends_with($resolved, '-thumb.jpg')) {
                    $update->execute([$resolved, $product['product_id']]);
                    $fixed++;
                } elseif (str_ends_with($resolved, '-thumb.jpg')) {
                    $update->execute([str_replace('-thumb.jpg', '.jpg', $resolved), $product['product_id']]);
                    $fixed++;
                }
            }
        }
        if ($fixed) {
            $log[] = "Updated the photo path of $fixed product(s)";
        }
        $categories = $pdo->prepare('UPDATE products SET category = ? WHERE category = ?');
        foreach (['Beanies', 'Beanie', 'Hats & Beanies', 'Blankets', 'Blanket', 'Throws & Blankets', 'Wall Hangers', 'Wall Hanger', 'Coaster', 'Flower', 'Keychain'] as $old) {
            $categories->execute([canonical_product_category($old), $old]);
        }
        $log[] = $log ? 'Upgrade complete.' : 'Database is already up to date. Nothing to change.';
    } catch (Throwable $ex) {
        $errors[] = 'Upgrade stopped: ' . $ex->getMessage() . ' (The database user needs ALTER and CREATE rights; on XAMPP use root.)';
    }
}

if ($cli) {
    echo implode(PHP_EOL, array_merge($log, $errors)) . PHP_EOL;
    exit($errors ? 1 : 0);
}

page_header('Upgrade database');
?>
<section class="form-card narrow">
    <h1>Upgrade database to schema version 7</h1>
    <p>Updates product details, removes retired card checkout, safely cancels its unpaid test orders, and keeps customer and order history.</p>
    <?= render_errors($errors) ?>
    <?php if ($log): ?><div class="alert alert-success"><ul><?php foreach ($log as $line): ?><li><?= e($line) ?></li><?php endforeach; ?></ul></div>
        <a class="button" href="<?= e(url('install_check.php')) ?>">Run the installation check</a>
    <?php else: ?>
        <p><strong>Back up first:</strong> phpMyAdmin &rsaquo; <?= e(DB_NAME) ?> &rsaquo; Export &rsaquo; Go.</p>
        <form method="post"><?= csrf_input() ?><button class="button" type="submit">Upgrade now</button></form>
    <?php endif; ?>
</section>
<?php page_footer(); ?>
