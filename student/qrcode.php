<?php
/**
 * หน้าแสดง QR-Code สำหรับสแกนเข้าออก
 */
session_start();
define('STUDENT_PAGE', true);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/functions.php';

// ตรวจสอบสิทธิ์
if (!isLoggedIn() || ($_SESSION[SESSION_USER_ROLE] != 'student' && $_SESSION[SESSION_USER_ROLE] != 'admin')) {
    redirect(BASE_URL . '/login.php');
}

$pageTitle = 'QR Code';
$currentPage = 'qrcode';

$db = getDB();
$userId = $_SESSION[SESSION_USER_ID];

// ดึงข้อมูลนักเรียน
$stmt = $db->prepare("
    SELECT s.*, u.full_name, u.username
    FROM students s
    LEFT JOIN users u ON s.user_id = u.user_id
    WHERE u.user_id = ?
");
$stmt->execute([$userId]);
$student = $stmt->fetch();

if (!$student) {
    setAlert('danger', 'ไม่พบข้อมูลนักเรียน');
    redirect(BASE_URL . '/login.php');
}

$studentId = $student['student_id'];
$studentCode = $student['student_code'];

// ตรวจสอบหรือสร้าง QR-Code
$stmt = $db->prepare("SELECT * FROM qr_codes WHERE student_id = ? AND status = 'active'");
$stmt->execute([$studentId]);
$qrCode = $stmt->fetch();

if (!$qrCode) {
    // สร้าง QR-Code ใหม่
    $qrData = base64_encode(json_encode([
        'type' => 'student_scan',
        'student_id' => $studentId,
        'student_code' => $studentCode,
        'timestamp' => time()
    ]));

    $qrCodePath = 'uploads/qrcodes/student_' . $studentId . '.png';
    $fullPath = '../' . $qrCodePath;

    // สร้าง QR-Code ด้วย API (ใช้ฟรีจาก quickchart.io)
    $qrUrl = 'https://quickchart.io/qr?text=' . urlencode($qrData) . '&size=400&margin=2';

    // ดาวน์โหลดและบันทึก QR-Code
    $qrImageData = @file_get_contents($qrUrl);
    if ($qrImageData) {
        file_put_contents($fullPath, $qrImageData);

        // บันทึกในฐานข้อมูล
        $stmt = $db->prepare("
            INSERT INTO qr_codes (student_id, qr_data, qr_image_path, status)
            VALUES (?, ?, ?, 'active')
        ");
        $stmt->execute([$studentId, $qrData, $qrCodePath]);

        logActivity($userId, 'generate_qrcode', 'สร้าง QR-Code');

        // ดึงข้อมูล QR ที่สร้างใหม่
        $qrCode = [
            'qr_data' => $qrData,
            'qr_image_path' => $qrCodePath,
            'created_at' => date('Y-m-d H:i:s')
        ];
    }
}

include 'includes/header.php';
?>

<!-- QR Code Display -->
<div class="qr-display">
    <?php if ($qrCode): ?>
        <div class="mb-3">
            <img src="<?php echo BASE_URL . '/' . $qrCode['qr_image_path']; ?>?v=<?php echo time(); ?>"
                 alt="QR Code"
                 class="img-fluid"
                 style="max-width: 300px;">
        </div>

        <h5 class="mb-2"><?php echo clean($student['first_name'] . ' ' . $student['last_name']); ?></h5>
        <p class="text-muted mb-3"><?php echo clean($studentCode); ?></p>

        <div class="alert alert-info">
            <i class="bi bi-info-circle"></i>
            <strong>วิธีใช้งาน</strong><br>
            นำ QR Code นี้ให้เจ้าหน้าที่สแกนเพื่อบันทึกการเข้าออกวิทยาลัย
        </div>

        <div class="d-grid gap-2">
            <button onclick="downloadQR()" class="btn btn-primary">
                <i class="bi bi-download"></i> ดาวน์โหลด QR Code
            </button>
            <button onclick="shareQR()" class="btn btn-success">
                <i class="bi bi-share"></i> แชร์ QR Code
            </button>
            <button onclick="window.print()" class="btn btn-secondary">
                <i class="bi bi-printer"></i> พิมพ์ QR Code
            </button>
        </div>

        <div class="text-center mt-4">
            <small class="text-muted">
                สร้างเมื่อ: <?php echo thaiDate($qrCode['created_at']); ?>
            </small>
        </div>

    <?php else: ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i>
            <strong>ไม่สามารถสร้าง QR-Code ได้</strong><br>
            กรุณาลองใหม่อีกครั้งหรือติดต่อแอดมิน
        </div>
        <a href="qrcode.php" class="btn btn-primary">
            <i class="bi bi-arrow-clockwise"></i> ลองใหม่
        </a>
    <?php endif; ?>
</div>

<!-- Usage Guide -->
<div class="card mt-3">
    <div class="card-header">
        <i class="bi bi-lightbulb"></i> คำแนะนำการใช้งาน
    </div>
    <div class="card-body">
        <ol class="mb-0">
            <li class="mb-2">เปิดหน้านี้บนมือถือของคุณ</li>
            <li class="mb-2">นำ QR Code ให้เจ้าหน้าที่สแกนเพื่อบันทึกเข้า</li>
            <li class="mb-2">เมื่อออกจากวิทยาลัย ให้สแกนอีกครั้งเพื่อบันทึกออก</li>
            <li class="mb-0">สามารถดาวน์โหลดหรือพิมพ์ QR Code เก็บไว้ใช้ได้</li>
        </ol>
    </div>
</div>

<!-- Recent Scans -->
<?php
$stmt = $db->prepare("
    SELECT * FROM attendance_logs
    WHERE student_id = ?
    ORDER BY log_date DESC, log_time DESC
    LIMIT 10
");
$stmt->execute([$studentId]);
$recentScans = $stmt->fetchAll();
?>

<?php if (!empty($recentScans)): ?>
<div class="card mt-3 mb-4">
    <div class="card-header">
        <i class="bi bi-clock-history"></i> การสแกนล่าสุด
    </div>
    <div class="card-body">
        <div class="list-group list-group-flush">
            <?php foreach (array_slice($recentScans, 0, 5) as $scan): ?>
                <div class="list-group-item px-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <?php if ($scan['log_type'] == 'in'): ?>
                                <i class="bi bi-box-arrow-in-right text-success fs-5"></i>
                                <strong class="ms-2">เข้า</strong>
                            <?php else: ?>
                                <i class="bi bi-box-arrow-right text-danger fs-5"></i>
                                <strong class="ms-2">ออก</strong>
                            <?php endif; ?>
                            <br>
                            <small class="text-muted ms-4">
                                <?php echo thaiDate($scan['log_date']); ?>
                            </small>
                        </div>
                        <div class="text-end">
                            <strong><?php echo formatTime($scan['log_time']); ?></strong>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-3">
            <a href="history.php" class="btn btn-sm btn-outline-primary">
                ดูทั้งหมด <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
@media print {
    .top-nav, .bottom-nav, .card-header, .btn, .alert {
        display: none !important;
    }
    .qr-display {
        box-shadow: none;
        border: 2px solid #000;
        page-break-after: always;
    }
    .qr-display img {
        border: 3px solid #000 !important;
    }
}
</style>

<script>
function downloadQR() {
    const qrImage = document.querySelector('.qr-display img');
    const link = document.createElement('a');
    link.href = qrImage.src;
    link.download = 'QR-Code-<?php echo $studentCode; ?>.png';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

function shareQR() {
    if (navigator.share) {
        const qrImage = document.querySelector('.qr-display img');
        fetch(qrImage.src)
            .then(res => res.blob())
            .then(blob => {
                const file = new File([blob], 'QR-Code-<?php echo $studentCode; ?>.png', { type: 'image/png' });
                navigator.share({
                    title: 'QR Code - <?php echo clean($student["first_name"]); ?>',
                    text: 'QR Code สำหรับสแกนเข้าออกวิทยาลัย',
                    files: [file]
                }).catch(err => console.log('Error sharing:', err));
            });
    } else {
        alert('เบราว์เซอร์ของคุณไม่รองรับการแชร์');
    }
}

// Auto-refresh QR code every 30 seconds to keep session alive
setInterval(function() {
    const img = document.querySelector('.qr-display img');
    if (img) {
        const src = img.src.split('?')[0];
        img.src = src + '?v=' + Date.now();
    }
}, 30000);
</script>

<?php include 'includes/footer.php'; ?>
