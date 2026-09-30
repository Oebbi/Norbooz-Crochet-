<?php
/** Product card used on the home page, shop and related-products lists. Expects $product. */
$cardId = (int)$product['product_id'];
$cardStock = (int)$product['stock_qty'];
$cardCategory = canonical_product_category((string)$product['category']);
?>
<article class="product-card">
    <a class="product-image-link" href="<?= e(url('product.php?id=' . $cardId)) ?>" tabindex="-1" aria-hidden="true">
        <img src="<?= e(url(product_image($product['image_path'], $cardCategory, true))) ?>" alt="" loading="lazy" width="600" height="600">
        <?php if ($cardStock < 1): ?><span class="badge badge-muted">Sold out</span><?php elseif ($cardStock <= LOW_STOCK_LEVEL): ?><span class="badge">Only <?= $cardStock ?> left</span><?php endif; ?>
    </a>
    <div class="product-body">
        <p class="product-category"><?= e($cardCategory) ?></p>
        <h3><a href="<?= e(url('product.php?id=' . $cardId)) ?>"><?= e($product['name']) ?></a></h3>
        <div class="product-meta">
            <strong><?= money($product['price']) ?></strong>
            <?php if ($cardStock > 0 && !is_admin()): ?>
                <form method="post" action="<?= e(url('cart.php')) ?>" class="inline-form">
                    <?= csrf_input() ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= $cardId ?>">
                    <input type="hidden" name="quantity" value="1">
                    <input type="hidden" name="return" value="<?= e(current_request_path()) ?>">
                    <button class="button button-small" type="submit" aria-label="Add to cart: <?= e($product['name']) ?>">Add to cart</button>
                </form>
            <?php elseif ($cardStock < 1): ?>
                <a class="text-link small" href="<?= e(url('customize.php?based_on=' . $cardId)) ?>">Request one</a>
            <?php endif; ?>
        </div>
    </div>
</article>
