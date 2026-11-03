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
$medicines = $db->query("SELECT medicineId, medicineName, barcode, price FROM medicines ORDER BY medicineName")->fetchAll();

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- html5-qrcode: camera barcode scanning -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        .purchase-scan-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 12px;
            align-items: center;
        }
        .purchase-scan-input {
            flex: 1;
            padding: 9px 12px;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
            font-size: 13px;
            outline: none;
        }
        .purchase-scan-input:focus {
            border-color: #2f6f4f;
        }
        .scan-feedback-badge {
            display: none;
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 12px;
        }
        .scan-feedback-badge.success {
            background: #edf7ed;
            border: 1px solid #b7eb8f;
            color: #1e4620;
        }
        .scan-feedback-badge.error {
            background: #fdf2f2;
            border: 1px solid #f8b4b4;
            color: #9b1c1c;
        }
        .scan-cam-btn {
            background: #2f6f4f;
            color: #fff;
            border: none;
            border-radius: 6px;
            padding: 9px 14px;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        .scan-cam-btn:hover {
            background: #245a3f;
        }
    </style>
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
                        <!-- Barcode Scanner Bar for incoming deliveries -->
                        <div class="purchase-scan-bar">
                            <i class="fa-solid fa-barcode" style="color: #2f6f4f; font-size: 20px;"></i>
                            <input type="text" id="purchaseBarcodeInput" class="purchase-scan-input" placeholder="Scan medicine barcode with scanner to add or select item..." autocomplete="off">
                            <button type="button" class="scan-cam-btn" onclick="openPurchaseCameraScanner()" title="Scan with camera">
                                <i class="fa-solid fa-camera"></i> Camera
                            </button>
                        </div>
                        <div id="purchaseScanFeedback" class="scan-feedback-badge"></div>

                        <div class="line-items-header">
                            <span>Medicine</span>
                            <span>Quantity</span>
                            <span>Buying Price</span>
                            <span></span>
                        </div>

                        <div id="lineItemsContainer">
                            <div class="line-item-row">
                                <select name="medicineId[]" class="line-medicine" onchange="onMedicineSelected(this)">
                                    <option value="">Select medicine</option>
                                    <?php foreach ($medicines as $m): ?>
                                        <option value="<?= $m['medicineId'] ?>" data-barcode="<?= htmlspecialchars($m['barcode'] ?? '') ?>" data-price="<?= $m['price'] ?? '' ?>">
                                            <?= htmlspecialchars($m['medicineName']) ?><?= !empty($m['barcode']) ? ' [' . htmlspecialchars($m['barcode']) . ']' : '' ?>
                                        </option>
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

<!-- Camera Scanner Modal -->
<div class="scanner-overlay" id="purchaseScannerOverlay" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); align-items: center; justify-content: center; z-index: 200;">
    <div class="scanner-box" style="background: #fff; border-radius: 12px; padding: 20px; width: 100%; max-width: 400px;">
        <div class="scanner-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
            <h3 style="margin: 0;">Scan Barcode</h3>
            <button type="button" onclick="closePurchaseCameraScanner()" style="background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
        </div>
        <div id="purchaseCameraReader"></div>
        <p class="scanner-hint" id="purchaseScannerHint" style="text-align: center; color: #718096; font-size: 13px; margin-top: 10px;">Point camera at the medicine barcode.</p>
    </div>
</div>

<script>
let purchaseCameraScanner = null;
const purchaseBarcodeInput = document.getElementById('purchaseBarcodeInput');
const purchaseScanFeedback = document.getElementById('purchaseScanFeedback');

function onMedicineSelected(selectEl) {
    const selectedOption = selectEl.options[selectEl.selectedIndex];
    const defaultPrice = selectedOption.getAttribute('data-price');
    const row = selectEl.closest('.line-item-row');
    const priceInput = row.querySelector('.line-price');

    if (defaultPrice && (!priceInput.value || parseFloat(priceInput.value) === 0)) {
        priceInput.value = defaultPrice;
    }
    recalcTotal();
}

function rowTemplate() {
    const container = document.getElementById('lineItemsContainer');
    const first = container.querySelector('.line-item-row');
    const clone = first.cloneNode(true);
    clone.querySelectorAll('select, input').forEach(el => { el.value = ''; });
    return clone;
}

function addRow() {
    const container = document.getElementById('lineItemsContainer');
    const newRow = rowTemplate();
    container.appendChild(newRow);
    return newRow;
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

/* -------------------------------------------------------------
   Barcode Scanning Logic for Purchases
   ------------------------------------------------------------- */
purchaseBarcodeInput.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const code = this.value.trim();
        if (code) {
            handlePurchaseBarcodeScan(code);
        }
    }
});

async function handlePurchaseBarcodeScan(barcode) {
    purchaseBarcodeInput.value = '';
    purchaseScanFeedback.style.display = 'block';
    purchaseScanFeedback.className = 'scan-feedback-badge';
    purchaseScanFeedback.textContent = `Looking up barcode "${barcode}"...`;

    try {
        const res = await fetch(`lookup_barcode.php?barcode=${encodeURIComponent(barcode)}`);
        const data = await res.json();

        if (data.found && data.medicine) {
            const med = data.medicine;

            // Find an empty row or create a new row
            const rows = document.querySelectorAll('.line-item-row');
            let targetRow = null;

            for (let row of rows) {
                const sel = row.querySelector('.line-medicine');
                if (!sel.value) {
                    targetRow = row;
                    break;
                }
            }

            if (!targetRow) {
                targetRow = addRow();
            }

            const sel = targetRow.querySelector('.line-medicine');
            sel.value = String(med.medicineId);

            const priceInput = targetRow.querySelector('.line-price');
            priceInput.value = med.price;

            const qtyInput = targetRow.querySelector('.line-qty');
            qtyInput.focus();

            recalcTotal();

            purchaseScanFeedback.className = 'scan-feedback-badge success';
            purchaseScanFeedback.textContent = `✅ Selected "${med.medicineName}" (Barcode: ${med.barcode}) with buying price UGX ${med.formattedPrice}. Enter quantity.`;
        } else {
            purchaseScanFeedback.className = 'scan-feedback-badge error';
            purchaseScanFeedback.textContent = `❌ Barcode not found: No medicine matches barcode "${barcode}".`;
        }
    } catch (err) {
        purchaseScanFeedback.className = 'scan-feedback-badge error';
        purchaseScanFeedback.textContent = '❌ Server communication error.';
    }
}

function openPurchaseCameraScanner() {
    const overlay = document.getElementById('purchaseScannerOverlay');
    overlay.style.display = 'flex';
    document.getElementById('purchaseScannerHint').textContent = 'Point camera at the medicine barcode.';
    document.getElementById('purchaseScannerHint').style.color = '';
    purchaseCameraScanner = new Html5Qrcode("purchaseCameraReader");

    purchaseCameraScanner.start(
        { facingMode: "environment" },
        { fps: 10, qrbox: { width: 250, height: 150 } },
        (decodedText) => {
            closePurchaseCameraScanner();
            handlePurchaseBarcodeScan(decodedText);
        },
        () => {}
    ).catch(() => {
        document.getElementById('purchaseScannerHint').textContent = '❌ Could not access camera';
        document.getElementById('purchaseScannerHint').style.color = '#c0392b';
    });
}

function closePurchaseCameraScanner() {
    document.getElementById('purchaseScannerOverlay').style.display = 'none';
    if (purchaseCameraScanner) {
        purchaseCameraScanner.stop().catch(() => {});
        purchaseCameraScanner = null;
    }
    purchaseBarcodeInput.focus();
}
</script>

</body>
</html>
