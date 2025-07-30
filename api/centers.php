<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $pdo->query("SELECT * FROM exam_centers");
    $centers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($centers);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $stmt = $pdo->prepare("INSERT INTO exam_centers (center_code, name, location) VALUES (?, ?, ?)");
    $stmt->execute([$data['center_code'], $data['name'], $data['location']]);
    echo json_encode(['success' => true]);
}
?>