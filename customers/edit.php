<?php

$basePath = "../";
$pageTitle = "Edit Customer";

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

            header(
                "Location: index.php?error=Invalid customer ID."
            );

            exit();

        }


        $sql = "
            SELECT
                id,
                customer_name,
                mobile,
                address,
                email
            FROM customers
            WHERE id = ?
            LIMIT 1
        ";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param("i", $id);

        $stmt->execute();

        $result = $stmt->get_result();

        $customer = $result->fetch_assoc();

        $stmt->close();


        if (!$customer) {

            header(
                "Location: index.php?error=Customer not found."
            );

            exit();

        }


        if ($_SERVER["REQUEST_METHOD"] === "POST") {

            $customerName = trim(
                $_POST["customer_name"] ?? ""
            );

            $mobile = trim(
                $_POST["mobile"] ?? ""
            );

            $address = trim(
                $_POST["address"] ?? ""
            );

            $email = trim(
                $_POST["email"] ?? ""
            );


            if ($customerName === "" || $mobile === "") {

                echo "<script>
                        alert('Customer name and mobile number are required.');
                      </script>";

            } elseif (
                $email !== "" &&
                !filter_var(
                    $email,
                    FILTER_VALIDATE_EMAIL
                )
            ) {

                echo "<script>
                        alert('Please enter a valid email address.');
                      </script>";

            } else {

                $updateSql = "
                    UPDATE customers
                    SET
                        customer_name = ?,
                        mobile = ?,
                        address = ?,
                        email = ?
                    WHERE id = ?
                ";

                $updateStmt = $conn->prepare(
                    $updateSql
                );


                $updateStmt->bind_param(
                    "ssssi",
                    $customerName,
                    $mobile,
                    $address,
                    $email,
                    $id
                );


                if ($updateStmt->execute()) {

                    $updateStmt->close();
                    $conn->close();

                    header(
                        "Location: index.php?success=" .
                        urlencode(
                            "Customer updated successfully."
                        )
                    );

                    exit();

                } else {

                    echo "<script>
                            alert('Failed to update customer.');
                          </script>";

                }


                $updateStmt->close();

            }


            $customer["customer_name"] = $customerName;
            $customer["mobile"] = $mobile;
            $customer["address"] = $address;
            $customer["email"] = $email;

        }

        ?>


        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h2 class="fw-bold">
                    Edit Customer
                </h2>

                <p class="text-muted mb-0">
                    Update customer information.
                </p>

            </div>

            <a
                href="index.php"
                class="btn btn-secondary">

                <i class="bi bi-arrow-left"></i>

                Back to Customers

            </a>

        </div>


        <div class="card shadow-sm border-0">

            <div class="card-body">

                <form
                    method="POST"
                    action="edit.php?id=<?php echo $id; ?>">

                    <div class="row g-3">

                        <div class="col-md-6">

                            <label class="form-label">
                                Customer Name
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                name="customer_name"
                                class="form-control"
                                maxlength="100"
                                required
                                value="<?php echo htmlspecialchars($customer["customer_name"]); ?>">

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Mobile Number
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="text"
                                name="mobile"
                                class="form-control"
                                maxlength="20"
                                required
                                value="<?php echo htmlspecialchars($customer["mobile"]); ?>">

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Email
                            </label>

                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                maxlength="100"
                                value="<?php echo htmlspecialchars($customer["email"] ?? ""); ?>">

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Address
                            </label>

                            <input
                                type="text"
                                name="address"
                                class="form-control"
                                maxlength="255"
                                value="<?php echo htmlspecialchars($customer["address"] ?? ""); ?>">

                        </div>


                        <div class="col-12 mt-4">

                            <button
                                type="submit"
                                class="btn btn-primary">

                                <i class="bi bi-save"></i>

                                Update Customer

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