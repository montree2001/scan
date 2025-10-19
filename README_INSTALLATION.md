# คำแนะนำการติดตั้งและใช้งานระบบ

## ระบบสแกนเข้าออกวิทยาลัยด้วย QR-Code

---

## 📋 ข้อกำหนดระบบ

- **Web Server**: Apache (XAMPP)
- **PHP**: เวอร์ชัน 7.4 ขึ้นไป
- **MySQL**: เวอร์ชัน 5.7 ขึ้นไป หรือ MariaDB
- **Browser**: Chrome, Safari, Firefox (เวอร์ชันล่าสุด)

---

## 🚀 ขั้นตอนการติดตั้ง

### 1. ติดตั้ง XAMPP
- ดาวน์โหลด XAMPP จาก https://www.apachefriends.org/
- ติดตั้งและเปิดใช้งาน Apache และ MySQL

### 2. สร้างฐานข้อมูล

1. เปิด phpMyAdmin ที่ http://localhost/phpmyadmin
2. สร้างฐานข้อมูลใหม่หรือใช้ไฟล์ SQL

**วิธีที่ 1: สร้างด้วยไฟล์ SQL**
```bash
# เข้าไปที่โฟลเดอร์ scan
cd /Applications/XAMPP/xamppfiles/htdocs/scan

# Import ฐานข้อมูล
mysql -u root -p < database/schema.sql
```

**วิธีที่ 2: ใช้ phpMyAdmin**
- เปิด phpMyAdmin
- คลิก "Import"
- เลือกไฟล์ `database/schema.sql`
- คลิก "Go"

### 3. ตั้งค่า Config

แก้ไขไฟล์ `config/config.php` (ถ้าจำเป็น):

```php
// ข้อมูลการเชื่อมต่อฐานข้อมูล
define('DB_HOST', 'localhost');
define('DB_NAME', 'college_scan_system');
define('DB_USER', 'root');
define('DB_PASS', ''); // ใส่รหัสผ่าน MySQL ของคุณ

// ตั้งค่า URL
define('BASE_URL', 'http://localhost/scan');
```

### 4. ตั้งค่าสิทธิ์โฟลเดอร์

ให้สิทธิ์เขียนไฟล์สำหรับโฟลเดอร์ uploads:

**สำหรับ Mac/Linux:**
```bash
chmod -R 755 uploads/
```

**สำหรับ Windows:**
- คลิกขวาที่โฟลเดอร์ uploads
- เลือก Properties > Security
- ให้สิทธิ์ Write

### 5. ติดตั้ง Composer และ Library (สำหรับการอ่าน Excel)

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/scan
composer require phpoffice/phpspreadsheet
```

---

## 👤 บัญชีผู้ใช้เริ่มต้น

### แอดมิน (Administrator)
- **Username**: `admin`
- **Password**: `admin123`

**⚠️ หมายเหตุ**: กรุณาเปลี่ยนรหัสผ่านทันทีหลังจากเข้าสู่ระบบครั้งแรก!

---

## 📱 การเข้าใช้งาน

### หน้าเข้าสู่ระบบ
```
http://localhost/scan/login.php
```

### หน้า Dashboard ตาม Role

**แอดมิน:**
```
http://localhost/scan/admin/index.php
```

**เจ้าหน้าที่ (Staff):**
```
http://localhost/scan/staff/index.php
```

**นักเรียน (Student):**
```
http://localhost/scan/student/index.php
```

---

## 📂 โครงสร้างโฟลเดอร์

```
scan/
├── admin/                  # ส่วนของแอดมิน
│   ├── includes/          # Header, Footer
│   ├── index.php          # Dashboard
│   ├── students.php       # จัดการนักเรียน
│   ├── import.php         # นำเข้าข้อมูล Excel
│   ├── qrcode.php         # จัดการ QR-Code
│   ├── users.php          # จัดการผู้ใช้
│   └── reports.php        # รายงาน
├── staff/                  # ส่วนของเจ้าหน้าที่
├── student/                # ส่วนของนักเรียน
├── config/                 # ไฟล์ Config
│   ├── config.php         # ค่าคงที่และการตั้งค่า
│   ├── database.php       # การเชื่อมต่อฐานข้อมูล
│   └── functions.php      # ฟังก์ชันทั่วไป
├── database/              # ไฟล์ฐานข้อมูล
│   └── schema.sql         # โครงสร้างฐานข้อมูล
├── uploads/               # ไฟล์ที่อัพโหลด
│   ├── photos/           # รูปภาพนักเรียน
│   └── qrcodes/          # QR-Code
├── login.php             # หน้า Login
├── logout.php            # Logout
└── index.php             # หน้าแรก
```

---

## ✅ ฟีเจอร์ที่พร้อมใช้งาน

### ส่วนที่สร้างเสร็จแล้ว ✓

1. ✅ **โครงสร้างฐานข้อมูล** (10 ตาราง)
   - users (ผู้ใช้งาน)
   - students (นักเรียน)
   - student_photos (รูปภาพ)
   - student_vehicles (ข้อมูลรถ)
   - qr_codes (QR-Code)
   - attendance_logs (บันทึกเข้าออก)
   - import_logs (บันทึกการนำเข้า)
   - system_settings (ตั้งค่าระบบ)
   - activity_logs (บันทึกกิจกรรม)

2. ✅ **ระบบ Config และ Database**
   - การเชื่อมต่อฐานข้อมูลด้วย PDO
   - ฟังก์ชันทั่วไปกว่า 20 ฟังก์ชัน
   - ป้องกัน SQL Injection และ XSS

3. ✅ **ระบบ Login/Logout**
   - ระบบ Authentication
   - Session Management
   - ป้องกัน CSRF
   - Activity Logging

4. ✅ **Dashboard แอดมิน**
   - แสดงสถิติทั่วไป (4 การ์ด)
   - กราฟสถิติ 7 วันล่าสุด
   - รายการเข้าออกล่าสุด 10 รายการ
   - เมนูด่วนสำหรับการทำงาน

---

## 🔜 ฟีเจอร์ที่กำลังจะพัฒนา

### รอการสร้าง (Pending)

5. ⏳ **ระบบนำเข้าข้อมูลจาก Excel**
   - อัพโหลดไฟล์ Excel
   - Validate ข้อมูล
   - นำเข้าข้อมูลนักเรียน
   - รายงานผลการนำเข้า

6. ⏳ **ระบบจัดการนักเรียน (CRUD)**
   - เพิ่ม/แก้ไข/ลบนักเรียน
   - จัดการรูปภาพ
   - จัดการข้อมูลรถ
   - ค้นหาและกรองข้อมูล

7. ⏳ **ระบบสร้างและพิมพ์ QR-Code**
   - สร้าง QR-Code สำหรับนักเรียน
   - พิมพ์บัตร QR-Code
   - Export เป็น PDF

8. ⏳ **ระบบจัดการผู้ใช้งาน**
   - เพิ่ม/แก้ไข/ลบผู้ใช้
   - จัดการสิทธิ์
   - เปลี่ยนรหัสผ่าน

9. ⏳ **ระบบรายงาน**
   - รายงานการเข้าออกรายวัน/เดือน
   - Export Excel/PDF
   - สถิติและกราฟ

10. ⏳ **ระบบสแกน QR-Code (Staff/Student)**
    - สแกนผ่านกล้องมือถือ
    - บันทึกเข้าออกอัตโนมัติ
    - แสดงข้อมูลนักเรียน

---

## 🔧 การแก้ปัญหา (Troubleshooting)

### ปัญหา: เชื่อมต่อฐานข้อมูลไม่ได้
**วิธีแก้:**
- ตรวจสอบว่า MySQL เปิดทำงานใน XAMPP
- ตรวจสอบ username และ password ใน `config/config.php`
- ตรวจสอบว่าสร้างฐานข้อมูล `college_scan_system` แล้ว

### ปัญหา: อัพโหลดรูปภาพไม่ได้
**วิธีแก้:**
- ตรวจสอบสิทธิ์โฟลเดอร์ `uploads/`
- ตรวจสอบขนาดไฟล์ (ไม่เกิน 5MB)
- ตรวจสอบประเภทไฟล์ (JPG, PNG เท่านั้น)

### ปัญหา: หน้าเว็บแสดงผิดพลาด (Error 500)
**วิธีแก้:**
- ตรวจสอบ Error Log ใน XAMPP
- เปิด Error Reporting ใน `config/config.php`
- ตรวจสอบว่าไฟล์ PHP ไม่มี Syntax Error

---

## 📞 ติดต่อและสนับสนุน

หากพบปัญหาหรือต้องการความช่วยเหลือ:
- ตรวจสอบ Error Log ที่ XAMPP Control Panel
- ดูเอกสารใน `SYSTEM_SCOPE.md`
- ตรวจสอบ Activity Logs ในระบบ

---

## 🔐 ความปลอดภัย

ระบบมีการป้องกัน:
- ✅ SQL Injection (ใช้ Prepared Statements)
- ✅ XSS Attack (ใช้ htmlspecialchars)
- ✅ CSRF Attack (ใช้ CSRF Token)
- ✅ Password Hashing (ใช้ password_hash)
- ✅ Session Security
- ✅ File Upload Validation

**คำแนะนำ:**
- เปลี่ยนรหัสผ่านเริ่มต้นทันที
- อย่าใช้ในสภาพแวดล้อม Production โดยไม่ตั้งค่า HTTPS
- สำรองข้อมูลเป็นประจำ

---

**เวอร์ชัน**: 1.0.0
**วันที่อัพเดท**: 2025-10-16
