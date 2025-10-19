<?php
/**
 * ไฟล์ Debug สำหรับตรวจสอบข้อมูลนักเรียน
 */
session_start();
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/functions.php';

if (!isLoggedIn()) {
    die('กรุณา Login ก่อน');
}

$db = getDB();
$userId = $_SESSION[SESSION_USER_ID];

?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug - ตรวจสอบข้อมูลนักเรียน</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; font-family: monospace; }
        .section { margin-bottom: 30px; padding: 20px; border: 2px solid #ddd; border-radius: 10px; }
        .ok { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Debug ข้อมูลนักเรียน</h1>

        <!-- Session Data -->
        <div class="section">
            <h3>📋 ข้อมูล Session</h3>
            <table class="table table-bordered">
                <tr>
                    <th width="200">User ID</th>
                    <td><?php echo $_SESSION[SESSION_USER_ID] ?? '-'; ?></td>
                </tr>
                <tr>
                    <th>Username</th>
                    <td><?php echo $_SESSION[SESSION_USERNAME] ?? '-'; ?></td>
                </tr>
                <tr>
                    <th>Role</th>
                    <td><?php echo $_SESSION[SESSION_USER_ROLE] ?? '-'; ?></td>
                </tr>
                <tr>
                    <th>Full Name</th>
                    <td><?php echo $_SESSION[SESSION_FULL_NAME] ?? '-'; ?></td>
                </tr>
            </table>
        </div>

        <!-- User Data -->
        <div class="section">
            <h3>👤 ข้อมูลใน Users Table</h3>
            <?php
            $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();

            if ($user) {
                echo '<p class="ok">✅ พบข้อมูล User</p>';
                echo '<pre>' . print_r($user, true) . '</pre>';
            } else {
                echo '<p class="error">❌ ไม่พบข้อมูล User</p>';
            }
            ?>
        </div>

        <!-- Students Table - Method 1 -->
        <div class="section">
            <h3>🎓 ตรวจสอบ Students Table (วิธีที่ 1 - JOIN)</h3>
            <?php
            $stmt = $db->prepare("
                SELECT s.*, u.full_name, u.username, u.email
                FROM students s
                LEFT JOIN users u ON s.user_id = u.user_id
                WHERE u.user_id = ?
            ");
            $stmt->execute([$userId]);
            $student = $stmt->fetch();

            if ($student) {
                echo '<p class="ok">✅ พบข้อมูล Student (JOIN สำเร็จ)</p>';
                echo '<pre>' . print_r($student, true) . '</pre>';
            } else {
                echo '<p class="error">❌ ไม่พบข้อมูล Student (JOIN ไม่สำเร็จ)</p>';
                echo '<p class="warning">ตรวจสอบว่า username ตรงกับ student_code หรือไม่</p>';
            }
            ?>
        </div>

        <!-- Students Table - Method 2 -->
        <div class="section">
            <h3>🎓 ตรวจสอบ Students Table (วิธีที่ 2 - ค้นหาด้วย username)</h3>
            <?php
            if ($user) {
                $stmt = $db->prepare("SELECT * FROM students WHERE student_code = ?");
                $stmt->execute([$user['username']]);
                $studentDirect = $stmt->fetch();

                if ($studentDirect) {
                    echo '<p class="ok">✅ พบข้อมูล Student โดยตรง</p>';
                    echo '<p>student_code = "' . htmlspecialchars($user['username']) . '"</p>';
                    echo '<pre>' . print_r($studentDirect, true) . '</pre>';
                } else {
                    echo '<p class="error">❌ ไม่พบข้อมูล Student ที่มี student_code = "' . htmlspecialchars($user['username']) . '"</p>';
                }
            }
            ?>
        </div>

        <!-- All Students -->
        <div class="section">
            <h3>📊 รายชื่อนักเรียนทั้งหมด (10 คนแรก)</h3>
            <?php
            $stmt = $db->query("SELECT student_id, student_code, first_name, last_name, status FROM students LIMIT 10");
            $allStudents = $stmt->fetchAll();

            if (!empty($allStudents)) {
                echo '<table class="table table-sm table-bordered">';
                echo '<thead><tr><th>ID</th><th>รหัสนักเรียน</th><th>ชื่อ</th><th>นามสกุล</th><th>สถานะ</th></tr></thead>';
                echo '<tbody>';
                foreach ($allStudents as $s) {
                    $highlight = ($user && $s['student_code'] == $user['username']) ? 'style="background: #ffffcc;"' : '';
                    echo '<tr ' . $highlight . '>';
                    echo '<td>' . $s['student_id'] . '</td>';
                    echo '<td><strong>' . htmlspecialchars($s['student_code']) . '</strong></td>';
                    echo '<td>' . htmlspecialchars($s['first_name']) . '</td>';
                    echo '<td>' . htmlspecialchars($s['last_name']) . '</td>';
                    echo '<td>' . $s['status'] . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo '<p class="error">❌ ไม่มีข้อมูลนักเรียนในตาราง students เลย!</p>';
            }
            ?>
        </div>

        <!-- Solution -->
        <div class="section">
            <h3>💡 วิธีแก้ไข</h3>
            <?php
            if ($user && !$student) {
                echo '<div class="alert alert-warning">';
                echo '<h5>ปัญหา: User มีอยู่แต่ไม่มีข้อมูลนักเรียน</h5>';
                echo '<p><strong>Username ของคุณ:</strong> ' . htmlspecialchars($user['username']) . '</p>';
                echo '<p><strong>วิธีแก้:</strong></p>';
                echo '<ol>';
                echo '<li>ไปที่หน้า <a href="admin/students.php">จัดการนักเรียน</a></li>';
                echo '<li>เพิ่มนักเรียนใหม่โดยใช้รหัสนักเรียนเป็น: <code>' . htmlspecialchars($user['username']) . '</code></li>';
                echo '<li>หรือ <a href="create_student_user.php">สร้างนักเรียนทดสอบ</a></li>';
                echo '</ol>';
                echo '</div>';
            } elseif ($student) {
                echo '<div class="alert alert-success">';
                echo '<h5>✅ ข้อมูลพร้อมใช้งาน!</h5>';
                echo '<p>สามารถเข้าใช้งานหน้านักเรียนได้แล้ว</p>';
                echo '<a href="student/index.php" class="btn btn-primary">ไปหน้านักเรียน</a>';
                echo '</div>';
            }
            ?>
        </div>

        <!-- Actions -->
        <div class="section">
            <h3>⚡ Actions</h3>
            <a href="admin/students.php" class="btn btn-primary">จัดการนักเรียน</a>
            <a href="create_student_user.php" class="btn btn-success">สร้างนักเรียนทดสอบ</a>
            <a href="student/index.php" class="btn btn-info">ไปหน้านักเรียน</a>
            <a href="logout.php" class="btn btn-danger">Logout</a>
        </div>

    </div>
</body>
</html>
