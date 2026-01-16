<?php
// Centralized database connection for PPKTDID
// Usage: require_once __DIR__ . '/db.php'; then use $conn (PDO)

if (!isset($conn) || !($conn instanceof PDO)) {
    try {
        $conn = new PDO(
            'mysql:host=localhost;dbname=ppktdid;charset=utf8mb4',
            'root',
            ''
        );
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // In production, avoid echoing sensitive errors
        // error_log('DB Connection failed: ' . $e->getMessage());
        $conn = null;
    }
}
