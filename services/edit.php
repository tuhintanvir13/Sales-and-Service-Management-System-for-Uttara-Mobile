<?php

/*
|--------------------------------------------------------------------------
| Service Edit
|--------------------------------------------------------------------------
| File:
| C:\xampp\htdocs\uttara_mobile\services\edit.php
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../config/database.php';

$basePath = "../";
$pageTitle = "Edit Service";

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';


/*
|--------------------------------------------------------------------------
| Get Service ID
|--------------------------------------------------------------------------
*/

$serviceId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);


/*
|--------------------------------------------------------------------------
| Validate ID
|--------------------------------------------------------------------------
*/

if (!$serviceId || $serviceId <= 0) {

    header("Location: index.php?error=" . urlencode(
        "Invalid service ID."
    ));

    exit();
}


/*
|--------------------------------------------------------------------------
| Status Options
|--------------------------------------------------------------------------
*/

$statusOptions = [
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
| Variables
|--------------------------------------------------------------------------
*/

$error = "";

$service = null;


/*
|--------------------------------------------------------------------------
| Load Existing Service
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
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
        additional_notes
    FROM services
    WHERE id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);


if (!$stmt) {

    $error = "Unable to load service information.";

} else {

    $stmt->bind_param(
        "i",
        $serviceId
    );

    $stmt->execute();

    $result = $stmt->get_result();

    $service = $result->fetch_assoc();

    $stmt->close();


    if (!$service) {

        header("Location: index.php?error=" . urlencode(
            "Service order not found."
        ));

        exit();
    }
}

/*
|--------------------------------------------------------------------------
| Visible Service Order Number
|--------------------------------------------------------------------------
| services.id remains the immutable database key. This number is only the
| user-facing sequential Service Order number.
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
| Process Update
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && $service
) {

    /*
     * Get submitted values.
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
        $_POST["service_charge"] ?? ""
    );

    $serviceStatus = trim(
        $_POST["service_status"] ?? ""
    );

    $receivedDate = trim(
        $_POST["received_date"] ?? ""
    );

    $expectedDeliveryDate = trim(
        $_POST["expected_delivery_date"] ?? ""
    );

    $deliveryDate = trim(
        $_POST["delivery_date"] ?? ""
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

        $error =
            "Please select a valid customer.";

    } elseif ($deviceBrand === "") {

        $error =
            "Device brand is required.";

    } elseif ($deviceModel === "") {

        $error =
            "Device model is required.";

    } elseif ($imeiSerial === "") {

        $error =
            "IMEI / Serial Number is required.";

    } elseif ($problemDescription === "") {

        $error =
            "Problem description is required.";

    } elseif (
        $serviceCharge === ""
        || !is_numeric($serviceCharge)
        || (float)$serviceCharge < 0
    ) {

        $error =
            "Please enter a valid service charge.";

    } elseif (
        !in_array(
            $serviceStatus,
            $statusOptions,
            true
        )
    ) {

        $error =
            "Invalid service status.";

    } elseif ($receivedDate === "") {

        $error =
            "Received date is required.";

    } elseif ($expectedDeliveryDate === "") {

        $error =
            "Expected delivery date is required.";

    }


    /*
     |--------------------------------------------------------------------------
     | Validate Customer
     |--------------------------------------------------------------------------
     */

    if ($error === "") {

        $customerCheckSql = "
            SELECT
                id
            FROM customers
            WHERE id = ?
            LIMIT 1
        ";

        $customerCheckStmt =
            $conn->prepare(
                $customerCheckSql
            );


        if (!$customerCheckStmt) {

            $error =
                "Unable to validate customer.";

        } else {

            $customerCheckStmt->bind_param(
                "i",
                $customerId
            );

            $customerCheckStmt->execute();

            $customerResult =
                $customerCheckStmt->get_result();

            $customerExists =
                $customerResult->fetch_assoc();

            $customerCheckStmt->close();


            if (!$customerExists) {

                $error =
                    "Selected customer does not exist.";

            }

        }

    }


    /*
     |--------------------------------------------------------------------------
     | Update Service
     |--------------------------------------------------------------------------
     */

    if ($error === "") {

        /*
         * Convert empty delivery date to NULL.
         */

        $deliveryDateValue =
            ($deliveryDate !== "")
                ? $deliveryDate
                : null;


        /*
         * Update service.
         *
         * created_at is NOT changed.
         * updated_at is handled automatically by MySQL.
         */

        $updateSql = "
            UPDATE services
            SET
                customer_id = ?,
                device_brand = ?,
                device_model = ?,
                imei_serial = ?,
                problem_description = ?,
                service_charge = ?,
                service_status = ?,
                received_date = ?,
                expected_delivery_date = ?,
                delivery_date = ?,
                additional_notes = ?
            WHERE id = ?
        ";


        $updateStmt =
            $conn->prepare(
                $updateSql
            );


        if (!$updateStmt) {

            $error =
                "Unable to prepare service update.";

        } else {

            $chargeValue =
                (float)$serviceCharge;


            /*
             * Bind:
             *
             * i  customer_id
             * s  device_brand
             * s  device_model
             * s  imei_serial
             * s  problem_description
             * d  service_charge
             * s  service_status
             * s  received_date
             * s  expected_delivery_date
             * s  delivery_date
             * s  additional_notes
             * i  service_id
             */

            $updateStmt->bind_param(
                "isssssdssssi",
                $customerId,
                $deviceBrand,
                $deviceModel,
                $imeiSerial,
                $problemDescription,
                $chargeValue,
                $serviceStatus,
                $receivedDate,
                $expectedDeliveryDate,
                $deliveryDateValue,
                $additionalNotes,
                $serviceId
            );


            if ($updateStmt->execute()) {

                $updateStmt->close();


                /*
                 * Successful update.
                 *
                 * Go to View Service instead of Dashboard.
                 */

                header(
                    "Location: view.php?id="
                    . $serviceId
                    . "&success="
                    . urlencode(
                        "Service order updated successfully."
                    )
                );

                exit();

            } else {

                $error =
                    "Unable to update service order.";

                $updateStmt->close();

            }

        }

    }


    /*
     |--------------------------------------------------------------------------
     | Keep submitted values in form when validation fails
     |--------------------------------------------------------------------------
     */

    if ($error !== "") {

        $service["customer_id"] =
            $customerId ?? $service["customer_id"];

        $service["device_brand"] =
            $deviceBrand;

        $service["device_model"] =
            $deviceModel;

        $service["imei_serial"] =
            $imeiSerial;

        $service["problem_description"] =
            $problemDescription;

        $service["service_charge"] =
            $serviceCharge;

        $service["service_status"] =
            $serviceStatus;

        $service["received_date"] =
            $receivedDate;

        $service["expected_delivery_date"] =
            $expectedDeliveryDate;

        $service["delivery_date"] =
            $deliveryDate;

        $service["additional_notes"] =
            $additionalNotes;
    }

}


/*
|--------------------------------------------------------------------------
| Load Customers
|--------------------------------------------------------------------------
*/

$customers = [];


$customerSql = "
    SELECT
        id,
        customer_name,
        mobile
    FROM customers
    ORDER BY customer_name ASC
";


$customerResult =
    $conn->query(
        $customerSql
    );


if (
    $customerResult
    && $customerResult->num_rows > 0
) {

    while (
        $customer =
        $customerResult->fetch_assoc()
    ) {

        $customers[] =
            $customer;
    }

}

?>

<div class="page-content">

    <!-- Page Header -->

    <div class="page-header">

        <div>

            <h1>
                Edit Service
            </h1>

            <p>
                Update customer service and repair order information.
            </p>

        </div>


        <div>

            <a
                href="index.php"
                class="btn btn-secondary"
            >

                <i class="fa fa-arrow-left"></i>

                Back to Services

            </a>

        </div>

    </div>


    <!-- Error Message -->

    <?php if ($error !== ""): ?>

        <div class="service-alert service-alert-danger">

            <i class="fa fa-exclamation-triangle"></i>

            <span>
                <?= htmlspecialchars($error) ?>
            </span>

        </div>

    <?php endif; ?>


    <!-- Edit Form -->

    <div class="edit-card">

        <div class="edit-card-header">

            <div>

                <h2>
                    Service Order #<?= $serviceOrderNo ?>
                </h2>

                <p>
                    Modify the information below and save the changes.
                </p>

            </div>

        </div>


        <form
            method="POST"
            action="edit.php?id=<?= (int)$service["id"] ?>"
        >

            <div class="form-grid">


                <!-- Customer -->

                <div class="form-group">

                    <label>
                        Customer
                        <span>*</span>
                    </label>

                    <select
                        name="customer_id"
                        required
                    >

                        <option value="">
                            Select Customer
                        </option>


                        <?php foreach ($customers as $customer): ?>

                            <option
                                value="<?= (int)$customer["id"] ?>"
                                <?= (
                                    (int)$service["customer_id"]
                                    ===
                                    (int)$customer["id"]
                                )
                                    ? "selected"
                                    : ""
                                ?>
                            >

                                <?= htmlspecialchars(
                                    $customer["customer_name"]
                                ) ?>

                                -
                                <?= htmlspecialchars(
                                    $customer["mobile"]
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- Device Brand -->

                <div class="form-group">

                    <label>
                        Device Brand
                        <span>*</span>
                    </label>

                    <input
                        type="text"
                        name="device_brand"
                        value="<?= htmlspecialchars(
                            $service["device_brand"]
                        ) ?>"
                        maxlength="100"
                        required
                    >

                </div>


                <!-- Device Model -->

                <div class="form-group">

                    <label>
                        Device Model
                        <span>*</span>
                    </label>

                    <input
                        type="text"
                        name="device_model"
                        value="<?= htmlspecialchars(
                            $service["device_model"]
                        ) ?>"
                        maxlength="100"
                        required
                    >

                </div>


                <!-- IMEI -->

                <div class="form-group">

                    <label>
                        IMEI / Serial Number
                        <span>*</span>
                    </label>

                    <input
                        type="text"
                        name="imei_serial"
                        value="<?= htmlspecialchars(
                            $service["imei_serial"]
                        ) ?>"
                        maxlength="100"
                        required
                    >

                </div>


                <!-- Service Charge -->

                <div class="form-group">

                    <label>
                        Service Charge
                        <span>*</span>
                    </label>

                    <input
                        type="number"
                        name="service_charge"
                        value="<?= htmlspecialchars(
                            $service["service_charge"]
                        ) ?>"
                        min="0"
                        step="0.01"
                        required
                    >

                </div>


                <!-- Status -->

                <div class="form-group">

                    <label>
                        Service Status
                        <span>*</span>
                    </label>

                    <select
                        name="service_status"
                        required
                    >

                        <?php foreach ($statusOptions as $option): ?>

                            <option
                                value="<?= htmlspecialchars($option) ?>"
                                <?= (
                                    $service["service_status"]
                                    ===
                                    $option
                                )
                                    ? "selected"
                                    : ""
                                ?>
                            >

                                <?= htmlspecialchars($option) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <!-- Received Date -->

                <div class="form-group">

                    <label>
                        Received Date
                        <span>*</span>
                    </label>

                    <input
                        type="date"
                        name="received_date"
                        value="<?= htmlspecialchars(
                            $service["received_date"]
                        ) ?>"
                        required
                    >

                </div>


                <!-- Expected Delivery -->

                <div class="form-group">

                    <label>
                        Expected Delivery Date
                        <span>*</span>
                    </label>

                    <input
                        type="date"
                        name="expected_delivery_date"
                        value="<?= htmlspecialchars(
                            $service["expected_delivery_date"]
                        ) ?>"
                        required
                    >

                </div>


                <!-- Delivery Date -->

                <div class="form-group">

                    <label>
                        Delivery Date
                    </label>

                    <input
                        type="date"
                        name="delivery_date"
                        value="<?= htmlspecialchars(
                            $service["delivery_date"] ?? ""
                        ) ?>"
                    >

                </div>


                <!-- Problem Description -->

                <div class="form-group full-width">

                    <label>
                        Problem Description
                        <span>*</span>
                    </label>

                    <textarea
                        name="problem_description"
                        rows="4"
                        required
                    ><?= htmlspecialchars(
                        $service["problem_description"]
                    ) ?></textarea>

                </div>


                <!-- Additional Notes -->

                <div class="form-group full-width">

                    <label>
                        Additional Notes
                    </label>

                    <textarea
                        name="additional_notes"
                        rows="4"
                    ><?= htmlspecialchars(
                        $service["additional_notes"] ?? ""
                    ) ?></textarea>

                </div>


            </div>


            <!-- Form Buttons -->

            <div class="form-actions">

                <a
                    href="index.php"
                    class="btn btn-secondary"
                >

                    <i class="fa fa-times"></i>

                    Cancel

                </a>


                <button
                    type="submit"
                    class="btn btn-primary"
                >

                    <i class="fa fa-save"></i>

                    Save Changes

                </button>

            </div>

        </form>

    </div>

</div>


<style>

/*
|--------------------------------------------------------------------------
| Edit Service Page
|--------------------------------------------------------------------------
*/

.page-content {

    margin-left: 223px;

    padding: 32px 35px;

    width: calc(100% - 223px);

    box-sizing: border-box;
}


/*
|--------------------------------------------------------------------------
| Header
|--------------------------------------------------------------------------
*/

.page-header {

    display: flex;

    align-items: center;

    justify-content: space-between;

    margin-bottom: 25px;
}


.page-header h1 {

    margin: 0 0 6px;

    font-size: 30px;

    font-weight: 700;

    color: #172033;
}


.page-header p {

    margin: 0;

    color: #526177;

    font-size: 14px;
}


/*
|--------------------------------------------------------------------------
| Buttons
|--------------------------------------------------------------------------
*/

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

    box-sizing: border-box;
}


.btn-primary {

    background: #1769e0;

    color: #ffffff !important;
}


.btn-primary:hover {

    background: #0d5ccc;

}


.btn-secondary {

    background: #68727e;

    color: #ffffff !important;
}


.btn-secondary:hover {

    background: #56606b;

}


/*
|--------------------------------------------------------------------------
| Alert
|--------------------------------------------------------------------------
*/

.service-alert {

    display: flex;

    align-items: center;

    gap: 10px;

    padding: 13px 16px;

    margin-bottom: 20px;

    border-radius: 7px;

    font-size: 14px;
}


.service-alert-danger {

    background: #fde8e8;

    color: #a61b1b;

    border: 1px solid #f5c2c2;
}


/*
|--------------------------------------------------------------------------
| Edit Card
|--------------------------------------------------------------------------
*/

.edit-card {

    background: #ffffff;

    border-radius: 9px;

    box-shadow:
        0 2px 8px rgba(0, 0, 0, 0.06);

    overflow: hidden;
}


.edit-card-header {

    padding: 22px 25px;

    border-bottom: 1px solid #e5e7eb;
}


.edit-card-header h2 {

    margin: 0 0 5px;

    font-size: 20px;

    color: #172033;
}


.edit-card-header p {

    margin: 0;

    color: #697586;

    font-size: 14px;
}


/*
|--------------------------------------------------------------------------
| Form
|--------------------------------------------------------------------------
*/

.edit-card form {

    padding: 25px;
}


.form-grid {

    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 20px;
}


.form-group {

    display: flex;

    flex-direction: column;

    min-width: 0;
}


.form-group.full-width {

    grid-column: 1 / -1;
}


.form-group label {

    margin-bottom: 7px;

    font-size: 14px;

    font-weight: 600;

    color: #263449;
}


.form-group label span {

    color: #dc2626;
}


.form-group input,
.form-group select,
.form-group textarea {

    width: 100%;

    padding: 10px 12px;

    border: 1px solid #d5dce5;

    border-radius: 6px;

    background: #ffffff;

    color: #172033;

    font-size: 14px;

    box-sizing: border-box;

    outline: none;
}


.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {

    border-color: #1769e0;

    box-shadow:
        0 0 0 2px rgba(
            23,
            105,
            224,
            0.10
        );
}


.form-group textarea {

    resize: vertical;

    min-height: 100px;

}


/*
|--------------------------------------------------------------------------
| Form Actions
|--------------------------------------------------------------------------
*/

.form-actions {

    display: flex;

    justify-content: flex-end;

    align-items: center;

    gap: 10px;

    margin-top: 25px;

    padding-top: 20px;

    border-top: 1px solid #e5e7eb;
}


/*
|--------------------------------------------------------------------------
| Responsive
|--------------------------------------------------------------------------
*/

@media (max-width: 900px) {

    .page-content {

        margin-left: 0;

        width: 100%;

        padding: 25px 20px;

    }


    .form-grid {

        grid-template-columns: 1fr;

    }


    .form-group.full-width {

        grid-column: auto;

    }

}


@media (max-width: 600px) {

    .page-header {

        flex-direction: column;

        align-items: flex-start;

        gap: 15px;

    }


    .form-actions {

        flex-direction: column-reverse;

        align-items: stretch;

    }


    .form-actions .btn {

        width: 100%;

    }

}

</style>