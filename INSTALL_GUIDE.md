# 🚀 คู่มือติดตั้งและใช้งานระบบ

## ขั้นตอนการติดตั้ง

### 1. สร้างฐานข้อมูล
```bash
# เปิด Terminal และรันคำสั่ง
cd /Applications/XAMPP/xamppfiles/htdocs/scan
mysql -u root -p < database/schema.sql
```

หรือใช้ phpMyAdmin:
1. เปิด http://localhost/phpmyadmin
2. คลิก Import
3. เลือกไฟล์ database/schema.sql
4. คลิก Go

### 2. ตรวจสอบระบบ
เปิด: http://localhost/scan/debug_login.php

ไฟล์นี้จะ:
- ตรวจสอบการเชื่อมต่อฐานข้อมูล
- สร้าง admin อัตโนมัติถ้าไม่มี
- แก้ไข password ถ้าผิด

### 3. เข้าสู่ระบบ
เปิด: http://localhost/scan/login.php

**ข้อมูล Login:**
- Username: `admin`
- Password: `admin123`

---

## ฟีเจอร์ที่พร้อมใช้งาน

### ✅ ส่วนแอดมิน
1. **Dashboard** (`/admin/index.php`)
   - สถิติทั่วไป (นักเรียน, อยู่ในวิทยาลัย, เข้าออกวันนี้)
   - กราฟสถิติ 7 วัน
   - รายการเข้าออกล่าสุด

2. **จัดการนักเรียน** (`/admin/students.php`)
   - เพิ่ม/แก้ไข/ลบนักเรียน
   - ค้นหาและกรอง
   - Pagination

3. **จัดการผู้ใช้** (`/admin/users.php`)
   - เพิ่ม/แก้ไข/ลบผู้ใช้
   - จัดการสิทธิ์ (admin, staff, student)

4. **นำเข้า Excel** - กำลังพัฒนา
5. **QR-Code** - กำลังพัฒนา
6. **รายงาน** - กำลังพัฒนา

---

## การแก้ปัญหา

### ปัญหา: Login ไม่ได้
**วิธีแก้:**
1. เปิด http://localhost/scan/clear_session.php
2. คลิก "เข้าสู่ระบบ"
3. Login ใหม่

### ปัญหา: Error 500
**วิธีแก้:**
```bash
chmod -R 755 config/ admin/ database/
chmod 755 uploads uploads/photos uploads/qrcodes
```

### ปัญหา: ไม่พบไฟล์ config
**วิธีแก้:**
เปิด http://localhost/scan/test.php เพื่อตรวจสอบ

---

## โครงสร้างโฟลเดอร์

```
scan/
├── config/              # ไฟล์ config
│   ├── config.php
│   ├── database.php
│   └── functions.php
├── database/            # ฐานข้อมูล
│   └── schema.sql
├── admin/               # ส่วนแอดมิน
│   ├── includes/
│   │   ├── header.php
│   │   └── footer.php
│   ├── index.php       # Dashboard
│   ├── students.php    # จัดการนักเรียน
│   └── users.php       # จัดการผู้ใช้
├── uploads/             # ไฟล์ที่อัพโหลด
│   ├── photos/
│   └── qrcodes/
└── login.php           # หน้า Login
```

---

## ข้อมูลสำคัญ

**ฐานข้อมูล:** college_scan_system
**URL:** http://localhost/scan
**Admin:** admin / admin123

⚠️ **คำเตือน:** เปลี่ยนรหัสผ่าน admin ทันทีหลัง Login ครั้งแรก!

---

**วันที่อัพเดท:** 2025-10-16
