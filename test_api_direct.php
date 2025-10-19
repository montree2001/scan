<?php
// Simple test to check if the API works
chdir(dirname(__FILE__));
session_start();

// Mock session data to simulate login
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'admin';
$_SESSION['full_name'] = 'Administrator';

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
include 'admin/api_students.php';
?>