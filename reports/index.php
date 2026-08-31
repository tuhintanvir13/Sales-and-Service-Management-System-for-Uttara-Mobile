<?php

require_once __DIR__ . '/../config/database.php';

$basePath = "../";
$pageTitle = "Reports & Statistics";


/*
|--------------------------------------------------------------------------
| Date Filters
|--------------------------------------------------------------------------
*/

$fromDate = isset($_GET['from_date'])
    ? trim($_GET['from_date'])
    : '';

$toDate = isset($_GET['to_date'])
    ? trim($_GET['to_date'])
    : '';


/*
|--------------------------------------------------------------------------
| Date Conditions
|--------------------------------------------------------------------------
*/

$salesDateCondition = "";
$serviceDateCondition = "";

$salesParams = [];
$salesTypes = "";

$serviceParams = [];
$serviceTypes = "";


if ($fromDate !== '') {

    $salesDateCondition .= "
        AND DATE(sale_date) >= ?
    ";

    $salesParams[] = $fromDate;
    $salesTypes .= "s";


    $serviceDateCondition .= "
        AND DATE(received_date) >= ?
    ";

    $serviceParams[] = $fromDate;
    $serviceTypes .= "s";
}


if ($toDate !== '') {

    $salesDateCondition .= "
        AND DATE(sale_date) <= ?
    ";

    $salesParams[] = $toDate;
    $salesTypes .= "s";


    $serviceDateCondition .= "
        AND DATE(received_date) <= ?
    ";

    $serviceParams[] = $toDate;
    $serviceTypes .= "s";
}


/*
|--------------------------------------------------------------------------
| Helper Function
|--------------------------------------------------------------------------
*/

function getSingleValue(
    mysqli $conn,
    string $sql,
    string $types = "",
    array $params = []
) {

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return 0;
    }

    if (!empty($params)) {

        $stmt->bind_param(
            $types,
            ...$params
        );
    }

    $stmt->execute();

    $result = $stmt->get_result();

    $row = $result->fetch_assoc();

    $stmt->close();

    if (!$row) {
        return 0;
    }

    return array_values($row)[0] ?? 0;
}


/*
|--------------------------------------------------------------------------
| 1. Total Customers
|--------------------------------------------------------------------------
*/

$totalCustomers = (int) getSingleValue(
    $conn,
    "
        SELECT COUNT(*)
        FROM customers
    "
);


/*
|--------------------------------------------------------------------------
| 2. Total Products / Parts
|--------------------------------------------------------------------------
*/

$totalProducts = (int) getSingleValue(
    $conn,
    "
        SELECT COUNT(*)
        FROM products
        WHERE status = 'Active'
    "
);


/*
|--------------------------------------------------------------------------
| 3. Total Sales
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT COUNT(*)
    FROM sales
    WHERE 1=1
    {$salesDateCondition}
";

$totalSales = (int) getSingleValue(
    $conn,
    $sql,
    $salesTypes,
    $salesParams
);


/*
|--------------------------------------------------------------------------
| 4. Total Services
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT COUNT(*)
    FROM services
    WHERE 1=1
    {$serviceDateCondition}
";

$totalServices = (int) getSingleValue(
    $conn,
    $sql,
    $serviceTypes,
    $serviceParams
);


/*
|--------------------------------------------------------------------------
| 5. Sales Revenue
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT COALESCE(SUM(grand_total), 0)
    FROM sales
    WHERE 1=1
    {$salesDateCondition}
";

$totalSalesRevenue = (float) getSingleValue(
    $conn,
    $sql,
    $salesTypes,
    $salesParams
);


/*
|--------------------------------------------------------------------------
| 6. Sales Received
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT COALESCE(SUM(paid_amount), 0)
    FROM sales
    WHERE 1=1
    {$salesDateCondition}
";

$totalSalesReceived = (float) getSingleValue(
    $conn,
    $sql,
    $salesTypes,
    $salesParams
);


/*
|--------------------------------------------------------------------------
| 7. Sales Due
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT COALESCE(SUM(due_amount), 0)
    FROM sales
    WHERE 1=1
    {$salesDateCondition}
";

$totalSalesDue = (float) getSingleValue(
    $conn,
    $sql,
    $salesTypes,
    $salesParams
);


/*
|--------------------------------------------------------------------------
| 8. Service Revenue
|--------------------------------------------------------------------------
|
| Service revenue =
| Service Charge + Used Parts
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        COALESCE(SUM(
            s.service_charge
            +
            COALESCE(
                (
                    SELECT SUM(sp.total_price)
                    FROM service_parts sp
                    WHERE sp.service_id = s.id
                      AND sp.used_status = 'Used'
                ),
                0
            )
        ), 0)

    FROM services s

    WHERE 1=1

    {$serviceDateCondition}
";

$totalServiceRevenue = (float) getSingleValue(
    $conn,
    $sql,
    $serviceTypes,
    $serviceParams
);


/*
|--------------------------------------------------------------------------
| 9. Service Payments Received
|--------------------------------------------------------------------------
*/

if ($fromDate === '' && $toDate === '') {

    $totalServiceReceived = (float) getSingleValue(
        $conn,
        "
            SELECT COALESCE(
                SUM(amount),
                0
            )
            FROM payments
            WHERE service_id IS NOT NULL
        "
    );

} else {

    $paymentDateCondition = "";
    $paymentParams = [];
    $paymentTypes = "";


    if ($fromDate !== '') {

        $paymentDateCondition .= "
            AND DATE(payment_date) >= ?
        ";

        $paymentParams[] = $fromDate;
        $paymentTypes .= "s";
    }


    if ($toDate !== '') {

        $paymentDateCondition .= "
            AND DATE(payment_date) <= ?
        ";

        $paymentParams[] = $toDate;
        $paymentTypes .= "s";
    }


    $totalServiceReceived = (float) getSingleValue(
        $conn,
        "
            SELECT COALESCE(
                SUM(amount),
                0
            )
            FROM payments
            WHERE service_id IS NOT NULL
            {$paymentDateCondition}
        ",
        $paymentTypes,
        $paymentParams
    );
}


/*
|--------------------------------------------------------------------------
| 10. Service Due
|--------------------------------------------------------------------------
*/

$totalServiceDue =
    max(
        0,
        $totalServiceRevenue -
        $totalServiceReceived
    );


/*
|--------------------------------------------------------------------------
| 11. Overall Statistics
|--------------------------------------------------------------------------
*/

$totalRevenue =
    $totalSalesRevenue +
    $totalServiceRevenue;

$totalReceived =
    $totalSalesReceived +
    $totalServiceReceived;

$totalDue =
    $totalSalesDue +
    $totalServiceDue;


/*
|--------------------------------------------------------------------------
| 12. Today's Sales
|--------------------------------------------------------------------------
*/

$todaySales = (int) getSingleValue(
    $conn,
    "
        SELECT COUNT(*)
        FROM sales
        WHERE DATE(sale_date) = CURDATE()
    "
);


$todaySalesAmount = (float) getSingleValue(
    $conn,
    "
        SELECT COALESCE(
            SUM(grand_total),
            0
        )
        FROM sales
        WHERE DATE(sale_date) = CURDATE()
    "
);


/*
|--------------------------------------------------------------------------
| 13. Today's Services
|--------------------------------------------------------------------------
*/

$todayServices = (int) getSingleValue(
    $conn,
    "
        SELECT COUNT(*)
        FROM services
        WHERE DATE(received_date) = CURDATE()
    "
);


/*
|--------------------------------------------------------------------------
| 14. Sales Payment Status
|--------------------------------------------------------------------------
*/

$paidSales = (int) getSingleValue(
    $conn,
    "
        SELECT COUNT(*)
        FROM sales
        WHERE payment_status = 'Paid'
        {$salesDateCondition}
    ",
    $salesTypes,
    $salesParams
);


$partialSales = (int) getSingleValue(
    $conn,
    "
        SELECT COUNT(*)
        FROM sales
        WHERE payment_status = 'Partial'
        {$salesDateCondition}
    ",
    $salesTypes,
    $salesParams
);


$dueSales = (int) getSingleValue(
    $conn,
    "
        SELECT COUNT(*)
        FROM sales
        WHERE payment_status = 'Due'
        {$salesDateCondition}
    ",
    $salesTypes,
    $salesParams
);


/*
|--------------------------------------------------------------------------
| 15. Service Status Statistics
|--------------------------------------------------------------------------
*/

$serviceStatuses = [
    "Pending" => 0,
    "Under Inspection" => 0,
    "Waiting for Parts" => 0,
    "In Repair" => 0,
    "Completed" => 0,
    "Delivered" => 0,
    "Cancelled" => 0
];


$sql = "
    SELECT
        service_status,
        COUNT(*) AS total

    FROM services

    WHERE 1=1

    {$serviceDateCondition}

    GROUP BY service_status
";

$stmt = $conn->prepare($sql);

if ($stmt) {

    if (!empty($serviceParams)) {

        $stmt->bind_param(
            $serviceTypes,
            ...$serviceParams
        );
    }

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $serviceStatus =
            $row['service_status'];

        if (
            isset(
                $serviceStatuses[
                    $serviceStatus
                ]
            )
        ) {

            $serviceStatuses[
                $serviceStatus
            ] =
                (int)$row['total'];
        }
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| 16. Low Stock
|--------------------------------------------------------------------------
*/

$lowStockCount = (int) getSingleValue(
    $conn,
    "
        SELECT COUNT(*)
        FROM products
        WHERE status = 'Active'
          AND quantity > 0
          AND quantity <= minimum_stock
    "
);


/*
|--------------------------------------------------------------------------
| 17. Out of Stock
|--------------------------------------------------------------------------
*/

$outOfStockCount = (int) getSingleValue(
    $conn,
    "
        SELECT COUNT(*)
        FROM products
        WHERE status = 'Active'
          AND quantity <= 0
    "
);


/*
|--------------------------------------------------------------------------
| 18. Recent Sales
|--------------------------------------------------------------------------
*/

$recentSales = [];

$stmt = $conn->prepare("
    SELECT
        s.id,
        s.invoice_number,
        s.grand_total,
        s.paid_amount,
        s.due_amount,
        s.payment_status,
        s.sale_date,
        c.customer_name

    FROM sales s

    LEFT JOIN customers c
        ON c.id = s.customer_id

    ORDER BY s.sale_date DESC

    LIMIT 8
");

if ($stmt) {

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $recentSales[] = $row;
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| 19. Recent Services
|--------------------------------------------------------------------------
*/

$recentServices = [];

$stmt = $conn->prepare("
    SELECT
        s.id,
        s.device_brand,
        s.device_model,
        s.service_charge,
        s.service_status,
        s.received_date,
        c.customer_name

    FROM services s

    LEFT JOIN customers c
        ON c.id = s.customer_id

    ORDER BY s.received_date DESC

    LIMIT 8
");

if ($stmt) {

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $recentServices[] = $row;
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| 20. Payment Method Statistics
|--------------------------------------------------------------------------
*/

$paymentMethods = [];

$sql = "
    SELECT
        payment_method,
        COUNT(*) AS total_transactions,
        COALESCE(SUM(amount), 0) AS total_amount

    FROM payments

    WHERE 1=1
";


$paymentParams = [];
$paymentTypes = "";


if ($fromDate !== '') {

    $sql .= "
        AND DATE(payment_date) >= ?
    ";

    $paymentParams[] = $fromDate;
    $paymentTypes .= "s";
}


if ($toDate !== '') {

    $sql .= "
        AND DATE(payment_date) <= ?
    ";

    $paymentParams[] = $toDate;
    $paymentTypes .= "s";
}


$sql .= "
    GROUP BY payment_method

    ORDER BY total_amount DESC
";


$stmt = $conn->prepare($sql);

if ($stmt) {

    if (!empty($paymentParams)) {

        $stmt->bind_param(
            $paymentTypes,
            ...$paymentParams
        );
    }

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $paymentMethods[] = $row;
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| 21. Monthly Sales
|--------------------------------------------------------------------------
*/

$monthlySales = [];

$stmt = $conn->prepare("
    SELECT
        DATE_FORMAT(
            sale_date,
            '%Y-%m'
        ) AS month,

        COALESCE(
            SUM(grand_total),
            0
        ) AS total

    FROM sales

    WHERE sale_date >= DATE_SUB(
        CURDATE(),
        INTERVAL 5 MONTH
    )

    GROUP BY
        DATE_FORMAT(
            sale_date,
            '%Y-%m'
        )

    ORDER BY month ASC
");

if ($stmt) {

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $monthlySales[] = $row;
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Page
|--------------------------------------------------------------------------
*/

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

                    <i class="bi bi-bar-chart-line me-2"></i>

                    Reports & Dashboard

                </h2>

                <p class="text-muted mb-0">

                    Real-time statistics from the Uttara Mobile database.

                </p>

            </div>

        </div>


        <!-- Date Filter -->

        <div class="card shadow-sm border-0 mb-4">

            <div class="card-body">

                <form method="GET">

                    <div class="row g-3 align-items-end">

                        <div class="col-md-4">

                            <label class="form-label">
                                From Date
                            </label>

                            <input
                                type="date"
                                name="from_date"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $fromDate
                                ); ?>"
                            >

                        </div>


                        <div class="col-md-4">

                            <label class="form-label">
                                To Date
                            </label>

                            <input
                                type="date"
                                name="to_date"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $toDate
                                ); ?>"
                            >

                        </div>


                        <div class="col-md-2">

                            <button
                                type="submit"
                                class="btn btn-primary w-100">

                                <i class="bi bi-filter me-1"></i>

                                Apply

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


        <!-- Main Statistics -->

        <div class="row g-4 mb-4">

            <div class="col-md-6 col-xl-3">

                <div class="dashboard-card">

                    <div class="card-icon">

                        <i class="bi bi-people"></i>

                    </div>

                    <div>

                        <h6>Total Customers</h6>

                        <h3>
                            <?= number_format(
                                $totalCustomers
                            ); ?>
                        </h3>

                    </div>

                </div>

            </div>


            <div class="col-md-6 col-xl-3">

                <div class="dashboard-card">

                    <div class="card-icon">

                        <i class="bi bi-box-seam"></i>

                    </div>

                    <div>

                        <h6>Products / Parts</h6>

                        <h3>
                            <?= number_format(
                                $totalProducts
                            ); ?>
                        </h3>

                    </div>

                </div>

            </div>


            <div class="col-md-6 col-xl-3">

                <div class="dashboard-card">

                    <div class="card-icon">

                        <i class="bi bi-cart-check"></i>

                    </div>

                    <div>

                        <h6>Total Sales</h6>

                        <h3>
                            <?= number_format(
                                $totalSales
                            ); ?>
                        </h3>

                    </div>

                </div>

            </div>


            <div class="col-md-6 col-xl-3">

                <div class="dashboard-card">

                    <div class="card-icon">

                        <i class="bi bi-tools"></i>

                    </div>

                    <div>

                        <h6>Total Services</h6>

                        <h3>
                            <?= number_format(
                                $totalServices
                            ); ?>
                        </h3>

                    </div>

                </div>

            </div>

        </div>


        <!-- Financial Statistics -->

        <div class="row g-4 mb-4">

            <div class="col-md-6 col-xl-3">

                <div class="card shadow-sm border-0 h-100">

                    <div class="card-body">

                        <small class="text-muted">
                            Total Revenue
                        </small>

                        <h3 class="fw-bold mt-2">

                            ৳<?= number_format(
                                $totalRevenue,
                                2
                            ); ?>

                        </h3>

                        <small class="text-muted">

                            Sales + Services

                        </small>

                    </div>

                </div>

            </div>


            <div class="col-md-6 col-xl-3">

                <div class="card shadow-sm border-0 h-100">

                    <div class="card-body">

                        <small class="text-muted">
                            Total Received
                        </small>

                        <h3 class="fw-bold text-success mt-2">

                            ৳<?= number_format(
                                $totalReceived,
                                2
                            ); ?>

                        </h3>

                        <small class="text-muted">

                            Payments received

                        </small>

                    </div>

                </div>

            </div>


            <div class="col-md-6 col-xl-3">

                <div class="card shadow-sm border-0 h-100">

                    <div class="card-body">

                        <small class="text-muted">
                            Total Due
                        </small>

                        <h3 class="fw-bold text-danger mt-2">

                            ৳<?= number_format(
                                $totalDue,
                                2
                            ); ?>

                        </h3>

                        <small class="text-muted">

                            Outstanding amount

                        </small>

                    </div>

                </div>

            </div>


            <div class="col-md-6 col-xl-3">

                <div class="card shadow-sm border-0 h-100">

                    <div class="card-body">

                        <small class="text-muted">
                            Low Stock
                        </small>

                        <h3 class="fw-bold text-warning mt-2">

                            <?= number_format(
                                $lowStockCount
                            ); ?>

                        </h3>

                        <small class="text-muted">

                            Items need attention

                        </small>

                    </div>

                </div>

            </div>

        </div>


        <!-- Today -->

        <div class="row g-4 mb-4">

            <div class="col-md-4">

                <div class="card shadow-sm border-0">

                    <div class="card-body">

                        <h6 class="text-muted">
                            Today's Sales
                        </h6>

                        <h3 class="fw-bold">

                            <?= number_format(
                                $todaySales
                            ); ?>

                        </h3>

                        <div class="text-success">

                            ৳<?= number_format(
                                $todaySalesAmount,
                                2
                            ); ?>

                        </div>

                    </div>

                </div>

            </div>


            <div class="col-md-4">

                <div class="card shadow-sm border-0">

                    <div class="card-body">

                        <h6 class="text-muted">
                            Today's Services
                        </h6>

                        <h3 class="fw-bold">

                            <?= number_format(
                                $todayServices
                            ); ?>

                        </h3>

                        <div class="text-muted">

                            Service orders received today

                        </div>

                    </div>

                </div>

            </div>


            <div class="col-md-4">

                <div class="card shadow-sm border-0">

                    <div class="card-body">

                        <h6 class="text-muted">
                            Out of Stock
                        </h6>

                        <h3 class="fw-bold text-danger">

                            <?= number_format(
                                $outOfStockCount
                            ); ?>

                        </h3>

                        <div class="text-muted">

                            Active products with zero stock

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- Sales Payment Status + Service Status -->

        <div class="row g-4 mb-4">

            <div class="col-lg-5">

                <div class="card shadow-sm border-0 h-100">

                    <div class="card-header bg-white">

                        <h5 class="mb-0">

                            <i class="bi bi-wallet2 me-2"></i>

                            Sales Payment Status

                        </h5>

                    </div>


                    <div class="card-body">

                        <div class="row g-3">

                            <div class="col-4">

                                <div class="text-center">

                                    <h3 class="text-success">

                                        <?= $paidSales; ?>

                                    </h3>

                                    <small>
                                        Paid
                                    </small>

                                </div>

                            </div>


                            <div class="col-4">

                                <div class="text-center">

                                    <h3 class="text-warning">

                                        <?= $partialSales; ?>

                                    </h3>

                                    <small>
                                        Partial
                                    </small>

                                </div>

                            </div>


                            <div class="col-4">

                                <div class="text-center">

                                    <h3 class="text-danger">

                                        <?= $dueSales; ?>

                                    </h3>

                                    <small>
                                        Due
                                    </small>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <div class="col-lg-7">

                <div class="card shadow-sm border-0 h-100">

                    <div class="card-header bg-white">

                        <h5 class="mb-0">

                            <i class="bi bi-tools me-2"></i>

                            Service Status

                        </h5>

                    </div>


                    <div class="card-body">

                        <div class="row g-3">

                            <?php foreach (
                                $serviceStatuses
                                as $serviceStatus => $count
                            ): ?>

                                <div class="col-6 col-md-4">

                                    <div class="border rounded p-3">

                                        <div class="small text-muted">

                                            <?= htmlspecialchars(
                                                $serviceStatus
                                            ); ?>

                                        </div>

                                        <div class="fs-4 fw-bold">

                                            <?= number_format(
                                                $count
                                            ); ?>

                                        </div>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- Revenue Breakdown -->

        <div class="row g-4 mb-4">

            <div class="col-lg-6">

                <div class="card shadow-sm border-0">

                    <div class="card-header bg-white">

                        <h5 class="mb-0">

                            <i class="bi bi-graph-up me-2"></i>

                            Revenue Breakdown

                        </h5>

                    </div>


                    <div class="card-body">

                        <div class="mb-4">

                            <div class="d-flex justify-content-between mb-2">

                                <span>
                                    Sales Revenue
                                </span>

                                <strong>

                                    ৳<?= number_format(
                                        $totalSalesRevenue,
                                        2
                                    ); ?>

                                </strong>

                            </div>

                            <div
                                class="progress"
                                style="height: 10px;">

                                <?php

                                $salesPercentage =
                                    $totalRevenue > 0
                                        ? (
                                            $totalSalesRevenue /
                                            $totalRevenue
                                        ) * 100
                                        : 0;

                                ?>

                                <div
                                    class="progress-bar"
                                    style="width: <?= $salesPercentage; ?>%;">
                                </div>

                            </div>

                        </div>


                        <div>

                            <div class="d-flex justify-content-between mb-2">

                                <span>
                                    Service Revenue
                                </span>

                                <strong>

                                    ৳<?= number_format(
                                        $totalServiceRevenue,
                                        2
                                    ); ?>

                                </strong>

                            </div>

                            <div
                                class="progress"
                                style="height: 10px;">

                                <?php

                                $servicePercentage =
                                    $totalRevenue > 0
                                        ? (
                                            $totalServiceRevenue /
                                            $totalRevenue
                                        ) * 100
                                        : 0;

                                ?>

                                <div
                                    class="progress-bar"
                                    style="width: <?= $servicePercentage; ?>%;">
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- Payment Methods -->

            <div class="col-lg-6">

                <div class="card shadow-sm border-0">

                    <div class="card-header bg-white">

                        <h5 class="mb-0">

                            <i class="bi bi-credit-card me-2"></i>

                            Payment Methods

                        </h5>

                    </div>


                    <div class="card-body p-0">

                        <div class="table-responsive">

                            <table class="table table-hover mb-0">

                                <thead class="table-light">

                                    <tr>

                                        <th>
                                            Method
                                        </th>

                                        <th>
                                            Transactions
                                        </th>

                                        <th>
                                            Amount
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>

                                <?php if (
                                    empty($paymentMethods)
                                ): ?>

                                    <tr>

                                        <td
                                            colspan="3"
                                            class="text-center text-muted py-4">

                                            No payment records found.

                                        </td>

                                    </tr>

                                <?php else: ?>

                                    <?php foreach (
                                        $paymentMethods
                                        as $method
                                    ): ?>

                                        <tr>

                                            <td>

                                                <?= htmlspecialchars(
                                                    $method[
                                                        'payment_method'
                                                    ]
                                                ); ?>

                                            </td>

                                            <td>

                                                <?= number_format(
                                                    $method[
                                                        'total_transactions'
                                                    ]
                                                ); ?>

                                            </td>

                                            <td class="fw-semibold">

                                                ৳<?= number_format(
                                                    $method[
                                                        'total_amount'
                                                    ],
                                                    2
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


        <!-- Recent Sales -->

        <div class="row g-4 mb-4">

            <div class="col-lg-7">

                <div class="card shadow-sm border-0">

                    <div class="card-header bg-white">

                        <h5 class="mb-0">

                            <i class="bi bi-cart-check me-2"></i>

                            Recent Sales

                        </h5>

                    </div>


                    <div class="card-body p-0">

                        <div class="table-responsive">

                            <table class="table table-hover mb-0">

                                <thead class="table-light">

                                    <tr>

                                        <th>
                                            Invoice
                                        </th>

                                        <th>
                                            Customer
                                        </th>

                                        <th>
                                            Total
                                        </th>

                                        <th>
                                            Status
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>

                                <?php if (
                                    empty($recentSales)
                                ): ?>

                                    <tr>

                                        <td
                                            colspan="4"
                                            class="text-center text-muted py-4">

                                            No sales records found.

                                        </td>

                                    </tr>

                                <?php else: ?>

                                    <?php foreach (
                                        $recentSales
                                        as $sale
                                    ): ?>

                                        <tr>

                                            <td>

                                                <strong>

                                                    <?= htmlspecialchars(
                                                        $sale[
                                                            'invoice_number'
                                                        ]
                                                    ); ?>

                                                </strong>

                                                <div class="small text-muted">

                                                    <?= date(
                                                        "d M Y",
                                                        strtotime(
                                                            $sale[
                                                                'sale_date'
                                                            ]
                                                        )
                                                    ); ?>

                                                </div>

                                            </td>


                                            <td>

                                                <?= htmlspecialchars(
                                                    $sale[
                                                        'customer_name'
                                                    ]
                                                    ?: "Walk-in"
                                                ); ?>

                                            </td>


                                            <td>

                                                ৳<?= number_format(
                                                    $sale[
                                                        'grand_total'
                                                    ],
                                                    2
                                                ); ?>

                                            </td>


                                            <td>

                                                <?php

                                                $status =
                                                    $sale[
                                                        'payment_status'
                                                    ];

                                                $badge =
                                                    "bg-secondary";

                                                if (
                                                    $status === "Paid"
                                                ) {

                                                    $badge =
                                                        "bg-success";

                                                } elseif (
                                                    $status === "Partial"
                                                ) {

                                                    $badge =
                                                        "bg-warning text-dark";

                                                } elseif (
                                                    $status === "Due"
                                                ) {

                                                    $badge =
                                                        "bg-danger";
                                                }

                                                ?>

                                                <span
                                                    class="badge <?= $badge; ?>">

                                                    <?= htmlspecialchars(
                                                        $status
                                                    ); ?>

                                                </span>

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


            <!-- Recent Services -->

            <div class="col-lg-5">

                <div class="card shadow-sm border-0">

                    <div class="card-header bg-white">

                        <h5 class="mb-0">

                            <i class="bi bi-tools me-2"></i>

                            Recent Services

                        </h5>

                    </div>


                    <div class="card-body p-0">

                        <div class="table-responsive">

                            <table class="table table-hover mb-0">

                                <thead class="table-light">

                                    <tr>

                                        <th>
                                            Customer
                                        </th>

                                        <th>
                                            Device
                                        </th>

                                        <th>
                                            Status
                                        </th>

                                    </tr>

                                </thead>


                                <tbody>

                                <?php if (
                                    empty($recentServices)
                                ): ?>

                                    <tr>

                                        <td
                                            colspan="3"
                                            class="text-center text-muted py-4">

                                            No service records found.

                                        </td>

                                    </tr>

                                <?php else: ?>

                                    <?php foreach (
                                        $recentServices
                                        as $service
                                    ): ?>

                                        <tr>

                                            <td>

                                                <?= htmlspecialchars(
                                                    $service[
                                                        'customer_name'
                                                    ]
                                                    ?: "Walk-in"
                                                ); ?>

                                                <div class="small text-muted">

                                                    <?= date(
                                                        "d M Y",
                                                        strtotime(
                                                            $service[
                                                                'received_date'
                                                            ]
                                                        )
                                                    ); ?>

                                                </div>

                                            </td>


                                            <td>

                                                <?= htmlspecialchars(
                                                    $service[
                                                        'device_brand'
                                                    ]
                                                ); ?>

                                                <?= htmlspecialchars(
                                                    $service[
                                                        'device_model'
                                                    ]
                                                ); ?>

                                            </td>


                                            <td>

                                                <span class="badge bg-secondary">

                                                    <?= htmlspecialchars(
                                                        $service[
                                                            'service_status'
                                                        ]
                                                    ); ?>

                                                </span>

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


        <!-- Monthly Sales -->

        <div class="card shadow-sm border-0 mb-4">

            <div class="card-header bg-white">

                <h5 class="mb-0">

                    <i class="bi bi-calendar3 me-2"></i>

                    Sales — Last 6 Months

                </h5>

            </div>


            <div class="card-body">

                <?php if (
                    empty($monthlySales)
                ): ?>

                    <p class="text-muted text-center mb-0">

                        No sales data available.

                    </p>

                <?php else: ?>

                    <?php

                    $maxMonthlySales = 0;

                    foreach (
                        $monthlySales
                        as $month
                    ) {

                        $maxMonthlySales =
                            max(
                                $maxMonthlySales,
                                (float)$month['total']
                            );
                    }

                    ?>


                    <?php foreach (
                        $monthlySales
                        as $month
                    ): ?>

                        <?php

                        $percentage =
                            $maxMonthlySales > 0
                                ? (
                                    (
                                        (float)$month[
                                            'total'
                                        ]
                                        /
                                        $maxMonthlySales
                                    ) * 100
                                )
                                : 0;

                        ?>

                        <div class="mb-3">

                            <div class="d-flex justify-content-between mb-1">

                                <span>

                                    <?= htmlspecialchars(
                                        date(
                                            "M Y",
                                            strtotime(
                                                $month['month']
                                                . "-01"
                                            )
                                        )
                                    ); ?>

                                </span>

                                <strong>

                                    ৳<?= number_format(
                                        $month['total'],
                                        2
                                    ); ?>

                                </strong>

                            </div>


                            <div
                                class="progress"
                                style="height: 12px;">

                                <div
                                    class="progress-bar"
                                    style="width: <?= $percentage; ?>%;">
                                </div>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>