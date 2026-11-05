<?php
require_once __DIR__ . '/../includes/admin.php';
require_admin();

const PER_PAGE = 10;

// ตัวกรอง : key => [ชื่อ, เงื่อนไข SQL, การเรียง] (รอตรวจเรียงเก่าสุดก่อน จะได้ตรวจตามลำดับ)
$filters = [
    'pending' => ['รอตรวจ', "o.status = 'pending'", 'o.created_at'],
    'paid'    => ['ยืนยันแล้ว', 'o.status IN (' . PAID_SQL . ')', 'o.created_at DESC'],
    'all'     => ['ทั้งหมด', '1 = 1', 'o.created_at DESC'],
];
$filter = isset($filters[$_GET['filter'] ?? '']) ? $_GET['filter'] : 'pending';
[, $where, $orderBy] = $filters[$filter];

// ยืนยัน / ปฏิเสธสลิป แล้วกลับมาหน้าเดิม (กดรีเฟรชจะได้ไม่ส่งซ้ำ)
$notice = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $done    = false;
    if (($_POST['action'] ?? '') === 'confirm') {
        $done = confirm_payment($pdo, $orderId);
        $message = 'ยืนยันการชำระ ' . order_no($orderId) . ' แล้ว';
    } elseif (($_POST['action'] ?? '') === 'reject') {
        $reason  = mb_substr(trim($_POST['reason'] ?? ''), 0, 200) ?: 'สลิปไม่ถูกต้อง';
        $done    = reject_payment($pdo, $orderId, $reason);
        $message = 'ปฏิเสธ ' . order_no($orderId) . ' แล้ว คืนสต็อกเรียบร้อย';
    }

    if ($done) {
        $_SESSION['flash'] = $message;
        header("Location: orders.php?filter=$filter");
        exit;
    }
    $notice = 'ออเดอร์นี้ถูกจัดการไปแล้ว หรือไม่ได้รอตรวจสลิป';
}

$pendingCount = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
$todaySales   = (float) $pdo->query(
    'SELECT COALESCE(SUM(net_total), 0) FROM orders WHERE status IN (' . PAID_SQL . ') AND DATE(created_at) = CURDATE()'
)->fetchColumn();

// แบ่งหน้า : หน้าละ PER_PAGE ออเดอร์
$total  = (int) $pdo->query("SELECT COUNT(*) FROM orders o WHERE $where")->fetchColumn();
$page   = min(max(1, (int) ($_GET['page'] ?? 1)), max(1, (int) ceil($total / PER_PAGE)));
$offset = ($page - 1) * PER_PAGE;

$orders = $pdo->query(
    "SELECT o.*, u.first_name, u.last_name, c.code AS coupon_code,
            (SELECT SUM(qty) FROM order_items WHERE order_id = o.id) AS item_count
       FROM orders o
       JOIN users u ON u.id = o.user_id
       LEFT JOIN coupons c ON c.id = o.coupon_id
      WHERE $where
      ORDER BY $orderBy
      LIMIT " . PER_PAGE . " OFFSET $offset"
)->fetchAll();

$methods = ['qr' => 'PromptPay', 'cod' => 'เก็บเงินปลายทาง'];

$pageTitle    = 'ออเดอร์ · ตรวจสลิป';
$pageSubtitle = "รอตรวจสลิป $pendingCount ออเดอร์";
$activeMenu   = 'orders';
require __DIR__ . '/../includes/admin_header.php';
?>
<section class="stat-grid stat-grid-2" aria-label="ภาพรวม">
    <div class="card stat stat-warning">
        <span class="stat-label"><?= icon('clock') ?>รอตรวจสลิป</span>
        <strong><?= $pendingCount ?> ออเดอร์</strong>
    </div>
    <div class="card stat">
        <span class="stat-label"><?= icon('chart') ?>ยอดขายวันนี้</span>
        <strong><?= baht($todaySales) ?></strong>
    </div>
</section>

<nav class="chips page-chips" aria-label="กรองออเดอร์">
    <?php foreach ($filters as $key => [$label]): ?>
        <a class="chip" href="orders.php?filter=<?= $key ?>" <?= $key === $filter ? 'aria-current="page"' : '' ?>>
            <?= $key === $filter ? icon('check') : '' ?><?= $label ?><?= $key === 'pending' ? " ($pendingCount)" : '' ?>
        </a>
    <?php endforeach; ?>
</nav>

<?php if ($notice): ?>
    <p class="notice" role="status"><?= e($notice) ?></p>
<?php endif; ?>

<?php if (!$orders): ?>
    <p class="muted">ไม่มีออเดอร์ในกลุ่มนี้</p>
<?php endif; ?>

<div class="order-list">
    <?php foreach ($orders as $order): ?>
        <article class="card order-card">
            <div class="order-head">
                <h2><?= order_no($order['id']) ?></h2>
                <?= status_tag($order['status']) ?>
            </div>
            <p class="muted">
                <?= e("{$order['first_name']} {$order['last_name']}") ?> · <?= $order['item_count'] ?> ชิ้น · <?= thai_time($order['created_at']) ?>
            </p>

            <?php if ($order['status'] === 'pending'): ?>
                <div class="slip-body">
                    <?php if ($order['slip_image']): ?>
                        <a class="slip-thumb" href="../uploads/slips/<?= e($order['slip_image']) ?>" target="_blank">
                            <img src="../uploads/slips/<?= e($order['slip_image']) ?>" alt="สลิปของออเดอร์ <?= order_no($order['id']) ?>">
                        </a>
                    <?php else: ?>
                        <div class="slip-thumb"><?= icon('eye') ?><span>ยังไม่มีสลิป</span></div>
                    <?php endif; ?>
                    <dl class="summary-list">
                        <div><dt>ยอดที่ต้องชำระ</dt><dd><?= baht((float) $order['net_total']) ?></dd></div>
                        <div><dt>คูปอง</dt><dd><?= e($order['coupon_code'] ?? '–') ?></dd></div>
                        <div><dt>วิธีชำระ</dt><dd><?= $methods[$order['payment_method']] ?></dd></div>
                    </dl>
                </div>
                <!-- กดปฏิเสธ : app.js ถามเหตุผลแล้วใส่ในช่อง reason (ไม่มี JS = ใช้เหตุผลตั้งต้น) -->
                <form class="slip-actions" method="post">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                    <input type="hidden" name="reason" value="">
                    <button class="button button-danger" type="submit" name="action" value="reject">ปฏิเสธ</button>
                    <button class="button" type="submit" name="action" value="confirm">ยืนยันการชำระ</button>
                </form>
            <?php else: ?>
                <p class="order-line">
                    <span>ยอด <strong><?= baht((float) $order['net_total']) ?></strong></span>
                    <span class="muted"><?= $methods[$order['payment_method']] ?></span>
                </p>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</div>

<?php if ($orders): ?>
    <?= pagination($total, $page, PER_PAGE, fn($n) => "orders.php?filter=$filter&page=$n") ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/admin_footer.php';


// ---------- ฟังก์ชันของหน้านี้ ----------
// ทั้งสองแบบทำได้เฉพาะออเดอร์ที่ยังรอตรวจ (WHERE status = 'pending') กันแอดมินสองคนกดซ้ำ

// ยืนยันการชำระ : เปลี่ยนเป็นชำระแล้วและบันทึกเวลา
function confirm_payment(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare("UPDATE orders SET status = 'paid', paid_at = NOW() WHERE id = ? AND status = 'pending'");
    $stmt->execute([$id]);
    return $stmt->rowCount() === 1;
}

// ปฏิเสธสลิป : ยกเลิกออเดอร์ บันทึกเหตุผลให้ลูกค้าเห็น และคืนสต็อก ให้สำเร็จพร้อมกันทั้งหมด
function reject_payment(PDO $pdo, int $id, string $reason): bool
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled', cancel_reason = ? WHERE id = ? AND status = 'pending'");
        $stmt->execute([$reason, $id]);
        if ($stmt->rowCount() !== 1) {
            $pdo->rollBack();
            return false;
        }

        $items = $pdo->prepare('SELECT variant_id, qty FROM order_items WHERE order_id = ?');
        $items->execute([$id]);
        foreach ($items as $item) {
            $pdo->prepare('UPDATE product_variants SET stock = stock + ? WHERE id = ?')->execute([$item['qty'], $item['variant_id']]);
            $pdo->prepare("INSERT INTO stock_movements (variant_id, order_id, type, qty) VALUES (?, ?, 'in', ?)")
                ->execute([$item['variant_id'], $id, $item['qty']]);
        }

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
