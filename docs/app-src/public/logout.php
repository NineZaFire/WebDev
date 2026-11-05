<?php
require_once __DIR__ . '/../includes/auth.php';

// ออกจากระบบด้วย POST เท่านั้น ลิงก์จากเว็บอื่นจะสั่งให้ผู้ใช้ออกจากระบบไม่ได้
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    logout_user();
}
header('Location: index.php');
