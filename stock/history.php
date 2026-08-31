<?php

$basePath = "../";
$pageTitle = "Stock History";

require_once "../includes/header.php";
require_once "../config/database.php";

require_once "../includes/navbar.php";
require_once "../includes/sidebar.php";


/*
|--------------------------------------------------------------------------
| Filter Values
|--------------------------------------------------------------------------
*/

$search = trim($_GET["search"] ?? "");

$type = trim($_GET["type"] ?? "");

$fromDate = trim($_GET["from_date"] ?? "");

$toDate = trim($_GET["to_date"] ?? "");


/*
|--------------------------------------------------------------------------
| Delete Stock History Transaction
|--------------------------------------------------------------------------
|
| History deletion removes only the selected transaction record. It does
| not change the current stock quantity, so deleting an old history entry
| cannot accidentally alter inventory.
|
*/

$deleteMessage = "";
$deleteError = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_history_id"])) {

    $deleteHistoryId = (int) $_POST["delete_history_id"];

    if ($deleteHistoryId > 0) {

        $deleteSql = "
            DELETE FROM stock_transactions
            WHERE id = ?
            LIMIT 1
        ";

        $deleteStmt = $conn->prepare($deleteSql);

        if ($deleteStmt) {

            $deleteStmt->bind_param("i", $deleteHistoryId);

            if ($deleteStmt->execute() && $deleteStmt->affected_rows > 0) {
                $deleteMessage = "Stock history deleted successfully.";
            } else {
                $deleteError = "Unable to delete the selected history record.";
            }

            $deleteStmt->close();

        } else {
            $deleteError = "Unable to process the delete request.";
        }

    } else {
        $deleteError = "Invalid history record.";
    }
}

?>


<div class="content">

    <?php if ($deleteMessage !== ""): ?>

        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-1"></i>
            <?php echo htmlspecialchars($deleteMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>

    <?php endif; ?>

    <?php if ($deleteError !== ""): ?>

        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <?php echo htmlspecialchars($deleteError); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>

    <?php endif; ?>

    <div class="container-fluid">


        <!-- ========================================================= -->
        <!-- PAGE HEADER -->
        <!-- ========================================================= -->

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h2 class="fw-bold">
                    Stock History
                </h2>

                <p class="text-muted mb-0">
                    View and filter all stock transactions.
                </p>

            </div>


            <div>

                <a
                    href="index.php"
                    class="btn btn-secondary me-2">

                    <i class="bi bi-arrow-left"></i>

                    Back

                </a>


                <a
                    href="add.php"
                    class="btn btn-primary">

                    <i class="bi bi-plus-circle"></i>

                    Add Stock

                </a>

            </div>

        </div>


        <!-- ========================================================= -->
        <!-- FILTER -->
        <!-- ========================================================= -->

        <div class="card shadow-sm border-0 mb-4">

            <div class="card-body">

                <form
                    method="GET"
                    action="history.php">

                    <div class="row g-3">


                        <!-- Search -->

                        <div class="col-lg-3 col-md-6">

                            <label class="form-label">
                                Product / Part
                            </label>

                            <input
                                type="text"
                                name="search"
                                class="form-control"
                                placeholder="Search..."
                                value="<?php
                                echo htmlspecialchars($search);
                                ?>">

                        </div>


                        <!-- Transaction Type -->

                        <div class="col-lg-2 col-md-6">

                            <label class="form-label">
                                Transaction
                            </label>

                            <select
                                name="type"
                                class="form-select">

                                <option value="">
                                    All
                                </option>

                                <option
                                    value="Purchase"
                                    <?php
                                    echo $type === "Purchase"
                                        ? "selected"
                                        : "";
                                    ?>>

                                    Purchase

                                </option>

                                <option
                                    value="Return"
                                    <?php
                                    echo $type === "Return"
                                        ? "selected"
                                        : "";
                                    ?>>

                                    Return

                                </option>

                                <option
                                    value="Sale"
                                    <?php
                                    echo $type === "Sale"
                                        ? "selected"
                                        : "";
                                    ?>>

                                    Sale

                                </option>

                                <option
                                    value="Service"
                                    <?php
                                    echo $type === "Service"
                                        ? "selected"
                                        : "";
                                    ?>>

                                    Service

                                </option>

                                <option
                                    value="Used"
                                    <?php
                                    echo $type === "Used"
                                        ? "selected"
                                        : "";
                                    ?>>

                                    Used

                                </option>

                            </select>

                        </div>


                        <!-- From Date -->

                        <div class="col-lg-2 col-md-6">

                            <label class="form-label">
                                From Date
                            </label>

                            <input
                                type="date"
                                name="from_date"
                                class="form-control"
                                value="<?php
                                echo htmlspecialchars($fromDate);
                                ?>">

                        </div>


                        <!-- To Date -->

                        <div class="col-lg-2 col-md-6">

                            <label class="form-label">
                                To Date
                            </label>

                            <input
                                type="date"
                                name="to_date"
                                class="form-control"
                                value="<?php
                                echo htmlspecialchars($toDate);
                                ?>">

                        </div>


                        <!-- Buttons -->

                        <div class="col-lg-3 col-md-6 d-flex align-items-end">

                            <button
                                type="submit"
                                class="btn btn-primary me-2">

                                <i class="bi bi-search"></i>

                                Filter

                            </button>


                            <a
                                href="history.php"
                                class="btn btn-secondary">

                                Reset

                            </a>

                        </div>

                    </div>

                </form>

            </div>

        </div>


        <!-- ========================================================= -->
        <!-- TRANSACTION TABLE -->
        <!-- ========================================================= -->

        <div class="card shadow-sm border-0">

            <div class="card-body">


                <div class="table-responsive">

                    <table class="table table-hover align-middle">


                        <thead class="table-light">

                            <tr>

                                <th>No.</th>

                                <th>Date</th>

                                <th>Product / Part</th>

                                <th>Type</th>

                                <th>Quantity</th>

                                <th>Reference</th>

                                <th>Created By</th>

                                <th>Notes</th>

                                <th>Action</th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php


                        /*
                         * Base Query
                         */

                        $sql = "
                            SELECT

                                st.id,
                                st.product_id,
                                st.transaction_type,
                                st.quantity,
                                st.reference_id,
                                st.notes,
                                st.transaction_date,
                                st.created_by,

                                p.product_name,

                                u.username

                            FROM stock_transactions st

                            INNER JOIN products p
                                ON st.product_id = p.id

                            LEFT JOIN users u
                                ON st.created_by = u.id

                            WHERE 1 = 1
                        ";


                        $params = [];

                        $paramTypes = "";


                        /*
                         * Product Search
                         */

                        if ($search !== "") {

                            $sql .= "
                                AND p.product_name LIKE ?
                            ";

                            $params[] =
                                "%" . $search . "%";

                            $paramTypes .= "s";

                        }


                        /*
                         * Transaction Type
                         */

                        if ($type !== "") {

                            $sql .= "
                                AND st.transaction_type = ?
                            ";

                            $params[] =
                                $type;

                            $paramTypes .= "s";

                        }


                        /*
                         * From Date
                         */

                        if ($fromDate !== "") {

                            $sql .= "
                                AND st.transaction_date >= ?
                            ";

                            $params[] =
                                $fromDate . " 00:00:00";

                            $paramTypes .= "s";

                        }


                        /*
                         * To Date
                         */

                        if ($toDate !== "") {

                            $sql .= "
                                AND st.transaction_date <= ?
                            ";

                            $params[] =
                                $toDate . " 23:59:59";

                            $paramTypes .= "s";

                        }


                        /*
                         * Order
                         */

                        $sql .= "
                            ORDER BY
                                st.transaction_date ASC,
                                st.id ASC
                        ";


                        /*
                         * Prepare
                         */

                        $stmt =
                            $conn->prepare($sql);


                        /*
                         * Bind parameters
                         */

                        if (!empty($params)) {

                            $stmt->bind_param(
                                $paramTypes,
                                ...$params
                            );

                        }


                        /*
                         * Execute
                         */

                        $stmt->execute();


                        $result =
                            $stmt->get_result();


                        $historySerial = 0;

                        if (
                            $result &&
                            $result->num_rows > 0
                        ):


                            while (
                                $transaction =
                                $result->fetch_assoc()
                            ):

                                $historySerial++;

                        ?>


                            <tr>


                                <!-- Sequential History Number -->

                                <td>

                                    <strong>
                                        <?php echo $historySerial; ?>
                                    </strong>

                                </td>


                                <!-- Date -->

                                <td>

                                    <?php

                                    echo date(
                                        "d M Y, h:i A",
                                        strtotime(
                                            $transaction[
                                                "transaction_date"
                                            ]
                                        )
                                    );

                                    ?>

                                </td>


                                <!-- Product -->

                                <td>

                                    <strong>

                                        <?php

                                        echo htmlspecialchars(
                                            $transaction[
                                                "product_name"
                                            ]
                                        );

                                        ?>

                                    </strong>

                                </td>


                                <!-- Transaction Type -->

                                <td>

                                    <?php

                                    $transactionType =
                                        $transaction[
                                            "transaction_type"
                                        ];


                                    if (
                                        $transactionType
                                        === "Purchase"
                                    ):

                                    ?>

                                        <span
                                            class="badge bg-success">

                                            Purchase

                                        </span>

                                    <?php
                                    elseif (
                                        $transactionType
                                        === "Return"
                                    ):
                                    ?>

                                        <span
                                            class="badge bg-info text-dark">

                                            Return

                                        </span>

                                    <?php
                                    elseif (
                                        $transactionType
                                        === "Sale"
                                    ):
                                    ?>

                                        <span
                                            class="badge bg-primary">

                                            Sale

                                        </span>

                                    <?php
                                    elseif (
                                        $transactionType
                                        === "Service"
                                    ):
                                    ?>

                                        <span
                                            class="badge bg-warning text-dark">

                                            Service

                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="badge bg-secondary">

                                            Used

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- Quantity -->

                                <td>

                                    <?php

                                    if (
                                        in_array(
                                            $transactionType,
                                            [
                                                "Sale",
                                                "Service",
                                                "Used"
                                            ],
                                            true
                                        )
                                    ):

                                    ?>

                                        <span
                                            class="text-danger fw-bold">

                                            -<?php
                                            echo $transaction["quantity"];
                                            ?>

                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="text-success fw-bold">

                                            +<?php
                                            echo $transaction["quantity"];
                                            ?>

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- Reference -->

                                <td>

                                    <?php

                                    if (
                                        $transaction["reference_id"]
                                        !== null
                                    ) {

                                        echo "#";
                                        echo htmlspecialchars(
                                            $transaction[
                                                "reference_id"
                                            ]
                                        );

                                    } else {

                                        echo "N/A";

                                    }

                                    ?>

                                </td>


                                <!-- Created By -->

                                <td>

                                    <?php

                                    echo $transaction["username"]
                                        ? htmlspecialchars(
                                            $transaction["username"]
                                        )
                                        : "N/A";

                                    ?>

                                </td>


                                <!-- Notes -->

                                <td>

                                    <?php

                                    echo $transaction["notes"]
                                        ? htmlspecialchars(
                                            $transaction["notes"]
                                        )
                                        : "-";

                                    ?>

                                </td>


                                <!-- Delete -->

                                <td>

                                    <form
                                        method="POST"
                                        action="history.php"
                                        class="d-inline"
                                        onsubmit="return confirm('Are you sure you want to delete this stock history record? This will remove only the history record. Current stock will not change, and the visible history numbers will automatically remain 1, 2, 3, ... .');">

                                        <input
                                            type="hidden"
                                            name="delete_history_id"
                                            value="<?php echo (int) $transaction["id"]; ?>">

                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-danger"
                                            title="Delete history record">

                                            <i class="bi bi-trash"></i>
                                            Delete

                                        </button>

                                    </form>

                                </td>


                            </tr>


                        <?php

                            endwhile;

                        else:

                        ?>

                            <tr>

                                <td
                                    colspan="9"
                                    class="text-center text-muted py-4">

                                    No stock transactions found.

                                </td>

                            </tr>

                        <?php endif; ?>


                        </tbody>

                    </table>

                </div>

            </div>

        </div>

    </div>

</div>


<?php

$stmt->close();

$conn->close();

require_once "../includes/footer.php";

?>