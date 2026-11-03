<?php
require_once 'includes/auth.php';
require_once 'connect.php';

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

$sql = "SELECT m.medicineId, m.medicineName, m.barcode, c.categoryName, m.quantity, m.minStock, m.expiryDate
    FROM medicines m LEFT JOIN categories c ON c.categoryId = m.categoryId
    $whereSql
    ORDER BY m.medicineName ASC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$medicines = $stmt->fetchAll();

$categories = $db->query("SELECT categoryId, categoryName FROM categories ORDER BY categoryName")->fetchAll();


function invStatus($row)
{
    $expired = strtotime($row['expiryDate']) < strtotime('today');
    if ($expired) {
        return ['label' => 'Expired', 'class' => 'badge-expired'];
    }

    $daysLeft = (strtotime($row['expiryDate']) - strtotime('today')) / 86400;
    if ($daysLeft <= 30) {
        return ['label' => 'Expiring Soon', 'class' => 'badge-warn'];
    }

    if ($row['quantity'] <= $row['minStock']) {
        return ['label' => 'Low Stock', 'class' => 'badge-warn'];
    }

    return ['label' => 'OK', 'class' => 'badge-ok'];
}

$pageTitle = 'Inventory';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory</title>
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

                <!-- Search bar -->
                <form method="GET" class="med-search-bar">
                    <span class="med-search-icon"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search Medicines by Name, Barcode, ID">
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

                    <a href="inventory.php" class="reset-filters">Reset Filters</a>
                </form>

                <!-- Table -->
                <div class="panel full-panel">
                    <table>
                        <thead>
                            <tr>
                                <th>Medicine</th>
                                <th>Barcode</th>
                                <th>Category</th>
                                <th>Current Stock</th>
                                <th>Minimum Stock</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($medicines)): ?>
                                <tr>
                                    <td colspan="6" class="empty-row">No medicines match your filters.</td>
                                </tr>
                                <?php else: foreach ($medicines as $m): $status = invStatus($m); ?>
                                    <tr>
                                        <td><?= htmlspecialchars($m['medicineName']) ?></td>
                                        <td>
                                            <?php if (!empty($m['barcode'])): ?>
                                                <code style="background:#eef2ee; padding:2px 6px; border-radius:4px; font-size:12px;"><?= htmlspecialchars($m['barcode']) ?></code>
                                            <?php else: ?>
                                                <span style="color:#9ca3af; font-size:12px;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($m['categoryName'] ?? 'N/A') ?></td>
                                        <td><?= (int)$m['quantity'] ?></td>
                                        <td><?= (int)$m['minStock'] ?></td>
                                        <td><span class="badge <?= $status['class'] ?>"><?= $status['label'] ?></span></td>
                                    </tr>
                            <?php endforeach;
                            endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </main>
    </div>

</body>

</html>
