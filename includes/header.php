<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["admin_id"])) {

    $loginPath = isset($basePath) ? $basePath . "login.php" : "login.php";

    header("Location: " . $loginPath);
    exit();
}

$basePath = $basePath ?? "";

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        <?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) : "Uttara Mobile Management System"; ?>
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css"
        rel="stylesheet">

    <link
        rel="stylesheet"
        href="<?php echo $basePath; ?>assets/css/style.css">

</head>

<body>

<div class="main-wrapper">