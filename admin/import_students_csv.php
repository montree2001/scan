<?php
/**
 * นำเข้าข้อมูลนักเรียนจากไฟล์ CSV
 */
session_start();
define('ADMIN_PAGE', true);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/functions.php';

// ตรวจสอบสิทธิ์ Admin
requireAdmin();

$pageTitle = 'นำเข้าข้อมูลนักเรียนจาก CSV';
$currentPage = 'import_csv';

$db = getDB();
$message = '';
$messageType = '';
$importStats = null;

// Handle CSV Import
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import'])) {
    try {
        $csvFile = '../ฐานข้อมูลเด็ก.csv';

        if (!file_exists($csvFile)) {
            throw new Exception('ไม่พบไฟล์ CSV: ' . $csvFile);
        }

        $handle = fopen($csvFile, 'r');
        if (!$handle) {
            throw new Exception('ไม่สามารถเปิดไฟล์ CSV ได้');
        }

        // ข้าม BOM ถ้ามี
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // อ่าน header
        $header = fgetcsv($handle);
        if (!$header) {
            throw new Exception('ไม่สามารถอ่าน header ของไฟล์ CSV ได้');
        }

        $success = 0;
        $error = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        $db->beginTransaction();

        while (($row = fgetcsv($handle)) !== false) {
            try {
                // ตรวจสอบว่ามีข้อมูลครบหรือไม่
                if (count($row) < 19) {
                    $skipped++;
                    continue;
                }

                // แมปข้อมูลจาก CSV
                $orderNo = trim($row[0]);
                $idCard = trim($row[1]);
                $studentCode = trim($row[2]);
                $classGroup = trim($row[3]);
                $title = trim($row[4]);
                $firstName = trim($row[5]);
                $lastName = trim($row[6]);
                $gender = trim($row[7]) === 'ช' ? 'male' : 'female';
                $nickname = trim($row[8]);
                $major = trim($row[9]);
                $phone = trim($row[10]);
                $level = trim($row[11]);
                $houseNo = trim($row[12]);
                $moo = trim($row[13]);
                $street = trim($row[14]);
                $province = trim($row[15]);
                $district = trim($row[16]);
                $subDistrict = trim($row[17]);
                $zipCode = trim($row[18]);

                // สร้างที่อยู่เต็ม
                $address = '';
                if ($houseNo) $address .= $houseNo;
                if ($moo) $address .= ' หมู่ ' . $moo;
                if ($street && $street !== '-') $address .= ' ถนน' . $street;
                if ($subDistrict) $address .= ' ต.' . $subDistrict;
                if ($district) $address .= ' อ.' . $district;
                if ($province) $address .= ' จ.' . $province;
                if ($zipCode) $address .= ' ' . $zipCode;

                // ตรวจสอบข้อมูลที่จำเป็น
                if (empty($studentCode) || empty($firstName) || empty($lastName)) {
                    $errors[] = "แถวที่ $orderNo: ข้อมูลไม่ครบ (ต้องมีรหัส, ชื่อ, นามสกุล)";
                    $error++;
                    continue;
                }

                // ตรวจสอบว่ามีนักเรียนคนนี้อยู่แล้วหรือไม่
                $stmt = $db->prepare("SELECT student_id FROM students WHERE student_code = ?");
                $stmt->execute([$studentCode]);
                $existing = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    // อัปเดตข้อมูล
                    $stmt = $db->prepare("
                        UPDATE students SET
                            id_card = ?,
                            first_name = ?,
                            last_name = ?,
                            nickname = ?,
                            gender = ?,
                            phone = ?,
                            email = ?,
                            class = ?,
                            major = ?,
                            address = ?,
                            updated_at = NOW()
                        WHERE student_code = ?
                    ");

                    $email = strtolower($studentCode) . '@student.ac.th';

                    $stmt->execute([
                        $idCard ?: null,
                        $firstName,
                        $lastName,
                        $nickname ?: null,
                        $gender,
                        $phone ?: null,
                        $email,
                        $classGroup,
                        $major,
                        trim($address) ?: null,
                        $studentCode
                    ]);

                    $updated++;
                } else {
                    // เพิ่มข้อมูลใหม่
                    $email = strtolower($studentCode) . '@student.ac.th';

                    $stmt = $db->prepare("
                        INSERT INTO students (
                            student_code, id_card, first_name, last_name, nickname,
                            gender, phone, email, class, major, address, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
                    ");

                    $stmt->execute([
                        $studentCode,
                        $idCard ?: null,
                        $firstName,
                        $lastName,
                        $nickname ?: null,
                        $gender,
                        $phone ?: null,
                        $email,
                        $classGroup,
                        $major,
                        trim($address) ?: null
                    ]);

                    $success++;
                }

            } catch (PDOException $e) {
                $errors[] = "แถวที่ $orderNo: " . $e->getMessage();
                $error++;
            }
        }

        fclose($handle);
        $db->commit();

        $importStats = [
            'success' => $success,
            'updated' => $updated,
            'error' => $error,
            'skipped' => $skipped,
            'errors' => $errors
        ];

        $_SESSION['success'] = "นำเข้าข้อมูลสำเร็จ: เพิ่มใหม่ {$success} คน, อัปเดต {$updated} คน";

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['error'] = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
    }
}

include 'includes/header.php';
?>

<style>
    .stats-box {
        background: white;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }

    .error-list {
        max-height: 300px;
        overflow-y: auto;
        background: #f8f9fa;
        padding: 15px;
        border-radius: 5px;
    }
</style>

<div class="card">
    <div class="card-header">
        <i class="bi bi-file-earmark-spreadsheet"></i> นำเข้าข้อมูลนักเรียนจาก CSV
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <h6><i class="bi bi-info-circle"></i> ข้อมูลที่จะนำเข้า</h6>
            <p class="mb-0">
                ระบบจะนำเข้าข้อมูลจากไฟล์ <strong>ฐานข้อมูลเด็ก.csv</strong> ที่อยู่ในโฟลเดอร์หลัก
            </p>
            <p class="mb-0 mt-2">
                <strong>ข้อมูลที่จะนำเข้า:</strong><br>
                - เลขบัตรประชาชน 13 หลัก<br>
                - รหัสนักเรียน, ชื่อ-นามสกุล, ชื่อเล่น<br>
                - ชั้นเรียน, สาขาวิชา<br>
                - เบอร์โทรศัพท์, ที่อยู่<br>
            </p>
        </div>

        <?php if ($importStats): ?>
            <div class="stats-box">
                <h5><i class="bi bi-graph-up"></i> สรุปผลการนำเข้า</h5>
                <div class="row mt-3">
                    <div class="col-md-3">
                        <div class="stat-card green">
                            <h3><?php echo number_format($importStats['success']); ?></h3>
                            <p>เพิ่มใหม่</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card blue">
                            <h3><?php echo number_format($importStats['updated']); ?></h3>
                            <p>อัปเดต</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card orange">
                            <h3><?php echo number_format($importStats['error']); ?></h3>
                            <p>ล้มเหลว</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card purple">
                            <h3><?php echo number_format($importStats['skipped']); ?></h3>
                            <p>ข้าม</p>
                        </div>
                    </div>
                </div>

                <?php if (!empty($importStats['errors'])): ?>
                    <div class="mt-4">
                        <h6 class="text-danger">
                            <i class="bi bi-exclamation-triangle"></i>
                            รายการที่เกิดข้อผิดพลาด (<?php echo count($importStats['errors']); ?> รายการ)
                        </h6>
                        <div class="error-list">
                            <?php foreach ($importStats['errors'] as $err): ?>
                                <div class="text-danger small mb-1">• <?php echo htmlspecialchars($err); ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <form method="POST" onsubmit="return confirm('ต้องการนำเข้าข้อมูลหรือไม่? กระบวนการนี้อาจใช้เวลาสักครู่');">
            <div class="alert alert-warning">
                <h6><i class="bi bi-exclamation-triangle"></i> คำเตือน</h6>
                <ul class="mb-0">
                    <li>หากพบรหัสนักเรียนซ้ำ ระบบจะ<strong>อัปเดต</strong>ข้อมูลเดิม</li>
                    <li>กระบวนการนำเข้าอาจใช้เวลาสักครู่สำหรับไฟล์ขนาดใหญ่</li>
                    <li>กรุณาตรวจสอบข้อมูลในไฟล์ CSV ให้ถูกต้องก่อนนำเข้า</li>
                </ul>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" name="import" class="btn btn-primary btn-lg">
                    <i class="bi bi-cloud-upload"></i> เริ่มนำเข้าข้อมูล
                </button>
                <a href="students.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> กลับไปจัดการนักเรียน
                </a>
            </div>
        </form>
    </div>
</div>

<!-- File Info -->
<div class="card mt-4">
    <div class="card-header">
        <i class="bi bi-file-text"></i> ข้อมูลไฟล์
    </div>
    <div class="card-body">
        <?php
        $csvFile = '../ฐานข้อมูลเด็ก.csv';
        if (file_exists($csvFile)) {
            $fileSize = filesize($csvFile);
            $fileTime = filemtime($csvFile);

            // นับจำนวนแถวใน CSV
            $lineCount = 0;
            $handle = fopen($csvFile, 'r');
            if ($handle) {
                // ข้าม BOM
                $bom = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") {
                    rewind($handle);
                }

                while (fgets($handle) !== false) {
                    $lineCount++;
                }
                fclose($handle);
            }
        ?>
            <table class="table table-bordered">
                <tr>
                    <th width="200">ชื่อไฟล์</th>
                    <td><code><?php echo basename($csvFile); ?></code></td>
                </tr>
                <tr>
                    <th>ขนาดไฟล์</th>
                    <td><?php echo number_format($fileSize / 1024, 2); ?> KB</td>
                </tr>
                <tr>
                    <th>จำนวนแถว</th>
                    <td><?php echo number_format($lineCount - 1); ?> แถว (ไม่รวม header)</td>
                </tr>
                <tr>
                    <th>แก้ไขล่าสุด</th>
                    <td><?php echo date('d/m/Y H:i:s', $fileTime); ?></td>
                </tr>
            </table>
        <?php } else { ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle"></i>
                ไม่พบไฟล์ <strong><?php echo $csvFile; ?></strong>
            </div>
        <?php } ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
