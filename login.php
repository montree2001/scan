<?php
/**
 * หน้า Login
 */
session_start();
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/functions.php';

// ถ้า Login แล้วให้ Redirect ไปหน้าที่เหมาะสม
if (isLoggedIn()) {
    if (isAdmin()) {
        redirect(BASE_URL . '/admin/index.php');
    } elseif (isStaff()) {
        redirect(BASE_URL . '/staff/index.php');
    } else {
        redirect(BASE_URL . '/student/index.php');
    }
}

// ประมวลผล Login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (empty($username) || empty($password)) {
        setAlert('danger', 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน');
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND status = 'active'");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // Login สำเร็จ
                $_SESSION[SESSION_USER_ID] = $user['user_id'];
                $_SESSION[SESSION_USERNAME] = $user['username'];
                $_SESSION[SESSION_ROLE] = $user['role'];
                $_SESSION[SESSION_FULL_NAME] = $user['full_name'];

                // อัพเดทเวลา Login ล่าสุด
                $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
                $updateStmt->execute([$user['user_id']]);

                // บันทึก Activity Log
                logActivity($user['user_id'], 'login', 'เข้าสู่ระบบ');

                // Redirect ตาม Role
                if ($user['role'] === 'admin') {
                    redirect(BASE_URL . '/admin/index.php');
                } elseif ($user['role'] === 'staff') {
                    redirect(BASE_URL . '/staff/index.php');
                } else {
                    redirect(BASE_URL . '/student/index.php');
                }
            } else {
                setAlert('danger', 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง');
                logActivity(null, 'login_failed', "พยายาม Login ด้วย username: {$username}");
            }
        } catch (Exception $e) {
            error_log("Login Error: " . $e->getMessage());
            setAlert('danger', 'เกิดข้อผิดพลาดในการเข้าสู่ระบบ');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Sarabun', sans-serif;
        }
        .login-container {
            max-width: 420px;
            width: 100%;
            margin: 0 auto;
            padding: 20px;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .login-header h3 {
            margin: 0;
            font-weight: 600;
        }
        .login-header p {
            margin: 10px 0 0 0;
            opacity: 0.9;
        }
        .login-body {
            padding: 40px 30px;
        }
        .form-control {
            padding: 12px 15px;
            border-radius: 10px;
            border: 2px solid #e9ecef;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: none;
        }
        .btn-login {
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            font-weight: 600;
            font-size: 16px;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .input-group-text {
            background: transparent;
            border: 2px solid #e9ecef;
            border-right: none;
        }
        .input-group .form-control {
            border-left: none;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <h3><i class="bi bi-qr-code-scan"></i> <?php echo APP_NAME; ?></h3>
                <p>กรุณาเข้าสู่ระบบเพื่อใช้งาน</p>
            </div>
            <div class="login-body">
                <?php showAlert(); ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">ชื่อผู้ใช้</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-person"></i></span>
                            <input type="text" class="form-control" name="username" placeholder="กรอกชื่อผู้ใช้" required autofocus>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">รหัสผ่าน</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-lock"></i></span>
                            <input type="password" class="form-control" name="password" placeholder="กรอกรหัสผ่าน" required>
                        </div>
                    </div>

                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="remember" id="remember">
                        <label class="form-check-label" for="remember">จดจำการเข้าสู่ระบบ</label>
                    </div>

                    <button type="submit" class="btn btn-login">
                        <i class="bi bi-box-arrow-in-right"></i> เข้าสู่ระบบ
                    </button>
                </form>

                <div class="text-center mt-4">
                    <small class="text-muted">
                        ระบบสำหรับแอดมิน เจ้าหน้าที่ และนักเรียน<br>
                        <strong>ผู้ดูแลระบบ:</strong> admin / admin123
                    </small>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
