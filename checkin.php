<?php
/**
 * หน้าเช็คอินสาธารณะ - ไม่ต้อง Login
 * นักเรียนสามารถเช็คอินเข้า-ออกได้ด้วยเลขบัตรประชาชนหรือรหัสนักเรียน
 */
require_once 'config/config.php';
require_once 'config/database.php';

// ดึงการตั้งค่าวิทยาลัย
$db = getDB();
$collegeSettings = null;

try {
    $stmt = $db->query("SELECT * FROM college_settings LIMIT 1");
    $collegeSettings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // ถ้ายังไม่มีตาราง (ยังไม่รัน migration)
    error_log("college_settings table not found: " . $e->getMessage());
}

if (!$collegeSettings) {
    // ถ้ายังไม่มีการตั้งค่า ให้ใช้ค่าเริ่มต้น
    $collegeSettings = [
        'college_name' => 'วิทยาลัย',
        'college_latitude' => 13.7563,
        'college_longitude' => 100.5018,
        'allowed_radius_meters' => 500,
        'security_warning_text' => 'วิทยาลัยเป็นพื้นที่ควบคุมทางการทหาร เพื่อความมั่นคง ห้ามบันทึกภาพบริเวณหวงห้าม ฝ่าฝืนมีโทษทางกฎหมาย'
    ];
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>เช็คอินเข้า-ออก - <?php echo htmlspecialchars($collegeSettings['college_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <style>
        :root {
            --primary-color: #0d6efd;
            --success-color: #198754;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Sarabun', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
        }

        .card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            margin-bottom: 20px;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #0a58ca 100%);
            color: white;
            border-radius: 20px 20px 0 0 !important;
            padding: 25px;
            text-align: center;
        }

        .card-header h2 {
            margin: 0;
            font-weight: 600;
        }

        .card-body {
            padding: 30px;
        }

        .btn-checkin {
            width: 100%;
            padding: 20px;
            font-size: 1.3rem;
            font-weight: 600;
            border-radius: 15px;
            border: none;
            margin-bottom: 15px;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-checkin:active {
            transform: scale(0.98);
        }

        .btn-check-in {
            background: linear-gradient(135deg, var(--success-color) 0%, #146c43 100%);
            color: white;
        }

        .btn-check-out {
            background: linear-gradient(135deg, var(--danger-color) 0%, #b02a37 100%);
            color: white;
        }

        .form-control, .form-select {
            border-radius: 10px;
            padding: 15px;
            font-size: 1.1rem;
            border: 2px solid #ddd;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.25);
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }

        .alert {
            border-radius: 15px;
            padding: 20px;
        }

        .security-warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffe69c 100%);
            border: 2px solid var(--warning-color);
            padding: 20px;
            border-radius: 15px;
            margin-top: 20px;
        }

        .security-warning h5 {
            color: #664d03;
            margin-bottom: 10px;
        }

        .security-warning p {
            color: #664d03;
            margin: 0;
            line-height: 1.6;
        }

        .location-status {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }

        .location-status.inside {
            background: #d1e7dd;
            color: #0f5132;
            border: 2px solid #198754;
        }

        .location-status.outside {
            background: #f8d7da;
            color: #842029;
            border: 2px solid #dc3545;
        }

        .location-status.checking {
            background: #cfe2ff;
            color: #084298;
            border: 2px solid #0d6efd;
        }

        #qrCodeSection {
            text-align: center;
            padding: 20px;
            background: white;
            border-radius: 15px;
            margin-top: 20px;
        }

        #qrCodeImage {
            max-width: 300px;
            margin: 20px auto;
        }

        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
        }

        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .step {
            flex: 1;
            text-align: center;
            position: relative;
        }

        .step::after {
            content: '';
            position: absolute;
            top: 20px;
            left: 50%;
            width: 100%;
            height: 2px;
            background: #ddd;
            z-index: -1;
        }

        .step:last-child::after {
            display: none;
        }

        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #ddd;
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .step.active .step-circle {
            background: var(--primary-color);
        }

        .step.completed .step-circle {
            background: var(--success-color);
        }

        #map {
            height: 350px;
            width: 100%;
            border-radius: 15px;
            margin-top: 10px;
            border: 3px solid white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .map-container {
            margin-bottom: 20px;
        }

        .map-info {
            background: white;
            padding: 15px;
            border-radius: 15px;
            margin-top: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        .map-info h6 {
            margin: 0 0 10px 0;
            color: #333;
            font-weight: 600;
        }

        .map-info .info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }

        .map-info .info-item:last-child {
            border-bottom: none;
        }

        .map-legend {
            background: white;
            padding: 10px 15px;
            border-radius: 10px;
            margin-top: 10px;
            font-size: 0.9rem;
        }

        .map-legend .legend-item {
            display: flex;
            align-items: center;
            margin: 5px 0;
        }

        .map-legend .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            margin-right: 10px;
            border: 2px solid #333;
        }

        .legend-college {
            background: #dc3545;
        }

        .legend-user {
            background: #0d6efd;
        }

        .legend-radius {
            background: rgba(25, 135, 84, 0.2);
            border-color: #198754 !important;
        }

        @media (max-width: 576px) {
            .card-body {
                padding: 20px;
            }

            .btn-checkin {
                padding: 15px;
                font-size: 1.1rem;
            }

            #map {
                height: 300px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header Card -->
        <div class="card">
            <div class="card-header">
                <i class="bi bi-geo-alt-fill fs-1"></i>
                <h2 class="mt-2"><?php echo htmlspecialchars($collegeSettings['college_name']); ?></h2>
                <p class="mb-0">ระบบเช็คอินเข้า-ออก</p>
            </div>
        </div>

        <!-- Location Status -->
        <div id="locationStatus" class="location-status checking">
            <div class="spinner-border spinner-border-sm me-2"></div>
            <span>กำลังตรวจสอบตำแหน่ง GPS...</span>
        </div>

        <!-- Map Card -->
        <div class="card map-container">
            <div class="card-body" style="padding: 20px;">
                <h5 class="mb-3">
                    <i class="bi bi-map"></i> แผนที่ตำแหน่ง
                </h5>
                <div id="map"></div>

                <div class="map-legend">
                    <div class="legend-item">
                        <div class="legend-color legend-college"></div>
                        <span><strong>ตำแหน่งวิทยาลัย</strong></span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color legend-user"></div>
                        <span><strong>ตำแหน่งของคุณ</strong></span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color legend-radius"></div>
                        <span>พื้นที่ที่อนุญาตให้เช็คอิน</span>
                    </div>
                </div>

                <div class="map-info" id="mapInfo" style="display: none;">
                    <h6><i class="bi bi-info-circle"></i> ข้อมูลตำแหน่ง</h6>
                    <div class="info-item">
                        <span>ระยะทาง:</span>
                        <strong id="distanceText">-</strong>
                    </div>
                    <div class="info-item">
                        <span>พิกัด GPS:</span>
                        <span id="coordsText" style="font-size: 0.85rem;">-</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Form Card -->
        <div class="card">
            <div class="card-body">
                <!-- Step 1: เลือกประเภทการเช็คอิน -->
                <div id="step1" class="step-content">
                    <h4 class="text-center mb-4">เลือกประเภทการเช็คอิน</h4>

                    <button type="button" class="btn btn-checkin btn-check-in" onclick="selectCheckInType('in')">
                        <i class="bi bi-arrow-down-circle me-2"></i>
                        เช็คอินเข้า
                    </button>

                    <button type="button" class="btn btn-checkin btn-check-out" onclick="selectCheckInType('out')">
                        <i class="bi bi-arrow-up-circle me-2"></i>
                        เช็คอินออก
                    </button>

                    <div class="text-center mt-3">
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i>
                            กรุณาเลือกประเภทการเช็คอินที่ต้องการ
                        </small>
                    </div>
                </div>

                <!-- Step 2: กรอกข้อมูล -->
                <div id="step2" class="step-content" style="display: none;">
                    <button type="button" class="btn btn-sm btn-outline-secondary mb-3" onclick="backToStep1()">
                        <i class="bi bi-arrow-left"></i> กลับ
                    </button>

                    <h4 class="text-center mb-4" id="checkInTypeTitle"></h4>

                    <form id="checkInForm">
                        <input type="hidden" id="checkInType" name="log_type">
                        <input type="hidden" id="gpsLatitude" name="gps_latitude">
                        <input type="hidden" id="gpsLongitude" name="gps_longitude">
                        <input type="hidden" id="isOutsideArea" name="is_outside_area" value="0">

                        <div class="mb-3">
                            <label for="identifierType" class="form-label">
                                <i class="bi bi-card-list"></i> ประเภทข้อมูล
                            </label>
                            <select class="form-select" id="identifierType" required>
                                <option value="id_card">เลขบัตรประชาชน 13 หลัก</option>
                                <option value="student_code">รหัสนักเรียน</option>
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="identifier" class="form-label" id="identifierLabel">
                                <i class="bi bi-person-badge"></i> เลขบัตรประชาชน
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                id="identifier"
                                name="identifier"
                                placeholder="กรอกเลขบัตรประชาชน 13 หลัก"
                                required
                                maxlength="13"
                                autocomplete="off">
                            <div class="form-text">
                                <i class="bi bi-shield-check"></i> ข้อมูลของคุณจะถูกเก็บเป็นความลับ
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100" style="padding: 15px; font-size: 1.1rem; border-radius: 10px;">
                            <i class="bi bi-check-circle me-2"></i>
                            ยืนยันเช็คอิน
                        </button>
                    </form>
                </div>

                <!-- Step 3: แสดงผลลัพธ์ -->
                <div id="step3" class="step-content" style="display: none;">
                    <!-- จะแสดงผลลัพธ์ที่นี่ -->
                </div>
            </div>
        </div>

        <!-- QR Code Section (สำหรับนอกพื้นที่) -->
        <div id="qrCodeSection" style="display: none;">
            <h5>คุณอยู่นอกพื้นที่วิทยาลัย</h5>
            <p>กรุณาแสดง QR Code นี้ให้เจ้าหน้าที่สแกน</p>
            <div id="qrCodeImage"></div>
        </div>

        <!-- Security Warning -->
        <div class="security-warning">
            <h5>
                <i class="bi bi-exclamation-triangle-fill"></i>
                ข้อความเตือนด้านความมั่นคง
            </h5>
            <p><?php echo nl2br(htmlspecialchars($collegeSettings['security_warning_text'])); ?></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        // ตั้งค่า GPS ของวิทยาลัย
        const COLLEGE_LAT = <?php echo $collegeSettings['college_latitude']; ?>;
        const COLLEGE_LNG = <?php echo $collegeSettings['college_longitude']; ?>;
        const ALLOWED_RADIUS = <?php echo $collegeSettings['allowed_radius_meters']; ?>; // เมตร

        let currentPosition = null;
        let isInsideArea = false;

        // ตัวแปรสำหรับแผนที่
        let map = null;
        let collegeMarker = null;
        let userMarker = null;
        let radiusCircle = null;

        // ตรวจสอบ GPS เมื่อโหลดหน้า
        document.addEventListener('DOMContentLoaded', function() {
            initMap();
            checkGPSLocation();

            // เปลี่ยน placeholder เมื่อเปลี่ยนประเภทข้อมูล
            document.getElementById('identifierType').addEventListener('change', function() {
                const label = document.getElementById('identifierLabel');
                const input = document.getElementById('identifier');

                if (this.value === 'id_card') {
                    label.innerHTML = '<i class="bi bi-person-badge"></i> เลขบัตรประชาชน';
                    input.placeholder = 'กรอกเลขบัตรประชาชน 13 หลัก';
                    input.maxLength = 13;
                } else {
                    label.innerHTML = '<i class="bi bi-person-badge"></i> รหัสนักเรียน';
                    input.placeholder = 'กรอกรหัสนักเรียน';
                    input.maxLength = 20;
                }

                input.value = '';
            });

            // Handle form submission
            document.getElementById('checkInForm').addEventListener('submit', handleCheckIn);
        });

        // สร้างแผนที่
        function initMap() {
            // สร้างแผนที่โดยให้ตำแหน่งเริ่มต้นที่วิทยาลัย
            map = L.map('map').setView([COLLEGE_LAT, COLLEGE_LNG], 15);

            // เพิ่ม tile layer จาก OpenStreetMap
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);

            // เพิ่มจุดหมายวิทยาลัย
            collegeMarker = L.marker([COLLEGE_LAT, COLLEGE_LNG], {
                icon: L.divIcon({
                    className: 'custom-marker',
                    html: '<div style="background-color: #dc3545; width: 30px; height: 30px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>',
                    iconSize: [30, 30],
                    iconAnchor: [15, 15]
                })
            }).addTo(map);
            collegeMarker.bindPopup('<strong><?php echo htmlspecialchars($collegeSettings['college_name']); ?></strong><br>ตำแหน่งวิทยาลัย');

            // เพิ่มวงกลมแสดงรัศมีที่อนุญาต
            radiusCircle = L.circle([COLLEGE_LAT, COLLEGE_LNG], {
                color: '#198754',
                fillColor: '#198754',
                fillOpacity: 0.15,
                radius: ALLOWED_RADIUS
            }).addTo(map);
            radiusCircle.bindPopup('พื้นที่ที่อนุญาตให้เช็คอิน<br>รัศมี ' + ALLOWED_RADIUS + ' เมตร');
        }

        // อัพเดทตำแหน่งผู้ใช้บนแผนที่
        function updateUserLocationOnMap(lat, lng, distance) {
            // ลบ marker เก่า (ถ้ามี)
            if (userMarker) {
                map.removeLayer(userMarker);
            }

            // เพิ่ม marker ใหม่
            userMarker = L.marker([lat, lng], {
                icon: L.divIcon({
                    className: 'custom-marker',
                    html: '<div style="background-color: #0d6efd; width: 25px; height: 25px; border-radius: 50%; border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>',
                    iconSize: [25, 25],
                    iconAnchor: [12.5, 12.5]
                })
            }).addTo(map);

            const distanceText = Math.round(distance) + ' ม.';
            const statusText = distance <= ALLOWED_RADIUS ? '<span style="color: #198754;">อยู่ในพื้นที่</span>' : '<span style="color: #dc3545;">อยู่นอกพื้นที่</span>';

            userMarker.bindPopup('<strong>ตำแหน่งของคุณ</strong><br>ระยะทาง: ' + distanceText + '<br>' + statusText);

            // ปรับแผนที่ให้แสดงทั้งสองจุด
            const bounds = L.latLngBounds([
                [COLLEGE_LAT, COLLEGE_LNG],
                [lat, lng]
            ]);
            map.fitBounds(bounds, { padding: [50, 50] });

            // แสดงข้อมูลตำแหน่ง
            document.getElementById('mapInfo').style.display = 'block';
            document.getElementById('distanceText').textContent = distanceText;
            document.getElementById('coordsText').textContent = lat.toFixed(6) + ', ' + lng.toFixed(6);
        }

        function checkGPSLocation() {
            const statusDiv = document.getElementById('locationStatus');

            if (!navigator.geolocation) {
                statusDiv.className = 'location-status outside';
                statusDiv.innerHTML = '<i class="bi bi-geo-alt-fill"></i> เบราว์เซอร์ของคุณไม่รองรับ GPS';
                return;
            }

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    currentPosition = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude
                    };

                    // คำนวณระยะทาง
                    const distance = calculateDistance(
                        COLLEGE_LAT,
                        COLLEGE_LNG,
                        currentPosition.lat,
                        currentPosition.lng
                    );

                    isInsideArea = distance <= ALLOWED_RADIUS;

                    document.getElementById('gpsLatitude').value = currentPosition.lat;
                    document.getElementById('gpsLongitude').value = currentPosition.lng;
                    document.getElementById('isOutsideArea').value = isInsideArea ? '0' : '1';

                    if (isInsideArea) {
                        statusDiv.className = 'location-status inside';
                        statusDiv.innerHTML = '<i class="bi bi-check-circle-fill"></i> คุณอยู่ในพื้นที่วิทยาลัย (' + Math.round(distance) + ' ม.)';
                    } else {
                        statusDiv.className = 'location-status outside';
                        statusDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> คุณอยู่นอกพื้นที่วิทยาลัย (' + Math.round(distance) + ' ม.)';
                    }

                    // อัพเดทแผนที่
                    updateUserLocationOnMap(currentPosition.lat, currentPosition.lng, distance);
                },
                function(error) {
                    statusDiv.className = 'location-status outside';
                    statusDiv.innerHTML = '<i class="bi bi-geo-alt-fill"></i> ไม่สามารถตรวจสอบตำแหน่งได้ กรุณาเปิดใช้งาน GPS';
                    console.error('GPS Error:', error);
                }
            );
        }

        // คำนวณระยะทางระหว่าง 2 จุด (Haversine formula)
        function calculateDistance(lat1, lon1, lat2, lon2) {
            const R = 6371000; // รัศมีโลก เป็นเมตร
            const dLat = toRad(lat2 - lat1);
            const dLon = toRad(lon2 - lon1);
            const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                     Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
                     Math.sin(dLon/2) * Math.sin(dLon/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            return R * c;
        }

        function toRad(degrees) {
            return degrees * Math.PI / 180;
        }

        function selectCheckInType(type) {
            document.getElementById('checkInType').value = type;
            document.getElementById('step1').style.display = 'none';
            document.getElementById('step2').style.display = 'block';

            const title = type === 'in' ? 'เช็คอินเข้า' : 'เช็คอินออก';
            document.getElementById('checkInTypeTitle').innerHTML =
                `<i class="bi bi-arrow-${type === 'in' ? 'down' : 'up'}-circle"></i> ${title}`;
        }

        function backToStep1() {
            document.getElementById('step2').style.display = 'none';
            document.getElementById('step1').style.display = 'block';
            document.getElementById('checkInForm').reset();
        }

        function handleCheckIn(e) {
            e.preventDefault();

            const formData = new FormData(e.target);
            const identifier = document.getElementById('identifier').value;
            const identifierType = document.getElementById('identifierType').value;

            formData.append('identifier_type', identifierType);

            // Show loading
            const submitBtn = e.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>กำลังตรวจสอบ...';

            fetch('api_checkin.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers.get('content-type'));

                if (!response.ok) {
                    throw new Error('HTTP error! status: ' + response.status);
                }

                // ตรวจสอบว่า response เป็น JSON หรือไม่
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    return response.text().then(text => {
                        console.error('Response is not JSON:', text);
                        throw new Error('Response is not JSON');
                    });
                }

                return response.json();
            })
            .then(data => {
                console.log('API Response:', data);

                if (data.success) {
                    showSuccess(data);
                } else {
                    showError(data.message || 'เกิดข้อผิดพลาด');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Fetch Error:', error);
                console.error('Error details:', error.message);
                showError('เกิดข้อผิดพลาด: ' + error.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        }

        function showSuccess(data) {
            document.getElementById('step2').style.display = 'none';
            const step3 = document.getElementById('step3');

            const typeText = data.log_type === 'in' ? 'เข้า' : 'ออก';
            const typeColor = data.log_type === 'in' ? 'success' : 'danger';
            const typeIcon = data.log_type === 'in' ? 'arrow-down-circle' : 'arrow-up-circle';

            // ข้อความที่แสดง
            let mainMessage = '';
            let alertIcon = '';

            if (data.is_duplicate) {
                mainMessage = `คุณได้เช็คอิน${typeText}ไว้แล้ว`;
                alertIcon = '<i class="bi bi-info-circle-fill fs-1 d-block mb-3"></i>';
            } else {
                mainMessage = `เช็คอิน${typeText}สำเร็จ!`;
                alertIcon = '<i class="bi bi-check-circle-fill fs-1 d-block mb-3"></i>';
            }

            step3.innerHTML = `
                <div class="text-center">
                    <div class="alert alert-${typeColor}" style="font-size: 1.2rem;">
                        ${alertIcon}
                        <h4>${mainMessage}</h4>
                        ${data.is_duplicate ? '<p class="mb-0 mt-2" style="font-size: 0.95rem;">แสดงข้อมูลการเช็คอินล่าสุด</p>' : ''}
                    </div>

                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="mb-3">ข้อมูลนักเรียน</h5>
                            <table class="table table-borderless">
                                <tr>
                                    <th class="text-start">ชื่อ-นามสกุล:</th>
                                    <td class="text-end"><strong>${data.student.first_name} ${data.student.last_name}</strong></td>
                                </tr>
                                <tr>
                                    <th class="text-start">รหัสนักเรียน:</th>
                                    <td class="text-end">${data.student.student_code}</td>
                                </tr>
                                ${data.student.class ? `
                                <tr>
                                    <th class="text-start">ชั้นเรียน:</th>
                                    <td class="text-end">${data.student.class}</td>
                                </tr>
                                ` : ''}
                                ${data.student.major ? `
                                <tr>
                                    <th class="text-start">สาขาวิชา:</th>
                                    <td class="text-end">${data.student.major}</td>
                                </tr>
                                ` : ''}
                                <tr>
                                    <th class="text-start">เวลา:</th>
                                    <td class="text-end">${data.timestamp}</td>
                                </tr>
                                ${data.is_outside_area ? `
                                <tr>
                                    <th colspan="2" class="text-center">
                                        <span class="badge bg-warning">เช็คอินนอกพื้นที่</span>
                                    </th>
                                </tr>
                                ` : ''}
                            </table>
                        </div>
                    </div>

                    <button type="button" class="btn btn-primary" onclick="resetForm()">
                        <i class="bi bi-arrow-clockwise"></i> เช็คอินใหม่
                    </button>
                </div>
            `;

            step3.style.display = 'block';

            // แสดง QR Code ถ้าอยู่นอกพื้นที่
            if (data.is_outside_area && data.qr_code_data) {
                showQRCode(data.qr_code_data);
            }
        }

        function showError(message) {
            alert('❌ ' + message);
        }

        function showQRCode(data) {
            const qrSection = document.getElementById('qrCodeSection');
            const qrImage = document.getElementById('qrCodeImage');
            qrImage.innerHTML = '';

            new QRCode(qrImage, {
                text: JSON.stringify(data),
                width: 256,
                height: 256,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.H
            });

            qrSection.style.display = 'block';
        }

        function resetForm() {
            document.getElementById('step3').style.display = 'none';
            document.getElementById('step1').style.display = 'block';
            document.getElementById('checkInForm').reset();
            document.getElementById('qrCodeSection').style.display = 'none';
            checkGPSLocation(); // ตรวจสอบ GPS ใหม่
        }
    </script>
</body>
</html>
