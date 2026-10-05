<?php

session_start();

require_once "../config/database.php";

/* =========================================================
   ADMIN LOGIN CHECK
========================================================= */

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

/* =========================================================
   HELPER FUNCTIONS
========================================================= */

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function redirectWithMessage($message, $type = "success")
{
    $url = "assets.php?message=" . urlencode($message)
         . "&message_type=" . urlencode($type);

    header("Location: " . $url);
    exit;
}

$allowedStatuses = [
    "Available",
    "Assigned",
    "Maintenance",
    "Retired"
];

$allowedAssetTypes = [
    "Laptop",
    "Desktop",
    "Monitor",
    "Printer",
    "Network Device",
    "Server",
    "Keyboard",
    "Mouse",
    "Other"
];

/* =========================================================
   MESSAGE FROM REDIRECT
========================================================= */

$message = $_GET["message"] ?? "";
$messageType = $_GET["message_type"] ?? "";

if (!in_array($messageType, ["success", "error"], true)) {
    $messageType = "";
}

/* =========================================================
   ADD ASSET
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_asset"])) {

    $asset_code = trim($_POST["asset_code"] ?? "");
    $asset_name = trim($_POST["asset_name"] ?? "");
    $asset_type = trim($_POST["asset_type"] ?? "");
    $brand = trim($_POST["brand"] ?? "");
    $model = trim($_POST["model"] ?? "");
    $serial_number = trim($_POST["serial_number"] ?? "");

    $assigned_to = !empty($_POST["assigned_to"])
        ? (int)$_POST["assigned_to"]
        : null;

    $status = $_POST["status"] ?? "Available";

    $purchase_date = !empty($_POST["purchase_date"])
        ? $_POST["purchase_date"]
        : null;


    /* -----------------------------------------------------
       REQUIRED FIELD VALIDATION
    ----------------------------------------------------- */

    if (
        $asset_code === "" ||
        $asset_name === "" ||
        $asset_type === ""
    ) {

        redirectWithMessage(
            "Asset code, asset name and asset type are required.",
            "error"
        );
    }


    /* -----------------------------------------------------
       ASSET TYPE VALIDATION
    ----------------------------------------------------- */

    if (!in_array($asset_type, $allowedAssetTypes, true)) {

        redirectWithMessage(
            "Invalid asset type selected.",
            "error"
        );
    }


    /* -----------------------------------------------------
       STATUS VALIDATION
    ----------------------------------------------------- */

    if (!in_array($status, $allowedStatuses, true)) {

        redirectWithMessage(
            "Invalid asset status selected.",
            "error"
        );
    }


    /* -----------------------------------------------------
       ASSIGNMENT / STATUS CONSISTENCY
    ----------------------------------------------------- */

    if ($assigned_to !== null) {

        if ($status === "Available") {
            $status = "Assigned";
        }

    } else {

        if ($status === "Assigned") {
            $status = "Available";
        }
    }


    /* -----------------------------------------------------
       CHECK EMPLOYEE
    ----------------------------------------------------- */

    if ($assigned_to !== null) {

        $checkEmployee = $conn->prepare(
            "SELECT employee_id
             FROM employees
             WHERE employee_id = ?"
        );

        $checkEmployee->bind_param(
            "i",
            $assigned_to
        );

        $checkEmployee->execute();

        $employeeExists =
            $checkEmployee->get_result()->fetch_assoc();

        $checkEmployee->close();

        if (!$employeeExists) {

            redirectWithMessage(
                "Selected employee does not exist.",
                "error"
            );
        }
    }


    /* -----------------------------------------------------
       CHECK DUPLICATE ASSET CODE
    ----------------------------------------------------- */

    $check = $conn->prepare(
        "SELECT asset_id
         FROM assets
         WHERE asset_code = ?"
    );

    $check->bind_param(
        "s",
        $asset_code
    );

    $check->execute();

    $duplicate =
        $check->get_result()->fetch_assoc();

    $check->close();

    if ($duplicate) {

        redirectWithMessage(
            "Asset code already exists.",
            "error"
        );
    }


    /* -----------------------------------------------------
       CHECK DUPLICATE SERIAL NUMBER
    ----------------------------------------------------- */

    if ($serial_number !== "") {

        $check = $conn->prepare(
            "SELECT asset_id
             FROM assets
             WHERE serial_number = ?"
        );

        $check->bind_param(
            "s",
            $serial_number
        );

        $check->execute();

        $duplicateSerial =
            $check->get_result()->fetch_assoc();

        $check->close();

        if ($duplicateSerial) {

            redirectWithMessage(
                "Serial number already exists.",
                "error"
            );
        }
    }


    /* -----------------------------------------------------
       INSERT ASSET
    ----------------------------------------------------- */

    $stmt = $conn->prepare(
        "INSERT INTO assets
        (
            asset_code,
            asset_name,
            asset_type,
            brand,
            model,
            serial_number,
            assigned_to,
            status,
            purchase_date
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    $stmt->bind_param(
        "ssssssiss",
        $asset_code,
        $asset_name,
        $asset_type,
        $brand,
        $model,
        $serial_number,
        $assigned_to,
        $status,
        $purchase_date
    );

    if ($stmt->execute()) {

        $stmt->close();

        redirectWithMessage(
            "Asset added successfully.",
            "success"
        );

    } else {

        $stmt->close();

        redirectWithMessage(
            "Unable to add asset.",
            "error"
        );
    }
}


/* =========================================================
   EDIT ASSET
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["edit_asset"])) {

    $asset_id = (int)($_POST["asset_id"] ?? 0);

    $asset_code = trim($_POST["asset_code"] ?? "");
    $asset_name = trim($_POST["asset_name"] ?? "");
    $asset_type = trim($_POST["asset_type"] ?? "");
    $brand = trim($_POST["brand"] ?? "");
    $model = trim($_POST["model"] ?? "");
    $serial_number = trim($_POST["serial_number"] ?? "");

    $assigned_to = !empty($_POST["assigned_to"])
        ? (int)$_POST["assigned_to"]
        : null;

    $status = $_POST["status"] ?? "Available";

    $purchase_date = !empty($_POST["purchase_date"])
        ? $_POST["purchase_date"]
        : null;


    /* -----------------------------------------------------
       REQUIRED FIELD VALIDATION
    ----------------------------------------------------- */

    if (
        $asset_id <= 0 ||
        $asset_code === "" ||
        $asset_name === "" ||
        $asset_type === ""
    ) {

        redirectWithMessage(
            "Please fill all required asset fields.",
            "error"
        );
    }


    /* -----------------------------------------------------
       ASSET TYPE VALIDATION
    ----------------------------------------------------- */

    if (!in_array($asset_type, $allowedAssetTypes, true)) {

        redirectWithMessage(
            "Invalid asset type selected.",
            "error"
        );
    }


    /* -----------------------------------------------------
       STATUS VALIDATION
    ----------------------------------------------------- */

    if (!in_array($status, $allowedStatuses, true)) {

        redirectWithMessage(
            "Invalid asset status selected.",
            "error"
        );
    }


    /* -----------------------------------------------------
       ASSIGNMENT / STATUS CONSISTENCY
    ----------------------------------------------------- */

    if ($assigned_to !== null) {

        if ($status === "Available") {
            $status = "Assigned";
        }

    } else {

        if ($status === "Assigned") {
            $status = "Available";
        }
    }


    /* -----------------------------------------------------
       CHECK ASSET EXISTS
    ----------------------------------------------------- */

    $checkAsset = $conn->prepare(
        "SELECT asset_id
         FROM assets
         WHERE asset_id = ?"
    );

    $checkAsset->bind_param(
        "i",
        $asset_id
    );

    $checkAsset->execute();

    $assetExists =
        $checkAsset->get_result()->fetch_assoc();

    $checkAsset->close();

    if (!$assetExists) {

        redirectWithMessage(
            "Asset does not exist.",
            "error"
        );
    }


    /* -----------------------------------------------------
       CHECK EMPLOYEE
    ----------------------------------------------------- */

    if ($assigned_to !== null) {

        $checkEmployee = $conn->prepare(
            "SELECT employee_id
             FROM employees
             WHERE employee_id = ?"
        );

        $checkEmployee->bind_param(
            "i",
            $assigned_to
        );

        $checkEmployee->execute();

        $employeeExists =
            $checkEmployee->get_result()->fetch_assoc();

        $checkEmployee->close();

        if (!$employeeExists) {

            redirectWithMessage(
                "Selected employee does not exist.",
                "error"
            );
        }
    }


    /* -----------------------------------------------------
       CHECK DUPLICATE ASSET CODE
       EXCLUDE CURRENT ASSET
    ----------------------------------------------------- */

    $check = $conn->prepare(
        "SELECT asset_id
         FROM assets
         WHERE asset_code = ?
         AND asset_id != ?"
    );

    $check->bind_param(
        "si",
        $asset_code,
        $asset_id
    );

    $check->execute();

    $duplicate =
        $check->get_result()->fetch_assoc();

    $check->close();

    if ($duplicate) {

        redirectWithMessage(
            "Asset code already exists for another asset.",
            "error"
        );
    }


    /* -----------------------------------------------------
       CHECK DUPLICATE SERIAL NUMBER
       EXCLUDE CURRENT ASSET
    ----------------------------------------------------- */

    if ($serial_number !== "") {

        $check = $conn->prepare(
            "SELECT asset_id
             FROM assets
             WHERE serial_number = ?
             AND asset_id != ?"
        );

        $check->bind_param(
            "si",
            $serial_number,
            $asset_id
        );

        $check->execute();

        $duplicateSerial =
            $check->get_result()->fetch_assoc();

        $check->close();

        if ($duplicateSerial) {

            redirectWithMessage(
                "Serial number already exists for another asset.",
                "error"
            );
        }
    }


    /* -----------------------------------------------------
       UPDATE ASSET
    ----------------------------------------------------- */

    $stmt = $conn->prepare(
        "UPDATE assets
         SET
            asset_code = ?,
            asset_name = ?,
            asset_type = ?,
            brand = ?,
            model = ?,
            serial_number = ?,
            assigned_to = ?,
            status = ?,
            purchase_date = ?
         WHERE asset_id = ?"
    );

    $stmt->bind_param(
        "ssssssissi",
        $asset_code,
        $asset_name,
        $asset_type,
        $brand,
        $model,
        $serial_number,
        $assigned_to,
        $status,
        $purchase_date,
        $asset_id
    );

    if ($stmt->execute()) {

        $stmt->close();

        redirectWithMessage(
            "Asset updated successfully.",
            "success"
        );

    } else {

        $stmt->close();

        redirectWithMessage(
            "Unable to update asset.",
            "error"
        );
    }
}


/* =========================================================
   DELETE ASSET
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_asset"])) {

    $asset_id = (int)($_POST["asset_id"] ?? 0);

    if ($asset_id <= 0) {

        redirectWithMessage(
            "Invalid asset selected.",
            "error"
        );
    }


    $stmt = $conn->prepare(
        "DELETE FROM assets
         WHERE asset_id = ?"
    );

    $stmt->bind_param(
        "i",
        $asset_id
    );

    if ($stmt->execute()) {

        if ($stmt->affected_rows > 0) {

            $stmt->close();

            redirectWithMessage(
                "Asset deleted successfully.",
                "success"
            );

        } else {

            $stmt->close();

            redirectWithMessage(
                "Asset was not found.",
                "error"
            );
        }

    } else {

        $stmt->close();

        redirectWithMessage(
            "Unable to delete asset. It may be referenced by another record.",
            "error"
        );
    }
}


/* =========================================================
   SEARCH AND FILTER
========================================================= */

$search = trim($_GET["search"] ?? "");

$statusFilter = $_GET["status"] ?? "";

$where = [];

$params = [];

$types = "";


/* ---------------------------------------------------------
   SEARCH
--------------------------------------------------------- */

if ($search !== "") {

    $where[] = "(
        a.asset_code LIKE ?
        OR a.asset_name LIKE ?
        OR a.asset_type LIKE ?
        OR a.brand LIKE ?
        OR a.model LIKE ?
        OR a.serial_number LIKE ?
        OR e.full_name LIKE ?
    )";

    $searchValue = "%" . $search . "%";

    for ($i = 0; $i < 7; $i++) {

        $params[] = $searchValue;

        $types .= "s";
    }
}


/* ---------------------------------------------------------
   STATUS FILTER
--------------------------------------------------------- */

if (
    $statusFilter !== "" &&
    in_array(
        $statusFilter,
        $allowedStatuses,
        true
    )
) {

    $where[] = "a.status = ?";

    $params[] = $statusFilter;

    $types .= "s";
}


/* =========================================================
   GET ASSETS
========================================================= */

$sql = "
    SELECT
        a.asset_id,
        a.asset_code,
        a.asset_name,
        a.asset_type,
        a.brand,
        a.model,
        a.serial_number,
        a.assigned_to,
        a.status,
        a.purchase_date,
        a.created_at,
        e.full_name AS employee_name,
        e.employee_code
    FROM assets a
    LEFT JOIN employees e
        ON a.assigned_to = e.employee_id
";


if (!empty($where)) {

    $sql .=
        " WHERE " .
        implode(" AND ", $where);
}


$sql .= " ORDER BY a.asset_id DESC";


$stmt = $conn->prepare($sql);


if (!empty($params)) {

    $stmt->bind_param(
        $types,
        ...$params
    );
}


$stmt->execute();

$result = $stmt->get_result();


$assets = [];


while ($row = $result->fetch_assoc()) {

    $assets[] = $row;
}


$stmt->close();


/* =========================================================
   GET EMPLOYEES
========================================================= */

$employeeResult = $conn->query(
    "SELECT
        employee_id,
        employee_code,
        full_name
     FROM employees
     ORDER BY full_name ASC"
);


$employees = [];


if ($employeeResult) {

    while ($employee = $employeeResult->fetch_assoc()) {

        $employees[] = $employee;
    }
}


/* =========================================================
   STATISTICS
========================================================= */

$totalAssets = 0;

$availableAssets = 0;

$assignedAssets = 0;

$maintenanceAssets = 0;

$retiredAssets = 0;


$statsResult = $conn->query(
    "SELECT
        COUNT(*) AS total_assets,
        SUM(status = 'Available') AS available_assets,
        SUM(status = 'Assigned') AS assigned_assets,
        SUM(status = 'Maintenance') AS maintenance_assets,
        SUM(status = 'Retired') AS retired_assets
     FROM assets"
);


if ($statsResult) {

    $stats = $statsResult->fetch_assoc();

    $totalAssets =
        (int)($stats["total_assets"] ?? 0);

    $availableAssets =
        (int)($stats["available_assets"] ?? 0);

    $assignedAssets =
        (int)($stats["assigned_assets"] ?? 0);

    $maintenanceAssets =
        (int)($stats["maintenance_assets"] ?? 0);

    $retiredAssets =
        (int)($stats["retired_assets"] ?? 0);
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Assets - IT Help Desk</title>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

    <style>

        .page-container {
            width: 92%;
            max-width: 1400px;
            margin: 35px auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
        }

        .page-header h1 {
            margin: 0 0 8px;
        }

        .page-header p {
            margin: 0;
        }

        .primary-btn {
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            background: #198754;
            color: white;
        }

        .primary-btn:hover {
            opacity: 0.9;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 18px;
            margin-bottom: 25px;
        }

        .stat-box {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);
        }

        .stat-box span {
            display: block;
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .stat-box strong {
            font-size: 28px;
        }

        .filter-panel {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 25px;
        }

        .filter-form {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .filter-form input,
        .filter-form select {
            padding: 11px 13px;
            border: 1px solid #ccc;
            border-radius: 7px;
            min-width: 220px;
        }

        .filter-form button {
            padding: 11px 18px;
            border: none;
            border-radius: 7px;
            cursor: pointer;
        }

        .search-btn {
            background: #198754;
            color: white;
        }

        .reset-btn {
            background: #e9ecef;
            color: #333;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            padding: 11px 18px;
            border-radius: 7px;
        }

        .table-panel {
            background: white;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.08);
            overflow-x: auto;
        }

        .asset-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1050px;
        }

        .asset-table th,
        .asset-table td {
            padding: 14px 12px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        .asset-table th {
            background: #f8f9fa;
            font-size: 14px;
        }

        .asset-table tr:hover {
            background: #fafafa;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-available {
            background: #d1e7dd;
            color: #0f5132;
        }

        .status-assigned {
            background: #cfe2ff;
            color: #084298;
        }

        .status-maintenance {
            background: #fff3cd;
            color: #664d03;
        }

        .status-retired {
            background: #e2e3e5;
            color: #41464b;
        }

        .action-buttons {
            display: flex;
            gap: 6px;
        }

        .action-btn {
            border: none;
            border-radius: 6px;
            padding: 7px 10px;
            cursor: pointer;
            font-size: 12px;
        }

        .view-btn {
            background: #0d6efd;
            color: white;
        }

        .edit-btn {
            background: #198754;
            color: white;
        }

        .delete-btn {
            background: #dc3545;
            color: white;
        }

        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            z-index: 9999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-content {
            background: white;
            width: 100%;
            max-width: 650px;
            max-height: 90vh;
            overflow-y: auto;
            border-radius: 12px;
            padding: 25px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            margin: 0;
        }

        .close-btn {
            border: none;
            background: none;
            font-size: 26px;
            cursor: pointer;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        .form-group label {
            font-weight: 600;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            padding: 11px;
            border: 1px solid #ccc;
            border-radius: 7px;
        }

        .form-actions {
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .cancel-btn {
            border: none;
            background: #e9ecef;
            padding: 11px 18px;
            border-radius: 7px;
            cursor: pointer;
        }

        .message {
            width: 92%;
            max-width: 1400px;
            margin: 20px auto;
            padding: 13px 16px;
            border-radius: 8px;
            font-weight: 600;
        }

        .message-success {
            background: #d1e7dd;
            color: #0f5132;
        }

        .message-error {
            background: #f8d7da;
            color: #842029;
        }

        .empty-message {
            text-align: center;
            padding: 40px;
            color: #777;
        }

        .asset-details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .asset-detail-box {
            padding: 12px;
            background: #f8f9fa;
            border-radius: 8px;
        }

        .asset-detail-box strong {
            display: block;
            margin-bottom: 5px;
        }

        @media (max-width: 1000px) {

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

        }

        @media (max-width: 650px) {

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-group.full {
                grid-column: auto;
            }

            .asset-details-grid {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>


<!-- ======================================================
     NAVBAR
====================================================== -->

<header class="navbar">

    <div class="logo">
        <span>IT</span> Help Desk
    </div>

    <nav class="nav-links">

        <a href="dashboard.php">
            Dashboard
        </a>

        <a href="employees.php">
            Employees
        </a>

        <a href="technicians.php">
            Technicians
        </a>

        <a
            href="assets.php"
            class="active"
        >
            Assets
        </a>

        <a href="tickets.php">
            Tickets
        </a>

        <a href="reports.php">
            Reports
        </a>

        <a
            href="../logout.php"
            class="nav-login"
        >
            Logout
        </a>

    </nav>

</header>


<!-- ======================================================
     MESSAGE
====================================================== -->

<?php if ($message !== ""): ?>

    <div
        class="message <?php echo $messageType === "success"
            ? "message-success"
            : "message-error"; ?>"
    >

        <?php echo e($message); ?>

    </div>

<?php endif; ?>


<!-- ======================================================
     MAIN CONTENT
====================================================== -->

<main class="page-container">


    <!-- PAGE HEADER -->

    <section class="page-header">

        <div>

            <p>
                ADMINISTRATION
            </p>

            <h1>
                Asset Management
            </h1>

            <p>
                Manage IT hardware and other organizational assets.
            </p>

        </div>

        <button
            type="button"
            class="primary-btn"
            onclick="openAddModal()"
        >
            + Add Asset
        </button>

    </section>


    <!-- ==================================================
         STATISTICS
    ================================================== -->

    <section class="stats-grid">

        <div class="stat-box">

            <span>
                Total Assets
            </span>

            <strong>
                <?php echo $totalAssets; ?>
            </strong>

        </div>


        <div class="stat-box">

            <span>
                Available
            </span>

            <strong>
                <?php echo $availableAssets; ?>
            </strong>

        </div>


        <div class="stat-box">

            <span>
                Assigned
            </span>

            <strong>
                <?php echo $assignedAssets; ?>
            </strong>

        </div>


        <div class="stat-box">

            <span>
                Maintenance
            </span>

            <strong>
                <?php echo $maintenanceAssets; ?>
            </strong>

        </div>


        <div class="stat-box">

            <span>
                Retired
            </span>

            <strong>
                <?php echo $retiredAssets; ?>
            </strong>

        </div>

    </section>


    <!-- ==================================================
         SEARCH / FILTER
    ================================================== -->

    <section class="filter-panel">

        <form
            method="GET"
            class="filter-form"
        >

            <input
                type="text"
                name="search"
                placeholder="Search assets..."
                value="<?php echo e($search); ?>"
            >

            <select name="status">

                <option value="">
                    All Status
                </option>

                <option
                    value="Available"
                    <?php echo $statusFilter === "Available"
                        ? "selected"
                        : ""; ?>
                >
                    Available
                </option>

                <option
                    value="Assigned"
                    <?php echo $statusFilter === "Assigned"
                        ? "selected"
                        : ""; ?>
                >
                    Assigned
                </option>

                <option
                    value="Maintenance"
                    <?php echo $statusFilter === "Maintenance"
                        ? "selected"
                        : ""; ?>
                >
                    Maintenance
                </option>

                <option
                    value="Retired"
                    <?php echo $statusFilter === "Retired"
                        ? "selected"
                        : ""; ?>
                >
                    Retired
                </option>

            </select>

            <button
                type="submit"
                class="search-btn"
            >
                Search
            </button>

            <a
                href="assets.php"
                class="reset-btn"
            >
                Reset
            </a>

        </form>

    </section>


    <!-- ==================================================
         ASSET TABLE
    ================================================== -->

    <section class="table-panel">

        <table class="asset-table">

            <thead>

                <tr>

                    <th>
                        Asset
                    </th>

                    <th>
                        Type
                    </th>

                    <th>
                        Brand / Model
                    </th>

                    <th>
                        Serial Number
                    </th>

                    <th>
                        Assigned To
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Purchase Date
                    </th>

                    <th>
                        Action
                    </th>

                </tr>

            </thead>

            <tbody>

            <?php if (count($assets) > 0): ?>

                <?php foreach ($assets as $asset): ?>

                    <?php

                    $statusClass = strtolower(
                        str_replace(
                            " ",
                            "-",
                            $asset["status"]
                        )
                    );

                    $assetJson = json_encode(
                        $asset,
                        JSON_HEX_TAG |
                        JSON_HEX_APOS |
                        JSON_HEX_QUOT |
                        JSON_HEX_AMP
                    );

                    ?>

                    <tr>

                        <td>

                            <strong>
                                <?php echo e($asset["asset_code"]); ?>
                            </strong>

                            <br>

                            <small>
                                <?php echo e($asset["asset_name"]); ?>
                            </small>

                        </td>


                        <td>
                            <?php echo e($asset["asset_type"]); ?>
                        </td>


                        <td>

                            <?php
                            echo e(
                                $asset["brand"] ?: "-"
                            );
                            ?>

                            <br>

                            <small>

                                <?php
                                echo e(
                                    $asset["model"] ?: "-"
                                );
                                ?>

                            </small>

                        </td>


                        <td>

                            <?php
                            echo e(
                                $asset["serial_number"] ?: "-"
                            );
                            ?>

                        </td>


                        <td>

                            <?php if (!empty($asset["employee_name"])): ?>

                                <strong>
                                    <?php
                                    echo e(
                                        $asset["employee_name"]
                                    );
                                    ?>
                                </strong>

                                <br>

                                <small>
                                    <?php
                                    echo e(
                                        $asset["employee_code"]
                                    );
                                    ?>
                                </small>

                            <?php else: ?>

                                <span>
                                    Not Assigned
                                </span>

                            <?php endif; ?>

                        </td>


                        <td>

                            <span
                                class="status status-<?php echo e($statusClass); ?>"
                            >
                                <?php
                                echo e(
                                    $asset["status"]
                                );
                                ?>
                            </span>

                        </td>


                        <td>

                            <?php

                            echo !empty($asset["purchase_date"])
                                ? date(
                                    "d M Y",
                                    strtotime(
                                        $asset["purchase_date"]
                                    )
                                )
                                : "-";

                            ?>

                        </td>


                        <td>

                            <div class="action-buttons">

                                <button
                                    type="button"
                                    class="action-btn view-btn"
                                    onclick='viewAsset(<?php echo $assetJson; ?>)'
                                >
                                    View
                                </button>


                                <button
                                    type="button"
                                    class="action-btn edit-btn"
                                    onclick='editAsset(<?php echo $assetJson; ?>)'
                                >
                                    Edit
                                </button>


                                <form
                                    method="POST"
                                    style="display:inline;"
                                    onsubmit="return confirm('Are you sure you want to delete this asset?');"
                                >

                                    <input
                                        type="hidden"
                                        name="asset_id"
                                        value="<?php
                                            echo (int)$asset["asset_id"];
                                        ?>"
                                    >

                                    <button
                                        type="submit"
                                        name="delete_asset"
                                        class="action-btn delete-btn"
                                    >
                                        Delete
                                    </button>

                                </form>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="8"
                        class="empty-message"
                    >
                        No assets found.
                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </section>

</main>


<!-- ======================================================
     ADD ASSET MODAL
====================================================== -->

<div
    id="addModal"
    class="modal"
>

    <div class="modal-content">

        <div class="modal-header">

            <h2>
                Add New Asset
            </h2>

            <button
                type="button"
                class="close-btn"
                onclick="closeModal('addModal')"
            >
                &times;
            </button>

        </div>


        <form method="POST">

            <div class="form-grid">


                <div class="form-group">

                    <label>
                        Asset Code *
                    </label>

                    <input
                        type="text"
                        name="asset_code"
                        placeholder="AST-009"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Asset Name *
                    </label>

                    <input
                        type="text"
                        name="asset_name"
                        placeholder="Dell Latitude 5550"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Asset Type *
                    </label>

                    <select
                        name="asset_type"
                        required
                    >

                        <option value="">
                            Select Type
                        </option>

                        <?php foreach ($allowedAssetTypes as $type): ?>

                            <option value="<?php echo e($type); ?>">
                                <?php echo e($type); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Brand
                    </label>

                    <input
                        type="text"
                        name="brand"
                        placeholder="Dell"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Model
                    </label>

                    <input
                        type="text"
                        name="model"
                        placeholder="Latitude 5550"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Serial Number
                    </label>

                    <input
                        type="text"
                        name="serial_number"
                        placeholder="DL5550-009"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Assign To
                    </label>

                    <select name="assigned_to">

                        <option value="">
                            Not Assigned
                        </option>

                        <?php foreach ($employees as $employee): ?>

                            <option
                                value="<?php
                                    echo (int)$employee["employee_id"];
                                ?>"
                            >

                                <?php
                                echo e(
                                    $employee["employee_code"]
                                );
                                ?>

                                -

                                <?php
                                echo e(
                                    $employee["full_name"]
                                );
                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Status
                    </label>

                    <select name="status">

                        <?php foreach ($allowedStatuses as $assetStatus): ?>

                            <option
                                value="<?php echo e($assetStatus); ?>"
                            >
                                <?php echo e($assetStatus); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Purchase Date
                    </label>

                    <input
                        type="date"
                        name="purchase_date"
                    >

                </div>

            </div>


            <div class="form-actions">

                <button
                    type="button"
                    class="cancel-btn"
                    onclick="closeModal('addModal')"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    name="add_asset"
                    class="primary-btn"
                >
                    Add Asset
                </button>

            </div>

        </form>

    </div>

</div>


<!-- ======================================================
     VIEW ASSET MODAL
====================================================== -->

<div
    id="viewModal"
    class="modal"
>

    <div class="modal-content">

        <div class="modal-header">

            <h2>
                Asset Details
            </h2>

            <button
                type="button"
                class="close-btn"
                onclick="closeModal('viewModal')"
            >
                &times;
            </button>

        </div>


        <div id="assetDetails"></div>

    </div>

</div>


<!-- ======================================================
     EDIT ASSET MODAL
====================================================== -->

<div
    id="editModal"
    class="modal"
>

    <div class="modal-content">

        <div class="modal-header">

            <h2>
                Edit Asset
            </h2>

            <button
                type="button"
                class="close-btn"
                onclick="closeModal('editModal')"
            >
                &times;
            </button>

        </div>


        <form method="POST">

            <input
                type="hidden"
                name="asset_id"
                id="edit_asset_id"
            >


            <div class="form-grid">


                <div class="form-group">

                    <label>
                        Asset Code *
                    </label>

                    <input
                        type="text"
                        name="asset_code"
                        id="edit_asset_code"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Asset Name *
                    </label>

                    <input
                        type="text"
                        name="asset_name"
                        id="edit_asset_name"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>
                        Asset Type *
                    </label>

                    <select
                        name="asset_type"
                        id="edit_asset_type"
                        required
                    >

                        <?php foreach ($allowedAssetTypes as $type): ?>

                            <option value="<?php echo e($type); ?>">
                                <?php echo e($type); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Brand
                    </label>

                    <input
                        type="text"
                        name="brand"
                        id="edit_brand"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Model
                    </label>

                    <input
                        type="text"
                        name="model"
                        id="edit_model"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Serial Number
                    </label>

                    <input
                        type="text"
                        name="serial_number"
                        id="edit_serial_number"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Assign To
                    </label>

                    <select
                        name="assigned_to"
                        id="edit_assigned_to"
                    >

                        <option value="">
                            Not Assigned
                        </option>

                        <?php foreach ($employees as $employee): ?>

                            <option
                                value="<?php
                                    echo (int)$employee["employee_id"];
                                ?>"
                            >

                                <?php
                                echo e(
                                    $employee["employee_code"]
                                );
                                ?>

                                -

                                <?php
                                echo e(
                                    $employee["full_name"]
                                );
                                ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Status
                    </label>

                    <select
                        name="status"
                        id="edit_status"
                    >

                        <?php foreach ($allowedStatuses as $assetStatus): ?>

                            <option
                                value="<?php echo e($assetStatus); ?>"
                            >
                                <?php echo e($assetStatus); ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Purchase Date
                    </label>

                    <input
                        type="date"
                        name="purchase_date"
                        id="edit_purchase_date"
                    >

                </div>

            </div>


            <div class="form-actions">

                <button
                    type="button"
                    class="cancel-btn"
                    onclick="closeModal('editModal')"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    name="edit_asset"
                    class="primary-btn"
                >
                    Save Changes
                </button>

            </div>

        </form>

    </div>

</div>


<!-- ======================================================
     FOOTER
====================================================== -->

<footer class="footer">

    <div class="footer-content">

        <div class="footer-logo">

            <span>IT</span> Help Desk

        </div>

        <p>
            IT Support and Asset Management System
        </p>

    </div>


    <div class="footer-bottom">

        © 2026 IT Help Desk. All Rights Reserved.

    </div>

</footer>


<!-- ======================================================
     JAVASCRIPT
====================================================== -->

<script>

/* =========================================================
   OPEN ADD MODAL
========================================================= */

function openAddModal() {

    document.getElementById("addModal").style.display = "flex";

}


/* =========================================================
   CLOSE MODAL
========================================================= */

function closeModal(id) {

    const modal = document.getElementById(id);

    if (modal) {

        modal.style.display = "none";

    }

}


/* =========================================================
   VIEW ASSET
========================================================= */

function viewAsset(asset) {

    const employee =
        asset.employee_name
            ? asset.employee_name +
              " (" +
              asset.employee_code +
              ")"
            : "Not Assigned";


    const purchaseDate =
        asset.purchase_date
            ? asset.purchase_date
            : "-";


    document.getElementById("assetDetails").innerHTML = `

        <div class="asset-details-grid">

            <div class="asset-detail-box">

                <strong>
                    Asset Code
                </strong>

                ${escapeHtml(asset.asset_code)}

            </div>


            <div class="asset-detail-box">

                <strong>
                    Asset Name
                </strong>

                ${escapeHtml(asset.asset_name)}

            </div>


            <div class="asset-detail-box">

                <strong>
                    Asset Type
                </strong>

                ${escapeHtml(asset.asset_type)}

            </div>


            <div class="asset-detail-box">

                <strong>
                    Brand
                </strong>

                ${escapeHtml(asset.brand || "-")}

            </div>


            <div class="asset-detail-box">

                <strong>
                    Model
                </strong>

                ${escapeHtml(asset.model || "-")}

            </div>


            <div class="asset-detail-box">

                <strong>
                    Serial Number
                </strong>

                ${escapeHtml(asset.serial_number || "-")}

            </div>


            <div class="asset-detail-box">

                <strong>
                    Assigned To
                </strong>

                ${escapeHtml(employee)}

            </div>


            <div class="asset-detail-box">

                <strong>
                    Status
                </strong>

                ${escapeHtml(asset.status)}

            </div>


            <div class="asset-detail-box">

                <strong>
                    Purchase Date
                </strong>

                ${escapeHtml(purchaseDate)}

            </div>


        </div>

    `;


    document.getElementById("viewModal").style.display = "flex";

}


/* =========================================================
   EDIT ASSET
========================================================= */

function editAsset(asset) {

    document.getElementById("edit_asset_id").value =
        asset.asset_id;


    document.getElementById("edit_asset_code").value =
        asset.asset_code;


    document.getElementById("edit_asset_name").value =
        asset.asset_name;


    document.getElementById("edit_asset_type").value =
        asset.asset_type;


    document.getElementById("edit_brand").value =
        asset.brand || "";


    document.getElementById("edit_model").value =
        asset.model || "";


    document.getElementById("edit_serial_number").value =
        asset.serial_number || "";


    document.getElementById("edit_assigned_to").value =
        asset.assigned_to || "";


    document.getElementById("edit_status").value =
        asset.status;


    document.getElementById("edit_purchase_date").value =
        asset.purchase_date || "";


    document.getElementById("editModal").style.display =
        "flex";

}


/* =========================================================
   ESCAPE HTML
========================================================= */

function escapeHtml(value) {

    return String(value)

        .replace(/&/g, "&amp;")

        .replace(/</g, "&lt;")

        .replace(/>/g, "&gt;")

        .replace(/"/g, "&quot;")

        .replace(/'/g, "&#039;");

}


/* =========================================================
   CLOSE MODAL WHEN CLICKING OUTSIDE
========================================================= */

window.addEventListener(
    "click",
    function(event) {

        const modals =
            document.querySelectorAll(".modal");


        modals.forEach(
            function(modal) {

                if (event.target === modal) {

                    modal.style.display = "none";

                }

            }
        );

    }
);


/* =========================================================
   CLOSE MODAL WITH ESCAPE KEY
========================================================= */

document.addEventListener(
    "keydown",
    function(event) {

        if (event.key === "Escape") {

            document
                .querySelectorAll(".modal")
                .forEach(
                    function(modal) {

                        modal.style.display = "none";

                    }
                );

        }

    }
);

</script>


</body>

</html>