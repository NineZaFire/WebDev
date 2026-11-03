<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current  = $_POST['current_password'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
    $stmt->execute([current_user()['id']]);
    $hash = $stmt->fetchColumn();

    // ตรวจซ้ำฝั่งเซิร์ฟเวอร์เสมอ รหัสผ่านใหม่ใช้กติกาเดียวกับหน้าสมัครสมาชิก
    if (!password_verify($current, $hash)) {
        $errors['current_password'] = 'รหัสผ่านปัจจุบันไม่ถูกต้อง';
    }
    if (mb_strlen($password) < 8) {
        $errors['password'] = 'รหัสผ่านอย่างน้อย 8 ตัวอักษร';
    } elseif (password_verify($password, $hash)) {
        $errors['password'] = 'รหัสผ่านใหม่ต้องไม่ซ้ำกับรหัสผ่านเดิม';
    }
    if ($password !== $confirm) {
        $errors['password_confirm'] = 'รหัสผ่านทั้งสองช่องไม่ตรงกัน';
    }

    if (!$errors) {
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), current_user()['id']]);
        session_regenerate_id(true);   // เปลี่ยนรหัสผ่านแล้วได้ session ID ใหม่ เหมือนตอนเข้าสู่ระบบ
        $_SESSION['flash'] = 'เปลี่ยนรหัสผ่านแล้ว';
        header('Location: account.php');
        exit;
    }
}

$pageTitle = 'เปลี่ยนรหัสผ่าน';
$activeTab = 'account';
$backUrl   = 'account.php';
require __DIR__ . '/../includes/header.php';
?>
<div class="narrow">
    <h1 class="desktop-only">เปลี่ยนรหัสผ่าน</h1>

    <!-- ช่องรหัสผ่านไม่เติมค่าเดิมกลับมาเมื่อมี error (ไม่ส่งรหัสผ่านกลับไปในหน้าเว็บ) -->
    <form method="post" action="password.php">
        <div class="field">
            <label for="currentPassword">รหัสผ่านปัจจุบัน</label>
            <input type="password" id="currentPassword" name="current_password"
                   autocomplete="current-password" required <?= invalid_attr($errors, 'current_password') ?>>
            <?= field_error($errors, 'current_password') ?>
        </div>

        <div class="field">
            <label for="password">รหัสผ่านใหม่</label>
            <input type="password" id="password" name="password" minlength="8"
                   autocomplete="new-password" required <?= invalid_attr($errors, 'password') ?>>
            <p class="field-hint" id="passwordHint">อย่างน้อย 8 ตัวอักษร</p>
            <?= field_error($errors, 'password') ?>
        </div>

        <div class="field">
            <label for="passwordConfirm">ยืนยันรหัสผ่านใหม่</label>
            <input type="password" id="passwordConfirm" name="password_confirm"
                   autocomplete="new-password" required <?= invalid_attr($errors, 'password_confirm') ?>>
            <p class="field-error" id="confirmError" <?= isset($errors['password_confirm']) ? '' : 'hidden' ?>>
                <?= icon('alert') ?>รหัสผ่านทั้งสองช่องไม่ตรงกัน
            </p>
        </div>

        <button class="button button-block" type="submit">บันทึกรหัสผ่านใหม่</button>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
