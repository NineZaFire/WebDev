<?php
// session และสิทธิ์ผู้ใช้ : ทุกหน้า require ไฟล์นี้ก่อนส่ง output ใด ๆ
require_once __DIR__ . '/connect.php';

if (session_status() === PHP_SESSION_NONE) {
    // httponly = JS อ่าน cookie ไม่ได้, Lax = ฟอร์มจากเว็บอื่นส่งมาพร้อม session ของเราไม่ได้
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

// เก็บเฉพาะข้อมูลที่ต้องใช้บ่อยลง session ไม่เก็บ password_hash
function login_user(array $user): void
{
    session_regenerate_id(true);   // ได้ session ID ใหม่ กัน session fixation
    $_SESSION['user'] = [
        'id'         => (int) $user['id'],
        'first_name' => $user['first_name'],
        'email'      => $user['email'],
        'role'       => $user['role'],
        'login_at'   => date('Y-m-d H:i:s'),   // แสดงในหน้าบัญชี
    ];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// หน้าที่จะพาไปหลังล็อกอิน : รับเฉพาะ path ในเว็บนี้ (ขึ้นต้นด้วย / ตัวเดียว) กันการพาไปเว็บอื่น
function safe_next(): string
{
    $next = $_POST['next'] ?? $_GET['next'] ?? '';
    return str_starts_with($next, '/') && !str_starts_with($next, '//') ? $next : 'index.php';
}

function require_login(): void
{
    if (!current_user()) {
        $back = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header("Location: ../public/login.php?next=$back");
        exit;
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        exit('ไม่มีสิทธิ์เข้าหน้านี้');
    }
}
