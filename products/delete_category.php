<?php
session_start();

if (!isset($_SESSION["admin_id"])) {
    header("Location: ../login.php");
    exit();
}

require_once "../config/database.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: categories.php?error=" . urlencode("Invalid request."));
    exit();
}

$id = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
    header("Location: categories.php?error=" . urlencode("Invalid category ID."));
    exit();
}

$stmt = $conn->prepare("SELECT id FROM categories WHERE id = ? LIMIT 1");
if (!$stmt) {
    header("Location: categories.php?error=" . urlencode("Database error."));
    exit();
}
$stmt->bind_param("i", $id);
$stmt->execute();
$exists = $stmt->get_result()->num_rows > 0;
$stmt->close();

if (!$exists) {
    header("Location: categories.php?error=" . urlencode("Category not found."));
    exit();
}

// Do not break products that already use this category.
$stmt = $conn->prepare("SELECT COUNT(*) AS total FROM products WHERE category_id = ?");
if (!$stmt) {
    header("Location: categories.php?error=" . urlencode("Database error."));
    exit();
}
$stmt->bind_param("i", $id);
$stmt->execute();
$count = (int) $stmt->get_result()->fetch_assoc()["total"];
$stmt->close();

if ($count > 0) {
    header("Location: categories.php?error=" . urlencode("This category cannot be deleted because it is used by {$count} product/part(s)."));
    exit();
}

$stmt = $conn->prepare("DELETE FROM categories WHERE id = ? LIMIT 1");
if (!$stmt) {
    header("Location: categories.php?error=" . urlencode("Database error."));
    exit();
}
$stmt->bind_param("i", $id);
$ok = $stmt->execute();
$stmt->close();
$conn->close();

header("Location: categories.php?" . ($ok ? "success=" . urlencode("Category deleted successfully.") : "error=" . urlencode("Unable to delete category.")));
exit();
