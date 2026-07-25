<?php
require_once 'includes/auth.php';
require_role(['admin']);
require_once 'connect.php';

$purchaseError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_purchase'])) {
    $supplierId = $_POST['supplierId'] ?? '';
    $invoiceNumber= trim($_POST['invoiceNumber'] ?? '');
    $purchaseDate= $_POST['purchaseDate'] ?? '';
    $medicineIds = $_POST['medicineId'] ?? [];
    $quantities =$_POST['quantity'] ?? [];
    $prices =$_POST['buyingPrice'] ?? [];

    if ($supplierId === '' || $purchaseDate === '') {
        $purchaseError = "Supplier and Purchase Date are required.";
    } else {
        // Build a clean list of valid line items (skip any left blank)
        $lineItems = [];
        foreach ($medicineIds as $i => $medId) {
            $qty = $quantities[$i] ?? '';
            $price = $prices[$i] ?? '';
            if ($medId !== '' && $qty !== '' && $price !== '' && (int)$qty > 0) {
                $lineItems[] = [

                    'medicineId' => (int)$medId,
                    'quantity'   => (int)$qty,
                    'price'      => (float)$price,
                ];
            }
        }

        if (empty($lineItems)) {
            $purchaseError = "Add at least one medicine with a quantity and buying price.";
        } else {
            try {
                $db->beginTransaction();

                $totalAmount = 0;
                foreach ($lineItems as $item) {
                    $totalAmount += $item['quantity'] * $item['price'];
                }

                $stmt = $db->prepare("INSERT INTO purchases (supplierId, invoiceNumber, purchaseDate, totalAmount) VALUES (?, ?, ?, ?)");
                $stmt->execute([$supplierId, $invoiceNumber !== '' ? $invoiceNumber : null, $purchaseDate, $totalAmount]);
                $purchaseId = $db->lastInsertId();

                $detailStmt = $db->prepare("INSERT INTO purchase_details (purchaseId, medicineId, quantity, buyingPrice, subtotal) VALUES (?, ?, ?, ?, ?)");
                $stockStmt = $db->prepare("UPDATE medicines SET quantity = quantity + ?, price = ? WHERE medicineId = ?");

                foreach ($lineItems as $item) {
                    $subtotal = $item['quantity'] * $item['price'];
                    $detailStmt->execute([$purchaseId, $item['medicineId'], $item['quantity'], $item['price'], $subtotal]);
                    $stockStmt->execute([$item['quantity'], $item['price'], $item['medicineId']]);
                }

                $db->commit();
                header("Location: purchases.php?success=1");
                exit();

            } catch (Exception $e) {
                $db->rollBack();
                $purchaseError = "Could not save purchase. Please check the values and try again.";
            }
        }
    }
}

$suppliers = $db->query("SELECT supplierId, supplierName FROM suppliers ORDER BY supplierName")->fetchAll();
$medicines = $db->query("SELECT medicineId, medicineName FROM medicines ORDER BY medicineName")->fetchAll();

$pageTitle = 'Purchases';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchases</title>
    <link rel="stylesheet" href="includes/style.css">
    <link rel="stylesheet" href="includes/style_dashboard.css">
    <link rel="stylesheet" href="includes/style_purchases.css">
</head>
<body>

<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>

    <main class="pims-main">
        <?php include 'includes/header.php'; ?>

        <div class="container">

            <?php if (isset($_GET['success'])): ?>
                <div class="pos-alert pos-alert-success">Purchase made successfully.</div>
            <?php endif; ?>
            <?php if ($purchaseError): ?>
                <div class="pos-alert pos-alert-error"><?= htmlspecialchars($purchaseError) ?></div>
            <?php endif; ?>

            <div class="panel full-panel purchase-panel">
                <div class="purchase-header">
                    <span class="panel-title">Record a new delivery</span>
                </div>

                <form method="POST" id="purchaseForm">
                    <input type="hidden" name="save_purchase" value="1">

                    <div class="form-row">
                        <div class="form-group">
                            <label>Supplier</label>
                            <select name="supplierId" required>
                                <option value="">Select supplier</option>
                                <?php foreach ($suppliers as $s): ?>
                                    <option value="<?= $s['supplierId'] ?>"><?= htmlspecialchars($s['supplierName']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Invoice Number</label>
                            <input type="text" name="invoiceNumber" placeholder="e.g INV-9001">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Purchase Date</label>
                            <input type="date" name="purchaseDate" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div></div>
                    </div>

                    <div class="line-items-box">
                        <div class="line-items-header">
                            <span>Medicine</span>
                            <span>Quantity</span>
                            <span>Buying Price</span>
                            <span></span>
                        </div>

                        <div id="lineItemsContainer">
                            <div class="line-item-row">
                                <select name="medicineId[]" class="line-medicine" onchange="recalcTotal()">
                                    <option value="">Select medicine</option>
                                    <?php foreach ($medicines as $m): ?>
                                        <option value="<?= $m['medicineId'] ?>"><?= htmlspecialchars($m['medicineName']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="number" name="quantity[]" class="line-qty" placeholder="Qty" min="1" oninput="recalcTotal()">
                                <input type="number" name="buyingPrice[]" class="line-price" placeholder="Price" min="0" step="0.01" oninput="recalcTotal()">
                                <button type="button" class="remove-row-btn" onclick="removeRow(this)" title="Remove row"><i class="fa-solid fa-trash" style="color: rgb(251, 0, 0);"></i></button>
                            </div>
                        </div>

                        <button type="button" class="add-row-btn" onclick="addRow()">+ Add More Medicines</button>
                    </div>

                    <div class="purchase-footer">
                        <div class="purchase-total">Total: UGX <span id="totalDisplay">0</span></div>
                        <button type="submit" class="save-purchase-btn">Save Purchase</button>
                    </div>
                </form>
            </div>

        </div>
    </main>
</div>

<script>
function rowTemplate() {
    const container = document.getElementById('lineItemsContainer');
    const first = container.querySelector('.line-item-row');
    const clone = first.cloneNode(true);
    clone.querySelectorAll('select, input').forEach(el => { el.value = ''; });
    return clone;
}

function addRow() {
    const container = document.getElementById('lineItemsContainer');
    container.appendChild(rowTemplate());
}

function removeRow(btn) {
    const container = document.getElementById('lineItemsContainer');
    const rows = container.querySelectorAll('.line-item-row');
    if (rows.length > 1) {
        btn.closest('.line-item-row').remove();
        recalcTotal();
    }
}

function recalcTotal() {
    let total = 0;
    document.querySelectorAll('.line-item-row').forEach(row => {
        const qty = parseFloat(row.querySelector('.line-qty').value) || 0;
        const price = parseFloat(row.querySelector('.line-price').value) || 0;
        total += qty * price;
    });
    document.getElementById('totalDisplay').textContent = total.toLocaleString();
}
</script>

</body>
</html>