<?php

$basePath = "../";
$pageTitle = "View Product / Part";

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
    SELECT
        p.*,
        c.category_name,
        s.supplier_name
    FROM products p

    LEFT JOIN categories c
        ON p.category_id = c.id

    LEFT JOIN suppliers s
        ON p.supplier_id = s.id

    WHERE p.id = ?

    LIMIT 1
";


$stmt = $conn->prepare($sql);

$stmt->bind_param("i", $id);

$stmt->execute();

$result = $stmt->get_result();

$product = $result->fetch_assoc();


if (!$product) {

    $stmt->close();
    $conn->close();

    header(
        "Location: index.php?error=Product/Part not found."
    );

    exit();

}

?>

<div class="content">

    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h2 class="fw-bold">
                    Product / Part Details
                </h2>

                <p class="text-muted mb-0">
                    View complete inventory information.
                </p>

            </div>

            <div>

                <a
                    href="edit.php?id=<?php echo $product["id"]; ?>"
                    class="btn btn-warning">

                    <i class="bi bi-pencil"></i>

                    Edit

                </a>

                <a
                    href="index.php"
                    class="btn btn-secondary">

                    <i class="bi bi-arrow-left"></i>

                    Back

                </a>

            </div>

        </div>


        <div class="card shadow-sm border-0">

            <div class="card-body">

                <div class="row g-4">

                    <div class="col-md-6">

                        <label class="text-muted">
                            Product / Part ID
                        </label>

                        <h5>
                            #<?php echo $product["id"]; ?>
                        </h5>

                    </div>


                    <div class="col-md-6">

                        <label class="text-muted">
                            Name
                        </label>

                        <h5>
                            <?php
                            echo htmlspecialchars(
                                $product["product_name"]
                            );
                            ?>
                        </h5>

                    </div>


                    <div class="col-md-4">

                        <label class="text-muted">
                            Type
                        </label>

                        <h5>

                            <?php if ($product["item_type"] === "Product"): ?>

                                <span class="badge bg-primary">
                                    Product
                                </span>

                            <?php else: ?>

                                <span class="badge bg-info text-dark">
                                    Part
                                </span>

                            <?php endif; ?>

                        </h5>

                    </div>


                    <div class="col-md-4">

                        <label class="text-muted">
                            Category
                        </label>

                        <h5>

                            <?php

                            echo $product["category_name"]
                                ? htmlspecialchars(
                                    $product["category_name"]
                                )
                                : "N/A";

                            ?>

                        </h5>

                    </div>


                    <div class="col-md-4">

                        <label class="text-muted">
                            Supplier
                        </label>

                        <h5>

                            <?php

                            echo $product["supplier_name"]
                                ? htmlspecialchars(
                                    $product["supplier_name"]
                                )
                                : "N/A";

                            ?>

                        </h5>

                    </div>


                    <div class="col-md-4">

                        <label class="text-muted">
                            Purchase Price
                        </label>

                        <h5>
                            ৳<?php echo number_format(
                                $product["purchase_price"],
                                2
                            ); ?>
                        </h5>

                    </div>


                    <div class="col-md-4">

                        <label class="text-muted">
                            Selling Price
                        </label>

                        <h5>
                            ৳<?php echo number_format(
                                $product["selling_price"],
                                2
                            ); ?>
                        </h5>

                    </div>


                    <div class="col-md-4">

                        <label class="text-muted">
                            Current Quantity
                        </label>

                        <h5>

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

                            <?php elseif ($quantity <= $minimumStock): ?>

                                <span class="badge bg-warning text-dark">

                                    <?php echo $quantity; ?>

                                    Low Stock

                                </span>

                            <?php else: ?>

                                <span class="badge bg-success">

                                    <?php echo $quantity; ?>

                                </span>

                            <?php endif; ?>

                        </h5>

                    </div>


                    <div class="col-md-4">

                        <label class="text-muted">
                            Minimum Stock Level
                        </label>

                        <h5>
                            <?php echo $product["minimum_stock"]; ?>
                        </h5>

                    </div>


                    <div class="col-md-4">

                        <label class="text-muted">
                            Status
                        </label>

                        <h5>

                            <?php if ($product["status"] === "Active"): ?>

                                <span class="badge bg-success">
                                    Active
                                </span>

                            <?php else: ?>

                                <span class="badge bg-secondary">
                                    Inactive
                                </span>

                            <?php endif; ?>

                        </h5>

                    </div>


                    <div class="col-md-4">

                        <label class="text-muted">
                            Created Date
                        </label>

                        <h5>

                            <?php

                            echo date(
                                "d M Y, h:i A",
                                strtotime(
                                    $product["created_at"]
                                )
                            );

                            ?>

                        </h5>

                    </div>


                    <div class="col-md-4">

                        <label class="text-muted">
                            Last Updated
                        </label>

                        <h5>

                            <?php

                            echo date(
                                "d M Y, h:i A",
                                strtotime(
                                    $product["updated_at"]
                                )
                            );

                            ?>

                        </h5>

                    </div>

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