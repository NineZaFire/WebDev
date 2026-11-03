<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

// มาจากหน้าชำระเงินหรือหน้าบัญชี : บันทึกแล้วกลับไปหน้านั้น
$from = ($_GET['from'] ?? '') === 'checkout' ? 'checkout' : 'account';

// ที่อยู่ล่าสุด (ไม่มี = ช่องว่าง)
$stmt = $pdo->prepare('SELECT * FROM addresses WHERE user_id = ? ORDER BY id DESC LIMIT 1');
$stmt->execute([current_user()['id']]);
$form = $stmt->fetch() ?: ['street' => '', 'subdistrict' => '', 'district' => '', 'province' => '', 'postal_code' => ''];

// ชื่อช่อง => [ชื่อที่แสดง, ความยาวสูงสุด]
$fields = [
    'street'      => ['บ้านเลขที่ ถนน', 200],
    'subdistrict' => ['แขวง / ตำบล', 80],
    'district'    => ['เขต / อำเภอ', 80],
    'province'    => ['จังหวัด', 80],
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['street', 'subdistrict', 'district', 'province', 'postal_code'] as $key) {
        $form[$key] = trim($_POST[$key] ?? '');
    }

    // ตรวจซ้ำฝั่งเซิร์ฟเวอร์เสมอ เพราะค่าที่ส่งมาจากเบราว์เซอร์แก้ไขได้
    foreach ($fields as $key => [$label, $max]) {
        if ($form[$key] === '' || mb_strlen($form[$key]) > $max) {
            $errors[$key] = "กรุณากรอก$label (ไม่เกิน $max ตัวอักษร)";
        }
    }
    if (!preg_match('/^\d{5}$/', $form['postal_code'])) {
        $errors['postal_code'] = 'รหัสไปรษณีย์ 5 หลัก';
    }

    if (!$errors) {
        // เพิ่มเป็นแถวใหม่ทุกครั้ง ออเดอร์เก่ายังชี้ไปที่อยู่เดิมได้ ที่อยู่ล่าสุดคือที่อยู่จัดส่ง
        $pdo->prepare('INSERT INTO addresses (user_id, street, subdistrict, district, province, postal_code) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([current_user()['id'], $form['street'], $form['subdistrict'], $form['district'], $form['province'], $form['postal_code']]);
        header("Location: $from.php");
        exit;
    }
}

$pageTitle = 'ที่อยู่จัดส่ง';
$activeTab = $from === 'checkout' ? 'cart' : 'account';
$backUrl   = "$from.php";
require __DIR__ . '/../includes/header.php';
?>
<div class="narrow">
    <h1 class="desktop-only">ที่อยู่จัดส่ง</h1>

    <form method="post" action="address.php?from=<?= $from ?>">
        <?php foreach ($fields as $key => [$label, $max]): ?>
            <div class="field">
                <label for="<?= $key ?>"><?= $label ?></label>
                <input type="text" id="<?= $key ?>" name="<?= $key ?>" value="<?= e($form[$key]) ?>"
                       maxlength="<?= $max ?>" required <?= invalid_attr($errors, $key) ?>>
                <?= field_error($errors, $key) ?>
            </div>
        <?php endforeach; ?>

        <div class="field">
            <label for="postal_code">รหัสไปรษณีย์</label>
            <input type="text" id="postal_code" name="postal_code" value="<?= e($form['postal_code']) ?>"
                   pattern="\d{5}" maxlength="5" inputmode="numeric" title="รหัสไปรษณีย์ 5 หลัก"
                   required <?= invalid_attr($errors, 'postal_code') ?>>
            <?= field_error($errors, 'postal_code') ?>
        </div>

        <button class="button button-block" type="submit">บันทึกที่อยู่</button>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
