<?php

$basePath = "../";
$pageTitle = "Add Stock";

require_once "../includes/header.php";
require_once "../config/database.php";

require_once "../includes/navbar.php";
require_once "../includes/sidebar.php";


$error = "";
$success = "";
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if(isset($_SESSION["stock_success"])){$success=$_SESSION["stock_success"]; unset($_SESSION["stock_success"]);}


/*
 * Process form submission
 */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $productId = filter_input(
        INPUT_POST,
        "product_id",
        FILTER_VALIDATE_INT
    );

    $quantity = filter_input(
        INPUT_POST,
        "quantity",
        FILTER_VALIDATE_INT
    );

    $transactionType =
        trim($_POST["transaction_type"] ?? "");

    $notes =
        trim($_POST["notes"] ?? "");


    /*
     * Basic validation
     */

    if (!$productId) {

        $error =
            "Please select a valid product or part.";

    } elseif (!$quantity || $quantity < 1) {

        $error =
            "Quantity must be at least 1.";

    } elseif (
        !in_array(
            $transactionType,
            ["Purchase", "Return"],
            true
        )
    ) {

        $error =
            "Invalid stock transaction type.";

    }


    /*
     * Continue if validation passed
     */

    if ($error === "") {

        /*
         * Get current stock.
         *
         * FOR UPDATE is used because we are going
         * to modify the stock inside a transaction.
         */

        $selectSql = "
            SELECT
                id,
                quantity,
                status

            FROM products

            WHERE id = ?

            LIMIT 1
        ";


        $selectStmt =
            $conn->prepare($selectSql);


        if (!$selectStmt) {

            $error =
                "Database query failed.";

        } else {

            $selectStmt->bind_param(
                "i",
                $productId
            );

            $selectStmt->execute();

            $result =
                $selectStmt->get_result();

            $product =
                $result->fetch_assoc();

            $selectStmt->close();


            if (!$product) {

                $error =
                    "Product/Part not found.";

            } elseif (
                $product["status"] !== "Active"
            ) {

                $error =
                    "This product/part is inactive.";

            }

        }

    }


    /*
     * Perform stock operation
     */

    if ($error === "") {

        $previousQuantity =
            (int) $product["quantity"];

        $newQuantity =
            $previousQuantity + $quantity;


        /*
         * Get logged-in admin ID.
         */

        $createdBy =
            isset($_SESSION["admin_id"])
                ? (int) $_SESSION["admin_id"]
                : null;


        /*
         * Start database transaction.
         */

        $conn->begin_transaction();


        try {

            /*
             * Update current stock.
             */

            $updateSql = "
                UPDATE products

                SET quantity = ?

                WHERE id = ?
            ";


            $updateStmt =
                $conn->prepare($updateSql);


            if (!$updateStmt) {

                throw new Exception(
                    "Unable to prepare stock update."
                );

            }


            $updateStmt->bind_param(
                "ii",
                $newQuantity,
                $productId
            );


            if (!$updateStmt->execute()) {

                throw new Exception(
                    "Unable to update product stock."
                );

            }


            $updateStmt->close();


            /*
             * Insert stock transaction.
             *
             * Your table does not have
             * previous_quantity/new_quantity,
             * so we only store the quantity changed.
             */

            $transactionSql = "
                INSERT INTO stock_transactions
                (
                    product_id,
                    transaction_type,
                    quantity,
                    reference_id,
                    notes,
                    transaction_date,
                    created_by
                )

                VALUES
                (
                    ?,
                    ?,
                    ?,
                    NULL,
                    ?,
                    NOW(),
                    ?
                )
            ";


            $transactionStmt =
                $conn->prepare(
                    $transactionSql
                );


            if (!$transactionStmt) {

                throw new Exception(
                    "Unable to prepare stock transaction."
                );

            }


            $transactionStmt->bind_param(
                "isisi",
                $productId,
                $transactionType,
                $quantity,
                $notes,
                $createdBy
            );


            if (!$transactionStmt->execute()) {

                throw new Exception(
                    "Unable to save stock transaction."
                );

            }


            $transactionStmt->close();


            /*
             * Commit everything.
             */

            $conn->commit();
            $_SESSION["stock_success"] = "Stock added successfully.";
            header("Location: add.php");
            exit;

            $conn->close();


            header(
                "Location: index.php?success=" .
                urlencode(
                    "Stock added successfully."
                )
            );

            exit();


        } catch (Exception $e) {

            /*
             * Undo database changes.
             */

            $conn->rollback();

            $error =
                "Stock operation failed. Please try again.";

        }

    }

}

?>


<div class="content">
<?php if ($success !== ""): ?><div class="alert alert-success alert-dismissible fade show" role="alert"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <div class="container-fluid">


        <!-- Page Header -->

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h2 class="fw-bold">
                    Add Stock
                </h2>

                <p class="text-muted mb-0">
                    Add purchased or returned stock.
                </p>

            </div>


            <a
                href="index.php"
                class="btn btn-secondary">

                <i class="bi bi-arrow-left"></i>

                Back

            </a>

        </div>


        <!-- Error -->

        <?php if ($error !== ""): ?>

            <div class="alert alert-danger">

                <i class="bi bi-exclamation-triangle me-2"></i>

                <?php
                echo htmlspecialchars($error);
                ?>

            </div>

        <?php endif; ?>


        <!-- Form -->

        <div class="card shadow-sm border-0">

            <div class="card-body">

                <form
                    method="POST"
                    action="add.php">


                    <div class="row g-3">


                        <!-- Product -->

                        <div class="col-md-6">

                            <label class="form-label">

                                Product / Part

                                <span class="text-danger">*</span>

                            </label>


                            <select
                                name="product_id"
                                class="form-select"
                                required>

                                <option value="">
                                    Select Product / Part
                                </option>


                                <?php

                                $productSql = "
                                    SELECT
                                        id,
                                        product_name,
                                        item_type,
                                        quantity

                                    FROM products

                                    WHERE status = 'Active'

                                    ORDER BY
                                        product_name ASC
                                ";


                                $productResult =
                                    $conn->query(
                                        $productSql
                                    );


                                if (
                                    $productResult &&
                                    $productResult->num_rows > 0
                                ):

                                    while (
                                        $product =
                                        $productResult->fetch_assoc()
                                    ):

                                ?>

                                    <option
                                        value="<?php
                                        echo $product["id"];
                                        ?>">

                                        <?php

                                        echo htmlspecialchars(
                                            $product["product_name"]
                                        );

                                        echo " - ";

                                        echo htmlspecialchars(
                                            $product["item_type"]
                                        );

                                        echo " (Current Stock: ";

                                        echo $product["quantity"];

                                        echo ")";

                                        ?>

                                    </option>

                                <?php

                                    endwhile;

                                endif;

                                ?>

                            </select>

                        </div>


                        <!-- Quantity -->

                        <div class="col-md-3">

                            <label class="form-label">

                                Quantity

                                <span class="text-danger">*</span>

                            </label>


                            <input
                                type="number"
                                name="quantity"
                                class="form-control"
                                min="1"
                                step="1"
                                required>

                        </div>


                        <!-- Transaction Type -->

                        <div class="col-md-3">

                            <label class="form-label">

                                Transaction Type

                                <span class="text-danger">*</span>

                            </label>


                            <select
                                name="transaction_type"
                                class="form-select"
                                required>

                                <option value="Purchase">
                                    Purchase
                                </option>

                                <option value="Return">
                                    Return
                                </option>

                            </select>

                        </div>


                        <!-- Notes -->

                        <div class="col-12">

                            <label class="form-label">

                                Notes

                            </label>


                            <textarea
                                name="notes"
                                class="form-control"
                                rows="3"
                                maxlength="255"
                                placeholder="Optional stock information..."></textarea>

                        </div>


                        <!-- Buttons -->

                        <div class="col-12 mt-4">

                            <button
                                type="submit"
                                class="btn btn-primary">

                                <i class="bi bi-plus-circle"></i>

                                Add Stock

                            </button>


                            <a
                                href="index.php"
                                class="btn btn-secondary">

                                Cancel

                            </a>

                        </div>


                    </div>

                </form>

            </div>

        </div>

    </div>

</div>


<?php

$conn->close();

require_once "../includes/footer.php";

?>