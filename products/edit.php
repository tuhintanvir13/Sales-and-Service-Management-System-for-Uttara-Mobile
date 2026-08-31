<?php

$basePath = "../";
$pageTitle = "Edit Product / Part";

require_once "../includes/header.php";
require_once "../config/database.php";

require_once "../includes/navbar.php";
require_once "../includes/sidebar.php";


$id = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);


if (!$id) {

    header(
        "Location: index.php?error=Invalid product/part ID."
    );

    exit();

}


$sql = "
    SELECT *
    FROM products
    WHERE id = ?
    LIMIT 1
";


$stmt = $conn->prepare($sql);

$stmt->bind_param("i", $id);

$stmt->execute();

$result = $stmt->get_result();

$product = $result->fetch_assoc();

$stmt->close();


if (!$product) {

    header(
        "Location: index.php?error=Product/Part not found."
    );

    exit();

}


$error = "";


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $productName =
        trim($_POST["product_name"] ?? "");

    $categoryId =
        trim($_POST["category_id"] ?? "");

    $itemType =
        trim($_POST["item_type"] ?? "");

    $purchasePrice =
        trim($_POST["purchase_price"] ?? "");

    $sellingPrice =
        trim($_POST["selling_price"] ?? "");

    $quantity =
        trim($_POST["quantity"] ?? "");

    $minimumStock =
        trim($_POST["minimum_stock"] ?? "");

    $supplierId =
        trim($_POST["supplier_id"] ?? "");

    $status =
        trim($_POST["status"] ?? "");


    if (
        $productName === "" ||
        $purchasePrice === "" ||
        $sellingPrice === "" ||
        $quantity === "" ||
        $minimumStock === ""
    ) {

        $error =
            "Please fill in all required fields.";

    } elseif (
        !in_array(
            $itemType,
            ["Product", "Part"],
            true
        )
    ) {

        $error =
            "Invalid product/part type.";

    } elseif (
        !is_numeric($purchasePrice) ||
        !is_numeric($sellingPrice)
    ) {

        $error =
            "Price must be a valid number.";

    } elseif (
        $purchasePrice < 0 ||
        $sellingPrice < 0
    ) {

        $error =
            "Price cannot be negative.";

    } elseif (
        filter_var(
            $quantity,
            FILTER_VALIDATE_INT
        ) === false ||
        $quantity < 0
    ) {

        $error =
            "Quantity must be a valid non-negative integer.";

    } elseif (
        filter_var(
            $minimumStock,
            FILTER_VALIDATE_INT
        ) === false ||
        $minimumStock < 0
    ) {

        $error =
            "Minimum stock must be a valid non-negative integer.";

    }


    if ($error === "") {

        $categoryId =
            $categoryId !== ""
                ? (int) $categoryId
                : null;

        $supplierId =
            $supplierId !== ""
                ? (int) $supplierId
                : null;

        $purchasePrice =
            (float) $purchasePrice;

        $sellingPrice =
            (float) $sellingPrice;

        $quantity =
            (int) $quantity;

        $minimumStock =
            (int) $minimumStock;


        $updateSql = "
            UPDATE products
            SET
                product_name = ?,
                category_id = ?,
                item_type = ?,
                purchase_price = ?,
                selling_price = ?,
                quantity = ?,
                minimum_stock = ?,
                supplier_id = ?,
                status = ?
            WHERE id = ?
        ";


        $updateStmt =
            $conn->prepare($updateSql);


        if (!$updateStmt) {

            $error =
                "Database query failed.";

        } else {

            $updateStmt->bind_param(
                "sisddiiisi",
                $productName,
                $categoryId,
                $itemType,
                $purchasePrice,
                $sellingPrice,
                $quantity,
                $minimumStock,
                $supplierId,
                $status,
                $id
            );


            if ($updateStmt->execute()) {

                $updateStmt->close();
                $conn->close();

                header(
                    "Location: index.php?success=" .
                    urlencode(
                        "Product/Part updated successfully."
                    )
                );

                exit();

            } else {

                $error =
                    "Failed to update product/part.";

            }


            $updateStmt->close();

        }

    }


    $product["product_name"] = $productName;
    $product["category_id"] = $categoryId;
    $product["item_type"] = $itemType;
    $product["purchase_price"] = $purchasePrice;
    $product["selling_price"] = $sellingPrice;
    $product["quantity"] = $quantity;
    $product["minimum_stock"] = $minimumStock;
    $product["supplier_id"] = $supplierId;
    $product["status"] = $status;

}

?>

<div class="content">

    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h2 class="fw-bold">
                    Edit Product / Part
                </h2>

                <p class="text-muted mb-0">
                    Update product or part information.
                </p>

            </div>

            <a
                href="index.php"
                class="btn btn-secondary">

                <i class="bi bi-arrow-left"></i>

                Back

            </a>

        </div>


        <?php if ($error !== ""): ?>

            <div class="alert alert-danger">

                <?php echo htmlspecialchars($error); ?>

            </div>

        <?php endif; ?>


        <div class="card shadow-sm border-0">

            <div class="card-body">

                <form
                    method="POST"
                    action="edit.php?id=<?php echo $id; ?>">

                    <div class="row g-3">

                        <div class="col-md-6">

                            <label class="form-label">

                                Product / Part Name

                                <span class="text-danger">*</span>

                            </label>

                            <input
                                type="text"
                                name="product_name"
                                class="form-control"
                                maxlength="150"
                                required
                                value="<?php echo htmlspecialchars($product["product_name"]); ?>">

                        </div>


                        <div class="col-md-3">

                            <label class="form-label">
                                Type
                            </label>

                            <select
                                name="item_type"
                                class="form-select">

                                <option
                                    value="Product"
                                    <?php echo $product["item_type"] === "Product" ? "selected" : ""; ?>>

                                    Product

                                </option>

                                <option
                                    value="Part"
                                    <?php echo $product["item_type"] === "Part" ? "selected" : ""; ?>>

                                    Part

                                </option>

                            </select>

                        </div>


                        <div class="col-md-3">

                            <label class="form-label">
                                Status
                            </label>

                            <select
                                name="status"
                                class="form-select">

                                <option
                                    value="Active"
                                    <?php echo $product["status"] === "Active" ? "selected" : ""; ?>>

                                    Active

                                </option>

                                <option
                                    value="Inactive"
                                    <?php echo $product["status"] === "Inactive" ? "selected" : ""; ?>>

                                    Inactive

                                </option>

                            </select>

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Category
                            </label>

                            <select
                                name="category_id"
                                class="form-select">

                                <option value="">
                                    Select Category
                                </option>

                                <?php

                                $categoryResult =
                                    $conn->query(
                                        "
                                        SELECT id, category_name
                                        FROM categories
                                        ORDER BY category_name ASC
                                        "
                                    );


                                while (
                                    $category =
                                    $categoryResult->fetch_assoc()
                                ):

                                ?>

                                    <option
                                        value="<?php echo $category["id"]; ?>"
                                        <?php
                                        echo (string) $product["category_id"] ===
                                            (string) $category["id"]
                                            ? "selected"
                                            : "";
                                        ?>>

                                        <?php
                                        echo htmlspecialchars(
                                            $category["category_name"]
                                        );
                                        ?>

                                    </option>

                                <?php endwhile; ?>

                            </select>

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Supplier
                            </label>

                            <select
                                name="supplier_id"
                                class="form-select">

                                <option value="">
                                    Select Supplier
                                </option>

                                <?php

                                $supplierResult =
                                    $conn->query(
                                        "
                                        SELECT id, supplier_name
                                        FROM suppliers
                                        ORDER BY supplier_name ASC
                                        "
                                    );


                                while (
                                    $supplier =
                                    $supplierResult->fetch_assoc()
                                ):

                                ?>

                                    <option
                                        value="<?php echo $supplier["id"]; ?>"
                                        <?php
                                        echo (string) $product["supplier_id"] ===
                                            (string) $supplier["id"]
                                            ? "selected"
                                            : "";
                                        ?>>

                                        <?php
                                        echo htmlspecialchars(
                                            $supplier["supplier_name"]
                                        );
                                        ?>

                                    </option>

                                <?php endwhile; ?>

                            </select>

                        </div>


                        <div class="col-md-4">

                            <label class="form-label">
                                Purchase Price
                            </label>

                            <div class="input-group">

                                <span class="input-group-text">
                                    ৳
                                </span>

                                <input
                                    type="number"
                                    name="purchase_price"
                                    class="form-control"
                                    min="0"
                                    step="0.01"
                                    required
                                    value="<?php echo htmlspecialchars($product["purchase_price"]); ?>">

                            </div>

                        </div>


                        <div class="col-md-4">

                            <label class="form-label">
                                Selling Price
                            </label>

                            <div class="input-group">

                                <span class="input-group-text">
                                    ৳
                                </span>

                                <input
                                    type="number"
                                    name="selling_price"
                                    class="form-control"
                                    min="0"
                                    step="0.01"
                                    required
                                    value="<?php echo htmlspecialchars($product["selling_price"]); ?>">

                            </div>

                        </div>


                        <div class="col-md-4">

                            <label class="form-label">
                                Quantity
                            </label>

                            <input
                                type="number"
                                name="quantity"
                                class="form-control"
                                min="0"
                                step="1"
                                required
                                value="<?php echo htmlspecialchars($product["quantity"]); ?>">

                        </div>


                        <div class="col-md-4">

                            <label class="form-label">
                                Minimum Stock
                            </label>

                            <input
                                type="number"
                                name="minimum_stock"
                                class="form-control"
                                min="0"
                                step="1"
                                required
                                value="<?php echo htmlspecialchars($product["minimum_stock"]); ?>">

                        </div>


                        <div class="col-12 mt-4">

                            <button
                                type="submit"
                                class="btn btn-primary">

                                <i class="bi bi-save"></i>

                                Update Product / Part

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