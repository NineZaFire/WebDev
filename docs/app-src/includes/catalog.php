<?php
// รายการสินค้าหน้าแรก : index.php ใช้แสดงครั้งแรก และ api/products.php ส่งผลลัพธ์ให้ Ajax ทุกครั้งที่เปลี่ยนตัวกรอง
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/ui.php';

// ช่วงราคา : key => [ชื่อ, เงื่อนไข SQL]
const PRICE_RANGES = [
    'under300' => ['ไม่เกิน ฿300', 'p.base_price <= 300'],
    '300to600' => ['฿300 ถึง ฿600', 'p.base_price > 300 AND p.base_price <= 600'],
    'over600'  => ['มากกว่า ฿600', 'p.base_price > 600'],
];

// การเรียง : key => [ชื่อ, ORDER BY]  (แนะนำ = ขายดีที่สุดก่อน)
const SORTS = [
    'recommended' => ['แนะนำ', 'sold DESC, p.id'],
    'price_asc'   => ['ราคาต่ำไปสูง', 'p.base_price, p.id'],
    'price_desc'  => ['ราคาสูงไปต่ำ', 'p.base_price DESC, p.id'],
    'newest'      => ['มาใหม่', 'p.created_at DESC, p.id DESC'],
];

/**
 * ตัวกรองจาก URL : ?q=คำค้น&category=1&size[]=M&size[]=L&price=under300&sort=price_asc
 * ค่าที่ไม่รู้จักถือว่าไม่ได้เลือก
 * @param array $categories [id => ชื่อหมวด]
 */
function read_filters(array $get, array $categories): array
{
    $category = (int) ($get['category'] ?? 0);
    $price    = $get['price'] ?? '';
    $sort     = $get['sort'] ?? '';

    return [
        'q'        => trim($get['q'] ?? ''),
        'category' => isset($categories[$category]) ? $category : 0,
        'sizes'    => array_values(array_intersect(SIZES, (array) ($get['size'] ?? []))),
        'price'    => isset(PRICE_RANGES[$price]) ? $price : '',
        'sort'     => isset(SORTS[$sort]) ? $sort : 'recommended',
    ];
}

// สินค้าที่เปิดขายและตรงกับตัวกรอง
// ประกอบ SQL จากเงื่อนไขที่เลือก : ค่าที่ผู้ใช้กรอกส่งผ่าน ? เสมอ ส่วนช่วงราคาและการเรียงมาจากค่าคงที่ด้านบน
function find_products(PDO $pdo, array $filters): array
{
    $where  = ['p.is_active = 1'];
    $params = [];

    if ($filters['q'] !== '') {
        // ค้นจากชื่อ รายละเอียด และชื่อหมวด (พิมพ์ "เสื้อยืด" ก็เจอเสื้อยืดทุกตัว)
        $like    = "%{$filters['q']}%";
        $where[] = '(p.name LIKE ? OR p.description LIKE ? OR c.name LIKE ?)';
        array_push($params, $like, $like, $like);
    }
    if ($filters['category']) {
        $where[]  = 'p.category_id = ?';
        $params[] = $filters['category'];
    }
    if ($filters['sizes']) {
        // มีของอย่างน้อย 1 ไซซ์ในไซซ์ที่เลือก
        $in      = implode(',', array_fill(0, count($filters['sizes']), '?'));
        $where[] = "EXISTS (SELECT 1 FROM product_variants v WHERE v.product_id = p.id AND v.stock > 0 AND v.size IN ($in))";
        array_push($params, ...$filters['sizes']);
    }
    if ($filters['price']) {
        $where[] = PRICE_RANGES[$filters['price']][1];
    }

    $stmt = $pdo->prepare(
        'SELECT p.id, p.name, p.base_price,
                (SELECT filename FROM product_images i
                  WHERE i.product_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS image,
                (SELECT COALESCE(SUM(v.stock), 0) FROM product_variants v WHERE v.product_id = p.id) AS stock,
                (SELECT COALESCE(SUM(oi.qty), 0)
                   FROM order_items oi
                   JOIN product_variants v ON v.id = oi.variant_id
                   JOIN orders o ON o.id = oi.order_id
                  WHERE v.product_id = p.id AND o.status IN (' . PAID_SQL . ')) AS sold
           FROM products p
           JOIN categories c ON c.id = p.category_id
          WHERE ' . implode(' AND ', $where) . '
          ORDER BY ' . SORTS[$filters['sort']][1]
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// หัวข้อเหนือรายการ : คำค้น > ชื่อหมวด > "เสื้อทั้งหมด"
function result_title(array $filters, array $categories): string
{
    if ($filters['q'] !== '') {
        return "ผลการค้นหา \"{$filters['q']}\"";
    }
    return $filters['category'] ? $categories[$filters['category']] : 'เสื้อทั้งหมด';
}

// การ์ดสินค้า (<li> ทีละใบ) ใส่ใน <ul class="product-grid"> ไม่มีสินค้า = ข้อความแจ้งแทน
function product_grid(array $products): string
{
    if (!$products) {
        return '<li class="grid-empty">ไม่พบสินค้าที่ตรงกับตัวกรอง <a href="index.php">ล้างตัวกรอง</a></li>';
    }

    ob_start();
    foreach ($products as $p): ?>
        <li>
            <a class="product-card" href="product.php?id=<?= $p['id'] ?>">
                <?php if ($p['image']): ?>
                    <img class="product-thumb" src="../uploads/products/<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>">
                <?php else: ?>
                    <div class="product-thumb"><?= icon('shirt') ?></div>
                <?php endif; ?>
                <div class="product-info">
                    <h2><?= e($p['name']) ?></h2>
                    <span class="price"><?= baht((float) $p['base_price']) ?></span>
                    <?php if ((int) $p['stock'] === 0): ?>
                        <p class="sold-out">สินค้าหมด</p>
                    <?php endif; ?>
                </div>
            </a>
        </li>
    <?php endforeach;
    return ob_get_clean();
}
