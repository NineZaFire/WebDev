<?php
require_once __DIR__ . '/../includes/auth.php';

$stmt = $pdo->prepare(
    'SELECT p.*, c.name AS category
       FROM products p
       JOIN categories c ON c.id = p.category_id
      WHERE p.id = ? AND p.is_active = 1'
);
$stmt->execute([(int) ($_GET['id'] ?? 0)]);
$product = $stmt->fetch();

if (!$product) {
    http_response_code(404);
    $pageTitle = 'ไม่พบสินค้า';
    $backUrl   = 'index.php';
    require __DIR__ . '/../includes/header.php';
    echo '<div class="empty-state"><h1>ไม่พบสินค้านี้</h1><a class="button" href="index.php">กลับหน้าแรก</a></div>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$stmt = $pdo->prepare('SELECT id, size, stock FROM product_variants WHERE product_id = ? ORDER BY size');
$stmt->execute([$product['id']]);
$variants = $stmt->fetchAll();

$stmt = $pdo->prepare('SELECT filename FROM product_images WHERE product_id = ? ORDER BY sort_order, id');
$stmt->execute([$product['id']]);
$images = $stmt->fetchAll(PDO::FETCH_COLUMN);

// เลือกไซซ์แรกที่ยังมีของไว้ให้ก่อน (ไม่มีเลย = สินค้าหมด)
$selected = null;
foreach ($variants as $v) {
    if ($v['stock'] > 0) {
        $selected = $v;
        break;
    }
}

$pageTitle  = 'รายละเอียดสินค้า';
$backUrl    = 'index.php';
$hideTabBar = true;
require __DIR__ . '/../includes/header.php';
?>
<nav class="breadcrumb desktop-only" aria-label="ตำแหน่งของหน้านี้">
    <a href="index.php">หน้าแรก</a> / <span><?= e($product['category']) ?></span> / <span><?= e($product['name']) ?></span>
</nav>

<article class="product-detail">
    <div class="gallery">
        <?php if ($images): ?>
            <img class="product-thumb gallery-main" id="mainImage" src="../uploads/products/<?= e($images[0]) ?>" alt="<?= e($product['name']) ?>">
        <?php else: ?>
            <div class="product-thumb gallery-main"><?= icon('shirt') ?></div>
        <?php endif; ?>

        <?php if (count($images) > 1): ?>
            <div class="gallery-thumbs" id="galleryThumbs">
                <?php foreach ($images as $i => $file): ?>
                    <button type="button" class="thumb-button" <?= $i === 0 ? 'aria-current="true"' : '' ?> aria-label="ดูรูปที่ <?= $i + 1 ?>">
                        <img src="../uploads/products/<?= e($file) ?>" alt="">
                    </button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <form class="product-buy" id="addToCartForm" method="post" action="../api/cart.php">
        <input type="hidden" name="action" value="add">

        <h1><?= e($product['name']) ?></h1>
        <p class="product-price">
            <span class="price"><?= baht((float) $product['base_price']) ?></span>
            <span class="muted" id="stockText"><?= $selected ? "เหลือ {$selected['stock']} ชิ้น" : 'สินค้าหมด' ?></span>
        </p>

        <fieldset class="size-picker">
            <legend>ไซซ์</legend>
            <div class="size-options">
                <?php foreach ($variants as $v): ?>
                    <label class="size-option">
                        <input type="radio" name="variant_id" value="<?= $v['id'] ?>" data-stock="<?= $v['stock'] ?>"
                            <?= $v['id'] === ($selected['id'] ?? null) ? 'checked' : '' ?> <?= $v['stock'] > 0 ? '' : 'disabled' ?>>
                        <span><?= $v['stock'] > 0 ? $v['size'] : "<s>{$v['size']}</s> หมด" ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <div class="buy-controls">
            <div class="qty-row">
                <span class="mobile-only">จำนวน</span>
                <div class="stepper">
                    <button type="button" data-step="-1" aria-label="ลดจำนวน"><?= icon('minus') ?></button>
                    <input type="number" id="qty" name="qty" value="1" min="1" max="<?= $selected['stock'] ?? 1 ?>" aria-label="จำนวน">
                    <button type="button" data-step="1" aria-label="เพิ่มจำนวน"><?= icon('plus') ?></button>
                </div>
            </div>

            <div class="buy-bar">
                <a class="icon-button mobile-only" href="cart.php" aria-label="ดูตะกร้า"><?= icon('bag') ?></a>
                <button class="button" type="submit" <?= $selected ? '' : 'disabled' ?>>เพิ่มลงตะกร้า</button>
            </div>
        </div>

        <h2 class="desktop-only">รายละเอียดสินค้า</h2>
        <p class="description"><?= e($product['description']) ?></p>
    </form>
</article>

<div id="toast" role="status" hidden>
    <?= icon('check') ?><span id="toastText"></span><a href="cart.php">ดูตะกร้า</a>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
