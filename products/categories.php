<?php

$basePath = "../";
$pageTitle = "Categories";

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

    $categoryName =
        trim($_POST["category_name"] ?? "");


    if ($categoryName === "") {

        $error = "Category name is required.";

    } else {

        $nextId = 1;
        $idResult = $conn->query("SELECT id FROM categories ORDER BY id ASC");
        while ($row = $idResult->fetch_assoc()) {
            if ((int)$row["id"] != $nextId) { break; }
            $nextId++;
        }

        $sql = "
            INSERT INTO categories (id, category_name)
            VALUES (?, ?)
        ";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "is",
            $nextId,
            $categoryName
        );


        if ($stmt->execute()) {

            header("Location: categories.php?success=" . urlencode("Category added successfully.")); exit();

        } else {

            $error =
                "Category already exists or could not be added.";

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
                    Categories
                </h2>

                <p class="text-muted mb-0">
                    Manage product and part categories.
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


        <div class="row g-4">

            <div class="col-lg-5">

                <div class="card shadow-sm border-0">

                    <div class="card-body">

                        <h5 class="card-title">
                            Add Category
                        </h5>

                        <form
                            method="POST"
                            action="categories.php">

                            <div class="mb-3">

                                <label class="form-label">
                                    Category Name
                                </label>

                                <input
                                    type="text"
                                    name="category_name"
                                    class="form-control"
                                    maxlength="100"
                                    required>

                            </div>

                            <button
                                type="submit"
                                class="btn btn-primary">

                                <i class="bi bi-plus-circle"></i>

                                Add Category

                            </button>

                        </form>

                    </div>

                </div>

            </div>


            <div class="col-lg-7">

                <div class="card shadow-sm border-0">

                    <div class="card-body">

                        <h5 class="card-title">
                            Category List
                        </h5>


                        <div class="table-responsive">

                            <table class="table table-hover">

                                <thead class="table-light">

                                    <tr>

                                        <th>ID</th>

                                        <th>
                                            Category Name
                                        </th>

                                        <th>
                                            Created Date
                                        </th>

                                        <th>
                                            Action
                                        </th>

                                    </tr>

                                </thead>

                                <tbody>

                                <?php

                                $result = $conn->query(
                                    "
                                    SELECT *
                                    FROM categories
                                    ORDER BY id ASC
                                    "
                                );


                                if (
                                    $result &&
                                    $result->num_rows > 0
                                ):

                                    while (
                                        $category =
                                        $result->fetch_assoc()
                                    ):

                                ?>

                                    <tr>

                                        <td>
                                            <?php
                                            echo $category["id"];
                                            ?>
                                        </td>

                                        <td>
                                            <?php
                                            echo htmlspecialchars(
                                                $category["category_name"]
                                            );
                                            ?>
                                        </td>

                                        <td>
                                            <?php
                                            echo date(
                                                "d M Y",
                                                strtotime(
                                                    $category["created_at"]
                                                )
                                            );
                                            ?>
                                        </td>

                                        <td>
                                            <form method="POST" action="delete_category.php" onsubmit="return confirm('Are you sure you want to delete this category?');" class="d-inline">
                                                <input type="hidden" name="id" value="<?php echo (int) $category["id"]; ?>">
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
                                            colspan="4"
                                            class="text-center text-muted">

                                            No categories found.

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

    </div>

</div>


<?php

$conn->close();

require_once "../includes/footer.php";

?>