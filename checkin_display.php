<?php
/**
 * หน้าแสดงผลการเช็คอินแบบ Real-time
 * สำหรับเจ้าหน้าที่ตรวจสอบ - ไม่ต้อง Login
 * แสดงรายการเช็คอินล่าสุดแบบอัตโนมัติ
 */
require_once 'config/config.php';
require_once 'config/database.php';

$db = getDB();

// ดึงการตั้งค่าวิทยาลัย
$settings = null;
try {
    $stmt = $db->query("SELECT college_name, security_warning_text FROM college_settings LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("college_settings table not found: " . $e->getMessage());
}
$collegeName = $settings ? $settings['college_name'] : 'วิทยาลัย';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการเช็คอินล่าสุด - <?php echo htmlspecialchars($collegeName); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background: #f8f9fa;
            padding: 20px;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 20px;
            text-align: center;
        }

        .stats-row {
            margin-bottom: 20px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 10px 0;
        }

        .stat-label {
            color: #666;
        }

        .checkin-list {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .checkin-item {
            border-bottom: 1px solid #eee;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: slideIn 0.5s ease-out;
        }

        .checkin-item:last-child {
            border-bottom: none;
        }

        .checkin-item.new {
            background: #d1e7dd;
            animation: highlight 2s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes highlight {
            0% {
                background: #d1e7dd;
            }
            100% {
                background: white;
            }
        }

        .badge-in {
            background: #28a745;
            padding: 8px 15px;
            border-radius: 20px;
        }

        .badge-out {
            background: #dc3545;
            padding: 8px 15px;
            border-radius: 20px;
        }

        .badge-outside {
            background: #ffc107;
            color: #000;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.85rem;
        }

        .student-info h5 {
            margin: 0 0 5px 0;
        }

        .student-info small {
            color: #666;
        }

        .time-info {
            text-align: right;
        }

        .time-info .time {
            font-size: 1.2rem;
            font-weight: bold;
        }

        .photo-preview {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 15px;
        }

        .no-photo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }

        .refresh-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            padding: 10px 20px;
            border-radius: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: none;
        }

        .refresh-indicator.active {
            display: block;
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid" style="max-width: 1400px;">
        <!-- Header -->
        <div class="header">
            <h1><i class="bi bi-display"></i> <?php echo htmlspecialchars($collegeName); ?></h1>
            <p class="mb-0">ระบบแสดงผลการเช็คอินแบบ Real-time</p>
            <small>อัปเดตอัตโนมัติทุก 5 วินาที</small>
        </div>

        <!-- Stats -->
        <div class="row stats-row g-3">
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="bi bi-arrow-down-circle text-success" style="font-size: 2rem;"></i>
                    <div class="stat-number text-success" id="statIn">0</div>
                    <div class="stat-label">เข้าวันนี้</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="bi bi-arrow-up-circle text-danger" style="font-size: 2rem;"></i>
                    <div class="stat-number text-danger" id="statOut">0</div>
                    <div class="stat-label">ออกวันนี้</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="bi bi-people text-primary" style="font-size: 2rem;"></i>
                    <div class="stat-number text-primary" id="statInside">0</div>
                    <div class="stat-label">อยู่ในวิทยาลัย</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="bi bi-clock text-info" style="font-size: 2rem;"></i>
                    <div class="stat-number text-info" id="currentTime">--:--</div>
                    <div class="stat-label">เวลาปัจจุบัน</div>
                </div>
            </div>
        </div>

        <!-- Check-in List -->
        <div class="checkin-list">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4><i class="bi bi-list-ul"></i> รายการเช็คอินล่าสุด (20 รายการ)</h4>
                <div>
                    <button class="btn btn-sm btn-outline-primary" onclick="loadData()">
                        <i class="bi bi-arrow-clockwise"></i> รีเฟรช
                    </button>
                    <a href="checkin.php" class="btn btn-sm btn-primary">
                        <i class="bi bi-qr-code-scan"></i> หน้าเช็คอิน
                    </a>
                </div>
            </div>

            <div id="checkinList">
                <div class="text-center text-muted py-5">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">กำลังโหลดข้อมูล...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Refresh Indicator -->
    <div class="refresh-indicator" id="refreshIndicator">
        <i class="bi bi-arrow-clockwise"></i> กำลังอัปเดต...
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let lastLogId = 0;
        let autoRefreshInterval;

        // โหลดข้อมูลเมื่อเปิดหน้า
        document.addEventListener('DOMContentLoaded', function() {
            loadData();
            updateClock();

            // อัปเดตอัตโนมัติทุก 5 วินาที
            autoRefreshInterval = setInterval(loadData, 5000);

            // อัปเดตเวลาทุกวินาที
            setInterval(updateClock, 1000);
        });

        function updateClock() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            document.getElementById('currentTime').textContent = `${hours}:${minutes}:${seconds}`;
        }

        function loadData() {
            document.getElementById('refreshIndicator').classList.add('active');

            fetch('api_checkin_display.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateStats(data.stats);
                        updateList(data.logs);
                    }
                })
                .catch(error => {
                    console.error('Error loading data:', error);
                })
                .finally(() => {
                    setTimeout(() => {
                        document.getElementById('refreshIndicator').classList.remove('active');
                    }, 500);
                });
        }

        function updateStats(stats) {
            document.getElementById('statIn').textContent = stats.total_in || 0;
            document.getElementById('statOut').textContent = stats.total_out || 0;
            document.getElementById('statInside').textContent = stats.currently_inside || 0;
        }

        function updateList(logs) {
            const listContainer = document.getElementById('checkinList');

            if (logs.length === 0) {
                listContainer.innerHTML = `
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-inbox" style="font-size: 3rem;"></i>
                        <p class="mt-2">ยังไม่มีรายการเช็คอินวันนี้</p>
                    </div>
                `;
                return;
            }

            let html = '';
            logs.forEach(log => {
                const isNew = log.log_id > lastLogId;
                if (isNew && lastLogId > 0) {
                    playNotificationSound();
                }

                const typeClass = log.log_type === 'in' ? 'badge-in' : 'badge-out';
                const typeIcon = log.log_type === 'in' ? 'arrow-down-circle' : 'arrow-up-circle';
                const typeText = log.log_type === 'in' ? 'เข้า' : 'ออก';

                html += `
                    <div class="checkin-item ${isNew ? 'new' : ''}" data-log-id="${log.log_id}">
                        <div class="d-flex align-items-center flex-grow-1">
                            ${log.photo_path ?
                                `<img src="${log.photo_path}" class="photo-preview" alt="รูปภาพ">` :
                                `<div class="no-photo"><i class="bi bi-person-fill fs-3 text-muted"></i></div>`
                            }
                            <div class="student-info">
                                <h5>${log.first_name} ${log.last_name}</h5>
                                <small>
                                    <i class="bi bi-person-badge"></i> ${log.student_code}
                                    ${log.class ? `<span class="ms-2"><i class="bi bi-book"></i> ${log.class}</span>` : ''}
                                    ${log.is_outside_area ? '<span class="badge-outside ms-2"><i class="bi bi-geo-alt"></i> นอกพื้นที่</span>' : ''}
                                </small>
                            </div>
                        </div>
                        <div class="time-info">
                            <div class="time">${log.log_time}</div>
                            <span class="badge ${typeClass}">
                                <i class="bi bi-${typeIcon}"></i> ${typeText}
                            </span>
                        </div>
                    </div>
                `;
            });

            listContainer.innerHTML = html;

            // อัปเดต lastLogId
            if (logs.length > 0) {
                lastLogId = Math.max(...logs.map(log => log.log_id));
            }
        }

        function playNotificationSound() {
            // เล่นเสียงแจ้งเตือน (optional)
            const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEARKwAAIhYAQACABAAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmwhBjGH0fPTgjMGHm7A7+OZUCE');
            audio.play().catch(() => {});
        }
    </script>
</body>
</html>
