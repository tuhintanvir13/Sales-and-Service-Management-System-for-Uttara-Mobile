<?php

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Validate Invoice ID
|--------------------------------------------------------------------------
*/

$invoiceId = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

if ($invoiceId <= 0) {
    header("Location: index.php?error=invalid_invoice");
    exit();
}


/*
|--------------------------------------------------------------------------
| Check Invoice
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT
        id,
        invoice_number,
        sale_id,
        service_id
    FROM invoices
    WHERE id = ?
    LIMIT 1
");

if (!$stmt) {
    header("Location: index.php?error=database");
    exit();
}

$stmt->bind_param("i", $invoiceId);
$stmt->execute();

$result = $stmt->get_result();
$invoice = $result->fetch_assoc();

$stmt->close();


if (!$invoice) {
    header("Location: index.php?error=not_found");
    exit();
}


/*
|--------------------------------------------------------------------------
| Delete Invoice Only
|--------------------------------------------------------------------------
|
| IMPORTANT:
| This deletes only the invoice record.
|
| It does NOT delete:
| - Sale
| - Service
| - Customer
| - Products
| - Payments
|
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();

try {

    $stmt = $conn->prepare("
        DELETE FROM invoices
        WHERE id = ?
        LIMIT 1
    ");

    if (!$stmt) {
        throw new Exception(
            "Unable to prepare delete query."
        );
    }

    $stmt->bind_param(
        "i",
        $invoiceId
    );

    if (!$stmt->execute()) {
        throw new Exception(
            "Unable to delete invoice."
        );
    }

    $stmt->close();

    $conn->commit();

    header(
        "Location: index.php?deleted=1"
    );

    exit();

} catch (Throwable $e) {

    $conn->rollback();

    header(
        "Location: index.php?error=delete_failed"
    );

    exit();
}