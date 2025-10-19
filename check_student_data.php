<?php
/**
 * สคริปต์ตรวจสอบข้อมูลนักเรียน
 * ใช้สำหรับ debug เมื่อไม่พบข้อมูล
 */
session_start();
require_once 'config/config.php';
require_once 'config/database.php';

// ตรวจสอบว่า Login แล้วหรือยัง
if (!isset($_SESSION[SESSION_USER_ID])) {
    die('กรุณา Login ก่อน');
}

$userId = $_SESSION[SESSION_USER_ID];
$db = getDB();

echo "<h2>ตรวจสอบข้อมูลนักเรียน</h2>";
echo "<hr>";

// 1. ตรวจสอบข้อมูล User
echo "<h3>1. ข้อมูล User ที่ Login:</h3>";
$stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($user);
echo "</pre>";

// 2. ตรวจสอบข้อมูล Student ที่เชื่อมกับ User
echo "<h3>2. ข้อมูล Student ที่เชื่อมกับ User ID {$userId}:</h3>";
$stmt = $db->prepare("SELECT * FROM students WHERE user_id = ?");
$stmt->execute([$userId]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($students)) {
    echo "<p style='color: red;'><strong>❌ ไม่พบข้อมูลนักเรียนที่เชื่อมกับ user_id นี้</strong></p>";
    echo "<p>วิธีแก้ไข:</p>";
    echo "<ul>";
    echo "<li>ใช้ฟังก์ชัน Import ที่หน้า Admin เพื่อนำเข้าข้อมูลนักเรียน</li>";
    echo "<li>หรือสร้างข้อมูลนักเรียนด้วยตัวเอง และเชื่อมกับ user_id = {$userId}</li>";
    echo "</ul>";
} else {
    echo "<pre>";
    print_r($students);
    echo "</pre>";
}

// 3. ดูนักเรียนทั้งหมดในระบบ
echo "<h3>3. รายชื่อนักเรียนทั้งหมดในระบบ:</h3>";
$stmt = $db->query("
    SELECT
        s.student_id,
        s.student_code,
        s.first_name,
        s.last_name,
        s.user_id,
        s.status,
        u.username,
        u.role
    FROM students s
    LEFT JOIN users u ON s.user_id = u.user_id
    ORDER BY s.student_id DESC
    LIMIT 10
");
$allStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>student_id</th><th>student_code</th><th>ชื่อ-นามสกุล</th><th>user_id</th><th>username</th><th>status</th></tr>";
foreach ($allStudents as $s) {
    echo "<tr>";
    echo "<td>{$s['student_id']}</td>";
    echo "<td>{$s['student_code']}</td>";
    echo "<td>{$s['first_name']} {$s['last_name']}</td>";
    echo "<td>" . ($s['user_id'] ?? '<span style="color:red;">NULL</span>') . "</td>";
    echo "<td>" . ($s['username'] ?? '-') . "</td>";
    echo "<td>{$s['status']}</td>";
    echo "</tr>";
}
echo "</table>";

// 4. ตรวจสอบว่ามี User ที่เป็น student แต่ไม่มีข้อมูลใน students table
echo "<h3>4. User ที่เป็น Student แต่ไม่มีข้อมูลในตาราง students:</h3>";
$stmt = $db->query("
    SELECT u.*
    FROM users u
    LEFT JOIN students s ON u.user_id = s.user_id
    WHERE u.role = 'student' AND s.student_id IS NULL
");
$orphanUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($orphanUsers)) {
    echo "<p style='color: green;'>✅ ไม่มี User ที่ไม่มีข้อมูล Student</p>";
} else {
    echo "<p style='color: red;'>❌ พบ User ที่ไม่มีข้อมูล Student:</p>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>user_id</th><th>username</th><th>full_name</th><th>email</th></tr>";
    foreach ($orphanUsers as $u) {
        echo "<tr>";
        echo "<td>{$u['user_id']}</td>";
        echo "<td>{$u['username']}</td>";
        echo "<td>{$u['full_name']}</td>";
        echo "<td>{$u['email']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

echo "<hr>";
echo "<p><a href='index.php'>← กลับหน้าหลัก</a></p>";
