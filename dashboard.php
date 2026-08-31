<?php

$basePath = "";
$pageTitle = "Dashboard";

require_once "includes/header.php";
require_once "config/database.php";

require_once "includes/navbar.php";
require_once "includes/sidebar.php";


/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
| All dashboard figures below are calculated directly from the existing
| Uttara Mobile MySQL database. No values are hard-coded.
|--------------------------------------------------------------------------
*/


/*
 * Helper for single numeric database values.
 */
function dashboardValue($conn, $sql)
{
    $result = $conn->query($sql);

    if (!$result) {
        return 0;
    }

    $row = $result->fetch_assoc();

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

$totalCustomers = (int) dashboardValue(
    $conn,
    "SELECT COUNT(*) AS total FROM customers"
);


/*
|--------------------------------------------------------------------------
| 2. Total Active Products / Parts
|--------------------------------------------------------------------------
| The Products and Stock modules use status = 'Active', so the Dashboard
| follows the same existing definition.
|--------------------------------------------------------------------------
*/

$totalProducts = (int) dashboardValue(
    $conn,
    "
        SELECT COUNT(*) AS total
        FROM products
        WHERE status = 'Active'
    "
);


/*
|--------------------------------------------------------------------------
| 3. Total Sales
|--------------------------------------------------------------------------
*/

$totalSales = (int) dashboardValue(
    $conn,
    "SELECT COUNT(*) AS total FROM sales"
);


/*
|--------------------------------------------------------------------------
| 4. Total Services / Service Orders
|--------------------------------------------------------------------------
*/

$totalServices = (int) dashboardValue(
    $conn,
    "SELECT COUNT(*) AS total FROM services"
);


/*
|--------------------------------------------------------------------------
| Additional Business Statistics
|--------------------------------------------------------------------------
*/

$todaySales = (int) dashboardValue(
    $conn,
    "
        SELECT COUNT(*) AS total
        FROM sales
        WHERE DATE(sale_date) = CURDATE()
    "
);


$todayServices = (int) dashboardValue(
    $conn,
    "
        SELECT COUNT(*) AS total
        FROM services
        WHERE DATE(received_date) = CURDATE()
    "
);


$totalRevenue = (float) dashboardValue(
    $conn,
    "
        SELECT COALESCE(SUM(grand_total), 0) AS total
        FROM sales
    "
);


$totalSalesDue = (float) dashboardValue(
    $conn,
    "
        SELECT COALESCE(SUM(due_amount), 0) AS total
        FROM sales
        WHERE due_amount > 0
    "
);


/*
|--------------------------------------------------------------------------
| Service Revenue
|--------------------------------------------------------------------------
| Existing Reports module defines service revenue as:
| service charge + the total price of parts marked as Used.
|--------------------------------------------------------------------------
*/

$totalServiceRevenue = (float) dashboardValue(
    $conn,
    "
        SELECT
            COALESCE(
                SUM(
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
                ),
                0
            ) AS total
        FROM services s
    "
);


$totalRevenue += $totalServiceRevenue;


/*
|--------------------------------------------------------------------------
| Service Payments / Overall Due
|--------------------------------------------------------------------------
*/

$totalServiceReceived = (float) dashboardValue(
    $conn,
    "
        SELECT COALESCE(SUM(amount), 0) AS total
        FROM payments
        WHERE service_id IS NOT NULL
    "
);


$totalServiceDue = max(
    0,
    $totalServiceRevenue - $totalServiceReceived
);


$totalDue = $totalSalesDue + $totalServiceDue;


/*
|--------------------------------------------------------------------------
| Low Stock / Out of Stock
|--------------------------------------------------------------------------
*/

$lowStockCount = (int) dashboardValue(
    $conn,
    "
        SELECT COUNT(*) AS total
        FROM products
        WHERE status = 'Active'
          AND quantity > 0
          AND quantity <= minimum_stock
    "
);


$outOfStockCount = (int) dashboardValue(
    $conn,
    "
        SELECT COUNT(*) AS total
        FROM products
        WHERE status = 'Active'
          AND quantity <= 0
    "
);


/*
|--------------------------------------------------------------------------
| Recent Sales
|--------------------------------------------------------------------------
*/

$recentSales = [];

$stmt = $conn->prepare(
    "
        SELECT
            s.id,
            s.invoice_number,
            s.grand_total,
            s.sale_date,
            c.customer_name

        FROM sales s

        LEFT JOIN customers c
            ON c.id = s.customer_id

        ORDER BY
            s.sale_date DESC,
            s.id DESC

        LIMIT 5
    "
);

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
| Recent Services
|--------------------------------------------------------------------------
*/

$recentServices = [];

$stmt = $conn->prepare(
    "
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

        ORDER BY
            s.received_date DESC,
            s.id DESC

        LIMIT 5
    "
);

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
| Low Stock Items
|--------------------------------------------------------------------------
*/

$lowStockItems = [];

$stmt = $conn->prepare(
    "
        SELECT
            id,
            product_name,
            item_type,
            quantity,
            minimum_stock

        FROM products

        WHERE status = 'Active'
          AND quantity <= minimum_stock

        ORDER BY
            quantity ASC,
            product_name ASC

        LIMIT 8
    "
);

if ($stmt) {

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $lowStockItems[] = $row;
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Build Recent Activity
|--------------------------------------------------------------------------
*/

$recentActivity = [];

foreach ($recentSales as $sale) {

    $recentActivity[] = [
        "type" => "Sale",
        "date" => $sale["sale_date"],
        "title" => "Sale #" . $sale["invoice_number"],
        "customer" => $sale["customer_name"] ?: "Walk-in / Unknown Customer",
        "amount" => (float) $sale["grand_total"],
        "link" => "sales/view.php?id=" . (int) $sale["id"]
    ];
}


foreach ($recentServices as $service) {

    $device = trim(
        ($service["device_brand"] ?? "") .
        " " .
        ($service["device_model"] ?? "")
    );

    $recentActivity[] = [
        "type" => "Service",
        "date" => $service["received_date"],
        "title" => $device !== ""
            ? $device
            : "Service Order #" . (int) $service["id"],
        "customer" => $service["customer_name"] ?: "Unknown Customer",
        "amount" => (float) $service["service_charge"],
        "status" => $service["service_status"] ?? "",
        "link" => "services/view.php?id=" . (int) $service["id"]
    ];
}


usort(
    $recentActivity,
    function ($a, $b) {

        $dateA = strtotime($a["date"] ?? "");
        $dateB = strtotime($b["date"] ?? "");

        if ($dateA === $dateB) {
            return 0;
        }

        return ($dateA < $dateB) ? 1 : -1;
    }
);


$recentActivity = array_slice(
    $recentActivity,
    0,
    8
);

?>


<div class="content">

    <div class="container-fluid">

        <!-- ========================================================= -->
        <!-- PAGE HEADER -->
        <!-- ========================================================= -->

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h2 class="fw-bold">
                    Dashboard
                </h2>

                <p class="text-muted mb-0">
                    Welcome to Uttara Mobile Sales & Service Management System.
                </p>

            </div>

        </div>


        <!-- ========================================================= -->
        <!-- MAIN STATISTICS -->
        <!-- ========================================================= -->

        <div class="row g-4">

            <!-- Customers -->

            <div class="col-md-6 col-xl-3">

                <div class="dashboard-card">

                    <div class="card-icon">
                        <i class="bi bi-people"></i>
                    </div>

                    <div>

                        <h6>Total Customers</h6>

                        <h3>
                            <?php echo number_format($totalCustomers); ?>
                        </h3>

                    </div>

                </div>

            </div>


            <!-- Products -->

            <div class="col-md-6 col-xl-3">

                <div class="dashboard-card">

                    <div class="card-icon">
                        <i class="bi bi-box-seam"></i>
                    </div>

                    <div>

                        <h6>Total Products/Parts</h6>

                        <h3>
                            <?php echo number_format($totalProducts); ?>
                        </h3>

                    </div>

                </div>

            </div>


            <!-- Sales -->

            <div class="col-md-6 col-xl-3">

                <div class="dashboard-card">

                    <div class="card-icon">
                        <i class="bi bi-cart-check"></i>
                    </div>

                    <div>

                        <h6>Total Sales</h6>

                        <h3>
                            <?php echo number_format($totalSales); ?>
                        </h3>

                    </div>

                </div>

            </div>


            <!-- Services -->

            <div class="col-md-6 col-xl-3">

                <div class="dashboard-card">

                    <div class="card-icon">
                        <i class="bi bi-tools"></i>
                    </div>

                    <div>

                        <h6>Total Services</h6>

                        <h3>
                            <?php echo number_format($totalServices); ?>
                        </h3>

                    </div>

                </div>

            </div>

        </div>


        <!-- ========================================================= -->
        <!-- ADDITIONAL BUSINESS STATISTICS -->
        <!-- ========================================================= -->

        <div class="row g-4 mt-1">

            <!-- Total Revenue -->

            <div class="col-md-6 col-xl-3">

                <div class="card shadow-sm border-0 h-100">

                    <div class="card-body">

                        <p class="text-muted mb-1">
                            Total Revenue
                        </p>

                        <h4 class="fw-bold mb-1">
                            ৳<?php echo number_format($totalRevenue, 2); ?>
                        </h4>

                        <small class="text-muted">
                            Sales + service revenue
                        </small>

                    </div>

                </div>

            </div>


            <!-- Total Due -->

            <div class="col-md-6 col-xl-3">

                <div class="card shadow-sm border-0 h-100">

                    <div class="card-body">

                        <p class="text-muted mb-1">
                            Total Due
                        </p>

                        <h4 class="fw-bold mb-1">
                            ৳<?php echo number_format($totalDue, 2); ?>
                        </h4>

                        <small class="text-muted">
                            Outstanding sales and service dues
                        </small>

                    </div>

                </div>

            </div>


            <!-- Today's Sales -->

            <div class="col-md-6 col-xl-3">

                <div class="card shadow-sm border-0 h-100">

                    <div class="card-body">

                        <p class="text-muted mb-1">
                            Today's Sales
                        </p>

                        <h4 class="fw-bold mb-1">
                            <?php echo number_format($todaySales); ?>
                        </h4>

                        <small class="text-muted">
                            Sales recorded today
                        </small>

                    </div>

                </div>

            </div>


            <!-- Today's Services -->

            <div class="col-md-6 col-xl-3">

                <div class="card shadow-sm border-0 h-100">

                    <div class="card-body">

                        <p class="text-muted mb-1">
                            Today's Services
                        </p>

                        <h4 class="fw-bold mb-1">
                            <?php echo number_format($todayServices); ?>
                        </h4>

                        <small class="text-muted">
                            Service orders received today
                        </small>

                    </div>

                </div>

            </div>

        </div>


        <!-- ========================================================= -->
        <!-- RECENT ACTIVITY + LOW STOCK -->
        <!-- ========================================================= -->

        <div class="row mt-4 g-4">

            <!-- Recent Activity -->

            <div class="col-lg-8">

                <div class="card shadow-sm border-0 h-100">

                    <div class="card-body">

                        <div class="d-flex justify-content-between align-items-center mb-3">

                            <div>

                                <h5 class="card-title mb-1">
                                    Recent Activity
                                </h5>

                                <p class="text-muted mb-0">
                                    Latest sales and service activities.
                                </p>

                            </div>

                            <a
                                href="reports/index.php"
                                class="btn btn-sm btn-outline-primary">

                                Reports

                            </a>

                        </div>


                        <?php if (!empty($recentActivity)): ?>

                            <div class="table-responsive">

                                <table class="table table-hover align-middle mb-0">

                                    <thead class="table-light">

                                        <tr>

                                            <th>Type</th>

                                            <th>Activity</th>

                                            <th>Customer</th>

                                            <th>Date</th>

                                            <th class="text-end">
                                                Amount
                                            </th>

                                        </tr>

                                    </thead>

                                    <tbody>

                                    <?php foreach ($recentActivity as $activity): ?>

                                        <tr>

                                            <td>

                                                <?php if ($activity["type"] === "Sale"): ?>

                                                    <span class="badge bg-primary">
                                                        Sale
                                                    </span>

                                                <?php else: ?>

                                                    <span class="badge bg-info text-dark">
                                                        Service
                                                    </span>

                                                <?php endif; ?>

                                            </td>


                                            <td>

                                                <a
                                                    href="<?php echo htmlspecialchars($activity["link"]); ?>"
                                                    class="text-decoration-none fw-semibold">

                                                    <?php
                                                    echo htmlspecialchars(
                                                        $activity["title"]
                                                    );
                                                    ?>

                                                </a>

                                                <?php if (
                                                    isset($activity["status"]) &&
                                                    $activity["status"] !== ""
                                                ): ?>

                                                    <br>

                                                    <small class="text-muted">
                                                        <?php
                                                        echo htmlspecialchars(
                                                            $activity["status"]
                                                        );
                                                        ?>
                                                    </small>

                                                <?php endif; ?>

                                            </td>


                                            <td>

                                                <?php
                                                echo htmlspecialchars(
                                                    $activity["customer"]
                                                );
                                                ?>

                                            </td>


                                            <td>

                                                <?php

                                                $activityDate =
                                                    strtotime(
                                                        $activity["date"]
                                                    );

                                                echo $activityDate
                                                    ? date(
                                                        "d M Y",
                                                        $activityDate
                                                    )
                                                    : "N/A";

                                                ?>

                                            </td>


                                            <td class="text-end">

                                                ৳<?php
                                                echo number_format(
                                                    $activity["amount"],
                                                    2
                                                );
                                                ?>

                                            </td>

                                        </tr>

                                    <?php endforeach; ?>

                                    </tbody>

                                </table>

                            </div>

                        <?php else: ?>

                            <div class="text-center text-muted py-5">

                                <i class="bi bi-activity fs-2 d-block mb-2"></i>

                                No sales or service activity found.

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            </div>


            <!-- Low Stock -->

            <div class="col-lg-4">

                <div class="card shadow-sm border-0 h-100">

                    <div class="card-body">

                        <div class="d-flex justify-content-between align-items-center mb-3">

                            <div>

                                <h5 class="card-title mb-1">
                                    Low Stock
                                </h5>

                                <p class="text-muted mb-0">
                                    Inventory requiring attention.
                                </p>

                            </div>

                            <span class="badge bg-warning text-dark">
                                <?php echo number_format($lowStockCount); ?>
                            </span>

                        </div>


                        <?php if (!empty($lowStockItems)): ?>

                            <div class="list-group list-group-flush">

                                <?php foreach ($lowStockItems as $item): ?>

                                    <a
                                        href="products/view.php?id=<?php echo (int)$item["id"]; ?>"
                                        class="list-group-item list-group-item-action px-0">

                                        <div class="d-flex justify-content-between align-items-center">

                                            <div class="me-2">

                                                <div class="fw-semibold">

                                                    <?php
                                                    echo htmlspecialchars(
                                                        $item["product_name"]
                                                    );
                                                    ?>

                                                </div>

                                                <small class="text-muted">

                                                    <?php
                                                    echo htmlspecialchars(
                                                        $item["item_type"]
                                                    );
                                                    ?>

                                                </small>

                                            </div>


                                            <div class="text-end">

                                                <span class="badge <?php
                                                    echo (int)$item["quantity"] <= 0
                                                        ? "bg-danger"
                                                        : "bg-warning text-dark";
                                                ?>">

                                                    <?php
                                                    echo (int)$item["quantity"];
                                                    ?>

                                                </span>

                                                <small class="d-block text-muted">
                                                    Min:
                                                    <?php
                                                    echo (int)$item["minimum_stock"];
                                                    ?>
                                                </small>

                                            </div>

                                        </div>

                                    </a>

                                <?php endforeach; ?>

                            </div>

                        <?php else: ?>

                            <div class="text-center text-muted py-5">

                                <i class="bi bi-check-circle fs-2 d-block mb-2"></i>

                                No low-stock items.

                            </div>

                        <?php endif; ?>


                        <?php if ($outOfStockCount > 0): ?>

                            <div class="alert alert-danger mt-3 mb-0">

                                <strong>
                                    <?php echo number_format($outOfStockCount); ?>
                                </strong>

                                item(s) are currently out of stock.

                            </div>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


<?php

$conn->close();

require_once "includes/footer.php";

?>