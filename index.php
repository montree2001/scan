<?php
/**
 * หน้าแรกของระบบ
 * จะ Redirect ไปหน้าที่เหมาะสมตาม Role
 */
session_start();
require_once 'config/config.php';
require_once 'config/functions.php';

// ถ้ายัง Login ไม่ได้ให้ไปหน้า Login
if (!isLoggedIn()) {
    redirect(BASE_URL . '/login.php');
}

// Redirect ตาม Role
if (isAdmin()) {
    redirect(BASE_URL . '/admin/index.php');
} elseif (isStaff()) {
    redirect(BASE_URL . '/staff/index.php');
} else {
    redirect(BASE_URL . '/student/index.php');
}
