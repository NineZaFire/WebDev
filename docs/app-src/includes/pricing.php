<?php
// ที่เดียวที่คำนวณราคา : ตะกร้า, หน้าชำระเงิน และ api เรียกใช้ price_cart() ตัวเดียวกัน
//
// กติกา
//   1. โปรโมชั่นนับสินค้าทั้งตะกร้า (เฉพาะหมวดที่โปรกำหนด) แล้วเลือกโปรที่ลดได้มากที่สุดเพียงโปรเดียว
//        buy_x_get_y    ทุก ๆ (buy+free) ชิ้น แถม free ชิ้น โดยแถมตัวที่ราคาต่ำที่สุด
//        timed_discount ลด % หรือบาทต่อชิ้น ทุกชิ้นในช่วงเวลาที่กำหนด
//        volume         ลด % ตามขั้นสูงสุดใน promotion_tiers ที่จำนวนชิ้นถึง
//        bundle         ครบชุดตาม promotion_bundle_items จ่าย bundle_price ต่อเซ็ต (จับคู่ตัวแพงก่อน)
//   2. คูปองคิดจากยอดหลังหักโปรโมชั่น และใช้ยอดนั้นเทียบ min_total
//   3. ยอดหลังหักส่วนลดทั้งหมด >= FREE_SHIPPING_MIN ส่งฟรี ไม่ถึงคิด SHIPPING_FEE
require_once __DIR__ . '/connect.php';

const SHIPPING_FEE = 40;
const FREE_SHIPPING_MIN = 1000;

/**
 * @param array       $cart       [variant_id => qty] (รูปแบบเดียวกับ $_SESSION['cart'])
 * @param string|null $couponCode โค้ดที่ผู้ใช้กรอก
 * @param string|null $now        'Y-m-d H:i:s' ใช้ตรวจช่วงเวลาโปร/คูปอง (ค่าตั้งต้น = ตอนนี้)
 */
function price_cart(PDO $pdo, array $cart, ?string $couponCode = null, ?string $now = null): array
{
    $now ??= date('Y-m-d H:i:s');
    $quote = [
        'lines'           => [],
        'warnings'        => [],
        'item_count'      => 0,
        'subtotal'        => 0.0,
        'promotion'       => null,
        'promo_discount'  => 0.0,
        'coupon'          => null,
        'coupon_error'    => null,
        'coupon_discount' => 0.0,
        'discount_total'  => 0.0,
        'shipping_fee'    => 0.0,
        'net_total'       => 0.0,
    ];

    $cart = array_filter(array_map('intval', $cart), fn($q) => $q > 0);
    if (!$cart) {
        return $quote;
    }

    $variants = load_variants($pdo, array_keys($cart));
    $units    = [];   // สินค้าทีละชิ้น ใช้คิดโปรที่นับข้ามรายการ

    foreach ($cart as $variantId => $qty) {
        $v = $variants[$variantId] ?? null;
        if (!$v || !$v['is_active']) {
            $quote['warnings'][] = "สินค้ารหัส $variantId ไม่มีจำหน่ายแล้ว";
            continue;
        }
        if ($qty > $v['stock']) {
            $quote['warnings'][] = "{$v['name']} ไซซ์ {$v['size']} เหลือเพียง {$v['stock']} ชิ้น";
        }

        $price = (float) $v['base_price'];
        $index = count($quote['lines']);
        $quote['lines'][] = [
            'variant_id'      => $variantId,
            'product_id'      => (int) $v['product_id'],
            'category_id'     => (int) $v['category_id'],
            'name'            => $v['name'],
            'size'            => $v['size'],
            'image'           => $v['image'],
            'stock'           => (int) $v['stock'],
            'qty'             => $qty,
            'unit_price'      => $price,
            'line_total'      => $price * $qty,
            'promotion_id'    => null,
            'discount'        => 0.0,
            'unit_price_paid' => $price,
        ];
        for ($i = 0; $i < $qty; $i++) {
            $units[] = ['line' => $index, 'price' => $price, 'category_id' => (int) $v['category_id']];
        }

        $quote['item_count'] += $qty;
        $quote['subtotal']   += $price * $qty;
    }

    // ลองทุกโปรที่ใช้ได้ตอนนี้ เก็บโปรที่ลดรวมได้มากที่สุด
    $best = null;
    foreach (load_active_promotions($pdo, $now) as $p) {
        $result = promotion_result($p, $units);
        if ($result && $result['total'] > ($best['total'] ?? 0)) {
            $best = $result + ['promotion' => $p];
        }
    }

    if ($best) {
        $quote['promotion'] = [
            'id'     => (int) $best['promotion']['id'],
            'name'   => $best['promotion']['name'],
            'detail' => $best['detail'],
        ];
        foreach ($best['by_line'] as $index => $off) {
            $line = &$quote['lines'][$index];
            $line['promotion_id']    = $quote['promotion']['id'];
            $line['discount']        = $off;
            $line['unit_price_paid'] = round(($line['line_total'] - $off) / $line['qty'], 2);
            unset($line);
        }
        $quote['promo_discount'] = round(array_sum($best['by_line']), 2);
    }

    $afterPromo = $quote['subtotal'] - $quote['promo_discount'];

    $couponCode = strtoupper(trim($couponCode ?? ''));
    if ($couponCode !== '') {
        [$coupon, $error] = check_coupon($pdo, $couponCode, $afterPromo, $now);
        if ($coupon) {
            $quote['coupon']          = ['id' => (int) $coupon['id'], 'code' => $coupon['code']];
            $quote['coupon_discount'] = $coupon['discount_type'] === 'percent'
                ? round($afterPromo * $coupon['discount_value'] / 100, 2)
                : min((float) $coupon['discount_value'], $afterPromo);
        } else {
            $quote['coupon_error'] = $error;
        }
    }

    $afterDiscount = $afterPromo - $quote['coupon_discount'];

    $quote['discount_total'] = round($quote['promo_discount'] + $quote['coupon_discount'], 2);
    $quote['shipping_fee']   = ($quote['lines'] && $afterDiscount < FREE_SHIPPING_MIN) ? SHIPPING_FEE : 0.0;
    $quote['net_total']      = round($afterDiscount + $quote['shipping_fee'], 2);

    return $quote;
}

function load_variants(PDO $pdo, array $ids): array
{
    $in   = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT v.id, v.product_id, v.size, v.stock,
                p.name, p.base_price, p.category_id, p.is_active,
                (SELECT filename FROM product_images i
                  WHERE i.product_id = p.id ORDER BY i.sort_order, i.id LIMIT 1) AS image
           FROM product_variants v
           JOIN products p ON p.id = v.product_id
          WHERE v.id IN ($in)"
    );
    $stmt->execute(array_values($ids));

    $rows = [];
    foreach ($stmt as $r) {
        $rows[(int) $r['id']] = $r;
    }
    return $rows;
}

// โปรที่เปิดอยู่และอยู่ในช่วงเวลา พร้อม tiers (volume) และ bundle_items (bundle)
function load_active_promotions(PDO $pdo, string $now): array
{
    $stmt = $pdo->prepare(
        "SELECT * FROM promotions
          WHERE is_active = 1
            AND (start_at IS NULL OR start_at <= :now1)
            AND (end_at   IS NULL OR end_at   >= :now2)"
    );
    $stmt->execute(['now1' => $now, 'now2' => $now]);

    $promos = [];
    foreach ($stmt as $p) {
        $p['tiers']        = [];
        $p['bundle_items'] = [];
        $promos[(int) $p['id']] = $p;
    }
    if (!$promos) {
        return [];
    }

    $in = implode(',', array_keys($promos));
    foreach ($pdo->query("SELECT * FROM promotion_tiers WHERE promotion_id IN ($in) ORDER BY min_qty") as $t) {
        $promos[(int) $t['promotion_id']]['tiers'][] = $t;
    }
    foreach ($pdo->query("SELECT * FROM promotion_bundle_items WHERE promotion_id IN ($in)") as $b) {
        $promos[(int) $b['promotion_id']]['bundle_items'][] = $b;
    }
    return array_values($promos);
}

/**
 * ส่วนลดของโปรหนึ่งตัวต่อทั้งตะกร้า
 * @return array{total: float, by_line: array<int, float>, detail: string}|null  null = ใช้ไม่ได้
 */
function promotion_result(array $p, array $units): ?array
{
    if ($p['category_id'] !== null) {
        $units = array_values(array_filter($units, fn($u) => $u['category_id'] === (int) $p['category_id']));
    }
    $byLine = [];
    $add = function (int $line, float $off) use (&$byLine) {
        $byLine[$line] = ($byLine[$line] ?? 0) + $off;
    };

    switch ($p['type']) {
        case 'buy_x_get_y':
            $buy  = (int) $p['buy_qty'];
            $free = (int) $p['free_qty'];
            if ($buy < 1 || $free < 1) {
                return null;
            }
            $freeCount = intdiv(count($units), $buy + $free) * $free;
            usort($units, fn($a, $b) => $a['price'] <=> $b['price']);
            foreach (array_slice($units, 0, $freeCount) as $u) {
                $add($u['line'], $u['price']);
            }
            $detail = "ซื้อ $buy แถม $free (แถม $freeCount ชิ้น)";
            break;

        case 'timed_discount':
            foreach ($units as $u) {
                $off = $p['discount_type'] === 'percent'
                    ? $u['price'] * $p['discount_value'] / 100
                    : min((float) $p['discount_value'], $u['price']);
                $add($u['line'], $off);
            }
            $detail = $p['discount_type'] === 'percent'
                ? 'ลด ' . (float) $p['discount_value'] . '%'
                : 'ลดชิ้นละ ' . baht((float) $p['discount_value']);
            break;

        case 'volume':
            $tier = null;
            foreach ($p['tiers'] as $t) {   // เรียง min_qty จากน้อยไปมากแล้ว
                if (count($units) >= (int) $t['min_qty']) {
                    $tier = $t;
                }
            }
            if (!$tier) {
                return null;
            }
            foreach ($units as $u) {
                $add($u['line'], $u['price'] * $tier['discount_percent'] / 100);
            }
            $detail = "ซื้อ {$tier['min_qty']} ชิ้นขึ้นไป ลด " . (float) $tier['discount_percent'] . '%';
            break;

        case 'bundle':
            [$sets, $byLine] = bundle_discount($p, $units);
            $detail = "$sets เซ็ต เซ็ตละ " . baht((float) $p['bundle_price']);
            break;

        default:
            return null;
    }

    if (!$byLine) {
        return null;
    }
    // ปัดทีละรายการแล้วเศษสตางค์อาจไม่ครบ ใส่ส่วนต่างไว้ที่รายการที่ลดมากที่สุด
    $total  = round(array_sum($byLine), 2);
    $byLine = array_map(fn($off) => round($off, 2), $byLine);
    $k = array_search(max($byLine), $byLine);
    $byLine[$k] = round($byLine[$k] + $total - array_sum($byLine), 2);

    return $total > 0 ? ['total' => $total, 'by_line' => $byLine, 'detail' => $detail] : null;
}

// จับเซ็ตจากตัวแพงสุดของแต่ละหมวดก่อน หยุดเมื่อเซ็ตถัดไปไม่ได้ลดแล้ว
// ส่วนลดของแต่ละเซ็ตเฉลี่ยลงแต่ละชิ้นตามสัดส่วนราคา
function bundle_discount(array $p, array $units): array
{
    $price = (float) $p['bundle_price'];
    if (!$p['bundle_items'] || $price <= 0) {
        return [0, []];
    }

    $pools = [];
    foreach ($p['bundle_items'] as $item) {
        $cat  = (int) $item['category_id'];
        $pool = array_values(array_filter($units, fn($u) => $u['category_id'] === $cat));
        usort($pool, fn($a, $b) => $b['price'] <=> $a['price']);
        $pools[] = ['qty' => (int) $item['qty'], 'units' => $pool];
    }
    $maxSets = min(array_map(fn($pl) => intdiv(count($pl['units']), $pl['qty']), $pools));

    $byLine = [];
    $sets   = 0;
    for ($s = 0; $s < $maxSets; $s++) {
        $members = [];
        foreach ($pools as $pl) {
            array_push($members, ...array_slice($pl['units'], $s * $pl['qty'], $pl['qty']));
        }
        $full = array_sum(array_column($members, 'price'));
        $off  = $full - $price;
        if ($off <= 0) {
            break;
        }
        foreach ($members as $m) {
            $byLine[$m['line']] = ($byLine[$m['line']] ?? 0) + $off * $m['price'] / $full;
        }
        $sets++;
    }
    return [$sets, $byLine];
}

/** @return array{0: ?array, 1: ?string} [คูปอง, ข้อความ error] */
function check_coupon(PDO $pdo, string $code, float $amount, string $now): array
{
    $stmt = $pdo->prepare('SELECT * FROM coupons WHERE code = ?');
    $stmt->execute([$code]);
    $c = $stmt->fetch();

    if (!$c) {
        return [null, 'ไม่พบคูปองนี้'];
    }
    if ($c['expires_at'] !== null && $c['expires_at'] < $now) {
        return [null, 'คูปองหมดอายุแล้ว'];
    }
    if ($amount < (float) $c['min_total']) {
        return [null, 'ยอดซื้อขั้นต่ำสำหรับคูปองนี้คือ ' . baht((float) $c['min_total'])];
    }
    if ($c['max_uses'] !== null) {
        $used = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE coupon_id = ? AND status NOT IN ('cancelled','expired')");
        $used->execute([$c['id']]);
        if ((int) $used->fetchColumn() >= (int) $c['max_uses']) {
            return [null, 'คูปองนี้ถูกใช้ครบจำนวนแล้ว'];
        }
    }
    return [$c, null];
}
