<?php
/**
 * หน้าดูประวัติการเข้า-ออกของนักเรียน
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

$userId = $_SESSION[SESSION_USER_ID];
$fullName = $_SESSION[SESSION_FULL_NAME];

// รับค่าการกรอง
$filterDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$filterType = isset($_GET['type']) ? $_GET['type'] : 'all';
$filterClass = isset($_GET['class']) ? $_GET['class'] : 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

try {
    $db = getDB();

    // ดึงรายการชั้นเรียนทั้งหมด
    $stmt = $db->query("SELECT DISTINCT class FROM students WHERE class IS NOT NULL ORDER BY class");
    $classes = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // สร้าง SQL Query
    $whereConditions = ["al.log_date = ?"];
    $params = [$filterDate];

    if ($filterType !== 'all') {
        $whereConditions[] = "al.log_type = ?";
        $params[] = $filterType;
    }

    if ($filterClass !== 'all') {
        $whereConditions[] = "s.class = ?";
        $params[] = $filterClass;
    }

    $whereClause = implode(' AND ', $whereConditions);

    // นับจำนวนทั้งหมด
    $countStmt = $db->prepare("
        SELECT COUNT(*) as total
        FROM attendance_logs al
        JOIN students s ON al.student_id = s.student_id
        WHERE {$whereClause}
    ");
    $countStmt->execute($params);
    $totalRecords = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalRecords / $perPage);

    // ดึงข้อมูล
    $params[] = $perPage;
    $params[] = $offset;

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
        WHERE {$whereClause}
        ORDER BY al.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // สถิติวันที่เลือก
    $statsStmt = $db->prepare("
        SELECT
            log_type,
            COUNT(*) as count
        FROM attendance_logs
        WHERE log_date = ?
        GROUP BY log_type
    ");
    $statsStmt->execute([$filterDate]);
    $stats = ['in' => 0, 'out' => 0];
    foreach ($statsStmt->fetchAll(PDO::FETCH_ASSOC) as $stat) {
        $stats[$stat['log_type']] = $stat['count'];
    }

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $logs = [];
    $classes = [];
    $totalRecords = 0;
    $totalPages = 0;
    $stats = ['in' => 0, 'out' => 0];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>ประวัติการเข้าออก - <?php echo APP_NAME; ?></title>
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

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
        }

        .stats-box {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
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

        .filter-section {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        .form-select, .form-control {
            border-radius: 8px;
        }

        .pagination {
            justify-content: center;
        }

        .pagination .page-link {
            border-radius: 8px;
            margin: 0 2px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-light sticky-top">
        <div class="container-fluid">
            <a href="index.php" class="navbar-brand">
                <i class="bi bi-arrow-left"></i> กลับ
            </a>
            <span class="navbar-text">
                <i class="bi bi-clock-history"></i> ประวัติการเข้าออก
            </span>
        </div>
    </nav>

    <div class="container">
        <!-- สถิติ -->
        <div class="row g-3 mb-3">
            <div class="col-6">
                <div class="stats-box text-center">
                    <i class="bi bi-arrow-down-circle text-success" style="font-size: 2rem;"></i>
                    <h3 class="text-success mb-0"><?php echo $stats['in']; ?></h3>
                    <small class="text-muted">เข้า</small>
                </div>
            </div>
            <div class="col-6">
                <div class="stats-box text-center">
                    <i class="bi bi-arrow-up-circle text-danger" style="font-size: 2rem;"></i>
                    <h3 class="text-danger mb-0"><?php echo $stats['out']; ?></h3>
                    <small class="text-muted">ออก</small>
                </div>
            </div>
        </div>

        <!-- ตัวกรอง -->
        <div class="filter-section">
            <h6 class="mb-3"><i class="bi bi-funnel"></i> กรองข้อมูล</h6>
            <form method="GET" id="filterForm">
                <div class="row g-2">
                    <div class="col-12 col-md-4">
                        <label class="form-label small">วันที่:</label>
                        <input type="date" name="date" class="form-control" value="<?php echo $filterDate; ?>" onchange="this.form.submit()">
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small">ประเภท:</label>
                        <select name="type" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?php echo $filterType === 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                            <option value="in" <?php echo $filterType === 'in' ? 'selected' : ''; ?>>เข้า</option>
                            <option value="out" <?php echo $filterType === 'out' ? 'selected' : ''; ?>>ออก</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label small">ชั้น:</label>
                        <select name="class" class="form-select" onchange="this.form.submit()">
                            <option value="all" <?php echo $filterClass === 'all' ? 'selected' : ''; ?>>ทั้งหมด</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo clean($class); ?>" <?php echo $filterClass === $class ? 'selected' : ''; ?>>
                                    <?php echo clean($class); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>

        <!-- รายการ -->
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">รายการทั้งหมด</h5>
                    <span class="badge bg-white text-dark"><?php echo $totalRecords; ?> รายการ</span>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($logs)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                        <p>ไม่พบรายการในวันที่เลือก</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <div class="log-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1">
                                        <?php echo clean($log['first_name'] . ' ' . $log['last_name']); ?>
                                    </h6>
                                    <small class="text-muted d-block">
                                        <i class="bi bi-person-badge"></i> <?php echo clean($log['student_code']); ?>
                                        <?php if ($log['class']): ?>
                                            <span class="ms-2">
                                                <i class="bi bi-book"></i> <?php echo clean($log['class']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </small>
                                    <small class="text-muted d-block mt-1">
                                        <i class="bi bi-clock"></i>
                                        <?php echo date('H:i น.', strtotime($log['created_at'])); ?>
                                        <?php if ($log['scan_method'] == 'manual'): ?>
                                            <span class="badge bg-secondary ms-1">Manual</span>
                                        <?php else: ?>
                                            <span class="badge bg-info ms-1">QR Scan</span>
                                        <?php endif; ?>
                                    </small>
                                    <?php if ($log['recorded_by_name']): ?>
                                        <small class="text-muted d-block">
                                            <i class="bi bi-person"></i> บันทึกโดย: <?php echo clean($log['recorded_by_name']); ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <span class="badge <?php echo $log['log_type'] == 'in' ? 'badge-in' : 'badge-out'; ?> fs-6">
                                        <?php echo $log['log_type'] == 'in' ? 'เข้า' : 'ออก'; ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <nav aria-label="Page navigation" class="mt-3">
                            <ul class="pagination">
                                <?php if ($page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?date=<?php echo $filterDate; ?>&type=<?php echo $filterType; ?>&class=<?php echo $filterClass; ?>&page=<?php echo $page - 1; ?>">
                                            <i class="bi bi-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?date=<?php echo $filterDate; ?>&type=<?php echo $filterType; ?>&class=<?php echo $filterClass; ?>&page=<?php echo $i; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>

                                <?php if ($page < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?date=<?php echo $filterDate; ?>&type=<?php echo $filterType; ?>&class=<?php echo $filterClass; ?>&page=<?php echo $page + 1; ?>">
                                            <i class="bi bi-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
