<?php
require_once __DIR__ . '/phpqrcode/qrlib.php';

function generateQRCode($data, $filename = null) {
    ob_start();
    QRcode::png($data, null, QR_ECLEVEL_H, 10);
    $image = ob_get_clean();
    return 'data:image/png;base64,' . base64_encode($image);
}


?>