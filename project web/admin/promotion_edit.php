<?php
require_once __DIR__ . '/../includes/admin.php';
require_once __DIR__ . '/../includes/promotion_form.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);   // 0 = สร้างโปรใหม่

// ค่าตั้งต้นของโปรใหม่ (ช่องเงื่อนไขที่ยังไม่มีค่า ฟอร์มด้านล่างใส่ตัวเลขตัวอย่างให้)
$promo = [
    'name' => '', 'type' => 'volume', 'category_id' => null, 'start_at' => date('Y-m-d'), 'end_at' => '',
    'is_active' => 1, 'tiers' => [], 'bundle_items' => [],
];
$errors = [];

if ($id) {
    $promo = load_promotion($pdo, $id);
    if (!$promo) {
        http_response_code(404);
        exit('ไม่พบโปรโมชั่นนี้');
    }
}

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ตรวจซ้ำฝั่งเซิร์ฟเวอร์เสมอ ไม่ผ่าน = แสดงฟอร์มเดิมพร้อมค่าที่กรอกและข้อความ error
    [$promo, $errors] = read_promotion_form($_POST, $categories);
    if (!$errors) {
        save_promotion($pdo, $id, $promo);
        $_SESSION['flash'] = "บันทึกโปร \"{$promo['name']}\" แล้ว";
        header('Location: promotions.php');
        exit;
    }
}

// ประเภทโปร : value => [ชื่อ, คำอธิบาย, ไอคอน]
$types = [
    'buy_x_get_y'    => ['ซื้อ X แถม Y', 'ซื้อครบ ได้ของแถม', 'bag'],
    'timed_discount' => ['ลดราคาช่วงเวลา', 'ลด % ในวันที่กำหนด', 'clock'],
    'volume'         => ['ลดตามจำนวน', 'ซื้อมาก ลดมาก', 'chart'],
    'bundle'         => ['ชุดเซ็ต', 'หลายชิ้น ราคาเดียว', 'tag'],
];
$tiers     = $promo['tiers'] ?: [['min_qty' => 2, 'discount_percent' => 10], ['min_qty' => 3, 'discount_percent' => 15]];
$bundleIds = array_column($promo['bundle_items'], 'category_id');

$pageTitle  = $id ? 'แก้ไขโปรโมชั่น' : 'สร้างโปรโมชั่น';
$activeMenu = 'promotions';
$backUrl    = 'promotions.php';
$hideTabBar = true;
require __DIR__ . '/../includes/admin_header.php';
?>
<form class="admin-form" id="promotionForm" method="post">
    <div class="field">
        <label for="name">ชื่อโปรโมชั่น</label>
        <input type="text" id="name" name="name" value="<?= e($promo['name']) ?>" maxlength="100" placeholder="เช่น ซื้อเยอะยิ่งถูก"
               required <?= invalid_attr($errors, 'name') ?>>
        <?= field_error($errors, 'name') ?>
    </div>

    <fieldset class="field">
        <legend>ประเภท</legend>
        <div class="type-options">
            <?php foreach ($types as $value => [$label, $hint, $iconName]): ?>
                <label class="type-option">
                    <input type="radio" name="type" value="<?= $value ?>" <?= $value === $promo['type'] ? 'checked' : '' ?>>
                    <span><?= icon($iconName) ?><strong><?= $label ?></strong><small><?= $hint ?></small></span>
                </label>
            <?php endforeach; ?>
        </div>
        <?= field_error($errors, 'type') ?>
    </fieldset>

    <!-- แต่ละกล่องบอกใน data-types ว่าใช้กับประเภทไหน กล่องของประเภทอื่นถูกซ่อน และ app.js สลับให้เมื่อเปลี่ยนประเภท -->
    <fieldset class="field" <?= types_attr('buy_x_get_y timed_discount volume', $promo['type']) ?>>
        <label for="category">หมวดที่ร่วมโปร</label>
        <select id="category" name="category_id">
            <option value="">ทุกหมวด</option>
            <?php foreach ($categories as $categoryId => $categoryName): ?>
                <option value="<?= $categoryId ?>" <?= $categoryId === (int) $promo['category_id'] ? 'selected' : '' ?>><?= e($categoryName) ?></option>
            <?php endforeach; ?>
        </select>
        <?= field_error($errors, 'category_id') ?>
    </fieldset>

    <section class="card condition-card">
        <h2>เงื่อนไขส่วนลด</h2>

        <fieldset class="condition" <?= types_attr('buy_x_get_y', $promo['type']) ?>>
            <div class="tier-row">
                ซื้อ <input type="number" name="buy_qty" value="<?= e($promo['buy_qty'] ?? 2) ?>" min="1" max="99" required aria-label="จำนวนที่ต้องซื้อ">
                แถม <input type="number" name="free_qty" value="<?= e($promo['free_qty'] ?? 1) ?>" min="1" max="99" required aria-label="จำนวนที่แถม"> ตัว
            </div>
        </fieldset>

        <fieldset class="condition" <?= types_attr('timed_discount', $promo['type']) ?>>
            <div class="tier-row">
                ลด <input type="number" name="discount_value" value="<?= e($promo['discount_value'] ?? 15) ?>" min="1" max="9999" required aria-label="ส่วนลด">
                <select name="discount_type" aria-label="หน่วยส่วนลด">
                    <option value="percent">%</option>
                    <option value="fixed" <?= ($promo['discount_type'] ?? '') === 'fixed' ? 'selected' : '' ?>>บาทต่อชิ้น</option>
                </select>
            </div>
        </fieldset>

        <fieldset class="condition" <?= types_attr('volume', $promo['type']) ?>>
            <div id="tierRows">
                <?php foreach ($tiers as $tier): ?>
                    <div class="tier-row">
                        ซื้อ <input type="number" name="tier_qty[]" value="<?= e($tier['min_qty']) ?>" min="1" max="99" required aria-label="จำนวนขั้นต่ำ">
                        ตัวขึ้นไป ลด <input type="number" name="tier_percent[]" value="<?= e($tier['discount_percent']) ?>" min="1" max="90" required aria-label="ส่วนลด (%)"> %
                        <button type="button" class="icon-button remove-tier" aria-label="ลบขั้นนี้"><?= icon('trash') ?></button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="link-button add-tier" id="addTier"><?= icon('plus') ?>เพิ่มขั้นส่วนลด</button>
        </fieldset>

        <fieldset class="condition" <?= types_attr('bundle', $promo['type']) ?>>
            <p class="field-hint">หมวดในเซ็ต (หมวดละ 1 ตัว)</p>
            <div class="chips">
                <?php foreach ($categories as $categoryId => $categoryName): ?>
                    <label class="chip-option">
                        <input type="checkbox" name="bundle_categories[]" value="<?= $categoryId ?>" <?= in_array($categoryId, $bundleIds) ? 'checked' : '' ?>>
                        <span><?= e($categoryName) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
            <div class="tier-row">
                ราคาต่อเซ็ต <input type="number" name="bundle_price" value="<?= e($promo['bundle_price'] ?? 650) ?>" min="1" max="99999" required aria-label="ราคาต่อเซ็ต"> บาท
            </div>
        </fieldset>

        <?= field_error($errors, 'condition') ?>
    </section>

    <div class="field-row">
        <div class="field">
            <label for="startAt">วันเริ่ม</label>
            <input type="date" id="startAt" name="start_at" value="<?= e($promo['start_at']) ?>">
        </div>
        <div class="field">
            <label for="endAt">วันสิ้นสุด</label>
            <input type="date" id="endAt" name="end_at" value="<?= e($promo['end_at']) ?>" <?= invalid_attr($errors, 'dates') ?>>
            <p class="field-hint">เว้นว่าง = ไม่กำหนด</p>
            <?= field_error($errors, 'dates') ?>
        </div>
    </div>

    <!-- ตัวอย่างราคา : app.js ส่งค่าในฟอร์มไปคิดที่ api/promotion_preview.php แล้วแสดงผลในกล่องนี้ (ไม่มี JS = ไม่แสดงกล่อง) -->
    <section class="preview-card" id="previewCard" aria-live="polite" hidden>
        <h2>ตัวอย่างราคาที่ลูกค้าเห็น</h2>
        <p id="previewText"></p>
        <p class="preview-price" id="previewPrice">
            จ่าย <strong id="previewPay"></strong>
            <span class="tag tag-success" id="previewSave"></span>
        </p>
    </section>

    <label class="switch-row">
        <span>
            <strong>เปิดใช้งาน</strong>
            <span class="muted">ปิดไว้ก่อนได้ ลูกค้าจะยังไม่ได้ส่วนลด</span>
        </span>
        <input type="checkbox" class="switch" name="is_active" <?= $promo['is_active'] ? 'checked' : '' ?>>
    </label>

    <div class="buy-bar">
        <button class="button" type="submit">บันทึกโปรโมชั่น</button>
    </div>
</form>
<?php require __DIR__ . '/../includes/admin_footer.php';


// ---------- ฟังก์ชันของหน้านี้ ----------

// กล่องที่ใช้กับบางประเภท : บอกประเภทไว้ใน data-types และถ้าไม่ใช่ประเภทที่เลือก ให้ซ่อนและ disabled
// (กล่องที่ disabled เบราว์เซอร์จะไม่ตรวจและไม่ส่งค่าในกล่องนั้น)
function types_attr(string $types, string $selected): string
{
    $show = in_array($selected, explode(' ', $types), true);
    return "data-types=\"$types\"" . ($show ? '' : ' hidden disabled');
}

// โปรที่จะแก้ไข พร้อมขั้นส่วนลดและหมวดในเซ็ต ในรูปแบบเดียวกับ read_promotion_form() (ไม่พบ = null)
function load_promotion(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM promotions WHERE id = ?');
    $stmt->execute([$id]);
    $promo = $stmt->fetch();
    if (!$promo) {
        return null;
    }

    // ช่องวันที่ในฟอร์มใช้แค่วัน และแสดงตัวเลข 15 แทน 15.00
    $promo['start_at'] = substr($promo['start_at'] ?? '', 0, 10);
    $promo['end_at']   = substr($promo['end_at'] ?? '', 0, 10);
    foreach (['discount_value', 'bundle_price'] as $key) {
        if ($promo[$key] !== null) {
            $promo[$key] = (float) $promo[$key];
        }
    }

    $stmt = $pdo->prepare('SELECT min_qty, discount_percent FROM promotion_tiers WHERE promotion_id = ? ORDER BY min_qty');
    $stmt->execute([$id]);
    $promo['tiers'] = array_map(
        fn($tier) => ['min_qty' => $tier['min_qty'], 'discount_percent' => (float) $tier['discount_percent']],
        $stmt->fetchAll()
    );

    $stmt = $pdo->prepare('SELECT category_id, qty FROM promotion_bundle_items WHERE promotion_id = ?');
    $stmt->execute([$id]);
    $promo['bundle_items'] = $stmt->fetchAll();

    return $promo;
}

// บันทึกโปรพร้อมขั้นส่วนลดและหมวดในเซ็ต ให้สำเร็จพร้อมกันทั้งหมด
// ขั้นและหมวดในเซ็ตของเดิมลบแล้วใส่ใหม่ตามฟอร์ม เปลี่ยนประเภทโปรแล้วจึงไม่มีของเก่าค้าง
function save_promotion(PDO $pdo, int $id, array $promo): void
{
    $values = [
        $promo['name'], $promo['type'], $promo['category_id'],
        $promo['discount_type'], $promo['discount_value'], $promo['buy_qty'], $promo['free_qty'], $promo['bundle_price'],
        $promo['start_at'] !== '' ? "{$promo['start_at']} 00:00:00" : null,   // เริ่มตั้งแต่ต้นวัน
        $promo['end_at'] !== '' ? "{$promo['end_at']} 23:59:59" : null,       // ใช้ได้ถึงสิ้นวัน
        $promo['is_active'],
    ];

    $pdo->beginTransaction();
    try {
        if ($id) {
            $pdo->prepare(
                'UPDATE promotions SET name = ?, type = ?, category_id = ?, discount_type = ?, discount_value = ?,
                        buy_qty = ?, free_qty = ?, bundle_price = ?, start_at = ?, end_at = ?, is_active = ?
                  WHERE id = ?'
            )->execute([...$values, $id]);
            $pdo->prepare('DELETE FROM promotion_tiers WHERE promotion_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM promotion_bundle_items WHERE promotion_id = ?')->execute([$id]);
        } else {
            $pdo->prepare(
                'INSERT INTO promotions (name, type, category_id, discount_type, discount_value,
                                        buy_qty, free_qty, bundle_price, start_at, end_at, is_active)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute($values);
            $id = (int) $pdo->lastInsertId();
        }

        foreach ($promo['tiers'] as $tier) {
            $pdo->prepare('INSERT INTO promotion_tiers (promotion_id, min_qty, discount_percent) VALUES (?, ?, ?)')
                ->execute([$id, $tier['min_qty'], $tier['discount_percent']]);
        }
        foreach ($promo['bundle_items'] as $item) {
            $pdo->prepare('INSERT INTO promotion_bundle_items (promotion_id, category_id, qty) VALUES (?, ?, ?)')
                ->execute([$id, $item['category_id'], $item['qty']]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
