<?php
require_once 'includes/auth.php';
require_once 'connect.php';

header('Content-Type: application/json');

$user_id = $_SESSION['userId'];
$payment_method = $_POST['payment_method'] ?? '';
$cart_data = json_decode($_POST['cart_data'] ?? '[]', true);

if (empty($cart_data)) {
    echo json_encode(['success' => false, 'message' => 'Cart is empty.']);
    exit();
}

try {
    $db->beginTransaction();

    $total_amount = 0;
    foreach ($cart_data as $item) {
        $total_amount += $item['price'] * $item['qty'];
    }

    $stmt = $db->prepare("INSERT INTO sales (userId, totalAmount, paymentMethod) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $total_amount, $payment_method]);
    $sale_id = $db->lastInsertId();

    foreach ($cart_data as $item) {
        $subtotal = $item['price'] * $item['qty'];

        $stmt_det = $db->prepare("INSERT INTO sale_details (saleId, medicineId, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?)");
        $stmt_det->execute([$sale_id, $item['id'], $item['qty'], $item['price'], $subtotal]);

        $stmt_upd = $db->prepare("UPDATE medicines SET quantity = quantity - ? WHERE medicineId = ?");
        $stmt_upd->execute([$item['qty'], $item['id']]);
    }

    $db->commit();

    echo json_encode([
        'success' => true,
        'saleId'=> $sale_id,
        'saleDate' => date('d M Y'),
        'totalAmount'=> $total_amount,
    ]);

} catch (Exception $e) {
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Could not complete the sale. Please try again.']);
}