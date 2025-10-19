<?php
// Simple test to check if the API works
chdir(dirname(__FILE__));

// Define required constants before starting session
define('SESSION_USER_ID', 'user_id');
define('SESSION_ROLE', 'user_role');
define('SESSION_FULL_NAME', 'full_name');

session_start();

// Mock session data to simulate login
$_SESSION[SESSION_USER_ID] = 1;
$_SESSION[SESSION_ROLE] = 'admin';
$_SESSION[SESSION_FULL_NAME] = 'Administrator';

// Mock POST data for DataTables
$_POST = [
    'draw' => 1,
    'start' => 0,
    'length' => 10,
    'search' => ['value' => ''],
    'order' => [['column' => 0, 'dir' => 'asc']]
];

echo "Testing API...\n";
echo "Session: " . print_r($_SESSION, true) . "\n";

// Include the API file to test it
ob_start();
include 'admin/api_students.php';
$output = ob_get_clean();
echo "Output: " . $output . "\n";
?>