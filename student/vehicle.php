<?php
/**
 * หน้าจัดการยานพาหนะ
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

$pageTitle = 'ยานพาหนะ';
$currentPage = 'vehicle';

$db = getDB();
$userId = $_SESSION[SESSION_USER_ID];

// ดึงข้อมูลนักเรียน
$stmt = $db->prepare("
    SELECT s.*, u.username
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

// ลบยานพาหนะ
if (isset($_GET['delete']) && $_GET['delete']) {
    $vehicleId = $_GET['delete'];
    $stmt = $db->prepare("UPDATE student_vehicles SET status = 'inactive' WHERE vehicle_id = ? AND student_id = ?");
    $stmt->execute([$vehicleId, $studentId]);
    logActivity($userId, 'delete_vehicle', "ลบยานพาหนะ ID: $vehicleId");
    setAlert('success', 'ลบยานพาหนะเรียบร้อยแล้ว');
    redirect(BASE_URL . '/student/vehicle.php');
}

// เพิ่ม/แก้ไขยานพาหนะ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_vehicle'])) {
    $vehicleType = $_POST['vehicle_type'] ?? '';
    $licensePlate = $_POST['license_plate'] ?? '';
    $brand = $_POST['brand'] ?? '';
    $model = $_POST['model'] ?? '';
    $color = $_POST['color'] ?? '';
    $vehicleId = $_POST['vehicle_id'] ?? null;

    try {
        if ($vehicleId) {
            // แก้ไข
            $stmt = $db->prepare("
                UPDATE student_vehicles
                SET vehicle_type = ?, license_plate = ?, brand = ?, model = ?, color = ?
                WHERE vehicle_id = ? AND student_id = ?
            ");
            $stmt->execute([$vehicleType, $licensePlate, $brand, $model, $color, $vehicleId, $studentId]);
            setAlert('success', 'แก้ไขยานพาหนะเรียบร้อยแล้ว');
        } else {
            // เพิ่มใหม่
            $stmt = $db->prepare("
                INSERT INTO student_vehicles (student_id, vehicle_type, license_plate, brand, model, color, status)
                VALUES (?, ?, ?, ?, ?, ?, 'active')
            ");
            $stmt->execute([$studentId, $vehicleType, $licensePlate, $brand, $model, $color]);
            setAlert('success', 'เพิ่มยานพาหนะเรียบร้อยแล้ว');
        }

        logActivity($userId, 'save_vehicle', "บันทึกยานพาหนะ: $licensePlate");
        redirect(BASE_URL . '/student/vehicle.php');

    } catch (Exception $e) {
        setAlert('danger', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
}

// ดึงข้อมูลยานพาหนะทั้งหมด
$stmt = $db->prepare("SELECT * FROM student_vehicles WHERE student_id = ? AND status = 'active' ORDER BY created_at DESC");
$stmt->execute([$studentId]);
$vehicles = $stmt->fetchAll();

// ดึงข้อมูลสำหรับแก้ไข
$editVehicle = null;
if (isset($_GET['edit'])) {
    $editId = $_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM student_vehicles WHERE vehicle_id = ? AND student_id = ?");
    $stmt->execute([$editId, $studentId]);
    $editVehicle = $stmt->fetch();
}

include 'includes/header.php';
?>

<!-- Summary -->
<div class="card mb-3">
    <div class="card-body text-center">
        <i class="bi bi-car-front-fill text-primary" style="font-size: 48px;"></i>
        <h3 class="mt-2 mb-0"><?php echo count($vehicles); ?></h3>
        <p class="text-muted mb-0">ยานพาหนะที่ลงทะเบียน</p>
    </div>
</div>

<!-- Add Vehicle Button -->
<div class="d-grid gap-2 mb-3">
    <button class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#vehicleModal">
        <i class="bi bi-plus-circle"></i> เพิ่มยานพาหนะ
    </button>
</div>

<!-- Vehicle List -->
<?php if (empty($vehicles)): ?>
    <div class="card">
        <div class="card-body text-center py-5">
            <i class="bi bi-car-front fs-1 text-muted"></i>
            <p class="text-muted mt-3 mb-0">คุณยังไม่มียานพาหนะที่ลงทะเบียน</p>
            <p class="text-muted">กดปุ่มด้านบนเพื่อเพิ่มยานพาหนะ</p>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($vehicles as $vehicle): ?>
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <!-- Icon -->
                    <div class="me-3">
                        <?php
                        $iconClass = 'bi-car-front-fill';
                        if ($vehicle['vehicle_type'] == 'motorcycle') $iconClass = 'bi-scooter';
                        elseif ($vehicle['vehicle_type'] == 'bicycle') $iconClass = 'bi-bicycle';
                        ?>
                        <i class="bi <?php echo $iconClass; ?> text-primary" style="font-size: 48px;"></i>
                    </div>

                    <!-- Info -->
                    <div class="flex-fill">
                        <h5 class="mb-1">
                            <?php
                            $typeNames = [
                                'car' => 'รถยนต์',
                                'motorcycle' => 'รถมอเตอร์ไซค์',
                                'bicycle' => 'จักรยาน'
                            ];
                            echo $typeNames[$vehicle['vehicle_type']] ?? $vehicle['vehicle_type'];
                            ?>
                        </h5>
                        <div class="mb-2">
                            <span class="badge bg-primary fs-6">
                                <?php echo clean($vehicle['license_plate']); ?>
                            </span>
                        </div>
                        <small class="text-muted">
                            <?php if ($vehicle['brand'] || $vehicle['model']): ?>
                                <i class="bi bi-info-circle"></i>
                                <?php echo clean($vehicle['brand'] . ' ' . $vehicle['model']); ?>
                                <br>
                            <?php endif; ?>
                            <?php if ($vehicle['color']): ?>
                                <i class="bi bi-palette"></i>
                                สี: <?php echo clean($vehicle['color']); ?>
                                <br>
                            <?php endif; ?>
                            <i class="bi bi-calendar-plus"></i>
                            ลงทะเบียนเมื่อ: <?php echo thaiDate($vehicle['created_at']); ?>
                        </small>
                    </div>

                    <!-- Actions -->
                    <div class="ms-2">
                        <a href="?edit=<?php echo $vehicle['vehicle_id']; ?>" class="btn btn-sm btn-warning mb-1">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <button onclick="deleteVehicle(<?php echo $vehicle['vehicle_id']; ?>, '<?php echo clean($vehicle['license_plate']); ?>')"
                                class="btn btn-sm btn-danger">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Info Card -->
<div class="card mt-3 mb-4">
    <div class="card-header">
        <i class="bi bi-info-circle"></i> ข้อมูลสำคัญ
    </div>
    <div class="card-body">
        <ul class="mb-0">
            <li class="mb-2">ลงทะเบียนยานพาหนะทุกคันที่ใช้เข้าวิทยาลัย</li>
            <li class="mb-2">กรุณาระบุทะเบียนรถให้ถูกต้อง</li>
            <li class="mb-0">ข้อมูลนี้จะใช้ในการตรวจสอบความปลอดภัย</li>
        </ul>
    </div>
</div>

<!-- Modal เพิ่ม/แก้ไขยานพาหนะ -->
<div class="modal fade" id="vehicleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-car-front"></i>
                        <span id="modalTitle"><?php echo $editVehicle ? 'แก้ไข' : 'เพิ่ม'; ?>ยานพาหนะ</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="vehicle_id" value="<?php echo $editVehicle['vehicle_id'] ?? ''; ?>">

                    <div class="mb-3">
                        <label class="form-label">ประเภทยานพาหนะ <span class="text-danger">*</span></label>
                        <select class="form-select" name="vehicle_type" required>
                            <option value="car" <?php echo (isset($editVehicle) && $editVehicle['vehicle_type'] == 'car') ? 'selected' : ''; ?>>รถยนต์</option>
                            <option value="motorcycle" <?php echo (isset($editVehicle) && $editVehicle['vehicle_type'] == 'motorcycle') ? 'selected' : ''; ?>>รถมอเตอร์ไซค์</option>
                            <option value="bicycle" <?php echo (isset($editVehicle) && $editVehicle['vehicle_type'] == 'bicycle') ? 'selected' : ''; ?>>จักรยาน</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">ทะเบียนรถ <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="license_plate"
                               value="<?php echo clean($editVehicle['license_plate'] ?? ''); ?>"
                               placeholder="กข 1234 กรุงเทพ" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">ยี่ห้อ</label>
                        <input type="text" class="form-control" name="brand"
                               value="<?php echo clean($editVehicle['brand'] ?? ''); ?>"
                               placeholder="เช่น Toyota, Honda">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">รุ่น</label>
                        <input type="text" class="form-control" name="model"
                               value="<?php echo clean($editVehicle['model'] ?? ''); ?>"
                               placeholder="เช่น Vios, PCX">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">สี</label>
                        <input type="text" class="form-control" name="color"
                               value="<?php echo clean($editVehicle['color'] ?? ''); ?>"
                               placeholder="เช่น ขาว, ดำ, แดง">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" name="save_vehicle" class="btn btn-primary">
                        <i class="bi bi-save"></i> บันทึก
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function deleteVehicle(id, plate) {
    if (confirm('ต้องการลบยานพาหนะ ' + plate + ' ใช่หรือไม่?')) {
        window.location.href = '?delete=' + id;
    }
}

<?php if ($editVehicle): ?>
// Auto show modal for edit
document.addEventListener('DOMContentLoaded', function() {
    var modal = new bootstrap.Modal(document.getElementById('vehicleModal'));
    modal.show();
});
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
