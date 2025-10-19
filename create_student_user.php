<?php
/**
 * สร้าง User และ Student สำหรับทดสอบ
 */
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/functions.php';

$db = getDB();
$created = [];
$errors = [];

// ตัวอย่างข้อมูลนักเรียน
$testStudents = [
    [
        'student_code' => '66010001',
        'first_name' => 'สมชาย',
        'last_name' => 'ใจดี',
        'nickname' => 'ชาย',
        'class' => 'ปวช.1/1',
        'grade' => 'ปวช.1',
        'gender' => 'male',
        'phone' => '0812345678',
        'email' => 'somchai@example.com',
        'password' => 'student123'
    ],
    [
        'student_code' => '66010002',
        'first_name' => 'สมหญิง',
        'last_name' => 'สวยงาม',
        'nickname' => 'หญิง',
        'class' => 'ปวช.1/1',
        'grade' => 'ปวช.1',
        'gender' => 'female',
        'phone' => '0823456789',
        'email' => 'somying@example.com',
        'password' => 'student123'
    ]
];

foreach ($testStudents as $data) {
    try {
        // ตรวจสอบว่ามี username ซ้ำไหม
        $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ?");
        $stmt->execute([$data['student_code']]);

        if ($stmt->rowCount() > 0) {
            $errors[] = "Username {$data['student_code']} มีอยู่แล้ว";
            continue;
        }

        // สร้าง User
        $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);
        $stmt = $db->prepare("
            INSERT INTO users (username, password, email, full_name, role, status)
            VALUES (?, ?, ?, ?, 'student', 'active')
        ");
        $fullName = $data['first_name'] . ' ' . $data['last_name'];
        $stmt->execute([
            $data['student_code'],
            $hashedPassword,
            $data['email'],
            $fullName
        ]);
        $userId = $db->lastInsertId();

        // สร้าง Student
        $stmt = $db->prepare("
            INSERT INTO students (
                user_id, student_code, first_name, last_name, nickname,
                class, grade, gender, phone, email, status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");
        $stmt->execute([
            $userId,
            $data['student_code'],
            $data['first_name'],
            $data['last_name'],
            $data['nickname'],
            $data['class'],
            $data['grade'],
            $data['gender'],
            $data['phone'],
            $data['email']
        ]);

        $created[] = [
            'student_code' => $data['student_code'],
            'name' => $fullName,
            'password' => $data['password']
        ];

    } catch (Exception $e) {
        $errors[] = "Error creating {$data['student_code']}: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>สร้างนักเรียนทดสอบ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
            font-family: 'Sarabun', sans-serif;
        }
        .card {
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body p-4">
                        <h3 class="mb-4">
                            <i class="bi bi-person-plus-fill"></i> สร้างนักเรียนทดสอบ
                        </h3>

                        <?php if (!empty($created)): ?>
                            <div class="alert alert-success">
                                <h5><i class="bi bi-check-circle"></i> สร้างสำเร็จ <?php echo count($created); ?> รายการ</h5>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>รหัสนักเรียน</th>
                                            <th>ชื่อ-นามสกุล</th>
                                            <th>รหัสผ่าน</th>
                                            <th>การใช้งาน</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($created as $student): ?>
                                            <tr>
                                                <td><strong><?php echo $student['student_code']; ?></strong></td>
                                                <td><?php echo $student['name']; ?></td>
                                                <td><code><?php echo $student['password']; ?></code></td>
                                                <td>
                                                    <a href="login.php" class="btn btn-sm btn-primary">
                                                        <i class="bi bi-box-arrow-in-right"></i> Login
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($errors)): ?>
                            <div class="alert alert-warning">
                                <h5><i class="bi bi-exclamation-triangle"></i> มีข้อผิดพลาดบางส่วน</h5>
                                <ul class="mb-0">
                                    <?php foreach ($errors as $error): ?>
                                        <li><?php echo htmlspecialchars($error); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if (empty($created) && empty($errors)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> ไม่มีข้อมูลที่สร้าง (อาจมีอยู่แล้วในระบบ)
                            </div>
                        <?php endif; ?>

                        <div class="mt-4">
                            <a href="login.php" class="btn btn-primary">
                                <i class="bi bi-box-arrow-in-right"></i> ไปหน้า Login
                            </a>
                            <a href="admin/students.php" class="btn btn-secondary">
                                <i class="bi bi-people"></i> จัดการนักเรียน
                            </a>
                            <a href="clear_session.php" class="btn btn-warning">
                                <i class="bi bi-trash"></i> ล้าง Session
                            </a>
                        </div>

                        <hr class="my-4">

                        <div class="alert alert-light">
                            <h6>ข้อมูลสำหรับ Login:</h6>
                            <ul class="mb-0">
                                <li><strong>Username:</strong> 66010001 (หรือ 66010002)</li>
                                <li><strong>Password:</strong> student123</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</body>
</html>
