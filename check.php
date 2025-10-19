<?php
/**
 * ไฟล์ตรวจสอบแบบง่าย
 */
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Quick Check</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .box { background: white; padding: 20px; margin: 10px 0; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: green; }
        .error { color: red; }
        h1 { color: #333; }
    </style>
</head>
<body>
    <h1>✅ ตรวจสอบระบบอย่างรวดเร็ว</h1>

    <div class="box">
        <h3>1. PHP ทำงานได้</h3>
        <p class="success">✓ PHP Version: <?php echo phpversion(); ?></p>
    </div>

    <div class="box">
        <h3>2. ไฟล์ Config</h3>
        <?php
        $config_file = __DIR__ . '/config/config.php';
        if (file_exists($config_file)) {
            echo "<p class='success'>✓ config.php พบแล้ว</p>";

            // ลอง require
            try {
                require_once $config_file;
                echo "<p class='success'>✓ โหลด config.php สำเร็จ</p>";
                echo "<p>- DB_NAME: " . DB_NAME . "</p>";
                echo "<p>- BASE_URL: " . BASE_URL . "</p>";
            } catch (Exception $e) {
                echo "<p class='error'>✗ โหลด config.php ไม่ได้: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p class='error'>✗ ไม่พบ config.php</p>";
            echo "<p>พยายามหาที่: $config_file</p>";
        }
        ?>
    </div>

    <div class="box">
        <h3>3. ฐานข้อมูล</h3>
        <?php
        if (defined('DB_HOST')) {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
                $pdo = new PDO($dsn, DB_USER, DB_PASS);
                echo "<p class='success'>✓ เชื่อมต่อ MySQL สำเร็จ</p>";

                // เช็คว่ามี database หรือยัง
                $stmt = $pdo->query("SHOW DATABASES LIKE '" . DB_NAME . "'");
                if ($stmt->rowCount() > 0) {
                    echo "<p class='success'>✓ พบฐานข้อมูล: " . DB_NAME . "</p>";
                } else {
                    echo "<p class='error'>✗ ยังไม่มีฐานข้อมูล: " . DB_NAME . "</p>";
                    echo "<p><strong>ขั้นตอนต่อไป:</strong> Import ไฟล์ database/schema.sql</p>";
                }
            } catch (PDOException $e) {
                echo "<p class='error'>✗ เชื่อมต่อไม่ได้: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p class='error'>✗ ไม่สามารถโหลด config</p>";
        }
        ?>
    </div>

    <div class="box">
        <h3>4. ขั้นตอนต่อไป</h3>
        <?php
        if (defined('DB_HOST')) {
            $dsn = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
            try {
                $pdo = new PDO($dsn, DB_USER, DB_PASS);
                $stmt = $pdo->query("SHOW DATABASES LIKE '" . DB_NAME . "'");

                if ($stmt->rowCount() > 0) {
                    echo "<p class='success'>✅ ระบบพร้อมใช้งาน!</p>";
                    echo "<p><a href='login.php' style='background:#4CAF50;color:white;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;'>เข้าสู่ระบบ</a></p>";
                } else {
                    echo "<p class='error'>⚠️ ต้องสร้างฐานข้อมูลก่อน</p>";
                    echo "<ol>";
                    echo "<li>เปิด <a href='http://localhost/phpmyadmin' target='_blank'>phpMyAdmin</a></li>";
                    echo "<li>คลิก <strong>Import</strong></li>";
                    echo "<li>เลือกไฟล์: <code>database/schema.sql</code></li>";
                    echo "<li>คลิก <strong>Go</strong></li>";
                    echo "</ol>";
                }
            } catch (PDOException $e) {
                echo "<p class='error'>⚠️ เชื่อมต่อฐานข้อมูลไม่ได้</p>";
            }
        }
        ?>
    </div>

    <div class="box">
        <h3>ลิงก์เพิ่มเติม</h3>
        <ul>
            <li><a href="test.php">ทดสอบระบบแบบละเอียด</a></li>
            <li><a href="login.php">หน้าเข้าสู่ระบบ</a></li>
            <li><a href="README_INSTALLATION.md">คู่มือการติดตั้ง</a></li>
        </ul>
    </div>
</body>
</html>
