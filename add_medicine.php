<?php
require_once 'includes/auth.php';
require_once 'connect.php';

$addError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_medicine'])) {
    $medicineName    = trim($_POST['medicineName'] ?? '');
    $barcode         = trim($_POST['barcode'] ?? '');
    $categoryId      = $_POST['categoryId'] ?? '';
    $supplierId      = $_POST['supplierId'] ?? '';
    $quantity        = $_POST['quantity'] ?? '';
    $minStock        = $_POST['minStock'] ?? '';
    $price           = $_POST['price'] ?? '';
    $sellingPrice    = $_POST['sellingPrice'] ?? '';
    $manufactureDate = $_POST['manufactureDate'] ?? '';
    $expiryDate      = $_POST['expiryDate'] ?? '';

    if ($medicineName === '' || $categoryId === '' || $supplierId === '' || $quantity === '' || $price === '' || $sellingPrice === '' || $expiryDate === '') {
        $addError = "Please fill in all required fields.";
    } elseif ($manufactureDate !== '' && $expiryDate <= $manufactureDate) {
        $addError = "Expiry date must be after the manufacture date.";
    } else {
        // Prevent duplicate barcode entries before database insertion
        if ($barcode !== '') {
            $checkStmt = $db->prepare("SELECT medicineId, medicineName FROM medicines WHERE barcode = ? LIMIT 1");
            $checkStmt->execute([$barcode]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $addError = "The barcode '" . htmlspecialchars($barcode) . "' is already assigned to '" . htmlspecialchars($existing['medicineName']) . "'. Duplicate barcodes are not allowed.";
            }
        }

        if ($addError === null) {
            try {
                $stmt = $db->prepare("INSERT INTO medicines (medicineName, barcode, categoryId, supplierId, quantity, minStock, price, sellingPrice, manufactureDate, expiryDate)
                VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $medicineName,
                    $barcode !== '' ? $barcode : null,
                    $categoryId,
                    $supplierId,
                    $quantity,
                    $minStock !== '' ? $minStock : 0,
                    $price,
                    $sellingPrice,
                    $manufactureDate !== '' ? $manufactureDate : null,
                    $expiryDate,
                ]);

                header("Location: medicines.php?added=1");
                exit();
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $addError = "The barcode scanned already exists in the database. Please use a unique barcode.";
                } else {
                    $addError = "Could not save medicine. Please check the values and try again.";
                }
            }
        }
    }
}

$categories = $db->query("SELECT categoryId, categoryName FROM categories ORDER BY categoryName")->fetchAll();
$suppliers  = $db->query("SELECT supplierId, supplierName FROM suppliers ORDER BY supplierName")->fetchAll();

$defaultBarcode = htmlspecialchars($_POST['barcode'] ?? $_GET['barcode'] ?? '');

$pageTitle = 'Add Medicine';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Medicine</title>
    <link rel="stylesheet" href="includes/style.css">
    <link rel="stylesheet" href="includes/style_dashboard.css">
    <link rel="stylesheet" href="includes/style_medicines.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- html5-qrcode: reads barcodes from the device camera -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        .barcode-status-card {
            display: none;
            background: #fff8e6;
            border: 1px solid #f6d365;
            border-left: 5px solid #d97706;
            border-radius: 8px;
            padding: 14px 18px;
            margin-bottom: 20px;
        }
        .barcode-status-card.error {
            background: #fdf2f2;
            border-color: #f8b4b4;
            border-left-color: #e02424;
        }
        .barcode-status-card.success {
            background: #f3faf7;
            border-color: #bcf0da;
            border-left-color: #0e9f6e;
        }
        .barcode-med-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 10px;
            margin-top: 10px;
            font-size: 13px;
        }
        .barcode-med-details div {
            background: rgba(255, 255, 255, 0.7);
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        .barcode-med-details strong {
            display: block;
            color: #4b5563;
            font-size: 11px;
            text-transform: uppercase;
        }
        .barcode-action-btn {
            display: inline-block;
            margin-top: 10px;
            padding: 6px 14px;
            background: #2f6f4f;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
        }
        .barcode-action-btn:hover {
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

            <?php if ($addError): ?>
                <div class="pos-alert pos-alert-error" id="serverAlert"><?= htmlspecialchars($addError) ?></div>
            <?php endif; ?>

            <!-- Scanned Medicine Information Card -->
            <div id="barcodeStatusCard" class="barcode-status-card">
                <div id="barcodeStatusTitle" style="font-weight: bold; font-size: 14px;"></div>
                <div id="barcodeStatusMessage" style="font-size: 13px; margin-top: 4px;"></div>
                <div id="barcodeMedDetails" class="barcode-med-details" style="display: none;">
                    <div><strong>Medicine Name</strong><span id="dtlName">-</span></div>
                    <div><strong>Barcode</strong><span id="dtlBarcode">-</span></div>
                    <div><strong>Category</strong><span id="dtlCategory">-</span></div>
                    <div><strong>Buying Price</strong>UGX <span id="dtlPrice">-</span></div>
                    <div><strong>Selling Price</strong>UGX <span id="dtlSellingPrice">-</span></div>
                    <div><strong>Current Stock</strong><span id="dtlStock">-</span></div>
                    <div><strong>Expiry Date</strong><span id="dtlExpiry">-</span></div>
                </div>
                <div id="barcodeActionArea" style="margin-top: 8px; display: none;"></div>
            </div>

            <div class="panel full-panel">
                <div class="panel-header">
                    <span class="panel-title">Add Medicine</span>
                    <a href="medicines.php" class="view-all"><i class="fa-solid fa-circle-arrow-left"></i>Back to Medicines</a>
                </div>

                <form method="POST" class="med-form" id="addMedicineForm">
                    <input type="hidden" name="add_medicine" value="1">

                    <div class="form-row">
                        <div class="form-group">
                            <label>Medicine Name</label>
                            <input type="text" name="medicineName" id="medNameField" value="<?= htmlspecialchars($_POST['medicineName'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Barcode</label>
                            <div class="barcode-input-row">
                                <input type="text" id="barcodeField" name="barcode" value="<?= $defaultBarcode ?>" placeholder="Scan with USB scanner or type code" autocomplete="off">
                                <button type="button" class="scan-icon-btn" onclick="openCameraScanner()" title="Scan with camera"><i class="fa-solid fa-barcode"></i></button>
                            </div>
                            <small id="barcodeHint" style="font-size: 12px; color: #6b7280; margin-top: 4px; display: block;">Supports USB handheld scanners, camera scanner, or manual entry.</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Category</label>
                            <select name="categoryId" id="categoryField" required>
                                <option value="">Select category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['categoryId'] ?>" <?= (($_POST['categoryId'] ?? '') == $cat['categoryId']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cat['categoryName']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Supplier</label>
                            <select name="supplierId" id="supplierField" required>
                                <option value="">Select supplier</option>
                                <?php foreach ($suppliers as $sup): ?>
                                    <option value="<?= $sup['supplierId'] ?>" <?= (($_POST['supplierId'] ?? '') == $sup['supplierId']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sup['supplierName']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Quantity in Stock</label>
                            <input type="number" name="quantity" id="quantityField" min="0" value="<?= htmlspecialchars($_POST['quantity'] ?? '0') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Minimum Stock (low stock alert level)</label>
                            <input type="number" name="minStock" id="minStockField" min="0" value="<?= htmlspecialchars($_POST['minStock'] ?? '0') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Buying Price (UGX)</label>
                            <input type="number" name="price" id="priceField" min="0" step="0.01" value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Selling Price (UGX)</label>
                            <input type="number" name="sellingPrice" id="sellingPriceField" min="0" step="0.01" value="<?= htmlspecialchars($_POST['sellingPrice'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Manufacture Date</label>
                            <input type="date" name="manufactureDate" id="mfgDateField" value="<?= htmlspecialchars($_POST['manufactureDate'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Expiry Date</label>
                            <input type="date" name="expiryDate" id="expiryDateField" value="<?= htmlspecialchars($_POST['expiryDate'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="med-form-footer">
                        <a href="medicines.php" class="modal-cancel-btn">Cancel</a>
                        <button type="submit" id="btnSaveMedicine" class="modal-save-btn">Save Medicine</button>
                    </div>
                </form>
            </div>

        </div>
    </main>
</div>

<!-- Camera Scanner Modal -->
<div class="scanner-overlay" id="scannerOverlay">
    <div class="scanner-box">
        <div class="scanner-header">
            <h3>Scan Barcode</h3>
            <button type="button" onclick="closeCameraScanner()">&times;</button>
        </div>
        <div id="cameraReader"></div>
        <p class="scanner-hint" id="scannerHint">Point the camera at the barcode on the medicine box.</p>
    </div>
</div>

<script>
    let html5QrCode = null;
    const barcodeField = document.getElementById('barcodeField');
    const btnSave = document.getElementById('btnSaveMedicine');
    const statusCard = document.getElementById('barcodeStatusCard');
    const statusTitle = document.getElementById('barcodeStatusTitle');
    const statusMsg = document.getElementById('barcodeStatusMessage');
    const medDetails = document.getElementById('barcodeMedDetails');
    const actionArea = document.getElementById('barcodeActionArea');

    // Handle USB Barcode Scanner (intercept Enter key in barcode field to prevent premature form submit)
    barcodeField.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            handleBarcodeLookup(this.value.trim());
        }
    });

    barcodeField.addEventListener('blur', function() {
        if (this.value.trim() !== '') {
            handleBarcodeLookup(this.value.trim());
        }
    });

    // Global USB barcode scanner detection (keyboard wedge)
    // USB scanners type rapidly and terminate with Enter
    let keyBuffer = '';
    let lastKeyTime = 0;
    window.addEventListener('keydown', function(e) {
        const currentTime = Date.now();
        const activeTag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';

        // If user is typing in another input field (not the barcode field), don't intercept unless it's a fast burst
        if (activeTag === 'input' && document.activeElement.id !== 'barcodeField') {
            return;
        }

        if (currentTime - lastKeyTime > 60) {
            keyBuffer = '';
        }
        lastKeyTime = currentTime;

        if (e.key === 'Enter') {
            if (keyBuffer.length >= 3) {
                e.preventDefault();
                barcodeField.value = keyBuffer;
                handleBarcodeLookup(keyBuffer);
                keyBuffer = '';
            }
        } else if (e.key.length === 1) {
            keyBuffer += e.key;
        }
    });

    async function handleBarcodeLookup(barcode) {
        if (!barcode) {
            hideBarcodeStatus();
            btnSave.disabled = false;
            return;
        }

        statusCard.style.display = 'block';
        statusCard.className = 'barcode-status-card';
        statusTitle.textContent = 'Checking Barcode...';
        statusMsg.textContent = `Looking up barcode "${barcode}" in database...`;
        medDetails.style.display = 'none';
        actionArea.style.display = 'none';

        try {
            const res = await fetch(`lookup_barcode.php?barcode=${encodeURIComponent(barcode)}`);
            const data = await res.json();

            if (data.found && data.medicine) {
                const med = data.medicine;
                // Medicine already exists - show details, auto-populate fields, prevent duplicate insertion
                statusCard.className = 'barcode-status-card error';
                statusTitle.textContent = ` Duplicate Barcode: Already Registered to "${med.medicineName}"`;
                statusMsg.textContent = `This barcode is already assigned to a medicine in the database. Duplicate barcodes cannot be registered.`;

                // Display complete medicine information
                document.getElementById('dtlName').textContent = med.medicineName;
                document.getElementById('dtlBarcode').textContent = med.barcode;
                document.getElementById('dtlCategory').textContent = med.categoryName;
                document.getElementById('dtlPrice').textContent = med.formattedPrice;
                document.getElementById('dtlSellingPrice').textContent = med.formattedSellingPrice;
                document.getElementById('dtlStock').textContent = med.quantity + ' units';
                document.getElementById('dtlExpiry').textContent = med.formattedExpiry;
                medDetails.style.display = 'grid';

                // Automatically populate the relevant fields
                document.getElementById('medNameField').value = med.medicineName;
                document.getElementById('categoryField').value = med.categoryId;
                document.getElementById('supplierField').value = med.supplierId;
                document.getElementById('quantityField').value = med.quantity;
                document.getElementById('minStockField').value = med.minStock;
                document.getElementById('priceField').value = med.price;
                document.getElementById('sellingPriceField').value = med.sellingPrice;
                document.getElementById('mfgDateField').value = med.manufactureDate || '';
                document.getElementById('expiryDateField').value = med.expiryDate || '';

                // Prevent submitting duplicate barcode
                btnSave.disabled = true;
                btnSave.title = 'Cannot register: barcode already in use';

                actionArea.style.display = 'block';
                actionArea.innerHTML = `
                    <a href="medicines.php?q=${encodeURIComponent(med.barcode)}" class="barcode-action-btn">
                        <i class="fa-solid fa-pen-to-square"></i> View / Edit "${med.medicineName}" in Medicines
                    </a>
                `;
            } else {
                // Barcode is available for new registration
                statusCard.className = 'barcode-status-card success';
                statusTitle.textContent = 'Barcode Available';
                statusMsg.textContent = `Barcode "${barcode}" is not registered in the system. You can proceed with registration.`;
                medDetails.style.display = 'none';
                actionArea.style.display = 'none';

                btnSave.disabled = false;
                btnSave.title = '';
            }
        } catch (err) {
            statusCard.className = 'barcode-status-card error';
            statusTitle.textContent = 'Lookup Error';
            statusMsg.textContent = 'Could not verify barcode with server. Please try again.';
        }
    }

    function hideBarcodeStatus() {
        statusCard.style.display = 'none';
        medDetails.style.display = 'none';
        actionArea.style.display = 'none';
    }

    function openCameraScanner() {
        document.getElementById('scannerOverlay').style.display = 'flex';
        document.getElementById('scannerHint').textContent = 'Point the camera at the barcode on the medicine box.';
        document.getElementById('scannerHint').style.color = '';
        html5QrCode = new Html5Qrcode("cameraReader");

        html5QrCode.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: { width: 250, height: 150 } },
            (decodedText) => {
                document.getElementById('barcodeField').value = decodedText;
                document.getElementById('scannerHint').textContent = `Captured: ${decodedText}`;
                document.getElementById('scannerHint').style.color = '#2f6f4f';
                setTimeout(() => {
                    closeCameraScanner();
                    handleBarcodeLookup(decodedText);
                }, 600);
            },
            (errorMessage) => { /* scanning */ }
        ).catch((err) => {
            document.getElementById('scannerHint').textContent = 'Could not access camera';
            document.getElementById('scannerHint').style.color = '#c0392b';
        });
    }

    function closeCameraScanner() {
        document.getElementById('scannerOverlay').style.display = 'none';
        if (html5QrCode) {
            html5QrCode.stop().catch(() => {});
            html5QrCode = null;
        }
    }

    // Auto-check on load if barcode field has a value
    window.addEventListener('DOMContentLoaded', () => {
        if (barcodeField.value.trim() !== '') {
            handleBarcodeLookup(barcodeField.value.trim());
        }
    });
</script>

</body>
</html>
