<?php
// โครงหน้าของฝั่งลูกค้า : ตั้งตัวแปรเหล่านี้ก่อน include (ไม่ตั้งก็ได้)
//   $pageTitle    ชื่อหน้า
//   $activeTab    เมนูของหน้านี้ : home | search | cart | orders | account
//   $backUrl      มีค่า = จอมือถือแสดงปุ่มย้อนกลับกับชื่อหน้า แทนโลโก้
//   $plainLayout  true = ไม่มี header และ tab bar (หน้าเข้าสู่ระบบ/สมัครสมาชิก)
//   $hideTabBar   true = ไม่มี tab bar เพราะหน้านั้นมีแถบปุ่มด้านล่างของตัวเอง (สินค้า, ตะกร้า)
//   $useQr        true = โหลดไลบรารีวาด QR (หน้าชำระเงิน)
// ทุกหน้าอยู่ลึก 1 ชั้น (public/, admin/) ลิงก์จึงขึ้นต้นด้วย ../ ได้เสมอ ทั้งบน XAMPP และ Docker
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';

$siteName    = 'LMShirtShop';
$pageTitle   ??= '';
$activeTab   ??= '';
$backUrl     ??= null;
$plainLayout ??= false;
$hideTabBar  ??= $plainLayout;
$useQr       ??= false;

$user      = current_user();
$cartCount = array_sum($_SESSION['cart'] ?? []);

// เมนูหลัก ใช้ทั้ง tab bar บนมือถือ (footer.php) และ header บนจอใหญ่ : key => [ชื่อ, ลิงก์, ไอคอน]
$menu = [
    'home'    => ['หน้าแรก', 'index.php', 'home'],
    'search'  => ['ค้นหา', 'index.php#q', 'search'],
    'cart'    => ['ตะกร้า', 'cart.php', 'bag'],
    'orders'  => ['คำสั่งซื้อ', 'orders.php', 'orders'],
    'account' => ['บัญชี', $user ? 'account.php' : 'login.php', 'user'],
];
$current = fn(string $key) => $key === $activeTab ? 'aria-current="page"' : '';

// ข้อความแจ้งผลจากหน้าก่อน (เช่น เปลี่ยนรหัสผ่านแล้ว) แสดงครั้งเดียวแล้วลบออกจาก session
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ? "$pageTitle | $siteName" : $siteName) ?></title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<?php if (!$plainLayout): ?>
<header class="app-header">
    <div class="container app-header-bar">
        <?php if ($backUrl): ?>
            <a class="icon-button mobile-only" href="<?= e($backUrl) ?>" aria-label="ย้อนกลับ"><?= icon('back') ?></a>
            <span class="app-title mobile-only"><?= e($pageTitle) ?></span>
        <?php endif; ?>

        <a class="logo <?= $backUrl ? 'desktop-only' : '' ?>" href="../public/index.php"><?= icon('shirt') . $siteName ?></a>

        <form class="search-box desktop-only" action="../public/index.php" role="search">
            <?= icon('search') ?>
            <input type="search" name="q" value="<?= e($_GET['q'] ?? '') ?>" placeholder="ค้นหาเสื้อ เช่น เสื้อยืด, ฮู้ด…" aria-label="ค้นหาสินค้า">
        </form>

        <nav class="header-nav" aria-label="เมนูหลัก">
            <?php foreach (['orders', 'account'] as $key): [$label, $href, $iconName] = $menu[$key]; ?>
                <a class="desktop-only" href="../public/<?= $href ?>" <?= $current($key) ?>><?= icon($iconName) . $label ?></a>
            <?php endforeach; ?>
            <a class="cart-link" href="../public/cart.php" aria-label="ตะกร้า" <?= $current('cart') ?>>
                <?= icon('bag') ?><span class="desktop-only">ตะกร้า</span>
                <span class="badge" id="cartCount" <?= $cartCount ? '' : 'hidden' ?>><?= $cartCount ?></span>
            </a>
        </nav>
    </div>
</header>
<?php endif; ?>
<main class="container page <?= $plainLayout ? 'page-plain' : '' ?>">
<?php if ($flash): ?>
    <p class="flash" role="status"><?= icon('check') . e($flash) ?></p>
<?php endif; ?>
