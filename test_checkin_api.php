<?php
// ทดสอบ API เช็คอิน
$url = 'http://localhost/scan/api_checkin.php';

// ข้อมูลทดสอบ
$data = [
    'identifier' => '1101000268630',
    'identifier_type' => 'id_card',
    'log_type' => 'in',
    'gps_latitude' => 13.7563,
    'gps_longitude' => 100.5018,
    'is_outside_area' => 0
];

// สร้าง POST request
$options = [
    'http' => [
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'method'  => 'POST',
        'content' => http_build_query($data)
    ]
];

$context  = stream_context_create($options);
$result = file_get_contents($url, false, $context);

echo "Response:\n";
echo $result . "\n\n";

$response = json_decode($result, true);
if ($response) {
    echo "Decoded:\n";
    print_r($response);
} else {
    echo "Failed to decode JSON\n";
}
