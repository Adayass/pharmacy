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
        try {
            $stmt = $db->prepare("
                UPDATE medicines SET
                    medicineName = ?, categoryId = ?, supplierId = ?,
                    price = ?, sellingPrice = ?, quantity = ?, minStock = ?,
                    manufactureDate = ?, expiryDate = ?
                WHERE medicineId = ?
            ");
            $stmt->execute([
                $medicineName, $categoryId, $supplierId,
                $price, $sellingPrice, $quantity, $minStock !== '' ? $minStock : 0,
                $manufactureDate !== '' ? $manufactureDate : null, $expiryDate,
                $editId,
            ]);
            header("Location: medicines.php?updated=1");
            exit();
        } catch (PDOException $e) {
            $editError = "Could not save changes. Please check the values and try again.";
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
    $where[] = "(m.medicineName LIKE :search OR m.medicineId = :searchId)";
    $params['search']   = "%$search%";
    $params['searchId'] = is_numeric($search) ? (int)$search : 0;
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

$sql = "SELECT m.medicineId, m.medicineName, m.categoryId, m.supplierId, c.categoryName, s.supplierName, m.quantity, m.minStock, m.price, m.sellingPrice, m.manufactureDate, m.expiryDate
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
            <?php if ($deleteError): ?>
                <div class="pos-alert pos-alert-error"><?= htmlspecialchars($deleteError) ?></div>
            <?php endif; ?>

            <!-- Search bar -->
            <form method="GET" class="med-search-bar">
                <svg xmlns="http://www.w3.org/2000/svg" height="24" width="24" viewBox="0 0 640 640"><path d="M544 513L397.2 364.2C417.2 336.3 429.1 302 429.1 265C429.1 171.9 354.4 96.1 262.6 96.1C170.7 96 96 171.8 96 264.9C96 358 170.7 433.8 262.5 433.8C302.3 433.8 338.8 419.6 367.5 395.9L513.5 544L544 513zM262.5 394.8C191.9 394.8 134.4 336.5 134.4 264.9C134.4 193.3 191.9 135 262.5 135C333.1 135 390.6 193.3 390.6 264.9C390.6 336.5 333.2 394.8 262.5 394.8z"/></svg>
                <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search Medicines by Name, ID">
                <button type="submit" class="med-search-btn">Search</button>
            </form>

            <!-- Filters -->
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

                <a href="add_medicine.php" class="med-add-btn">+ Add Medicine</a>
            </form>

            <!-- Table -->
            <div class="panel full-panel">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
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
                            <tr><td colspan="10" class="empty-row">No medicines match your filters.</td></tr>
                        <?php else: foreach ($medicines as $m): $status = medicineStatus($m); ?>
                            <tr>
                                <td><?= $m['medicineId'] ?></td>
                                <td><?= htmlspecialchars($m['medicineName']) ?></td>
                                <td><?= htmlspecialchars($m['categoryName'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($m['supplierName'] ?? 'N/A') ?></td>
                                <td><?= (int)$m['quantity'] ?></td>
                                <td>UGX <?= number_format($m['price']) ?></td>
                                <td>UGX <?= number_format($m['sellingPrice']) ?></td>
                                <td><?= date('d M Y', strtotime($m['expiryDate'])) ?></td>
                                <td><span class="badge <?= $status['class'] ?>"><?= $status['label'] ?></span></td>
                                <td class="med-actions">
                                    <a href="#" title="Edit" onclick="openEditModal(<?= $m['medicineId'] ?>); return false;"><i class="fa-solid fa-pen"></i></a>
                                    <a href="medicines.php?delete=<?= $m['medicineId'] ?>"title="Delete" class="med-delete"
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

<!--edit medicine fonction-->
<div class="modal-overlay" id="editMedicineOverlay" style="<?= $editError ? 'display:flex;' : '' ?>">
    <div class="modal-box">
        <div class="modal-header">
            <h3 id="editModalTitle">Edit Medicine</h3>
            <button type="button" class="modal-close" onclick="closeEditModal()">x</button>
        </div>

        <?php if ($editError): ?>
            <div class="pos-alert pos-alert-error" style="margin: 16px 24px 0;"><?= htmlspecialchars($editError) ?></div>
        <?php endif; ?>

        <form method="POST" class="modal-form" id="editMedicineForm">
            <input type="hidden" name="edit_medicine" value="1">
            <input type="hidden" name="medicineId" id="edit_medicineId">

            <div class="form-group">
                <label>Medicine Name</label>
                <input type="text" name="medicineName" id="edit_medicineName" required>
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
    // All medicine data needed for editing, embedded here so the modal
    // opens instantly without an extra AJAX request per click.
    const medicinesData = <?= json_encode($medicines) ?>;

    function openEditModal(id) {
        const med = medicinesData.find(m => m.medicineId == id);
        if (!med) return;

        document.getElementById('editModalTitle').textContent = 'Edit ' + med.medicineName;
        document.getElementById('edit_medicineId').value = med.medicineId;
        document.getElementById('edit_medicineName').value = med.medicineName;
        document.getElementById('edit_categoryId').value = med.categoryId;
        document.getElementById('edit_supplierId').value = med.supplierId;
        document.getElementById('edit_price').value = med.price;
        document.getElementById('edit_sellingPrice').value = med.sellingPrice;
        document.getElementById('edit_quantity').value = med.quantity;
        document.getElementById('edit_minStock').value = med.minStock;
        document.getElementById('edit_manufactureDate').value = med.manufactureDate || '';
        document.getElementById('edit_expiryDate').value = med.expiryDate;

        document.getElementById('editMedicineOverlay').style.display = 'flex';
    }

    function closeEditModal() {
        document.getElementById('editMedicineOverlay').style.display = 'none';
    }
</script>

</body>
</html>