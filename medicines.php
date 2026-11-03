<?php
require_once 'includes/auth.php';
require_once 'connect.php';

$editError = null;
$deleteError = null;

/* ---------------------------------------------------------
   Handle Edit submission
   --------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_medicine'])) {
    $editId          = (int)($_POST['medicineId'] ?? 0);
    $medicineName    = trim($_POST['medicineName'] ?? '');
    $barcode         = trim($_POST['barcode'] ?? '');
    $barcodeVal      = $barcode !== '' ? $barcode : null;
    $categoryId      = $_POST['categoryId'] ?? '';
    $supplierId      = $_POST['supplierId'] ?? '';
    $price           = $_POST['price'] ?? '';
    $sellingPrice    = $_POST['sellingPrice'] ?? '';
    $quantity        = $_POST['quantity'] ?? '';
    $minStock        = $_POST['minStock'] ?? '';
    $manufactureDate = $_POST['manufactureDate'] ?? '';
    $expiryDate      = $_POST['expiryDate'] ?? '';

    if ($medicineName === '' || $categoryId === '' || $supplierId === '' || $quantity === '' || $price === '' || $sellingPrice === '' || $expiryDate === '') {
        $editError = "Please fill in all required fields.";
    } elseif ($manufactureDate !== '' && $expiryDate <= $manufactureDate) {
        $editError = "Expiry date must be after the manufacture date.";
    } else {
        // Prevent duplicate barcode on another medicine
        if ($barcodeVal !== null) {
            $checkStmt = $db->prepare("SELECT medicineId, medicineName FROM medicines WHERE barcode = ? AND medicineId != ? LIMIT 1");
            $checkStmt->execute([$barcodeVal, $editId]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
            if ($existing) {
                $editError = "The barcode '" . htmlspecialchars($barcodeVal) . "' is already assigned to '" . htmlspecialchars($existing['medicineName']) . "'. Duplicate barcodes are not allowed.";
            }
        }

        if ($editError === null) {
            try {
                $stmt = $db->prepare("
                    UPDATE medicines SET
                        medicineName = ?, barcode = ?, categoryId = ?, supplierId = ?,
                        price = ?, sellingPrice = ?, quantity = ?, minStock = ?,
                        manufactureDate = ?, expiryDate = ?
                    WHERE medicineId = ?
                ");
                $stmt->execute([
                    $medicineName, $barcodeVal, $categoryId, $supplierId,
                    $price, $sellingPrice, $quantity, $minStock !== '' ? $minStock : 0,
                    $manufactureDate !== '' ? $manufactureDate : null, $expiryDate,
                    $editId,
                ]);
                header("Location: medicines.php?updated=1");
                exit();
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $editError = "The barcode is already registered to another medicine. Duplicate barcodes are not allowed.";
                } else {
                    $editError = "Could not save changes. Please check the values and try again.";
                }
            }
        }
    }
}

/*handle Delete request*/
if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];
    try {
        $stmt = $db->prepare("DELETE FROM medicines WHERE medicineId = ?");
        $stmt->execute([$deleteId]);
        header("Location: medicines.php?deleted=1");
        exit();
    } catch (PDOException $e) {
        $deleteError = "This medicine can't be deleted because it has sales, purchase, or stock history linked to it.";
    }
}

$search      = trim($_GET['q'] ?? '');
$categoryId  = $_GET['category'] ?? '';
$stockStatus = $_GET['stock_status'] ?? '';   // '', 'low', 'expired', 'ok'
$expiryRange = $_GET['expiry_range'] ?? '';   // '', '30', '90', 'expired'

$where  = [];
$params = [];

if ($search !== '') {
    $where[] = "(m.medicineName LIKE :search OR m.medicineId = :searchId OR m.barcode = :barcodeExact OR m.barcode LIKE :search)";
    $params['search']       = "%$search%";
    $params['barcodeExact'] = $search;
    $params['searchId']     = is_numeric($search) ? (int)$search : 0;
}

if ($categoryId !== '') {
    $where[] = "m.categoryId = :categoryId";
    $params['categoryId'] = $categoryId;
}

if ($stockStatus === 'low') {
    $where[] = "m.quantity <= m.minStock AND m.expiryDate >= CURDATE()";
} elseif ($stockStatus === 'expired') {
    $where[] = "m.expiryDate < CURDATE()";
} elseif ($stockStatus === 'ok') {
    $where[] = "m.quantity > m.minStock AND m.expiryDate >= CURDATE()";
}

if ($expiryRange === '30') {
    $where[] = "m.expiryDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
} elseif ($expiryRange === '90') {
    $where[] = "m.expiryDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 90 DAY)";
} elseif ($expiryRange === 'expired') {
    $where[] = "m.expiryDate < CURDATE()";
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$sql = "SELECT m.medicineId, m.medicineName, m.barcode, m.categoryId, m.supplierId, c.categoryName, s.supplierName, m.quantity, m.minStock, m.price, m.sellingPrice, m.manufactureDate, m.expiryDate
FROM medicines m LEFT JOIN categories c ON c.categoryId = m.categoryId LEFT JOIN suppliers s  ON s.supplierId  = m.supplierId
$whereSql ORDER BY m.medicineId ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$medicines = $stmt->fetchAll();

$categories = $db->query("SELECT categoryId, categoryName FROM categories ORDER BY categoryName")->fetchAll();
$suppliers  = $db->query("SELECT supplierId, supplierName FROM suppliers ORDER BY supplierName")->fetchAll();

function medicineStatus($row) {
    $expired = strtotime($row['expiryDate']) < strtotime('today');
    $low     = $row['quantity'] <= $row['minStock'];

    if ($expired) {
        return ['label' => 'Expired', 'class' => 'badge-expired'];
    }
    $daysLeft = (strtotime($row['expiryDate']) - strtotime('today')) / 86400;
    if ($daysLeft <= 30) {
        return ['label' => 'Expiring Soon', 'class' => 'badge-warn'];
    }
    if ($low) {
        return ['label' => 'Low Stock', 'class' => 'badge-warn'];
    }
    return ['label' => 'OK', 'class' => 'badge-ok'];
}

$pageTitle = 'Medicines';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Medicines</title>
    <link rel="stylesheet" href="includes/style.css">
    <link rel="stylesheet" href="includes/style_dashboard.css">
    <link rel="stylesheet" href="includes/style_medicines.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- html5-qrcode: reads barcodes from the device camera -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        .med-scan-btn {
            background: #1e303d;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        .med-scan-btn:hover {
            background: #2b4457;
        }
        .med-scan-btn i {
            font-size: 16px;
        }
        .barcode-badge {
            background: #eef2ee;
            color: #1f2937;
            padding: 3px 8px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            display: inline-block;
        }
        .scan-result-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px;
            margin-top: 14px;
        }
        .scan-result-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            font-size: 13px;
            margin-top: 10px;
        }
        .scan-result-grid div {
            background: #fff;
            padding: 8px 10px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }
        .scan-result-grid strong {
            display: block;
            color: #64748b;
            font-size: 11px;
            text-transform: uppercase;
        }
    </style>
</head>
<body>

<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>

    <main class="pims-main">
        <?php include 'includes/header.php'; ?>

        <div class="container">

            <?php if (isset($_GET['updated'])): ?>
                <div class="pos-alert pos-alert-success">Medicine updated successfully.</div>
            <?php endif; ?>
            <?php if (isset($_GET['deleted'])): ?>
                <div class="pos-alert pos-alert-success">Medicine deleted successfully.</div>
            <?php endif; ?>
            <?php if (isset($_GET['added'])): ?>
                <div class="pos-alert pos-alert-success">Medicine registered successfully.</div>
            <?php endif; ?>
            <?php if ($deleteError): ?>
                <div class="pos-alert pos-alert-error"><?= htmlspecialchars($deleteError) ?></div>
            <?php endif; ?>

            <!-- Search bar -->
            <form method="GET" class="med-search-bar">
                <svg xmlns="http://www.w3.org/2000/svg" height="24" width="24" viewBox="0 0 640 640"><path d="M544 513L397.2 364.2C417.2 336.3 429.1 302 429.1 265C429.1 171.9 354.4 96.1 262.6 96.1C170.7 96 96 171.8 96 264.9C96 358 170.7 433.8 262.5 433.8C302.3 433.8 338.8 419.6 367.5 395.9L513.5 544L544 513zM262.5 394.8C191.9 394.8 134.4 336.5 134.4 264.9C134.4 193.3 191.9 135 262.5 135C333.1 135 390.6 193.3 390.6 264.9C390.6 336.5 333.2 394.8 262.5 394.8z"/></svg>
                <input type="text" name="q" id="medSearchInput" value="<?= htmlspecialchars($search) ?>" placeholder="Search Medicines by Name, Barcode, or ID...">
                <button type="submit" class="med-search-btn">Search</button>
            </form>

            <!-- Filters & Actions -->
            <form method="GET" class="med-filters-bar">
                <input type="hidden" name="q" value="<?= htmlspecialchars($search) ?>">

                <div class="med-filter-group">
                    <label>Category</label>
                    <select name="category" onchange="this.form.submit()">
                        <option value="">All categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['categoryId'] ?>" <?= $categoryId == $cat['categoryId'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['categoryName']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="med-filter-group">
                    <label>Stock status</label>
                    <select name="stock_status" onchange="this.form.submit()">
                        <option value="">All stocks</option>
                        <option value="ok" <?= $stockStatus === 'ok' ? 'selected' : '' ?>>OK</option>
                        <option value="low" <?= $stockStatus === 'low' ? 'selected' : '' ?>>Low Stock</option>
                        <option value="expired" <?= $stockStatus === 'expired' ? 'selected' : '' ?>>Expired</option>
                    </select>
                </div>

                <div class="med-filter-group">
                    <label>Expiry Range</label>
                    <select name="expiry_range" onchange="this.form.submit()">
                        <option value="">Any expiry</option>
                        <option value="30" <?= $expiryRange === '30' ? 'selected' : '' ?>>Next 30 days</option>
                        <option value="90" <?= $expiryRange === '90' ? 'selected' : '' ?>>Next 90 days</option>
                        <option value="expired" <?= $expiryRange === 'expired' ? 'selected' : '' ?>>Already expired</option>
                    </select>
                </div>

                <div style="margin-left: auto; display: flex; gap: 10px;">
                    <button type="button" class="med-scan-btn" onclick="openMedScanModal()"><i class="fa-solid fa-barcode"></i> Scan Barcode</button>
                    <a href="add_medicine.php" class="med-add-btn">+ Add Medicine</a>
                </div>
            </form>

            <!-- Table -->
            <div class="panel full-panel">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Barcode</th>
                            <th>Category</th>
                            <th>Supplier</th>
                            <th>Stock</th>
                            <th>Buying Price</th>
                            <th>Selling Price</th>
                            <th>Expiry Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($medicines)): ?>
                            <tr><td colspan="11" class="empty-row">No medicines match your filters.</td></tr>
                        <?php else: foreach ($medicines as $m): $status = medicineStatus($m); ?>
                            <tr>
                                <td><?= $m['medicineId'] ?></td>
                                <td><?= htmlspecialchars($m['medicineName']) ?></td>
                                <td>
                                    <?php if (!empty($m['barcode'])): ?>
                                        <span class="barcode-badge"><?= htmlspecialchars($m['barcode']) ?></span>
                                    <?php else: ?>
                                        <span style="color:#9ca3af; font-size:12px;">None</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($m['categoryName'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($m['supplierName'] ?? 'N/A') ?></td>
                                <td><?= (int)$m['quantity'] ?></td>
                                <td>UGX <?= number_format($m['price']) ?></td>
                                <td>UGX <?= number_format($m['sellingPrice']) ?></td>
                                <td><?= date('d M Y', strtotime($m['expiryDate'])) ?></td>
                                <td><span class="badge <?= $status['class'] ?>"><?= $status['label'] ?></span></td>
                                <td class="med-actions">
                                    <a href="#" title="Edit" onclick="openEditModal(<?= $m['medicineId'] ?>); return false;"><i class="fa-solid fa-pen"></i></a>
                                    <a href="medicines.php?delete=<?= $m['medicineId'] ?>" title="Delete" class="med-delete"
                                    onclick="return confirm('Delete <?= htmlspecialchars(addslashes($m['medicineName'])) ?>? This cannot be undone.');"><i class="fa-solid fa-trash" style="color: rgb(251, 0, 0);"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </main>
</div>

<!-- Barcode Scanner & Lookup Modal -->
<div class="modal-overlay" id="medScannerModal">
    <div class="modal-box" style="max-width: 520px;">
        <div class="modal-header">
            <h3><i class="fa-solid fa-barcode"></i> Scan Medicine Barcode</h3>
            <button type="button" class="modal-close" onclick="closeMedScanModal()">&times;</button>
        </div>

        <div style="padding: 16px 24px;">
            <p style="font-size: 13px; color: #64748b; margin-top: 0;">Scan with a USB barcode scanner, camera, or type the barcode number.</p>

            <div class="barcode-input-row">
                <input type="text" id="medScannerInput" placeholder="Scan barcode or type code..." autocomplete="off">
                <button type="button" class="scan-icon-btn" onclick="openMedCameraReader()" title="Scan with camera"><i class="fa-solid fa-camera"></i></button>
                <button type="button" class="med-search-btn" onclick="triggerMedBarcodeLookup()">Lookup</button>
            </div>

            <!-- In-modal camera stream container -->
            <div id="medCameraContainer" style="display: none; margin-top: 14px;">
                <div id="medCameraReader" style="width: 100%;"></div>
                <button type="button" class="modal-cancel-btn" style="width: 100%; margin-top: 8px;" onclick="closeMedCameraReader()">Stop Camera</button>
            </div>

            <!-- Status message -->
            <div id="medScanStatus" style="margin-top: 12px; font-size: 13px; display: none;"></div>

            <!-- Scanned Medicine Information Display -->
            <div id="medScanResultCard" class="scan-result-card" style="display: none;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h4 id="resMedName" style="margin: 0; font-size: 16px; color: #1e303d;">Medicine Name</h4>
                    <span id="resMedStatus" class="badge">OK</span>
                </div>

                <div class="scan-result-grid">
                    <div><strong>Barcode</strong><span id="resMedBarcode">-</span></div>
                    <div><strong>Category</strong><span id="resMedCategory">-</span></div>
                    <div><strong>Stock Quantity</strong><span id="resMedStock">-</span></div>
                    <div><strong>Expiry Date</strong><span id="resMedExpiry">-</span></div>
                    <div><strong>Buying Price</strong>UGX <span id="resMedPrice">-</span></div>
                    <div><strong>Selling Price</strong>UGX <span id="resMedSellingPrice">-</span></div>
                </div>

                <div style="margin-top: 14px; display: flex; gap: 10px;">
                    <button type="button" id="btnOpenEditFromScan" class="modal-save-btn" style="flex: 1;">
                        <i class="fa-solid fa-pen-to-square"></i> Edit / Update Stock
                    </button>
                    <button type="button" class="modal-cancel-btn" onclick="resetMedScanner()">Scan Another</button>
                </div>
            </div>

            <div id="medScanNotFoundAction" style="display: none; margin-top: 14px; text-align: center;">
                <a href="#" id="linkRegisterScanned" class="med-add-btn" style="display: inline-block;">
                    + Register New Medicine with this Barcode
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Edit Medicine Modal -->
<div class="modal-overlay" id="editMedicineOverlay" style="<?= $editError ? 'display:flex;' : '' ?>">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="editModalTitle">Edit Medicine</h3>
            <button type="button" class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>

        <?php if ($editError): ?>
            <div class="pos-alert pos-alert-error" style="margin: 16px 24px 0;"><?= htmlspecialchars($editError) ?></div>
        <?php endif; ?>

        <form method="POST" class="modal-form" id="editMedicineForm">
            <input type="hidden" name="edit_medicine" value="1">
            <input type="hidden" name="medicineId" id="edit_medicineId">

            <div class="form-row">
                <div class="form-group">
                    <label>Medicine Name</label>
                    <input type="text" name="medicineName" id="edit_medicineName" required>
                </div>
                <div class="form-group">
                    <label>Barcode</label>
                    <input type="text" name="barcode" id="edit_barcode" placeholder="Scan or type barcode">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Category</label>
                    <select name="categoryId" id="edit_categoryId" required>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['categoryId'] ?>"><?= htmlspecialchars($cat['categoryName']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Supplier</label>
                    <select name="supplierId" id="edit_supplierId" required>
                        <?php foreach ($suppliers as $sup): ?>
                            <option value="<?= $sup['supplierId'] ?>"><?= htmlspecialchars($sup['supplierName']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Buying Price (UGX)</label>
                    <input type="number" name="price" id="edit_price" min="0" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Selling Price (UGX)</label>
                    <input type="number" name="sellingPrice" id="edit_sellingPrice" min="0" step="0.01" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Quantity / Stock</label>
                    <input type="number" name="quantity" id="edit_quantity" min="0" required>
                </div>
                <div class="form-group">
                    <label>Minimum Stock (reorder level)</label>
                    <input type="number" name="minStock" id="edit_minStock" min="0">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Manufacture Date</label>
                    <input type="date" name="manufactureDate" id="edit_manufactureDate">
                </div>
                <div class="form-group">
                    <label>Expiry Date</label>
                    <input type="date" name="expiryDate" id="edit_expiryDate" required>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="modal-cancel-btn" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="modal-save-btn">Save Medicine</button>
            </div>
        </form>
    </div>
</div>

<script>
    const medicinesData = <?= json_encode($medicines) ?>;
    let medCameraScanner = null;

    function openEditModal(id) {
        let med = medicinesData.find(m => m.medicineId == id);
        if (!med) return;

        populateEditModal(med);
        document.getElementById('editMedicineOverlay').style.display = 'flex';
    }

    function populateEditModal(med) {
        document.getElementById('editModalTitle').textContent = 'Edit ' + med.medicineName;
        document.getElementById('edit_medicineId').value = med.medicineId;
        document.getElementById('edit_medicineName').value = med.medicineName;
        document.getElementById('edit_barcode').value = med.barcode || '';
        document.getElementById('edit_categoryId').value = med.categoryId;
        document.getElementById('edit_supplierId').value = med.supplierId;
        document.getElementById('edit_price').value = med.price;
        document.getElementById('edit_sellingPrice').value = med.sellingPrice;
        document.getElementById('edit_quantity').value = med.quantity;
        document.getElementById('edit_minStock').value = med.minStock;
        document.getElementById('edit_manufactureDate').value = med.manufactureDate || '';
        document.getElementById('edit_expiryDate').value = med.expiryDate;
    }

    function closeEditModal() {
        document.getElementById('editMedicineOverlay').style.display = 'none';
    }

    /* Barcode Scanner Modal Logic */
    const medScanModal = document.getElementById('medScannerModal');
    const medScanInput = document.getElementById('medScannerInput');
    const medScanStatus = document.getElementById('medScanStatus');
    const medScanResultCard = document.getElementById('medScanResultCard');
    const medScanNotFound = document.getElementById('medScanNotFoundAction');

    function openMedScanModal() {
        medScanModal.style.display = 'flex';
        resetMedScanner();
        setTimeout(() => medScanInput.focus(), 100);
    }

    function closeMedScanModal() {
        medScanModal.style.display = 'none';
        closeMedCameraReader();
    }

    function resetMedScanner() {
        medScanInput.value = '';
        medScanStatus.style.display = 'none';
        medScanResultCard.style.display = 'none';
        medScanNotFound.style.display = 'none';
        medScanInput.focus();
    }

    function triggerMedBarcodeLookup() {
        const code = medScanInput.value.trim();
        if (code) {
            lookupMedicineByBarcode(code);
        }
    }

    medScanInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            triggerMedBarcodeLookup();
        }
    });

    async function lookupMedicineByBarcode(barcode) {
        medScanStatus.style.display = 'block';
        medScanStatus.style.color = '#3b82f6';
        medScanStatus.textContent = `Looking up barcode "${barcode}"...`;
        medScanResultCard.style.display = 'none';
        medScanNotFound.style.display = 'none';

        try {
            const res = await fetch(`lookup_barcode.php?barcode=${encodeURIComponent(barcode)}`);
            const data = await res.json();

            if (data.found && data.medicine) {
                const med = data.medicine;
                medScanStatus.style.display = 'block';
                medScanStatus.style.color = '#0e9f6e';
                medScanStatus.textContent = `✅ Medicine found: "${med.medicineName}"`;

                // Display medicine information
                document.getElementById('resMedName').textContent = med.medicineName;
                document.getElementById('resMedBarcode').textContent = med.barcode;
                document.getElementById('resMedCategory').textContent = med.categoryName;
                document.getElementById('resMedStock').textContent = med.quantity + ' units';
                document.getElementById('resMedExpiry').textContent = med.formattedExpiry;
                document.getElementById('resMedPrice').textContent = med.formattedPrice;
                document.getElementById('resMedSellingPrice').textContent = med.formattedSellingPrice;

                const statusBadge = document.getElementById('resMedStatus');
                if (med.isExpired) {
                    statusBadge.textContent = 'Expired';
                    statusBadge.className = 'badge badge-expired';
                } else if (med.isLowStock) {
                    statusBadge.textContent = 'Low Stock';
                    statusBadge.className = 'badge badge-warn';
                } else {
                    statusBadge.textContent = 'OK';
                    statusBadge.className = 'badge badge-ok';
                }

                medScanResultCard.style.display = 'block';

                // Automatically wire Edit button to open Edit modal pre-populated
                document.getElementById('btnOpenEditFromScan').onclick = function() {
                    closeMedScanModal();
                    populateEditModal(med);
                    document.getElementById('editMedicineOverlay').style.display = 'flex';
                };
            } else {
                medScanStatus.style.display = 'block';
                medScanStatus.style.color = '#e02424';
                medScanStatus.textContent = `❌ No medicine found with barcode "${barcode}".`;

                medScanNotFound.style.display = 'block';
                document.getElementById('linkRegisterScanned').href = `add_medicine.php?barcode=${encodeURIComponent(barcode)}`;
            }
        } catch (err) {
            medScanStatus.style.display = 'block';
            medScanStatus.style.color = '#e02424';
            medScanStatus.textContent = '❌ Server communication error.';
        }
    }

    function openMedCameraReader() {
        const container = document.getElementById('medCameraContainer');
        container.style.display = 'block';
        medCameraScanner = new Html5Qrcode("medCameraReader");

        medCameraScanner.start(
            { facingMode: "environment" },
            { fps: 10, qrbox: { width: 250, height: 150 } },
            (decodedText) => {
                medScanInput.value = decodedText;
                closeMedCameraReader();
                lookupMedicineByBarcode(decodedText);
            },
            () => {}
        ).catch(() => {
            medScanStatus.style.display = 'block';
            medScanStatus.style.color = '#e02424';
            medScanStatus.textContent = '❌ Could not access camera';
        });
    }

    function closeMedCameraReader() {
        const container = document.getElementById('medCameraContainer');
        container.style.display = 'none';
        if (medCameraScanner) {
            medCameraScanner.stop().catch(() => {});
            medCameraScanner = null;
        }
    }

    // Global USB barcode scanner detection (keyboard wedge)
    let globalKeyBuffer = '';
    let globalLastKeyTime = 0;
    window.addEventListener('keydown', function(e) {
        const now = Date.now();
        const activeTag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';

        // Don't intercept if user is typing in regular search or edit form inputs
        if (activeTag === 'input' && document.activeElement.id !== 'medScannerInput') {
            return;
        }

        if (now - globalLastKeyTime > 60) {
            globalKeyBuffer = '';
        }
        globalLastKeyTime = now;

        if (e.key === 'Enter') {
            if (globalKeyBuffer.length >= 3) {
                e.preventDefault();
                openMedScanModal();
                medScanInput.value = globalKeyBuffer;
                lookupMedicineByBarcode(globalKeyBuffer);
                globalKeyBuffer = '';
            }
        } else if (e.key.length === 1) {
            globalKeyBuffer += e.key;
        }
    });
</script>

</body>
</html>
