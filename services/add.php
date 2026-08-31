<?php

/*
|--------------------------------------------------------------------------
| Service Management - Add New Service
|--------------------------------------------------------------------------
*/

$basePath = "../";
$pageTitle = "New Service";


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
| Your existing login system uses admin_id.
| Do NOT use ../includes/auth.php because that file does not exist.
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


$error = "";


/*
|--------------------------------------------------------------------------
| Default Form Values
|--------------------------------------------------------------------------
*/

$customerId = "";
$deviceBrand = "";
$deviceModel = "";
$imeiSerial = "";
$problemDescription = "";
$serviceCharge = "0.00";
$serviceStatus = "Pending";
$receivedDate = date("Y-m-d");
$expectedDeliveryDate = date(
    "Y-m-d",
    strtotime("+1 day")
);
$additionalNotes = "";


/*
|--------------------------------------------------------------------------
| Service Status Options
|--------------------------------------------------------------------------
*/

$serviceStatuses = [
    "Pending",
    "Under Inspection",
    "Waiting for Parts",
    "In Repair",
    "Completed",
    "Delivered",
    "Cancelled"
];


/*
|--------------------------------------------------------------------------
| Process Form Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /*
    |--------------------------------------------------------------------------
    | Get Submitted Values
    |--------------------------------------------------------------------------
    */

    $customerId = filter_input(
        INPUT_POST,
        "customer_id",
        FILTER_VALIDATE_INT
    );

    $deviceBrand = trim(
        $_POST["device_brand"] ?? ""
    );

    $deviceModel = trim(
        $_POST["device_model"] ?? ""
    );

    $imeiSerial = trim(
        $_POST["imei_serial"] ?? ""
    );

    $problemDescription = trim(
        $_POST["problem_description"] ?? ""
    );

    $serviceCharge = trim(
        $_POST["service_charge"] ?? "0"
    );

    $serviceStatus = trim(
        $_POST["service_status"] ?? "Pending"
    );

    $receivedDate = trim(
        $_POST["received_date"] ?? ""
    );

    $expectedDeliveryDate = trim(
        $_POST["expected_delivery_date"] ?? ""
    );

    $additionalNotes = trim(
        $_POST["additional_notes"] ?? ""
    );


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if (!$customerId) {

        $error = "Please select a customer.";

    } elseif ($deviceBrand === "") {

        $error = "Please enter the device brand.";

    } elseif ($deviceModel === "") {

        $error = "Please enter the device model.";

    } elseif ($problemDescription === "") {

        $error = "Please enter the problem description.";

    } elseif (
        $serviceCharge === "" ||
        !is_numeric($serviceCharge) ||
        (float)$serviceCharge < 0
    ) {

        $error = "Please enter a valid service charge.";

    } elseif (
        !in_array(
            $serviceStatus,
            $serviceStatuses,
            true
        )
    ) {

        $error = "Invalid service status.";

    } elseif ($receivedDate === "") {

        $error = "Please select the received date.";

    } elseif ($expectedDeliveryDate === "") {

        $error = "Please select the expected delivery date.";

    } elseif (
        $expectedDeliveryDate < $receivedDate
    ) {

        $error =
            "Expected delivery date cannot be earlier than received date.";

    }


    /*
    |--------------------------------------------------------------------------
    | Verify Customer
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $customerCheckSql = "
            SELECT id
            FROM customers
            WHERE id = ?
            LIMIT 1
        ";

        $customerCheckStmt =
            $conn->prepare($customerCheckSql);

        if (!$customerCheckStmt) {

            $error =
                "Unable to verify customer.";

        } else {

            $customerCheckStmt->bind_param(
                "i",
                $customerId
            );

            $customerCheckStmt->execute();

            $customerCheckResult =
                $customerCheckStmt->get_result();

            if (
                $customerCheckResult->num_rows === 0
            ) {

                $error =
                    "Selected customer was not found.";

            }

            $customerCheckStmt->close();
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Insert Service
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $createdBy =
            (int) $_SESSION["admin_id"];


        /*
        |--------------------------------------------------------------------------
        | delivery_date
        |--------------------------------------------------------------------------
        | New service has not been delivered yet,
        | therefore this remains NULL.
        |--------------------------------------------------------------------------
        */

        $deliveryDate = null;


        $insertSql = "
            INSERT INTO services
            (
                customer_id,
                device_brand,
                device_model,
                imei_serial,
                problem_description,
                service_charge,
                service_status,
                received_date,
                expected_delivery_date,
                delivery_date,
                additional_notes,
                created_by
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?
            )
        ";


        $insertStmt =
            $conn->prepare($insertSql);


        if (!$insertStmt) {

            $error =
                "Database error while creating service: " .
                $conn->error;

        } else {

            /*
            |--------------------------------------------------------------------------
            | Bind Parameters
            |--------------------------------------------------------------------------
            |
            | i = integer
            | s = string
            | d = decimal
            |
            */

            $serviceChargeDecimal =
                (float) $serviceCharge;


            $insertStmt->bind_param(
                "issssdsssssi",
                $customerId,
                $deviceBrand,
                $deviceModel,
                $imeiSerial,
                $problemDescription,
                $serviceChargeDecimal,
                $serviceStatus,
                $receivedDate,
                $expectedDeliveryDate,
                $deliveryDate,
                $additionalNotes,
                $createdBy
            );


            if ($insertStmt->execute()) {

                $newServiceId =
                    $conn->insert_id;

                $insertStmt->close();


                /*
                |--------------------------------------------------------------------------
                | Redirect to Service Details
                |--------------------------------------------------------------------------
                */

                header(
                    "Location: view.php?id=" .
                    $newServiceId
                );

                exit();

            } else {

                $error =
                    "Unable to create service: " .
                    $insertStmt->error;

                $insertStmt->close();
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| Load Customers
|--------------------------------------------------------------------------
|
| IMPORTANT:
| We only use customer_name here.
| We do NOT use mobile_number because your
| services/index.php screenshot already proves
| that c.mobile_number does not exist.
|
|--------------------------------------------------------------------------
*/

$customers = [];

$customerSql = "
    SELECT
        id,
        customer_name
    FROM customers
    ORDER BY customer_name ASC
";


$customerResult =
    $conn->query($customerSql);


if ($customerResult) {

    while (
        $customer =
        $customerResult->fetch_assoc()
    ) {

        $customers[] = $customer;
    }
}


/*
|--------------------------------------------------------------------------
| Existing Layout
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
                    New Service
                </h2>

                <p class="text-muted mb-0">
                    Create a new mobile service or repair order.
                </p>

            </div>


            <a
                href="index.php"
                class="btn btn-secondary">

                <i class="bi bi-arrow-left"></i>

                Back to Services

            </a>

        </div>


        <!-- Error Message -->

        <?php if ($error !== ""): ?>

            <div
                class="alert alert-danger alert-dismissible fade show"
                role="alert">

                <i class="bi bi-exclamation-triangle-fill me-2"></i>

                <?= htmlspecialchars($error); ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert">
                </button>

            </div>

        <?php endif; ?>


        <!-- Service Form -->

        <form
            method="POST"
            action="add.php">


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

                        <div class="col-md-8">

                            <label
                                for="customer_id"
                                class="form-label">

                                Customer

                                <span class="text-danger">
                                    *
                                </span>

                            </label>


                            <select
                                name="customer_id"
                                id="customer_id"
                                class="form-select"
                                required>

                                <option value="">
                                    -- Select Customer --
                                </option>


                                <?php foreach (
                                    $customers as $customer
                                ): ?>

                                    <option
                                        value="<?= (int)$customer["id"]; ?>"
                                        <?= (
                                            (int)$customerId ===
                                            (int)$customer["id"]
                                        )
                                            ? "selected"
                                            : ""
                                        ?>>

                                        <?= htmlspecialchars(
                                            $customer["customer_name"]
                                        ); ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>


                            <?php if (
                                empty($customers)
                            ): ?>

                                <div class="form-text text-danger">

                                    No customers found.
                                    Please create a customer first.

                                </div>

                            <?php endif; ?>

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


                        <!-- Brand -->

                        <div class="col-md-4 mb-3">

                            <label
                                for="device_brand"
                                class="form-label">

                                Device Brand

                                <span class="text-danger">
                                    *
                                </span>

                            </label>


                            <input
                                type="text"
                                name="device_brand"
                                id="device_brand"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $deviceBrand
                                ); ?>"
                                placeholder="Example: Samsung"
                                maxlength="100"
                                required>

                        </div>


                        <!-- Model -->

                        <div class="col-md-4 mb-3">

                            <label
                                for="device_model"
                                class="form-label">

                                Device Model

                                <span class="text-danger">
                                    *
                                </span>

                            </label>


                            <input
                                type="text"
                                name="device_model"
                                id="device_model"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $deviceModel
                                ); ?>"
                                placeholder="Example: Galaxy A15"
                                maxlength="100"
                                required>

                        </div>


                        <!-- IMEI -->

                        <div class="col-md-4 mb-3">

                            <label
                                for="imei_serial"
                                class="form-label">

                                IMEI / Serial Number

                            </label>


                            <input
                                type="text"
                                name="imei_serial"
                                id="imei_serial"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $imeiSerial
                                ); ?>"
                                placeholder="Example: 123456789"
                                maxlength="100">

                        </div>


                    </div>

                </div>

            </div>


            <!-- Service Information -->

            <div class="card shadow-sm border-0 mb-4">

                <div class="card-header bg-white">

                    <h5 class="mb-0">

                        <i class="bi bi-tools me-2"></i>

                        Service Information

                    </h5>

                </div>


                <div class="card-body">

                    <div class="row">


                        <!-- Problem -->

                        <div class="col-md-6 mb-3">

                            <label
                                for="problem_description"
                                class="form-label">

                                Problem Description

                                <span class="text-danger">
                                    *
                                </span>

                            </label>


                            <textarea
                                name="problem_description"
                                id="problem_description"
                                class="form-control"
                                rows="5"
                                placeholder="Describe the customer's problem..."
                                required><?= htmlspecialchars(
                                    $problemDescription
                                ); ?></textarea>

                        </div>


                        <!-- Charge -->

                        <div class="col-md-3 mb-3">

                            <label
                                for="service_charge"
                                class="form-label">

                                Service Charge

                            </label>


                            <div class="input-group">

                                <span class="input-group-text">
                                    ৳
                                </span>


                                <input
                                    type="number"
                                    name="service_charge"
                                    id="service_charge"
                                    class="form-control"
                                    value="<?= htmlspecialchars(
                                        $serviceCharge
                                    ); ?>"
                                    min="0"
                                    step="0.01">

                            </div>

                        </div>


                        <!-- Status -->

                        <div class="col-md-3 mb-3">

                            <label
                                for="service_status"
                                class="form-label">

                                Service Status

                            </label>


                            <select
                                name="service_status"
                                id="service_status"
                                class="form-select">

                                <?php foreach (
                                    $serviceStatuses as $status
                                ): ?>

                                    <option
                                        value="<?= htmlspecialchars(
                                            $status
                                        ); ?>"
                                        <?= (
                                            $serviceStatus ===
                                            $status
                                        )
                                            ? "selected"
                                            : ""
                                        ?>>

                                        <?= htmlspecialchars(
                                            $status
                                        ); ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                    </div>

                </div>

            </div>


            <!-- Date Information -->

            <div class="card shadow-sm border-0 mb-4">

                <div class="card-header bg-white">

                    <h5 class="mb-0">

                        <i class="bi bi-calendar-event me-2"></i>

                        Service Dates

                    </h5>

                </div>


                <div class="card-body">

                    <div class="row">


                        <!-- Received Date -->

                        <div class="col-md-6 mb-3">

                            <label
                                for="received_date"
                                class="form-label">

                                Received Date

                                <span class="text-danger">
                                    *
                                </span>

                            </label>


                            <input
                                type="date"
                                name="received_date"
                                id="received_date"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $receivedDate
                                ); ?>"
                                required>

                        </div>


                        <!-- Expected Delivery -->

                        <div class="col-md-6 mb-3">

                            <label
                                for="expected_delivery_date"
                                class="form-label">

                                Expected Delivery Date

                                <span class="text-danger">
                                    *
                                </span>

                            </label>


                            <input
                                type="date"
                                name="expected_delivery_date"
                                id="expected_delivery_date"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $expectedDeliveryDate
                                ); ?>"
                                required>

                        </div>


                    </div>

                </div>

            </div>


            <!-- Additional Notes -->

            <div class="card shadow-sm border-0 mb-4">

                <div class="card-header bg-white">

                    <h5 class="mb-0">

                        <i class="bi bi-sticky me-2"></i>

                        Additional Notes

                    </h5>

                </div>


                <div class="card-body">

                    <textarea
                        name="additional_notes"
                        id="additional_notes"
                        class="form-control"
                        rows="4"
                        maxlength="65535"
                        placeholder="Add any additional information about this service..."><?= htmlspecialchars(
                            $additionalNotes
                        ); ?></textarea>

                </div>

            </div>


            <!-- Buttons -->

            <div class="d-flex justify-content-end gap-2 mb-5">

                <a
                    href="index.php"
                    class="btn btn-secondary">

                    <i class="bi bi-x-circle me-1"></i>

                    Cancel

                </a>


                <button
                    type="submit"
                    class="btn btn-primary">

                    <i class="bi bi-check-circle me-1"></i>

                    Create Service

                </button>

            </div>


        </form>

    </div>

</div>


<?php

require_once "../includes/footer.php";

?>