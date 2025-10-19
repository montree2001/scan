<?php
/**
 * หน้านำเข้าข้อมูลจาก Excel/CSV
 */
session_start();
define('ADMIN_PAGE', true);

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/functions.php';

requireAdmin();

$pageTitle = 'นำเข้าข้อมูล';
$currentPage = 'import';

$db = getDB();

// ตรวจสอบว่ามี PHPSpreadsheet หรือไม่
$hasSpreadsheet = file_exists('../vendor/autoload.php');
if ($hasSpreadsheet) {
    require_once '../vendor/autoload.php';
}

$step = $_GET['step'] ?? 1;
$previewData = [];
$importResult = null;

// Step 2: Upload และแสดง Preview
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['excel_file'])) {
    $file = $_FILES['excel_file'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExt = ['xlsx', 'xls', 'csv'];

        if (in_array($ext, $allowedExt)) {
            $uploadPath = UPLOAD_PATH . '/' . uniqid('import_') . '.' . $ext;

            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $_SESSION['import_file'] = $uploadPath;

                try {
                    if ($hasSpreadsheet && in_array($ext, ['xlsx', 'xls'])) {
                        // อ่านด้วย PHPSpreadsheet
                        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($uploadPath);
                        $worksheet = $spreadsheet->getActiveSheet();
                        $highestRow = $worksheet->getHighestRow();
                        $highestColumn = $worksheet->getHighestColumn();

                        // อ่านแค่ 100 แถวแรกสำหรับ Preview
                        $maxPreview = min($highestRow, 100);

                        for ($row = 1; $row <= $maxPreview; $row++) {
                            $rowData = [];
                            for ($col = 'A'; $col <= $highestColumn; $col++) {
                                $rowData[] = $worksheet->getCell($col . $row)->getValue();
                            }
                            $previewData[] = $rowData;
                        }
                    } else {
                        // อ่านด้วย CSV (fallback)
                        $handle = fopen($uploadPath, 'r');
                        $rowCount = 0;
                        while (($data = fgetcsv($handle, 1000, ',')) !== FALSE && $rowCount < 100) {
                            $previewData[] = $data;
                            $rowCount++;
                        }
                        fclose($handle);
                    }

                    $_SESSION['preview_data'] = $previewData;
                    $step = 2;

                } catch (Exception $e) {
                    setAlert('danger', 'ไม่สามารถอ่านไฟล์ได้: ' . $e->getMessage());
                    @unlink($uploadPath);
                }
            } else {
                setAlert('danger', 'ไม่สามารถอัพโหลดไฟล์ได้');
            }
        } else {
            setAlert('danger', 'ประเภทไฟล์ไม่ถูกต้อง (รองรับเฉพาะ .xlsx, .xls, .csv)');
        }
    }
}

// Step 3: Import ข้อมูล
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_import'])) {
    $mapping = $_POST['mapping'] ?? [];
    $hasHeader = isset($_POST['has_header']);
    $importFile = $_SESSION['import_file'] ?? null;

    if ($importFile && file_exists($importFile)) {
        try {
            $ext = strtolower(pathinfo($importFile, PATHINFO_EXTENSION));
            $allData = [];

            if ($hasSpreadsheet && in_array($ext, ['xlsx', 'xls'])) {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($importFile);
                $worksheet = $spreadsheet->getActiveSheet();
                $highestRow = $worksheet->getHighestRow();
                $highestColumn = $worksheet->getHighestColumn();

                for ($row = 1; $row <= $highestRow; $row++) {
                    $rowData = [];
                    for ($col = 'A'; $col <= $highestColumn; $col++) {
                        $rowData[] = $worksheet->getCell($col . $row)->getValue();
                    }
                    $allData[] = $rowData;
                }
            } else {
                $handle = fopen($importFile, 'r');
                while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                    $allData[] = $data;
                }
                fclose($handle);
            }

            // เริ่มนำเข้า
            $startRow = $hasHeader ? 1 : 0;
            $successCount = 0;
            $failedCount = 0;
            $duplicateCount = 0;
            $errors = [];

            for ($i = $startRow; $i < count($allData); $i++) {
                $row = $allData[$i];

                // สร้างข้อมูลตาม mapping
                $studentData = [];
                foreach ($mapping as $dbField => $excelCol) {
                    if ($excelCol !== '' && isset($row[$excelCol])) {
                        $studentData[$dbField] = trim($row[$excelCol]);
                    }
                }

                // ตรวจสอบข้อมูลที่จำเป็น
                if (empty($studentData['student_code']) || empty($studentData['first_name'])) {
                    $errors[] = "แถว " . ($i + 1) . ": ขาดรหัสนักเรียนหรือชื่อ";
                    $failedCount++;
                    continue;
                }

                try {
                    // ตรวจสอบ duplicate
                    $checkStmt = $db->prepare("SELECT student_id FROM students WHERE student_code = ?");
                    $checkStmt->execute([$studentData['student_code']]);

                    if ($checkStmt->rowCount() > 0) {
                        $duplicateCount++;
                        continue;
                    }

                    // Insert ข้อมูล
                    $fields = array_keys($studentData);
                    $placeholders = array_fill(0, count($fields), '?');

                    $sql = "INSERT INTO students (" . implode(', ', $fields) . ", status)
                            VALUES (" . implode(', ', $placeholders) . ", 'active')";

                    $stmt = $db->prepare($sql);
                    $stmt->execute(array_values($studentData));

                    $successCount++;

                } catch (Exception $e) {
                    $errors[] = "แถว " . ($i + 1) . ": " . $e->getMessage();
                    $failedCount++;
                }
            }

            // บันทึก Log
            $logStmt = $db->prepare("
                INSERT INTO import_logs (imported_by, file_name, total_records, success_records, failed_records, status, error_log)
                VALUES (?, ?, ?, ?, ?, 'completed', ?)
            ");
            $logStmt->execute([
                $_SESSION[SESSION_USER_ID],
                basename($importFile),
                count($allData) - $startRow,
                $successCount,
                $failedCount,
                implode("\n", array_slice($errors, 0, 100)) // เก็บแค่ 100 error แรก
            ]);

            logActivity($_SESSION[SESSION_USER_ID], 'import_students', "นำเข้านักเรียน $successCount รายการ");

            $importResult = [
                'success' => $successCount,
                'failed' => $failedCount,
                'duplicate' => $duplicateCount,
                'total' => count($allData) - $startRow,
                'errors' => array_slice($errors, 0, 50) // แสดงแค่ 50 error แรก
            ];

            // ลบไฟล์
            @unlink($importFile);
            unset($_SESSION['import_file']);
            unset($_SESSION['preview_data']);

            $step = 3;

        } catch (Exception $e) {
            setAlert('danger', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
        }
    }
}

// ดึง Preview Data จาก Session
if (isset($_SESSION['preview_data'])) {
    $previewData = $_SESSION['preview_data'];
}

include 'includes/header.php';
?>

<div class="card">
    <div class="card-header">
        <i class="bi bi-file-earmark-excel"></i> นำเข้าข้อมูลนักเรียนจากไฟล์ Excel/CSV
    </div>
    <div class="card-body">

        <!-- Steps Progress -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="d-flex justify-content-between">
                    <div class="text-center <?php echo $step >= 1 ? 'text-primary' : 'text-muted'; ?>">
                        <div class="mb-2">
                            <i class="bi bi-upload fs-2"></i>
                        </div>
                        <div><strong>1. อัพโหลดไฟล์</strong></div>
                    </div>
                    <div class="text-center <?php echo $step >= 2 ? 'text-primary' : 'text-muted'; ?>">
                        <div class="mb-2">
                            <i class="bi bi-table fs-2"></i>
                        </div>
                        <div><strong>2. ตรวจสอบข้อมูล</strong></div>
                    </div>
                    <div class="text-center <?php echo $step >= 3 ? 'text-primary' : 'text-muted'; ?>">
                        <div class="mb-2">
                            <i class="bi bi-check-circle fs-2"></i>
                        </div>
                        <div><strong>3. เสร็จสิ้น</strong></div>
                    </div>
                </div>
                <hr>
            </div>
        </div>

        <?php if ($step == 1): ?>
            <!-- Step 1: Upload File -->
            <div class="alert alert-info">
                <h5><i class="bi bi-info-circle"></i> คำแนะนำ</h5>
                <ul class="mb-0">
                    <li>ไฟล์ต้องเป็นนามสกุล .xlsx, .xls หรือ .csv</li>
                    <li>แถวแรกควรเป็น Header (ชื่อคอลัมน์)</li>
                    <li>ข้อมูลที่จำเป็น: รหัสนักเรียน, ชื่อ, นามสกุล</li>
                    <li>ข้อมูลที่แนะนำ: ชั้น, เบอร์โทร, อีเมล</li>
                </ul>
            </div>

            <?php if (!$hasSpreadsheet): ?>
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>PHPSpreadsheet ยังไม่ถูกติดตั้ง</strong><br>
                    รองรับเฉพาะไฟล์ .csv เท่านั้น<br>
                    <small>ติดตั้งด้วย: <code>composer install</code></small>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" class="mt-4">
                <div class="mb-3">
                    <label class="form-label"><strong>เลือกไฟล์ Excel/CSV</strong></label>
                    <input type="file" class="form-control" name="excel_file"
                           accept=".xlsx,.xls,.csv" required>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-upload"></i> อัพโหลดและดูตัวอย่าง
                </button>
            </form>

        <?php elseif ($step == 2 && !empty($previewData)): ?>
            <!-- Step 2: Preview & Mapping -->
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> อ่านไฟล์สำเร็จ! พบข้อมูล <?php echo count($previewData); ?> แถว
            </div>

            <form method="POST" action="">
                <h5>จับคู่ข้อมูล</h5>
                <p class="text-muted">เลือกคอลัมน์ที่ตรงกับข้อมูลในระบบ</p>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <label class="form-check-label">
                            <input type="checkbox" class="form-check-input" name="has_header" value="1" checked>
                            แถวแรกเป็น Header (ชื่อคอลัมน์)
                        </label>
                    </div>
                </div>

                <?php
                $fields = [
                    'student_code' => 'รหัสนักเรียน *',
                    'first_name' => 'ชื่อ *',
                    'last_name' => 'นามสกุล *',
                    'nickname' => 'ชื่อเล่น',
                    'date_of_birth' => 'วันเกิด',
                    'gender' => 'เพศ',
                    'class' => 'ชั้นเรียน',
                    'grade' => 'ระดับชั้น',
                    'phone' => 'เบอร์โทร',
                    'email' => 'อีเมล',
                    'parent_name' => 'ชื่อผู้ปกครอง',
                    'parent_phone' => 'เบอร์ผู้ปกครอง'
                ];

                $numColumns = count($previewData[0]);
                ?>

                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th width="200">ฟิลด์ในระบบ</th>
                                <th>เลือกคอลัมน์</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fields as $dbField => $label): ?>
                                <tr>
                                    <td><?php echo $label; ?></td>
                                    <td>
                                        <select class="form-select form-select-sm" name="mapping[<?php echo $dbField; ?>]">
                                            <option value="">-- ไม่ใช้ --</option>
                                            <?php for ($i = 0; $i < $numColumns; $i++): ?>
                                                <?php
                                                $colName = isset($previewData[0][$i]) ? $previewData[0][$i] : "Column " . ($i + 1);
                                                ?>
                                                <option value="<?php echo $i; ?>">
                                                    Col <?php echo $i + 1; ?>: <?php echo clean(substr($colName, 0, 50)); ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <h5 class="mt-4">ตัวอย่างข้อมูล (10 แถวแรก)</h5>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th width="50">#</th>
                                <?php for ($i = 0; $i < $numColumns; $i++): ?>
                                    <th>Col <?php echo $i + 1; ?></th>
                                <?php endfor; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($previewData, 0, 10) as $idx => $row): ?>
                                <tr>
                                    <td><?php echo $idx + 1; ?></td>
                                    <?php foreach ($row as $cell): ?>
                                        <td><?php echo clean(substr($cell, 0, 30)); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4">
                    <button type="submit" name="confirm_import" class="btn btn-success btn-lg">
                        <i class="bi bi-check-circle"></i> ยืนยันและนำเข้าข้อมูล
                    </button>
                    <a href="import.php" class="btn btn-secondary btn-lg">
                        <i class="bi bi-x-circle"></i> ยกเลิก
                    </a>
                </div>
            </form>

        <?php elseif ($step == 3 && $importResult): ?>
            <!-- Step 3: Result -->
            <div class="alert alert-success">
                <h4><i class="bi bi-check-circle"></i> การนำเข้าข้อมูลเสร็จสิ้น!</h4>
            </div>

            <div class="row">
                <div class="col-md-3">
                    <div class="card text-center border-success">
                        <div class="card-body">
                            <h2 class="text-success"><?php echo $importResult['success']; ?></h2>
                            <p class="mb-0">สำเร็จ</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center border-danger">
                        <div class="card-body">
                            <h2 class="text-danger"><?php echo $importResult['failed']; ?></h2>
                            <p class="mb-0">ล้มเหลว</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center border-warning">
                        <div class="card-body">
                            <h2 class="text-warning"><?php echo $importResult['duplicate']; ?></h2>
                            <p class="mb-0">ซ้ำ (ข้าม)</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center border-primary">
                        <div class="card-body">
                            <h2 class="text-primary"><?php echo $importResult['total']; ?></h2>
                            <p class="mb-0">ทั้งหมด</p>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($importResult['errors'])): ?>
                <div class="mt-4">
                    <h5>รายการที่มีปัญหา (<?php echo count($importResult['errors']); ?> รายการแรก)</h5>
                    <div class="alert alert-warning">
                        <ul class="mb-0">
                            <?php foreach ($importResult['errors'] as $error): ?>
                                <li><?php echo clean($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <div class="mt-4">
                <a href="students.php" class="btn btn-primary">
                    <i class="bi bi-people"></i> ดูรายชื่อนักเรียน
                </a>
                <a href="import.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-repeat"></i> นำเข้าอีกครั้ง
                </a>
            </div>
        <?php endif; ?>

    </div>
</div>

<!-- ประวัติการนำเข้า -->
<div class="card mt-4">
    <div class="card-header">
        <i class="bi bi-clock-history"></i> ประวัติการนำเข้า
    </div>
    <div class="card-body">
        <?php
        $logsStmt = $db->query("
            SELECT l.*, u.full_name
            FROM import_logs l
            LEFT JOIN users u ON l.imported_by = u.user_id
            ORDER BY l.import_date DESC
            LIMIT 10
        ");
        $logs = $logsStmt->fetchAll();
        ?>

        <?php if (empty($logs)): ?>
            <p class="text-muted">ยังไม่มีประวัติการนำเข้า</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm">
                    <thead>
                        <tr>
                            <th>วันที่</th>
                            <th>ไฟล์</th>
                            <th>ผู้นำเข้า</th>
                            <th class="text-center">ทั้งหมด</th>
                            <th class="text-center">สำเร็จ</th>
                            <th class="text-center">ล้มเหลว</th>
                            <th>สถานะ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo formatDateTime($log['import_date']); ?></td>
                                <td><?php echo clean($log['file_name']); ?></td>
                                <td><?php echo clean($log['full_name']); ?></td>
                                <td class="text-center"><?php echo $log['total_records']; ?></td>
                                <td class="text-center text-success"><?php echo $log['success_records']; ?></td>
                                <td class="text-center text-danger"><?php echo $log['failed_records']; ?></td>
                                <td>
                                    <?php if ($log['status'] == 'completed'): ?>
                                        <span class="badge bg-success">เสร็จสิ้น</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">ล้มเหลว</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
