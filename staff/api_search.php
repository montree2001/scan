<?php
/**
 * API สำหรับค้นหานักเรียน
 * รับค่าจาก GET: q (query string)
 */
session_start();
header('Content-Type: application/json; charset=utf-8');

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/functions.php';

// ตรวจสอบว่า Login แล้วหรือยัง
if (!isLoggedIn()) {
    echo json_encode([
        'success' => false,
        'message' => 'กรุณาเข้าสู่ระบบ'
    ]);
    exit;
}

$role = $_SESSION[SESSION_ROLE];
if ($role !== 'staff' && $role !== 'admin') {
    echo json_encode([
        'success' => false,
        'message' => 'ไม่มีสิทธิ์เข้าถึง'
    ]);
    exit;
}

// รับค่าการค้นหา
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($query) < 2) {
    echo json_encode([
        'success' => false,
        'message' => 'กรุณาพิมพ์อย่างน้อย 2 ตัวอักษร'
    ]);
    exit;
}

try {
    $db = getDB();

    // ค้นหาจาก student_code, first_name, last_name
    $searchTerm = "%{$query}%";

    $stmt = $db->prepare("
        SELECT
            student_id,
            student_code,
            first_name,
            last_name,
            nickname,
            class,
            grade
        FROM students
        WHERE status = 'active'
        AND (
            student_code LIKE ?
            OR first_name LIKE ?
            OR last_name LIKE ?
            OR nickname LIKE ?
            OR CONCAT(first_name, ' ', last_name) LIKE ?
        )
        ORDER BY class, first_name
        LIMIT 20
    ");

    $stmt->execute([
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm,
        $searchTerm
    ]);

    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'students' => $students,
        'count' => count($students)
    ]);

} catch (PDOException $e) {
    error_log("Database error in api_search.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในการค้นหา'
    ]);
}
