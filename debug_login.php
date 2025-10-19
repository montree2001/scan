<?php
/**
 * ไฟล์ Debug การ Login
 */
require_once 'config/config.php';
require_once 'config/database.php';

echo "<style>body{font-family:Arial;padding:20px;background:#f5f5f5;} .box{background:white;padding:20px;margin:10px 0;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);} .success{color:green;} .error{color:red;} pre{background:#f0f0f0;padding:10px;border-radius:5px;overflow:auto;}</style>";

echo "<h1>🔍 ตรวจสอบระบบ Login</h1>";

// 1. ตรวจสอบการเชื่อมต่อฐานข้อมูล
echo "<div class='box'>";
echo "<h3>1. ตรวจสอบการเชื่อมต่อฐานข้อมูล</h3>";
try {
    $db = getDB();
    echo "<p class='success'>✓ เชื่อมต่อฐานข้อมูลสำเร็จ</p>";

    // 2. ตรวจสอบว่ามีตาราง users หรือไม่
    echo "<h3>2. ตรวจสอบตาราง users</h3>";
    $stmt = $db->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "<p class='success'>✓ พบตาราง users</p>";

        // 3. ตรวจสอบจำนวน users
        echo "<h3>3. ตรวจสอบข้อมูล users</h3>";
        $stmt = $db->query("SELECT * FROM users");
        $users = $stmt->fetchAll();

        if (count($users) > 0) {
            echo "<p class='success'>✓ พบ " . count($users) . " users ในระบบ</p>";
            echo "<table border='1' cellpadding='10' style='border-collapse:collapse;width:100%;'>";
            echo "<tr><th>user_id</th><th>username</th><th>full_name</th><th>role</th><th>status</th><th>password (10 ตัวแรก)</th></tr>";
            foreach ($users as $user) {
                echo "<tr>";
                echo "<td>" . $user['user_id'] . "</td>";
                echo "<td><strong>" . htmlspecialchars($user['username']) . "</strong></td>";
                echo "<td>" . htmlspecialchars($user['full_name']) . "</td>";
                echo "<td><span style='color:blue;'>" . $user['role'] . "</span></td>";
                echo "<td><span style='color:" . ($user['status'] == 'active' ? 'green' : 'red') . ";'>" . $user['status'] . "</span></td>";
                echo "<td><code>" . substr($user['password'], 0, 10) . "...</code></td>";
                echo "</tr>";
            }
            echo "</table>";

            // 4. ทดสอบ Login
            echo "<h3>4. ทดสอบ Login</h3>";
            $testUsername = 'admin';
            $testPassword = 'admin123';

            echo "<p>กำลังทดสอบ Login ด้วย:</p>";
            echo "<ul>";
            echo "<li>Username: <strong>$testUsername</strong></li>";
            echo "<li>Password: <strong>$testPassword</strong></li>";
            echo "</ul>";

            $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
            $stmt->execute([$testUsername]);
            $user = $stmt->fetch();

            if ($user) {
                echo "<p class='success'>✓ พบผู้ใช้: " . htmlspecialchars($user['username']) . "</p>";
                echo "<p>Password Hash ในฐานข้อมูล: <code>" . $user['password'] . "</code></p>";

                // ทดสอบ password_verify
                if (password_verify($testPassword, $user['password'])) {
                    echo "<p class='success'>✅ <strong>รหัสผ่านถูกต้อง! Login ควรจะทำงานได้</strong></p>";
                } else {
                    echo "<p class='error'>✗ รหัสผ่านไม่ถูกต้อง</p>";

                    // สร้าง hash ใหม่
                    echo "<h4>🔧 แก้ไข: สร้าง Password Hash ใหม่</h4>";
                    $newHash = password_hash($testPassword, PASSWORD_DEFAULT);
                    echo "<p>Password Hash ใหม่: <code>$newHash</code></p>";

                    // อัพเดท password
                    $updateStmt = $db->prepare("UPDATE users SET password = ? WHERE username = ?");
                    if ($updateStmt->execute([$newHash, $testUsername])) {
                        echo "<p class='success'>✓ อัพเดท password สำเร็จ!</p>";
                        echo "<p><strong>ลอง Login อีกครั้งที่:</strong> <a href='login.php'>login.php</a></p>";
                    } else {
                        echo "<p class='error'>✗ อัพเดท password ไม่สำเร็จ</p>";
                    }
                }
            } else {
                echo "<p class='error'>✗ ไม่พบผู้ใช้ หรือ status ไม่เป็น active</p>";

                // สร้าง admin ใหม่
                echo "<h4>🔧 แก้ไข: สร้าง Admin ใหม่</h4>";
                $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
                $insertStmt = $db->prepare("
                    INSERT INTO users (username, password, email, full_name, role, status)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                try {
                    $insertStmt->execute([
                        'admin',
                        $adminPassword,
                        'admin@college.ac.th',
                        'ผู้ดูแลระบบ',
                        'admin',
                        'active'
                    ]);
                    echo "<p class='success'>✓ สร้าง Admin ใหม่สำเร็จ!</p>";
                    echo "<p><strong>ลอง Login ที่:</strong> <a href='login.php'>login.php</a></p>";
                    echo "<p>Username: <code>admin</code> / Password: <code>admin123</code></p>";
                } catch (Exception $e) {
                    echo "<p class='error'>✗ สร้าง Admin ไม่สำเร็จ: " . $e->getMessage() . "</p>";
                }
            }

        } else {
            echo "<p class='error'>✗ ไม่มี users ในระบบ</p>";

            // สร้าง admin
            echo "<h4>🔧 แก้ไข: สร้าง Admin</h4>";
            $adminPassword = password_hash('admin123', PASSWORD_DEFAULT);
            $insertStmt = $db->prepare("
                INSERT INTO users (username, password, email, full_name, role, status)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insertStmt->execute([
                'admin',
                $adminPassword,
                'admin@college.ac.th',
                'ผู้ดูแลระบบ',
                'admin',
                'active'
            ]);
            echo "<p class='success'>✓ สร้าง Admin สำเร็จ!</p>";
            echo "<p><strong>ลอง Login ที่:</strong> <a href='login.php'>login.php</a></p>";
            echo "<p>Username: <code>admin</code> / Password: <code>admin123</code></p>";
        }

    } else {
        echo "<p class='error'>✗ ไม่พบตาราง users</p>";
        echo "<p><strong>ต้อง Import ฐานข้อมูลก่อน!</strong></p>";
        echo "<ol>";
        echo "<li>เปิด <a href='http://localhost/phpmyadmin' target='_blank'>phpMyAdmin</a></li>";
        echo "<li>คลิก Import</li>";
        echo "<li>เลือกไฟล์: database/schema.sql</li>";
        echo "<li>คลิก Go</li>";
        echo "</ol>";
    }

} catch (Exception $e) {
    echo "<p class='error'>✗ Error: " . $e->getMessage() . "</p>";
}
echo "</div>";

echo "<div class='box'>";
echo "<h3>ลิงก์เพิ่มเติม</h3>";
echo "<ul>";
echo "<li><a href='login.php'>หน้า Login</a></li>";
echo "<li><a href='check.php'>ตรวจสอบระบบ</a></li>";
echo "<li><a href='clear_session.php'>ล้าง Session</a></li>";
echo "</ul>";
echo "</div>";
