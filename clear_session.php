<?php
/**
 * ไฟล์สำหรับลบ Session ทั้งหมด
 */

// เริ่ม session
session_start();

// ลบ session ทั้งหมด
$_SESSION = array();

// ทำลาย session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-42000, '/');
}

// ทำลาย session
session_destroy();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ล้างข้อมูล Session</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            text-align: center;
            max-width: 500px;
        }
        h1 { color: #667eea; margin-bottom: 20px; }
        .success-icon { font-size: 64px; color: #4CAF50; margin-bottom: 20px; }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            margin: 10px;
            font-weight: 600;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .info {
            background: #f0f0f0;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            text-align: left;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon">✅</div>
        <h1>ล้างข้อมูล Session สำเร็จ!</h1>
        <p>ข้อมูล Session ทั้งหมดถูกลบแล้ว</p>

        <div class="info">
            <strong>ขั้นตอนต่อไป:</strong>
            <ol style="margin: 10px 0; padding-left: 20px;">
                <li>คลิกปุ่ม "เข้าสู่ระบบ" ด้านล่าง</li>
                <li>Login ด้วย username: <code>admin</code></li>
                <li>Password: <code>admin123</code></li>
            </ol>
        </div>

        <a href="login.php" class="btn">🔐 เข้าสู่ระบบ</a>
        <a href="check.php" class="btn" style="background: #4CAF50;">🔍 ตรวจสอบระบบ</a>
    </div>
</body>
</html>
