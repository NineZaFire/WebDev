<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/pricing.php';
require_login();

// เลข PromptPay ของร้าน (เลขประจำตัว 13 หลัก หรือเบอร์โทร 10 หลัก)
// ระบบนี้จำลองการชำระเงิน จึงใช้เลข 0 ทั้งหมด สแกนแล้วโอนเงินจริงไม่ได้
const PROMPTPAY_ID = '0000000000000';

$coupon = $_SESSION['coupon'] ?? '';
$quote  = price_cart($pdo, $_SESSION['cart'] ?? [], $coupon);
if (!$quote['lines']) {
    header('Location: cart.php');
    exit;
}

$stmt = $pdo->prepare('SELECT first_name, last_name, phone FROM users WHERE id = ?');
$stmt->execute([current_user()['id']]);
$me = $stmt->fetch();

// ที่อยู่ล่าสุดคือที่อยู่จัดส่ง ยังไม่มี = ไปกรอกก่อน
$stmt = $pdo->prepare('SELECT * FROM addresses WHERE user_id = ? ORDER BY id DESC LIMIT 1');
$stmt->execute([current_user()['id']]);
$address = $stmt->fetch();
if (!$address) {
    header('Location: address.php?from=checkout');
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    [$slip, $slipError] = uploaded_slip($_FILES['slip'] ?? null);
    if ($slipError) {
        $errors['slip'] = $slipError;
    } elseif ($quote['warnings']) {
        $errors['cart'] = implode(' ', $quote['warnings']);
    } else {
        try {
            $orderId = place_order($pdo, current_user()['id'], (int) $address['id'], $_SESSION['cart'], $coupon, $slip);

            // สั่งซื้อแล้ว : ลบตะกร้าและคูปองออกจาก session
            unset($_SESSION['cart'], $_SESSION['coupon']);
            header("Location: order_done.php?id=$orderId");
            exit;
        } catch (RuntimeException $e) {
            $errors['cart'] = $e->getMessage();   // เช่น มีคนซื้อตัดหน้าจนสต็อกไม่พอ
        }
    }
}

$pageTitle  = 'ชำระเงิน';
$activeTab  = 'cart';
$backUrl    = 'cart.php';
$hideTabBar = true;
$useQr      = true;
require __DIR__ . '/../includes/header.php';
?>
<div class="checkout">
    <?= checkout_steps(2) ?>

    <?php if (isset($errors['cart'])): ?>
        <p class="notice" role="alert"><?= e($errors['cart']) ?> <a href="cart.php">กลับไปตะกร้า</a></p>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <section class="card">
            <div class="card-head">
                <h2 class="title-icon"><?= icon('pin') ?>ที่อยู่จัดส่ง</h2>
                <a href="address.php?from=checkout">แก้ไข</a>
            </div>
            <p><?= e("{$me['first_name']} {$me['last_name']}") ?> · <?= e(preg_replace('/^(\d{3})(\d{3})(\d{4})$/', '$1-$2-$3', $me['phone'])) ?></p>
            <p class="muted"><?= e("{$address['street']} {$address['subdistrict']} {$address['district']} {$address['province']} {$address['postal_code']}") ?></p>
        </section>

        <section class="card qr-card">
            <h2>สแกนจ่ายด้วย PromptPay</h2>
            <!-- app.js วาด QR จาก data-payload (ถ้าไม่มี JS จะเห็นไอคอนแทน) -->
            <div class="qr-box" id="qrBox" role="img" aria-label="QR PromptPay ยอด <?= baht($quote['net_total']) ?>"
                 data-payload="<?= e(promptpay_payload(PROMPTPAY_ID, $quote['net_total'])) ?>"><?= icon('qr') ?></div>
            <p>ยอดชำระ <strong class="qr-amount"><?= baht($quote['net_total']) ?></strong></p>
        </section>

        <label class="slip-drop <?= isset($errors['slip']) ? 'slip-error' : '' ?>">
            <?= icon('upload') ?>
            <strong id="slipName">แตะเพื่อแนบสลิปการโอน</strong>
            <span class="muted">JPG หรือ PNG · ไม่เกิน 5 MB</span>
            <input type="file" id="slipInput" name="slip" accept="image/jpeg,image/png" hidden>
        </label>
        <?= field_error($errors, 'slip') ?>

        <p class="checkout-note"><?= icon('alert') ?>ระบบจำลองการชำระเงิน แอดมินจะตรวจสลิปแล้วยืนยันออเดอร์ ดูสถานะได้ที่หน้า "คำสั่งซื้อ"</p>

        <div class="buy-bar">
            <a class="icon-button mobile-only" href="cart.php" aria-label="กลับไปตะกร้า"><?= icon('bag') ?></a>
            <button class="button" type="submit">ยืนยันคำสั่งซื้อ</button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php';


// ---------- ฟังก์ชันของหน้านี้ ----------

// สลิปจาก <input type="file"> : คืน [ไฟล์, ข้อความ error]
// รับเฉพาะ JPG/PNG ไม่เกิน 5 MB โดยตรวจจากเนื้อไฟล์ ไม่เชื่อนามสกุลที่ผู้ใช้ตั้งมา
function uploaded_slip(?array $file): array
{
    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return [null, 'กรุณาแนบสลิปการโอนเงิน'];
    }
    $info = $file['error'] === UPLOAD_ERR_OK ? @getimagesize($file['tmp_name']) : false;
    $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png'][$info['mime'] ?? ''] ?? null;
    if (!$ext || $file['size'] > 5 * 1024 * 1024) {
        return [null, 'สลิปต้องเป็นไฟล์ JPG หรือ PNG ขนาดไม่เกิน 5 MB'];
    }
    return [['tmp' => $file['tmp_name'], 'ext' => $ext], null];
}

// สร้างออเดอร์ : ทุกขั้นต้องสำเร็จพร้อมกัน หรือย้อนกลับทั้งหมด (BEGIN ... COMMIT)
// ถ้าตัดสต็อกแล้วแต่สร้างออเดอร์ไม่สำเร็จ สินค้าจะหายจากคลังโดยไม่มีออเดอร์
function place_order(PDO $pdo, int $userId, int $addressId, array $cart, string $coupon, array $slip): int
{
    $file = bin2hex(random_bytes(8)) . '.' . $slip['ext'];

    $pdo->beginTransaction();
    try {
        // 1. ล็อกแถวสต็อกของสินค้าในตะกร้า คนอื่นจะตัดสต็อกแถวเดียวกันได้หลังเรา COMMIT เท่านั้น
        $ids = array_map('intval', array_keys($cart));
        $pdo->prepare('SELECT id FROM product_variants WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') FOR UPDATE')
            ->execute($ids);

        // 2. คิดราคาใหม่จากฐานข้อมูล (ไม่เชื่อยอดจากหน้าเว็บ) สต็อกไม่พอ = ยกเลิกทั้งหมด
        $quote = price_cart($pdo, $cart, $coupon);
        if ($quote['warnings'] || count($quote['lines']) !== count($cart)) {
            throw new RuntimeException('สินค้าบางรายการเหลือไม่พอแล้ว กรุณาตรวจตะกร้าอีกครั้ง');
        }

        // 3. ออเดอร์ (รอแอดมินตรวจสลิป)
        $pdo->prepare(
            "INSERT INTO orders (user_id, address_id, coupon_id, status, payment_method, subtotal, discount_total, shipping_fee, net_total, slip_image)
             VALUES (?, ?, ?, 'pending', 'qr', ?, ?, ?, ?, ?)"
        )->execute([$userId, $addressId, $quote['coupon']['id'] ?? null, $quote['subtotal'], $quote['discount_total'],
                    $quote['shipping_fee'], $quote['net_total'], $file]);
        $orderId = (int) $pdo->lastInsertId();

        // 4. รายการสินค้า + ตัดสต็อก + บันทึกการเคลื่อนไหวของสต็อก
        foreach ($quote['lines'] as $line) {
            $pdo->prepare('INSERT INTO order_items (order_id, variant_id, promotion_id, qty, unit_price_paid, discount_amount) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$orderId, $line['variant_id'], $line['promotion_id'], $line['qty'], $line['unit_price_paid'], $line['discount']]);
            $pdo->prepare('UPDATE product_variants SET stock = stock - ? WHERE id = ?')->execute([$line['qty'], $line['variant_id']]);
            $pdo->prepare("INSERT INTO stock_movements (variant_id, order_id, type, qty) VALUES (?, ?, 'out', ?)")
                ->execute([$line['variant_id'], $orderId, $line['qty']]);
        }

        // 5. เก็บไฟล์สลิป ย้ายไม่สำเร็จ = ยกเลิกทั้งหมด
        if (!move_uploaded_file($slip['tmp'], __DIR__ . '/../uploads/slips/' . $file)) {
            throw new RuntimeException('บันทึกสลิปไม่สำเร็จ ลองใหม่อีกครั้ง');
        }

        $pdo->commit();
        return $orderId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

// ข้อความใน QR PromptPay ตามมาตรฐาน EMVCo (Thai QR Payment) ฝังยอดเงินไว้ด้วย
function promptpay_payload(string $id, float $amount): string
{
    // แต่ละช่อง = รหัส 2 หลัก + ความยาว 2 หลัก + ค่า
    $field = fn(string $tag, string $value) => $tag . sprintf('%02d', strlen($value)) . $value;

    // เลขประจำตัว 13 หลักใช้ตรง ๆ, เบอร์โทร 08xxxxxxxx เปลี่ยนเป็น 00668xxxxxxxx
    $target = strlen($id) === 13 ? $field('02', $id) : $field('01', '0066' . substr($id, 1));

    $data = $field('00', '01')                                       // รูปแบบข้อมูล
          . $field('01', '12')                                       // QR ใช้ครั้งเดียว (มียอดเงิน)
          . $field('29', $field('00', 'A000000677010111') . $target) // PromptPay + บัญชีปลายทาง
          . $field('58', 'TH')                                       // ประเทศ
          . $field('53', '764')                                      // สกุลเงินบาท
          . $field('54', number_format($amount, 2, '.', ''))         // ยอดเงิน
          . '6304';                                                  // CRC ต่อท้าย
    return $data . sprintf('%04X', crc16($data));
}

// CRC-16/CCITT-FALSE (poly 0x1021, เริ่มที่ 0xFFFF) ตัวตรวจความถูกต้องท้าย QR
function crc16(string $data): int
{
    $crc = 0xFFFF;
    for ($i = 0; $i < strlen($data); $i++) {
        $crc ^= ord($data[$i]) << 8;
        for ($bit = 0; $bit < 8; $bit++) {
            $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF;
        }
    }
    return $crc;
}
