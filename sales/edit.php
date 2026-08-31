<?php

require_once __DIR__ . '/../config/database.php';

$basePath = "../";
$pageTitle = "Edit Sale";

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';


$error = "";

$saleId = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);


/*
|--------------------------------------------------------------------------
| Validate Sale ID
|--------------------------------------------------------------------------
*/

if (!$saleId) {

    header("Location: index.php");
    exit();

}


/*
|--------------------------------------------------------------------------
| Load Customers
|--------------------------------------------------------------------------
*/

$customers = [];

$customerResult = $conn->query("
    SELECT
        id,
        customer_name,
        mobile
    FROM customers
    ORDER BY customer_name ASC
");

if ($customerResult) {

    while ($customer = $customerResult->fetch_assoc()) {
        $customers[] = $customer;
    }
}


/*
|--------------------------------------------------------------------------
| Load Products / Parts
|--------------------------------------------------------------------------
*/

$products = [];

$productResult = $conn->query("
    SELECT
        id,
        product_name,
        item_type,
        selling_price,
        quantity,
        status
    FROM products
    ORDER BY product_name ASC
");

if ($productResult) {

    while ($product = $productResult->fetch_assoc()) {
        $products[] = $product;
    }
}


/*
|--------------------------------------------------------------------------
| Load Services
|--------------------------------------------------------------------------
*/

$services = [];
$serviceResult = $conn->query("
    SELECT id, customer_id, device_brand, device_model, service_charge, service_status
    FROM services
    ORDER BY id ASC
");
if ($serviceResult) {
    $serviceOrder = 1;
    while ($service = $serviceResult->fetch_assoc()) {
        $service['service_order'] = $serviceOrder++;
        $services[] = $service;
    }
}


/*
|--------------------------------------------------------------------------
| Load Existing Sale
|--------------------------------------------------------------------------
*/

$saleStmt = $conn->prepare("
    SELECT
        id,
        customer_id,
        invoice_number,
        subtotal,
        discount,
        grand_total,
        paid_amount,
        due_amount,
        payment_status,
        sale_date,
        created_by

    FROM sales

    WHERE id = ?

    LIMIT 1
");

if (!$saleStmt) {

    die("Database error while loading sale.");

}

$saleStmt->bind_param(
    "i",
    $saleId
);

$saleStmt->execute();

$saleResult =
    $saleStmt->get_result();

$sale =
    $saleResult->fetch_assoc();

$saleStmt->close();


if (!$sale) {

    header("Location: index.php");
    exit();

}


$formServiceId = null;
$serviceLinkStmt = $conn->prepare("
    SELECT service_id FROM invoices
    WHERE sale_id = ? AND service_id IS NOT NULL
    ORDER BY id DESC LIMIT 1
");
if ($serviceLinkStmt) {
    $serviceLinkStmt->bind_param("i", $saleId);
    $serviceLinkStmt->execute();
    $linkedService = $serviceLinkStmt->get_result()->fetch_assoc();
    $serviceLinkStmt->close();
    if ($linkedService) $formServiceId = (int)$linkedService['service_id'];
}


/*
|--------------------------------------------------------------------------
| Load Existing Sale Items
|--------------------------------------------------------------------------
*/

$itemStmt = $conn->prepare("
    SELECT
        si.id,
        si.sale_id,
        si.product_id,
        si.quantity,
        si.unit_price,
        si.total_price,

        p.product_name,
        p.item_type,
        p.quantity AS current_stock,
        p.status

    FROM sale_items si

    LEFT JOIN products p
        ON si.product_id = p.id

    WHERE si.sale_id = ?

    ORDER BY si.id ASC
");

if (!$itemStmt) {

    die("Database error while loading sale items.");

}

$itemStmt->bind_param(
    "i",
    $saleId
);

$itemStmt->execute();

$itemResult =
    $itemStmt->get_result();

$existingItems = [];

while ($item = $itemResult->fetch_assoc()) {

    $existingItems[] = $item;

}

$itemStmt->close();


/*
|--------------------------------------------------------------------------
| Process Edit
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $customerId =
        filter_input(
            INPUT_POST,
            "customer_id",
            FILTER_VALIDATE_INT
        );


    if ($customerId === false) {
        $customerId = null;
    }


    $saleDate =
        trim(
            $_POST["sale_date"] ?? ""
        );

    $serviceId = filter_input(INPUT_POST, "service_id", FILTER_VALIDATE_INT);
    if ($serviceId === false) $serviceId = null;
    $formServiceId = $serviceId;


    $productIds =
        $_POST["product_id"] ?? [];


    $quantities =
        $_POST["quantity"] ?? [];


    $unitPrices =
        $_POST["unit_price"] ?? [];


    $discount =
        isset($_POST["discount"])
            ? (float)$_POST["discount"]
            : 0;


    $paidAmount =
        isset($_POST["paid_amount"])
            ? (float)$_POST["paid_amount"]
            : 0;


    /*
    |--------------------------------------------------------------------------
    | Basic Validation
    |--------------------------------------------------------------------------
    */

    if ($saleDate === "") {

        $error =
            "Sale date is required.";

    } elseif (
        empty($productIds) ||
        empty($quantities) ||
        empty($unitPrices)
    ) {

        $error =
            "Please add at least one product or part.";

    } elseif ($discount < 0) {

        $error =
            "Discount cannot be negative.";

    } elseif ($paidAmount < 0) {

        $error =
            "Paid amount cannot be negative.";

    }


    /*
    |--------------------------------------------------------------------------
    | Prepare New Sale Items
    |--------------------------------------------------------------------------
    */

    $newItems = [];

    if ($error === "") {

        foreach ($productIds as $index => $productId) {

            $productId =
                (int)$productId;


            $quantity =
                isset($quantities[$index])
                    ? (int)$quantities[$index]
                    : 0;


            $unitPrice =
                isset($unitPrices[$index])
                    ? (float)$unitPrices[$index]
                    : 0;


            if ($productId <= 0) {
                continue;
            }


            if ($quantity < 1) {

                $error =
                    "Every quantity must be at least 1.";

                break;

            }


            if ($unitPrice < 0) {

                $error =
                    "Unit price cannot be negative.";

                break;

            }


            /*
             * Combine duplicate products.
             */

            if (isset($newItems[$productId])) {

                /*
                 * Use weighted total if the same product
                 * was accidentally added twice.
                 */

                $oldQty =
                    $newItems[$productId]["quantity"];

                $oldPrice =
                    $newItems[$productId]["unit_price"];


                $newQty =
                    $oldQty + $quantity;


                $newItems[$productId]["quantity"] =
                    $newQty;


                /*
                 * Keep the latest entered unit price.
                 */

                $newItems[$productId]["unit_price"] =
                    $unitPrice;

            } else {

                $newItems[$productId] = [

                    "product_id" =>
                        $productId,

                    "quantity" =>
                        $quantity,

                    "unit_price" =>
                        round(
                            $unitPrice,
                            2
                        )

                ];

            }

        }


        if (
            $error === "" &&
            empty($newItems)
        ) {

            $error =
                "Please add at least one valid product or part.";

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Update Sale
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $conn->begin_transaction();


        try {

            /*
            |--------------------------------------------------------------------------
            | Lock Sale
            |--------------------------------------------------------------------------
            */

            $lockSaleStmt =
                $conn->prepare("
                    SELECT
                        id,
                        customer_id,
                        invoice_number,
                        subtotal,
                        discount,
                        grand_total,
                        paid_amount,
                        due_amount,
                        payment_status,
                        sale_date,
                        created_by

                    FROM sales

                    WHERE id = ?

                    LIMIT 1

                    FOR UPDATE
                ");


            if (!$lockSaleStmt) {

                throw new Exception(
                    "Unable to lock sale."
                );

            }


            $lockSaleStmt->bind_param(
                "i",
                $saleId
            );


            $lockSaleStmt->execute();


            $lockedSale =
                $lockSaleStmt
                    ->get_result()
                    ->fetch_assoc();


            $lockSaleStmt->close();


            if (!$lockedSale) {

                throw new Exception(
                    "Sale record was not found."
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Validate Customer
            |--------------------------------------------------------------------------
            */

            if ($customerId !== null) {

                $customerStmt =
                    $conn->prepare("
                        SELECT id
                        FROM customers
                        WHERE id = ?
                        LIMIT 1
                    ");


                if (!$customerStmt) {

                    throw new Exception(
                        "Unable to verify customer."
                    );

                }


                $customerStmt->bind_param(
                    "i",
                    $customerId
                );


                $customerStmt->execute();


                $customerExists =
                    $customerStmt
                        ->get_result()
                        ->fetch_assoc();


                $customerStmt->close();


                if (!$customerExists) {

                    throw new Exception(
                        "Selected customer was not found."
                    );

                }

            }


            /*
            |--------------------------------------------------------------------------
            | Verify Selected Service Order
            |--------------------------------------------------------------------------
            */

            $serviceCharge = 0.00;
            if ($serviceId !== null) {
                if ($customerId === null) throw new Exception("Please select a customer before selecting a service order.");
                $serviceStmt = $conn->prepare("SELECT id, customer_id, service_charge FROM services WHERE id = ? LIMIT 1 FOR UPDATE");
                if (!$serviceStmt) throw new Exception("Unable to verify service order.");
                $serviceStmt->bind_param("i", $serviceId);
                $serviceStmt->execute();
                $selectedService = $serviceStmt->get_result()->fetch_assoc();
                $serviceStmt->close();
                if (!$selectedService) throw new Exception("Selected service order was not found.");
                if ((int)$selectedService['customer_id'] !== (int)$customerId) throw new Exception("Selected service order does not belong to the selected customer.");
                $serviceCharge = round((float)$selectedService['service_charge'], 2);
            }


            /*
            |--------------------------------------------------------------------------
            | Get Old Sale Items
            |--------------------------------------------------------------------------
            */

            $oldItemsStmt =
                $conn->prepare("
                    SELECT
                        product_id,
                        quantity

                    FROM sale_items

                    WHERE sale_id = ?

                    FOR UPDATE
                ");


            if (!$oldItemsStmt) {

                throw new Exception(
                    "Unable to load previous sale items."
                );

            }


            $oldItemsStmt->bind_param(
                "i",
                $saleId
            );


            $oldItemsStmt->execute();


            $oldResult =
                $oldItemsStmt
                    ->get_result();


            $oldItems = [];


            while ($oldItem =
                $oldResult->fetch_assoc()) {

                $productId =
                    (int)$oldItem["product_id"];


                if (isset($oldItems[$productId])) {

                    $oldItems[$productId] +=
                        (int)$oldItem["quantity"];

                } else {

                    $oldItems[$productId] =
                        (int)$oldItem["quantity"];

                }

            }


            $oldItemsStmt->close();


            /*
            |--------------------------------------------------------------------------
            | Build Product ID List
            |--------------------------------------------------------------------------
            */

            $allProductIds =
                array_unique(
                    array_merge(
                        array_keys($oldItems),
                        array_keys($newItems)
                    )
                );


            /*
            |--------------------------------------------------------------------------
            | Load and Lock Products
            |--------------------------------------------------------------------------
            */

            $lockedProducts = [];


            foreach ($allProductIds as $productId) {

                $productStmt =
                    $conn->prepare("
                        SELECT
                            id,
                            product_name,
                            quantity,
                            selling_price,
                            status

                        FROM products

                        WHERE id = ?

                        LIMIT 1

                        FOR UPDATE
                    ");


                if (!$productStmt) {

                    throw new Exception(
                        "Unable to lock product."
                    );

                }


                $productStmt->bind_param(
                    "i",
                    $productId
                );


                $productStmt->execute();


                $product =
                    $productStmt
                        ->get_result()
                        ->fetch_assoc();


                $productStmt->close();


                if (!$product) {

                    throw new Exception(
                        "A product/part from this sale no longer exists."
                    );

                }


                $lockedProducts[$productId] =
                    $product;

            }


            /*
            |--------------------------------------------------------------------------
            | Calculate New Subtotal
            |--------------------------------------------------------------------------
            */

            $subtotal = 0;


            foreach ($newItems as $productId => &$item) {

                $product =
                    $lockedProducts[$productId];


                /*
                 * New product must be active.
                 *
                 * Existing products can remain in an
                 * old sale even if their current status
                 * is inactive.
                 */

                $wasPreviouslySold =
                    isset($oldItems[$productId]);


                if (
                    !$wasPreviouslySold &&
                    $product["status"] !== "Active"
                ) {

                    throw new Exception(
                        "Product/Part \"" .
                        $product["product_name"] .
                        "\" is inactive."
                    );

                }


                $item["total_price"] =
                    round(
                        $item["unit_price"] *
                        $item["quantity"],
                        2
                    );


                $subtotal =
                    round(
                        $subtotal +
                        $item["total_price"],
                        2
                    );

            }

            unset($item);

            $subtotal = round($subtotal + $serviceCharge, 2);


            /*
            |--------------------------------------------------------------------------
            | Discount
            |--------------------------------------------------------------------------
            */

            $discount =
                round(
                    $discount,
                    2
                );


            if ($discount > $subtotal) {

                throw new Exception(
                    "Discount cannot be greater than subtotal."
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Grand Total
            |--------------------------------------------------------------------------
            */

            $grandTotal =
                round(
                    $subtotal - $discount,
                    2
                );


            /*
            |--------------------------------------------------------------------------
            | Payment
            |--------------------------------------------------------------------------
            */

            $paidAmount =
                round(
                    $paidAmount,
                    2
                );


            if ($paidAmount > $grandTotal) {

                throw new Exception(
                    "Paid amount cannot be greater than grand total."
                );

            }


            $dueAmount =
                round(
                    $grandTotal -
                    $paidAmount,
                    2
                );


            /*
            |--------------------------------------------------------------------------
            | Payment Status
            |--------------------------------------------------------------------------
            */

            if ($dueAmount <= 0) {

                $paymentStatus =
                    "Paid";

                $paidAmount =
                    $grandTotal;

                $dueAmount =
                    0;

            } elseif ($paidAmount > 0) {

                $paymentStatus =
                    "Partial";

            } else {

                $paymentStatus =
                    "Due";

            }


            /*
            |--------------------------------------------------------------------------
            | Calculate Stock Changes
            |--------------------------------------------------------------------------
            |
            | Positive difference:
            | old sale quantity > new sale quantity
            | → return stock
            |
            | Negative difference:
            | new sale quantity > old quantity
            | → deduct additional stock
            |
            */

            foreach ($allProductIds as $productId) {

                $oldQuantity =
                    isset($oldItems[$productId])
                        ? $oldItems[$productId]
                        : 0;


                $newQuantity =
                    isset($newItems[$productId])
                        ? $newItems[$productId]["quantity"]
                        : 0;


                $stockDifference =
                    $newQuantity -
                    $oldQuantity;


                if ($stockDifference > 0) {

                    /*
                     * Additional quantity is being sold.
                     */

                    $availableStock =
                        (int)$lockedProducts[$productId]["quantity"];


                    if (
                        $stockDifference >
                        $availableStock
                    ) {

                        throw new Exception(
                            "Not enough stock for \"" .
                            $lockedProducts[$productId]["product_name"] .
                            "\". Available stock: " .
                            $availableStock .
                            "."
                        );

                    }

                }

            }


            /*
            |--------------------------------------------------------------------------
            | Update Product Stock
            |--------------------------------------------------------------------------
            */

            $stockUpdateStmt =
                $conn->prepare("
                    UPDATE products

                    SET quantity = quantity - ?

                    WHERE id = ?
                ");


            if (!$stockUpdateStmt) {

                throw new Exception(
                    "Unable to prepare stock update."
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Stock Transaction
            |--------------------------------------------------------------------------
            */

            $transactionStmt =
                $conn->prepare("
                    INSERT INTO stock_transactions
                    (
                        product_id,
                        transaction_type,
                        quantity,
                        reference_id,
                        notes,
                        transaction_date,
                        created_by
                    )

                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?,
                        NOW(),
                        ?
                    )
                ");


            if (!$transactionStmt) {

                throw new Exception(
                    "Unable to prepare stock transaction."
                );

            }


            $createdBy =
                isset($_SESSION["admin_id"])
                    ? (int)$_SESSION["admin_id"]
                    : null;


            /*
            |--------------------------------------------------------------------------
            | Apply Stock Differences
            |--------------------------------------------------------------------------
            */

            foreach ($allProductIds as $productId) {

                $oldQuantity =
                    isset($oldItems[$productId])
                        ? $oldItems[$productId]
                        : 0;


                $newQuantity =
                    isset($newItems[$productId])
                        ? $newItems[$productId]["quantity"]
                        : 0;


                $difference =
                    $newQuantity -
                    $oldQuantity;


                /*
                |--------------------------------------------------------------------------
                | Additional Sale
                |--------------------------------------------------------------------------
                */

                if ($difference > 0) {

                    $stockUpdateStmt->bind_param(
                        "ii",
                        $difference,
                        $productId
                    );


                    if (
                        !$stockUpdateStmt->execute()
                    ) {

                        throw new Exception(
                            "Unable to reduce stock."
                        );

                    }


                    $transactionType =
                        "Sale";


                    $transactionQuantity =
                        $difference;


                    $notes =
                        "Sale correction - additional quantity for " .
                        $lockedProducts[$productId]["product_name"] .
                        " - Invoice " .
                        $lockedSale["invoice_number"];


                    $transactionStmt->bind_param(
                        "isiisi",
                        $productId,
                        $transactionType,
                        $transactionQuantity,
                        $saleId,
                        $notes,
                        $createdBy
                    );


                    if (
                        !$transactionStmt->execute()
                    ) {

                        throw new Exception(
                            "Unable to record sale stock correction."
                        );

                    }

                }


                /*
                |--------------------------------------------------------------------------
                | Returned Quantity
                |--------------------------------------------------------------------------
                */

                elseif ($difference < 0) {

                    $returnedQuantity =
                        abs($difference);


                    /*
                     * Restore stock.
                     *
                     * quantity = quantity - (-difference)
                     * therefore stock increases.
                     */

                    $negativeDifference =
                        -$returnedQuantity;


                    $stockUpdateStmt->bind_param(
                        "ii",
                        $negativeDifference,
                        $productId
                    );


                    if (
                        !$stockUpdateStmt->execute()
                    ) {

                        throw new Exception(
                            "Unable to restore stock."
                        );

                    }


                    $transactionType =
                        "Return";


                    $transactionQuantity =
                        $returnedQuantity;


                    $notes =
                        "Sale correction - returned quantity for " .
                        $lockedProducts[$productId]["product_name"] .
                        " - Invoice " .
                        $lockedSale["invoice_number"];


                    $transactionStmt->bind_param(
                        "isiisi",
                        $productId,
                        $transactionType,
                        $transactionQuantity,
                        $saleId,
                        $notes,
                        $createdBy
                    );


                    if (
                        !$transactionStmt->execute()
                    ) {

                        throw new Exception(
                            "Unable to record returned stock."
                        );

                    }

                }

            }


            $stockUpdateStmt->close();

            $transactionStmt->close();


            /*
            |--------------------------------------------------------------------------
            | Update Sales Table
            |--------------------------------------------------------------------------
            */

            $updateSaleStmt =
                $conn->prepare("
                    UPDATE sales

                    SET
                        customer_id = ?,
                        subtotal = ?,
                        discount = ?,
                        grand_total = ?,
                        paid_amount = ?,
                        due_amount = ?,
                        payment_status = ?,
                        sale_date = ?

                    WHERE id = ?
                ");


            if (!$updateSaleStmt) {

                throw new Exception(
                    "Unable to prepare sale update."
                );

            }


            /*
             * Convert datetime-local input
             * to MySQL DATETIME format.
             */

            $mysqlSaleDate =
                date(
                    "Y-m-d H:i:s",
                    strtotime($saleDate)
                );


            $updateSaleStmt->bind_param(
                "idddddssi",
                $customerId,
                $subtotal,
                $discount,
                $grandTotal,
                $paidAmount,
                $dueAmount,
                $paymentStatus,
                $mysqlSaleDate,
                $saleId
            );


            if (
                !$updateSaleStmt->execute()
            ) {

                throw new Exception(
                    "Unable to update sale."
                );

            }


            $updateSaleStmt->close();


            /*
            |--------------------------------------------------------------------------
            | Update Service Link
            |--------------------------------------------------------------------------
            */

            $deleteInvoiceLinkStmt = $conn->prepare("DELETE FROM invoices WHERE sale_id = ?");
            if (!$deleteInvoiceLinkStmt) throw new Exception("Unable to update sale service link.");
            $deleteInvoiceLinkStmt->bind_param("i", $saleId);
            if (!$deleteInvoiceLinkStmt->execute()) throw new Exception("Unable to update sale service link.");
            $deleteInvoiceLinkStmt->close();

            if ($serviceId !== null) {
                $invoiceStmt = $conn->prepare("INSERT INTO invoices (invoice_number, sale_id, service_id, invoice_date) VALUES (?, ?, ?, NOW())");
                if (!$invoiceStmt) throw new Exception("Unable to link service order to sale.");
                $invoiceStmt->bind_param("sii", $sale['invoice_number'], $saleId, $serviceId);
                if (!$invoiceStmt->execute()) throw new Exception("Unable to link service order to sale.");
                $invoiceStmt->close();
            }


            /*
            |--------------------------------------------------------------------------
            | Delete Existing Sale Items
            |--------------------------------------------------------------------------
            */

            $deleteItemsStmt =
                $conn->prepare("
                    DELETE FROM sale_items
                    WHERE sale_id = ?
                ");


            if (!$deleteItemsStmt) {

                throw new Exception(
                    "Unable to remove old sale items."
                );

            }


            $deleteItemsStmt->bind_param(
                "i",
                $saleId
            );


            if (
                !$deleteItemsStmt->execute()
            ) {

                throw new Exception(
                    "Unable to remove old sale items."
                );

            }


            $deleteItemsStmt->close();


            /*
            |--------------------------------------------------------------------------
            | Insert Corrected Sale Items
            |--------------------------------------------------------------------------
            */

            $insertItemStmt =
                $conn->prepare("
                    INSERT INTO sale_items
                    (
                        sale_id,
                        product_id,
                        quantity,
                        unit_price,
                        total_price
                    )

                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        ?
                    )
                ");


            if (!$insertItemStmt) {

                throw new Exception(
                    "Unable to prepare sale item insertion."
                );

            }


            foreach ($newItems as $item) {

                $productId =
                    $item["product_id"];


                $quantity =
                    $item["quantity"];


                $unitPrice =
                    $item["unit_price"];


                $totalPrice =
                    $item["total_price"];


                $insertItemStmt->bind_param(
                    "iiidd",
                    $saleId,
                    $productId,
                    $quantity,
                    $unitPrice,
                    $totalPrice
                );


                if (
                    !$insertItemStmt->execute()
                ) {

                    throw new Exception(
                        "Unable to save corrected sale item."
                    );

                }

            }


            $insertItemStmt->close();


            /*
            |--------------------------------------------------------------------------
            | Commit
            |--------------------------------------------------------------------------
            */

            $conn->commit();


            header(
                "Location: view.php?id=" .
                $saleId .
                "&updated=1"
            );

            exit();


        } catch (Throwable $e) {

            $conn->rollback();

            $error =
                $e->getMessage();

        }

    }

}


/*
|--------------------------------------------------------------------------
| Prepare Form Values
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST" && $error !== "") {

    $formCustomerId =
        $customerId;

    $formSaleDate =
        $saleDate;

    $formDiscount =
        $discount;

    $formPaidAmount =
        $paidAmount;


    $formItems = [];


    foreach ($newItems as $item) {

        $formItems[] = $item;

    }

} else {

    $formCustomerId =
        $sale["customer_id"];

    $formSaleDate =
        date(
            "Y-m-d\TH:i",
            strtotime($sale["sale_date"])
        );

    $formDiscount =
        $sale["discount"];

    $formPaidAmount =
        $sale["paid_amount"];


    $formItems = [];


    foreach ($existingItems as $item) {

        $formItems[] = [

            "product_id" =>
                $item["product_id"],

            "quantity" =>
                $item["quantity"],

            "unit_price" =>
                $item["unit_price"],

            "total_price" =>
                $item["total_price"]

        ];

    }

}

?>


<div class="page-content">

    <div class="page-header">

        <div>

            <h1>Edit Sale</h1>

            <p>
                Correct customer, products, quantities, prices and payment information.
            </p>

        </div>


        <div>

            <a
                href="view.php?id=<?= (int)$saleId ?>"
                class="btn btn-secondary"
            >
                <i class="fa fa-arrow-left"></i>
                Back to Sale
            </a>

        </div>

    </div>


    <?php if ($error !== ""): ?>

        <div class="alert-error">

            <i class="fa fa-exclamation-triangle"></i>

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <!-- Invoice Number -->

    <div class="content-card">

        <div class="card-header">

            <h2>Sale Information</h2>

            <p>
                Invoice number cannot be changed.
            </p>

        </div>


        <div class="form-body">

            <div class="form-grid">

                <div>

                    <label class="form-label">
                        Invoice Number
                    </label>

                    <input
                        type="text"
                        class="form-control readonly-control"
                        value="<?= htmlspecialchars($sale['invoice_number']) ?>"
                        readonly
                    >

                </div>


                <div>

                    <label class="form-label">
                        Sale Date
                    </label>

                    <input
                        type="datetime-local"
                        name="sale_date"
                        form="saleForm"
                        class="form-control"
                        value="<?= htmlspecialchars($formSaleDate) ?>"
                        required
                    >

                </div>


                <div>

                    <label class="form-label">
                        Customer
                    </label>

                    <select
                        name="customer_id"
                        form="saleForm"
                        class="form-control"
                    >

                        <option value="">
                            Walk-in Customer
                        </option>

                        <?php foreach ($customers as $customer): ?>

                            <option
                                value="<?= (int)$customer['id'] ?>"
                                <?= (
                                    (string)$formCustomerId ===
                                    (string)$customer['id']
                                )
                                    ? 'selected'
                                    : ''
                                ?>
                            >

                                <?= htmlspecialchars(
                                    $customer['customer_name']
                                ) ?>

                                -
                                <?= htmlspecialchars(
                                    $customer['mobile']
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div>
                    <label class="form-label" for="service_id">Service Order</label>
                    <select name="service_id" id="service_id" form="saleForm" class="form-control">
                        <option value="">No Service Order</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?= (int)$service['id'] ?>" data-customer="<?= (int)$service['customer_id'] ?>" data-charge="<?= htmlspecialchars(number_format((float)$service['service_charge'], 2, '.', '')) ?>" <?= ($formServiceId !== null && (int)$formServiceId === (int)$service['id']) ? 'selected' : '' ?>>
                                Service Order #<?= (int)$service['service_order'] ?> — <?= htmlspecialchars($service['device_brand'] . ' ' . $service['device_model']) ?> — Charge: ৳<?= number_format((float)$service['service_charge'], 2) ?> — <?= htmlspecialchars($service['service_status']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="service-charge-note">Service Charge: <strong id="serviceChargeDisplay">৳0.00</strong></div>
                </div>

            </div>

        </div>

    </div>


    <!-- Sale Items -->

    <form
        method="POST"
        action="edit.php?id=<?= (int)$saleId ?>"
        id="saleForm"
    >

        <input
            type="hidden"
            name="customer_id"
            id="hiddenCustomerId"
            value="<?= $formCustomerId !== null
                ? (int)$formCustomerId
                : '' ?>"
        >

        <input
            type="hidden"
            name="sale_date"
            id="hiddenSaleDate"
            value="<?= htmlspecialchars($formSaleDate) ?>"
        >

        <input type="hidden" name="service_id" id="hiddenServiceId" value="<?= $formServiceId !== null ? (int)$formServiceId : '' ?>">


        <div class="content-card">

            <div class="card-header item-header">

                <div>

                    <h2>Sale Items</h2>

                    <p>
                        Correct products, quantities and unit prices.
                    </p>

                </div>


                <button
                    type="button"
                    class="btn btn-primary"
                    id="addItemBtn"
                >
                    <i class="fa fa-plus"></i>
                    Add Item
                </button>

            </div>


            <div class="table-wrapper">

                <table class="items-table">

                    <thead>

                        <tr>

                            <th>Product / Part</th>
                            <th>Current Stock</th>
                            <th>Unit Price</th>
                            <th>Quantity</th>
                            <th>Total</th>
                            <th>Action</th>

                        </tr>

                    </thead>


                    <tbody id="saleItemsBody">

                    <?php foreach ($formItems as $item): ?>

                        <tr class="sale-item-row">

                            <td>

                                <select
                                    name="product_id[]"
                                    class="product-select form-control"
                                    required
                                >

                                    <option value="">
                                        Select Product / Part
                                    </option>

                                    <?php foreach ($products as $product): ?>

                                        <option
                                            value="<?= (int)$product['id'] ?>"
                                            data-price="<?= htmlspecialchars($product['selling_price']) ?>"
                                            data-stock="<?= (int)$product['quantity'] ?>"
                                            data-status="<?= htmlspecialchars($product['status']) ?>"
                                            <?= (
                                                (int)$item["product_id"] ===
                                                (int)$product["id"]
                                            )
                                                ? "selected"
                                                : ""
                                            ?>
                                        >

                                            <?= htmlspecialchars(
                                                $product['product_name']
                                            ) ?>

                                            -
                                            <?= htmlspecialchars(
                                                $product['item_type']
                                            ) ?>

                                            (Stock:
                                            <?= (int)$product['quantity'] ?>)

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </td>


                            <td class="available-stock">
                                -
                            </td>


                            <td>

                                <input
                                    type="number"
                                    name="unit_price[]"
                                    class="form-control unit-price"
                                    value="<?= number_format(
                                        (float)$item['unit_price'],
                                        2,
                                        '.',
                                        ''
                                    ) ?>"
                                    min="0"
                                    step="0.01"
                                    required
                                >

                            </td>


                            <td>

                                <input
                                    type="number"
                                    name="quantity[]"
                                    class="form-control quantity-input"
                                    value="<?= (int)$item['quantity'] ?>"
                                    min="1"
                                    step="1"
                                    required
                                >

                            </td>


                            <td class="row-total">
                                ৳0.00
                            </td>


                            <td>

                                <button
                                    type="button"
                                    class="remove-item"
                                    title="Remove Item"
                                >
                                    <i class="fa fa-trash"></i>
                                </button>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        </div>


        <!-- Totals -->

        <div class="content-card totals-card">

            <div class="totals-body">

                <div class="total-line">
                    <span>Service Charge</span>
                    <strong id="serviceChargeTotalDisplay">৳0.00</strong>
                </div>

                <div class="total-line">

                    <span>
                        Subtotal
                    </span>

                    <strong id="subtotalDisplay">
                        ৳0.00
                    </strong>

                </div>


                <div class="total-line">

                    <label>
                        Discount
                    </label>

                    <input
                        type="number"
                        name="discount"
                        id="discount"
                        class="form-control total-input"
                        value="<?= number_format(
                            (float)$formDiscount,
                            2,
                            '.',
                            ''
                        ) ?>"
                        min="0"
                        step="0.01"
                    >

                </div>


                <div class="total-line grand-line">

                    <span>
                        Grand Total
                    </span>

                    <strong id="grandTotalDisplay">
                        ৳0.00
                    </strong>

                </div>


                <div class="total-line">

                    <label>
                        Paid Amount
                    </label>

                    <input
                        type="number"
                        name="paid_amount"
                        id="paid_amount"
                        class="form-control total-input"
                        value="<?= number_format(
                            (float)$formPaidAmount,
                            2,
                            '.',
                            ''
                        ) ?>"
                        min="0"
                        step="0.01"
                    >

                </div>


                <div class="total-line due-line">

                    <span>
                        Due Amount
                    </span>

                    <strong id="dueDisplay">
                        ৳0.00
                    </strong>

                </div>


                <div class="total-actions">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        <i class="fa fa-save"></i>
                        Update Sale
                    </button>


                    <a
                        href="view.php?id=<?= (int)$saleId ?>"
                        class="btn btn-secondary"
                    >
                        Cancel
                    </a>

                </div>

            </div>

        </div>

    </form>

</div>


<style>

.page-content {
    margin-left: 223px;
    padding: 32px 35px;
    width: calc(100% - 223px);
    box-sizing: border-box;
}

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 28px;
}

.page-header h1 {
    margin: 0 0 6px;
    font-size: 30px;
    color: #172033;
}

.page-header p {
    margin: 0;
    color: #526177;
    font-size: 14px;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    border: none;
    border-radius: 6px;
    padding: 10px 15px;
    text-decoration: none;
    font-size: 14px;
    cursor: pointer;
}

.btn-primary {
    background: #1769e0;
    color: #ffffff !important;
}

.btn-primary:hover {
    background: #0d5ccc;
}

.btn-secondary {
    background: #68727e;
    color: #ffffff !important;
}

.content-card {
    background: #ffffff;
    border-radius: 9px;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    overflow: hidden;
    margin-bottom: 22px;
}

.card-header {
    padding: 16px;
    border-bottom: 1px solid #e4e7eb;
}

.card-header h2 {
    margin: 0 0 5px;
    font-size: 18px;
    font-weight: 500;
    color: #172033;
}

.card-header p {
    margin: 0;
    color: #526177;
    font-size: 14px;
}

.item-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.form-body {
    padding: 20px;
}

.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1.5fr;
    gap: 18px;
}

.form-label {
    display: block;
    margin-bottom: 7px;
    color: #526177;
    font-size: 14px;
    font-weight: 600;
}

.form-control {
    width: 100%;
    min-height: 40px;
    padding: 8px 10px;
    border: 1px solid #d7dce2;
    border-radius: 6px;
    box-sizing: border-box;
    background: #ffffff;
}

.readonly-control {
    background: #f4f5f7;
    color: #68727e;
}

.table-wrapper {
    overflow-x: auto;
}

.items-table {
    width: 100%;
    min-width: 900px;
    border-collapse: collapse;
}

.items-table th {
    background: #f7f8fa;
    padding: 12px 14px;
    text-align: left;
    font-size: 13px;
    border-bottom: 1px solid #dfe3e8;
}

.items-table td {
    padding: 12px 14px;
    border-bottom: 1px solid #e5e8ec;
}

.available-stock {
    font-weight: 600;
    color: #526177;
}

.row-total {
    font-weight: 700;
    white-space: nowrap;
}

.remove-item {
    width: 31px;
    height: 31px;
    border: 1px solid #d9534f;
    background: #ffffff;
    color: #d9534f;
    border-radius: 5px;
    cursor: pointer;
}

.remove-item:hover {
    background: #d9534f;
    color: #ffffff;
}

.totals-card {
    display: flex;
    justify-content: flex-end;
}

.totals-body {
    width: 450px;
    padding: 22px;
}

.total-line {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    padding: 10px 0;
    color: #526177;
}

.total-line strong {
    color: #172033;
    font-size: 16px;
}

.grand-line {
    border-top: 1px solid #e1e5e9;
    border-bottom: 1px solid #e1e5e9;
    margin: 5px 0;
    padding: 14px 0;
}

.grand-line strong {
    font-size: 21px;
}

.due-line strong {
    color: #b42318;
}

.total-input {
    width: 180px;
}

.service-charge-note { margin-top: 9px; color: #526177; font-size: 14px; }

.total-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 18px;
}

.alert-error {
    background: #fde2e2;
    border: 1px solid #f4b4b4;
    color: #a61b1b;
    padding: 12px 15px;
    border-radius: 7px;
    margin-bottom: 20px;
}

@media (max-width: 900px) {

    .form-grid {
        grid-template-columns: 1fr;
    }

}

@media (max-width: 700px) {

    .page-content {
        margin-left: 0;
        width: 100%;
        padding: 20px;
    }

    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

    .totals-body {
        width: 100%;
        box-sizing: border-box;
    }

}

</style>


<script>

/*
|--------------------------------------------------------------------------
| Product Options
|--------------------------------------------------------------------------
*/

const productOptions = `
<?php foreach ($products as $product): ?>

<option
    value="<?= (int)$product['id'] ?>"
    data-price="<?= htmlspecialchars($product['selling_price']) ?>"
    data-stock="<?= (int)$product['quantity'] ?>"
    data-status="<?= htmlspecialchars($product['status']) ?>"
>

    <?= htmlspecialchars($product['product_name']) ?>

    -
    <?= htmlspecialchars($product['item_type']) ?>

    (Stock:
    <?= (int)$product['quantity'] ?>)

</option>

<?php endforeach; ?>
`;


/*
|--------------------------------------------------------------------------
| Update Row
|--------------------------------------------------------------------------
*/

function updateRow(row)
{
    const select =
        row.querySelector(".product-select");

    const priceInput =
        row.querySelector(".unit-price");

    const quantityInput =
        row.querySelector(".quantity-input");

    const stockDisplay =
        row.querySelector(".available-stock");

    const totalDisplay =
        row.querySelector(".row-total");


    const option =
        select.options[
            select.selectedIndex
        ];


    if (!option || !option.value) {

        stockDisplay.textContent =
            "-";

        totalDisplay.textContent =
            "৳0.00";

        calculateTotals();

        return;

    }


    const stock =
        parseInt(
            option.dataset.stock
        ) || 0;


    stockDisplay.textContent =
        stock;


    let quantity =
        parseInt(
            quantityInput.value
        ) || 1;


    if (quantity < 1) {

        quantity = 1;

        quantityInput.value = 1;

    }


    const price =
        parseFloat(
            priceInput.value
        ) || 0;


    const total =
        price * quantity;


    totalDisplay.textContent =
        "৳" +
        total.toFixed(2);


    calculateTotals();
}


/*
|--------------------------------------------------------------------------
| Calculate Totals
|--------------------------------------------------------------------------
*/

function getSelectedServiceCharge()
{
    const serviceSelect = document.getElementById("service_id");
    if (!serviceSelect || !serviceSelect.value) return 0;
    const option = serviceSelect.options[serviceSelect.selectedIndex];
    return parseFloat(option.dataset.charge || "0") || 0;
}


function calculateTotals()
{
    let subtotal = 0;


    document
        .querySelectorAll(".sale-item-row")
        .forEach(function(row) {

            const price =
                parseFloat(
                    row.querySelector(
                        ".unit-price"
                    ).value
                ) || 0;


            const quantity =
                parseInt(
                    row.querySelector(
                        ".quantity-input"
                    ).value
                ) || 0;


            subtotal +=
                price * quantity;

        });


    subtotal =
        Math.max(
            0,
            subtotal
        );

    const serviceCharge = getSelectedServiceCharge();
    subtotal = Math.max(0, subtotal + serviceCharge);
    const serviceChargeDisplay = document.getElementById("serviceChargeDisplay");
    const serviceChargeTotalDisplay = document.getElementById("serviceChargeTotalDisplay");
    if (serviceChargeDisplay) serviceChargeDisplay.textContent = "৳" + serviceCharge.toFixed(2);
    if (serviceChargeTotalDisplay) serviceChargeTotalDisplay.textContent = "৳" + serviceCharge.toFixed(2);


    let discount =
        parseFloat(
            document.getElementById(
                "discount"
            ).value
        ) || 0;


    if (discount < 0) {
        discount = 0;
    }


    let grandTotal =
        Math.max(
            0,
            subtotal - discount
        );


    let paid =
        parseFloat(
            document.getElementById(
                "paid_amount"
            ).value
        ) || 0;


    if (paid < 0) {
        paid = 0;
    }


    let due =
        Math.max(
            0,
            grandTotal - paid
        );


    document.getElementById(
        "subtotalDisplay"
    ).textContent =
        "৳" +
        subtotal.toFixed(2);


    document.getElementById(
        "grandTotalDisplay"
    ).textContent =
        "৳" +
        grandTotal.toFixed(2);


    document.getElementById(
        "dueDisplay"
    ).textContent =
        "৳" +
        due.toFixed(2);


    /*
     * Update every row total.
     */

    document
        .querySelectorAll(".sale-item-row")
        .forEach(function(row) {

            const price =
                parseFloat(
                    row.querySelector(
                        ".unit-price"
                    ).value
                ) || 0;


            const quantity =
                parseInt(
                    row.querySelector(
                        ".quantity-input"
                    ).value
                ) || 0;


            row.querySelector(
                ".row-total"
            ).textContent =
                "৳" +
                (
                    price * quantity
                ).toFixed(2);

        });
}


/*
|--------------------------------------------------------------------------
| Attach Row Events
|--------------------------------------------------------------------------
*/

function attachRowEvents(row)
{

    row.querySelector(
        ".product-select"
    ).addEventListener(
        "change",
        function() {

            /*
             * When changing product,
             * automatically load its selling price.
             */

            const option =
                this.options[
                    this.selectedIndex
                ];


            if (
                option &&
                option.value
            ) {

                const price =
                    parseFloat(
                        option.dataset.price
                    ) || 0;


                row.querySelector(
                    ".unit-price"
                ).value =
                    price.toFixed(2);

            }


            updateRow(row);

        }
    );


    row.querySelector(
        ".unit-price"
    ).addEventListener(
        "input",
        function() {

            updateRow(row);

        }
    );


    row.querySelector(
        ".quantity-input"
    ).addEventListener(
        "input",
        function() {

            updateRow(row);

        }
    );


    row.querySelector(
        ".remove-item"
    ).addEventListener(
        "click",
        function() {

            const rows =
                document.querySelectorAll(
                    ".sale-item-row"
                );


            if (rows.length <= 1) {

                alert(
                    "At least one sale item is required."
                );

                return;

            }


            row.remove();

            calculateTotals();

        }
    );


    updateRow(row);
}


/*
|--------------------------------------------------------------------------
| Existing Rows
|--------------------------------------------------------------------------
*/

document
    .querySelectorAll(
        ".sale-item-row"
    )
    .forEach(function(row) {

        attachRowEvents(row);

    });


/*
|--------------------------------------------------------------------------
| Add New Item
|--------------------------------------------------------------------------
*/

document
    .getElementById(
        "addItemBtn"
    )
    .addEventListener(
        "click",
        function() {

            const tbody =
                document.getElementById(
                    "saleItemsBody"
                );


            const row =
                document.createElement(
                    "tr"
                );


            row.className =
                "sale-item-row";


            row.innerHTML = `

                <td>

                    <select
                        name="product_id[]"
                        class="product-select form-control"
                        required
                    >

                        <option value="">
                            Select Product / Part
                        </option>

                        ${productOptions}

                    </select>

                </td>


                <td class="available-stock">
                    -
                </td>


                <td>

                    <input
                        type="number"
                        name="unit_price[]"
                        class="form-control unit-price"
                        value="0.00"
                        min="0"
                        step="0.01"
                        required
                    >

                </td>


                <td>

                    <input
                        type="number"
                        name="quantity[]"
                        class="form-control quantity-input"
                        value="1"
                        min="1"
                        step="1"
                        required
                    >

                </td>


                <td class="row-total">
                    ৳0.00
                </td>


                <td>

                    <button
                        type="button"
                        class="remove-item"
                        title="Remove Item"
                    >

                        <i class="fa fa-trash"></i>

                    </button>

                </td>

            `;


            tbody.appendChild(row);


            attachRowEvents(row);

        }
    );


/*
|--------------------------------------------------------------------------
| Discount / Paid Amount
|--------------------------------------------------------------------------
*/

document
    .getElementById(
        "discount"
    )
    .addEventListener(
        "input",
        calculateTotals
    );


document
    .getElementById(
        "paid_amount"
    )
    .addEventListener(
        "input",
        calculateTotals
    );


/*
|--------------------------------------------------------------------------
| Keep Header Customer / Date
| synchronized with form
|--------------------------------------------------------------------------
*/

const headerCustomer =
    document.querySelector(
        'select[name="customer_id"][form="saleForm"]'
    );


if (headerCustomer) {

    headerCustomer.addEventListener(
        "change",
        function() {

            document.getElementById(
                "hiddenCustomerId"
            ).value =
                this.value;

            filterServicesByCustomer();

        }
    );

}


const serviceSelect = document.getElementById("service_id");

function filterServicesByCustomer()
{
    if (!headerCustomer || !serviceSelect) return;
    const customerId = headerCustomer.value;
    let selectedValid = false;
    Array.from(serviceSelect.options).forEach(function(option) {
        if (!option.value) { option.hidden = false; return; }
        const visible = customerId !== "" && option.dataset.customer === customerId;
        option.hidden = !visible;
        if (visible && option.selected) selectedValid = true;
    });
    if (!selectedValid && serviceSelect.value) {
        serviceSelect.value = "";
        document.getElementById("hiddenServiceId").value = "";
    }
    calculateTotals();
}

if (serviceSelect) {
    serviceSelect.addEventListener("change", function() {
        document.getElementById("hiddenServiceId").value = this.value;
        calculateTotals();
    });
}

filterServicesByCustomer();


const headerSaleDate =
    document.querySelector(
        'input[name="sale_date"][form="saleForm"]'
    );


if (headerSaleDate) {

    headerSaleDate.addEventListener(
        "change",
        function() {

            document.getElementById(
                "hiddenSaleDate"
            ).value =
                this.value;

        }
    );

}


/*
|--------------------------------------------------------------------------
| Initial Calculation
|--------------------------------------------------------------------------
*/

calculateTotals();

</script>