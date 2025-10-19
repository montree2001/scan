<?php
/**
 * หน้าประวัติการเข้าออก
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

$pageTitle = 'ประวัติการเข้าออก';
$currentPage = 'history';

$db = getDB();
$userId = $_SESSION[SESSION_USER_ID];

// ดึงข้อมูลนักเรียน
$stmt = $db->prepare("
    SELECT s.*, u.username
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

// ตัวกรอง
$filterDate = $_GET['date'] ?? '';
$filterType = $_GET['type'] ?? '';
$filterMonth = $_GET['month'] ?? date('Y-m');

// Query ประวัติ
$whereClause = ['student_id = ?'];
$params = [$studentId];

if ($filterDate) {
    $whereClause[] = 'log_date = ?';
    $params[] = $filterDate;
} elseif ($filterMonth) {
    $whereClause[] = 'DATE_FORMAT(log_date, "%Y-%m") = ?';
    $params[] = $filterMonth;
}

if ($filterType && in_array($filterType, ['in', 'out'])) {
    $whereClause[] = 'log_type = ?';
    $params[] = $filterType;
}

$sql = "SELECT * FROM attendance_logs WHERE " . implode(' AND ', $whereClause) . " ORDER BY log_date DESC, log_time DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// สถิติเดือนนี้
$stmt = $db->prepare("
    SELECT
        COUNT(DISTINCT log_date) as days_attended,
        COUNT(CASE WHEN log_type = 'in' THEN 1 END) as total_in,
        COUNT(CASE WHEN log_type = 'out' THEN 1 END) as total_out
    FROM attendance_logs
    WHERE student_id = ?
    AND DATE_FORMAT(log_date, '%Y-%m') = ?
");
$stmt->execute([$studentId, $filterMonth]);
$monthStats = $stmt->fetch();

include 'includes/header.php';
?>

<!-- Month Stats -->
<div class="row g-3 mb-3">
    <div class="col-4">
        <div class="card text-center">
            <div class="card-body p-2">
                <h4 class="text-primary mb-0"><?php echo $monthStats['days_attended'] ?? 0; ?></h4>
                <small class="text-muted">วันที่มา</small>
            </div>
        </div>
    </div>
    <div class="col-4">
        <div class="card text-center">
            <div class="card-body p-2">
                <h4 class="text-success mb-0"><?php echo $monthStats['total_in'] ?? 0; ?></h4>
                <small class="text-muted">ครั้งเข้า</small>
            </div>
        </div>
    </div>
    <div class="col-4">
        <div class="card text-center">
            <div class="card-body p-2">
                <h4 class="text-danger mb-0"><?php echo $monthStats['total_out'] ?? 0; ?></h4>
                <small class="text-muted">ครั้งออก</small>
            </div>
        </div>
    </div>
</div>

<!-- Filter Card -->
<div class="card">
    <div class="card-header">
        <i class="bi bi-funnel"></i> ตัวกรอง
    </div>
    <div class="card-body">
        <form method="GET">
            <div class="row g-2">
                <div class="col-6">
                    <label class="form-label small">เดือน</label>
                    <input type="month" class="form-control form-control-sm" name="month"
                           value="<?php echo $filterMonth; ?>">
                </div>
                <div class="col-6">
                    <label class="form-label small">วันที่</label>
                    <input type="date" class="form-control form-control-sm" name="date"
                           value="<?php echo $filterDate; ?>">
                </div>
                <div class="col-12">
                    <label class="form-label small">ประเภท</label>
                    <select class="form-select form-select-sm" name="type">
                        <option value="">ทั้งหมด</option>
                        <option value="in" <?php echo $filterType == 'in' ? 'selected' : ''; ?>>เข้า</option>
                        <option value="out" <?php echo $filterType == 'out' ? 'selected' : ''; ?>>ออก</option>
                    </select>
                </div>
                <div class="col-6">
                    <button type="submit" class="btn btn-primary btn-sm w-100">
                        <i class="bi bi-search"></i> ค้นหา
                    </button>
                </div>
                <div class="col-6">
                    <a href="history.php" class="btn btn-secondary btn-sm w-100">
                        <i class="bi bi-x-circle"></i> ล้าง
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- History List -->
<div class="card mt-3 mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-clock-history"></i> ประวัติ (<?php echo count($logs); ?> รายการ)</span>
        <?php if (!empty($logs)): ?>
            <a href="#" onclick="exportHistory()" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-download"></i>
            </a>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (empty($logs)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox fs-1 text-muted"></i>
                <p class="text-muted mt-2">ไม่พบบันทึกการเข้าออก</p>
            </div>
        <?php else: ?>
            <div class="list-group list-group-flush">
                <?php
                $currentDate = null;
                foreach ($logs as $log):
                    $logDate = $log['log_date'];
                    if ($logDate != $currentDate):
                        $currentDate = $logDate;
                ?>
                    <!-- Date Header -->
                    <div class="list-group-item bg-light">
                        <strong>
                            <i class="bi bi-calendar3"></i>
                            <?php echo thaiDate($logDate); ?>
                        </strong>
                    </div>
                <?php endif; ?>

                <!-- Log Item -->
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <?php if ($log['log_type'] == 'in'): ?>
                                <div class="d-flex align-items-center">
                                    <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center"
                                         style="width: 40px; height: 40px;">
                                        <i class="bi bi-box-arrow-in-right fs-5"></i>
                                    </div>
                                    <div class="ms-3">
                                        <strong>เข้า</strong>
                                        <?php if ($log['note']): ?>
                                            <br><small class="text-muted"><?php echo clean($log['note']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="d-flex align-items-center">
                                    <div class="bg-danger text-white rounded-circle d-flex align-items-center justify-content-center"
                                         style="width: 40px; height: 40px;">
                                        <i class="bi bi-box-arrow-right fs-5"></i>
                                    </div>
                                    <div class="ms-3">
                                        <strong>ออก</strong>
                                        <?php if ($log['note']): ?>
                                            <br><small class="text-muted"><?php echo clean($log['note']); ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="text-end">
                            <h5 class="mb-0"><?php echo formatTime($log['log_time']); ?></h5>
                            <small class="text-muted">
                                <?php
                                $method = $log['scan_method'] ?? 'manual';
                                if ($method == 'qr') {
                                    echo '<i class="bi bi-qr-code"></i> QR';
                                } else {
                                    echo '<i class="bi bi-hand-index"></i> Manual';
                                }
                                ?>
                            </small>
                        </div>
                    </div>
                </div>

                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function exportHistory() {
    // สร้าง CSV
    let csv = 'วันที่,เวลา,ประเภท,หมายเหตุ\n';

    <?php foreach ($logs as $log): ?>
    csv += '<?php echo thaiDate($log["log_date"]); ?>,';
    csv += '<?php echo formatTime($log["log_time"]); ?>,';
    csv += '<?php echo $log["log_type"] == "in" ? "เข้า" : "ออก"; ?>,';
    csv += '<?php echo clean($log["note"] ?? ""); ?>\n';
    <?php endforeach; ?>

    // Download
    const blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = 'ประวัติการเข้าออก-<?php echo $student["student_code"]; ?>-<?php echo date("Y-m-d"); ?>.csv';
    link.click();
}
</script>

<?php include 'includes/footer.php'; ?>
