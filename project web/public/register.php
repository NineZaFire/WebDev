<?php
require_once __DIR__ . '/../includes/auth.php';

$next = safe_next();
if (current_user()) {
    header("Location: $next");
    exit;
}

$form   = ['first_name' => '', 'last_name' => '', 'email' => '', 'phone' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $key => $value) {
        $form[$key] = trim($_POST[$key] ?? '');
    }
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    // ตรวจซ้ำฝั่งเซิร์ฟเวอร์เสมอ เพราะค่าที่ส่งมาจากเบราว์เซอร์แก้ไขได้
    if ($form['first_name'] === '' || mb_strlen($form['first_name']) > 50) {
        $errors['first_name'] = 'กรุณากรอกชื่อ (ไม่เกิน 50 ตัวอักษร)';
    }
    if ($form['last_name'] === '' || mb_strlen($form['last_name']) > 50) {
        $errors['last_name'] = 'กรุณากรอกนามสกุล (ไม่เกิน 50 ตัวอักษร)';
    }
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL) || strlen($form['email']) > 100) {
        $errors['email'] = 'รูปแบบอีเมลไม่ถูกต้อง';
    }
    if (!preg_match('/^0[0-9]{9}$/', $form['phone'])) {
        $errors['phone'] = 'เบอร์โทร 10 หลัก ขึ้นต้นด้วย 0';
    }
    if (mb_strlen($password) < 8) {
        $errors['password'] = 'รหัสผ่านอย่างน้อย 8 ตัวอักษร';
    }
    if ($password !== $confirm) {
        $errors['password_confirm'] = 'รหัสผ่านทั้งสองช่องไม่ตรงกัน';
    }

    if (!isset($errors['email'])) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $stmt->execute([$form['email']]);
        if ($stmt->fetchColumn() > 0) {
            $errors['email'] = 'อีเมลนี้มีบัญชีแล้ว';
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            'INSERT INTO users (first_name, last_name, email, phone, password_hash) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$form['first_name'], $form['last_name'], $form['email'], $form['phone'], password_hash($password, PASSWORD_DEFAULT)]);

        // สมัครเสร็จแล้วล็อกอินให้เลย
        login_user($form + ['id' => $pdo->lastInsertId(), 'role' => 'customer']);
        header("Location: $next");
        exit;
    }
}

$pageTitle   = 'สมัครสมาชิก';
$plainLayout = true;
require __DIR__ . '/../includes/header.php';
?>
<header class="auth-head">
    <a class="auth-logo" href="index.php" aria-label="กลับหน้าแรก"><?= icon('shirt') ?></a>
    <h1>สมัครสมาชิก</h1>
    <p class="muted">สร้างบัญชีเพื่อสั่งซื้อและติดตามออเดอร์</p>
</header>

<form method="post" action="register.php">
    <input type="hidden" name="next" value="<?= e($next) ?>">

    <div class="field-row">
        <div class="field">
            <label for="first_name">ชื่อ</label>
            <input type="text" id="first_name" name="first_name" value="<?= e($form['first_name']) ?>"
                   maxlength="50" autocomplete="given-name" required <?= invalid_attr($errors, 'first_name') ?>>
            <?= field_error($errors, 'first_name') ?>
        </div>
        <div class="field">
            <label for="last_name">นามสกุล</label>
            <input type="text" id="last_name" name="last_name" value="<?= e($form['last_name']) ?>"
                   maxlength="50" autocomplete="family-name" required <?= invalid_attr($errors, 'last_name') ?>>
            <?= field_error($errors, 'last_name') ?>
        </div>
    </div>

    <div class="field">
        <label for="email">อีเมล</label>
        <input type="email" id="email" name="email" value="<?= e($form['email']) ?>" placeholder="name@example.com"
               maxlength="100" autocomplete="email" required <?= invalid_attr($errors, 'email') ?>>
        <?= field_error($errors, 'email') ?>
    </div>

    <div class="field">
        <label for="phone">เบอร์โทร</label>
        <input type="tel" id="phone" name="phone" value="<?= e($form['phone']) ?>" placeholder="0812345678"
               pattern="0[0-9]{9}" title="เบอร์โทร 10 หลัก ขึ้นต้นด้วย 0" inputmode="numeric"
               autocomplete="tel" required <?= invalid_attr($errors, 'phone') ?>>
        <?= field_error($errors, 'phone') ?>
    </div>

    <div class="field">
        <label for="password">รหัสผ่าน</label>
        <input type="password" id="password" name="password" minlength="8"
               autocomplete="new-password" required <?= invalid_attr($errors, 'password') ?>>
        <p class="field-hint" id="passwordHint">อย่างน้อย 8 ตัวอักษร</p>
        <?= field_error($errors, 'password') ?>
    </div>

    <div class="field">
        <label for="passwordConfirm">ยืนยันรหัสผ่าน</label>
        <input type="password" id="passwordConfirm" name="password_confirm"
               autocomplete="new-password" required <?= invalid_attr($errors, 'password_confirm') ?>>
        <p class="field-error" id="confirmError" <?= isset($errors['password_confirm']) ? '' : 'hidden' ?>>
            <?= icon('alert') ?>รหัสผ่านทั้งสองช่องไม่ตรงกัน
        </p>
    </div>

    <button class="button button-block" type="submit">สมัครสมาชิก</button>
</form>

<p class="auth-note">มีบัญชีแล้ว? <a href="login.php?next=<?= urlencode($next) ?>">เข้าสู่ระบบ</a></p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
