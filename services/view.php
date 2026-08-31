```php
<?php

/*
|--------------------------------------------------------------------------
| Service Management - View Service
|--------------------------------------------------------------------------
*/

$basePath = "../";
$pageTitle = "Service Details";


/*
|--------------------------------------------------------------------------
| Start Session
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["admin_id"])) {
    header("Location: ../login.php");
    exit();
}


/*
|--------------------------------------------------------------------------
| Database Connection
|--------------------------------------------------------------------------
*/

require_once "../config/database.php";


/*
|--------------------------------------------------------------------------
| Get Service ID
|--------------------------------------------------------------------------
*/

$serviceId = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);


if (!$serviceId) {

    header("Location: index.php");
    exit();

}


/*
|--------------------------------------------------------------------------
| Load Service Details
|--------------------------------------------------------------------------
|
| IMPORTANT:
| We only use customer_name from customers.
| We do NOT use c.mobile_number because your
| existing customers table does not contain that
| column.
|
|--------------------------------------------------------------------------
*/

$serviceSql = "
    SELECT
        s.id,
        s.customer_id,
        s.device_brand,
        s.device_model,
        s.imei_serial,
        s.problem_description,
        s.service_charge,
        s.service_status,
        s.received_date,
        s.expected_delivery_date,
        s.delivery_date,
        s.additional_notes,
        s.created_by,
        s.created_at,
        s.updated_at,
        c.customer_name

    FROM services s

    LEFT JOIN customers c
        ON c.id = s.customer_id

    WHERE s.id = ?

    LIMIT 1
";


$serviceStmt =
    $conn->prepare($serviceSql);


if (!$serviceStmt) {

    die(
        "Database error: " .
        htmlspecialchars($conn->error)
    );

}


$serviceStmt->bind_param(
    "i",
    $serviceId
);


$serviceStmt->execute();


$serviceResult =
    $serviceStmt->get_result();


$service =
    $serviceResult->fetch_assoc();


$serviceStmt->close();


/*
|--------------------------------------------------------------------------
| Service Not Found
|--------------------------------------------------------------------------
*/

if (!$service) {

    header(
        "Location: index.php?error=" .
        urlencode("Service not found.")
    );

    exit();

}

/*
|--------------------------------------------------------------------------
| Visible Service Order Number
|--------------------------------------------------------------------------
| Keep services.id as the real database key. The visible order number is
| calculated from the current records so deleting an order automatically
| closes the numbering gap and newly created orders continue sequentially.
|--------------------------------------------------------------------------
*/

$serviceOrderNo = 1;
$orderStmt = $conn->prepare("SELECT COUNT(*) AS order_no FROM services WHERE id <= ?");
if ($orderStmt) {
    $orderStmt->bind_param("i", $serviceId);
    $orderStmt->execute();
    $orderResult = $orderStmt->get_result();
    if ($orderRow = $orderResult->fetch_assoc()) {
        $serviceOrderNo = (int)$orderRow['order_no'];
    }
    $orderStmt->close();
}


/*
|--------------------------------------------------------------------------
| Load Service Parts
|--------------------------------------------------------------------------
|
| This prepares the Service Parts section for the
| next development stage.
|
|--------------------------------------------------------------------------
*/

$parts = [];


$partsSql = "
    SELECT
        sp.id,
        sp.service_id,
        sp.product_id,
        sp.quantity,
        sp.unit_price,
        sp.total_price,
        sp.used_status,
        sp.created_at,
        p.product_name,
        p.item_type

    FROM service_parts sp

    LEFT JOIN products p
        ON p.id = sp.product_id

    WHERE sp.service_id = ?

    ORDER BY sp.id DESC
";


$partsStmt =
    $conn->prepare($partsSql);


if ($partsStmt) {

    $partsStmt->bind_param(
        "i",
        $serviceId
    );

    $partsStmt->execute();

    $partsResult =
        $partsStmt->get_result();


    while (
        $part =
        $partsResult->fetch_assoc()
    ) {

        $parts[] = $part;

    }


    $partsStmt->close();

}


/*
|--------------------------------------------------------------------------
| Calculate Parts Total
|--------------------------------------------------------------------------
*/

$partsTotal = 0;


foreach ($parts as $part) {

    $partsTotal +=
        (float) $part["total_price"];

}


/*
|--------------------------------------------------------------------------
| Calculate Grand Total
|--------------------------------------------------------------------------
*/

$serviceCharge =
    (float) $service["service_charge"];


$grandTotal =
    $serviceCharge +
    $partsTotal;


/*
|--------------------------------------------------------------------------
| Status Badge
|--------------------------------------------------------------------------
*/

$statusClass = "bg-secondary";


switch ($service["service_status"]) {

    case "Pending":
        $statusClass = "bg-warning text-dark";
        break;

    case "Under Inspection":
        $statusClass = "bg-info text-dark";
        break;

    case "Waiting for Parts":
        $statusClass = "bg-warning text-dark";
        break;

    case "In Repair":
        $statusClass = "bg-primary";
        break;

    case "Completed":
        $statusClass = "bg-success";
        break;

    case "Delivered":
        $statusClass = "bg-success";
        break;

    case "Cancelled":
        $statusClass = "bg-danger";
        break;

}


/*
|--------------------------------------------------------------------------
| Include Layout
|--------------------------------------------------------------------------
*/

require_once "../includes/header.php";
require_once "../includes/navbar.php";
require_once "../includes/sidebar.php";

?>


<div class="content">

    <div class="container-fluid">


        <!-- Page Header -->

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h2 class="fw-bold">

                    <i class="bi bi-tools me-2"></i>

                    Service Details

                </h2>

                <p class="text-muted mb-0">

                    View service and repair order information.

                </p>

            </div>


            <div class="d-flex gap-2">

                <a
                    href="index.php"
                    class="btn btn-secondary">

                    <i class="bi bi-arrow-left me-1"></i>

                    Back

                </a>


                <a
                    href="edit.php?id=<?= (int)$service["id"]; ?>"
                    class="btn btn-primary">

                    <i class="bi bi-pencil me-1"></i>

                    Edit Service

                </a>

                <form
                    method="POST"
                    action="delete.php"
                    class="d-inline"
                    onsubmit="return confirm('Are you sure you want to delete Service #<?= $serviceOrderNo; ?>? This action cannot be undone.');"
                >
                    <input type="hidden" name="id" value="<?= (int)$service["id"]; ?>">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-trash me-1"></i>
                        Delete Service
                    </button>
                </form>

            </div>

        </div>


        <!-- Service Status -->

        <div class="card shadow-sm border-0 mb-4">

            <div class="card-body">

                <div class="row align-items-center">

                    <div class="col-md-8">

                        <h4 class="mb-1">

                            Service #<?= $serviceOrderNo; ?>

                        </h4>

                        <p class="text-muted mb-0">

                            <?= htmlspecialchars(
                                $service["device_brand"]
                            ); ?>

                            -

                            <?= htmlspecialchars(
                                $service["device_model"]
                            ); ?>

                        </p>

                    </div>


                    <div class="col-md-4 text-md-end mt-3 mt-md-0">

                        <span
                            class="badge <?= $statusClass; ?> fs-6 px-3 py-2">

                            <?= htmlspecialchars(
                                $service["service_status"]
                            ); ?>

                        </span>

                    </div>

                </div>

            </div>

        </div>


        <!-- Customer Information -->

        <div class="card shadow-sm border-0 mb-4">

            <div class="card-header bg-white">

                <h5 class="mb-0">

                    <i class="bi bi-person me-2"></i>

                    Customer Information

                </h5>

            </div>


            <div class="card-body">

                <div class="row">

                    <div class="col-md-6 mb-3">

                        <label class="text-muted small">
                            Customer
                        </label>

                        <div class="fw-semibold">

                            <?= htmlspecialchars(
                                $service["customer_name"]
                                ?? "Unknown Customer"
                            ); ?>

                        </div>

                    </div>


                    <div class="col-md-6 mb-3">

                        <label class="text-muted small">
                            Customer ID
                        </label>

                        <div class="fw-semibold">

                            #<?= (int)$service["customer_id"]; ?>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- Device Information -->

        <div class="card shadow-sm border-0 mb-4">

            <div class="card-header bg-white">

                <h5 class="mb-0">

                    <i class="bi bi-phone me-2"></i>

                    Device Information

                </h5>

            </div>


            <div class="card-body">

                <div class="row">

                    <div class="col-md-4 mb-3">

                        <label class="text-muted small">
                            Brand
                        </label>

                        <div class="fw-semibold">

                            <?= htmlspecialchars(
                                $service["device_brand"]
                            ); ?>

                        </div>

                    </div>


                    <div class="col-md-4 mb-3">

                        <label class="text-muted small">
                            Model
                        </label>

                        <div class="fw-semibold">

                            <?= htmlspecialchars(
                                $service["device_model"]
                            ); ?>

                        </div>

                    </div>


                    <div class="col-md-4 mb-3">

                        <label class="text-muted small">
                            IMEI / Serial
                        </label>

                        <div class="fw-semibold">

                            <?php if (
                                !empty($service["imei_serial"])
                            ): ?>

                                <?= htmlspecialchars(
                                    $service["imei_serial"]
                                ); ?>

                            <?php else: ?>

                                <span class="text-muted">
                                    Not provided
                                </span>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- Service Information -->

        <div class="card shadow-sm border-0 mb-4">

            <div class="card-header bg-white">

                <h5 class="mb-0">

                    <i class="bi bi-wrench-adjustable me-2"></i>

                    Service Information

                </h5>

            </div>


            <div class="card-body">

                <div class="row">


                    <div class="col-md-8 mb-4">

                        <label class="text-muted small">
                            Problem Description
                        </label>

                        <div class="border rounded p-3">

                            <?= nl2br(
                                htmlspecialchars(
                                    $service[
                                        "problem_description"
                                    ]
                                )
                            ); ?>

                        </div>

                    </div>


                    <div class="col-md-4 mb-4">

                        <label class="text-muted small">
                            Service Charge
                        </label>

                        <div class="fs-4 fw-bold">

                            ৳<?= number_format(
                                $serviceCharge,
                                2
                            ); ?>

                        </div>

                    </div>


                </div>

            </div>

        </div>


        <!-- Service Dates -->

        <div class="card shadow-sm border-0 mb-4">

            <div class="card-header bg-white">

                <h5 class="mb-0">

                    <i class="bi bi-calendar-event me-2"></i>

                    Service Dates

                </h5>

            </div>


            <div class="card-body">

                <div class="row">


                    <div class="col-md-4 mb-3">

                        <label class="text-muted small">
                            Received Date
                        </label>

                        <div class="fw-semibold">

                            <?= !empty(
                                $service["received_date"]
                            )
                                ? date(
                                    "d M Y",
                                    strtotime(
                                        $service[
                                            "received_date"
                                        ]
                                    )
                                )
                                : "-"
                            ?>

                        </div>

                    </div>


                    <div class="col-md-4 mb-3">

                        <label class="text-muted small">
                            Expected Delivery
                        </label>

                        <div class="fw-semibold">

                            <?= !empty(
                                $service[
                                    "expected_delivery_date"
                                ]
                            )
                                ? date(
                                    "d M Y",
                                    strtotime(
                                        $service[
                                            "expected_delivery_date"
                                        ]
                                    )
                                )
                                : "-"
                            ?>

                        </div>

                    </div>


                    <div class="col-md-4 mb-3">

                        <label class="text-muted small">
                            Delivery Date
                        </label>

                        <div class="fw-semibold">

                            <?php if (
                                !empty(
                                    $service["delivery_date"]
                                )
                            ): ?>

                                <?= date(
                                    "d M Y",
                                    strtotime(
                                        $service[
                                            "delivery_date"
                                        ]
                                    )
                                ); ?>

                            <?php else: ?>

                                <span class="text-muted">
                                    Not delivered
                                </span>

                            <?php endif; ?>

                        </div>

                    </div>


                </div>

            </div>

        </div>


        <!-- Additional Notes -->

        <?php if (
            !empty($service["additional_notes"])
        ): ?>

            <div class="card shadow-sm border-0 mb-4">

                <div class="card-header bg-white">

                    <h5 class="mb-0">

                        <i class="bi bi-sticky me-2"></i>

                        Additional Notes

                    </h5>

                </div>


                <div class="card-body">

                    <?= nl2br(
                        htmlspecialchars(
                            $service["additional_notes"]
                        )
                    ); ?>

                </div>

            </div>

        <?php endif; ?>


        <!-- Service Parts -->

        <div class="card shadow-sm border-0 mb-4">

            <div class="card-header bg-white">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <h5 class="mb-0">

                            <i class="bi bi-box-seam me-2"></i>

                            Service Parts

                        </h5>

                    </div>


                    <a
                        href="../service_parts/add.php?service_id=<?= (int)$service["id"]; ?>"
                        class="btn btn-sm btn-primary">

                        <i class="bi bi-plus-circle me-1"></i>

                        Add Part

                    </a>

                </div>

            </div>


            <div class="card-body p-0">

                <?php if (
                    empty($parts)
                ): ?>

                    <div class="p-4 text-center text-muted">

                        <i class="bi bi-box fs-2 d-block mb-2"></i>

                        No parts have been added to this service yet.

                    </div>

                <?php else: ?>

                    <div class="table-responsive">

                        <table class="table table-hover mb-0">

                            <thead class="table-light">

                                <tr>

                                    <th>
                                        Part
                                    </th>

                                    <th>
                                        Type
                                    </th>

                                    <th>
                                        Quantity
                                    </th>

                                    <th>
                                        Unit Price
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

                                <?php foreach (
                                    $parts as $part
                                ): ?>

                                    <tr>

                                        <td>

                                            <?= htmlspecialchars(
                                                $part[
                                                    "product_name"
                                                ]
                                                ??
                                                "Unknown Product"
                                            ); ?>

                                        </td>


                                        <td>

                                            <?= htmlspecialchars(
                                                $part[
                                                    "item_type"
                                                ]
                                                ?? "-"
                                            ); ?>

                                        </td>


                                        <td>

                                            <?= (int)
                                                $part[
                                                    "quantity"
                                                ]; ?>

                                        </td>


                                        <td>

                                            ৳<?= number_format(
                                                (float)
                                                $part[
                                                    "unit_price"
                                                ],
                                                2
                                            ); ?>

                                        </td>


                                        <td class="fw-semibold">

                                            ৳<?= number_format(
                                                (float)
                                                $part[
                                                    "total_price"
                                                ],
                                                2
                                            ); ?>

                                        </td>


                                        <td>

                                            <?php

                                            $partStatusClass =
                                                "bg-secondary";

                                            if (
                                                $part[
                                                    "used_status"
                                                ] === "Pending"
                                            ) {

                                                $partStatusClass =
                                                    "bg-warning text-dark";

                                            } elseif (
                                                $part[
                                                    "used_status"
                                                ] === "Used"
                                            ) {

                                                $partStatusClass =
                                                    "bg-success";

                                            } elseif (
                                                $part[
                                                    "used_status"
                                                ] === "Returned"
                                            ) {

                                                $partStatusClass =
                                                    "bg-danger";

                                            }

                                            ?>


                                            <span
                                                class="badge <?= $partStatusClass; ?>">

                                                <?= htmlspecialchars(
                                                    $part[
                                                        "used_status"
                                                    ]
                                                ); ?>

                                            </span>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>


                            <tfoot>

                                <tr>

                                    <td
                                        colspan="4"
                                        class="text-end fw-bold">

                                        Parts Total:

                                    </td>

                                    <td
                                        colspan="2"
                                        class="fw-bold">

                                        ৳<?= number_format(
                                            $partsTotal,
                                            2
                                        ); ?>

                                    </td>

                                </tr>

                            </tfoot>

                        </table>

                    </div>

                <?php endif; ?>

            </div>

        </div>


        <!-- Total -->

        <div class="card shadow-sm border-0 mb-5">

            <div class="card-body">

                <div class="row justify-content-end">

                    <div class="col-md-5">

                        <div class="d-flex justify-content-between mb-2">

                            <span>
                                Service Charge
                            </span>

                            <span>
                                ৳<?= number_format(
                                    $serviceCharge,
                                    2
                                ); ?>
                            </span>

                        </div>


                        <div class="d-flex justify-content-between mb-2">

                            <span>
                                Parts Total
                            </span>

                            <span>
                                ৳<?= number_format(
                                    $partsTotal,
                                    2
                                ); ?>
                            </span>

                        </div>


                        <hr>


                        <div class="d-flex justify-content-between">

                            <strong class="fs-5">
                                Grand Total
                            </strong>

                            <strong class="fs-5">
                                ৳<?= number_format(
                                    $grandTotal,
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


<?php

require_once "../includes/footer.php";

?>
```
