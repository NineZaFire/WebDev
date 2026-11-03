<?php
// โครงหน้าของแอดมิน : จอใหญ่มีเมนูด้านซ้าย / มือถือมีแถบหัวสีน้ำเงินเข้มกับ tab bar ด้านล่าง
// ตั้งตัวแปรเหล่านี้ก่อน include (ไม่ตั้งก็ได้)
//   $pageTitle     ชื่อหน้า
//   $pageSubtitle  ข้อความใต้ชื่อหน้า
//   $activeMenu    เมนูของหน้านี้ : dashboard | products | orders | promotions
//   $headerAction  [ชื่อปุ่ม, ลิงก์] ปุ่มหลักของหน้า เช่น เพิ่มสินค้า
//   $backUrl       มีค่า = มือถือแสดงปุ่มย้อนกลับ
//   $hideTabBar    true = ไม่มี tab bar เพราะหน้านั้นมีแถบปุ่มบันทึกด้านล่างแทน
//   $useChart      true = โหลด Chart.js (หน้าที่มีกราฟ)
require_once __DIR__ . '/admin.php';

$siteName      = 'LMShirtShop';
$pageTitle     ??= '';
$pageSubtitle  ??= '';
$activeMenu    ??= '';
$headerAction  ??= null;
$backUrl       ??= null;
$hideTabBar    ??= false;
$useChart      ??= false;

// เมนูแอดมิน ใช้ทั้งเมนูด้านซ้ายและ tab bar : key => [ชื่อ, ลิงก์, ไอคอน]
$menu = [
    'dashboard'  => ['แดชบอร์ด', 'index.php', 'chart'],
    'products'   => ['สินค้า', 'products.php', 'shirt'],
    'orders'     => ['ออเดอร์', 'orders.php', 'orders'],
    'promotions' => ['โปรโมชั่น', 'promotions.php', 'tag'],
];
$current       = fn(string $key) => $key === $activeMenu ? 'aria-current="page"' : '';
$pendingOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();

// ข้อความแจ้งผลจากหน้าก่อน (เช่น บันทึกแล้ว) แสดงครั้งเดียวแล้วลบออกจาก session
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e("$pageTitle | แอดมิน $siteName") ?></title>
    <link rel="stylesheet" href="../assets/style.css">
</head>
<body class="admin">
<aside class="admin-sidebar desktop-only">
    <a class="logo" href="index.php"><?= icon('shirt') . $siteName ?><span class="pill">แอดมิน</span></a>
    <nav aria-label="เมนูแอดมิน">
        <?php foreach ($menu as $key => [$label, $href, $iconName]): ?>
            <a href="<?= $href ?>" <?= $current($key) ?>>
                <?= icon($iconName) . $label ?>
                <?php if ($key === 'orders' && $pendingOrders): ?><span class="badge"><?= $pendingOrders ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="admin-user">
        <?= icon('user') ?>
        <div>
            <strong><?= e(current_user()['first_name']) ?></strong>
            <form method="post" action="../public/logout.php"><button class="link-button" type="submit">ออกจากระบบ</button></form>
        </div>
    </div>
</aside>

<header class="admin-bar mobile-only">
    <?php if ($backUrl): ?>
        <a class="icon-button" href="<?= e($backUrl) ?>" aria-label="ย้อนกลับ"><?= icon('back') ?></a>
    <?php endif; ?>
    <span class="app-title"><?= e($pageTitle) ?></span>
    <?php if ($headerAction): ?>
        <a class="button button-light" href="<?= e($headerAction[1]) ?>"><?= icon('plus') . e($headerAction[0]) ?></a>
    <?php endif; ?>
</header>

<main class="admin-main">
    <div class="admin-page-head desktop-only">
        <div>
            <h1><?= e($pageTitle) ?></h1>
            <?php if ($pageSubtitle): ?><p class="muted"><?= e($pageSubtitle) ?></p><?php endif; ?>
        </div>
        <?php if ($headerAction): ?>
            <a class="button" href="<?= e($headerAction[1]) ?>"><?= e($headerAction[0]) ?></a>
        <?php endif; ?>
    </div>

    <?php if ($flash): ?>
        <p class="flash" role="status"><?= icon('check') . e($flash) ?></p>
    <?php endif; ?>
