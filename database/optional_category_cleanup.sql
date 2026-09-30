-- OPTIONAL: run this only if your existing database still uses the old demo categories.
-- It does not change the database structure.
UPDATE products SET category = 'Hats' WHERE category IN ('Beanies','Beanie','Hats & Beanies');
UPDATE products SET category = 'Throws' WHERE category IN ('Blankets','Blanket','Throws & Blankets');
UPDATE products SET category = 'Wall Hangings' WHERE category IN ('Wall Hangers','Wall Hanger');
UPDATE products SET category = 'Coasters' WHERE category = 'Coaster';
UPDATE products SET category = 'Flowers' WHERE category = 'Flower';
UPDATE products SET category = 'Keychains' WHERE category = 'Keychain';
