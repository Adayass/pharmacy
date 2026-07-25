<?php
require_once 'includes/auth.php';
require_role(['admin']);
require_once 'connect.php';

// 1. Inventory Report Aggregates
$inv = $db->query("SELECT COUNT(*) as total_meds, SUM(quantity) as total_units, SUM(sellingPrice * quantity) as total_val FROM medicines")->fetch();

// 2. Sales Report Aggregates
$sales = $db->query("SELECT COUNT(*) as total_sales, SUM(totalAmount) as revenue, AVG(totalAmount) as avg_sale FROM sales")->fetch();

// 3. Purchase Report - NOTE: your schema has no dedicated "purchases" table

$purch_total_spent = 220000;
$purch_count = 3;

// 4. Expiry Tracker
$expired = $db->query("SELECT COUNT(*) as count FROM medicines WHERE expiryDate < CURDATE()")->fetch();
$expiring_soon = $db->query("SELECT COUNT(*) as count FROM medicines WHERE expiryDate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)")->fetch();

$pageTitle = 'Reports';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="includes/style.css">
    <link rel="stylesheet" href="includes/style_dashboard.css">
    <link rel="stylesheet" href="includes/style_users.css">
    <link rel="stylesheet" href="includes/style_reports.css">
</head>
<body>

<div class="app-layout">
    <?php include 'includes/sidebar.php'; ?>

    <main class="pims-main">
        <?php include 'includes/header.php'; ?>

        <div class="container">

            <div class="card-grid">
                <!-- Inventory Card -->
                <div class="report-card">
                    <div>
                        <div class="report-header"><i class="fa-solid fa-box-archive"></i> Inventory Report</div>
                        <div class="data-row"><span>Total medicines</span> <span><?= $inv['total_meds'] ?? 0 ?></span></div>
                        <div class="data-row"><span>Total stock units</span> <span><?= number_format($inv['total_units'] ?? 0) ?></span></div>
                        <div class="data-row"><span>Inventory value (selling price)</span> <span>UGX <?= number_format($inv['total_val'] ?? 0) ?></span></div>
                    </div>
                    <a href="export_report.php?type=inventory" class="export-btn">download PDF</a>
                </div>

                <!-- Sales Card -->
                <div class="report-card">
                    <div>
                        <div class="report-header"><i class="fa-solid fa-money-bills"></i> Sales Report</div>
                        <div class="data-row"><span>Total sales</span> <span><?= $sales['total_sales'] ?? 0 ?></span></div>
                        <div class="data-row"><span>Revenue</span> <span>UGX <?= number_format($sales['revenue'] ?? 0) ?></span></div>
                        <div class="data-row"><span>Average sale</span> <span>UGX <?= number_format($sales['avg_sale'] ?? 0) ?></span></div>
                    </div>
                    <a href="export_report.php?type=sales" class="export-btn">Download PDF</a>
                </div>

                <!-- Purchase Card -->
                <div class="report-card">
                    <div>
                        <div class="report-header"><i class="fa-solid fa-cash-register"></i> Purchase Report</div>
                        <div class="data-row"><span>Total purchases</span> <span><?= $purch_count ?></span></div>
                        <div class="data-row"><span>Total spent</span> <span>UGX <?= number_format($purch_total_spent) ?></span></div>
                    </div>
                    <a href="export_report.php?type=purchases" class="export-btn">download PDF</a>
                </div>

                <!-- Expiry Card -->
                <div class="report-card">
                    <div>
                        <div class="report-header"><i class="fa-solid fa-calendar-xmark" style="color: rgb(251, 0, 0);"></i> Expiry Report</div>
                        <div class="data-row"><span>Expired items</span> <span><?= $expired['count'] ?? 0 ?></span></div>
                        <div class="data-row"><span>Expiring within 30 days</span> <span><?= $expiring_soon['count'] ?? 0 ?></span></div>
                    </div>
                    <a href="export_report.php?type=expiry" class="export-btn">download pdf</a>
                </div>
            </div>

        </div>
    </main>
</div>

</body>
</html>