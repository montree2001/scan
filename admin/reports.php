<?php
/**
 * หน้าแสดงรายงานต่างๆของระบบ
 */
session_start();
define('ADMIN_PAGE', true);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/functions.php';

// ตรวจสอบสิทธิ์
requireAdmin();

$pageTitle = 'รายงาน';
$currentPage = 'reports';

// รับพารามิเตอร์จาก URL
$reportType = isset($_GET['report_type']) ? $_GET['report_type'] : 'overview';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // วันแรกของเดือน
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d'); // วันปัจจุบัน
$studentCode = isset($_GET['student_code']) ? trim($_GET['student_code']) : '';
$classList = isset($_GET['class']) ? $_GET['class'] : '';

try {
    $db = getDB();

    // ดึงข้อมูลสรุปรวม
    $stmt = $db->query("SELECT COUNT(*) as total FROM students WHERE status = 'active'");
    $totalStudents = $stmt->fetch()['total'];

    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
    $totalUsers = $stmt->fetch()['total'];

    $stmt = $db->query("SELECT COUNT(*) as total FROM attendance_logs WHERE log_date = CURDATE()");
    $todayAttendance = $stmt->fetch()['total'];

    // ดึงข้อมูลรายงานตามประเภท
    $attendanceData = null;
    $studentClassData = null;
    $popularTimesData = null;
    $attendanceTrendData = null;

    // คำสั่ง SQL พื้นฐานที่ใช้ร่วมกัน
    $baseCondition = "WHERE a.log_date BETWEEN ? AND ?";
    $params = [$startDate, $endDate];

    // เพิ่มเงื่อนไขตามพารามิเตอร์
    if (!empty($studentCode)) {
        $baseCondition .= " AND s.student_code = ?";
        $params[] = $studentCode;
    }

    if (!empty($classList)) {
        $baseCondition .= " AND s.class = ?";
        $params[] = $classList;
    }

    // รายงานสรุปการเข้าออกตามวันที่
    $stmt = $db->prepare("
        SELECT 
            a.log_date,
            COUNT(CASE WHEN a.log_type = 'in' THEN 1 END) as in_count,
            COUNT(CASE WHEN a.log_type = 'out' THEN 1 END) as out_count,
            COUNT(*) as total_count
        FROM attendance_logs a
        LEFT JOIN students s ON a.student_id = s.student_id
        $baseCondition
        GROUP BY a.log_date
        ORDER BY a.log_date DESC
    ");
    $stmt->execute($params);
    $attendanceData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // รายงานการเข้าออกแยกตามชั้นเรียน
    $stmt = $db->prepare("
        SELECT 
            s.class,
            COUNT(CASE WHEN a.log_type = 'in' THEN 1 END) as in_count,
            COUNT(CASE WHEN a.log_type = 'out' THEN 1 END) as out_count,
            COUNT(*) as total_count
        FROM attendance_logs a
        LEFT JOIN students s ON a.student_id = s.student_id
        $baseCondition
        GROUP BY s.class
        ORDER BY total_count DESC
    ");
    $stmt->execute($params);
    $studentClassData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // ช่วงเวลาที่มีการเข้าออกมากที่สุด (แยกตามชั่วโมง)
    $stmt = $db->prepare("
        SELECT 
            HOUR(a.log_time) as hour,
            COUNT(*) as count
        FROM attendance_logs a
        LEFT JOIN students s ON a.student_id = s.student_id
        $baseCondition
        GROUP BY HOUR(a.log_time)
        ORDER BY hour
    ");
    $stmt->execute($params);
    $popularTimesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // รายงานแนวโน้มการเข้าออก 7 วันล่าสุด
    $stmt = $db->prepare("
        SELECT
            log_date,
            COUNT(*) as total
        FROM attendance_logs
        WHERE log_date >= DATE_SUB(?, INTERVAL 7 DAY)
        GROUP BY log_date
        ORDER BY log_date ASC
    ");
    $stmt->execute([$endDate]);
    $attendanceTrendData = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Reports Page Error: " . $e->getMessage());
    setAlert('danger', 'เกิดข้อผิดพลาดในการดึงข้อมูลรายงาน');
    $attendanceData = [];
    $studentClassData = [];
    $popularTimesData = [];
    $attendanceTrendData = [];
}

// ดึงข้อมูลชั้นเรียนทั้งหมด
try {
    $stmt = $db->query("SELECT DISTINCT class FROM students WHERE class IS NOT NULL AND class != '' AND status = 'active' ORDER BY class");
    $classes = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    $classes = [];
}

include 'includes/header.php';
?>

<div class="row mb-4">
    <div class="col-md-3 col-sm-6">
        <div class="stat-card blue">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3><?php echo number_format($totalStudents); ?></h3>
                    <p>นักเรียนทั้งหมด</p>
                </div>
                <div>
                    <i class="bi bi-people fs-1"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="stat-card green">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3><?php echo number_format($todayAttendance); ?></h3>
                    <p>เข้าออกวันนี้</p>
                </div>
                <div>
                    <i class="bi bi-calendar-check fs-1"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="stat-card orange">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3><?php echo count($attendanceData); ?></h3>
                    <p>วันที่มีข้อมูล</p>
                </div>
                <div>
                    <i class="bi bi-calendar-range fs-1"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="stat-card purple">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3><?php echo count($classes); ?></h3>
                    <p>ชั้นเรียนทั้งหมด</p>
                </div>
                <div>
                    <i class="bi bi-journal fs-1"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h5 class="mb-0"><i class="bi bi-graph-up"></i> ตัวกรองรายงาน</h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label for="reportType" class="form-label">ประเภทรายงาน</label>
                <select class="form-control" id="reportType" name="report_type">
                    <option value="overview" <?php echo ($reportType === 'overview') ? 'selected' : ''; ?>>ภาพรวม</option>
                    <option value="daily" <?php echo ($reportType === 'daily') ? 'selected' : ''; ?>>รายวัน</option>
                    <option value="class" <?php echo ($reportType === 'class') ? 'selected' : ''; ?>>แยกตามชั้นเรียน</option>
                    <option value="time" <?php echo ($reportType === 'time') ? 'selected' : ''; ?>>แยกตามช่วงเวลา</option>
                    <option value="trend" <?php echo ($reportType === 'trend') ? 'selected' : ''; ?>>แนวโน้ม</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="startDate" class="form-label">วันที่เริ่มต้น</label>
                <input type="date" class="form-control" id="startDate" name="start_date" value="<?php echo clean($startDate); ?>">
            </div>
            <div class="col-md-3">
                <label for="endDate" class="form-label">วันที่สิ้นสุด</label>
                <input type="date" class="form-control" id="endDate" name="end_date" value="<?php echo clean($endDate); ?>">
            </div>
            <div class="col-md-3">
                <label for="classList" class="form-label">ชั้นเรียน</label>
                <select class="form-control" id="classList" name="class">
                    <option value="">ทุกชั้นเรียน</option>
                    <?php foreach ($classes as $class): ?>
                        <option value="<?php echo htmlspecialchars($class); ?>" <?php echo ($classList === $class) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($class); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label for="studentCode" class="form-label">รหัสนักเรียน</label>
                <input type="text" class="form-control" id="studentCode" name="student_code" placeholder="ค้นหารหัสนักเรียน" value="<?php echo clean($studentCode); ?>">
            </div>
            <div class="col-md-6 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="bi bi-funnel"></i> กรองข้อมูล
                </button>
                <a href="reports.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-clockwise"></i> ล้าง
                </a>
            </div>
        </form>
    </div>
</div>

<!-- รายงานตามประเภท -->
<div class="mt-4">
    <?php if ($reportType === 'overview'): ?>
        <!-- ภาพรวมรายงาน -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-calendar-check"></i> สถิติการเข้าออกรายวัน
                    </div>
                    <div class="card-body">
                        <?php if (!empty($attendanceData)): ?>
                            <canvas id="attendanceChart" height="100"></canvas>
                        <?php else: ?>
                            <p class="text-center text-muted">ไม่พบข้อมูล</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-clock-history"></i> ช่วงเวลาเข้าออกมากที่สุด
                    </div>
                    <div class="card-body">
                        <?php if (!empty($popularTimesData)): ?>
                            <canvas id="timeChart" height="100"></canvas>
                        <?php else: ?>
                            <p class="text-center text-muted">ไม่พบข้อมูล</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <i class="bi bi-people"></i> สรุปการเข้าออกแยกตามชั้นเรียน
                    </div>
                    <div class="card-body">
                        <?php if (!empty($studentClassData)): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>ชั้นเรียน</th>
                                            <th>เข้า</th>
                                            <th>ออก</th>
                                            <th>รวม</th>
                                            <th>เปอร์เซ็นต์</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($studentClassData as $data): ?>
                                        <tr>
                                            <td><?php echo clean($data['class'] ?: 'ไม่ระบุ'); ?></td>
                                            <td><?php echo number_format($data['in_count']); ?></td>
                                            <td><?php echo number_format($data['out_count']); ?></td>
                                            <td><?php echo number_format($data['total_count']); ?></td>
                                            <td>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar" role="progressbar" 
                                                         style="width: <?php echo min(100, ($data['total_count'] / max(1, $totalStudents)) * 100); ?>%">
                                                        <?php echo round(($data['total_count'] / max(1, $totalStudents)) * 100, 1); ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-muted">ไม่พบข้อมูล</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <?php elseif ($reportType === 'daily'): ?>
        <!-- รายงานรายวัน -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-calendar-range"></i> รายงานการเข้าออกตามวันที่
            </div>
            <div class="card-body">
                <?php if (!empty($attendanceData)): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>วันที่</th>
                                    <th>เข้า</th>
                                    <th>ออก</th>
                                    <th>รวม</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendanceData as $data): ?>
                                <tr>
                                    <td><?php echo thaiDate($data['log_date']); ?></td>
                                    <td><?php echo number_format($data['in_count']); ?></td>
                                    <td><?php echo number_format($data['out_count']); ?></td>
                                    <td><?php echo number_format($data['total_count']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <canvas id="dailyChart" height="100"></canvas>
                <?php else: ?>
                    <p class="text-center text-muted">ไม่พบข้อมูล</p>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($reportType === 'class'): ?>
        <!-- รายงานแยกตามชั้นเรียน -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-people"></i> รายงานการเข้าออกแยกตามชั้นเรียน
            </div>
            <div class="card-body">
                <?php if (!empty($studentClassData)): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>ชั้นเรียน</th>
                                    <th>เข้า</th>
                                    <th>ออก</th>
                                    <th>รวม</th>
                                    <th>เปอร์เซ็นต์</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($studentClassData as $data): ?>
                                <tr>
                                    <td><?php echo clean($data['class'] ?: 'ไม่ระบุ'); ?></td>
                                    <td><?php echo number_format($data['in_count']); ?></td>
                                    <td><?php echo number_format($data['out_count']); ?></td>
                                    <td><?php echo number_format($data['total_count']); ?></td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?php echo min(100, ($data['total_count'] / array_sum(array_column($studentClassData, 'total_count'))) * 100); ?>%">
                                                <?php echo round(($data['total_count'] / array_sum(array_column($studentClassData, 'total_count'))) * 100, 1); ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <canvas id="classChart" height="100"></canvas>
                <?php else: ?>
                    <p class="text-center text-muted">ไม่พบข้อมูล</p>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($reportType === 'time'): ?>
        <!-- รายงานแยกตามช่วงเวลา -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-clock"></i> รายงานการเข้าออกแยกตามช่วงเวลา (ชั่วโมง)
            </div>
            <div class="card-body">
                <?php if (!empty($popularTimesData)): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>ชั่วโมง</th>
                                    <th>จำนวน</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($popularTimesData as $data): ?>
                                <tr>
                                    <td><?php echo $data['hour']; ?>:00 - <?php echo $data['hour']; ?>:59</td>
                                    <td><?php echo number_format($data['count']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <canvas id="timeOfDayChart" height="100"></canvas>
                <?php else: ?>
                    <p class="text-center text-muted">ไม่พบข้อมูล</p>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif ($reportType === 'trend'): ?>
        <!-- รายงานแนวโน้ม -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-graph-up-arrow"></i> แนวโน้มการเข้าออก 7 วันล่าสุด
            </div>
            <div class="card-body">
                <?php if (!empty($attendanceTrendData)): ?>
                    <canvas id="trendChart" height="100"></canvas>
                    <div class="table-responsive mt-4">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>วันที่</th>
                                    <th>จำนวน</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_reverse($attendanceTrendData) as $data): // แสดงจากใหม่ไปเก่า ?>
                                <tr>
                                    <td><?php echo thaiDate($data['log_date']); ?></td>
                                    <td><?php echo number_format($data['total']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center text-muted">ไม่พบข้อมูล</p>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>
        <!-- กรณีเลือกประเภทรายงานอื่นที่ยังไม่ได้กำหนด -->
        <div class="card">
            <div class="card-body text-center">
                <i class="bi bi-info-circle text-info" style="font-size: 3rem;"></i>
                <h5 class="mt-3">กรุณาเลือกประเภทรายงาน</h5>
                <p class="text-muted">กรุณาเลือกประเภทรายงานจากตัวเลือกด้านบนเพื่อดูข้อมูล</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
<?php if (!empty($attendanceData)): ?>
    // Chart สำหรับรายงานภาพรวม (รายวัน)
    const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
    const attendanceData = <?php echo json_encode($attendanceData); ?>;
    
    const attendanceLabels = attendanceData.map(item => {
        const date = new Date(item.log_date);
        return date.toLocaleDateString('th-TH', { day: 'numeric', month: 'short' });
    });
    
    const inData = attendanceData.map(item => item.in_count);
    const outData = attendanceData.map(item => item.out_count);
    
    new Chart(attendanceCtx, {
        type: 'bar',
        data: {
            labels: attendanceLabels,
            datasets: [
                {
                    label: 'เข้า',
                    data: inData,
                    backgroundColor: 'rgba(40, 167, 69, 0.7)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1
                },
                {
                    label: 'ออก',
                    data: outData,
                    backgroundColor: 'rgba(220, 53, 69, 0.7)',
                    borderColor: 'rgba(220, 53, 69, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
<?php endif; ?>

<?php if (!empty($popularTimesData)): ?>
    // Chart สำหรับรายงานช่วงเวลา
    const timeCtx = document.getElementById('timeChart').getContext('2d');
    const timeData = <?php echo json_encode($popularTimesData); ?>;
    
    const timeLabels = timeData.map(item => item.hour + ':00');
    const timeCounts = timeData.map(item => item.count);
    
    new Chart(timeCtx, {
        type: 'line',
        data: {
            labels: timeLabels,
            datasets: [{
                label: 'จำนวนครั้ง',
                data: timeCounts,
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                borderColor: 'rgb(102, 126, 234)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
<?php endif; ?>

<?php if ($reportType === 'daily' && !empty($attendanceData)): ?>
    // Chart สำหรับรายงานรายวัน
    const dailyCtx = document.getElementById('dailyChart').getContext('2d');
    const dailyData = <?php echo json_encode($attendanceData); ?>;
    
    const dailyLabels = dailyData.map(item => {
        const date = new Date(item.log_date);
        return date.toLocaleDateString('th-TH', { day: 'numeric', month: 'short' });
    });
    
    const dailyInData = dailyData.map(item => item.in_count);
    const dailyOutData = dailyData.map(item => item.out_count);
    
    new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: dailyLabels,
            datasets: [
                {
                    label: 'เข้า',
                    data: dailyInData,
                    borderColor: 'rgb(40, 167, 69)',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4,
                    fill: true
                },
                {
                    label: 'ออก',
                    data: dailyOutData,
                    borderColor: 'rgb(220, 53, 69)',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    tension: 0.4,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
<?php endif; ?>

<?php if ($reportType === 'class' && !empty($studentClassData)): ?>
    // Chart สำหรับรายงานแยกตามชั้นเรียน
    const classCtx = document.getElementById('classChart').getContext('2d');
    const classData = <?php echo json_encode($studentClassData); ?>;
    
    const classLabels = classData.map(item => item.class || 'ไม่ระบุ');
    const classTotalData = classData.map(item => item.total_count);
    
    new Chart(classCtx, {
        type: 'doughnut',
        data: {
            labels: classLabels,
            datasets: [{
                data: classTotalData,
                backgroundColor: [
                    'rgb(255, 99, 132)',
                    'rgb(54, 162, 235)',
                    'rgb(255, 205, 86)',
                    'rgb(75, 192, 192)',
                    'rgb(153, 102, 255)',
                    'rgb(255, 159, 64)',
                    'rgb(199, 199, 199)',
                    'rgb(83, 102, 255)'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
<?php endif; ?>

<?php if ($reportType === 'time' && !empty($popularTimesData)): ?>
    // Chart สำหรับรายงานแยกตามช่วงเวลา
    const timeOfDayCtx = document.getElementById('timeOfDayChart').getContext('2d');
    const timeOfDayData = <?php echo json_encode($popularTimesData); ?>;
    
    const timeOfDayLabels = timeOfDayData.map(item => item.hour + ':00');
    const timeOfDayCounts = timeOfDayData.map(item => item.count);
    
    new Chart(timeOfDayCtx, {
        type: 'bar',
        data: {
            labels: timeOfDayLabels,
            datasets: [{
                label: 'จำนวนครั้ง',
                data: timeOfDayCounts,
                backgroundColor: 'rgba(75, 192, 192, 0.6)',
                borderColor: 'rgb(75, 192, 192)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
<?php endif; ?>

<?php if ($reportType === 'trend' && !empty($attendanceTrendData)): ?>
    // Chart สำหรับรายงานแนวโน้ม
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    const trendData = <?php echo json_encode($attendanceTrendData); ?>;
    
    const trendLabels = trendData.map(item => {
        const date = new Date(item.log_date);
        return date.toLocaleDateString('th-TH', { day: 'numeric', month: 'short' });
    });
    
    const trendCounts = trendData.map(item => item.total);
    
    new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: trendLabels,
            datasets: [{
                label: 'จำนวนครั้ง',
                data: trendCounts,
                borderColor: 'rgb(255, 99, 132)',
                backgroundColor: 'rgba(255, 99, 132, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>