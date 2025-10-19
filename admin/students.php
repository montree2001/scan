<?php
/**
 * หน้าจัดการนักเรียน (CRUD)
 * รองรับข้อมูลจากไฟล์ CSV
 */
session_start();
define('ADMIN_PAGE', true);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/functions.php';

requireAdmin();

$pageTitle = 'จัดการนักเรียน';
$currentPage = 'students';

$db = getDB();

// จัดการ Actions
$action = $_GET['action'] ?? 'list';
$studentId = $_GET['id'] ?? null;

// ลบนักเรียน
if ($action === 'delete' && $studentId) {
    try {
        $stmt = $db->prepare("UPDATE students SET status = 'inactive' WHERE student_id = ?");
        $stmt->execute([$studentId]);

        logActivity($_SESSION[SESSION_USER_ID], 'delete_student', "ลบนักเรียน ID: $studentId");
        $_SESSION['success'] = 'ลบนักเรียนเรียบร้อยแล้ว';
    } catch (Exception $e) {
        $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }
    redirect(BASE_URL . '/admin/students.php');
}

// บันทึกนักเรียน (เพิ่ม/แก้ไข)
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
    $grade = $_POST['grade'] ?? '';
    $phone = trim($_POST['phone']) ?? '';
    $email = trim($_POST['email']) ?? '';
    $parentName = trim($_POST['parent_name']) ?? '';
    $parentPhone = trim($_POST['parent_phone']) ?? '';
    $address = trim($_POST['address']) ?? '';
    $emergencyContact = trim($_POST['emergency_contact']) ?? '';
    $emergencyPhone = trim($_POST['emergency_phone']) ?? '';
    $editId = $_POST['student_id'] ?? null;

    try {
        if ($editId) {
            // แก้ไข
            $stmt = $db->prepare("
                UPDATE students SET
                    student_code = ?, id_card = ?, first_name = ?, last_name = ?, nickname = ?,
                    date_of_birth = ?, gender = ?, class = ?, major = ?, grade = ?,
                    phone = ?, email = ?, parent_name = ?, parent_phone = ?,
                    address = ?, emergency_contact = ?, emergency_phone = ?
                WHERE student_id = ?
            ");
            $stmt->execute([
                $studentCode, $idCard, $firstName, $lastName, $nickname,
                $dateOfBirth, $gender, $class, $major, $grade,
                $phone, $email, $parentName, $parentPhone,
                $address, $emergencyContact, $emergencyPhone,
                $editId
            ]);
            logActivity($_SESSION[SESSION_USER_ID], 'update_student', "แก้ไขนักเรียน: $firstName $lastName");
            $_SESSION['success'] = 'แก้ไขข้อมูลนักเรียนเรียบร้อยแล้ว';
        } else {
            // เพิ่มใหม่
            $stmt = $db->prepare("
                INSERT INTO students (
                    student_code, id_card, first_name, last_name, nickname,
                    date_of_birth, gender, class, major, grade,
                    phone, email, parent_name, parent_phone,
                    address, emergency_contact, emergency_phone, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            $stmt->execute([
                $studentCode, $idCard, $firstName, $lastName, $nickname,
                $dateOfBirth, $gender, $class, $major, $grade,
                $phone, $email, $parentName, $parentPhone,
                $address, $emergencyContact, $emergencyPhone
            ]);
            logActivity($_SESSION[SESSION_USER_ID], 'add_student', "เพิ่มนักเรียน: $firstName $lastName");
            $_SESSION['success'] = 'เพิ่มนักเรียนเรียบร้อยแล้ว';
        }
        redirect(BASE_URL . '/admin/students.php');
    } catch (Exception $e) {
        $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }
}

// ดึงข้อมูลนักเรียนสำหรับแก้ไข
$editStudent = null;
if ($action === 'edit' && $studentId) {
    $stmt = $db->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt->execute([$studentId]);
    $editStudent = $stmt->fetch(PDO::FETCH_ASSOC);
}

// ค้นหาและกรอง
$search = $_GET['search'] ?? '';
$filterClass = $_GET['filter_class'] ?? '';
$page = $_GET['page'] ?? 1;

// Query หลัก
$where = ["status = 'active'"];
$params = [];

if ($search) {
    $where[] = "(student_code LIKE ? OR id_card LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR nickname LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam, $searchParam]);
}

if ($filterClass) {
    $where[] = "class = ?";
    $params[] = $filterClass;
}

$whereClause = implode(' AND ', $where);

// นับจำนวนทั้งหมด
$countStmt = $db->prepare("SELECT COUNT(*) as total FROM students WHERE $whereClause");
$countStmt->execute($params);
$totalStudents = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

// Pagination (not used with DataTables)
$pagination = paginate($totalStudents, $page, ITEMS_PER_PAGE);

// ดึงข้อมูลทั้งหมด (DataTables จะจัดการ pagination)
$stmt = $db->prepare("
    SELECT * FROM students
    WHERE $whereClause
    ORDER BY student_code ASC
");
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงรายการชั้นเรียนทั้งหมด
$classStmt = $db->query("SELECT DISTINCT class FROM students WHERE class IS NOT NULL AND class != '' AND status = 'active' ORDER BY class");
$classes = $classStmt->fetchAll(PDO::FETCH_COLUMN);

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
/* Modern Stats Cards */
.stats-card {
    background: white;
    border-radius: 15px;
    padding: 25px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    border-left: 4px solid;
    transition: all 0.3s ease;
    margin-bottom: 20px;
    overflow: hidden;
    position: relative;
}

.stats-card::before {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 150px;
    height: 150px;
    border-radius: 50%;
    opacity: 0.1;
    transform: translate(50%, -50%);
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.stats-card.purple {
    border-left-color: #667eea;
}
.stats-card.purple::before {
    background: #667eea;
}

.stats-card.green {
    border-left-color: #11998e;
}
.stats-card.green::before {
    background: #11998e;
}

.stats-card.orange {
    border-left-color: #f5576c;
}
.stats-card.orange::before {
    background: #f5576c;
}

.stats-card.blue {
    border-left-color: #4facfe;
}
.stats-card.blue::before {
    background: #4facfe;
}

.stats-card .icon {
    font-size: 3rem;
    opacity: 0.2;
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
}

.stats-card .number {
    font-size: 2.5rem;
    font-weight: 700;
    margin: 0;
    position: relative;
    z-index: 1;
}

.stats-card .label {
    font-size: 0.95rem;
    color: #6c757d;
    margin: 0;
    position: relative;
    z-index: 1;
}

.stats-card.purple .number { color: #667eea; }
.stats-card.green .number { color: #11998e; }
.stats-card.orange .number { color: #f5576c; }
.stats-card.blue .number { color: #4facfe; }

/* Table Fixes */
#studentsTable {
    font-size: 0.9rem;
}

#studentsTable td {
    vertical-align: middle;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 200px;
}

#studentsTable td.wrap-text {
    white-space: normal;
    word-wrap: break-word;
}

#studentsTable .student-name {
    font-weight: 600;
    color: #2c3e50;
}

#studentsTable .student-code {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 500;
    display: inline-block;
}

#studentsTable .gender-badge {
    font-size: 0.8rem;
    padding: 2px 8px;
    border-radius: 4px;
}

#studentsTable .phone-link {
    color: #11998e;
    text-decoration: none;
    font-size: 0.85rem;
}

#studentsTable .phone-link:hover {
    text-decoration: underline;
}

/* Action Buttons */
.action-btn {
    padding: 6px 10px;
    font-size: 0.85rem;
    border: none;
    border-radius: 6px;
    transition: all 0.2s;
    margin: 0 2px;
}

.action-btn:hover {
    transform: scale(1.05);
}

.action-btn.btn-view {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    color: white;
}

.action-btn.btn-edit {
    background: linear-gradient(135deg, #ffc837 0%, #ff8008 100%);
    color: white;
}

.action-btn.btn-delete {
    background: linear-gradient(135deg, #f5576c 0%, #f093fb 100%);
    color: white;
}

/* Card Improvements */
.card {
    border: none;
    border-radius: 15px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.08);
    overflow: hidden;
}

.card-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    color: white;
    border: none;
    padding: 20px;
    font-size: 1.1rem;
    font-weight: 600;
}

.card-body {
    padding: 25px;
}

/* DataTables Custom Styling */
.dataTables_wrapper .dataTables_length select {
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 5px 10px;
}

.dataTables_wrapper .dataTables_filter input {
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 8px 15px;
    margin-left: 10px;
}

.dataTables_wrapper .dataTables_paginate .paginate_button.current {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    color: white !important;
    border: none !important;
    border-radius: 6px !important;
}

.dataTables_wrapper .dataTables_paginate .paginate_button:hover {
    background: #f8f9fa !important;
    color: #667eea !important;
    border: 1px solid #667eea !important;
    border-radius: 6px !important;
}

/* Responsive Table */
@media (max-width: 768px) {
    #studentsTable {
        font-size: 0.8rem;
    }

    .action-btn {
        padding: 4px 8px;
        font-size: 0.75rem;
        margin: 1px;
    }

    .stats-card .number {
        font-size: 2rem;
    }

    .stats-card .icon {
        font-size: 2.5rem;
    }
}
</style>

<!-- สถิติแบบใหม่ -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="stats-card purple">
            <i class="bi bi-people-fill icon"></i>
            <p class="number"><?php echo number_format($totalStudents); ?></p>
            <p class="label">นักเรียนทั้งหมด</p>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stats-card green">
            <i class="bi bi-card-checklist icon"></i>
            <?php
            $stmt = $db->query("SELECT COUNT(*) as total FROM students WHERE id_card IS NOT NULL AND id_card != '' AND status = 'active'");
            $withIdCard = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            ?>
            <p class="number"><?php echo number_format($withIdCard); ?></p>
            <p class="label">มีเลขบัตรประชาชน</p>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stats-card orange">
            <i class="bi bi-door-open icon"></i>
            <?php
            $stmt = $db->query("SELECT COUNT(DISTINCT class) as total FROM students WHERE class IS NOT NULL AND class != '' AND status = 'active'");
            $totalClasses = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            ?>
            <p class="number"><?php echo number_format($totalClasses); ?></p>
            <p class="label">ห้องเรียน</p>
        </div>
    </div>
    <div class="col-lg-3 col-md-6">
        <div class="stats-card blue">
            <i class="bi bi-mortarboard icon"></i>
            <?php
            $stmt = $db->query("SELECT COUNT(DISTINCT major) as total FROM students WHERE major IS NOT NULL AND major != '' AND status = 'active'");
            $totalMajors = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
            ?>
            <p class="number"><?php echo number_format($totalMajors); ?></p>
            <p class="label">สาขาวิชา</p>
        </div>
    </div>
</div>

<!-- ส่วนค้นหาและกรอง -->
<div class="card mb-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-8">
                <form method="GET" class="row g-3">
                    <div class="col-md-5">
                        <input type="text" class="form-control" name="search" placeholder="ค้นหา (รหัส, เลขบัตร, ชื่อ, นามสกุล)" value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-4">
                        <select name="filter_class" class="form-select">
                            <option value="">-- ทุกชั้นเรียน --</option>
                            <?php foreach ($classes as $c): ?>
                                <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $filterClass == $c ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> ค้นหา</button>
                        <a href="students.php" class="btn btn-secondary"><i class="bi bi-x-circle"></i> ล้าง</a>
                    </div>
                </form>
            </div>
            <div class="col-md-4 text-end">
                <a href="import_students_csv.php" class="btn btn-info">
                    <i class="bi bi-file-earmark-arrow-down"></i> นำเข้าจาก CSV
                </a>
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                    <i class="bi bi-plus-circle"></i> เพิ่มนักเรียน
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ตารางรายชื่อนักเรียน -->
<div class="card">
    <div class="card-header">
        <i class="bi bi-people"></i> รายชื่อนักเรียนทั้งหมด (<?php echo number_format($totalStudents); ?> คน)
    </div>
    <div class="card-body">
        <?php if (empty($students)): ?>
            <div class="alert alert-info text-center">
                <i class="bi bi-info-circle"></i> ไม่พบข้อมูลนักเรียน
                <p class="mb-0 mt-2">
                    <a href="import_students_csv.php" class="btn btn-primary">
                        <i class="bi bi-file-earmark-arrow-down"></i> นำเข้าข้อมูลจาก CSV
                    </a>
                </p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table id="studentsTable" class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 60px;">#</th>
                            <th style="width: 120px;">รหัสนักเรียน</th>
                            <th style="width: 220px;">ชื่อ-นามสกุล</th>
                            <th style="width: 100px;">ชั้น/ห้อง</th>
                            <th style="width: 150px;">สาขาวิชา</th>
                            <th style="width: 140px;" class="text-center">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $no = 1;
                        foreach ($students as $student):
                        ?>
                            <tr>
                                <td><?php echo $no++; ?></td>
                                <td>
                                    <span class="student-code"><?php echo htmlspecialchars($student['student_code']); ?></span>
                                </td>
                                <td class="wrap-text">
                                    <div class="d-flex align-items-center">
                                        <div>
                                            <?php if ($student['gender'] == 'male'): ?>
                                                <i class="bi bi-gender-male text-primary me-2" style="font-size: 1.2rem;"></i>
                                            <?php else: ?>
                                                <i class="bi bi-gender-female text-danger me-2" style="font-size: 1.2rem;"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="student-name"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></div>
                                            <?php if ($student['nickname']): ?>
                                                <small class="text-muted">( <?php echo htmlspecialchars($student['nickname']); ?> )</small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($student['class']): ?>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($student['class']); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="wrap-text" style="max-width: 150px;">
                                    <small><?php echo $student['major'] ? htmlspecialchars($student['major']) : '-'; ?></small>
                                </td>
                                <td class="text-center">
                                    <a href="view_student.php?id=<?php echo $student['student_id']; ?>" class="action-btn btn-view" title="ดูรายละเอียด">
                                        <i class="bi bi-eye-fill"></i>
                                    </a>
                                    <a href="edit_student.php?id=<?php echo $student['student_id']; ?>" class="action-btn btn-edit" title="แก้ไขข้อมูล">
                                        <i class="bi bi-pencil-fill"></i>
                                    </a>
                                    <a href="?action=delete&id=<?php echo $student['student_id']; ?>" class="action-btn btn-delete" onclick="return confirm('ต้องการลบนักเรียนคนนี้หรือไม่?')" title="ลบ">
                                        <i class="bi bi-trash-fill"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination handled by DataTables -->
            <?php /* Pagination removed - DataTables handles it
            <?php if ($pagination['total_pages'] > 1): ?>
                <nav class="mt-3">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search ? '&search='  . urlencode($search) : ''; ?><?php echo $filterClass ? '&filter_class=' . urlencode($filterClass) : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
            */ ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal เพิ่ม/แก้ไขนักเรียน -->
<div class="modal fade" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-person-plus"></i> <?php echo $editStudent ? 'แก้ไขข้อมูลนักเรียน' : 'เพิ่มนักเรียนใหม่'; ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if ($editStudent): ?>
                        <input type="hidden" name="student_id" value="<?php echo $editStudent['student_id']; ?>">
                    <?php endif; ?>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">รหัสนักเรียน <span class="text-danger">*</span></label>
                            <input type="text" name="student_code" class="form-control" required
                                   value="<?php echo $editStudent ? htmlspecialchars($editStudent['student_code']) : ''; ?>">
                        </div>
                        <?php if ($hasIdCardField): ?>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">เลขบัตรประชาชน (13 หลัก)</label>
                            <input type="text" name="id_card" class="form-control" maxlength="13" pattern="\d{13}"
                                   value="<?php echo $editStudent ? htmlspecialchars($editStudent['id_card']) : ''; ?>">
                            <small class="text-muted">ใส่เฉพาะตัวเลข 13 หลัก</small>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">ชื่อ <span class="text-danger">*</span></label>
                            <input type="text" name="first_name" class="form-control" required
                                   value="<?php echo $editStudent ? htmlspecialchars($editStudent['first_name']) : ''; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">นามสกุล <span class="text-danger">*</span></label>
                            <input type="text" name="last_name" class="form-control" required
                                   value="<?php echo $editStudent ? htmlspecialchars($editStudent['last_name']) : ''; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">ชื่อเล่น</label>
                            <input type="text" name="nickname" class="form-control"
                                   value="<?php echo $editStudent ? htmlspecialchars($editStudent['nickname']) : ''; ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">เพศ</label>
                            <select name="gender" class="form-select">
                                <option value="male" <?php echo ($editStudent && $editStudent['gender'] == 'male') ? 'selected' : ''; ?>>ชาย</option>
                                <option value="female" <?php echo ($editStudent && $editStudent['gender'] == 'female') ? 'selected' : ''; ?>>หญิง</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">วันเกิด</label>
                            <input type="date" name="date_of_birth" class="form-control"
                                   value="<?php echo $editStudent ? $editStudent['date_of_birth'] : ''; ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">เบอร์โทร</label>
                            <input type="tel" name="phone" class="form-control"
                                   value="<?php echo $editStudent ? htmlspecialchars($editStudent['phone']) : ''; ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ชั้น/ห้อง</label>
                            <input type="text" name="class" class="form-control" placeholder="เช่น ปวช.1/1"
                                   value="<?php echo $editStudent ? htmlspecialchars($editStudent['class']) : ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">สาขาวิชา</label>
                            <input type="text" name="major" class="form-control" placeholder="เช่น 20101 - ช่างยนต์"
                                   value="<?php echo $editStudent ? htmlspecialchars($editStudent['major']) : ''; ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">อีเมล</label>
                        <input type="email" name="email" class="form-control"
                               value="<?php echo $editStudent ? htmlspecialchars($editStudent['email']) : ''; ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">ที่อยู่</label>
                        <textarea name="address" class="form-control" rows="3"><?php echo $editStudent ? htmlspecialchars($editStudent['address']) : ''; ?></textarea>
                    </div>

                    <hr>
                    <h6>ข้อมูลผู้ปกครอง</h6>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ชื่อผู้ปกครอง</label>
                            <input type="text" name="parent_name" class="form-control"
                                   value="<?php echo $editStudent ? htmlspecialchars($editStudent['parent_name']) : ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">เบอร์โทรผู้ปกครอง</label>
                            <input type="tel" name="parent_phone" class="form-control"
                                   value="<?php echo $editStudent ? htmlspecialchars($editStudent['parent_phone']) : ''; ?>">
                        </div>
                    </div>

                    <hr>
                    <h6>ติดต่อฉุกเฉิน</h6>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ชื่อผู้ติดต่อฉุกเฉิน</label>
                            <input type="text" name="emergency_contact" class="form-control"
                                   value="<?php echo $editStudent ? htmlspecialchars($editStudent['emergency_contact']) : ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">เบอร์โทรฉุกเฉิน</label>
                            <input type="tel" name="emergency_phone" class="form-control"
                                   value="<?php echo $editStudent ? htmlspecialchars($editStudent['emergency_phone']) : ''; ?>">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" name="save_student" class="btn btn-primary">
                        <i class="bi bi-save"></i> บันทึก
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Auto show edit modal -->
<?php if ($editStudent): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const modal = new bootstrap.Modal(document.getElementById('addStudentModal'));
        modal.show();
    });
</script>
<?php endif; ?>

<!-- DataTables Initialization -->
<script>
$(document).ready(function() {
    $('#studentsTable').DataTable({
        responsive: true,
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/th.json'
        },
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "ทั้งหมด"]],
        order: [[1, 'asc']], // เรียงตามรหัสนักเรียน
        columnDefs: [
            { orderable: false, targets: [0, -1] }, // ลำดับและปุ่มจัดการไม่ให้เรียงลำดับ
            { className: "text-center", targets: [0, -1] }, // จัดกึ่งกลาง
            { width: "60px", targets: 0 },   // #
            { width: "120px", targets: 1 },  // รหัส
            { width: "220px", targets: 2 },  // ชื่อ-นามสกุล
            { width: "100px", targets: 3 },  // ชั้น/ห้อง
            { width: "150px", targets: 4 },  // สาขาวิชา
            { width: "140px", targets: 5 }   // จัดการ
        ],
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
             '<"row"<"col-sm-12"tr>>' +
             '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
        drawCallback: function() {
            // Re-apply tooltips after redraw
            $('[title]').tooltip();
        }
    });

    // Initialize tooltips
    $('[title]').tooltip();
});
</script>

<?php include 'includes/footer.php'; ?>
