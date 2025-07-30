<?php
session_start();
if ($_SESSION['role'] !== 'admin') {
    header("Location: /auth/login.php");
    exit();
}
require_once __DIR__ . '/../config/db.php';

$logId = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("
    SELECT l.*, d.serial_number, d.qr_data, u.username as verifier 
    FROM verification_logs l
    LEFT JOIN documents d ON l.document_id = d.id
    LEFT JOIN users u ON l.verified_by = u.id
    WHERE l.id = ?
");
$stmt->execute([$logId]);
$log = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$log) {
    header("Location: /admin/dashboard.php");
    exit();
}
?>
<!-- HTML structure similar to dashboard, showing full log details -->