<?php
// ปิดหน้าที่ admin_header.php เปิดไว้ และใช้ตัวแปรที่ admin_header.php ตั้งไว้
/** @var bool $hideTabBar */
/** @var bool $useChart */
/** @var array $menu */
/** @var callable $current */
?>
</main>
<?php if (!$hideTabBar): ?>
<nav class="tab-bar mobile-only" aria-label="เมนูแอดมิน">
    <?php foreach ($menu as $key => [$label, $href, $iconName]): ?>
        <a href="<?= $href ?>" <?= $current($key) ?>><?= icon($iconName) ?><span><?= $label ?></span></a>
    <?php endforeach; ?>
    <a href="../public/account.php"><?= icon('user') ?><span>บัญชี</span></a>
</nav>
<?php endif; ?>
<?php if ($useChart): ?>
<script src="../assets/vendor/chart.umd.min.js"></script>
<?php endif; ?>
<script src="../assets/app.js"></script>
</body>
</html>
