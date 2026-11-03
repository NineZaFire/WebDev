<?php
// ของที่ทุกหน้าใน admin/ ใช้ร่วมกัน (แต่ละหน้ายังต้องเรียก require_admin() เองก่อนทำอะไร)
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/ui.php';

// SIZES และ PAID_SQL อยู่ใน ui.php เพราะฝั่งลูกค้าใช้ด้วย
const LOW_STOCK = 5;   // เหลือไม่เกินนี้ = ใกล้หมด

// เลขหน้าใต้รายการ : "แสดง 1 ถึง 7 จาก 12 รายการ" กับปุ่มเลขหน้า
// $link(n) คืน URL ของหน้า n (คงคำค้นและตัวกรองเดิมไว้)
function pagination(int $total, int $page, int $perPage, callable $link): string
{
    $pages = max(1, (int) ceil($total / $perPage));
    $first = ($page - 1) * $perPage + 1;
    $last  = min($page * $perPage, $total);

    $html = "<nav class=\"pagination\" aria-label=\"เลขหน้า\"><p class=\"muted\">แสดง $first ถึง $last จาก $total รายการ</p><div class=\"pager\">";
    if ($page > 1) {
        $html .= '<a href="' . e($link($page - 1)) . '" aria-label="หน้าก่อน">' . icon('back') . '</a>';
    }
    for ($i = 1; $i <= $pages; $i++) {
        $html .= '<a href="' . e($link($i)) . '"' . ($i === $page ? ' aria-current="page"' : '') . ">$i</a>";
    }
    if ($page < $pages) {
        $html .= '<a href="' . e($link($page + 1)) . '" aria-label="หน้าถัดไป">' . icon('next') . '</a>';
    }
    return $html . '</div></nav>';
}

// ตัวเลขสต็อกในตาราง : 0 = "หมด" สีแดง, ใกล้หมด = สีส้มมีไอคอน
function stock_cell(?int $stock): string
{
    if ($stock === null) {
        return '–';
    }
    if ($stock === 0) {
        return '<span class="text-danger">หมด</span>';
    }
    if ($stock <= LOW_STOCK) {
        return '<span class="text-warning">' . icon('alert') . $stock . '</span>';
    }
    return (string) $stock;
}

// ตั้งสต็อกแต่ละไซซ์ ([ไซซ์ => จำนวน]) แล้วบันทึกส่วนต่างลง stock_movements (in = เพิ่ม, out = ลด)
// สต็อกในตารางจึงเท่ากับผลรวมของ stock_movements เสมอ ใช้ทั้งหน้าแก้ไขสินค้าและหน้านำเข้าสินค้า
// เรียกภายใน transaction ของหน้านั้น
function save_stock(PDO $pdo, int $productId, array $stock): void
{
    foreach ($stock as $size => $newStock) {
        $stmt = $pdo->prepare('SELECT id, stock FROM product_variants WHERE product_id = ? AND size = ? FOR UPDATE');
        $stmt->execute([$productId, $size]);
        $variant = $stmt->fetch();

        if ($variant) {
            $variantId = (int) $variant['id'];
            $diff      = $newStock - (int) $variant['stock'];
            $pdo->prepare('UPDATE product_variants SET stock = ? WHERE id = ?')->execute([$newStock, $variantId]);
        } else {
            $pdo->prepare('INSERT INTO product_variants (product_id, size, stock) VALUES (?, ?, ?)')->execute([$productId, $size, $newStock]);
            $variantId = (int) $pdo->lastInsertId();
            $diff      = $newStock;
        }

        if ($diff !== 0) {
            $pdo->prepare('INSERT INTO stock_movements (variant_id, type, qty) VALUES (?, ?, ?)')
                ->execute([$variantId, $diff > 0 ? 'in' : 'out', abs($diff)]);
        }
    }
}

// ป้ายของสินค้า 1 ชิ้น : ปิดขาย หรือไซซ์ที่เหลือน้อยที่สุดถ้าใกล้หมด (ไม่มีปัญหา = ไม่มีป้าย)
function product_tag(array $p): string
{
    if (!$p['is_active']) {
        return '<span class="tag">' . icon('x') . 'ปิดขาย</span>';
    }

    $lowest = null;
    foreach (SIZES as $size) {
        if ($p[$size] !== null && ($lowest === null || $p[$size] < $p[$lowest])) {
            $lowest = $size;
        }
    }
    if ($lowest === null || $p[$lowest] > LOW_STOCK) {
        return '';
    }
    return $p[$lowest] == 0
        ? '<span class="tag tag-danger">' . icon('alert') . "$lowest หมด</span>"
        : '<span class="tag tag-warning">' . icon('alert') . "$lowest เหลือ {$p[$lowest]}</span>";
}
