<?php
require_once __DIR__ . '/../includes/admin.php';
require_admin();

// ยอดขายวันนี้เทียบเมื่อวาน (นับเฉพาะออเดอร์ที่ชำระแล้ว)
$sales = $pdo->query(
    "SELECT COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() THEN net_total END), 0) AS today,
            COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() - INTERVAL 1 DAY THEN net_total END), 0) AS yesterday
       FROM orders
      WHERE status IN (" . PAID_SQL . ") AND created_at >= CURDATE() - INTERVAL 1 DAY"
)->fetch();
$diff = $sales['today'] - $sales['yesterday'];

// ออเดอร์วันนี้ (ไม่นับที่ยกเลิก/หมดเวลา) และจำนวนตัวที่ขายได้
$today = $pdo->query(
    "SELECT COUNT(DISTINCT o.id) AS orders, COALESCE(SUM(oi.qty), 0) AS items
       FROM orders o
       JOIN order_items oi ON oi.order_id = o.id
      WHERE DATE(o.created_at) = CURDATE() AND o.status NOT IN ('cancelled', 'expired')"
)->fetch();

// งานที่ต้องจัดการ : ออเดอร์รอตรวจสลิป (เก่าสุดก่อน), ไซซ์ที่ใกล้หมด (น้อยสุดก่อน), โปรที่หมดเขตภายในพรุ่งนี้
$pending = $pdo->query("SELECT id, created_at FROM orders WHERE status = 'pending' ORDER BY created_at")->fetchAll();
$low = $pdo->query(
    "SELECT p.name, v.size, v.stock
       FROM product_variants v
       JOIN products p ON p.id = v.product_id
      WHERE p.is_active = 1 AND v.stock <= " . LOW_STOCK . "
      ORDER BY v.stock, p.name"
)->fetchAll();
$ending = $pdo->query(
    "SELECT name, end_at FROM promotions
      WHERE is_active = 1 AND end_at >= NOW() AND end_at < CURDATE() + INTERVAL 2 DAY
      ORDER BY end_at"
)->fetchAll();

// รอมานานแค่ไหน : "2 ชม." / "3 วัน" / "ไม่ถึงชั่วโมง"
function waited(string $since): string
{
    $hours = intdiv(time() - strtotime($since), 3600);
    if ($hours >= 24) {
        return intdiv($hours, 24) . ' วัน';
    }
    return $hours > 0 ? "$hours ชม." : 'ไม่ถึงชั่วโมง';
}

$pageTitle    = 'แดชบอร์ด';
$pageSubtitle = 'วันนี้ ' . thai_date('today', true);
$activeMenu   = 'dashboard';
$useChart     = true;
require __DIR__ . '/../includes/admin_header.php';
?>
<p class="today mobile-only">วันนี้ <?= thai_date('today', true) ?></p>

<section class="stat-grid" aria-label="ภาพรวมวันนี้">
    <div class="card stat">
        <span class="stat-label"><?= icon('chart') ?>ยอดขายวันนี้</span>
        <strong><?= baht((float) $sales['today']) ?></strong>
        <span class="muted">
            <?= $diff == 0 ? 'เท่ากับเมื่อวาน' : ($diff > 0 ? 'มากกว่า' : 'น้อยกว่า') . 'เมื่อวาน ' . baht(abs($diff)) ?>
        </span>
    </div>
    <div class="card stat">
        <span class="stat-label"><?= icon('orders') ?>ออเดอร์วันนี้</span>
        <strong><?= $today['orders'] ?></strong>
        <span class="muted">ขายได้ <?= $today['items'] ?> ตัว</span>
    </div>
    <div class="card stat stat-warning">
        <span class="stat-label"><?= icon('clock') ?>รอตรวจสลิป</span>
        <strong><?= count($pending) ?></strong>
        <span class="muted"><?= $pending ? 'รอนานสุด ' . waited($pending[0]['created_at']) : 'ไม่มีค้าง' ?></span>
    </div>
    <div class="card stat stat-danger">
        <span class="stat-label"><?= icon('alert') ?>สินค้าใกล้หมด</span>
        <strong><?= count($low) ?></strong>
        <span class="muted">เหลือไม่เกิน <?= LOW_STOCK ?> ตัว</span>
    </div>
</section>

<div class="dash-grid">
    <section class="card">
        <div class="card-head">
            <div>
                <h2>ยอดขาย 7 วันล่าสุด</h2>
                <strong class="big-number" id="sumSales">–</strong>
            </div>
            <a href="report.php">ดูรายงาน</a>
        </div>
        <!-- app.js ดึงข้อมูลจาก api/report.php?range=7d แล้ววาดกราฟลง canvas นี้ -->
        <div class="chart-box"><canvas id="salesChart" data-range="7d" role="img" aria-label="กราฟยอดขาย 7 วันล่าสุด"></canvas></div>
    </section>

    <section class="card">
        <h2 class="desktop-only">ต้องจัดการ</h2>
        <nav class="todo-list" aria-label="งานที่ต้องจัดการ">
            <?php if ($pending): ?>
                <a class="todo-warning" href="orders.php">
                    <?= icon('clock') ?>
                    <span><strong>ตรวจสลิป <?= count($pending) ?> ออเดอร์</strong><span>เก่าสุด <?= order_no($pending[0]['id']) ?></span></span>
                    <?= icon('next') ?>
                </a>
            <?php endif; ?>
            <?php if ($low): ?>
                <a class="todo-danger" href="products.php?filter=low">
                    <?= icon('alert') ?>
                    <span>
                        <strong>สินค้าใกล้หมด <?= count($low) ?> รายการ</strong>
                        <span><?= e("{$low[0]['name']} ไซซ์ {$low[0]['size']}") ?> <?= $low[0]['stock'] == 0 ? 'หมดแล้ว' : "เหลือ {$low[0]['stock']} ตัว" ?></span>
                    </span>
                    <?= icon('next') ?>
                </a>
            <?php endif; ?>
            <?php foreach ($ending as $promo): ?>
                <a href="promotions.php">
                    <?= icon('tag') ?>
                    <span>
                        <strong>โปร "<?= e($promo['name']) ?>" หมดเขต<?= date('Y-m-d', strtotime($promo['end_at'])) === date('Y-m-d') ? 'วันนี้' : 'พรุ่งนี้' ?></strong>
                        <span>ต่ออายุหรือปล่อยให้หมด</span>
                    </span>
                    <?= icon('next') ?>
                </a>
            <?php endforeach; ?>
            <?php if (!$pending && !$low && !$ending): ?>
                <p class="muted">ไม่มีงานค้าง</p>
            <?php endif; ?>
        </nav>
    </section>

    <section class="card desktop-only">
        <h2>สถานะออเดอร์ 7 วัน</h2>
        <ul class="bar-list" id="statusBars"></ul>
    </section>

    <section class="card desktop-only">
        <h2>สินค้าขายดี 7 วัน</h2>
        <ul class="bar-list" id="topProducts"></ul>
    </section>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
