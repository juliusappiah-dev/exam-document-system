<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../libs/qr_helper.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Generate unique serial number
    $serial = 'DOC-' . strtoupper(bin2hex(random_bytes(4)));
    
    // Prepare QR data
    $qrData = [
        'center_code' => $data['center_code'],
        'subject_code' => $data['subject_code'],
        'batch_code' => $data['batch_code'],
        'serial' => $serial,
        'timestamp' => date('c')
    ];
    $qrDataString = json_encode($qrData);
    
    // Save to database
    $stmt = $pdo->prepare("INSERT INTO documents (serial_number, qr_data, exam_center_id, batch_id, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$serial, $qrDataString, $data['center_id'], $data['batch_id'], $data['user_id']]);
    
    // Generate QR image (base64 for preview)
    $qrImage = generateQRCode($qrDataString);
    
    echo json_encode([
        'success' => true,
        'serial' => $serial,
        'qr_image' => $qrImage,
        'qr_data' => $qrData
    ]);
}
?>