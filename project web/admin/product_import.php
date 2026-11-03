<?php
require_once __DIR__ . '/../includes/admin.php';
require_admin();

const MAX_IMPORT = 100;   // นำเข้าได้ครั้งละไม่เกินนี้

// ชื่อหมวด => id (ในไฟล์ใช้ชื่อหมวด เพราะคนเขียนไฟล์ไม่รู้ id)
$categories = $pdo->query('SELECT name, id FROM categories ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
$errors     = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file = $_FILES['file'] ?? null;
    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'กรุณาเลือกไฟล์ JSON';
    } elseif ($file['size'] > 1024 * 1024) {
        $errors[] = 'ไฟล์ต้องไม่เกิน 1 MB';
    } else {
        [$items, $errors] = read_import(file_get_contents($file['tmp_name']), $pdo, $categories);
    }

    // ผ่านทุกรายการเท่านั้นถึงบันทึก ผิดแม้รายการเดียว = ไม่บันทึกอะไรเลย แก้ไฟล์แล้วนำเข้าใหม่ได้ทันที
    if (!$errors) {
        import_products($pdo, $items);
        $_SESSION['flash'] = 'นำเข้าสินค้า ' . count($items) . ' รายการแล้ว';
        header('Location: products.php');
        exit;
    }
}

$pageTitle  = 'นำเข้าสินค้าจาก JSON';
$activeMenu = 'products';
$backUrl    = 'products.php';
$hideTabBar = true;
require __DIR__ . '/../includes/admin_header.php';
?>
<form class="admin-form" method="post" enctype="multipart/form-data">
    <?php if ($errors): ?>
        <div class="notice notice-danger" role="alert">
            <strong>นำเข้าไม่สำเร็จ ยังไม่ได้บันทึกสินค้าใด ๆ</strong>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <section class="card import-help">
        <h2>รูปแบบไฟล์</h2>
        <p>อาร์เรย์ของสินค้า แต่ละรายการมี name, category (ชื่อหมวด) และ price ส่วน description กับ stock ใส่หรือไม่ใส่ก็ได้ ไซซ์ที่ไม่ใส่ = 0 ตัว</p>
        <pre>[
  {
    "name": "Henley Tee",
    "category": "เสื้อยืด",
    "price": 420,
    "description": "เสื้อยืดคอกระดุม",
    "stock": { "S": 10, "M": 15, "L": 12, "XL": 6 }
  }
]</pre>
        <p class="muted">หมวดที่ใช้ได้ : <?= e(implode(', ', array_keys($categories))) ?> · ไซซ์ : <?= implode(', ', SIZES) ?> · ครั้งละไม่เกิน <?= MAX_IMPORT ?> รายการ</p>
        <a class="title-icon" href="../assets/sample-products.json" download><?= icon('download') ?>ดาวน์โหลดไฟล์ตัวอย่าง</a>
    </section>

    <div class="field">
        <label for="importFile">ไฟล์ JSON</label>
        <input type="file" id="importFile" name="file" accept=".json,application/json" required>
    </div>

    <!-- ตัวอย่างข้อมูลในไฟล์ : app.js อ่านไฟล์ที่เลือกแล้วสร้างแถวในตาราง (ยังไม่บันทึกจนกว่าจะกดนำเข้า) -->
    <p class="field-error" id="importError" hidden></p>
    <section class="card import-preview" id="importPreview" hidden>
        <h2 id="importSummary"></h2>
        <div class="table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>ชื่อ</th>
                        <th>หมวด</th>
                        <th class="num">ราคา</th>
                        <?php foreach (SIZES as $size): ?>
                            <th class="num"><?= $size ?></th>
                        <?php endforeach; ?>
                        <th class="num">รวม</th>
                    </tr>
                </thead>
                <tbody id="importRows"></tbody>
            </table>
        </div>
    </section>

    <div class="buy-bar">
        <button class="button" type="submit" id="importSubmit">นำเข้าสินค้า</button>
    </div>
</form>
<?php require __DIR__ . '/../includes/admin_footer.php';


// ---------- ฟังก์ชันของหน้านี้ ----------

// แปลงข้อความ JSON เป็นรายการสินค้าที่พร้อมบันทึก : คืน [รายการสินค้า, ข้อความ error]
// ไฟล์มาจากผู้ใช้จึงตรวจทุกช่อง และบอกลำดับรายการที่ผิดให้หาเจอง่าย
function read_import(string $json, PDO $pdo, array $categories): array
{
    $json = preg_replace('/^\xEF\xBB\xBF/', '', $json);   // ไฟล์ที่บันทึกจาก Notepad มักมี BOM นำหน้า
    $data = json_decode($json, true);

    if (!is_array($data) || !array_is_list($data)) {
        return [[], ['ไฟล์ต้องเป็น JSON แบบอาร์เรย์ของสินค้า [ {...}, {...} ] และบันทึกเป็น UTF-8']];
    }
    if (!$data) {
        return [[], ['ไม่มีสินค้าในไฟล์']];
    }
    if (count($data) > MAX_IMPORT) {
        return [[], ['นำเข้าได้ครั้งละไม่เกิน ' . MAX_IMPORT . ' รายการ']];
    }

    // ชื่อที่ใช้ไปแล้ว (ตัวพิมพ์เล็ก) : สินค้าที่มีอยู่ + รายการก่อนหน้าในไฟล์ กันนำเข้าซ้ำ
    $taken = array_fill_keys(array_map('mb_strtolower', $pdo->query('SELECT name FROM products')->fetchAll(PDO::FETCH_COLUMN)), true);

    $items  = [];
    $errors = [];
    foreach ($data as $i => $row) {
        [$item, $problem] = read_import_row($row, $categories);
        if (!$problem && isset($taken[mb_strtolower($item['name'])])) {
            $problem = "ชื่อ \"{$item['name']}\" ซ้ำกับสินค้าที่มีอยู่ หรือรายการอื่นในไฟล์";
        }

        if ($problem) {
            $errors[] = 'รายการที่ ' . ($i + 1) . " : $problem";
        } else {
            $items[] = $item;
            $taken[mb_strtolower($item['name'])] = true;
        }
    }
    return [$items, $errors];
}

// ตรวจสินค้า 1 รายการ : คืน [สินค้า, ปัญหาแรกที่เจอ (null = ผ่าน)]
// ค่าจาก json_decode เป็นได้หลายชนิด (ข้อความ ตัวเลข อาร์เรย์ ...) จึงเช็กชนิดก่อนใช้ทุกครั้ง
function read_import_row(mixed $row, array $categories): array
{
    if (!is_array($row)) {
        return [null, 'ต้องเป็นข้อมูลสินค้าในวงเล็บ { }'];
    }
    $name     = is_string($row['name'] ?? null) ? trim($row['name']) : '';
    $category = is_string($row['category'] ?? null) ? $row['category'] : '';
    $price    = $row['price'] ?? null;
    $desc     = $row['description'] ?? '';
    $stock    = $row['stock'] ?? [];

    if ($name === '' || mb_strlen($name) > 100) {
        return [null, 'name ต้องเป็นข้อความ 1 ถึง 100 ตัวอักษร'];
    }
    if (!isset($categories[$category])) {
        return [null, "ไม่พบหมวด \"$category\""];
    }
    if (!is_numeric($price) || $price < 1 || $price > 99999) {
        return [null, 'price ต้องเป็นตัวเลข 1 ถึง 99,999'];
    }
    if (!is_string($desc) || mb_strlen($desc) > 1000) {
        return [null, 'description ต้องเป็นข้อความไม่เกิน 1,000 ตัวอักษร'];
    }
    if (!is_array($stock) || array_diff(array_keys($stock), SIZES)) {
        return [null, 'stock ต้องอยู่ในรูป { "S": 10, "M": 5 } ใช้ได้เฉพาะไซซ์ ' . implode(', ', SIZES)];
    }
    foreach ($stock as $qty) {
        if (!is_int($qty) || $qty < 0 || $qty > 9999) {
            return [null, 'จำนวนสต็อกต้องเป็นจำนวนเต็ม 0 ถึง 9,999'];
        }
    }

    return [[
        'name'        => $name,
        'category_id' => $categories[$category],
        'price'       => round((float) $price, 2),
        'description' => trim($desc),
        'stock'       => array_merge(array_fill_keys(SIZES, 0), $stock),   // ไซซ์ที่ไม่ใส่ = 0
    ], null];
}

// บันทึกสินค้าทุกรายการพร้อมกัน : สำเร็จทั้งหมด หรือย้อนกลับทั้งหมดถ้ามีขั้นไหนผิดพลาด
// สต็อกเริ่มต้นบันทึกเป็นการรับเข้า (in) ใน stock_movements ด้วย save_stock() ตัวเดียวกับหน้าแก้ไขสินค้า
function import_products(PDO $pdo, array $items): void
{
    $pdo->beginTransaction();
    try {
        foreach ($items as $item) {
            $pdo->prepare('INSERT INTO products (name, category_id, description, base_price, is_active) VALUES (?, ?, ?, ?, 1)')
                ->execute([$item['name'], $item['category_id'], $item['description'], $item['price']]);
            save_stock($pdo, (int) $pdo->lastInsertId(), $item['stock']);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
