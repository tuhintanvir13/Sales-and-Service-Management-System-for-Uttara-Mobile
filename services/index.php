<?php

require_once __DIR__ . '/../config/database.php';

$basePath = "../";

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
require_once __DIR__ . '/../includes/sidebar.php';


/*
|--------------------------------------------------------------------------
| SVG ICON HELPER
|--------------------------------------------------------------------------
| Uses inline SVG instead of Font Awesome so icons always display.
|--------------------------------------------------------------------------
*/

function serviceIcon($name, $size = 18)
{
    $size = (int)$size;

    $icons = [

        'plus-circle' => '
            <svg xmlns="http://www.w3.org/2000/svg"
                 width="' . $size . '"
                 height="' . $size . '"
                 viewBox="0 0 24 24"
                 fill="none"
                 stroke="currentColor"
                 stroke-width="2"
                 stroke-linecap="round"
                 stroke-linejoin="round"
                 aria-hidden="true">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="8" x2="12" y2="16"></line>
                <line x1="8" y1="12" x2="16" y2="12"></line>
            </svg>
        ',

        'tools' => '
            <svg xmlns="http://www.w3.org/2000/svg"
                 width="' . $size . '"
                 height="' . $size . '"
                 viewBox="0 0 24 24"
                 fill="none"
                 stroke="currentColor"
                 stroke-width="2"
                 stroke-linecap="round"
                 stroke-linejoin="round"
                 aria-hidden="true">
                <path d="M14.7 6.3a4 4 0 0 0-5.4-5.4l3.1 3.1-3.2 3.2-3.1-3.1a4 4 0 0 0 5.4 5.4l7.4 7.4a2 2 0 1 0 2.8-2.8z"></path>
                <path d="M17 14l-7 7"></path>
                <path d="M5 5l4 4"></path>
            </svg>
        ',

        'hourglass' => '
            <svg xmlns="http://www.w3.org/2000/svg"
                 width="' . $size . '"
                 height="' . $size . '"
                 viewBox="0 0 24 24"
                 fill="none"
                 stroke="currentColor"
                 stroke-width="2"
                 stroke-linecap="round"
                 stroke-linejoin="round"
                 aria-hidden="true">
                <path d="M5 4h14"></path>
                <path d="M5 20h14"></path>
                <path d="M7 4c0 4 2 5 5 8-3 3-5 4-5 8"></path>
                <path d="M17 4c0 4-2 5-5 8 3 3 5 4 5 8"></path>
            </svg>
        ',

        'wrench' => '
            <svg xmlns="http://www.w3.org/2000/svg"
                 width="' . $size . '"
                 height="' . $size . '"
                 viewBox="0 0 24 24"
                 fill="none"
                 stroke="currentColor"
                 stroke-width="2"
                 stroke-linecap="round"
                 stroke-linejoin="round"
                 aria-hidden="true">
                <path d="M14.7 6.3a4 4 0 0 0-5.4-5.4l3.1 3.1-3.2 3.2-3.1-3.1a4 4 0 0 0 5.4 5.4l7.4 7.4a2 2 0 1 0 2.8-2.8z"></path>
            </svg>
        ',

        'check-circle' => '
            <svg xmlns="http://www.w3.org/2000/svg"
                 width="' . $size . '"
                 height="' . $size . '"
                 viewBox="0 0 24 24"
                 fill="none"
                 stroke="currentColor"
                 stroke-width="2"
                 stroke-linecap="round"
                 stroke-linejoin="round"
                 aria-hidden="true">
                <circle cx="12" cy="12" r="10"></circle>
                <polyline points="8 12 11 15 16 9"></polyline>
            </svg>
        ',

        'search' => '
            <svg xmlns="http://www.w3.org/2000/svg"
                 width="' . $size . '"
                 height="' . $size . '"
                 viewBox="0 0 24 24"
                 fill="none"
                 stroke="currentColor"
                 stroke-width="2"
                 stroke-linecap="round"
                 stroke-linejoin="round"
                 aria-hidden="true">
                <circle cx="11" cy="11" r="7"></circle>
                <line x1="16.5" y1="16.5" x2="21" y2="21"></line>
            </svg>
        ',

        'eye' => '
            <svg xmlns="http://www.w3.org/2000/svg"
                 width="' . $size . '"
                 height="' . $size . '"
                 viewBox="0 0 24 24"
                 fill="none"
                 stroke="currentColor"
                 stroke-width="2"
                 stroke-linecap="round"
                 stroke-linejoin="round"
                 aria-hidden="true">
                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"></path>
                <circle cx="12" cy="12" r="3"></circle>
            </svg>
        ',

        'edit' => '
            <svg xmlns="http://www.w3.org/2000/svg"
                 width="' . $size . '"
                 height="' . $size . '"
                 viewBox="0 0 24 24"
                 fill="none"
                 stroke="currentColor"
                 stroke-width="2"
                 stroke-linecap="round"
                 stroke-linejoin="round"
                 aria-hidden="true">
                <path d="M12 20h9"></path>
                <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"></path>
            </svg>
        '
    ];

    return $icons[$name] ?? '';
}


/*
|--------------------------------------------------------------------------
| SERVICE STATISTICS
|--------------------------------------------------------------------------
*/

$totalServices = 0;
$pendingServices = 0;
$inRepairServices = 0;
$completedServices = 0;


/*
|--------------------------------------------------------------------------
| Total Services
|--------------------------------------------------------------------------
*/

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM services
");

if ($result) {

    $row = $result->fetch_assoc();

    $totalServices = (int)$row['total'];
}


/*
|--------------------------------------------------------------------------
| Pending Services
|--------------------------------------------------------------------------
*/

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM services
    WHERE service_status = 'Pending'
");

if ($result) {

    $row = $result->fetch_assoc();

    $pendingServices = (int)$row['total'];
}


/*
|--------------------------------------------------------------------------
| In Repair Services
|--------------------------------------------------------------------------
*/

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM services
    WHERE service_status = 'In Repair'
");

if ($result) {

    $row = $result->fetch_assoc();

    $inRepairServices = (int)$row['total'];
}


/*
|--------------------------------------------------------------------------
| Completed Services
|--------------------------------------------------------------------------
*/

$result = $conn->query("
    SELECT COUNT(*) AS total
    FROM services
    WHERE service_status = 'Completed'
");

if ($result) {

    $row = $result->fetch_assoc();

    $completedServices = (int)$row['total'];
}


/*
|--------------------------------------------------------------------------
| SEARCH / FILTER VALUES
|--------------------------------------------------------------------------
*/

$search = isset($_GET['search'])
    ? trim($_GET['search'])
    : '';

$status = isset($_GET['status'])
    ? trim($_GET['status'])
    : '';

$fromDate = isset($_GET['from_date'])
    ? trim($_GET['from_date'])
    : '';

$toDate = isset($_GET['to_date'])
    ? trim($_GET['to_date'])
    : '';


/*
|--------------------------------------------------------------------------
| SERVICE ORDERS QUERY
|--------------------------------------------------------------------------
|
| IMPORTANT:
| customers table contains "mobile", NOT "mobile_number".
|
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        s.id,
        c.customer_name,
        c.mobile,
        s.device_brand,
        s.device_model,
        s.imei_serial,
        s.service_charge,
        s.service_status,
        s.received_date,
        s.expected_delivery_date

    FROM services s

    LEFT JOIN customers c
        ON s.customer_id = c.id

    WHERE 1=1
";


$params = [];
$types = "";


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
*/

if ($search !== '') {

    $sql .= "
        AND (
            c.customer_name LIKE ?
            OR c.mobile LIKE ?
            OR s.device_brand LIKE ?
            OR s.device_model LIKE ?
            OR s.imei_serial LIKE ?
        )
    ";

    $searchValue = "%" . $search . "%";

    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;
    $params[] = $searchValue;

    $types .= "sssss";
}


/*
|--------------------------------------------------------------------------
| STATUS FILTER
|--------------------------------------------------------------------------
*/

if ($status !== '') {

    $sql .= "
        AND s.service_status = ?
    ";

    $params[] = $status;

    $types .= "s";
}


/*
|--------------------------------------------------------------------------
| FROM DATE
|--------------------------------------------------------------------------
*/

if ($fromDate !== '') {

    $sql .= "
        AND DATE(s.received_date) >= ?
    ";

    $params[] = $fromDate;

    $types .= "s";
}


/*
|--------------------------------------------------------------------------
| TO DATE
|--------------------------------------------------------------------------
*/

if ($toDate !== '') {

    $sql .= "
        AND DATE(s.received_date) <= ?
    ";

    $params[] = $toDate;

    $types .= "s";
}


/*
|--------------------------------------------------------------------------
| SERVICE ORDERS - ASCENDING ORDER
|--------------------------------------------------------------------------
| The database primary key (s.id) is kept untouched so existing
| relationships remain safe. The visible Service Order number is
| generated sequentially from the ordered result set.
|--------------------------------------------------------------------------
*/

$sql .= "
    ORDER BY s.id ASC
";


/*
|--------------------------------------------------------------------------
| PREPARE QUERY
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare($sql);

$services = [];


if ($stmt) {

    if (!empty($params)) {

        $stmt->bind_param(
            $types,
            ...$params
        );
    }

    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {

        $services[] = $row;
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| STATUS BADGE CLASS
|--------------------------------------------------------------------------
*/

function getStatusBadgeClass($status)
{
    switch ($status) {

        case 'Pending':
            return 'status-pending';

        case 'Under Inspection':
            return 'status-inspection';

        case 'Waiting for Parts':
            return 'status-waiting';

        case 'In Repair':
            return 'status-repair';

        case 'Completed':
            return 'status-completed';

        case 'Delivered':
            return 'status-delivered';

        case 'Cancelled':
            return 'status-cancelled';

        default:
            return 'status-default';
    }
}

?>


<div class="page-content">


    <!-- =====================================================
         PAGE HEADER
         ===================================================== -->

    <div class="page-header">

        <div>

            <h1>
                Service Management
            </h1>

            <p>
                Manage mobile servicing and repair orders.
            </p>

        </div>


        <div>

            <a
                href="add.php"
                class="btn btn-primary"
            >

                <?= serviceIcon('plus-circle', 17) ?>

                New Service

            </a>

        </div>

    </div>


    <!-- =====================================================
         STATISTICS
         ===================================================== -->

    <div class="stats-grid">


        <!-- Total Services -->

        <div class="stat-card">

            <div class="stat-content">

                <div class="stat-title">
                    Total Services
                </div>

                <div class="stat-number">
                    <?= $totalServices ?>
                </div>

            </div>


            <div class="stat-icon blue">

                <?= serviceIcon('tools', 32) ?>

            </div>

        </div>


        <!-- Pending -->

        <div class="stat-card">

            <div class="stat-content">

                <div class="stat-title">
                    Pending
                </div>

                <div class="stat-number">
                    <?= $pendingServices ?>
                </div>

            </div>


            <div class="stat-icon yellow">

                <?= serviceIcon('hourglass', 32) ?>

            </div>

        </div>


        <!-- In Repair -->

        <div class="stat-card">

            <div class="stat-content">

                <div class="stat-title">
                    In Repair
                </div>

                <div class="stat-number">
                    <?= $inRepairServices ?>
                </div>

            </div>


            <div class="stat-icon cyan">

                <?= serviceIcon('wrench', 32) ?>

            </div>

        </div>


        <!-- Completed -->

        <div class="stat-card">

            <div class="stat-content">

                <div class="stat-title">
                    Completed
                </div>

                <div class="stat-number">
                    <?= $completedServices ?>
                </div>

            </div>


            <div class="stat-icon green">

                <?= serviceIcon('check-circle', 32) ?>

            </div>

        </div>


    </div>


    <!-- =====================================================
         SEARCH / FILTER
         ===================================================== -->

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
                        placeholder="Customer, mobile, device or IMEI..."
                    >

                </div>


                <!-- Status -->

                <div class="filter-item">

                    <label>
                        Status
                    </label>


                    <select name="status">

                        <option value="">
                            All
                        </option>


                        <option
                            value="Pending"
                            <?= $status === 'Pending' ? 'selected' : '' ?>
                        >
                            Pending
                        </option>


                        <option
                            value="Under Inspection"
                            <?= $status === 'Under Inspection' ? 'selected' : '' ?>
                        >
                            Under Inspection
                        </option>


                        <option
                            value="Waiting for Parts"
                            <?= $status === 'Waiting for Parts' ? 'selected' : '' ?>
                        >
                            Waiting for Parts
                        </option>


                        <option
                            value="In Repair"
                            <?= $status === 'In Repair' ? 'selected' : '' ?>
                        >
                            In Repair
                        </option>


                        <option
                            value="Completed"
                            <?= $status === 'Completed' ? 'selected' : '' ?>
                        >
                            Completed
                        </option>


                        <option
                            value="Delivered"
                            <?= $status === 'Delivered' ? 'selected' : '' ?>
                        >
                            Delivered
                        </option>


                        <option
                            value="Cancelled"
                            <?= $status === 'Cancelled' ? 'selected' : '' ?>
                        >
                            Cancelled
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

                        <?= serviceIcon('search', 16) ?>

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


    <!-- =====================================================
         SERVICE ORDERS
         ===================================================== -->

    <div class="content-card">


        <div class="card-header">

            <div>

                <h2>
                    Service Orders
                </h2>

                <p>
                    List of customer service and repair orders.
                </p>

            </div>

        </div>


        <div class="table-wrapper">


            <table class="data-table">


                <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            Customer
                        </th>

                        <th>
                            Mobile
                        </th>

                        <th>
                            Device
                        </th>

                        <th>
                            IMEI / Serial
                        </th>

                        <th>
                            Charge
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Received
                        </th>

                        <th>
                            Expected
                        </th>

                        <th class="actions-column">
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php if (empty($services)): ?>


                    <tr>

                        <td
                            colspan="10"
                            class="empty-state"
                        >

                            No service orders found.

                        </td>

                    </tr>


                <?php else: ?>


                    <?php foreach ($services as $serviceIndex => $service): ?>


                        <tr>


                            <!-- ID -->

                            <td>

                                #<?= (int)($serviceIndex + 1) ?>

                            </td>


                            <!-- Customer -->

                            <td>

                                <?= htmlspecialchars(
                                    $service['customer_name'] ?? 'N/A'
                                ) ?>

                            </td>


                            <!-- Mobile -->

                            <td>

                                <?= htmlspecialchars(
                                    $service['mobile'] ?? 'N/A'
                                ) ?>

                            </td>


                            <!-- Device -->

                            <td>

                                <div class="device-brand">

                                    <?= htmlspecialchars(
                                        $service['device_brand'] ?? ''
                                    ) ?>

                                </div>


                                <div class="device-model">

                                    <?= htmlspecialchars(
                                        $service['device_model'] ?? ''
                                    ) ?>

                                </div>

                            </td>


                            <!-- IMEI -->

                            <td>

                                <?= htmlspecialchars(
                                    $service['imei_serial'] ?? ''
                                ) ?>

                            </td>


                            <!-- Charge -->

                            <td>

                                ৳<?= number_format(
                                    (float)$service['service_charge'],
                                    2
                                ) ?>

                            </td>


                            <!-- Status -->

                            <td>

                                <span
                                    class="status-badge <?= getStatusBadgeClass(
                                        $service['service_status']
                                    ) ?>"
                                >

                                    <?= htmlspecialchars(
                                        $service['service_status']
                                    ) ?>

                                </span>

                            </td>


                            <!-- Received -->

                            <td>

                                <?= !empty(
                                    $service['received_date']
                                )
                                    ? date(
                                        'd M Y',
                                        strtotime(
                                            $service['received_date']
                                        )
                                    )
                                    : '-'
                                ?>

                            </td>


                            <!-- Expected -->

                            <td>

                                <?= !empty(
                                    $service['expected_delivery_date']
                                )
                                    ? date(
                                        'd M Y',
                                        strtotime(
                                            $service['expected_delivery_date']
                                        )
                                    )
                                    : '-'
                                ?>

                            </td>


                            <!-- =================================================
                                 ACTIONS
                                 ================================================= -->

                            <td class="actions-cell">


                                <div class="action-buttons">


                                    <!-- VIEW -->

                                    <a
                                        href="view.php?id=<?= (int)$service['id'] ?>"
                                        class="action-btn view-btn"
                                        title="View Service"
                                        aria-label="View Service"
                                    >

                                        <?= serviceIcon('eye', 17) ?>

                                    </a>


                                    <!-- EDIT -->

                                    <a
                                        href="edit.php?id=<?= (int)$service['id'] ?>"
                                        class="action-btn edit-btn"
                                        title="Edit Service"
                                        aria-label="Edit Service"
                                    >

                                        <?= serviceIcon('edit', 17) ?>

                                    </a>


                                    <!-- DELETE -->

                                    <form
                                        method="POST"
                                        action="delete.php"
                                        class="d-inline"
                                        onsubmit="return confirm('Are you sure you want to delete Service #<?= (int)($serviceIndex + 1) ?>? This action cannot be undone.');"
                                    >
                                        <input type="hidden" name="id" value="<?= (int)$service['id'] ?>">
                                        <button
                                            type="submit"
                                            class="action-btn delete-btn"
                                            title="Delete Service"
                                            aria-label="Delete Service"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                <polyline points="3 6 5 6 21 6"></polyline>
                                                <path d="M19 6l-1 14H6L5 6"></path>
                                                <path d="M10 11v6"></path>
                                                <path d="M14 11v6"></path>
                                                <path d="M9 6V4h6v2"></path>
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
   SERVICES PAGE
   Keep original Uttara Mobile layout
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

    box-sizing: border-box;
}


.btn svg {

    flex-shrink: 0;

    display: block;
}


.btn-primary {

    background: #1769e0;

    color: #ffffff !important;
}


.btn-primary:hover {

    background: #0d5ccc;

    color: #ffffff !important;
}


.btn-secondary {

    background: #68727e;

    color: #ffffff !important;
}


.btn-secondary:hover {

    background: #56606b;

    color: #ffffff !important;
}


/* =========================================================
   STATISTICS
   ========================================================= */

.stats-grid {

    display: grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

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

    box-shadow:
        0 2px 8px rgba(0, 0, 0, 0.06);

    box-sizing: border-box;
}


.stat-content {

    min-width: 0;
}


.stat-title {

    color: #536174;

    font-size: 14px;

    margin-bottom: 7px;
}


.stat-number {

    font-size: 25px;

    line-height: 1;

    font-weight: 700;

    color: #111827;
}


.stat-icon {

    display: flex;

    align-items: center;

    justify-content: center;

    flex-shrink: 0;

    margin-left: 15px;
}


.stat-icon svg {

    display: block;
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
   FILTER SECTION
   ========================================================= */

.filter-card {

    background: #ffffff;

    border-radius: 9px;

    padding: 18px 16px;

    margin-bottom: 22px;

    box-shadow:
        0 2px 8px rgba(0, 0, 0, 0.06);

    box-sizing: border-box;
}


.filter-grid {

    display: grid;

    grid-template-columns:
        minmax(260px, 2fr)
        minmax(150px, 1fr)
        minmax(150px, 1fr)
        minmax(150px, 1fr)
        auto;

    gap: 14px;

    align-items: end;
}


.filter-item {

    min-width: 0;
}


.filter-item label {

    display: block;

    margin-bottom: 7px;

    font-size: 13px;

    font-weight: 500;

    color: #26364b;
}


.filter-item input,
.filter-item select {

    width: 100%;

    height: 36px;

    padding: 7px 11px;

    border: 1px solid #d9dfe7;

    border-radius: 5px;

    box-sizing: border-box;

    font-size: 14px;

    color: #26364b;

    background: #ffffff;
}


.filter-item input::placeholder {

    color: #657388;

    opacity: 1;
}


.filter-item input:focus,
.filter-item select:focus {

    outline: none;

    border-color: #1769e0;

    box-shadow:
        0 0 0 2px rgba(23, 105, 224, 0.10);
}


.filter-buttons {

    display: flex;

    gap: 8px;

    align-items: center;

    white-space: nowrap;
}


/* =========================================================
   SERVICE ORDERS CARD
   ========================================================= */

.content-card {

    background: #ffffff;

    border-radius: 9px;

    box-shadow:
        0 2px 8px rgba(0, 0, 0, 0.06);

    overflow: hidden;

    width: 100%;

    box-sizing: border-box;
}


.card-header {

    padding: 16px 16px 13px;

    border-bottom: 1px solid #e4e7eb;
}


.card-header h2 {

    margin: 0 0 5px;

    font-size: 18px;

    line-height: 1.3;

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

    min-width: 1050px;

    border-collapse: collapse;

    table-layout: auto;

    font-size: 14px;
}


.data-table thead th {

    background: #f7f8fa;

    color: #172033;

    font-weight: 600;

    text-align: left;

    padding: 12px 14px;

    border-bottom: 1px solid #dfe3e8;

    white-space: nowrap;
}


.data-table tbody td {

    padding: 13px 14px;

    border-bottom: 1px solid #e5e8ec;

    color: #273449;

    vertical-align: middle;

    white-space: nowrap;
}


.data-table tbody tr:last-child td {

    border-bottom: none;
}


.data-table tbody tr:hover {

    background: #fafbfc;
}


/* =========================================================
   CUSTOMER / DEVICE TEXT
   ========================================================= */

.data-table td:nth-child(1) {

    font-weight: 600;

    color: #273449;
}


.data-table td:nth-child(2) {

    font-weight: 500;

    color: #172033;
}


.data-table td:nth-child(3) {

    color: #35445a;
}


.device-brand {

    font-weight: 600;

    color: #172033;

    line-height: 1.3;
}


.device-model {

    margin-top: 3px;

    color: #657388;

    font-size: 13px;

    line-height: 1.3;
}


.data-table td:nth-child(5) {

    color: #35445a;

    font-family: Arial, sans-serif;
}


.data-table td:nth-child(6) {

    font-weight: 500;

    color: #172033;
}


/* =========================================================
   STATUS BADGES
   ========================================================= */

.status-badge {

    display: inline-block;

    padding: 5px 9px;

    border-radius: 6px;

    font-size: 12px;

    line-height: 1.2;

    font-weight: 600;

    white-space: nowrap;
}


.status-pending {

    background: #fff3cd;

    color: #856404;
}


.status-inspection {

    background: #e8f1ff;

    color: #1559b7;
}


.status-waiting {

    background: #fff0b8;

    color: #735400;
}


.status-repair {

    background: #dbeaff;

    color: #075cc5;
}


.status-completed {

    background: #d9f5e9;

    color: #08754f;
}


.status-delivered {

    background: #e3e7eb;

    color: #46515e;
}


.status-cancelled {

    background: #fde2e2;

    color: #b42318;
}


.status-default {

    background: #e9ecef;

    color: #495057;
}


/* =========================================================
   ACTION BUTTONS
   ========================================================= */

.actions-column {

    text-align: center !important;
}


.actions-cell {

    text-align: center;

    width: 100px;
}


.action-buttons {

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 8px;
}


.action-btn {

    width: 34px;

    height: 34px;

    display: inline-flex;

    align-items: center;

    justify-content: center;

    border-radius: 6px;

    text-decoration: none;

    background: #ffffff;

    box-sizing: border-box;

    transition:
        background-color 0.15s ease,
        color 0.15s ease,
        border-color 0.15s ease,
        transform 0.15s ease;
}


.action-btn svg {

    display: block;

    flex-shrink: 0;
}


.action-btn:hover {

    transform: translateY(-1px);
}


/* VIEW BUTTON */

.view-btn {

    border: 1px solid #1769e0;

    color: #1769e0 !important;
}


.view-btn:hover {

    background: #1769e0;

    color: #ffffff !important;
}


/* EDIT BUTTON */

.edit-btn {

    border: 1px solid #f0a400;

    color: #e39400 !important;
}


.edit-btn:hover {

    background: #f0a400;

    color: #ffffff !important;
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

    .page-content {

        padding: 28px 25px;
    }


    .stats-grid {

        grid-template-columns:
            repeat(2, minmax(0, 1fr));
    }


    .filter-grid {

        grid-template-columns:
            1fr 1fr;
    }


    .search-item {

        grid-column: 1 / -1;
    }


    .filter-buttons {

        grid-column: 1 / -1;

        justify-content: flex-start;
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

</style>