<?php
// ทดสอบการเชื่อมต่อฐานข้อมูล
require_once 'config/config.php';
require_once 'config/database.php';

try {
    $db = getDB();
    echo "✅ เชื่อมต่อฐานข้อมูลสำเร็จ\n\n";

    // ทดสอบค้นหานักเรียนด้วยเลขบัตรประชาชน
    $test_id_card = '1101000268630';
    $stmt = $db->prepare("SELECT * FROM students WHERE id_card = ? AND status = 'active'");
    $stmt->execute([$test_id_card]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($student) {
        echo "✅ พบข้อมูลนักเรียน:\n";
        echo "ID: " . $student['student_id'] . "\n";
        echo "รหัส: " . $student['student_code'] . "\n";
        echo "ชื่อ: " . $student['first_name'] . " " . $student['last_name'] . "\n";
        echo "เลขบัตร: " . $student['id_card'] . "\n\n";
    } else {
        echo "❌ ไม่พบข้อมูลนักเรียน\n\n";
    }

    // ตรวจสอบตาราง attendance_logs
    $stmt = $db->query("SELECT COUNT(*) as total FROM attendance_logs");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "📊 จำนวนบันทึกการเช็คอิน: " . $result['total'] . " รายการ\n\n";

    // ตรวจสอบว่ามีคอลัมน์ทั้งหมดหรือไม่
    $stmt = $db->query("SHOW COLUMNS FROM attendance_logs");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "📋 คอลัมน์ใน attendance_logs:\n";
    foreach ($columns as $col) {
        echo "  - " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
