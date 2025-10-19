<?php
/**
 * หน้า Logout
 */
session_start();
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/functions.php';

// บันทึก Activity Log ก่อน Logout
if (isset($_SESSION[SESSION_USER_ID])) {
    logActivity($_SESSION[SESSION_USER_ID], 'logout', 'ออกจากระบบ');
}

// ทำลาย Session ทั้งหมด
$_SESSION = array();

// ทำลาย Session Cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-42000, '/');
}

// ทำลาย Session
session_destroy();

// Redirect ไปหน้า Login
redirect(BASE_URL . '/login.php');
