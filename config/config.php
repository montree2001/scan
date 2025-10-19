<?php
/**
 * ไฟล์ Config หลักของระบบ
 * สำหรับตั้งค่าการเชื่อมต่อฐานข้อมูลและค่าคงที่ต่างๆ
 */

// ตั้งค่า Error Reporting สำหรับ Development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ตั้งค่า Timezone
date_default_timezone_set('Asia/Bangkok');

// ตั้งค่า Session (เฉพาะตอนที่ยังไม่มี session)
if (session_status() === PHP_SESSION_NONE) {
    @ini_set('session.cookie_httponly', 1);
    @ini_set('session.use_only_cookies', 1);
    @ini_set('session.cookie_secure', 0); // เปลี่ยนเป็น 1 ถ้าใช้ HTTPS
}

// ข้อมูลการเชื่อมต่อฐานข้อมูล
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'college_scan_system');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ตั้งค่า Path
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', 'http://localhost/scan');
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('PHOTO_PATH', UPLOAD_PATH . '/photos');
define('QR_PATH', UPLOAD_PATH . '/qrcodes');

// สร้างโฟลเดอร์ uploads ถ้ายังไม่มี
if (!file_exists(UPLOAD_PATH)) {
    @mkdir(UPLOAD_PATH, 0755, true);
}
if (!file_exists(PHOTO_PATH)) {
    @mkdir(PHOTO_PATH, 0755, true);
}
if (!file_exists(QR_PATH)) {
    @mkdir(QR_PATH, 0755, true);
}

// ตั้งค่าไฟล์อัพโหลด
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/jpg']);

// ตั้งค่า Pagination
define('ITEMS_PER_PAGE', 20);

// ตั้งค่า Session Keys
define('SESSION_USER_ID', 'user_id');
define('SESSION_USERNAME', 'username');
define('SESSION_ROLE', 'user_role');
define('SESSION_USER_ROLE', 'user_role'); // Alias for SESSION_ROLE
define('SESSION_FULL_NAME', 'full_name');

// Application Settings
define('APP_NAME', 'ระบบสแกนเข้าออกวิทยาลัย');
define('SITE_NAME', 'ระบบสแกนเข้าออกวิทยาลัย'); // Alias for APP_NAME
define('APP_VERSION', '1.0.0');
