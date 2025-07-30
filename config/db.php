<?php
$host = 'localhost';
$dbname = 'exam_qr_system';
$username = 'root'; // Change in production
$password = ''; // Change in production

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>