<?php
require_once 'includes/auth.php';
require_once 'connect.php';

// Fetch all available medicines for grid rendering
$meds = $db->query("SELECT * FROM medicines WHERE quantity > 0 AND expiryDate >= CURDATE()")->fetchAll();
$pageTitle = 'New Sale';
$cashierName = $_SESSION['fullName'] ?? $_SESSION['userName'] ?? 'Unknown';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Sale</title>
    <link rel="stylesheet" href="includes/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="includes/style_new_sale.css">
    <link rel="stylesheet" href="includes/header.css">
    <link rel="stylesheet" href="includes/dashboard.css">
    <link rel="stylesheet" href="includes/style_receipt.css">
    <!-- html5-qrcode: camera barcode scanning -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        .pos-scanner-box {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 16px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            margin-bottom: 12px;
        }
        .pos-scanner-row {
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
        }
        .pos-scanner-icon {
            font-size: 20px;
            color: #2f6f4f;
        }
        .pos-scanner-row input {
            flex: 1;
            padding: 10px 14px;
            border-radius: 8px;
            border: 1px solid #cbd5e0;
            font-size: 14px;
            outline: none;
        }
        .pos-scanner-row input:focus {
            border-color: #2f6f4f;
            box-shadow: 0 0 0 2px rgba(47, 111, 79, 0.15);
        }
        .pos-cam-btn {
            background: #2f6f4f;
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 10px 16px;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }
        .pos-cam-btn:hover {
            background: #245a3f;
        }
        .pos-feedback-card {
            margin-top: 10px;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            display: none;
        }
        .pos-feedback-card.success {
            background: #edf7ed;
            border: 1px solid #b7eb8f;
            color: #1e4620;
        }
        .pos-feedback-card.error {
            background: #fdf2f2;
            border: 1px solid #f8b4b4;
            color: #9b1c1c;
        }
        .pos-feedback-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 6px;
            margin-top: 6px;
            font-size: 12px;
        }
        .pos-feedback-details span {
            background: rgba(255, 255, 255, 0.7);
            padding: 4px 8px;
            border-radius: 4px;
            display: block;
        }
    </style>
</head>

<body>

    <div class="app-layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="pims-main">
            <?php include 'includes/header.php'; ?>

            <div class="pos-container">
                <div class="inventory-pane">

                    <!-- Barcode Scanner Input Row -->
                    <div class="pos-scanner-box">
                        <div class="pos-scanner-row">
                            <i class="fa-solid fa-barcode pos-scanner-icon" title="Barcode Scanner Ready"></i>
                            <input type="text" id="posBarcodeField" placeholder="Scan medicine barcode with scanner (or type & press Enter)..." autocomplete="off" autofocus>
                            <button type="button" class="pos-cam-btn" onclick="openPosCameraScanner()" title="Scan barcode with camera">
                                <i class="fa-solid fa-camera"></i> Camera
                            </button>
                        </div>
                        <div id="posFeedbackCard" class="pos-feedback-card">
                            <div id="posFeedbackText" style="font-weight: 600;"></div>
                            <div id="posFeedbackDetails" class="pos-feedback-details" style="display: none;"></div>
                        </div>
                    </div>

                    <!-- Search Filter Box -->
                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" id="searchBar" placeholder="Search Medicines by Name, Barcode, ID..." onkeyup="filterMedicines()">
                    </div>

                    <div class="med-grid" id="medicineGrid">
                        <?php foreach ($meds as $row): ?>
                            <div class="med-card" data-barcode="<?= htmlspecialchars($row['barcode'] ?? '') ?>" onclick="addToCart(<?= $row['medicineId'] ?>, '<?= addslashes($row['medicineName']) ?>', <?= $row['sellingPrice'] ?>, <?= $row['quantity'] ?>)">
                                <div class="med-name"><?= htmlspecialchars($row['medicineName']) ?></div>
                                <div class="med-meta">
                                    <span>UGX <?= number_format($row['sellingPrice']) ?></span>
                                    <span><?= $row['quantity'] ?> in stock</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="cart-pane">
                    <div style="display:flex; flex-direction:column; flex-grow:1;">
                        <h3>Shopping Cart</h3>
                        <div class="cart-items-list">
                            <div class="cart-header-row"><span>Medicine</span><span>Qty</span><span>Price</span></div>
                            <div id="cartContents" style="text-align: center; color: #a0aec0; padding-top: 30px; font-style: italic;">
                                Cart is empty. Add medicines from the left or scan barcode.
                            </div>
                        </div>
                    </div>

                    <form class="checkout-form" id="posForm" onsubmit="return false;">
                        <div class="calc-row"><span>Total</span><span>UGX <span id="lblTotal">0</span></span></div>

                        <div class="form-group">
                            <label>Payment Method</label>
                            <select name="payment_method" id="paymentMethod">
                                <option value="cash">Cash</option>
                                <option value="Mobile Money">Mobile Money</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Cash Received</label>
                            <input type="number" id="txtReceived" value="0" oninput="calculateBalance()">
                        </div>

                        <div class="calc-row" style="margin-bottom: 10px;"><span>Balance</span><span>UGX <span id="lblBalance">0</span></span></div>

                        <button type="button" class="submit-btn" id="btnSubmit" disabled onclick="completeSale()">Complete Sale</button>
                    </form>
                </div>
            </div>
            <?php require_once 'includes/footer.php'; ?>

        </main>
    </div>

    <!-- Camera Scanner Modal -->
    <div class="scanner-overlay" id="posScannerOverlay">
        <div class="scanner-box">
            <div class="scanner-header">
                <h3>Scan Barcode</h3>
                <button type="button" onclick="closePosCameraScanner()">&times;</button>
            </div>
            <div id="posCameraReader"></div>
            <p class="scanner-hint" id="posScannerHint">Point the camera at the medicine barcode.</p>
        </div>
    </div>

    <!-- Receipt Modal -->
    <div class="receipt-overlay" id="receiptOverlay">
        <div class="receipt-box" id="receiptPrintArea">
            <div class="receipt-modal-header">
                <h3>Receipt</h3>
                <button type="button" class="receipt-close-x" onclick="closeReceipt()">X</button>
            </div>

            <div class="receipt-body">
                <div class="receipt-logo">
                    <img src="includes/logomenu.png" alt="Logo">
                </div>
                <div class="receipt-shop-name">PIMS LTD</div>

                <div class="receipt-meta-row">
                    <span>Receipt: <strong id="rcptId"></strong></span>
                    <span id="rcptDate"></span>
                    <span>Cashier: <?= htmlspecialchars($cashierName) ?></span>
                </div>

                <table class="receipt-table">
                    <thead>
                        <tr><th>Item</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr>
                    </thead>
                    <tbody id="rcptItems"></tbody>
                </table>

                <div class="receipt-totals">
                    <div><span>Total</span><span id="rcptTotal"></span></div>
                    <div><span>Payment</span><span id="rcptPayment"></span></div>
                    <div><span>Cash received</span><span id="rcptReceived"></span></div>
                    <div><span>Balance</span><span id="rcptBalance"></span></div>
                </div>

                <p class="receipt-thanks">Thank you for your purchase!</p>
            </div>

            <div class="receipt-footer">
                <button type="button" class="receipt-close-btn" onclick="closeReceipt()">Close</button>
                <button type="button" class="receipt-print-btn" onclick="window.print()"><i class="fa-solid fa-print"></i> Print Receipt</button>
            </div>
        </div>
    </div>

    <script>
        let cart = [];
        let posCameraScanner = null;
        const posBarcodeField = document.getElementById('posBarcodeField');
        const posFeedbackCard = document.getElementById('posFeedbackCard');
        const posFeedbackText = document.getElementById('posFeedbackText');
        const posFeedbackDetails = document.getElementById('posFeedbackDetails');

        function filterMedicines() {
            let filter = document.getElementById('searchBar').value.toLowerCase().trim();
            let cards = document.getElementsByClassName('med-card');
            for (let card of cards) {
                let name = card.getElementsByClassName('med-name')[0].innerText.toLowerCase();
                let barcode = (card.getAttribute('data-barcode') || '').toLowerCase();
                card.style.display = (name.includes(filter) || barcode.includes(filter)) ? "" : "none";
            }
        }

        function addToCart(id, name, price, maxQty) {
            let existing = cart.find(item => item.id === id);
            if (existing) {
                if (existing.qty < maxQty) {
                    existing.qty++;
                } else {
                    alert(`Cannot exceed available warehouse stock (${maxQty} units).`);
                    return false;
                }
            } else {
                if (maxQty <= 0) {
                    alert(`Cannot add "${name}": Out of stock.`);
                    return false;
                }
                cart.push({
                    id: id,
                    name: name,
                    price: price,
                    qty: 1
                });
            }
            renderCart();
            return true;
        }

        function renderCart() {
            let container = document.getElementById('cartContents');
            if (cart.length === 0) {
                container.innerHTML = `<div style="text-align: center; color: #a0aec0; padding-top: 30px; font-style: italic;">Cart is empty. Add medicines from the left or scan barcode.</div>`;
                document.getElementById('btnSubmit').disabled = true;
                document.getElementById('btnSubmit').classList.remove('ready');
                return;
            }

            let html = '';
            let total = 0;
            cart.forEach(item => {
                let subtotal = item.price * item.qty;
                total += subtotal;
                html += `<div class="cart-item-row">
                    <span>${item.name}</span>
                    <span>${item.qty}</span>
                    <span>${subtotal.toLocaleString()}</span>
                </div>`;
            });

            container.innerHTML = html;
            document.getElementById('lblTotal').innerText = total.toLocaleString();
            document.getElementById('btnSubmit').disabled = false;
            document.getElementById('btnSubmit').classList.add('ready');
            calculateBalance();
        }

        function calculateBalance() {
            let total = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
            let received = parseFloat(document.getElementById('txtReceived').value) || 0;
            let balance = received - total;
            document.getElementById('lblBalance').innerText = balance >= 0 ? balance.toLocaleString() : 0;
        }

        /* -------------------------------------------------------------
           Barcode Scanning Logic (USB Barcode Scanner & Camera)
           ------------------------------------------------------------- */
        let lastScanCode = '';
        let lastScanTimestamp = 0;

        // Listen for Enter key on barcode field (typical USB scanner behavior)
        posBarcodeField.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const code = this.value.trim();
                if (code) {
                    processScannedBarcode(code);
                }
            }
        });

        // Global USB scanner keyboard wedge detection
        let posKeyBuffer = '';
        let posLastKeyTime = 0;
        window.addEventListener('keydown', function(e) {
            const now = Date.now();
            const activeId = document.activeElement ? document.activeElement.id : '';

            // Don't intercept if user is typing in cash received or search input
            if (activeId === 'txtReceived' || activeId === 'searchBar') {
                return;
            }

            if (now - posLastKeyTime > 60) {
                posKeyBuffer = '';
            }
            posLastKeyTime = now;

            if (e.key === 'Enter') {
                if (posKeyBuffer.length >= 3) {
                    e.preventDefault();
                    processScannedBarcode(posKeyBuffer);
                    posKeyBuffer = '';
                }
            } else if (e.key.length === 1) {
                posKeyBuffer += e.key;
            }
        });

        async function processScannedBarcode(barcode) {
            const now = Date.now();
            // Debounce accidental immediate re-reads (< 300ms)
            if (barcode === lastScanCode && (now - lastScanTimestamp) < 300) {
                return;
            }
            lastScanCode = barcode;
            lastScanTimestamp = now;

            posBarcodeField.value = '';

            try {
                const res = await fetch(`lookup_barcode.php?barcode=${encodeURIComponent(barcode)}`);
                const data = await res.json();

                if (data.found && data.medicine) {
                    const med = data.medicine;

                    // Display full medicine details: name, barcode, category, price, stock, expiry
                    posFeedbackCard.style.display = 'block';
                    posFeedbackDetails.style.display = 'grid';
                    posFeedbackDetails.innerHTML = `
                        <span><strong>Name:</strong> ${med.medicineName}</span>
                        <span><strong>Barcode:</strong> ${med.barcode}</span>
                        <span><strong>Category:</strong> ${med.categoryName}</span>
                        <span><strong>Price:</strong> UGX ${med.formattedSellingPrice}</span>
                        <span><strong>Stock:</strong> ${med.quantity} in stock</span>
                        <span><strong>Expiry:</strong> ${med.formattedExpiry}</span>
                    `;

                    // Validate stock and expiry date
                    if (med.isExpired) {
                        posFeedbackCard.className = 'pos-feedback-card error';
                        posFeedbackText.textContent = `❌ Cannot sell "${med.medicineName}": Expired on ${med.formattedExpiry}!`;
                        return;
                    }

                    if (med.isOutOfStock || med.quantity <= 0) {
                        posFeedbackCard.className = 'pos-feedback-card error';
                        posFeedbackText.textContent = `❌ Cannot sell "${med.medicineName}": Out of stock (0 available)!`;
                        return;
                    }

                    // Check if already in cart at max warehouse stock
                    const cartItem = cart.find(i => i.id === med.medicineId);
                    if (cartItem && cartItem.qty >= med.quantity) {
                        posFeedbackCard.className = 'pos-feedback-card error';
                        posFeedbackText.textContent = `⚠️ Cannot add more "${med.medicineName}": Reached warehouse stock limit (${med.quantity} units).`;
                        return;
                    }

                    // Automatically populate cart with scanned item
                    const added = addToCart(med.medicineId, med.medicineName, med.sellingPrice, med.quantity);
                    if (added) {
                        posFeedbackCard.className = 'pos-feedback-card success';
                        posFeedbackText.textContent = `✅ Added "${med.medicineName}" to cart!`;
                    }

                } else {
                    // Barcode does not exist
                    posFeedbackCard.style.display = 'block';
                    posFeedbackCard.className = 'pos-feedback-card error';
                    posFeedbackText.textContent = `❌ Barcode not found: No medicine in database matches barcode "${barcode}".`;
                    posFeedbackDetails.style.display = 'none';
                }
            } catch (err) {
                posFeedbackCard.style.display = 'block';
                posFeedbackCard.className = 'pos-feedback-card error';
                posFeedbackText.textContent = '❌ Failed to reach server during barcode lookup.';
                posFeedbackDetails.style.display = 'none';
            } finally {
                posBarcodeField.focus();
            }
        }

        function openPosCameraScanner() {
            document.getElementById('posScannerOverlay').style.display = 'flex';
            document.getElementById('posScannerHint').textContent = 'Point camera at the barcode on the medicine box.';
            document.getElementById('posScannerHint').style.color = '';
            posCameraScanner = new Html5Qrcode("posCameraReader");

            posCameraScanner.start(
                { facingMode: "environment" },
                { fps: 10, qrbox: { width: 250, height: 150 } },
                (decodedText) => {
                    document.getElementById('posScannerHint').textContent = `✅ Captured: ${decodedText}`;
                    document.getElementById('posScannerHint').style.color = '#2f6f4f';
                    setTimeout(() => {
                        closePosCameraScanner();
                        processScannedBarcode(decodedText);
                    }, 500);
                },
                () => {}
            ).catch(() => {
                document.getElementById('posScannerHint').textContent = '❌ Could not access camera';
                document.getElementById('posScannerHint').style.color = '#c0392b';
            });
        }

        function closePosCameraScanner() {
            document.getElementById('posScannerOverlay').style.display = 'none';
            if (posCameraScanner) {
                posCameraScanner.stop().catch(() => {});
                posCameraScanner = null;
            }
            posBarcodeField.focus();
        }

        /* -------------------------------------------------------------
           Sale Processing & Receipt Modal
           ------------------------------------------------------------- */
        async function completeSale() {
            const paymentMethod = document.getElementById('paymentMethod').value;
            const received = parseFloat(document.getElementById('txtReceived').value) || 0;
            const total = cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
            const balance = received - total;

            const btn = document.getElementById('btnSubmit');
            btn.disabled = true;
            btn.textContent = 'Processing...';

            try {
                const formData = new FormData();
                formData.append('checkout', '1');
                formData.append('payment_method', paymentMethod);
                formData.append('cart_data', JSON.stringify(cart));

                const res = await fetch('process_sale.php', { method: 'POST', body: formData });
                const data = await res.json();

                if (!data.success) {
                    alert(data.message || 'Could not complete the sale.');
                    btn.disabled = false;
                    btn.textContent = 'Complete Sale';
                    return;
                }

                showReceipt(data, paymentMethod, received, balance, total);

                // Reset the sale so a fresh cart starts once the receipt is closed
                cart = [];
                document.getElementById('txtReceived').value = 0;

            } catch (err) {
                alert('Could not reach the server to complete the sale.');
                btn.disabled = false;
                btn.textContent = 'Complete Sale';
            }
        }

        function showReceipt(data, paymentMethod, received, balance, total) {
            document.getElementById('rcptId').textContent = 'RCT-' + String(data.saleId).padStart(4, '0');
            document.getElementById('rcptDate').textContent = data.saleDate;
            document.getElementById('rcptTotal').textContent = 'UGX ' + total.toLocaleString();
            document.getElementById('rcptPayment').textContent = paymentMethod;
            document.getElementById('rcptReceived').textContent = 'UGX ' + received.toLocaleString();
            document.getElementById('rcptBalance').textContent = 'UGX ' + (balance >= 0 ? balance.toLocaleString() : 0);

            let rows = '';
            cart.forEach(item => {
                const subtotal = item.price * item.qty;
                rows += `<tr>
                    <td>${item.name}</td>
                    <td>${item.qty}</td>
                    <td>UGX ${item.price.toLocaleString()}</td>
                    <td>UGX ${subtotal.toLocaleString()}</td>
                </tr>`;
            });
            document.getElementById('rcptItems').innerHTML = rows;

            document.getElementById('receiptOverlay').style.display = 'flex';
        }

        function closeReceipt() {
            document.getElementById('receiptOverlay').style.display = 'none';
            renderCart();
            window.location.reload();
        }
    </script>

</body>

</html>
