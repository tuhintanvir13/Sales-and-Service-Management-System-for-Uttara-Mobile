<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION["admin_id"])) {
    header("Location: ../login.php");
    exit();
}

require_once __DIR__ . '/../config/database.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: index.php");
    exit();
}

$serviceId = filter_input(INPUT_POST, "id", FILTER_VALIDATE_INT);

if (!$serviceId || $serviceId <= 0) {
    header("Location: index.php?error=" . urlencode("Invalid service ID."));
    exit();
}

try {
    $conn->begin_transaction();

    // Remove related parts first to respect the service_parts foreign key.
    $partsStmt = $conn->prepare("DELETE FROM service_parts WHERE service_id = ?");
    if (!$partsStmt) {
        throw new Exception("Unable to prepare service parts deletion.");
    }
    $partsStmt->bind_param("i", $serviceId);
    $partsStmt->execute();
    $partsStmt->close();

    $serviceStmt = $conn->prepare("DELETE FROM services WHERE id = ? LIMIT 1");
    if (!$serviceStmt) {
        throw new Exception("Unable to prepare service deletion.");
    }
    $serviceStmt->bind_param("i", $serviceId);
    $serviceStmt->execute();

    if ($serviceStmt->affected_rows !== 1) {
        $serviceStmt->close();
        throw new Exception("Service order not found.");
    }

    $serviceStmt->close();
    $conn->commit();
    header("Location: index.php?success=" . urlencode("Service order deleted successfully."));
    exit();

} catch (Throwable $e) {
    $conn->rollback();
    header("Location: index.php?error=" . urlencode("Unable to delete service order."));
    exit();
}
