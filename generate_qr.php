<?php
/**
 * ไฟล์สำหรับสร้างและดาวน์โหลด QR Code ของนักเรียน
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/functions.php';

// โหลด autoloader ของ Composer (จาก root directory)
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    die('ไม่พบไฟล์ autoloader ของ Composer. โปรดติดตั้ง dependencies โดยใช้คำสั่ง "composer install"');
}

// ตรวจสอบว่ามีการติดตั้งไลบรารีสร้าง QR Code แล้วหรือยัง
if (!class_exists('Endroid\QrCode\QrCode')) {
    die('ไม่พบไลบรารี PHP QR Code โปรดติดตั้งผ่าน Composer');
}

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

// ตรวจสอบว่ามีการส่ง student_id หรือไม่
$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($student_id <= 0) {
    die('ไม่พบ ID ของนักเรียน');
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, student_id, first_name, last_name, class, phone FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        die('ไม่พบข้อมูลนักเรียน');
    }

    // สร้างข้อมูลสำหรับ QR Code ที่มีข้อมูลที่จำเป็นสำหรับระบบแสกนเข้าออก
    $qr_data = json_encode([
        'student_id' => $student['student_id'],
        'first_name' => $student['first_name'],
        'last_name' => $student['last_name'],
        'class' => $student['class'],
        'timestamp' => time(),
        'system' => 'college_scan_system'
    ]);
    
    // สร้าง QR Code
    $qrCode = new QrCode($qr_data);
    $qrCode->setSize(400); // ขนาดใหญ่ขึ้นสำหรับการพิมพ์
    $qrCode->setMargin(10);
    
    $writer = new PngWriter();
    $qrCodeResult = $writer->write($qrCode);
    
    // ตั้งชื่อไฟล์สำหรับดาวน์โหลด
    $filename = 'qrcode_' . $student['student_id'] . '_' . date('Y-m-d') . '.png';
    
    // ส่งออกไฟล์ภาพ
    header('Content-Type: image/png');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $qrCodeResult->getString();
} catch (Exception $e) {
    error_log("Error generating QR code: " . $e->getMessage());
    die('เกิดข้อผิดพลาดในการสร้าง QR Code');
}