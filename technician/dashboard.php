<?php

session_start();

require_once "../config/database.php";


/* =========================================================
   TECHNICIAN LOGIN CHECK
========================================================= */

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "technician"
) {
    header("Location: ../login.php");
    exit;
}


/* =========================================================
   GET LOGGED-IN TECHNICIAN
========================================================= */

$user_id = $_SESSION["user_id"];

$technicianQuery = $conn->prepare("
    SELECT
        technician_id,
        technician_code,
        full_name,
        specialization,
        phone,
        availability
    FROM technicians
    WHERE user_id = ?
    LIMIT 1
");

$technicianQuery->bind_param("i", $user_id);
$technicianQuery->execute();

$technicianResult = $technicianQuery->get_result();
$technician = $technicianResult->fetch_assoc();

$technicianQuery->close();


if (!$technician) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}


$technician_id = $technician["technician_id"];


/* =========================================================
   HELPER FUNCTIONS
========================================================= */

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}


function ticketId($id, $createdAt = null)
{
    $year = date("Y");

    if ($createdAt) {
        $timestamp = strtotime($createdAt);

        if ($timestamp !== false) {
            $year = date("Y", $timestamp);
        }
    }

    return "INC-" . $year . "-" .
        str_pad((string)$id, 3, "0", STR_PAD_LEFT);
}


function displayStatus($status)
{
    if ($status === "Open") {
        return "New";
    }

    return $status;
}


function statusClass($status)
{
    switch ($status) {

        case "Open":
            return "status-new";

        case "In Progress":
            return "status-progress";

        case "Resolved":
        case "Closed":
            return "status-resolved";

        default:
            return "status-new";
    }
}


/* =========================================================
   TICKET STATISTICS
========================================================= */


/* Total assigned tickets */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM tickets
    WHERE technician_id = ?
");

$stmt->bind_param("i", $technician_id);
$stmt->execute();

$result = $stmt->get_result();
$assignedTickets = (int)$result->fetch_assoc()["total"];

$stmt->close();


/* New tickets */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM tickets
    WHERE technician_id = ?
    AND status = 'Open'
");

$stmt->bind_param("i", $technician_id);
$stmt->execute();

$result = $stmt->get_result();
$newTickets = (int)$result->fetch_assoc()["total"];

$stmt->close();


/* In Progress tickets */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM tickets
    WHERE technician_id = ?
    AND status = 'In Progress'
");

$stmt->bind_param("i", $technician_id);
$stmt->execute();

$result = $stmt->get_result();
$inProgressTickets = (int)$result->fetch_assoc()["total"];

$stmt->close();


/* Resolved tickets */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM tickets
    WHERE technician_id = ?
    AND status = 'Resolved'
");

$stmt->bind_param("i", $technician_id);
$stmt->execute();

$result = $stmt->get_result();
$resolvedTickets = (int)$result->fetch_assoc()["total"];

$stmt->close();


/* =========================================================
   GET RECENT ASSIGNED TICKETS
========================================================= */

$recentTickets = [];

$stmt = $conn->prepare("
    SELECT
        t.ticket_id,
        t.title,
        t.description,
        t.category,
        t.priority,
        t.status,
        t.created_at,
        t.updated_at,
        e.full_name AS employee_name
    FROM tickets t
    INNER JOIN employees e
        ON t.employee_id = e.employee_id
    WHERE t.technician_id = ?
    ORDER BY t.updated_at DESC
    LIMIT 4
");

$stmt->bind_param("i", $technician_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $recentTickets[] = $row;
}

$stmt->close();

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Technician Dashboard - IT Help Desk</title>

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

        <a href="tickets.php">
            Assigned Tickets
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
                TECHNICIAN PORTAL
            </p>

            <h1>
                Welcome,
                <?= e($technician["full_name"]) ?>
            </h1>

            <p>
                Manage assigned IT support requests and resolve employee issues.
            </p>

        </div>


        <a href="tickets.php" class="primary-btn">
            View Assigned Tickets
        </a>

    </div>



    <!-- STATISTICS -->

    <div class="dashboard-stats">


        <!-- ASSIGNED TICKETS -->

        <div class="dashboard-stat-card">

            <div class="stat-icon">
                🎫
            </div>

            <div>

                <h2>
                    <?= str_pad(
                        (string)$assignedTickets,
                        2,
                        "0",
                        STR_PAD_LEFT
                    ) ?>
                </h2>

                <p>
                    Assigned Tickets
                </p>

            </div>

        </div>



        <!-- NEW TICKETS -->

        <div class="dashboard-stat-card">

            <div class="stat-icon">
                🆕
            </div>

            <div>

                <h2>
                    <?= str_pad(
                        (string)$newTickets,
                        2,
                        "0",
                        STR_PAD_LEFT
                    ) ?>
                </h2>

                <p>
                    New Tickets
                </p>

            </div>

        </div>



        <!-- IN PROGRESS -->

        <div class="dashboard-stat-card">

            <div class="stat-icon">
                🔧
            </div>

            <div>

                <h2>
                    <?= str_pad(
                        (string)$inProgressTickets,
                        2,
                        "0",
                        STR_PAD_LEFT
                    ) ?>
                </h2>

                <p>
                    In Progress
                </p>

            </div>

        </div>



        <!-- RESOLVED -->

        <div class="dashboard-stat-card">

            <div class="stat-icon">
                ✅
            </div>

            <div>

                <h2>
                    <?= str_pad(
                        (string)$resolvedTickets,
                        2,
                        "0",
                        STR_PAD_LEFT
                    ) ?>
                </h2>

                <p>
                    Resolved
                </p>

            </div>

        </div>


    </div>



    <!-- MAIN CONTENT -->

    <div class="dashboard-grid">


        <!-- ASSIGNED TICKETS -->

        <section class="dashboard-panel">


            <div class="panel-header">

                <div>

                    <h2>
                        Assigned Tickets
                    </h2>

                    <p>
                        Recent support requests assigned to you.
                    </p>

                </div>


                <a href="tickets.php">
                    View All
                </a>

            </div>



            <?php if (count($recentTickets) > 0): ?>


                <?php foreach ($recentTickets as $ticket): ?>


                    <div class="ticket-row">


                        <div class="ticket-info">


                            <strong>
                                <?= e(
                                    ticketId(
                                        $ticket["ticket_id"],
                                        $ticket["created_at"]
                                    )
                                ) ?>
                            </strong>


                            <span>
                                <?= e($ticket["title"]) ?>
                            </span>


                            <small>
                                <?= e($ticket["category"]) ?>
                                •
                                <?= e($ticket["priority"]) ?> Priority
                                •
                                Employee:
                                <?= e($ticket["employee_name"]) ?>
                            </small>


                        </div>


                        <div class="ticket-status <?= e(
                            statusClass($ticket["status"])
                        ) ?>">

                            <?= e(
                                displayStatus($ticket["status"])
                            ) ?>

                        </div>


                    </div>


                <?php endforeach; ?>


            <?php else: ?>


                <div
                    style="
                        padding: 30px;
                        text-align: center;
                    "
                >

                    <div style="font-size: 40px;">
                        🎫
                    </div>

                    <h3>
                        No Tickets Assigned
                    </h3>

                    <p>
                        There are currently no support tickets assigned to you.
                    </p>

                </div>


            <?php endif; ?>


        </section>



        <!-- WORK SUMMARY -->

        <section class="dashboard-panel">


            <div class="panel-header">

                <div>

                    <h2>
                        Work Summary
                    </h2>

                    <p>
                        Your current support workload.
                    </p>

                </div>

            </div>



            <!-- NEW -->

            <div class="asset-row">

                <div class="asset-icon">
                    🆕
                </div>


                <div class="asset-info">

                    <strong>
                        New
                    </strong>

                    <span>
                        Tickets waiting for action
                    </span>

                </div>


                <div class="asset-status">
                    <?= str_pad(
                        (string)$newTickets,
                        2,
                        "0",
                        STR_PAD_LEFT
                    ) ?>
                </div>

            </div>



            <!-- IN PROGRESS -->

            <div class="asset-row">

                <div class="asset-icon">
                    🔧
                </div>


                <div class="asset-info">

                    <strong>
                        In Progress
                    </strong>

                    <span>
                        Currently being handled
                    </span>

                </div>


                <div class="asset-status">
                    <?= str_pad(
                        (string)$inProgressTickets,
                        2,
                        "0",
                        STR_PAD_LEFT
                    ) ?>
                </div>

            </div>



            <!-- RESOLVED -->

            <div class="asset-row">

                <div class="asset-icon">
                    ✅
                </div>


                <div class="asset-info">

                    <strong>
                        Resolved
                    </strong>

                    <span>
                        Successfully completed
                    </span>

                </div>


                <div class="asset-status">
                    <?= str_pad(
                        (string)$resolvedTickets,
                        2,
                        "0",
                        STR_PAD_LEFT
                    ) ?>
                </div>

            </div>


        </section>


    </div>



    <!-- QUICK ACTIONS -->

    <section class="quick-actions">


        <h2>
            Quick Actions
        </h2>


        <div class="quick-action-grid">


            <a
                href="tickets.php"
                class="quick-action-card"
            >

                <span>
                    🎫
                </span>

                <div>

                    <strong>
                        View Assigned Tickets
                    </strong>

                    <p>
                        See all tickets assigned to you.
                    </p>

                </div>

            </a>



            <a
                href="tickets.php"
                class="quick-action-card"
            >

                <span>
                    🔧
                </span>

                <div>

                    <strong>
                        Update Ticket Status
                    </strong>

                    <p>
                        Update the progress of a ticket.
                    </p>

                </div>

            </a>



            <a
                href="tickets.php"
                class="quick-action-card"
            >

                <span>
                    📝
                </span>

                <div>

                    <strong>
                        Add Resolution Notes
                    </strong>

                    <p>
                        Record the solution provided.
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