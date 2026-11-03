<?php
require_once __DIR__ . '/../includes/admin.php';
require_admin();

// หมวดทั้งหมด : id => [name, จำนวนสินค้า, จำนวนโปรโมชั่นที่ใช้หมวดนี้] (ยังมีคนใช้ = ลบไม่ได้)
$categories = $pdo->query(
    'SELECT c.id, c.name,
            (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS products,
            (SELECT COUNT(*) FROM promotions m WHERE m.category_id = c.id)
          + (SELECT COUNT(*) FROM promotion_bundle_items b WHERE b.category_id = c.id) AS promotions
       FROM categories c
      ORDER BY c.id'
)->fetchAll(PDO::FETCH_UNIQUE);

$errors = [];   // key = 'new' (ช่องเพิ่มหมวด), id (ชื่อหมวดที่แก้ไม่ผ่าน) หรือ "delete-id" (ลบไม่ได้)
$typed  = [];   // ชื่อที่กรอกมา ใส่กลับในช่องเดิมเมื่อมี error
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int) ($_POST['id'] ?? 0);
    $name   = trim($_POST['name'] ?? '');

    if ($action !== 'add' && !isset($categories[$id])) {
        $notice = 'ไม่พบหมวดนี้ อาจถูกลบไปแล้ว';
    } elseif ($action === 'add' || $action === 'rename') {
        $key   = $action === 'add' ? 'new' : $id;
        $error = name_error($pdo, $name, $action === 'add' ? 0 : $id);
        if ($error) {
            $errors[$key] = $error;
            $typed[$key]  = $name;
        } else {
            if ($action === 'add') {
                $pdo->prepare('INSERT INTO categories (name) VALUES (?)')->execute([$name]);
                $_SESSION['flash'] = "เพิ่มหมวด \"$name\" แล้ว";
            } else {
                $pdo->prepare('UPDATE categories SET name = ? WHERE id = ?')->execute([$name, $id]);
                $_SESSION['flash'] = "เปลี่ยนชื่อหมวดเป็น \"$name\" แล้ว";
            }
            header('Location: categories.php');
            exit;
        }
    } elseif ($action === 'delete') {
        // สินค้าและโปรโมชั่นอ้างถึงหมวดด้วย foreign key ลบได้เฉพาะหมวดที่ยังไม่มีใครใช้
        if ($categories[$id]['products'] || $categories[$id]['promotions']) {
            $errors["delete-$id"] = 'ลบไม่ได้ เพราะยังมีสินค้าหรือโปรโมชั่นอยู่ในหมวดนี้';
        } else {
            $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
            $_SESSION['flash'] = "ลบหมวด \"{$categories[$id]['name']}\" แล้ว";
            header('Location: categories.php');
            exit;
        }
    }
}

$pageTitle    = 'หมวดหมู่สินค้า';
$pageSubtitle = count($categories) . ' หมวด';
$activeMenu   = 'products';
$backUrl      = 'products.php';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="category-page">
    <?php if ($notice): ?>
        <p class="notice" role="status"><?= e($notice) ?></p>
    <?php endif; ?>

    <form class="card category-add" method="post">
        <input type="hidden" name="action" value="add">
        <div class="field">
            <label for="newName">เพิ่มหมวดใหม่</label>
            <div class="inline-fields">
                <input type="text" id="newName" name="name" value="<?= e($typed['new'] ?? '') ?>" maxlength="50"
                       placeholder="เช่น ฮู้ด" required <?= invalid_attr($errors, 'new') ?>>
                <button class="button" type="submit"><?= icon('plus') ?>เพิ่ม</button>
            </div>
            <?= field_error($errors, 'new') ?>
        </div>
    </form>

    <ul class="card category-list">
        <?php foreach ($categories as $id => $c): ?>
            <?php $inUse = $c['products'] || $c['promotions']; ?>
            <li>
                <form class="field" method="post">
                    <input type="hidden" name="action" value="rename">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <div class="inline-fields">
                        <input type="text" name="name" value="<?= e($typed[$id] ?? $c['name']) ?>" maxlength="50" required
                               aria-label="ชื่อหมวด <?= e($c['name']) ?>" <?= invalid_attr($errors, $id) ?>>
                        <button class="button button-outline" type="submit">บันทึกชื่อ</button>
                    </div>
                </form>
                <div class="category-meta">
                    <span class="muted">
                        สินค้า <?= $c['products'] ?> รายการ<?= $c['promotions'] ? " · ใช้ในโปรโมชั่น {$c['promotions']} รายการ" : '' ?>
                    </span>
                    <form method="post" data-confirm="ลบหมวด &quot;<?= e($c['name']) ?>&quot;?">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <button class="delete-link" type="submit" <?= $inUse ? 'disabled title="ยังมีสินค้าหรือโปรโมชั่นอยู่ในหมวดนี้"' : '' ?>>
                            <?= icon('trash') ?>ลบ
                        </button>
                    </form>
                </div>
                <?= field_error($errors, $id) . field_error($errors, "delete-$id") ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php';


// ---------- ฟังก์ชันของหน้านี้ ----------

// ชื่อหมวด 1–50 ตัวอักษรและไม่ซ้ำกับหมวดอื่น ($id = หมวดที่กำลังแก้ ไม่นับตัวเอง)
// เทียบแบบไม่สนตัวพิมพ์ใหญ่เล็ก ตาม collation ของฐานข้อมูลที่ใช้กับ UNIQUE ของคอลัมน์ name
function name_error(PDO $pdo, string $name, int $id): ?string
{
    if ($name === '' || mb_strlen($name) > 50) {
        return 'ชื่อหมวดต้องมี 1 ถึง 50 ตัวอักษร';
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM categories WHERE name = ? AND id <> ?');
    $stmt->execute([$name, $id]);
    return $stmt->fetchColumn() > 0 ? "มีหมวด \"$name\" อยู่แล้ว" : null;
}
