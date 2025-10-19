<?php
/**
 * หน้าจัดการนักเรียน (CRUD) - Version 2.0
 * ใช้ DataTables แบบ Server-side Processing
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

// สถิติรวม
$statsQuery = "
    SELECT
        COUNT(*) as total,
        SUM(CASE WHEN id_card IS NOT NULL AND id_card != '' THEN 1 ELSE 0 END) as with_id_card,
        SUM(CASE WHEN gender = 'male' THEN 1 ELSE 0 END) as male_count,
        SUM(CASE WHEN gender = 'female' THEN 1 ELSE 0 END) as female_count
    FROM students
    WHERE status = 'active'
";
$stats = $db->query($statsQuery)->fetch(PDO::FETCH_ASSOC);

// ดึงรายการชั้นเรียน
$classStmt = $db->query("
    SELECT DISTINCT class
    FROM students
    WHERE class IS NOT NULL AND class != '' AND status = 'active'
    ORDER BY class
");
$classes = $classStmt->fetchAll(PDO::FETCH_COLUMN);

// ดึงรายการสาขา
$majorStmt = $db->query("
    SELECT DISTINCT major
    FROM students
    WHERE major IS NOT NULL AND major != '' AND status = 'active'
    ORDER BY major
");
$majors = $majorStmt->fetchAll(PDO::FETCH_COLUMN);

include 'includes/header.php';
?>

<style>
    /* Modern Card Styles */
    .stats-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 15px;
        padding: 25px;
        margin-bottom: 20px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        transition: transform 0.3s, box-shadow 0.3s;
    }

    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    }

    .stats-card.green {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    }

    .stats-card.orange {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }

    .stats-card.blue {
        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }

    .stats-card h2 {
        font-size: 2.5rem;
        font-weight: 700;
        margin: 0;
    }

    .stats-card p {
        margin: 5px 0 0 0;
        opacity: 0.9;
        font-size: 0.95rem;
    }

    .stats-card i {
        font-size: 3rem;
        opacity: 0.3;
    }

    /* DataTable Card */
    .table-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.08);
    }

    .table-card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
    }

    .table-card-header h4 {
        margin: 0;
        font-weight: 600;
        color: #333;
    }

    /* DataTables Custom Styles */
    #studentsTable {
        width: 100% !important;
    }

    #studentsTable thead {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    #studentsTable thead th {
        font-weight: 600;
        border: none !important;
        padding: 15px 10px;
        vertical-align: middle;
    }

    #studentsTable tbody tr {
        transition: background-color 0.3s;
    }

    #studentsTable tbody tr:hover {
        background-color: #f8f9fa;
    }

    #studentsTable tbody td {
        vertical-align: middle;
        padding: 12px 10px;
    }

    /* Badge Styles */
    .badge-status {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 500;
    }

    .badge-male {
        background: #e3f2fd;
        color: #1976d2;
    }

    .badge-female {
        background: #fce4ec;
        color: #c2185b;
    }

    .badge-active {
        background: #e8f5e9;
        color: #388e3c;
    }

    /* Action Buttons */
    .btn-action {
        padding: 6px 12px;
        font-size: 0.875rem;
        border-radius: 6px;
        margin: 0 2px;
        transition: all 0.3s;
    }

    .btn-action:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }

    /* Student Photo */
    .student-photo {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #fff;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }

    .student-photo-placeholder {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: 600;
        font-size: 1.1rem;
    }

    /* Filter Section */
    .filter-section {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 20px;
    }

    .filter-section .form-label {
        font-weight: 600;
        color: #555;
        margin-bottom: 8px;
    }

    /* Loading Overlay */
    .loading-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 9999;
        justify-content: center;
        align-items: center;
    }

    .loading-overlay.show {
        display: flex;
    }

    .loading-spinner {
        background: white;
        padding: 30px;
        border-radius: 15px;
        text-align: center;
    }

    /* DataTables Buttons */
    .dt-buttons {
        margin-bottom: 15px;
    }

    .dt-button {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
        color: white !important;
        border: none !important;
        padding: 8px 16px !important;
        border-radius: 6px !important;
        font-weight: 500 !important;
        margin-right: 8px !important;
        transition: all 0.3s !important;
    }

    .dt-button:hover {
        transform: translateY(-2px) !important;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4) !important;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .stats-card h2 {
            font-size: 1.8rem;
        }

        .table-card {
            padding: 15px;
        }

        .table-card-header {
            flex-direction: column;
            align-items: flex-start;
        }

        .table-card-header .btn {
            margin-top: 10px;
            width: 100%;
        }
    }
</style>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner">
        <div class="spinner-border text-primary" role="status" style="width: 3rem; height: 3rem;">
            <span class="visually-hidden">กำลังโหลด...</span>
        </div>
        <p class="mt-3 mb-0">กำลังประมวลผล...</p>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row">
    <div class="col-lg-3 col-md-6 mb-4">
        <div class="stats-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><?php echo number_format($stats['total']); ?></h2>
                    <p>นักเรียนทั้งหมด</p>
                </div>
                <i class="bi bi-people-fill"></i>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-4">
        <div class="stats-card green">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><?php echo number_format($stats['with_id_card']); ?></h2>
                    <p>มีเลขบัตรประชาชน</p>
                </div>
                <i class="bi bi-card-checklist"></i>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-4">
        <div class="stats-card blue">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><?php echo number_format($stats['male_count']); ?></h2>
                    <p>นักเรียนชาย</p>
                </div>
                <i class="bi bi-gender-male"></i>
            </div>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-4">
        <div class="stats-card orange">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><?php echo number_format($stats['female_count']); ?></h2>
                    <p>นักเรียนหญิง</p>
                </div>
                <i class="bi bi-gender-female"></i>
            </div>
        </div>
    </div>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <div class="row">
        <div class="col-md-3">
            <label class="form-label">
                <i class="bi bi-funnel"></i> ชั้นเรียน
            </label>
            <select class="form-select" id="filterClass">
                <option value="">ทั้งหมด</option>
                <?php foreach ($classes as $class): ?>
                    <option value="<?php echo htmlspecialchars($class); ?>">
                        <?php echo htmlspecialchars($class); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label">
                <i class="bi bi-funnel"></i> สาขาวิชา
            </label>
            <select class="form-select" id="filterMajor">
                <option value="">ทั้งหมด</option>
                <?php foreach ($majors as $major): ?>
                    <option value="<?php echo htmlspecialchars($major); ?>">
                        <?php echo htmlspecialchars($major); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label">
                <i class="bi bi-funnel"></i> เพศ
            </label>
            <select class="form-select" id="filterGender">
                <option value="">ทั้งหมด</option>
                <option value="male">ชาย</option>
                <option value="female">หญิง</option>
            </select>
        </div>

        <div class="col-md-3">
            <label class="form-label">&nbsp;</label>
            <button type="button" class="btn btn-secondary w-100" id="btnResetFilter">
                <i class="bi bi-x-circle"></i> ล้างตัวกรอง
            </button>
        </div>
    </div>
</div>

<!-- Main Table Card -->
<div class="table-card">
    <div class="table-card-header">
        <div>
            <h4><i class="bi bi-table"></i> รายชื่อนักเรียน</h4>
            <small class="text-muted">จัดการข้อมูลนักเรียนในระบบ</small>
        </div>
        <div>
            <a href="import_students_csv.php" class="btn btn-success me-2">
                <i class="bi bi-file-earmark-arrow-up"></i> นำเข้าข้อมูล
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                <i class="bi bi-plus-circle"></i> เพิ่มนักเรียน
            </button>
        </div>
    </div>

    <!-- DataTable -->
    <div class="table-responsive">
        <table id="studentsTable" class="table table-hover" style="width:100%">
            <thead>
                <tr>
                    <th width="60">รูป</th>
                    <th>รหัสนักเรียน</th>
                    <th>ชื่อ-นามสกุล</th>
                    <th>ชั้นเรียน</th>
                    <th>สาขาวิชา</th>
                    <th>เพศ</th>
                    <th>เลขบัตรประชาชน</th>
                    <th>เบอร์โทร</th>
                    <th>สถานะ</th>
                    <th width="150">จัดการ</th>
                </tr>
            </thead>
            <tbody>
                <!-- Data will be loaded via AJAX -->
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addStudentModalLabel">
                    <i class="bi bi-person-plus"></i> เพิ่มนักเรียนใหม่
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="studentForm">
                    <input type="hidden" id="student_id" name="student_id">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">รหัสนักเรียน <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="student_code" name="student_code" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">เลขบัตรประชาชน</label>
                            <input type="text" class="form-control" id="id_card" name="id_card" maxlength="13">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ชื่อ <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">นามสกุล <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">ชั้นเรียน</label>
                            <input type="text" class="form-control" id="class" name="class">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">สาขาวิชา</label>
                            <input type="text" class="form-control" id="major" name="major">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">เพศ</label>
                            <select class="form-select" id="gender" name="gender">
                                <option value="">เลือก</option>
                                <option value="male">ชาย</option>
                                <option value="female">หญิง</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">เบอร์โทรศัพท์</label>
                            <input type="text" class="form-control" id="phone" name="phone">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">อีเมล</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="button" class="btn btn-primary" id="btnSaveStudent">
                    <i class="bi bi-save"></i> บันทึก
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>

<link href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap5.min.css" rel="stylesheet">

<script>
$(document).ready(function() {
    // Initialize DataTable
    let table = $('#studentsTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: 'api_students.php',
            type: 'POST',
            data: function(d) {
                d.filter_class = $('#filterClass').val();
                d.filter_major = $('#filterMajor').val();
                d.filter_gender = $('#filterGender').val();
            },
            error: function(xhr, error, thrown) {
                console.error('DataTables error:', error);
                console.error('Response:', xhr.responseText);
            }
        },
        columns: [
            {
                data: 'photo',
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    if (data) {
                        return `<img src="${data}" class="student-photo" alt="Photo">`;
                    } else {
                        const initial = row.first_name ? row.first_name.charAt(0) : '?';
                        return `<div class="student-photo-placeholder">${initial}</div>`;
                    }
                }
            },
            { data: 'student_code' },
            {
                data: null,
                render: function(data, type, row) {
                    return `${row.first_name} ${row.last_name}`;
                }
            },
            { data: 'class' },
            { data: 'major' },
            {
                data: 'gender',
                render: function(data) {
                    if (data === 'male') {
                        return '<span class="badge badge-male"><i class="bi bi-gender-male"></i> ชาย</span>';
                    } else if (data === 'female') {
                        return '<span class="badge badge-female"><i class="bi bi-gender-female"></i> หญิง</span>';
                    }
                    return '-';
                }
            },
            { data: 'id_card' },
            { data: 'phone' },
            {
                data: 'status',
                render: function(data) {
                    return '<span class="badge badge-active">ใช้งาน</span>';
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    return `
                        <button class="btn btn-sm btn-primary btn-action" onclick="viewStudent(${row.student_id})" title="ดูข้อมูล">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-sm btn-warning btn-action" onclick="editStudent(${row.student_id})" title="แก้ไข">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-sm btn-danger btn-action" onclick="deleteStudent(${row.student_id}, '${row.first_name} ${row.last_name}')" title="ลบ">
                            <i class="bi bi-trash"></i>
                        </button>
                    `;
                }
            }
        ],
        dom: "<'row'<'col-sm-12 col-md-6'l><'col-sm-12 col-md-6'f>>" +
             "<'row'<'col-sm-12 col-md-6'B>>" +
             "<'row'<'col-sm-12'tr>>" +
             "<'row'<'col-sm-12 col-md-5'i><'col-sm-12 col-md-7'p>>",
        buttons: [
            {
                extend: 'copy',
                text: '<i class="bi bi-clipboard"></i> คัดลอก',
                className: 'btn-sm'
            },
            {
                extend: 'excel',
                text: '<i class="bi bi-file-earmark-excel"></i> Excel',
                className: 'btn-sm',
                title: 'รายชื่อนักเรียน',
                exportOptions: {
                    columns: [1, 2, 3, 4, 5, 6, 7]
                }
            },
            {
                extend: 'pdf',
                text: '<i class="bi bi-file-earmark-pdf"></i> PDF',
                className: 'btn-sm',
                title: 'รายชื่อนักเรียน',
                exportOptions: {
                    columns: [1, 2, 3, 4, 5, 6, 7]
                }
            },
            {
                extend: 'print',
                text: '<i class="bi bi-printer"></i> พิมพ์',
                className: 'btn-sm',
                title: 'รายชื่อนักเรียน',
                exportOptions: {
                    columns: [1, 2, 3, 4, 5, 6, 7]
                }
            },
            {
                extend: 'colvis',
                text: '<i class="bi bi-eye-slash"></i> แสดง/ซ่อนคอลัมน์',
                className: 'btn-sm'
            }
        ],
        language: {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/th.json'
        },
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "ทั้งหมด"]],
        order: [[1, 'asc']],
        responsive: true
    });

    // Filter change handlers
    $('#filterClass, #filterMajor, #filterGender').on('change', function() {
        table.ajax.reload();
    });

    // Reset filter
    $('#btnResetFilter').on('click', function() {
        $('#filterClass, #filterMajor, #filterGender').val('');
        table.ajax.reload();
    });

    // Save student
    $('#btnSaveStudent').on('click', function() {
        const formData = $('#studentForm').serialize();

        $('#loadingOverlay').addClass('show');

        $.ajax({
            url: 'api_save_student.php',
            method: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                $('#loadingOverlay').removeClass('show');

                if (response.success) {
                    $('#addStudentModal').modal('hide');
                    $('#studentForm')[0].reset();
                    table.ajax.reload();

                    alert('✅ บันทึกข้อมูลสำเร็จ!');
                } else {
                    alert('❌ เกิดข้อผิดพลาด: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                $('#loadingOverlay').removeClass('show');
                console.error('Error:', error);
                alert('❌ เกิดข้อผิดพลาดในการบันทึกข้อมูล');
            }
        });
    });
});

// View student details
function viewStudent(id) {
    window.location.href = 'student_detail.php?id=' + id;
}

// Edit student
function editStudent(id) {
    $('#loadingOverlay').addClass('show');

    $.ajax({
        url: 'api_student_details.php',
        method: 'GET',
        data: { id: id },
        dataType: 'json',
        success: function(response) {
            $('#loadingOverlay').removeClass('show');

            if (response.success) {
                const student = response.data;

                $('#addStudentModalLabel').html('<i class="bi bi-pencil"></i> แก้ไขข้อมูลนักเรียน');
                $('#student_id').val(student.student_id);
                $('#student_code').val(student.student_code);
                $('#id_card').val(student.id_card);
                $('#first_name').val(student.first_name);
                $('#last_name').val(student.last_name);
                $('#class').val(student.class);
                $('#major').val(student.major);
                $('#gender').val(student.gender);
                $('#phone').val(student.phone);
                $('#email').val(student.email);

                $('#addStudentModal').modal('show');
            } else {
                alert('❌ ไม่พบข้อมูลนักเรียน');
            }
        },
        error: function() {
            $('#loadingOverlay').removeClass('show');
            alert('❌ เกิดข้อผิดพลาดในการดึงข้อมูล');
        }
    });
}

// Delete student
function deleteStudent(id, name) {
    if (confirm(`คุณต้องการลบนักเรียน "${name}" ใช่หรือไม่?`)) {
        $('#loadingOverlay').addClass('show');

        $.ajax({
            url: 'api_delete_student.php',
            method: 'POST',
            data: { id: id },
            dataType: 'json',
            success: function(response) {
                $('#loadingOverlay').removeClass('show');

                if (response.success) {
                    $('#studentsTable').DataTable().ajax.reload();
                    alert('✅ ลบข้อมูลสำเร็จ!');
                } else {
                    alert('❌ เกิดข้อผิดพลาด: ' + response.message);
                }
            },
            error: function() {
                $('#loadingOverlay').removeClass('show');
                alert('❌ เกิดข้อผิดพลาดในการลบข้อมูล');
            }
        });
    }
}

// Reset modal when closed
$('#addStudentModal').on('hidden.bs.modal', function () {
    $('#studentForm')[0].reset();
    $('#student_id').val('');
    $('#addStudentModalLabel').html('<i class="bi bi-person-plus"></i> เพิ่มนักเรียนใหม่');
});
</script>

<?php include 'includes/footer.php'; ?>
