<?php
require_once __DIR__ . '/config.php';

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
    return $_SESSION['csrf_token'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        exit('Invalid request token. Please go back, refresh the page and try again.');
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) return null;
    return [
        'user_id' => (int)$_SESSION['user_id'],
        'full_name' => $_SESSION['full_name'] ?? '',
        'role' => $_SESSION['role'] ?? 'customer',
    ];
}

function require_login(): void
{
    if (!current_user()) {
        $_SESSION['flash_error'] = 'Please log in to continue.';
        redirect('login.php');
    }
}

function require_admin(): void
{
    require_login();
    if (($_SESSION['role'] ?? '') !== 'admin') {
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
            $html .= '<div class="alert alert-' . $type . '" role="status">' . e($_SESSION[$key]) . '</div>';
            unset($_SESSION[$key]);
        }
    }
    return $html;
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

function login_key(string $email): string
{
    return strtolower(trim($email));
}

function login_is_blocked(string $email): int
{
    $key = login_key($email);
    $record = $_SESSION['login_protection'][$key] ?? null;
    if (!$record || empty($record['blocked_until'])) return 0;
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
    $record['count']++;
    if ($record['count'] >= 5) {
        $record['blocked_until'] = time() + 300; // 5-minute prototype lockout
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
    $fullTitle = e($title) . ' | ' . e(APP_NAME);
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="description" content="Norbooz Crochet handmade products and order management">';
    echo '<title>' . $fullTitle . '</title>';
    echo '<link rel="stylesheet" href="' . e(url('assets/css/styles.css')) . '">';
    echo '</head><body><a class="skip-link" href="#main">Skip to main content</a>';
    echo '<header class="site-header"><div class="container nav-wrap">';
    echo '<a class="brand" href="' . e(url('index.php')) . '">Norbooz Crochet</a>';
    echo '<nav aria-label="Primary navigation">';
    echo '<a href="' . e(url('index.php')) . '">Home</a>';
    echo '<a href="' . e(url('products.php')) . '">Products</a>';
    if ($user) {
        if ($user['role'] === 'admin') {
            echo '<a href="' . e(url('admin.php')) . '">Admin Panel</a>';
        } else {
            echo '<a href="' . e(url('order.php')) . '">Place Order</a>';
            echo '<a href="' . e(url('my_orders.php')) . '">My Orders</a>';
        }
        echo '<span class="nav-user">Hello, ' . e($user['full_name']) . '</span>';
        echo '<a href="' . e(url('logout.php')) . '">Logout</a>';
    } else {
        echo '<a href="' . e(url('register.php')) . '">Register</a>';
        echo '<a href="' . e(url('login.php')) . '">Login</a>';
    }
    echo '</nav></div></header><main id="main" class="container main-content">';
    echo render_flash();
}

function page_footer(): void
{
    echo '</main><footer class="site-footer"><div class="container">';
    echo '<p>&copy; ' . date('Y') . ' Norbooz Crochet. ICT312 academic prototype. No online payments are collected.</p>';
    echo '</div></footer><script src="' . e(url('assets/js/app.js')) . '"></script></body></html>';
}
