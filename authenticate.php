<?php

session_start();

require_once "config/database.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit();
}

$username = trim($_POST["username"] ?? "");
$password = $_POST["password"] ?? "";

if (empty($username) || empty($password)) {
    header("Location: login.php?error=Please enter username and password.");
    exit();
}

$sql = "SELECT id, username, password FROM users WHERE username = ? LIMIT 1";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database query failed.");
}

$stmt->bind_param("s", $username);

$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 1) {

    $user = $result->fetch_assoc();

    if (password_verify($password, $user["password"])) {

        session_regenerate_id(true);

        $_SESSION["admin_id"] = $user["id"];
        $_SESSION["admin_username"] = $user["username"];

        header("Location: dashboard.php");
        exit();

    } else {

        header("Location: login.php?error=Invalid username or password.");
        exit();

    }

} else {

    header("Location: login.php?error=Invalid username or password.");
    exit();

}

$stmt->close();
$conn->close();

?>