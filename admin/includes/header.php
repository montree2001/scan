<?php
if (!defined('ADMIN_PAGE')) {
    die('Access denied');
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'แอดมิน'; ?> - <?php echo APP_NAME; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;600;700&display=swap" rel="stylesheet">

    <!-- Custom CSS -->
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f8f9fa;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 250px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding-top: 20px;
            transition: all 0.3s;
            z-index: 1000;
        }

        .sidebar .logo {
            text-align: center;
            color: white;
            padding: 20px;
            font-size: 24px;
            font-weight: 700;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            margin: 5px 10px;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
            color: white;
        }

        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
        }

        .main-content {
            margin-left: 250px;
            padding: 20px;
            min-height: 100vh;
        }

        .top-navbar {
            background: white;
            padding: 15px 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 15px 20px;
            font-weight: 600;
        }

        .stat-card {
            padding: 20px;
            border-radius: 15px;
            color: white;
            margin-bottom: 20px;
        }

        .stat-card.blue {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-card.green {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }

        .stat-card.orange {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stat-card.purple {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stat-card h3 {
            font-size: 36px;
            font-weight: 700;
            margin: 0;
        }

        .stat-card p {
            margin: 0;
            opacity: 0.9;
        }

        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                overflow: hidden;
            }

            .main-content {
                margin-left: 0;
            }

            .sidebar.show {
                width: 250px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="logo">
            <i class="bi bi-qr-code-scan"></i> Admin Panel
        </div>

        <nav class="nav flex-column mt-4">
            <a class="nav-link <?php echo ($currentPage ?? '') == 'dashboard' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/index.php">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a class="nav-link <?php echo ($currentPage ?? '') == 'students' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/students.php">
                <i class="bi bi-people"></i> จัดการนักเรียน
            </a>
            <a class="nav-link <?php echo ($currentPage ?? '') == 'import' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/import.php">
                <i class="bi bi-file-earmark-excel"></i> นำเข้าข้อมูล Excel
            </a>
            <a class="nav-link <?php echo ($currentPage ?? '') == 'import_csv' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/import_students_csv.php">
                <i class="bi bi-filetype-csv"></i> นำเข้าข้อมูล CSV
            </a>
            <a class="nav-link <?php echo ($currentPage ?? '') == 'qrcode' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/qrcode.php">
                <i class="bi bi-qr-code"></i> จัดการ QR-Code
            </a>
            <a class="nav-link <?php echo ($currentPage ?? '') == 'users' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/users.php">
                <i class="bi bi-person-gear"></i> จัดการผู้ใช้
            </a>
            <a class="nav-link <?php echo ($currentPage ?? '') == 'attendance' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/attendance.php">
                <i class="bi bi-calendar-check"></i> บันทึกเข้าออก
            </a>
            <a class="nav-link <?php echo ($currentPage ?? '') == 'reports' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/reports.php">
                <i class="bi bi-graph-up"></i> รายงาน
            </a>
            <a class="nav-link <?php echo ($currentPage ?? '') == 'settings' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/settings.php">
                <i class="bi bi-gear"></i> ตั้งค่าระบบ
            </a>
            <a class="nav-link <?php echo ($currentPage ?? '') == 'settings_checkin' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>/admin/settings_checkin.php">
                <i class="bi bi-geo-alt"></i> ตั้งค่าเช็คอินสาธารณะ
            </a>

            <hr class="text-white mx-3">

            <a class="nav-link" href="<?php echo BASE_URL; ?>/logout.php">
                <i class="bi bi-box-arrow-right"></i> ออกจากระบบ
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="top-navbar d-flex justify-content-between align-items-center">
            <div>
                <button class="btn btn-link d-md-none" id="sidebarToggle">
                    <i class="bi bi-list fs-3"></i>
                </button>
                <h5 class="mb-0 d-inline-block"><?php echo $pageTitle ?? 'แอดมิน'; ?></h5>
            </div>
            <div>
                <span class="me-3">
                    <i class="bi bi-person-circle"></i>
                    <?php echo clean($_SESSION[SESSION_FULL_NAME]); ?>
                </span>
                <span class="badge bg-primary">Admin</span>
            </div>
        </div>

        <!-- Page Content -->
        <div class="page-content">
            <?php showAlert(); ?>
