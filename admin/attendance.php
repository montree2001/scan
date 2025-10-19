<?php
/**
 * หน้าแสดงข้อมูลการเข้าออกของนักเรียน
 */
session_start();
define('ADMIN_PAGE', true);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/functions.php';

// ตรวจสอบสิทธิ์
requireAdmin();

$pageTitle = 'บันทึกเข้าออก';
$currentPage = 'attendance';

// ดึงพารามิเตอร์จาก URL
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : ''; // เริ่มต้นไม่มีการกรองวันที่
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : ''; // เริ่มต้นไม่มีการกรองวันที่
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$studentCode = isset($_GET['student_code']) ? trim($_GET['student_code']) : '';
$logType = isset($_GET['log_type']) ? $_GET['log_type'] : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

try {
    $db = getDB();
    
    // สร้าง query แบบใช้ร่วมกันระหว่างนับจำนวนและดึงข้อมูล
    $baseQuery = "
        SELECT 
            a.log_id,
            a.log_type,
            a.log_date,
            a.log_time,
            a.scan_method,
            a.is_outside_area,
            a.notes,
            s.student_code,
            s.first_name,
            s.last_name,
            s.class,
            s.major
        FROM attendance_logs a
        LEFT JOIN students s ON a.student_id = s.student_id
        WHERE 1=1
    ";
    
    $countQuery = "
        SELECT COUNT(*) as total
        FROM attendance_logs a
        LEFT JOIN students s ON a.student_id = s.student_id
        WHERE 1=1
    ";
    
    $params = [];
    
    // เพิ่มเงื่อนไขการค้นหา
    if (!empty($startDate) && !empty($endDate)) {
        $baseQuery .= " AND a.log_date BETWEEN ? AND ?";
        $countQuery .= " AND a.log_date BETWEEN ? AND ?";
        $params[] = $startDate;
        $params[] = $endDate;
    } elseif (!empty($startDate)) {
        $baseQuery .= " AND a.log_date >= ?";
        $countQuery .= " AND a.log_date >= ?";
        $params[] = $startDate;
    } elseif (!empty($endDate)) {
        $baseQuery .= " AND a.log_date <= ?";
        $countQuery .= " AND a.log_date <= ?";
        $params[] = $endDate;
    }
    
    if (!empty($studentCode)) {
        $baseQuery .= " AND s.student_code LIKE ?";
        $countQuery .= " AND s.student_code LIKE ?";
        $params[] = "%{$studentCode}%";
    }
    
    if (!empty($logType) && in_array($logType, ['in', 'out'])) {
        $baseQuery .= " AND a.log_type = ?";
        $countQuery .= " AND a.log_type = ?";
        $params[] = $logType;
    }
    
    if (!empty($search)) {
        $baseQuery .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_code LIKE ? OR s.class LIKE ?)";
        $countQuery .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_code LIKE ? OR s.class LIKE ?)";
        $searchParam = "%{$search}%";
        $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    }
    
    // นับจำนวนข้อมูลทั้งหมด
    $stmt = $db->prepare($countQuery);
    $stmt->execute($params);
    $totalItems = $stmt->fetch()['total'];
    
    // กำหนดจำนวนรายการต่อหน้า
    $itemsPerPage = ITEMS_PER_PAGE;
    $pagination = paginate($totalItems, $page, $itemsPerPage);
    
    $baseQuery .= " ORDER BY a.log_date DESC, a.log_time DESC LIMIT {$pagination['items_per_page']} OFFSET {$pagination['offset']}";
    
    $stmt = $db->prepare($baseQuery);
    $stmt->execute($params);
    $attendanceLogs = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Attendance Page Error: " . $e->getMessage());
    $attendanceLogs = [];
    $pagination = [
        'total_items' => 0,
        'total_pages' => 0,
        'current_page' => 1,
        'items_per_page' => ITEMS_PER_PAGE,
        'offset' => 0
    ];
    setAlert('danger', 'เกิดข้อผิดพลาดในการดึงข้อมูล');
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-calendar-check"></i> บันทึกการเข้าออก
                </h5>
            </div>
            <div class="card-body">
                <!-- ช่องค้นหาและตัวกรอง -->
                <form method="GET" class="mb-4">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label for="startDate" class="form-label">วันที่เริ่มต้น</label>
                            <input type="date" class="form-control" id="startDate" name="start_date" value="<?php echo clean($startDate); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="endDate" class="form-label">วันที่สิ้นสุด</label>
                            <input type="date" class="form-control" id="endDate" name="end_date" value="<?php echo clean($endDate); ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="logType" class="form-label">ประเภท</label>
                            <select class="form-control" id="logType" name="log_type">
                                <option value="">ทั้งหมด</option>
                                <option value="in" <?php echo ($logType === 'in') ? 'selected' : ''; ?>>เข้า</option>
                                <option value="out" <?php echo ($logType === 'out') ? 'selected' : ''; ?>>ออก</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="studentCode" class="form-label">รหัสนักเรียน</label>
                            <input type="text" class="form-control" id="studentCode" name="student_code" placeholder="รหัสนักเรียน" value="<?php echo clean($studentCode); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="search" class="form-label">ค้นหา</label>
                            <input type="text" class="form-control" id="search" name="search" placeholder="ค้นหาจากชื่อ, นามสกุล, รหัสนักเรียน หรือชั้น" value="<?php echo clean($search); ?>">
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="bi bi-search"></i> ค้นหา
                            </button>
                            <a href="attendance.php" class="btn btn-secondary">
                                <i class="bi bi-arrow-clockwise"></i> ล้าง
                            </a>
                        </div>
                    </div>
                </form>

                <!-- สรุปข้อมูล -->
                <div class="row mb-4">
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card blue">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3><?php echo number_format($pagination['total_items']); ?></h3>
                                    <p>รายการทั้งหมด</p>
                                </div>
                                <div>
                                    <i class="bi bi-journal-text fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card green">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3>
                                    <?php 
                                        // นับจำนวนเข้าแยกจากฐานข้อมูล
                                        $inQuery = "
                                            SELECT COUNT(*) as count
                                            FROM attendance_logs a
                                            LEFT JOIN students s ON a.student_id = s.student_id
                                            WHERE a.log_type = 'in'
                                        ";
                                        $inParams = [];
                                        
                                        if (!empty($startDate) && !empty($endDate)) {
                                            $inQuery .= " AND a.log_date BETWEEN ? AND ?";
                                            $inParams[] = $startDate;
                                            $inParams[] = $endDate;
                                        } elseif (!empty($startDate)) {
                                            $inQuery .= " AND a.log_date >= ?";
                                            $inParams[] = $startDate;
                                        } elseif (!empty($endDate)) {
                                            $inQuery .= " AND a.log_date <= ?";
                                            $inParams[] = $endDate;
                                        }
                                        
                                        if (!empty($studentCode)) {
                                            $inQuery .= " AND s.student_code LIKE ?";
                                            $inParams[] = "%{$studentCode}%";
                                        }
                                        
                                        if (!empty($search)) {
                                            $inQuery .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_code LIKE ? OR s.class LIKE ?)";
                                            $searchParam = "%{$search}%";
                                            $inParams = array_merge($inParams, [$searchParam, $searchParam, $searchParam, $searchParam]);
                                        }
                                        
                                        $stmt = $db->prepare($inQuery);
                                        $stmt->execute($inParams);
                                        $inCount = $stmt->fetch()['count'];
                                        echo number_format($inCount);
                                    ?>
                                    </h3>
                                    <p>เข้า</p>
                                </div>
                                <div>
                                    <i class="bi bi-arrow-right-circle fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card orange">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3>
                                    <?php 
                                        // นับจำนวนออกแยกจากฐานข้อมูล
                                        $outQuery = "
                                            SELECT COUNT(*) as count
                                            FROM attendance_logs a
                                            LEFT JOIN students s ON a.student_id = s.student_id
                                            WHERE a.log_type = 'out'
                                        ";
                                        $outParams = [];
                                        
                                        if (!empty($startDate) && !empty($endDate)) {
                                            $outQuery .= " AND a.log_date BETWEEN ? AND ?";
                                            $outParams[] = $startDate;
                                            $outParams[] = $endDate;
                                        } elseif (!empty($startDate)) {
                                            $outQuery .= " AND a.log_date >= ?";
                                            $outParams[] = $startDate;
                                        } elseif (!empty($endDate)) {
                                            $outQuery .= " AND a.log_date <= ?";
                                            $outParams[] = $endDate;
                                        }
                                        
                                        if (!empty($studentCode)) {
                                            $outQuery .= " AND s.student_code LIKE ?";
                                            $outParams[] = "%{$studentCode}%";
                                        }
                                        
                                        if (!empty($search)) {
                                            $outQuery .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_code LIKE ? OR s.class LIKE ?)";
                                            $searchParam = "%{$search}%";
                                            $outParams = array_merge($outParams, [$searchParam, $searchParam, $searchParam, $searchParam]);
                                        }
                                        
                                        $stmt = $db->prepare($outQuery);
                                        $stmt->execute($outParams);
                                        $outCount = $stmt->fetch()['count'];
                                        echo number_format($outCount);
                                    ?>
                                    </h3>
                                    <p>ออก</p>
                                </div>
                                <div>
                                    <i class="bi bi-arrow-left-circle fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-6">
                        <div class="stat-card purple">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h3><?php echo clean($pagination['current_page']) . '/' . max(1, $pagination['total_pages']); ?></h3>
                                    <p>หน้าที่</p>
                                </div>
                                <div>
                                    <i class="bi bi-file-ppt fs-1"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ตารางข้อมูล -->
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th style="width: 12%;">วันที่-เวลา</th>
                                <th style="width: 10%;">รหัสนักเรียน</th>
                                <th style="width: 15%;">ชื่อ-นามสกุล</th>
                                <th style="width: 8%;">ชั้น</th>
                                <th style="width: 12%;">สาขาวิชา</th>
                                <th style="width: 8%;">ประเภท</th>
                                <th style="width: 12%;">วิธีการ</th>
                                <th style="width: 23%;">หมายเหตุ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($attendanceLogs)): ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted">ไม่พบข้อมูล</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($attendanceLogs as $log): ?>
                                    <tr>
                                        <td>
                                            <?php echo thaiDate($log['log_date']) . ' ' . formatTime($log['log_time']); ?>
                                        </td>
                                        <td><?php echo clean($log['student_code']); ?></td>
                                        <td><?php echo clean($log['first_name'] . ' ' . $log['last_name']); ?></td>
                                        <td><?php echo clean($log['class']); ?></td>
                                        <td><?php echo clean($log['major']); ?></td>
                                        <td>
                                            <?php if ($log['log_type'] == 'in'): ?>
                                                <span class="badge bg-success">เข้า</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">ออก</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($log['scan_method'] == 'qr_scan'): ?>
                                                <i class="bi bi-qr-code-scan text-primary"></i> สแกน QR
                                            <?php elseif ($log['scan_method'] == 'public_checkin'): ?>
                                                <i class="bi bi-calendar-check text-info"></i> เช็คอินสาธารณะ
                                            <?php else: ?>
                                                <i class="bi bi-pencil text-secondary"></i> บันทึกเอง
                                            <?php endif; ?>
                                            
                                            <?php if ($log['is_outside_area']): ?>
                                                <span class="badge bg-warning ms-1">นอกพื้นที่</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo clean($log['notes']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php echo showPagination($pagination, 'attendance.php?' . http_build_query([
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'student_code' => $studentCode,
                    'log_type' => $logType,
                    'search' => $search
                ])); ?>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>