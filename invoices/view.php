<?php

require_once __DIR__ . '/../config/database.php';

$basePath = "../";
$pageTitle = "Invoice Details";

$invoiceId = isset($_GET['id'])
    ? (int)$_GET['id']
    : 0;

if ($invoiceId <= 0) {
    header("Location: index.php");
    exit();
}


/*
|--------------------------------------------------------------------------
| Load Invoice
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        i.*,

        s.customer_id AS sale_customer_id,
        s.invoice_number AS sale_invoice_number,
        s.subtotal,
        s.discount,
        s.grand_total AS sale_total,
        s.paid_amount AS sale_paid,
        s.due_amount AS sale_due,

        sv.customer_id AS service_customer_id,
        sv.device_brand,
        sv.device_model,
        sv.imei_serial,
        sv.problem_description,
        sv.service_charge,
        sv.service_status,
        sv.received_date,
        sv.expected_delivery_date,

        c.customer_name,
        c.mobile

    FROM invoices i

    LEFT JOIN sales s
        ON s.id = i.sale_id

    LEFT JOIN services sv
        ON sv.id = i.service_id

    LEFT JOIN customers c
        ON c.id = COALESCE(
            s.customer_id,
            sv.customer_id
        )

    WHERE i.id = ?

    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . htmlspecialchars($conn->error));
}

$stmt->bind_param("i", $invoiceId);

$stmt->execute();

$result = $stmt->get_result();

$invoice = $result->fetch_assoc();

$stmt->close();

if (!$invoice) {

    header("Location: index.php");
    exit();
}


/*
|--------------------------------------------------------------------------
| Determine Invoice Type
|--------------------------------------------------------------------------
*/

$isSale =
    !empty($invoice['sale_id']);

$isService =
    !empty($invoice['service_id']);


/*
|--------------------------------------------------------------------------
| Sale Items
|--------------------------------------------------------------------------
*/

$saleItems = [];

if ($isSale) {

    $stmt = $conn->prepare("
        SELECT
            si.quantity,
            si.unit_price,
            si.total_price,
            p.product_name,
            p.item_type

        FROM sale_items si

        LEFT JOIN products p
            ON p.id = si.product_id

        WHERE si.sale_id = ?

        ORDER BY si.id ASC
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $invoice['sale_id']
        );

        $stmt->execute();

        $result = $stmt->get_result();

        $displaySerial = 0;

while ($row = $result->fetch_assoc()) {
    $displaySerial++;

            $saleItems[] = $row;
        }

        $stmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| Service Parts
|--------------------------------------------------------------------------
*/

$serviceParts = [];

if ($isService) {

    $stmt = $conn->prepare("
        SELECT
            sp.quantity,
            sp.unit_price,
            sp.total_price,
            sp.used_status,
            p.product_name,
            p.item_type

        FROM service_parts sp

        LEFT JOIN products p
            ON p.id = sp.product_id

        WHERE sp.service_id = ?

        ORDER BY sp.id ASC
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $invoice['service_id']
        );

        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $serviceParts[] = $row;
        }

        $stmt->close();
    }
}


/*
|--------------------------------------------------------------------------
| Calculate Invoice
|--------------------------------------------------------------------------
*/

if ($isSale) {

    $invoiceTotal =
        (float)$invoice['sale_total'];

    /*
     * Existing sale paid amount is treated
     * as the original payment.
     */

    $paidAmount =
        (float)$invoice['sale_paid'];

} else {

    $partsTotal = 0;

    foreach ($serviceParts as $part) {

        if (
            isset($part['used_status']) &&
            $part['used_status'] !== 'Used'
        ) {
            continue;
        }

        $partsTotal +=
            (float)$part['total_price'];
    }

    $invoiceTotal =
        (float)$invoice['service_charge'] +
        $partsTotal;


    /*
     * Service payments come from payments table.
     */

    $paidAmount = 0;

    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) AS total
        FROM payments
        WHERE service_id = ?
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $invoice['service_id']
        );

        $stmt->execute();

        $paymentResult =
            $stmt->get_result();

        $paidAmount =
            (float)$paymentResult
                ->fetch_assoc()['total'];

        $stmt->close();
    }
}

$dueAmount =
    max(
        0,
        $invoiceTotal - $paidAmount
    );


if ($dueAmount <= 0) {

    $paymentStatus = "Paid";
    $statusClass = "bg-success";

} elseif ($paidAmount > 0) {

    $paymentStatus = "Partial";
    $statusClass = "bg-warning text-dark";

} else {

    $paymentStatus = "Due";
    $statusClass = "bg-danger";
}


/*
|--------------------------------------------------------------------------
| Payment History
|--------------------------------------------------------------------------
*/

$payments = [];

if ($isSale) {

    $stmt = $conn->prepare("
        SELECT
            id,
            amount,
            payment_method,
            payment_date,
            notes

        FROM payments

        WHERE sale_id = ?

        ORDER BY payment_date ASC, id ASC
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $invoice['sale_id']
        );

        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }

        $stmt->close();
    }

} elseif ($isService) {

    $stmt = $conn->prepare("
        SELECT
            id,
            amount,
            payment_method,
            payment_date,
            notes

        FROM payments

        WHERE service_id = ?

        ORDER BY payment_date ASC, id ASC
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $invoice['service_id']
        );

        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }

        $stmt->close();
    }
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';

?>

<div class="content">

    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h2 class="fw-bold mb-1">

                    <i class="bi bi-receipt me-2"></i>

                    Invoice Details

                </h2>

                <p class="text-muted mb-0">

                    <?= htmlspecialchars(
                        $invoice['invoice_number']
                    ); ?>

                </p>

            </div>


            <div class="d-flex gap-2">

                <a
                    href="index.php"
                    class="btn btn-secondary">

                    <i class="bi bi-arrow-left me-1"></i>

                    Back

                </a>


                <?php if ($dueAmount > 0): ?>

                    <a
                        href="payment.php?id=<?= $invoiceId; ?>"
                        class="btn btn-success">

                        <i class="bi bi-cash-stack me-1"></i>

                        Add Payment

                    </a>

                <?php endif; ?>


                <a
                    href="print.php?id=<?= $invoiceId; ?>"
                    target="_blank"
                    class="btn btn-primary">

                    <i class="bi bi-printer me-1"></i>

                    Print

                </a>

            </div>

        </div>


        <!-- Invoice -->

        <div class="card shadow-sm border-0 mb-4">

            <div class="card-body p-4">

                <div class="row mb-4">

                    <div class="col-md-6">

                        <h3 class="fw-bold mb-1">

                            Uttara Mobile

                        </h3>

                        <p class="text-muted mb-0">

                            Sales & Service Management System

                        </p>

                    </div>


                    <div class="col-md-6 text-md-end">

                        <h4 class="fw-bold">

                            INVOICE

                        </h4>

                        <div>

                            <strong>
                                <?= htmlspecialchars(
                                    $invoice['invoice_number']
                                ); ?>
                            </strong>

                        </div>

                        <div class="text-muted">

                            <?= date(
                                "d M Y, h:i A",
                                strtotime(
                                    $invoice['invoice_date']
                                )
                            ); ?>

                        </div>

                    </div>

                </div>


                <hr>


                <!-- Customer -->

                <div class="row mb-4">

                    <div class="col-md-6">

                        <small class="text-muted">
                            BILL TO
                        </small>

                        <h5 class="fw-bold mt-1">

                            <?= htmlspecialchars(
                                $invoice['customer_name']
                                ?: "Walk-in Customer"
                            ); ?>

                        </h5>

                        <?php if (
                            !empty($invoice['mobile'])
                        ): ?>

                            <div>

                                <i class="bi bi-telephone me-1"></i>

                                <?= htmlspecialchars(
                                    $invoice['mobile']
                                ); ?>

                            </div>

                        <?php endif; ?>

                    </div>


                    <div class="col-md-6 text-md-end">

                        <span class="badge <?= $statusClass; ?> fs-6">

                            <?= $paymentStatus; ?>

                        </span>

                        <div class="mt-2 text-muted">

                            Type:
                            <strong>

                                <?= $isSale
                                    ? "Sale"
                                    : "Service"; ?>

                            </strong>

                        </div>

                    </div>

                </div>


                <?php if ($isSale): ?>

                    <!-- Sale Details -->

                    <div class="table-responsive">

                        <table class="table table-bordered">

                            <thead class="table-light">

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

                            <?php foreach (
                                $saleItems
                                as $index => $item
                            ): ?>

                                <tr>

                                    <td>
                                        <?= $index + 1; ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $item['product_name']
                                        ); ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $item['item_type']
                                            ?: "-"
                                        ); ?>
                                    </td>

                                    <td>
                                        <?= (int)$item['quantity']; ?>
                                    </td>

                                    <td>
                                        ৳<?= number_format(
                                            $item['unit_price'],
                                            2
                                        ); ?>
                                    </td>

                                    <td>
                                        ৳<?= number_format(
                                            $item['total_price'],
                                            2
                                        ); ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>


                    <div class="row justify-content-end">

                        <div class="col-md-5">

                            <table class="table">

                                <tr>

                                    <th>
                                        Subtotal
                                    </th>

                                    <td class="text-end">
                                        ৳<?= number_format(
                                            $invoice['subtotal'],
                                            2
                                        ); ?>
                                    </td>

                                </tr>


                                <tr>

                                    <th>
                                        Discount
                                    </th>

                                    <td class="text-end">
                                        ৳<?= number_format(
                                            $invoice['discount'],
                                            2
                                        ); ?>
                                    </td>

                                </tr>


                                <tr>

                                    <th class="fs-5">
                                        Grand Total
                                    </th>

                                    <td class="text-end fs-5 fw-bold">
                                        ৳<?= number_format(
                                            $invoiceTotal,
                                            2
                                        ); ?>
                                    </td>

                                </tr>

                            </table>

                        </div>

                    </div>


                <?php else: ?>

                    <!-- Service Information -->

                    <div class="card bg-light border-0 mb-4">

                        <div class="card-body">

                            <h5 class="fw-bold mb-3">

                                <i class="bi bi-tools me-2"></i>

                                Service Information

                            </h5>

                            <div class="row g-3">

                                <div class="col-md-4">

                                    <small class="text-muted">
                                        Brand
                                    </small>

                                    <div class="fw-semibold">

                                        <?= htmlspecialchars(
                                            $invoice['device_brand']
                                        ); ?>

                                    </div>

                                </div>


                                <div class="col-md-4">

                                    <small class="text-muted">
                                        Model
                                    </small>

                                    <div class="fw-semibold">

                                        <?= htmlspecialchars(
                                            $invoice['device_model']
                                        ); ?>

                                    </div>

                                </div>


                                <div class="col-md-4">

                                    <small class="text-muted">
                                        IMEI / Serial
                                    </small>

                                    <div class="fw-semibold">

                                        <?= htmlspecialchars(
                                            $invoice['imei_serial']
                                            ?: "-"
                                        ); ?>

                                    </div>

                                </div>


                                <div class="col-md-12">

                                    <small class="text-muted">
                                        Problem
                                    </small>

                                    <div class="fw-semibold">

                                        <?= nl2br(
                                            htmlspecialchars(
                                                $invoice[
                                                    'problem_description'
                                                ]
                                            )
                                        ); ?>

                                    </div>

                                </div>

                            </div>

                        </div>

                    </div>


                    <!-- Service Items -->

                    <div class="table-responsive">

                        <table class="table table-bordered">

                            <thead class="table-light">

                                <tr>

                                    <th>Description</th>

                                    <th>Qty</th>

                                    <th>Unit Price</th>

                                    <th>Total</th>

                                </tr>

                            </thead>

                            <tbody>

                                <tr>

                                    <td>
                                        Service / Repair Charge
                                    </td>

                                    <td>1</td>

                                    <td>
                                        ৳<?= number_format(
                                            $invoice['service_charge'],
                                            2
                                        ); ?>
                                    </td>

                                    <td>
                                        ৳<?= number_format(
                                            $invoice['service_charge'],
                                            2
                                        ); ?>
                                    </td>

                                </tr>


                                <?php foreach (
                                    $serviceParts
                                    as $part
                                ): ?>

                                    <?php

                                    if (
                                        $part['used_status']
                                        !== 'Used'
                                    ) {
                                        continue;
                                    }

                                    ?>

                                    <tr>

                                        <td>

                                            <?= htmlspecialchars(
                                                $part['product_name']
                                                ?: "Part"
                                            ); ?>

                                        </td>

                                        <td>
                                            <?= (int)$part['quantity']; ?>
                                        </td>

                                        <td>
                                            ৳<?= number_format(
                                                $part['unit_price'],
                                                2
                                            ); ?>
                                        </td>

                                        <td>
                                            ৳<?= number_format(
                                                $part['total_price'],
                                                2
                                            ); ?>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>


                    <div class="row justify-content-end">

                        <div class="col-md-5">

                            <table class="table">

                                <tr>

                                    <th>
                                        Service Charge
                                    </th>

                                    <td class="text-end">

                                        ৳<?= number_format(
                                            $invoice['service_charge'],
                                            2
                                        ); ?>

                                    </td>

                                </tr>


                                <tr>

                                    <th>
                                        Parts Total
                                    </th>

                                    <td class="text-end">

                                        ৳<?= number_format(
                                            $invoiceTotal -
                                            (float)$invoice[
                                                'service_charge'
                                            ],
                                            2
                                        ); ?>

                                    </td>

                                </tr>


                                <tr>

                                    <th class="fs-5">
                                        Grand Total
                                    </th>

                                    <td class="text-end fs-5 fw-bold">

                                        ৳<?= number_format(
                                            $invoiceTotal,
                                            2
                                        ); ?>

                                    </td>

                                </tr>

                            </table>

                        </div>

                    </div>

                <?php endif; ?>


                <!-- Payment Summary -->

                <div class="row justify-content-end mt-3">

                    <div class="col-md-5">

                        <div class="card border-0 bg-light">

                            <div class="card-body">

                                <div class="d-flex justify-content-between mb-2">

                                    <span>
                                        Total
                                    </span>

                                    <strong>
                                        ৳<?= number_format(
                                            $invoiceTotal,
                                            2
                                        ); ?>
                                    </strong>

                                </div>


                                <div class="d-flex justify-content-between mb-2">

                                    <span class="text-success">
                                        Paid
                                    </span>

                                    <strong class="text-success">
                                        ৳<?= number_format(
                                            $paidAmount,
                                            2
                                        ); ?>
                                    </strong>

                                </div>


                                <hr>


                                <div class="d-flex justify-content-between">

                                    <span class="fw-bold">
                                        Due
                                    </span>

                                    <strong class="text-danger fs-5">
                                        ৳<?= number_format(
                                            $dueAmount,
                                            2
                                        ); ?>
                                    </strong>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- Payment History -->

        <div class="card shadow-sm border-0">

            <div class="card-header bg-white">

                <div class="d-flex justify-content-between">

                    <h5 class="mb-0">

                        <i class="bi bi-cash-stack me-2"></i>

                        Payment History

                    </h5>

                    <?php if ($dueAmount > 0): ?>

                        <a
                            href="payment.php?id=<?= $invoiceId; ?>"
                            class="btn btn-sm btn-success">

                            <i class="bi bi-plus-circle me-1"></i>

                            Add Payment

                        </a>

                    <?php endif; ?>

                </div>

            </div>


            <div class="card-body p-0">

                <div class="table-responsive">

                    <table class="table table-hover mb-0">

                        <thead class="table-light">

                            <tr>

                                <th>Date</th>

                                <th>Amount</th>

                                <th>Method</th>

                                <th>Notes</th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php if (empty($payments)): ?>

                            <tr>

                                <td
                                    colspan="4"
                                    class="text-center text-muted py-4">

                                    No additional payment records found.

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($payments as $payment): ?>

                                <tr>

                                    <td>

                                        <?= date(
                                            "d M Y, h:i A",
                                            strtotime(
                                                $payment['payment_date']
                                            )
                                        ); ?>

                                    </td>

                                    <td class="fw-semibold text-success">

                                        ৳<?= number_format(
                                            $payment['amount'],
                                            2
                                        ); ?>

                                    </td>

                                    <td>

                                        <?= htmlspecialchars(
                                            $payment['payment_method']
                                        ); ?>

                                    </td>

                                    <td>

                                        <?= htmlspecialchars(
                                            $payment['notes']
                                            ?: "-"
                                        ); ?>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>