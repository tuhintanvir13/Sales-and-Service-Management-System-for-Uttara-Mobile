<?php

$basePath = "../";
$pageTitle = "Add Customer";

require_once "../config/database.php";

// Process the form before any HTML output so the success redirect works reliably.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $customerName = trim($_POST["customer_name"] ?? "");
    $mobile = trim($_POST["mobile"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $email = trim($_POST["email"] ?? "");

    if ($customerName === "" || $mobile === "") {
        header("Location: add.php?error=" . urlencode("Customer name and mobile number are required."));
        exit();
    }

    if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: add.php?error=" . urlencode("Please enter a valid email address."));
        exit();
    }

    // Reuse the lowest available positive Customer ID.
    $nextId = 1;
    $idResult = $conn->query("SELECT id FROM customers ORDER BY id ASC");

    if ($idResult) {
        while ($idRow = $idResult->fetch_assoc()) {
            $currentId = (int) $idRow["id"];

            if ($currentId === $nextId) {
                $nextId++;
            } elseif ($currentId > $nextId) {
                break;
            }
        }
    }

    $sql = "
        INSERT INTO customers
        (id, customer_name, mobile, address, email)
        VALUES (?, ?, ?, ?, ?)
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        header("Location: add.php?error=" . urlencode("Unable to prepare customer record."));
        exit();
    }

    $stmt->bind_param("issss", $nextId, $customerName, $mobile, $address, $email);

    if ($stmt->execute()) {
        $stmt->close();
        $conn->close();

        header("Location: index.php?success=" . urlencode("Successfully added customer!"));
        exit();
    }

    $stmt->close();
    $conn->close();
    header("Location: add.php?error=" . urlencode("Failed to add customer. Please try again."));
    exit();
}

require_once "../includes/header.php";
require_once "../includes/navbar.php";
require_once "../includes/sidebar.php";

?>

<div class="content">
    <div class="container-fluid">

        <?php if (isset($_GET["error"])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php echo htmlspecialchars($_GET["error"]); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold">Add Customer</h2>
                <p class="text-muted mb-0">Add a new customer to the system.</p>
            </div>

            <a href="index.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i>
                Back to Customers
            </a>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <form action="add.php" method="POST">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label for="customer_name" class="form-label">
                                Customer Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="customer_name" id="customer_name" class="form-control" maxlength="100" required>
                        </div>

                        <div class="col-md-6">
                            <label for="mobile" class="form-label">
                                Mobile Number <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="mobile" id="mobile" class="form-control" maxlength="20" required>
                        </div>

                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" name="email" id="email" class="form-control" maxlength="100">
                        </div>

                        <div class="col-md-6">
                            <label for="address" class="form-label">Address</label>
                            <input type="text" name="address" id="address" class="form-control" maxlength="255">
                        </div>

                        <div class="col-12 mt-4">
                            <button type="submit" name="save_customer" class="btn btn-primary">
                                <i class="bi bi-save"></i>
                                Save Customer
                            </button>

                            <a href="index.php" class="btn btn-secondary">Cancel</a>
                        </div>

                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
