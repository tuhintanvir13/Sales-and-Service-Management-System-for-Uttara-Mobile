<?php

session_start();

if (!isset($_SESSION["admin_id"])) {

    header("Location: ../login.php");
    exit();

}

require_once "../config/database.php";


if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header(
        "Location: index.php?error=" .
        urlencode("Invalid request.")
    );

    exit();

}


$id = filter_input(
    INPUT_POST,
    "id",
    FILTER_VALIDATE_INT
);


if (!$id || $id <= 0) {

    header(
        "Location: index.php?error=" .
        urlencode("Invalid customer ID.")
    );

    exit();

}


/*
|--------------------------------------------------------------------------
| Verify Customer Exists
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "SELECT id FROM customers WHERE id = ? LIMIT 1"
);

if (!$stmt) {

    header(
        "Location: index.php?error=" .
        urlencode("Database error.")
    );

    exit();

}

$stmt->bind_param("i", $id);

$stmt->execute();

$result = $stmt->get_result();

$customerExists = $result->num_rows > 0;

$stmt->close();


if (!$customerExists) {

    header(
        "Location: index.php?error=" .
        urlencode("Customer not found.")
    );

    exit();

}


/*
|--------------------------------------------------------------------------
| Check Existing Sales
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "
        SELECT COUNT(*) AS total
        FROM sales
        WHERE customer_id = ?
    "
);

if (!$stmt) {

    header(
        "Location: index.php?error=" .
        urlencode("Database error.")
    );

    exit();

}

$stmt->bind_param("i", $id);

$stmt->execute();

$salesCount = (int)
    $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();


/*
|--------------------------------------------------------------------------
| Check Existing Services
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "
        SELECT COUNT(*) AS total
        FROM services
        WHERE customer_id = ?
    "
);

if (!$stmt) {

    header(
        "Location: index.php?error=" .
        urlencode("Database error.")
    );

    exit();

}

$stmt->bind_param("i", $id);

$stmt->execute();

$servicesCount = (int)
    $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();


/*
|--------------------------------------------------------------------------
| Protect Historical Business Records
|--------------------------------------------------------------------------
|
| A customer that already has sales or service orders must not be removed,
| because those transactions depend on the customer record.
|
*/

if (
    $salesCount > 0 ||
    $servicesCount > 0
) {

    $message =
        "Customer cannot be deleted because this customer has existing " .
        "sales or service records. Keep the customer to preserve " .
        "transaction history.";

    header(
        "Location: index.php?error=" .
        urlencode($message)
    );

    exit();

}


/*
|--------------------------------------------------------------------------
| Delete Customer
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "
        DELETE FROM customers
        WHERE id = ?
        LIMIT 1
    "
);

if (!$stmt) {

    header(
        "Location: index.php?error=" .
        urlencode(
            "Unable to prepare delete operation."
        )
    );

    exit();

}


$stmt->bind_param(
    "i",
    $id
);


if ($stmt->execute()) {

    if ($stmt->affected_rows > 0) {

        header(
            "Location: index.php?success=" .
            urlencode(
                "Customer deleted successfully."
            )
        );

    } else {

        header(
            "Location: index.php?error=" .
            urlencode(
                "Customer was not deleted."
            )
        );

    }

} else {

    header(
        "Location: index.php?error=" .
        urlencode(
            "Unable to delete customer."
        )
    );

}


$stmt->close();

$conn->close();

exit();

?>