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

    return "INC-" . $year . "-" . str_pad((string)$id, 3, "0", STR_PAD_LEFT);
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
   TICKET SUMMARY
========================================================= */

$totalTickets = 0;
$newTickets = 0;
$inProgressTickets = 0;
$resolvedTickets = 0;


/* Total tickets */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM tickets
    WHERE employee_id = ?
");

$stmt->bind_param("i", $employee_id);
$stmt->execute();

$result = $stmt->get_result();
$totalTickets = (int)$result->fetch_assoc()["total"];

$stmt->close();


/* New tickets */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM tickets
    WHERE employee_id = ?
    AND status = 'Open'
");

$stmt->bind_param("i", $employee_id);
$stmt->execute();

$result = $stmt->get_result();
$newTickets = (int)$result->fetch_assoc()["total"];

$stmt->close();


/* In Progress tickets */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM tickets
    WHERE employee_id = ?
    AND status = 'In Progress'
");

$stmt->bind_param("i", $employee_id);
$stmt->execute();

$result = $stmt->get_result();
$inProgressTickets = (int)$result->fetch_assoc()["total"];

$stmt->close();


/* Resolved tickets */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM tickets
    WHERE employee_id = ?
    AND status = 'Resolved'
");

$stmt->bind_param("i", $employee_id);
$stmt->execute();

$result = $stmt->get_result();
$resolvedTickets = (int)$result->fetch_assoc()["total"];

$stmt->close();


/* =========================================================
   GET EMPLOYEE TICKETS
========================================================= */

$tickets = [];

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
        t.technician_id,
        tech.full_name AS technician_name
    FROM tickets t
    LEFT JOIN technicians tech
        ON t.technician_id = tech.technician_id
    WHERE t.employee_id = ?
    ORDER BY t.created_at DESC
");

$stmt->bind_param("i", $employee_id);
$stmt->execute();

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $tickets[] = $row;
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

    <title>My Tickets - IT Help Desk</title>

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
                My Support Tickets
            </h1>

            <p>
                View and track all your submitted IT support requests.
            </p>

        </div>


        <a href="create-ticket.php" class="primary-btn">
            + Create New Ticket
        </a>

    </div>


    <!-- TICKET SUMMARY -->

    <div class="dashboard-stats">


        <!-- TOTAL -->

        <div class="dashboard-stat-card">

            <div class="stat-icon">
                🎫
            </div>

            <div>

                <h2>
                    <?= $totalTickets ?>
                </h2>

                <p>
                    Total Tickets
                </p>

            </div>

        </div>


        <!-- NEW -->

        <div class="dashboard-stat-card">

            <div class="stat-icon">
                🆕
            </div>

            <div>

                <h2>
                    <?= $newTickets ?>
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
                    <?= $inProgressTickets ?>
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
                    <?= $resolvedTickets ?>
                </h2>

                <p>
                    Resolved
                </p>

            </div>

        </div>

    </div>


    <!-- SUBMITTED TICKETS -->

    <section class="dashboard-panel ticket-list-panel">


        <div class="panel-header">

            <div>

                <h2>
                    Submitted Tickets
                </h2>

                <p>
                    Track the current status of your support requests.
                </p>

            </div>

        </div>


        <?php if (count($tickets) > 0): ?>


            <?php foreach ($tickets as $ticket): ?>


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

                            <?= e(
                                date(
                                    "d M Y",
                                    strtotime($ticket["created_at"])
                                )
                            ) ?>

                            <?php if (!empty($ticket["technician_name"])): ?>

                                • Technician:
                                <?= e($ticket["technician_name"]) ?>

                            <?php endif; ?>

                        </small>


                    </div>


                    <div class="ticket-status <?= e(statusClass($ticket["status"])) ?>">

                        <?= e(displayStatus($ticket["status"])) ?>

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

                <h3>
                    No Tickets Found
                </h3>

                <p>
                    You have not submitted any support tickets yet.
                </p>

                <br>

                <a
                    href="create-ticket.php"
                    class="primary-btn"
                >
                    Create Your First Ticket
                </a>

            </div>


        <?php endif; ?>


    </section>


    <!-- STATUS INFORMATION -->

    <section class="dashboard-panel ticket-status-guide">


        <div class="panel-header">

            <div>

                <h2>
                    Ticket Status Guide
                </h2>

                <p>
                    Understand the different stages of your support request.
                </p>

            </div>

        </div>


        <div class="status-guide-grid">


            <div class="status-guide-item">

                <span class="guide-number">
                    01
                </span>

                <div>

                    <strong>
                        New
                    </strong>

                    <p>
                        Your ticket has been submitted and is waiting for assignment.
                    </p>

                </div>

            </div>


            <div class="status-guide-item">

                <span class="guide-number">
                    02
                </span>

                <div>

                    <strong>
                        Assigned
                    </strong>

                    <p>
                        A technician has been assigned to handle your issue.
                    </p>

                </div>

            </div>


            <div class="status-guide-item">

                <span class="guide-number">
                    03
                </span>

                <div>

                    <strong>
                        In Progress
                    </strong>

                    <p>
                        The technician is currently working on your issue.
                    </p>

                </div>

            </div>


            <div class="status-guide-item">

                <span class="guide-number">
                    04
                </span>

                <div>

                    <strong>
                        Resolved
                    </strong>

                    <p>
                        The technician has completed the requested support.
                    </p>

                </div>

            </div>


            <div class="status-guide-item">

                <span class="guide-number">
                    05
                </span>

                <div>

                    <strong>
                        Closed
                    </strong>

                    <p>
                        The ticket has been completed and officially closed.
                    </p>

                </div>

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