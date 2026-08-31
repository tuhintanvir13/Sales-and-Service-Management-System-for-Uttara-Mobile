<?php

$basePath = "../";
$pageTitle = "Stock Management";

require_once "../includes/header.php";
require_once "../config/database.php";

require_once "../includes/navbar.php";
require_once "../includes/sidebar.php";


/*
|--------------------------------------------------------------------------
| Delete current stock item
|--------------------------------------------------------------------------
|
| The Current Stock page only shows active products/parts. Instead of
| physically deleting the product row (which could break stock history
| records), deletion is handled as a soft delete by marking the item
| inactive. This removes it from Current Stock while preserving history.
|
*/

$deleteMessage = "";
$deleteError = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_stock_id"])) {

    $deleteStockId = (int) $_POST["delete_stock_id"];

    if ($deleteStockId > 0) {

        $deleteSql = "
            UPDATE products
            SET status = 'Inactive'
            WHERE id = ?
              AND status = 'Active'
        ";

        $deleteStmt = $conn->prepare($deleteSql);

        if ($deleteStmt) {

            $deleteStmt->bind_param("i", $deleteStockId);

            if ($deleteStmt->execute() && $deleteStmt->affected_rows > 0) {
                $deleteMessage = "Stock item deleted successfully.";
            } else {
                $deleteError = "Unable to delete the selected stock item.";
            }

            $deleteStmt->close();

        } else {
            $deleteError = "Unable to process the delete request.";
        }

    } else {
        $deleteError = "Invalid stock item.";
    }
}


/*
|--------------------------------------------------------------------------
| Get filter values
|--------------------------------------------------------------------------
*/

$search = trim($_GET["search"] ?? "");

$type = trim($_GET["type"] ?? "");

$stockStatus = trim($_GET["stock_status"] ?? "");


/*
|--------------------------------------------------------------------------
| Summary Statistics
|--------------------------------------------------------------------------
*/


/*
 * Total Products / Parts
 */

$sql = "
    SELECT COUNT(*) AS total
    FROM products
    WHERE status = 'Active'
";

$result = $conn->query($sql);

$totalItems = 0;

if ($result) {

    $row = $result->fetch_assoc();

    $totalItems = (int) $row["total"];

}


/*
 * Available Stock
 */

$sql = "
    SELECT COALESCE(SUM(quantity), 0) AS total_stock
    FROM products
    WHERE status = 'Active'
";

$result = $conn->query($sql);

$totalStock = 0;

if ($result) {

    $row = $result->fetch_assoc();

    $totalStock = (int) $row["total_stock"];

}


/*
 * Low Stock
 */

$sql = "
    SELECT COUNT(*) AS total
    FROM products
    WHERE status = 'Active'
    AND quantity > 0
    AND quantity <= minimum_stock
";

$result = $conn->query($sql);

$lowStock = 0;

if ($result) {

    $row = $result->fetch_assoc();

    $lowStock = (int) $row["total"];

}


/*
 * Out of Stock
 */

$sql = "
    SELECT COUNT(*) AS total
    FROM products
    WHERE status = 'Active'
    AND quantity <= 0
";

$result = $conn->query($sql);

$outOfStock = 0;

if ($result) {

    $row = $result->fetch_assoc();

    $outOfStock = (int) $row["total"];

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
                    Stock Management
                </h2>

                <p class="text-muted mb-0">
                    Monitor and manage product and part inventory.
                </p>

            </div>


            <div>

                <a
                    href="history.php"
                    class="btn btn-outline-secondary me-2">

                    <i class="bi bi-clock-history"></i>

                    Stock History

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
        <!-- SUCCESS MESSAGE -->
        <!-- ========================================================= -->

        <?php if (isset($_GET["success"])): ?>

            <div class="alert alert-success alert-dismissible fade show">

                <i class="bi bi-check-circle me-2"></i>

                <?php
                echo htmlspecialchars($_GET["success"]);
                ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert">
                </button>

            </div>

        <?php endif; ?>


        <!-- ========================================================= -->
        <!-- ERROR MESSAGE -->
        <!-- ========================================================= -->

        <?php if (isset($_GET["error"])): ?>

            <div class="alert alert-danger alert-dismissible fade show">

                <i class="bi bi-exclamation-triangle me-2"></i>

                <?php
                echo htmlspecialchars($_GET["error"]);
                ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert">
                </button>

            </div>

        <?php endif; ?>


        <!-- ========================================================= -->
        <!-- SUMMARY CARDS -->
        <!-- ========================================================= -->

        <div class="row g-4 mb-4">


            <!-- Total Items -->

            <div class="col-xl-3 col-md-6">

                <div class="card border-0 shadow-sm h-100">

                    <div class="card-body">

                        <div class="d-flex justify-content-between">

                            <div>

                                <p class="text-muted mb-1">
                                    Total Items
                                </p>

                                <h3 class="fw-bold mb-0">

                                    <?php
                                    echo $totalItems;
                                    ?>

                                </h3>

                            </div>

                            <div class="fs-2 text-primary">

                                <i class="bi bi-box-seam"></i>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- Available Stock -->

            <div class="col-xl-3 col-md-6">

                <div class="card border-0 shadow-sm h-100">

                    <div class="card-body">

                        <div class="d-flex justify-content-between">

                            <div>

                                <p class="text-muted mb-1">
                                    Available Stock
                                </p>

                                <h3 class="fw-bold mb-0">

                                    <?php
                                    echo $totalStock;
                                    ?>

                                </h3>

                            </div>

                            <div class="fs-2 text-success">

                                <i class="bi bi-boxes"></i>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- Low Stock -->

            <div class="col-xl-3 col-md-6">

                <div class="card border-0 shadow-sm h-100">

                    <div class="card-body">

                        <div class="d-flex justify-content-between">

                            <div>

                                <p class="text-muted mb-1">
                                    Low Stock
                                </p>

                                <h3 class="fw-bold mb-0">

                                    <?php
                                    echo $lowStock;
                                    ?>

                                </h3>

                            </div>

                            <div class="fs-2 text-warning">

                                <i class="bi bi-exclamation-triangle"></i>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


            <!-- Out Of Stock -->

            <div class="col-xl-3 col-md-6">

                <div class="card border-0 shadow-sm h-100">

                    <div class="card-body">

                        <div class="d-flex justify-content-between">

                            <div>

                                <p class="text-muted mb-1">
                                    Out of Stock
                                </p>

                                <h3 class="fw-bold mb-0">

                                    <?php
                                    echo $outOfStock;
                                    ?>

                                </h3>

                            </div>

                            <div class="fs-2 text-danger">

                                <i class="bi bi-x-circle"></i>

                            </div>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- ========================================================= -->
        <!-- FILTER -->
        <!-- ========================================================= -->

        <div class="card shadow-sm border-0 mb-4">

            <div class="card-body">

                <form
                    method="GET"
                    action="index.php">

                    <div class="row g-3">


                        <!-- Search -->

                        <div class="col-lg-4 col-md-6">

                            <label class="form-label">
                                Search
                            </label>

                            <input
                                type="text"
                                name="search"
                                class="form-control"
                                placeholder="Search product or part..."
                                value="<?php
                                echo htmlspecialchars($search);
                                ?>">

                        </div>


                        <!-- Type -->

                        <div class="col-lg-2 col-md-6">

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
                                    value="Product"
                                    <?php
                                    echo $type === "Product"
                                        ? "selected"
                                        : "";
                                    ?>>

                                    Product

                                </option>

                                <option
                                    value="Part"
                                    <?php
                                    echo $type === "Part"
                                        ? "selected"
                                        : "";
                                    ?>>

                                    Part

                                </option>

                            </select>

                        </div>


                        <!-- Stock Status -->

                        <div class="col-lg-3 col-md-6">

                            <label class="form-label">
                                Stock Status
                            </label>

                            <select
                                name="stock_status"
                                class="form-select">

                                <option value="">
                                    All Stock
                                </option>

                                <option
                                    value="in_stock"
                                    <?php
                                    echo $stockStatus === "in_stock"
                                        ? "selected"
                                        : "";
                                    ?>>

                                    In Stock

                                </option>

                                <option
                                    value="low_stock"
                                    <?php
                                    echo $stockStatus === "low_stock"
                                        ? "selected"
                                        : "";
                                    ?>>

                                    Low Stock

                                </option>

                                <option
                                    value="out_of_stock"
                                    <?php
                                    echo $stockStatus === "out_of_stock"
                                        ? "selected"
                                        : "";
                                    ?>>

                                    Out of Stock

                                </option>

                            </select>

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
                                href="index.php"
                                class="btn btn-secondary">

                                Reset

                            </a>

                        </div>

                    </div>

                </form>

            </div>

        </div>


        <!-- ========================================================= -->
        <!-- CURRENT STOCK TABLE -->
        <!-- ========================================================= -->

        <div class="card shadow-sm border-0">

            <div class="card-body">


                <div class="d-flex justify-content-between align-items-center mb-3">

                    <div>

                        <h5 class="card-title mb-1">
                            Current Stock
                        </h5>

                        <p class="text-muted mb-0">
                            Current quantity of products and parts.
                        </p>

                    </div>

                </div>


                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead class="table-light">

                            <tr>

                                <th>ID</th>

                                <th>Name</th>

                                <th>Category</th>

                                <th>Type</th>

                                <th>Current Stock</th>

                                <th>Minimum Stock</th>

                                <th>Status</th>

                                <th>Action</th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php


                        /*
                         * Build query dynamically.
                         */

                        $sql = "
                            SELECT

                                p.id,
                                p.product_name,
                                p.item_type,
                                p.quantity,
                                p.minimum_stock,
                                p.status,

                                c.category_name

                            FROM products p

                            LEFT JOIN categories c
                                ON p.category_id = c.id

                            WHERE p.status = 'Active'
                        ";


                        $params = [];

                        $paramTypes = "";


                        /*
                         * Search
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
                         * Type
                         */

                        if ($type !== "") {

                            $sql .= "
                                AND p.item_type = ?
                            ";

                            $params[] =
                                $type;

                            $paramTypes .= "s";

                        }


                        /*
                         * Stock Status
                         */

                        if ($stockStatus === "in_stock") {

                            $sql .= "
                                AND p.quantity > p.minimum_stock
                            ";

                        } elseif (
                            $stockStatus === "low_stock"
                        ) {

                            $sql .= "
                                AND p.quantity > 0
                                AND p.quantity <= p.minimum_stock
                            ";

                        } elseif (
                            $stockStatus === "out_of_stock"
                        ) {

                            $sql .= "
                                AND p.quantity <= 0
                            ";

                        }


                        $sql .= "
                            ORDER BY
                                p.id ASC
                        ";


                        $stmt =
                            $conn->prepare($sql);


                        if (!empty($params)) {

                            $stmt->bind_param(
                                $paramTypes,
                                ...$params
                            );

                        }


                        $stmt->execute();

                        $result =
                            $stmt->get_result();


                        if (
                            $result &&
                            $result->num_rows > 0
                        ):


                            while (
                                $item =
                                $result->fetch_assoc()
                            ):

                        ?>

                            <tr>


                                <!-- ID -->

                                <td>

                                    <?php
                                    echo $item["id"];
                                    ?>

                                </td>


                                <!-- Name -->

                                <td>

                                    <strong>

                                        <?php
                                        echo htmlspecialchars(
                                            $item["product_name"]
                                        );
                                        ?>

                                    </strong>

                                </td>


                                <!-- Category -->

                                <td>

                                    <?php

                                    echo $item["category_name"]
                                        ? htmlspecialchars(
                                            $item["category_name"]
                                        )
                                        : "N/A";

                                    ?>

                                </td>


                                <!-- Type -->

                                <td>

                                    <?php

                                    if (
                                        $item["item_type"]
                                        === "Product"
                                    ):

                                    ?>

                                        <span
                                            class="badge bg-primary">

                                            Product

                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="badge bg-info text-dark">

                                            Part

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <!-- Current Stock -->

                                <td>

                                    <strong>

                                        <?php
                                        echo $item["quantity"];
                                        ?>

                                    </strong>

                                </td>


                                <!-- Minimum Stock -->

                                <td>

                                    <?php
                                    echo $item["minimum_stock"];
                                    ?>

                                </td>


                                <!-- Status -->

                                <td>

                                    <?php

                                    $quantity =
                                        (int) $item["quantity"];

                                    $minimum =
                                        (int) $item["minimum_stock"];


                                    if ($quantity <= 0):

                                    ?>

                                        <span
                                            class="badge bg-danger">

                                            Out of Stock

                                        </span>

                                    <?php
                                    elseif (
                                        $quantity <= $minimum
                                    ):
                                    ?>

                                        <span
                                            class="badge bg-warning text-dark">

                                            Low Stock

                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="badge bg-success">

                                            In Stock

                                        </span>

                                    <?php endif; ?>

                                </td>

                                <!-- Delete -->

                                <td>

                                    <form
                                        method="POST"
                                        action="index.php"
                                        class="d-inline"
                                        onsubmit="return confirm('Are you sure you want to delete this stock item? It will be removed from Current Stock.');">

                                        <input
                                            type="hidden"
                                            name="delete_stock_id"
                                            value="<?php echo (int) $item["id"]; ?>">

                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-danger"
                                            title="Delete stock item">

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
                                    colspan="8"
                                    class="text-center text-muted py-4">

                                    No stock items found.

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