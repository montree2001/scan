<?php
/**
 * หน้า Dashboard นักเรียน
 */
session_start();
define('STUDENT_PAGE', true);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/functions.php';

// ตรวจสอบสิทธิ์ (student หรือ admin)
if (!isLoggedIn() || ($_SESSION[SESSION_USER_ROLE] != 'student' && $_SESSION[SESSION_USER_ROLE] != 'admin')) {
    redirect(BASE_URL . '/login.php');
}

$pageTitle = 'หน้าหลัก';
$currentPage = 'dashboard';

$db = getDB();
$userId = $_SESSION[SESSION_USER_ID];

// ดึงข้อมูลนักเรียน
$stmt = $db->prepare("
    SELECT
        s.*,
        u.full_name,
        u.username,
        u.email as user_email,
        (SELECT photo_path FROM student_photos WHERE student_id = s.student_id AND is_primary = 1 LIMIT 1) as photo_path
    FROM students s
    INNER JOIN users u ON s.user_id = u.user_id
    WHERE s.user_id = ? AND s.status = 'active'
");
$stmt->execute([$userId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    // ออกจากระบบและแสดง error
    session_unset();
    session_destroy();
    ?>
    <!DOCTYPE html>
    <html lang="th">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>ข้อผิดพลาด</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
        <style>
            body {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: 'Sarabun', sans-serif;
            }
            .card {
                border-radius: 20px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body text-center p-5">
                            <i class="bi bi-exclamation-triangle text-warning" style="font-size: 64px;"></i>
                            <h3 class="mt-3">ไม่พบข้อมูลนักเรียน</h3>
                            <p class="text-muted">บัญชีของคุณยังไม่มีข้อมูลนักเรียนในระบบ<br>กรุณาติดต่อแอดมินเพื่อเพิ่มข้อมูล</p>
                            <div class="alert alert-info text-start mt-3">
                                <strong>ข้อมูลผู้ใช้:</strong><br>
                                <small>
                                    User ID: <?php echo $_SESSION[SESSION_USER_ID] ?? '-'; ?><br>
                                    Username: <?php echo $_SESSION[SESSION_USERNAME] ?? '-'; ?><br>
                                    Role: <?php echo $_SESSION[SESSION_USER_ROLE] ?? '-'; ?>
                                </small>
                            </div>
                            <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-primary btn-lg mt-3">
                                <i class="bi bi-box-arrow-in-right"></i> กลับไปหน้า Login
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    </body>
    </html>
    <?php
    exit;
}

$studentId = $student['student_id'];

// สถิติวันนี้
$today = date('Y-m-d');
$stmt = $db->prepare("
    SELECT
        COUNT(CASE WHEN log_type = 'in' THEN 1 END) as total_in,
        COUNT(CASE WHEN log_type = 'out' THEN 1 END) as total_out,
        MIN(CASE WHEN log_type = 'in' THEN log_time END) as first_in,
        MAX(CASE WHEN log_type = 'out' THEN log_time END) as last_out
    FROM attendance_logs
    WHERE student_id = ? AND log_date = ?
");
$stmt->execute([$studentId, $today]);
$todayStats = $stmt->fetch(PDO::FETCH_ASSOC);

// สถิติเดือนนี้
$stmt = $db->prepare("
    SELECT
        COUNT(DISTINCT log_date) as days_attended,
        COUNT(CASE WHEN log_type = 'in' THEN 1 END) as total_in,
        COUNT(CASE WHEN log_type = 'out' THEN 1 END) as total_out
    FROM attendance_logs
    WHERE student_id = ?
    AND YEAR(log_date) = YEAR(CURDATE())
    AND MONTH(log_date) = MONTH(CURDATE())
");
$stmt->execute([$studentId]);
$monthStats = $stmt->fetch(PDO::FETCH_ASSOC);

// บันทึกล่าสุด 5 รายการ
$stmt = $db->prepare("
    SELECT * FROM attendance_logs
    WHERE student_id = ?
    ORDER BY log_date DESC, log_time DESC
    LIMIT 5
");
$stmt->execute([$studentId]);
$recentLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ตรวจสอบสถานะปัจจุบัน (อยู่ในวิทยาลัยหรือไม่)
$stmt = $db->prepare("
    SELECT log_type FROM attendance_logs
    WHERE student_id = ? AND log_date = ?
    ORDER BY created_at DESC
    LIMIT 1
");
$stmt->execute([$studentId, $today]);
$lastLog = $stmt->fetch(PDO::FETCH_ASSOC);
$isInside = ($lastLog && $lastLog['log_type'] == 'in');

// ตรวจสอบข้อมูลที่ยังไม่ครบ
$incompleteData = [];
if (empty($student['photo_path'])) {
    $incompleteData[] = 'รูปภาพประจำตัว';
}
if (empty($student['phone'])) {
    $incompleteData[] = 'เบอร์โทรศัพท์';
}
if (empty($student['email']) && empty($student['user_email'])) {
    $incompleteData[] = 'อีเมล';
}

// นับจำนวนยานพาหนะ
$stmt = $db->prepare("SELECT COUNT(*) as total FROM student_vehicles WHERE student_id = ? AND is_active = 1");
$stmt->execute([$studentId]);
$vehicleCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

include 'includes/header.php';
?>

<!-- Status Alert -->
<?php if ($isInside): ?>
<div class="alert alert-success d-flex align-items-center">
    <i class="bi bi-check-circle-fill fs-3 me-3"></i>
    <div>
        <strong>คุณอยู่ในวิทยาลัย</strong><br>
        <small>เข้ามาเมื่อ: <?php echo formatTime($todayStats['first_in']); ?></small>
    </div>
</div>
<?php else: ?>
<div class="alert alert-warning d-flex align-items-center">
    <i class="bi bi-exclamation-triangle-fill fs-3 me-3"></i>
    <div>
        <strong>คุณอยู่นอกวิทยาลัย</strong><br>
        <small>กรุณาสแกน QR-Code เพื่อบันทึกเข้า</small>
    </div>
</div>
<?php endif; ?>

<!-- Incomplete Data Warning -->
<?php if (!empty($incompleteData)): ?>
<div class="alert alert-info">
    <i class="bi bi-info-circle"></i>
    <strong>ข้อมูลของคุณยังไม่สมบูรณ์</strong><br>
    กรุณาเพิ่มข้อมูล: <?php echo implode(', ', $incompleteData); ?>
    <a href="profile.php" class="alert-link">แก้ไขที่นี่</a>
</div>
<?php endif; ?>

<!-- Today Stats -->
<div class="row g-3">
    <div class="col-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
            <i class="bi bi-box-arrow-in-right"></i>
            <h3><?php echo $todayStats['total_in'] ?? 0; ?></h3>
            <p>เข้าวันนี้</p>
        </div>
    </div>
    <div class="col-6">
        <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
            <i class="bi bi-box-arrow-right"></i>
            <h3><?php echo $todayStats['total_out'] ?? 0; ?></h3>
            <p>ออกวันนี้</p>
        </div>
    </div>
</div>

<!-- Month Stats -->
<div class="row g-3 mt-2">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h6 class="mb-3"><i class="bi bi-calendar-month"></i> สถิติเดือนนี้</h6>
                <div class="row text-center">
                    <div class="col-4">
                        <h4 class="text-primary mb-0"><?php echo $monthStats['days_attended'] ?? 0; ?></h4>
                        <small class="text-muted">วันที่มา</small>
                    </div>
                    <div class="col-4">
                        <h4 class="text-success mb-0"><?php echo $monthStats['total_in'] ?? 0; ?></h4>
                        <small class="text-muted">ครั้งเข้า</small>
                    </div>
                    <div class="col-4">
                        <h4 class="text-danger mb-0"><?php echo $monthStats['total_out'] ?? 0; ?></h4>
                        <small class="text-muted">ครั้งออก</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="card mt-3">
    <div class="card-header">
        <i class="bi bi-lightning-charge-fill"></i> เมนูด่วน
    </div>
    <div class="card-body">
        <div class="row g-2">
            <div class="col-6">
                <a href="qrcode.php" class="btn btn-primary w-100">
                    <i class="bi bi-qr-code"></i><br>
                    แสดง QR Code
                </a>
            </div>
            <div class="col-6">
                <a href="profile.php" class="btn btn-info w-100">
                    <i class="bi bi-person-fill"></i><br>
                    แก้ไขโปรไฟล์
                </a>
            </div>
            <div class="col-6">
                <a href="vehicle.php" class="btn btn-success w-100">
                    <i class="bi bi-car-front-fill"></i><br>
                    ยานพาหนะ (<?php echo $vehicleCount; ?>)
                </a>
            </div>
            <div class="col-6">
                <a href="history.php" class="btn btn-warning w-100">
                    <i class="bi bi-clock-history"></i><br>
                    ดูประวัติ
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="card mt-3">
    <div class="card-header">
        <i class="bi bi-clock-history"></i> บันทึกล่าสุด
    </div>
    <div class="card-body">
        <?php if (empty($recentLogs)): ?>
            <p class="text-muted text-center">ยังไม่มีบันทึกการเข้าออก</p>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php foreach ($recentLogs as $log): ?>
                    <div class="list-group-item px-0">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <?php if ($log['log_type'] == 'in'): ?>
                                    <i class="bi bi-box-arrow-in-right text-success fs-5"></i>
                                    <strong class="ms-2">เข้า</strong>
                                <?php else: ?>
                                    <i class="bi bi-box-arrow-right text-danger fs-5"></i>
                                    <strong class="ms-2">ออก</strong>
                                <?php endif; ?>
                                <br>
                                <small class="text-muted ms-4">
                                    <?php echo thaiDate($log['log_date']); ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <strong><?php echo formatTime($log['log_time']); ?></strong><br>
                                <small class="text-muted"><?php echo clean($log['note'] ?? ''); ?></small>
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
        <?php endif; ?>
    </div>
</div>

<!-- Student Info -->
<div class="card mt-3 mb-4">
    <div class="card-header">
        <i class="bi bi-person-badge"></i> ข้อมูลของฉัน
    </div>
    <div class="card-body">
        <table class="table table-sm table-borderless mb-0">
            <tr>
                <td width="120"><i class="bi bi-person"></i> ชื่อ-นามสกุล</td>
                <td><strong><?php echo clean($student['first_name'] . ' ' . $student['last_name']); ?></strong></td>
            </tr>
            <tr>
                <td><i class="bi bi-credit-card-2-front"></i> รหัสนักเรียน</td>
                <td><?php echo clean($student['student_code']); ?></td>
            </tr>
            <tr>
                <td><i class="bi bi-book"></i> ชั้นเรียน</td>
                <td><?php echo clean($student['class'] ?? '-'); ?></td>
            </tr>
            <tr>
                <td><i class="bi bi-telephone"></i> เบอร์โทร</td>
                <td><?php echo clean($student['phone'] ?? '-'); ?></td>
            </tr>
            <tr>
                <td><i class="bi bi-envelope"></i> อีเมล</td>
                <td><?php echo clean($student['email'] ?? $student['user_email'] ?? '-'); ?></td>
            </tr>
        </table>
        <div class="text-center mt-3">
            <a href="profile.php" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-pencil"></i> แก้ไขข้อมูล
            </a>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
