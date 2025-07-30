<?php
require_once __DIR__ . '/phpqrcode/qrlib.php';

function generateQRCodeWithLogo($data, $filename = null, $logoPath = null) {
    $tempDir = __DIR__ . '/../temp/';
    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    
    $qrTempFile = $tempDir . 'qr_' . uniqid() . '.png';
    $finalFile = $filename ?: $tempDir . 'qr_final_' . uniqid() . '.png';
    
    // Generate base QR code with high error correction
    QRcode::png($data, $qrTempFile, QR_ECLEVEL_H, 10, 2);
    
    if ($logoPath && file_exists($logoPath)) {
        // Load QR code
        $qr = imagecreatefrompng($qrTempFile);
        
        // Load logo
        $logo = imagecreatefromstring(file_get_contents($logoPath));
        
        // Get dimensions
        $qrWidth = imagesx($qr);
        $qrHeight = imagesy($qr);
        $logoWidth = imagesx($logo);
        $logoHeight = imagesy($logo);
        
        // Calculate logo size (15% of QR code size)
        $logoNewWidth = $qrWidth * 0.15;
        $logoNewHeight = $logoHeight * ($logoNewWidth / $logoWidth);
        
        // Resize logo
        $logoResized = imagecreatetruecolor($logoNewWidth, $logoNewHeight);
        imagealphablending($logoResized, false);
        imagesavealpha($logoResized, true);
        imagecopyresampled($logoResized, $logo, 0, 0, 0, 0, 
                          $logoNewWidth, $logoNewHeight, $logoWidth, $logoHeight);
        
        // Calculate position (center)
        $x = ($qrWidth - $logoNewWidth) / 2;
        $y = ($qrHeight - $logoNewHeight) / 2;
        
        // Merge logo with QR code
        imagecopymerge($qr, $logoResized, $x, $y, 0, 0, $logoNewWidth, $logoNewHeight, 100);
        
        // Save final image
        imagepng($qr, $finalFile);
        imagedestroy($qr);
        imagedestroy($logo);
        imagedestroy($logoResized);
        
        // Clean up temp file
        unlink($qrTempFile);
    } else {
        rename($qrTempFile, $finalFile);
    }
    
    if (!$filename) {
        // Return as base64 for direct output
        $imageData = file_get_contents($finalFile);
        unlink($finalFile);
        return 'data:image/png;base64,' . base64_encode($imageData);
    }
    
    return $finalFile;
}
?>