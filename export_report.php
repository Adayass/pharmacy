<?php
require_once 'includes/auth.php';
require_once 'connect.php';
require_role(['admin']);

$type = $_GET['type'] ?? '';
$allowed = ['inventory', 'sales', 'purchases', 'expiry'];

if (!in_array($type, $allowed)) {
    die("Invalid report type.");
}

$filename = "report_{$type}_" . date('Y-m-d') . ".csv";
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');

switch ($type) {

    case 'inventory':
        fputcsv($out, ['Medicine', 'Category', 'Supplier', 'Stock', 'Buying Price', 'Selling Price', 'Expiry Date']);
        $rows = $db->query("
            SELECT m.medicineName, c.categoryName, s.supplierName,
                   m.quantity, m.price, m.sellingPrice, m.expiryDate
            FROM medicines m
            LEFT JOIN categories c ON c.categoryId = m.categoryId
            LEFT JOIN suppliers s ON s.supplierId = m.supplierId
            ORDER BY m.medicineName
        ")->fetchAll();
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['medicineName'],
                $r['categoryName'] ?? 'N/A',
                $r['supplierName'] ?? 'N/A',
                $r['quantity'],
                $r['price'],
                $r['sellingPrice'],
                $r['expiryDate'],
            ]);
        }
        break;

    case 'sales':
        fputcsv($out, ['Receipt', 'Date', 'Cashier', 'Payment Method', 'Total']);
        $rows = $db->query("
            SELECT s.saleId, s.saleDate, u.fullName AS cashier, s.paymentMethod, s.totalAmount
            FROM sales s
            LEFT JOIN users u ON u.userId = s.userId
            ORDER BY s.saleDate DESC
        ")->fetchAll();
        foreach ($rows as $r) {
            fputcsv($out, [
                'RCT-' . str_pad($r['saleId'], 4, '0', STR_PAD_LEFT),
                $r['saleDate'],
                $r['cashier'],
                $r['paymentMethod'],
                $r['totalAmount'],
            ]);
        }
        break;

    case 'expiry':
        fputcsv($out, ['Medicine', 'Quantity', 'Expiry Date', 'Status']);
        $rows = $db->query("
            SELECT medicineName, quantity, expiryDate
            FROM medicines
            WHERE expiryDate <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            ORDER BY expiryDate ASC
        ")->fetchAll();
        foreach ($rows as $r) {
            $status = strtotime($r['expiryDate']) < strtotime('today') ? 'Expired' : 'Expiring Soon';
            fputcsv($out, [$r['medicineName'], $r['quantity'], $r['expiryDate'], $status]);
        }
        break;

    case 'purchases':
        // No purchases table exists yet in your schema - exporting the
        // same placeholder summary shown on the Reports page for now.
        fputcsv($out, ['Metric', 'Value']);
        fputcsv($out, ['Total purchases', 3]);
        fputcsv($out, ['Total spent (UGX)', 220000]);
        fputcsv($out, ['Note', 'Placeholder data - no purchases table in database yet']);
        break;
}

fclose($out);
exit();