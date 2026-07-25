<?php require_once 'includes/auth.php'; ?>
<?php
require_once 'connect.php';

//Total medicines
$totalMedicines = $db->query("SELECT COUNT(*) FROM medicines")->fetchColumn();

//Low stock
$lowStockCount = $db->query("SELECT COUNT(*) FROM medicines WHERE quantity <= minStock AND expiryDate >= CURDATE()")->fetchColumn();

//Expired medicines
$expiredCount = $db->query("SELECT COUNT(*) FROM medicines WHERE expiryDate < CURDATE()")->fetchColumn();

//Suppliers
$supplierCount = $db->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();

//Today's sales total
$todaySales = $db->query("SELECT COALESCE(SUM(totalAmount),0) FROM sales WHERE DATE(saleDate) = CURDATE()")->fetchColumn();

//This month's sales total
$monthSales = $db->query("SELECT COALESCE(SUM(totalAmount),0) FROM sales WHERE MONTH(saleDate) = MONTH(CURDATE()) AND YEAR(saleDate) = YEAR(CURDATE())")->fetchColumn();

// Recent sales (latest 5)
$recentSales = $db->query("SELECT s.saleId, u.fullName AS cashierName, s.saleDate, s.totalAmount FROM sales s LEFT JOIN users u ON u.userId = s.userId ORDER BY s.saleDate DESC, s.saleId DESC LIMIT 5")->fetchAll();

// Medicines expiring soon (next 30 days)
$expiringSoon = $db->query("SELECT medicineName, expiryDate, DATEDIFF(expiryDate, CURDATE()) AS daysLeft FROM medicines WHERE expiryDate >= CURDATE() AND expiryDate <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) ORDER BY expiryDate ASC LIMIT 5")->fetchAll();

// Low stock list
$lowStockList = $db->query("SELECT m.medicineName, c.categoryName, s.supplierName, m.quantity, m.minStock, m.expiryDate FROM medicines m
LEFT JOIN categories c ON c.categoryId = m.categoryId LEFT JOIN suppliers s ON s.supplierId = m.supplierId WHERE m.quantity <= m.minStock OR m.expiryDate < CURDATE()
ORDER BY m.quantity ASC LIMIT 6")->fetchAll();

function stockStatus($row)
{
    if (strtotime($row['expiryDate']) < strtotime('today')) {
        return ['label' => 'Expired', 'class' => 'badge-expired'];
    }
    return ['label' => 'Stock low', 'class' => 'badge-low'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="includes/style.css">
    <link rel="stylesheet" href="includes/style_dashboard.css">
</head>
<body>

<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>

    <main class="pims-main">
        <?php include 'includes/header.php'; ?>

        <div class="container">

            <!-- Stat Cards -->
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-icon icon-total"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                            <path d="M128 176C128 149.5 149.5 128 176 128C202.5 128 224 149.5 224 176L224 288L128 288L128 176zM64 176L64 464C64 525.9 114.1 576 176 576C237.9 576 288 525.9 288 464L288 358.2L404.3 527.7C439.8 579.4 509.6 592 560.3 555.8C611 519.6 623.3 448.3 587.8 396.6L459.3 209.3C423.8 157.6 354 145 303.3 181.2C297.7 185.2 292.6 189.6 288 194.3L288 176C288 114.1 237.9 64 176 64C114.1 64 64 114.1 64 176zM328.6 304.2C312.6 280.9 318.6 248.9 340.5 233.2C361.7 218.1 391 222.9 406.5 245.4L473.5 343L393.6 398.9L328.6 304.1z" />
                        </svg></div>
                    <div>
                        <div class="stat-value"><?= (int)$totalMedicines ?></div>
                        <div class="stat-label">Total Medicines</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-warn"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                            <path opacity="0.8" fill="rgb(251, 45, 0)" d="M320 64C334.7 64 348.2 72.1 355.2 85L571.2 485C577.9 497.4 577.6 512.4 570.4 524.5C563.2 536.6 550.1 544 536 544L104 544C89.9 544 76.8 536.6 69.6 524.5C62.4 512.4 62.1 497.4 68.8 485L284.8 85C291.8 72.1 305.3 64 320 64zM320 416C302.3 416 288 430.3 288 448C288 465.7 302.3 480 320 480C337.7 480 352 465.7 352 448C352 430.3 337.7 416 320 416zM320 224C301.8 224 287.3 239.5 288.6 257.7L296 361.7C296.9 374.2 307.4 384 319.9 384C332.5 384 342.9 374.3 343.8 361.7L351.2 257.7C352.5 239.5 338.1 224 319.8 224z" />
                        </svg></div>
                    <div>
                        <div class="stat-value"><?= (int)$lowStockCount ?></div>
                        <div class="stat-label">Low Stock Medicines</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-danger"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                            <path opacity="0.8" fill="rgb(251, 0, 0)" d="M224 64C241.7 64 256 78.3 256 96L256 128L384 128L384 96C384 78.3 398.3 64 416 64C433.7 64 448 78.3 448 96L448 128L480 128C515.3 128 544 156.7 544 192L544 480C544 515.3 515.3 544 480 544L160 544C124.7 544 96 515.3 96 480L96 192C96 156.7 124.7 128 160 128L192 128L192 96C192 78.3 206.3 64 224 64zM387.9 284.1C378.5 274.7 363.3 274.7 354 284.1L320.1 318L286.2 284.1C276.8 274.7 261.6 274.7 252.3 284.1C243 293.5 242.9 308.7 252.3 318L286.2 351.9L252.3 385.8C242.9 395.2 242.9 410.4 252.3 419.7C261.7 429 276.9 429.1 286.2 419.7L320.1 385.8L354 419.7C363.4 429.1 378.6 429.1 387.9 419.7C397.2 410.3 397.3 395.1 387.9 385.8L354 351.9L387.9 318C397.3 308.6 397.3 293.4 387.9 284.1z" />
                        </svg></div>
                    <div>
                        <div class="stat-value"><?= (int)$expiredCount ?></div>
                        <div class="stat-label">Expired Medicines</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-blue"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                            <path d="M96 144C87.2 144 80 151.2 80 160L80 448C80 456.8 87.2 464 96 464L99.3 464C109.7 427.1 143.7 400 184 400C224.3 400 258.2 427.1 268.7 464L371.3 464C376.2 446.6 386.4 431.3 400 420.1L400 160C400 151.2 392.8 144 384 144L96 144zM99.3 512L96 512C60.7 512 32 483.3 32 448L32 160C32 124.7 60.7 96 96 96L384 96C419.3 96 448 124.7 448 160L448 192L503.4 192C520.4 192 536.7 198.7 548.7 210.7L589.3 251.3C601.3 263.3 608 279.6 608 296.6L608 448C608 483.3 579.3 512 544 512L540.7 512C530.3 548.9 496.3 576 456 576C415.7 576 381.8 548.9 371.3 512L268.7 512C258.3 548.9 224.3 576 184 576C143.7 576 109.8 548.9 99.3 512zM448 320L560 320L560 296.6C560 292.4 558.3 288.3 555.3 285.3L514.7 244.7C511.7 241.7 507.6 240 503.4 240L448 240L448 320zM448 368L448 400.4C450.6 400.2 453.3 400 456 400C496.3 400 530.2 427.1 540.7 464L544 464C552.8 464 560 456.8 560 448L560 368L448 368zM184 528C206.1 528 224 510.1 224 488C224 465.9 206.1 448 184 448C161.9 448 144 465.9 144 488C144 510.1 161.9 528 184 528zM456 528C478.1 528 496 510.1 496 488C496 465.9 478.1 448 456 448C433.9 448 416 465.9 416 488C416 510.1 433.9 528 456 528z" />
                        </svg>
                    </div>
                    <div>
                        <div class="stat-value"><?= (int)$supplierCount ?></div>
                        <div class="stat-label">Suppliers</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-green"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                            <path d="M64 483.6L64 173.5C64 150.3 88.1 134.9 110.3 141.5C198 167.7 260 147 322.4 126.2C386.9 104.7 451.8 83.1 545.7 113.1C564.2 119 576 136.9 576 156.4L576 466.5C576 489.7 551.9 505.1 529.8 498.5C442.1 472.3 380 493 317.7 513.8C253.2 535.3 188.3 556.9 94.4 526.9C75.9 521 64.1 503.1 64.1 483.6zM400 320C400 267 364.2 224 320 224C275.8 224 240 267 240 320C240 373 275.8 416 320 416C364.2 416 400 373 400 320zM184 477.6C188.4 477.6 191.9 473.8 191.2 469.5C186.6 441.7 164.2 420 136 416.5C131.6 416 128 419.6 128 424L128 463.9C128 467.5 130.4 470.7 134 471.6C151.9 475.8 168.3 477.7 184 477.7zM502.5 426.5C507.5 427.3 512 423.5 512 418.5L512 375.9C512 371.5 508.4 367.8 504 368.4C478.8 371.5 458.1 389.3 450.8 413C449.4 417.7 453.1 422.1 458 422.2C472.2 422.6 487 423.9 502.4 426.5zM512 216L512 176.1C512 172.5 509.5 169.3 506 168.4C488.1 164.2 471.7 162.3 456 162.3C451.6 162.3 448.1 166.1 448.8 170.4C453.4 198.2 475.8 219.9 504 223.4C508.4 223.9 512 220.3 512 215.9zM189.2 226.9C190.6 222.2 186.9 217.8 182 217.7C167.8 217.3 153 216 137.6 213.4C132.6 212.6 128.1 216.4 128.1 221.4L128 264C128 268.4 131.6 272.1 136 271.5C161.2 268.4 181.9 250.6 189.2 226.9z" />
                        </svg></div>
                    <div>
                        <div class="stat-value">UGX <?= number_format($todaySales) ?></div>
                        <div class="stat-label">Today's Sales</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-blue"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640">
                            <path d="M192 96C156.7 96 128 124.7 128 160L128 384C128 419.3 156.7 448 192 448L544 448C579.3 448 608 419.3 608 384L608 160C608 124.7 579.3 96 544 96L192 96zM368 192C412.2 192 448 227.8 448 272C448 316.2 412.2 352 368 352C323.8 352 288 316.2 288 272C288 227.8 323.8 192 368 192zM192 216L192 168C192 163.6 195.6 160 200 160L248 160C252.4 160 256.1 163.6 255.5 168C251.9 197 228.9 219.9 200 223.5C195.6 224 192 220.4 192 216zM192 328C192 323.6 195.6 319.9 200 320.5C229 324.1 251.9 347.1 255.5 376C256 380.4 252.4 384 248 384L200 384C195.6 384 192 380.4 192 376L192 328zM536 223.5C507 219.9 484.1 196.9 480.5 168C480 163.6 483.6 160 488 160L536 160C540.4 160 544 163.6 544 168L544 216C544 220.4 540.4 224.1 536 223.5zM544 328L544 376C544 380.4 540.4 384 536 384L488 384C483.6 384 479.9 380.4 480.5 376C484.1 347 507.1 324.1 536 320.5C540.4 320 544 323.6 544 328zM80 216C80 202.7 69.3 192 56 192C42.7 192 32 202.7 32 216L32 480C32 515.3 60.7 544 96 544L488 544C501.3 544 512 533.3 512 520C512 506.7 501.3 496 488 496L96 496C87.2 496 80 488.8 80 480L80 216z" />
                        </svg></div>
                    <div>
                        <div class="stat-value">UGX <?= number_format($monthSales) ?></div>
                        <div class="stat-label">Monthly Sales</div>
                    </div>
                </div>
            </div>

            <!-- Recent Sales & Expiring Soon -->
            <div class="panels-row">

                <div class="panel">
                    <div class="panel-header">
                        <span class="panel-title">Recent Sales</span>
                        <a href="sales_history.php" class="view-all">View all</a>
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Receipt</th>
                                <th>Cashier</th>
                                <th>Date</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentSales)): ?>
                                <tr>
                                    <td colspan="4" class="empty-row">No sales recorded yet.</td>
                                </tr>
                                <?php else: foreach ($recentSales as $s): ?>
                                    <tr>
                                        <td>RCT-<?= str_pad($s['saleId'], 4, '0', STR_PAD_LEFT) ?></td>
                                        <td><?= htmlspecialchars($s['cashierName'] ?? 'Unknown') ?></td>
                                        <td><?= date('d M Y', strtotime($s['saleDate'])) ?></td>
                                        <td>UGX <?= number_format($s['totalAmount']) ?></td>
                                    </tr>
                            <?php endforeach;
                            endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <span class="panel-title">Medicines Expiring Soon</span>
                        <!-- <a href="expiring_medicines.php" class="view-all">View all</a> -->
                    </div>
                    <table>
                        <thead>
                            <tr>
                                <th>Medicine</th>
                                <th>Expiry</th>
                                <th>Days Left</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($expiringSoon)): ?>
                                <tr>
                                    <td colspan="3" class="empty-row">Nothing expiring in the next 30 days.</td>
                                </tr>
                                <?php else: foreach ($expiringSoon as $m): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($m['medicineName']) ?></td>
                                        <td><?= date('d M Y', strtotime($m['expiryDate'])) ?></td>
                                        <td><?= (int)$m['daysLeft'] ?>d</td>
                                    </tr>
                            <?php endforeach;
                            endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>

            <!-- Low Stock List -->
            <div class="panel full-panel">
                <div class="panel-header">
                    <span class="panel-title">Low Stock List</span>
                    <a href="low_stock.php" class="view-all">View all</a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Medicine</th>
                            <th>Category</th>
                            <th>Supplier</th>
                            <th>Stock</th>
                            <th>Min. Stock</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lowStockList)): ?>
                            <tr>
                                <td colspan="6" class="empty-row">No low stock or expired medicines.</td>
                            </tr>
                            <?php else: foreach ($lowStockList as $item): $status = stockStatus($item); ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['medicineName']) ?></td>
                                    <td><?= htmlspecialchars($item['categoryName'] ?? 'N/A') ?></td>
                                    <td><?= htmlspecialchars($item['supplierName'] ?? 'N/A') ?></td>
                                    <td><?= (int)$item['quantity'] ?></td>
                                    <td><?= (int)$item['minStock'] ?></td>
                                    <td><span class="badge <?= $status['class'] ?>"><?= $status['label'] ?></span></td>
                                </tr>
                        <?php endforeach;
                        endif; ?>
                    </tbody>
                </table>
            </div>
<?php require_once 'includes/footer.php'; ?>
        </div><!-- /.container -->
    </main>
</div><!-- /.app-layout -->

</body>
</html>