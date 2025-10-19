<?php
/**
 * ทดสอบ response จาก API
 */

$url = 'http://localhost/scan/api_checkin.php';

// ล้างข้อมูลเดิม
require_once 'config/config.php';
require_once 'config/database.php';
$db = getDB();
$db->exec("DELETE FROM attendance_logs WHERE student_id = 2 AND log_date = CURDATE()");

$data = [
    'identifier' => '1101000268630',
    'identifier_type' => 'id_card',
    'log_type' => 'in',
    'gps_latitude' => 13.7563,
    'gps_longitude' => 100.5018,
    'is_outside_area' => 0
];

$options = [
    'http' => [
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query($data),
        'ignore_errors' => true
    ]
];

$context = stream_context_create($options);
$result = file_get_contents($url, false, $context);

echo "=== RAW RESPONSE ===\n";
echo $result;
echo "\n\n";

echo "=== RESPONSE LENGTH ===\n";
echo "Length: " . strlen($result) . " bytes\n\n";

echo "=== FIRST 100 CHARS (HEX) ===\n";
$first100 = substr($result, 0, 100);
for ($i = 0; $i < strlen($first100); $i++) {
    echo sprintf("%02X ", ord($first100[$i]));
    if (($i + 1) % 16 == 0) echo "\n";
}
echo "\n\n";

echo "=== TRYING TO DECODE JSON ===\n";
$decoded = json_decode($result, true);
if ($decoded === null) {
    echo "❌ JSON DECODE FAILED!\n";
    echo "JSON Error: " . json_last_error_msg() . "\n";
    echo "Error Code: " . json_last_error() . "\n";
} else {
    echo "✅ JSON DECODE SUCCESS!\n";
    print_r($decoded);
}

echo "\n=== HTTP RESPONSE HEADERS ===\n";
print_r($http_response_header);
