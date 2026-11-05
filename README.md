# LMShirtShop — ร้านขายเสือออนไลน์

เว็บแอปพลิเคชันร้านขายเสื้อออนไลน์ พัฒนาด้วย **PHP + MySQL** รองรับทั้งฝั่งลูกค้า (เลือกดูสินค้า ตะกร้า สั่งซื้อ) และฝั่งผู้ดูแลระบบ (จัดการสินค้า คำสั่งซื้อ ผู้ใช้) พร้อมรันได้ทั้งผ่าน **XAMPP** และ **Docker**

> โปรเจกต์รายวิชา Web Development

---

## ✨ ฟีเจอร์หลัก

### ฝั่งลูกค้า
- สมัครสมาชิก / เข้าสู่ระบบ
- ดูรายการสินค้า ค้นหา และกรองตามหมวดหมู่
- ดูรายละเอียดสินค้า เลือกไซซ์ / สี
- เพิ่มสินค้าลงตะกร้า และสั่งซื้อ
- ดูประวัติและสถานะคำสั่งซื้อ

### ฝั่งผู้ดูแลระบบ (Admin)
- จัดการสินค้า (เพิ่ม / แก้ไข / ลบ / อัปโหลดรูป)
- จัดการหมวดหมู่
- จัดการคำสั่งซื้อและอัปเดตสถานะ
- จัดการผู้ใช้งาน

> ⚠️ ปรับรายการฟีเจอร์ให้ตรงกับที่ระบบของคุณมีจริง

---

## 🛠️ เทคโนโลยีที่ใช้

| ส่วน | เทคโนโลยี |
|------|-----------|
| Backend | PHP |
| Database | MySQL / MariaDB |
| Frontend | HTML, CSS, JavaScript |
| Environment | XAMPP, Docker, Docker Compose |
| Design | draw.io (ER Diagram), Wireframe |

---

## 📁 โครงสร้างโปรเจกต์

```
project web/
├── admin/                # หน้าจัดการระบบสำหรับผู้ดูแล
├── api/                  # API endpoints
├── assets/               # CSS, JS, รูปภาพ
├── includes/             # ไฟล์ที่ใช้ร่วมกัน (config, header, footer, functions)
├── public/               # ไฟล์ที่เข้าถึงได้จากภายนอก
├── index.php             # หน้าแรกของเว็บไซต์
├── shirtshop_xampp.sql   # ไฟล์ฐานข้อมูลสำหรับ import
├── docker-compose.yml    # ตั้งค่า Docker services
├── Dockerfile            # Image สำหรับ PHP/Apache
├── LMshirtER.drawio      # ER Diagram (ไฟล์ต้นฉบับ)
├── LMshirtER.jpg         # ER Diagram (รูปภาพ)
├── wireframe.pdf         # Wireframe ของหน้าเว็บ
├── DEMO.md               # ขั้นตอนการ demo ระบบ
└── README.md
```

---

## 🚀 วิธีติดตั้งและใช้งาน

### วิธีที่ 1: ใช้ XAMPP

1. ติดตั้ง [XAMPP](https://www.apachefriends.org/) แล้วเปิด **Apache** และ **MySQL**
2. คัดลอกโปรเจกต์ไปไว้ที่ `C:\xampp\htdocs\` (เช่น `C:\xampp\htdocs\project-web`)
3. เปิด [phpMyAdmin](http://localhost/phpmyadmin) แล้วสร้างฐานข้อมูลชื่อ `shirtshop`
4. Import ไฟล์ `shirtshop_xampp.sql` เข้าไปในฐานข้อมูลที่สร้าง
5. แก้ค่าเชื่อมต่อฐานข้อมูลในโฟลเดอร์ `includes/` (เช่น `config.php`) ให้ตรงกับเครื่องของคุณ
6. เปิดเบราว์เซอร์ไปที่ `http://localhost/project-web`

### วิธีที่ 2: ใช้ Docker

1. ติดตั้ง [Docker Desktop](https://www.docker.com/products/docker-desktop/)
2. Clone โปรเจกต์

   ```bash
   git clone https://github.com/<your-username>/<your-repo>.git
   cd <your-repo>
   ```

3. สร้างไฟล์ตั้งค่า (ถ้ามี `.env.example` ให้คัดลอกเป็น `.env`)

   ```bash
   cp .env.example .env
   ```

4. สั่งรัน

   ```bash
   docker compose up -d --build
   ```

5. เปิดเบราว์เซอร์ไปที่ `http://localhost:8080` *(พอร์ตขึ้นอยู่กับที่ตั้งใน `docker-compose.yml`)*

หยุดการทำงาน:

```bash
docker compose down
```

---

## 🔑 บัญชีสำหรับทดลองใช้

| บทบาท | Username / Email | Password |
|-------|------------------|----------|
| Admin | `admin@example.com` | `********` |
| ลูกค้า | `user@example.com` | `********` |

> ใส่เฉพาะบัญชีทดสอบเท่านั้น **ห้ามใส่รหัสผ่านจริง**

---

## 🗄️ ฐานข้อมูล

ดูโครงสร้างความสัมพันธ์ของตารางได้ที่ `LMshirtER.jpg`

![ER Diagram](LMshirtER.jpg)

---

## 📸 ตัวอย่างหน้าจอ

*(ใส่ภาพ screenshot ของหน้าเว็บที่นี่)*

| หน้าแรก | รายละเอียดสินค้า | หน้าแอดมิน |
|---------|------------------|------------|
| ![home](assets/screenshots/home.png) | ![product](assets/screenshots/product.png) | ![admin](assets/screenshots/admin.png) |

---

## 📚 เอกสารเพิ่มเติม

- [DEMO.md](DEMO.md) — ขั้นตอนการสาธิตระบบ
- `wireframe.pdf` — Wireframe ของหน้าเว็บ
- `LMshirtER.drawio` — ไฟล์ต้นฉบับ ER Diagram (เปิดด้วย [draw.io](https://app.diagrams.net/))

---

## 👥 ผู้จัดทำ

| ชื่อ | หน้าที่ |
|------|---------|
| ชื่อ-นามสกุล | Frontend / Backend / Database |
| ชื่อ-นามสกุล | ... |

---

## 📝 License

โปรเจกต์นี้จัดทำเพื่อการศึกษา