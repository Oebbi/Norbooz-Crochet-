<?php
require_once __DIR__ . '/config.php';

/**
 * Shared helpers for Norbooz Crochet.
 * Every page includes this file first. It sends security headers, starts a hardened
 * session and provides output-encoding, CSRF, authentication, cart, image, email and layout helpers.
 */

date_default_timezone_set('Australia/Sydney');

/* ---------------------------------------------------------------------------
 * HTTP security headers and session
 * ------------------------------------------------------------------------- */

function is_https_request(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

function send_security_headers(): void
{
    if (headers_sent() || PHP_SAPI === 'cli') {
        return;
    }
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'");
    if (is_https_request()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('norbooz_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => (BASE_URL === '' ? '/' : BASE_URL . '/'),
        'domain' => '',
        'secure' => is_https_request(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Idle timeout: signed-in users are logged out after SESSION_IDLE_MINUTES of inactivity.
    $now = time();
    if (!empty($_SESSION['user_id']) && !empty($_SESSION['last_activity'])
        && ($now - (int)$_SESSION['last_activity']) > SESSION_IDLE_MINUTES * 60) {
        $cart = $_SESSION['cart'] ?? [];
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['cart'] = $cart;
        $_SESSION['flash_info'] = 'You were signed out after ' . SESSION_IDLE_MINUTES . ' minutes of inactivity. Please log in again.';
    }
    $_SESSION['last_activity'] = $now;
}

send_security_headers();
if (PHP_SAPI !== 'cli') {
    start_secure_session();
} elseif (!isset($_SESSION)) {
    $_SESSION = [];
}

/* ---------------------------------------------------------------------------
 * Output, URLs and redirects
 * ------------------------------------------------------------------------- */

function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    $base = rtrim(BASE_URL, '/');
    if ($path === '') {
        return $base . '/';
    }
    // Encode spaces and other unsafe characters in file paths, but keep query strings intact.
    [$file, $query] = array_pad(explode('?', $path, 2), 2, null);
    $file = implode('/', array_map('rawurlencode', explode('/', ltrim($file, '/'))));
    return $base . '/' . $file . ($query !== null ? '?' . $query : '');
}

/** Absolute URL for emails. Uses APP_URL on a live site; otherwise a validated Host header. */
function absolute_url(string $path = ''): string
{
    if (APP_URL !== '') {
        return rtrim(APP_URL, '/') . '/' . ltrim(substr(url($path), strlen(rtrim(BASE_URL, '/'))), '/');
    }
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (!preg_match('/^[a-z0-9.-]+(:\d+)?$/i', $host)) {
        $host = 'localhost';
    }
    return (is_https_request() ? 'https' : 'http') . '://' . $host . url($path);
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

/**
 * Only allow redirects back to pages on this site (prevents open-redirect attacks).
 */
function safe_next(?string $next, string $default = 'index.php'): string
{
    $next = trim((string)$next);
    if ($next === '' || preg_match('#^(?:[a-z][a-z0-9+.-]*:|//|\\\\)#i', $next) || str_contains($next, "\n") || str_contains($next, "\r")) {
        return $default;
    }
    if (!preg_match('#^[a-z_]+\.php(\?[A-Za-z0-9_=&%.\-]*)?$#', ltrim($next, '/'))) {
        return $default;
    }
    return ltrim($next, '/');
}

function current_page(): string
{
    return basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
}

function current_request_path(): string
{
    $query = (string)($_SERVER['QUERY_STRING'] ?? '');
    return current_page() . ($query !== '' ? '?' . $query : '');
}

function client_ip(): string
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function is_local_request(): bool
{
    return in_array(client_ip(), ['127.0.0.1', '::1'], true);
}

/* ---------------------------------------------------------------------------
 * CSRF protection
 * ------------------------------------------------------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string)$_SESSION['csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = (string)($_POST['csrf_token'] ?? '');
    if ($token === '' || !hash_equals((string)($_SESSION['csrf_token'] ?? ''), $token)) {
        http_response_code(403);
        page_header('Request expired');
        echo '<section class="form-card narrow"><h1>Request expired</h1><p>Your session token did not match. This protects you from forged requests. Please go back, refresh the page and try again.</p><a class="button" href="' . e(url('index.php')) . '">Return home</a></section>';
        page_footer();
        exit;
    }
}

function require_post(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('Method not allowed.');
    }
    verify_csrf();
}

/* ---------------------------------------------------------------------------
 * Authentication and authorisation
 * ------------------------------------------------------------------------- */

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'user_id' => (int)$_SESSION['user_id'],
        'full_name' => (string)($_SESSION['full_name'] ?? ''),
        'role' => (string)($_SESSION['role'] ?? 'customer'),
    ];
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function sign_in_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['user_id'];
    $_SESSION['full_name'] = (string)$user['full_name'];
    $_SESSION['role'] = (string)$user['role'];
    $_SESSION['last_activity'] = time();
    unset($_SESSION['csrf_token']);
}

function require_login(): void
{
    if (!current_user()) {
        flash('info', 'Please log in to continue.');
        redirect('login.php?next=' . rawurlencode(current_request_path()));
    }
}

function require_customer(): void
{
    require_login();
    if (is_admin()) {
        flash('info', 'Administrator accounts manage the shop. Use a customer account to place orders.');
        redirect('admin.php');
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        page_header('Access denied');
        echo '<section class="form-card narrow"><h1>Access denied</h1><p>This area is for the shop administrator only.</p><a class="button" href="' . e(url('index.php')) . '">Return home</a></section>';
        page_footer();
        exit;
    }
}

function not_found(string $message = 'The page you requested could not be found.'): never
{
    http_response_code(404);
    page_header('Not found');
    echo '<section class="form-card narrow"><h1>Not found</h1><p>' . e($message) . '</p><a class="button" href="' . e(url('products.php')) . '">Browse the shop</a></section>';
    page_footer();
    exit;
}

/* Login throttling is stored in the database so clearing cookies does not reset it. */

function login_block_seconds(string $email): int
{
    $window = LOGIN_WINDOW_MINUTES;
    $stmt = db()->prepare(
        "SELECT
            SUM(email = ?) AS email_failures,
            COUNT(*) AS ip_failures,
            MAX(attempted_at) AS last_attempt
         FROM login_attempts
         WHERE success = 0 AND (email = ? OR ip_address = ?)
           AND attempted_at > (NOW() - INTERVAL $window MINUTE)"
    );
    $stmt->execute([$email, $email, client_ip()]);
    $row = $stmt->fetch() ?: [];
    $emailFailures = (int)($row['email_failures'] ?? 0);
    $ipFailures = (int)($row['ip_failures'] ?? 0);
    if ($emailFailures < LOGIN_MAX_FAILURES && $ipFailures < LOGIN_MAX_IP_FAILURES) {
        return 0;
    }
    $last = strtotime((string)$row['last_attempt']) ?: time();
    return max(1, ($last + $window * 60) - time());
}

function record_login_attempt(string $email, bool $success): void
{
    db()->prepare('INSERT INTO login_attempts (email, ip_address, success) VALUES (?, ?, ?)')
        ->execute([mb_substr($email, 0, 190), client_ip(), $success ? 1 : 0]);
    if ($success) {
        // A successful login clears earlier failures for that email.
        db()->prepare('DELETE FROM login_attempts WHERE email = ? AND success = 0')->execute([$email]);
    }
    // Keep the table small: remove records older than one day.
    if (random_int(1, 20) === 1) {
        db()->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
    }
}

function password_problems(string $password, string $confirm): array
{
    $errors = [];
    if (strlen($password) < 10 || strlen($password) > 72) {
        $errors[] = 'Password must be between 10 and 72 characters.';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        $errors[] = 'Password must include at least one letter and one number.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Passwords do not match.';
    }
    return $errors;
}

/* ---------------------------------------------------------------------------
 * Flash messages
 * ------------------------------------------------------------------------- */

function flash(string $type, string $message): void
{
    $_SESSION['flash_' . $type] = $message;
}

function render_flash(): string
{
    $html = '';
    foreach (['success', 'error', 'info'] as $type) {
        $key = 'flash_' . $type;
        if (!empty($_SESSION[$key])) {
            $html .= '<div class="alert alert-' . e($type) . '" role="' . ($type === 'error' ? 'alert' : 'status') . '">' . e($_SESSION[$key]) . '</div>';
            unset($_SESSION[$key]);
        }
    }
    return $html;
}

function render_errors(array $errors): string
{
    if (!$errors) {
        return '';
    }
    $items = implode('', array_map(fn($error) => '<li>' . e($error) . '</li>', $errors));
    return '<div class="alert alert-error" role="alert"><ul>' . $items . '</ul></div>';
}

/* ---------------------------------------------------------------------------
 * Formatting and validation helpers
 * ------------------------------------------------------------------------- */

function normalise_email(string $email): string
{
    return strtolower(trim($email));
}

/** Money is calculated in whole cents to avoid floating-point rounding errors. */
function to_cents(float|string|int $amount): int
{
    return (int)round(((float)$amount) * 100);
}

function cents_to_decimal(int $cents): string
{
    return number_format($cents / 100, 2, '.', '');
}

function money(float|string|int $amount): string
{
    return '$' . number_format((float)$amount, 2);
}

function valid_phone(string $phone): bool
{
    return (bool)preg_match('/^\+?[0-9 ()-]{8,20}$/', $phone);
}

function format_date(?string $value, bool $withTime = true): string
{
    if (!$value) {
        return '';
    }
    $time = strtotime($value);
    return $time ? date($withTime ? 'j M Y, g:i a' : 'j M Y', $time) : '';
}

function text_length_ok(string $value, int $min, int $max): bool
{
    $length = mb_strlen($value);
    return $length >= $min && $length <= $max;
}

/* ---------------------------------------------------------------------------
 * Product families
 * ------------------------------------------------------------------------- */

function product_categories(): array
{
    return ['Plushies', 'Throws', 'Bags', 'Hats', 'Keychains', 'Flowers', 'Wall Hangings', 'Coasters'];
}

/** Converts older demo labels into the final product-family names. */
function canonical_product_category(string $category): string
{
    $map = [
        'Beanies' => 'Hats', 'Beanie' => 'Hats', 'Hats & Beanies' => 'Hats',
        'Blankets' => 'Throws', 'Blanket' => 'Throws', 'Throws & Blankets' => 'Throws',
        'Wall Hangers' => 'Wall Hangings', 'Wall Hanger' => 'Wall Hangings',
        'Coaster' => 'Coasters', 'Flower' => 'Flowers', 'Keychain' => 'Keychains',
    ];
    $category = trim($category);
    return $map[$category] ?? $category;
}

function category_database_values(string $category): array
{
    return match ($category) {
        'Hats' => ['Hats', 'Beanies', 'Beanie', 'Hats & Beanies'],
        'Throws' => ['Throws', 'Blankets', 'Blanket', 'Throws & Blankets'],
        'Wall Hangings' => ['Wall Hangings', 'Wall Hangers', 'Wall Hanger'],
        'Coasters' => ['Coasters', 'Coaster'],
        'Flowers' => ['Flowers', 'Flower'],
        'Keychains' => ['Keychains', 'Keychain'],
        default => [$category],
    };
}

function category_url(string $category): string
{
    return url('products.php?category=' . rawurlencode($category));
}

/** Photo shown on each product-family card on the home page. */
function category_image(string $category): string
{
    $images = [
        'Plushies' => 'penguin-family', 'Throws' => 'granny-square-throw', 'Bags' => 'granny-square-bag',
        'Hats' => 'blue-beanie', 'Keychains' => 'friendship-keychains', 'Flowers' => 'rose-bouquet',
        'Wall Hangings' => 'cat-yarn-wall-hanging', 'Coasters' => 'flower-coasters',
    ];
    $slug = $images[$category] ?? '';
    return $slug !== '' ? PRODUCT_IMAGE_DIR . '/' . $slug . '-thumb.jpg' : 'assets/images/product-placeholder.svg';
}

/* ---------------------------------------------------------------------------
 * Product images
 * ------------------------------------------------------------------------- */

/**
 * Validates an image path typed by the administrator: local images folder only,
 * no "..", no remote URLs.
 */
function safe_product_image_path(string $path): string
{
    $path = trim(str_replace('\\', '/', $path));
    $path = ltrim($path, '/');
    if (preg_match('#^assets/images(?:/[A-Za-z0-9 ._-]+)+\.(svg|png|jpe?g|gif|webp)$#i', $path) && !str_contains($path, '..')) {
        return $path;
    }
    return 'assets/images/product-placeholder.svg';
}

/**
 * Filenames used before the photos were optimised. Existing databases that still
 * point to these names are automatically shown the new optimised photo.
 */
function legacy_image_aliases(): array
{
    return [
        '2-kitten' => 'kitten-pair', 'airpod-pouch' => 'airpod-pouch', 'baby-doggies' => 'blue-puppy',
        'baby-duckies' => 'duckies-in-hats', 'bag' => 'pink-shoulder-bag', 'bunny' => 'bunny-plush',
        'capibara-gang' => 'capybara-keychains', 'coaster' => 'flower-coasters', 'hanging-flowers' => 'hanging-plant-baskets',
        'hat' => 'blue-beanie', 'jelly-fish' => 'pink-jellyfish', 'kitten-group' => 'kitten-group',
        'piggy' => 'piggy-plush', 'turtles' => 'turtle-pair', 'boquet' => 'rose-bouquet',
        'dolphi-key-chain' => 'dolphin-keychain', 'friendship-key-chain' => 'friendship-keychains',
        'kitten-wall-hanger' => 'cat-yarn-wall-hanging', 'mifi-keychain' => 'bunny-keychain',
        'mistty-keychain' => 'misty-keychain', 'octopus-group' => 'octopus-trio', 'penguin-group' => 'penguin-family',
        'pikachu-wall-hanger' => 'character-wall-hanging', 'puppies' => 'puppy-trio', 'shoulder-bag' => 'granny-square-bag',
        'snorlax' => 'sleepy-character-plush', 'throw-2' => 'granny-square-throw', 'throw' => 'pink-bobble-throw',
        'wall-hanger' => 'floral-wall-hanging',
        'demo-plushie' => 'kitten-group', 'demo-throw' => 'granny-square-throw', 'demo-bag' => 'granny-square-bag',
        'demo-hat' => 'blue-beanie', 'demo-keychain' => 'friendship-keychains', 'demo-flower' => 'rose-bouquet',
        'demo-wall-hanging' => 'cat-yarn-wall-hanging', 'demo-coasters' => 'flower-coasters',
    ];
}

function image_slug(string $fileName): string
{
    $name = strtolower(basename(str_replace('\\', '/', $fileName)));
    $name = preg_replace('/(\.(png|jpe?g|gif|webp|svg))+$/i', '', $name);
    $name = preg_replace('/[^a-z0-9]+/', '-', $name);
    return trim((string)$name, '-');
}

/**
 * Returns the path of an image that really exists on disk for this product.
 * Order: stored path -> optimised photo with the same name -> legacy alias -> category photo -> placeholder.
 * $thumb = true returns the 600px thumbnail when one exists (used on grids for fast loading).
 */
function product_image(?string $storedPath, string $category = '', bool $thumb = false): string
{
    $root = APP_ROOT . '/';
    $path = safe_product_image_path((string)$storedPath);
    $candidates = [];

    if ($path !== 'assets/images/product-placeholder.svg') {
        if ($thumb && preg_match('/\.jpe?g$/i', $path) && !str_ends_with($path, '-thumb.jpg')) {
            $candidates[] = preg_replace('/\.jpe?g$/i', '-thumb.jpg', $path);
        }
        $candidates[] = $path;
    }

    $slug = image_slug((string)$storedPath);
    if ($slug !== '') {
        $aliases = legacy_image_aliases();
        foreach (array_unique([$slug, $aliases[$slug] ?? $slug]) as $name) {
            if ($thumb) {
                $candidates[] = PRODUCT_IMAGE_DIR . '/' . $name . '-thumb.jpg';
            }
            $candidates[] = PRODUCT_IMAGE_DIR . '/' . $name . '.jpg';
        }
    }
    if ($category !== '') {
        $candidates[] = category_image(canonical_product_category($category));
    }

    foreach ($candidates as $candidate) {
        if (is_file($root . $candidate)) {
            return $candidate;
        }
    }
    return 'assets/images/product-placeholder.svg';
}

function product_image_exists(?string $storedPath): bool
{
    $path = safe_product_image_path((string)$storedPath);
    return $path !== 'assets/images/product-placeholder.svg' && is_file(APP_ROOT . '/' . $path);
}

/**
 * Validates an uploaded image and re-encodes it as JPEG (1200px + 600px thumbnail).
 * Re-encoding strips hidden metadata (for example GPS location) and any embedded code.
 * Returns the stored relative path.
 */
function store_product_image(array $file): string
{
    $tmp = validate_uploaded_image($file);
    $name = 'upload-' . date('Ymd') . '-' . bin2hex(random_bytes(6));
    $dir = APP_ROOT . '/' . PRODUCT_IMAGE_DIR;

    if (!function_exists('imagecreatefromstring')) {
        // GD is not enabled: keep the validated original with a random name.
        $ext = image_extension_for(mime_of($tmp));
        if (!move_uploaded_file($tmp, "$dir/$name.$ext")) {
            throw new RuntimeException('The image could not be saved.');
        }
        return PRODUCT_IMAGE_DIR . "/$name.$ext";
    }

    $image = imagecreatefromstring((string)file_get_contents($tmp));
    if ($image === false) {
        throw new RuntimeException('The image could not be read. Please upload a JPG, PNG or WEBP photo.');
    }
    $image = apply_exif_orientation($image, $tmp);
    save_resized_jpeg($image, "$dir/$name.jpg", 1200, 82);
    save_resized_jpeg($image, "$dir/$name-thumb.jpg", 600, 78);
    return PRODUCT_IMAGE_DIR . "/$name.jpg";
}

/** Customer inspiration photos are stored outside the public web folder (storage/uploads). */
function store_private_upload(array $file): string
{
    $tmp = validate_uploaded_image($file);
    $dir = STORAGE_DIR . '/uploads';
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
        throw new RuntimeException('The image could not be stored.');
    }
    $name = bin2hex(random_bytes(16));
    if (function_exists('imagecreatefromstring') && ($image = imagecreatefromstring((string)file_get_contents($tmp)))) {
        $image = apply_exif_orientation($image, $tmp);
        save_resized_jpeg($image, "$dir/$name.jpg", 1600, 82);
        return "$name.jpg";
    }
    $ext = image_extension_for(mime_of($tmp));
    if (!move_uploaded_file($tmp, "$dir/$name.$ext")) {
        throw new RuntimeException('The image could not be stored.');
    }
    return "$name.$ext";
}

function mime_of(string $path): string
{
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = (string)finfo_file($finfo, $path);
        finfo_close($finfo);
        return $mime;
    }
    $info = @getimagesize($path);
    return (string)($info['mime'] ?? '');
}

function image_extension_for(string $mime): string
{
    return ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime] ?? 'jpg';
}

function validate_uploaded_image(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(($file['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || ($file['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE
            ? 'The image is too large for the server upload limit.'
            : 'The image did not upload correctly. Please try again.');
    }
    $tmp = (string)$file['tmp_name'];
    if (!is_uploaded_file($tmp)) {
        throw new RuntimeException('Invalid upload.');
    }
    if ((int)$file['size'] > MAX_UPLOAD_MB * 1024 * 1024) {
        throw new RuntimeException('Images must be ' . MAX_UPLOAD_MB . ' MB or smaller.');
    }
    $mime = mime_of($tmp);
    if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true) || @getimagesize($tmp) === false) {
        throw new RuntimeException('Only JPG, PNG, WEBP or GIF images can be uploaded.');
    }
    return $tmp;
}

function apply_exif_orientation(GdImage $image, string $path): GdImage
{
    if (!function_exists('exif_read_data')) {
        return $image;
    }
    $exif = @exif_read_data($path);
    $angle = match ((int)($exif['Orientation'] ?? 1)) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
    if ($angle !== 0) {
        $rotated = imagerotate($image, $angle, 0);
        if ($rotated !== false) {
            return $rotated;
        }
    }
    return $image;
}

function save_resized_jpeg(GdImage $image, string $target, int $maxSide, int $quality): void
{
    $w = imagesx($image);
    $h = imagesy($image);
    $scale = min(1, $maxSide / max($w, $h));
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));
    $canvas = imagecreatetruecolor($nw, $nh);
    imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
    imagecopyresampled($canvas, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imageinterlace($canvas, true);
    if (!imagejpeg($canvas, $target, $quality)) {
        throw new RuntimeException('The image could not be saved.');
    }
}

/* ---------------------------------------------------------------------------
 * Shopping cart (stored in the session: product_id => quantity)
 * ------------------------------------------------------------------------- */

function cart(): array
{
    $cart = $_SESSION['cart'] ?? [];
    return is_array($cart) ? $cart : [];
}

function cart_count(): int
{
    return array_sum(array_map('intval', cart()));
}

function cart_set(int $productId, int $quantity): void
{
    $cart = cart();
    if ($quantity <= 0) {
        unset($cart[$productId]);
    } else {
        $cart[$productId] = min($quantity, 99);
    }
    $_SESSION['cart'] = $cart;
}

function cart_clear(): void
{
    $_SESSION['cart'] = [];
}

/**
 * Loads cart products with their current price and stock from the database.
 * Items that are no longer available are removed; quantities above stock are reduced.
 * Returns [lines, subtotal_cents, notices].
 */
function cart_lines(): array
{
    $cart = cart();
    if (!$cart) {
        return [[], 0, []];
    }
    $ids = array_map('intval', array_keys($cart));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT product_id, name, category, price, stock_qty, image_path, is_active FROM products WHERE product_id IN ($placeholders)");
    $stmt->execute($ids);
    $products = [];
    foreach ($stmt->fetchAll() as $row) {
        $products[(int)$row['product_id']] = $row;
    }

    $lines = [];
    $notices = [];
    $subtotal = 0;
    foreach ($cart as $id => $qty) {
        $id = (int)$id;
        $product = $products[$id] ?? null;
        if (!$product || !(int)$product['is_active'] || (int)$product['stock_qty'] < 1) {
            $notices[] = ($product['name'] ?? 'An item') . ' is no longer available and was removed from your cart.';
            cart_set($id, 0);
            continue;
        }
        if ($qty > (int)$product['stock_qty']) {
            $qty = (int)$product['stock_qty'];
            cart_set($id, $qty);
            $notices[] = 'Only ' . $qty . ' of ' . $product['name'] . ' available, so your quantity was updated.';
        }
        $unit = to_cents($product['price']);
        $lines[] = $product + ['quantity' => (int)$qty, 'unit_cents' => $unit, 'line_cents' => $unit * (int)$qty];
        $subtotal += $unit * (int)$qty;
    }
    return [$lines, $subtotal, $notices];
}

function delivery_fee_cents(string $method, int $subtotalCents): int
{
    if ($method === 'pickup') {
        return 0;
    }
    if (FREE_POSTAGE_OVER > 0 && $subtotalCents >= to_cents(FREE_POSTAGE_OVER)) {
        return 0;
    }
    return to_cents(POSTAGE_FEE);
}

/* ---------------------------------------------------------------------------
 * Orders: status workflow
 * ------------------------------------------------------------------------- */

function order_statuses(): array
{
    return ['pending', 'in_progress', 'ready', 'completed', 'cancelled'];
}

function is_valid_order_status(string $status): bool
{
    return in_array($status, order_statuses(), true);
}

/**
 * Allowed status changes. Completed orders are final; cancelled orders can be re-opened
 * (stock permitting) in case they were cancelled by mistake.
 */
function allowed_status_transitions(string $from): array
{
    return match ($from) {
        'pending' => ['in_progress', 'ready', 'cancelled'],
        'in_progress' => ['ready', 'cancelled'],
        'ready' => ['completed', 'cancelled'],
        'cancelled' => ['pending'],
        default => [],
    };
}

function can_change_status(string $from, string $to): bool
{
    return in_array($to, allowed_status_transitions($from), true);
}

function format_status(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}

function status_description(string $status): string
{
    return [
        'pending' => 'Order received. We will confirm it and send payment details.',
        'in_progress' => 'Your piece is being made or prepared.',
        'ready' => 'Ready for pickup or posting.',
        'completed' => 'Delivered or collected. Thank you!',
        'cancelled' => 'This order was cancelled and the stock released.',
    ][$status] ?? '';
}

function request_statuses(): array
{
    return ['new', 'reviewing', 'quoted', 'accepted', 'declined', 'completed'];
}

/**
 * Changes an order's status inside an existing transaction, adjusting stock and
 * writing the change to the audit history. Throws RuntimeException on a rule breach.
 */
function change_order_status(PDO $pdo, int $orderId, string $newStatus, int $changedBy, string $note = ''): array
{
    $stmt = $pdo->prepare('SELECT order_id, user_id, status FROM orders WHERE order_id = ? FOR UPDATE');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        throw new RuntimeException('Order not found.');
    }
    $oldStatus = (string)$order['status'];
    if ($oldStatus === $newStatus) {
        return $order;
    }
    if (!can_change_status($oldStatus, $newStatus)) {
        throw new RuntimeException('An order cannot move from ' . format_status($oldStatus) . ' to ' . format_status($newStatus) . '.');
    }

    // Lock the products in a consistent order (by id) to avoid deadlocks.
    $items = $pdo->prepare(
        'SELECT oi.product_id, oi.quantity, p.name, p.stock_qty
         FROM order_items oi JOIN products p ON p.product_id = oi.product_id
         WHERE oi.order_id = ? ORDER BY oi.product_id FOR UPDATE'
    );
    $items->execute([$orderId]);
    $items = $items->fetchAll();

    if ($newStatus === 'cancelled') {
        $restore = $pdo->prepare('UPDATE products SET stock_qty = stock_qty + ? WHERE product_id = ?');
        foreach ($items as $item) {
            $restore->execute([(int)$item['quantity'], (int)$item['product_id']]);
        }
    } elseif ($oldStatus === 'cancelled') {
        $reserve = $pdo->prepare('UPDATE products SET stock_qty = stock_qty - ? WHERE product_id = ? AND stock_qty >= ?');
        foreach ($items as $item) {
            $reserve->execute([(int)$item['quantity'], (int)$item['product_id'], (int)$item['quantity']]);
            if ($reserve->rowCount() !== 1) {
                throw new RuntimeException('Cannot re-open this order: not enough stock of ' . $item['name'] . '.');
            }
        }
    }

    $pdo->prepare('UPDATE orders SET status = ?, updated_at = NOW() WHERE order_id = ?')->execute([$newStatus, $orderId]);
    $pdo->prepare('INSERT INTO order_status_history (order_id, old_status, new_status, changed_by, note) VALUES (?, ?, ?, ?, ?)')
        ->execute([$orderId, $oldStatus, $newStatus, $changedBy, $note !== '' ? mb_substr($note, 0, 255) : null]);

    $order['old_status'] = $oldStatus;
    $order['status'] = $newStatus;
    return $order;
}

/* ---------------------------------------------------------------------------
 * Email
 * ------------------------------------------------------------------------- */

/**
 * Sends a plain-text email. In MAIL_MODE 'log' the message is written to storage/mail.log
 * instead, so the full flow can be demonstrated on XAMPP without a mail server.
 */
function send_email(string $to, string $subject, string $body): bool
{
    $subject = APP_NAME . ': ' . preg_replace('/[\r\n]+/', ' ', $subject);
    $body .= "\n\n-- \n" . APP_NAME . "\n" . SHOP_EMAIL . "\n" . absolute_url('index.php') . "\n";

    if (MAIL_MODE === 'mail') {
        $headers = 'From: ' . APP_NAME . ' <' . SHOP_EMAIL . ">\r\n"
            . 'Reply-To: ' . SHOP_EMAIL . "\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n";
        $sent = mail($to, $subject, $body, $headers);
        if (!$sent) {
            error_log('Norbooz mail() failed for ' . $to . ': ' . $subject);
        }
        return $sent;
    }

    if (!is_dir(STORAGE_DIR)) {
        @mkdir(STORAGE_DIR, 0750, true);
    }
    $entry = str_repeat('=', 70) . "\nDate: " . date('Y-m-d H:i:s') . "\nTo: $to\nSubject: $subject\n\n$body\n";
    return file_put_contents(STORAGE_DIR . '/mail.log', $entry, FILE_APPEND | LOCK_EX) !== false;
}

/* ---------------------------------------------------------------------------
 * Layout
 * ------------------------------------------------------------------------- */

function nav_link(string $href, string $label, array $activePages = []): string
{
    $active = in_array(current_page(), $activePages ?: [basename(explode('?', $href)[0])], true);
    return '<a href="' . e(url($href)) . '"' . ($active ? ' aria-current="page"' : '') . '>' . e($label) . '</a>';
}

function page_header(string $title, string $description = ''): void
{
    $user = current_user();
    $count = cart_count();
    $fullTitle = $title . ' | ' . APP_NAME;
    $description = $description !== '' ? $description : 'Handmade crochet plushies, throws, bags, hats, keychains, flowers, wall hangings and coasters from ' . SHOP_LOCATION . '. Custom orders welcome.';
    $styleVersion = (string)(@filemtime(APP_ROOT . '/assets/css/styles.css') ?: 1);
    $scriptVersion = (string)(@filemtime(APP_ROOT . '/assets/js/app.js') ?: 1);

    echo '<!doctype html><html lang="en-AU"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="description" content="' . e($description) . '">';
    echo '<title>' . e($fullTitle) . '</title>';
    echo '<link rel="icon" href="' . e(url('assets/images/favicon.svg')) . '" type="image/svg+xml">';
    echo '<link rel="stylesheet" href="' . e(url('assets/css/styles.css?v=' . $styleVersion)) . '">';
    echo '<script src="' . e(url('assets/js/app.js?v=' . $scriptVersion)) . '" defer></script>';
    echo '</head><body><a class="skip-link" href="#main">Skip to main content</a>';

    echo '<header class="site-header"><div class="container header-inner">';
    echo '<a class="brand" href="' . e(url('index.php')) . '"><span class="brand-mark" aria-hidden="true">N</span><span class="brand-text">Norbooz<span>.</span>Crochet</span></a>';
    echo '<button class="menu-toggle" type="button" aria-expanded="false" aria-controls="site-menu"><span class="sr-only">Menu</span><span class="menu-bars" aria-hidden="true"></span></button>';
    echo '<div class="site-menu" id="site-menu">';
    echo '<nav class="main-nav" aria-label="Main navigation">';
    echo nav_link('index.php', 'Home');
    echo nav_link('products.php', 'Shop', ['products.php', 'product.php']);
    echo nav_link('customize.php', 'Custom orders');
    echo nav_link('about.php', 'About');
    echo nav_link('contact.php', 'Contact');
    echo '</nav>';

    echo '<nav class="account-nav" aria-label="Account">';
    if ($user && $user['role'] === 'admin') {
        echo '<a class="pill" href="' . e(url('admin.php')) . '">Admin panel</a>';
    } else {
        echo '<a class="cart-link" href="' . e(url('cart.php')) . '"' . (current_page() === 'cart.php' ? ' aria-current="page"' : '') . '>Cart<span class="cart-count" aria-hidden="true">' . $count . '</span><span class="sr-only">, ' . $count . ' item' . ($count === 1 ? '' : 's') . '</span></a>';
    }
    if ($user) {
        if ($user['role'] !== 'admin') {
            echo '<a class="pill" href="' . e(url('my_orders.php')) . '">My orders</a>';
        }
        echo '<a class="account-link" href="' . e(url('account.php')) . '" title="Account settings">' . e(explode(' ', $user['full_name'])[0] ?: 'Account') . '</a>';
        echo '<form class="inline-form" method="post" action="' . e(url('logout.php')) . '">' . csrf_input() . '<button class="link-button" type="submit">Log out</button></form>';
    } else {
        echo '<a class="pill" href="' . e(url('login.php')) . '">Log in</a>';
        echo '<a class="pill pill-dark" href="' . e(url('register.php')) . '">Register</a>';
    }
    echo '</nav></div></div></header>';

    echo '<main id="main" class="container main-content" tabindex="-1">';
    echo render_flash();
}

function page_footer(): void
{
    echo '</main><footer class="site-footer"><div class="container footer-grid">';
    echo '<div><div class="footer-brand">Norbooz Crochet</div><p>Handmade crochet pieces made in small batches in ' . e(SHOP_LOCATION) . '. Custom orders welcome.</p><p><a href="mailto:' . e(SHOP_EMAIL) . '">' . e(SHOP_EMAIL) . '</a></p></div>';
    echo '<div><strong>Shop</strong><div class="footer-links"><a href="' . e(url('products.php')) . '">All products</a><a href="' . e(category_url('Plushies')) . '">Plushies</a><a href="' . e(category_url('Bags')) . '">Bags</a><a href="' . e(url('customize.php')) . '">Custom orders</a></div></div>';
    echo '<div><strong>Help</strong><div class="footer-links"><a href="' . e(url('about.php')) . '">About &amp; FAQ</a><a href="' . e(url('contact.php')) . '">Contact</a><a href="' . e(url('privacy.php')) . '">Privacy policy</a><a href="' . e(url('my_orders.php')) . '">Track an order</a></div></div>';
    echo '</div><div class="container footer-bottom"><p>&copy; ' . date('Y') . ' Norbooz Crochet. Payments are arranged by PayID or bank transfer; this website never collects card details.</p></div>';
    echo '</footer></body></html>';
}

function admin_nav(): void
{
    $links = [
        'admin.php' => 'Dashboard',
        'admin_orders.php' => 'Orders',
        'admin_products.php' => 'Products',
        'admin_requests.php' => 'Custom requests',
        'admin_customers.php' => 'Customers',
    ];
    $current = current_page();
    $aliases = ['admin_order.php' => 'admin_orders.php', 'admin_product_edit.php' => 'admin_products.php'];
    $current = $aliases[$current] ?? $current;
    echo '<nav class="admin-nav" aria-label="Administration">';
    foreach ($links as $href => $label) {
        echo '<a href="' . e(url($href)) . '"' . ($current === $href ? ' aria-current="page"' : '') . '>' . e($label) . '</a>';
    }
    echo '</nav>';
}

function status_badge(string $status): string
{
    return '<span class="status status-' . e($status) . '">' . e(format_status($status)) . '</span>';
}

function pagination(int $page, int $pages, array $query, string $page_file): string
{
    if ($pages <= 1) {
        return '';
    }
    $html = '<nav class="pagination" aria-label="Pages">';
    for ($i = 1; $i <= $pages; $i++) {
        $query['page'] = $i;
        $href = url($page_file . '?' . http_build_query($query));
        $html .= $i === $page
            ? '<span aria-current="page">' . $i . '</span>'
            : '<a href="' . e($href) . '">' . $i . '</a>';
    }
    return $html . '</nav>';
}

/** Request types offered on the custom request form (shared by customize.php and request_edit.php). */
function request_types(): array
{
    return ['Plushie', 'Throw or blanket', 'Bag', 'Hat', 'Keychain', 'Flowers', 'Wall hanging', 'Coasters', 'Other'];
}

/** Number of orders and custom requests a customer still has in progress. */
function user_open_work_count(PDO $pdo, int $userId): int
{
    $open = $pdo->prepare("SELECT (SELECT COUNT(*) FROM orders WHERE user_id = ? AND status IN ('pending','in_progress','ready'))
                           + (SELECT COUNT(*) FROM custom_requests WHERE user_id = ? AND status IN ('new','reviewing','quoted','accepted'))");
    $open->execute([$userId, $userId]);
    return (int)$open->fetchColumn();
}

/** Delete a stored private upload (custom request photo). Safe to call with null. */
function delete_private_upload(?string $file): void
{
    if ($file !== null && $file !== '') {
        @unlink(STORAGE_DIR . '/uploads/' . basename($file));
    }
}

/**
 * Australian Privacy Principle 11.2: de-identify a customer. Order totals are kept for the shop's
 * financial records, but contact details, request text and photos are removed.
 * Used by "delete my account" (account.php) and by the owner (admin_customers.php).
 */
function deidentify_user(PDO $pdo, int $userId): void
{
    $email = $pdo->prepare("SELECT email FROM users WHERE user_id = ? AND role = 'customer'");
    $email->execute([$userId]);
    $email = $email->fetchColumn();
    if ($email === false) {
        throw new RuntimeException('Customer not found.');
    }
    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE users SET full_name = 'Deleted customer', email = CONCAT('deleted-', user_id, '@invalid.local'), phone = '', address = '', marketing_opt_in = 0, is_deleted = 1, password_hash = ? WHERE user_id = ?")
            ->execute([password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT), $userId]);
        $pdo->prepare("UPDATE orders SET phone = '', address = 'Removed at customer request', custom_note = NULL WHERE user_id = ?")->execute([$userId]);
        $files = $pdo->prepare('SELECT inspiration_path FROM custom_requests WHERE user_id = ? AND inspiration_path IS NOT NULL');
        $files->execute([$userId]);
        $paths = $files->fetchAll(PDO::FETCH_COLUMN);
        $pdo->prepare("UPDATE custom_requests SET description = 'Removed at customer request', color_preferences = '', phone = '', delivery_address = '', inspiration_path = NULL WHERE user_id = ?")->execute([$userId]);
        $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$userId]);
        $pdo->prepare('DELETE FROM login_attempts WHERE email = ?')->execute([$email]);
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $ex;
    }
    foreach ($paths as $file) {
        delete_private_upload((string)$file);
    }
}
