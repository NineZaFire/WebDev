<?php
// เนื้อหาหน้าตะกร้า : cart.php ใช้แสดงครั้งแรก และ api/cart.php ส่ง HTML นี้กลับไปให้ Ajax ใส่แทนของเดิม
// $quote มาจาก price_cart() ใน pricing.php
require_once __DIR__ . '/pricing.php';
require_once __DIR__ . '/ui.php';

function cart_view(array $quote, string $couponCode): string
{
    ob_start();

    if (!$quote['lines']) { ?>
        <div class="empty-state">
            <?= icon('bag') ?>
            <h1>ตะกร้ายังว่าง</h1>
            <p class="muted">เลือกเสื้อที่ชอบแล้วกดเพิ่มลงตะกร้าได้เลย</p>
            <a class="button" href="index.php">เลือกซื้อสินค้า</a>
        </div>
        <?php return ob_get_clean();
    }

    $afterDiscount = $quote['net_total'] - $quote['shipping_fee'];
    ?>
    <h1 class="desktop-only">ตะกร้าสินค้า (<?= $quote['item_count'] ?>)</h1>

    <?php foreach ($quote['warnings'] as $warning): ?>
        <p class="notice"><?= e($warning) ?></p>
    <?php endforeach; ?>

    <div class="cart-layout">
        <div>
            <section class="card cart-items" aria-label="รายการสินค้าในตะกร้า">
                <div class="cart-row cart-head desktop-only">
                    <span>สินค้า</span><span>ราคา</span><span>จำนวน</span><span>รวม</span>
                </div>

                <?php foreach ($quote['lines'] as $line): ?>
                    <div class="cart-row">
                        <?php if ($line['image']): ?>
                            <img class="product-thumb cart-thumb" src="../uploads/products/<?= e($line['image']) ?>" alt="">
                        <?php else: ?>
                            <div class="product-thumb cart-thumb"><?= icon('shirt') ?></div>
                        <?php endif; ?>

                        <div class="cart-name">
                            <a href="product.php?id=<?= $line['product_id'] ?>"><?= e($line['name']) ?></a>
                            <span class="muted">ไซซ์ <?= $line['size'] ?></span>
                        </div>

                        <span class="cart-price price"><?= baht($line['unit_price']) ?></span>

                        <!-- ปุ่มเพิ่ม/ลด/ลบ บอกคำสั่งผ่าน data-action ให้ app.js ส่งไป api/cart.php -->
                        <div class="stepper cart-qty">
                            <button type="button" data-action="update" data-variant="<?= $line['variant_id'] ?>"
                                    data-qty="<?= $line['qty'] - 1 ?>" aria-label="ลดจำนวน"><?= icon('minus') ?></button>
                            <span><?= $line['qty'] ?></span>
                            <button type="button" data-action="update" data-variant="<?= $line['variant_id'] ?>"
                                    data-qty="<?= $line['qty'] + 1 ?>" aria-label="เพิ่มจำนวน"
                                    <?= $line['qty'] >= $line['stock'] ? 'disabled' : '' ?>><?= icon('plus') ?></button>
                        </div>

                        <span class="cart-total desktop-only"><?= baht($line['line_total']) ?></span>

                        <button type="button" class="icon-button cart-remove" data-action="remove"
                                data-variant="<?= $line['variant_id'] ?>" aria-label="ลบ <?= e($line['name']) ?>"><?= icon('trash') ?></button>
                    </div>
                <?php endforeach; ?>
            </section>

            <a class="back-link desktop-only" href="index.php"><?= icon('back') ?>เลือกซื้อสินค้าต่อ</a>
        </div>

        <aside class="card">
            <h2 class="desktop-only">สรุปคำสั่งซื้อ</h2>

            <form class="field coupon-form">
                <input type="hidden" name="action" value="coupon">
                <label for="couponCode">คูปองส่วนลด</label>
                <div class="coupon-row">
                    <input type="text" id="couponCode" name="code" value="<?= e($couponCode) ?>" placeholder="กรอกโค้ด เช่น LM10" maxlength="30">
                    <button class="button button-outline" type="submit">ใช้โค้ด</button>
                </div>
                <?php if ($quote['coupon']): ?>
                    <p class="field-ok"><?= icon('check') ?>ใช้โค้ด <?= e($quote['coupon']['code']) ?> แล้ว ลด <?= baht($quote['coupon_discount']) ?></p>
                <?php elseif ($quote['coupon_error']): ?>
                    <p class="field-error"><?= icon('alert') . e($quote['coupon_error']) ?></p>
                <?php endif; ?>
            </form>

            <dl class="summary-list">
                <div>
                    <dt>ราคาสินค้า (<?= $quote['item_count'] ?> ชิ้น)</dt>
                    <dd><?= baht($quote['subtotal']) ?></dd>
                </div>
                <?php if ($quote['promotion']): ?>
                    <div>
                        <dt>โปร<?= e($quote['promotion']['name']) ?><small><?= e($quote['promotion']['detail']) ?></small></dt>
                        <dd>−<?= baht($quote['promo_discount']) ?></dd>
                    </div>
                <?php endif; ?>
                <?php if ($quote['coupon']): ?>
                    <div>
                        <dt>คูปอง <?= e($quote['coupon']['code']) ?></dt>
                        <dd>−<?= baht($quote['coupon_discount']) ?></dd>
                    </div>
                <?php endif; ?>
                <div>
                    <dt>ค่าจัดส่ง</dt>
                    <dd><?= $quote['shipping_fee'] > 0 ? baht($quote['shipping_fee']) : 'ส่งฟรี' ?></dd>
                </div>
                <div class="summary-total">
                    <dt>รวมทั้งหมด</dt>
                    <dd><?= baht($quote['net_total']) ?></dd>
                </div>
            </dl>

            <?php if ($quote['shipping_fee'] > 0): ?>
                <p class="field-hint">ซื้อเพิ่มอีก <?= baht(FREE_SHIPPING_MIN - $afterDiscount) ?> ส่งฟรี</p>
            <?php endif; ?>

            <a class="button button-block desktop-only checkout-button" href="checkout.php">ไปชำระเงิน</a>
        </aside>
    </div>

    <div class="buy-bar mobile-only">
        <div class="buy-total">
            <span class="muted">ยอดชำระ</span>
            <strong><?= baht($quote['net_total']) ?></strong>
        </div>
        <a class="button" href="checkout.php">ไปชำระเงิน</a>
    </div>
    <?php
    return ob_get_clean();
}
