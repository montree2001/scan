<?php
/**
 * ไฟล์ Functions ทั่วไปที่ใช้ในระบบ
 */

/**
 * ป้องกัน XSS Attack
 */
function clean($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect ไปหน้าอื่น
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/**
 * แสดงข้อความ Alert ด้วย Session
 */
function setAlert($type, $message) {
    $_SESSION['alert_type'] = $type; // success, danger, warning, info
    $_SESSION['alert_message'] = $message;
}

/**
 * แสดง Alert ที่เก็บใน Session
 */
function showAlert() {
    if (isset($_SESSION['alert_type']) && isset($_SESSION['alert_message'])) {
        $type = $_SESSION['alert_type'];
        $message = $_SESSION['alert_message'];

        echo "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
                {$message}
                <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
              </div>";

        unset($_SESSION['alert_type']);
        unset($_SESSION['alert_message']);
    }
}

/**
 * ตรวจสอบว่า Login แล้วหรือยัง
 */
function isLoggedIn() {
    return isset($_SESSION[SESSION_USER_ID]);
}

/**
 * ตรวจสอบ Role ของผู้ใช้
 */
function hasRole($role) {
    return isset($_SESSION[SESSION_ROLE]) && $_SESSION[SESSION_ROLE] === $role;
}

/**
 * ตรวจสอบว่าเป็น Admin หรือไม่
 */
function isAdmin() {
    return hasRole('admin');
}

/**
 * ตรวจสอบว่าเป็น Student หรือไม่
 */
function isStudent() {
    return hasRole('student');
}

/**
 * ตรวจสอบว่าเป็น Staff หรือไม่
 */
function isStaff() {
    return hasRole('staff');
}

/**
 * บังคับให้ Login ก่อนใช้งาน
 */
function requireLogin() {
    if (!isLoggedIn()) {
        redirect(BASE_URL . '/login.php');
    }
}

/**
 * บังคับให้เป็น Admin
 */
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        setAlert('danger', 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
        redirect(BASE_URL . '/index.php');
    }
}

/**
 * บังคับให้เป็น Staff
 */
function requireStaff() {
    requireLogin();
    if (!isStaff() && !isAdmin()) {
        setAlert('danger', 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้');
        redirect(BASE_URL . '/index.php');
    }
}

/**
 * Format วันที่เป็นภาษาไทย
 */
function thaiDate($date, $format = 'd/m/Y') {
    if (empty($date) || $date == '0000-00-00') {
        return '-';
    }

    $timestamp = strtotime($date);

    $thaiMonths = [
        1 => 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
        'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'
    ];

    $day = date('d', $timestamp);
    $month = $thaiMonths[(int)date('m', $timestamp)];
    $year = date('Y', $timestamp) + 543;

    return "{$day} {$month} {$year}";
}

/**
 * Format เวลา
 */
function formatTime($time) {
    if (empty($time)) {
        return '-';
    }
    return date('H:i', strtotime($time));
}

/**
 * Format วันที่และเวลา
 */
function formatDateTime($datetime) {
    if (empty($datetime) || $datetime == '0000-00-00 00:00:00') {
        return '-';
    }
    return thaiDate($datetime) . ' ' . date('H:i', strtotime($datetime)) . ' น.';
}

/**
 * สร้าง Token สำหรับป้องกัน CSRF
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * ตรวจสอบ CSRF Token
 */
function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Log กิจกรรมของผู้ใช้
 */
function logActivity($userId, $activityType, $description) {
    try {
        $db = getDB();
        $stmt = $db->prepare("
            INSERT INTO activity_logs
            (user_id, activity_type, activity_description, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");

        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $stmt->execute([$userId, $activityType, $description, $ipAddress, $userAgent]);
        return true;
    } catch (Exception $e) {
        error_log("Log Activity Error: " . $e->getMessage());
        return false;
    }
}

/**
 * ตรวจสอบและอัพโหลดไฟล์รูปภาพ
 */
function uploadImage($file, $targetDir, $prefix = '') {
    // ตรวจสอบว่ามีไฟล์หรือไม่
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return ['success' => false, 'message' => 'ไม่มีไฟล์ที่จะอัพโหลด'];
    }

    // ตรวจสอบ Error
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'เกิดข้อผิดพลาดในการอัพโหลดไฟล์'];
    }

    // ตรวจสอบขนาดไฟล์
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'ไฟล์มีขนาดใหญ่เกินไป (สูงสุด 5MB)'];
    }

    // ตรวจสอบประเภทไฟล์
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, ALLOWED_IMAGE_TYPES)) {
        return ['success' => false, 'message' => 'ประเภทไฟล์ไม่ถูกต้อง (รองรับเฉพาะ JPG, PNG)'];
    }

    // สร้างชื่อไฟล์ใหม่
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $prefix . uniqid() . '_' . time() . '.' . $extension;
    $targetPath = $targetDir . '/' . $filename;

    // ย้ายไฟล์
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'filename' => $filename, 'path' => $targetPath];
    } else {
        return ['success' => false, 'message' => 'ไม่สามารถบันทึกไฟล์ได้'];
    }
}

/**
 * ลบไฟล์
 */
function deleteFile($filePath) {
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    return false;
}

/**
 * สร้างรหัสนักเรียนอัตโนมัติ
 */
function generateStudentCode() {
    $year = date('Y') + 543; // ปี พ.ศ.
    $random = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
    return $year . $random;
}

/**
 * Pagination
 */
function paginate($totalItems, $currentPage = 1, $itemsPerPage = ITEMS_PER_PAGE) {
    $totalPages = ceil($totalItems / $itemsPerPage);
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $itemsPerPage;

    return [
        'total_items' => $totalItems,
        'total_pages' => $totalPages,
        'current_page' => $currentPage,
        'items_per_page' => $itemsPerPage,
        'offset' => $offset
    ];
}

/**
 * แสดง Pagination HTML
 */
function showPagination($pagination, $url) {
    if ($pagination['total_pages'] <= 1) {
        return '';
    }

    $html = '<nav><ul class="pagination justify-content-center">';

    // Previous
    if ($pagination['current_page'] > 1) {
        $prevPage = $pagination['current_page'] - 1;
        $html .= "<li class='page-item'><a class='page-link' href='{$url}?page={$prevPage}'>ก่อนหน้า</a></li>";
    } else {
        $html .= "<li class='page-item disabled'><span class='page-link'>ก่อนหน้า</span></li>";
    }

    // Pages
    for ($i = 1; $i <= $pagination['total_pages']; $i++) {
        if ($i == $pagination['current_page']) {
            $html .= "<li class='page-item active'><span class='page-link'>{$i}</span></li>";
        } else {
            $html .= "<li class='page-item'><a class='page-link' href='{$url}?page={$i}'>{$i}</a></li>";
        }
    }

    // Next
    if ($pagination['current_page'] < $pagination['total_pages']) {
        $nextPage = $pagination['current_page'] + 1;
        $html .= "<li class='page-item'><a class='page-link' href='{$url}?page={$nextPage}'>ถัดไป</a></li>";
    } else {
        $html .= "<li class='page-item disabled'><span class='page-link'>ถัดไป</span></li>";
    }

    $html .= '</ul></nav>';

    return $html;
}
