<?php
require_once __DIR__ . '/../includes/admin.php';
require_admin();

$tab = ($_GET['tab'] ?? '') === 'coupons' ? 'coupons' : 'promotions';

// เปิด/ปิดโปร : สวิตช์ส่งฟอร์มทันทีที่กด (data-autosubmit) บันทึกแล้วกลับมาหน้าเดิม (กดรีเฟรชจะได้ไม่ส่งซ้ำ)
// ไม่ติ๊ก = เบราว์เซอร์ไม่ส่ง is_active มา = ปิด
$notice = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id     = (int) ($_POST['promotion_id'] ?? 0);
    $active = isset($_POST['is_active']) ? 1 : 0;

    $stmt = $pdo->prepare('SELECT name FROM promotions WHERE id = ?');
    $stmt->execute([$id]);
    $name = $stmt->fetchColumn();

    if ($name === false) {
        $notice = 'ไม่พบโปรโมชั่นนี้';
    } else {
        $pdo->prepare('UPDATE promotions SET is_active = ? WHERE id = ?')->execute([$active, $id]);
        $_SESSION['flash'] = $active ? "เปิดใช้ \"$name\" แล้ว" : "ปิด \"$name\" แล้ว ลูกค้าจะไม่ได้ส่วนลดนี้";
        header('Location: promotions.php');
        exit;
    }
}

$promotions = $pdo->query(
    'SELECT p.*, c.name AS category FROM promotions p LEFT JOIN categories c ON c.id = p.category_id ORDER BY p.id'
)->fetchAll();

// ขั้นส่วนลด (volume) และหมวดในเซ็ต (bundle) จัดกลุ่มตามโปร
$tiers = [];
foreach ($pdo->query('SELECT * FROM promotion_tiers ORDER BY min_qty') as $row) {
    $tiers[$row['promotion_id']][] = $row;
}
$bundles = [];
foreach ($pdo->query('SELECT b.promotion_id, c.name FROM promotion_bundle_items b JOIN categories c ON c.id = b.category_id ORDER BY c.id') as $row) {
    $bundles[$row['promotion_id']][] = $row['name'];
}

$coupons = $pdo->query(
    "SELECT c.*,
            (SELECT COUNT(*) FROM orders o WHERE o.coupon_id = c.id AND o.status NOT IN ('cancelled', 'expired')) AS used
       FROM coupons c
      ORDER BY c.id"
)->fetchAll();

$pageTitle    = 'โปรโมชั่น';
$pageSubtitle = count($promotions) . ' โปรโมชั่น · ' . count($coupons) . ' คูปอง';
$activeMenu   = 'promotions';
$headerAction = ['สร้างโปร', 'promotion_edit.php'];
require __DIR__ . '/../includes/admin_header.php';
?>
<nav class="segmented" aria-label="ประเภท">
    <a href="promotions.php" <?= $tab === 'promotions' ? 'aria-current="page"' : '' ?>>โปรโมชั่น <?= count($promotions) ?></a>
    <a href="promotions.php?tab=coupons" <?= $tab === 'coupons' ? 'aria-current="page"' : '' ?>>คูปอง <?= count($coupons) ?></a>
</nav>

<?php if ($notice): ?>
    <p class="notice" role="status"><?= e($notice) ?></p>
<?php endif; ?>

<div class="promo-list">
    <?php if ($tab === 'promotions'): ?>
        <?php foreach ($promotions as $p): [$state, $stateClass] = promo_state($p); ?>
            <article class="card promo-card">
                <div class="promo-head">
                    <h2><?= e($p['name']) ?></h2>
                    <form method="post">
                        <input type="hidden" name="promotion_id" value="<?= $p['id'] ?>">
                        <label class="switch-label">
                            <input type="checkbox" class="switch" name="is_active" data-autosubmit <?= $p['is_active'] ? 'checked' : '' ?>
                                   aria-label="เปิดใช้โปร <?= e($p['name']) ?>">
                            <?= $p['is_active'] ? 'เปิด' : 'ปิด' ?>
                        </label>
                    </form>
                </div>
                <div class="tag-row">
                    <span class="tag"><?= icon('tag') . promo_type($p) ?></span>
                    <span class="tag <?= $stateClass ?>"><?= $state ?></span>
                </div>
                <p><?= e(promo_detail($p, $tiers[$p['id']] ?? [], $bundles[$p['id']] ?? [])) ?></p>
                <div class="promo-foot">
                    <span class="muted"><?= date_range($p['start_at'], $p['end_at']) ?></span>
                    <a class="title-icon" href="promotion_edit.php?id=<?= $p['id'] ?>" aria-label="แก้ไข <?= e($p['name']) ?>"><?= icon('edit') ?>แก้ไข</a>
                </div>
            </article>
        <?php endforeach; ?>
    <?php else: ?>
        <?php foreach ($coupons as $c): ?>
            <?php $expired = $c['expires_at'] && $c['expires_at'] < date('Y-m-d H:i:s'); ?>
            <?php $usedUp = $c['max_uses'] && $c['used'] >= $c['max_uses']; ?>
            <article class="card promo-card">
                <div class="promo-head">
                    <h2 class="coupon-code"><?= e($c['code']) ?></h2>
                    <?php if ($expired): ?>
                        <span class="tag">หมดอายุแล้ว</span>
                    <?php elseif ($usedUp): ?>
                        <span class="tag">ใช้ครบแล้ว</span>
                    <?php else: ?>
                        <span class="tag tag-success"><?= icon('check') ?>ใช้ได้</span>
                    <?php endif; ?>
                </div>
                <p>
                    ลด <?= $c['discount_type'] === 'percent' ? (float) $c['discount_value'] . '%' : baht((float) $c['discount_value']) ?>
                    · ขั้นต่ำ <?= baht((float) $c['min_total']) ?>
                </p>
                <p class="muted">
                    ใช้แล้ว <?= $c['used'] ?><?= $c['max_uses'] ? " / {$c['max_uses']}" : '' ?> ครั้ง
                    · <?= $c['expires_at'] ? 'หมดอายุ ' . thai_date($c['expires_at']) : 'ไม่มีวันหมดอายุ' ?>
                </p>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php';


// ---------- ฟังก์ชันของหน้านี้ ----------

// ชื่อประเภทบนป้าย
function promo_type(array $p): string
{
    return match ($p['type']) {
        'buy_x_get_y'    => "ซื้อ {$p['buy_qty']} แถม {$p['free_qty']}",
        'timed_discount' => 'ลดราคาช่วงเวลา',
        'volume'         => 'ลดตามจำนวน',
        'bundle'         => 'ชุดเซ็ต',
    };
}

// สถานะตอนนี้ : [ข้อความ, class ของป้าย]
function promo_state(array $p): array
{
    $now = date('Y-m-d H:i:s');
    $end = $p['end_at'] ? date('Y-m-d', strtotime($p['end_at'])) : null;
    if (!$p['is_active']) {
        return ['ปิดอยู่', ''];
    }
    if ($p['start_at'] && $p['start_at'] > $now) {
        return ['ตั้งเวลาไว้', 'tag-primary'];
    }
    if ($p['end_at'] && $p['end_at'] < $now) {
        return ['หมดเขตแล้ว', ''];
    }
    if ($end === date('Y-m-d')) {
        return ['หมดเขตวันนี้', 'tag-warning'];
    }
    if ($end === date('Y-m-d', strtotime('+1 day'))) {
        return ['หมดเขตพรุ่งนี้', 'tag-warning'];
    }
    return ['กำลังใช้', 'tag-success'];
}

// เงื่อนไขของโปรเป็นประโยคสั้น ๆ
function promo_detail(array $p, array $tiers, array $bundle): string
{
    $scope = $p['category'] ? "หมวด{$p['category']}" : 'ทุกสินค้า';
    return match ($p['type']) {
        'buy_x_get_y'    => "$scope · แถมตัวที่ราคาต่ำที่สุดในตะกร้า",
        'timed_discount' => 'ลด ' . ($p['discount_type'] === 'percent' ? (float) $p['discount_value'] . '%' : 'ชิ้นละ ' . baht((float) $p['discount_value'])) . " $scope",
        'volume'         => "$scope · " . implode(', ', array_map(fn($t) => "{$t['min_qty']} ตัวขึ้นไป ลด " . (float) $t['discount_percent'] . '%', $tiers)),
        'bundle'         => implode(' + ', $bundle) . ' เซ็ตละ ' . baht((float) $p['bundle_price']),
    };
}

// ช่วงวันที่ : "1 ถึง 31 ต.ค. 2569" (เดือนเดียวกัน) หรือ "1 มิ.ย. 2569 ถึง 31 ต.ค. 2569"
function date_range(?string $start, ?string $end): string
{
    if (!$end) {
        return 'ไม่กำหนดวันหมดเขต';
    }
    if (!$start) {
        return 'ถึง ' . thai_date($end);
    }
    $sameMonth = date('Y-m', strtotime($start)) === date('Y-m', strtotime($end));
    return ($sameMonth ? date('j', strtotime($start)) : thai_date($start)) . ' ถึง ' . thai_date($end);
}
