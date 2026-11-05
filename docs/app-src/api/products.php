<?php
// รายการสินค้าตามตัวกรองเป็น JSON : หน้าแรกเรียกด้วย fetch ทุกครั้งที่เปลี่ยนตัวกรอง
//   ?q=&category=&size[]=&price=&sort=  (แบบเดียวกับ URL ของหน้าแรก)
//   ตอบ {count: จำนวนสินค้า, title: หัวข้อรายการ, html: การ์ดสินค้าไว้ใส่แทนของเดิม}
require_once __DIR__ . '/../includes/catalog.php';

header('Content-Type: application/json; charset=utf-8');

$categories = $pdo->query('SELECT id, name FROM categories ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
$filters    = read_filters($_GET, $categories);
$products   = find_products($pdo, $filters);

echo json_encode([
    'count' => count($products),
    'title' => result_title($filters, $categories),
    'html'  => product_grid($products),
], JSON_UNESCAPED_UNICODE);
