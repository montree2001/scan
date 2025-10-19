<?php
session_start();
define('ADMIN_PAGE', true);
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/functions.php';

// Simulate being logged in
if (!isset($_SESSION[SESSION_USER_ID])) {
    $_SESSION[SESSION_USER_ID] = 1;
    $_SESSION[SESSION_ROLE] = 'admin';
    $_SESSION[SESSION_FULL_NAME] = 'Admin User';
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test DataTables</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
</head>
<body>
    <div class="container mt-5">
        <h2>Test DataTables</h2>
        <table id="testTable" class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>#</th>
                    <th>รหัสนักเรียน</th>
                    <th>ชื่อ-นามสกุล</th>
                    <th>ชื่อเล่น</th>
                    <th>ชั้นเรียน</th>
                    <th>สาขาวิชา</th>
                    <th>เบอร์โทร</th>
                    <th>Actions</th>
                </tr>
            </thead>
        </table>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>

    <script>
    $(document).ready(function() {
        $('#testTable').DataTable({
            "processing": true,
            "serverSide": true,
            "ajax": {
                "url": "<?php echo BASE_URL; ?>/admin/api_students.php",
                "type": "POST"
            },
            "columns": [
                { "data": "DT_RowIndex", "orderable": false },
                { "data": "student_code" },
                { "data": "full_name" },
                { "data": "nickname" },
                { "data": "class" },
                { "data": "major" },
                { "data": "phone" },
                { "data": "actions", "orderable": false }
            ],
            "language": {
                "lengthMenu": "แสดง _MENU_ รายการต่อหน้า",
                "zeroRecords": "ไม่พบข้อมูล",
                "info": "แสดงหน้า _PAGE_ จาก _PAGES_",
                "infoEmpty": "ไม่พบข้อมูล",
                "infoFiltered": "(กรองจาก _MAX_ รายการทั้งหมด)",
                "search": "ค้นหา:",
                "paginate": {
                    "first": "หน้าแรก",
                    "last": "หน้าสุดท้าย",
                    "next": "ถัดไป",
                    "previous": "ก่อนหน้า"
                },
                "processing": "กำลังโหลดข้อมูล..."
            }
        });
    });
    </script>
</body>
</html>