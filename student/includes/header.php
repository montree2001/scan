<?php
if (!defined('STUDENT_PAGE')) {
    die('Access denied');
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo $pageTitle ?? 'นักเรียน'; ?> - <?php echo SITE_NAME; ?></title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --danger-color: #e74a3b;
            --warning-color: #f6c23e;
            --info-color: #36b9cc;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background: #f8f9fc;
            padding-bottom: 80px; /* Space for bottom nav */
        }

        /* Top Navigation */
        .top-nav {
            background: linear-gradient(135deg, var(--primary-color) 0%, #224abe 100%);
            color: white;
            padding: 15px 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .top-nav .student-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .top-nav .avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            border: 2px solid white;
            object-fit: cover;
            background: white;
        }

        .top-nav .name {
            flex: 1;
        }

        .top-nav .name h6 {
            margin: 0;
            font-weight: 600;
            font-size: 16px;
        }

        .top-nav .name small {
            opacity: 0.9;
            font-size: 13px;
        }

        /* Content Area */
        .content-area {
            padding: 15px;
            margin-bottom: 20px;
        }

        /* Cards */
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 15px;
        }

        .card-header {
            background: white;
            border-bottom: 1px solid #e3e6f0;
            padding: 15px 20px;
            font-weight: 600;
            border-radius: 15px 15px 0 0 !important;
        }

        /* Stat Cards */
        .stat-card {
            text-align: center;
            padding: 20px;
            border-radius: 15px;
            color: white;
            margin-bottom: 15px;
        }

        .stat-card i {
            font-size: 40px;
            margin-bottom: 10px;
        }

        .stat-card h3 {
            font-size: 32px;
            font-weight: 700;
            margin: 10px 0 5px 0;
        }

        .stat-card p {
            margin: 0;
            opacity: 0.9;
        }

        /* Bottom Navigation */
        .bottom-nav {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            padding: 8px 0;
            z-index: 1000;
        }

        .bottom-nav .nav-items {
            display: flex;
            justify-content: space-around;
            align-items: center;
            max-width: 600px;
            margin: 0 auto;
        }

        .bottom-nav .nav-item {
            flex: 1;
            text-align: center;
            padding: 8px 5px;
            color: var(--secondary-color);
            text-decoration: none;
            transition: all 0.3s;
        }

        .bottom-nav .nav-item i {
            display: block;
            font-size: 24px;
            margin-bottom: 3px;
        }

        .bottom-nav .nav-item span {
            display: block;
            font-size: 11px;
        }

        .bottom-nav .nav-item.active {
            color: var(--primary-color);
        }

        .bottom-nav .nav-item:hover {
            color: var(--primary-color);
        }

        /* Alerts */
        .alert {
            border-radius: 10px;
            border: none;
        }

        /* Buttons */
        .btn {
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 500;
        }

        .btn-lg {
            padding: 12px 30px;
            font-size: 16px;
        }

        /* Badge */
        .badge {
            padding: 6px 12px;
            font-weight: 500;
        }

        /* QR Code Display */
        .qr-display {
            background: white;
            padding: 30px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .qr-display img {
            max-width: 100%;
            height: auto;
            border: 5px solid var(--primary-color);
            border-radius: 15px;
            padding: 10px;
        }

        /* Photo Upload */
        .photo-upload {
            position: relative;
            width: 150px;
            height: 150px;
            margin: 0 auto;
        }

        .photo-upload img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-color);
        }

        .photo-upload .upload-btn {
            position: absolute;
            bottom: 5px;
            right: 5px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            color: white;
            border: 3px solid white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        /* Responsive */
        @media (min-width: 768px) {
            .content-area {
                max-width: 800px;
                margin: 20px auto;
            }

            body {
                padding-bottom: 20px;
            }

            .bottom-nav {
                display: none;
            }

            .desktop-sidebar {
                display: block !important;
            }
        }

        .desktop-sidebar {
            display: none;
        }
    </style>

    <!-- Thai Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<!-- Top Navigation -->
<div class="top-nav">
    <div class="student-info">
        <?php
        $studentData = null;
        if (isset($_SESSION[SESSION_USER_ID])) {
            $db = getDB();
            $stmt = $db->prepare("
                SELECT s.*, u.full_name, u.username
                FROM students s
                LEFT JOIN users u ON s.user_id = u.user_id
                WHERE u.user_id = ?
            ");
            $stmt->execute([$_SESSION[SESSION_USER_ID]]);
            $studentData = $stmt->fetch();
        }
        ?>
        <img src="<?php echo !empty($studentData['photo_path']) ? BASE_URL . '/' . $studentData['photo_path'] : BASE_URL . '/assets/images/default-avatar.png'; ?>"
             alt="Avatar"
             class="avatar"
             onerror="this.src='<?php echo BASE_URL; ?>/assets/images/default-avatar.png'">
        <div class="name">
            <h6><?php echo clean($studentData['first_name'] ?? 'นักเรียน') . ' ' . clean($studentData['last_name'] ?? ''); ?></h6>
            <small><?php echo clean($studentData['student_code'] ?? ''); ?> | <?php echo clean($studentData['class'] ?? '-'); ?></small>
        </div>
        <a href="<?php echo BASE_URL; ?>/logout.php" class="btn btn-sm btn-light" onclick="return confirm('ต้องการออกจากระบบ?')">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>
</div>

<!-- Desktop Sidebar (Hidden on Mobile) -->
<div class="desktop-sidebar">
    <div class="d-flex">
        <div class="bg-white shadow-sm" style="width: 250px; min-height: 100vh;">
            <div class="p-3">
                <h5><i class="bi bi-person-circle"></i> เมนูนักเรียน</h5>
                <hr>
                <nav class="nav flex-column">
                    <a class="nav-link <?php echo ($currentPage == 'dashboard') ? 'active' : ''; ?>" href="index.php">
                        <i class="bi bi-house-door"></i> หน้าหลัก
                    </a>
                    <a class="nav-link <?php echo ($currentPage == 'profile') ? 'active' : ''; ?>" href="profile.php">
                        <i class="bi bi-person"></i> โปรไฟล์
                    </a>
                    <a class="nav-link <?php echo ($currentPage == 'qrcode') ? 'active' : ''; ?>" href="qrcode.php">
                        <i class="bi bi-qr-code"></i> QR Code
                    </a>
                    <a class="nav-link <?php echo ($currentPage == 'history') ? 'active' : ''; ?>" href="history.php">
                        <i class="bi bi-clock-history"></i> ประวัติ
                    </a>
                    <a class="nav-link <?php echo ($currentPage == 'vehicle') ? 'active' : ''; ?>" href="vehicle.php">
                        <i class="bi bi-car-front"></i> ยานพาหนะ
                    </a>
                </nav>
            </div>
        </div>
        <div class="flex-fill">
            <div class="content-area">
                <?php displayAlert(); ?>
