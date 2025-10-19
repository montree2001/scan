<?php
/**
 * สคริปต์สำหรับเชื่อมโยงข้อมูล Student กับ User
 * ใช้เมื่อมี User แต่ยังไม่มีข้อมูล Student
 */
require_once 'config/config.php';
require_once 'config/database.php';

$db = getDB();

// ตัวอย่างการสร้างข้อมูล Student ทดสอบ
// แก้ไข user_id ให้ตรงกับ user ที่ต้องการเชื่อมโยง

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $user_id = (int)$_POST['user_id'];
        $student_code = trim($_POST['student_code']);
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $class = trim($_POST['class']);

        // ตรวจสอบว่า user_id นี้มีอยู่จริง
        $stmt = $db->prepare("SELECT user_id, username, full_name FROM users WHERE user_id = ? AND role = 'student'");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception("ไม่พบ User ID {$user_id} หรือ User นี้ไม่ใช่นักเรียน");
        }

        // ตรวจสอบว่ามีข้อมูล Student แล้วหรือยัง
        $stmt = $db->prepare("SELECT student_id FROM students WHERE user_id = ?");
        $stmt->execute([$user_id]);
        if ($stmt->fetch()) {
            throw new Exception("User ID {$user_id} มีข้อมูล Student อยู่แล้ว");
        }

        // ตรวจสอบว่า student_code ซ้ำหรือไม่
        $stmt = $db->prepare("SELECT student_id FROM students WHERE student_code = ?");
        $stmt->execute([$student_code]);
        if ($stmt->fetch()) {
            throw new Exception("รหัสนักเรียน {$student_code} มีในระบบแล้ว");
        }

        // สร้างข้อมูล Student
        $stmt = $db->prepare("
            INSERT INTO students
            (user_id, student_code, first_name, last_name, class, status, created_at)
            VALUES (?, ?, ?, ?, ?, 'active', NOW())
        ");
        $stmt->execute([$user_id, $student_code, $first_name, $last_name, $class]);

        $message = "<div class='alert alert-success'>✅ สร้างข้อมูลนักเรียนสำเร็จ! สามารถ Login ด้วย User ID {$user_id} ได้แล้ว</div>";

    } catch (Exception $e) {
        $message = "<div class='alert alert-danger'>❌ เกิดข้อผิดพลาด: " . $e->getMessage() . "</div>";
    }
}

// ดึงรายชื่อ User ที่เป็น student แต่ยังไม่มีข้อมูล
$stmt = $db->query("
    SELECT u.*
    FROM users u
    LEFT JOIN students s ON u.user_id = s.user_id
    WHERE u.role = 'student' AND s.student_id IS NULL
    ORDER BY u.user_id
");
$orphanUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เชื่อมโยงข้อมูล Student-User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <h2>เชื่อมโยงข้อมูล Student กับ User</h2>
        <p class="text-muted">สำหรับสร้างข้อมูลนักเรียนให้กับ User ที่ยังไม่มีข้อมูล</p>
        <hr>

        <?php echo $message; ?>

        <h4>User ที่ยังไม่มีข้อมูล Student (<?php echo count($orphanUsers); ?> รายการ)</h4>

        <?php if (empty($orphanUsers)): ?>
            <div class="alert alert-info">
                ✅ User ทุกคนมีข้อมูล Student เรียบร้อยแล้ว
            </div>
        <?php else: ?>
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>User ID</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orphanUsers as $user): ?>
                        <tr>
                            <td><?php echo $user['user_id']; ?></td>
                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                            <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($user['email'] ?? '-'); ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createModal<?php echo $user['user_id']; ?>">
                                    สร้างข้อมูล Student
                                </button>
                            </td>
                        </tr>

                        <!-- Modal -->
                        <div class="modal fade" id="createModal<?php echo $user['user_id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <form method="POST">
                                        <div class="modal-header">
                                            <h5 class="modal-title">สร้างข้อมูล Student สำหรับ <?php echo htmlspecialchars($user['username']); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">

                                            <div class="mb-3">
                                                <label class="form-label">รหัสนักเรียน *</label>
                                                <input type="text" name="student_code" class="form-control" required>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">ชื่อ *</label>
                                                <input type="text" name="first_name" class="form-control" required>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">นามสกุล *</label>
                                                <input type="text" name="last_name" class="form-control" required>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">ชั้นเรียน</label>
                                                <input type="text" name="class" class="form-control" placeholder="เช่น ม.1/1">
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                                            <button type="submit" class="btn btn-primary">บันทึก</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <hr>
        <p>
            <a href="check_student_data.php" class="btn btn-info">ตรวจสอบข้อมูล</a>
            <a href="index.php" class="btn btn-secondary">กลับหน้าหลัก</a>
        </p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
