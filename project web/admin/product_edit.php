<?php
require_once __DIR__ . '/../includes/admin.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);   // 0 = เพิ่มสินค้าใหม่

// ค่าตั้งต้นของฟอร์ม (สินค้าใหม่)
$product = ['name' => '', 'base_price' => '', 'category_id' => 0, 'description' => '', 'is_active' => 1];
$stock   = array_fill_keys(SIZES, 0);
$images  = [];
$errors  = [];

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) {
        http_response_code(404);
        exit('ไม่พบสินค้านี้');
    }
    $product['base_price'] = (float) $product['base_price'];   // แสดง 300 แทน 300.00

    $stmt = $pdo->prepare('SELECT size, stock FROM product_variants WHERE product_id = ?');
    $stmt->execute([$id]);
    $stock = array_merge($stock, $stmt->fetchAll(PDO::FETCH_KEY_PAIR));

    $stmt = $pdo->prepare('SELECT id, filename FROM product_images WHERE product_id = ? ORDER BY sort_order, id');
    $stmt->execute([$id]);
    $images = $stmt->fetchAll();
}

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete' && $id) {
    // ลบได้เฉพาะสินค้าที่ยังไม่เคยมีคนสั่ง เพราะ order_items ยังอ้างถึงไซซ์ของสินค้านั้นอยู่
    if (product_was_ordered($pdo, $id)) {
        $errors['delete'] = 'สินค้านี้มีในคำสั่งซื้อแล้ว ลบไม่ได้ ให้ปิดขายแทน';
    } else {
        delete_product($pdo, $id);
        $_SESSION['flash'] = 'ลบสินค้าแล้ว';
        header('Location: products.php');
        exit;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $product = [
        'name'        => trim($_POST['name'] ?? ''),
        'base_price'  => trim($_POST['base_price'] ?? ''),
        'category_id' => (int) ($_POST['category_id'] ?? 0),
        'description' => trim($_POST['description'] ?? ''),
        'is_active'   => isset($_POST['is_active']) ? 1 : 0,
    ];
    foreach (SIZES as $size) {
        $stock[$size] = trim($_POST['stock'][$size] ?? '');
    }
    $removeImages = array_map('intval', (array) ($_POST['remove_images'] ?? []));
    [$uploads, $uploadError] = uploaded_images($_FILES['images'] ?? []);

    // ตรวจซ้ำฝั่งเซิร์ฟเวอร์เสมอ เพราะค่าที่ส่งมาจากเบราว์เซอร์แก้ไขได้
    if ($product['name'] === '' || mb_strlen($product['name']) > 100) {
        $errors['name'] = 'กรุณากรอกชื่อสินค้า (ไม่เกิน 100 ตัวอักษร)';
    }
    if (!is_numeric($product['base_price']) || $product['base_price'] < 1 || $product['base_price'] > 99999) {
        $errors['base_price'] = 'ราคาต้องเป็นตัวเลข 1 ถึง 99,999';
    }
    if (!isset($categories[$product['category_id']])) {
        $errors['category_id'] = 'กรุณาเลือกหมวดหมู่';
    }
    if (mb_strlen($product['description']) > 1000) {
        $errors['description'] = 'รายละเอียดไม่เกิน 1,000 ตัวอักษร';
    }
    foreach ($stock as $value) {
        if (!preg_match('/^\d{1,4}$/', $value)) {
            $errors['stock'] = 'สต็อกต้องเป็นจำนวนเต็ม 0 ถึง 9,999';
        }
    }
    if ($uploadError) {
        $errors['images'] = $uploadError;
    }

    if (!$errors) {
        save_product($pdo, $id, $product, array_map('intval', $stock), $removeImages, $uploads);
        $_SESSION['flash'] = 'บันทึกสินค้าแล้ว';
        header('Location: products.php');
        exit;
    }
}

$pageTitle  = $id ? 'แก้ไขสินค้า' : 'เพิ่มสินค้า';
$activeMenu = 'products';
$backUrl    = 'products.php';
$hideTabBar = true;   // มือถือใช้แถบปุ่มบันทึกด้านล่างแทน tab bar
require __DIR__ . '/../includes/admin_header.php';
?>
<form class="admin-form" id="productForm" method="post" enctype="multipart/form-data" data-low-stock="<?= LOW_STOCK ?>">
    <input type="hidden" name="action" value="save">

    <fieldset class="field">
        <legend>รูปสินค้า</legend>
        <div class="image-grid" id="imageGrid">
            <?php foreach ($images as $image): ?>
                <label class="image-item">
                    <img src="../uploads/products/<?= e($image['filename']) ?>" alt="">
                    <span><input type="checkbox" name="remove_images[]" value="<?= $image['id'] ?>">ลบ</span>
                </label>
            <?php endforeach; ?>
            <label class="image-add" id="imageAdd">
                <?= icon('plus') ?>เพิ่มรูป
                <input type="file" id="imageInput" name="images[]" accept="image/jpeg,image/png,image/webp" multiple hidden>
            </label>
        </div>
        <p class="field-hint">JPG, PNG หรือ WebP ไม่เกิน 5 MB รูปแรกคือรูปหลัก</p>
        <?= field_error($errors, 'images') ?>
    </fieldset>

    <div class="field">
        <label for="name">ชื่อสินค้า</label>
        <input type="text" id="name" name="name" value="<?= e($product['name']) ?>" maxlength="100" required <?= invalid_attr($errors, 'name') ?>>
        <?= field_error($errors, 'name') ?>
    </div>

    <div class="field">
        <label for="price">ราคา (บาท)</label>
        <input type="number" id="price" name="base_price" value="<?= e((string) $product['base_price']) ?>"
               min="1" max="99999" step="0.01" required <?= invalid_attr($errors, 'base_price') ?>>
        <?= field_error($errors, 'base_price') ?>
    </div>

    <fieldset class="field">
        <legend>หมวดหมู่</legend>
        <div class="chips">
            <?php foreach ($categories as $categoryId => $categoryName): ?>
                <label class="chip-option">
                    <input type="radio" name="category_id" value="<?= $categoryId ?>" required
                        <?= $categoryId === (int) $product['category_id'] ? 'checked' : '' ?>>
                    <span><?= e($categoryName) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <p class="field-hint">ไม่มีหมวดที่ต้องการ? เพิ่มได้ที่หน้า <a href="categories.php">หมวดหมู่สินค้า</a></p>
        <?= field_error($errors, 'category_id') ?>
    </fieldset>

    <div class="field">
        <label for="description">รายละเอียดสินค้า</label>
        <textarea id="description" name="description" rows="3" maxlength="1000" <?= invalid_attr($errors, 'description') ?>><?= e($product['description']) ?></textarea>
        <?= field_error($errors, 'description') ?>
    </div>

    <section class="card stock-card" aria-labelledby="stockTitle">
        <div class="stock-head">
            <h2 id="stockTitle">สต็อกตามไซซ์</h2>
            <span class="muted" id="stockTotal">รวม <?= array_sum(array_map('intval', $stock)) ?> ตัว</span>
        </div>
        <?php foreach (SIZES as $size): $value = (int) $stock[$size]; ?>
            <div class="stock-row">
                <label for="stock-<?= $size ?>">ไซซ์ <?= $size ?></label>
                <!-- ป้ายนี้ app.js เปลี่ยนตามตัวเลขที่กรอก -->
                <span class="tag <?= $value === 0 ? 'tag-danger' : 'tag-warning' ?>" id="stockBadge-<?= $size ?>" <?= $value > LOW_STOCK ? 'hidden' : '' ?>>
                    <?= $value === 0 ? 'หมด' : 'ใกล้หมด' ?>
                </span>
                <div class="stepper">
                    <button type="button" data-step="-1" aria-label="ลดสต็อกไซซ์ <?= $size ?>"><?= icon('minus') ?></button>
                    <input type="number" id="stock-<?= $size ?>" name="stock[<?= $size ?>]" value="<?= e((string) $stock[$size]) ?>"
                           min="0" max="9999" required data-size="<?= $size ?>">
                    <button type="button" data-step="1" aria-label="เพิ่มสต็อกไซซ์ <?= $size ?>"><?= icon('plus') ?></button>
                </div>
            </div>
        <?php endforeach; ?>
        <?= field_error($errors, 'stock') ?>
    </section>

    <label class="switch-row">
        <span>
            <strong>เปิดขาย</strong>
            <span class="muted">ถ้าปิด ลูกค้าจะไม่เห็นสินค้านี้ในร้าน</span>
        </span>
        <input type="checkbox" class="switch" name="is_active" <?= $product['is_active'] ? 'checked' : '' ?>>
    </label>

    <div class="buy-bar">
        <button class="button" type="submit"><?= $id ? 'บันทึกการเปลี่ยนแปลง' : 'เพิ่มสินค้า' ?></button>
    </div>
</form>

<?php if ($id): ?>
    <form class="delete-form" method="post" data-confirm="ลบสินค้านี้ถาวร? ลบแล้วกู้คืนไม่ได้">
        <input type="hidden" name="action" value="delete">
        <button class="button button-danger" type="submit"><?= icon('trash') ?>ลบสินค้านี้</button>
        <?= field_error($errors, 'delete') ?>
    </form>
<?php endif; ?>
<?php require __DIR__ . '/../includes/admin_footer.php';


// ---------- ฟังก์ชันของหน้านี้ ----------

// ไฟล์จาก <input type="file" multiple> : คืน [รายการรูปที่ผ่าน, ข้อความ error]
// รับเฉพาะ JPG, PNG, WebP ไม่เกิน 5 MB โดยตรวจจากเนื้อไฟล์ ไม่เชื่อนามสกุลที่ผู้ใช้ตั้งมา
function uploaded_images(array $files): array
{
    $images = [];
    foreach ($files['error'] ?? [] as $i => $error) {
        if ($error === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $info = $error === UPLOAD_ERR_OK ? @getimagesize($files['tmp_name'][$i]) : false;
        $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$info['mime'] ?? ''] ?? null;
        if (!$ext || $files['size'][$i] > 5 * 1024 * 1024) {
            return [[], 'รูปต้องเป็นไฟล์ JPG, PNG หรือ WebP ขนาดไม่เกิน 5 MB'];
        }
        $images[] = ['tmp' => $files['tmp_name'][$i], 'ext' => $ext];
    }
    return [$images, null];
}

// บันทึกสินค้า สต็อก และรูป ให้สำเร็จพร้อมกันทั้งหมด หรือย้อนกลับทั้งหมดถ้ามีขั้นไหนผิดพลาด
function save_product(PDO $pdo, int $id, array $product, array $stock, array $removeImages, array $uploads): void
{
    $folder  = __DIR__ . '/../uploads/products/';
    $removed = [];

    $pdo->beginTransaction();
    try {
        $values = [$product['name'], $product['category_id'], $product['description'], $product['base_price'], $product['is_active']];
        if ($id) {
            $pdo->prepare('UPDATE products SET name = ?, category_id = ?, description = ?, base_price = ?, is_active = ? WHERE id = ?')
                ->execute([...$values, $id]);
        } else {
            $pdo->prepare('INSERT INTO products (name, category_id, description, base_price, is_active) VALUES (?, ?, ?, ?, ?)')
                ->execute($values);
            $id = (int) $pdo->lastInsertId();
        }

        save_stock($pdo, $id, $stock);

        // รูปที่ติ๊ก "ลบ" : ลบแถวในฐานข้อมูลก่อน ไฟล์ค่อยลบหลัง commit สำเร็จ
        foreach ($removeImages as $imageId) {
            $stmt = $pdo->prepare('SELECT filename FROM product_images WHERE id = ? AND product_id = ?');
            $stmt->execute([$imageId, $id]);
            $file = $stmt->fetchColumn();
            if ($file) {
                $pdo->prepare('DELETE FROM product_images WHERE id = ?')->execute([$imageId]);
                $removed[] = $file;
            }
        }

        // รูปใหม่ต่อท้ายรูปเดิม ตั้งชื่อไฟล์แบบสุ่ม กันชื่อซ้ำและชื่อแปลก ๆ จากเครื่องผู้ใช้
        $stmt = $pdo->prepare('SELECT COALESCE(MAX(sort_order), -1) FROM product_images WHERE product_id = ?');
        $stmt->execute([$id]);
        $order = (int) $stmt->fetchColumn();
        foreach ($uploads as $upload) {
            $file = bin2hex(random_bytes(8)) . '.' . $upload['ext'];
            if (!move_uploaded_file($upload['tmp'], $folder . $file)) {
                throw new RuntimeException('บันทึกไฟล์รูปไม่สำเร็จ');
            }
            $pdo->prepare('INSERT INTO product_images (product_id, filename, sort_order) VALUES (?, ?, ?)')
                ->execute([$id, $file, ++$order]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    foreach ($removed as $file) {
        @unlink($folder . $file);   // ไฟล์หายไปก่อนแล้วก็ไม่เป็นไร
    }
}

function product_was_ordered(PDO $pdo, int $id): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM order_items oi
           JOIN product_variants v ON v.id = oi.variant_id
          WHERE v.product_id = ?'
    );
    $stmt->execute([$id]);
    return $stmt->fetchColumn() > 0;
}

// ลบสินค้าพร้อมไซซ์ ประวัติสต็อก และรูป (เรียกเฉพาะสินค้าที่ยังไม่เคยมีคนสั่ง)
function delete_product(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('SELECT filename FROM product_images WHERE product_id = ?');
    $stmt->execute([$id]);
    $files = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE m FROM stock_movements m JOIN product_variants v ON v.id = m.variant_id WHERE v.product_id = ?')
            ->execute([$id]);
        $pdo->prepare('DELETE FROM product_variants WHERE product_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);   // product_images ถูกลบตาม (ON DELETE CASCADE)
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    foreach ($files as $file) {
        @unlink(__DIR__ . '/../uploads/products/' . $file);
    }
}
