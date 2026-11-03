<?php
// ตัวอย่างราคาในฟอร์มโปรโมชั่น : รับค่าในฟอร์ม (POST จาก fetch) แล้วตอบเป็น JSON
//   {ok: true,  text: "ซื้อเสื้อยืด 3 ตัว ราคาเต็ม ฿900", pay: "฿765", save: "ประหยัด ฿135"}
//   {ok: false, text: ข้อความที่บอกว่าต้องแก้อะไร}
// คิดส่วนลดด้วย promotion_result() ตัวเดียวกับตะกร้า ตัวอย่างจึงตรงกับที่ลูกค้าจะได้จริง
require_once __DIR__ . '/../includes/admin.php';
require_once __DIR__ . '/../includes/pricing.php';
require_once __DIR__ . '/../includes/promotion_form.php';
require_admin();

header('Content-Type: application/json; charset=utf-8');

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
[$promo, $errors] = read_promotion_form($_POST, $categories);

// ตัวอย่างดูเฉพาะเงื่อนไขส่วนลด ชื่อโปรและวันที่ยังไม่กรอกก็แสดงได้
$error = $errors['type'] ?? $errors['category_id'] ?? $errors['condition'] ?? null;
if ($error) {
    reply(false, $error);
}

// ตะกร้าตัวอย่าง : [หมวด, จำนวนตัว] หมวด null = ทุกหมวด
$picks = match ($promo['type']) {
    'buy_x_get_y'    => [[$promo['category_id'], $promo['buy_qty'] + $promo['free_qty']]],   // ซื้อครบพอดีได้ของแถม
    'timed_discount' => [[$promo['category_id'], 1]],
    'volume'         => [[$promo['category_id'], max(array_column($promo['tiers'], 'min_qty'))]],   // ถึงขั้นสูงสุด
    'bundle'         => array_map(fn($item) => [$item['category_id'], $item['qty']], $promo['bundle_items']),
};

// แต่ละหมวดใช้สินค้าที่ราคาถูกที่สุดที่เปิดขายอยู่
$cheapest = $pdo->prepare(
    'SELECT p.base_price, p.category_id, c.name AS category
       FROM products p
       JOIN categories c ON c.id = p.category_id
      WHERE p.is_active = 1 AND (? IS NULL OR p.category_id = ?)
      ORDER BY p.base_price
      LIMIT 1'
);
$units = [];
$parts = [];
foreach ($picks as $line => [$categoryId, $qty]) {
    $cheapest->execute([$categoryId, $categoryId]);
    $product = $cheapest->fetch();
    if (!$product) {
        reply(false, 'หมวดที่เลือกยังไม่มีสินค้าที่เปิดขาย');
    }
    for ($i = 0; $i < $qty; $i++) {
        $units[] = ['line' => $line, 'price' => (float) $product['base_price'], 'category_id' => (int) $product['category_id']];
    }
    $parts[] = "{$product['category']} $qty ตัว";
}

$full   = array_sum(array_column($units, 'price'));
$text   = 'ซื้อ' . implode(' + ', $parts) . ' ราคาเต็ม ' . baht($full);
$result = promotion_result($promo, $units);

if (!$result) {
    reply(false, "$text ไม่ได้ส่วนลด ราคาต่อเซ็ตต้องต่ำกว่าราคาเต็ม");   // เกิดได้กับชุดเซ็ตเท่านั้น
}
reply(true, $text, ['pay' => baht($full - $result['total']), 'save' => 'ประหยัด ' . baht($result['total'])]);


// ตอบเป็น JSON แล้วจบการทำงานทันที
function reply(bool $ok, string $text, array $more = []): never
{
    echo json_encode(['ok' => $ok, 'text' => $text] + $more, JSON_UNESCAPED_UNICODE);
    exit;
}
