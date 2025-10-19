<?php
/**
 * หน้าจัดการผู้ใช้งาน
 */
session_start();
define('ADMIN_PAGE', true);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/functions.php';

requireAdmin();

$pageTitle = 'จัดการผู้ใช้งาน';
$currentPage = 'users';

$db = getDB();
$action = $_GET['action'] ?? 'list';
$userId = $_GET['id'] ?? null;

// ลบผู้ใช้ (Hard Delete - ลบจริง)
if ($action === 'delete' && $userId) {
    try {
        // ตรวจสอบว่าไม่ใช่ตัวเอง
        if ($userId == $_SESSION[SESSION_USER_ID]) {
            setAlert('danger', 'ไม่สามารถลบบัญชีของตัวเองได้');
            redirect(BASE_URL . '/admin/users.php');
        }

        // ดึงข้อมูลก่อนลบ
        $stmt = $db->prepare("SELECT username FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if ($user) {
            // ลบจริงจากฐานข้อมูล
            $stmt = $db->prepare("DELETE FROM users WHERE user_id = ?");
            $stmt->execute([$userId]);

            logActivity($_SESSION[SESSION_USER_ID], 'delete_user', "ลบผู้ใช้: {$user['username']} (ID: $userId)");
            setAlert('success', 'ลบผู้ใช้ ' . htmlspecialchars($user['username']) . ' ออกจากระบบแล้ว');
        } else {
            setAlert('danger', 'ไม่พบผู้ใช้ที่ต้องการลบ');
        }
    } catch (Exception $e) {
        setAlert('danger', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
    redirect(BASE_URL . '/admin/users.php');
}

// Soft Delete (ปิดการใช้งาน)
if ($action === 'deactivate' && $userId) {
    try {
        $stmt = $db->prepare("UPDATE users SET status = 'inactive' WHERE user_id = ?");
        $stmt->execute([$userId]);
        logActivity($_SESSION[SESSION_USER_ID], 'deactivate_user', "ปิดการใช้งานผู้ใช้ ID: $userId");
        setAlert('success', 'ปิดการใช้งานผู้ใช้เรียบร้อยแล้ว');
    } catch (Exception $e) {
        setAlert('danger', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
    redirect(BASE_URL . '/admin/users.php');
}

// เปิดการใช้งานอีกครั้ง
if ($action === 'activate' && $userId) {
    try {
        $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE user_id = ?");
        $stmt->execute([$userId]);
        logActivity($_SESSION[SESSION_USER_ID], 'activate_user', "เปิดการใช้งานผู้ใช้ ID: $userId");
        setAlert('success', 'เปิดการใช้งานผู้ใช้เรียบร้อยแล้ว');
    } catch (Exception $e) {
        setAlert('danger', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
    redirect(BASE_URL . '/admin/users.php');
}

// บันทึกผู้ใช้
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $username = $_POST['username'] ?? '';
    $fullName = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? 'student';
    $password = $_POST['password'] ?? '';
    $editId = $_POST['user_id'] ?? null;

    try {
        if ($editId) {
            // แก้ไข - ตรวจสอบ username ซ้ำ (ยกเว้นตัวเอง)
            $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
            $stmt->execute([$username, $editId]);
            if ($stmt->rowCount() > 0) {
                setAlert('danger', 'Username นี้มีอยู่แล้ว กรุณาเลือก username อื่น');
                redirect(BASE_URL . '/admin/users.php?action=edit&id=' . $editId);
            }

            if (!empty($password)) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, role = ?, password = ? WHERE user_id = ?");
                $stmt->execute([$username, $fullName, $email, $role, $hashedPassword, $editId]);
            } else {
                $stmt = $db->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, role = ? WHERE user_id = ?");
                $stmt->execute([$username, $fullName, $email, $role, $editId]);
            }
            setAlert('success', 'แก้ไขผู้ใช้เรียบร้อยแล้ว');
        } else {
            // เพิ่มใหม่
            if (empty($password)) {
                setAlert('danger', 'กรุณากรอกรหัสผ่าน');
                redirect(BASE_URL . '/admin/users.php');
            }

            // ตรวจสอบ username ซ้ำ
            $stmt = $db->prepare("SELECT user_id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->rowCount() > 0) {
                setAlert('danger', 'Username "' . htmlspecialchars($username) . '" มีอยู่แล้ว กรุณาเลือก username อื่น');
                redirect(BASE_URL . '/admin/users.php');
            }

            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, password, email, full_name, role, status) VALUES (?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$username, $hashedPassword, $email, $fullName, $role]);
            setAlert('success', 'เพิ่มผู้ใช้เรียบร้อยแล้ว');
        }
        redirect(BASE_URL . '/admin/users.php');
    } catch (Exception $e) {
        setAlert('danger', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
    }
}

// ดึงข้อมูลผู้ใช้สำหรับแก้ไข
$editUser = null;
if ($action === 'edit' && $userId) {
    $stmt = $db->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $editUser = $stmt->fetch();
}

// ดึงข้อมูลผู้ใช้ทั้งหมด (รวมทั้ง active และ inactive)
$showInactive = $_GET['show_inactive'] ?? false;
if ($showInactive) {
    $stmt = $db->query("SELECT * FROM users ORDER BY user_id DESC");
} else {
    $stmt = $db->query("SELECT * FROM users WHERE status = 'active' ORDER BY user_id DESC");
}
$users = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="row mb-3">
    <div class="col-md-6">
        <?php if ($showInactive): ?>
            <a href="users.php" class="btn btn-secondary">
                <i class="bi bi-eye"></i> แสดงเฉพาะผู้ใช้งาน
            </a>
        <?php else: ?>
            <a href="users.php?show_inactive=1" class="btn btn-outline-secondary">
                <i class="bi bi-eye-slash"></i> แสดงทั้งหมด (รวมปิดใช้งาน)
            </a>
        <?php endif; ?>
    </div>
    <div class="col-md-6 text-end">
        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#userModal">
            <i class="bi bi-plus-circle"></i> เพิ่มผู้ใช้ใหม่
        </button>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <i class="bi bi-people"></i> รายชื่อผู้ใช้งานทั้งหมด (<?php echo count($users); ?> คน)
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th width="80">ID</th>
                        <th>Username</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th>Email</th>
                        <th>สิทธิ์</th>
                        <th>Login ล่าสุด</th>
                        <th width="150" class="text-center">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr class="<?php echo $user['status'] == 'inactive' ? 'table-secondary' : ''; ?>">
                            <td><?php echo $user['user_id']; ?></td>
                            <td>
                                <strong><?php echo clean($user['username']); ?></strong>
                                <?php if ($user['status'] == 'inactive'): ?>
                                    <span class="badge bg-secondary ms-1">ปิดใช้งาน</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo clean($user['full_name']); ?></td>
                            <td><?php echo clean($user['email']); ?></td>
                            <td>
                                <?php
                                $roleColors = ['admin' => 'danger', 'staff' => 'warning', 'student' => 'info'];
                                $roleName = ['admin' => 'แอดมิน', 'staff' => 'เจ้าหน้าที่', 'student' => 'นักเรียน'];
                                ?>
                                <span class="badge bg-<?php echo $roleColors[$user['role']] ?? 'secondary'; ?>">
                                    <?php echo $roleName[$user['role']] ?? $user['role']; ?>
                                </span>
                            </td>
                            <td><?php echo $user['last_login'] ? formatDateTime($user['last_login']) : '-'; ?></td>
                            <td class="text-center">
                                <a href="?action=edit&id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-warning" title="แก้ไข">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php if ($user['user_id'] != $_SESSION[SESSION_USER_ID]): ?>
                                    <?php if ($user['status'] == 'active'): ?>
                                        <button class="btn btn-sm btn-secondary" onclick="deactivateUser(<?php echo $user['user_id']; ?>, '<?php echo clean($user['username']); ?>')" title="ปิดใช้งาน">
                                            <i class="bi bi-dash-circle"></i>
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-success" onclick="activateUser(<?php echo $user['user_id']; ?>, '<?php echo clean($user['username']); ?>')" title="เปิดใช้งาน">
                                            <i class="bi bi-check-circle"></i>
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['user_id']; ?>, '<?php echo clean($user['username']); ?>')" title="ลบถาวร">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal เพิ่ม/แก้ไขผู้ใช้ -->
<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-person-plus"></i> <span id="modalTitle">เพิ่มผู้ใช้ใหม่</span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="user_id">

                    <div class="mb-3">
                        <label class="form-label">Username <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="username" id="username" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="full_name" id="full_name" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control" name="email" id="email">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">สิทธิ์ <span class="text-danger">*</span></label>
                        <select class="form-select" name="role" id="role" required>
                            <option value="student">นักเรียน</option>
                            <option value="staff">เจ้าหน้าที่</option>
                            <option value="admin">แอดมิน</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">รหัสผ่าน <span id="pwdNote">(ใส่เฉพาะตอนต้องการเปลี่ยน)</span></label>
                        <input type="password" class="form-control" name="password" id="password">
                        <small class="text-muted">ความยาวอย่างน้อย 6 ตัวอักษร</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" name="save_user" class="btn btn-primary">
                        <i class="bi bi-save"></i> บันทึก
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function deleteUser(id, username) {
    if (confirm('⚠️ คุณต้องการลบผู้ใช้ "' + username + '" ออกจากระบบถาวรใช่หรือไม่?\n\nการลบนี้ไม่สามารถกู้คืนได้!')) {
        window.location.href = '?action=delete&id=' + id;
    }
}

function deactivateUser(id, username) {
    if (confirm('ต้องการปิดการใช้งานผู้ใช้ "' + username + '" ใช่หรือไม่?\n\n(สามารถเปิดใช้งานอีกครั้งได้ภายหลัง)')) {
        window.location.href = '?action=deactivate&id=' + id;
    }
}

function activateUser(id, username) {
    if (confirm('ต้องการเปิดใช้งานผู้ใช้ "' + username + '" อีกครั้งใช่หรือไม่?')) {
        window.location.href = '?action=activate&id=' + id;
    }
}

<?php if ($editUser): ?>
document.addEventListener('DOMContentLoaded', function() {
    var modal = new bootstrap.Modal(document.getElementById('userModal'));
    modal.show();

    document.getElementById('modalTitle').textContent = 'แก้ไขข้อมูลผู้ใช้';
    document.getElementById('user_id').value = '<?php echo $editUser['user_id']; ?>';
    document.getElementById('username').value = '<?php echo clean($editUser['username']); ?>';
    document.getElementById('full_name').value = '<?php echo clean($editUser['full_name']); ?>';
    document.getElementById('email').value = '<?php echo clean($editUser['email']); ?>';
    document.getElementById('role').value = '<?php echo $editUser['role']; ?>';
    document.getElementById('pwdNote').style.display = 'inline';
    document.getElementById('password').removeAttribute('required');
});
<?php endif; ?>
</script>

<?php include 'includes/footer.php'; ?>
