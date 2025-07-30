<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

// Validate input
$centerId = $_POST['center_id'] ?? null;
$centerName = trim($_POST['center_name'] ?? '');
$centerCode = trim($_POST['center_code'] ?? '');

if (!$centerId || empty($centerName) || empty($centerCode)) {
    $_SESSION['center_error'] = "All fields are required";
    header("Location: manage_resources.php");
    exit();
}

try {
    // Check if center code already exists (excluding current record)
    $stmt = $pdo->prepare("SELECT id FROM exam_centers WHERE center_code = ? AND id != ?");
    $stmt->execute([$centerCode, $centerId]);
    
    if ($stmt->fetch()) {
        $_SESSION['center_error'] = "Center code already exists";
        header("Location: manage_resources.php");
        exit();
    }

    // Update the center
    $stmt = $pdo->prepare("UPDATE exam_centers SET name = ?, center_code = ? WHERE id = ?");
    $stmt->execute([$centerName, $centerCode, $centerId]);
    
    $_SESSION['center_success'] = "Exam center updated successfully";
} catch (PDOException $e) {
    $_SESSION['center_error'] = "Error updating exam center: " . $e->getMessage();
}

header("Location: manage_resources.php");
exit();
?>