<?php
/**
 * หน้าแก้ไขข้อมูลนักเรียน
 */
session_start();
define('ADMIN_PAGE', true);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/functions.php';

requireAdmin();

$pageTitle = 'แก้ไขข้อมูลนักเรียน';
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

// บันทึกข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_student'])) {
    $studentCode = trim($_POST['student_code']) ?? '';
    $idCard = trim($_POST['id_card']) ?? null;
    $firstName = trim($_POST['first_name']) ?? '';
    $lastName = trim($_POST['last_name']) ?? '';
    $nickname = trim($_POST['nickname']) ?? '';
    $dateOfBirth = $_POST['date_of_birth'] ?? null;
    $gender = $_POST['gender'] ?? '';
    $class = trim($_POST['class']) ?? '';
    $major = trim($_POST['major']) ?? '';
    $phone = trim($_POST['phone']) ?? '';
    $email = trim($_POST['email']) ?? '';
    $parentName = trim($_POST['parent_name']) ?? '';
    $parentPhone = trim($_POST['parent_phone']) ?? '';
    $address = trim($_POST['address']) ?? '';
    $emergencyContact = trim($_POST['emergency_contact']) ?? '';
    $emergencyPhone = trim($_POST['emergency_phone']) ?? '';

    try {
        $stmt = $db->prepare("
            UPDATE students SET
                student_code = ?, id_card = ?, first_name = ?, last_name = ?, nickname = ?,
                date_of_birth = ?, gender = ?, class = ?, major = ?,
                phone = ?, email = ?, parent_name = ?, parent_phone = ?,
                address = ?, emergency_contact = ?, emergency_phone = ?,
                updated_at = NOW()
            WHERE student_id = ?
        ");
        $stmt->execute([
            $studentCode, $idCard, $firstName, $lastName, $nickname,
            $dateOfBirth, $gender, $class, $major,
            $phone, $email, $parentName, $parentPhone,
            $address, $emergencyContact, $emergencyPhone,
            $studentId
        ]);

        logActivity($_SESSION[SESSION_USER_ID], 'update_student', "แก้ไขข้อมูลนักเรียน: $firstName $lastName (ID: $studentId)");
        $_SESSION['success'] = 'แก้ไขข้อมูลนักเรียนเรียบร้อยแล้ว';
        redirect(BASE_URL . '/admin/view_student.php?id=' . $studentId);
    } catch (Exception $e) {
        $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }
}

// ตรวจสอบว่ามี id_card field หรือยัง
$hasIdCardField = false;
try {
    $stmt = $db->query("SHOW COLUMNS FROM students LIKE 'id_card'");
    $hasIdCardField = ($stmt->rowCount() > 0);
} catch (PDOException $e) {
    // Ignore
}

include 'includes/header.php';
?>

<style>
.edit-form-card {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
}

.form-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 2px solid #f0f0f0;
}

.form-section:last-child {
    border-bottom: none;
}

.form-section h5 {
    color: #667eea;
    font-weight: 600;
    margin-bottom: 20px;
}

.form-label {
    font-weight: 600;
    color: #444;
    margin-bottom: 8px;
}

.form-control, .form-select {
    border-radius: 8px;
    border: 1px solid #ddd;
    padding: 10px 15px;
}

.form-control:focus, .form-select:focus {
    border-color: #667eea;
    box-shadow: 0 0 0 0.25rem rgba(102, 126, 234, 0.25);
}

.required {
    color: #f5576c;
}

.action-buttons {
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    margin-top: 30px;
}

.btn-custom {
    padding: 12px 30px;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
}

.btn-cancel {
    background: #6c757d;
    color: white;
}

.btn-cancel:hover {
    background: #5a6268;
    color: white;
    transform: translateY(-2px);
}

.btn-save {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
}

.btn-save:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(17, 153, 142, 0.3);
    color: white;
}

.page-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
}

.page-header h4 {
    margin: 0;
    font-weight: 700;
}
</style>

<!-- Page Header -->
<div class="page-header">
    <h4><i class="bi bi-pencil-square"></i> แก้ไขข้อมูลนักเรียน</h4>
</div>

<!-- Edit Form -->
<div class="edit-form-card">
    <form method="POST">
        <!-- ข้อมูลพื้นฐาน -->
        <div class="form-section">
            <h5><i class="bi bi-person-badge"></i> ข้อมูลพื้นฐาน</h5>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">รหัสนักเรียน <span class="required">*</span></label>
                    <input type="text" name="student_code" class="form-control" required
                           value="<?php echo htmlspecialchars($student['student_code']); ?>">
                </div>
                <?php if ($hasIdCardField): ?>
                <div class="col-md-6 mb-3">
                    <label class="form-label">เลขบัตรประชาชน (13 หลัก)</label>
                    <input type="text" name="id_card" class="form-control" maxlength="13" pattern="\d{13}"
                           value="<?php echo $student['id_card'] ? htmlspecialchars($student['id_card']) : ''; ?>"
                           placeholder="1234567890123">
                    <small class="text-muted">ใส่เฉพาะตัวเลข 13 หลัก</small>
                </div>
                <?php endif; ?>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">ชื่อ <span class="required">*</span></label>
                    <input type="text" name="first_name" class="form-control" required
                           value="<?php echo htmlspecialchars($student['first_name']); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">นามสกุล <span class="required">*</span></label>
                    <input type="text" name="last_name" class="form-control" required
                           value="<?php echo htmlspecialchars($student['last_name']); ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">ชื่อเล่น</label>
                    <input type="text" name="nickname" class="form-control"
                           value="<?php echo $student['nickname'] ? htmlspecialchars($student['nickname']) : ''; ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">เพศ</label>
                    <select name="gender" class="form-select">
                        <option value="male" <?php echo $student['gender'] == 'male' ? 'selected' : ''; ?>>ชาย</option>
                        <option value="female" <?php echo $student['gender'] == 'female' ? 'selected' : ''; ?>>หญิง</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">วันเกิด</label>
                    <input type="date" name="date_of_birth" class="form-control"
                           value="<?php echo $student['date_of_birth'] ?? ''; ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">เบอร์โทรศัพท์</label>
                    <input type="tel" name="phone" class="form-control"
                           value="<?php echo $student['phone'] ? htmlspecialchars($student['phone']) : ''; ?>"
                           placeholder="0812345678">
                </div>
            </div>
        </div>

        <!-- ข้อมูลการศึกษา -->
        <div class="form-section">
            <h5><i class="bi bi-book"></i> ข้อมูลการศึกษา</h5>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">ชั้น/ห้อง</label>
                    <input type="text" name="class" class="form-control" placeholder="เช่น ปวช.1/1"
                           value="<?php echo $student['class'] ? htmlspecialchars($student['class']) : ''; ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">สาขาวิชา</label>
                    <input type="text" name="major" class="form-control" placeholder="เช่น 20101 - ช่างยนต์"
                           value="<?php echo $student['major'] ? htmlspecialchars($student['major']) : ''; ?>">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label">อีเมล</label>
                <input type="email" name="email" class="form-control" placeholder="example@email.com"
                       value="<?php echo $student['email'] ? htmlspecialchars($student['email']) : ''; ?>">
            </div>
        </div>

        <!-- ที่อยู่ -->
        <div class="form-section">
            <h5><i class="bi bi-geo-alt-fill"></i> ที่อยู่</h5>
            <div class="mb-3">
                <label class="form-label">ที่อยู่</label>
                <textarea name="address" class="form-control" rows="3" placeholder="บ้านเลขที่ หมู่ ตำบล อำเภอ จังหวัด รหัสไปรษณีย์"><?php echo $student['address'] ? htmlspecialchars($student['address']) : ''; ?></textarea>
            </div>
        </div>

        <!-- ข้อมูลผู้ปกครอง -->
        <div class="form-section">
            <h5><i class="bi bi-people-fill"></i> ข้อมูลผู้ปกครอง</h5>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">ชื่อผู้ปกครอง</label>
                    <input type="text" name="parent_name" class="form-control"
                           value="<?php echo $student['parent_name'] ? htmlspecialchars($student['parent_name']) : ''; ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">เบอร์โทรผู้ปกครอง</label>
                    <input type="tel" name="parent_phone" class="form-control" placeholder="0812345678"
                           value="<?php echo $student['parent_phone'] ? htmlspecialchars($student['parent_phone']) : ''; ?>">
                </div>
            </div>
        </div>

        <!-- ผู้ติดต่อฉุกเฉิน -->
        <div class="form-section">
            <h5><i class="bi bi-telephone-fill"></i> ผู้ติดต่อฉุกเฉิน</h5>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">ชื่อผู้ติดต่อฉุกเฉิน</label>
                    <input type="text" name="emergency_contact" class="form-control"
                           value="<?php echo $student['emergency_contact'] ? htmlspecialchars($student['emergency_contact']) : ''; ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">เบอร์โทรฉุกเฉิน</label>
                    <input type="tel" name="emergency_phone" class="form-control" placeholder="0812345678"
                           value="<?php echo $student['emergency_phone'] ? htmlspecialchars($student['emergency_phone']) : ''; ?>">
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="view_student.php?id=<?php echo $student['student_id']; ?>" class="btn-custom btn-cancel">
                <i class="bi bi-x-circle"></i> ยกเลิก
            </a>
            <button type="submit" name="save_student" class="btn-custom btn-save">
                <i class="bi bi-check-circle"></i> บันทึกการแก้ไข
            </button>
        </div>
    </form>
</div>

<?php include 'includes/footer.php'; ?>
