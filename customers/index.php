<?php

$basePath = "../";
$pageTitle = "Customer Management";

require_once "../includes/header.php";
require_once "../config/database.php";

?>

<?php require_once "../includes/navbar.php"; ?>

<?php require_once "../includes/sidebar.php"; ?>


<div class="content">

    <div class="container-fluid">

        <?php if (isset($_GET["success"])): ?>

            <div class="alert alert-success alert-dismissible fade show" role="alert">

                <i class="bi bi-check-circle-fill me-2"></i>
                <strong>Success!</strong>
                <?php echo htmlspecialchars($_GET["success"]); ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert">
                </button>

            </div>

        <?php endif; ?>


        <script>
            setTimeout(function () {
                const alertBox = document.querySelector('.alert-success');
                if (alertBox) {
                    const closeButton = alertBox.querySelector('.btn-close');
                    if (closeButton) closeButton.click();
                }
            }, 4000);
        </script>


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
                    Customer Management
                </h2>

                <p class="text-muted mb-0">
                    Manage customer information and records.
                </p>

            </div>

            <a
                href="add.php"
                class="btn btn-primary">

                <i class="bi bi-plus-circle"></i>

                Add Customer

            </a>

        </div>


        <!-- Search -->

        <div class="card shadow-sm border-0 mb-4">

            <div class="card-body">

                <form method="GET" action="index.php">

                    <div class="row g-3">

                        <div class="col-md-9">

                            <label class="form-label">
                                Search Customer
                            </label>

                            <input
                                type="text"
                                name="search"
                                class="form-control"
                                placeholder="Search by name or mobile number"
                                value="<?php echo isset($_GET["search"]) ? htmlspecialchars($_GET["search"]) : ""; ?>">

                        </div>

                        <div class="col-md-3 d-flex align-items-end">

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


        <!-- Customer Table -->

        <div class="card shadow-sm border-0">

            <div class="card-body">

                <div class="d-flex justify-content-between align-items-center mb-3">

                    <h5 class="card-title mb-0">
                        Customer List
                    </h5>

                    <?php

                    $countSql = "SELECT COUNT(*) AS total FROM customers";

                    $countResult = $conn->query($countSql);

                    $totalCustomers = 0;

                    if ($countResult) {

                        $countData = $countResult->fetch_assoc();

                        $totalCustomers = $countData["total"];

                    }

                    ?>

                    <span class="badge bg-primary">
                        <?php echo $totalCustomers; ?> Customers
                    </span>

                </div>


                <div class="table-responsive">

                    <table class="table table-hover align-middle">

                        <thead class="table-light">

                            <tr>

                                <th>ID</th>

                                <th>Customer Name</th>

                                <th>Mobile Number</th>

                                <th>Email</th>

                                <th>Created Date</th>

                                <th class="text-center">
                                    Actions
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php

                        $search = trim($_GET["search"] ?? "");

                        if ($search !== "") {

                            $sql = "
                                SELECT id, customer_name, mobile, email, created_at
                                FROM customers
                                WHERE customer_name LIKE ?
                                OR mobile LIKE ?
                                ORDER BY id DESC
                            ";

                            $stmt = $conn->prepare($sql);

                            $searchValue = "%" . $search . "%";

                            $stmt->bind_param(
                                "ss",
                                $searchValue,
                                $searchValue
                            );

                            $stmt->execute();

                            $result = $stmt->get_result();

                        } else {

                            $sql = "
                                SELECT id, customer_name, mobile, email, created_at
                                FROM customers
                                ORDER BY id DESC
                            ";

                            $result = $conn->query($sql);

                        }


                        if ($result && $result->num_rows > 0):

                            while ($customer = $result->fetch_assoc()):

                        ?>

                            <tr>

                                <td>
                                    <?php echo $customer["id"]; ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($customer["customer_name"]); ?>
                                </td>

                                <td>
                                    <?php echo htmlspecialchars($customer["mobile"]); ?>
                                </td>

                                <td>

                                    <?php

                                    echo !empty($customer["email"])
                                        ? htmlspecialchars($customer["email"])
                                        : "N/A";

                                    ?>

                                </td>

                                <td>
                                    <?php
                                    echo date(
                                        "d M Y",
                                        strtotime($customer["created_at"])
                                    );
                                    ?>
                                </td>

                                <td class="text-center">

                                    <a
                                        href="view.php?id=<?php echo $customer["id"]; ?>"
                                        class="btn btn-sm btn-info text-white"
                                        title="View">

                                        <i class="bi bi-eye"></i>

                                    </a>


                                    <a
                                        href="edit.php?id=<?php echo $customer["id"]; ?>"
                                        class="btn btn-sm btn-warning"
                                        title="Edit">

                                        <i class="bi bi-pencil"></i>

                                    </a>


                                    <form
                                        action="delete.php"
                                        method="POST"
                                        class="d-inline"
                                        onsubmit="return confirm('Are you sure you want to delete this customer?');">

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?php echo $customer["id"]; ?>">

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
                                    colspan="6"
                                    class="text-center text-muted py-4">

                                    No customers found.

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

if (isset($stmt)) {
    $stmt->close();
}

$conn->close();

require_once "../includes/footer.php";

?>