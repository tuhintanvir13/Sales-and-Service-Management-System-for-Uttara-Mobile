<?php

require_once __DIR__ . '/../config/database.php';

$basePath = "../";
$pageTitle = "Invoices";


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

$search = isset($_GET['search'])
    ? trim($_GET['search'])
    : '';

$type = isset($_GET['type'])
    ? trim($_GET['type'])
    : '';

$status = isset($_GET['status'])
    ? trim($_GET['status'])
    : '';

/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$totalInvoices = 0;
$totalSalesInvoices = 0;
$totalServiceInvoices = 0;
$totalDue = 0;

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM invoices
");

if ($result) {
    $totalInvoices = (int)$result->fetch_assoc()['total'];
}

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM invoices
    WHERE sale_id IS NOT NULL
");

if ($result) {
    $totalSalesInvoices = (int)$result->fetch_assoc()['total'];
}

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM invoices
    WHERE service_id IS NOT NULL
");

if ($result) {
    $totalServiceInvoices = (int)$result->fetch_assoc()['total'];
}

/*
|--------------------------------------------------------------------------
| Build Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        i.id,
        i.invoice_number,
        i.invoice_date,
        i.sale_id,
        i.service_id,

        c.customer_name,

        s.invoice_number AS sale_invoice,
        s.grand_total AS sale_total,
        s.paid_amount AS sale_paid,
        s.due_amount AS sale_due,
        s.payment_status AS sale_status,

        sv.service_charge,
        sv.service_status,

        COALESCE(
            (
                SELECT SUM(p.amount)
                FROM payments p
                WHERE p.service_id = sv.id
            ),
            0
        ) AS service_paid,

        COALESCE(
            (
                SELECT SUM(sp.total_price)
                FROM service_parts sp
                WHERE sp.service_id = sv.id
                  AND sp.used_status = 'Used'
            ),
            0
        ) AS parts_total

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

    WHERE 1 = 1
";

$params = [];
$types = "";

/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "
        AND (
            i.invoice_number LIKE ?
            OR c.customer_name LIKE ?
        )
    ";

    $searchValue = "%" . $search . "%";

    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= "ss";
}

/*
|--------------------------------------------------------------------------
| Type Filter
|--------------------------------------------------------------------------
*/

if ($type === "Sale") {

    $sql .= "
        AND i.sale_id IS NOT NULL
    ";

} elseif ($type === "Service") {

    $sql .= "
        AND i.service_id IS NOT NULL
    ";
}

/*
|--------------------------------------------------------------------------
| Fetch
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY i.id DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . htmlspecialchars($conn->error));
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();

$result = $stmt->get_result();

$invoices = [];

$displaySerial = 0;

while ($row = $result->fetch_assoc()) {
    $displaySerial++;


    if ($row['sale_id'] !== null) {

        $row['invoice_type'] = "Sale";

        $row['total'] =
            (float)$row['sale_total'];

        $row['paid'] =
            (float)$row['sale_paid'];

        $row['due'] =
            max(
                0,
                (float)$row['sale_total'] -
                (float)$row['sale_paid']
            );

    } else {

        $row['invoice_type'] = "Service";

        $row['total'] =
            (float)$row['service_charge'] +
            (float)$row['parts_total'];

        $row['paid'] =
            (float)$row['service_paid'];

        $row['due'] =
            max(
                0,
                $row['total'] -
                $row['paid']
            );
    }

    if ($row['due'] <= 0) {
        $row['payment_status'] = "Paid";
    } elseif ($row['paid'] > 0) {
        $row['payment_status'] = "Partial";
    } else {
        $row['payment_status'] = "Due";
    }

    $totalDue += $row['due'];

    $invoices[] = $row;
}

$stmt->close();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';

?>

<div class="content">

    <div class="container-fluid">

        <!-- Header -->

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h2 class="fw-bold mb-1">

                    <i class="bi bi-receipt me-2"></i>

                    Billing & Invoices

                </h2>

                <p class="text-muted mb-0">

                    Manage sales invoices, service invoices and payments.

                </p>

            </div>

        </div>


        <!-- Statistics -->

        <div class="row g-3 mb-4">

            <div class="col-md-3">

                <div class="dashboard-card">

                    <div class="card-icon">

                        <i class="bi bi-receipt"></i>

                    </div>

                    <div>

                        <h6>Total Invoices</h6>

                        <h3>
                            <?= number_format($totalInvoices); ?>
                        </h3>

                    </div>

                </div>

            </div>


            <div class="col-md-3">

                <div class="dashboard-card">

                    <div class="card-icon">

                        <i class="bi bi-cart-check"></i>

                    </div>

                    <div>

                        <h6>Sales Invoices</h6>

                        <h3>
                            <?= number_format($totalSalesInvoices); ?>
                        </h3>

                    </div>

                </div>

            </div>


            <div class="col-md-3">

                <div class="dashboard-card">

                    <div class="card-icon">

                        <i class="bi bi-tools"></i>

                    </div>

                    <div>

                        <h6>Service Invoices</h6>

                        <h3>
                            <?= number_format($totalServiceInvoices); ?>
                        </h3>

                    </div>

                </div>

            </div>


            <div class="col-md-3">

                <div class="dashboard-card">

                    <div class="card-icon">

                        <i class="bi bi-wallet2"></i>

                    </div>

                    <div>

                        <h6>Total Due</h6>

                        <h3>
                            ৳<?= number_format($totalDue, 2); ?>
                        </h3>

                    </div>

                </div>

            </div>

        </div>


        <!-- Search -->

        <div class="card shadow-sm border-0 mb-4">

            <div class="card-body">

                <form method="GET">

                    <div class="row g-3 align-items-end">

                        <div class="col-md-5">

                            <label class="form-label">
                                Search
                            </label>

                            <input
                                type="text"
                                name="search"
                                class="form-control"
                                placeholder="Invoice number or customer"
                                value="<?= htmlspecialchars($search); ?>"
                            >

                        </div>


                        <div class="col-md-3">

                            <label class="form-label">
                                Type
                            </label>

                            <select
                                name="type"
                                class="form-select">

                                <option value="">
                                    All
                                </option>

                                <option
                                    value="Sale"
                                    <?= $type === "Sale" ? "selected" : ""; ?>
                                >
                                    Sale
                                </option>

                                <option
                                    value="Service"
                                    <?= $type === "Service" ? "selected" : ""; ?>
                                >
                                    Service
                                </option>

                            </select>

                        </div>


                        <div class="col-md-2">

                            <button
                                type="submit"
                                class="btn btn-primary w-100">

                                <i class="bi bi-search me-1"></i>

                                Search

                            </button>

                        </div>


                        <div class="col-md-2">

                            <a
                                href="index.php"
                                class="btn btn-secondary w-100">

                                Reset

                            </a>

                        </div>

                    </div>

                </form>

            </div>

        </div>


        <!-- Invoice Table -->

        <div class="card shadow-sm border-0">

            <div class="card-header bg-white">

                <h5 class="mb-0">

                    <i class="bi bi-list-ul me-2"></i>

                    Invoice List

                </h5>

            </div>


            <div class="card-body p-0">

                <div class="table-responsive">

                    <table class="table table-hover mb-0">

                        <thead class="table-light">

                            <tr>

                                <th>Invoice</th>

                                <th>Customer</th>

                                <th>Type</th>

                                <th>Date</th>

                                <th>Total</th>

                                <th>Paid</th>

                                <th>Due</th>

                                <th>Status</th>

                                <th>Action</th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php if (empty($invoices)): ?>

                            <tr>

                                <td
                                    colspan="9"
                                    class="text-center py-5 text-muted">

                                    No invoices found.

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($invoices as $invoice): ?>

                                <tr>

                                    <td>

                                        <strong>

                                            <?= htmlspecialchars(
                                                $invoice['invoice_number']
                                            ); ?>

                                        </strong>

                                    </td>


                                    <td>

                                        <?= htmlspecialchars(
                                            $invoice['customer_name']
                                            ?: 'Walk-in Customer'
                                        ); ?>

                                    </td>


                                    <td>

                                        <?php if (
                                            $invoice['invoice_type'] === 'Sale'
                                        ): ?>

                                            <span class="badge bg-primary">

                                                <i class="bi bi-cart-check me-1"></i>

                                                Sale

                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-info text-dark">

                                                <i class="bi bi-tools me-1"></i>

                                                Service

                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <?= date(
                                            "d M Y, h:i A",
                                            strtotime(
                                                $invoice['invoice_date']
                                            )
                                        ); ?>

                                    </td>


                                    <td>

                                        <strong>

                                            ৳<?= number_format(
                                                $invoice['total'],
                                                2
                                            ); ?>

                                        </strong>

                                    </td>


                                    <td>

                                        <span class="text-success">

                                            ৳<?= number_format(
                                                $invoice['paid'],
                                                2
                                            ); ?>

                                        </span>

                                    </td>


                                    <td>

                                        <?php if ($invoice['due'] > 0): ?>

                                            <span class="text-danger fw-semibold">

                                                ৳<?= number_format(
                                                    $invoice['due'],
                                                    2
                                                ); ?>

                                            </span>

                                        <?php else: ?>

                                            <span class="text-muted">
                                                ৳0.00
                                            </span>

                                        <?php endif; ?>

                                    </td>


                                    <td>

                                        <?php

                                        $badge =
                                            "bg-secondary";

                                        if (
                                            $invoice['payment_status']
                                            === "Paid"
                                        ) {
                                            $badge =
                                                "bg-success";
                                        } elseif (
                                            $invoice['payment_status']
                                            === "Partial"
                                        ) {
                                            $badge =
                                                "bg-warning text-dark";
                                        } elseif (
                                            $invoice['payment_status']
                                            === "Due"
                                        ) {
                                            $badge =
                                                "bg-danger";
                                        }

                                        ?>

                                        <span class="badge <?= $badge; ?>">

                                            <?= htmlspecialchars(
                                                $invoice['payment_status']
                                            ); ?>

                                        </span>

                                    </td>


                                    <td>

                                        <div class="d-flex gap-1">

                                           <a
    href="delete.php?id=<?= (int)$invoice['id']; ?>"
    class="btn btn-sm btn-outline-danger"
    title="Delete"
    onclick="return confirm(
        'Are you sure you want to delete this invoice?\\n\\nThe sale/service will NOT be deleted.'
    );">

    <i class="bi bi-trash"></i>

</a>


                                            <?php if (
                                                $invoice['due'] > 0
                                            ): ?>

                                                <a
                                                    href="payment.php?id=<?= (int)$invoice['id']; ?>"
                                                    class="btn btn-sm btn-outline-success"
                                                    title="Payment">

                                                    <i class="bi bi-cash-stack"></i>

                                                </a>

                                            <?php endif; ?>


                                            <a
                                                href="print.php?id=<?= (int)$invoice['id']; ?>"
                                                target="_blank"
                                                class="btn btn-sm btn-outline-secondary"
                                                title="Print">

                                                <i class="bi bi-printer"></i>

                                            </a>

                                        </div>

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