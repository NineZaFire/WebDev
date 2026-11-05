<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cart_view.php';

$coupon = $_SESSION['coupon'] ?? '';
$quote  = price_cart($pdo, $_SESSION['cart'] ?? [], $coupon);

$pageTitle  = 'ตะกร้าสินค้า';
$activeTab  = 'cart';
$backUrl    = 'index.php';
$hideTabBar = (bool) $quote['lines'];   // มีสินค้า = ใช้แถบ "ไปชำระเงิน" ด้านล่างแทน tab bar
require __DIR__ . '/../includes/header.php';
?>
<!-- app.js แทนที่เนื้อหาในกล่องนี้ด้วย HTML ที่ api/cart.php ส่งกลับมา -->
<div id="cartContent"><?= cart_view($quote, $coupon) ?></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
