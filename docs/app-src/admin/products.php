<?php
require_once __DIR__ . '/../includes/admin.php';
require_admin();

const PER_PAGE = 7;

$q      = trim($_GET['q'] ?? '');
$filter = $_GET['filter'] ?? 'all';
$page   = max(1, (int) ($_GET['page'] ?? 1));

// สินค้า 1 แถว : แยกสต็อกแต่ละไซซ์เป็นคอลัมน์ (S, M, L, XL) และเก็บสต็อกต่ำสุดไว้กรอง "ใกล้หมด"
$sql = "SELECT p.id, p.name, p.base_price, p.is_active, c.name AS category,
               (SELECT filename FROM product_images i
                 WHERE i.product_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS image,
               MAX(CASE WHEN v.size = 'S'  THEN v.stock END) AS S,
               MAX(CASE WHEN v.size = 'M'  THEN v.stock END) AS M,
               MAX(CASE WHEN v.size = 'L'  THEN v.stock END) AS L,
               MAX(CASE WHEN v.size = 'XL' THEN v.stock END) AS XL,
               MIN(v.stock) AS min_stock
          FROM products p
          JOIN categories c ON c.id = p.category_id
          LEFT JOIN product_variants v ON v.product_id = p.id
         WHERE p.name LIKE ?
         GROUP BY p.id";
$search = '%' . $q . '%';

// จำนวนของแต่ละตัวกรอง (แสดงบนปุ่ม และใช้คำนวณเลขหน้า)
$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS `all`,
            COALESCE(SUM(is_active = 1 AND min_stock <= ?), 0) AS low,
            COALESCE(SUM(is_active = 0), 0) AS closed
       FROM ($sql) AS t"
);
$stmt->execute([LOW_STOCK, $search]);
$counts = array_map('intval', $stmt->fetch());

$filters = [
    'all'    => ['ทั้งหมด', ''],
    'low'    => ['ใกล้หมด', 'HAVING p.is_active = 1 AND MIN(v.stock) <= ' . LOW_STOCK],
    'closed' => ['ปิดขาย', 'HAVING p.is_active = 0'],
];
if (!isset($filters[$filter])) {
    $filter = 'all';
}

// แบ่งหน้า : หน้าละ PER_PAGE รายการ
$total  = $counts[$filter];
$pages  = max(1, (int) ceil($total / PER_PAGE));
$page   = min($page, $pages);
$offset = ($page - 1) * PER_PAGE;

$stmt = $pdo->prepare("$sql {$filters[$filter][1]} ORDER BY p.id DESC LIMIT " . PER_PAGE . " OFFSET $offset");
$stmt->execute([$search]);
$products = $stmt->fetchAll();

// ลิงก์ที่คงคำค้นและตัวกรองเดิมไว้ เปลี่ยนเฉพาะค่าที่ส่งมา
$link = fn(array $change) => 'products.php?' . http_build_query(array_merge(['q' => $q, 'filter' => $filter, 'page' => 1], $change));

$pageTitle    = 'สินค้า';
$pageSubtitle = "ทั้งหมด {$counts['all']} รายการ";
$activeMenu   = 'products';
$headerAction = ['เพิ่มสินค้า', 'product_edit.php'];
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="admin-toolbar">
    <form class="search-box" action="products.php" role="search">
        <?= icon('search') ?>
        <input type="hidden" name="filter" value="<?= e($filter) ?>">
        <input type="search" name="q" value="<?= e($q) ?>" placeholder="ค้นหาชื่อสินค้า" aria-label="ค้นหาชื่อสินค้า">
    </form>

    <nav class="chips" aria-label="กรองสินค้า">
        <?php foreach ($filters as $key => [$label]): ?>
            <a class="chip" href="<?= e($link(['filter' => $key])) ?>" <?= $key === $filter ? 'aria-current="page"' : '' ?>>
                <?= $key === $filter ? icon('check') : '' ?><?= $label ?> <?= $counts[$key] ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <a class="chip import-link" href="product_import.php"><?= icon('upload') ?>นำเข้า JSON</a>
    <a class="chip" href="categories.php"><?= icon('tag') ?>หมวดหมู่</a>
</div>

<?php if (!$products): ?>
    <p class="muted">ไม่พบสินค้า</p>
<?php else: ?>
    <!-- มือถือ : รายการแบบการ์ด กดทั้งแถวเพื่อแก้ไข -->
    <ul class="card admin-list mobile-only">
        <?php foreach ($products as $p): ?>
            <li>
                <a href="product_edit.php?id=<?= $p['id'] ?>">
                    <?php if ($p['image']): ?>
                        <img class="product-thumb list-thumb" src="../uploads/products/<?= e($p['image']) ?>" alt="">
                    <?php else: ?>
                        <div class="product-thumb list-thumb"><?= icon('shirt') ?></div>
                    <?php endif; ?>
                    <div class="admin-list-info">
                        <strong><?= e($p['name']) ?></strong>
                        <span class="muted">สต็อก <?= implode(', ', array_map(fn($size) => "$size " . ($p[$size] ?? 0), SIZES)) ?></span>
                        <?= product_tag($p) ?>
                    </div>
                    <span class="price"><?= baht((float) $p['base_price']) ?></span>
                    <?= icon('next') ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <!-- จอใหญ่ : ตาราง -->
    <div class="card table-wrap desktop-only">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>สินค้า</th>
                    <th>หมวด</th>
                    <th class="num">ราคา</th>
                    <?php foreach (SIZES as $size): ?>
                        <th class="num">ไซซ์ <?= $size ?></th>
                    <?php endforeach; ?>
                    <th>สถานะ</th>
                    <th><span class="visually-hidden">แก้ไข</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                    <tr>
                        <td>
                            <div class="product-cell">
                                <?php if ($p['image']): ?>
                                    <img class="product-thumb list-thumb" src="../uploads/products/<?= e($p['image']) ?>" alt="">
                                <?php else: ?>
                                    <div class="product-thumb list-thumb"><?= icon('shirt') ?></div>
                                <?php endif; ?>
                                <?= e($p['name']) ?>
                            </div>
                        </td>
                        <td><?= e($p['category']) ?></td>
                        <td class="num"><?= baht((float) $p['base_price']) ?></td>
                        <?php foreach (SIZES as $size): ?>
                            <td class="num"><?= stock_cell($p[$size]) ?></td>
                        <?php endforeach; ?>
                        <td>
                            <?= $p['is_active']
                                ? '<span class="tag tag-success">' . icon('check') . 'เปิดขาย</span>'
                                : '<span class="tag">' . icon('x') . 'ปิดขาย</span>' ?>
                        </td>
                        <td>
                            <a class="icon-button" href="product_edit.php?id=<?= $p['id'] ?>" aria-label="แก้ไข <?= e($p['name']) ?>"><?= icon('edit') ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?= pagination($total, $page, PER_PAGE, fn($n) => $link(['page' => $n])) ?>
<?php endif; ?>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
