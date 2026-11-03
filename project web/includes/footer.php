<?php
// ปิดหน้าที่ header.php เปิดไว้ และใช้ตัวแปรที่ header.php ตั้งไว้
/** @var bool $plainLayout */
/** @var string $siteName */
/** @var bool $hideTabBar */
/** @var bool $useQr */
/** @var array $menu */
/** @var callable $current */
?>
</main>
<?php if (!$plainLayout): ?>
<!-- footer แสดงเฉพาะจอใหญ่ มือถือมี tab bar อยู่ด้านล่างแทน -->
<footer class="site-footer desktop-only">
    <div class="container">© <?= date('Y') + 543 ?> <?= $siteName ?> · ร้านขายเสื้อออนไลน์ · ชำระเงินผ่าน PromptPay</div>
</footer>
<?php endif; ?>
<?php if (!$hideTabBar): ?>
<nav class="tab-bar mobile-only" aria-label="เมนูด้านล่าง">
    <?php foreach ($menu as $key => [$label, $href, $iconName]): ?>
        <a href="../public/<?= $href ?>" <?= $current($key) ?>><?= icon($iconName) ?><span><?= $label ?></span></a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>
<?php if ($useQr): ?>
<script src="../assets/vendor/qrcode.js"></script>
<?php endif; ?>
<script src="../assets/app.js"></script>
</body>
</html>
