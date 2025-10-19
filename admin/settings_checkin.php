<?php
/**
 * หน้าตั้งค่าระบบเช็คอินสาธารณะ
 * สำหรับแอดมินจัดการ GPS และข้อความต่างๆ
 */
session_start();
define('ADMIN_PAGE', true);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/functions.php';

// ตรวจสอบสิทธิ์ Admin
requireAdmin();

$pageTitle = 'ตั้งค่าระบบเช็คอินสาธารณะ';
$currentPage = 'settings_checkin';

$db = getDB();
$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $collegeName = trim($_POST['college_name']);
        $latitude = (float)$_POST['college_latitude'];
        $longitude = (float)$_POST['college_longitude'];
        $radius = (int)$_POST['allowed_radius_meters'];
        $warningText = trim($_POST['security_warning_text']);

        // Validate
        if (empty($collegeName)) {
            throw new Exception('กรุณากรอกชื่อวิทยาลัย');
        }

        if ($latitude < -90 || $latitude > 90) {
            throw new Exception('ละติจูดต้องอยู่ระหว่าง -90 ถึง 90');
        }

        if ($longitude < -180 || $longitude > 180) {
            throw new Exception('ลองจิจูดต้องอยู่ระหว่าง -180 ถึง 180');
        }

        if ($radius < 0) {
            throw new Exception('รัศมีต้องเป็นค่าบวก');
        }

        // Check if settings exist
        $stmt = $db->query("SELECT setting_id FROM college_settings LIMIT 1");
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Update
            $stmt = $db->prepare("
                UPDATE college_settings
                SET college_name = ?,
                    college_latitude = ?,
                    college_longitude = ?,
                    allowed_radius_meters = ?,
                    security_warning_text = ?
                WHERE setting_id = ?
            ");
            $stmt->execute([
                $collegeName,
                $latitude,
                $longitude,
                $radius,
                $warningText,
                $existing['setting_id']
            ]);
        } else {
            // Insert
            $stmt = $db->prepare("
                INSERT INTO college_settings
                (college_name, college_latitude, college_longitude, allowed_radius_meters, security_warning_text)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$collegeName, $latitude, $longitude, $radius, $warningText]);
        }

        $_SESSION['success'] = 'บันทึกการตั้งค่าสำเร็จ';
        redirect(BASE_URL . '/admin/settings_checkin.php');

    } catch (Exception $e) {
        $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }
}

// ตรวจสอบว่ามีตาราง college_settings หรือยัง
$tableExists = false;
$settings = null;
$totalStudents = 0;
$studentsWithIdCard = 0;
$studentsWithoutIdCard = 0;

try {
    // ตรวจสอบว่ามีตาราง college_settings หรือยัง
    $stmt = $db->query("SHOW TABLES LIKE 'college_settings'");
    $tableExists = ($stmt->rowCount() > 0);

    if ($tableExists) {
        // Load current settings
        $stmt = $db->query("SELECT * FROM college_settings LIMIT 1");
        $settings = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Error checking college_settings table: " . $e->getMessage());
}

if (!$settings) {
    $settings = [
        'college_name' => 'วิทยาลัย',
        'college_latitude' => 13.7563,
        'college_longitude' => 100.5018,
        'allowed_radius_meters' => 500,
        'security_warning_text' => 'วิทยาลัยเป็นพื้นที่ควบคุมทางการทหาร เพื่อความมั่นคง ห้ามบันทึกภาพบริเวณหวงห้าม ฝ่าฝืนมีโทษทางกฎหมาย'
    ];
}

// นับจำนวนนักเรียนที่มีเลขบัตรและไม่มีเลขบัตร
try {
    $stmt = $db->query("SELECT COUNT(*) as total FROM students WHERE status = 'active'");
    $totalStudents = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

    // ตรวจสอบว่ามีฟิลด์ id_card หรือยัง
    $stmt = $db->query("SHOW COLUMNS FROM students LIKE 'id_card'");
    $idCardFieldExists = ($stmt->rowCount() > 0);

    if ($idCardFieldExists) {
        $stmt = $db->query("SELECT COUNT(*) as total FROM students WHERE id_card IS NOT NULL AND id_card != '' AND status = 'active'");
        $studentsWithIdCard = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
        $studentsWithoutIdCard = $totalStudents - $studentsWithIdCard;
    }
} catch (PDOException $e) {
    error_log("Error counting students: " . $e->getMessage());
}

include 'includes/header.php';
?>

<!-- Leaflet CSS for OpenStreetMap -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<style>
    #map {
        height: 450px;
        border-radius: 10px;
        margin-top: 15px;
        border: 2px solid #dee2e6;
        z-index: 1;
    }

    .map-instructions {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 5px;
        padding: 12px;
        margin-top: 10px;
        font-size: 0.9rem;
    }

    .info-box {
        background: #e7f3ff;
        border-left: 4px solid #0d6efd;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }

    .warning-box {
        background: #fff3cd;
        border-left: 4px solid #ffc107;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }

    .danger-box {
        background: #f8d7da;
        border-left: 4px solid #dc3545;
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
</style>

<?php if (!$tableExists): ?>
    <div class="alert alert-danger">
        <h4><i class="bi bi-exclamation-triangle-fill"></i> ยังไม่ได้รัน Migration!</h4>
        <p><strong>ระบบเช็คอินสาธารณะยังไม่พร้อมใช้งาน</strong></p>
        <p>กรุณารัน Database Migration ก่อนใช้งาน เพื่อสร้างตารางและฟิลด์ที่จำเป็น</p>
        <hr>
        <a href="<?php echo BASE_URL; ?>/run_migration_checkin.php" class="btn btn-warning btn-lg">
            <i class="bi bi-database-fill-gear"></i> รัน Migration ทันที
        </a>
    </div>
<?php endif; ?>

<!-- Statistics -->
<div class="row mb-4">
    <div class="col-md-4">
        <div class="stat-card blue">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3><?php echo number_format($totalStudents); ?></h3>
                    <p>นักเรียนทั้งหมด</p>
                </div>
                <div>
                    <i class="bi bi-people-fill fs-1"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card green">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3><?php echo number_format($studentsWithIdCard); ?></h3>
                    <p>มีเลขบัตรประชาชน</p>
                </div>
                <div>
                    <i class="bi bi-check-circle-fill fs-1"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="stat-card orange">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h3><?php echo number_format($studentsWithoutIdCard); ?></h3>
                    <p>ยังไม่มีเลขบัตร</p>
                </div>
                <div>
                    <i class="bi bi-exclamation-circle-fill fs-1"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($studentsWithoutIdCard > 0): ?>
    <div class="warning-box">
        <h6><i class="bi bi-exclamation-triangle"></i> แจ้งเตือน</h6>
        <p class="mb-0">
            มีนักเรียน <strong><?php echo number_format($studentsWithoutIdCard); ?></strong> คนที่ยังไม่มีเลขบัตรประชาชน
            กรุณาเพิ่มเลขบัตรประชาชนให้นักเรียนเหล่านี้ที่หน้า
            <a href="students.php">จัดการนักเรียน</a>
        </p>
    </div>
<?php endif; ?>

<!-- Settings Form -->
<div class="card">
    <div class="card-header">
        <i class="bi bi-sliders"></i> ตั้งค่าพิกัด GPS และข้อความเตือน
    </div>
    <div class="card-body">
        <?php if (!$tableExists): ?>
            <div class="danger-box">
                <i class="bi bi-info-circle"></i>
                <strong>ฟอร์มจะใช้งานได้เมื่อรัน Migration เสร็จแล้ว</strong>
            </div>
        <?php endif; ?>

        <form method="POST" <?php echo !$tableExists ? 'style="opacity: 0.5; pointer-events: none;"' : ''; ?>>
            <div class="mb-3">
                <label class="form-label fw-bold">
                    <i class="bi bi-building"></i> ชื่อวิทยาลัย
                </label>
                <input type="text" name="college_name" class="form-control"
                       value="<?php echo htmlspecialchars($settings['college_name']); ?>" required>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">
                        <i class="bi bi-geo-alt"></i> ละติจูด (Latitude)
                    </label>
                    <input type="number" step="0.00000001" name="college_latitude" id="latitude"
                           class="form-control" value="<?php echo $settings['college_latitude']; ?>" required>
                    <small class="text-muted">ค่าระหว่าง -90 ถึง 90</small>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label fw-bold">
                        <i class="bi bi-geo"></i> ลองจิจูด (Longitude)
                    </label>
                    <input type="number" step="0.00000001" name="college_longitude" id="longitude"
                           class="form-control" value="<?php echo $settings['college_longitude']; ?>" required>
                    <small class="text-muted">ค่าระหว่าง -180 ถึง 180</small>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">
                    <i class="bi bi-rulers"></i> รัศมีที่อนุญาต (เมตร)
                </label>
                <input type="number" name="allowed_radius_meters" id="radius" class="form-control"
                       value="<?php echo $settings['allowed_radius_meters']; ?>" required min="0">
                <small class="text-muted">นักเรียนสามารถเช็คอินได้ภายในรัศมีนี้จากจุดกลาง</small>
            </div>

            <!-- แผนที่ OpenStreetMap -->
            <div class="mb-3">
                <label class="form-label fw-bold">
                    <i class="bi bi-map"></i> เลือกตำแหน่งวิทยาลัยบนแผนที่
                </label>
                <div id="map"></div>
                <div class="map-instructions">
                    <strong><i class="bi bi-hand-index"></i> วิธีใช้แผนที่:</strong>
                    <ul class="mb-0 mt-1" style="padding-left: 20px;">
                        <li><strong>คลิก</strong> บนแผนที่เพื่อเลือกตำแหน่ง</li>
                        <li><strong>ลาก</strong> ปักหมุดสีแดงเพื่อปรับตำแหน่ง</li>
                        <li><strong>วงกลมสีฟ้า</strong>แสดงรัศมีที่อนุญาต (เปลี่ยนตามค่ารัศมีด้านบน)</li>
                        <li>ใช้ปุ่มด้านล่างเพื่อใช้ตำแหน่งปัจจุบัน หรือค้นหาที่อยู่</li>
                    </ul>
                    <div class="mt-2">
                        <button type="button" class="btn btn-sm btn-primary" onclick="getCurrentLocation()">
                            <i class="bi bi-geo-alt-fill"></i> ใช้ตำแหน่งปัจจุบัน
                        </button>
                        <button type="button" class="btn btn-sm btn-success" onclick="searchAddress()">
                            <i class="bi bi-search"></i> ค้นหาที่อยู่
                        </button>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="resetToDefault()">
                            <i class="bi bi-arrow-counterclockwise"></i> รีเซ็ต
                        </button>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">
                    <i class="bi bi-shield-exclamation"></i> ข้อความเตือนด้านความมั่นคง
                </label>
                <textarea name="security_warning_text" class="form-control" rows="4" required><?php echo htmlspecialchars($settings['security_warning_text']); ?></textarea>
                <small class="text-muted">ข้อความนี้จะแสดงในหน้าเช็คอินสาธารณะ</small>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-save"></i> บันทึกการตั้งค่า
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Quick Links -->
<div class="card mt-4">
    <div class="card-header">
        <i class="bi bi-link-45deg"></i> ลิงก์ด่วน
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <a href="<?php echo BASE_URL; ?>/checkin.php" target="_blank" class="btn btn-outline-primary w-100">
                    <i class="bi bi-qr-code-scan"></i> หน้าเช็คอินสาธารณะ
                </a>
            </div>
            <div class="col-md-4">
                <a href="<?php echo BASE_URL; ?>/checkin_display.php" target="_blank" class="btn btn-outline-success w-100">
                    <i class="bi bi-display"></i> จอแสดงผล Real-time
                </a>
            </div>
            <div class="col-md-4">
                <a href="students.php" class="btn btn-outline-info w-100">
                    <i class="bi bi-people"></i> จัดการข้อมูลนักเรียน
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet JS for OpenStreetMap -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    let map;
    let marker;
    let circle;
    const defaultLat = <?php echo $settings['college_latitude']; ?>;
    const defaultLng = <?php echo $settings['college_longitude']; ?>;
    const defaultRadius = <?php echo $settings['allowed_radius_meters']; ?>;

    // เริ่มต้นแผนที่เมื่อโหลดหน้า
    document.addEventListener('DOMContentLoaded', function() {
        initMap();

        // อัปเดตแผนที่เมื่อเปลี่ยนค่าพิกัดในช่อง input
        document.getElementById('latitude').addEventListener('input', updateMapFromInputs);
        document.getElementById('longitude').addEventListener('input', updateMapFromInputs);

        // อัปเดตวงกลมเมื่อเปลี่ยนรัศมี
        document.getElementById('radius').addEventListener('input', updateRadius);
    });

    function initMap() {
        const lat = parseFloat(document.getElementById('latitude').value) || defaultLat;
        const lng = parseFloat(document.getElementById('longitude').value) || defaultLng;
        const radius = parseInt(document.getElementById('radius').value) || defaultRadius;

        // สร้างแผนที่
        map = L.map('map').setView([lat, lng], 16);

        // เพิ่ม OpenStreetMap tile layer
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(map);

        // เพิ่ม marker ที่ลากได้
        marker = L.marker([lat, lng], {
            draggable: true,
            title: 'ตำแหน่งวิทยาลัย'
        }).addTo(map);

        // เพิ่มวงกลมแสดงรัศมี
        circle = L.circle([lat, lng], {
            color: '#0d6efd',
            fillColor: '#0d6efd',
            fillOpacity: 0.1,
            radius: radius
        }).addTo(map);

        // popup แสดงพิกัด
        marker.bindPopup(`<b>ตำแหน่งวิทยาลัย</b><br>Lat: ${lat}<br>Lng: ${lng}`).openPopup();

        // อัปเดตค่าเมื่อลาก marker
        marker.on('dragend', function(e) {
            const pos = e.target.getLatLng();
            updatePosition(pos.lat, pos.lng);
        });

        // คลิกบนแผนที่เพื่อเลือกตำแหน่ง
        map.on('click', function(e) {
            const pos = e.latlng;
            marker.setLatLng(pos);
            circle.setLatLng(pos);
            updatePosition(pos.lat, pos.lng);
        });
    }

    function updatePosition(lat, lng) {
        // ปัดเศษเป็น 8 ตำแหน่ง
        lat = parseFloat(lat.toFixed(8));
        lng = parseFloat(lng.toFixed(8));

        document.getElementById('latitude').value = lat;
        document.getElementById('longitude').value = lng;

        // อัปเดต popup
        marker.bindPopup(`<b>ตำแหน่งวิทยาลัย</b><br>Lat: ${lat}<br>Lng: ${lng}`).openPopup();
    }

    function updateMapFromInputs() {
        const lat = parseFloat(document.getElementById('latitude').value);
        const lng = parseFloat(document.getElementById('longitude').value);

        if (lat && lng && !isNaN(lat) && !isNaN(lng)) {
            const newPos = L.latLng(lat, lng);
            map.setView(newPos, map.getZoom());
            marker.setLatLng(newPos);
            circle.setLatLng(newPos);
            marker.bindPopup(`<b>ตำแหน่งวิทยาลัย</b><br>Lat: ${lat}<br>Lng: ${lng}`);
        }
    }

    function updateRadius() {
        const radius = parseInt(document.getElementById('radius').value);
        if (radius && !isNaN(radius) && circle) {
            circle.setRadius(radius);
        }
    }

    function getCurrentLocation() {
        if (navigator.geolocation) {
            // แสดง loading
            const originalText = event.target.innerHTML;
            event.target.innerHTML = '<i class="bi bi-arrow-repeat"></i> กำลังค้นหา...';
            event.target.disabled = true;

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;

                    document.getElementById('latitude').value = lat;
                    document.getElementById('longitude').value = lng;

                    const newPos = L.latLng(lat, lng);
                    map.setView(newPos, 16);
                    marker.setLatLng(newPos);
                    circle.setLatLng(newPos);
                    marker.bindPopup(`<b>ตำแหน่งปัจจุบัน</b><br>Lat: ${lat}<br>Lng: ${lng}`).openPopup();

                    event.target.innerHTML = originalText;
                    event.target.disabled = false;

                    alert('✅ ได้รับตำแหน่งปัจจุบันแล้ว!');
                },
                function(error) {
                    event.target.innerHTML = originalText;
                    event.target.disabled = false;

                    let errorMsg = '';
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMsg = 'คุณปฏิเสธการเข้าถึงตำแหน่ง กรุณาอนุญาตการเข้าถึงตำแหน่งในเบราว์เซอร์';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMsg = 'ไม่สามารถระบุตำแหน่งได้';
                            break;
                        case error.TIMEOUT:
                            errorMsg = 'หมดเวลาในการค้นหาตำแหน่ง';
                            break;
                    }
                    alert('❌ ' + errorMsg);
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        } else {
            alert('❌ เบราว์เซอร์ของคุณไม่รองรับ Geolocation');
        }
    }

    function searchAddress() {
        const address = prompt('กรุณากรอกที่อยู่หรือชื่อสถานที่ที่ต้องการค้นหา:', 'วิทยาลัย');

        if (address && address.trim()) {
            // ใช้ Nominatim API ของ OpenStreetMap
            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}&countrycodes=th&limit=1`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.length > 0) {
                        const lat = parseFloat(data[0].lat);
                        const lng = parseFloat(data[0].lon);

                        document.getElementById('latitude').value = lat;
                        document.getElementById('longitude').value = lng;

                        const newPos = L.latLng(lat, lng);
                        map.setView(newPos, 16);
                        marker.setLatLng(newPos);
                        circle.setLatLng(newPos);
                        marker.bindPopup(`<b>${data[0].display_name}</b><br>Lat: ${lat}<br>Lng: ${lng}`).openPopup();

                        alert('✅ พบตำแหน่ง: ' + data[0].display_name);
                    } else {
                        alert('❌ ไม่พบตำแหน่งที่ค้นหา กรุณาลองใหม่');
                    }
                })
                .catch(error => {
                    console.error('Search error:', error);
                    alert('❌ เกิดข้อผิดพลาดในการค้นหา');
                });
        }
    }

    function resetToDefault() {
        if (confirm('ต้องการรีเซ็ตพิกัดเป็นค่าเริ่มต้นหรือไม่?')) {
            document.getElementById('latitude').value = defaultLat;
            document.getElementById('longitude').value = defaultLng;

            const newPos = L.latLng(defaultLat, defaultLng);
            map.setView(newPos, 16);
            marker.setLatLng(newPos);
            circle.setLatLng(newPos);
            marker.bindPopup(`<b>ตำแหน่งเริ่มต้น</b><br>Lat: ${defaultLat}<br>Lng: ${defaultLng}`).openPopup();
        }
    }
</script>

<?php include 'includes/footer.php'; ?>
