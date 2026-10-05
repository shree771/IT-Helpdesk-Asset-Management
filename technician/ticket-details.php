
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

$user_id = (int) $_SESSION["user_id"];

$technicianQuery = $conn->prepare("
    SELECT technician_id, technician_code, full_name,
           specialization, phone, availability
    FROM technicians
    WHERE user_id = ?
    LIMIT 1
");

$technicianQuery->bind_param("i", $user_id);
$technicianQuery->execute();
$technician = $technicianQuery->get_result()->fetch_assoc();
$technicianQuery->close();

if (!$technician) {
    session_destroy();
    header("Location: ../login.php");
    exit;
}

$technician_id = (int) $technician["technician_id"];

/* =========================================================
   HELPER FUNCTIONS
========================================================= */

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

function ticketId($id, $createdAt = null)
{
    $year = $createdAt ? date("Y", strtotime($createdAt)) : date("Y");

    return "INC-" . $year . "-" . str_pad((string) $id, 3, "0", STR_PAD_LEFT);
}

function displayStatus($status)
{
    return $status === "Open" ? "New" : $status;
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
   GET TICKET ID
========================================================= */

$ticket_id = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

if ($ticket_id <= 0) {
    header("Location: tickets.php");
    exit;
}

$successMessage = "";
$errorMessage = "";

/* =========================================================
   UPDATE TICKET STATUS AND SAVE TECHNICIAN NOTES
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $postedTicketId = (int) ($_POST["ticket_id"] ?? 0);

    if ($postedTicketId !== $ticket_id) {
        $errorMessage = "Invalid ticket request.";

    } elseif (isset($_POST["resolve_ticket"])) {

        // Mark as resolved while retaining the existing notes.
        $resolveQuery = $conn->prepare("
            UPDATE tickets
            SET status = 'Resolved'
            WHERE ticket_id = ?
              AND technician_id = ?
        ");

        $resolveQuery->bind_param(
            "ii",
            $ticket_id,
            $technician_id
        );

        if ($resolveQuery->execute()) {
            if ($resolveQuery->affected_rows > 0) {
                $successMessage = "Ticket has been marked as resolved.";
            } else {
                $errorMessage = "Ticket is already resolved or could not be updated.";
            }
        } else {
            $errorMessage = "Unable to resolve this ticket.";
        }

        $resolveQuery->close();

    } else {

        $newStatus = trim($_POST["ticket_status"] ?? "");
        $resolutionNotes = trim($_POST["resolution_notes"] ?? "");
        $technicianComment = trim($_POST["technician_comment"] ?? "");

        // The database stores Open, not Assigned.
        if ($newStatus === "Assigned") {
            $newStatus = "Open";
        }

        $allowedStatuses = [
            "Open",
            "In Progress",
            "Resolved",
            "Closed"
        ];

        if (!in_array($newStatus, $allowedStatuses, true)) {
            $errorMessage = "Please select a valid ticket status.";

        } else {

            // Save all three fields for this technician's ticket.
            $updateQuery = $conn->prepare("
                UPDATE tickets
                SET status = ?,
                    resolution_notes = ?,
                    technician_comment = ?
                WHERE ticket_id = ?
                  AND technician_id = ?
            ");

            $updateQuery->bind_param(
                "sssii",
                $newStatus,
                $resolutionNotes,
                $technicianComment,
                $ticket_id,
                $technician_id
            );

            if ($updateQuery->execute()) {

                // Check ownership even if the values did not change.
                $checkQuery = $conn->prepare("
                    SELECT ticket_id
                    FROM tickets
                    WHERE ticket_id = ?
                      AND technician_id = ?
                    LIMIT 1
                ");

                $checkQuery->bind_param(
                    "ii",
                    $ticket_id,
                    $technician_id
                );

                $checkQuery->execute();
                $checkResult = $checkQuery->get_result();

                if ($checkResult->num_rows === 1) {
                    $successMessage = "Ticket status and notes saved successfully.";
                } else {
                    $errorMessage = "You are not authorized to update this ticket.";
                }

                $checkQuery->close();

            } else {
                $errorMessage = "Unable to save ticket details.";
            }

            $updateQuery->close();
        }
    }
}

/* =========================================================
   GET TICKET DETAILS
========================================================= */

$ticketQuery = $conn->prepare("
    SELECT
        t.ticket_id,
        t.title,
        t.description,
        t.category,
        t.priority,
        t.status,
        t.created_at,
        t.updated_at,
        t.resolution_notes,
        t.technician_comment,
        e.employee_id,
        e.employee_code,
        e.full_name AS employee_name,
        e.department,
        e.phone AS employee_phone
    FROM tickets t
    INNER JOIN employees e
        ON t.employee_id = e.employee_id
    WHERE t.ticket_id = ?
      AND t.technician_id = ?
    LIMIT 1
");

$ticketQuery->bind_param(
    "ii",
    $ticket_id,
    $technician_id
);

$ticketQuery->execute();
$ticket = $ticketQuery->get_result()->fetch_assoc();
$ticketQuery->close();

if (!$ticket) {
    http_response_code(403);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Ticket Not Available - IT Help Desk</title>
        <link rel="stylesheet" href="../css/style.css">
    </head>
    <body>
        <header class="navbar">
            <div class="logo"><span>IT</span> Help Desk</div>
            <nav>
                <a href="dashboard.php">Dashboard</a>
                <a href="tickets.php">Assigned Tickets</a>
                <a href="../logout.php" class="login-btn">Logout</a>
            </nav>
        </header>

        <main class="dashboard-page">
            <section class="dashboard-panel">
                <div style="text-align:center; padding:60px 30px;">
                    <div style="font-size:50px;">⚠️</div>
                    <h2>Ticket Not Available</h2>
                    <p>This ticket does not exist or is not assigned to you.</p>
                    <br>
                    <a href="tickets.php" class="primary-btn">
                        ← Back to Assigned Tickets
                    </a>
                </div>
            </section>
        </main>
    </body>
    </html>
    <?php
    exit;
}

/* =========================================================
   PREPARE DISPLAY VALUES
========================================================= */

$displayTicketId = ticketId(
    $ticket["ticket_id"],
    $ticket["created_at"]
);

$displayStatus = displayStatus($ticket["status"]);
$statusClassName = statusClass($ticket["status"]);
$priorityClassName = priorityClass($ticket["priority"]);

$currentStatus = $ticket["status"];

$step1Active = false;
$step2Active = false;
$step3Active = false;
$step4Active = false;
$step5Active = false;

if ($currentStatus === "Open") {
    $step1Active = true;
} elseif ($currentStatus === "In Progress") {
    $step1Active = true;
    $step2Active = true;
    $step3Active = true;
} elseif ($currentStatus === "Resolved") {
    $step1Active = true;
    $step2Active = true;
    $step3Active = true;
    $step4Active = true;
} elseif ($currentStatus === "Closed") {
    $step1Active = true;
    $step2Active = true;
    $step3Active = true;
    $step4Active = true;
    $step5Active = true;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Details - IT Help Desk</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body>

<header class="navbar">
    <div class="logo"><span>IT</span> Help Desk</div>
    <nav>
        <a href="dashboard.php">Dashboard</a>
        <a href="tickets.php">Assigned Tickets</a>
        <a href="../logout.php" class="login-btn">Logout</a>
    </nav>
</header>

<main class="dashboard-page">

    <div class="dashboard-header">
        <div>
            <p class="dashboard-label">TECHNICIAN PORTAL</p>
            <h1>Ticket Details</h1>
            <p>Review the issue and update the support ticket.</p>
        </div>
        <a href="tickets.php" class="secondary-btn">← Back to Tickets</a>
    </div>

    <?php if ($successMessage !== ""): ?>
        <div style="background:#e8f8ef;border:1px solid #9ed8b7;color:#176b43;padding:15px 20px;border-radius:10px;margin-bottom:20px;">
            <?= e($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage !== ""): ?>
        <div style="background:#fff0f0;border:1px solid #e2a6a6;color:#a32929;padding:15px 20px;border-radius:10px;margin-bottom:20px;">
            <?= e($errorMessage) ?>
        </div>
    <?php endif; ?>

    <!-- TICKET INFORMATION -->
    <section class="dashboard-panel ticket-details-panel">
        <div class="panel-header">
            <div>
                <h2><?= e($displayTicketId) ?></h2>
                <p>
                    Ticket submitted on
                    <?= e(date("d F Y", strtotime($ticket["created_at"]))) ?>
                </p>
            </div>
            <div class="ticket-status <?= e($statusClassName) ?>">
                <?= e($displayStatus) ?>
            </div>
        </div>

        <div class="ticket-details-content">

            <div class="ticket-detail-section">
                <p class="detail-label">ISSUE TITLE</p>
                <h2><?= e($ticket["title"]) ?></h2>
            </div>

            <div class="ticket-detail-section">
                <p class="detail-label">DESCRIPTION</p>
                <p class="detail-description">
                    <?= nl2br(e($ticket["description"])) ?>
                </p>
            </div>

            <div class="ticket-information-grid">

                <div class="information-box">
                    <span>Employee</span>
                    <strong><?= e($ticket["employee_name"]) ?></strong>
                </div>

                <div class="information-box">
                    <span>Department</span>
                    <strong><?= e($ticket["department"]) ?></strong>
                </div>

                <div class="information-box">
                    <span>Priority</span>
                    <strong class="<?= e($priorityClassName) ?>">
                        <?= e($ticket["priority"]) ?>
                    </strong>
                </div>

                <div class="information-box">
                    <span>Category</span>
                    <strong><?= e($ticket["category"]) ?></strong>
                </div>

                <div class="information-box">
                    <span>Employee Code</span>
                    <strong><?= e($ticket["employee_code"]) ?></strong>
                </div>

                <div class="information-box">
                    <span>Employee Phone</span>
                    <strong><?= e($ticket["employee_phone"] ?: "Not provided") ?></strong>
                </div>

                <div class="information-box">
                    <span>Assigned Technician</span>
                    <strong><?= e($technician["full_name"]) ?></strong>
                </div>

                <div class="information-box">
                    <span>Technician Code</span>
                    <strong><?= e($technician["technician_code"]) ?></strong>
                </div>

            </div>
        </div>
    </section>

    <!-- TICKET WORKFLOW -->
    <section class="dashboard-panel ticket-workflow-panel">
        <div class="panel-header">
            <div>
                <h2>Ticket Workflow</h2>
                <p>Current progress of this support ticket.</p>
            </div>
        </div>

        <div class="ticket-progress">

            <div class="progress-step <?= $step1Active ? "active-step" : "" ?>">
                <div class="progress-circle">01</div>
                <strong>New</strong>
                <span>Ticket received</span>
            </div>

            <div class="progress-line"></div>

            <div class="progress-step <?= $step2Active ? "active-step" : "" ?>">
                <div class="progress-circle">02</div>
                <strong>Assigned</strong>
                <span>Technician assigned</span>
            </div>

            <div class="progress-line"></div>

            <div class="progress-step <?= $step3Active ? "active-step" : "" ?>">
                <div class="progress-circle">03</div>
                <strong>In Progress</strong>
                <span>Issue being handled</span>
            </div>

            <div class="progress-line"></div>

            <div class="progress-step <?= $step4Active ? "active-step" : "" ?>">
                <div class="progress-circle">04</div>
                <strong>Resolved</strong>
                <span>Issue resolved</span>
            </div>

            <div class="progress-line"></div>

            <div class="progress-step <?= $step5Active ? "active-step" : "" ?>">
                <div class="progress-circle">05</div>
                <strong>Closed</strong>
                <span>Ticket completed</span>
            </div>

        </div>
    </section>

    <!-- UPDATE TICKET -->
    <section class="dashboard-panel ticket-update-panel">

        <div class="panel-header">
            <div>
                <h2>Update Ticket</h2>
                <p>Update the ticket status and save technician notes.</p>
            </div>
        </div>

        <form method="POST" class="ticket-form">

            <input
                type="hidden"
                name="ticket_id"
                value="<?= (int) $ticket["ticket_id"] ?>"
            >

            <div class="form-group">
                <label for="ticketStatus">Ticket Status</label>

                <select id="ticketStatus" name="ticket_status" required>
                    <option value="">Select ticket status</option>

                    <option
                        value="Assigned"
                        <?= $ticket["status"] === "Open" ? "selected" : "" ?>
                    >Assigned / New</option>

                    <option
                        value="In Progress"
                        <?= $ticket["status"] === "In Progress" ? "selected" : "" ?>
                    >In Progress</option>

                    <option
                        value="Resolved"
                        <?= $ticket["status"] === "Resolved" ? "selected" : "" ?>
                    >Resolved</option>

                    <option
                        value="Closed"
                        <?= $ticket["status"] === "Closed" ? "selected" : "" ?>
                    >Closed</option>
                </select>
            </div>

            <div class="form-group">
                <label for="resolutionNotes">Resolution / Work Notes</label>
                <textarea
                    id="resolutionNotes"
                    name="resolution_notes"
                    placeholder="Enter troubleshooting steps, actions taken, and resolution details..."
                ><?= e($ticket["resolution_notes"] ?? "") ?></textarea>
            </div>

            <div class="form-group">
                <label for="technicianComment">Technician Comment</label>
                <textarea
                    id="technicianComment"
                    name="technician_comment"
                    placeholder="Add additional information for the employee or administrator..."
                ><?= e($ticket["technician_comment"] ?? "") ?></textarea>
            </div>

            <button type="submit" class="auth-btn">
                Save Ticket Details
            </button>

        </form>
    </section>

    <!-- ACTIONS -->
    <section class="ticket-action-section">

        <a href="tickets.php" class="secondary-btn">
            ← Back to Assigned Tickets
        </a>

        <?php if ($ticket["status"] !== "Resolved" && $ticket["status"] !== "Closed"): ?>
            <form method="POST" style="display:inline;">
                <input
                    type="hidden"
                    name="ticket_id"
                    value="<?= (int) $ticket["ticket_id"] ?>"
                >

                <input type="hidden" name="resolve_ticket" value="1">

                <button
                    type="submit"
                    class="primary-btn"
                    onclick="return confirm('Are you sure you want to mark this ticket as resolved?');"
                >
                    Mark Ticket as Resolved
                </button>
            </form>
        <?php endif; ?>

    </section>

</main>

<footer>
    <div class="footer-content">
        <div>
            <h3>IT Help Desk</h3>
            <p>Reliable IT support and asset management system.</p>
        </div>
        <p>© 2026 IT Help Desk</p>
    </div>
</footer>

</body>
</html>