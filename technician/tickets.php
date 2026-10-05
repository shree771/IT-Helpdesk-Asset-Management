
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

    return "INC-" .
        $year .
        "-" .
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


function priorityClass($priority)
{
    switch ($priority) {

        case "High":
        case "Critical":
            return "priority-high";

        case "Medium":
            return "priority-medium";

        case "Low":
            return "priority-low";

        default:
            return "priority-medium";
    }
}


/* =========================================================
   TICKET STATISTICS
========================================================= */


/* Total assigned */

$stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM tickets
    WHERE technician_id = ?
");

$stmt->bind_param("i", $technician_id);
$stmt->execute();

$result = $stmt->get_result();

$totalAssigned = (int)$result->fetch_assoc()["total"];

$stmt->close();


/* New */

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


/* In Progress */

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


/* Resolved */

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
   GET ASSIGNED TICKETS
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
        e.employee_id,
        e.employee_code,
        e.full_name AS employee_name,
        e.department,
        e.phone
    FROM tickets t
    INNER JOIN employees e
        ON t.employee_id = e.employee_id
    WHERE t.technician_id = ?
    ORDER BY t.created_at DESC
");

$stmt->bind_param("i", $technician_id);
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

    <title>Assigned Tickets - IT Help Desk</title>

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


    <!-- PAGE HEADER -->

    <div class="dashboard-header">

        <div>

            <p class="dashboard-label">
                TECHNICIAN PORTAL
            </p>

            <h1>
                Assigned Tickets
            </h1>

            <p>
                View and manage support tickets assigned to you.
            </p>

        </div>

    </div>



    <!-- TICKET STATISTICS -->

    <div class="dashboard-stats">


        <!-- TOTAL ASSIGNED -->

        <div class="dashboard-stat-card">

            <div class="stat-icon">
                🎫
            </div>

            <div>

                <h2>
                    <?= str_pad(
                        (string)$totalAssigned,
                        2,
                        "0",
                        STR_PAD_LEFT
                    ) ?>
                </h2>

                <p>
                    Total Assigned
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
                    <?= str_pad(
                        (string)$newTickets,
                        2,
                        "0",
                        STR_PAD_LEFT
                    ) ?>
                </h2>

                <p>
                    New
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



    <!-- TICKET LIST -->

    <section class="dashboard-panel technician-ticket-panel">


        <div class="panel-header">

            <div>

                <h2>
                    Support Tickets
                </h2>

                <p>
                    Review employee issues and update their progress.
                </p>

            </div>

        </div>



        <?php if (count($tickets) > 0): ?>


            <?php foreach ($tickets as $ticket): ?>


                <div class="technician-ticket-row">


                    <!-- MAIN INFORMATION -->

                    <div class="technician-ticket-main">


                        <div class="ticket-id">

                            <?= e(
                                ticketId(
                                    $ticket["ticket_id"],
                                    $ticket["created_at"]
                                )
                            ) ?>

                        </div>


                        <h3>
                            <?= e($ticket["title"]) ?>
                        </h3>


                        <p>
                            Employee:
                            <?= e($ticket["employee_name"]) ?>
                        </p>


                    </div>



                    <!-- PRIORITY -->

                    <div class="ticket-priority <?= e(
                        priorityClass($ticket["priority"])
                    ) ?>">

                        <?= e($ticket["priority"]) ?>

                    </div>



                    <!-- DATE -->

                    <div class="ticket-date">

                        <span>
                            Submitted
                        </span>

                        <strong>

                            <?= e(
                                date(
                                    "d M Y",
                                    strtotime($ticket["created_at"])
                                )
                            ) ?>

                        </strong>

                    </div>



                    <!-- STATUS -->

                    <div class="ticket-status <?= e(
                        statusClass($ticket["status"])
                    ) ?>">

                        <?= e(
                            displayStatus($ticket["status"])
                        ) ?>

                    </div>



                    <!-- VIEW DETAILS -->

                    <a
                        href="ticket-details.php?id=<?= (int)$ticket["ticket_id"] ?>"
                        class="ticket-view-btn"
                    >
                        View Details →
                    </a>


                </div>


            <?php endforeach; ?>


        <?php else: ?>


            <div
                style="
                    padding: 45px 30px;
                    text-align: center;
                "
            >

                <div style="font-size: 45px;">
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



    <!-- WORKFLOW -->

    <section class="dashboard-panel technician-workflow">


        <div class="panel-header">

            <div>

                <h2>
                    Ticket Handling Workflow
                </h2>

                <p>
                    Follow these steps when handling an assigned ticket.
                </p>

            </div>

        </div>



        <div class="technician-workflow-grid">


            <!-- STEP 1 -->

            <div class="workflow-step">

                <span>
                    01
                </span>

                <h3>
                    Review
                </h3>

                <p>
                    Review the employee's reported issue and ticket details.
                </p>

            </div>



            <!-- STEP 2 -->

            <div class="workflow-step">

                <span>
                    02
                </span>

                <h3>
                    Accept
                </h3>

                <p>
                    Accept the ticket and begin working on the reported issue.
                </p>

            </div>



            <!-- STEP 3 -->

            <div class="workflow-step">

                <span>
                    03
                </span>

                <h3>
                    Update
                </h3>

                <p>
                    Update the ticket status while troubleshooting the issue.
                </p>

            </div>



            <!-- STEP 4 -->

            <div class="workflow-step">

                <span>
                    04
                </span>

                <h3>
                    Resolve
                </h3>

                <p>
                    Add resolution notes and mark the ticket as resolved.
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