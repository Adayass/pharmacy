<?php
require_once 'includes/auth.php';
require_role(['admin']); // Suppliers is admin-only
require_once 'connect.php';

$addError = null;
$addSuccess = false;
$deleteError = null;
$deleteSuccess = false;

/* ---------------------------------------------------------
   Handle Add Supplier submission
   --------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_supplier'])) {
    $supplierName =trim($_POST['supplierName'] ?? '');
    $contactName =trim($_POST['contactName'] ?? '');
    $phone =trim($_POST['phone'] ?? '');
    $email =trim($_POST['email'] ?? '');
    $address =trim($_POST['address'] ?? '');

    if ($supplierName === '') {
        $addError = "Supplier name is required.";
    } else {
        try {
            $stmt = $db->prepare("INSERT INTO suppliers (supplierName, contactName, phone, email, address) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$supplierName, $contactName, $phone, $email, $address]);
            $addSuccess = true;
        } catch (PDOException $e) {
            $addError = "Could not save supplier. Please check the values and try again.";
        }
    }
}

/* ---------------------------------------------------------
   Handle delete request
   --------------------------------------------------------- */
if (isset($_GET['delete'])) {
    $deleteId = (int)$_GET['delete'];

    try {
        $stmt = $db->prepare("DELETE FROM suppliers WHERE supplierId = ?");
        $stmt->execute([$deleteId]);
        $deleteSuccess = true;
    } catch (PDOException $e) {
        // suppliers.supplierId is referenced by medicines.supplierId with
        // no cascade rule, so a supplier tied to existing medicines can't
        // be deleted without those medicines being reassigned first.
        $deleteError = "This supplier can't be deleted because medicines are still linked to them.";
    }
}

/* ---------------------------------------------------------
   Fetch suppliers with their medicine count
   --------------------------------------------------------- */
$suppliers = $db->query("
    SELECT s.supplierId, s.supplierName, s.contactName, s.phone, s.email, s.address,
           COUNT(m.medicineId) AS medicineCount
    FROM suppliers s
    LEFT JOIN medicines m ON m.supplierId = s.supplierId
    GROUP BY s.supplierId
    ORDER BY s.supplierName ASC
")->fetchAll();

$totalSuppliers = count($suppliers);

$pageTitle = 'Supplier';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suppliers</title>
    <link rel="stylesheet" href="includes/style.css">
    <link rel="stylesheet" href="includes/style_dashboard.css">
    <link rel="stylesheet" href="includes/style_suppliers.css">
</head>
<body>

<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>

    <main class="pims-main">
        <?php include 'includes/header.php'; ?>

        <div class="container">

            <?php if ($deleteSuccess): ?>
                <div class="pos-alert pos-alert-success">Supplier deleted successfully.</div>
            <?php endif; ?>
            <?php if ($deleteError): ?>
                <div class="pos-alert pos-alert-error"><?= htmlspecialchars($deleteError) ?></div>
            <?php endif; ?>
            <?php if ($addSuccess): ?>
                <div class="pos-alert pos-alert-success">Supplier added successfully.</div>
            <?php endif; ?>

            <div class="sup-toolbar">
                <div class="sup-count"><?= $totalSuppliers ?> supplier(s) on file</div>
                <button type="button" class="sup-add-btn" onclick="openAddSupplierModal()">+ Add Supplier</button>
            </div>

            <div class="panel full-panel">
                <table>
                    <thead>
                        <tr>
                            <th>Supplier</th>
                            <th>Contact Person</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Address</th>
                            <th>Medicines</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($suppliers)): ?>
                            <tr><td colspan="7" class="empty-row">No suppliers on file yet.</td></tr>
                        <?php else: foreach ($suppliers as $s): ?>
                            <tr>
                                <td><?= htmlspecialchars($s['supplierName']) ?></td>
                                <td><?= htmlspecialchars($s['contactName'] ?? '') ?></td>
                                <td><?= htmlspecialchars($s['phone'] ?? '') ?></td>
                                <td><a href="mailto:<?= htmlspecialchars($s['email']) ?>"><?= htmlspecialchars($s['email'] ?? '') ?></a></td>
                                <td><?= htmlspecialchars($s['address'] ?? '') ?></td>
                                <td><?= (int)$s['medicineCount'] ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </main>
</div>

<!-- Add Supplier Modal -->
<div class="modal-overlay" id="addSupplierOverlay" style="<?= $addError ? 'display:flex;' : '' ?>">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Add Supplier</h3>
            <button type="button" class="modal-close" onclick="closeAddSupplierModal()">&times;</button>
        </div>

        <?php if ($addError): ?>
            <div class="pos-alert pos-alert-error" style="margin: 16px 24px 0;"><?= htmlspecialchars($addError) ?></div>
        <?php endif; ?>

        <form method="POST" class="modal-form">
            <input type="hidden" name="add_supplier" value="1">

            <div class="form-group">
                <label>Supplier Name</label>
                <input type="text" name="supplierName" placeholder="entreprise name" value="<?= htmlspecialchars($_POST['supplierName'] ?? '') ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Contact Person</label>
                    <input type="text" name="contactName" placeholder="name of the deliver guy " value="<?= htmlspecialchars($_POST['contactName'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" placeholder="Phone number" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" placeholder="address email " value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Address</label>
                <input type="text" name="address" placeholder="the company's address" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
            </div>

            <div class="modal-footer">
                <button type="button" class="modal-cancel-btn" onclick="closeAddSupplierModal()">Cancel</button>
                <button type="submit" class="modal-save-btn">Save Supplier</button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddSupplierModal() {
    document.getElementById('addSupplierOverlay').style.display = 'flex';
}
function closeAddSupplierModal() {
    document.getElementById('addSupplierOverlay').style.display = 'none';
}
</script>

</body>
</html>