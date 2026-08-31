<?php

require_once __DIR__ . '/../config/database.php';

$basePath = "../";

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';


/*
|--------------------------------------------------------------------------
| Sales Statistics
|--------------------------------------------------------------------------
*/

$totalSales   = 0;
$todaySales   = 0;
$totalRevenue = 0;
$totalDue     = 0;


/*
|--------------------------------------------------------------------------
| Total Sales
|--------------------------------------------------------------------------
*/

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM sales
");

if ($result) {
    $row = $result->fetch_assoc();
    $totalSales = (int) $row['total'];
}


/*
|--------------------------------------------------------------------------
| Today's Sales
|--------------------------------------------------------------------------
*/

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM sales
    WHERE DATE(sale_date) = CURDATE()
");

if ($result) {
    $row = $result->fetch_assoc();
    $todaySales = (int) $row['total'];
}


/*
|--------------------------------------------------------------------------
| Total Revenue
|--------------------------------------------------------------------------
*/

$result = $conn->query("
    SELECT COALESCE(SUM(grand_total), 0) AS total
    FROM sales
");

if ($result) {
    $row = $result->fetch_assoc();
    $totalRevenue = (float) $row['total'];
}


/*
|--------------------------------------------------------------------------
| Total Due
|--------------------------------------------------------------------------
*/

$result = $conn->query("
    SELECT COALESCE(SUM(due_amount), 0) AS total
    FROM sales
    WHERE due_amount > 0
");

if ($result) {
    $row = $result->fetch_assoc();
    $totalDue = (float) $row['total'];
}


/*
|--------------------------------------------------------------------------
| Search / Filters
|--------------------------------------------------------------------------
*/

$search = isset($_GET['search'])
    ? trim($_GET['search'])
    : '';

$paymentStatus = isset($_GET['payment_status'])
    ? trim($_GET['payment_status'])
    : '';

$fromDate = isset($_GET['from_date'])
    ? trim($_GET['from_date'])
    : '';

$toDate = isset($_GET['to_date'])
    ? trim($_GET['to_date'])
    : '';


/*
|--------------------------------------------------------------------------
| Sales Query
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        s.id,
        s.invoice_number,
        s.subtotal,
        s.discount,
        s.grand_total,
        s.paid_amount,
        s.due_amount,
        s.payment_status,
        s.sale_date,
        (SELECT sv.service_charge FROM invoices ii INNER JOIN services sv ON sv.id = ii.service_id WHERE ii.sale_id = s.id AND ii.service_id IS NOT NULL ORDER BY ii.id DESC LIMIT 1) AS service_charge,
        c.customer_name,
        c.mobile

    FROM sales s

    LEFT JOIN customers c
        ON s.customer_id = c.id

    WHERE 1 = 1
";


$params = [];
$types  = "";


/*
|--------------------------------------------------------------------------
| Search
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "
        AND (
            s.invoice_number LIKE ?
            OR c.customer_name LIKE ?
            OR c.mobile LIKE ?
        )
    ";

    $searchValue = "%" . $search . "%";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= "sss";
}


/*
|--------------------------------------------------------------------------
| Payment Status
|--------------------------------------------------------------------------
*/

if ($paymentStatus !== '') {

    $sql .= "
        AND s.payment_status = ?
    ";

    $params[] = $paymentStatus;

    $types .= "s";
}


/*
|--------------------------------------------------------------------------
| From Date
|--------------------------------------------------------------------------
*/

if ($fromDate !== '') {

    $sql .= "
        AND DATE(s.sale_date) >= ?
    ";

    $params[] = $fromDate;

    $types .= "s";
}


/*
|--------------------------------------------------------------------------
| To Date
|--------------------------------------------------------------------------
*/

if ($toDate !== '') {

    $sql .= "
        AND DATE(s.sale_date) <= ?
    ";

    $params[] = $toDate;

    $types .= "s";
}


/*
|--------------------------------------------------------------------------
| Latest Sale First
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY s.id ASC
";


/*
|--------------------------------------------------------------------------
| Execute Sales Query
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare($sql);

$sales = [];

if ($stmt) {

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $sales[] = $row;
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Payment Badge
|--------------------------------------------------------------------------
*/

function getPaymentBadgeClass($status)
{
    switch ($status) {

        case 'Paid':
            return 'payment-paid';

        case 'Partial':
            return 'payment-partial';

        case 'Due':
            return 'payment-due';

        default:
            return 'payment-default';
    }
}

?>


<div class="page-content">

    <?php if (isset($_GET['deleted'])): ?>

        <div class="success-message" style="margin-bottom: 20px;">
            <i class="fa fa-check-circle"></i>
            Sale record deleted successfully and stock has been restored.
        </div>

    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>

        <div class="error-message" style="margin-bottom: 20px;">
            <i class="fa fa-exclamation-circle"></i>
            <?= htmlspecialchars($_GET['error']) ?>
        </div>

    <?php endif; ?>


    <!-- =========================================================
         PAGE HEADER
         ========================================================= -->

    <div class="page-header">

        <div>

            <h1>Sales Management</h1>

            <p>
                Manage product and part sales.
            </p>

        </div>


        <div>

            <a
                href="add.php"
                class="btn btn-primary"
            >

                <span class="btn-icon">+</span>

                New Sale

            </a>

        </div>

    </div>



    <!-- =========================================================
         STATISTICS
         ========================================================= -->

    <div class="stats-grid">


        <!-- Total Sales -->

        <div class="stat-card">

            <div class="stat-content">

                <div class="stat-title">
                    Total Sales
                </div>

                <div class="stat-number">
                    <?= $totalSales ?>
                </div>

            </div>

            <div class="stat-icon blue">

                <svg
                    width="28"
                    height="28"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >

                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>

                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>

                </svg>

            </div>

        </div>



        <!-- Today's Sales -->

        <div class="stat-card">

            <div class="stat-content">

                <div class="stat-title">
                    Today's Sales
                </div>

                <div class="stat-number">
                    <?= $todaySales ?>
                </div>

            </div>

            <div class="stat-icon cyan">

                <svg
                    width="28"
                    height="28"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >

                    <rect x="3" y="4" width="18" height="18" rx="2"></rect>

                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>

                    <line x1="3" y1="10" x2="21" y2="10"></line>

                </svg>

            </div>

        </div>



        <!-- Total Revenue -->

        <div class="stat-card">

            <div class="stat-content">

                <div class="stat-title">
                    Total Revenue
                </div>

                <div class="stat-number money-number">
                    ৳<?= number_format($totalRevenue, 2) ?>
                </div>

            </div>

          <div class="stat-icon green">

                <svg
                    width="28"
                    height="28"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >

                     <text x="5" y="19" font-size="20" font-family="Arial">৳</text>

                </svg>

            </div>
  
        </div>



        <!-- Total Due -->

        <div class="stat-card">

            <div class="stat-content">

                <div class="stat-title">
                    Total Due
                </div>

                <div class="stat-number money-number">
                    ৳<?= number_format($totalDue, 2) ?>
                </div>

            </div>

            <div class="stat-icon yellow">

                <svg
                    width="28"
                    height="28"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                >

                    <rect x="2" y="5" width="20" height="14" rx="2"></rect>

                    <line x1="2" y1="10" x2="22" y2="10"></line>

                </svg>

            </div>

        </div>

    </div>



    <!-- =========================================================
         FILTER
         ========================================================= -->

    <div class="filter-card">

        <form
            method="GET"
            action="index.php"
        >

            <div class="filter-grid">


                <!-- Search -->

                <div class="filter-item search-item">

                    <label>
                        Search
                    </label>

                    <input
                        type="text"
                        name="search"
                        value="<?= htmlspecialchars($search) ?>"
                        placeholder="Invoice, customer name or mobile"
                    >

                </div>



                <!-- Payment Status -->

                <div class="filter-item">

                    <label>
                        Payment Status
                    </label>

                    <select name="payment_status">

                        <option value="">
                            All Status
                        </option>

                        <option
                            value="Paid"
                            <?= $paymentStatus === 'Paid' ? 'selected' : '' ?>
                        >
                            Paid
                        </option>

                        <option
                            value="Partial"
                            <?= $paymentStatus === 'Partial' ? 'selected' : '' ?>
                        >
                            Partial
                        </option>

                        <option
                            value="Due"
                            <?= $paymentStatus === 'Due' ? 'selected' : '' ?>
                        >
                            Due
                        </option>

                    </select>

                </div>



                <!-- From Date -->

                <div class="filter-item">

                    <label>
                        From Date
                    </label>

                    <input
                        type="date"
                        name="from_date"
                        value="<?= htmlspecialchars($fromDate) ?>"
                    >

                </div>



                <!-- To Date -->

                <div class="filter-item">

                    <label>
                        To Date
                    </label>

                    <input
                        type="date"
                        name="to_date"
                        value="<?= htmlspecialchars($toDate) ?>"
                    >

                </div>



                <!-- Buttons -->

                <div class="filter-buttons">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >

                        <svg
                            width="16"
                            height="16"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                        >

                            <circle cx="11" cy="11" r="7"></circle>

                            <line
                                x1="16.65"
                                y1="16.65"
                                x2="21"
                                y2="21"
                            ></line>

                        </svg>

                        Search

                    </button>


                    <a
                        href="index.php"
                        class="btn btn-secondary"
                    >
                        Reset
                    </a>

                </div>

            </div>

        </form>

    </div>



    <!-- =========================================================
         SALES TABLE
         ========================================================= -->

    <div class="content-card">


        <div class="card-header">

            <div>

                <h2>
                    Sales Records
                </h2>

                <p>
                    List of completed customer sales.
                </p>

            </div>

        </div>



        <div class="table-wrapper">

            <table class="data-table">


                <thead>

                    <tr>

                        <th>ID</th>

                        <th>Invoice</th>

                        <th>Customer</th>

                        <th>Mobile</th>

                        <th>Service Charge</th>

                        <th>Subtotal</th>

                        <th>Discount</th>

                        <th>Grand Total</th>

                        <th>Paid</th>

                        <th>Due</th>

                        <th>Status</th>

                        <th>Date</th>

                        <th class="action-heading">
                            Action
                        </th>

                    </tr>

                </thead>



                <tbody>


                <?php if (empty($sales)): ?>

                    <tr>

                        <td
                            colspan="12"
                            class="empty-state"
                        >
                            No sales records found.
                        </td>

                    </tr>


                <?php else: ?>


                    <?php foreach ($sales as $sale): ?>

                        <tr>


                            <!-- ID -->

                            <td>
                                #<?= (int)$sale['id'] ?>
                            </td>



                            <!-- Invoice -->

                            <td class="invoice-number">

                                <?= htmlspecialchars(
                                    $sale['invoice_number']
                                ) ?>

                            </td>



                            <!-- Customer -->

                            <td>

                                <?= htmlspecialchars(
                                    $sale['customer_name'] ?? 'Walk-in Customer'
                                ) ?>

                            </td>



                            <!-- Mobile -->

                            <td>

                                <?= htmlspecialchars(
                                    $sale['mobile'] ?? '-'
                                ) ?>

                            </td>



                            <!-- Service Charge -->

                            <td>
                                ৳<?= number_format((float)($sale['service_charge'] ?? 0), 2) ?>
                            </td>

                            <!-- Subtotal -->

                            <td>

                                ৳<?= number_format(
                                    (float)$sale['subtotal'],
                                    2
                                ) ?>

                            </td>



                            <!-- Discount -->

                            <td>

                                ৳<?= number_format(
                                    (float)$sale['discount'],
                                    2
                                ) ?>

                            </td>



                            <!-- Grand Total -->

                            <td class="grand-total">

                                ৳<?= number_format(
                                    (float)$sale['grand_total'],
                                    2
                                ) ?>

                            </td>



                            <!-- Paid -->

                            <td>

                                ৳<?= number_format(
                                    (float)$sale['paid_amount'],
                                    2
                                ) ?>

                            </td>



                            <!-- Due -->

                            <td>

                                ৳<?= number_format(
                                    (float)$sale['due_amount'],
                                    2
                                ) ?>

                            </td>



                            <!-- Status -->

                            <td>

                                <span
                                    class="payment-badge
                                    <?= getPaymentBadgeClass(
                                        $sale['payment_status']
                                    ) ?>"
                                >

                                    <?= htmlspecialchars(
                                        $sale['payment_status']
                                    ) ?>

                                </span>

                            </td>



                            <!-- Date -->

                            <td>

                                <?= date(
                                    'd M Y, h:i A',
                                    strtotime($sale['sale_date'])
                                ) ?>

                            </td>



                            <!-- =================================================
                                 ACTION
                                 ================================================= -->

                            <td class="action-cell">

                                <div class="action-buttons">


                                    <!-- VIEW SALE -->

                                    <a
                                        href="view.php?id=<?= (int)$sale['id'] ?>"
                                        class="action-btn view-btn"
                                        title="View Sale"
                                        aria-label="View Sale"
                                    >

                                        <!-- Eye Icon -->

                                        <svg
                                            width="18"
                                            height="18"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        >

                                            <path
                                                d="M2.062 12.348a1 1 0 0 1 0-.696
                                                10.75 10.75 0 0 1 19.876 0
                                                1 1 0 0 1 0 .696
                                                10.75 10.75 0 0 1-19.876 0Z"
                                            ></path>

                                            <circle
                                                cx="12"
                                                cy="12"
                                                r="3"
                                            ></circle>

                                        </svg>

                                    </a>



                                    <!-- EDIT SALE -->

                                    <a
                                        href="edit.php?id=<?= (int)$sale['id'] ?>"
                                        class="action-btn edit-btn"
                                        title="Edit Sale"
                                        aria-label="Edit Sale"
                                    >

                                        <!-- Pencil Icon -->

                                        <svg
                                            width="18"
                                            height="18"
                                            viewBox="0 0 24 24"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                        >

                                            <path
                                                d="M12 20h9"
                                            ></path>

                                            <path
                                                d="M16.5 3.5a2.121 2.121 0 0 1 3 3
                                                L7 19l-4 1 1-4Z"
                                            ></path>

                                        </svg>

                                    </a>


                                    <!-- DELETE SALE -->

                                    <form
                                        method="POST"
                                        action="delete.php"
                                        class="delete-sale-form"
                                        onsubmit="return confirm('Are you sure you want to delete this sale record? The sold stock will be restored. This action cannot be undone.');"
                                    >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)$sale['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="action-btn delete-btn"
                                            title="Delete Sale"
                                            aria-label="Delete Sale"
                                        >

                                            <svg
                                                width="18"
                                                height="18"
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                            >
                                                <path d="M3 6h18"></path>
                                                <path d="M8 6V4h8v2"></path>
                                                <path d="M19 6l-1 14H6L5 6"></path>
                                                <path d="M10 11v5"></path>
                                                <path d="M14 11v5"></path>
                                            </svg>

                                        </button>

                                    </form>


                                </div>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php endif; ?>


                </tbody>

            </table>

        </div>

    </div>


</div>



<style>

/* =========================================================
   SALES PAGE
   ========================================================= */

.page-content {
    margin-left: 223px;
    padding: 32px 35px;
    width: calc(100% - 223px);
    box-sizing: border-box;
}


/* =========================================================
   PAGE HEADER
   ========================================================= */

.page-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 28px;
}

.page-header h1 {
    margin: 0 0 6px;
    font-size: 30px;
    line-height: 1.2;
    font-weight: 700;
    color: #172033;
}

.page-header p {
    margin: 0;
    color: #526177;
    font-size: 14px;
}


/* =========================================================
   BUTTONS
   ========================================================= */

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
    line-height: 1;
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

.btn-secondary:hover {
    background: #56606b;
}

.btn-icon {
    font-size: 20px;
    font-weight: 400;
    line-height: 12px;
}


/* =========================================================
   STATISTICS
   ========================================================= */

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 20px;
    margin-bottom: 22px;
}

.stat-card {
    background: #ffffff;
    border-radius: 9px;
    padding: 18px 16px;
    min-height: 86px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
}

.stat-title {
    color: #536174;
    font-size: 14px;
    margin-bottom: 7px;
}

.stat-number {
    font-size: 25px;
    font-weight: 700;
    color: #111827;
}

.money-number {
    font-size: 21px;
}

.stat-icon {
    display: flex;
    align-items: center;
    justify-content: center;
}

.stat-icon.blue {
    color: #1769e0;
}

.stat-icon.yellow {
    color: #f5b400;
}

.stat-icon.cyan {
    color: #11bfe2;
}

.stat-icon.green {
    color: #008c62;
}


/* =========================================================
   FILTER
   ========================================================= */

.filter-card {
    background: #ffffff;
    border-radius: 9px;
    padding: 18px;
    margin-bottom: 22px;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
}

.filter-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr auto;
    gap: 14px;
    align-items: end;
}

.filter-item label {
    display: block;
    margin-bottom: 7px;
    color: #526177;
    font-size: 13px;
    font-weight: 600;
}

.filter-item input,
.filter-item select {
    width: 100%;
    height: 40px;
    padding: 8px 10px;
    border: 1px solid #d7dce2;
    border-radius: 6px;
    box-sizing: border-box;
    background: #ffffff;
}

.filter-buttons {
    display: flex;
    gap: 8px;
}


/* =========================================================
   SALES CARD
   ========================================================= */

.content-card {
    background: #ffffff;
    border-radius: 9px;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    overflow: hidden;
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
    font-size: 14px;
    color: #526177;
}


/* =========================================================
   TABLE
   ========================================================= */

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

.data-table {
    width: 100%;
    min-width: 1250px;
    border-collapse: collapse;
    font-size: 14px;
}

.data-table th {
    background: #f7f8fa;
    color: #172033;
    padding: 12px 14px;
    text-align: left;
    border-bottom: 1px solid #dfe3e8;
    white-space: nowrap;
}

.data-table td {
    padding: 13px 14px;
    border-bottom: 1px solid #e5e8ec;
    color: #273449;
    white-space: nowrap;
    vertical-align: middle;
}

.data-table tbody tr:hover {
    background: #fafbfc;
}


/* =========================================================
   INVOICE
   ========================================================= */

.invoice-number {
    font-weight: 600;
    color: #1769e0 !important;
}

.grand-total {
    font-weight: 700;
    color: #172033 !important;
}


/* =========================================================
   PAYMENT BADGES
   ========================================================= */

.payment-badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
}

.payment-paid {
    background: #d9f5e9;
    color: #08754f;
}

.payment-partial {
    background: #fff3cd;
    color: #856404;
}

.payment-due {
    background: #fde2e2;
    color: #b42318;
}

.payment-default {
    background: #e9ecef;
    color: #495057;
}


/* =========================================================
   ACTION COLUMN
   ========================================================= */

.action-heading {
    text-align: center !important;
}

.action-cell {
    text-align: center !important;
    vertical-align: middle !important;
}

.action-buttons {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}


/* =========================================================
   ACTION BUTTON
   ========================================================= */

.action-btn {
    width: 34px;
    height: 34px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    box-sizing: border-box;

    border-radius: 6px;

    text-decoration: none;

    background: #ffffff;

    transition:
        background-color 0.2s ease,
        color 0.2s ease,
        border-color 0.2s ease,
        transform 0.2s ease;

    cursor: pointer;
}

.action-btn svg {
    display: block;
    flex-shrink: 0;
}


/* =========================================================
   VIEW BUTTON
   Blue Eye Icon
   ========================================================= */

.view-btn {
    color: #1769e0 !important;
    border: 1px solid #1769e0;
}

.view-btn:hover {
    color: #ffffff !important;
    background: #1769e0;
    border-color: #1769e0;
    transform: translateY(-1px);
}


/* =========================================================
   EDIT BUTTON
   Orange Pencil Icon
   ========================================================= */

.edit-btn {
    color: #e39400 !important;
    border: 1px solid #f0a400;
}

.edit-btn:hover {
    color: #ffffff !important;
    background: #f0a400;
    border-color: #f0a400;
    transform: translateY(-1px);
}


/* =========================================================
   EMPTY STATE
   ========================================================= */

.empty-state {
    text-align: center !important;
    padding: 35px !important;
    color: #697586 !important;
}


/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 1200px) {

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .filter-grid {
        grid-template-columns: 1fr 1fr;
    }

    .search-item {
        grid-column: 1 / -1;
    }

    .filter-buttons {
        grid-column: 1 / -1;
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

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .filter-grid {
        grid-template-columns: 1fr;
    }

    .search-item {
        grid-column: auto;
    }

    .filter-buttons {
        grid-column: auto;
    }

}


.delete-sale-form {
    margin: 0;
    padding: 0;
    display: inline-flex;
}

.delete-btn {
    color: #dc3545 !important;
    border: 1px solid #dc3545;
}

.delete-btn:hover {
    color: #ffffff !important;
    background: #dc3545;
    border-color: #dc3545;
    transform: translateY(-1px);
}


.error-message {
    background: #fde2e2;
    color: #b42318;
    border: 1px solid #f5b5b5;
    border-radius: 7px;
    padding: 12px 15px;
    font-size: 14px;
}

</style>