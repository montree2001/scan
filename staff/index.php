<?php
/**
 * หน้า Dashboard ของเจ้าหน้าที่ - ระบบสแกนเข้าออก
 * รองรับการใช้งานบนมือถือ
 */
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/functions.php';

// ตรวจสอบว่า Login แล้วหรือยัง และต้องเป็น staff หรือ admin
if (!isLoggedIn()) {
    redirect(BASE_URL . '/login.php');
}

$role = $_SESSION[SESSION_ROLE];
if ($role !== 'staff' && $role !== 'admin') {
    redirect(BASE_URL . '/index.php');
}

// ดึงข้อมูลผู้ใช้
$userId = $_SESSION[SESSION_USER_ID];
$username = $_SESSION[SESSION_USERNAME];
$fullName = $_SESSION[SESSION_FULL_NAME];

// ดึงสถิติการเข้าออกวันนี้
try {
    $db = getDB();

    // นับจำนวนการเข้าวันนี้
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM attendance_logs WHERE log_date = CURDATE() AND log_type = 'in'");
    $stmt->execute();
    $todayIn = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // นับจำนวนการออกวันนี้
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM attendance_logs WHERE log_date = CURDATE() AND log_type = 'out'");
    $stmt->execute();
    $todayOut = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // นักเรียนที่อยู่ในวิทยาลัย (เข้าแล้วแต่ยังไม่ออก)
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT student_id) as count
        FROM attendance_logs al1
        WHERE log_date = CURDATE()
        AND log_type = 'in'
        AND NOT EXISTS (
            SELECT 1 FROM attendance_logs al2
            WHERE al2.student_id = al1.student_id
            AND al2.log_date = CURDATE()
            AND al2.log_type = 'out'
            AND al2.created_at > al1.created_at
        )
    ");
    $stmt->execute();
    $currentlyInside = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // ดึงรายการเข้าออกล่าสุด 10 รายการ
    $stmt = $db->prepare("
        SELECT
            al.*,
            s.student_code,
            s.first_name,
            s.last_name,
            s.class,
            u.full_name as recorded_by_name
        FROM attendance_logs al
        JOIN students s ON al.student_id = s.student_id
        LEFT JOIN users u ON al.recorded_by = u.user_id
        WHERE al.log_date = CURDATE()
        ORDER BY al.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recentLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $todayIn = 0;
    $todayOut = 0;
    $currentlyInside = 0;
    $recentLogs = [];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>เจ้าหน้าที่สแกน - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #11998e;
            --secondary-color: #38ef7d;
        }

        body {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            font-family: 'Sarabun', sans-serif;
            padding-bottom: 80px;
        }

        .navbar {
            background: white !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .container {
            max-width: 1200px;
            padding: 20px 15px;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }

        .stats-card:active {
            transform: scale(0.98);
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 10px 0;
        }

        .stats-label {
            color: #666;
            font-size: 0.9rem;
        }

        .action-btn {
            background: white;
            border: none;
            border-radius: 15px;
            padding: 25px 20px;
            margin-bottom: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            width: 100%;
            transition: all 0.3s;
            text-decoration: none;
            display: block;
        }

        .action-btn:active {
            transform: scale(0.95);
        }

        .action-btn i {
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .action-btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
        }

        .action-btn-secondary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .action-btn-info {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .log-item {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .badge-in {
            background: #28a745;
        }

        .badge-out {
            background: #dc3545;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
        }

        @media (max-width: 768px) {
            .stats-number {
                font-size: 2rem;
            }

            .action-btn i {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-light sticky-top">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="bi bi-person-badge text-success"></i>
                <?php echo clean($fullName); ?>
            </span>
            <a href="<?php echo BASE_URL; ?>/logout.php" class="btn btn-outline-danger btn-sm">
                <i class="bi bi-box-arrow-right"></i> ออกจากระบบ
            </a>
        </div>
    </nav>

    <div class="container">
        <!-- สถิติวันนี้ -->
        <div class="row g-3 mb-4">
            <div class="col-4">
                <div class="stats-card text-center">
                    <i class="bi bi-arrow-down-circle text-success" style="font-size: 2rem;"></i>
                    <div class="stats-number text-success"><?php echo $todayIn; ?></div>
                    <div class="stats-label">เข้า</div>
                </div>
            </div>
            <div class="col-4">
                <div class="stats-card text-center">
                    <i class="bi bi-arrow-up-circle text-danger" style="font-size: 2rem;"></i>
                    <div class="stats-number text-danger"><?php echo $todayOut; ?></div>
                    <div class="stats-label">ออก</div>
                </div>
            </div>
            <div class="col-4">
                <div class="stats-card text-center">
                    <i class="bi bi-people text-primary" style="font-size: 2rem;"></i>
                    <div class="stats-number text-primary"><?php echo $currentlyInside; ?></div>
                    <div class="stats-label">อยู่ใน</div>
                </div>
            </div>
        </div>

        <!-- ปุ่มเมนูหลัก -->
        <div class="row g-3 mb-4">
            <div class="col-12">
                <a href="scan.php" class="action-btn action-btn-primary">
                    <div class="text-center">
                        <i class="bi bi-qr-code-scan"></i>
                        <h5 class="mb-0">สแกน QR Code</h5>
                        <small>เข้า-ออกด้วย QR Code</small>
                    </div>
                </a>
            </div>
            <div class="col-6">
                <a href="manual.php" class="action-btn action-btn-secondary">
                    <div class="text-center">
                        <i class="bi bi-pencil-square"></i>
                        <h6 class="mb-0">บันทึกด้วยตัวเอง</h6>
                        <small>เลือกจากรายชื่อ</small>
                    </div>
                </a>
            </div>
            <div class="col-6">
                <a href="history.php" class="action-btn action-btn-info">
                    <div class="text-center">
                        <i class="bi bi-clock-history"></i>
                        <h6 class="mb-0">ดูประวัติ</h6>
                        <small>รายการเข้าออก</small>
                    </div>
                </a>
            </div>
        </div>

        <!-- รายการเข้าออกล่าสุด -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-list-ul"></i> รายการล่าสุดวันนี้</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recentLogs)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                        <p>ยังไม่มีรายการเข้าออกวันนี้</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentLogs as $log): ?>
                        <div class="log-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">
                                        <?php echo clean($log['first_name'] . ' ' . $log['last_name']); ?>
                                    </h6>
                                    <small class="text-muted">
                                        <i class="bi bi-person-badge"></i> <?php echo clean($log['student_code']); ?>
                                        <span class="ms-2">
                                            <i class="bi bi-book"></i> <?php echo clean($log['class']); ?>
                                        </span>
                                    </small>
                                    <div class="mt-1">
                                        <small class="text-muted">
                                            <i class="bi bi-clock"></i>
                                            <?php echo date('H:i น.', strtotime($log['created_at'])); ?>
                                            <?php if ($log['scan_method'] == 'manual'): ?>
                                                <span class="badge bg-secondary ms-1">Manual</span>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                                <div>
                                    <span class="badge <?php echo $log['log_type'] == 'in' ? 'badge-in' : 'badge-out'; ?> fs-6">
                                        <?php echo $log['log_type'] == 'in' ? 'เข้า' : 'ออก'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="text-center mt-3">
                        <a href="history.php" class="btn btn-outline-primary btn-sm">
                            ดูทั้งหมด <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto refresh every 30 seconds
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
