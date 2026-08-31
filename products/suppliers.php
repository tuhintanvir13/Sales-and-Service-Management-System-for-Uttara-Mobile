<?php

$basePath = "../";
$pageTitle = "Suppliers";

require_once "../includes/header.php";
require_once "../config/database.php";

require_once "../includes/navbar.php";
require_once "../includes/sidebar.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$error = $_GET["error"] ?? "";
$success = $_GET["success"] ?? "";


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $supplierName =
        trim($_POST["supplier_name"] ?? "");

    $mobile =
        trim($_POST["mobile"] ?? "");

    $address =
        trim($_POST["address"] ?? "");

    $email =
        trim($_POST["email"] ?? "");


    if ($supplierName === "") {

        $error =
            "Supplier name is required.";

    } elseif (
        $email !== "" &&
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error =
            "Please enter a valid email.";

    } else {

        $nextId = 1;
        $idResult = $conn->query("SELECT id FROM suppliers ORDER BY id ASC");
        while ($row = $idResult->fetch_assoc()) {
            if ((int)$row["id"] != $nextId) { break; }
            $nextId++;
        }

        $sql = "
            INSERT INTO suppliers
            (
                id,
                supplier_name,
                mobile,
                address,
                email
            )
            VALUES (?, ?, ?, ?, ?)
        ";


        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "issss",
            $nextId,
            $supplierName,
            $mobile,
            $address,
            $email
        );


        if ($stmt->execute()) {

            header("Location: suppliers.php?success=" . urlencode("Supplier added successfully.")); exit();

        } else {

            $error =
                "Unable to add supplier.";

        }


        $stmt->close();

    }

}

?>

<div class="content">

    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h2 class="fw-bold">
                    Suppliers
                </h2>

                <p class="text-muted mb-0">
                    Manage product and part suppliers.
                </p>

            </div>

            <a
                href="index.php"
                class="btn btn-secondary">

                <i class="bi bi-arrow-left"></i>

                Back to Products

            </a>

        </div>


        <?php if ($success !== ""): ?>

            <div class="alert alert-success">

                <?php echo htmlspecialchars($success); ?>

            </div>

        <?php endif; ?>


        <?php if ($error !== ""): ?>

            <div class="alert alert-danger">

                <?php echo htmlspecialchars($error); ?>

            </div>

        <?php endif; ?>


        <div class="card shadow-sm border-0 mb-4">

            <div class="card-body">

                <h5 class="card-title">
                    Add Supplier
                </h5>


                <form
                    method="POST"
                    action="suppliers.php">

                    <div class="row g-3">

                        <div class="col-md-6">

                            <label class="form-label">
                                Supplier Name
                            </label>

                            <input
                                type="text"
                                name="supplier_name"
                                class="form-control"
                                maxlength="150"
                                required>

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Mobile
                            </label>

                            <input
                                type="text"
                                name="mobile"
                                class="form-control"
                                maxlength="20">

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Email
                            </label>

                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                maxlength="100">

                        </div>


                        <div class="col-md-6">

                            <label class="form-label">
                                Address
                            </label>

                            <input
                                type="text"
                                name="address"
                                class="form-control"
                                maxlength="255">

                        </div>


                        <div class="col-12">

                            <button
                                type="submit"
                                class="btn btn-primary">

                                <i class="bi bi-plus-circle"></i>

                                Add Supplier

                            </button>

                        </div>

                    </div>

                </form>

            </div>

        </div>


        <div class="card shadow-sm border-0">

            <div class="card-body">

                <h5 class="card-title">
                    Supplier List
                </h5>


                <div class="table-responsive">

                    <table class="table table-hover">

                        <thead class="table-light">

                            <tr>

                                <th>ID</th>

                                <th>Name</th>

                                <th>Mobile</th>

                                <th>Email</th>

                                <th>Address</th>

                                <th>Created Date</th>

                                <th>Action</th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php

                        $result = $conn->query(
                            "
                            SELECT *
                            FROM suppliers
                            ORDER BY id ASC
                            "
                        );


                        if (
                            $result &&
                            $result->num_rows > 0
                        ):

                            while (
                                $supplier =
                                $result->fetch_assoc()
                            ):

                        ?>

                            <tr>

                                <td>
                                    <?php
                                    echo $supplier["id"];
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $supplier["supplier_name"]
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $supplier["mobile"] ?: "N/A"
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $supplier["email"] ?: "N/A"
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo htmlspecialchars(
                                        $supplier["address"] ?: "N/A"
                                    );
                                    ?>
                                </td>

                                <td>
                                    <?php
                                    echo date(
                                        "d M Y",
                                        strtotime(
                                            $supplier["created_at"]
                                        )
                                    );
                                    ?>
                                </td>

                                <td>
                                    <form method="POST" action="delete_supplier.php" onsubmit="return confirm('Are you sure you want to delete this supplier?');" class="d-inline">
                                        <input type="hidden" name="id" value="<?php echo (int) $supplier["id"]; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="bi bi-trash"></i> Delete
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
                                    colspan="7"
                                    class="text-center text-muted">

                                    No suppliers found.

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

$conn->close();

require_once "../includes/footer.php";

?>