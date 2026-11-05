<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/catalog.php';

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
$filters    = read_filters($_GET, $categories);
$products   = find_products($pdo, $filters);

// จำนวนสินค้าที่เปิดขายในแต่ละหมวด (จอใหญ่แสดงข้างชื่อหมวด)
$counts = $pdo->query('SELECT category_id, COUNT(*) FROM products WHERE is_active = 1 GROUP BY category_id')
    ->fetchAll(PDO::FETCH_KEY_PAIR);

// แบนเนอร์ : โปรที่ใช้ได้ตอนนี้และใกล้หมดเขตที่สุด (โปรที่ไม่มีวันหมดเขตอยู่ท้ายสุด)
$promo = $pdo->query(
    'SELECT p.*, c.name AS category
       FROM promotions p
       LEFT JOIN categories c ON c.id = p.category_id
      WHERE p.is_active = 1
        AND (p.start_at IS NULL OR p.start_at <= NOW())
        AND (p.end_at IS NULL OR p.end_at >= NOW())
      ORDER BY p.end_at IS NULL, p.end_at, p.id
      LIMIT 1'
)->fetch();

$pageTitle = $filters['q'] === '' ? 'หน้าแรก' : "ค้นหา \"{$filters['q']}\"";
$activeTab = 'home';
require __DIR__ . '/../includes/header.php';
?>
<form class="search-box mobile-only" action="index.php" role="search">
    <?= icon('search') ?>
    <input type="search" id="q" name="q" value="<?= e($filters['q']) ?>" placeholder="ค้นหาเสื้อ เช่น เสื้อยืด, ฮู้ด…" aria-label="ค้นหาสินค้า">
</form>

<div class="shop">
    <!-- ตัวกรองทั้งหมดอยู่ในฟอร์มเดียว : มือถือ = แถวชิปหมวดหมู่ + แผ่นตัวกรองที่เลื่อนขึ้นจากด้านล่าง, จอใหญ่ = แถบด้านซ้าย
         app.js ส่งค่าในฟอร์มไป api/products.php ทุกครั้งที่เปลี่ยน แล้วใส่รายการสินค้าใหม่แทนของเดิม -->
    <form class="filters" id="filterForm" action="index.php">
        <input type="hidden" name="q" value="<?= e($filters['q']) ?>">

        <div class="filters-head desktop-only">
            <h2>ตัวกรอง</h2>
            <a href="index.php">ล้างทั้งหมด</a>
        </div>

        <fieldset class="category-filter">
            <legend class="desktop-only">หมวดหมู่</legend>
            <div class="category-chips">
                <?php foreach ([0 => 'ทั้งหมด'] + $categories as $id => $name): ?>
                    <?php // หมวดที่ยังไม่มีสินค้าเปิดขายไม่ต้องแสดง (เว้นแต่กำลังเลือกอยู่)
                    if ($id && empty($counts[$id]) && $id !== $filters['category']) continue; ?>
                    <label class="chip-option">
                        <input type="radio" name="category" value="<?= $id ?: '' ?>" <?= $id === $filters['category'] ? 'checked' : '' ?>>
                        <span><?= e($name) ?><small class="category-count"><?= $id ? ($counts[$id] ?? 0) : array_sum($counts) ?></small></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <button type="button" class="chip filter-open mobile-only" id="filterOpen" aria-label="ตัวกรองเพิ่มเติม"><?= icon('filter') ?></button>
        </fieldset>

        <div class="sheet-backdrop" id="sheetBackdrop" hidden></div>
        <section class="filter-sheet" id="filterSheet" aria-label="ตัวกรอง">
            <div class="sheet-head mobile-only">
                <h2>ตัวกรอง</h2>
                <button type="button" class="icon-button" id="filterClose" aria-label="ปิดตัวกรอง"><?= icon('x') ?></button>
            </div>

            <fieldset>
                <legend>ไซซ์</legend>
                <div class="chips">
                    <?php foreach (SIZES as $size): ?>
                        <label class="chip-option">
                            <input type="checkbox" name="size[]" value="<?= $size ?>" <?= in_array($size, $filters['sizes'], true) ? 'checked' : '' ?>>
                            <span><?= $size ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <fieldset>
                <legend>ช่วงราคา</legend>
                <div class="chips">
                    <?php foreach (['' => ['ทุกราคา']] + PRICE_RANGES as $key => [$label]): ?>
                        <label class="chip-option">
                            <input type="radio" name="price" value="<?= $key ?>" <?= $key === $filters['price'] ? 'checked' : '' ?>>
                            <span><?= $label ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <fieldset>
                <legend>เรียงตาม</legend>
                <div class="chips">
                    <?php foreach (SORTS as $key => [$label]): ?>
                        <label class="chip-option">
                            <input type="radio" name="sort" value="<?= $key ?>" <?= $key === $filters['sort'] ? 'checked' : '' ?>>
                            <span><?= $label ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <!-- ไม่มี JS : ปุ่มนี้ส่งฟอร์มโหลดหน้าใหม่ตามปกติ / มี JS : รายการอัปเดตไปแล้ว ปุ่มนี้แค่ปิดแผ่นตัวกรอง -->
            <div class="sheet-actions mobile-only">
                <a class="button button-outline" href="index.php">ล้างทั้งหมด</a>
                <button class="button" type="submit" id="showResults">แสดง <?= count($products) ?> รายการ</button>
            </div>
        </section>
    </form>

    <?php if ($promo): ?>
        <section class="promo-banner" aria-label="โปรโมชั่น">
            <div>
                <p><?= promo_ends($promo['end_at']) ?></p>
                <h2><?= e(promo_headline($pdo, $promo)) ?></h2>
                <p><?= e(coupon_hint($pdo) ?? $promo['name']) ?></p>
            </div>
            <?= icon('shirt') ?>
        </section>
    <?php endif; ?>

    <section class="shop-main" aria-labelledby="resultTitle">
        <div class="section-head">
            <h1 id="resultTitle"><?= e(result_title($filters, $categories)) ?></h1>
            <span class="muted" id="resultCount"><?= count($products) ?> รายการ</span>
        </div>
        <ul class="product-grid" id="productGrid"><?= product_grid($products) ?></ul>
    </section>
</div>
<?php require __DIR__ . '/../includes/footer.php';


// ---------- ฟังก์ชันของหน้านี้ (แบนเนอร์โปร) ----------

// ข้อความใหญ่บนแบนเนอร์ : เงื่อนไขของโปรแบบสั้น ๆ
function promo_headline(PDO $pdo, array $p): string
{
    $scope = $p['category'] ? "หมวด{$p['category']}" : 'ทุกสินค้า';
    switch ($p['type']) {
        case 'buy_x_get_y':
            return "ซื้อ {$p['buy_qty']} แถม {$p['free_qty']} $scope";

        case 'timed_discount':
            $off = $p['discount_type'] === 'percent' ? (float) $p['discount_value'] . '%' : 'ชิ้นละ ' . baht((float) $p['discount_value']);
            return "ลด $off $scope";

        case 'volume':   // บอกขั้นแรก เช่น "ซื้อ 2 ตัว ลด 10%"
            $stmt = $pdo->prepare('SELECT min_qty, discount_percent FROM promotion_tiers WHERE promotion_id = ? ORDER BY min_qty LIMIT 1');
            $stmt->execute([$p['id']]);
            $tier = $stmt->fetch();
            return "ซื้อ {$tier['min_qty']} ตัว ลด " . (float) $tier['discount_percent'] . '%';

        default:         // bundle : "เสื้อยืด + เสื้อโปโล เซ็ตละ ฿650"
            $stmt = $pdo->prepare(
                'SELECT c.name FROM promotion_bundle_items b JOIN categories c ON c.id = b.category_id
                  WHERE b.promotion_id = ? ORDER BY c.id'
            );
            $stmt->execute([$p['id']]);
            return implode(' + ', $stmt->fetchAll(PDO::FETCH_COLUMN)) . ' เซ็ตละ ' . baht((float) $p['bundle_price']);
    }
}

// ป้ายเล็กบนแบนเนอร์ : "หมดเขตวันนี้" / "ถึง 31 ต.ค. 2569" / "โปรประจำร้าน" (ไม่มีวันหมดเขต)
function promo_ends(?string $end): string
{
    if (!$end) {
        return 'โปรประจำร้าน';
    }
    return date('Y-m-d', strtotime($end)) === date('Y-m-d') ? 'หมดเขตวันนี้' : 'ถึง ' . thai_date($end);
}

// คูปองที่ยังใช้ได้และยอดขั้นต่ำน้อยที่สุด เป็นประโยคชวนใช้ (ไม่มีคูปอง = null)
function coupon_hint(PDO $pdo): ?string
{
    $c = $pdo->query(
        "SELECT * FROM coupons c
          WHERE (c.expires_at IS NULL OR c.expires_at >= NOW())
            AND (c.max_uses IS NULL OR c.max_uses > (SELECT COUNT(*) FROM orders o
                                                     WHERE o.coupon_id = c.id AND o.status NOT IN ('cancelled', 'expired')))
          ORDER BY c.min_total, c.id
          LIMIT 1"
    )->fetch();
    if (!$c) {
        return null;
    }

    $off = $c['discount_type'] === 'percent' ? (float) $c['discount_value'] . '%' : baht((float) $c['discount_value']);
    $min = $c['min_total'] > 0 ? ' เมื่อซื้อครบ ' . baht((float) $c['min_total']) : '';
    return "กรอกโค้ด {$c['code']} ในตะกร้า ลดเพิ่ม $off$min";
}
