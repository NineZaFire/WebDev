<?php
// เชื่อมต่อฐานข้อมูล : เวอร์ชัน GitHub Pages
// PHP ทำงานในเบราว์เซอร์ (WebAssembly) จึงใช้ SQLite แทน MySQL ข้อมูลเก็บไว้ในเบราว์เซอร์ของผู้ใช้แต่ละคน
// ไฟล์นี้ใช้แทน includes/connect.php เฉพาะในโฟลเดอร์ docs/ โค้ดหลักใน project web ยังใช้ MySQL ตามเดิม
date_default_timezone_set('Asia/Bangkok');

// แปลงคำสั่งเฉพาะของ MySQL ที่เว็บใช้ ให้ SQLite เข้าใจ
class ShopPdo extends PDO
{
    public static function translate(string $sql): string
    {
        if (preg_match('/^\s*SET\s+time_zone/i', $sql)) {
            return 'SELECT 1';
        }
        $sql = preg_replace('/\s+FOR\s+UPDATE\b/i', '', $sql);
        // CURDATE() - INTERVAL 1 DAY → date(CURDATE(), '-1 day')
        $sql = preg_replace_callback(
            '/\b(CURDATE|NOW)\(\)\s*([+-])\s*INTERVAL\s+(\d+)\s+(DAY|HOUR|MONTH)\b/i',
            fn($m) => (strtoupper($m[1]) === 'CURDATE' ? 'date' : 'datetime')
                . "({$m[1]}(), '{$m[2]}{$m[3]} " . strtolower($m[4]) . "')",
            $sql
        );
        // DELETE แบบ JOIN มีเฉพาะใน MySQL
        $sql = preg_replace(
            '/DELETE\s+m\s+FROM\s+stock_movements\s+m\s+JOIN\s+product_variants\s+v\s+ON\s+v\.id\s*=\s*m\.variant_id\s+WHERE\s+v\.product_id\s*=\s*\?/i',
            'DELETE FROM stock_movements WHERE variant_id IN (SELECT id FROM product_variants WHERE product_id = ?)',
            $sql
        );
        return $sql;
    }

    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        return parent::prepare(self::translate($query), $options);
    }

    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false
    {
        return $fetchMode === null
            ? parent::query(self::translate($query))
            : parent::query(self::translate($query), $fetchMode, ...$fetchModeArgs);
    }

    public function exec(string $statement): int|false
    {
        return parent::exec(self::translate($statement));
    }
}

$dbFile = getenv('SHOP_SQLITE') ?: '/persist/data/shop.sqlite';   // /persist = IndexedDB ของเบราว์เซอร์

// ในเบราว์เซอร์ : โค้ดอยู่ใน /preload/www (โหลดใหม่ทุกครั้ง) ข้อมูลที่ต้องเก็บไว้อยู่ใน /persist
if (!getenv('SHOP_SQLITE')) {
    foreach (['/persist/sessions', '/persist/uploads/products', '/persist/uploads/slips'] as $dir) {
        is_dir($dir) || @mkdir($dir, 0777, true);
    }
    $uploads = dirname(__DIR__) . '/uploads';
    if (!file_exists($uploads)) {
        @symlink('/persist/uploads', $uploads);
    }
}
$fresh  = !file_exists($dbFile);
if ($fresh) {
    @mkdir(dirname($dbFile), 0777, true);
    copy(__DIR__ . '/../_seed/shop.seed.sqlite', $dbFile);
}

$pdo = new ShopPdo('sqlite:' . $dbFile, null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->sqliteCreateFunction('NOW', fn() => date('Y-m-d H:i:s'), 0);
$pdo->sqliteCreateFunction('CURDATE', fn() => date('Y-m-d'), 0);
$pdo->sqliteCreateFunction('YEAR', fn($d) => $d === null ? null : (int) substr($d, 0, 4), 1);
$pdo->sqliteCreateFunction('MONTH', fn($d) => $d === null ? null : (int) substr($d, 5, 2), 1);
$pdo->sqliteCreateFunction('DATE_FORMAT', function ($d, $format) {
    if ($d === null) {
        return null;
    }
    $map = ['%Y' => 'Y', '%m' => 'm', '%d' => 'd', '%H' => 'H', '%i' => 'i', '%s' => 's', '%y' => 'y', '%e' => 'j', '%c' => 'n'];
    return date(strtr($format, $map), strtotime($d));
}, 2);
$pdo->exec('PRAGMA foreign_keys = ON');

// เปิดเว็บครั้งแรก : เลื่อนวันที่ของข้อมูลจำลองให้ "วันนี้" ในข้อมูลตรงกับวันนี้ (เหมือนท้ายไฟล์ shirtshop_xampp.sql)
if ($fresh) {
    $base  = $pdo->query("SELECT v FROM app_meta WHERE k = 'base_date'")->fetchColumn();
    $shift = (int) (new DateTime($base))->diff(new DateTime(date('Y-m-d')))->format('%r%a');
    if ($shift !== 0) {
        $d = ($shift > 0 ? '+' : '') . $shift . ' days';
        $pdo->beginTransaction();
        $pdo->exec("UPDATE orders SET created_at = datetime(created_at, '$d'), paid_at = datetime(paid_at, '$d')");
        $pdo->exec("UPDATE stock_movements SET created_at = datetime(created_at, '$d')");
        $pdo->exec("UPDATE promotions SET start_at = datetime(start_at, '$d'), end_at = datetime(end_at, '$d')");
        $pdo->exec("UPDATE coupons SET expires_at = datetime(expires_at, '$d')");
        $pdo->commit();
    }
}

// ฟังก์ชันเล็ก ๆ ที่ทุกหน้าใช้ (เหมือน includes/connect.php ต้นฉบับ)
function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

// ฿290 หรือ ฿1,011.55 (แสดงสตางค์เฉพาะเมื่อมี)
function baht(float $n): string
{
    return '฿' . number_format($n, floor($n) == $n ? 0 : 2);
}
