<?php
/**
 * API สำหรับเช็คอินสาธารณะ (Public Check-in)
 * ไม่ต้อง Login - ใช้เลขบัตรประชาชนหรือรหัสนักเรียนในการเช็คอิน
 */
header('Content-Type: application/json; charset=utf-8');

require_once 'config/config.php';
require_once 'config/database.php';

// รับข้อมูลจาก POST
$identifier = isset($_POST['identifier']) ? trim($_POST['identifier']) : '';
$identifier_type = isset($_POST['identifier_type']) ? trim($_POST['identifier_type']) : 'id_card';
$log_type = isset($_POST['log_type']) ? trim($_POST['log_type']) : '';
$gps_latitude = isset($_POST['gps_latitude']) ? (float)$_POST['gps_latitude'] : null;
$gps_longitude = isset($_POST['gps_longitude']) ? (float)$_POST['gps_longitude'] : null;
$is_outside_area = isset($_POST['is_outside_area']) ? (int)$_POST['is_outside_area'] : 0;

// Validate input
if (empty($identifier)) {
    echo json_encode([
        'success' => false,
        'message' => 'กรุณากรอกเลขบัตรประชาชนหรือรหัสนักเรียน'
    ]);
    exit;
}

if (!in_array($log_type, ['in', 'out'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ประเภทการเช็คอินไม่ถูกต้อง'
    ]);
    exit;
}

if (!in_array($identifier_type, ['id_card', 'student_code'])) {
    echo json_encode([
        'success' => false,
        'message' => 'ประเภทข้อมูลไม่ถูกต้อง'
    ]);
    exit;
}

// Validate เลขบัตรประชาชน (ถ้าเป็น id_card)
if ($identifier_type === 'id_card') {
    if (!preg_match('/^\d{13}$/', $identifier)) {
        echo json_encode([
            'success' => false,
            'message' => 'เลขบัตรประชาชนต้องเป็นตัวเลข 13 หลัก'
        ]);
        exit;
    }
}

try {
    $db = getDB();

    // ค้นหานักเรียนจาก id_card หรือ student_code
    if ($identifier_type === 'id_card') {
        $stmt = $db->prepare("
            SELECT student_id, student_code, first_name, last_name, class, major
            FROM students
            WHERE id_card = ? AND status = 'active'
        ");
    } else {
        $stmt = $db->prepare("
            SELECT student_id, student_code, first_name, last_name, class, major
            FROM students
            WHERE student_code = ? AND status = 'active'
        ");
    }

    $stmt->execute([$identifier]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo json_encode([
            'success' => false,
            'message' => $identifier_type === 'id_card'
                ? 'ไม่พบข้อมูลนักเรียนด้วยเลขบัตรประชาชนนี้'
                : 'ไม่พบข้อมูลนักเรียนด้วยรหัสนักเรียนนี้'
        ]);
        exit;
    }

    $student_id = $student['student_id'];

    // ตรวจสอบการเช็คอินในวันนี้
    if ($log_type === 'in') {
        // กรณีเช็คอินเข้า: ตรวจสอบว่ามีการเช็คอินเข้าในวันนี้แล้วหรือยัง
        $stmt = $db->prepare("
            SELECT log_id, log_type, log_date, log_time, gps_latitude, gps_longitude, is_outside_area, created_at
            FROM attendance_logs
            WHERE student_id = ?
            AND log_date = CURDATE()
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$student_id]);
        $lastLog = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($lastLog && $lastLog['log_type'] === 'in') {
            // มีการเช็คอินเข้าแล้วในวันนี้ และยังไม่ได้เช็คอินออก
            // ให้แสดงข้อมูลการเช็คอินล่าสุดแทนการบันทึกซ้ำ
            $timestamp = date('d/m/Y H:i:s', strtotime($lastLog['created_at']));

            echo json_encode([
                'success' => true,
                'message' => 'คุณเช็คอินเข้าไปแล้ว',
                'log_type' => 'in',
                'student' => [
                    'student_id' => $student['student_id'],
                    'student_code' => $student['student_code'],
                    'first_name' => $student['first_name'],
                    'last_name' => $student['last_name'],
                    'class' => $student['class'],
                    'major' => $student['major']
                ],
                'timestamp' => $timestamp,
                'is_outside_area' => (bool)$lastLog['is_outside_area'],
                'is_duplicate' => true
            ]);
            exit;
        }
        // ถ้าบันทึกล่าสุดเป็น 'out' หรือไม่มีบันทึกเลย ให้เช็คอินเข้าได้
    } else {
        // กรณีเช็คอินออก: ตรวจสอบว่ามีการเช็คอินเข้าในวันนี้แล้วหรือยัง
        $stmt = $db->prepare("
            SELECT log_id, log_type, log_date, log_time, gps_latitude, gps_longitude, is_outside_area, created_at
            FROM attendance_logs
            WHERE student_id = ?
            AND log_date = CURDATE()
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$student_id]);
        $lastLog = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$lastLog) {
            // ไม่มีการเช็คอินเข้าในวันนี้เลย
            echo json_encode([
                'success' => false,
                'message' => 'กรุณาเช็คอินเข้าก่อนเช็คอินออก'
            ]);
            exit;
        }

        if ($lastLog['log_type'] === 'out') {
            // บันทึกล่าสุดเป็น 'out' แสดงว่าเช็คอินออกไปแล้ว ให้แสดงข้อมูลเดิม
            $timestamp = date('d/m/Y H:i:s', strtotime($lastLog['created_at']));

            echo json_encode([
                'success' => true,
                'message' => 'คุณเช็คอินออกไปแล้ว',
                'log_type' => 'out',
                'student' => [
                    'student_id' => $student['student_id'],
                    'student_code' => $student['student_code'],
                    'first_name' => $student['first_name'],
                    'last_name' => $student['last_name'],
                    'class' => $student['class'],
                    'major' => $student['major']
                ],
                'timestamp' => $timestamp,
                'is_outside_area' => (bool)$lastLog['is_outside_area'],
                'is_duplicate' => true
            ]);
            exit;
        }

        // ถ้าบันทึกล่าสุดเป็น 'in' แสดงว่ายังไม่ได้เช็คอินออก ให้เช็คอินออกได้
    }

    // ค้นหา QR Code ของนักเรียน (ถ้ามี)
    $qr_id = null;
    $stmt = $db->prepare("SELECT qr_id FROM qr_codes WHERE student_id = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$student_id]);
    $qr = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($qr) {
        $qr_id = $qr['qr_id'];
    }

    // บันทึกข้อมูลการเช็คอิน
    $stmt = $db->prepare("
        INSERT INTO attendance_logs
        (student_id, qr_id, log_type, log_date, log_time, scan_method, gps_latitude, gps_longitude, is_outside_area, notes)
        VALUES (?, ?, ?, CURDATE(), CURTIME(), 'public_checkin', ?, ?, ?, ?)
    ");

    $notes = $is_outside_area ? 'เช็คอินนอกพื้นที่ (Public Check-in)' : 'เช็คอินสาธารณะ (Public Check-in)';

    $result = $stmt->execute([
        $student_id,
        $qr_id,
        $log_type,
        $gps_latitude,
        $gps_longitude,
        $is_outside_area,
        $notes
    ]);

    if ($result) {
        // อัปเดต last_used ของ QR Code (ถ้ามี)
        if ($qr_id) {
            $stmt = $db->prepare("UPDATE qr_codes SET last_used = NOW() WHERE qr_id = ?");
            $stmt->execute([$qr_id]);
        }

        // สร้างข้อมูลสำหรับ response
        $response = [
            'success' => true,
            'message' => 'เช็คอินสำเร็จ',
            'log_type' => $log_type,
            'student' => [
                'student_id' => $student['student_id'],
                'student_code' => $student['student_code'],
                'first_name' => $student['first_name'],
                'last_name' => $student['last_name'],
                'class' => $student['class'],
                'major' => $student['major']
            ],
            'timestamp' => date('d/m/Y H:i:s'),
            'is_outside_area' => (bool)$is_outside_area,
            'is_duplicate' => false
        ];

        // ถ้าอยู่นอกพื้นที่ ให้สร้างข้อมูลสำหรับ QR Code
        if ($is_outside_area) {
            $response['qr_code_data'] = [
                'student_id' => $student['student_id'],
                'student_code' => $student['student_code'],
                'first_name' => $student['first_name'],
                'last_name' => $student['last_name'],
                'class' => $student['class'],
                'log_type' => $log_type,
                'timestamp' => time(),
                'system' => 'college_scan_system_public'
            ];
        }

        echo json_encode($response);
    } else {
        throw new Exception('ไม่สามารถบันทึกข้อมูลได้');
    }

} catch (PDOException $e) {
    error_log("Database error in api_checkin.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในระบบฐานข้อมูล'
    ]);
} catch (Exception $e) {
    error_log("Error in api_checkin.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
