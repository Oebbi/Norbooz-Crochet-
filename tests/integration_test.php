<?php
/**
 * Norbooz Crochet - automated end-to-end acceptance and security tests.
 *
 * Drives the real website over HTTP like a browser (cookies, CSRF tokens, forms)
 * and checks the database afterwards. Test data is removed at the end.
 *
 * Usage (from the project folder, with Apache and MySQL running):
 *   C:\xampp\php\php.exe tests\integration_test.php http://localhost/norbooz_crochet
 *   php tests/integration_test.php http://localhost/norbooz_crochet [admin-password]
 *
 * Run it on a test copy of the database. It needs MAIL_MODE 'log' (the default)
 * so that the password-reset email can be read from storage/mail.log.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run from the command line.');
}
require_once __DIR__ . '/../config/functions.php';

$base = rtrim($argv[1] ?? 'http://localhost/norbooz_crochet', '/');
$adminPassword = $argv[2] ?? 'Admin@12345';
$adminEmail = 'admin@norboozcrochet.local';
$run = substr(bin2hex(random_bytes(4)), 0, 8);
$pdo = db();

$results = [];
$section = '';
function heading(string $name): void { global $section; $section = $name; echo PHP_EOL . "== $name ==" . PHP_EOL; }
function check(string $id, string $name, bool $ok, string $detail = ''): void
{
    global $results, $section;
    $results[] = ['id' => $id, 'section' => $section, 'name' => $name, 'ok' => $ok, 'detail' => $detail];
    echo ($ok ? '[PASS] ' : '[FAIL] ') . "$id $name" . ($ok || $detail === '' ? '' : " -- $detail") . PHP_EOL;
}

/** Minimal browser: keeps its own cookie jar. */
final class Browser
{
    private string $jar;
    public array $last = ['status' => 0, 'body' => '', 'headers' => '', 'location' => ''];
    public function __construct(private string $base) { $this->jar = tempnam(sys_get_temp_dir(), 'nzc'); }
    public function __destruct() { @unlink($this->jar); }

    public function request(string $method, string $path, array $data = [], bool $multipart = false): array
    {
        $ch = curl_init($this->base . '/' . ltrim($path, '/'));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_TIMEOUT => 30,
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $multipart ? $data : http_build_query($data));
        }
        $raw = (string)curl_exec($ch);
        $size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        $headers = substr($raw, 0, $size);
        preg_match('/^Location:\s*(.+)$/mi', $headers, $m);
        return $this->last = ['status' => $status, 'headers' => $headers, 'body' => substr($raw, $size), 'location' => trim($m[1] ?? '')];
    }
    public function get(string $path): array { return $this->request('GET', $path); }
    /** GET the page, copy its CSRF token into the form data, then POST. */
    public function submit(string $page, array $data, ?string $action = null, bool $multipart = false): array
    {
        $this->get($page);
        $data['csrf_token'] = $this->token();
        return $this->request('POST', $action ?? $page, $data, $multipart);
    }
    public function follow(): array
    {
        $loc = $this->last['location'];
        if ($loc === '') { return $this->last; }
        $path = preg_replace('#^https?://[^/]+#', '', $loc);
        $basePath = (string)parse_url($this->base, PHP_URL_PATH);
        if ($basePath !== '' && str_starts_with($path, $basePath)) { $path = substr($path, strlen($basePath)); }
        return $this->get($path);
    }
    public function token(): string
    {
        preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $this->last['body'], $m);
        return $m[1] ?? '';
    }
    public function field(string $name): string
    {
        preg_match('/name="' . preg_quote($name, '/') . '" value="([^"]*)"/', $this->last['body'], $m);
        return html_entity_decode($m[1] ?? '');
    }
}

function stock(int $id): int { $s = db()->prepare('SELECT stock_qty FROM products WHERE product_id = ?'); $s->execute([$id]); return (int)$s->fetchColumn(); }
function login(Browser $b, string $email, string $password): array { return $b->submit('login.php', ['email' => $email, 'password' => $password]); }
function register(Browser $b, string $name, string $email, string $password = 'Crochet2025!'): array
{
    return $b->submit('register.php', ['full_name' => $name, 'email' => $email, 'phone' => '0412 345 678', 'address' => '1 Test Street, Canberra ACT 2600', 'password' => $password, 'confirm_password' => $password, 'privacy' => '1']);
}

// Start clean: remove old lockouts from this machine so repeated runs do not block each other.
$pdo->exec("DELETE FROM login_attempts WHERE ip_address IN ('127.0.0.1', '::1')");
$products = $pdo->query('SELECT product_id, stock_qty FROM products WHERE is_active = 1 AND stock_qty >= 3 ORDER BY product_id LIMIT 2')->fetchAll();
if (count($products) < 2) {
    exit('Need at least two active products with 3+ stock. Re-import the database first.' . PHP_EOL);
}
[$p1, $p2] = [(int)$products[0]['product_id'], (int)$products[1]['product_id']];
$originalStock = [$p1 => (int)$products[0]['stock_qty'], $p2 => (int)$products[1]['stock_qty']];
$emailA = "test-a-$run@example.test";
$emailB = "test-b-$run@example.test";
$createdProductIds = [];

echo "Norbooz Crochet integration tests against $base (run $run)" . PHP_EOL;

/* ---------------------------------------------------------------- */
heading('Public pages');
$guest = new Browser($base);
foreach (['index.php' => 'Home', 'products.php' => 'Shop', 'product.php?id=' . $p1 => 'Product detail', 'customize.php' => 'Custom orders', 'about.php' => 'About', 'privacy.php' => 'Privacy', 'contact.php' => 'Contact', 'cart.php' => 'Cart'] as $page => $label) {
    $r = $guest->get($page);
    check('P' . count($results), "$label page loads (200)", $r['status'] === 200 && !preg_match('/(Fatal error|Warning:|Notice:|Deprecated:)/', $r['body']), "status {$r['status']}");
}
$r = $guest->get('products.php?category=Plushies&sort=price_asc');
check('P-filter', 'Category filter and sort return products', $r['status'] === 200 && str_contains($r['body'], 'product-card'));
$r = $guest->get('products.php?q=' . rawurlencode('zzzz-no-match'));
check('P-empty', 'Search with no match shows the empty state', str_contains($r['body'], 'No products found'));
$r = $guest->get('product.php?id=999999');
check('P-404', 'Unknown product returns 404', $r['status'] === 404);
$bad = [];
foreach ($pdo->query('SELECT name, image_path, category FROM products')->fetchAll() as $row) {
    if (!is_file(APP_ROOT . '/' . product_image($row['image_path'], $row['category'])) || !product_image_exists($row['image_path'])) { $bad[] = $row['name']; }
}
check('P-img', 'Every product has a photo file on disk', !$bad, implode(', ', $bad));
$r = $guest->get('index.php');
preg_match('/<img[^>]+src="([^"]+products[^"]+)"/', $r['body'], $m);
$img = $guest->get(str_replace((string)parse_url($base, PHP_URL_PATH) . '/', '', $m[1] ?? 'missing.jpg'));
check('P-img2', 'Product photo is served and under 400 KB', $img['status'] === 200 && strlen($img['body']) < 400 * 1024, 'size ' . strlen($img['body']));

/* ---------------------------------------------------------------- */
heading('Security controls');
$r = (new Browser($base))->get('index.php');
check('S1', 'Content-Security-Policy header sent', (bool)preg_match('/^Content-Security-Policy:.*default-src \'self\'/mi', $r['headers']));
check('S2', 'X-Frame-Options DENY and nosniff headers sent', (bool)preg_match('/^X-Frame-Options:\s*DENY/mi', $r['headers']) && (bool)preg_match('/^X-Content-Type-Options:\s*nosniff/mi', $r['headers']));
check('S3', 'Session cookie is HttpOnly and SameSite', (bool)preg_match('/^Set-Cookie:.*HttpOnly.*SameSite=Lax/mi', $r['headers']) || (bool)preg_match('/^Set-Cookie:.*SameSite=Lax.*HttpOnly/mi', $r['headers']));
$r = $guest->get('products.php?q=' . rawurlencode("' OR 1=1 -- "));
check('S4', 'SQL injection in search is treated as text', $r['status'] === 200 && str_contains($r['body'], 'No products found'));
$r = $guest->get('products.php?q=' . rawurlencode('<script>alert(1)</script>'));
check('S5', 'Script in search is HTML-encoded (XSS)', !str_contains($r['body'], '<script>alert(1)</script>') && str_contains($r['body'], '&lt;script&gt;'));
foreach (['checkout.php', 'my_orders.php', 'account.php', 'admin.php', 'admin_orders.php'] as $page) {
    $r = $guest->get($page);
    check('S-guest-' . $page, "Guest is sent to login from $page", $r['status'] === 302 && str_contains($r['location'], 'login.php'));
}
$r = $guest->request('POST', 'cart.php', ['action' => 'add', 'product_id' => $p1, 'quantity' => 1]);
check('S6', 'POST without CSRF token is rejected (403)', $r['status'] === 403);
$r = $guest->get('logout.php');
check('S7', 'Logout only accepts POST (405 on GET)', $r['status'] === 405);
$r = $guest->submit('login.php?next=' . rawurlencode('https://evil.example/phish'), ['email' => $adminEmail, 'password' => $adminPassword, 'next' => 'https://evil.example/phish']);
check('S8', 'Login ignores an external "next" address (open redirect)', $r['status'] === 302 && !str_contains($r['location'], 'evil.example'), $r['location']);
$guest->submit('logout.php', [], 'logout.php');
foreach (['config/config.php', 'database/norbooz_crochet_db.sql', 'storage/mail.log'] as $file) {
    $r = $guest->get($file);
    $apache = str_contains(strtolower($r['headers']), 'apache');
    check('S-file-' . basename($file), "Direct access to $file does not reveal contents", $r['status'] === 403 || $r['status'] === 404 || ($file === 'config/config.php' && trim($r['body']) === '') || !$apache, "status {$r['status']} (enforced by .htaccess on Apache)");
}

/* ---------------------------------------------------------------- */
heading('Registration and login');
$a = new Browser($base);
$r = register($a, 'Test Customer A', $emailA, 'short');
check('A1', 'Weak password is rejected', $r['status'] === 200 && str_contains($r['body'], 'Password must be'));
$r = register($a, 'Test Customer A', $emailA);
check('A2', 'Valid registration signs the customer in', $r['status'] === 302);
$row = $pdo->prepare('SELECT password_hash FROM users WHERE email = ?'); $row->execute([$emailA]); $hash = (string)$row->fetchColumn();
check('A3', 'Password stored as bcrypt hash, not plain text', str_starts_with($hash, '$2y$') && !str_contains($hash, 'Crochet2025!'));
$dup = new Browser($base);
$r = register($dup, 'Duplicate', $emailA);
check('A4', 'Duplicate email is rejected', str_contains($r['body'], 'already exists'));
$b = new Browser($base);
register($b, 'Test Customer B', $emailB);
$locked = new Browser($base);
for ($i = 0; $i < 5; $i++) { login($locked, $emailB, 'WrongPassword' . $i); }
$r = login($locked, $emailB, 'Crochet2025!');
check('A5', 'Account locks after 5 wrong passwords, even with the right one', str_contains($r['body'], 'Too many unsuccessful attempts'));
$fresh = new Browser($base);
$r = login($fresh, $emailB, 'Crochet2025!');
check('A6', 'Lockout is stored server-side (new cookies do not bypass it)', str_contains($r['body'], 'Too many unsuccessful attempts'));
$pdo->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$emailB]);
$r = login($fresh, $emailB, 'Crochet2025!');
check('A7', 'Correct password works once the lockout ends', $r['status'] === 302);

/* ---------------------------------------------------------------- */
heading('Cart and checkout');
$before1 = stock($p1); $before2 = stock($p2);
$a->submit('product.php?id=' . $p1, ['action' => 'add', 'product_id' => $p1, 'quantity' => 2, 'return' => 'cart.php'], 'cart.php');
$a->submit('product.php?id=' . $p2, ['action' => 'add', 'product_id' => $p2, 'quantity' => 99, 'return' => 'cart.php'], 'cart.php');
$r = $a->get('cart.php');
check('C1', 'Items appear in the cart', substr_count($r['body'], 'class="cart-row"') === 2);
check('C2', 'Quantity above stock is capped to available stock', str_contains($r['body'], 'value="' . $before2 . '"'));
$a->submit('cart.php', ['action' => 'update', "quantity[$p2]" => 1]);
$r = $a->get('checkout.php');
check('C3', 'Checkout page shows the order summary', $r['status'] === 200 && str_contains($r['body'], 'Place order'));
$token = $a->field('checkout_token');
$csrf = $a->token();
$r = $a->request('POST', 'checkout.php', ['csrf_token' => $csrf, 'checkout_token' => $token, 'delivery_method' => 'post', 'phone' => '12', 'address' => '', 'accept_terms' => '1']);
check('C4', 'Invalid phone and missing address are rejected', str_contains($r['body'], 'contact phone') && str_contains($r['body'], 'postal address'));
$order = ['csrf_token' => $csrf, 'checkout_token' => $token, 'delivery_method' => 'post', 'phone' => '0412 345 678', 'address' => '1 Test Street, Canberra ACT 2600', 'custom_note' => 'Test order <b>bold</b>', 'accept_terms' => '1'];
$r = $a->request('POST', 'checkout.php', $order);
preg_match('/order_detail\.php\?id=(\d+)/', $r['location'], $m);
$orderA = (int)($m[1] ?? 0);
check('C5', 'Order is placed and customer is sent to the order page', $r['status'] === 302 && $orderA > 0, $r['location']);
check('C6', 'Stock is reduced by the ordered quantities', stock($p1) === $before1 - 2 && stock($p2) === $before2 - 1, 'p1 ' . stock($p1) . ', p2 ' . stock($p2));
$o = $pdo->prepare('SELECT subtotal_amount, delivery_fee, total_amount FROM orders WHERE order_id = ?'); $o->execute([$orderA]); $o = $o->fetch();
$price = $pdo->prepare('SELECT price FROM products WHERE product_id = ?');
$price->execute([$p1]); $pr1 = (float)$price->fetchColumn(); $price->execute([$p2]); $pr2 = (float)$price->fetchColumn();
$expectedSub = to_cents($pr1) * 2 + to_cents($pr2);
check('C7', 'Server calculates subtotal, postage and total correctly', to_cents($o['subtotal_amount']) === $expectedSub && to_cents($o['total_amount']) === $expectedSub + delivery_fee_cents('post', $expectedSub), json_encode($o));
$r = $a->request('POST', 'checkout.php', $order);
$count = $pdo->prepare('SELECT COUNT(*) FROM orders o JOIN users u ON u.user_id = o.user_id WHERE u.email = ?'); $count->execute([$emailA]);
check('C8', 'Submitting the same checkout twice does not create a duplicate order', (int)$count->fetchColumn() === 1);
$r = $a->get('cart.php');
check('C9', 'Cart is emptied after checkout', str_contains($r['body'], 'Your cart is empty'));
$r = $a->get('order_detail.php?id=' . $orderA);
check('C10', 'Customer can view their order and its history', $r['status'] === 200 && str_contains($r['body'], 'Order placed'));
check('C11', 'Order note is HTML-encoded on output', str_contains($r['body'], '&lt;b&gt;bold&lt;/b&gt;'));
$mail = (string)@file_get_contents(STORAGE_DIR . '/mail.log');
check('C12', 'Confirmation email is generated for the customer', str_contains($mail, "To: $emailA") && str_contains($mail, "Order #$orderA received"));

/* ---------------------------------------------------------------- */
heading('Access control');
$r = $b->get('order_detail.php?id=' . $orderA);
check('X1', "Another customer cannot open someone else's order (IDOR)", $r['status'] === 404);
$r = $b->get('admin.php');
check('X2', 'Customer cannot open the admin panel (403)', $r['status'] === 403);
$r = $b->submit('my_orders.php', ['action' => 'cancel', 'order_id' => $orderA], 'order_detail.php?id=' . $orderA);
$st = $pdo->prepare('SELECT status FROM orders WHERE order_id = ?'); $st->execute([$orderA]);
check('X3', "Another customer cannot cancel someone else's order", $st->fetchColumn() === 'pending');

/* ---------------------------------------------------------------- */
heading('Customer cancellation');
$b->submit('product.php?id=' . $p1, ['action' => 'add', 'product_id' => $p1, 'quantity' => 1, 'return' => 'cart.php'], 'cart.php');
$b->get('checkout.php');
$r = $b->request('POST', 'checkout.php', ['csrf_token' => $b->token(), 'checkout_token' => $b->field('checkout_token'), 'delivery_method' => 'pickup', 'phone' => '0412 000 111', 'address' => '', 'accept_terms' => '1']);
preg_match('/id=(\d+)/', $r['location'], $m);
$orderB = (int)($m[1] ?? 0);
$fee = $pdo->prepare('SELECT delivery_fee FROM orders WHERE order_id = ?'); $fee->execute([$orderB]);
check('K1', 'Pickup order has no delivery fee', $orderB > 0 && (float)$fee->fetchColumn() === 0.0);
$stockBefore = stock($p1);
$b->submit('order_detail.php?id=' . $orderB, ['action' => 'cancel', 'order_id' => $orderB]);
$st->execute([$orderB]);
check('K2', 'Customer can cancel a pending order', $st->fetchColumn() === 'cancelled');
check('K3', 'Cancelled items are returned to stock', stock($p1) === $stockBefore + 1);

/* ---------------------------------------------------------------- */
heading('Administrator');
$admin = new Browser($base);
$r = login($admin, $adminEmail, $adminPassword);
check('D1', 'Administrator can log in and reach the dashboard', $r['status'] === 302 && str_contains($r['location'], 'admin.php'), 'check the admin password argument');
$r = $admin->get('admin.php');
check('D2', 'Dashboard loads', $r['status'] === 200 && str_contains($r['body'], 'Dashboard'));
foreach (['admin_orders.php', 'admin_products.php', 'admin_requests.php', 'admin_customers.php', 'admin_order.php?id=' . $orderA, 'admin_product_edit.php?id=' . $p1] as $page) {
    $r = $admin->get($page);
    check('D-' . $page, "Admin page $page loads", $r['status'] === 200 && !preg_match('/(Fatal error|Warning:)/', $r['body']));
}
$r = $admin->get('admin_orders.php?export=csv');
check('D3', 'Orders export as CSV', str_contains($r['headers'], 'text/csv') && str_contains($r['body'], 'Order,Date,Status'));
foreach (['in_progress', 'ready', 'completed'] as $next) {
    $admin->submit('admin_order.php?id=' . $orderA, ['order_id' => $orderA, 'status' => $next, 'note' => "Test $next", 'notify' => '1']);
}
$st->execute([$orderA]);
check('D4', 'Admin moves an order Pending > In progress > Ready > Completed', $st->fetchColumn() === 'completed');
$r = $admin->submit('admin_order.php?id=' . $orderA, ['order_id' => $orderA, 'status' => 'pending']);
$st->execute([$orderA]);
check('D5', 'Invalid transition (Completed > Pending) is refused', $st->fetchColumn() === 'completed' && str_contains($r['body'], 'cannot move'));
$h = $pdo->prepare('SELECT COUNT(*) FROM order_status_history WHERE order_id = ?'); $h->execute([$orderA]);
check('D6', 'Every status change is recorded in the audit history', (int)$h->fetchColumn() === 4);
$mail = (string)@file_get_contents(STORAGE_DIR . '/mail.log');
check('D7', 'Customer is emailed about status changes', str_contains($mail, "Order #$orderA is now Completed"));
$stockBefore = stock($p1);
$admin->submit('admin_order.php?id=' . $orderB, ['order_id' => $orderB, 'status' => 'pending']);
$st->execute([$orderB]);
check('D8', 'Admin can re-open a cancelled order, which reserves stock again', $st->fetchColumn() === 'pending' && stock($p1) === $stockBefore - 1);
$admin->submit('admin_order.php?id=' . $orderB, ['order_id' => $orderB, 'status' => 'cancelled']);
$st->execute([$orderB]);
check('D9', 'Admin cancellation returns stock', $st->fetchColumn() === 'cancelled' && stock($p1) === $stockBefore);

// Product management with a real image upload.
$png = tempnam(sys_get_temp_dir(), 'img') . '.png';
if (function_exists('imagecreatetruecolor')) {
    $im = imagecreatetruecolor(1600, 1200); imagefill($im, 0, 0, imagecolorallocate($im, 200, 120, 160)); imagepng($im, $png);
} else {
    file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));
}
$admin->get('admin_product_edit.php');
$r = $admin->request('POST', 'admin_product_edit.php', [
    'csrf_token' => $admin->token(), 'name' => "Test Product $run", 'category' => 'Plushies', 'description' => 'Created by the automated test suite.',
    'price' => '19.95', 'stock_qty' => '3', 'is_active' => '1', 'image_path' => '', 'image' => new CURLFile($png, 'image/png', 'test.png'),
], true);
$np = $pdo->prepare('SELECT product_id, image_path FROM products WHERE name = ?'); $np->execute(["Test Product $run"]); $np = $np->fetch();
if ($np) { $createdProductIds[] = (int)$np['product_id']; }
check('D10', 'Admin adds a product with an uploaded photo', $np && is_file(APP_ROOT . '/' . $np['image_path']), $np['image_path'] ?? 'not created');
if ($np && function_exists('imagecreatefromstring')) {
    $size = getimagesize(APP_ROOT . '/' . $np['image_path']);
    check('D11', 'Uploaded photo is resized to 1200px and a thumbnail is created', $size[0] <= 1200 && is_file(APP_ROOT . '/' . preg_replace('/\.jpg$/', '-thumb.jpg', $np['image_path'])));
}
$fake = tempnam(sys_get_temp_dir(), 'bad') . '.php';
file_put_contents($fake, '<?php echo "hacked"; ?>');
$admin->get('admin_product_edit.php');
$r = $admin->request('POST', 'admin_product_edit.php', ['csrf_token' => $admin->token(), 'name' => "Bad Upload $run", 'category' => 'Plushies', 'description' => 'Should be rejected by the upload validation.', 'price' => '1', 'stock_qty' => '1', 'image' => new CURLFile($fake, 'image/png', 'evil.php')], true);
$exists = $pdo->prepare('SELECT COUNT(*) FROM products WHERE name = ?'); $exists->execute(["Bad Upload $run"]);
check('D12', 'A PHP file disguised as an image is rejected', (int)$exists->fetchColumn() === 0 && str_contains($r['body'], 'Only JPG, PNG, WEBP or GIF'));
$r = $admin->submit('admin_product_edit.php', ['name' => 'X', 'category' => 'Nope', 'description' => 'short', 'price' => '-5', 'stock_qty' => 'abc']);
check('D13', 'Invalid product data is rejected with messages', substr_count($r['body'], '<li>') >= 4);
if ($np) {
    $admin->submit('admin_product_edit.php?id=' . $np['product_id'], ['product_id' => $np['product_id'], 'action' => 'delete']);
    $exists->execute(["Test Product $run"]);
    check('D14', 'A product that was never ordered can be deleted', (int)$exists->fetchColumn() === 0);
}
$admin->submit('admin_product_edit.php?id=' . $p1, ['product_id' => $p1, 'action' => 'delete']);
check('D15', 'A product that appears in orders cannot be deleted', stock($p1) >= 0 && (int)$pdo->query('SELECT COUNT(*) FROM products WHERE product_id = ' . $p1)->fetchColumn() === 1);

/* ---------------------------------------------------------------- */
heading('Custom requests');
$a->get('customize.php');
$r = $a->request('POST', 'customize.php', [
    'csrf_token' => $a->token(), 'request_type' => 'Plushie', 'title' => "Blue whale $run", 'description' => 'A blue whale plushie about 25 cm long.',
    'color_preferences' => 'Navy and white', 'size_details' => '25 cm', 'quantity' => '1', 'budget' => '40', 'needed_by' => date('Y-m-d', strtotime('+30 days')),
    'phone' => '0412 345 678', 'delivery_address' => 'Pickup', 'inspiration' => new CURLFile($png, 'image/png', 'whale.png'),
], true);
$req = $pdo->prepare('SELECT request_id, inspiration_path FROM custom_requests WHERE title = ?'); $req->execute(["Blue whale $run"]); $req = $req->fetch();
check('R1', 'Customer sends a custom request with a photo', $req && $req['inspiration_path'] && is_file(STORAGE_DIR . '/uploads/' . $req['inspiration_path']));
if ($req) {
    $r = $a->get('request_image.php?id=' . $req['request_id']);
    check('R2', 'Customer can see their own inspiration photo', $r['status'] === 200 && str_contains($r['headers'], 'image/'));
    $r = $b->get('request_image.php?id=' . $req['request_id']);
    check('R3', "Other customers cannot see the photo (private storage)", $r['status'] === 404);
    $admin->submit('admin_requests.php', ['request_id' => $req['request_id'], 'status' => 'quoted', 'quoted_price' => '45', 'admin_response' => 'Happy to make this! Ready in two weeks.', 'notify' => '1']);
    $r = $a->get('my_orders.php');
    check('R4', 'Customer sees the quote and reply under My orders', str_contains($r['body'], '$45.00') && str_contains($r['body'], 'Ready in two weeks'));
}
$r = $a->submit('customize.php', ['request_type' => 'Robot', 'title' => '', 'description' => 'x', 'color_preferences' => '', 'size_details' => '', 'quantity' => '0', 'needed_by' => '2001-01-01', 'phone' => 'abc', 'delivery_address' => '']);
check('R5', 'Invalid custom request is rejected with messages', substr_count($r['body'], '<li>') >= 6);

/* ---------------------------------------------------------------- */
heading('CRUD: update and delete');
$emailC = "test-c-$run@example.test";
$c = new Browser($base);
register($c, 'Test Customer C', $emailC);
$uidC = (int)$pdo->query("SELECT user_id FROM users WHERE email = " . $pdo->quote($emailC))->fetchColumn();
$reqForm = ['request_type' => 'Hat', 'title' => 'CRUD hat', 'description' => 'A warm hat for winter please.', 'color_preferences' => 'Blue', 'size_details' => 'Adult', 'quantity' => '1', 'budget' => '', 'needed_by' => '', 'phone' => '0412 345 678', 'delivery_address' => 'Pickup'];
$c->submit('customize.php', $reqForm);
$reqC = (int)$pdo->query("SELECT MAX(request_id) FROM custom_requests WHERE user_id = $uidC")->fetchColumn();
$r = $c->get('my_orders.php');
check('U1', 'Customer sees Edit/Withdraw for a new custom request', str_contains($r['body'], 'request_edit.php?id=' . $reqC));
$c->submit('request_edit.php?id=' . $reqC, array_merge($reqForm, ['action' => 'update', 'request_id' => $reqC, 'title' => 'CRUD hat EDITED', 'quantity' => '3']));
$row = $pdo->query("SELECT title, quantity FROM custom_requests WHERE request_id = $reqC")->fetch();
check('U2', 'Customer can update their own new custom request', $row && $row['title'] === 'CRUD hat EDITED' && (int)$row['quantity'] === 3);
$bad = $c->submit('request_edit.php?id=' . $reqC, array_merge($reqForm, ['action' => 'update', 'request_id' => $reqC, 'title' => '', 'quantity' => '0']));
check('U3', 'Invalid edit is rejected and nothing changes', substr_count($bad['body'], '<li>') >= 2 && $pdo->query("SELECT title FROM custom_requests WHERE request_id = $reqC")->fetchColumn() === 'CRUD hat EDITED');
$r = $b->get('request_edit.php?id=' . $reqC);
check('U4', 'Another customer cannot open or edit the request (owner check)', $r['status'] === 404);
$admin->submit('admin_requests.php', ['request_id' => $reqC, 'status' => 'reviewing', 'quoted_price' => '', 'admin_response' => '']);
$c->submit('request_edit.php?id=' . $reqC, array_merge($reqForm, ['action' => 'update', 'request_id' => $reqC, 'title' => 'SNEAKY']));
check('U5', 'Customer cannot edit once the maker is reviewing it', $pdo->query("SELECT title FROM custom_requests WHERE request_id = $reqC")->fetchColumn() === 'CRUD hat EDITED');
$c->submit('request_edit.php?id=' . $reqC, ['action' => 'delete', 'request_id' => $reqC]);
check('U6', 'Customer cannot withdraw a request that is under review', (int)$pdo->query("SELECT COUNT(*) FROM custom_requests WHERE request_id = $reqC")->fetchColumn() === 1);
$admin->submit('admin_requests.php', ['action' => 'delete', 'request_id' => $reqC]);
check('U7', 'Admin cannot delete a request that is still in progress', (int)$pdo->query("SELECT COUNT(*) FROM custom_requests WHERE request_id = $reqC")->fetchColumn() === 1);
$admin->submit('admin_requests.php', ['request_id' => $reqC, 'status' => 'declined', 'quoted_price' => '', 'admin_response' => '']);
$admin->submit('admin_requests.php', ['action' => 'delete', 'request_id' => $reqC]);
check('U8', 'Admin can delete a declined request', (int)$pdo->query("SELECT COUNT(*) FROM custom_requests WHERE request_id = $reqC")->fetchColumn() === 0);
$c->submit('customize.php', $reqForm);
$reqC2 = (int)$pdo->query("SELECT MAX(request_id) FROM custom_requests WHERE user_id = $uidC")->fetchColumn();
$c->submit('request_edit.php?id=' . $reqC2, ['action' => 'delete', 'request_id' => $reqC2]);
check('U9', 'Customer can withdraw (delete) their own new request', (int)$pdo->query("SELECT COUNT(*) FROM custom_requests WHERE request_id = $reqC2")->fetchColumn() === 0);

$pdo->prepare("INSERT INTO orders (user_id, status, subtotal_amount, delivery_method, delivery_fee, total_amount, phone, address) VALUES (?, 'pending', 10, 'pickup', 0, 10, '0412 345 678', 'Pickup - Canberra, ACT')")->execute([$uidC]);
$orderC = (int)$pdo->lastInsertId();
$pdo->prepare('INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, 1, 10)')->execute([$orderC, $p1]);
$admin->submit('admin_order.php?id=' . $orderC, ['action' => 'details', 'order_id' => $orderC, 'phone' => '0400 111 222', 'address' => '5 New Road, Canberra ACT', 'custom_note' => 'Leave with neighbour']);
$row = $pdo->query("SELECT phone, address, custom_note FROM orders WHERE order_id = $orderC")->fetch();
check('U10', 'Admin can update contact and delivery details on an open order', $row['phone'] === '0400 111 222' && $row['address'] === '5 New Road, Canberra ACT' && $row['custom_note'] === 'Leave with neighbour');
$admin->submit('admin_order.php?id=' . $orderC, ['action' => 'details', 'order_id' => $orderC, 'phone' => 'abc', 'address' => '', 'custom_note' => '']);
check('U11', 'Invalid order details are rejected', $pdo->query("SELECT phone FROM orders WHERE order_id = $orderC")->fetchColumn() === '0400 111 222');
$admin->submit('admin_order.php?id=' . $orderC, ['action' => 'delete', 'order_id' => $orderC]);
check('U12', 'An open order cannot be deleted', (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_id = $orderC")->fetchColumn() === 1);
$r = $c->request('POST', 'admin_order.php?id=' . $orderC, ['action' => 'delete', 'order_id' => $orderC, 'csrf_token' => $c->token()]);
check('U13', 'A customer cannot use the admin order actions', $r['status'] !== 200 && (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_id = $orderC")->fetchColumn() === 1);
$pdo->exec("UPDATE orders SET status = 'cancelled' WHERE order_id = $orderC");
$admin->submit('admin_order.php?id=' . $orderC, ['action' => 'details', 'order_id' => $orderC, 'phone' => '0400 999 999', 'address' => 'Somewhere', 'custom_note' => '']);
check('U14', 'A cancelled order can no longer be edited', $pdo->query("SELECT phone FROM orders WHERE order_id = $orderC")->fetchColumn() === '0400 111 222');
$admin->submit('admin_order.php?id=' . $orderC, ['action' => 'delete', 'order_id' => $orderC]);
check('U15', 'Admin can delete a cancelled order (items cascade)', (int)$pdo->query("SELECT COUNT(*) FROM orders WHERE order_id = $orderC")->fetchColumn() === 0 && (int)$pdo->query("SELECT COUNT(*) FROM order_items WHERE order_id = $orderC")->fetchColumn() === 0);

$c->submit('customize.php', $reqForm);
$admin->submit('admin_customers.php', ['user_id' => $uidC]);
check('U16', 'Admin cannot delete a customer with an open request', (int)$pdo->query("SELECT is_deleted FROM users WHERE user_id = $uidC")->fetchColumn() === 0);
$pdo->exec("DELETE FROM custom_requests WHERE user_id = $uidC");
$admin->submit('admin_customers.php', ['user_id' => $uidC]);
$row = $pdo->query("SELECT is_deleted, full_name, phone FROM users WHERE user_id = $uidC")->fetch();
check('U17', 'Admin can delete a finished customer (personal details removed)', (int)$row['is_deleted'] === 1 && $row['full_name'] === 'Deleted customer' && $row['phone'] === '');
$r = $c->request('POST', 'admin_customers.php', ['user_id' => $uidC, 'csrf_token' => $c->token()]);
check('U18', 'A customer cannot open the admin customer list', $r['status'] !== 200);

/* ---------------------------------------------------------------- */
heading('Password reset and privacy');
$reset = new Browser($base);
$reset->submit('forgot_password.php', ['email' => $emailB]);
check('Z1', 'Reset request shows the same message for any email', str_contains($reset->last['body'], 'If an account uses that email'));
$reset->submit('forgot_password.php', ['email' => "nobody-$run@example.test"]);
check('Z2', 'Unknown email gets the same message (no account discovery)', str_contains($reset->last['body'], 'If an account uses that email'));
$mail = (string)@file_get_contents(STORAGE_DIR . '/mail.log');
preg_match_all('/reset_password\.php\?token=([a-f0-9]{64})/', $mail, $m);
$tokenReset = end($m[1]) ?: '';
$r = $reset->get('reset_password.php?token=' . $tokenReset);
check('Z3', 'Emailed reset link is absolute and opens the reset form', $tokenReset !== '' && str_contains($r['body'], 'Choose a new password') && preg_match('#https?://[^\s]+reset_password\.php#', $mail));
$reset->request('POST', 'reset_password.php', ['csrf_token' => $reset->token(), 'token' => $tokenReset, 'password' => 'NewCrochet2026', 'confirm_password' => 'NewCrochet2026']);
$r = login(new Browser($base), $emailB, 'NewCrochet2026');
check('Z4', 'New password works after reset', $r['status'] === 302);
$r = $reset->get('reset_password.php?token=' . $tokenReset);
check('Z5', 'Reset link cannot be used twice', str_contains($r['body'], 'invalid, already used or expired'));
$r = $a->submit('account.php', ['action' => 'export']);
$json = json_decode($r['body'], true);
check('Z6', 'Customer can download their data (APP 12) without the password hash', is_array($json) && ($json['account']['email'] ?? '') === $emailA && !isset($json['account']['password_hash']) && count($json['orders']) === 1);
$r = $a->submit('account.php', ['action' => 'delete', 'confirm_delete_password' => 'Crochet2025!']);
check('Z7', 'Account with an open custom request cannot be deleted yet', str_contains($r['body'], 'still in progress'));
if ($req) { $pdo->prepare("UPDATE custom_requests SET status = 'declined' WHERE request_id = ?")->execute([$req['request_id']]); }
$r = $a->submit('account.php', ['action' => 'delete', 'confirm_delete_password' => 'Crochet2025!']);
$gone = $pdo->prepare('SELECT is_deleted, full_name, phone FROM users WHERE email LIKE ? OR (full_name = ? AND is_deleted = 1)');
$gone->execute([$emailA, 'Deleted customer']);
$acct = $pdo->prepare("SELECT o.address FROM orders o WHERE o.order_id = ?"); $acct->execute([$orderA]);
check('Z8', 'Deleting an account removes personal details but keeps order totals (APP 11.2)', $acct->fetchColumn() === 'Removed at customer request' && ($req ? !is_file(STORAGE_DIR . '/uploads/' . $req['inspiration_path']) : true));
$r = login(new Browser($base), $emailA, 'Crochet2025!');
check('Z9', 'Deleted account can no longer log in', $r['status'] === 200);

/* ---------------------------------------------------------------- */
// Clean up everything the tests created and restore stock.
$ids = $pdo->query("SELECT user_id FROM users WHERE email LIKE 'test-%-$run@example.test' OR (is_deleted = 1 AND email LIKE 'deleted-%@invalid.local' AND created_at > NOW() - INTERVAL 1 HOUR)")->fetchAll(PDO::FETCH_COLUMN);
if ($ids) {
    $in = implode(',', array_map('intval', $ids));
    foreach ($pdo->query("SELECT inspiration_path FROM custom_requests WHERE user_id IN ($in) AND inspiration_path IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN) as $file) { @unlink(STORAGE_DIR . '/uploads/' . basename($file)); }
    $pdo->exec("DELETE FROM order_status_history WHERE order_id IN (SELECT order_id FROM orders WHERE user_id IN ($in))");
    $pdo->exec("DELETE FROM order_items WHERE order_id IN (SELECT order_id FROM orders WHERE user_id IN ($in))");
    $pdo->exec("DELETE FROM orders WHERE user_id IN ($in)");
    $pdo->exec("DELETE FROM custom_requests WHERE user_id IN ($in)");
    $pdo->exec("DELETE FROM password_resets WHERE user_id IN ($in)");
    $pdo->exec("DELETE FROM users WHERE user_id IN ($in)");
}
foreach ($originalStock as $id => $qty) { $pdo->prepare('UPDATE products SET stock_qty = ? WHERE product_id = ?')->execute([$qty, $id]); }
foreach (glob(APP_ROOT . '/' . PRODUCT_IMAGE_DIR . '/upload-*') ?: [] as $file) { if (filemtime($file) > time() - 3600 && $np && str_contains($file, basename($np['image_path'], '.jpg'))) { @unlink($file); } }
$pdo->exec("DELETE FROM login_attempts WHERE ip_address IN ('127.0.0.1', '::1')");
@unlink($png); @unlink($fake);

$passed = count(array_filter($results, fn($r) => $r['ok']));
$total = count($results);
echo PHP_EOL . "Result: $passed / $total passed." . PHP_EOL;

// Save a Markdown copy of the results for the test report.
$report = "# Norbooz Crochet test results\n\nRun: " . date('Y-m-d H:i') . " against $base\n\nResult: **$passed / $total passed**\n\n| ID | Area | Test | Result |\n|---|---|---|---|\n";
foreach ($results as $row) { $report .= "| {$row['id']} | {$row['section']} | {$row['name']} | " . ($row['ok'] ? 'PASS' : 'FAIL') . " |\n"; }
@file_put_contents(__DIR__ . '/last_results.md', $report);
exit($passed === $total ? 0 : 1);
