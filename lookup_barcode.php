<?php
require_once 'includes/auth.php';
require_once 'connect.php';

header('Content-Type: application/json; charset=utf-8');

$barcode = trim($_GET['barcode'] ?? $_POST['barcode'] ?? '');

// Input validation
if ($barcode === '') {
    echo json_encode([
        'found' => false,
        'success' => false,
        'message' => 'Please provide a barcode to scan or search.'
    ]);
    exit();
}

// Check for duplicate barcode mode (used when registering or updating a medicine)
if (isset($_GET['check_duplicate']) || isset($_POST['check_duplicate'])) {
    $excludeId = (int)($_GET['excludeId'] ?? $_POST['excludeId'] ?? 0);
    $stmt = $db->prepare("SELECT medicineId, medicineName FROM medicines WHERE barcode = ? AND medicineId != ? LIMIT 1");
    $stmt->execute([$barcode, $excludeId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        echo json_encode([
            'found' => true,
            'success' => true,
            'exists' => true,
            'duplicate' => true,
            'medicineId' => (int)$existing['medicineId'],
            'medicineName' => $existing['medicineName'],
            'message' => "Barcode '" . htmlspecialchars($barcode) . "' is already assigned to '" . htmlspecialchars($existing['medicineName']) . "'."
        ]);
    } else {
        echo json_encode([
            'found' => false,
            'success' => true,
            'exists' => false,
            'duplicate' => false,
            'message' => 'Barcode is available.'
        ]);
    }
    exit();
}

// Retrieve complete medicine details from database
$stmt = $db->prepare("
    SELECT m.medicineId, m.medicineName, m.barcode, m.categoryId, c.categoryName,
           m.supplierId, s.supplierName, m.price, m.sellingPrice,
           m.quantity, m.minStock, m.manufactureDate, m.expiryDate
    FROM medicines m
    LEFT JOIN categories c ON c.categoryId = m.categoryId
    LEFT JOIN suppliers s  ON s.supplierId  = m.supplierId
    WHERE m.barcode = ?
    LIMIT 1
");
$stmt->execute([$barcode]);
$med = $stmt->fetch(PDO::FETCH_ASSOC);

if ($med) {
    $isExpired = (!empty($med['expiryDate']) && strtotime($med['expiryDate']) < strtotime('today'));
    $isLowStock = ((int)$med['quantity'] <= (int)$med['minStock']);
    $isOutOfStock = ((int)$med['quantity'] <= 0);

    echo json_encode([
        'found' => true,
        'success' => true,
        'medicine' => [
            'medicineId'            => (int)$med['medicineId'],
            'medicineName'          => $med['medicineName'],
            'barcode'               => $med['barcode'],
            'categoryId'            => $med['categoryId'],
            'categoryName'          => $med['categoryName'] ?? 'Uncategorized',
            'supplierId'            => $med['supplierId'],
            'supplierName'          => $med['supplierName'] ?? 'Unknown Supplier',
            'price'                 => (float)$med['price'],
            'sellingPrice'          => (float)$med['sellingPrice'],
            'formattedPrice'        => number_format((float)$med['price']),
            'formattedSellingPrice' => number_format((float)$med['sellingPrice']),
            'quantity'              => (int)$med['quantity'],
            'minStock'              => (int)$med['minStock'],
            'manufactureDate'       => $med['manufactureDate'] ?? '',
            'expiryDate'            => $med['expiryDate'] ?? '',
            'formattedExpiry'       => !empty($med['expiryDate']) ? date('d M Y', strtotime($med['expiryDate'])) : 'N/A',
            'isExpired'             => $isExpired,
            'isLowStock'            => $isLowStock,
            'isOutOfStock'          => $isOutOfStock
        ]
    ]);
} else {
    echo json_encode([
        'found' => false,
        'success' => false,
        'message' => "Medicine with barcode '" . htmlspecialchars($barcode) . "' not found in database."
    ]);
}
