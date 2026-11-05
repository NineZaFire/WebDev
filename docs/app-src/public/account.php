<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$stmt = $pdo->prepare('SELECT first_name, last_name, email, phone FROM users WHERE id = ?');
$stmt->execute([current_user()['id']]);
$me = $stmt->fetch();

// ที่อยู่ล่าสุดคือที่อยู่จัดส่ง
$stmt = $pdo->prepare('SELECT * FROM addresses WHERE user_id = ? ORDER BY id DESC LIMIT 1');
$stmt->execute([current_user()['id']]);
$address = $stmt->fetch();

$pageTitle = 'บัญชีของฉัน';
$activeTab = 'account';
require __DIR__ . '/../includes/header.php';
?>
<div class="account">
    <section class="profile">
        <div class="avatar" aria-hidden="true"><?= e(mb_substr($me['first_name'], 0, 1)) ?></div>
        <div>
            <h1><?= e($me['first_name'] . ' ' . $me['last_name']) ?></h1>
            <p class="muted"><?= e($me['email']) ?></p>
        </div>
    </section>

    <section class="card">
        <div class="card-head">
            <h2>ข้อมูลจัดส่ง</h2>
            <a class="title-icon" href="address.php"><?= icon('edit') ?>แก้ไข</a>
        </div>
        <dl class="info-list">
            <div>
                <dt>ชื่อผู้รับ</dt>
                <dd><?= e($me['first_name'] . ' ' . $me['last_name']) ?></dd>
            </div>
            <div>
                <dt>เบอร์โทร</dt>
                <dd><?= e(preg_replace('/^(\d{3})(\d{3})(\d{4})$/', '$1-$2-$3', $me['phone'])) ?></dd>
            </div>
            <div>
                <dt>ที่อยู่</dt>
                <dd>
                    <?php if ($address): ?>
                        <?= e("{$address['street']} {$address['subdistrict']} {$address['district']} {$address['province']} {$address['postal_code']}") ?>
                    <?php else: ?>
                        <span class="muted">ยังไม่มีที่อยู่จัดส่ง</span>
                    <?php endif; ?>
                </dd>
            </div>
        </dl>
    </section>

    <nav class="card link-list" aria-label="เมนูบัญชี">
        <a href="orders.php"><?= icon('orders') ?>คำสั่งซื้อของฉัน<?= icon('next') ?></a>
        <a href="password.php"><?= icon('eye') ?>เปลี่ยนรหัสผ่าน<?= icon('next') ?></a>
        <?php if (is_admin()): ?>
            <a href="../admin/index.php"><?= icon('chart') ?>จัดการร้าน<?= icon('next') ?></a>
        <?php endif; ?>
    </nav>

    <!-- session ของเบราว์เซอร์นี้ : เวลาเข้าสู่ระบบที่เก็บไว้ใน $_SESSION และรหัส session จาก session_id()
         แสดงแค่ 8 ตัวแรก เพราะรหัสเต็มใช้แทนการเข้าสู่ระบบได้ ใครได้ไปก็สวมรอยเป็นเราได้ -->
    <section class="card">
        <h2>การเข้าสู่ระบบครั้งนี้</h2>
        <dl class="info-list">
            <div>
                <dt>เข้าสู่ระบบเมื่อ</dt>
                <dd><?= isset(current_user()['login_at']) ? thai_time(current_user()['login_at']) : '–' ?></dd>
            </div>
            <div>
                <dt>รหัสเซสชัน</dt>
                <dd><code><?= e(substr(session_id(), 0, 8)) ?>…</code></dd>
            </div>
        </dl>
    </section>

    <form method="post" action="logout.php">
        <button class="button button-danger button-block" type="submit">ออกจากระบบ</button>
    </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
