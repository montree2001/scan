<?php
/**
 * หน้าแสดงรายละเอียดนักเรียน
 */
session_start();
define('ADMIN_PAGE', true);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/functions.php';

requireAdmin();

$pageTitle = 'รายละเอียดนักเรียน';
$currentPage = 'students';

$db = getDB();
$studentId = $_GET['id'] ?? null;

if (!$studentId) {
    $_SESSION['error'] = 'ไม่พบ ID นักเรียน';
    redirect(BASE_URL . '/admin/students.php');
}

// ดึงข้อมูลนักเรียน
$stmt = $db->prepare("SELECT * FROM students WHERE student_id = ?");
$stmt->execute([$studentId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    $_SESSION['error'] = 'ไม่พบข้อมูลนักเรียน';
    redirect(BASE_URL . '/admin/students.php');
}

// ดึงประวัติการเข้าออก (10 รายการล่าสุด)
$logsStmt = $db->prepare("
    SELECT * FROM attendance_logs
    WHERE student_id = ?
    ORDER BY log_date DESC, log_time DESC
    LIMIT 10
");
$logsStmt->execute([$studentId]);
$logs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<style>
.info-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    margin-bottom: 20px;
}

.info-card h5 {
    color: #667eea;
    font-weight: 600;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid #f0f0f0;
}

.info-table {
    width: 100%;
}

.info-table tr {
    border-bottom: 1px solid #f5f5f5;
}

.info-table th {
    padding: 12px 0;
    font-weight: 600;
    color: #666;
    width: 180px;
}

.info-table td {
    padding: 12px 0;
    color: #333;
}

.student-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 30px;
    margin-bottom: 30px;
    box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
}

.student-header .student-name {
    font-size: 2rem;
    font-weight: 700;
    margin-bottom: 5px;
}

.student-header .student-code {
    font-size: 1.2rem;
    opacity: 0.9;
}

.badge-custom {
    padding: 8px 15px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.95rem;
}

.badge-male {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.badge-female {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
}

.log-badge {
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 500;
}

.log-badge.in {
    background: #d4edda;
    color: #155724;
}

.log-badge.out {
    background: #f8d7da;
    color: #721c24;
}

.action-button {
    padding: 10px 20px;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}

.btn-back {
    background: #6c757d;
    color: white;
}

.btn-back:hover {
    background: #5a6268;
    color: white;
    transform: translateY(-2px);
}

.btn-edit {
    background: linear-gradient(135deg, #ffc837 0%, #ff8008 100%);
    color: white;
}

.btn-edit:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(255, 128, 8, 0.3);
    color: white;
}

.btn-delete {
    background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%);
    color: white;
}

.btn-delete:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(245, 87, 108, 0.3);
    color: white;
}

.qr-code-box {
    text-align: center;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 10px;
}

.qr-code-box img {
    max-width: 200px;
    border: 3px solid #667eea;
    border-radius: 10px;
    padding: 10px;
    background: white;
}
</style>

<!-- Student Header -->
<div class="student-header">
    <div class="row align-items-center">
        <div class="col-md-8">
            <div class="student-name">
                <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                <?php if ($student['nickname']): ?>
                    <small style="font-size: 1.3rem;">( <?php echo htmlspecialchars($student['nickname']); ?> )</small>
                <?php endif; ?>
            </div>
            <div class="student-code">รหัสนักเรียน: <?php echo htmlspecialchars($student['student_code']); ?></div>
        </div>
        <div class="col-md-4 text-end">
            <span class="badge-custom <?php echo $student['gender'] == 'male' ? 'badge-male' : 'badge-female'; ?>">
                <i class="bi bi-gender-<?php echo $student['gender'] == 'male' ? 'male' : 'female'; ?>"></i>
                <?php echo $student['gender'] == 'male' ? 'ชาย' : 'หญิง'; ?>
            </span>
        </div>
    </div>
</div>

<!-- Action Buttons -->
<div class="mb-4">
    <a href="students.php" class="action-button btn-back">
        <i class="bi bi-arrow-left"></i> กลับไปรายชื่อ
    </a>
    <a href="edit_student.php?id=<?php echo $student['student_id']; ?>" class="action-button btn-edit">
        <i class="bi bi-pencil-fill"></i> แก้ไขข้อมูล
    </a>
    <a href="?action=delete&id=<?php echo $student['student_id']; ?>" class="action-button btn-delete" onclick="return confirm('ต้องการลบนักเรียนคนนี้หรือไม่?')">
        <i class="bi bi-trash-fill"></i> ลบนักเรียน
    </a>
</div>

<!-- Student Information -->
<div class="row">
    <!-- ข้อมูลส่วนตัว -->
    <div class="col-md-6">
        <div class="info-card">
            <h5><i class="bi bi-person-circle"></i> ข้อมูลส่วนตัว</h5>
            <table class="info-table">
                <tr>
                    <th>รหัสนักเรียน</th>
                    <td><?php echo htmlspecialchars($student['student_code']); ?></td>
                </tr>
                <?php if ($student['id_card']): ?>
                <tr>
                    <th>เลขบัตรประชาชน</th>
                    <td><?php echo htmlspecialchars($student['id_card']); ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <th>ชื่อ</th>
                    <td><?php echo htmlspecialchars($student['first_name']); ?></td>
                </tr>
                <tr>
                    <th>นามสกุล</th>
                    <td><?php echo htmlspecialchars($student['last_name']); ?></td>
                </tr>
                <tr>
                    <th>ชื่อเล่น</th>
                    <td><?php echo $student['nickname'] ? htmlspecialchars($student['nickname']) : '-'; ?></td>
                </tr>
                <tr>
                    <th>เพศ</th>
                    <td><?php echo $student['gender'] == 'male' ? 'ชาย' : 'หญิง'; ?></td>
                </tr>
                <tr>
                    <th>วันเกิด</th>
                    <td><?php echo $student['date_of_birth'] ? thai_date(strtotime($student['date_of_birth'])) : '-'; ?></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- ข้อมูลการศึกษา -->
    <div class="col-md-6">
        <div class="info-card">
            <h5><i class="bi bi-book"></i> ข้อมูลการศึกษา</h5>
            <table class="info-table">
                <tr>
                    <th>ชั้น/ห้อง</th>
                    <td><?php echo $student['class'] ? htmlspecialchars($student['class']) : '-'; ?></td>
                </tr>
                <tr>
                    <th>สาขาวิชา</th>
                    <td><?php echo $student['major'] ? htmlspecialchars($student['major']) : '-'; ?></td>
                </tr>
                <tr>
                    <th>เบอร์โทรศัพท์</th>
                    <td>
                        <?php if ($student['phone']): ?>
                            <a href="tel:<?php echo htmlspecialchars($student['phone']); ?>">
                                <i class="bi bi-telephone-fill"></i> <?php echo htmlspecialchars($student['phone']); ?>
                            </a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>อีเมล</th>
                    <td>
                        <?php if ($student['email']): ?>
                            <a href="mailto:<?php echo htmlspecialchars($student['email']); ?>">
                                <i class="bi bi-envelope-fill"></i> <?php echo htmlspecialchars($student['email']); ?>
                            </a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th>สถานะ</th>
                    <td>
                        <?php if ($student['status'] == 'active'): ?>
                            <span class="badge bg-success">ใช้งาน</span>
                        <?php else: ?>
                            <span class="badge bg-danger">ระงับ</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</div>

<!-- ที่อยู่ -->
<?php if ($student['address']): ?>
<div class="row">
    <div class="col-12">
        <div class="info-card">
            <h5><i class="bi bi-geo-alt-fill"></i> ที่อยู่</h5>
            <p class="mb-0"><?php echo nl2br(htmlspecialchars($student['address'])); ?></p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ข้อมูลผู้ปกครองและผู้ติดต่อฉุกเฉิน -->
<div class="row">
    <div class="col-md-6">
        <div class="info-card">
            <h5><i class="bi bi-people-fill"></i> ข้อมูลผู้ปกครอง</h5>
            <table class="info-table">
                <tr>
                    <th>ชื่อผู้ปกครอง</th>
                    <td><?php echo $student['parent_name'] ? htmlspecialchars($student['parent_name']) : '-'; ?></td>
                </tr>
                <tr>
                    <th>เบอร์โทร</th>
                    <td>
                        <?php if ($student['parent_phone']): ?>
                            <a href="tel:<?php echo htmlspecialchars($student['parent_phone']); ?>">
                                <i class="bi bi-telephone-fill"></i> <?php echo htmlspecialchars($student['parent_phone']); ?>
                            </a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div class="col-md-6">
        <div class="info-card">
            <h5><i class="bi bi-telephone-fill"></i> ผู้ติดต่อฉุกเฉิน</h5>
            <table class="info-table">
                <tr>
                    <th>ชื่อผู้ติดต่อ</th>
                    <td><?php echo $student['emergency_contact'] ? htmlspecialchars($student['emergency_contact']) : '-'; ?></td>
                </tr>
                <tr>
                    <th>เบอร์โทร</th>
                    <td>
                        <?php if ($student['emergency_phone']): ?>
                            <a href="tel:<?php echo htmlspecialchars($student['emergency_phone']); ?>">
                                <i class="bi bi-telephone-fill"></i> <?php echo htmlspecialchars($student['emergency_phone']); ?>
                            </a>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</div>

<!-- ประวัติการเข้าออก -->
<?php if (!empty($logs)): ?>
<div class="row">
    <div class="col-12">
        <div class="info-card">
            <h5><i class="bi bi-clock-history"></i> ประวัติการเข้าออก (10 รายการล่าสุด)</h5>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>วันที่</th>
                            <th>เวลา</th>
                            <th>ประเภท</th>
                            <th>วิธีการ</th>
                            <th>หมายเหตุ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo thai_date(strtotime($log['log_date'])); ?></td>
                            <td><?php echo date('H:i', strtotime($log['log_time'])); ?> น.</td>
                            <td>
                                <span class="log-badge <?php echo $log['log_type']; ?>">
                                    <?php echo $log['log_type'] == 'in' ? 'เข้า' : 'ออก'; ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                $method = '';
                                switch ($log['scan_method']) {
                                    case 'qr_scan':
                                        $method = '<i class="bi bi-qr-code"></i> สแกน QR';
                                        break;
                                    case 'manual':
                                        $method = '<i class="bi bi-keyboard"></i> บันทึกเอง';
                                        break;
                                    case 'public_checkin':
                                        $method = '<i class="bi bi-phone"></i> เช็คอินสาธารณะ';
                                        break;
                                    default:
                                        $method = '-';
                                }
                                echo $method;
                                ?>
                            </td>
                            <td><?php echo $log['notes'] ? htmlspecialchars($log['notes']) : '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
