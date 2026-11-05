<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

// ตัวกรอง : key => [ชื่อ, เงื่อนไข SQL]
$filters = [
    'all'       => ['ทั้งหมด', ''],
    'pending'   => ['รอตรวจ', "AND status = 'pending'"],
    'completed' => ['สำเร็จ', "AND status = 'completed'"],
];
$filter = isset($filters[$_GET['filter'] ?? '']) ? $_GET['filter'] : 'all';

$stmt = $pdo->prepare(
    "SELECT o.*, (SELECT SUM(qty) FROM order_items WHERE order_id = o.id) AS item_count
       FROM orders o
      WHERE user_id = ? {$filters[$filter][1]}
      ORDER BY created_at DESC"
);
$stmt->execute([current_user()['id']]);
$orders = $stmt->fetchAll();

// รายการสินค้าของทุกออเดอร์ในหน้านี้ ดึงครั้งเดียวแล้วจัดกลุ่มตามออเดอร์
$items = [];
if ($orders) {
    $ids  = implode(',', array_column($orders, 'id'));
    $rows = $pdo->query(
        "SELECT oi.order_id, oi.qty, oi.unit_price_paid, oi.discount_amount, v.size, p.name
           FROM order_items oi
           JOIN product_variants v ON v.id = oi.variant_id
           JOIN products p ON p.id = v.product_id
          WHERE oi.order_id IN ($ids)
          ORDER BY oi.id"
    );
    foreach ($rows as $row) {
        $items[$row['order_id']][] = $row;
    }
}

// คำอธิบายใต้เลขออเดอร์ : ยกเลิกบอกเหตุผล กำลังส่งบอกเลขพัสดุ
function order_note(array $order): string
{
    if ($order['status'] === 'cancelled' && $order['cancel_reason']) {
        return 'เหตุผล: ' . $order['cancel_reason'];
    }
    if ($order['status'] === 'shipped' && $order['tracking_no']) {
        return 'เลขพัสดุ ' . $order['tracking_no'];
    }
    return ORDER_STATUS[$order['status']][3];
}

$pageTitle = 'คำสั่งซื้อของฉัน';
$activeTab = 'orders';
require __DIR__ . '/../includes/header.php';
?>
<h1 class="desktop-only">คำสั่งซื้อของฉัน</h1>

<nav class="chips page-chips" aria-label="กรองคำสั่งซื้อ">
    <?php foreach ($filters as $key => [$label]): ?>
        <a class="chip" href="orders.php?filter=<?= $key ?>" <?= $key === $filter ? 'aria-current="page"' : '' ?>>
            <?= $key === $filter ? icon('check') : '' ?><?= $label ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php if (!$orders): ?>
    <div class="empty-state">
        <?= icon('orders') ?>
        <h2>ยังไม่มีคำสั่งซื้อ</h2>
        <a class="button" href="index.php">เลือกซื้อสินค้า</a>
    </div>
<?php endif; ?>

<div class="order-list">
    <?php foreach ($orders as $order): ?>
        <article class="card order-card">
            <div class="order-head">
                <h2><?= order_no($order['id']) ?></h2>
                <?= status_tag($order['status']) ?>
            </div>
            <p class="muted"><?= thai_date($order['created_at']) ?> · <?= $order['item_count'] ?> ชิ้น</p>
            <p><?= e(order_note($order)) ?></p>

            <!-- กด "ดูรายละเอียด" เพื่อเปิด/ปิดรายการสินค้า (details ของ HTML ไม่ต้องใช้ JS) -->
            <details class="order-details">
                <summary>
                    <span>ยอดรวม <strong class="price"><?= baht((float) $order['net_total']) ?></strong></span>
                    <span class="summary-link">ดูรายละเอียด</span>
                </summary>
                <ul class="order-items">
                    <?php foreach ($items[$order['id']] ?? [] as $item): ?>
                        <li>
                            <span><?= e($item['name']) ?> <span class="muted">ไซซ์ <?= $item['size'] ?> × <?= $item['qty'] ?></span></span>
                            <span><?= baht($item['unit_price_paid'] * $item['qty']) ?></span>
                        </li>
                    <?php endforeach; ?>
                    <?php
                    // ราคาสินค้าข้างบนหักโปรแล้ว ส่วนลดที่เหลือคือคูปอง
                    $couponOff = round($order['discount_total'] - array_sum(array_column($items[$order['id']] ?? [], 'discount_amount')), 2);
                    ?>
                    <?php if ($couponOff > 0): ?>
                        <li class="muted"><span>ส่วนลดคูปอง</span><span>−<?= baht($couponOff) ?></span></li>
                    <?php endif; ?>
                    <li class="muted"><span>ค่าจัดส่ง</span><span><?= $order['shipping_fee'] > 0 ? baht((float) $order['shipping_fee']) : 'ส่งฟรี' ?></span></li>
                </ul>
            </details>
        </article>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
