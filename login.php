<?php

session_start();

if (isset($_SESSION['admin_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

if (isset($_GET['error'])) {
    $error = $_GET['error'];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Login - Uttara Mobile</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="login-body">

    <div class="container">

        <div class="row justify-content-center align-items-center min-vh-100">

            <div class="col-md-5 col-lg-4">

                <div class="card login-card shadow">

                    <div class="card-body p-4">

                        <div class="text-center mb-4">

                            <h2 class="fw-bold">
                                Uttara Mobile
                            </h2>

                            <p class="text-muted">
                                Sales & Service Management System
                            </p>

                        </div>

                        <?php if (!empty($error)): ?>

                            <div class="alert alert-danger">
                                <?php echo htmlspecialchars($error); ?>
                            </div>

                        <?php endif; ?>

                        <form action="authenticate.php" method="POST">

                            <div class="mb-3">

                                <label for="username" class="form-label">
                                    Username
                                </label>

                                <input
                                    type="text"
                                    name="username"
                                    id="username"
                                    class="form-control"
                                    placeholder="Enter username"
                                    required>

                            </div>

                            <div class="mb-3">

                                <label for="password" class="form-label">
                                    Password
                                </label>

                                <input
                                    type="password"
                                    name="password"
                                    id="password"
                                    class="form-control"
                                    placeholder="Enter password"
                                    required>

                            </div>

                            <button
                                type="submit"
                                class="btn btn-primary w-100">

                                Login

                            </button>

                        </form>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <script src="assets/js/script.js"></script>

</body>

</html>