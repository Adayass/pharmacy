<?php
require_once 'includes/auth.php';
require_once 'connect.php';
// $db is the PDO connection from connect.php (equivalent of $conn in the original)

// Pull data relational records joining Sales onto Cashier identities
$query = "SELECT s.saleId, s.saleDate, u.fullName as cashier, s.paymentMethod, s.totalAmount FROM sales s JOIN users u ON s.userId = u.userId ORDER BY s.saleDate DESC";
$result = $db->query($query)->fetchAll();

$pageTitle = 'Sales History';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales History</title>
    <link rel="stylesheet" href="includes/style.css">
    <link rel="stylesheet" href="includes/style_dashboard.css">
</head>
<body>

<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>

    <main class="pims-main">
        <?php include 'includes/header.php'; ?>

        <div class="container">

            <div class="panel full-panel">
                <table>
                    <thead>
                        <tr>
                            <th>Receipt No</th>
                            <th>Date</th>
                            <th>Cashier</th>
                            <th>Payment</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($result) > 0): ?>
                            <?php foreach ($result as $row): ?>
                                <tr>
                                    <td>RCT-<?= str_pad($row['saleId'], 4, '0', STR_PAD_LEFT) ?></td>
                                    <td><?= date('d M Y', strtotime($row['saleDate'])) ?></td>
                                    <td><?= htmlspecialchars($row['cashier']) ?></td>
                                    <td><?= htmlspecialchars($row['paymentMethod']) ?></td>
                                    <td>UGX <?= number_format($row['totalAmount']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="empty-row">No transactions recorded yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
<?php require_once 'includes/footer.php'; ?>
        </div>
    </main>
</div>

</body>
</html>