<?php
// ส่วนหน้าตาเล็ก ๆ ที่หลายหน้าใช้ซ้ำ

const THAI_DAYS       = ['อาทิตย์', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์'];
const THAI_DAYS_SHORT = ['อา.', 'จ.', 'อ.', 'พ.', 'พฤ.', 'ศ.', 'ส.'];
const THAI_MONTHS     = ['ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];

// วันที่แบบไทย ปี พ.ศ. : "19 ต.ค. 2569" หรือ "อาทิตย์ 19 ต.ค. 2569"
function thai_date(string $date, bool $withWeekday = false): string
{
    $time = strtotime($date);
    $text = date('j', $time) . ' ' . THAI_MONTHS[date('n', $time) - 1] . ' ' . (date('Y', $time) + 543);
    return $withWeekday ? THAI_DAYS[date('w', $time)] . " $text" : $text;
}

// วันเวลาแบบสั้น : "19 ต.ค. 10:21"
function thai_time(string $datetime): string
{
    $time = strtotime($datetime);
    return date('j', $time) . ' ' . THAI_MONTHS[date('n', $time) - 1] . ' ' . date('H:i', $time);
}

// เลขที่ออเดอร์ที่ลูกค้าและแอดมินเห็น เช่น #LM-1024
function order_no(int $id): string
{
    return '#LM-' . (1000 + $id);
}

// สถานะออเดอร์ : status => [ชื่อ, class ของป้าย, ไอคอน, คำอธิบายสำหรับลูกค้า]
const ORDER_STATUS = [
    'pending'   => ['รอตรวจสลิป', 'tag-warning', 'clock', 'แนบสลิปแล้ว รอแอดมินตรวจ'],
    'paid'      => ['ชำระแล้ว', 'tag-success', 'check', 'ยืนยันการชำระแล้ว รอเตรียมจัดส่ง'],
    'preparing' => ['เตรียมจัดส่ง', 'tag-primary', 'bag', 'ร้านกำลังแพ็กสินค้า'],
    'shipped'   => ['กำลังจัดส่ง', 'tag-primary', 'truck', 'อยู่ระหว่างขนส่ง'],
    'completed' => ['สำเร็จ', 'tag-success', 'check', 'ส่งถึงแล้ว'],
    'cancelled' => ['ยกเลิก', 'tag-danger', 'x', 'ยกเลิกแล้ว'],
    'expired'   => ['หมดเวลาชำระ', '', 'clock', 'ไม่ได้ชำระเงินภายในเวลาที่กำหนด'],
];

// สถานะที่นับเป็นยอดขาย (ชำระแล้ว) ใช้ใน SQL : WHERE status IN (" . PAID_SQL . ")
// ไม่นับ pending (รอตรวจสลิป), cancelled (ยกเลิก), expired (หมดเวลาชำระ)
const PAID_SQL = "'paid', 'preparing', 'shipped', 'completed'";

const SIZES = ['S', 'M', 'L', 'XL'];   // ตรงกับ ENUM ของ product_variants.size

function status_tag(string $status): string
{
    [$label, $class, $iconName] = ORDER_STATUS[$status];
    return "<span class=\"tag $class\">" . icon($iconName) . $label . '</span>';
}

// แถบขั้นตอนสั่งซื้อ ตะกร้า → ชำระเงิน → เสร็จสิ้น ($current = ขั้นที่อยู่ตอนนี้ 1–3)
function checkout_steps(int $current): string
{
    $html = '<ol class="steps" aria-label="ขั้นตอนการสั่งซื้อ">';
    foreach (['ตะกร้า', 'ชำระเงิน', 'เสร็จสิ้น'] as $i => $label) {
        $step = $i + 1;
        if ($step < $current) {
            $html .= '<li class="step-done"><span>' . icon('check') . "</span>$label</li>";
        } else {
            $html .= '<li' . ($step === $current ? ' aria-current="step"' : '') . "><span>$step</span>$label</li>";
        }
    }
    return $html . '</ol>';
}

// ไอคอนเส้นแบบเดียวกับ wireframe เขียนเป็น SVG ในหน้าเลย ไม่ต้องโหลดไลบรารีเพิ่ม
// สีของไอคอนตามสีตัวอักษรที่ครอบอยู่ (currentColor)
function icon(string $name): string
{
    $paths = [
        'home'   => '<path d="M3 10.5 12 3l9 7.5V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'bag'    => '<path d="M5 7h14l-1 13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1z"/><path d="M9 10V6a3 3 0 0 1 6 0v4"/>',
        'orders' => '<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4M12 16h4M8 11h.01M8 16h.01"/>',
        'user'   => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'back'   => '<path d="m15 18-6-6 6-6"/>',
        'next'   => '<path d="m9 18 6-6-6-6"/>',
        'shirt'  => '<path d="M8 3 3 6l2 5 2-1v11h10V10l2 1 2-5-5-3a4 4 0 0 1-8 0z"/>',
        'alert'  => '<circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/>',
        'check'  => '<path d="m5 12 5 5 9-10"/>',
        'minus'  => '<path d="M5 12h14"/>',
        'plus'   => '<path d="M12 5v14M5 12h14"/>',
        'trash'  => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1l1-13M9 7V4h6v3"/>',
        'x'      => '<path d="M6 6l12 12M18 6 6 18"/>',
        'chart'  => '<path d="M4 4v16h16M8 16v-4M12 16V8M16 16v-6"/>',
        'tag'    => '<path d="M3 12V4a1 1 0 0 1 1-1h8l9 9-9 9z"/><circle cx="7.5" cy="7.5" r="1.5"/>',
        'edit'   => '<path d="M4 20h4L19 9l-4-4L4 16z"/><path d="m13 7 4 4"/>',
        'clock'  => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'pin'    => '<path d="M12 21s-7-6.2-7-11a7 7 0 0 1 14 0c0 4.8-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
        'qr'     => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3M21 14v.01M14 21h.01M17 21h4v-4M17 17h.01"/>',
        'upload' => '<path d="M12 15V4M7 9l5-5 5 5M4 15v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4"/>',
        'download' => '<path d="M12 4v11M7 10l5 5 5-5M4 15v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4"/>',
        'eye'    => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'truck'  => '<path d="M3 6h11v10H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'filter' => '<path d="M21 4h-7M10 4H3M21 12h-9M8 12H3M21 20h-5M12 20H3M14 2v4M8 10v4M16 18v4"/>',
    ];

    return '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
        . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$name] . '</svg>';
}

// ใส่ใน <input> ที่มี error ให้กรอบเป็นสีแดง และโปรแกรมอ่านหน้าจอรู้ว่าช่องนี้ผิด
function invalid_attr(array $errors, string $field): string
{
    return isset($errors[$field]) ? 'aria-invalid="true"' : '';
}

// ข้อความ error ใต้ช่องกรอก (ไม่มี error = ไม่แสดงอะไร)
function field_error(array $errors, string $field): string
{
    if (!isset($errors[$field])) {
        return '';
    }
    return '<p class="field-error">' . icon('alert') . e($errors[$field]) . '</p>';
}
