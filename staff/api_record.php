<?php
/**
 * API สำหรับบันทึกการเข้า-ออกของนักเรียน
 * รับค่าจาก POST: student_code, log_type (in/out), scan_method (qr_scan/manual)
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

// รับข้อมูลจาก POST
$student_code = isset($_POST['student_code']) ? trim($_POST['student_code']) : '';
$log_type = isset($_POST['log_type']) ? trim($_POST['log_type']) : '';
$scan_method = isset($_POST['scan_method']) ? trim($_POST['scan_method']) : 'manual';
$vehicle_id = isset($_POST['vehicle_id']) ? (int)$_POST['vehicle_id'] : null;
$notes = isset($_POST['notes']) ? trim($_POST['notes']) : '';

// Validate
if (empty($student_code)) {
    echo json_encode([
        'success' => false,
        'message' => 'กรุณาระบุรหัสนักเรียน'
    ]);
    exit;
}

if (!in_array($log_type, ['in', 'out'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ประเภทการบันทึกไม่ถูกต้อง'
    ]);
    exit;
}

if (!in_array($scan_method, ['qr_scan', 'manual'])) {
    echo json_encode([
        'success' => false,
        'message' => 'วิธีการบันทึกไม่ถูกต้อง'
    ]);
    exit;
}

try {
    $db = getDB();

    // ค้นหานักเรียนจาก student_code
    $stmt = $db->prepare("SELECT student_id, first_name, last_name, class FROM students WHERE student_code = ? AND status = 'active'");
    $stmt->execute([$student_code]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo json_encode([
            'success' => false,
            'message' => 'ไม่พบข้อมูลนักเรียนหรือนักเรียนไม่ได้ใช้งาน'
        ]);
        exit;
    }

    $student_id = $student['student_id'];

    // ค้นหา QR Code ของนักเรียน (ถ้ามี)
    $qr_id = null;
    if ($scan_method === 'qr_scan') {
        $stmt = $db->prepare("SELECT qr_id FROM qr_codes WHERE student_id = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$student_id]);
        $qr = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($qr) {
            $qr_id = $qr['qr_id'];
        }
    }

    // ตรวจสอบว่ามีการบันทึกซ้ำหรือไม่ (ในรอบ 2 นาที)
    $stmt = $db->prepare("
        SELECT log_id FROM attendance_logs
        WHERE student_id = ?
        AND log_type = ?
        AND created_at >= DATE_SUB(NOW(), INTERVAL 2 MINUTE)
        LIMIT 1
    ");
    $stmt->execute([$student_id, $log_type]);
    if ($stmt->fetch()) {
        echo json_encode([
            'success' => false,
            'message' => 'มีการบันทึกซ้ำในระยะเวลาใกล้เคียง'
        ]);
        exit;
    }

    // บันทึกข้อมูลการเข้า-ออก
    $stmt = $db->prepare("
        INSERT INTO attendance_logs
        (student_id, qr_id, log_type, log_date, log_time, vehicle_id, scan_method, recorded_by, notes)
        VALUES (?, ?, ?, CURDATE(), CURTIME(), ?, ?, ?, ?)
    ");

    $recorded_by = $_SESSION[SESSION_USER_ID];

    $result = $stmt->execute([
        $student_id,
        $qr_id,
        $log_type,
        $vehicle_id,
        $scan_method,
        $recorded_by,
        $notes
    ]);

    if ($result) {
        // อัปเดต last_used ของ QR Code
        if ($qr_id) {
            $stmt = $db->prepare("UPDATE qr_codes SET last_used = NOW() WHERE qr_id = ?");
            $stmt->execute([$qr_id]);
        }

        // บันทึก activity log
        logActivity(
            $recorded_by,
            'attendance_record',
            "บันทึกการ{$log_type} ของนักเรียน {$student['first_name']} {$student['last_name']} ({$student_code})"
        );

        echo json_encode([
            'success' => true,
            'message' => 'บันทึกสำเร็จ',
            'data' => [
                'student' => [
                    'name' => $student['first_name'] . ' ' . $student['last_name'],
                    'code' => $student_code,
                    'class' => $student['class']
                ],
                'log_type' => $log_type,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ]);
    } else {
        throw new Exception('ไม่สามารถบันทึกข้อมูลได้');
    }

} catch (PDOException $e) {
    error_log("Database error in api_record.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในระบบฐานข้อมูล'
    ]);
} catch (Exception $e) {
    error_log("Error in api_record.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
