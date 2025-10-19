<?php
/**
 * หน้าสแกน QR Code สำหรับเจ้าหน้าที่
 * รองรับการเปิดกล้องมือถือเพื่อสแกน
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
    <title>สแกน QR Code - <?php echo APP_NAME; ?></title>
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

        #reader {
            width: 100%;
            border-radius: 10px;
            overflow: hidden;
        }

        #reader video {
            width: 100% !important;
            border-radius: 10px;
        }

        .student-info {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .btn-action {
            border-radius: 10px;
            padding: 15px 30px;
            font-size: 1.1rem;
            margin: 5px;
        }

        .success-animation {
            animation: pulse 0.5s;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .alert {
            border-radius: 10px;
        }

        #scanStatus {
            position: fixed;
            top: 80px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1000;
            min-width: 300px;
            display: none;
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
                <i class="bi bi-qr-code-scan"></i> สแกน QR Code
            </span>
        </div>
    </nav>

    <!-- Status Alert -->
    <div id="scanStatus" class="alert"></div>

    <div class="container">
        <!-- QR Code Scanner -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-camera"></i> สแกนบัตร QR Code</h5>
            </div>
            <div class="card-body">
                <div id="reader"></div>
                <div class="text-center mt-3">
                    <button id="startScan" class="btn btn-success" onclick="startScanning()">
                        <i class="bi bi-play-fill"></i> เริ่มสแกน
                    </button>
                    <button id="stopScan" class="btn btn-danger" onclick="stopScanning()" style="display:none;">
                        <i class="bi bi-stop-fill"></i> หยุดสแกน
                    </button>
                </div>
                <div class="alert alert-info mt-3">
                    <small>
                        <i class="bi bi-info-circle"></i>
                        วางบัตร QR Code ให้อยู่ในกรอบกล้องเพื่อทำการสแกน
                    </small>
                </div>
            </div>
        </div>

        <!-- Student Information (hidden by default) -->
        <div id="studentInfo" class="student-info" style="display: none;">
            <div class="text-center mb-3">
                <i class="bi bi-person-circle text-success" style="font-size: 4rem;"></i>
            </div>
            <h4 class="text-center mb-3" id="studentName"></h4>
            <table class="table table-borderless">
                <tr>
                    <th width="120">รหัสนักเรียน:</th>
                    <td id="studentCode"></td>
                </tr>
                <tr>
                    <th>ชั้น:</th>
                    <td id="studentClass"></td>
                </tr>
                <tr>
                    <th>เวลา:</th>
                    <td id="currentTime"></td>
                </tr>
            </table>

            <div class="text-center mt-4">
                <h5 class="mb-3">เลือกประเภท:</h5>
                <button class="btn btn-success btn-action" onclick="recordAttendance('in')">
                    <i class="bi bi-arrow-down-circle"></i> บันทึกเข้า
                </button>
                <button class="btn btn-danger btn-action" onclick="recordAttendance('out')">
                    <i class="bi bi-arrow-up-circle"></i> บันทึกออก
                </button>
                <div class="mt-3">
                    <button class="btn btn-secondary" onclick="resetScan()">
                        <i class="bi bi-arrow-clockwise"></i> สแกนใหม่
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- QR Code Scanner Library -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

    <script>
        let html5QrcodeScanner = null;
        let currentStudentData = null;

        function startScanning() {
            document.getElementById('startScan').style.display = 'none';
            document.getElementById('stopScan').style.display = 'inline-block';
            document.getElementById('studentInfo').style.display = 'none';

            html5QrcodeScanner = new Html5QrcodeScanner(
                "reader",
                {
                    fps: 10,
                    qrbox: { width: 250, height: 250 },
                    aspectRatio: 1.0,
                    rememberLastUsedCamera: true
                }
            );

            html5QrcodeScanner.render(onScanSuccess, onScanError);
        }

        function stopScanning() {
            if (html5QrcodeScanner) {
                html5QrcodeScanner.clear();
                html5QrcodeScanner = null;
            }
            document.getElementById('startScan').style.display = 'inline-block';
            document.getElementById('stopScan').style.display = 'none';
        }

        function onScanSuccess(decodedText, decodedResult) {
            // หยุดการสแกนชั่วคราว
            stopScanning();

            // พยายาม parse JSON
            try {
                const data = JSON.parse(decodedText);

                if (data.system === 'college_scan_system' && data.student_id) {
                    currentStudentData = data;
                    displayStudentInfo(data);
                } else {
                    showStatus('error', 'QR Code ไม่ถูกต้อง');
                    setTimeout(resetScan, 2000);
                }
            } catch (e) {
                showStatus('error', 'ไม่สามารถอ่าน QR Code ได้');
                setTimeout(resetScan, 2000);
            }
        }

        function onScanError(errorMessage) {
            // ไม่ต้องแสดง error ทุกครั้ง เพราะจะเกิดบ่อยขณะกำลังสแกน
        }

        function displayStudentInfo(data) {
            document.getElementById('studentName').textContent = data.first_name + ' ' + data.last_name;
            document.getElementById('studentCode').textContent = data.student_id;
            document.getElementById('studentClass').textContent = data.class || '-';
            document.getElementById('currentTime').textContent = new Date().toLocaleString('th-TH');
            document.getElementById('studentInfo').style.display = 'block';
            document.getElementById('studentInfo').classList.add('success-animation');
        }

        function recordAttendance(type) {
            if (!currentStudentData) {
                showStatus('error', 'ไม่พบข้อมูลนักเรียน');
                return;
            }

            const formData = new FormData();
            formData.append('student_code', currentStudentData.student_id);
            formData.append('log_type', type);
            formData.append('scan_method', 'qr_scan');

            fetch('api_record.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const typeText = type === 'in' ? 'เข้า' : 'ออก';
                    showStatus('success', `บันทึกการ${typeText}สำเร็จ`);
                    setTimeout(() => {
                        resetScan();
                    }, 1500);
                } else {
                    showStatus('error', data.message || 'เกิดข้อผิดพลาด');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showStatus('error', 'เกิดข้อผิดพลาดในการบันทึก');
            });
        }

        function resetScan() {
            currentStudentData = null;
            document.getElementById('studentInfo').style.display = 'none';
            document.getElementById('studentInfo').classList.remove('success-animation');
            startScanning();
        }

        function showStatus(type, message) {
            const statusDiv = document.getElementById('scanStatus');
            statusDiv.className = 'alert alert-' + (type === 'success' ? 'success' : 'danger');
            statusDiv.textContent = message;
            statusDiv.style.display = 'block';

            setTimeout(() => {
                statusDiv.style.display = 'none';
            }, 3000);
        }

        // เริ่มต้น
        document.addEventListener('DOMContentLoaded', function() {
            // ไม่เริ่มสแกนอัตโนมัติ ให้ผู้ใช้กดปุ่ม
        });
    </script>
</body>
</html>
