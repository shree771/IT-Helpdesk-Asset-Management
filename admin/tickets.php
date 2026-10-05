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

function displayStatus($status)
{
    if ($status === "Open") {
        return "New";
    }

    return $status;
}

function ticketId($id)
{
    return "INC-" . date("Y") . "-" . str_pad($id, 3, "0", STR_PAD_LEFT);
}

/* =========================================================
   MESSAGE
========================================================= */

$message = "";
$messageType = "";

/* =========================================================
   HANDLE POST REQUESTS
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $action = $_POST["action"] ?? "";

    /* =====================================================
       CREATE NEW TICKET
    ===================================================== */

    if ($action === "create_ticket") {

        $employee_id = (int)($_POST["employee_id"] ?? 0);
        $title = trim($_POST["title"] ?? "");
        $description = trim($_POST["description"] ?? "");
        $category = trim($_POST["category"] ?? "");
        $priority = trim($_POST["priority"] ?? "Medium");

        $allowedPriorities = [
            "Low",
            "Medium",
            "High",
            "Critical"
        ];

        $allowedCategories = [
            "Network",
            "Hardware",
            "Software",
            "Email",
            "Access",
            "Performance"
        ];

        if (
            $employee_id <= 0 ||
            $title === "" ||
            $description === "" ||
            $category === ""
        ) {

            $message = "Please fill in all required ticket details.";
            $messageType = "error";

        } elseif (!in_array($priority, $allowedPriorities, true)) {

            $message = "Invalid priority selected.";
            $messageType = "error";

        } elseif (!in_array($category, $allowedCategories, true)) {

            $message = "Invalid category selected.";
            $messageType = "error";

        } else {

            /* Check employee exists */

            $checkEmployee = $conn->prepare(
                "SELECT employee_id
                 FROM employees
                 WHERE employee_id = ?
                 LIMIT 1"
            );

            $checkEmployee->bind_param(
                "i",
                $employee_id
            );

            $checkEmployee->execute();

            $employeeResult = $checkEmployee->get_result();

            if ($employeeResult->num_rows !== 1) {

                $message = "Selected employee was not found.";
                $messageType = "error";

            } else {

                /* Insert ticket */

                $insert = $conn->prepare(
                    "INSERT INTO tickets
                    (
                        employee_id,
                        technician_id,
                        title,
                        description,
                        category,
                        priority,
                        status
                    )
                    VALUES
                    (
                        ?,
                        NULL,
                        ?,
                        ?,
                        ?,
                        ?,
                        'Open'
                    )"
                );

                $insert->bind_param(
                    "issss",
                    $employee_id,
                    $title,
                    $description,
                    $category,
                    $priority
                );

                if ($insert->execute()) {

                    $message =
                        "Ticket created successfully. Ticket ID: " .
                        ticketId($insert->insert_id);

                    $messageType = "success";

                } else {

                    $message =
                        "Failed to create ticket: " .
                        $conn->error;

                    $messageType = "error";
                }

                $insert->close();
            }

            $checkEmployee->close();
        }
    }

    /* =====================================================
       ASSIGN TICKET
    ===================================================== */

    if ($action === "assign_ticket") {

        $ticket_id = (int)($_POST["ticket_id"] ?? 0);
        $technician_id = (int)($_POST["technician_id"] ?? 0);
        $status = trim($_POST["status"] ?? "Open");

        $allowedStatuses = [
            "Open",
            "In Progress",
            "Resolved",
            "Closed"
        ];

        if (
            $ticket_id <= 0 ||
            $technician_id <= 0
        ) {

            $message = "Please select a ticket and technician.";
            $messageType = "error";

        } elseif (
            !in_array($status, $allowedStatuses, true)
        ) {

            $message = "Invalid ticket status.";
            $messageType = "error";

        } else {

            /* Check ticket exists */

            $checkTicket = $conn->prepare(
                "SELECT ticket_id
                 FROM tickets
                 WHERE ticket_id = ?
                 LIMIT 1"
            );

            $checkTicket->bind_param(
                "i",
                $ticket_id
            );

            $checkTicket->execute();

            $ticketResult = $checkTicket->get_result();

            if ($ticketResult->num_rows !== 1) {

                $message = "Selected ticket was not found.";
                $messageType = "error";

            } else {

                /* Check technician exists */

                $checkTechnician = $conn->prepare(
                    "SELECT technician_id
                     FROM technicians
                     WHERE technician_id = ?
                     LIMIT 1"
                );

                $checkTechnician->bind_param(
                    "i",
                    $technician_id
                );

                $checkTechnician->execute();

                $technicianResult =
                    $checkTechnician->get_result();

                if ($technicianResult->num_rows !== 1) {

                    $message =
                        "Selected technician was not found.";

                    $messageType = "error";

                } else {

                    /* Update ticket */

                    $update = $conn->prepare(
                        "UPDATE tickets
                         SET technician_id = ?,
                             status = ?
                         WHERE ticket_id = ?"
                    );

                    $update->bind_param(
                        "isi",
                        $technician_id,
                        $status,
                        $ticket_id
                    );

                    if ($update->execute()) {

                        $message =
                            "Ticket assigned successfully.";

                        $messageType = "success";

                    } else {

                        $message =
                            "Failed to update ticket: " .
                            $conn->error;

                        $messageType = "error";
                    }

                    $update->close();
                }

                $checkTechnician->close();
            }

            $checkTicket->close();
        }
    }
}

/* =========================================================
   GET STATISTICS
========================================================= */

$totalTickets = 0;
$newTickets = 0;
$assignedTickets = 0;
$inProgressTickets = 0;
$resolvedTickets = 0;
$closedTickets = 0;

/* Total */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM tickets"
);

if ($result) {

    $row = $result->fetch_assoc();

    $totalTickets = (int)$row["total"];
}

/* New = Open + Not Assigned */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM tickets
     WHERE status = 'Open'
     AND technician_id IS NULL"
);

if ($result) {

    $row = $result->fetch_assoc();

    $newTickets = (int)$row["total"];
}

/* Assigned = Open + Technician Assigned */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM tickets
     WHERE status = 'Open'
     AND technician_id IS NOT NULL"
);

if ($result) {

    $row = $result->fetch_assoc();

    $assignedTickets = (int)$row["total"];
}

/* In Progress */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM tickets
     WHERE status = 'In Progress'"
);

if ($result) {

    $row = $result->fetch_assoc();

    $inProgressTickets = (int)$row["total"];
}

/* Resolved */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM tickets
     WHERE status = 'Resolved'"
);

if ($result) {

    $row = $result->fetch_assoc();

    $resolvedTickets = (int)$row["total"];
}

/* Closed */

$result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM tickets
     WHERE status = 'Closed'"
);

if ($result) {

    $row = $result->fetch_assoc();

    $closedTickets = (int)$row["total"];
}

/* =========================================================
   GET EMPLOYEES
========================================================= */

$employees = [];

$result = $conn->query(
    "SELECT
        employee_id,
        employee_code,
        full_name,
        department
     FROM employees
     ORDER BY full_name ASC"
);

if ($result) {

    while ($row = $result->fetch_assoc()) {

        $employees[] = $row;
    }
}

/* =========================================================
   GET TICKETS
========================================================= */

$tickets = [];

$sql = "
    SELECT
        t.ticket_id,
        t.employee_id,
        t.technician_id,
        t.title,
        t.description,
        t.category,
        t.priority,
        t.status,
        t.created_at,
        t.updated_at,

        e.full_name AS employee_name,
        e.employee_code,

        tech.full_name AS technician_name,
        tech.technician_code

    FROM tickets t

    INNER JOIN employees e
        ON t.employee_id = e.employee_id

    LEFT JOIN technicians tech
        ON t.technician_id = tech.technician_id

    ORDER BY t.created_at DESC
";

$result = $conn->query($sql);

if ($result) {

    while ($row = $result->fetch_assoc()) {

        $tickets[] = $row;
    }
}

/* =========================================================
   GET TECHNICIANS
========================================================= */

$technicians = [];

$result = $conn->query(
    "SELECT
        technician_id,
        technician_code,
        full_name,
        specialization,
        availability
     FROM technicians
     ORDER BY full_name ASC"
);

if ($result) {

    while ($row = $result->fetch_assoc()) {

        $technicians[] = $row;
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

<title>Ticket Management - IT Help Desk</title>

<style>

/* =========================================================
   GENERAL
========================================================= */

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: #effaf4;
    color: #063f2b;
}

/* =========================================================
   NAVBAR
========================================================= */

.navbar {
    background: #075b35;
    min-height: 72px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 38px;
    color: white;
}

.logo {
    font-size: 24px;
    font-weight: 800;
}

.logo span {
    background: #21c866;
    padding: 8px 10px;
    border-radius: 7px;
    margin-right: 7px;
}

.nav-links {
    display: flex;
    align-items: center;
    gap: 8px;
}

.nav-links a {
    color: white;
    text-decoration: none;
    padding: 12px 15px;
    border-radius: 7px;
    font-weight: 600;
}

.nav-links a:hover {
    background: #0d7144;
}

.nav-links a.active {
    background: #20c867;
}

.nav-login {
    background: #0d7144;
}

/* =========================================================
   MAIN
========================================================= */

.container {
    width: 92%;
    max-width: 1450px;
    margin: 45px auto;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 28px;
}

.page-header h1 {
    margin: 5px 0;
    font-size: 38px;
}

.page-header p {
    margin: 0;
    color: #648075;
    font-size: 16px;
}

.label {
    color: #087448;
    font-size: 13px;
    font-weight: 800;
    letter-spacing: 2px;
}

/* =========================================================
   BUTTONS
========================================================= */

.button-group {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.primary-btn {
    background: #075b35;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 14px 22px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
}

.primary-btn:hover {
    background: #06482b;
}

.secondary-btn {
    border: none;
    background: #e4f5eb;
    color: #075b35;
    padding: 13px 20px;
    border-radius: 8px;
    font-weight: 700;
    cursor: pointer;
}

.secondary-btn:hover {
    background: #d5f0df;
}

.action-btn {
    border: 1px solid #b9e7ce;
    background: white;
    color: #075b35;
    border-radius: 7px;
    padding: 8px 12px;
    cursor: pointer;
    font-weight: 700;
    margin-right: 5px;
}

.action-btn:hover {
    background: #e8faef;
}

/* =========================================================
   MESSAGE
========================================================= */

.message {
    padding: 16px 20px;
    border-radius: 10px;
    margin-bottom: 25px;
    font-weight: 600;
}

.message.success {
    background: #dcf8e8;
    border: 1px solid #9de5bd;
    color: #08683b;
}

.message.error {
    background: #ffe5e5;
    border: 1px solid #f1aaaa;
    color: #a00000;
}

/* =========================================================
   STATISTICS
========================================================= */

.stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card {
    background: white;
    border: 1px solid #ccebdd;
    border-radius: 15px;
    padding: 25px;
    display: flex;
    align-items: center;
    gap: 18px;
    box-shadow: 0 8px 25px rgba(0, 80, 45, 0.05);
}

.stat-icon {
    width: 52px;
    height: 52px;
    background: #dcf9e8;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 21px;
    font-weight: 800;
    color: #087448;
}

.stat-card span {
    display: block;
    color: #6b8378;
    margin-bottom: 5px;
}

.stat-card strong {
    font-size: 28px;
}

/* =========================================================
   PANEL
========================================================= */

.panel {
    background: white;
    border: 1px solid #ccebdd;
    border-radius: 15px;
    padding: 25px;
    margin-bottom: 25px;
    box-shadow: 0 8px 25px rgba(0, 80, 45, 0.05);
}

.panel-title {
    margin-bottom: 20px;
}

.panel-title h2 {
    margin: 0 0 5px;
}

.panel-title p {
    margin: 0;
    color: #6b8378;
}

/* =========================================================
   WORKFLOW
========================================================= */

.workflow {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
}

.workflow-step {
    flex: 1;
    text-align: center;
    padding: 18px;
    border: 1px solid #d5eee0;
    border-radius: 12px;
}

.workflow-number {
    font-weight: 800;
    font-size: 13px;
    color: #087448;
}

.workflow-step h3 {
    margin: 8px 0;
}

.workflow-step span {
    color: #71877d;
    font-size: 14px;
}

.workflow-arrow {
    font-size: 25px;
    font-weight: bold;
    color: #0a7b47;
}

/* =========================================================
   FILTERS
========================================================= */

.filters {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr;
    gap: 15px;
}

.form-group label {
    display: block;
    font-weight: 700;
    margin-bottom: 8px;
}

input,
select,
textarea {
    width: 100%;
    padding: 13px 14px;
    border: 1px solid #bfe5d0;
    border-radius: 8px;
    font-size: 14px;
    background: white;
    outline: none;
}

textarea {
    min-height: 130px;
    resize: vertical;
}

input:focus,
select:focus,
textarea:focus {
    border-color: #20c867;
}

/* =========================================================
   TABLE
========================================================= */

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1200px;
}

th {
    text-align: left;
    background: #effbf4;
    color: #075b35;
    padding: 15px;
    font-size: 13px;
}

td {
    padding: 16px 15px;
    border-bottom: 1px solid #e5f1eb;
    font-size: 14px;
}

tr:hover td {
    background: #fbfffc;
}

.ticket-id {
    font-weight: 800;
    color: #087448;
}

.issue-title {
    font-weight: 700;
    display: block;
}

.issue-description {
    display: block;
    color: #788b82;
    margin-top: 5px;
    font-size: 12px;
}

/* =========================================================
   BADGES
========================================================= */

.badge {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
}

.priority-critical {
    background: #ffd4d4;
    color: #8b0000;
}

.priority-high {
    background: #ffe2e2;
    color: #b00000;
}

.priority-medium {
    background: #fff1cf;
    color: #996400;
}

.priority-low {
    background: #dcf6e7;
    color: #087448;
}

.status-new {
    background: #e5f1ff;
    color: #24629b;
}

.status-assigned {
    background: #e8e0ff;
    color: #6842a5;
}

.status-progress {
    background: #fff0d5;
    color: #9a6200;
}

.status-resolved {
    background: #dcf8e8;
    color: #087448;
}

.status-closed {
    background: #e8e8e8;
    color: #555;
}

/* =========================================================
   MODAL
========================================================= */

.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 40, 25, 0.55);
    padding: 40px 20px;
    overflow-y: auto;
}

.modal-content {
    max-width: 800px;
    margin: auto;
    background: white;
    border-radius: 15px;
    overflow: hidden;
}

.modal-header {
    background: #075b35;
    color: white;
    padding: 22px 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h2 {
    margin: 0;
}

.close {
    font-size: 28px;
    cursor: pointer;
}

.modal-body {
    padding: 25px;
}

.detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}

.detail-box {
    background: #f4fbf7;
    padding: 15px;
    border-radius: 10px;
}

.detail-box.full {
    grid-column: 1 / -1;
}

.detail-label {
    font-size: 12px;
    color: #6c8177;
    margin-bottom: 5px;
}

.detail-value {
    font-weight: 700;
    white-space: pre-wrap;
}

/* =========================================================
   CREATE / ASSIGN FORM
========================================================= */

.assignment-grid,
.create-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.full-width {
    grid-column: 1 / -1;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 20px;
}

/* =========================================================
   FOOTER
========================================================= */

footer {
    background: #075b35;
    color: white;
    text-align: center;
    padding: 30px;
    margin-top: 50px;
}

/* =========================================================
   RESPONSIVE
========================================================= */

@media (max-width: 1100px) {

    .navbar {
        flex-wrap: wrap;
        gap: 15px;
        padding: 15px 25px;
    }

    .nav-links {
        flex-wrap: wrap;
        justify-content: center;
    }

    .stats {
        grid-template-columns: repeat(2, 1fr);
    }

    .filters {
        grid-template-columns: 1fr 1fr;
    }

    .workflow {
        flex-wrap: wrap;
    }

    .workflow-step {
        min-width: 150px;
    }

    .workflow-arrow {
        display: none;
    }
}

@media (max-width: 700px) {

    .navbar {
        flex-direction: column;
        gap: 15px;
        padding: 20px;
    }

    .nav-links {
        flex-wrap: wrap;
        justify-content: center;
    }

    .stats,
    .filters,
    .assignment-grid,
    .create-grid,
    .detail-grid {
        grid-template-columns: 1fr;
    }

    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 20px;
    }

    .full-width {
        grid-column: auto;
    }

    .button-group {
        width: 100%;
    }

    .button-group button {
        width: 100%;
    }
}

</style>

</head>

<body>

<!-- =====================================================
     NAVIGATION
===================================================== -->

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

        <a href="tickets.php" class="active">
            Tickets
        </a>

        <a href="reports.php">
            Reports
        </a>

        <a href="assets.php">
            Assets
        </a>

        <a href="../logout.php" class="nav-login">
            Logout
        </a>

    </nav>

</header>

<main class="container">

<!-- =====================================================
     PAGE HEADER
===================================================== -->

<section class="page-header">

    <div>

        <div class="label">
            ADMINISTRATION
        </div>

        <h1>
            Support Ticket Management
        </h1>

        <p>
            Manage, create, assign and monitor all IT support tickets.
        </p>

    </div>

    <div class="button-group">

        <button
            class="primary-btn"
            type="button"
            onclick="openCreateTicket()"
        >
            + Create New Ticket
        </button>

        <button
            class="primary-btn"
            type="button"
            onclick="openAssignment()"
        >
            + Assign Ticket
        </button>

    </div>

</section>

<!-- =====================================================
     MESSAGE
===================================================== -->

<?php if ($message !== ""): ?>

    <div class="message <?php echo e($messageType); ?>">

        <?php echo e($message); ?>

    </div>

<?php endif; ?>

<!-- =====================================================
     STATISTICS
===================================================== -->

<section class="stats">

    <div class="stat-card">

        <div class="stat-icon">
            T
        </div>

        <div>

            <span>Total Tickets</span>

            <strong>
                <?php echo $totalTickets; ?>
            </strong>

        </div>

    </div>

    <div class="stat-card">

        <div class="stat-icon">
            N
        </div>

        <div>

            <span>New Tickets</span>

            <strong>
                <?php echo $newTickets; ?>
            </strong>

        </div>

    </div>

    <div class="stat-card">

        <div class="stat-icon">
            A
        </div>

        <div>

            <span>Assigned Tickets</span>

            <strong>
                <?php echo $assignedTickets; ?>
            </strong>

        </div>

    </div>

    <div class="stat-card">

        <div class="stat-icon">
            P
        </div>

        <div>

            <span>In Progress</span>

            <strong>
                <?php echo $inProgressTickets; ?>
            </strong>

        </div>

    </div>

</section>

<!-- =====================================================
     SECONDARY STATISTICS
===================================================== -->

<section class="panel">

    <div class="stats">

        <div class="stat-card">

            <div class="stat-icon">
                R
            </div>

            <div>

                <span>Resolved</span>

                <strong>
                    <?php echo $resolvedTickets; ?>
                </strong>

            </div>

        </div>

        <div class="stat-card">

            <div class="stat-icon">
                C
            </div>

            <div>

                <span>Closed</span>

                <strong>
                    <?php echo $closedTickets; ?>
                </strong>

            </div>

        </div>

        <div class="stat-card">

            <div class="stat-icon">
                U
            </div>

            <div>

                <span>Unassigned</span>

                <strong>
                    <?php echo $newTickets; ?>
                </strong>

            </div>

        </div>

        <div class="stat-card">

            <div class="stat-icon">
                A
            </div>

            <div>

                <span>Active Assigned</span>

                <strong>
                    <?php echo $assignedTickets + $inProgressTickets; ?>
                </strong>

            </div>

        </div>

    </div>

</section>

<!-- =====================================================
     WORKFLOW
===================================================== -->

<section class="panel">

    <div class="panel-title">

        <h2>
            Ticket Workflow
        </h2>

        <p>
            Track tickets through each support stage.
        </p>

    </div>

    <div class="workflow">

        <div class="workflow-step">

            <div class="workflow-number">
                01
            </div>

            <h3>
                New
            </h3>

            <span>
                <?php echo $newTickets; ?> Tickets
            </span>

        </div>

        <div class="workflow-arrow">
            →
        </div>

        <div class="workflow-step">

            <div class="workflow-number">
                02
            </div>

            <h3>
                Assigned
            </h3>

            <span>
                <?php echo $assignedTickets; ?> Tickets
            </span>

        </div>

        <div class="workflow-arrow">
            →
        </div>

        <div class="workflow-step">

            <div class="workflow-number">
                03
            </div>

            <h3>
                In Progress
            </h3>

            <span>
                <?php echo $inProgressTickets; ?> Tickets
            </span>

        </div>

        <div class="workflow-arrow">
            →
        </div>

        <div class="workflow-step">

            <div class="workflow-number">
                04
            </div>

            <h3>
                Resolved
            </h3>

            <span>
                <?php echo $resolvedTickets; ?> Tickets
            </span>

        </div>

        <div class="workflow-arrow">
            →
        </div>

        <div class="workflow-step">

            <div class="workflow-number">
                05
            </div>

            <h3>
                Closed
            </h3>

            <span>
                <?php echo $closedTickets; ?> Tickets
            </span>

        </div>

    </div>

</section>

<!-- =====================================================
     FILTERS
===================================================== -->

<section class="panel">

    <div class="filters">

        <div class="form-group">

            <label>
                Search Tickets
            </label>

            <input
                type="text"
                id="ticketSearch"
                placeholder="Search ticket ID, issue or employee..."
                onkeyup="filterTickets()"
            >

        </div>

        <div class="form-group">

            <label>
                Status
            </label>

            <select
                id="ticketStatus"
                onchange="filterTickets()"
            >

                <option value="all">
                    All Status
                </option>

                <option value="Open">
                    New
                </option>

                <option value="In Progress">
                    In Progress
                </option>

                <option value="Resolved">
                    Resolved
                </option>

                <option value="Closed">
                    Closed
                </option>

            </select>

        </div>

        <div class="form-group">

            <label>
                Priority
            </label>

            <select
                id="ticketPriority"
                onchange="filterTickets()"
            >

                <option value="all">
                    All Priority
                </option>

                <option value="Critical">
                    Critical
                </option>

                <option value="High">
                    High
                </option>

                <option value="Medium">
                    Medium
                </option>

                <option value="Low">
                    Low
                </option>

            </select>

        </div>

        <div class="form-group">

            <label>
                Category
            </label>

            <select
                id="ticketCategory"
                onchange="filterTickets()"
            >

                <option value="all">
                    All Categories
                </option>

                <option value="Network">
                    Network
                </option>

                <option value="Hardware">
                    Hardware
                </option>

                <option value="Software">
                    Software
                </option>

                <option value="Email">
                    Email
                </option>

                <option value="Access">
                    Access
                </option>

                <option value="Performance">
                    Performance
                </option>

            </select>

        </div>

    </div>

</section>

<!-- =====================================================
     TICKET TABLE
===================================================== -->

<section class="panel">

    <div class="panel-title">

        <h2>
            All Support Tickets
        </h2>

        <p>
            View and manage submitted support requests.
        </p>

        <strong id="ticketCount">
            Showing <?php echo count($tickets); ?> tickets
        </strong>

    </div>

    <div class="table-wrapper">

        <table>

            <thead>

                <tr>

                    <th>
                        Ticket
                    </th>

                    <th>
                        Issue
                    </th>

                    <th>
                        Employee
                    </th>

                    <th>
                        Category
                    </th>

                    <th>
                        Priority
                    </th>

                    <th>
                        Technician
                    </th>

                    <th>
                        Date
                    </th>

                    <th>
                        Status
                    </th>

                    <th>
                        Action
                    </th>

                </tr>

            </thead>

            <tbody>

            <?php if (count($tickets) > 0): ?>

                <?php foreach ($tickets as $ticket): ?>

                    <?php

                    $priorityClass =
                        "priority-" .
                        strtolower($ticket["priority"]);

                    $statusClass = "status-new";

                    if (
                        $ticket["status"] === "In Progress"
                    ) {

                        $statusClass = "status-progress";

                    } elseif (
                        $ticket["status"] === "Resolved"
                    ) {

                        $statusClass = "status-resolved";

                    } elseif (
                        $ticket["status"] === "Closed"
                    ) {

                        $statusClass = "status-closed";

                    } elseif (
                        $ticket["status"] === "Open" &&
                        !empty($ticket["technician_id"])
                    ) {

                        $statusClass = "status-assigned";
                    }

                    $searchText = strtolower(
                        ticketId($ticket["ticket_id"]) .
                        " " .
                        $ticket["title"] .
                        " " .
                        $ticket["employee_name"] .
                        " " .
                        $ticket["category"] .
                        " " .
                        $ticket["priority"]
                    );

                    ?>

                    <tr
                        class="ticket-row"
                        data-search="<?php echo e($searchText); ?>"
                        data-status="<?php echo e($ticket["status"]); ?>"
                        data-priority="<?php echo e($ticket["priority"]); ?>"
                        data-category="<?php echo e($ticket["category"]); ?>"
                    >

                        <td>

                            <span class="ticket-id">

                                <?php
                                echo e(
                                    ticketId(
                                        $ticket["ticket_id"]
                                    )
                                );
                                ?>

                            </span>

                        </td>

                        <td>

                            <span class="issue-title">

                                <?php
                                echo e(
                                    $ticket["title"]
                                );
                                ?>

                            </span>

                            <span class="issue-description">

                                <?php
                                echo e(
                                    mb_strimwidth(
                                        $ticket["description"],
                                        0,
                                        55,
                                        "..."
                                    )
                                );
                                ?>

                            </span>

                        </td>

                        <td>

                            <?php
                            echo e(
                                $ticket["employee_name"]
                            );
                            ?>

                            <br>

                            <small>

                                <?php
                                echo e(
                                    $ticket["employee_code"]
                                );
                                ?>

                            </small>

                        </td>

                        <td>

                            <?php
                            echo e(
                                $ticket["category"]
                            );
                            ?>

                        </td>

                        <td>

                            <span
                                class="badge <?php echo e($priorityClass); ?>"
                            >

                                <?php
                                echo e(
                                    $ticket["priority"]
                                );
                                ?>

                            </span>

                        </td>

                        <td>

                            <?php
                            if (!empty($ticket["technician_name"])):
                            ?>

                                <?php
                                echo e(
                                    $ticket["technician_name"]
                                );
                                ?>

                                <br>

                                <small>

                                    <?php
                                    echo e(
                                        $ticket["technician_code"]
                                    );
                                    ?>

                                </small>

                            <?php else: ?>

                                <span style="color:#888;">
                                    Not Assigned
                                </span>

                            <?php endif; ?>

                        </td>

                        <td>

                            <?php
                            echo date(
                                "d M Y",
                                strtotime(
                                    $ticket["created_at"]
                                )
                            );
                            ?>

                        </td>

                        <td>

                            <span
                                class="badge <?php echo e($statusClass); ?>"
                            >

                                <?php
                                echo e(
                                    displayStatus(
                                        $ticket["status"]
                                    )
                                );
                                ?>

                            </span>

                        </td>

                        <td>

                            <button
                                type="button"
                                class="action-btn"
                                onclick='viewTicket(
                                    <?php
                                    echo json_encode(
                                        $ticket,
                                        JSON_HEX_TAG |
                                        JSON_HEX_APOS |
                                        JSON_HEX_QUOT |
                                        JSON_HEX_AMP
                                    );
                                    ?>
                                )'
                            >
                                View
                            </button>

                            <button
                                type="button"
                                class="action-btn"
                                onclick="assignTicket(
                                    <?php
                                    echo (int)$ticket["ticket_id"];
                                    ?>
                                )"
                            >
                                Assign
                            </button>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php else: ?>

                <tr>

                    <td
                        colspan="9"
                        style="
                            text-align:center;
                            padding:50px;
                            color:#71877d;
                        "
                    >

                        No tickets have been created yet.

                        <br><br>

                        Click

                        <strong>
                            Create New Ticket
                        </strong>

                        to create the first ticket.

                    </td>

                </tr>

            <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>

<!-- =====================================================
     ASSIGNMENT PANEL
===================================================== -->

<section
    class="panel"
    id="assignmentPanel"
    style="display:none;"
>

    <div class="panel-title">

        <h2>
            Assign Support Ticket
        </h2>

        <p>
            Assign a ticket to a technician and update its status.
        </p>

    </div>

    <form
        method="POST"
        action="tickets.php"
    >

        <input
            type="hidden"
            name="action"
            value="assign_ticket"
        >

        <div class="assignment-grid">

            <div class="form-group">

                <label>
                    Select Ticket
                </label>

                <select
                    name="ticket_id"
                    id="assignmentTicket"
                    required
                >

                    <option value="">
                        Select a ticket
                    </option>

                    <?php foreach ($tickets as $ticket): ?>

                        <option
                            value="<?php
                                echo (int)$ticket["ticket_id"];
                            ?>"
                        >

                            <?php
                            echo e(
                                ticketId(
                                    $ticket["ticket_id"]
                                )
                            );
                            ?>

                            -

                            <?php
                            echo e(
                                $ticket["title"]
                            );
                            ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="form-group">

                <label>
                    Assign Technician
                </label>

                <select
                    name="technician_id"
                    required
                >

                    <option value="">
                        Select technician
                    </option>

                    <?php foreach ($technicians as $technician): ?>

                        <option
                            value="<?php
                                echo (int)$technician["technician_id"];
                            ?>"
                        >

                            <?php
                            echo e(
                                $technician["full_name"]
                            );
                            ?>

                            -

                            <?php
                            echo e(
                                $technician["specialization"]
                            );
                            ?>

                            -
                            <?php
                            echo e(
                                $technician["availability"]
                            );
                            ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>

            <div class="form-group">

                <label>
                    Ticket Status
                </label>

                <select
                    name="status"
                    required
                >

                    <option value="Open">
                        New
                    </option>

                    <option value="In Progress">
                        In Progress
                    </option>

                    <option value="Resolved">
                        Resolved
                    </option>

                    <option value="Closed">
                        Closed
                    </option>

                </select>

            </div>

        </div>

        <div class="form-actions">

            <button
                type="button"
                class="secondary-btn"
                onclick="closeAssignment()"
            >
                Cancel
            </button>

            <button
                type="submit"
                class="primary-btn"
            >
                Assign Ticket
            </button>

        </div>

    </form>

</section>

</main>

<!-- =====================================================
     CREATE NEW TICKET MODAL
===================================================== -->

<div
    class="modal"
    id="createTicketModal"
>

    <div class="modal-content">

        <div class="modal-header">

            <h2>
                Create New Ticket
            </h2>

            <span
                class="close"
                onclick="closeCreateTicket()"
            >
                ×
            </span>

        </div>

        <div class="modal-body">

            <form
                method="POST"
                action="tickets.php"
            >

                <input
                    type="hidden"
                    name="action"
                    value="create_ticket"
                >

                <div class="create-grid">

                    <!-- Employee -->

                    <div class="form-group">

                        <label>
                            Employee *
                        </label>

                        <select
                            name="employee_id"
                            required
                        >

                            <option value="">
                                Select employee
                            </option>

                            <?php foreach ($employees as $employee): ?>

                                <option
                                    value="<?php
                                        echo (int)$employee["employee_id"];
                                    ?>"
                                >

                                    <?php
                                    echo e(
                                        $employee["full_name"]
                                    );
                                    ?>

                                    -

                                    <?php
                                    echo e(
                                        $employee["employee_code"]
                                    );
                                    ?>

                                    -

                                    <?php
                                    echo e(
                                        $employee["department"]
                                    );
                                    ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <!-- Category -->

                    <div class="form-group">

                        <label>
                            Category *
                        </label>

                        <select
                            name="category"
                            required
                        >

                            <option value="">
                                Select category
                            </option>

                            <option value="Network">
                                Network
                            </option>

                            <option value="Hardware">
                                Hardware
                            </option>

                            <option value="Software">
                                Software
                            </option>

                            <option value="Email">
                                Email
                            </option>

                            <option value="Access">
                                Access
                            </option>

                            <option value="Performance">
                                Performance
                            </option>

                        </select>

                    </div>

                    <!-- Priority -->

                    <div class="form-group">

                        <label>
                            Priority *
                        </label>

                        <select
                            name="priority"
                            required
                        >

                            <option value="Low">
                                Low
                            </option>

                            <option
                                value="Medium"
                                selected
                            >
                                Medium
                            </option>

                            <option value="High">
                                High
                            </option>

                            <option value="Critical">
                                Critical
                            </option>

                        </select>

                    </div>

                    <!-- Title -->

                    <div class="form-group full-width">

                        <label>
                            Ticket Title *
                        </label>

                        <input
                            type="text"
                            name="title"
                            maxlength="200"
                            placeholder="Enter the issue title"
                            required
                        >

                    </div>

                    <!-- Description -->

                    <div class="form-group full-width">

                        <label>
                            Description *
                        </label>

                        <textarea
                            name="description"
                            placeholder="Describe the IT problem in detail..."
                            required
                        ></textarea>

                    </div>

                </div>

                <div class="form-actions">

                    <button
                        type="button"
                        class="secondary-btn"
                        onclick="closeCreateTicket()"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="primary-btn"
                    >
                        Create Ticket
                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<!-- =====================================================
     VIEW TICKET MODAL
===================================================== -->

<div
    class="modal"
    id="ticketModal"
>

    <div class="modal-content">

        <div class="modal-header">

            <h2>
                Ticket Details
            </h2>

            <span
                class="close"
                onclick="closeTicket()"
            >
                ×
            </span>

        </div>

        <div
            class="modal-body"
            id="ticketDetails"
        ></div>

    </div>

</div>

<!-- =====================================================
     FOOTER
===================================================== -->

<footer>

    <strong>
        IT Help Desk
    </strong>

    <p>
        IT Support and Asset Management System
    </p>

    <small>
        © 2026 IT Help Desk. All Rights Reserved.
    </small>

</footer>

<script>

/* =========================================================
   CREATE TICKET MODAL
========================================================= */

function openCreateTicket()
{
    document.getElementById("createTicketModal").style.display = "block";
}

function closeCreateTicket()
{
    document.getElementById("createTicketModal").style.display = "none";
}

/* =========================================================
   ASSIGNMENT PANEL
========================================================= */

function openAssignment()
{
    const panel = document.getElementById("assignmentPanel");

    panel.style.display = "block";

    panel.scrollIntoView({
        behavior: "smooth",
        block: "start"
    });
}

function closeAssignment()
{
    document.getElementById("assignmentPanel").style.display = "none";
}

/* =========================================================
   ASSIGN SPECIFIC TICKET
========================================================= */

function assignTicket(ticketId)
{
    const panel = document.getElementById("assignmentPanel");

    const select = document.getElementById("assignmentTicket");

    select.value = ticketId;

    panel.style.display = "block";

    panel.scrollIntoView({
        behavior: "smooth",
        block: "start"
    });
}

/* =========================================================
   VIEW TICKET
========================================================= */

function viewTicket(ticket)
{
    const ticketNumber =
        "INC-" +
        new Date().getFullYear() +
        "-" +
        String(ticket.ticket_id).padStart(3, "0");

    const technician =
        ticket.technician_name
            ? ticket.technician_name
            : "Not Assigned";

    const status =
        ticket.status === "Open"
            ? (
                ticket.technician_name
                    ? "Assigned"
                    : "New"
            )
            : ticket.status;

    document.getElementById("ticketDetails").innerHTML = `

        <div class="detail-grid">

            <div class="detail-box">

                <div class="detail-label">
                    Ticket ID
                </div>

                <div class="detail-value">
                    ${escapeHtml(ticketNumber)}
                </div>

            </div>

            <div class="detail-box">

                <div class="detail-label">
                    Status
                </div>

                <div class="detail-value">
                    ${escapeHtml(status)}
                </div>

            </div>

            <div class="detail-box">

                <div class="detail-label">
                    Employee
                </div>

                <div class="detail-value">
                    ${escapeHtml(ticket.employee_name)}
                </div>

            </div>

            <div class="detail-box">

                <div class="detail-label">
                    Employee Code
                </div>

                <div class="detail-value">
                    ${escapeHtml(ticket.employee_code)}
                </div>

            </div>

            <div class="detail-box">

                <div class="detail-label">
                    Category
                </div>

                <div class="detail-value">
                    ${escapeHtml(ticket.category)}
                </div>

            </div>

            <div class="detail-box">

                <div class="detail-label">
                    Priority
                </div>

                <div class="detail-value">
                    ${escapeHtml(ticket.priority)}
                </div>

            </div>

            <div class="detail-box">

                <div class="detail-label">
                    Technician
                </div>

                <div class="detail-value">
                    ${escapeHtml(technician)}
                </div>

            </div>

            <div class="detail-box">

                <div class="detail-label">
                    Technician Code
                </div>

                <div class="detail-value">
                    ${
                        ticket.technician_code
                            ? escapeHtml(ticket.technician_code)
                            : "Not Assigned"
                    }
                </div>

            </div>

            <div class="detail-box">

                <div class="detail-label">
                    Created
                </div>

                <div class="detail-value">
                    ${escapeHtml(ticket.created_at)}
                </div>

            </div>

            <div class="detail-box">

                <div class="detail-label">
                    Updated
                </div>

                <div class="detail-value">
                    ${escapeHtml(ticket.updated_at)}
                </div>

            </div>

            <div class="detail-box full">

                <div class="detail-label">
                    Issue
                </div>

                <div class="detail-value">
                    ${escapeHtml(ticket.title)}
                </div>

            </div>

            <div class="detail-box full">

                <div class="detail-label">
                    Description
                </div>

                <div class="detail-value">
                    ${escapeHtml(ticket.description)}
                </div>

            </div>

        </div>

    `;

    document.getElementById("ticketModal").style.display = "block";
}

function closeTicket()
{
    document.getElementById("ticketModal").style.display = "none";
}

/* =========================================================
   FILTER TICKETS
========================================================= */

function filterTickets()
{
    const search =
        document
            .getElementById("ticketSearch")
            .value
            .toLowerCase()
            .trim();

    const status =
        document.getElementById("ticketStatus").value;

    const priority =
        document.getElementById("ticketPriority").value;

    const category =
        document.getElementById("ticketCategory").value;

    const rows =
        document.querySelectorAll(".ticket-row");

    let visibleCount = 0;

    rows.forEach(function(row)
    {
        const rowSearch =
            row.dataset.search || "";

        const rowStatus =
            row.dataset.status || "";

        const rowPriority =
            row.dataset.priority || "";

        const rowCategory =
            row.dataset.category || "";

        const matchesSearch =
            rowSearch.includes(search);

        const matchesStatus =
            status === "all" ||
            rowStatus === status;

        const matchesPriority =
            priority === "all" ||
            rowPriority === priority;

        const matchesCategory =
            category === "all" ||
            rowCategory === category;

        if (
            matchesSearch &&
            matchesStatus &&
            matchesPriority &&
            matchesCategory
        ) {

            row.style.display = "";

            visibleCount++;

        } else {

            row.style.display = "none";
        }
    });

    document.getElementById("ticketCount").innerText =
        "Showing " + visibleCount + " tickets";
}

/* =========================================================
   HTML ESCAPE
========================================================= */

function escapeHtml(value)
{
    if (
        value === null ||
        value === undefined
    ) {
        return "";
    }

    return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

/* =========================================================
   CLOSE MODALS WHEN CLICKING OUTSIDE
========================================================= */

window.onclick = function(event)
{
    const ticketModal =
        document.getElementById("ticketModal");

    const createModal =
        document.getElementById("createTicketModal");

    if (event.target === ticketModal) {

        ticketModal.style.display = "none";
    }

    if (event.target === createModal) {

        createModal.style.display = "none";
    }
};

/* =========================================================
   ESC KEY CLOSE MODALS
========================================================= */

document.addEventListener("keydown", function(event)
{
    if (event.key === "Escape") {

        closeTicket();

        closeCreateTicket();
    }
});

</script>

</body>

</html>