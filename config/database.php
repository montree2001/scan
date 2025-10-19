<?php
/**
 * ไฟล์สำหรับการเชื่อมต่อฐานข้อมูล
 * ใช้ PDO เพื่อความปลอดภัยและป้องกัน SQL Injection
 */

require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $conn;

    private function __construct() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $this->conn = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch(PDOException $e) {
            error_log("Connection Error: " . $e->getMessage());
            die("เกิดข้อผิดพลาดในการเชื่อมต่อฐานข้อมูล");
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }

    // ป้องกันการ clone
    private function __clone() {}

    // ป้องกันการ unserialize
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// ฟังก์ชันสำหรับดึง connection ใช้งานง่าย
function getDB() {
    return Database::getInstance()->getConnection();
}
