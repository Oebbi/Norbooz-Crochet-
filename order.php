<?php
// Older links pointed to order.php. Ordering now uses the cart and checkout pages.
require_once __DIR__ . '/config/functions.php';
$productId = filter_var($_GET['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
redirect($productId ? 'product.php?id=' . $productId : 'cart.php');
