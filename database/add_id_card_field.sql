-- เพิ่มฟิลด์ id_card (เลขบัตรประชาชน 13 หลัก) ในตาราง students
-- เพิ่มฟิลด์ gps_latitude และ gps_longitude ในตาราง attendance_logs

USE college_scan_system;

-- เพิ่มฟิลด์ id_card ในตาราง students (ถ้ายังไม่มี)
ALTER TABLE students
ADD COLUMN IF NOT EXISTS id_card VARCHAR(13) UNIQUE COMMENT 'เลขบัตรประชาชน 13 หลัก' AFTER student_code,
ADD INDEX idx_id_card (id_card);

-- เพิ่มฟิลด์ GPS ในตาราง attendance_logs เพื่อเก็บตำแหน่งที่เช็คอิน
ALTER TABLE attendance_logs
ADD COLUMN IF NOT EXISTS gps_latitude DECIMAL(10, 8) NULL COMMENT 'ละติจูด GPS' AFTER notes,
ADD COLUMN IF NOT EXISTS gps_longitude DECIMAL(11, 8) NULL COMMENT 'ลองจิจูด GPS' AFTER gps_latitude,
ADD COLUMN IF NOT EXISTS is_outside_area BOOLEAN DEFAULT FALSE COMMENT 'เช็คอินนอกพื้นที่หรือไม่' AFTER gps_longitude;

-- เพิ่มตาราง college_settings สำหรับตั้งค่าพิกัด GPS ของวิทยาลัย
CREATE TABLE IF NOT EXISTS college_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    college_name VARCHAR(255) DEFAULT 'วิทยาลัย',
    college_latitude DECIMAL(10, 8) NOT NULL COMMENT 'ละติจูด GPS ของวิทยาลัย',
    college_longitude DECIMAL(11, 8) NOT NULL COMMENT 'ลองจิจูด GPS ของวิทยาลัย',
    allowed_radius_meters INT DEFAULT 500 COMMENT 'รัศมีที่อนุญาตให้เช็คอินได้ (เมตร)',
    security_warning_text TEXT COMMENT 'ข้อความเตือนด้านความมั่นคง',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ใส่ข้อมูลเริ่มต้น (ตัวอย่างพิกัด กรุณาแก้ไขให้ตรงกับพิกัดจริงของวิทยาลัย)
INSERT INTO college_settings
(college_name, college_latitude, college_longitude, allowed_radius_meters, security_warning_text)
VALUES (
    'วิทยาลัย',
    13.7563,  -- ตัวอย่างพิกัด (กรุณาแก้ไข)
    100.5018, -- ตัวอย่างพิกัด (กรุณาแก้ไข)
    500,
    'วิทยาลัยเป็นพื้นที่ควบคุมทางการทหาร เพื่อความมั่นคง ห้ามบันทึกภาพบริเวณหวงห้าม ฝ่าฝืนมีโทษทางกฎหมาย'
)
ON DUPLICATE KEY UPDATE college_name = college_name;
