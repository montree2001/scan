<?php
/**
 * รัน Migration สำหรับระบบเช็คอินสาธารณะ
 * Compatible กับ MySQL ทุกเวอร์ชัน
 */
require_once 'config/config.php';
require_once 'config/database.php';

$db = getDB();

echo "<h2>รัน Database Migration สำหรับระบบเช็คอินสาธารณะ</h2>";
echo "<hr>";

try {
    // ตรวจสอบเวอร์ชัน MySQL
    $version = $db->query('SELECT VERSION()')->fetchColumn();
    echo "<p><strong>MySQL Version:</strong> {$version}</p>";
    echo "<hr>";

    $successCount = 0;
    $errorCount = 0;
    $skipCount = 0;

    // ====================================
    // 1. เพิ่มฟิลด์ id_card ในตาราง students
    // ====================================
    echo "<h3>1. ตรวจสอบและเพิ่มฟิลด์ในตาราง students</h3>";

    // ตรวจสอบว่ามีฟิลด์ id_card หรือไม่
    $stmt = $db->query("SHOW COLUMNS FROM students LIKE 'id_card'");
    if ($stmt->rowCount() == 0) {
        try {
            $db->exec("ALTER TABLE students ADD COLUMN id_card VARCHAR(13) NULL UNIQUE COMMENT 'เลขบัตรประชาชน 13 หลัก' AFTER student_code");
            echo "<p style='color: green;'>✅ เพิ่มฟิลด์ id_card สำเร็จ</p>";
            $successCount++;
        } catch (PDOException $e) {
            echo "<p style='color: red;'>❌ เพิ่มฟิลด์ id_card ล้มเหลว: " . $e->getMessage() . "</p>";
            $errorCount++;
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ ฟิลด์ id_card มีอยู่แล้ว (ข้าม)</p>";
        $skipCount++;
    }

    // เพิ่ม Index สำหรับ id_card
    $stmt = $db->query("SHOW INDEX FROM students WHERE Key_name = 'idx_id_card'");
    if ($stmt->rowCount() == 0) {
        try {
            $db->exec("ALTER TABLE students ADD INDEX idx_id_card (id_card)");
            echo "<p style='color: green;'>✅ เพิ่ม Index idx_id_card สำเร็จ</p>";
            $successCount++;
        } catch (PDOException $e) {
            echo "<p style='color: orange;'>⚠️ เพิ่ม Index idx_id_card: " . $e->getMessage() . "</p>";
            $skipCount++;
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ Index idx_id_card มีอยู่แล้ว (ข้าม)</p>";
        $skipCount++;
    }

    // ====================================
    // 2. เพิ่มฟิลด์ GPS ในตาราง attendance_logs
    // ====================================
    echo "<h3>2. ตรวจสอบและเพิ่มฟิลด์ในตาราง attendance_logs</h3>";

    $gpsFields = [
        'gps_latitude' => "DECIMAL(10, 8) NULL COMMENT 'ละติจูด GPS'",
        'gps_longitude' => "DECIMAL(11, 8) NULL COMMENT 'ลองจิจูด GPS'",
        'is_outside_area' => "TINYINT(1) DEFAULT 0 COMMENT 'เช็คอินนอกพื้นที่หรือไม่'"
    ];

    foreach ($gpsFields as $fieldName => $fieldDef) {
        $stmt = $db->query("SHOW COLUMNS FROM attendance_logs LIKE '{$fieldName}'");
        if ($stmt->rowCount() == 0) {
            try {
                $db->exec("ALTER TABLE attendance_logs ADD COLUMN {$fieldName} {$fieldDef}");
                echo "<p style='color: green;'>✅ เพิ่มฟิลด์ {$fieldName} สำเร็จ</p>";
                $successCount++;
            } catch (PDOException $e) {
                echo "<p style='color: red;'>❌ เพิ่มฟิลด์ {$fieldName} ล้มเหลว: " . $e->getMessage() . "</p>";
                $errorCount++;
            }
        } else {
            echo "<p style='color: blue;'>ℹ️ ฟิลด์ {$fieldName} มีอยู่แล้ว (ข้าม)</p>";
            $skipCount++;
        }
    }

    // ====================================
    // 3. สร้างตาราง college_settings
    // ====================================
    echo "<h3>3. ตรวจสอบและสร้างตาราง college_settings</h3>";

    $stmt = $db->query("SHOW TABLES LIKE 'college_settings'");
    if ($stmt->rowCount() == 0) {
        try {
            $createTableSQL = "
                CREATE TABLE college_settings (
                    setting_id INT AUTO_INCREMENT PRIMARY KEY,
                    college_name VARCHAR(255) DEFAULT 'วิทยาลัย',
                    college_latitude DECIMAL(10, 8) NOT NULL COMMENT 'ละติจูด GPS ของวิทยาลัย',
                    college_longitude DECIMAL(11, 8) NOT NULL COMMENT 'ลองจิจูด GPS ของวิทยาลัย',
                    allowed_radius_meters INT DEFAULT 500 COMMENT 'รัศมีที่อนุญาตให้เช็คอินได้ (เมตร)',
                    security_warning_text TEXT COMMENT 'ข้อความเตือนด้านความมั่นคง',
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ";
            $db->exec($createTableSQL);
            echo "<p style='color: green;'>✅ สร้างตาราง college_settings สำเร็จ</p>";
            $successCount++;

            // ใส่ข้อมูลเริ่มต้น
            $insertSQL = "
                INSERT INTO college_settings
                (college_name, college_latitude, college_longitude, allowed_radius_meters, security_warning_text)
                VALUES (
                    'วิทยาลัย',
                    13.7563,
                    100.5018,
                    500,
                    'วิทยาลัยเป็นพื้นที่ควบคุมทางการทหาร เพื่อความมั่นคง ห้ามบันทึกภาพบริเวณหวงห้าม ฝ่าฝืนมีโทษทางกฎหมาย'
                )
            ";
            $db->exec($insertSQL);
            echo "<p style='color: green;'>✅ ใส่ข้อมูลเริ่มต้นใน college_settings สำเร็จ</p>";
            $successCount++;

        } catch (PDOException $e) {
            echo "<p style='color: red;'>❌ สร้างตาราง college_settings ล้มเหลว: " . $e->getMessage() . "</p>";
            $errorCount++;
        }
    } else {
        echo "<p style='color: blue;'>ℹ️ ตาราง college_settings มีอยู่แล้ว (ข้าม)</p>";
        $skipCount++;

        // ตรวจสอบว่ามีข้อมูลหรือไม่
        $stmt = $db->query("SELECT COUNT(*) as count FROM college_settings");
        $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        if ($count == 0) {
            try {
                $insertSQL = "
                    INSERT INTO college_settings
                    (college_name, college_latitude, college_longitude, allowed_radius_meters, security_warning_text)
                    VALUES (
                        'วิทยาลัย',
                        13.7563,
                        100.5018,
                        500,
                        'วิทยาลัยเป็นพื้นที่ควบคุมทางการทหาร เพื่อความมั่นคง ห้ามบันทึกภาพบริเวณหวงห้าม ฝ่าฝืนมีโทษทางกฎหมาย'
                    )
                ";
                $db->exec($insertSQL);
                echo "<p style='color: green;'>✅ ใส่ข้อมูลเริ่มต้นใน college_settings สำเร็จ</p>";
                $successCount++;
            } catch (PDOException $e) {
                echo "<p style='color: orange;'>⚠️ ใส่ข้อมูลเริ่มต้น: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p style='color: blue;'>ℹ️ มีข้อมูลใน college_settings อยู่แล้ว ({$count} แถว)</p>";
        }
    }

    // ====================================
    // สรุปผล
    // ====================================
    echo "<hr>";
    echo "<h3>สรุปผลการ Migration</h3>";
    echo "<p>✅ สำเร็จ: {$successCount} รายการ</p>";
    echo "<p>⚠️ ข้ามเพราะมีอยู่แล้ว: {$skipCount} รายการ</p>";
    echo "<p>❌ ล้มเหลว: {$errorCount} รายการ</p>";

    // ====================================
    // ตรวจสอบโครงสร้างตาราง
    // ====================================
    echo "<hr>";
    echo "<h3>ตรวจสอบโครงสร้างตารางหลัง Migration</h3>";

    // ตรวจสอบ students table
    $stmt = $db->query("DESCRIBE students");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h4>ตาราง students:</h4>";
    $hasIdCard = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'id_card') {
            $hasIdCard = true;
            echo "<p style='color: green;'>✅ ฟิลด์ id_card: {$col['Type']} - {$col['Key']}</p>";
        }
    }

    if (!$hasIdCard) {
        echo "<p style='color: red;'>❌ ไม่พบฟิลด์ id_card</p>";
    }

    // ตรวจสอบ attendance_logs table
    $stmt = $db->query("DESCRIBE attendance_logs");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h4>ตาราง attendance_logs:</h4>";
    $checkFields = ['gps_latitude', 'gps_longitude', 'is_outside_area'];
    foreach ($checkFields as $field) {
        $found = false;
        foreach ($columns as $col) {
            if ($col['Field'] === $field) {
                $found = true;
                echo "<p style='color: green;'>✅ ฟิลด์ {$field}: {$col['Type']}</p>";
                break;
            }
        }
        if (!$found) {
            echo "<p style='color: red;'>❌ ไม่พบฟิลด์ {$field}</p>";
        }
    }

    // ตรวจสอบ college_settings table
    $stmt = $db->query("SHOW TABLES LIKE 'college_settings'");
    if ($stmt->fetch()) {
        echo "<h4>ตาราง college_settings:</h4>";
        echo "<p style='color: green;'>✅ ตารางถูกสร้างแล้ว</p>";

        $stmt = $db->query("SELECT * FROM college_settings LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($settings) {
            echo "<div style='background: #f8f9fa; padding: 10px; border-radius: 5px; margin-top: 10px;'>";
            echo "<strong>ข้อมูลปัจจุบัน:</strong><br>";
            echo "ชื่อวิทยาลัย: {$settings['college_name']}<br>";
            echo "พิกัด GPS: {$settings['college_latitude']}, {$settings['college_longitude']}<br>";
            echo "รัศมีที่อนุญาต: {$settings['allowed_radius_meters']} เมตร<br>";
            echo "ข้อความเตือน: {$settings['security_warning_text']}";
            echo "</div>";
        }
    } else {
        echo "<p style='color: red;'>❌ ไม่พบตาราง college_settings</p>";
    }

    // แสดงข้อความสรุป
    echo "<hr>";
    if ($errorCount == 0) {
        echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px;'>";
        echo "<h3 style='color: #155724;'>✅ Migration เสร็จสมบูรณ์</h3>";
        echo "<p>ตอนนี้คุณสามารถใช้งานระบบเช็คอินสาธารณะได้แล้ว</p>";
        echo "<p><strong>ขั้นตอนต่อไป:</strong></p>";
        echo "<ol>";
        echo "<li>ไปที่ <a href='admin/settings_checkin.php'>หน้าตั้งค่า</a> เพื่อแก้ไขพิกัด GPS ของวิทยาลัยให้ถูกต้อง</li>";
        echo "<li>เพิ่มเลขบัตรประชาชนให้กับนักเรียนในตาราง <code>students</code> (ผ่านหน้าจัดการนักเรียน)</li>";
        echo "<li>เข้าใช้งานหน้าเช็คอินสาธารณะที่ <a href='checkin.php'>checkin.php</a></li>";
        echo "<li>เปิดจอแสดงผลเช็คอินแบบ Real-time ที่ <a href='checkin_display.php'>checkin_display.php</a></li>";
        echo "</ol>";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
        echo "<h3 style='color: #721c24;'>⚠️ Migration เสร็จสิ้นแต่มีข้อผิดพลาด</h3>";
        echo "<p>กรุณาตรวจสอบข้อผิดพลาดด้านบนและลองแก้ไข</p>";
        echo "</div>";
    }

    echo "<p style='margin-top: 20px;'>";
    echo "<a href='admin/settings_checkin.php' class='btn btn-primary' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-right: 10px;'>⚙️ ตั้งค่าระบบ</a>";
    echo "<a href='checkin.php' class='btn btn-success' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-right: 10px;'>📱 หน้าเช็คอิน</a>";
    echo "<a href='checkin_display.php' class='btn btn-info' style='background: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-right: 10px;'>📺 จอแสดงผล</a>";
    echo "<a href='index.php' style='padding: 10px 20px; text-decoration: none;'>← กลับหน้าหลัก</a>";
    echo "</p>";

} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; color: #721c24;'>";
    echo "<h3>❌ เกิดข้อผิดพลาดร้ายแรง</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
    echo "</div>";
}
?>
<style>
    body {
        font-family: 'Sarabun', Arial, sans-serif;
        padding: 20px;
        max-width: 1200px;
        margin: 0 auto;
        background: #f8f9fa;
    }
    h2, h3, h4 {
        color: #333;
    }
    pre {
        background: #f4f4f4;
        padding: 10px;
        border-radius: 5px;
        overflow-x: auto;
        font-size: 12px;
    }
    code {
        background: #f4f4f4;
        padding: 2px 5px;
        border-radius: 3px;
        font-family: 'Courier New', monospace;
    }
    .btn {
        display: inline-block;
        text-decoration: none;
        transition: all 0.3s;
    }
    .btn:hover {
        opacity: 0.8;
        transform: translateY(-2px);
    }
    hr {
        margin: 20px 0;
        border: none;
        border-top: 2px solid #dee2e6;
    }
</style>
<link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
