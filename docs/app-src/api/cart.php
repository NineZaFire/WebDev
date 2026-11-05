<?php
// ตะกร้าสินค้าแบบ Ajax : รับคำสั่งแบบ POST แล้วตอบกลับเป็น JSON
//   action=add     variant_id, qty   เพิ่มสินค้า (หน้าสินค้า)
//   action=update  variant_id, qty   เปลี่ยนจำนวน 0 = ลบ (หน้าตะกร้า)
//   action=remove  variant_id        ลบออกจากตะกร้า
//   action=coupon  code              ใช้คูปอง ว่าง = ยกเลิกคูปอง
// ตะกร้าเก็บใน session : $_SESSION['cart'] = [variant_id => qty] และ $_SESSION['coupon'] = โค้ด
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cart_view.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'ต้องส่งแบบ POST']);
    exit;
}

$action    = $_POST['action'] ?? '';
$variantId = (int) ($_POST['variant_id'] ?? 0);
$qty       = (int) ($_POST['qty'] ?? 0);
$cart      = $_SESSION['cart'] ?? [];
$ok        = true;
$message   = '';

switch ($action) {
    case 'add':
        $stock  = stock_of($pdo, $variantId);
        $inCart = $cart[$variantId] ?? 0;
        if ($stock === null || $qty < 1) {
            [$ok, $message] = [false, 'กรุณาเลือกไซซ์และจำนวน'];
        } elseif ($inCart + $qty > $stock) {
            [$ok, $message] = [false, "ไซซ์นี้เหลือ $stock ชิ้น (ในตะกร้ามีแล้ว $inCart ชิ้น)"];
        } else {
            $cart[$variantId] = $inCart + $qty;
            $message = 'เพิ่มลงตะกร้าแล้ว';
        }
        break;

    case 'update':
        // ไม่ให้เกินสต็อก สินค้าที่ปิดขายไปแล้วถือว่าสต็อกเป็น 0
        $cart[$variantId] = min($qty, stock_of($pdo, $variantId) ?? 0);
        if ($cart[$variantId] < 1) {
            unset($cart[$variantId]);
        }
        break;

    case 'remove':
        unset($cart[$variantId]);
        break;

    case 'coupon':
        $code = strtoupper(trim($_POST['code'] ?? ''));
        if ($code === '') {
            unset($_SESSION['coupon']);
        } else {
            $_SESSION['coupon'] = $code;
        }
        break;

    default:
        [$ok, $message] = [false, 'ไม่รู้จักคำสั่งนี้'];
}

$_SESSION['cart'] = $cart;
$coupon = $_SESSION['coupon'] ?? '';

echo json_encode([
    'ok'      => $ok,
    'message' => $message,
    'count'   => array_sum($cart),
    'html'    => cart_view(price_cart($pdo, $cart, $coupon), $coupon),
]);

// สต็อกของไซซ์ที่ยังเปิดขายอยู่ (null = ไม่มีสินค้านี้)
function stock_of(PDO $pdo, int $variantId): ?int
{
    $stmt = $pdo->prepare(
        'SELECT v.stock FROM product_variants v
           JOIN products p ON p.id = v.product_id
          WHERE v.id = ? AND p.is_active = 1'
    );
    $stmt->execute([$variantId]);
    $stock = $stmt->fetchColumn();
    return $stock === false ? null : (int) $stock;
}
