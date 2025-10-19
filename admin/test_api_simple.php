<?php
/**
 * Simple test endpoint for API
 */
header('Content-Type: application/json');

// Start session
session_start();

// Mock session for testing
$_SESSION['user_id'] = 1;

// Mock POST data
$_POST = [
    'draw' => 1,
    'start' => 0,
    'length' => 10,
    'search' => ['value' => '']
];

// Include the API file
include 'api_students.php';
?>