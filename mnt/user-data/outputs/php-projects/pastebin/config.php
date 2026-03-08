<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');      // ← your MySQL username
define('DB_PASS', '');          // ← your MySQL password
define('DB_NAME', 'pastebin_app');
define('BASE_URL', 'http://localhost/pastebin'); // ← your domain

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . DB_NAME . "` CHARACTER SET utf8mb4");
            $pdo->exec("USE `" . DB_NAME . "`");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `pastes` (
                    `id`         INT AUTO_INCREMENT PRIMARY KEY,
                    `slug`       VARCHAR(12) NOT NULL UNIQUE,
                    `title`      VARCHAR(200) DEFAULT '',
                    `content`    LONGTEXT NOT NULL,
                    `language`   VARCHAR(40) DEFAULT 'plaintext',
                    `expires_at` DATETIME DEFAULT NULL,
                    `views`      INT DEFAULT 0,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_slug (slug),
                    INDEX idx_expires (expires_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (PDOException $e) {
            die('DB Error: ' . $e->getMessage());
        }
    }
    return $pdo;
}

function randomSlug(int $len = 8): string {
    $chars = 'abcdefghijkmnpqrstuvwxyz23456789';
    $s = '';
    for ($i = 0; $i < $len; $i++) $s .= $chars[random_int(0, strlen($chars) - 1)];
    return $s;
}
