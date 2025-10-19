<?php
/**
 * API สำหรับ DataTables ของนักเรียน
 */

// Set content type header
header('Content-Type: application/json');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug information
error_log("API Students called at: " . date('Y-m-d H:i:s'));
error_log("Session data: " . print_r($_SESSION ?? [], true));
error_log("POST data: " . print_r($_POST ?? [], true));

// Check if user is logged in
$userId = null;
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
} elseif (defined('SESSION_USER_ID') && isset($_SESSION[SESSION_USER_ID])) {
    $userId = $_SESSION[SESSION_USER_ID];
}

if ($userId === null) {
    error_log("Unauthorized access - no session");
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

error_log("Authorized access for user: " . $userId);

// Include required files
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/functions.php';

$db = getDB();

// รับพารามิเตอร์จาก DataTables
$draw = intval($_POST['draw'] ?? 1);
$start = intval($_POST['start'] ?? 0);
$length = intval($_POST['length'] ?? 10);
$search = $_POST['search']['value'] ?? '';

// รับ filters
$filterClass = $_POST['filter_class'] ?? '';
$filterMajor = $_POST['filter_major'] ?? '';
$filterGender = $_POST['filter_gender'] ?? '';

// Debug POST data
error_log("Draw: " . $draw);
error_log("Start: " . $start);
error_log("Length: " . $length);
error_log("Search: " . $search);
error_log("Filter Class: " . $filterClass);
error_log("Filter Major: " . $filterMajor);
error_log("Filter Gender: " . $filterGender);

// สร้าง WHERE conditions
$where = ["status = 'active'"];
$params = [];

// เพิ่มเงื่อนไขการค้นหา
if (!empty($search)) {
    $where[] = "(student_code LIKE :search OR id_card LIKE :search OR first_name LIKE :search OR last_name LIKE :search OR nickname LIKE :search OR class LIKE :search OR major LIKE :search OR phone LIKE :search)";
    $params[':search'] = "%$search%";
}

// เพิ่ม filters
if (!empty($filterClass)) {
    $where[] = "class = :filter_class";
    $params[':filter_class'] = $filterClass;
}

if (!empty($filterMajor)) {
    $where[] = "major = :filter_major";
    $params[':filter_major'] = $filterMajor;
}

if (!empty($filterGender)) {
    $where[] = "gender = :filter_gender";
    $params[':filter_gender'] = $filterGender;
}

$whereClause = implode(' AND ', $where);

// นับจำนวนทั้งหมด (ก่อน filter)
$totalQuery = "SELECT COUNT(*) as total FROM students WHERE status = 'active'";
$totalStmt = $db->query($totalQuery);
$totalRecords = $totalStmt->fetch(PDO::FETCH_ASSOC)['total'];

// นับจำนวนหลัง filter
$filteredQuery = "SELECT COUNT(*) as total FROM students WHERE $whereClause";
$filteredStmt = $db->prepare($filteredQuery);
foreach ($params as $key => $value) {
    if ($key !== ':length' && $key !== ':start') {
        $filteredStmt->bindValue($key, $value, PDO::PARAM_STR);
    }
}
$filteredStmt->execute();
$filteredRecords = $filteredStmt->fetch(PDO::FETCH_ASSOC)['total'];

error_log("Total records: " . $totalRecords);
error_log("Filtered records: " . $filteredRecords);

// สร้าง SQL สำหรับ query ข้อมูล
$columns = "student_id, student_code, id_card, first_name, last_name, nickname, class, major, gender, phone, email, status";
$query = "SELECT $columns FROM students WHERE $whereClause";

// เพิ่มการเรียงลำดับ
$query .= " ORDER BY student_id ASC";

// เพิ่ม LIMIT และ OFFSET
$query .= " LIMIT :length OFFSET :start";
$params[':length'] = $length;
$params[':start'] = $start;

error_log("Query: " . $query);
error_log("Params: " . print_r($params, true));

// ดึงข้อมูล
$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug information
error_log("Found " . count($students) . " students");

// จัดรูปแบบข้อมูลสำหรับ DataTables
$data = [];
foreach ($students as $student) {
    $data[] = [
        'student_id' => $student['student_id'],
        'student_code' => htmlspecialchars($student['student_code']),
        'first_name' => htmlspecialchars($student['first_name']),
        'last_name' => htmlspecialchars($student['last_name']),
        'nickname' => $student['nickname'] ? htmlspecialchars($student['nickname']) : '',
        'class' => $student['class'] ? htmlspecialchars($student['class']) : '-',
        'major' => $student['major'] ? htmlspecialchars($student['major']) : '-',
        'gender' => $student['gender'] ?? '',
        'id_card' => $student['id_card'] ? htmlspecialchars($student['id_card']) : '-',
        'phone' => $student['phone'] ? htmlspecialchars($student['phone']) : '-',
        'email' => $student['email'] ? htmlspecialchars($student['email']) : '-',
        'status' => $student['status'] ?? 'active',
        'photo' => null // จะเพิ่มภายหลัง
    ];
}

// จัดรูปแบบการตอบกลับสำหรับ DataTables
$response = [
    'draw' => $draw,
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $filteredRecords,
    'data' => $data
];

error_log("Returning response with " . count($data) . " rows");
echo json_encode($response);
?>