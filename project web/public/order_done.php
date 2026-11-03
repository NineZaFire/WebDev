<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

// ดูได้เฉพาะออเดอร์ของตัวเอง
$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
$stmt->execute([(int) ($_GET['id'] ?? 0), current_user()['id']]);
$order = $stmt->fetch();
if (!$order) {
    http_response_code(404);
    exit('ไม่พบคำสั่งซื้อนี้');
}

$pageTitle = 'สั่งซื้อสำเร็จ';
$activeTab = 'orders';
require __DIR__ . '/../includes/header.php';
?>
<div class="checkout">
    <?= checkout_steps(3) ?>

    <section class="card done-card">
        <span class="done-icon"><?= icon('check') ?></span>
        <h1>สั่งซื้อสำเร็จ</h1>
        <p class="muted">เลขที่คำสั่งซื้อ <strong><?= order_no($order['id']) ?></strong></p>
        <p>ยอดชำระ <strong class="qr-amount"><?= baht((float) $order['net_total']) ?></strong></p>
        <p><?= status_tag($order['status']) ?></p>
        <p class="muted">แอดมินจะตรวจสลิปแล้วยืนยันออเดอร์ ติดตามสถานะได้ที่หน้าคำสั่งซื้อ</p>
        <div class="done-actions">
            <a class="button" href="orders.php">ดูคำสั่งซื้อ</a>
            <a class="button button-outline" href="index.php">เลือกซื้อสินค้าต่อ</a>
        </div>
    </section>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
