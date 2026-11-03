# เช็กลิสต์ Rubric : LMShirtShop (040613411 Web Development)

ร้านขายเสื้อออนไลน์ เขียนด้วย PHP 8.2 + MySQL 8 (PDO) รันบน Docker หรือ XAMPP
ไฟล์นี้บอกว่า rubric แต่ละข้อดูได้ที่หน้าไหน และโค้ดอยู่ตรงไหน (กดลิงก์ใน VS Code เพื่อเปิดไฟล์ที่บรรทัดนั้น)
ลำดับการเดโมทีละขั้นตอนอยู่ใน [DEMO.md](DEMO.md)

| ข้อ | หัวข้อ | Basic | Bonus | Big Bonus |
|---|---|:-:|:-:|:-:|
| 1 | Wireframe และ System Architecture | ✓ | – | – |
| 2 | CSS Selector | ✓ | ✓ | ✓ |
| 3 | Front-End Validation | ✓ | ✓ | ✓ |
| 4 | Semantics Layout | ✓ | ✓ | ✓ |
| 5 | Mobile First | ✓ | ✓ | ✓ |
| 6 | DOM ด้วย getElementById() | ✓ | ✓ | ✓ |
| 7 | ข้อมูล JSON | ✓ | ✓ | ✓ |
| 8 | ฐานข้อมูล CRUD | ✓ | ✓ | ✓ |
| 9 | Cookies และ Session | ✓ | ✓ | ✓ |
| 10 | Ajax | ✓ | ✓ | ✓ |
| 11 | รายงานสรุปเชิงสถิติ | ✓ | ✓ | ✓ |

**บัญชีทดลอง** แอดมิน `admin@shirtshop.test` / `admin123` · ลูกค้า `somchai@test.com` / `password123`

**ก่อนนำเสนอ** import [shirtshop_xampp.sql](shirtshop_xampp.sql) ใหม่ในวันนั้น ข้อมูลออเดอร์ โปรโมชั่น และคูปองจะเลื่อนวันที่ให้ "วันนี้" ตรงกับวันที่ import (แดชบอร์ดและรายงานจะมีข้อมูลล่าสุดเสมอ)

---

## 1. Wireframe และ System Architecture

- Wireframe 21 หน้าจอ (มือถือ 390px และเดสก์ท็อป 1440px) : [wireframe.pdf](wireframe.pdf)
- สถาปัตยกรรม 3 ชั้น, Docker 2 container + 2 volume, โครงสร้างโฟลเดอร์, เส้นทาง checkout ใน transaction : `System Architecture — ร้านขายเสื้อออนไลน์.pdf`
- โค้ดจริงตรงกับเอกสาร : [docker-compose.yml](docker-compose.yml), [Dockerfile](Dockerfile), ค่าเชื่อมต่อจาก environment variable ใน [includes/connect.php](includes/connect.php)
- ER diagram (Chen notation) : [LMshirtER.jpg](LMshirtER.jpg) ตรงกับ [shirtshop_xampp.sql](shirtshop_xampp.sql) ทั้ง 13 ตาราง
  - ไฟล์ต้นฉบับแก้ไขได้ [LMshirtER.drawio](LMshirtER.drawio) เปิดด้วย draw.io (diagrams.net) แล้ว File > Export as > JPEG
  - foreign key แสดงเป็นเส้นความสัมพันธ์ · ตาราง promotion_bundle_items คือความสัมพันธ์ M:N "bundles" ที่มี attribute qty

## 2. CSS Selector — [assets/style.css](assets/style.css)

**Basic**

| ชนิด | ตัวอย่าง |
|---|---|
| Type | `body` [style.css:79](assets/style.css#L79) |
| Universal | `*, *::before, *::after` [style.css:73](assets/style.css#L73) |
| Class | `.card` [style.css:344](assets/style.css#L344) |
| ID | `#toast` แถบแจ้งผลเพิ่มลงตะกร้า (มีอันเดียวในหน้าสินค้า) [style.css:848](assets/style.css#L848) |
| Pseudo-class | `:focus-visible` [style.css:993](assets/style.css#L993), `:checked`, `:disabled`, `:last-of-type` [style.css:2708](assets/style.css#L2708) |
| Pseudo-element | `::before` ใส่เครื่องหมาย ✓ ให้ชิปที่เลือก [style.css:1286](assets/style.css#L1286) |
| Attribute | `[hidden]` [style.css:93](assets/style.css#L93), `a[aria-current="page"]` [style.css:303](assets/style.css#L303), `input[type="file"]` [style.css:1612](assets/style.css#L1612) |

**Bonus (Combination)** Descendant `.card h2` [style.css:1897](assets/style.css#L1897) · Child `.field > label` [style.css:415](assets/style.css#L415) · Adjacent sibling `.link-list a + a` [style.css:1231](assets/style.css#L1231) และ `input:checked + span`

**Big Bonus (Specificity Weight)** คำอธิบายพร้อมตัวอย่างจริงในไฟล์อยู่ต้นไฟล์ [style.css:6](assets/style.css#L6)
- `.category-chips .chip-option input:checked + span` (0,3,2) ชนะ `.chip-option input:checked + span` (0,2,2) [style.css:2688](assets/style.css#L2688) : หมวดหมู่บนจอใหญ่เป็นพื้นฟ้าอ่อนแทนพื้นน้ำเงิน
- `[hidden]` (0,1,0) ต้องใส่ `!important` เพราะ `#toast { display: flex }` (1,0,0) ชนะ ถ้าไม่ใส่ แถบแจ้งเตือนจะซ่อนไม่ได้
- สีพื้นของ `#toast` ตอน error ใส่ผ่าน style ของ element ใน JS ซึ่งชนะ selector ทุกตัว

## 3. Front-End Validation

- **Basic (Built-in)** สมัครสมาชิก : `required`, `type="email"` [register.php:91](public/register.php#L91), `minlength="8"` [register.php:106](public/register.php#L106) · เข้าสู่ระบบ [login.php:57](public/login.php#L57) · ราคาสินค้า `min`/`max` [product_edit.php:124](admin/product_edit.php#L124) · ฟอร์มโปรโมชั่น [promotion_edit.php:90](admin/promotion_edit.php#L90) · วันที่ในรายงาน `max` = วันนี้ [report.php:26](admin/report.php#L26)
- **Bonus (Regular Expression ผ่าน pattern)** เบอร์โทร `pattern="0[0-9]{9}"` [register.php:99](public/register.php#L99) · รหัสไปรษณีย์ `pattern="\d{5}"` [address.php:67](public/address.php#L67)
- **Big Bonus (JS)** รหัสผ่านสองช่องต้องตรงกัน (`setCustomValidity` กรอบแดง ข้อความแจ้ง) [app.js:95](assets/app.js#L95) ใช้ทั้งหน้าสมัครสมาชิกและหน้าเปลี่ยนรหัสผ่าน [password.php](public/password.php) · วันสิ้นสุดของรายงานต้องไม่ก่อนวันเริ่ม [app.js:580](assets/app.js#L580)
- ฝั่งเซิร์ฟเวอร์ตรวจซ้ำทุกฟอร์ม เช่น [includes/promotion_form.php:12](includes/promotion_form.php#L12) เพราะค่าจากเบราว์เซอร์แก้ไขได้

## 4. Semantics Layout

- **Basic / Bonus (ครบทุกหน้า)** ทุกหน้าใช้โครงเดียวกันจาก include
  - ลูกค้า : `<header>` [header.php:48](includes/header.php#L48), `<nav>` [header.php:62](includes/header.php#L62), `<main>` [header.php:74](includes/header.php#L74), `<footer>` [footer.php:13](includes/footer.php#L13), tab bar `<nav>` [footer.php:18](includes/footer.php#L18)
  - แอดมิน : `<aside>` [admin_header.php:45](includes/admin_header.php#L45), `<header>` [admin_header.php:64](includes/admin_header.php#L64), `<main>` [admin_header.php:74](includes/admin_header.php#L74)
  - เข้าสู่ระบบ/สมัครสมาชิก : `<header class="auth-head">` [login.php:45](public/login.php#L45)
  - เนื้อหาแบ่งด้วย `<section>` และ `<article>` เช่น [orders.php:75](public/orders.php#L75), [index.php:122](public/index.php#L122)
- **Big Bonus (Flexbox / Grid)** ตารางสินค้า `display: grid` 2 → 3 → 4 คอลัมน์ [style.css:545](assets/style.css#L545) · หน้าแรกจอใหญ่ใช้ `grid-template-areas` วางแบนเนอร์ แถบตัวกรอง และรายการสินค้า [style.css:2614](assets/style.css#L2614)

## 5. Mobile First

- **Basic / Bonus (ครบทุกหน้า)** สไตล์ตั้งต้นเขียนสำหรับจอมือถือ แล้วขยายด้วย `min-width: 640px` และ `1024px` [style.css:2](assets/style.css#L2) ทุกหน้าใช้ได้ทั้ง 390px และ 1440px
- **Big Bonus (ซ่อน/แสดง element ตามขนาดจอ)** `.desktop-only` / `.mobile-only` [style.css:144](assets/style.css#L144)
  - มือถือมี tab bar ด้านล่าง จอใหญ่มีเมนูบน header และ footer
  - หน้าแรก : มือถือเป็นแถวชิปหมวดหมู่ + แผ่นตัวกรองเลื่อนขึ้น จอใหญ่เป็นแถบตัวกรองด้านซ้าย
  - แอดมิน : มือถือเป็นแถบหัวสีน้ำเงินเข้ม จอใหญ่เป็นเมนูด้านซ้าย

## 6. จัดการ DOM ด้วย getElementById() — [assets/app.js](assets/app.js)

- **Basic (เปลี่ยน Style ไม่เกี่ยวกับ Ajax)** หน้าสมัครสมาชิก : ข้อความใต้ช่องรหัสผ่านเป็นสีเขียวเมื่อครบ 8 ตัว [app.js:89](assets/app.js#L89) · กรอบช่องยืนยันรหัสผ่านเป็นสีแดงเมื่อไม่ตรง [app.js:93](assets/app.js#L93)
- **Bonus (เพิ่ม Element ด้วย window load event)** หน้าชำระเงิน : เมื่อหน้าโหลดเสร็จ สร้าง QR PromptPay เป็น `<svg>` ใส่ในกล่อง `getElementById('qrBox')` [app.js:191](assets/app.js#L191) / [app.js:199](assets/app.js#L199)
- **Big Bonus (เพิ่ม/ซ่อน Element จากข้อมูลในฟอร์ม)**
  - นำเข้าสินค้า : เลือกไฟล์แล้วสร้างแถวในตารางตัวอย่าง [app.js:331](assets/app.js#L331)
  - แก้ไขสินค้า : เลือกรูปแล้วเพิ่มรูปตัวอย่าง [app.js:272](assets/app.js#L272)
  - สร้างโปรโมชั่น : เลือกประเภทแล้วซ่อน/แสดงกล่องเงื่อนไข [app.js:353](assets/app.js#L353)
  - หน้าแรก : เปิด/ปิดแผ่นตัวกรอง [app.js:109](assets/app.js#L109)
  - สมัครสมาชิก : ข้อความ "รหัสผ่านไม่ตรงกัน" [app.js:94](assets/app.js#L94)

## 7. ข้อมูล JSON

- **Basic** แดชบอร์ดและหน้ารายงานดึง JSON จาก [api/report.php:104](api/report.php#L104) ด้วย fetch [app.js:427](assets/app.js#L427) แล้วแสดงเป็นตัวเลข กราฟ และตาราง [app.js:448](assets/app.js#L448)
- **Bonus (แสดงผลจากการประมวลผล JSON)** ยอดรวม ค่าเฉลี่ยต่อออเดอร์ สัดส่วนสถานะ และสินค้าขายดี 5 อันดับ · ตัวอย่างราคาในฟอร์มโปรโมชั่น คิดส่วนลดด้วยฟังก์ชันเดียวกับตะกร้า [api/promotion_preview.php:55](api/promotion_preview.php#L55)
- **Big Bonus (เก็บข้อมูลจาก JSON ลงฐานข้อมูล)** นำเข้าสินค้าจากไฟล์ JSON [admin/product_import.php](admin/product_import.php)
  - อ่านไฟล์ `json_decode` [product_import.php:106](admin/product_import.php#L106) และตรวจทุกช่อง [product_import.php:103](admin/product_import.php#L103)
  - บันทึก products, product_variants, stock_movements ใน transaction เดียว [product_import.php:184](admin/product_import.php#L184)
  - ฝั่ง JS อ่านไฟล์ด้วย `JSON.parse` แสดงตารางก่อนกดนำเข้า [app.js:309](assets/app.js#L309)
  - ไฟล์ตัวอย่าง [assets/sample-products.json](assets/sample-products.json)

## 8. ฐานข้อมูลและ CRUD

- **Basic** เชื่อมต่อด้วย PDO [connect.php:11](includes/connect.php#L11) · สินค้า : เพิ่ม/แก้ไข [product_edit.php:217](admin/product_edit.php#L217), ลบ [product_edit.php:283](admin/product_edit.php#L283), แสดงรายการ [admin/products.php](admin/products.php) · หมวดหมู่ : เพิ่ม เปลี่ยนชื่อ ลบเฉพาะหมวดที่ไม่มีใครใช้ [admin/categories.php](admin/categories.php) · โปรโมชั่น : สร้าง แก้ไข เปิด/ปิด
- **Bonus (ค้นหาและแสดงผลซับซ้อน)** หน้าแรกค้นจากชื่อ รายละเอียด และชื่อหมวด แล้วกรองหมวด ไซซ์ที่มีของ ช่วงราคา และเรียง 4 แบบ (แนะนำ = ขายดีที่สุด) [includes/catalog.php:43](includes/catalog.php#L43) · แอดมินค้นหาและกรองสินค้าใกล้หมด/ปิดขาย [products.php:23](admin/products.php#L23)
- **Big Bonus (แบ่งหน้า)** ฟังก์ชัน `pagination()` [includes/admin.php:11](includes/admin.php#L11) ใช้ในรายการสินค้า (หน้าละ 7) และออเดอร์ (หน้าละ 10)

## 9. Cookies และ Session

- **Basic (วงจร Session ครบ)**
  1. Initialize : `session_start()` [auth.php:8](includes/auth.php#L8)
  2. Store : เก็บผู้ใช้หลังเข้าสู่ระบบ [auth.php:22](includes/auth.php#L22) · ตะกร้าและคูปอง [api/cart.php:7](api/cart.php#L7)
  3. Get : `current_user()` [auth.php:11](includes/auth.php#L11)
  4. Retrieve session ID : `session_id()` แสดงในหน้าบัญชี [account.php:62](public/account.php#L62) · `session_regenerate_id()` ตอนเข้าสู่ระบบ [auth.php:24](includes/auth.php#L24) และหลังเปลี่ยนรหัสผ่าน [password.php:32](public/password.php#L32)
  5. Remove variable : ลบตะกร้าและคูปองหลังสั่งซื้อ [checkout.php:42](public/checkout.php#L42) · ลบข้อความแจ้งหลังแสดง [admin_header.php:34](includes/admin_header.php#L34)
  6. Terminate : `session_destroy()` และลบ cookie ของ session [auth.php:34](includes/auth.php#L34)
  - Cookie : "จดจำอีเมล" ในหน้าเข้าสู่ระบบ [login.php:28](public/login.php#L28)
- **Bonus (Session + ฐานข้อมูล)** คำสั่งซื้อของฉันดึงจากฐานข้อมูลตามผู้ใช้ใน session [orders.php:16](public/orders.php#L16)
- **Big Bonus (Session อย่างน้อย 2 ค่ากับฐานข้อมูล)** สั่งซื้อใช้ผู้ใช้ ตะกร้า และคูปองจาก session คิดราคาและบันทึกออเดอร์ [checkout.php:39](public/checkout.php#L39)

## 10. Ajax

- **Basic (XMLHttpRequest + responseText)** `sendCart()` [app.js:7](assets/app.js#L7) แปลง `xhr.responseText` เป็น JSON [app.js:11](assets/app.js#L11)
- **Bonus (ข้อมูลจากฐานข้อมูล)** [api/cart.php](api/cart.php) ตรวจสต็อกและคิดราคาจากฐานข้อมูล [cart.php:72](api/cart.php#L72) · fetch รายการสินค้า [app.js:129](assets/app.js#L129) และรายงาน
- **Big Bonus (มากกว่า 1 จุดในหน้าเดียว)** หน้าตะกร้า : ปุ่ม + / − / ลบ [app.js:220](assets/app.js#L220) และคูปอง [app.js:233](assets/app.js#L233) (เปิด DevTools > Network ให้ดู request ไป api/cart.php ทุกครั้งที่กด)

## 11. รายงานสรุปเชิงสถิติ — หน้า "รายงานยอดขาย" ของแอดมิน

- **Basic (ตาราง)** ตารางยอดขายรายวัน/รายเดือน [report.php:51](admin/report.php#L51), [app.js:528](assets/app.js#L528)
- **Bonus (กราฟ)** Chart.js [app.js:499](assets/app.js#L499) (ไลบรารีเก็บไว้ใน [assets/vendor](assets/vendor) ไม่พึ่ง CDN)
- **Big Bonus (เปลี่ยนเงื่อนไขด้วยการกรอกข้อมูล)** ปุ่ม 7 วัน / 30 วัน / 12 เดือน [report.php:13](admin/report.php#L13) และกรอกช่วงวันที่เอง [report.php:19](admin/report.php#L19) เซิร์ฟเวอร์ตรวจช่วงซ้ำ (ไม่เกิน 92 วัน ไม่เลยวันนี้) [api/report.php:134](api/report.php#L134)

---

## ต้องทำก่อนส่ง

- [x] อัปเดต ER diagram ให้ตรงกับ [shirtshop_xampp.sql](shirtshop_xampp.sql)
- [ ] import [shirtshop_xampp.sql](shirtshop_xampp.sql) ใหม่ในวันนำเสนอ (ทั้ง XAMPP และ Docker)
- [ ] ถ้าแก้ตารางในฐานข้อมูลอีก ให้แก้ [LMshirtER.drawio](LMshirtER.drawio) แล้ว export เป็น LMshirtER.jpg ใหม่
