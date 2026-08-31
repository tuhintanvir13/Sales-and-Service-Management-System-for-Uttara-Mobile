<?php

require_once __DIR__ . '/../config/database.php';

$basePath = "../";
$pageTitle = "New Sale";

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';


$error = "";


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
| Load Active Products / Parts
|--------------------------------------------------------------------------
*/

$products = [];

$productResult = $conn->query("
    SELECT
        id,
        product_name,
        item_type,
        selling_price,
        quantity
    FROM products
    WHERE status = 'Active'
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
| Process Sale
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $customerId = filter_input(
        INPUT_POST,
        "customer_id",
        FILTER_VALIDATE_INT
    );

    if ($customerId === false) {
        $customerId = null;
    }


    $productIds = $_POST["product_id"] ?? [];
    $quantities = $_POST["quantity"] ?? [];

    $serviceId = filter_input(INPUT_POST, "service_id", FILTER_VALIDATE_INT);
    if ($serviceId === false) {
        $serviceId = null;
    }

    $discount = isset($_POST["discount"])
        ? (float)$_POST["discount"]
        : 0;

    $paidAmount = isset($_POST["paid_amount"])
        ? (float)$_POST["paid_amount"]
        : 0;


    /*
    |--------------------------------------------------------------------------
    | Basic Validation
    |--------------------------------------------------------------------------
    */

    if (empty($productIds) || empty($quantities)) {

        $error = "Please add at least one product or part.";

    } elseif ($discount < 0) {

        $error = "Discount cannot be negative.";

    } elseif ($paidAmount < 0) {

        $error = "Paid amount cannot be negative.";

    }


    /*
    |--------------------------------------------------------------------------
    | Prepare Sale Items
    |--------------------------------------------------------------------------
    */

    $items = [];

    if ($error === "") {

        foreach ($productIds as $index => $productId) {

            $productId = (int)$productId;

            $quantity = isset($quantities[$index])
                ? (int)$quantities[$index]
                : 0;


            if ($productId <= 0) {
                continue;
            }


            if ($quantity < 1) {

                $error =
                    "Every sale quantity must be at least 1.";

                break;
            }


            /*
             * If the same product is added more than once,
             * combine the quantities.
             */

            if (isset($items[$productId])) {

                $items[$productId]["quantity"] += $quantity;

            } else {

                $items[$productId] = [
                    "product_id" => $productId,
                    "quantity" => $quantity
                ];

            }

        }


        if ($error === "" && empty($items)) {

            $error =
                "Please add at least one valid product or part.";

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Database Transaction
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $conn->begin_transaction();


        try {

            /*
             * Verify customer if selected.
             */

            if ($customerId !== null) {

                $customerStmt = $conn->prepare("
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

                $customerResult =
                    $customerStmt->get_result();

                $customerExists =
                    $customerResult->fetch_assoc();

                $customerStmt->close();


                if (!$customerExists) {

                    throw new Exception(
                        "Selected customer was not found."
                    );

                }

            }


            /*
             * Verify selected service order and read its current charge.
             */

            $serviceCharge = 0.00;

            if ($serviceId !== null) {
                if ($customerId === null) {
                    throw new Exception("Please select a customer before selecting a service order.");
                }

                $serviceStmt = $conn->prepare("
                    SELECT id, customer_id, service_charge
                    FROM services
                    WHERE id = ?
                    LIMIT 1
                    FOR UPDATE
                ");
                if (!$serviceStmt) throw new Exception("Unable to verify service order.");
                $serviceStmt->bind_param("i", $serviceId);
                $serviceStmt->execute();
                $selectedService = $serviceStmt->get_result()->fetch_assoc();
                $serviceStmt->close();

                if (!$selectedService) throw new Exception("Selected service order was not found.");
                if ((int)$selectedService['customer_id'] !== (int)$customerId) {
                    throw new Exception("Selected service order does not belong to the selected customer.");
                }
                $serviceCharge = round((float)$selectedService['service_charge'], 2);
            }


            /*
             * Verify products and calculate subtotal.
             *
             * FOR UPDATE locks the rows until
             * the transaction is completed.
             */

            $verifiedItems = [];

            $subtotal = 0;


            foreach ($items as $item) {

                $productId =
                    $item["product_id"];

                $requestedQuantity =
                    $item["quantity"];


                $productStmt = $conn->prepare("
                    SELECT
                        id,
                        product_name,
                        selling_price,
                        quantity,
                        status
                    FROM products
                    WHERE id = ?
                    LIMIT 1
                    FOR UPDATE
                ");


                if (!$productStmt) {

                    throw new Exception(
                        "Unable to verify product."
                    );

                }


                $productStmt->bind_param(
                    "i",
                    $productId
                );

                $productStmt->execute();

                $productResult =
                    $productStmt->get_result();

                $product =
                    $productResult->fetch_assoc();

                $productStmt->close();


                if (!$product) {

                    throw new Exception(
                        "A selected product or part was not found."
                    );

                }


                if ($product["status"] !== "Active") {

                    throw new Exception(
                        "Product/Part \"" .
                        $product["product_name"] .
                        "\" is inactive."
                    );

                }


                $availableQuantity =
                    (int)$product["quantity"];


                if ($requestedQuantity > $availableQuantity) {

                    throw new Exception(
                        "Not enough stock for \"" .
                        $product["product_name"] .
                        "\". Available stock: " .
                        $availableQuantity .
                        "."
                    );

                }


                $unitPrice =
                    round(
                        (float)$product["selling_price"],
                        2
                    );


                $totalPrice =
                    round(
                        $unitPrice * $requestedQuantity,
                        2
                    );


                $subtotal =
                    round(
                        $subtotal + $totalPrice,
                        2
                    );


                $verifiedItems[] = [

                    "product_id" =>
                        $productId,

                    "product_name" =>
                        $product["product_name"],

                    "quantity" =>
                        $requestedQuantity,

                    "unit_price" =>
                        $unitPrice,

                    "total_price" =>
                        $totalPrice,

                    "current_stock" =>
                        $availableQuantity

                ];

            }


            /*
             * Add the selected service charge to the product subtotal.
             * This MUST happen server-side as well as in JavaScript so
             * the value shown in the form and the value validated/saved
             * by PHP are identical.
             */
            $subtotal = round($subtotal + $serviceCharge, 2);


            /*
             * Discount validation.
             */

            $discount =
                round($discount, 2);


            if ($discount > $subtotal) {

                throw new Exception(
                    "Discount cannot be greater than the subtotal."
                );

            }


            /*
             * Calculate grand total.
             */

            $grandTotal =
                round(
                    $subtotal - $discount,
                    2
                );


            /*
             * Payment validation.
             */

            $paidAmount =
                round($paidAmount, 2);


            if ($paidAmount > $grandTotal) {

                throw new Exception(
                    "Paid amount cannot be greater than the grand total."
                );

            }


            $dueAmount =
                round(
                    $grandTotal - $paidAmount,
                    2
                );


            /*
             * Determine payment status.
             */

            if ($dueAmount <= 0) {

                $paymentStatus = "Paid";

                $dueAmount = 0;

                $paidAmount = $grandTotal;

            } elseif ($paidAmount > 0) {

                $paymentStatus = "Partial";

            } else {

                $paymentStatus = "Due";

            }


            /*
             * Generate invoice number.
             */

            $invoiceNumber =
                "INV-" .
                date("Ymd-His") .
                "-" .
                random_int(100, 999);


            /*
             * Logged-in admin.
             */

            $createdBy =
                isset($_SESSION["admin_id"])
                    ? (int)$_SESSION["admin_id"]
                    : null;


            /*
             * Insert Sale.
             */

            $saleSql = "
                INSERT INTO sales
                (
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
                )
                VALUES
                (
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    ?,
                    NOW(),
                    ?
                )
            ";


            $saleStmt =
                $conn->prepare($saleSql);


            if (!$saleStmt) {

                throw new Exception(
                    "Unable to create sale."
                );

            }


            $saleStmt->bind_param(
                "isdddddsi",
                $customerId,
                $invoiceNumber,
                $subtotal,
                $discount,
                $grandTotal,
                $paidAmount,
                $dueAmount,
                $paymentStatus,
                $createdBy
            );


            if (!$saleStmt->execute()) {

                throw new Exception(
                    "Unable to save sale."
                );

            }


            $saleId =
                $conn->insert_id;


            $saleStmt->close();


            /*
             * Link selected service using the existing invoices table.
             */
            if ($serviceId !== null) {
                $invoiceStmt = $conn->prepare("
                    INSERT INTO invoices (invoice_number, sale_id, service_id, invoice_date)
                    VALUES (?, ?, ?, NOW())
                ");
                if (!$invoiceStmt) {
                    throw new Exception("Unable to link service order to sale.");
                }
                $invoiceStmt->bind_param("sii", $invoiceNumber, $saleId, $serviceId);
                if (!$invoiceStmt->execute()) {
                    throw new Exception("Unable to link service order to sale.");
                }
                $invoiceStmt->close();
            }


            /*
             * Insert Sale Items
             */

            $itemStmt = $conn->prepare("
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


            if (!$itemStmt) {

                throw new Exception(
                    "Unable to prepare sale item."
                );

            }


            /*
             * Stock Update Statement
             */

            $stockStmt = $conn->prepare("
                UPDATE products
                SET quantity = quantity - ?
                WHERE id = ?
            ");


            if (!$stockStmt) {

                throw new Exception(
                    "Unable to prepare stock update."
                );

            }


            /*
             * Stock Transaction Statement
             */

            $transactionStmt = $conn->prepare("
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
                    'Sale',
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


            foreach ($verifiedItems as $item) {

                /*
                 * Insert sale item
                 */

                $itemStmt->bind_param(
                    "iiidd",
                    $saleId,
                    $item["product_id"],
                    $item["quantity"],
                    $item["unit_price"],
                    $item["total_price"]
                );


                if (!$itemStmt->execute()) {

                    throw new Exception(
                        "Unable to save sale item."
                    );

                }


                /*
                 * Reduce product stock
                 */

                $stockStmt->bind_param(
                    "ii",
                    $item["quantity"],
                    $item["product_id"]
                );


                if (!$stockStmt->execute()) {

                    throw new Exception(
                        "Unable to update product stock."
                    );

                }


                /*
                 * Create stock transaction
                 */

                $notes =
                    "Sale " .
                    $invoiceNumber .
                    " - " .
                    $item["product_name"];


                $transactionStmt->bind_param(
                    "iiisi",
                    $item["product_id"],
                    $item["quantity"],
                    $saleId,
                    $notes,
                    $createdBy
                );


                if (!$transactionStmt->execute()) {

                    throw new Exception(
                        "Unable to save stock transaction."
                    );

                }

            }


            $itemStmt->close();
            $stockStmt->close();
            $transactionStmt->close();


            /*
             * Everything successful.
             */

            $conn->commit();


            header(
                "Location: view.php?id=" .
                $saleId .
                "&success=1"
            );

            exit();


        } catch (Throwable $e) {

            $conn->rollback();

            $error =
                $e->getMessage();

        }

    }

}

?>

<div class="page-content">

    <div class="page-header">

        <div>

            <h1>New Sale</h1>

            <p>
                Create a new product or part sale.
            </p>

        </div>

        <div>

            <a
                href="index.php"
                class="btn btn-secondary"
            >
                <i class="fa fa-arrow-left"></i>
                Back to Sales
            </a>

        </div>

    </div>


    <?php if ($error !== ""): ?>

        <div class="alert-error">

            <i class="fa fa-exclamation-triangle"></i>

            <?= htmlspecialchars($error) ?>

        </div>

    <?php endif; ?>


    <form
        method="POST"
        action="add.php"
        id="saleForm"
    >

        <!-- Customer -->

        <div class="content-card">

            <div class="card-header">

                <h2>Customer Information</h2>

                <p>
                    Select an existing customer or leave blank for a walk-in customer.
                </p>

            </div>

            <div class="form-body">

                <label class="form-label">
                    Customer
                </label>

                <select
                    name="customer_id"
                    class="form-control"
                >

                    <option value="">
                        Walk-in Customer
                    </option>

                    <?php foreach ($customers as $customer): ?>

                        <option
                            value="<?= (int)$customer['id'] ?>"
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

        </div>


        <!-- Service Order -->

        <div class="content-card">
            <div class="card-header">
                <h2>Service Order</h2>
                <p>Select a service order for the customer. Its service charge will be included in the sale total.</p>
            </div>
            <div class="form-body">
                <label class="form-label" for="service_id">Service Order</label>
                <select name="service_id" id="service_id" class="form-control">
                    <option value="">No Service Order</option>
                    <?php foreach ($services as $service): ?>
                        <option value="<?= (int)$service['id'] ?>" data-customer="<?= (int)$service['customer_id'] ?>" data-charge="<?= htmlspecialchars(number_format((float)$service['service_charge'], 2, '.', '')) ?>">
                            Service Order #<?= (int)$service['service_order'] ?> — <?= htmlspecialchars($service['device_brand'] . ' ' . $service['device_model']) ?> — Charge: ৳<?= number_format((float)$service['service_charge'], 2) ?> — <?= htmlspecialchars($service['service_status']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="service-charge-note">Service Charge: <strong id="serviceChargeDisplay">৳0.00</strong></div>
            </div>
        </div>


        <!-- Sale Items -->

        <div class="content-card sale-items-card">

            <div class="card-header item-header">

                <div>

                    <h2>Sale Items</h2>

                    <p>
                        Add one or more products or parts.
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
                            <th>Available</th>
                            <th>Unit Price</th>
                            <th>Quantity</th>
                            <th>Total</th>
                            <th></th>

                        </tr>

                    </thead>


                    <tbody id="saleItemsBody">

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
                                    name="display_price[]"
                                    class="form-control unit-price"
                                    value="0.00"
                                    readonly
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

                        </tr>

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

                    <label for="discount">
                        Discount
                    </label>

                    <input
                        type="number"
                        name="discount"
                        id="discount"
                        class="form-control total-input"
                        value="0.00"
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

                    <label for="paid_amount">
                        Paid Amount
                    </label>

                    <input
                        type="number"
                        name="paid_amount"
                        id="paid_amount"
                        class="form-control total-input"
                        value="0.00"
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
                        class="btn btn-primary confirm-btn"
                    >
                        <i class="fa fa-check-circle"></i>
                        Confirm Sale
                    </button>

                    <a
                        href="index.php"
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
    align-items: center;
    justify-content: space-between;
}

.form-body {
    padding: 20px;
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

.alert-error {
    background: #fde2e2;
    border: 1px solid #f4b4b4;
    color: #a61b1b;
    padding: 12px 15px;
    border-radius: 7px;
    margin-bottom: 20px;
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

.unit-price {
    background: #f5f6f8;
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

.service-charge-note {
    margin-top: 9px;
    color: #526177;
    font-size: 14px;
}

.total-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 18px;
}

.confirm-btn {
    padding-left: 18px;
    padding-right: 18px;
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

const productOptions = `
<?php foreach ($products as $product): ?>
<option
    value="<?= (int)$product['id'] ?>"
    data-price="<?= htmlspecialchars($product['selling_price']) ?>"
    data-stock="<?= (int)$product['quantity'] ?>"
>
    <?= htmlspecialchars($product['product_name']) ?>
    -
    <?= htmlspecialchars($product['item_type']) ?>
    (Stock: <?= (int)$product['quantity'] ?>)
</option>
<?php endforeach; ?>
`;


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
        select.options[select.selectedIndex];


    if (!option || !option.value) {

        priceInput.value = "0.00";

        stockDisplay.textContent = "-";

        totalDisplay.textContent = "৳0.00";

        calculateTotals();

        return;
    }


    const price =
        parseFloat(option.dataset.price) || 0;

    const stock =
        parseInt(option.dataset.stock) || 0;


    priceInput.value =
        price.toFixed(2);

    stockDisplay.textContent =
        stock;


    let quantity =
        parseInt(quantityInput.value) || 1;


    if (quantity < 1) {
        quantity = 1;
        quantityInput.value = 1;
    }


    if (quantity > stock && stock >= 0) {
        quantityInput.value = stock > 0 ? stock : 1;
        quantity = parseInt(quantityInput.value);
    }


    const total =
        price * quantity;


    totalDisplay.textContent =
        "৳" + total.toFixed(2);


    calculateTotals();
}


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
                    row.querySelector(".unit-price").value
                ) || 0;

            const quantity =
                parseInt(
                    row.querySelector(".quantity-input").value
                ) || 0;


            subtotal +=
                price * quantity;

        });


    subtotal =
        Math.max(0, subtotal);

    const serviceCharge = getSelectedServiceCharge();
    subtotal = Math.max(0, subtotal + serviceCharge);

    const serviceChargeDisplay = document.getElementById("serviceChargeDisplay");
    const serviceChargeTotalDisplay = document.getElementById("serviceChargeTotalDisplay");
    if (serviceChargeDisplay) serviceChargeDisplay.textContent = "৳" + serviceCharge.toFixed(2);
    if (serviceChargeTotalDisplay) serviceChargeTotalDisplay.textContent = "৳" + serviceCharge.toFixed(2);


    let discount =
        parseFloat(
            document.getElementById("discount").value
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
            document.getElementById("paid_amount").value
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
        "৳" + subtotal.toFixed(2);


    document.getElementById(
        "grandTotalDisplay"
    ).textContent =
        "৳" + grandTotal.toFixed(2);


    document.getElementById(
        "dueDisplay"
    ).textContent =
        "৳" + due.toFixed(2);
}


function attachRowEvents(row)
{
    row.querySelector(
        ".product-select"
    ).addEventListener(
        "change",
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


document
    .querySelectorAll(".sale-item-row")
    .forEach(function(row) {
        attachRowEvents(row);
    });


document
    .getElementById("addItemBtn")
    .addEventListener(
        "click",
        function() {

            const tbody =
                document.getElementById(
                    "saleItemsBody"
                );


            const row =
                document.createElement("tr");

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
                        name="display_price[]"
                        class="form-control unit-price"
                        value="0.00"
                        readonly
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


const customerSelect = document.querySelector('select[name="customer_id"]');
const serviceSelect = document.getElementById("service_id");

function filterServicesByCustomer()
{
    if (!customerSelect || !serviceSelect) return;
    const customerId = customerSelect.value;
    let selectedValid = false;
    Array.from(serviceSelect.options).forEach(function(option) {
        if (!option.value) { option.hidden = false; return; }
        const visible = customerId !== "" && option.dataset.customer === customerId;
        option.hidden = !visible;
        if (visible && option.selected) selectedValid = true;
    });
    if (!selectedValid && serviceSelect.value) serviceSelect.value = "";
    calculateTotals();
}

if (customerSelect) customerSelect.addEventListener("change", filterServicesByCustomer);
if (serviceSelect) serviceSelect.addEventListener("change", calculateTotals);
filterServicesByCustomer();


document
    .getElementById("discount")
    .addEventListener(
        "input",
        calculateTotals
    );


document
    .getElementById("paid_amount")
    .addEventListener(
        "input",
        calculateTotals
    );


document
    .getElementById("saleForm")
    .addEventListener(
        "submit",
        function(event) {

            const rows =
                document.querySelectorAll(
                    ".sale-item-row"
                );


            let valid = true;


            rows.forEach(function(row) {

                const select =
                    row.querySelector(
                        ".product-select"
                    );

                const quantity =
                    parseInt(
                        row.querySelector(
                            ".quantity-input"
                        ).value
                    ) || 0;


                if (!select.value || quantity < 1) {
                    valid = false;
                }

            });


            if (!valid) {

                event.preventDefault();

                alert(
                    "Please select a product and enter a valid quantity for every item."
                );

            }

        }
    );


calculateTotals();

</script>