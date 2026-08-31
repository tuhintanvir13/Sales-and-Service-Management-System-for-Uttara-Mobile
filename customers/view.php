<?php

$basePath = "../";
$pageTitle = "View Customer";

require_once "../includes/header.php";
require_once "../config/database.php";

?>

<?php require_once "../includes/navbar.php"; ?>

<?php require_once "../includes/sidebar.php"; ?>


<div class="content">

    <div class="container-fluid">

        <?php

        $id = filter_input(
            INPUT_GET,
            "id",
            FILTER_VALIDATE_INT
        );

        if (!$id) {

            header("Location: index.php?error=Invalid customer ID.");
            exit();

        }


        $sql = "
            SELECT
                id,
                customer_name,
                mobile,
                address,
                email,
                created_at,
                updated_at
            FROM customers
            WHERE id = ?
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param("i", $id);

        $stmt->execute();

        $result = $stmt->get_result();

        $customer = $result->fetch_assoc();


        if (!$customer) {

            $stmt->close();
            $conn->close();

            header(
                "Location: index.php?error=Customer not found."
            );

            exit();

        }

        ?>


        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h2 class="fw-bold">
                    Customer Details
                </h2>

                <p class="text-muted mb-0">
                    View customer information.
                </p>

            </div>

            <div>

                <a
                    href="edit.php?id=<?php echo $customer["id"]; ?>"
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
                            Customer ID
                        </label>

                        <h5>
                            #<?php echo $customer["id"]; ?>
                        </h5>

                    </div>


                    <div class="col-md-6">

                        <label class="text-muted">
                            Customer Name
                        </label>

                        <h5>
                            <?php
                            echo htmlspecialchars(
                                $customer["customer_name"]
                            );
                            ?>
                        </h5>

                    </div>


                    <div class="col-md-6">

                        <label class="text-muted">
                            Mobile Number
                        </label>

                        <h5>
                            <?php
                            echo htmlspecialchars(
                                $customer["mobile"]
                            );
                            ?>
                        </h5>

                    </div>


                    <div class="col-md-6">

                        <label class="text-muted">
                            Email
                        </label>

                        <h5>

                            <?php

                            echo !empty($customer["email"])
                                ? htmlspecialchars($customer["email"])
                                : "N/A";

                            ?>

                        </h5>

                    </div>


                    <div class="col-12">

                        <label class="text-muted">
                            Address
                        </label>

                        <h5>

                            <?php

                            echo !empty($customer["address"])
                                ? htmlspecialchars($customer["address"])
                                : "N/A";

                            ?>

                        </h5>

                    </div>


                    <div class="col-md-6">

                        <label class="text-muted">
                            Created Date
                        </label>

                        <h5>

                            <?php

                            echo date(
                                "d M Y, h:i A",
                                strtotime($customer["created_at"])
                            );

                            ?>

                        </h5>

                    </div>


                    <div class="col-md-6">

                        <label class="text-muted">
                            Last Updated
                        </label>

                        <h5>

                            <?php

                            echo date(
                                "d M Y, h:i A",
                                strtotime($customer["updated_at"])
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