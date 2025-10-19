<?php
/**
 * API สำหรับบันทึก/แก้ไขข้อมูลนักเรียน
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

    // รับข้อมูลจากฟอร์ม
    $studentId = $_POST['student_id'] ?? null;
    $studentCode = trim($_POST['student_code'] ?? '');
    $idCard = trim($_POST['id_card'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $class = trim($_POST['class'] ?? '');
    $major = trim($_POST['major'] ?? '');
    $gender = $_POST['gender'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Validate required fields
    if (empty($studentCode) || empty($firstName) || empty($lastName)) {
        echo json_encode([
            'success' => false,
            'message' => 'กรุณากรอกข้อมูลที่จำเป็น (รหัสนักเรียน, ชื่อ, นามสกุล)'
        ]);
        exit;
    }

    if ($studentId) {
        // อัพเดทข้อมูล
        $stmt = $db->prepare("
            UPDATE students SET
                student_code = ?,
                id_card = ?,
                first_name = ?,
                last_name = ?,
                class = ?,
                major = ?,
                gender = ?,
                phone = ?,
                email = ?,
                updated_at = NOW()
            WHERE student_id = ?
        ");

        $result = $stmt->execute([
            $studentCode,
            $idCard ?: null,
            $firstName,
            $lastName,
            $class ?: null,
            $major ?: null,
            $gender ?: null,
            $phone ?: null,
            $email ?: null,
            $studentId
        ]);

        if ($result) {
            // Log activity
            $userId = $_SESSION['user_id'] ?? $_SESSION[SESSION_USER_ID ?? 'user_id'];
            logActivity($userId, 'update_student', "แก้ไขข้อมูลนักเรียน: $firstName $lastName (ID: $studentId)");

            echo json_encode([
                'success' => true,
                'message' => 'แก้ไขข้อมูลสำเร็จ',
                'student_id' => $studentId
            ]);
        } else {
            throw new Exception('ไม่สามารถแก้ไขข้อมูลได้');
        }

    } else {
        // เพิ่มข้อมูลใหม่

        // ตรวจสอบรหัสนักเรียนซ้ำ
        $checkStmt = $db->prepare("SELECT student_id FROM students WHERE student_code = ?");
        $checkStmt->execute([$studentCode]);
        if ($checkStmt->rowCount() > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'รหัสนักเรียนนี้มีในระบบแล้ว'
            ]);
            exit;
        }

        $stmt = $db->prepare("
            INSERT INTO students (
                student_code, id_card, first_name, last_name,
                class, major, gender, phone, email, status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");

        $result = $stmt->execute([
            $studentCode,
            $idCard ?: null,
            $firstName,
            $lastName,
            $class ?: null,
            $major ?: null,
            $gender ?: null,
            $phone ?: null,
            $email ?: null
        ]);

        if ($result) {
            $newStudentId = $db->lastInsertId();

            // Log activity
            $userId = $_SESSION['user_id'] ?? $_SESSION[SESSION_USER_ID ?? 'user_id'];
            logActivity($userId, 'add_student', "เพิ่มนักเรียน: $firstName $lastName (ID: $newStudentId)");

            echo json_encode([
                'success' => true,
                'message' => 'เพิ่มข้อมูลสำเร็จ',
                'student_id' => $newStudentId
            ]);
        } else {
            throw new Exception('ไม่สามารถเพิ่มข้อมูลได้');
        }
    }

} catch (PDOException $e) {
    error_log("Database error in api_save_student.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในระบบฐานข้อมูล: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error in api_save_student.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
