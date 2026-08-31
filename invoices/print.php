<?php

require_once __DIR__ . '/../config/database.php';

$invoiceId = isset($_GET['id'])
    ? (int)$_GET['id']
    : 0;

if ($invoiceId <= 0) {
    die("Invalid invoice.");
}


/*
|--------------------------------------------------------------------------
| Load Invoice
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        i.*,

        s.customer_id AS sale_customer_id,
        s.subtotal,
        s.discount,
        s.grand_total AS sale_total,
        s.paid_amount AS sale_paid,

        sv.customer_id AS service_customer_id,
        sv.device_brand,
        sv.device_model,
        sv.imei_serial,
        sv.problem_description,
        sv.service_charge,

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
");

if (!$stmt) {
    die("Database error.");
}

$stmt->bind_param("i", $invoiceId);

$stmt->execute();

$result = $stmt->get_result();

$invoice = $result->fetch_assoc();

$stmt->close();

if (!$invoice) {
    die("Invoice not found.");
}

$isSale =
    !empty($invoice['sale_id']);

$isService =
    !empty($invoice['service_id']);


/*
|--------------------------------------------------------------------------
| Items
|--------------------------------------------------------------------------
*/

$items = [];

if ($isSale) {

    $stmt = $conn->prepare("
        SELECT
            si.quantity,
            si.unit_price,
            si.total_price,
            p.product_name

        FROM sale_items si

        LEFT JOIN products p
            ON p.id = si.product_id

        WHERE si.sale_id = ?

        ORDER BY si.id ASC
    ");

    $stmt->bind_param(
        "i",
        $invoice['sale_id']
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $displaySerial = 0;

while ($row = $result->fetch_assoc()) {
    $displaySerial++;

        $items[] = $row;
    }

    $stmt->close();

} else {

    $stmt = $conn->prepare("
        SELECT
            quantity,
            unit_price,
            total_price,
            used_status,
            p.product_name

        FROM service_parts sp

        LEFT JOIN products p
            ON p.id = sp.product_id

        WHERE service_id = ?

        ORDER BY sp.id ASC
    ");

    $stmt->bind_param(
        "i",
        $invoice['service_id']
    );

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        if ($row['used_status'] === 'Used') {
            $items[] = $row;
        }
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Calculate
|--------------------------------------------------------------------------
*/

if ($isSale) {

    $total =
        (float)$invoice['sale_total'];

    $paid =
        (float)$invoice['sale_paid'];

} else {

    $partsTotal = 0;

    foreach ($items as $item) {
        $partsTotal +=
            (float)$item['total_price'];
    }

    $total =
        (float)$invoice['service_charge'] +
        $partsTotal;

    $paid = 0;

    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) AS total
        FROM payments
        WHERE service_id = ?
    ");

    $stmt->bind_param(
        "i",
        $invoice['service_id']
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $paid =
        (float)$result
            ->fetch_assoc()['total'];

    $stmt->close();
}

$due =
    max(
        0,
        $total - $paid
    );

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>
        <?= htmlspecialchars(
            $invoice['invoice_number']
        ); ?>
    </title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            font-family: Arial, sans-serif;
            color: #222;
            background: #fff;
        }

        .invoice {
            max-width: 900px;
            margin: auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }

        .company h1 {
            margin: 0 0 5px;
        }

        .company p {
            margin: 0;
            color: #666;
        }

        .invoice-title {
            text-align: right;
        }

        .invoice-title h2 {
            margin: 0 0 8px;
        }

        .customer {
            margin: 25px 0;
            padding: 15px;
            border: 1px solid #ddd;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th,
        td {
            border: 1px solid #ddd;
            padding: 10px;
        }

        th {
            background: #f4f4f4;
            text-align: left;
        }

        .text-right {
            text-align: right;
        }

        .summary {
            width: 350px;
            margin-left: auto;
            margin-top: 20px;
        }

        .summary td,
        .summary th {
            border: none;
            padding: 8px;
        }

        .grand-total {
            font-size: 18px;
            font-weight: bold;
        }

        .paid {
            color: #198754;
        }

        .due {
            color: #dc3545;
            font-weight: bold;
        }

        .footer {
            margin-top: 50px;
            text-align: center;
            color: #777;
            border-top: 1px solid #ddd;
            padding-top: 15px;
        }

        .print-button {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 10px 18px;
            border: 0;
            background: #0d6efd;
            color: white;
            border-radius: 5px;
            cursor: pointer;
        }

        @media print {

            body {
                padding: 0;
            }

            .print-button {
                display: none;
            }

        }

    </style>

</head>

<body>

<button
    class="print-button"
    onclick="window.print()">

    Print Invoice

</button>


<div class="invoice">

    <div class="header">

        <div class="company">

            <h1>
                Uttara Mobile
            </h1>

            <p>
                Sales & Service Management System
            </p>

        </div>


        <div class="invoice-title">

            <h2>
                INVOICE
            </h2>

            <strong>

                <?= htmlspecialchars(
                    $invoice['invoice_number']
                ); ?>

            </strong>

            <div>

                <?= date(
                    "d M Y, h:i A",
                    strtotime(
                        $invoice['invoice_date']
                    )
                ); ?>

            </div>

        </div>

    </div>


    <div class="customer">

        <strong>
            BILL TO
        </strong>

        <h3>

            <?= htmlspecialchars(
                $invoice['customer_name']
                ?: "Walk-in Customer"
            ); ?>

        </h3>

        <?php if (!empty($invoice['mobile'])): ?>

            <div>

                Mobile:
                <?= htmlspecialchars(
                    $invoice['mobile']
                ); ?>

            </div>

        <?php endif; ?>

    </div>


    <?php if ($isService): ?>

        <div class="customer">

            <strong>
                SERVICE INFORMATION
            </strong>

            <p>

                <strong>Device:</strong>

                <?= htmlspecialchars(
                    $invoice['device_brand']
                ); ?>

                <?= htmlspecialchars(
                    $invoice['device_model']
                ); ?>

            </p>

            <p>

                <strong>IMEI / Serial:</strong>

                <?= htmlspecialchars(
                    $invoice['imei_serial']
                    ?: "-"
                ); ?>

            </p>

            <p>

                <strong>Problem:</strong>

                <?= nl2br(
                    htmlspecialchars(
                        $invoice['problem_description']
                    )
                ); ?>

            </p>

        </div>

    <?php endif; ?>


    <table>

        <thead>

            <tr>

                <th>
                    #
                </th>

                <th>
                    Description
                </th>

                <th>
                    Qty
                </th>

                <th>
                    Unit Price
                </th>

                <th>
                    Total
                </th>

            </tr>

        </thead>


        <tbody>

        <?php $counter = 1; ?>


        <?php if ($isService): ?>

            <tr>

                <td>
                    <?= $counter++; ?>
                </td>

                <td>
                    Service / Repair Charge
                </td>

                <td>
                    1
                </td>

                <td class="text-right">

                    ৳<?= number_format(
                        $invoice['service_charge'],
                        2
                    ); ?>

                </td>

                <td class="text-right">

                    ৳<?= number_format(
                        $invoice['service_charge'],
                        2
                    ); ?>

                </td>

            </tr>

        <?php endif; ?>


        <?php foreach ($items as $item): ?>

            <tr>

                <td>
                    <?= $counter++; ?>
                </td>

                <td>

                    <?= htmlspecialchars(
                        $item['product_name']
                        ?: "Part"
                    ); ?>

                </td>

                <td>

                    <?= (int)$item['quantity']; ?>

                </td>

                <td class="text-right">

                    ৳<?= number_format(
                        $item['unit_price'],
                        2
                    ); ?>

                </td>

                <td class="text-right">

                    ৳<?= number_format(
                        $item['total_price'],
                        2
                    ); ?>

                </td>

            </tr>

        <?php endforeach; ?>

        </tbody>

    </table>


    <table class="summary">

        <?php if ($isSale): ?>

            <tr>

                <th>
                    Subtotal
                </th>

                <td class="text-right">

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

                <td class="text-right">

                    ৳<?= number_format(
                        $invoice['discount'],
                        2
                    ); ?>

                </td>

            </tr>

        <?php endif; ?>


        <tr class="grand-total">

            <th>
                Grand Total
            </th>

            <td class="text-right">

                ৳<?= number_format(
                    $total,
                    2
                ); ?>

            </td>

        </tr>


        <tr>

            <th class="paid">
                Paid
            </th>

            <td class="text-right paid">

                ৳<?= number_format(
                    $paid,
                    2
                ); ?>

            </td>

        </tr>


        <tr>

            <th class="due">
                Due
            </th>

            <td class="text-right due">

                ৳<?= number_format(
                    $due,
                    2
                ); ?>

            </td>

        </tr>

    </table>


    <div class="footer">

        Thank you for choosing Uttara Mobile.

    </div>

</div>

<script>
    // Open the browser print dialog automatically when the invoice print page loads.
    window.addEventListener("load", function () {
        setTimeout(function () {
            window.print();
        }, 300);
    });
</script>

</body>

</html>