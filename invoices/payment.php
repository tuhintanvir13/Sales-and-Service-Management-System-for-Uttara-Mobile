<?php

require_once __DIR__ . '/../config/database.php';

$basePath = "../";
$pageTitle = "Receive Payment";

$invoiceId = isset($_GET['id'])
    ? (int)$_GET['id']
    : 0;

if ($invoiceId <= 0) {
    header("Location: index.php");
    exit();
}

$error = "";
$success = "";


/*
|--------------------------------------------------------------------------
| Load Invoice
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        i.*,

        s.customer_id AS sale_customer_id,
        s.grand_total AS sale_total,
        s.paid_amount AS sale_paid,
        s.due_amount AS sale_due,

        sv.customer_id AS service_customer_id,
        sv.service_charge,
        sv.device_brand,
        sv.device_model,

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
| Calculate Total
|--------------------------------------------------------------------------
*/

$isSale =
    !empty($invoice['sale_id']);

$isService =
    !empty($invoice['service_id']);

$invoiceTotal = 0;
$currentPaid = 0;

if ($isSale) {

    $invoiceTotal =
        (float)$invoice['sale_total'];

    $currentPaid =
        (float)$invoice['sale_paid'];

} else {

    $partsTotal = 0;

    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(total_price), 0) AS total
        FROM service_parts
        WHERE service_id = ?
          AND used_status = 'Used'
    ");

    if ($stmt) {

        $stmt->bind_param(
            "i",
            $invoice['service_id']
        );

        $stmt->execute();

        $partsResult =
            $stmt->get_result();

        $partsTotal =
            (float)$partsResult
                ->fetch_assoc()['total'];

        $stmt->close();
    }

    $invoiceTotal =
        (float)$invoice['service_charge'] +
        $partsTotal;


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

        $currentPaid =
            (float)$paymentResult
                ->fetch_assoc()['total'];

        $stmt->close();
    }
}

$currentDue =
    max(
        0,
        $invoiceTotal - $currentPaid
    );


/*
|--------------------------------------------------------------------------
| Process Payment
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $amount = isset($_POST['amount'])
        ? (float)$_POST['amount']
        : 0;

    $paymentMethod =
        isset($_POST['payment_method'])
            ? trim($_POST['payment_method'])
            : "Cash";

    $notes =
        isset($_POST['notes'])
            ? trim($_POST['notes'])
            : "";


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($amount <= 0) {

        $error =
            "Payment amount must be greater than zero.";

    } elseif ($amount > $currentDue + 0.001) {

        $error =
            "Payment cannot be greater than the current due amount.";

    } elseif (
        !in_array(
            $paymentMethod,
            [
                "Cash",
                "Card",
                "Mobile Banking",
                "Other"
            ],
            true
        )
    ) {

        $error =
            "Invalid payment method.";

    } else {

        $conn->begin_transaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | Insert Payment
            |--------------------------------------------------------------------------
            */

            if ($isSale) {

                $stmt = $conn->prepare("
                    INSERT INTO payments
                    (
                        sale_id,
                        service_id,
                        amount,
                        payment_method,
                        payment_date,
                        notes
                    )
                    VALUES
                    (
                        ?,
                        NULL,
                        ?,
                        ?,
                        NOW(),
                        ?
                    )
                ");

                if (!$stmt) {
                    throw new Exception(
                        "Unable to prepare payment."
                    );
                }

                $saleId =
                    (int)$invoice['sale_id'];

                $stmt->bind_param(
                    "idss",
                    $saleId,
                    $amount,
                    $paymentMethod,
                    $notes
                );

                if (!$stmt->execute()) {
                    throw new Exception(
                        "Unable to save payment."
                    );
                }

                $stmt->close();


                /*
                |--------------------------------------------------------------------------
                | Update Sale Payment Information
                |--------------------------------------------------------------------------
                */

                $newPaid =
                    $currentPaid +
                    $amount;

                $newDue =
                    max(
                        0,
                        $invoiceTotal -
                        $newPaid
                    );

                if ($newDue <= 0) {

                    $newStatus = "Paid";

                } elseif ($newPaid > 0) {

                    $newStatus = "Partial";

                } else {

                    $newStatus = "Due";
                }


                $stmt = $conn->prepare("
                    UPDATE sales
                    SET
                        paid_amount = ?,
                        due_amount = ?,
                        payment_status = ?
                    WHERE id = ?
                ");

                if (!$stmt) {
                    throw new Exception(
                        "Unable to update sale."
                    );
                }

                $stmt->bind_param(
                    "ddsi",
                    $newPaid,
                    $newDue,
                    $newStatus,
                    $saleId
                );

                if (!$stmt->execute()) {
                    throw new Exception(
                        "Unable to update sale payment status."
                    );
                }

                $stmt->close();

            } else {

                /*
                |--------------------------------------------------------------------------
                | Service Payment
                |--------------------------------------------------------------------------
                */

                $serviceId =
                    (int)$invoice['service_id'];

                $stmt = $conn->prepare("
                    INSERT INTO payments
                    (
                        sale_id,
                        service_id,
                        amount,
                        payment_method,
                        payment_date,
                        notes
                    )
                    VALUES
                    (
                        NULL,
                        ?,
                        ?,
                        ?,
                        NOW(),
                        ?
                    )
                ");

                if (!$stmt) {
                    throw new Exception(
                        "Unable to prepare payment."
                    );
                }

                $stmt->bind_param(
                    "idss",
                    $serviceId,
                    $amount,
                    $paymentMethod,
                    $notes
                );

                if (!$stmt->execute()) {
                    throw new Exception(
                        "Unable to save payment."
                    );
                }

                $stmt->close();
            }


            $conn->commit();


            header(
                "Location: view.php?id=" .
                $invoiceId .
                "&payment=success"
            );

            exit();

        } catch (Throwable $e) {

            $conn->rollback();

            $error =
                $e->getMessage();
        }
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

                    <i class="bi bi-cash-stack me-2"></i>

                    Receive Payment

                </h2>

                <p class="text-muted mb-0">

                    Record a payment against this invoice.

                </p>

            </div>


            <a
                href="view.php?id=<?= $invoiceId; ?>"
                class="btn btn-secondary">

                <i class="bi bi-arrow-left me-1"></i>

                Back to Invoice

            </a>

        </div>


        <?php if ($error !== ""): ?>

            <div class="alert alert-danger">

                <i class="bi bi-exclamation-triangle me-2"></i>

                <?= htmlspecialchars($error); ?>

            </div>

        <?php endif; ?>


        <div class="row g-4">

            <!-- Invoice Summary -->

            <div class="col-lg-5">

                <div class="card shadow-sm border-0">

                    <div class="card-header bg-white">

                        <h5 class="mb-0">

                            <i class="bi bi-receipt me-2"></i>

                            Invoice Summary

                        </h5>

                    </div>


                    <div class="card-body">

                        <div class="mb-3">

                            <small class="text-muted">
                                Invoice
                            </small>

                            <h5 class="fw-bold">

                                <?= htmlspecialchars(
                                    $invoice['invoice_number']
                                ); ?>

                            </h5>

                        </div>


                        <div class="mb-3">

                            <small class="text-muted">
                                Customer
                            </small>

                            <div class="fw-semibold">

                                <?= htmlspecialchars(
                                    $invoice['customer_name']
                                    ?: "Walk-in Customer"
                                ); ?>

                            </div>

                        </div>


                        <div class="mb-3">

                            <small class="text-muted">
                                Invoice Type
                            </small>

                            <div>

                                <?php if ($isSale): ?>

                                    <span class="badge bg-primary">
                                        Sale
                                    </span>

                                <?php else: ?>

                                    <span class="badge bg-info text-dark">
                                        Service
                                    </span>

                                <?php endif; ?>

                            </div>

                        </div>


                        <?php if ($isService): ?>

                            <div class="mb-3">

                                <small class="text-muted">
                                    Device
                                </small>

                                <div class="fw-semibold">

                                    <?= htmlspecialchars(
                                        $invoice['device_brand']
                                    ); ?>

                                    <?= htmlspecialchars(
                                        $invoice['device_model']
                                    ); ?>

                                </div>

                            </div>

                        <?php endif; ?>


                        <hr>


                        <div class="d-flex justify-content-between mb-2">

                            <span>
                                Invoice Total
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
                                Already Paid
                            </span>

                            <strong class="text-success">

                                ৳<?= number_format(
                                    $currentPaid,
                                    2
                                ); ?>

                            </strong>

                        </div>


                        <hr>


                        <div class="d-flex justify-content-between">

                            <span class="fw-bold">
                                Current Due
                            </span>

                            <strong class="text-danger fs-4">

                                ৳<?= number_format(
                                    $currentDue,
                                    2
                                ); ?>

                            </strong>

                        </div>

                    </div>

                </div>

            </div>


            <!-- Payment Form -->

            <div class="col-lg-7">

                <div class="card shadow-sm border-0">

                    <div class="card-header bg-white">

                        <h5 class="mb-0">

                            <i class="bi bi-wallet2 me-2"></i>

                            Payment Information

                        </h5>

                    </div>


                    <div class="card-body">

                        <?php if ($currentDue <= 0): ?>

                            <div class="alert alert-success">

                                <i class="bi bi-check-circle me-2"></i>

                                This invoice has already been fully paid.

                            </div>

                        <?php else: ?>

                            <form method="POST">

                                <div class="mb-3">

                                    <label
                                        for="amount"
                                        class="form-label">

                                        Payment Amount

                                    </label>

                                    <div class="input-group">

                                        <span class="input-group-text">
                                            ৳
                                        </span>

                                        <input
                                            type="number"
                                            name="amount"
                                            id="amount"
                                            class="form-control"
                                            step="0.01"
                                            min="0.01"
                                            max="<?= htmlspecialchars(
                                                number_format(
                                                    $currentDue,
                                                    2,
                                                    '.',
                                                    ''
                                                )
                                            ); ?>"
                                            value="<?= htmlspecialchars(
                                                number_format(
                                                    $currentDue,
                                                    2,
                                                    '.',
                                                    ''
                                                )
                                            ); ?>"
                                            required
                                        >

                                    </div>

                                    <small class="text-muted">

                                        Maximum:
                                        ৳<?= number_format(
                                            $currentDue,
                                            2
                                        ); ?>

                                    </small>

                                </div>


                                <div class="mb-3">

                                    <label
                                        for="payment_method"
                                        class="form-label">

                                        Payment Method

                                    </label>

                                    <select
                                        name="payment_method"
                                        id="payment_method"
                                        class="form-select"
                                        required>

                                        <option value="Cash">
                                            Cash
                                        </option>

                                        <option value="Card">
                                            Card
                                        </option>

                                        <option value="Mobile Banking">
                                            Mobile Banking
                                        </option>

                                        <option value="Other">
                                            Other
                                        </option>

                                    </select>

                                </div>


                                <div class="mb-4">

                                    <label
                                        for="notes"
                                        class="form-label">

                                        Notes

                                    </label>

                                    <textarea
                                        name="notes"
                                        id="notes"
                                        class="form-control"
                                        rows="4"
                                        placeholder="Optional payment note..."
                                    ></textarea>

                                </div>


                                <div class="d-flex justify-content-end gap-2">

                                    <a
                                        href="view.php?id=<?= $invoiceId; ?>"
                                        class="btn btn-secondary">

                                        Cancel

                                    </a>


                                    <button
                                        type="submit"
                                        class="btn btn-success">

                                        <i class="bi bi-check-circle me-1"></i>

                                        Receive Payment

                                    </button>

                                </div>

                            </form>

                        <?php endif; ?>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>