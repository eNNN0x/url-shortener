<?php
require_once 'config.php';

$code = trim($_GET['c'] ?? '');

if (!$code || !preg_match('/^[a-zA-Z0-9]+$/', $code)) {
    header('Location: index.php');
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare("SELECT long_url FROM urls WHERE code = ? LIMIT 1");
$stmt->execute([$code]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    // Increment click counter
    $pdo->prepare("UPDATE urls SET clicks = clicks + 1 WHERE code = ?")->execute([$code]);
    header('Location: ' . $row['long_url'], true, 301);
    exit;
}

// Not found
http_response_code(404);
header('Location: index.php?err=notfound');
exit;
