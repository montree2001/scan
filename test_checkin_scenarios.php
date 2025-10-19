<?php
/**
 * ทดสอบสถานการณ์ต่างๆ ของการเช็คอิน
 */

function testCheckIn($type, $scenarioName) {
    $url = 'http://localhost/scan/api_checkin.php';

    $data = [
        'identifier' => '1101000268630',
        'identifier_type' => 'id_card',
        'log_type' => $type,
        'gps_latitude' => 13.7563,
        'gps_longitude' => 100.5018,
        'is_outside_area' => 0
    ];

    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => http_build_query($data)
        ]
    ];

    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    $response = json_decode($result, true);

    echo "\n" . str_repeat("=", 80) . "\n";
    echo "📋 สถานการณ์: {$scenarioName}\n";
    echo str_repeat("-", 80) . "\n";

    if ($response['success']) {
        echo "✅ สำเร็จ: " . $response['message'] . "\n";
        echo "   ประเภท: " . ($response['log_type'] === 'in' ? 'เข้า' : 'ออก') . "\n";
        echo "   ชื่อ: {$response['student']['first_name']} {$response['student']['last_name']}\n";
        echo "   รหัส: {$response['student']['student_code']}\n";
        echo "   เวลา: {$response['timestamp']}\n";
        echo "   ซ้ำ: " . ($response['is_duplicate'] ? 'ใช่ (แสดงข้อมูลเดิม)' : 'ไม่ใช่ (บันทึกใหม่)') . "\n";
    } else {
        echo "❌ ไม่สำเร็จ: " . $response['message'] . "\n";
    }

    return $response;
}

echo "\n";
echo "🧪 เริ่มทดสอบระบบเช็คอิน\n";
echo "=" . str_repeat("=", 79) . "\n";

// ล้างข้อมูลเดิม
require_once 'config/config.php';
require_once 'config/database.php';
$db = getDB();
$db->exec("DELETE FROM attendance_logs WHERE student_id = 2");
echo "🗑️  ล้างข้อมูลเดิมแล้ว\n";

// สถานการณ์ 1: เช็คอินเข้าครั้งแรก
sleep(1);
$r1 = testCheckIn('in', '1. เช็คอินเข้าครั้งแรก');

// สถานการณ์ 2: เช็คอินเข้าซ้ำ (ควรแสดงข้อมูลเดิม)
sleep(1);
$r2 = testCheckIn('in', '2. เช็คอินเข้าซ้ำ (ควรแสดงข้อมูลเดิม)');

// สถานการณ์ 3: เช็คอินออกโดยไม่ได้เช็คอินเข้า (แต่เราเช็คอินเข้าไปแล้วจากข้อ 1)
sleep(1);
$r3 = testCheckIn('out', '3. เช็คอินออก (ครั้งแรก)');

// สถานการณ์ 4: เช็คอินออกซ้ำ (ควรแสดงข้อมูลเดิม)
sleep(1);
$r4 = testCheckIn('out', '4. เช็คอินออกซ้ำ (ควรแสดงข้อมูลเดิม)');

// สถานการณ์ 5: เช็คอินเข้าใหม่หลังเช็คอินออก (รอบใหม่)
sleep(1);
$r5 = testCheckIn('in', '5. เช็คอินเข้าใหม่หลังเช็คอินออก (รอบใหม่)');

// สถานการณ์ 6: เช็คอินเข้าซ้ำอีกครั้ง (ควรแสดงข้อมูลจากข้อ 5)
sleep(1);
$r6 = testCheckIn('in', '6. เช็คอินเข้าซ้ำอีกครั้ง (ควรแสดงข้อมูลจากข้อ 5)');

echo "\n" . str_repeat("=", 80) . "\n";
echo "📊 สรุปผลการทดสอบ\n";
echo str_repeat("=", 80) . "\n\n";

echo "สถานการณ์ 1 (เช็คอินเข้าครั้งแรก):        " .
    ($r1['success'] && !$r1['is_duplicate'] ? "✅ PASS" : "❌ FAIL") . "\n";

echo "สถานการณ์ 2 (เช็คอินเข้าซ้ำ):              " .
    ($r2['success'] && $r2['is_duplicate'] ? "✅ PASS" : "❌ FAIL") . "\n";

echo "สถานการณ์ 3 (เช็คอินออกครั้งแรก):         " .
    ($r3['success'] && !$r3['is_duplicate'] ? "✅ PASS" : "❌ FAIL") . "\n";

echo "สถานการณ์ 4 (เช็คอินออกซ้ำ):               " .
    ($r4['success'] && $r4['is_duplicate'] ? "✅ PASS" : "❌ FAIL") . "\n";

echo "สถานการณ์ 5 (เช็คอินเข้ารอบใหม่):         " .
    ($r5['success'] && !$r5['is_duplicate'] ? "✅ PASS" : "❌ FAIL") . "\n";

echo "สถานการณ์ 6 (เช็คอินเข้าซ้ำรอบใหม่):      " .
    ($r6['success'] && $r6['is_duplicate'] ? "✅ PASS" : "❌ FAIL") . "\n";

// แสดงบันทึกในฐานข้อมูล
echo "\n" . str_repeat("=", 80) . "\n";
echo "📝 บันทึกการเช็คอินในฐานข้อมูล\n";
echo str_repeat("=", 80) . "\n\n";

$stmt = $db->query("
    SELECT log_id, log_type, log_date, log_time, scan_method, notes, created_at
    FROM attendance_logs
    WHERE student_id = 2
    ORDER BY created_at ASC
");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($logs as $idx => $log) {
    echo ($idx + 1) . ". " .
        ($log['log_type'] === 'in' ? '📥 เข้า' : '📤 ออก') .
        " - " . $log['log_date'] . " " . $log['log_time'] .
        " (" . $log['scan_method'] . ")\n";
}

echo "\n✅ ทดสอบเสร็จสิ้น\n";
