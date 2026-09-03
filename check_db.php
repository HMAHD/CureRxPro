<?php
require_once __DIR__ . '/config/db.php';

echo "=== SETTINGS ===\n";
$stmt = $pdo->query("SELECT * FROM settings LIMIT 1");
print_r($stmt->fetch());

echo "\n=== LATEST PRESCRIPTION ===\n";
$stmt = $pdo->query("SELECT * FROM prescriptions ORDER BY id DESC LIMIT 1");
$rx = $stmt->fetch();
print_r($rx);

if ($rx) {
    echo "\n=== LATEST ITEMS ===\n";
    $stmt2 = $pdo->prepare("SELECT * FROM prescription_items WHERE prescription_id = ?");
    $stmt2->execute([$rx['id']]);
    print_r($stmt2->fetchAll());
}
