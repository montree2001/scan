<?php
/**
 * API สำหรับลบนักเรียน (Soft Delete)
 */

header('Content-Type: application/json; charset=utf-8');

session_start();

// ตรวจสอบการ login
if (!isset($_SESSION['user_id']) && !isset($_SESSION[SESSION_USER_ID ?? 'user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/functions.php';

try {
    $db = getDB();

    $studentId = $_POST['id'] ?? null;

    if (!$studentId) {
        echo json_encode([
            'success' => false,
            'message' => 'ไม่พบ ID นักเรียน'
        ]);
        exit;
    }

    // ดึงข้อมูลนักเรียนก่อนลบ (สำหรับ log)
    $stmt = $db->prepare("SELECT first_name, last_name, student_code FROM students WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo json_encode([
            'success' => false,
            'message' => 'ไม่พบข้อมูลนักเรียน'
        ]);
        exit;
    }

    // Soft delete (เปลี่ยนสถานะเป็น inactive)
    $deleteStmt = $db->prepare("UPDATE students SET status = 'inactive', updated_at = NOW() WHERE student_id = ?");
    $result = $deleteStmt->execute([$studentId]);

    if ($result) {
        // Log activity
        $userId = $_SESSION['user_id'] ?? $_SESSION[SESSION_USER_ID ?? 'user_id'];
        logActivity($userId, 'delete_student', "ลบนักเรียน: {$student['first_name']} {$student['last_name']} (รหัส: {$student['student_code']}, ID: $studentId)");

        echo json_encode([
            'success' => true,
            'message' => 'ลบข้อมูลสำเร็จ'
        ]);
    } else {
        throw new Exception('ไม่สามารถลบข้อมูลได้');
    }

} catch (PDOException $e) {
    error_log("Database error in api_delete_student.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในระบบฐานข้อมูล'
    ]);
} catch (Exception $e) {
    error_log("Error in api_delete_student.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
