<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');     // ← your MySQL username
define('DB_PASS', '');         // ← your MySQL password
define('DB_NAME', 'poll_app');
define('BASE_URL', 'http://localhost/poll-app'); // ← your domain

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO("mysql:host=".DB_HOST.";charset=utf8mb4", DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `".DB_NAME."` CHARACTER SET utf8mb4");
            $pdo->exec("USE `".DB_NAME."`");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `polls` (
                    `id`         INT AUTO_INCREMENT PRIMARY KEY,
                    `slug`       VARCHAR(12) NOT NULL UNIQUE,
                    `question`   VARCHAR(500) NOT NULL,
                    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_slug (slug)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `options` (
                    `id`       INT AUTO_INCREMENT PRIMARY KEY,
                    `poll_id`  INT NOT NULL,
                    `text`     VARCHAR(300) NOT NULL,
                    `votes`    INT DEFAULT 0,
                    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `votes` (
                    `id`         INT AUTO_INCREMENT PRIMARY KEY,
                    `poll_id`    INT NOT NULL,
                    `option_id`  INT NOT NULL,
                    `voter_hash` VARCHAR(64) NOT NULL,
                    `voted_at`   DATETIME DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY unique_vote (poll_id, voter_hash),
                    FOREIGN KEY (poll_id) REFERENCES polls(id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (PDOException $e) {
            die('DB Error: ' . $e->getMessage());
        }
    }
    return $pdo;
}

function randomSlug(int $len = 8): string {
    $c = 'abcdefghjkmnpqrstuvwxyz23456789';
    $s = '';
    for ($i = 0; $i < $len; $i++) $s .= $c[random_int(0, strlen($c)-1)];
    return $s;
}

function voterHash(int $pollId): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return hash('sha256', $pollId . $ip . $ua . date('Y-m-d'));
}
