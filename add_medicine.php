<?php
require_once 'includes/auth.php';
require_once 'connect.php';

$addError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_medicine'])) {
    $medicineName= trim($_POST['medicineName'] ?? '');
    $barcode= trim($_POST['barcode'] ?? '');
    $categoryId = $_POST['categoryId'] ?? '';
    $supplierId = $_POST['supplierId'] ?? '';
    $quantity = $_POST['quantity'] ?? '';
    $minStock = $_POST['minStock'] ?? '';
    $price = $_POST['price'] ?? '';
    $sellingPrice = $_POST['sellingPrice'] ?? '';
    $manufactureDate = $_POST['manufactureDate'] ?? '';
    $expiryDate = $_POST['expiryDate'] ?? '';

    if ($medicineName === '' || $categoryId === '' || $supplierId === '' || $quantity === '' || $price === '' || $sellingPrice === '' || $expiryDate === '') {
        $addError = "Please fill in all required fields.";
    } elseif ($manufactureDate !== '' && $expiryDate <= $manufactureDate) {
        $addError = "Expiry date must be after the manufacture date.";
    } else {
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
                $addError = "the barcode scaned is existe, try again";
            } else {
                $addError = "Could not save medicine. Please check the values and try again.";
            }
        }
    }
}

$categories = $db->query("SELECT categoryId, categoryName FROM categories ORDER BY categoryName")->fetchAll();
$suppliers  = $db->query("SELECT supplierId, supplierName FROM suppliers ORDER BY supplierName")->fetchAll();

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
</head>
<body>

<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>

    <main class="pims-main">
        <?php include 'includes/header.php'; ?>

        <div class="container">

            <?php if ($addError): ?>
                <div class="pos-alert pos-alert-error"><?= htmlspecialchars($addError) ?></div>
            <?php endif; ?>

            <div class="panel full-panel">
                <div class="panel-header">
                    <span class="panel-title">Add Medicine</span>
                    <a href="medicines.php" class="view-all"><i class="fa-solid fa-circle-arrow-left"></i>Back to Medicines</a>
                </div>

                <form method="POST" class="med-form">
                    <input type="hidden" name="add_medicine" value="1">

                    <div class="form-row">
                        <div class="form-group">
                            <label>Medicine Name</label>
                            <input type="text" name="medicineName" value="<?= htmlspecialchars($_POST['medicineName'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Barcode</label>
                            <div class="barcode-input-row">
                                <input type="text" id="barcodeField" name="barcode" value="<?= htmlspecialchars($_POST['barcode'] ?? '') ?>" placeholder="Scan or type manually">
                                <button type="button" class="scan-icon-btn" onclick="openCameraScanner()" title="Scan with camera"><i class="fa-solid fa-barcode"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Category</label>
                            <select name="categoryId" required>
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
                            <select name="supplierId" required>
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
                            <input type="number" name="quantity" min="0" value="<?= htmlspecialchars($_POST['quantity'] ?? '0') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Minimum Stock (low stock alert level)</label>
                            <input type="number" name="minStock" min="0" value="<?= htmlspecialchars($_POST['minStock'] ?? '0') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Buying Price (UGX)</label>
                            <input type="number" name="price" min="0" step="0.01" value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Selling Price (UGX)</label>
                            <input type="number" name="sellingPrice" min="0" step="0.01" value="<?= htmlspecialchars($_POST['sellingPrice'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Manufacture Date</label>
                            <input type="date" name="manufactureDate" value="<?= htmlspecialchars($_POST['manufactureDate'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Expiry Date</label>
                            <input type="date" name="expiryDate" value="<?= htmlspecialchars($_POST['expiryDate'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="med-form-footer">
                        <a href="medicines.php" class="modal-cancel-btn">Cancel</a>
                        <button type="submit" class="modal-save-btn">Save Medicine</button>
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
            <button type="button" onclick="closeCameraScanner()">x</button>
        </div>
        <div id="cameraReader"></div>
        <p class="scanner-hint" id="scannerHint">Point the camera at the barcode on the medicine box.</p>
    </div>
</div>

<script>
    let html5QrCode = null;

    function openCameraScanner() {
        document.getElementById('scannerOverlay').style.display = 'flex';
        document.getElementById('scannerHint').textContent = 'Point the camera at the barcode on the medicine box.';
        document.getElementById('scannerHint').style.color = '';
        html5QrCode = new Html5Qrcode("cameraReader");

        html5QrCode.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: { width: 250, height: 150 } },
            (decodedText) => {
                // No DB lookup here - this medicine doesn't exist yet,
                // we're just capturing the code printed on the box.
                document.getElementById('barcodeField').value = decodedText;
                document.getElementById('scannerHint').textContent = `✅ Captured: ${decodedText}`;
                document.getElementById('scannerHint').style.color = '#2f6f4f';
                setTimeout(closeCameraScanner, 700);
            },
            (errorMessage) => { /* fires continuously while scanning - ignore */ }
        ).catch((err) => {
            document.getElementById('scannerHint').textContent = '❌ Could not access the camera';
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
</script>

</body>
</html>