<?php
/**
 * API สำหรับดึงข้อมูลการเช็คอินล่าสุด
 * ใช้แสดงในหน้า checkin_display.php
 */
header('Content-Type: application/json; charset=utf-8');

require_once 'config/config.php';
require_once 'config/database.php';

try {
    $db = getDB();
    $today = date('Y-m-d');

    // ดึงสถิติวันนี้
    $stmt = $db->prepare("
        SELECT
            COUNT(CASE WHEN log_type = 'in' THEN 1 END) as total_in,
            COUNT(CASE WHEN log_type = 'out' THEN 1 END) as total_out
        FROM attendance_logs
        WHERE log_date = ?
    ");
    $stmt->execute([$today]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // นับนักเรียนที่อยู่ในวิทยาลัยปัจจุบัน
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT student_id) as count
        FROM attendance_logs al1
        WHERE log_date = ?
        AND log_type = 'in'
        AND NOT EXISTS (
            SELECT 1 FROM attendance_logs al2
            WHERE al2.student_id = al1.student_id
            AND al2.log_date = ?
            AND al2.log_type = 'out'
            AND al2.created_at > al1.created_at
        )
    ");
    $stmt->execute([$today, $today]);
    $stats['currently_inside'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // ดึงรายการเช็คอินล่าสุด 20 รายการ
    $stmt = $db->prepare("
        SELECT
            al.log_id,
            al.log_type,
            al.log_time,
            al.is_outside_area,
            al.created_at,
            s.student_id,
            s.student_code,
            s.first_name,
            s.last_name,
            s.class,
            (SELECT photo_path FROM student_photos
             WHERE student_id = s.student_id AND is_primary = 1 LIMIT 1) as photo_path
        FROM attendance_logs al
        INNER JOIN students s ON al.student_id = s.student_id
        WHERE al.log_date = ?
        ORDER BY al.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$today]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format เวลา
    foreach ($logs as &$log) {
        $log['log_time'] = date('H:i:s', strtotime($log['log_time']));
        $log['is_outside_area'] = (bool)$log['is_outside_area'];
    }

    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'logs' => $logs,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (PDOException $e) {
    error_log("Database error in api_checkin_display.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'เกิดข้อผิดพลาดในระบบฐานข้อมูล'
    ]);
} catch (Exception $e) {
    error_log("Error in api_checkin_display.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
