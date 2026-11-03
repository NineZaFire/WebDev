<?php
// เชื่อมต่อฐานข้อมูล : อ่านค่าจาก environment (Docker) ถ้าไม่มีใช้ค่าตั้งต้นของ XAMPP
date_default_timezone_set('Asia/Bangkok');

$dbHost = getenv('DB_HOST') ?: 'localhost';
$dbPort = getenv('DB_PORT') ?: '3306';
$dbName = getenv('DB_NAME') ?: 'shopdb';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPass = getenv('DB_PASS') ?: '';

$pdo = new PDO(
    "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4",
    $dbUser,
    $dbPass,
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]
);
$pdo->exec("SET time_zone = '+07:00'");

// ฟังก์ชันเล็ก ๆ ที่ทุกหน้าใช้
function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

// ฿290 หรือ ฿1,011.55 (แสดงสตางค์เฉพาะเมื่อมี)
function baht(float $n): string
{
    return '฿' . number_format($n, floor($n) == $n ? 0 : 2);
}
