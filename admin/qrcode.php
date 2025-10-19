<?php
// กำหนดค่าคงที่สำหรับหน้า Admin
define('ADMIN_PAGE', true);

// เริ่ม session และ include ไฟล์ที่จำเป็น
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

// โหลด autoloader ของ Composer
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    die('ไม่พบไฟล์ autoloader ของ Composer. โปรดติดตั้ง dependencies โดยใช้คำสั่ง "composer install"');
}

// ตรวจสอบสิทธิ์การเข้าถึง
requireAdmin();

// ตรวจสอบว่ามีการติดตั้งไลบรารีสร้าง QR Code แล้วหรือยัง
if (!class_exists('Endroid\QrCode\QrCode')) {
    setAlert('danger', 'ไม่พบไลบรารี PHP QR Code โปรดติดตั้งผ่าน Composer');
    redirect('students.php');
}

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

// ตรวจสอบว่ามีการส่ง student_id หรือไม่
$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($student_id <= 0) {
    setAlert('danger', 'ไม่พบ ID ของนักเรียน');
    redirect('students.php');
}

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT id, student_id, first_name, last_name, class, phone FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        setAlert('danger', 'ไม่พบข้อมูลนักเรียน');
        redirect('students.php');
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
    $qrCode->setSize(300);
    $qrCode->setMargin(10);
    
    $writer = new PngWriter();
    $qrCodeResult = $writer->write($qrCode);
    $qr_code_image = $qrCodeResult->getString();
    
    // ตั้งชื่อหน้าสำหรับ header
    $pageTitle = 'QR Code: ' . clean($student['first_name'] . ' ' . $student['last_name']);
    $currentPage = 'qrcode';
} catch (Exception $e) {
    setAlert('danger', 'เกิดข้อผิดพลาดในการดึงข้อมูลนักเรียน');
    redirect('students.php');
}
?>

<?php require_once 'includes/header.php'; ?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4><i class="bi bi-qr-code"></i> QR Code สำหรับนักเรียน</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h5 class="card-title"><?php echo clean($student['first_name'] . ' ' . $student['last_name']); ?></h5>
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>รหัสนักเรียน:</strong></td>
                                    <td><?php echo clean($student['student_id']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>ชั้นเรียน:</strong></td>
                                    <td><?php echo clean($student['class']); ?></td>
                                </tr>
                                <?php if (!empty($student['phone'])): ?>
                                <tr>
                                    <td><strong>เบอร์โทร:</strong></td>
                                    <td><?php echo clean($student['phone']); ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <td><strong>วันที่สร้าง:</strong></td>
                                    <td><?php echo date('d/m/Y เวลา H:i:s'); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <div class="qr-container text-center p-4 bg-light rounded border">
                                <h5 class="mb-3">QR Code สำหรับแสกนเข้าออก</h5>
                                <div class="d-flex justify-content-center">
                                    <img src="data:image/png;base64,<?php echo base64_encode($qr_code_image); ?>" 
                                         class="img-fluid" alt="QR Code นักเรียน" style="max-width: 250px; height: auto;">
                                </div>
                                <p class="mt-2 text-muted small">
                                    ใช้สำหรับแสกนเข้า-ออกวิทยาลัยผ่านระบบ
                                </p>
                                <div class="mt-3">
                                    <div class="alert alert-info p-2">
                                        <small>
                                            <i class="bi bi-info-circle"></i> 
                                            QR Code นี้มีข้อมูลที่จำเป็นสำหรับระบบทั้งหมด และสามารถใช้ตรวจสอบความถูกต้องได้
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-12 text-center">
                            <a href="students.php" class="btn btn-secondary me-2">
                                <i class="bi bi-arrow-left"></i> กลับไปหน้าจัดการนักเรียน
                            </a>
                            <button class="btn btn-primary me-2" onclick="window.print()">
                                <i class="bi bi-printer"></i> พิมพ์บัตร
                            </button>
                            <a href="../generate_qr.php?id=<?php echo $student['id']; ?>" class="btn btn-success">
                                <i class="bi bi-download"></i> ดาวน์โหลด QR Code
                            </a>
                        </div>
                    </div>
                    
                    <!-- ตัวอย่างการใช้งาน QR Code -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card border">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0"><i class="bi bi-info-circle"></i> ข้อมูลเพิ่มเติม</h5>
                                </div>
                                <div class="card-body">
                                    <p>QR Code นี้ถูกสร้างขึ้นเพื่อใช้ในระบบแสกนเข้าออกวิทยาลัย:</p>
                                    <ul>
                                        <li>สามารถใช้แสกนเข้าออกวิทยาลัยผ่านมือถือหรืออุปกรณ์ที่มีกล้อง</li>
                                        <li>ระบบจะบันทึกเวลาเข้า/ออกพร้อมข้อมูลนักเรียนอัตโนมัติ</li>
                                        <li>สามารถใช้แทนบัตรนักเรียนในการยืนยันตัวตน</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>