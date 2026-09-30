<?php
require_once __DIR__ . '/config/functions.php';

// Read and validate the filters from the address bar.
$search = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 80));
$category = (string)($_GET['category'] ?? '');
if (!in_array($category, product_categories(), true)) {
    $category = '';
}
$sortOptions = [
    'featured' => ['Featured', 'stock_qty > 0 DESC, product_id DESC'],
    'newest' => ['Newest', 'product_id DESC'],
    'price_asc' => ['Price: low to high', 'price ASC, name ASC'],
    'price_desc' => ['Price: high to low', 'price DESC, name ASC'],
    'name' => ['Name A-Z', 'name ASC'],
];
$sort = array_key_exists((string)($_GET['sort'] ?? ''), $sortOptions) ? (string)$_GET['sort'] : 'featured';
$inStockOnly = ($_GET['in_stock'] ?? '') === '1';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;

// Build the query from fixed SQL fragments; user values are only ever bound as parameters.
$where = ['is_active = 1'];
$params = [];
if ($search !== '') {
    $where[] = '(name LIKE ? OR description LIKE ? OR category LIKE ?)';
    $like = '%' . addcslashes($search, '%_\\') . '%';
    array_push($params, $like, $like, $like);
}
if ($category !== '') {
    $values = category_database_values($category);
    $where[] = 'category IN (' . implode(',', array_fill(0, count($values), '?')) . ')';
    array_push($params, ...$values);
}
if ($inStockOnly) {
    $where[] = 'stock_qty > 0';
}
$whereSql = implode(' AND ', $where);

$products = [];
$total = 0;
try {
    $count = db()->prepare("SELECT COUNT(*) FROM products WHERE $whereSql");
    $count->execute($params);
    $total = (int)$count->fetchColumn();
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    $offset = ($page - 1) * $perPage;

    $stmt = db()->prepare(
        "SELECT product_id, name, category, price, stock_qty, image_path
         FROM products WHERE $whereSql
         ORDER BY {$sortOptions[$sort][1]}
         LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $products = $stmt->fetchAll();
} catch (Throwable $ex) {
    $pages = 1;
    flash('error', 'Products could not be loaded. Please check the database installation.');
}

$query = array_filter(['q' => $search, 'category' => $category, 'sort' => $sort !== 'featured' ? $sort : '', 'in_stock' => $inStockOnly ? '1' : ''], fn($v) => $v !== '');
$heading = $category !== '' ? $category : ($search !== '' ? 'Search results' : 'All products');

page_header($heading, 'Shop handmade crochet ' . strtolower($category ?: 'gifts') . ' from Norbooz Crochet.');
?>
<section class="page-heading">
    <p class="eyebrow">The shop</p>
    <h1><?= e($heading) ?></h1>
    <p><?= $total ?> handmade item<?= $total === 1 ? '' : 's' ?><?= $search !== '' ? ' matching &ldquo;' . e($search) . '&rdquo;' : '' ?>. Can't find what you want? <a href="<?= e(url('customize.php')) ?>">Request a custom piece</a>.</p>
</section>

<form class="filter-bar" method="get" action="<?= e(url('products.php')) ?>" role="search">
    <div class="filter-search">
        <label for="q" class="sr-only">Search products</label>
        <input id="q" type="search" name="q" value="<?= e($search) ?>" placeholder="Search plushies, bags, flowers..." maxlength="80">
    </div>
    <div>
        <label for="category" class="sr-only">Product family</label>
        <select id="category" name="category">
            <option value="">All families</option>
            <?php foreach (product_categories() as $option): ?>
                <option value="<?= e($option) ?>" <?= $option === $category ? 'selected' : '' ?>><?= e($option) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="sort" class="sr-only">Sort by</label>
        <select id="sort" name="sort">
            <?php foreach ($sortOptions as $key => [$label]): ?>
                <option value="<?= e($key) ?>" <?= $key === $sort ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <label class="checkbox"><input type="checkbox" name="in_stock" value="1" <?= $inStockOnly ? 'checked' : '' ?>> In stock only</label>
    <button class="button button-small" type="submit">Apply</button>
    <?php if ($query): ?><a class="text-link small" href="<?= e(url('products.php')) ?>">Clear</a><?php endif; ?>
</form>

<nav class="chip-row" aria-label="Quick filters">
    <a class="chip" href="<?= e(url('products.php')) ?>"<?= $category === '' ? ' aria-current="true"' : '' ?>>All</a>
    <?php foreach (product_categories() as $option): ?>
        <a class="chip" href="<?= e(category_url($option)) ?>"<?= $option === $category ? ' aria-current="true"' : '' ?>><?= e($option) ?></a>
    <?php endforeach; ?>
</nav>

<?php if (!$products): ?>
    <div class="empty-state">
        <h2>No products found</h2>
        <p>Try a different search or family, or tell us what you are looking for and we can make it.</p>
        <div class="actions center"><a class="button button-secondary" href="<?= e(url('products.php')) ?>">Show all products</a><a class="button" href="<?= e(url('customize.php')) ?>">Request a custom piece</a></div>
    </div>
<?php else: ?>
    <h2 class="sr-only">Products</h2>
    <div class="product-grid">
        <?php foreach ($products as $product) { include __DIR__ . '/partials/product_card.php'; } ?>
    </div>
    <?= pagination($page, $pages, $query, 'products.php') ?>
<?php endif; ?>
<?php page_footer(); ?>
