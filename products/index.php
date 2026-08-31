<?php

$basePath = "../";
$pageTitle = "Products & Parts";

require_once "../includes/header.php";
require_once "../config/database.php";

require_once "../includes/navbar.php";
require_once "../includes/sidebar.php";

?>

<div class="content">

    <div class="container-fluid">

        <?php if (isset($_GET["success"])): ?>

            <div class="alert alert-success alert-dismissible fade show">

                <?php echo htmlspecialchars($_GET["success"]); ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert">
                </button>

            </div>

        <?php endif; ?>


        <?php if (isset($_GET["error"])): ?>

            <div class="alert alert-danger alert-dismissible fade show">

                <?php echo htmlspecialchars($_GET["error"]); ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert">
                </button>

            </div>

        <?php endif; ?>


        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h2 class="fw-bold">
                    Products & Parts
                </h2>

                <p class="text-muted mb-0">
                    Manage products, mobile parts and inventory information.
                </p>

            </div>


            <div>

                <a
                    href="categories.php"
                    class="btn btn-outline-secondary me-2">

                    <i class="bi bi-tags"></i>

                    Categories

                </a>


                <a
                    href="suppliers.php"
                    class="btn btn-outline-secondary me-2">

                    <i class="bi bi-truck"></i>

                    Suppliers

                </a>


                <a
                    href="add.php"
                    class="btn btn-primary">

                    <i class="bi bi-plus-circle"></i>

                    Add Product / Part

                </a>

            </div>

        </div>


        <!-- Search and Filter -->

        <div class="card shadow-sm border-0 mb-4">

            <div class="card-body">

                <form method="GET" action="index.php">

                    <div class="row g-3">

                        <div class="col-md-5">

                            <label class="form-label">
                                Search
                            </label>

                            <input
                                type="text"
                                name="search"
                                class="form-control"
                                placeholder="Search by product/part name"
                                value="<?php echo htmlspecialchars($_GET["search"] ?? ""); ?>">

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
                                    value="Product"
                                    <?php
                                    echo (($_GET["type"] ?? "") === "Product")
                                        ? "selected"
                                        : "";
                                    ?>>

                                    Product

                                </option>

                                <option
                                    value="Part"
                                    <?php
                                    echo (($_GET["type"] ?? "") === "Part")
                                        ? "selected"
                                        : "";
                                    ?>>

                                    Part

                                </option>

                            </select>

                        </div>


                        <div class="col-md-4 d-flex align-items-end">

                            <button
                                type="submit"
                                class="btn btn-primary me-2">

                                <i class="bi bi-search"></i>

                                Search

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


        <!-- Product Table -->

        <div class="card shadow-sm border-0">

            <div class="card-body">

                <div class="d-flex justify-content-between align-items-center mb-3">

                    <h5 class="card-title mb-0">
                        Product & Part List
                    </h5>


                    <?php

                    $countSql = "
                        SELECT COUNT(*) AS total
                        FROM products
                    ";

                    $countResult = $conn->query($countSql);

                    $totalProducts = 0;

                    if ($countResult) {

                        $countData =
                            $countResult->fetch_assoc();

                        $totalProducts =
                            $countData["total"];

                    }

                    ?>


                    <span class="badge bg-primary">

                        <?php echo $totalProducts; ?>

                        Items

                    </span>

                </div>


                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead class="table-light">

                            <tr>

                                <th>ID</th>

                                <th>Name</th>

                                <th>Category</th>

                                <th>Type</th>

                                <th>Purchase Price</th>

                                <th>Selling Price</th>

                                <th>Quantity</th>

                                <th>Status</th>

                                <th class="text-center">
                                    Actions
                                </th>

                            </tr>

                        </thead>


                        <tbody>

                        <?php

                        $search =
                            trim($_GET["search"] ?? "");

                        $type =
                            trim($_GET["type"] ?? "");


                        $sql = "
                            SELECT
                                p.id,
                                p.product_name,
                                c.category_name,
                                p.item_type,
                                p.purchase_price,
                                p.selling_price,
                                p.quantity,
                                p.minimum_stock,
                                p.status

                            FROM products p

                            LEFT JOIN categories c
                                ON p.category_id = c.id

                            WHERE 1=1
                        ";


                        $params = [];

                        $types = "";


                        if ($search !== "") {

                            $sql .= "
                                AND p.product_name LIKE ?
                            ";

                            $searchValue =
                                "%" . $search . "%";

                            $params[] =
                                $searchValue;

                            $types .= "s";

                        }


                        if ($type !== "") {

                            $sql .= "
                                AND p.item_type = ?
                            ";

                            $params[] =
                                $type;

                            $types .= "s";

                        }


                        $sql .= "
                            ORDER BY p.id ASC
                        ";


                        $stmt =
                            $conn->prepare($sql);


                        if (!empty($params)) {

                            $stmt->bind_param(
                                $types,
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
                                $product =
                                $result->fetch_assoc()
                            ):

                        ?>

                            <tr>

                                <td>
                                    <?php
                                    echo $product["id"];
                                    ?>
                                </td>


                                <td>

                                    <strong>

                                        <?php

                                        echo htmlspecialchars(
                                            $product["product_name"]
                                        );

                                        ?>

                                    </strong>

                                </td>


                                <td>

                                    <?php

                                    echo $product["category_name"]
                                        ? htmlspecialchars(
                                            $product["category_name"]
                                        )
                                        : "N/A";

                                    ?>

                                </td>


                                <td>

                                    <?php
                                    if (
                                        $product["item_type"]
                                        === "Product"
                                    ):
                                    ?>

                                        <span class="badge bg-primary">
                                            Product
                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-info text-dark">
                                            Part
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    ৳<?php

                                    echo number_format(
                                        $product["purchase_price"],
                                        2
                                    );

                                    ?>

                                </td>


                                <td>

                                    ৳<?php

                                    echo number_format(
                                        $product["selling_price"],
                                        2
                                    );

                                    ?>

                                </td>


                                <td>

                                    <?php

                                    $quantity =
                                        (int) $product["quantity"];

                                    $minimumStock =
                                        (int) $product["minimum_stock"];


                                    if ($quantity <= 0):

                                    ?>

                                        <span class="badge bg-danger">

                                            Out of Stock

                                        </span>

                                    <?php
                                    elseif (
                                        $quantity <=
                                        $minimumStock
                                    ):
                                    ?>

                                        <span
                                            class="badge bg-warning text-dark">

                                            <?php
                                            echo $quantity;
                                            ?>

                                            Low Stock

                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-success">

                                            <?php
                                            echo $quantity;
                                            ?>

                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?php

                                    if (
                                        $product["status"]
                                        === "Active"
                                    ):

                                    ?>

                                        <span class="badge bg-success">
                                            Active
                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-secondary">
                                            Inactive
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td class="text-center">

                                    <!-- View -->

                                    <a
                                        href="view.php?id=<?php echo $product["id"]; ?>"
                                        class="btn btn-sm btn-info text-white"
                                        title="View">

                                        <i class="bi bi-eye"></i>

                                    </a>


                                    <!-- Edit -->

                                    <a
                                        href="edit.php?id=<?php echo $product["id"]; ?>"
                                        class="btn btn-sm btn-warning"
                                        title="Edit">

                                        <i class="bi bi-pencil"></i>

                                    </a>


                                    <!-- Delete -->

                                    <form
                                        action="delete.php"
                                        method="POST"
                                        class="d-inline"
                                        onsubmit="return confirm('Are you sure you want to delete this item?');">

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?php echo $product["id"]; ?>">


                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-danger"
                                            title="Delete">

                                            <i class="bi bi-trash"></i>

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

                                    No products or parts found.

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