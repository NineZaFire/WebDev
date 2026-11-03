// สคริปต์ที่ทุกหน้าใช้ร่วมกัน (โหลดท้าย body จึงหา element ได้ทันที)
// แต่ละส่วนทำงานเฉพาะหน้าที่มี element ของมัน

// ---------- ตัวช่วยที่ใช้หลายหน้า ----------

// ส่งคำสั่งไป api/cart.php ด้วย XMLHttpRequest แล้วแปลง responseText (JSON) ส่งให้ onDone
function sendCart(formData, onDone) {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', '../api/cart.php');
    xhr.onload = () => {
        const data = JSON.parse(xhr.responseText);
        updateCartBadge(data.count);
        onDone(data);
    };
    xhr.onerror = () => alert('เชื่อมต่อเซิร์ฟเวอร์ไม่ได้ ลองใหม่อีกครั้ง');
    xhr.send(formData);
}

// ตัวเลขจำนวนสินค้าบนไอคอนตะกร้า (0 = ซ่อน)
function updateCartBadge(count) {
    const badge = document.getElementById('cartCount');
    badge.textContent = count;
    badge.hidden = count === 0;
}

// แถบข้อความด้านล่างจอ แสดง 3 วินาทีแล้วซ่อน : สำเร็จ = สีเข้ม, ไม่สำเร็จ = สีแดง
function showToast(message, ok) {
    const toast = document.getElementById('toast');
    document.getElementById('toastText').textContent = message;
    toast.style.background = ok ? '' : 'var(--danger-700)';
    toast.hidden = false;

    clearTimeout(toast.timer);
    toast.timer = setTimeout(() => { toast.hidden = true; }, 3000);
}

// ปุ่ม − / + ข้างช่องตัวเลข (หน้าสินค้า และฟอร์มสต็อกของแอดมิน) : ปุ่มบอกค่าที่จะบวกไว้ใน data-step
// ตัวเลขไม่เกิน min/max ของช่อง และแจ้ง event "input" ให้ส่วนอื่นรู้ว่าค่าเปลี่ยน
document.addEventListener('click', (event) => {
    const button = event.target.closest('.stepper [data-step]');
    if (!button) {
        return;
    }
    const input = button.parentElement.querySelector('input');
    const value = Number(input.value) + Number(button.dataset.step);
    input.value = Math.min(Math.max(value, Number(input.min)), Number(input.max));
    input.dispatchEvent(new Event('input', { bubbles: true }));
});

// ฟอร์มที่มี data-confirm (เช่น ลบสินค้า) : ถามยืนยันก่อนส่ง
document.addEventListener('submit', (event) => {
    const message = event.target.dataset.confirm;
    if (message && !confirm(message)) {
        event.preventDefault();
    }
});

// แอดมินตรวจสลิป : กดปฏิเสธแล้วถามเหตุผล (ลูกค้าจะเห็นข้อความนี้) กดยกเลิก = ไม่ส่งฟอร์ม
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!form.matches('.slip-actions') || !event.submitter || event.submitter.value !== 'reject') {
        return;
    }
    const reason = prompt('เหตุผลที่ปฏิเสธ (ลูกค้าจะเห็นข้อความนี้)', 'ยอดในสลิปไม่ตรง');
    if (reason === null) {
        event.preventDefault();
        return;
    }
    form.elements.reason.value = reason;
});

// สวิตช์ที่มี data-autosubmit (เช่น เปิด/ปิดโปรโมชั่น) : เปลี่ยนแล้วส่งฟอร์มทันที ไม่ต้องกดปุ่มบันทึก
document.addEventListener('change', (event) => {
    if (event.target.matches('[data-autosubmit]')) {
        event.target.form.submit();
    }
});

// ---------- รหัสผ่านใหม่ : ตรวจทันทีที่พิมพ์ (หน้าสมัครสมาชิก และหน้าเปลี่ยนรหัสผ่าน) ----------
const passwordConfirm = document.getElementById('passwordConfirm');

if (passwordConfirm) {
    const password = document.getElementById('password');
    const passwordHint = document.getElementById('passwordHint');
    const confirmError = document.getElementById('confirmError');

    function checkPassword() {
        // ยาวครบ 8 ตัว : ข้อความใต้ช่องเปลี่ยนเป็นสีเขียว
        passwordHint.style.color = password.value.length >= 8 ? 'var(--success-700)' : '';

        // สองช่องไม่ตรงกัน : กรอบเป็นสีแดง แสดงข้อความ และไม่ให้ส่งฟอร์ม
        const mismatch = passwordConfirm.value !== '' && passwordConfirm.value !== password.value;
        passwordConfirm.style.borderColor = mismatch ? 'var(--danger-700)' : '';
        confirmError.hidden = !mismatch;
        passwordConfirm.setCustomValidity(mismatch ? 'รหัสผ่านทั้งสองช่องไม่ตรงกัน' : '');
    }

    password.addEventListener('input', checkPassword);
    passwordConfirm.addEventListener('input', checkPassword);
}

// ---------- หน้าแรก : ตัวกรองสินค้า ----------
// เปลี่ยนตัวเลือกในฟอร์ม → ขอรายการใหม่จาก api/products.php (fetch) แล้วใส่แทนรายการเดิม ไม่ต้องโหลดหน้าใหม่
const filterForm = document.getElementById('filterForm');

if (filterForm) {
    // มือถือ : เปิด/ปิดแผ่นตัวกรอง (จอใหญ่ตัวกรองแสดงเป็นแถบซ้ายอยู่แล้ว)
    function showSheet(open) {
        document.getElementById('filterSheet').classList.toggle('open', open);
        document.getElementById('sheetBackdrop').hidden = !open;
    }

    document.getElementById('filterOpen').addEventListener('click', () => showSheet(true));
    document.getElementById('filterClose').addEventListener('click', () => showSheet(false));
    document.getElementById('sheetBackdrop').addEventListener('click', () => showSheet(false));

    let latestRequest = 0;
    filterForm.addEventListener('change', () => {
        // ค่าว่าง (ทั้งหมด, ทุกราคา) ไม่ต้องใส่ใน URL
        const params = new URLSearchParams();
        for (const [name, value] of new FormData(filterForm)) {
            if (value !== '') {
                params.append(name, value);
            }
        }

        // กดหลายตัวเลือกติดกัน : ใช้เฉพาะคำตอบของคำขอล่าสุด คำตอบเก่าที่มาช้ากว่าไม่ต้องแสดง
        const request = ++latestRequest;
        fetch('../api/products.php?' + params)
            .then((response) => response.json())
            .then((data) => {
                if (request !== latestRequest) {
                    return;
                }
                document.getElementById('productGrid').innerHTML = data.html;
                document.getElementById('resultTitle').textContent = data.title;
                document.getElementById('resultCount').textContent = data.count + ' รายการ';
                document.getElementById('showResults').textContent = 'แสดง ' + data.count + ' รายการ';
                history.replaceState(null, '', '?' + params);   // รีเฟรชหรือส่งลิงก์ต่อ ได้ตัวกรองเดิม
            });
    });

    // ปุ่ม "แสดง N รายการ" : รายการอัปเดตไปแล้ว แค่ปิดแผ่นตัวกรอง (ไม่มี JS = ส่งฟอร์มโหลดหน้าใหม่ตามปกติ)
    filterForm.addEventListener('submit', (event) => {
        event.preventDefault();
        showSheet(false);
    });
}

// ---------- หน้าสินค้า ----------
const addToCartForm = document.getElementById('addToCartForm');

if (addToCartForm) {
    const qty = document.getElementById('qty');

    // เลือกไซซ์ : แสดงจำนวนคงเหลือของไซซ์นั้น และสั่งได้ไม่เกินจำนวนนั้น
    addToCartForm.addEventListener('change', (event) => {
        if (event.target.name !== 'variant_id') {
            return;
        }
        const stock = Number(event.target.dataset.stock);
        document.getElementById('stockText').textContent = 'เหลือ ' + stock + ' ชิ้น';
        qty.max = stock;
        qty.value = Math.min(qty.value, stock);
    });

    // เพิ่มลงตะกร้าแบบ Ajax ไม่ต้องโหลดหน้าใหม่
    addToCartForm.addEventListener('submit', (event) => {
        event.preventDefault();
        sendCart(new FormData(addToCartForm), (data) => showToast(data.message, data.ok));
    });
}

// รูปย่อใต้รูปสินค้า : กดแล้วเปลี่ยนรูปใหญ่
const galleryThumbs = document.getElementById('galleryThumbs');

if (galleryThumbs) {
    galleryThumbs.addEventListener('click', (event) => {
        const button = event.target.closest('.thumb-button');
        if (!button) {
            return;
        }
        document.getElementById('mainImage').src = button.querySelector('img').src;
        galleryThumbs.querySelector('[aria-current]').removeAttribute('aria-current');
        button.setAttribute('aria-current', 'true');
    });
}

// ---------- หน้าชำระเงิน : วาด QR PromptPay เมื่อโหลดหน้าเสร็จ (window load event) ----------
// สร้าง <svg> จากข้อความที่ PHP เตรียมไว้ใน data-payload แล้วใส่แทนไอคอนในกล่อง
window.addEventListener('load', () => {
    const qrBox = document.getElementById('qrBox');
    if (!qrBox || !window.qrcode) {
        return;
    }
    const qr = qrcode(0, 'M');   // 0 = เลือกขนาดเองตามความยาวข้อความ, M = แก้ข้อผิดพลาดได้ราว 15%
    qr.addData(qrBox.dataset.payload);
    qr.make();
    qrBox.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 0, scalable: true });
});

// แสดงชื่อไฟล์สลิปที่เลือก
const slipInput = document.getElementById('slipInput');

if (slipInput) {
    slipInput.addEventListener('change', () => {
        const file = slipInput.files[0];
        document.getElementById('slipName').textContent = file ? 'แนบแล้ว: ' + file.name : 'แตะเพื่อแนบสลิปการโอน';
    });
}

// ---------- หน้าตะกร้า ----------
// ปุ่มและฟอร์มในตะกร้าถูกสร้างใหม่ทุกครั้งที่อัปเดต จึงดักเหตุการณ์ที่กล่องนอกสุดกล่องเดียว
const cartContent = document.getElementById('cartContent');

if (cartContent) {
    const showCart = (data) => { cartContent.innerHTML = data.html; };

    // ปุ่มเพิ่ม/ลดจำนวน และปุ่มลบ : บอกคำสั่งไว้ใน data-action, data-variant, data-qty
    cartContent.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action]');
        if (!button) {
            return;
        }
        const data = new FormData();
        data.append('action', button.dataset.action);
        data.append('variant_id', button.dataset.variant);
        data.append('qty', button.dataset.qty || 0);
        sendCart(data, showCart);
    });

    // ฟอร์มคูปอง
    cartContent.addEventListener('submit', (event) => {
        event.preventDefault();
        sendCart(new FormData(event.target), showCart);
    });
}

// ---------- แอดมิน : ฟอร์มเพิ่ม/แก้ไขสินค้า ----------
const productForm = document.getElementById('productForm');

if (productForm) {
    const lowStock = Number(productForm.dataset.lowStock);
    const stockInputs = productForm.querySelectorAll('input[data-size]');

    // รวมสต็อกทุกไซซ์ และป้าย "หมด" / "ใกล้หมด" ของแต่ละไซซ์ เปลี่ยนทันทีที่ตัวเลขเปลี่ยน
    function updateStock() {
        let total = 0;
        stockInputs.forEach((input) => {
            const stock = Number(input.value);
            const badge = document.getElementById('stockBadge-' + input.dataset.size);
            total += stock;
            badge.hidden = stock > lowStock;
            badge.textContent = stock === 0 ? 'หมด' : 'ใกล้หมด';
            badge.className = 'tag ' + (stock === 0 ? 'tag-danger' : 'tag-warning');
        });
        document.getElementById('stockTotal').textContent = 'รวม ' + total + ' ตัว';
    }

    stockInputs.forEach((input) => input.addEventListener('input', updateStock));

    // เลือกรูปใหม่ : สร้างรูปตัวอย่างต่อจากรูปเดิมก่อนกดบันทึก
    document.getElementById('imageInput').addEventListener('change', (event) => {
        const grid = document.getElementById('imageGrid');
        grid.querySelectorAll('.image-new').forEach((old) => old.remove());

        for (const file of event.target.files) {
            const preview = document.createElement('div');
            preview.className = 'image-item image-new';
            preview.innerHTML = '<img alt=""><span>ใหม่</span>';
            preview.querySelector('img').src = URL.createObjectURL(file);
            grid.insertBefore(preview, document.getElementById('imageAdd'));
        }
    });
}

// ---------- แอดมิน : นำเข้าสินค้าจาก JSON ----------
// เลือกไฟล์ → อ่านด้วย FileReader → แปลงด้วย JSON.parse → สร้างแถวในตารางให้ตรวจก่อนกดนำเข้า
// (เป็นแค่ตัวอย่าง เซิร์ฟเวอร์ตรวจทุกช่องอีกครั้งตอนบันทึก)
const importFile = document.getElementById('importFile');

if (importFile) {
    const sizes = ['S', 'M', 'L', 'XL'];
    const submit = document.getElementById('importSubmit');

    function showImportError(message) {
        const error = document.getElementById('importError');
        error.textContent = message;
        error.hidden = false;
        submit.disabled = true;
    }

    importFile.addEventListener('change', () => {
        const rows = document.getElementById('importRows');
        rows.innerHTML = '';
        document.getElementById('importPreview').hidden = true;
        document.getElementById('importError').hidden = true;
        submit.disabled = false;
        submit.textContent = 'นำเข้าสินค้า';

        const file = importFile.files[0];
        if (!file) {
            return;
        }
        const reader = new FileReader();
        reader.onload = () => {
            let products;
            try {
                products = JSON.parse(reader.result);
            } catch (error) {
                showImportError('ไฟล์นี้ไม่ใช่ JSON ที่ถูกต้อง');
                return;
            }
            if (!Array.isArray(products) || products.length === 0) {
                showImportError('ไฟล์ต้องเป็นอาร์เรย์ของสินค้าอย่างน้อย 1 รายการ');
                return;
            }

            // 1 สินค้า = 1 แถว : ลำดับ ชื่อ หมวด ราคา สต็อกแต่ละไซซ์ และสต็อกรวม (ไซซ์ที่ไม่ใส่ = 0)
            products.forEach((product, i) => {
                const p = product || {};
                const stock = sizes.map((size) => Number((p.stock || {})[size] || 0));
                const cells = [i + 1, p.name, p.category, p.price, ...stock, stock.reduce((sum, n) => sum + n, 0)];
                const row = document.createElement('tr');
                cells.forEach((value, column) => {
                    const cell = document.createElement('td');
                    cell.textContent = value ?? '–';   // textContent : ข้อความในไฟล์ไม่ถูกตีความเป็น HTML
                    cell.className = column >= 3 ? 'num' : '';
                    row.appendChild(cell);
                });
                rows.appendChild(row);
            });
            document.getElementById('importSummary').textContent = 'ข้อมูลในไฟล์ ' + products.length + ' รายการ';
            submit.textContent = 'นำเข้า ' + products.length + ' รายการ';
            document.getElementById('importPreview').hidden = false;
        };
        reader.readAsText(file);
    });
}

// ---------- แอดมิน : สร้าง/แก้ไขโปรโมชั่น ----------
const promotionForm = document.getElementById('promotionForm');

if (promotionForm) {
    // เลือกประเภท : แสดงเฉพาะกล่องเงื่อนไขของประเภทนั้น
    // กล่องที่ซ่อนถูก disabled ด้วย เบราว์เซอร์จะไม่ตรวจและไม่ส่งค่าในกล่องนั้น
    promotionForm.addEventListener('change', (event) => {
        if (event.target.name !== 'type') {
            return;
        }
        promotionForm.querySelectorAll('[data-types]').forEach((box) => {
            const show = box.dataset.types.split(' ').includes(event.target.value);
            box.hidden = !show;
            box.disabled = !show;
        });
    });

    // เพิ่มขั้นส่วนลด : คัดลอกแถวแรกแล้วล้างตัวเลข
    const tierRows = document.getElementById('tierRows');
    document.getElementById('addTier').addEventListener('click', () => {
        const row = tierRows.firstElementChild.cloneNode(true);
        row.querySelectorAll('input').forEach((input) => { input.value = ''; });
        tierRows.appendChild(row);
    });

    // ลบขั้นส่วนลด (ต้องเหลืออย่างน้อย 1 ขั้น) แล้วคิดตัวอย่างใหม่
    tierRows.addEventListener('click', (event) => {
        const button = event.target.closest('.remove-tier');
        if (button && tierRows.children.length > 1) {
            button.closest('.tier-row').remove();
            updatePreviewLater();
        }
    });

    // ตัวอย่างราคา : ส่งค่าในฟอร์มไปคิดที่ api/promotion_preview.php (fetch) แล้วแสดงคำตอบ (JSON) ในกล่องตัวอย่าง
    function showPreview(data) {
        document.getElementById('previewText').textContent = data.text;
        document.getElementById('previewPrice').hidden = !data.ok;   // คิดไม่ได้ = ซ่อนบรรทัดราคา เหลือแต่ข้อความบอกว่าต้องแก้อะไร
        if (data.ok) {
            document.getElementById('previewPay').textContent = data.pay;
            document.getElementById('previewSave').textContent = data.save;
        }
        document.getElementById('previewCard').hidden = false;
    }

    function updatePreview() {
        fetch('../api/promotion_preview.php', { method: 'POST', body: new FormData(promotionForm) })
            .then((response) => response.json())
            .then(showPreview)
            .catch(() => showPreview({ ok: false, text: 'คำนวณตัวอย่างไม่สำเร็จ' }));
    }

    // แก้ค่าต่อเนื่อง : รอให้หยุดแก้ 0.3 วินาทีค่อยส่ง จะได้ไม่ส่งทุกครั้งที่พิมพ์ตัวอักษร
    let previewTimer;
    function updatePreviewLater() {
        clearTimeout(previewTimer);
        previewTimer = setTimeout(updatePreview, 300);
    }

    promotionForm.addEventListener('input', updatePreviewLater);
    window.addEventListener('load', updatePreview);   // เปิดหน้าเสร็จ แสดงตัวอย่างของค่าที่มีอยู่ทันที
}

// ---------- แอดมิน : แดชบอร์ดและรายงานยอดขาย ----------
// ดึงข้อมูล JSON จาก api/report.php แล้ววาดกราฟ (Chart.js) สร้างตาราง และแถบสถานะ
const salesChart = document.getElementById('salesChart');

if (salesChart) {
    const css = getComputedStyle(document.documentElement);
    const color = (name) => css.getPropertyValue(name).trim();
    // เหมือน baht() ใน PHP : ฿290 หรือ ฿1,011.50 (แสดงสตางค์เฉพาะเมื่อมี)
    const money = (n) => '฿' + n.toLocaleString('th-TH', { minimumFractionDigits: Number.isInteger(n) ? 0 : 2, maximumFractionDigits: 2 });
    let chart = null;

    Chart.defaults.font.family = '"IBM Plex Sans Thai", system-ui, sans-serif';
    Chart.defaults.color = color('--neutral-600');

    // ใส่ข้อความถ้าหน้านั้นมี element นี้ (แดชบอร์ดมีบางช่อง หน้ารายงานมีครบ)
    function setText(id, text) {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = text;
        }
    }

    // query : "range=7d" (ปุ่มเลือกช่วง) หรือ "from=...&to=..." (ช่วงที่กรอกเอง)
    function loadReport(query) {
        fetch('../api/report.php?' + query)
            .then((response) => response.json())
            .then((data) => {
                // ช่วงที่กรอกไม่ผ่านการตรวจของเซิร์ฟเวอร์ : บอกเหตุผลใต้ฟอร์ม รายงานเดิมยังแสดงอยู่
                showRangeError(data.error || '');
                if (!data.error) {
                    showReport(data);
                }
            })
            .catch(() => setText('sumSales', 'โหลดข้อมูลไม่สำเร็จ'));
    }

    function showRangeError(message) {
        const error = document.getElementById('rangeError');
        if (error) {
            error.textContent = message;
            error.hidden = !message;
        }
    }

    function showReport(data) {
        setText('sumSales', money(data.summary.sales));
        setText('sumOrders', data.summary.orders.toLocaleString('th-TH'));
        setText('sumAverage', money(data.summary.average));
        setText('seriesTitle', 'ยอดขายราย' + data.unit + ' (บาท)');
        setText('tableUnit', data.unit);

        drawChart(data.series);
        fillTable(data.series);

        const allOrders = data.statuses.reduce((sum, status) => sum + status.count, 0);
        fillBars('statusBars', allOrders, data.statuses.map((status) => ({
            label: status.label,
            text: status.count + ' ออเดอร์',
            value: status.count,
            className: 'bar-' + status.key,
        })));
        fillBars('topProducts', data.top.length ? data.top[0].qty : 0, data.top.map((product, i) => ({
            label: (i + 1) + '. ' + product.name,
            text: product.qty + ' ตัว',
            value: product.qty,
        })));
    }

    // ตัวเลขบนหัวแท่ง (ปัดเป็นบาทเต็ม ตัวเลขละเอียดดูได้ที่ tooltip และตาราง)
    // แสดงเมื่อมีไม่เกิน 12 แท่ง เพราะ 30 แท่งตัวเลขจะทับกัน
    const barValues = {
        id: 'barValues',
        afterDatasetsDraw(c) {
            const bars = c.getDatasetMeta(0).data;
            if (bars.length > 12) {
                return;
            }
            c.ctx.save();
            c.ctx.font = '12px ' + Chart.defaults.font.family;
            c.ctx.fillStyle = color('--neutral-600');
            c.ctx.textAlign = 'center';
            bars.forEach((bar, i) => {
                const value = c.data.datasets[0].data[i];
                if (value > 0) {
                    c.ctx.fillText(Math.round(value).toLocaleString('th-TH'), bar.x, bar.y - 6);
                }
            });
            c.ctx.restore();
        },
    };

    function drawChart(series) {
        if (chart) {
            chart.destroy();
        }
        chart = new Chart(salesChart, {
            type: 'bar',
            data: {
                labels: series.map((point) => point.label),
                datasets: [{
                    data: series.map((point) => point.sales),
                    // แท่งสุดท้าย (วันนี้ / เดือนนี้) สีเข้มกว่าแท่งอื่น
                    backgroundColor: series.map((point, i) => color(i === series.length - 1 ? '--primary-900' : '--primary-600')),
                    borderRadius: 6,
                    maxBarThickness: 44,
                }],
            },
            options: {
                maintainAspectRatio: false,
                layout: { padding: { top: 20, right: 12 } },
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: (item) => money(item.parsed.y) } },
                },
                scales: {
                    x: { grid: { display: false }, ticks: { maxRotation: 0 } },   // ไม่เอียงชื่อวัน แคบไปก็ข้ามบางช่องแทน
                    y: { beginAtZero: true, ticks: { callback: (value) => value.toLocaleString('th-TH') } },
                },
            },
            plugins: [barValues],
        });
    }

    // ตารางรายวัน/รายเดือน : สร้างแถวใหม่ทุกครั้งที่เปลี่ยนช่วงเวลา
    function fillTable(series) {
        const rows = document.getElementById('salesRows');
        if (!rows) {
            return;
        }
        rows.innerHTML = '';
        series.forEach((point) => {
            const tr = document.createElement('tr');
            [point.label, point.orders.toLocaleString('th-TH'), money(point.sales)].forEach((text, i) => {
                const td = document.createElement('td');
                td.textContent = text;
                td.className = i > 0 ? 'num' : '';
                tr.appendChild(td);
            });
            rows.appendChild(tr);
        });
    }

    // แถบแนวนอน : ความยาว = ค่า ÷ ค่าเต็ม
    function fillBars(id, full, items) {
        const list = document.getElementById(id);
        if (!list) {
            return;
        }
        list.innerHTML = items.length ? '' : '<li class="muted">ยังไม่มีข้อมูลในช่วงนี้</li>';
        items.forEach((item) => {
            const li = document.createElement('li');
            li.className = item.className || '';
            li.innerHTML = '<div class="bar-label"><span></span><strong></strong></div><div class="bar"><span></span></div>';
            li.querySelector('.bar-label span').textContent = item.label;
            li.querySelector('.bar-label strong').textContent = item.text;
            li.querySelector('.bar span').style.width = (full ? item.value / full * 100 : 0) + '%';
            list.appendChild(li);
        });
    }

    // ปุ่มเลือกช่วงเวลา (หน้ารายงาน)
    const rangeButtons = document.querySelectorAll('button[data-range]');
    rangeButtons.forEach((button) => {
        button.addEventListener('click', () => {
            rangeButtons.forEach((other) => other.setAttribute('aria-pressed', other === button));
            loadReport('range=' + button.dataset.range);
        });
    });

    // กรอกช่วงวันที่เอง (หน้ารายงาน) : วันสิ้นสุดก่อนวันเริ่ม = ช่องไม่ผ่าน เบราว์เซอร์ไม่ยอมส่งฟอร์ม
    const rangeForm = document.getElementById('rangeForm');
    if (rangeForm) {
        const rangeFrom = document.getElementById('rangeFrom');
        const rangeTo = document.getElementById('rangeTo');
        rangeForm.addEventListener('input', () => {
            const reversed = rangeFrom.value && rangeTo.value && rangeTo.value < rangeFrom.value;   // "YYYY-MM-DD" เทียบเป็นข้อความได้
            rangeTo.setCustomValidity(reversed ? 'วันสิ้นสุดต้องไม่ก่อนวันเริ่ม' : '');
        });
        rangeForm.addEventListener('submit', (event) => {
            event.preventDefault();
            rangeButtons.forEach((button) => button.setAttribute('aria-pressed', 'false'));
            loadReport(new URLSearchParams(new FormData(rangeForm)));
        });
    }

    // สลับดูกราฟ / ตาราง
    const toggleTable = document.getElementById('toggleTable');
    if (toggleTable) {
        toggleTable.addEventListener('click', () => {
            const tableBox = document.getElementById('tableBox');
            tableBox.hidden = !tableBox.hidden;
            document.getElementById('chartBox').hidden = !tableBox.hidden;
            toggleTable.textContent = tableBox.hidden ? 'ดูเป็นตาราง' : 'ดูเป็นกราฟ';
        });
    }

    loadReport('range=' + salesChart.dataset.range);
}
