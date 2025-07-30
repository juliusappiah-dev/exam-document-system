<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../config/db.php';

// Validate input
$subjectId = $_POST['subject_id'] ?? null;
$subjectName = trim($_POST['subject_name'] ?? '');
$subjectCode = trim($_POST['subject_code'] ?? '');

if (!$subjectId || empty($subjectName) || empty($subjectCode)) {
    $_SESSION['subject_error'] = "All fields are required";
    header("Location: manage_resources.php");
    exit();
}

try {
    // Check if subject code already exists (excluding current record)
    $stmt = $pdo->prepare("SELECT id FROM subjects WHERE subject_code = ? AND id != ?");
    $stmt->execute([$subjectCode, $subjectId]);
    
    if ($stmt->fetch()) {
        $_SESSION['subject_error'] = "Subject code already exists";
        header("Location: manage_resources.php");
        exit();
    }

    // Update the subject
    $stmt = $pdo->prepare("UPDATE subjects SET name = ?, subject_code = ? WHERE id = ?");
    $stmt->execute([$subjectName, $subjectCode, $subjectId]);
    
    $_SESSION['subject_success'] = "Subject updated successfully";
} catch (PDOException $e) {
    $_SESSION['subject_error'] = "Error updating subject: " . $e->getMessage();
}

header("Location: manage_resources.php");
exit();
?>