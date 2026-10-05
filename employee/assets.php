<?php

session_start();

require_once "../config/database.php";


/* =========================================================
   EMPLOYEE LOGIN CHECK
========================================================= */

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "employee"
) {
    header("Location: ../login.php");
    exit;
}


/* =========================================================
   GET LOGGED-IN EMPLOYEE
========================================================= */

$user_id = $_SESSION["user_id"];

$employeeQuery = $conn->prepare("
    SELECT employee_id, employee_code, full_name, department
    FROM employees
    WHERE user_id = ?
    LIMIT 1
");

$employeeQuery->bind_param("i", $user_id);
$employeeQuery->execute();

$employeeResult = $employeeQuery->get_result();
$employee = $employeeResult->fetch_assoc();

$employeeQuery->close();


if (!$employee) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}


$employee_id = $employee["employee_id"];


/* =========================================================
   HELPER FUNCTION
========================================================= */

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}


/* =========================================================
   GET ASSETS ASSIGNED TO THIS EMPLOYEE
========================================================= */

$assets = [];

$stmt = $conn->prepare("
    SELECT
        asset_id,
        asset_code,
        asset_name,
        asset_type,
        brand,
        model,
        serial_number,
        status,
        purchase_date,
        created_at
    FROM assets
    WHERE assigned_to = ?
    ORDER BY created_at DESC
");

$stmt->bind_param("i", $employee_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $assets[] = $row;
}

$stmt->close();


/* =========================================================
   ASSET COUNTS
========================================================= */

$totalAssets = count($assets);

$laptopCount = 0;
$monitorCount = 0;
$accessoryCount = 0;


/* Count asset categories */

foreach ($assets as $asset) {

    $type = strtolower(trim($asset["asset_type"]));

    if (
        strpos($type, "laptop") !== false ||
        strpos($type, "notebook") !== false
    ) {
        $laptopCount++;
    }

    elseif (
        strpos($type, "monitor") !== false ||
        strpos($type, "display") !== false
    ) {
        $monitorCount++;
    }

    else {
        $accessoryCount++;
    }
}


/* =========================================================
   ASSET ICON
========================================================= */

function assetIcon($assetType)
{
    $type = strtolower(trim($assetType));

    if (
        strpos($type, "laptop") !== false ||
        strpos($type, "notebook") !== false
    ) {
        return "💻";
    }

    if (
        strpos($type, "monitor") !== false ||
        strpos($type, "display") !== false
    ) {
        return "🖥️";
    }

    if (
        strpos($type, "keyboard") !== false
    ) {
        return "⌨️";
    }

    if (
        strpos($type, "mouse") !== false
    ) {
        return "🖱️";
    }

    if (
        strpos($type, "printer") !== false
    ) {
        return "🖨️";
    }

    return "📦";
}


/* =========================================================
   STATUS CLASS
========================================================= */

function assetStatusClass($status)
{
    switch ($status) {

        case "Assigned":
            return "asset-active";

        case "Maintenance":
            return "asset-maintenance";

        case "Retired":
            return "asset-retired";

        case "Available":
            return "asset-available";

        default:
            return "asset-active";
    }
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

    <title>My Assets - IT Help Desk</title>

    <link rel="stylesheet" href="../css/style.css">

</head>


<body>


<header class="navbar">

    <div class="logo">
        <span>IT</span> Help Desk
    </div>


    <nav>

        <a href="dashboard.php">
            Dashboard
        </a>

        <a href="create-ticket.php">
            Create Ticket
        </a>

        <a href="tickets.php">
            My Tickets
        </a>

        <a href="assets.php">
            My Assets
        </a>

        <a href="../logout.php" class="login-btn">
            Logout
        </a>

    </nav>

</header>



<main class="dashboard-page">


    <!-- PAGE HEADER -->

    <div class="dashboard-header">

        <div>

            <p class="dashboard-label">
                EMPLOYEE PORTAL
            </p>

            <h1>
                My IT Assets
            </h1>

            <p>
                View the IT equipment currently assigned to you.
            </p>

        </div>

    </div>



    <!-- ASSET SUMMARY -->

    <div class="dashboard-stats">


        <!-- LAPTOPS -->

        <div class="dashboard-stat-card">

            <div class="stat-icon">
                💻
            </div>

            <div>

                <h2>
                    <?= str_pad((string)$laptopCount, 2, "0", STR_PAD_LEFT) ?>
                </h2>

                <p>
                    Laptops
                </p>

            </div>

        </div>



        <!-- MONITORS -->

        <div class="dashboard-stat-card">

            <div class="stat-icon">
                🖥️
            </div>

            <div>

                <h2>
                    <?= str_pad((string)$monitorCount, 2, "0", STR_PAD_LEFT) ?>
                </h2>

                <p>
                    Monitors
                </p>

            </div>

        </div>



        <!-- ACCESSORIES -->

        <div class="dashboard-stat-card">

            <div class="stat-icon">
                ⌨️
            </div>

            <div>

                <h2>
                    <?= str_pad((string)$accessoryCount, 2, "0", STR_PAD_LEFT) ?>
                </h2>

                <p>
                    Accessories
                </p>

            </div>

        </div>



        <!-- TOTAL -->

        <div class="dashboard-stat-card">

            <div class="stat-icon">
                📦
            </div>

            <div>

                <h2>
                    <?= str_pad((string)$totalAssets, 2, "0", STR_PAD_LEFT) ?>
                </h2>

                <p>
                    Total Assets
                </p>

            </div>

        </div>


    </div>



    <!-- ASSET LIST -->

    <section class="dashboard-panel asset-list-panel">


        <div class="panel-header">

            <div>

                <h2>
                    Assigned Assets
                </h2>

                <p>
                    Details of the equipment assigned to your account.
                </p>

            </div>

        </div>



        <?php if ($totalAssets > 0): ?>


            <?php foreach ($assets as $asset): ?>


                <div class="asset-detail-row">


                    <div class="large-asset-icon">

                        <?= assetIcon($asset["asset_type"]) ?>

                    </div>


                    <div class="asset-detail-info">


                        <h3>
                            <?= e($asset["asset_name"]) ?>
                        </h3>


                        <p>

                            <?= e($asset["asset_type"]) ?>

                            <?php if (!empty($asset["brand"])): ?>

                                • <?= e($asset["brand"]) ?>

                            <?php endif; ?>

                        </p>



                        <div class="asset-details-grid">


                            <!-- ASSET ID -->

                            <div>

                                <span>
                                    Asset ID
                                </span>

                                <strong>
                                    <?= e($asset["asset_code"]) ?>
                                </strong>

                            </div>



                            <!-- SERIAL NUMBER -->

                            <div>

                                <span>
                                    Serial Number
                                </span>

                                <strong>
                                    <?= e($asset["serial_number"]) ?>
                                </strong>

                            </div>



                            <!-- MODEL -->

                            <div>

                                <span>
                                    Model
                                </span>

                                <strong>

                                    <?php if (!empty($asset["model"])): ?>

                                        <?= e($asset["model"]) ?>

                                    <?php else: ?>

                                        Not specified

                                    <?php endif; ?>

                                </strong>

                            </div>



                            <!-- PURCHASE DATE -->

                            <div>

                                <span>
                                    Purchase Date
                                </span>

                                <strong>

                                    <?php if (!empty($asset["purchase_date"])): ?>

                                        <?= e(
                                            date(
                                                "d M Y",
                                                strtotime($asset["purchase_date"])
                                            )
                                        ) ?>

                                    <?php else: ?>

                                        Not specified

                                    <?php endif; ?>

                                </strong>

                            </div>


                        </div>

                    </div>



                    <!-- STATUS -->

                    <div class="<?= e(assetStatusClass($asset["status"])) ?>">

                        <?= e($asset["status"]) ?>

                    </div>


                </div>


            <?php endforeach; ?>


        <?php else: ?>


            <div
                style="
                    padding: 35px;
                    text-align: center;
                "
            >

                <div style="font-size: 45px;">
                    📦
                </div>

                <h3>
                    No Assets Assigned
                </h3>

                <p>
                    There are currently no IT assets assigned to your account.
                </p>

            </div>


        <?php endif; ?>


    </section>



    <!-- ASSET LIFECYCLE -->

    <section class="dashboard-panel asset-lifecycle-panel">


        <div class="panel-header">

            <div>

                <h2>
                    Asset Lifecycle
                </h2>

                <p>
                    How IT assets are managed by the organization.
                </p>

            </div>

        </div>



        <div class="lifecycle-grid">


            <div class="lifecycle-item">

                <div class="lifecycle-number">
                    01
                </div>

                <h3>
                    Available
                </h3>

                <p>
                    Asset is available in the IT inventory.
                </p>

            </div>



            <div class="lifecycle-item">

                <div class="lifecycle-number">
                    02
                </div>

                <h3>
                    Assigned
                </h3>

                <p>
                    Asset is assigned to an employee.
                </p>

            </div>



            <div class="lifecycle-item">

                <div class="lifecycle-number">
                    03
                </div>

                <h3>
                    Maintenance
                </h3>

                <p>
                    Asset is temporarily under maintenance.
                </p>

            </div>



            <div class="lifecycle-item">

                <div class="lifecycle-number">
                    04
                </div>

                <h3>
                    Retired
                </h3>

                <p>
                    Asset is removed from active inventory.
                </p>

            </div>


        </div>


    </section>


</main>



<footer>

    <div class="footer-content">

        <div>

            <h3>
                IT Help Desk
            </h3>

            <p>
                Reliable IT support and asset management system.
            </p>

        </div>


        <p>
            © 2026 IT Help Desk
        </p>

    </div>

</footer>



</body>

</html>