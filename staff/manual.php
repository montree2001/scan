<?php
/**
 * หน้าบันทึกการเข้า-ออกแบบ Manual
 * เจ้าหน้าที่สามารถค้นหาและเลือกนักเรียนได้
 */
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/functions.php';

// ตรวจสอบว่า Login แล้วหรือยัง และต้องเป็น staff หรือ admin
if (!isLoggedIn()) {
    redirect(BASE_URL . '/login.php');
}

$role = $_SESSION[SESSION_ROLE];
if ($role !== 'staff' && $role !== 'admin') {
    redirect(BASE_URL . '/index.php');
}

$userId = $_SESSION[SESSION_USER_ID];
$fullName = $_SESSION[SESSION_FULL_NAME];
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>บันทึกด้วยตัวเอง - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #11998e;
            --secondary-color: #38ef7d;
        }

        body {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            font-family: 'Sarabun', sans-serif;
            padding-bottom: 80px;
        }

        .navbar {
            background: white !important;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .container {
            max-width: 800px;
            padding: 20px 15px;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
        }

        .search-box {
            border-radius: 10px;
            border: 2px solid #ddd;
            padding: 12px 15px;
            font-size: 1.1rem;
        }

        .search-box:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(17, 153, 142, 0.25);
        }

        .student-item {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .student-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            border-color: var(--primary-color);
        }

        .student-item.selected {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, rgba(17, 153, 142, 0.1) 0%, rgba(56, 239, 125, 0.1) 100%);
        }

        .btn-action {
            border-radius: 10px;
            padding: 15px 30px;
            font-size: 1.1rem;
            margin: 5px;
        }

        .no-results {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }

        .action-section {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            padding: 15px;
            box-shadow: 0 -5px 15px rgba(0,0,0,0.1);
            display: none;
        }

        .action-section.show {
            display: block;
        }

        .badge-class {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-light sticky-top">
        <div class="container-fluid">
            <a href="index.php" class="navbar-brand">
                <i class="bi bi-arrow-left"></i> กลับ
            </a>
            <span class="navbar-text">
                <i class="bi bi-pencil-square"></i> บันทึกด้วยตัวเอง
            </span>
        </div>
    </nav>

    <div class="container">
        <!-- Search Box -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-search"></i> ค้นหานักเรียน</h5>
            </div>
            <div class="card-body">
                <input
                    type="text"
                    id="searchInput"
                    class="form-control search-box"
                    placeholder="ค้นหาด้วยชื่อ, นามสกุล หรือรหัสนักเรียน..."
                    autocomplete="off">
                <div class="mt-2">
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i>
                        พิมพ์อย่างน้อย 2 ตัวอักษรเพื่อเริ่มค้นหา
                    </small>
                </div>
            </div>
        </div>

        <!-- Results -->
        <div id="searchResults">
            <div class="no-results">
                <i class="bi bi-search" style="font-size: 3rem;"></i>
                <p class="mt-2">กรุณาพิมพ์เพื่อค้นหานักเรียน</p>
            </div>
        </div>
    </div>

    <!-- Action Section (Fixed Bottom) -->
    <div id="actionSection" class="action-section">
        <div class="container">
            <div id="selectedStudentInfo" class="mb-3"></div>
            <div class="d-flex justify-content-center gap-2">
                <button class="btn btn-success btn-action" onclick="recordAttendance('in')">
                    <i class="bi bi-arrow-down-circle"></i> บันทึกเข้า
                </button>
                <button class="btn btn-danger btn-action" onclick="recordAttendance('out')">
                    <i class="bi bi-arrow-up-circle"></i> บันทึกออก
                </button>
            </div>
            <div class="text-center mt-2">
                <button class="btn btn-sm btn-outline-secondary" onclick="clearSelection()">
                    <i class="bi bi-x-circle"></i> ยกเลิก
                </button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let selectedStudent = null;
        let searchTimeout = null;

        document.getElementById('searchInput').addEventListener('input', function() {
            const query = this.value.trim();

            clearTimeout(searchTimeout);

            if (query.length < 2) {
                document.getElementById('searchResults').innerHTML = `
                    <div class="no-results">
                        <i class="bi bi-search" style="font-size: 3rem;"></i>
                        <p class="mt-2">กรุณาพิมพ์เพื่อค้นหานักเรียน</p>
                    </div>
                `;
                clearSelection();
                return;
            }

            // แสดง loading
            document.getElementById('searchResults').innerHTML = `
                <div class="no-results">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">กำลังค้นหา...</p>
                </div>
            `;

            // ค้นหาหลังจาก 500ms
            searchTimeout = setTimeout(() => {
                searchStudents(query);
            }, 500);
        });

        function searchStudents(query) {
            fetch('api_search.php?q=' + encodeURIComponent(query))
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.students.length > 0) {
                        displayResults(data.students);
                    } else {
                        document.getElementById('searchResults').innerHTML = `
                            <div class="no-results">
                                <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                                <p class="mt-2">ไม่พบนักเรียนที่ค้นหา</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('searchResults').innerHTML = `
                        <div class="no-results text-danger">
                            <i class="bi bi-exclamation-triangle" style="font-size: 3rem;"></i>
                            <p class="mt-2">เกิดข้อผิดพลาดในการค้นหา</p>
                        </div>
                    `;
                });
        }

        function displayResults(students) {
            let html = '';
            students.forEach(student => {
                html += `
                    <div class="student-item" onclick="selectStudent(${JSON.stringify(student).replace(/"/g, '&quot;')})">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">${student.first_name} ${student.last_name}</h6>
                                <small class="text-muted">
                                    <i class="bi bi-person-badge"></i> ${student.student_code}
                                </small>
                                ${student.class ? `<span class="badge badge-class ms-2">${student.class}</span>` : ''}
                            </div>
                            <div>
                                <i class="bi bi-chevron-right"></i>
                            </div>
                        </div>
                    </div>
                `;
            });
            document.getElementById('searchResults').innerHTML = html;
        }

        function selectStudent(student) {
            selectedStudent = student;

            // Remove previous selection
            document.querySelectorAll('.student-item').forEach(item => {
                item.classList.remove('selected');
            });

            // Mark as selected
            event.currentTarget.classList.add('selected');

            // Show action section
            document.getElementById('selectedStudentInfo').innerHTML = `
                <div class="text-center">
                    <h6>${student.first_name} ${student.last_name}</h6>
                    <small class="text-muted">${student.student_code}</small>
                </div>
            `;
            document.getElementById('actionSection').classList.add('show');
        }

        function clearSelection() {
            selectedStudent = null;
            document.querySelectorAll('.student-item').forEach(item => {
                item.classList.remove('selected');
            });
            document.getElementById('actionSection').classList.remove('show');
        }

        function recordAttendance(type) {
            if (!selectedStudent) {
                alert('กรุณาเลือกนักเรียนก่อน');
                return;
            }

            const formData = new FormData();
            formData.append('student_code', selectedStudent.student_code);
            formData.append('log_type', type);
            formData.append('scan_method', 'manual');

            // Disable buttons
            document.querySelectorAll('.btn-action').forEach(btn => {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> กำลังบันทึก...';
            });

            fetch('api_record.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const typeText = type === 'in' ? 'เข้า' : 'ออก';
                    showAlert('success', `บันทึกการ${typeText}สำเร็จ`);
                    setTimeout(() => {
                        clearSelection();
                        document.getElementById('searchInput').value = '';
                        document.getElementById('searchResults').innerHTML = `
                            <div class="no-results">
                                <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                                <p class="mt-2">บันทึกสำเร็จ</p>
                            </div>
                        `;
                    }, 1500);
                } else {
                    showAlert('error', data.message || 'เกิดข้อผิดพลาด');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('error', 'เกิดข้อผิดพลาดในการบันทึก');
            })
            .finally(() => {
                // Enable buttons
                document.querySelectorAll('.btn-action').forEach(btn => {
                    btn.disabled = false;
                });
                document.querySelectorAll('.btn-action')[0].innerHTML = '<i class="bi bi-arrow-down-circle"></i> บันทึกเข้า';
                document.querySelectorAll('.btn-action')[1].innerHTML = '<i class="bi bi-arrow-up-circle"></i> บันทึกออก';
            });
        }

        function showAlert(type, message) {
            const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
            const alertHtml = `
                <div class="alert ${alertClass} alert-dismissible fade show" role="alert" style="position: fixed; top: 80px; left: 50%; transform: translateX(-50%); z-index: 9999; min-width: 300px;">
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', alertHtml);

            setTimeout(() => {
                document.querySelector('.alert')?.remove();
            }, 3000);
        }
    </script>
</body>
</html>
