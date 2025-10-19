<?php
/**
 * Script to fix student-user links in the database
 * This script connects existing students to their corresponding users
 */
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/functions.php';

$db = getDB();

// Find students that are not linked to users (user_id is NULL) but have a student_code 
// that matches a username in the users table
$stmt = $db->prepare("
    SELECT s.student_id, s.student_code, u.user_id
    FROM students s
    INNER JOIN users u ON s.student_code = u.username
    WHERE s.user_id IS NULL OR s.user_id = 0
");
$stmt->execute();
$studentsToUpdate = $stmt->fetchAll();

echo "Found " . count($studentsToUpdate) . " student records to update.\n";

foreach ($studentsToUpdate as $student) {
    $updateStmt = $db->prepare("UPDATE students SET user_id = ? WHERE student_id = ?");
    $updateStmt->execute([$student['user_id'], $student['student_id']]);
    echo "Updated student {$student['student_code']} (ID: {$student['student_id']}) with user_id {$student['user_id']}\n";
}

echo "Student-user linking update completed.\n";
?>