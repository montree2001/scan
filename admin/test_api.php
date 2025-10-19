<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../config/functions.php';

// Test if session exists
if (!isset($_SESSION[SESSION_USER_ID])) {
    echo "No session found\n";
    exit();
}

echo "Session found for user: " . $_SESSION[SESSION_USER_ID] . "\n";

// Test database connection
try {
    $db = getDB();
    echo "Database connected successfully\n";
    
    // Test simple query
    $stmt = $db->query("SELECT COUNT(*) as total FROM students WHERE status = 'active'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total active students: " . $result['total'] . "\n";
    
} catch (Exception $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
?>