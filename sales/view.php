<?php

require_once __DIR__ . '/../config/database.php';

$basePath = "../";
$pageTitle = "Sale Details";

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';


$saleId = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);


if (!$saleId) {

    header("Location: index.php");
    exit();

}


/*
|--------------------------------------------------------------------------
| Sale Information
|--------------------------------------------------------------------------
*/

$saleStmt = $conn->prepare("
    SELECT
        s.*,
        c.customer_name,
        c.mobile,
        c.address,
        c.email

    FROM sales s

    LEFT JOIN customers c
        ON s.customer_id = c.id

    WHERE s.id = ?

    LIMIT 1
");


$saleStmt->bind_param(
    "i",
    $saleId
);

$saleStmt->execute();

$saleResult =
    $saleStmt->get_result();

$sale =
    $saleResult->fetch_assoc();

$saleStmt->close();


if (!$sale) {

    header("Location: index.php");
    exit();

}


$linkedService = null;
$serviceStmt = $conn->prepare("
    SELECT s.id, s.device_brand, s.device_model, s.service_charge, s.service_status
    FROM invoices i
    INNER JOIN services s ON s.id = i.service_id
    WHERE i.sale_id = ? AND i.service_id IS NOT NULL
    ORDER BY i.id DESC LIMIT 1
");
if ($serviceStmt) {
    $serviceStmt->bind_param("i", $saleId);
    $serviceStmt->execute();
    $linkedService = $serviceStmt->get_result()->fetch_assoc();
    $serviceStmt->close();
}


/*
|--------------------------------------------------------------------------
| Sale Items
|--------------------------------------------------------------------------
*/

$itemStmt = $conn->prepare("
    SELECT
        si.id,
        si.quantity,
        si.unit_price,
        si.total_price,
        p.product_name,
        p.item_type

    FROM sale_items si

    LEFT JOIN products p
        ON si.product_id = p.id

    WHERE si.sale_id = ?

    ORDER BY si.id ASC
");


$itemStmt->bind_param(
    "i",
    $saleId
);

$itemStmt->execute();

$itemResult =
    $itemStmt->get_result();

$items = [];

while ($item = $itemResult->fetch_assoc()) {
    $items[] = $item;
}

$itemStmt->close();


function getPaymentBadgeClass($status)
{
    switch ($status) {

        case 'Paid':
            return 'payment-paid';

        case 'Partial':
            return 'payment-partial';

        case 'Due':
            return 'payment-due';

        default:
            return 'payment-default';
    }
}

?>

<div class="page-content">

    <div class="page-header">

        <div>

            <h1>Sale Details</h1>

            <p>
                View complete sales information.
            </p>

        </div>

        <div class="header-actions">

    <a
        href="index.php"
        class="btn btn-secondary"
    >
        <i class="fa fa-arrow-left"></i>
        Back
    </a>


    <a
        href="edit.php?id=<?= (int)$sale['id'] ?>"
        class="btn btn-edit"
    >
        <i class="fa fa-pencil"></i>
        Edit Sale
    </a>


    <form
        method="POST"
        action="delete.php"
        style="display:inline; margin:0;"
        onsubmit="return confirm('Are you sure you want to delete this sale record? The sold stock will be restored. This action cannot be undone.');"
    >
        <input type="hidden" name="id" value="<?= (int)$sale['id'] ?>">
        <button
            type="submit"
            class="btn btn-delete"
        >
            <i class="fa fa-trash"></i>
            Delete Sale
        </button>
    </form>


    <button
        type="button"
        class="btn btn-primary"
        onclick="window.print()"
    >
        <i class="fa fa-print"></i>
        Print
    </button>

</div>
    </div>


    <?php if (isset($_GET['success'])): ?>

    <div class="success-message">

        <i class="fa fa-check-circle"></i>

        Sale completed successfully.

    </div>

<?php endif; ?>


<?php if (isset($_GET['updated'])): ?>

    <div class="success-message">

        <i class="fa fa-check-circle"></i>

        Sale record updated successfully.

    </div>

<?php endif; ?>


    <div class="invoice-card">

        <!-- Invoice Header -->

        <div class="invoice-header">

            <div>

                <h2>
                    Uttara Mobile
                </h2>

                <p>
                    Sales & Service Management System
                </p>

            </div>

            <div class="invoice-meta">

                <div>

                    <span>Invoice</span>

                    <strong>
                        <?= htmlspecialchars(
                            $sale['invoice_number']
                        ) ?>
                    </strong>

                </div>

                <div>

                    <span>Date</span>

                    <strong>
                        <?= date(
                            'd M Y, h:i A',
                            strtotime($sale['sale_date'])
                        ) ?>
                    </strong>

                </div>

            </div>

        </div>


        <!-- Customer -->

        <div class="customer-section">

            <div>

                <span class="section-label">
                    Customer
                </span>

                <strong>
                    <?= htmlspecialchars(
                        $sale['customer_name']
                            ?? 'Walk-in Customer'
                    ) ?>
                </strong>

            </div>


            <div>

                <span class="section-label">
                    Mobile
                </span>

                <strong>
                    <?= htmlspecialchars(
                        $sale['mobile']
                            ?? '-'
                    ) ?>
                </strong>

            </div>


            <div>

                <span class="section-label">
                    Payment Status
                </span>

                <span
                    class="payment-badge <?= getPaymentBadgeClass($sale['payment_status']) ?>"
                >
                    <?= htmlspecialchars(
                        $sale['payment_status']
                    ) ?>
                </span>

            </div>

        </div>


        <!-- Items -->

        <div class="invoice-items">

            <table>

                <thead>

                    <tr>

                        <th>#</th>
                        <th>Product / Part</th>
                        <th>Type</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th>Total</th>

                    </tr>

                </thead>

                <tbody>

                <?php foreach ($items as $index => $item): ?>

                    <tr>

                        <td>
                            <?= $index + 1 ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $item['product_name']
                                    ?? 'Deleted Product'
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $item['item_type']
                                    ?? '-'
                            ) ?>
                        </td>

                        <td>
                            <?= (int)$item['quantity'] ?>
                        </td>

                        <td>
                            ৳<?= number_format(
                                (float)$item['unit_price'],
                                2
                            ) ?>
                        </td>

                        <td>
                            ৳<?= number_format(
                                (float)$item['total_price'],
                                2
                            ) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>


        <!-- Totals -->

        <div class="invoice-bottom">

            <div class="payment-summary">

                <?php if ($linkedService): ?>
                    <div class="summary-line">
                        <span>Service Order</span>
                        <strong>#<?= (int)$linkedService['id'] ?> — <?= htmlspecialchars($linkedService['device_brand'] . ' ' . $linkedService['device_model']) ?></strong>
                    </div>
                    <div class="summary-line">
                        <span>Service Charge</span>
                        <strong>৳<?= number_format((float)$linkedService['service_charge'], 2) ?></strong>
                    </div>
                <?php endif; ?>

                <div class="summary-line">

                    <span>
                        Subtotal
                    </span>

                    <strong>
                        ৳<?= number_format(
                            (float)$sale['subtotal'],
                            2
                        ) ?>
                    </strong>

                </div>


                <div class="summary-line">

                    <span>
                        Discount
                    </span>

                    <strong>
                        - ৳<?= number_format(
                            (float)$sale['discount'],
                            2
                        ) ?>
                    </strong>

                </div>


                <div class="summary-line grand">

                    <span>
                        Grand Total
                    </span>

                    <strong>
                        ৳<?= number_format(
                            (float)$sale['grand_total'],
                            2
                        ) ?>
                    </strong>

                </div>


                <div class="summary-line">

                    <span>
                        Paid Amount
                    </span>

                    <strong>
                        ৳<?= number_format(
                            (float)$sale['paid_amount'],
                            2
                        ) ?>
                    </strong>

                </div>


                <div class="summary-line due">

                    <span>
                        Due Amount
                    </span>

                    <strong>
                        ৳<?= number_format(
                            (float)$sale['due_amount'],
                            2
                        ) ?>
                    </strong>

                </div>

            </div>

        </div>


        <div class="invoice-footer">

            Thank you for choosing Uttara Mobile.

        </div>

    </div>

</div>


<style>

.page-content {
    margin-left: 223px;
    padding: 32px 35px;
    width: calc(100% - 223px);
    box-sizing: border-box;
}

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 28px;
}

.page-header h1 {
    margin: 0 0 6px;
    font-size: 30px;
    color: #172033;
}

.page-header p {
    margin: 0;
    color: #526177;
    font-size: 14px;
}

.header-actions {
    display: flex;
    gap: 8px;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    border: none;
    border-radius: 6px;
    padding: 10px 15px;
    text-decoration: none;
    font-size: 14px;
    cursor: pointer;
}

.btn-primary {
    background: #1769e0;
    color: #ffffff !important;
}

.btn-secondary {
    background: #68727e;
    color: #ffffff !important;
}

.success-message {
    background: #d9f5e9;
    border: 1px solid #a9e4c8;
    color: #08754f;
    padding: 12px 15px;
    border-radius: 7px;
    margin-bottom: 20px;
}

.invoice-card {
    background: #ffffff;
    border-radius: 9px;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    overflow: hidden;
}

.invoice-header {
    display: flex;
    justify-content: space-between;
    padding: 25px;
    border-bottom: 1px solid #e4e7eb;
}

.invoice-header h2 {
    margin: 0 0 5px;
    color: #172033;
}

.invoice-header p {
    margin: 0;
    color: #697586;
}

.invoice-meta {
    display: flex;
    gap: 35px;
}

.invoice-meta div {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.invoice-meta span {
    font-size: 12px;
    color: #697586;
}

.invoice-meta strong {
    color: #172033;
}

.customer-section {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr;
    gap: 20px;
    padding: 20px 25px;
    background: #f8f9fb;
}

.customer-section div {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.section-label {
    font-size: 12px;
    color: #697586;
}

.payment-badge {
    display: inline-block;
    width: fit-content;
    padding: 5px 9px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}

.payment-paid {
    background: #d9f5e9;
    color: #08754f;
}

.payment-partial {
    background: #fff3cd;
    color: #856404;
}

.payment-due {
    background: #fde2e2;
    color: #b42318;
}

.payment-default {
    background: #e9ecef;
    color: #495057;
}

.invoice-items {
    padding: 25px;
    overflow-x: auto;
}

.invoice-items table {
    width: 100%;
    border-collapse: collapse;
}

.invoice-items th {
    background: #f7f8fa;
    text-align: left;
    padding: 12px;
    border-bottom: 1px solid #dfe3e8;
}

.invoice-items td {
    padding: 13px 12px;
    border-bottom: 1px solid #e5e8ec;
}

.invoice-bottom {
    display: flex;
    justify-content: flex-end;
    padding: 0 25px 25px;
}

.payment-summary {
    width: 420px;
}

.summary-line {
    display: flex;
    justify-content: space-between;
    padding: 9px 0;
    color: #526177;
}

.summary-line strong {
    color: #172033;
}

.summary-line.grand {
    border-top: 1px solid #dfe3e8;
    border-bottom: 1px solid #dfe3e8;
    padding: 14px 0;
}

.summary-line.grand strong {
    font-size: 20px;
}

.summary-line.due strong {
    color: #b42318;
}

.invoice-footer {
    text-align: center;
    padding: 18px;
    background: #f7f8fa;
    color: #697586;
}

.btn-edit {
    background: #f0a400;
    color: #ffffff !important;
}

.btn-edit:hover {
    background: #d99200;
    color: #ffffff !important;
}

@media (max-width: 700px) {

    .page-content {
        margin-left: 0;
        width: 100%;
        padding: 20px;
    }

    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

    .invoice-header {
        flex-direction: column;
        gap: 20px;
    }

    .invoice-meta {
        flex-direction: column;
        gap: 10px;
    }

    .customer-section {
        grid-template-columns: 1fr;
    }

    .payment-summary {
        width: 100%;
    }

}

@media print {

    body {
        background: #ffffff !important;
    }

    .page-content {
        margin: 0 !important;
        width: 100% !important;
        padding: 0 !important;
    }

    .page-header,
    .success-message,
    nav,
    aside,
    .sidebar,
    .navbar {
        display: none !important;
    }

    .invoice-card {
        box-shadow: none !important;
        border-radius: 0 !important;
    }

}


.btn-delete {
    background: #dc3545;
    color: #ffffff !important;
    border: 1px solid #dc3545;
}

.btn-delete:hover {
    background: #bb2d3b;
    border-color: #bb2d3b;
    color: #ffffff !important;
}

</style>