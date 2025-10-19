<?php
// Simple test to check if the API works
session_start();
$_SESSION['user_id'] = 1; // Simulate logged in user

// Mock POST data
$_POST = [
    'draw' => 1,
    'start' => 0,
    'length' => 10,
    'search' => ['value' => '']
];

// Include the API file to test it
include 'api_students.php';
?>