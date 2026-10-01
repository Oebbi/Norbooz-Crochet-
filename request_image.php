<?php
/**
 * Serves a customer's private inspiration photo from storage/uploads.
 * Only the administrator or the customer who uploaded it may view it.
 */
require_once __DIR__ . '/config/functions.php';
require_login();

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$stmt = db()->prepare('SELECT user_id, inspiration_path FROM custom_requests WHERE request_id = ?');
$stmt->execute([(int)$id]);
$request = $stmt->fetch();

$user = current_user();
if (!$request || !$request['inspiration_path'] || (!is_admin() && (int)$request['user_id'] !== $user['user_id'])) {
    http_response_code(404);
    exit('Not found.');
}

$file = basename((string)$request['inspiration_path']);
$path = STORAGE_DIR . '/uploads/' . $file;
if (!preg_match('/^[a-f0-9]{32}\.(jpg|png|webp|gif)$/', $file) || !is_file($path)) {
    http_response_code(404);
    exit('Not found.');
}

header('Content-Type: ' . (mime_of($path) ?: 'application/octet-stream'));
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=3600');
header('Content-Disposition: inline; filename="request-' . (int)$id . '.' . pathinfo($file, PATHINFO_EXTENSION) . '"');
readfile($path);
