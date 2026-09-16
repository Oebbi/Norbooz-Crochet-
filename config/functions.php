<?php
require_once __DIR__ . '/config.php';

/**
 * Shared helpers for the Norbooz Crochet ICT312 prototype.
 * Security controls are intentionally kept understandable for an undergraduate project.
 */

function is_https_request(): bool
{
    return !empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off';
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");

    if (is_https_request()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

send_security_headers();

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => is_https_request(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function e(mixed $value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    $base = rtrim(BASE_URL, '/');
    return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

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
        exit('Invalid request token. Please return to the previous page, refresh it and try again.');
    }
}

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

function require_login(): void
{
    if (!current_user()) {
        flash('error', 'Please log in to continue.');
        redirect('login.php');
    }
}

function require_admin(): void
{
    require_login();
    if ((string)($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(403);
        exit('Access denied. Administrator permission is required.');
    }
}

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
            $html .= '<div class="alert alert-' . e($type) . '" role="status">' . e($_SESSION[$key]) . '</div>';
            unset($_SESSION[$key]);
        }
    }
    return $html;
}

function normalise_email(string $email): string
{
    return strtolower(trim($email));
}

function is_valid_order_status(string $status): bool
{
    return in_array($status, ['pending', 'in_progress', 'ready', 'completed', 'cancelled'], true);
}

function format_status(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}

function money(float|string $amount): string
{
    return '$' . number_format((float)$amount, 2);
}

/**
 * The eight product families used consistently across the redesigned shop.
 */
function product_categories(): array
{
    return [
        'Plushies',
        'Throws',
        'Bags',
        'Hats',
        'Keychains',
        'Flowers',
        'Wall Hangings',
        'Coasters',
    ];
}

/**
 * Converts older proposal/demo labels into the final product-family names.
 * This keeps existing database rows usable without requiring a schema change.
 */
function canonical_product_category(string $category): string
{
    $category = trim($category);
    $map = [
        'Beanies' => 'Hats',
        'Beanie' => 'Hats',
        'Hats & Beanies' => 'Hats',
        'Blankets' => 'Throws',
        'Blanket' => 'Throws',
        'Throws & Blankets' => 'Throws',
        'Wall Hangers' => 'Wall Hangings',
        'Wall Hanger' => 'Wall Hangings',
        'Coaster' => 'Coasters',
        'Flower' => 'Flowers',
        'Keychain' => 'Keychains',
    ];

    return $map[$category] ?? $category;
}

/**
 * Database values that belong to a selected final product family.
 */
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

/**
 * Allow local product images only. Subfolders such as assets/images/products/
 * are supported, but traversal and remote URLs are rejected.
 */
function safe_product_image_path(string $path): string
{
    $path = trim(str_replace('\\', '/', $path));
    if (preg_match('#^assets/images(?:/[A-Za-z0-9._-]+)+\.(svg|png|jpe?g|gif|webp)$#i', $path)) {
        return $path;
    }
    return 'assets/images/product-placeholder.svg';
}

function login_key(string $email): string
{
    return normalise_email($email);
}

/**
 * Prototype login protection. Five failed attempts for the same email in the
 * browser session causes a five-minute lockout. A production system should
 * additionally use persistent/IP-aware rate limiting at the server edge.
 */
function login_is_blocked(string $email): int
{
    $key = login_key($email);
    $record = $_SESSION['login_protection'][$key] ?? null;
    if (!$record || empty($record['blocked_until'])) {
        return 0;
    }

    $remaining = (int)$record['blocked_until'] - time();
    if ($remaining <= 0) {
        unset($_SESSION['login_protection'][$key]);
        return 0;
    }
    return $remaining;
}

function record_login_failure(string $email): void
{
    $key = login_key($email);
    $record = $_SESSION['login_protection'][$key] ?? ['count' => 0, 'blocked_until' => 0];
    $record['count'] = (int)$record['count'] + 1;

    if ($record['count'] >= 5) {
        $record['blocked_until'] = time() + 300;
        $record['count'] = 0;
    }
    $_SESSION['login_protection'][$key] = $record;
}

function clear_login_failures(string $email): void
{
    unset($_SESSION['login_protection'][login_key($email)]);
}

function page_header(string $title): void
{
    $user = current_user();
    $fullTitle = $title . ' | ' . APP_NAME;
    $searchValue = trim((string)($_GET['q'] ?? ''));

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="description" content="Norbooz Crochet handmade products and order management">';
    echo '<title>' . e($fullTitle) . '</title>';
    echo '<link rel="stylesheet" href="' . e(url('assets/css/styles.css')) . '">';
    echo '</head><body><a class="skip-link" href="#main">Skip to main content</a>';

    echo '<div class="announcement-bar">Handmade in small batches <span aria-hidden="true">•</span> Custom orders welcome <span aria-hidden="true">•</span> Local academic prototype</div>';
    echo '<header class="site-header">';
    echo '<div class="container header-main">';
    echo '<a class="brand" href="' . e(url('index.php')) . '"><span class="brand-mark" aria-hidden="true">N</span><span><strong>Norbooz</strong><small>Crochet</small></span></a>';

    echo '<form class="header-search" method="get" action="' . e(url('products.php')) . '" role="search">';
    echo '<label class="sr-only" for="site-search">Search products</label>';
    echo '<input id="site-search" type="search" name="q" value="' . e($searchValue) . '" placeholder="Search handmade crochet..." maxlength="80">';
    echo '<button type="submit" aria-label="Search">Search</button>';
    echo '</form>';

    echo '<nav class="account-nav" aria-label="Account navigation">';
    if ($user) {
        if ($user['role'] === 'admin') {
            echo '<a href="' . e(url('admin.php')) . '">Admin</a>';
        } else {
            echo '<a href="' . e(url('my_orders.php')) . '">My orders</a>';
        }
        echo '<span class="nav-user">Hi, ' . e($user['full_name']) . '</span>';
        echo '<form class="nav-logout" method="post" action="' . e(url('logout.php')) . '">' . csrf_input() . '<button class="nav-link-button" type="submit">Logout</button></form>';
    } else {
        echo '<a href="' . e(url('register.php')) . '">Register</a>';
        echo '<a class="header-login" href="' . e(url('login.php')) . '">Sign in</a>';
    }
    echo '</nav></div>';

    echo '<div class="category-nav-wrap"><div class="container category-nav" aria-label="Shop categories">';
    echo '<a href="' . e(url('index.php')) . '">Home</a>';
    echo '<a href="' . e(url('products.php')) . '">All products</a>';
    foreach (product_categories() as $category) {
        echo '<a href="' . e(category_url($category)) . '">' . e($category) . '</a>';
    }
    if ($user && $user['role'] === 'customer') {
        echo '<a class="category-nav-order" href="' . e(url('order.php')) . '">Place order</a>';
    }
    echo '</div></div></header>';

    echo '<main id="main" class="container main-content">';
    echo render_flash();
}

function page_footer(): void
{
    echo '</main><footer class="site-footer"><div class="container footer-grid">';
    echo '<div><div class="footer-brand">Norbooz Crochet</div><p>Handmade crochet products presented through a secure ICT312 web information system.</p></div>';
    echo '<div><strong>Shop</strong><div class="footer-links"><a href="' . e(url('products.php')) . '">All products</a><a href="' . e(category_url('Plushies')) . '">Plushies</a><a href="' . e(category_url('Bags')) . '">Bags</a><a href="' . e(category_url('Flowers')) . '">Flowers</a></div></div>';
    echo '<div><strong>Account</strong><div class="footer-links"><a href="' . e(url('register.php')) . '">Register</a><a href="' . e(url('login.php')) . '">Login</a></div></div>';
    echo '</div><div class="container footer-bottom"><p>&copy; ' . date('Y') . ' Norbooz Crochet. ICT312 academic prototype. No online payment-card data is collected.</p></div>';
    echo '</footer><script src="' . e(url('assets/js/app.js')) . '"></script></body></html>';
}
