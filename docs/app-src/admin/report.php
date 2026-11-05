<?php
require_once __DIR__ . '/../includes/admin.php';
require_admin();

// ตัวเลข กราฟ และตารางทั้งหมดในหน้านี้ app.js ดึงจาก api/report.php (JSON) มาใส่
$pageTitle  = 'รายงานยอดขาย';
$activeMenu = 'dashboard';
$backUrl    = 'index.php';
$useChart   = true;
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="segmented" role="group" aria-label="ช่วงเวลาของรายงาน">
    <button type="button" data-range="7d" aria-pressed="true">7 วัน</button>
    <button type="button" data-range="30d" aria-pressed="false">30 วัน</button>
    <button type="button" data-range="12m" aria-pressed="false">12 เดือน</button>
</div>

<!-- กรอกช่วงวันที่เอง : app.js ส่ง from / to ไปที่ api/report.php แล้ววาดรายงานใหม่ (เซิร์ฟเวอร์ตรวจช่วงซ้ำ) -->
<form class="range-form" id="rangeForm" aria-label="กำหนดช่วงวันที่เอง">
    <div class="field">
        <label for="rangeFrom">ตั้งแต่วันที่</label>
        <input type="date" id="rangeFrom" name="from" value="<?= date('Y-m-d', strtotime('-6 days')) ?>" max="<?= date('Y-m-d') ?>" required>
    </div>
    <div class="field">
        <label for="rangeTo">ถึงวันที่</label>
        <input type="date" id="rangeTo" name="to" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
    </div>
    <button class="button button-outline" type="submit">ดูรายงาน</button>
    <p class="field-error" id="rangeError" hidden></p>
</form>

<section class="card summary-row" aria-label="สรุปยอด">
    <div><span class="muted">ยอดขาย</span><strong id="sumSales">–</strong></div>
    <div><span class="muted">ออเดอร์</span><strong id="sumOrders">–</strong></div>
    <div><span class="muted">เฉลี่ยต่อออเดอร์</span><strong id="sumAverage">–</strong></div>
</section>

<section class="card">
    <div class="card-head">
        <h2 id="seriesTitle">ยอดขายรายวัน (บาท)</h2>
        <button type="button" class="link-button" id="toggleTable">ดูเป็นตาราง</button>
    </div>
    <div class="chart-box" id="chartBox">
        <canvas id="salesChart" data-range="7d" role="img" aria-label="กราฟยอดขาย"></canvas>
    </div>
    <div class="table-wrap" id="tableBox" hidden>
        <table class="admin-table">
            <thead>
                <tr><th id="tableUnit">วัน</th><th class="num">ออเดอร์</th><th class="num">ยอดขาย</th></tr>
            </thead>
            <tbody id="salesRows"></tbody>
        </table>
    </div>
</section>

<div class="report-grid">
    <section class="card">
        <h2>สถานะออเดอร์</h2>
        <ul class="bar-list" id="statusBars"></ul>
    </section>
    <section class="card">
        <h2>สินค้าขายดี</h2>
        <ul class="bar-list" id="topProducts"></ul>
    </section>
</div>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
