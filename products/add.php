<?php
ob_start();
if (session_status()===PHP_SESSION_NONE) session_start();
?>
<?php

$basePath = "../";
$pageTitle = "Add Product / Part";

require_once "../includes/header.php";
require_once "../config/database.php";

require_once "../includes/navbar.php";
require_once "../includes/sidebar.php";

?>

<div class="content">

    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h2 class="fw-bold">
                    Add Product / Part
                </h2>

                <p class="text-muted mb-0">
                    Add a new product or mobile service part.
                </p>

            </div>

            <a
                href="index.php"
                class="btn btn-secondary">

                <i class="bi bi-arrow-left"></i>

                Back

            </a>

        </div>


        <?php

        $formData = [
            "product_name" => "",
            "category_id" => "",
            "item_type" => "Product",
            "purchase_price" => "",
            "selling_price" => "",
            "quantity" => "",
            "minimum_stock" => "",
            "supplier_id" => "",
            "status" => "Active"
        ];


        $error = "";


        if ($_SERVER["REQUEST_METHOD"] === "POST") {

            $formData["product_name"] =
                trim($_POST["product_name"] ?? "");

            $formData["category_id"] =
                trim($_POST["category_id"] ?? "");

            $formData["item_type"] =
                trim($_POST["item_type"] ?? "");

            $formData["purchase_price"] =
                trim($_POST["purchase_price"] ?? "");

            $formData["selling_price"] =
                trim($_POST["selling_price"] ?? "");

            $formData["quantity"] =
                trim($_POST["quantity"] ?? "");

            $formData["minimum_stock"] =
                trim($_POST["minimum_stock"] ?? "");

            $formData["supplier_id"] =
                trim($_POST["supplier_id"] ?? "");

            $formData["status"] =
                trim($_POST["status"] ?? "");


            if (
                $formData["product_name"] === "" ||
                $formData["item_type"] === "" ||
                $formData["purchase_price"] === "" ||
                $formData["selling_price"] === "" ||
                $formData["quantity"] === "" ||
                $formData["minimum_stock"] === ""
            ) {

                $error = "Please fill in all required fields.";

            } elseif (
                !in_array(
                    $formData["item_type"],
                    ["Product", "Part"],
                    true
                )
            ) {

                $error = "Invalid item type.";

            } elseif (
                !is_numeric($formData["purchase_price"]) ||
                !is_numeric($formData["selling_price"])
            ) {

                $error = "Price must be a valid number.";

            } elseif (
                $formData["purchase_price"] < 0 ||
                $formData["selling_price"] < 0
            ) {

                $error = "Price cannot be negative.";

            } elseif (
                filter_var(
                    $formData["quantity"],
                    FILTER_VALIDATE_INT
                ) === false ||
                $formData["quantity"] < 0
            ) {

                $error = "Quantity must be a valid non-negative integer.";

            } elseif (
                filter_var(
                    $formData["minimum_stock"],
                    FILTER_VALIDATE_INT
                ) === false ||
                $formData["minimum_stock"] < 0
            ) {

                $error = "Minimum stock must be a valid non-negative integer.";

            }


            if ($error === "") {

                $categoryId = !empty($formData["category_id"])
                    ? (int) $formData["category_id"]
                    : null;

                $supplierId = !empty($formData["supplier_id"])
                    ? (int) $formData["supplier_id"]
                    : null;

                $purchasePrice =
                    (float) $formData["purchase_price"];

                $sellingPrice =
                    (float) $formData["selling_price"];

                $quantity =
                    (int) $formData["quantity"];

                $minimumStock =
                    (int) $formData["minimum_stock"];



                // Find smallest available ID
                $newId = 1;
                $idResult = $conn->query("SELECT id FROM products ORDER BY id ASC");
                if ($idResult) {
                    while ($row = $idResult->fetch_assoc()) {
                        if ((int)$row['id'] != $newId) {
                            break;
                        }
                        $newId++;
                    }
                }

                $sql = "
                    INSERT INTO products
                    (
                        id,
                        product_name,
                        category_id,
                        item_type,
                        purchase_price,
                        selling_price,
                        quantity,
                        minimum_stock,
                        supplier_id,
                        status
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ";


                $stmt = $conn->prepare($sql);


                if (!$stmt) {

                    $error = "Database query failed.";

                } else {

                    $stmt->bind_param(
                        "isisddiiis",
                        $newId,
                        $formData["product_name"],
                        $categoryId,
                        $formData["item_type"],
                        $purchasePrice,
                        $sellingPrice,
                        $quantity,
                        $minimumStock,
                        $supplierId,
                        $formData["status"]
                    );


                    if ($stmt->execute()) {

                        $stmt->close();
                        $conn->close();

                        $_SESSION["success_message"]="Product/Part added successfully.";
                        header("Location: index.php");

                        exit();

                    } else {

                        $error =
                            "Failed to add product/part.";

                        $stmt->close();

                    }

                }

            }

        }

        ?>


        <?php if ($error !== ""): ?>

            <div class="alert alert-danger">

                <?php echo htmlspecialchars($error); ?>

            </div>

        <?php endif; ?>


        <div class="card shadow-sm border-0">

            <div class="card-body">

                <form
                    method="POST"
                    action="add.php">

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
                                value="<?php echo htmlspecialchars($formData["product_name"]); ?>">

                        </div>


                        <div class="col-md-3">

                            <label class="form-label">

                                Type

                                <span class="text-danger">*</span>

                            </label>

                            <select
                                name="item_type"
                                class="form-select"
                                required>

                                <option
                                    value="Product"
                                    <?php echo $formData["item_type"] === "Product" ? "selected" : ""; ?>>

                                    Product

                                </option>

                                <option
                                    value="Part"
                                    <?php echo $formData["item_type"] === "Part" ? "selected" : ""; ?>>

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
                                    <?php echo $formData["status"] === "Active" ? "selected" : ""; ?>>

                                    Active

                                </option>

                                <option
                                    value="Inactive"
                                    <?php echo $formData["status"] === "Inactive" ? "selected" : ""; ?>>

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

                                $categorySql = "
                                    SELECT id, category_name
                                    FROM categories
                                    ORDER BY category_name ASC
                                ";

                                $categoryResult =
                                    $conn->query($categorySql);


                                while (
                                    $category =
                                    $categoryResult->fetch_assoc()
                                ):

                                ?>

                                    <option
                                        value="<?php echo $category["id"]; ?>"
                                        <?php
                                        echo (string) $formData["category_id"] ===
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

                                $supplierSql = "
                                    SELECT id, supplier_name
                                    FROM suppliers
                                    ORDER BY supplier_name ASC
                                ";

                                $supplierResult =
                                    $conn->query($supplierSql);


                                while (
                                    $supplier =
                                    $supplierResult->fetch_assoc()
                                ):

                                ?>

                                    <option
                                        value="<?php echo $supplier["id"]; ?>"
                                        <?php
                                        echo (string) $formData["supplier_id"] ===
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

                                <span class="text-danger">*</span>

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
                                    value="<?php echo htmlspecialchars($formData["purchase_price"]); ?>">

                            </div>

                        </div>


                        <div class="col-md-4">

                            <label class="form-label">

                                Selling Price

                                <span class="text-danger">*</span>

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
                                    value="<?php echo htmlspecialchars($formData["selling_price"]); ?>">

                            </div>

                        </div>


                        <div class="col-md-4">

                            <label class="form-label">

                                Quantity

                                <span class="text-danger">*</span>

                            </label>

                            <input
                                type="number"
                                name="quantity"
                                class="form-control"
                                min="0"
                                step="1"
                                required
                                value="<?php echo htmlspecialchars($formData["quantity"]); ?>">

                        </div>


                        <div class="col-md-4">

                            <label class="form-label">

                                Minimum Stock Level

                                <span class="text-danger">*</span>

                            </label>

                            <input
                                type="number"
                                name="minimum_stock"
                                class="form-control"
                                min="0"
                                step="1"
                                required
                                value="<?php echo htmlspecialchars($formData["minimum_stock"]); ?>">

                            <small class="text-muted">
                                Used to identify low-stock items.
                            </small>

                        </div>


                        <div class="col-12 mt-4">

                            <button
                                type="submit"
                                class="btn btn-primary">

                                <i class="bi bi-save"></i>

                                Save Product / Part

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