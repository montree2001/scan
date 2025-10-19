-- ฐานข้อมูลระบบสแกนเข้าออกวิทยาลัย
-- สร้างวันที่: 2025-10-16

-- สร้างฐานข้อมูล
CREATE DATABASE IF NOT EXISTS college_scan_system
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE college_scan_system;

-- ตาราง users: ข้อมูลผู้ใช้ระบบทุกประเภท
CREATE TABLE users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'student', 'staff') NOT NULL DEFAULT 'student',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    INDEX idx_username (username),
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตาราง students: ข้อมูลนักเรียน
CREATE TABLE students (
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    student_code VARCHAR(20) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    nickname VARCHAR(50),
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    class VARCHAR(50),
    grade VARCHAR(20),
    parent_name VARCHAR(100),
    parent_phone VARCHAR(20),
    address TEXT,
    phone VARCHAR(20),
    email VARCHAR(100),
    emergency_contact VARCHAR(100),
    emergency_phone VARCHAR(20),
    status ENUM('active', 'inactive', 'graduated') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_student_code (student_code),
    INDEX idx_class (class),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตาราง student_photos: รูปภาพนักเรียน
CREATE TABLE student_photos (
    photo_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    photo_path VARCHAR(255) NOT NULL,
    photo_type ENUM('profile', 'id_card', 'other') DEFAULT 'profile',
    is_primary BOOLEAN DEFAULT FALSE,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    INDEX idx_student_id (student_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตาราง student_vehicles: ข้อมูลรถของนักเรียน
CREATE TABLE student_vehicles (
    vehicle_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    vehicle_type ENUM('motorcycle', 'car', 'bicycle', 'other') NOT NULL,
    license_plate VARCHAR(20),
    brand VARCHAR(50),
    model VARCHAR(50),
    color VARCHAR(30),
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    INDEX idx_student_id (student_id),
    INDEX idx_license_plate (license_plate)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตาราง qr_codes: ข้อมูล QR Code ของนักเรียน
CREATE TABLE qr_codes (
    qr_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    qr_code VARCHAR(100) NOT NULL UNIQUE,
    qr_image_path VARCHAR(255),
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    last_used TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    INDEX idx_student_id (student_id),
    INDEX idx_qr_code (qr_code),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตาราง attendance_logs: บันทึกการเข้าออก
CREATE TABLE attendance_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    qr_id INT,
    log_type ENUM('in', 'out') NOT NULL,
    log_date DATE NOT NULL,
    log_time TIME NOT NULL,
    vehicle_id INT,
    scan_method ENUM('qr_scan', 'manual') DEFAULT 'qr_scan',
    recorded_by INT,
    location VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (qr_id) REFERENCES qr_codes(qr_id) ON DELETE SET NULL,
    FOREIGN KEY (vehicle_id) REFERENCES student_vehicles(vehicle_id) ON DELETE SET NULL,
    FOREIGN KEY (recorded_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_student_id (student_id),
    INDEX idx_log_date (log_date),
    INDEX idx_log_type (log_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตาราง import_logs: บันทึกการนำเข้าข้อมูล
CREATE TABLE import_logs (
    import_id INT AUTO_INCREMENT PRIMARY KEY,
    imported_by INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    total_records INT DEFAULT 0,
    success_records INT DEFAULT 0,
    failed_records INT DEFAULT 0,
    import_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('processing', 'completed', 'failed') DEFAULT 'processing',
    error_log TEXT,
    FOREIGN KEY (imported_by) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_imported_by (imported_by),
    INDEX idx_import_date (import_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตาราง system_settings: การตั้งค่าระบบ
CREATE TABLE system_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type VARCHAR(20) DEFAULT 'string',
    description TEXT,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ตาราง activity_logs: บันทึกกิจกรรมในระบบ
CREATE TABLE activity_logs (
    activity_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    activity_type VARCHAR(50) NOT NULL,
    activity_description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_activity_type (activity_type),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- สร้างผู้ใช้แอดมินเริ่มต้น (username: admin, password: admin123)
-- Password is hashed using PHP password_hash() with PASSWORD_DEFAULT
INSERT INTO users (username, password, email, full_name, role, status)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@college.ac.th', 'ผู้ดูแลระบบ', 'admin', 'active');

-- ตั้งค่าระบบเริ่มต้น
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('site_name', 'ระบบสแกนเข้าออกวิทยาลัย', 'string', 'ชื่อเว็บไซต์'),
('qr_code_expiry_days', '0', 'integer', 'จำนวนวันหมดอายุ QR Code (0 = ไม่หมดอายุ)'),
('allow_student_registration', '1', 'boolean', 'อนุญาตให้นักเรียนลงทะเบียนเอง'),
('max_vehicles_per_student', '3', 'integer', 'จำนวนรถสูงสุดต่อนักเรียน 1 คน'),
('default_timezone', 'Asia/Bangkok', 'string', 'เขตเวลาเริ่มต้น');
