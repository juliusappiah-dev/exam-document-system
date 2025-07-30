<?php
header("Content-Type: application/json");
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../libs/phpqrcode/qrcode.php'; // Adjust path if needed

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

// Generate a unique serial number (format: EXYYYY-RANDOM8)
function generateSerialNumber() {
    $prefix = 'EX' . date('Y') . '-';
    $random = substr(strtoupper(bin2hex(random_bytes(4))), 0, 8);
    return $prefix . $random;
}

// Generate QR code and save to server
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    // Validate input
    if (empty($data['center_code']) || empty($data['subject_code']) || empty($data['batch_code'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    // Generate serial number
    $serial = generateSerialNumber();

    // QR code data structure
    $qrData = json_encode([
        'serial' => $serial,
        'center_code' => $data['center_code'],
        'subject' => $data['subject_code'],
        'batch' => $data['batch_code']
    ]);

    // Generate QR code
    $options = new QROptions([
        'version' => 5,
        'outputType' => QRCode::OUTPUT_IMAGE_PNG,
        'eccLevel' => QRCode::ECC_H // High error correction
    ]);

    $qrCode = (new QRCode($options))->render($qrData);

    // Save QR code to server (optional)
    $qrDir = __DIR__ . '/../qr_codes/';
    if (!is_dir($qrDir)) mkdir($qrDir, 0755, true);

    $qrFilename = $serial . '.png';
    file_put_contents($qrDir . $qrFilename, base64_decode(str_replace('data:image/png;base64,', '', $qrCode)));

    // Store in database
    try {
        $stmt = $pdo->prepare("
            INSERT INTO documents 
            (serial_number, qr_data, exam_center_id, batch_id, created_by) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $serial,
            $qrData,
            $data['center_id'], // From frontend
            $data['batch_id'],  // From frontend
            $data['user_id']   // Logged-in admin
        ]);

        echo json_encode([
            'success' => true,
            'serial' => $serial,
            'qr_image' => 'qr_codes/' . $qrFilename // Return path for frontend
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>