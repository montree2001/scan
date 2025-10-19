<?php
/**
 * หน้า Dashboard ของแอดมิน
 */
session_start();
define('ADMIN_PAGE', true);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/functions.php';

// ตรวจสอบสิทธิ์
requireAdmin();

$pageTitle = 'Dashboard';
$currentPage = 'dashboard';

// ดึงข้อมูลสถิติ
try {
    $db = getDB();

    // นับจำนวนนักเรียนทั้งหมด
    $stmt = $db->query("SELECT COUNT(*) as total FROM students WHERE status = 'active'");
    $totalStudents = $stmt->fetch()['total'];

    // นับจำนวนผู้ใช้งานทั้งหมด
    $stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
    $totalUsers = $stmt->fetch()['total'];

    // นับจำนวนการเข้าออกวันนี้
    $stmt = $db->query("SELECT COUNT(*) as total FROM attendance_logs WHERE log_date = CURDATE()");
    $todayAttendance = $stmt->fetch()['total'];

    // นับจำนวนนักเรียนที่อยู่ในวิทยาลัยตอนนี้ (เข้ามาแล้วยังไม่ออก)
    $stmt = $db->query("
        SELECT COUNT(DISTINCT student_id) as total
        FROM attendance_logs a1
        WHERE log_date = CURDATE()
        AND log_type = 'in'
        AND NOT EXISTS (
            SELECT 1 FROM attendance_logs a2
            WHERE a2.student_id = a1.student_id
            AND a2.log_date = CURDATE()
            AND a2.log_type = 'out'
            AND a2.log_time > a1.log_time
        )
    ");
    $currentlyInCollege = $stmt->fetch()['total'];

    // ดึงข้อมูลการเข้าออกล่าสุด 10 รายการ
    $stmt = $db->query("
        SELECT
            a.log_id,
            a.log_type,
            a.log_date,
            a.log_time,
            s.student_code,
            s.first_name,
            s.last_name,
            s.class,
            a.scan_method
        FROM attendance_logs a
        JOIN students s ON a.student_id = s.student_id
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    $recentLogs = $stmt->fetchAll();

    // สถิติการเข้าออก 7 วันล่าสุด
    $stmt = $db->query("
        SELECT
            log_date,
            COUNT(*) as total
        FROM attendance_logs
        WHERE log_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY log_date
        ORDER BY log_date ASC
    ");
    $weeklyStats = $stmt->fetchAll();

} catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $totalStudents = $totalUsers = $todayAttendance = $currentlyInCollege = 0;
    $recentLogs = [];
    $weeklyStats = [];
}

include 'includes/header.php';
?>

<!-- Stat Cards -->
<div class="row">
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
                    <h3><?php echo number_format($currentlyInCollege); ?></h3>
                    <p>อยู่ในวิทยาลัย</p>
                </div>
                <div>
                    <i class="bi bi-building fs-1"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3 col-sm-6">
        <div class="stat-card orange">
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
        <div class="stat-card purple">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3><?php echo number_format($totalUsers); ?></h3>
                    <p>ผู้ใช้งานระบบ</p>
                </div>
                <div>
                    <i class="bi bi-person-check fs-1"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts and Recent Activity -->
<div class="row mt-4">
    <!-- Chart -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-graph-up"></i> สถิติการเข้าออก 7 วันล่าสุด
            </div>
            <div class="card-body">
                <canvas id="attendanceChart" height="100"></canvas>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-lightning"></i> เมนูด่วน
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="<?php echo BASE_URL; ?>/admin/students.php?action=add" class="btn btn-primary">
                        <i class="bi bi-person-plus"></i> เพิ่มนักเรียนใหม่
                    </a>
                    <a href="<?php echo BASE_URL; ?>/admin/import.php" class="btn btn-success">
                        <i class="bi bi-file-earmark-excel"></i> นำเข้าจาก Excel
                    </a>
                    <a href="<?php echo BASE_URL; ?>/admin/qrcode.php" class="btn btn-info">
                        <i class="bi bi-qr-code"></i> สร้าง QR-Code
                    </a>
                    <a href="<?php echo BASE_URL; ?>/admin/reports.php" class="btn btn-warning">
                        <i class="bi bi-graph-up"></i> ดูรายงาน
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <i class="bi bi-clock-history"></i> บันทึกการเข้าออกล่าสุด
            </div>
            <div class="card-body">
                <?php if (empty($recentLogs)): ?>
                    <p class="text-center text-muted">ยังไม่มีข้อมูลการเข้าออก</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>เวลา</th>
                                    <th>รหัสนักเรียน</th>
                                    <th>ชื่อ-นามสกุล</th>
                                    <th>ชั้น</th>
                                    <th>ประเภท</th>
                                    <th>วิธีการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentLogs as $log): ?>
                                    <tr>
                                        <td><?php echo thaiDate($log['log_date']) . ' ' . formatTime($log['log_time']); ?></td>
                                        <td><?php echo clean($log['student_code']); ?></td>
                                        <td><?php echo clean($log['first_name'] . ' ' . $log['last_name']); ?></td>
                                        <td><?php echo clean($log['class']); ?></td>
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
                                            <?php else: ?>
                                                <i class="bi bi-pencil text-secondary"></i> บันทึกเอง
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Attendance Chart
    const ctx = document.getElementById('attendanceChart').getContext('2d');
    const weeklyData = <?php echo json_encode($weeklyStats); ?>;

    const labels = weeklyData.map(item => {
        const date = new Date(item.log_date);
        return date.toLocaleDateString('th-TH', { day: 'numeric', month: 'short' });
    });

    const data = weeklyData.map(item => item.total);

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'จำนวนครั้ง',
                data: data,
                borderColor: 'rgb(102, 126, 234)',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
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
</script>

<?php include 'includes/footer.php'; ?>
