<?php
/**
 * หน้าโปรไฟล์และแก้ไขข้อมูลนักเรียน
 */
session_start();
define('STUDENT_PAGE', true);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/functions.php';

// ตรวจสอบสิทธิ์
if (!isLoggedIn() || ($_SESSION[SESSION_USER_ROLE] != 'student' && $_SESSION[SESSION_USER_ROLE] != 'admin')) {
    redirect(BASE_URL . '/login.php');
}

$pageTitle = 'โปรไฟล์';
$currentPage = 'profile';

$db = getDB();
$userId = $_SESSION[SESSION_USER_ID];

// ดึงข้อมูลนักเรียน
$stmt = $db->prepare("
    SELECT s.*, u.full_name, u.username, u.email as user_email
    FROM students s
    LEFT JOIN users u ON s.user_id = u.user_id
    WHERE u.user_id = ?
");
$stmt->execute([$userId]);
$student = $stmt->fetch();

if (!$student) {
    setAlert('danger', 'ไม่พบข้อมูลนักเรียน');
    redirect(BASE_URL . '/login.php');
}

$studentId = $student['student_id'];

// บันทึกข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $phone = $_POST['phone'] ?? '';
    $email = $_POST['email'] ?? '';
    $parentName = $_POST['parent_name'] ?? '';
    $parentPhone = $_POST['parent_phone'] ?? '';
    $address = $_POST['address'] ?? '';

    try {
        // อัพเดทข้อมูลนักเรียน
        $stmt = $db->prepare("
            UPDATE students
            SET phone = ?, email = ?, parent_name = ?, parent_phone = ?, address = ?
            WHERE student_id = ?
        ");
        $stmt->execute([$phone, $email, $parentName, $parentPhone, $address, $studentId]);

        // อัพเดทอีเมลใน users
        $stmt = $db->prepare("UPDATE users SET email = ? WHERE user_id = ?");
        $stmt->execute([$email, $userId]);

        logActivity($userId, 'update_profile', 'แก้ไขโปรไฟล์');
        setAlert('success', 'บันทึกข้อมูลเรียบร้อยแล้ว');
        redirect(BASE_URL . '/student/profile.php');

    } catch (Exception $e) {
        setAlert('danger', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
}

// อัพโหลดรูปภาพ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['photo'])) {
    $file = $_FILES['photo'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        $maxSize = 2 * 1024 * 1024; // 2MB

        if (!in_array($file['type'], $allowedTypes)) {
            setAlert('danger', 'รองรับเฉพาะไฟล์ JPG, JPEG, PNG เท่านั้น');
        } elseif ($file['size'] > $maxSize) {
            setAlert('danger', 'ไฟล์ต้องมีขนาดไม่เกิน 2MB');
        } else {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $newName = 'student_' . $studentId . '_' . time() . '.' . $ext;
            $uploadPath = UPLOAD_PATH . '/photos/' . $newName;

            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                // ลบรูปเก่า
                if (!empty($student['photo_path']) && file_exists($student['photo_path'])) {
                    @unlink($student['photo_path']);
                }

                // อัพเดทในฐานข้อมูล
                $stmt = $db->prepare("UPDATE students SET photo_path = ? WHERE student_id = ?");
                $stmt->execute(['uploads/photos/' . $newName, $studentId]);

                // บันทึกลง student_photos
                $stmt = $db->prepare("
                    INSERT INTO student_photos (student_id, photo_path, uploaded_by)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$studentId, 'uploads/photos/' . $newName, $userId]);

                logActivity($userId, 'upload_photo', 'อัพโหลดรูปภาพ');
                setAlert('success', 'อัพโหลดรูปภาพเรียบร้อยแล้ว');
                redirect(BASE_URL . '/student/profile.php');
            } else {
                setAlert('danger', 'ไม่สามารถอัพโหลดไฟล์ได้');
            }
        }
    }
}

// รีเฟรชข้อมูลหลังจากอัพเดท
$stmt->execute([$userId]);
$student = $stmt->fetch();

include 'includes/header.php';
?>

<!-- Profile Card -->
<div class="card">
    <div class="card-body text-center">
        <div class="photo-upload mb-3">
            <img src="<?php echo !empty($student['photo_path']) ? BASE_URL . '/' . $student['photo_path'] : 'https://via.placeholder.com/150'; ?>"
                 alt="Profile"
                 id="profileImage"
                 onerror="this.src='https://via.placeholder.com/150'">
            <label for="photoUpload" class="upload-btn">
                <i class="bi bi-camera"></i>
            </label>
            <form method="POST" enctype="multipart/form-data" id="photoForm">
                <input type="file" id="photoUpload" name="photo" accept="image/*" style="display: none;" onchange="uploadPhoto()">
            </form>
        </div>

        <h5><?php echo clean($student['first_name'] . ' ' . $student['last_name']); ?></h5>
        <p class="text-muted mb-0"><?php echo clean($student['student_code']); ?></p>
        <p class="text-muted"><?php echo clean($student['class'] ?? 'ไม่ระบุชั้นเรียน'); ?></p>
    </div>
</div>

<!-- Profile Form -->
<div class="card mt-3">
    <div class="card-header">
        <i class="bi bi-pencil-square"></i> แก้ไขข้อมูลส่วนตัว
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="mb-3">
                <label class="form-label"><i class="bi bi-person"></i> ชื่อ-นามสกุล</label>
                <input type="text" class="form-control" value="<?php echo clean($student['first_name'] . ' ' . $student['last_name']); ?>" readonly>
                <small class="text-muted">ไม่สามารถแก้ไขได้ กรุณาติดต่อแอดมิน</small>
            </div>

            <div class="mb-3">
                <label class="form-label"><i class="bi bi-credit-card-2-front"></i> รหัสนักเรียน</label>
                <input type="text" class="form-control" value="<?php echo clean($student['student_code']); ?>" readonly>
            </div>

            <div class="mb-3">
                <label class="form-label"><i class="bi bi-book"></i> ชั้นเรียน</label>
                <input type="text" class="form-control" value="<?php echo clean($student['class'] ?? '-'); ?>" readonly>
            </div>

            <hr class="my-4">

            <div class="mb-3">
                <label class="form-label"><i class="bi bi-telephone"></i> เบอร์โทรศัพท์ <span class="text-danger">*</span></label>
                <input type="tel" class="form-control" name="phone" value="<?php echo clean($student['phone'] ?? ''); ?>"
                       placeholder="0812345678" pattern="[0-9]{10}" required>
            </div>

            <div class="mb-3">
                <label class="form-label"><i class="bi bi-envelope"></i> อีเมล</label>
                <input type="email" class="form-control" name="email" value="<?php echo clean($student['email'] ?? ''); ?>"
                       placeholder="student@example.com">
            </div>

            <div class="mb-3">
                <label class="form-label"><i class="bi bi-geo-alt"></i> ที่อยู่</label>
                <textarea class="form-control" name="address" rows="3" placeholder="ที่อยู่ของคุณ"><?php echo clean($student['address'] ?? ''); ?></textarea>
            </div>

            <hr class="my-4">
            <h6 class="mb-3"><i class="bi bi-people"></i> ข้อมูลผู้ปกครอง</h6>

            <div class="mb-3">
                <label class="form-label"><i class="bi bi-person"></i> ชื่อผู้ปกครอง</label>
                <input type="text" class="form-control" name="parent_name" value="<?php echo clean($student['parent_name'] ?? ''); ?>"
                       placeholder="ชื่อผู้ปกครอง">
            </div>

            <div class="mb-3">
                <label class="form-label"><i class="bi bi-telephone"></i> เบอร์โทรผู้ปกครอง</label>
                <input type="tel" class="form-control" name="parent_phone" value="<?php echo clean($student['parent_phone'] ?? ''); ?>"
                       placeholder="0812345678" pattern="[0-9]{10}">
            </div>

            <div class="d-grid gap-2">
                <button type="submit" name="save_profile" class="btn btn-primary btn-lg">
                    <i class="bi bi-save"></i> บันทึกข้อมูล
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="bi bi-x-circle"></i> ยกเลิก
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Additional Info -->
<div class="card mt-3 mb-4">
    <div class="card-header">
        <i class="bi bi-info-circle"></i> ข้อมูลเพิ่มเติม
    </div>
    <div class="card-body">
        <table class="table table-sm table-borderless mb-0">
            <tr>
                <td width="140"><i class="bi bi-calendar-plus"></i> วันที่ลงทะเบียน</td>
                <td><?php echo thaiDate($student['created_at']); ?></td>
            </tr>
            <tr>
                <td><i class="bi bi-calendar-check"></i> แก้ไขล่าสุด</td>
                <td><?php echo $student['updated_at'] ? thaiDate($student['updated_at']) : '-'; ?></td>
            </tr>
            <tr>
                <td><i class="bi bi-toggle-on"></i> สถานะ</td>
                <td>
                    <?php if ($student['status'] == 'active'): ?>
                        <span class="badge bg-success">ใช้งาน</span>
                    <?php else: ?>
                        <span class="badge bg-danger">ไม่ใช้งาน</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
</div>

<script>
function uploadPhoto() {
    if (confirm('ต้องการอัพโหลดรูปภาพใหม่?')) {
        document.getElementById('photoForm').submit();
    }
}

// Preview image before upload
document.getElementById('photoUpload').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(event) {
            document.getElementById('profileImage').src = event.target.result;
        };
        reader.readAsDataURL(file);
    }
});
</script>

<?php include 'includes/footer.php'; ?>
