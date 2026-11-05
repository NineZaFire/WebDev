<?php
require_once __DIR__ . '/../includes/auth.php';

$next = safe_next();
if (current_user()) {
    header("Location: $next");
    exit;
}

$email    = $_COOKIE['remember_email'] ?? '';
$remember = $email !== '';
$errors   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $found = $stmt->fetch();

    if ($found && password_verify($password, $found['password_hash'])) {
        login_user($found);

        // cookie จดจำอีเมลไว้ 30 วัน ครั้งหน้าไม่ต้องพิมพ์ใหม่ (ไม่ติ๊ก = ลบทิ้ง)
        $expires = $remember ? time() + 30 * 24 * 60 * 60 : time() - 3600;
        setcookie('remember_email', $remember ? $email : '', ['expires' => $expires, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);

        // แอดมินที่ไม่ได้ตั้งใจไปหน้าไหน ให้เข้าแดชบอร์ด
        if ($found['role'] === 'admin' && $next === 'index.php') {
            $next = '../admin/index.php';
        }
        header("Location: $next");
        exit;
    }
    // ไม่บอกว่าผิดที่อีเมลหรือรหัสผ่าน จะได้เดาไม่ได้ว่าอีเมลไหนมีบัญชี
    $errors['password'] = 'อีเมลหรือรหัสผ่านไม่ถูกต้อง ลองใหม่อีกครั้ง';
}

$pageTitle   = 'เข้าสู่ระบบ';
$plainLayout = true;
require __DIR__ . '/../includes/header.php';
?>
<header class="auth-head">
    <a class="auth-logo" href="index.php" aria-label="กลับหน้าแรก"><?= icon('shirt') ?></a>
    <h1><?= $siteName ?></h1>
    <p class="muted">เข้าสู่ระบบเพื่อสั่งซื้อและติดตามออเดอร์</p>
</header>

<form method="post" action="login.php">
    <input type="hidden" name="next" value="<?= e($next) ?>">

    <div class="field">
        <label for="email">อีเมล</label>
        <input type="email" id="email" name="email" value="<?= e($email) ?>" placeholder="name@example.com"
               maxlength="100" autocomplete="email" required>
    </div>

    <div class="field">
        <label for="password">รหัสผ่าน</label>
        <input type="password" id="password" name="password" placeholder="อย่างน้อย 8 ตัวอักษร"
               minlength="8" autocomplete="current-password" required <?= invalid_attr($errors, 'password') ?>>
        <?= field_error($errors, 'password') ?>
    </div>

    <label class="checkbox">
        <input type="checkbox" name="remember" <?= $remember ? 'checked' : '' ?>> จดจำอีเมล
    </label>

    <button class="button button-block" type="submit">เข้าสู่ระบบ</button>
</form>

<p class="auth-note">ลืมรหัสผ่าน? ติดต่อแอดมินร้านเพื่อรีเซ็ต</p>
<p class="auth-note">ยังไม่มีบัญชี? <a href="register.php?next=<?= urlencode($next) ?>">สมัครสมาชิก</a></p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
