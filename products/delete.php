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
        urlencode("Invalid product/part ID.")
    );

    exit();

}


/*
|--------------------------------------------------------------------------
| Verify Product / Part Exists
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "
        SELECT id
        FROM products
        WHERE id = ?
        LIMIT 1
    "
);


if (!$stmt) {

    header(
        "Location: index.php?error=" .
        urlencode("Database error.")
    );

    exit();

}


$stmt->bind_param(
    "i",
    $id
);


$stmt->execute();


$result =
    $stmt->get_result();


$productExists =
    $result->num_rows > 0;


$stmt->close();


if (!$productExists) {

    header(
        "Location: index.php?error=" .
        urlencode("Product/Part not found.")
    );

    exit();

}


/*
|--------------------------------------------------------------------------
| Check Sales References
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "
        SELECT COUNT(*) AS total
        FROM sale_items
        WHERE product_id = ?
    "
);


if (!$stmt) {

    header(
        "Location: index.php?error=" .
        urlencode("Database error.")
    );

    exit();

}


$stmt->bind_param(
    "i",
    $id
);


$stmt->execute();


$saleItemsCount =
    (int) $stmt
        ->get_result()
        ->fetch_assoc()["total"];


$stmt->close();


/*
|--------------------------------------------------------------------------
| Check Service References
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "
        SELECT COUNT(*) AS total
        FROM service_parts
        WHERE product_id = ?
    "
);


if (!$stmt) {

    header(
        "Location: index.php?error=" .
        urlencode("Database error.")
    );

    exit();

}


$stmt->bind_param(
    "i",
    $id
);


$stmt->execute();


$servicePartsCount =
    (int) $stmt
        ->get_result()
        ->fetch_assoc()["total"];


$stmt->close();


/*
|--------------------------------------------------------------------------
| Check Stock History References
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "
        SELECT COUNT(*) AS total
        FROM stock_transactions
        WHERE product_id = ?
    "
);


if (!$stmt) {

    header(
        "Location: index.php?error=" .
        urlencode("Database error.")
    );

    exit();

}


$stmt->bind_param(
    "i",
    $id
);


$stmt->execute();


$stockTransactionsCount =
    (int) $stmt
        ->get_result()
        ->fetch_assoc()["total"];


$stmt->close();


/*
|--------------------------------------------------------------------------
| Protect Historical Business Records
|--------------------------------------------------------------------------
|
| Products and parts may be referenced by:
|
| - Sales
| - Service Parts
| - Stock Transactions
|
| These are historical business records. They should not be deleted just
| to make the product deletion succeed.
|
*/

if (
    $saleItemsCount > 0 ||
    $servicePartsCount > 0 ||
    $stockTransactionsCount > 0
) {

    $message =
        "Product/Part cannot be deleted because it is already used " .
        "in sales, services, or stock history. Keep it to preserve " .
        "transaction and inventory records.";


    header(
        "Location: index.php?error=" .
        urlencode($message)
    );


    exit();

}


/*
|--------------------------------------------------------------------------
| Delete Product / Part
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "
        DELETE FROM products
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
                "Product/Part deleted successfully."
            )
        );

    } else {

        header(
            "Location: index.php?error=" .
            urlencode(
                "Product/Part was not deleted."
            )
        );

    }

} else {

    header(
        "Location: index.php?error=" .
        urlencode(
            "Unable to delete Product/Part."
        )
    );

}


$stmt->close();


$conn->close();


exit();

?>