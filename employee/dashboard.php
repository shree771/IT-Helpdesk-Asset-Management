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

$user_id = (int) $_SESSION["user_id"];


/* =========================================================
   HELPER FUNCTION
========================================================= */

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}


/* =========================================================
   GET LOGGED-IN EMPLOYEE
========================================================= */

$stmt = $conn->prepare("
    SELECT
        employee_id,
        employee_code,
        full_name,
        department,
        phone
    FROM employees
    WHERE user_id = ?
    LIMIT 1
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$employee = $result->fetch_assoc();

$stmt->close();


if (!$employee) {
    die("Employee profile not found.");
}

$employee_id = (int) $employee["employee_id"];


/* =========================================================
   TICKET STATISTICS
========================================================= */

/* Total Tickets */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM tickets
    WHERE employee_id = ?
");

$stmt->bind_param("i", $employee_id);
$stmt->execute();

$total_tickets = (int) $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();


/* In Progress Tickets */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM tickets
    WHERE employee_id = ?
    AND status = 'In Progress'
");

$stmt->bind_param("i", $employee_id);
$stmt->execute();

$in_progress = (int) $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();


/* Resolved Tickets */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM tickets
    WHERE employee_id = ?
    AND status = 'Resolved'
");

$stmt->bind_param("i", $employee_id);
$stmt->execute();

$resolved = (int) $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();


/* =========================================================
   ASSIGNED ASSETS
========================================================= */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM assets
    WHERE assigned_to = ?
");

$stmt->bind_param("i", $employee_id);
$stmt->execute();

$assigned_assets = (int) $stmt->get_result()->fetch_assoc()["total"];

$stmt->close();


/* =========================================================
   RECENT TICKETS
========================================================= */

$stmt = $conn->prepare("
    SELECT
        ticket_id,
        title,
        status,
        created_at
    FROM tickets
    WHERE employee_id = ?
    ORDER BY created_at DESC
    LIMIT 3
");

$stmt->bind_param("i", $employee_id);
$stmt->execute();

$recent_tickets = $stmt->get_result();

$stmt->close();


/* =========================================================
   ASSIGNED ASSETS LIST
========================================================= */

$stmt = $conn->prepare("
    SELECT
        asset_id,
        asset_code,
        asset_name,
        asset_type,
        brand,
        model,
        status
    FROM assets
    WHERE assigned_to = ?
    ORDER BY created_at DESC
    LIMIT 3
");

$stmt->bind_param("i", $employee_id);
$stmt->execute();

$recent_assets = $stmt->get_result();

$stmt->close();


/* =========================================================
   TICKET ID FUNCTION
========================================================= */

function ticketId($id, $created_at = null)
{
    $year = date("Y");

    if (!empty($created_at)) {

        $timestamp = strtotime($created_at);

        if ($timestamp !== false) {
            $year = date("Y", $timestamp);
        }
    }

    return "INC-" . $year . "-" . str_pad(
        (string)$id,
        3,
        "0",
        STR_PAD_LEFT
    );
}


/* =========================================================
   TICKET STATUS DISPLAY
========================================================= */

function displayStatus($status)
{
    if ($status === "Open") {
        return "New";
    }

    return $status;
}


/* =========================================================
   TICKET STATUS CSS CLASS
========================================================= */

function statusClass($status)
{
    switch ($status) {

        case "In Progress":
            return "status-progress";

        case "Resolved":
            return "status-resolved";

        case "Closed":
            return "status-resolved";

        case "Open":
        default:
            return "status-new";
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

    <title>
        Employee Dashboard - IT Help Desk
    </title>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

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


    <!-- DASHBOARD HEADER -->

    <div class="dashboard-header">

        <div>

            <p class="dashboard-label">
                EMPLOYEE PORTAL
            </p>

            <h1>
                Welcome Back, <?= e($employee["full_name"]) ?>
            </h1>

            <p>
                Manage your IT support requests and assigned assets.
            </p>

        </div>


        <a
            href="create-ticket.php"
            class="primary-btn"
        >
            + Create Support Ticket
        </a>

    </div>



    <!-- EMPLOYEE INFORMATION -->

    <div style="
        margin-bottom: 25px;
        padding: 18px;
        background: #f8fafc;
        border-radius: 12px;
        border: 1px solid #e5e7eb;
    ">

        <strong>
            Employee ID:
        </strong>

        <?= e($employee["employee_code"]) ?>

        &nbsp;&nbsp; | &nbsp;&nbsp;

        <strong>
            Department:
        </strong>

        <?= e($employee["department"]) ?>

    </div>



    <!-- STATISTICS -->

    <div class="dashboard-stats">


        <div class="dashboard-stat-card">

            <div class="stat-icon">
                🎫
            </div>

            <div>

                <h2>
                    <?= $total_tickets ?>
                </h2>

                <p>
                    Total Tickets
                </p>

            </div>

        </div>



        <div class="dashboard-stat-card">

            <div class="stat-icon">
                🔧
            </div>

            <div>

                <h2>
                    <?= $in_progress ?>
                </h2>

                <p>
                    In Progress
                </p>

            </div>

        </div>



        <div class="dashboard-stat-card">

            <div class="stat-icon">
                ✅
            </div>

            <div>

                <h2>
                    <?= $resolved ?>
                </h2>

                <p>
                    Resolved
                </p>

            </div>

        </div>



        <div class="dashboard-stat-card">

            <div class="stat-icon">
                💻
            </div>

            <div>

                <h2>
                    <?= $assigned_assets ?>
                </h2>

                <p>
                    Assigned Assets
                </p>

            </div>

        </div>


    </div>



    <!-- MAIN DASHBOARD CONTENT -->

    <div class="dashboard-grid">


        <!-- RECENT TICKETS -->

        <section class="dashboard-panel">


            <div class="panel-header">

                <div>

                    <h2>
                        Recent Tickets
                    </h2>

                    <p>
                        Latest support requests submitted by you.
                    </p>

                </div>


                <a href="tickets.php">
                    View All
                </a>

            </div>



            <?php if ($recent_tickets->num_rows > 0): ?>

                <?php while ($ticket = $recent_tickets->fetch_assoc()): ?>

                    <div class="ticket-row">

                        <div class="ticket-info">

                            <strong>
                                <?= e(ticketId(
                                    $ticket["ticket_id"],
                                    $ticket["created_at"]
                                )) ?>
                            </strong>

                            <span>
                                <?= e($ticket["title"]) ?>
                            </span>

                        </div>


                        <div class="ticket-status <?= e(
                            statusClass($ticket["status"])
                        ) ?>">

                            <?= e(
                                displayStatus($ticket["status"])
                            ) ?>

                        </div>

                    </div>

                <?php endwhile; ?>

            <?php else: ?>

                <div class="ticket-row">

                    <div class="ticket-info">

                        <strong>
                            No Tickets
                        </strong>

                        <span>
                            You have not created any support tickets yet.
                        </span>

                    </div>

                </div>

            <?php endif; ?>


        </section>



        <!-- ASSIGNED ASSETS -->

        <section class="dashboard-panel">


            <div class="panel-header">

                <div>

                    <h2>
                        My Assets
                    </h2>

                    <p>
                        Currently assigned equipment.
                    </p>

                </div>


                <a href="assets.php">
                    View All
                </a>

            </div>



            <?php if ($recent_assets->num_rows > 0): ?>

                <?php while ($asset = $recent_assets->fetch_assoc()): ?>

                    <?php

                    $icon = "💻";

                    if ($asset["asset_type"] === "Monitor") {
                        $icon = "🖥️";
                    } elseif ($asset["asset_type"] === "Keyboard") {
                        $icon = "⌨️";
                    } elseif ($asset["asset_type"] === "Printer") {
                        $icon = "🖨️";
                    } elseif ($asset["asset_type"] === "Network Device") {
                        $icon = "🌐";
                    }

                    ?>

                    <div class="asset-row">

                        <div class="asset-icon">
                            <?= $icon ?>
                        </div>


                        <div class="asset-info">

                            <strong>
                                <?= e($asset["asset_name"]) ?>
                            </strong>

                            <span>

                                <?= e($asset["asset_type"]) ?>

                                •
                                
                                <?= e($asset["asset_code"]) ?>

                            </span>

                        </div>


                        <div class="asset-status">

                            <?= e($asset["status"]) ?>

                        </div>

                    </div>

                <?php endwhile; ?>

            <?php else: ?>

                <div class="asset-row">

                    <div class="asset-icon">
                        💻
                    </div>

                    <div class="asset-info">

                        <strong>
                            No Assets Assigned
                        </strong>

                        <span>
                            No equipment is currently assigned to you.
                        </span>

                    </div>

                </div>

            <?php endif; ?>


        </section>


    </div>



    <!-- QUICK ACTIONS -->

    <section class="quick-actions">


        <h2>
            Quick Actions
        </h2>


        <div class="quick-action-grid">


            <a
                href="create-ticket.php"
                class="quick-action-card"
            >

                <span>
                    🎫
                </span>

                <div>

                    <strong>
                        Create Support Ticket
                    </strong>

                    <p>
                        Report a new IT issue.
                    </p>

                </div>

            </a>



            <a
                href="tickets.php"
                class="quick-action-card"
            >

                <span>
                    📋
                </span>

                <div>

                    <strong>
                        Track My Tickets
                    </strong>

                    <p>
                        Check ticket progress.
                    </p>

                </div>

            </a>



            <a
                href="assets.php"
                class="quick-action-card"
            >

                <span>
                    💻
                </span>

                <div>

                    <strong>
                        View My Assets
                    </strong>

                    <p>
                        Check assigned equipment.
                    </p>

                </div>

            </a>


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