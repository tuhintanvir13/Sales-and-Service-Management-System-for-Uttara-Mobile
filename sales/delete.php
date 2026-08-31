<?php

require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Delete Sale
|--------------------------------------------------------------------------
| Deletes only the selected sale record and its sale_items.
|
| Because creating a sale reduces product stock, deleting a sale restores
| the exact quantities sold and records those restorations as Return
| transactions in stock_transactions.
|
| Customer, product, service and invoice-independent data are NOT deleted.
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$saleId = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT
);

if (!$saleId || $saleId < 1) {
    header('Location: index.php?error=invalid');
    exit();
}

$conn->begin_transaction();

try {

    /* Lock the sale first. */
    $saleStmt = $conn->prepare("\n        SELECT\n            id,\n            invoice_number\n        FROM sales\n        WHERE id = ?\n        LIMIT 1\n        FOR UPDATE\n    ");

    if (!$saleStmt) {
        throw new Exception('Unable to prepare sale lookup.');
    }

    $saleStmt->bind_param('i', $saleId);
    $saleStmt->execute();
    $sale = $saleStmt->get_result()->fetch_assoc();
    $saleStmt->close();

    if (!$sale) {
        throw new Exception('Sale record was not found.');
    }

    /*
     * Get all products and quantities belonging to this sale.
     * The product rows are also locked before stock is changed.
     */
    $itemsStmt = $conn->prepare("\n        SELECT\n            si.product_id,\n            si.quantity,\n            p.product_name\n        FROM sale_items si\n        INNER JOIN products p\n            ON p.id = si.product_id\n        WHERE si.sale_id = ?\n        FOR UPDATE\n    ");

    if (!$itemsStmt) {
        throw new Exception('Unable to load sale items.');
    }

    $itemsStmt->bind_param('i', $saleId);
    $itemsStmt->execute();
    $itemsResult = $itemsStmt->get_result();

    $items = [];

    while ($item = $itemsResult->fetch_assoc()) {
        $productId = (int)$item['product_id'];
        $quantity = (int)$item['quantity'];

        if ($productId > 0 && $quantity > 0) {
            if (!isset($items[$productId])) {
                $items[$productId] = [
                    'product_name' => $item['product_name'],
                    'quantity' => 0
                ];
            }

            $items[$productId]['quantity'] += $quantity;
        }
    }

    $itemsStmt->close();

    /* Restore product stock. */
    $stockStmt = $conn->prepare("\n        UPDATE products\n        SET quantity = quantity + ?\n        WHERE id = ?\n    ");

    if (!$stockStmt) {
        throw new Exception('Unable to prepare stock restoration.');
    }

    /* Record stock restoration as Return transactions. */
    $transactionStmt = $conn->prepare("\n        INSERT INTO stock_transactions\n        (\n            product_id,\n            transaction_type,\n            quantity,\n            reference_id,\n            notes,\n            transaction_date,\n            created_by\n        )\n        VALUES\n        (\n            ?,\n            ?,\n            ?,\n            ?,\n            ?,\n            NOW(),\n            ?\n        )\n    ");

    if (!$transactionStmt) {
        throw new Exception('Unable to prepare stock transaction.');
    }

    $createdBy = isset($_SESSION['admin_id'])
        ? (int)$_SESSION['admin_id']
        : null;

    $transactionType = 'Return';

    foreach ($items as $productId => $item) {

        $quantity = (int)$item['quantity'];

        $stockStmt->bind_param(
            'ii',
            $quantity,
            $productId
        );

        if (!$stockStmt->execute()) {
            throw new Exception(
                'Unable to restore stock for "' .
                $item['product_name'] . '".'
            );
        }

        $notes =
            'Sale deleted - stock restored for ' .
            $item['product_name'] .
            ' - Invoice ' .
            $sale['invoice_number'];

        $transactionStmt->bind_param(
            'isiisi',
            $productId,
            $transactionType,
            $quantity,
            $saleId,
            $notes,
            $createdBy
        );

        if (!$transactionStmt->execute()) {
            throw new Exception(
                'Unable to record stock restoration.'
            );
        }
    }

    $stockStmt->close();
    $transactionStmt->close();

    /* Delete child sale items first. */
    $deleteItemsStmt = $conn->prepare("\n        DELETE FROM sale_items\n        WHERE sale_id = ?\n    ");

    if (!$deleteItemsStmt) {
        throw new Exception('Unable to prepare sale item deletion.');
    }

    $deleteItemsStmt->bind_param('i', $saleId);

    if (!$deleteItemsStmt->execute()) {
        throw new Exception('Unable to delete sale items.');
    }

    $deleteItemsStmt->close();

    /* Delete only the sale record. */
    $deleteSaleStmt = $conn->prepare("\n        DELETE FROM sales\n        WHERE id = ?\n        LIMIT 1\n    ");

    if (!$deleteSaleStmt) {
        throw new Exception('Unable to prepare sale deletion.');
    }

    $deleteSaleStmt->bind_param('i', $saleId);

    if (!$deleteSaleStmt->execute()) {
        throw new Exception('Unable to delete sale record.');
    }

    if ($deleteSaleStmt->affected_rows !== 1) {
        throw new Exception('Sale record could not be deleted.');
    }

    $deleteSaleStmt->close();

    $conn->commit();

    header('Location: index.php?deleted=1');
    exit();

} catch (Throwable $e) {

    $conn->rollback();

    header(
        'Location: index.php?error=' .
        urlencode($e->getMessage())
    );
    exit();
}
