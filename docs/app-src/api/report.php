<?php
// ข้อมูลรายงานยอดขายเป็น JSON : แดชบอร์ดและหน้ารายงานใช้วาดกราฟ ตาราง และแถบสถานะ
//   ?range=7d | 30d | 12m            7 วัน / 30 วัน นับถึงวันนี้ หรือ 12 เดือนนับถึงเดือนนี้
//   ?from=2026-10-01&to=2026-10-19   ช่วงที่แอดมินกรอกเอง (รายวัน ไม่เกิน MAX_DAYS วัน)
require_once __DIR__ . '/../includes/admin.php';
require_admin();

const MAX_DAYS = 92;   // ช่วงที่กรอกเองยาวได้ไม่เกินนี้ (ราว 3 เดือน กราฟรายวันยังอ่านออก)

header('Content-Type: application/json; charset=utf-8');

$range = isset($_GET['from']) ? 'custom' : ($_GET['range'] ?? '7d');
if (!in_array($range, ['7d', '30d', '12m', 'custom'], true)) {
    $range = '7d';
}

// ช่องของกราฟ : รายวัน หรือรายเดือน
$today = new DateTimeImmutable('today');
if ($range === 'custom') {
    $start = parse_date($_GET['from'] ?? '');
    $end   = parse_date($_GET['to'] ?? '');
    $error = range_error($start, $end, $today);
    if ($error) {
        echo json_encode(['error' => $error], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $count  = $start->diff($end)->days + 1;
    $step   = '+1 day';
    $bucket = 'DATE(created_at)';
    $key    = 'Y-m-d';
} elseif ($range === '12m') {
    $count  = 12;
    $step   = '+1 month';
    $start  = $today->modify('first day of this month')->modify('-11 months');
    $bucket = "DATE_FORMAT(created_at, '%Y-%m')";
    $key    = 'Y-m';
} else {
    $count  = $range === '30d' ? 30 : 7;
    $step   = '+1 day';
    $start  = $today->modify('-' . ($count - 1) . ' days');
    $bucket = 'DATE(created_at)';
    $key    = 'Y-m-d';
}
// นับออเดอร์ตั้งแต่ $from ถึงก่อน $until (วันแรกหลังช่วง)
$from  = $start->format('Y-m-d');
$until = $start->modify("+$count " . ($range === '12m' ? 'months' : 'days'))->format('Y-m-d');

// ยอดขายของแต่ละวัน/เดือน (นับเฉพาะออเดอร์ที่ชำระแล้ว)
$stmt = $pdo->prepare(
    "SELECT $bucket AS bucket, SUM(net_total) AS sales, COUNT(*) AS orders
       FROM orders
      WHERE status IN (" . PAID_SQL . ") AND created_at >= ? AND created_at < ?
      GROUP BY bucket"
);
$stmt->execute([$from, $until]);
$totals = [];
foreach ($stmt as $row) {
    $totals[$row['bucket']] = $row;
}

// เติมวัน/เดือนที่ไม่มียอดขายเป็น 0 กราฟจะได้ครบทุกช่อง
$series = [];
for ($i = 0, $day = $start; $i < $count; $i++, $day = $day->modify($step)) {
    $row = $totals[$day->format($key)] ?? null;
    $series[] = [
        'label'  => series_label($day, $range),
        'sales'  => (float) ($row['sales'] ?? 0),
        'orders' => (int) ($row['orders'] ?? 0),
    ];
}
$sales  = round(array_sum(array_column($series, 'sales')), 2);
$orders = array_sum(array_column($series, 'orders'));

// จำนวนออเดอร์แยกตามสถานะ (ทุกออเดอร์ในช่วงนี้)
$stmt = $pdo->prepare(
    "SELECT CASE WHEN status IN (" . PAID_SQL . ") THEN 'paid' ELSE status END AS state, COUNT(*)
       FROM orders
      WHERE created_at >= ? AND created_at < ?
      GROUP BY state"
);
$stmt->execute([$from, $until]);
$byState  = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
$statuses = [];
foreach (['paid' => 'ชำระแล้ว', 'pending' => 'รอตรวจสลิป', 'cancelled' => 'ยกเลิก', 'expired' => 'หมดเวลาชำระ'] as $state => $label) {
    $statuses[] = ['key' => $state, 'label' => $label, 'count' => (int) ($byState[$state] ?? 0)];
}

// สินค้าขายดี 5 อันดับ นับจำนวนตัวที่ขายได้
$stmt = $pdo->prepare(
    "SELECT p.name, SUM(oi.qty) AS qty
       FROM order_items oi
       JOIN orders o ON o.id = oi.order_id
       JOIN product_variants v ON v.id = oi.variant_id
       JOIN products p ON p.id = v.product_id
      WHERE o.status IN (" . PAID_SQL . ") AND o.created_at >= ? AND o.created_at < ?
      GROUP BY p.id, p.name
      ORDER BY qty DESC, p.name
      LIMIT 5"
);
$stmt->execute([$from, $until]);
$top = array_map(fn($row) => ['name' => $row['name'], 'qty' => (int) $row['qty']], $stmt->fetchAll());

echo json_encode([
    'range'    => $range,
    'unit'     => $range === '12m' ? 'เดือน' : 'วัน',
    'summary'  => ['sales' => $sales, 'orders' => $orders, 'average' => $orders ? round($sales / $orders, 2) : 0],
    'series'   => $series,
    'statuses' => $statuses,
    'top'      => $top,
], JSON_UNESCAPED_UNICODE);

// ชื่อช่องในกราฟ : "จ. 13" (7 วัน) / "13 ต.ค." (30 วันและช่วงที่กรอกเอง) / "ต.ค. 69" (12 เดือน) และวันนี้แสดงว่า "วันนี้"
function series_label(DateTimeImmutable $day, string $range): string
{
    if ($range === '12m') {
        return THAI_MONTHS[$day->format('n') - 1] . ' ' . substr((string) ($day->format('Y') + 543), -2);
    }
    if ($day->format('Y-m-d') === date('Y-m-d')) {
        return 'วันนี้';
    }
    return $range === '7d'
        ? THAI_DAYS_SHORT[$day->format('w')] . ' ' . $day->format('j')
        : $day->format('j') . ' ' . THAI_MONTHS[$day->format('n') - 1];
}

// "2026-10-19" เป็นวันที่ (รูปแบบผิด หรือไม่มีวันนั้นจริง เช่น 30 ก.พ. = null)
function parse_date(string $value): ?DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $date : null;
}

// ตรวจช่วงที่กรอกเองซ้ำฝั่งเซิร์ฟเวอร์ : คืนข้อความ error หรือ null ถ้าใช้ได้
function range_error(?DateTimeImmutable $start, ?DateTimeImmutable $end, DateTimeImmutable $today): ?string
{
    if (!$start || !$end) {
        return 'กรุณากรอกวันที่ให้ครบและถูกต้อง';
    }
    if ($start > $end) {
        return 'วันเริ่มต้องไม่หลังวันสิ้นสุด';
    }
    if ($end > $today) {
        return 'วันสิ้นสุดต้องไม่เกินวันนี้';
    }
    if ($start->diff($end)->days >= MAX_DAYS) {
        return 'เลือกได้ไม่เกิน ' . MAX_DAYS . ' วัน ถ้าต้องการช่วงที่ยาวกว่านี้ให้ดูแบบ 12 เดือน';
    }
    return null;
}
