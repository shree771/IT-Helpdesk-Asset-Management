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

function validDate($date)
{
    $d = DateTime::createFromFormat("Y-m-d", $date);

    return $d &&
           $d->format("Y-m-d") === $date;
}

function ticketDisplayId($id, $createdAt = null)
{
    $year = date("Y");

    if (!empty($createdAt)) {
        $timestamp = strtotime($createdAt);

        if ($timestamp !== false) {
            $year = date("Y", $timestamp);
        }
    }

    return "INC-" . $year . "-" . str_pad((string)$id, 3, "0", STR_PAD_LEFT);
}

function barWidth($value, $maximum)
{
    if ($maximum <= 0) {
        return 0;
    }

    return min(100, round(($value / $maximum) * 100));
}

/* =========================================================
   DATE FILTER
========================================================= */

$defaultFrom = "2026-09-01";
$defaultTo   = "2026-09-29";

$from = isset($_GET["from"]) && validDate($_GET["from"])
    ? $_GET["from"]
    : $defaultFrom;

$to = isset($_GET["to"]) && validDate($_GET["to"])
    ? $_GET["to"]
    : $defaultTo;

if ($from > $to) {
    $temp = $from;
    $from = $to;
    $to = $temp;
}

/* =========================================================
   REPORT TYPE
========================================================= */

$reportType = isset($_GET["report_type"])
    ? $_GET["report_type"]
    : "All Reports";

$allowedReportTypes = [
    "All Reports",
    "Ticket Report",
    "Technician Report",
    "Asset Report",
    "Department Report"
];

if (!in_array($reportType, $allowedReportTypes, true)) {
    $reportType = "All Reports";
}

/* =========================================================
   FORMAT
========================================================= */

$format = isset($_GET["format"])
    ? $_GET["format"]
    : "Screen";

$allowedFormats = [
    "Screen",
    "PDF",
    "Excel"
];

if (!in_array($format, $allowedFormats, true)) {
    $format = "Screen";
}

/* =========================================================
   WHAT SHOULD BE DISPLAYED?
========================================================= */

$showAll         = ($reportType === "All Reports");
$showTicket      = ($showAll || $reportType === "Ticket Report");
$showTechnician  = ($showAll || $reportType === "Technician Report");
$showAsset       = ($showAll || $reportType === "Asset Report");
$showDepartment  = ($showAll || $reportType === "Department Report");

/* =========================================================
   TICKET SUMMARY
========================================================= */

$totalTickets = 0;
$newTickets = 0;
$assignedTickets = 0;
$inProgressTickets = 0;
$resolvedTickets = 0;
$closedTickets = 0;
$resolutionRate = 0;

if ($showTicket) {

    $sql = "
        SELECT
            COUNT(*) AS total_tickets,

            SUM(
                CASE
                    WHEN status = 'Open'
                    AND technician_id IS NULL
                    THEN 1
                    ELSE 0
                END
            ) AS new_tickets,

            SUM(
                CASE
                    WHEN status = 'Open'
                    AND technician_id IS NOT NULL
                    THEN 1
                    ELSE 0
                END
            ) AS assigned_tickets,

            SUM(
                CASE
                    WHEN status = 'In Progress'
                    THEN 1
                    ELSE 0
                END
            ) AS in_progress_tickets,

            SUM(
                CASE
                    WHEN status = 'Resolved'
                    THEN 1
                    ELSE 0
                END
            ) AS resolved_tickets,

            SUM(
                CASE
                    WHEN status = 'Closed'
                    THEN 1
                    ELSE 0
                END
            ) AS closed_tickets

        FROM tickets
        WHERE DATE(created_at) BETWEEN ? AND ?
    ";

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("ss", $from, $to);
        $stmt->execute();

        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {

            $totalTickets = (int)($row["total_tickets"] ?? 0);
            $newTickets = (int)($row["new_tickets"] ?? 0);
            $assignedTickets = (int)($row["assigned_tickets"] ?? 0);
            $inProgressTickets = (int)($row["in_progress_tickets"] ?? 0);
            $resolvedTickets = (int)($row["resolved_tickets"] ?? 0);
            $closedTickets = (int)($row["closed_tickets"] ?? 0);

            $completedTickets = $resolvedTickets + $closedTickets;

            $resolutionRate = $totalTickets > 0
                ? round(($completedTickets / $totalTickets) * 100)
                : 0;
        }

        $stmt->close();
    }
}

/* =========================================================
   PRIORITY REPORT
========================================================= */

$priorityReport = [];

if ($showTicket) {

    $sql = "
        SELECT
            priority,
            COUNT(*) AS total
        FROM tickets
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY priority
        ORDER BY
            FIELD(priority, 'Critical', 'High', 'Medium', 'Low')
    ";

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("ss", $from, $to);
        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $priorityReport[] = [
                "priority" => $row["priority"],
                "total" => (int)$row["total"]
            ];
        }

        $stmt->close();
    }
}

/* =========================================================
   CATEGORY REPORT
========================================================= */

$categoryReport = [];

if ($showTicket) {

    $sql = "
        SELECT
            category,
            COUNT(*) AS total
        FROM tickets
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY category
        ORDER BY total DESC
    ";

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("ss", $from, $to);
        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $categoryReport[] = [
                "category" => $row["category"],
                "total" => (int)$row["total"]
            ];
        }

        $stmt->close();
    }
}

/* =========================================================
   TECHNICIAN WORKLOAD
========================================================= */

$technicianReport = [];

if ($showTechnician) {

    $sql = "
        SELECT
            t.technician_id,
            t.technician_code,
            t.full_name,
            t.specialization,
            t.availability,

            COUNT(
                CASE
                    WHEN tk.status = 'Open'
                    THEN 1
                END
            ) AS open_count,

            COUNT(
                CASE
                    WHEN tk.status = 'In Progress'
                    THEN 1
                END
            ) AS in_progress_count,

            COUNT(
                CASE
                    WHEN tk.status = 'Resolved'
                    THEN 1
                END
            ) AS resolved_count,

            COUNT(
                CASE
                    WHEN tk.status = 'Closed'
                    THEN 1
                END
            ) AS closed_count,

            COUNT(tk.ticket_id) AS total_count

        FROM technicians t

        LEFT JOIN tickets tk
            ON t.technician_id = tk.technician_id
            AND DATE(tk.created_at) BETWEEN ? AND ?

        GROUP BY
            t.technician_id,
            t.technician_code,
            t.full_name,
            t.specialization,
            t.availability

        ORDER BY t.full_name ASC
    ";

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("ss", $from, $to);
        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {

            $technicianReport[] = [
                "technician_id" => (int)$row["technician_id"],
                "technician_code" => $row["technician_code"],
                "full_name" => $row["full_name"],
                "specialization" => $row["specialization"],
                "availability" => $row["availability"],
                "open_count" => (int)$row["open_count"],
                "in_progress_count" => (int)$row["in_progress_count"],
                "resolved_count" => (int)$row["resolved_count"],
                "closed_count" => (int)$row["closed_count"],
                "total_count" => (int)$row["total_count"]
            ];
        }

        $stmt->close();
    }
}

/* =========================================================
   ASSET REPORT
========================================================= */

$assetReport = [];

$totalAssets = 0;
$availableAssets = 0;
$assignedAssets = 0;
$maintenanceAssets = 0;
$retiredAssets = 0;

if ($showAsset) {

    $sql = "
        SELECT
            status,
            COUNT(*) AS total
        FROM assets
        GROUP BY status
        ORDER BY status
    ";

    $result = $conn->query($sql);

    if ($result) {

        while ($row = $result->fetch_assoc()) {

            $status = $row["status"];
            $total = (int)$row["total"];

            $assetReport[] = [
                "status" => $status,
                "total" => $total
            ];

            $totalAssets += $total;

            if ($status === "Available") {
                $availableAssets = $total;
            }

            if ($status === "Assigned") {
                $assignedAssets = $total;
            }

            if ($status === "Maintenance") {
                $maintenanceAssets = $total;
            }

            if ($status === "Retired") {
                $retiredAssets = $total;
            }
        }
    }
}

/* =========================================================
   DEPARTMENT REPORT
========================================================= */

$departmentReport = [];

if ($showDepartment) {

    $sql = "
        SELECT
            COALESCE(e.department, 'Unknown') AS department,
            COUNT(tk.ticket_id) AS total_tickets,

            SUM(
                CASE
                    WHEN tk.status = 'Open'
                    THEN 1
                    ELSE 0
                END
            ) AS open_tickets,

            SUM(
                CASE
                    WHEN tk.status = 'In Progress'
                    THEN 1
                    ELSE 0
                END
            ) AS in_progress_tickets,

            SUM(
                CASE
                    WHEN tk.status = 'Resolved'
                    THEN 1
                    ELSE 0
                END
            ) AS resolved_tickets,

            SUM(
                CASE
                    WHEN tk.status = 'Closed'
                    THEN 1
                    ELSE 0
                END
            ) AS closed_tickets

        FROM employees e

        LEFT JOIN tickets tk
            ON e.employee_id = tk.employee_id
            AND DATE(tk.created_at) BETWEEN ? AND ?

        GROUP BY e.department
        ORDER BY total_tickets DESC, department ASC
    ";

    $stmt = $conn->prepare($sql);

    if ($stmt) {
        $stmt->bind_param("ss", $from, $to);
        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {

            $departmentReport[] = [
                "department" => $row["department"],
                "total_tickets" => (int)$row["total_tickets"],
                "open_tickets" => (int)$row["open_tickets"],
                "in_progress_tickets" => (int)$row["in_progress_tickets"],
                "resolved_tickets" => (int)$row["resolved_tickets"],
                "closed_tickets" => (int)$row["closed_tickets"]
            ];
        }

        $stmt->close();
    }
}

/* =========================================================
   EMPLOYEE SUMMARY
========================================================= */

$totalEmployees = 0;

if ($showAll) {

    $result = $conn->query("
        SELECT COUNT(*) AS total
        FROM employees
    ");

    if ($result && $row = $result->fetch_assoc()) {
        $totalEmployees = (int)$row["total"];
    }
}

/* =========================================================
   RECENT ACTIVITY
========================================================= */

$recentTickets = [];

if ($showTicket) {

    $sql = "
        SELECT
            tk.ticket_id,
            tk.title,
            tk.status,
            tk.priority,
            tk.category,
            tk.created_at,

            e.full_name AS employee_name,

            te.full_name AS technician_name

        FROM tickets tk

        LEFT JOIN employees e
            ON tk.employee_id = e.employee_id

        LEFT JOIN technicians te
            ON tk.technician_id = te.technician_id

        WHERE DATE(tk.created_at) BETWEEN ? AND ?

        ORDER BY tk.created_at DESC

        LIMIT 10
    ";

    $stmt = $conn->prepare($sql);

    if ($stmt) {

        $stmt->bind_param("ss", $from, $to);
        $stmt->execute();

        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {

            $recentTickets[] = [
                "ticket_id" => (int)$row["ticket_id"],
                "display_id" => ticketDisplayId(
                    $row["ticket_id"],
                    $row["created_at"]
                ),
                "title" => $row["title"],
                "status" => $row["status"],
                "priority" => $row["priority"],
                "category" => $row["category"],
                "created_at" => $row["created_at"],
                "employee_name" => $row["employee_name"],
                "technician_name" => $row["technician_name"]
            ];
        }

        $stmt->close();
    }
}

/* =========================================================
   PREPARE DATA FOR JAVASCRIPT EXPORT
========================================================= */

$exportData = [
    "reportType" => $reportType,
    "from" => $from,
    "to" => $to,

    "summary" => [
        "totalTickets" => $totalTickets,
        "newTickets" => $newTickets,
        "assignedTickets" => $assignedTickets,
        "inProgressTickets" => $inProgressTickets,
        "resolvedTickets" => $resolvedTickets,
        "closedTickets" => $closedTickets,
        "resolutionRate" => $resolutionRate,
        "totalEmployees" => $totalEmployees,
        "totalAssets" => $totalAssets,
        "availableAssets" => $availableAssets,
        "assignedAssets" => $assignedAssets,
        "maintenanceAssets" => $maintenanceAssets,
        "retiredAssets" => $retiredAssets
    ],

    "priority" => $priorityReport,
    "category" => $categoryReport,
    "technicians" => $technicianReport,
    "assets" => $assetReport,
    "departments" => $departmentReport,
    "recentTickets" => $recentTickets
];

$exportJson = json_encode(
    $exportData,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_AMP |
    JSON_HEX_QUOT
);

/* =========================================================
   BAR MAXIMUMS
========================================================= */

$maxPriority = 0;

foreach ($priorityReport as $row) {
    $maxPriority = max($maxPriority, $row["total"]);
}

$maxCategory = 0;

foreach ($categoryReport as $row) {
    $maxCategory = max($maxCategory, $row["total"]);
}

$maxDepartment = 0;

foreach ($departmentReport as $row) {
    $maxDepartment = max(
        $maxDepartment,
        $row["total_tickets"]
    );
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

    <title>Reports - IT Helpdesk & Asset Management</title>

    <!-- =====================================================
         PDF LIBRARIES
    ====================================================== -->

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>

    <!-- =====================================================
         EXCEL LIBRARY
    ====================================================== -->

    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f4f7f6;
            color: #1f2937;
        }

        /* =====================================================
           SIDEBAR
        ====================================================== */

        .sidebar {
            position: fixed;

            left: 0;
            top: 0;

            width: 250px;
            height: 100vh;

            background: #0f5132;

            color: white;

            padding: 25px 18px;

            overflow-y: auto;
        }

        .brand {
            text-align: center;

            font-size: 21px;
            font-weight: 700;

            margin-bottom: 35px;
        }

        .brand span {
            display: block;

            font-size: 12px;

            margin-top: 5px;

            opacity: 0.75;
        }

        .nav-link {
            display: block;

            text-decoration: none;

            color: #d9f5e8;

            padding: 13px 15px;

            margin-bottom: 7px;

            border-radius: 8px;

            transition: 0.2s;
        }

        .nav-link:hover {
            background: #146c43;
            color: white;
        }

        .nav-link.active {
            background: #198754;
            color: white;
            font-weight: 700;
        }

        .nav-link.logout {
            margin-top: 30px;
            background: #9b2c2c;
        }

        .nav-link.logout:hover {
            background: #b83232;
        }

        /* =====================================================
           MAIN
        ====================================================== */

        .main {
            margin-left: 250px;

            padding: 30px;

            min-height: 100vh;
        }

        .page-header {
            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-bottom: 25px;
        }

        .page-header h1 {
            color: #0f5132;

            font-size: 30px;
        }

        .page-header p {
            color: #6b7280;

            margin-top: 5px;
        }

        /* =====================================================
           FILTER PANEL
        ====================================================== */

        .filter-panel {
            background: white;

            border-radius: 12px;

            padding: 22px;

            margin-bottom: 25px;

            box-shadow:
                0 3px 12px rgba(0,0,0,0.06);
        }

        .filter-grid {
            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 16px;

            align-items: end;
        }

        .form-group label {
            display: block;

            margin-bottom: 7px;

            font-size: 13px;

            font-weight: 700;

            color: #374151;
        }

        .form-group input,
        .form-group select {
            width: 100%;

            padding: 11px 12px;

            border: 1px solid #d1d5db;

            border-radius: 7px;

            background: white;

            outline: none;
        }

        .form-group input:focus,
        .form-group select:focus {
            border-color: #198754;

            box-shadow:
                0 0 0 3px rgba(25,135,84,0.12);
        }

        .generate-btn {
            width: 100%;

            padding: 11px;

            border: none;

            border-radius: 7px;

            background: #198754;

            color: white;

            font-weight: 700;

            cursor: pointer;
        }

        .generate-btn:hover {
            background: #157347;
        }

        /* =====================================================
           REPORT TITLE
        ====================================================== */

        .report-heading {
            background: #0f5132;

            color: white;

            padding: 18px 22px;

            border-radius: 10px;

            margin-bottom: 20px;
        }

        .report-heading h2 {
            font-size: 21px;
        }

        .report-heading p {
            margin-top: 5px;

            opacity: 0.85;

            font-size: 13px;
        }

        /* =====================================================
           CARDS
        ====================================================== */

        .cards {
            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 18px;

            margin-bottom: 25px;
        }

        .card {
            background: white;

            border-radius: 12px;

            padding: 20px;

            box-shadow:
                0 3px 12px rgba(0,0,0,0.06);
        }

        .card-title {
            font-size: 13px;

            color: #6b7280;

            margin-bottom: 8px;
        }

        .card-value {
            font-size: 28px;

            font-weight: 700;

            color: #0f5132;
        }

        /* =====================================================
           SECTION
        ====================================================== */

        .section {
            background: white;

            border-radius: 12px;

            padding: 22px;

            margin-bottom: 25px;

            box-shadow:
                0 3px 12px rgba(0,0,0,0.06);
        }

        .section-title {
            color: #0f5132;

            font-size: 20px;

            margin-bottom: 18px;

            border-bottom: 1px solid #e5e7eb;

            padding-bottom: 12px;
        }

        /* =====================================================
           STATUS BARS
        ====================================================== */

        .status-row {
            margin-bottom: 17px;
        }

        .status-top {
            display: flex;

            justify-content: space-between;

            margin-bottom: 6px;

            font-size: 13px;
        }

        .bar-background {
            height: 10px;

            background: #e5e7eb;

            border-radius: 20px;

            overflow: hidden;
        }

        .bar-fill {
            height: 100%;

            background: #198754;

            border-radius: 20px;
        }

        /* =====================================================
           TABLE
        ====================================================== */

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;

            border-collapse: collapse;

            min-width: 650px;
        }

        th {
            background: #e9f7ef;

            color: #0f5132;

            text-align: left;

            padding: 12px;

            font-size: 13px;
        }

        td {
            padding: 12px;

            border-bottom: 1px solid #e5e7eb;

            font-size: 13px;
        }

        tr:hover td {
            background: #fafafa;
        }

        .badge {
            display: inline-block;

            padding: 5px 9px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: 700;
        }

        .badge-green {
            background: #d1fae5;

            color: #065f46;
        }

        .badge-yellow {
            background: #fef3c7;

            color: #92400e;
        }

        .badge-red {
            background: #fee2e2;

            color: #991b1b;
        }

        .badge-blue {
            background: #dbeafe;

            color: #1e40af;
        }

        .badge-gray {
            background: #e5e7eb;

            color: #374151;
        }

        .empty {
            text-align: center;

            padding: 25px;

            color: #6b7280;
        }

        /* =====================================================
           RESPONSIVE
        ====================================================== */

        @media (max-width: 1100px) {

            .cards {
                grid-template-columns:
                    repeat(2, 1fr);
            }

            .filter-grid {
                grid-template-columns:
                    repeat(2, 1fr);
            }
        }

        @media (max-width: 750px) {

            .sidebar {
                position: static;

                width: 100%;

                height: auto;
            }

            .main {
                margin-left: 0;

                padding: 20px;
            }

            .cards {
                grid-template-columns: 1fr;
            }

            .filter-grid {
                grid-template-columns: 1fr;
            }
        }

    </style>

</head>

<body>

<!-- =========================================================
     SIDEBAR
========================================================== -->

<div class="sidebar">

    <div class="brand">

        IT Helpdesk

        <span>
            Asset Management System
        </span>

    </div>

    <a
        href="dashboard.php"
        class="nav-link"
    >
        Dashboard
    </a>

    <a
        href="employees.php"
        class="nav-link"
    >
        Employees
    </a>

    <a
        href="technicians.php"
        class="nav-link"
    >
        Technicians
    </a>

    <a
        href="assets.php"
        class="nav-link"
    >
        Assets
    </a>

    <a
        href="tickets.php"
        class="nav-link"
    >
        Tickets
    </a>

    <a
        href="reports.php"
        class="nav-link active"
    >
        Reports
    </a>

    <a
        href="../logout.php"
        class="nav-link logout"
    >
        Logout
    </a>

</div>


<!-- =========================================================
     MAIN CONTENT
========================================================== -->

<div class="main">

    <div class="page-header">

        <div>

            <h1>
                Reports
            </h1>

            <p>
                Generate and analyze IT Helpdesk and Asset Management reports.
            </p>

        </div>

    </div>


    <!-- =====================================================
         FILTER PANEL
    ====================================================== -->

    <div class="filter-panel">

        <form
            id="reportForm"
            method="GET"
            action="reports.php"
        >

            <div class="filter-grid">

                <!-- FROM -->

                <div class="form-group">

                    <label>
                        From Date
                    </label>

                    <input
                        type="date"
                        name="from"
                        value="<?= e($from) ?>"
                        required
                    >

                </div>


                <!-- TO -->

                <div class="form-group">

                    <label>
                        To Date
                    </label>

                    <input
                        type="date"
                        name="to"
                        value="<?= e($to) ?>"
                        required
                    >

                </div>


                <!-- REPORT TYPE -->

                <div class="form-group">

                    <label>
                        Report Type
                    </label>

                    <select
                        name="report_type"
                        id="reportType"
                    >

                        <option
                            value="All Reports"
                            <?= $reportType === "All Reports" ? "selected" : "" ?>
                        >
                            All Reports
                        </option>

                        <option
                            value="Ticket Report"
                            <?= $reportType === "Ticket Report" ? "selected" : "" ?>
                        >
                            Ticket Report
                        </option>

                        <option
                            value="Technician Report"
                            <?= $reportType === "Technician Report" ? "selected" : "" ?>
                        >
                            Technician Report
                        </option>

                        <option
                            value="Asset Report"
                            <?= $reportType === "Asset Report" ? "selected" : "" ?>
                        >
                            Asset Report
                        </option>

                        <option
                            value="Department Report"
                            <?= $reportType === "Department Report" ? "selected" : "" ?>
                        >
                            Department Report
                        </option>

                    </select>

                </div>


                <!-- FORMAT -->

                <div class="form-group">

                    <label>
                        Format
                    </label>

                    <select
                        name="format"
                        id="reportFormat"
                    >

                        <option value="Screen">
                            Screen
                        </option>

                        <option value="PDF">
                            PDF
                        </option>

                        <option value="Excel">
                            Excel
                        </option>

                    </select>

                </div>

            </div>


            <div style="margin-top:18px;">

                <button
                    type="submit"
                    class="generate-btn"
                >
                    Generate Report
                </button>

            </div>

        </form>

    </div>


    <!-- =====================================================
         CURRENT REPORT
    ====================================================== -->

    <div class="report-heading">

        <h2>
            <?= e($reportType) ?>
        </h2>

        <p>
            Report period:
            <?= e($from) ?>
            to
            <?= e($to) ?>
        </p>

    </div>


    <!-- =====================================================
         TICKET REPORT
    ====================================================== -->

    <?php if ($showTicket): ?>

        <div class="cards">

            <div class="card">

                <div class="card-title">
                    Total Tickets
                </div>

                <div class="card-value">
                    <?= $totalTickets ?>
                </div>

            </div>


            <div class="card">

                <div class="card-title">
                    New Tickets
                </div>

                <div class="card-value">
                    <?= $newTickets ?>
                </div>

            </div>


            <div class="card">

                <div class="card-title">
                    In Progress
                </div>

                <div class="card-value">
                    <?= $inProgressTickets ?>
                </div>

            </div>


            <div class="card">

                <div class="card-title">
                    Resolution Rate
                </div>

                <div class="card-value">
                    <?= $resolutionRate ?>%
                </div>

            </div>

        </div>


        <!-- TICKET STATUS -->

        <div class="section">

            <div class="section-title">
                Ticket Status
            </div>


            <?php

            $statusData = [
                [
                    "name" => "New",
                    "value" => $newTickets
                ],
                [
                    "name" => "Assigned",
                    "value" => $assignedTickets
                ],
                [
                    "name" => "In Progress",
                    "value" => $inProgressTickets
                ],
                [
                    "name" => "Resolved",
                    "value" => $resolvedTickets
                ],
                [
                    "name" => "Closed",
                    "value" => $closedTickets
                ]
            ];

            ?>

            <?php foreach ($statusData as $status): ?>

                <div class="status-row">

                    <div class="status-top">

                        <span>
                            <?= e($status["name"]) ?>
                        </span>

                        <strong>
                            <?= $status["value"] ?>
                        </strong>

                    </div>

                    <div class="bar-background">

                        <div
                            class="bar-fill"
                            style="width:<?= $totalTickets > 0
                                ? round(($status["value"] / $totalTickets) * 100)
                                : 0 ?>%;"
                        ></div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>


        <!-- PRIORITY -->

        <div class="section">

            <div class="section-title">
                Tickets by Priority
            </div>

            <?php if (count($priorityReport) > 0): ?>

                <?php foreach ($priorityReport as $row): ?>

                    <div class="status-row">

                        <div class="status-top">

                            <span>
                                <?= e($row["priority"]) ?>
                            </span>

                            <strong>
                                <?= $row["total"] ?>
                            </strong>

                        </div>

                        <div class="bar-background">

                            <div
                                class="bar-fill"
                                style="width:<?= barWidth(
                                    $row["total"],
                                    $maxPriority
                                ) ?>%;"
                            ></div>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="empty">
                    No priority data found.
                </div>

            <?php endif; ?>

        </div>


        <!-- CATEGORY -->

        <div class="section">

            <div class="section-title">
                Tickets by Category
            </div>

            <?php if (count($categoryReport) > 0): ?>

                <?php foreach ($categoryReport as $row): ?>

                    <div class="status-row">

                        <div class="status-top">

                            <span>
                                <?= e($row["category"]) ?>
                            </span>

                            <strong>
                                <?= $row["total"] ?>
                            </strong>

                        </div>

                        <div class="bar-background">

                            <div
                                class="bar-fill"
                                style="width:<?= barWidth(
                                    $row["total"],
                                    $maxCategory
                                ) ?>%;"
                            ></div>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="empty">
                    No category data found.
                </div>

            <?php endif; ?>

        </div>


        <!-- RECENT TICKETS -->

        <div class="section">

            <div class="section-title">
                Recent Ticket Activity
            </div>

            <?php if (count($recentTickets) > 0): ?>

                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Ticket ID
                                </th>

                                <th>
                                    Title
                                </th>

                                <th>
                                    Employee
                                </th>

                                <th>
                                    Technician
                                </th>

                                <th>
                                    Category
                                </th>

                                <th>
                                    Priority
                                </th>

                                <th>
                                    Status
                                </th>

                                <th>
                                    Created
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($recentTickets as $ticket): ?>

                                <tr>

                                    <td>
                                        <strong>
                                            <?= e($ticket["display_id"]) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= e($ticket["title"]) ?>
                                    </td>

                                    <td>
                                        <?= e(
                                            $ticket["employee_name"] ?? "N/A"
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= e(
                                            $ticket["technician_name"] ?? "Unassigned"
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= e($ticket["category"]) ?>
                                    </td>

                                    <td>

                                        <span class="badge badge-yellow">
                                            <?= e($ticket["priority"]) ?>
                                        </span>

                                    </td>

                                    <td>

                                        <?php

                                        $statusClass = "badge-gray";

                                        if ($ticket["status"] === "Open") {
                                            $statusClass = "badge-blue";
                                        }

                                        if ($ticket["status"] === "In Progress") {
                                            $statusClass = "badge-yellow";
                                        }

                                        if (
                                            $ticket["status"] === "Resolved" ||
                                            $ticket["status"] === "Closed"
                                        ) {
                                            $statusClass = "badge-green";
                                        }

                                        ?>

                                        <span class="badge <?= $statusClass ?>">

                                            <?= e($ticket["status"]) ?>

                                        </span>

                                    </td>

                                    <td>
                                        <?= e($ticket["created_at"]) ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty">
                    No tickets found for the selected date range.
                </div>

            <?php endif; ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         TECHNICIAN REPORT
    ====================================================== -->

    <?php if ($showTechnician): ?>

        <div class="section">

            <div class="section-title">
                Technician Workload
            </div>

            <?php if (count($technicianReport) > 0): ?>

                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Technician Code
                                </th>

                                <th>
                                    Technician
                                </th>

                                <th>
                                    Specialization
                                </th>

                                <th>
                                    Availability
                                </th>

                                <th>
                                    Open
                                </th>

                                <th>
                                    In Progress
                                </th>

                                <th>
                                    Resolved
                                </th>

                                <th>
                                    Closed
                                </th>

                                <th>
                                    Total
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($technicianReport as $tech): ?>

                                <tr>

                                    <td>
                                        <?= e($tech["technician_code"]) ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= e($tech["full_name"]) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= e(
                                            $tech["specialization"] ?? "N/A"
                                        ) ?>
                                    </td>

                                    <td>

                                        <?php

                                        $availabilityClass = "badge-gray";

                                        if (
                                            $tech["availability"] === "Available"
                                        ) {
                                            $availabilityClass = "badge-green";
                                        }

                                        if (
                                            $tech["availability"] === "Busy"
                                        ) {
                                            $availabilityClass = "badge-yellow";
                                        }

                                        ?>

                                        <span
                                            class="badge <?= $availabilityClass ?>"
                                        >
                                            <?= e($tech["availability"]) ?>
                                        </span>

                                    </td>

                                    <td>
                                        <?= $tech["open_count"] ?>
                                    </td>

                                    <td>
                                        <?= $tech["in_progress_count"] ?>
                                    </td>

                                    <td>
                                        <?= $tech["resolved_count"] ?>
                                    </td>

                                    <td>
                                        <?= $tech["closed_count"] ?>
                                    </td>

                                    <td>
                                        <strong>
                                            <?= $tech["total_count"] ?>
                                        </strong>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty">
                    No technician data found.
                </div>

            <?php endif; ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         ASSET REPORT
    ====================================================== -->

    <?php if ($showAsset): ?>

        <div class="cards">

            <div class="card">

                <div class="card-title">
                    Total Assets
                </div>

                <div class="card-value">
                    <?= $totalAssets ?>
                </div>

            </div>


            <div class="card">

                <div class="card-title">
                    Available
                </div>

                <div class="card-value">
                    <?= $availableAssets ?>
                </div>

            </div>


            <div class="card">

                <div class="card-title">
                    Assigned
                </div>

                <div class="card-value">
                    <?= $assignedAssets ?>
                </div>

            </div>


            <div class="card">

                <div class="card-title">
                    Maintenance
                </div>

                <div class="card-value">
                    <?= $maintenanceAssets ?>
                </div>

            </div>

        </div>


        <div class="section">

            <div class="section-title">
                Asset Status Report
            </div>

            <?php if (count($assetReport) > 0): ?>

                <div class="table-wrapper">

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Asset Status
                                </th>

                                <th>
                                    Number of Assets
                                </th>

                                <th>
                                    Percentage
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($assetReport as $row): ?>

                                <?php

                                $percentage = $totalAssets > 0
                                    ? round(
                                        ($row["total"] / $totalAssets) * 100,
                                        1
                                    )
                                    : 0;

                                ?>

                                <tr>

                                    <td>

                                        <span class="badge badge-green">
                                            <?= e($row["status"]) ?>
                                        </span>

                                    </td>

                                    <td>
                                        <?= $row["total"] ?>
                                    </td>

                                    <td>
                                        <?= $percentage ?>%
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty">
                    No asset data found.
                </div>

            <?php endif; ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         DEPARTMENT REPORT
    ====================================================== -->

    <?php if ($showDepartment): ?>

        <div class="section">

            <div class="section-title">
                Department Ticket Report
            </div>

            <?php if (count($departmentReport) > 0): ?>

                <?php foreach ($departmentReport as $row): ?>

                    <div class="status-row">

                        <div class="status-top">

                            <span>
                                <?= e($row["department"]) ?>
                            </span>

                            <strong>
                                <?= $row["total_tickets"] ?>
                            </strong>

                        </div>

                        <div class="bar-background">

                            <div
                                class="bar-fill"
                                style="width:<?= barWidth(
                                    $row["total_tickets"],
                                    $maxDepartment
                                ) ?>%;"
                            ></div>

                        </div>

                    </div>

                <?php endforeach; ?>


                <div
                    class="table-wrapper"
                    style="margin-top:25px;"
                >

                    <table>

                        <thead>

                            <tr>

                                <th>
                                    Department
                                </th>

                                <th>
                                    Total
                                </th>

                                <th>
                                    Open
                                </th>

                                <th>
                                    In Progress
                                </th>

                                <th>
                                    Resolved
                                </th>

                                <th>
                                    Closed
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach ($departmentReport as $row): ?>

                                <tr>

                                    <td>
                                        <strong>
                                            <?= e($row["department"]) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= $row["total_tickets"] ?>
                                    </td>

                                    <td>
                                        <?= $row["open_tickets"] ?>
                                    </td>

                                    <td>
                                        <?= $row["in_progress_tickets"] ?>
                                    </td>

                                    <td>
                                        <?= $row["resolved_tickets"] ?>
                                    </td>

                                    <td>
                                        <?= $row["closed_tickets"] ?>
                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php else: ?>

                <div class="empty">
                    No department data found.
                </div>

            <?php endif; ?>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         ALL REPORTS - EMPLOYEE SUMMARY
    ====================================================== -->

    <?php if ($showAll): ?>

        <div class="section">

            <div class="section-title">
                Employee Summary
            </div>

            <div class="cards">

                <div class="card">

                    <div class="card-title">
                        Total Employees
                    </div>

                    <div class="card-value">
                        <?= $totalEmployees ?>
                    </div>

                </div>

            </div>

        </div>

    <?php endif; ?>


</div>


<!-- =========================================================
     JAVASCRIPT
========================================================== -->

<script>

const reportData = <?= $exportJson ?>;


/* =========================================================
   FORM SUBMISSION
========================================================= */

document
    .getElementById("reportForm")
    .addEventListener("submit", function(event) {

        const from =
            document.querySelector('input[name="from"]').value;

        const to =
            document.querySelector('input[name="to"]').value;

        if (!from || !to) {

            event.preventDefault();

            alert("Please select both From Date and To Date.");

            return;
        }

        if (from > to) {

            event.preventDefault();

            alert("From Date cannot be later than To Date.");

            return;
        }

    });


/* =========================================================
   PDF EXPORT
========================================================= */

function downloadPDF() {

    if (
        typeof window.jspdf === "undefined" ||
        typeof window.jspdf.jsPDF === "undefined"
    ) {

        alert(
            "PDF library could not be loaded. Please check your internet connection and try again."
        );

        return;
    }

    const { jsPDF } = window.jspdf;

    const doc = new jsPDF({
        orientation: "landscape",
        unit: "mm",
        format: "a4"
    });


    let y = 18;


    /* -----------------------------------------------------
       TITLE
    ------------------------------------------------------ */

    doc.setFontSize(20);

    doc.setFont("helvetica", "bold");

    doc.text(
        "IT Helpdesk & Asset Management",
        14,
        y
    );

    y += 9;


    doc.setFontSize(15);

    doc.text(
        reportData.reportType,
        14,
        y
    );

    y += 7;


    doc.setFontSize(9);

    doc.setFont("helvetica", "normal");

    doc.text(
        "Report Period: " +
        reportData.from +
        " to " +
        reportData.to,
        14,
        y
    );

    y += 10;


    /* -----------------------------------------------------
       TICKET REPORT
    ------------------------------------------------------ */

    if (
        reportData.reportType === "All Reports" ||
        reportData.reportType === "Ticket Report"
    ) {

        doc.setFontSize(13);

        doc.setFont("helvetica", "bold");

        doc.text(
            "Ticket Summary",
            14,
            y
        );

        y += 5;


        doc.autoTable({

            startY: y,

            head: [[
                "Total",
                "New",
                "Assigned",
                "In Progress",
                "Resolved",
                "Closed",
                "Resolution Rate"
            ]],

            body: [[
                reportData.summary.totalTickets,
                reportData.summary.newTickets,
                reportData.summary.assignedTickets,
                reportData.summary.inProgressTickets,
                reportData.summary.resolvedTickets,
                reportData.summary.closedTickets,
                reportData.summary.resolutionRate + "%"
            ]],

            theme: "grid",

            styles: {
                fontSize: 8
            }

        });


        y =
            doc.lastAutoTable.finalY + 10;


        /* PRIORITY */

        doc.setFontSize(12);

        doc.text(
            "Tickets by Priority",
            14,
            y
        );

        y += 4;


        doc.autoTable({

            startY: y,

            head: [[
                "Priority",
                "Total"
            ]],

            body:
                reportData.priority.map(function(row) {

                    return [
                        row.priority,
                        row.total
                    ];

                }),

            theme: "grid",

            styles: {
                fontSize: 8
            }

        });


        y =
            doc.lastAutoTable.finalY + 10;


        /* CATEGORY */

        doc.setFontSize(12);

        doc.text(
            "Tickets by Category",
            14,
            y
        );

        y += 4;


        doc.autoTable({

            startY: y,

            head: [[
                "Category",
                "Total"
            ]],

            body:
                reportData.category.map(function(row) {

                    return [
                        row.category,
                        row.total
                    ];

                }),

            theme: "grid",

            styles: {
                fontSize: 8
            }

        });

    }


    /* -----------------------------------------------------
       TECHNICIAN REPORT
    ------------------------------------------------------ */

    if (
        reportData.reportType === "All Reports" ||
        reportData.reportType === "Technician Report"
    ) {

        doc.addPage();

        y = 18;

        doc.setFontSize(13);

        doc.setFont("helvetica", "bold");

        doc.text(
            "Technician Workload",
            14,
            y
        );

        y += 5;


        doc.autoTable({

            startY: y,

            head: [[
                "Code",
                "Technician",
                "Specialization",
                "Availability",
                "Open",
                "In Progress",
                "Resolved",
                "Closed",
                "Total"
            ]],

            body:
                reportData.technicians.map(function(row) {

                    return [
                        row.technician_code,
                        row.full_name,
                        row.specialization || "N/A",
                        row.availability,
                        row.open_count,
                        row.in_progress_count,
                        row.resolved_count,
                        row.closed_count,
                        row.total_count
                    ];

                }),

            theme: "grid",

            styles: {
                fontSize: 7
            }

        });

    }


    /* -----------------------------------------------------
       ASSET REPORT
    ------------------------------------------------------ */

    if (
        reportData.reportType === "All Reports" ||
        reportData.reportType === "Asset Report"
    ) {

        doc.addPage();

        y = 18;

        doc.setFontSize(13);

        doc.setFont("helvetica", "bold");

        doc.text(
            "Asset Status Report",
            14,
            y
        );

        y += 5;


        doc.autoTable({

            startY: y,

            head: [[
                "Status",
                "Total",
                "Percentage"
            ]],

            body:
                reportData.assets.map(function(row) {

                    const percentage =
                        reportData.summary.totalAssets > 0
                            ? (
                                row.total /
                                reportData.summary.totalAssets
                            ) * 100
                            : 0;

                    return [
                        row.status,
                        row.total,
                        percentage.toFixed(1) + "%"
                    ];

                }),

            theme: "grid",

            styles: {
                fontSize: 8
            }

        });

    }


    /* -----------------------------------------------------
       DEPARTMENT REPORT
    ------------------------------------------------------ */

    if (
        reportData.reportType === "All Reports" ||
        reportData.reportType === "Department Report"
    ) {

        doc.addPage();

        y = 18;

        doc.setFontSize(13);

        doc.setFont("helvetica", "bold");

        doc.text(
            "Department Ticket Report",
            14,
            y
        );

        y += 5;


        doc.autoTable({

            startY: y,

            head: [[
                "Department",
                "Total",
                "Open",
                "In Progress",
                "Resolved",
                "Closed"
            ]],

            body:
                reportData.departments.map(function(row) {

                    return [
                        row.department,
                        row.total_tickets,
                        row.open_tickets,
                        row.in_progress_tickets,
                        row.resolved_tickets,
                        row.closed_tickets
                    ];

                }),

            theme: "grid",

            styles: {
                fontSize: 8
            }

        });

    }


    /* -----------------------------------------------------
       FOOTER
    ------------------------------------------------------ */

    const pageCount =
        doc.internal.getNumberOfPages();

    for (
        let i = 1;
        i <= pageCount;
        i++
    ) {

        doc.setPage(i);

        doc.setFontSize(8);

        doc.setFont("helvetica", "normal");

        doc.text(
            "Generated by IT Helpdesk & Asset Management System",
            14,
            202
        );

        doc.text(
            "Page " +
            i +
            " of " +
            pageCount,
            270,
            202
        );

    }


    /* -----------------------------------------------------
       DOWNLOAD
    ------------------------------------------------------ */

    const safeName =
        reportData.reportType
            .replace(/[^a-z0-9]+/gi, "_")
            .replace(/^_+|_+$/g, "");

    doc.save(
        safeName +
        "_" +
        reportData.from +
        "_to_" +
        reportData.to +
        ".pdf"
    );

}


/* =========================================================
   EXCEL EXPORT
========================================================= */

function downloadExcel() {

    if (typeof XLSX === "undefined") {

        alert(
            "Excel library could not be loaded. Please check your internet connection and try again."
        );

        return;
    }


    const workbook =
        XLSX.utils.book_new();


    /* =====================================================
       TICKET REPORT
    ====================================================== */

    if (
        reportData.reportType === "All Reports" ||
        reportData.reportType === "Ticket Report"
    ) {

        const summarySheet =
            XLSX.utils.aoa_to_sheet([

                [
                    "IT Helpdesk & Asset Management"
                ],

                [
                    reportData.reportType
                ],

                [
                    "Report Period",
                    reportData.from,
                    reportData.to
                ],

                [],

                [
                    "Metric",
                    "Value"
                ],

                [
                    "Total Tickets",
                    reportData.summary.totalTickets
                ],

                [
                    "New Tickets",
                    reportData.summary.newTickets
                ],

                [
                    "Assigned Tickets",
                    reportData.summary.assignedTickets
                ],

                [
                    "In Progress Tickets",
                    reportData.summary.inProgressTickets
                ],

                [
                    "Resolved Tickets",
                    reportData.summary.resolvedTickets
                ],

                [
                    "Closed Tickets",
                    reportData.summary.closedTickets
                ],

                [
                    "Resolution Rate",
                    reportData.summary.resolutionRate + "%"
                ]

            ]);


        XLSX.utils.book_append_sheet(
            workbook,
            summarySheet,
            "Ticket Summary"
        );


        /* PRIORITY */

        const priorityRows = [
            [
                "Priority",
                "Total"
            ]
        ];


        reportData.priority.forEach(function(row) {

            priorityRows.push([
                row.priority,
                row.total
            ]);

        });


        const prioritySheet =
            XLSX.utils.aoa_to_sheet(
                priorityRows
            );


        XLSX.utils.book_append_sheet(
            workbook,
            prioritySheet,
            "Priority"
        );


        /* CATEGORY */

        const categoryRows = [
            [
                "Category",
                "Total"
            ]
        ];


        reportData.category.forEach(function(row) {

            categoryRows.push([
                row.category,
                row.total
            ]);

        });


        const categorySheet =
            XLSX.utils.aoa_to_sheet(
                categoryRows
            );


        XLSX.utils.book_append_sheet(
            workbook,
            categorySheet,
            "Category"
        );

    }


    /* =====================================================
       TECHNICIAN REPORT
    ====================================================== */

    if (
        reportData.reportType === "All Reports" ||
        reportData.reportType === "Technician Report"
    ) {

        const technicianRows = [

            [
                "Technician Code",
                "Technician",
                "Specialization",
                "Availability",
                "Open",
                "In Progress",
                "Resolved",
                "Closed",
                "Total"
            ]

        ];


        reportData.technicians.forEach(function(row) {

            technicianRows.push([

                row.technician_code,

                row.full_name,

                row.specialization || "N/A",

                row.availability,

                row.open_count,

                row.in_progress_count,

                row.resolved_count,

                row.closed_count,

                row.total_count

            ]);

        });


        const technicianSheet =
            XLSX.utils.aoa_to_sheet(
                technicianRows
            );


        XLSX.utils.book_append_sheet(
            workbook,
            technicianSheet,
            "Technicians"
        );

    }


    /* =====================================================
       ASSET REPORT
    ====================================================== */

    if (
        reportData.reportType === "All Reports" ||
        reportData.reportType === "Asset Report"
    ) {

        const assetRows = [

            [
                "Asset Status",
                "Total Assets",
                "Percentage"
            ]

        ];


        reportData.assets.forEach(function(row) {

            const percentage =
                reportData.summary.totalAssets > 0
                    ? (
                        row.total /
                        reportData.summary.totalAssets
                    ) * 100
                    : 0;


            assetRows.push([

                row.status,

                row.total,

                percentage.toFixed(1) + "%"

            ]);

        });


        const assetSheet =
            XLSX.utils.aoa_to_sheet(
                assetRows
            );


        XLSX.utils.book_append_sheet(
            workbook,
            assetSheet,
            "Assets"
        );

    }


    /* =====================================================
       DEPARTMENT REPORT
    ====================================================== */

    if (
        reportData.reportType === "All Reports" ||
        reportData.reportType === "Department Report"
    ) {

        const departmentRows = [

            [
                "Department",
                "Total Tickets",
                "Open",
                "In Progress",
                "Resolved",
                "Closed"
            ]

        ];


        reportData.departments.forEach(function(row) {

            departmentRows.push([

                row.department,

                row.total_tickets,

                row.open_tickets,

                row.in_progress_tickets,

                row.resolved_tickets,

                row.closed_tickets

            ]);

        });


        const departmentSheet =
            XLSX.utils.aoa_to_sheet(
                departmentRows
            );


        XLSX.utils.book_append_sheet(
            workbook,
            departmentSheet,
            "Departments"
        );

    }


    /* =====================================================
       RECENT TICKETS
    ====================================================== */

    if (
        reportData.reportType === "All Reports" ||
        reportData.reportType === "Ticket Report"
    ) {

        const recentRows = [

            [
                "Ticket ID",
                "Title",
                "Employee",
                "Technician",
                "Category",
                "Priority",
                "Status",
                "Created At"
            ]

        ];


        reportData.recentTickets.forEach(function(row) {

            recentRows.push([

                row.display_id,

                row.title,

                row.employee_name || "N/A",

                row.technician_name || "Unassigned",

                row.category,

                row.priority,

                row.status,

                row.created_at

            ]);

        });


        const recentSheet =
            XLSX.utils.aoa_to_sheet(
                recentRows
            );


        XLSX.utils.book_append_sheet(
            workbook,
            recentSheet,
            "Recent Tickets"
        );

    }


    /* =====================================================
       DOWNLOAD
    ====================================================== */

    const safeName =
        reportData.reportType
            .replace(/[^a-z0-9]+/gi, "_")
            .replace(/^_+|_+$/g, "");


    XLSX.writeFile(

        workbook,

        safeName +
        "_" +
        reportData.from +
        "_to_" +
        reportData.to +
        ".xlsx"

    );

}


/* =========================================================
   AUTO DOWNLOAD WHEN PDF / EXCEL IS SELECTED
========================================================= */

<?php if ($format === "PDF"): ?>

window.addEventListener(
    "load",
    function() {

        setTimeout(
            function() {

                downloadPDF();

            },
            500
        );

    }
);

<?php endif; ?>


<?php if ($format === "Excel"): ?>

window.addEventListener(
    "load",
    function() {

        setTimeout(
            function() {

                downloadExcel();

            },
            500
        );

    }
);

<?php endif; ?>

</script>

</body>

</html>