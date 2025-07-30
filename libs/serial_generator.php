<?php
function generateSerial() {
    do {
        $serial = 'DOC-' . strtoupper(bin2hex(random_bytes(4)));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE serial_number = ?");
        $stmt->execute([$serial]);
    } while ($stmt->fetchColumn() > 0);
    return $serial;
}
?>