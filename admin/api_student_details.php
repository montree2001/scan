<?php
/**
 * API สำหรับดูรายละเอียดนักเรียน
 */
header('Content-Type: text/html; charset=utf-8');
session_start();

if (!isset($_SESSION[SESSION_USER_ID]) || $_SESSION[SESSION_ROLE] !== 'admin') {
    echo 'Unauthorized';
    exit();
}

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['student_id'])) {
    echo 'Invalid request';
    exit();
}

$studentId = intval($_POST['student_id']);

$db = getDB();

try {
    $stmt = $db->prepare("
        SELECT s.*, u.username, u.email as user_email, u.role, u.status as user_status,
               TIMESTAMPDIFF(YEAR, s.date_of_birth, CURDATE()) AS age
        FROM students s
        LEFT JOIN users u ON s.user_id = u.user_id
        WHERE s.student_id = ? AND s.status = 'active'
    ");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        echo 'ไม่พบข้อมูลนักเรียน';
        exit();
    }

    // แสดงรายละเอียดนักเรียน
    ?>
    <div class="row">
        <div class="col-md-6">
            <h6 class="text-primary"><i class="bi bi-person"></i> ข้อมูลส่วนตัว</h6>
            <table class="table table-sm">
                <tr>
                    <th width="140">รหัสนักเรียน:</th>
                    <td><?php echo htmlspecialchars($student['student_code']); ?></td>
                </tr>
                <?php if ($student['id_card']): ?>
                <tr>
                    <th>เลขบัตรประชาชน:</th>
                    <td><?php echo htmlspecialchars($student['id_card']); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th>ชื่อ-นามสกุล:</th>
                    <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                </tr>
                <tr>
                    <th>ชื่อเล่น:</th>
                    <td><?php echo $student['nickname'] ? htmlspecialchars($student['nickname']) : '-'; ?></td>
                </tr>
                <tr>
                    <th>เพศ:</th>
                    <td>
                        <?php 
                        switch($student['gender']) {
                            case 'male': echo '<i class="bi bi-gender-male text-primary"></i> ชาย'; break;
                            case 'female': echo '<i class="bi bi-gender-female text-danger"></i> หญิง'; break;
                            default: echo 'ไม่ระบุ';
                        }
                        ?>
                    </td>
                </tr>
                <?php if ($student['date_of_birth']): ?>
                <tr>
                    <th>วันเกิด:</th>
                    <td><?php echo date('d/m/Y', strtotime($student['date_of_birth'])); ?> (อายุ <?php echo $student['age']; ?> ปี)</td>
                </tr>
                <?php endif; ?>
                <?php if ($student['phone']): ?>
                <tr>
                    <th>เบอร์โทร:</th>
                    <td>
                        <a href="tel:<?php echo htmlspecialchars($student['phone']); ?>">
                            <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($student['phone']); ?>
                        </a>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if ($student['email']): ?>
                <tr>
                    <th>อีเมล:</th>
                    <td>
                        <a href="mailto:<?php echo htmlspecialchars($student['email']); ?>">
                            <i class="bi bi-envelope"></i> <?php echo htmlspecialchars($student['email']); ?>
                        </a>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
            
            <?php if ($student['class'] || $student['major'] || $student['grade']): ?>
            <h6 class="text-primary mt-3"><i class="bi bi-book"></i> ข้อมูลการศึกษา</h6>
            <table class="table table-sm">
                <?php if ($student['class']): ?>
                <tr>
                    <th width="140">ชั้นเรียน:</th>
                    <td><?php echo htmlspecialchars($student['class']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($student['major']): ?>
                <tr>
                    <th>สาขาวิชา:</th>
                    <td><?php echo htmlspecialchars($student['major']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($student['grade']): ?>
                <tr>
                    <th>ระดับชั้น:</th>
                    <td><?php echo htmlspecialchars($student['grade']); ?></td>
                </tr>
                <?php endif; ?>
            </table>
            <?php endif; ?>
        </div>
        
        <div class="col-md-6">
            <?php if ($student['address']): ?>
            <h6 class="text-primary"><i class="bi bi-house"></i> ที่อยู่</h6>
            <p><?php echo nl2br(htmlspecialchars($student['address'])); ?></p>
            <?php endif; ?>
            
            <?php if ($student['parent_name'] || $student['parent_phone']): ?>
            <h6 class="text-primary mt-3"><i class="bi bi-people"></i> ข้อมูลผู้ปกครอง</h6>
            <table class="table table-sm">
                <?php if ($student['parent_name']): ?>
                <tr>
                    <th width="140">ชื่อผู้ปกครอง:</th>
                    <td><?php echo htmlspecialchars($student['parent_name']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($student['parent_phone']): ?>
                <tr>
                    <th>เบอร์โทร:</th>
                    <td>
                        <a href="tel:<?php echo htmlspecialchars($student['parent_phone']); ?>">
                            <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($student['parent_phone']); ?>
                        </a>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
            <?php endif; ?>
            
            <?php if ($student['emergency_contact'] || $student['emergency_phone']): ?>
            <h6 class="text-primary mt-3"><i class="bi bi-shield-exclamation"></i> ติดต่อฉุกเฉิน</h6>
            <table class="table table-sm">
                <?php if ($student['emergency_contact']): ?>
                <tr>
                    <th width="140">ผู้ติดต่อ:</th>
                    <td><?php echo htmlspecialchars($student['emergency_contact']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($student['emergency_phone']): ?>
                <tr>
                    <th>เบอร์โทร:</th>
                    <td>
                        <a href="tel:<?php echo htmlspecialchars($student['emergency_phone']); ?>">
                            <i class="bi bi-telephone"></i> <?php echo htmlspecialchars($student['emergency_phone']); ?>
                        </a>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
            <?php endif; ?>
            
            <?php if ($student['user_email'] || $student['username']): ?>
            <h6 class="text-primary mt-3"><i class="bi bi-person-gear"></i> ข้อมูลบัญชีผู้ใช้</h6>
            <table class="table table-sm">
                <?php if ($student['username']): ?>
                <tr>
                    <th width="140">ชื่อผู้ใช้:</th>
                    <td>
                        <?php echo htmlspecialchars($student['username']); ?>
                        <?php if ($student['user_status'] === 'active'): ?>
                            <span class="badge bg-success">ใช้งาน</span>
                        <?php else: ?>
                            <span class="badge bg-danger">ถูกระงับ</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
                <?php if ($student['user_email']): ?>
                <tr>
                    <th>อีเมล:</th>
                    <td><?php echo htmlspecialchars($student['user_email']); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($student['role']): ?>
                <tr>
                    <th>สิทธิ์:</th>
                    <td>
                        <?php 
                        switch($student['role']) {
                            case 'admin': echo '<span class="badge bg-primary">ผู้ดูแลระบบ</span>'; break;
                            case 'student': echo '<span class="badge bg-success">นักเรียน</span>'; break;
                            case 'staff': echo '<span class="badge bg-info">เจ้าหน้าที่</span>'; break;
                            default: echo '<span class="badge bg-secondary">' . htmlspecialchars($student['role']) . '</span>';
                        }
                        ?>
                    </td>
                </tr>
                <?php endif; ?>
            </table>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="row mt-3">
        <div class="col-12">
            <small class="text-muted">
                <i class="bi bi-clock"></i> 
                สร้างเมื่อ: <?php echo date('d/m/Y H:i:s', strtotime($student['created_at'])); ?> | 
                อัปเดตล่าสุด: <?php echo date('d/m/Y H:i:s', strtotime($student['updated_at'])); ?>
            </small>
        </div>
    </div>
    <?php
} catch (Exception $e) {
    echo 'เกิดข้อผิดพลาด: ' . $e->getMessage();
}
?>