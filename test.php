<?php
/**
 * ไฟล์ทดสอบระบบ - ตรวจสอบว่า PHP ทำงานได้หรือไม่
 */

echo "<!DOCTYPE html>";
echo "<html><head>";
echo "<meta charset='UTF-8'>";
echo "<title>ทดสอบระบบ</title>";
echo "<style>body{font-family:Arial,sans-serif;padding:20px;background:#f5f5f5;}";
echo ".success{color:green;} .error{color:red;} .info{color:blue;}";
echo "table{background:white;padding:20px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);}";
echo "td{padding:10px;border-bottom:1px solid #eee;}</style>";
echo "</head><body>";

echo "<h1>🔍 ระบบทดสอบ College Scan System</h1>";
echo "<table>";

// 1. ทดสอบ PHP Version
echo "<tr><td><strong>PHP Version:</strong></td><td>";
$phpVersion = phpversion();
if (version_compare($phpVersion, '7.4', '>=')) {
    echo "<span class='success'>✓ $phpVersion (รองรับ)</span>";
} else {
    echo "<span class='error'>✗ $phpVersion (ต้องการ 7.4 ขึ้นไป)</span>";
}
echo "</td></tr>";

// 2. ทดสอบโฟลเดอร์
echo "<tr><td><strong>Base Path:</strong></td><td>" . __DIR__ . "</td></tr>";

$folders = ['config', 'database', 'admin', 'uploads', 'uploads/photos', 'uploads/qrcodes'];
foreach ($folders as $folder) {
    $path = __DIR__ . '/' . $folder;
    echo "<tr><td><strong>Folder: $folder</strong></td><td>";
    if (file_exists($path)) {
        echo "<span class='success'>✓ มีอยู่แล้ว</span>";
    } else {
        if (mkdir($path, 0755, true)) {
            echo "<span class='info'>✓ สร้างใหม่สำเร็จ</span>";
        } else {
            echo "<span class='error'>✗ สร้างไม่ได้</span>";
        }
    }
    echo "</td></tr>";
}

// 3. ทดสอบไฟล์สำคัญ
$files = [
    'config/config.php',
    'config/database.php',
    'config/functions.php',
    'login.php',
    'index.php'
];

foreach ($files as $file) {
    $path = __DIR__ . '/' . $file;
    echo "<tr><td><strong>File: $file</strong></td><td>";
    if (file_exists($path)) {
        echo "<span class='success'>✓ พบไฟล์</span>";
    } else {
        echo "<span class='error'>✗ ไม่พบไฟล์</span>";
    }
    echo "</td></tr>";
}

// 4. ทดสอบ Extensions
$extensions = ['mysqli', 'pdo', 'pdo_mysql', 'gd', 'mbstring'];
foreach ($extensions as $ext) {
    echo "<tr><td><strong>Extension: $ext</strong></td><td>";
    if (extension_loaded($ext)) {
        echo "<span class='success'>✓ โหลดแล้ว</span>";
    } else {
        echo "<span class='error'>✗ ไม่ได้โหลด</span>";
    }
    echo "</td></tr>";
}

// 5. ทดสอบการเชื่อมต่อฐานข้อมูล (ถ้ามี config)
if (file_exists(__DIR__ . '/config/config.php')) {
    try {
        require_once __DIR__ . '/config/config.php';
        echo "<tr><td><strong>Config Load:</strong></td><td><span class='success'>✓ โหลด config สำเร็จ</span></td></tr>";

        // ทดสอบเชื่อมต่อฐานข้อมูล
        try {
            $dsn = "mysql:host=" . DB_HOST . ";charset=" . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS);
            echo "<tr><td><strong>MySQL Connection:</strong></td><td><span class='success'>✓ เชื่อมต่อ MySQL สำเร็จ</span></td></tr>";

            // ตรวจสอบว่ามีฐานข้อมูลหรือยัง
            $stmt = $pdo->query("SHOW DATABASES LIKE '" . DB_NAME . "'");
            if ($stmt->rowCount() > 0) {
                echo "<tr><td><strong>Database: " . DB_NAME . "</strong></td><td><span class='success'>✓ พบฐานข้อมูล</span></td></tr>";

                // ลองเชื่อมต่อกับฐานข้อมูล
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
                $pdo = new PDO($dsn, DB_USER, DB_PASS);
                echo "<tr><td><strong>Database Connection:</strong></td><td><span class='success'>✓ เชื่อมต่อฐานข้อมูลสำเร็จ</span></td></tr>";

                // ตรวจสอบตาราง
                $stmt = $pdo->query("SHOW TABLES");
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                echo "<tr><td><strong>Tables:</strong></td><td>";
                if (count($tables) > 0) {
                    echo "<span class='success'>✓ พบ " . count($tables) . " ตาราง</span><br>";
                    echo "<small>" . implode(", ", $tables) . "</small>";
                } else {
                    echo "<span class='error'>✗ ยังไม่มีตาราง (ต้อง Import database/schema.sql)</span>";
                }
                echo "</td></tr>";

            } else {
                echo "<tr><td><strong>Database: " . DB_NAME . "</strong></td><td><span class='error'>✗ ไม่พบฐานข้อมูล (ต้องสร้างก่อน)</span></td></tr>";
            }

        } catch (PDOException $e) {
            echo "<tr><td><strong>Database Error:</strong></td><td><span class='error'>✗ " . $e->getMessage() . "</span></td></tr>";
        }

    } catch (Exception $e) {
        echo "<tr><td><strong>Config Error:</strong></td><td><span class='error'>✗ " . $e->getMessage() . "</span></td></tr>";
    }
}

echo "</table>";

echo "<br><div style='background:white;padding:20px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);'>";
echo "<h2>📋 ขั้นตอนต่อไป</h2>";
echo "<ol>";
echo "<li><strong>ถ้ายังไม่มีฐานข้อมูล:</strong><br>";
echo "- เปิด phpMyAdmin: <a href='http://localhost/phpmyadmin' target='_blank'>http://localhost/phpmyadmin</a><br>";
echo "- Import ไฟล์: <code>database/schema.sql</code></li>";
echo "<li><strong>ถ้าพร้อมแล้ว:</strong><br>";
echo "- เข้าสู่ระบบที่: <a href='login.php'>login.php</a><br>";
echo "- Username: <code>admin</code> / Password: <code>admin123</code></li>";
echo "</ol>";
echo "</div>";

echo "</body></html>";
