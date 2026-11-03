<?php
// อ่านและตรวจค่าจากฟอร์มโปรโมชั่น ใช้ 2 ที่ : บันทึกโปร (admin/promotion_edit.php) และตัวอย่างราคา (api/promotion_preview.php)
// คืนโปรในรูปแบบเดียวกับที่ includes/pricing.php ใช้คิดส่วนลด คือแถวของตาราง promotions + tiers + bundle_items
// ช่องที่ไม่เกี่ยวกับประเภทที่เลือกเป็น null เช่น โปรลดตามจำนวนไม่มี buy_qty

const PROMO_TYPES = ['buy_x_get_y', 'timed_discount', 'volume', 'bundle'];

/**
 * @param array $categories [id => ชื่อหมวด]
 * @return array{0: array, 1: array<string, string>} [โปร, error ของแต่ละช่อง]
 */
function read_promotion_form(array $post, array $categories): array
{
    $type  = $post['type'] ?? '';
    $promo = [
        'name'           => trim($post['name'] ?? ''),
        'type'           => $type,
        'category_id'    => null,
        'discount_type'  => null,
        'discount_value' => null,
        'buy_qty'        => null,
        'free_qty'       => null,
        'bundle_price'   => null,
        'start_at'       => trim($post['start_at'] ?? ''),   // Y-m-d หรือว่าง = ไม่กำหนด
        'end_at'         => trim($post['end_at'] ?? ''),
        'is_active'      => isset($post['is_active']) ? 1 : 0,
        'tiers'          => [],
        'bundle_items'   => [],
    ];
    $errors = [];

    if ($promo['name'] === '' || mb_strlen($promo['name']) > 100) {
        $errors['name'] = 'กรุณากรอกชื่อโปรโมชั่น (ไม่เกิน 100 ตัวอักษร)';
    }
    if (!in_array($type, PROMO_TYPES, true)) {
        $errors['type'] = 'กรุณาเลือกประเภทโปรโมชั่น';
    }

    // หมวดที่ร่วมโปร ว่าง = ทุกหมวด (ชุดเซ็ตไม่ใช้ช่องนี้ ใช้หมวดในเซ็ตแทน)
    if ($type !== 'bundle' && ($post['category_id'] ?? '') !== '') {
        $promo['category_id'] = (int) $post['category_id'];
        if (!isset($categories[$promo['category_id']])) {
            $errors['category_id'] = 'กรุณาเลือกหมวดจากรายการ';
        }
    }

    // เงื่อนไขส่วนลดของประเภทที่เลือก
    $condition = null;
    switch ($type) {
        case 'buy_x_get_y':
            $promo['buy_qty']  = trim($post['buy_qty'] ?? '');
            $promo['free_qty'] = trim($post['free_qty'] ?? '');
            if (!int_between($promo['buy_qty'], 1, 99) || !int_between($promo['free_qty'], 1, 99)) {
                $condition = 'จำนวนที่ซื้อและที่แถมต้องเป็นจำนวนเต็ม 1 ถึง 99';
            }
            break;

        case 'timed_discount':
            $promo['discount_type']  = ($post['discount_type'] ?? '') === 'fixed' ? 'fixed' : 'percent';
            $promo['discount_value'] = trim($post['discount_value'] ?? '');
            if ($promo['discount_type'] === 'percent' && !int_between($promo['discount_value'], 1, 90)) {
                $condition = 'ส่วนลดต้องเป็นจำนวนเต็ม 1 ถึง 90%';
            } elseif ($promo['discount_type'] === 'fixed' && !int_between($promo['discount_value'], 1, 9999)) {
                $condition = 'ส่วนลดต่อชิ้นต้องเป็นจำนวนเต็ม 1 ถึง 9,999 บาท';
            }
            break;

        case 'volume':
            // แถวที่กรอกมาเรียงตามจำนวนชิ้นก่อนตรวจ
            foreach ((array) ($post['tier_qty'] ?? []) as $i => $qty) {
                $promo['tiers'][] = ['min_qty' => trim($qty), 'discount_percent' => trim($post['tier_percent'][$i] ?? '')];
            }
            usort($promo['tiers'], fn($a, $b) => (int) $a['min_qty'] <=> (int) $b['min_qty']);
            $condition = tier_error($promo['tiers']);
            break;

        case 'bundle':
            $ids = array_unique(array_map('intval', (array) ($post['bundle_categories'] ?? [])));
            foreach ($ids as $categoryId) {
                $promo['bundle_items'][] = ['category_id' => $categoryId, 'qty' => 1];   // หมวดละ 1 ตัว
            }
            $promo['bundle_price'] = trim($post['bundle_price'] ?? '');
            if (count($ids) < 2 || array_diff($ids, array_keys($categories))) {
                $condition = 'กรุณาเลือกหมวดในเซ็ตอย่างน้อย 2 หมวด';
            } elseif (!int_between($promo['bundle_price'], 1, 99999)) {
                $condition = 'ราคาต่อเซ็ตต้องเป็นจำนวนเต็ม 1 ถึง 99,999 บาท';
            }
            break;
    }
    if ($condition) {
        $errors['condition'] = $condition;
    }

    // วันที่ต้องเป็นวันจริงในรูปแบบ Y-m-d และวันสิ้นสุดไม่ก่อนวันเริ่ม
    foreach (['start_at', 'end_at'] as $key) {
        $date = DateTime::createFromFormat('Y-m-d', $promo[$key]);
        if ($promo[$key] !== '' && (!$date || $date->format('Y-m-d') !== $promo[$key])) {
            $errors['dates'] = 'วันที่ไม่ถูกต้อง';
        }
    }
    if (!isset($errors['dates']) && $promo['start_at'] !== '' && $promo['end_at'] !== '' && $promo['end_at'] < $promo['start_at']) {
        $errors['dates'] = 'วันสิ้นสุดต้องไม่ก่อนวันเริ่ม';
    }

    return [$promo, $errors];
}

// ขั้นส่วนลดของโปรลดตามจำนวน (เรียงตามจำนวนชิ้นแล้ว) : คืนข้อความ error หรือ null ถ้าถูกต้อง
// ขั้นถัดไปต้องซื้อมากขึ้นและลดมากขึ้น ("ซื้อมาก ลดมาก")
function tier_error(array $tiers): ?string
{
    if (!$tiers) {
        return 'กรุณาเพิ่มขั้นส่วนลดอย่างน้อย 1 ขั้น';
    }
    foreach ($tiers as $i => $tier) {
        if (!int_between($tier['min_qty'], 1, 99) || !int_between($tier['discount_percent'], 1, 90)) {
            return 'แต่ละขั้นต้องซื้อ 1 ถึง 99 ตัว และลด 1 ถึง 90%';
        }
        $previous = $tiers[$i - 1] ?? null;
        if ($previous && $tier['min_qty'] == $previous['min_qty']) {
            return 'จำนวนตัวของแต่ละขั้นต้องไม่ซ้ำกัน';
        }
        if ($previous && $tier['discount_percent'] <= $previous['discount_percent']) {
            return 'ขั้นที่ซื้อมากกว่าต้องลดมากกว่า';
        }
    }
    return null;
}

// ข้อความเป็นจำนวนเต็มระหว่าง min ถึง max ไหม (ไม่รับทศนิยม ติดลบ หรือช่องว่าง)
function int_between(string $value, int $min, int $max): bool
{
    return ctype_digit($value) && $value >= $min && $value <= $max;
}
