<?php
/**
 * Test script to check if API works
 */
session_start();

// Simulate logged in user
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'admin';
$_SESSION['full_name'] = 'Test User';

// Set POST data to simulate DataTables request
$_POST = [
    'draw' => 1,
    'start' => 0,
    'length' => 10,
    'search' => ['value' => ''],
    'order' => [['column' => 0, 'dir' => 'asc']]
];

echo "Testing API with simulated session...\n";

// Include the API file
require_once 'api_students.php';
?>