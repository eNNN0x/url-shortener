<?php
// ─── Database Configuration ───────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');         // ← change to your MySQL username
define('DB_PASS', '');             // ← change to your MySQL password
define('DB_NAME', 'url_shortener');

// ─── App Configuration ────────────────────────────────────────────────────
define('BASE_URL', 'http://localhost/url-shortener'); // ← change to your domain
define('SHORT_CODE_LENGTH', 6);

// ─── Connect ──────────────────────────────────────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            die(json_encode(['error' => 'DB connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// ─── Auto-create table if not exists ──────────────────────────────────────
function initDB(): void {
    $pdo = getDB();
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE `" . DB_NAME . "`");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `urls` (
            `id`         INT AUTO_INCREMENT PRIMARY KEY,
            `code`       VARCHAR(12) NOT NULL UNIQUE,
            `long_url`   TEXT NOT NULL,
            `clicks`     INT DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_code (code)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

initDB();
