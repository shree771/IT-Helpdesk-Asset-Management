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
        department
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
   VARIABLES
========================================================= */

$message = "";
$message_type = "";

$issue_title = "";
$category = "";
$priority = "";
$description = "";
$asset = "";
$location = "";


/* =========================================================
   CREATE TICKET
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $issue_title = trim($_POST["issueTitle"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $priority = trim($_POST["priority"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $asset = trim($_POST["asset"] ?? "");
    $location = trim($_POST["location"] ?? "");


    /* -----------------------------------------------------
       VALIDATE REQUIRED FIELDS
    ----------------------------------------------------- */

    if (
        $issue_title === "" ||
        $category === "" ||
        $priority === "" ||
        $description === "" ||
        $location === ""
    ) {

        $message = "Please fill in all required fields.";
        $message_type = "error";

    } else {


        /* -------------------------------------------------
           VALIDATE CATEGORY
        ------------------------------------------------- */

        $allowed_categories = [
            "hardware",
            "software",
            "network",
            "printer",
            "email",
            "account",
            "other"
        ];


        if (!in_array($category, $allowed_categories, true)) {

            $message = "Invalid issue category.";
            $message_type = "error";

        }


        /* -------------------------------------------------
           VALIDATE PRIORITY
        ------------------------------------------------- */

        $allowed_priorities = [
            "low",
            "medium",
            "high",
            "critical"
        ];


        if (
            $message_type === "" &&
            !in_array($priority, $allowed_priorities, true)
        ) {

            $message = "Invalid priority selected.";
            $message_type = "error";

        }


        /* -------------------------------------------------
           CREATE TICKET
        ------------------------------------------------- */

        if ($message_type === "") {

            /*
             * Convert form values to the exact values
             * expected by the database ENUM.
             */

            $priority_db = ucfirst($priority);


            /*
             * Convert category to a readable format.
             */

            $category_names = [
                "hardware" => "Hardware",
                "software" => "Software",
                "network" => "Network",
                "printer" => "Printer",
                "email" => "Email",
                "account" => "Account / Login",
                "other" => "Other"
            ];


            $category_db = $category_names[$category];


            /*
             * Include asset and location information
             * inside the description because the current
             * tickets table does not have separate columns
             * for these fields.
             */

            $final_description = $description;


            if ($asset !== "") {

                $final_description .=
                    "\n\nAffected Asset: " . $asset;

            }


            $final_description .=
                "\nLocation: " . $location;


            /*
             * Insert ticket.
             *
             * technician_id is NULL because the Admin
             * will assign a technician later.
             *
             * status automatically starts as Open.
             */

            $stmt = $conn->prepare("
                INSERT INTO tickets
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
                )
            ");


            $stmt->bind_param(
                "issss",
                $employee_id,
                $issue_title,
                $final_description,
                $category_db,
                $priority_db
            );


            if ($stmt->execute()) {

                $ticket_id = $stmt->insert_id;

                $message =
                    "Support ticket created successfully! Ticket ID: INC-" .
                    date("Y") .
                    "-" .
                    str_pad(
                        (string)$ticket_id,
                        3,
                        "0",
                        STR_PAD_LEFT
                    );

                $message_type = "success";


                /*
                 * Clear form after successful submission.
                 */

                $issue_title = "";
                $category = "";
                $priority = "";
                $description = "";
                $asset = "";
                $location = "";

            } else {

                $message =
                    "Unable to create the ticket. Please try again.";

                $message_type = "error";
            }


            $stmt->close();
        }
    }
}


/* =========================================================
   GET EMPLOYEE'S ASSIGNED ASSETS
========================================================= */

$stmt = $conn->prepare("
    SELECT
        asset_id,
        asset_code,
        asset_name,
        asset_type
    FROM assets
    WHERE assigned_to = ?
    AND status = 'Assigned'
    ORDER BY asset_name ASC
");

$stmt->bind_param("i", $employee_id);
$stmt->execute();

$assets = $stmt->get_result();

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

    <title>
        Create Support Ticket - IT Help Desk
    </title>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

    <style>

        .message-box {
            padding: 14px 18px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-weight: 600;
        }

        .message-success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #86efac;
        }

        .message-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .file-note {
            margin-top: 6px;
            font-size: 13px;
            color: #6b7280;
        }

    </style>

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

        <a
            href="../logout.php"
            class="login-btn"
        >
            Logout
        </a>

    </nav>

</header>



<main class="dashboard-page">


    <div class="dashboard-header">

        <div>

            <p class="dashboard-label">
                EMPLOYEE PORTAL
            </p>

            <h1>
                Create Support Ticket
            </h1>

            <p>
                Report an IT issue and our support team will assist you.
            </p>

        </div>

    </div>



    <?php if ($message !== ""): ?>

        <div class="message-box <?= $message_type === "success"
            ? "message-success"
            : "message-error" ?>">

            <?= e($message) ?>

        </div>

    <?php endif; ?>



    <div class="dashboard-grid">


        <!-- CREATE TICKET FORM -->

        <section class="dashboard-panel">


            <div class="panel-header">

                <div>

                    <h2>
                        Report an IT Issue
                    </h2>

                    <p>
                        Provide the details of your problem
                    </p>

                </div>

            </div>



            <form
                class="ticket-form"
                method="POST"
                action=""
            >


                <!-- ISSUE TITLE -->

                <div class="form-group">

                    <label for="issueTitle">
                        Issue Title
                    </label>

                    <input
                        type="text"
                        id="issueTitle"
                        name="issueTitle"
                        placeholder="Example: Laptop is not turning on"
                        value="<?= e($issue_title) ?>"
                        required
                    >

                </div>



                <!-- CATEGORY -->

                <div class="form-group">

                    <label for="category">
                        Issue Category
                    </label>

                    <select
                        id="category"
                        name="category"
                        required
                    >

                        <option value="">
                            Select issue category
                        </option>

                        <option
                            value="hardware"
                            <?= $category === "hardware"
                                ? "selected"
                                : "" ?>
                        >
                            Hardware
                        </option>

                        <option
                            value="software"
                            <?= $category === "software"
                                ? "selected"
                                : "" ?>
                        >
                            Software
                        </option>

                        <option
                            value="network"
                            <?= $category === "network"
                                ? "selected"
                                : "" ?>
                        >
                            Network
                        </option>

                        <option
                            value="printer"
                            <?= $category === "printer"
                                ? "selected"
                                : "" ?>
                        >
                            Printer
                        </option>

                        <option
                            value="email"
                            <?= $category === "email"
                                ? "selected"
                                : "" ?>
                        >
                            Email
                        </option>

                        <option
                            value="account"
                            <?= $category === "account"
                                ? "selected"
                                : "" ?>
                        >
                            Account / Login
                        </option>

                        <option
                            value="other"
                            <?= $category === "other"
                                ? "selected"
                                : "" ?>
                        >
                            Other
                        </option>

                    </select>

                </div>



                <!-- PRIORITY -->

                <div class="form-group">

                    <label for="priority">
                        Priority
                    </label>

                    <select
                        id="priority"
                        name="priority"
                        required
                    >

                        <option value="">
                            Select priority
                        </option>

                        <option
                            value="low"
                            <?= $priority === "low"
                                ? "selected"
                                : "" ?>
                        >
                            Low
                        </option>

                        <option
                            value="medium"
                            <?= $priority === "medium"
                                ? "selected"
                                : "" ?>
                        >
                            Medium
                        </option>

                        <option
                            value="high"
                            <?= $priority === "high"
                                ? "selected"
                                : "" ?>
                        >
                            High
                        </option>

                        <option
                            value="critical"
                            <?= $priority === "critical"
                                ? "selected"
                                : "" ?>
                        >
                            Critical
                        </option>

                    </select>

                </div>



                <!-- DESCRIPTION -->

                <div class="form-group">

                    <label for="description">
                        Problem Description
                    </label>

                    <textarea
                        id="description"
                        name="description"
                        placeholder="Describe your issue clearly..."
                        required
                    ><?= e($description) ?></textarea>

                </div>



                <!-- ASSET -->

                <div class="form-group">

                    <label for="asset">
                        Asset / Device
                    </label>

                    <select
                        id="asset"
                        name="asset"
                    >

                        <option value="">
                            Select affected device
                        </option>


                        <?php while ($asset_row = $assets->fetch_assoc()): ?>

                            <option
                                value="<?= e(
                                    $asset_row["asset_name"] .
                                    " (" .
                                    $asset_row["asset_code"] .
                                    ")"
                                ) ?>"
                            >

                                <?= e($asset_row["asset_name"]) ?>

                                -

                                <?= e($asset_row["asset_code"]) ?>

                            </option>

                        <?php endwhile; ?>


                        <option value="Other Device">
                            Other Device
                        </option>

                    </select>

                </div>



                <!-- LOCATION -->

                <div class="form-group">

                    <label for="location">
                        Location
                    </label>

                    <input
                        type="text"
                        id="location"
                        name="location"
                        placeholder="Example: IT Department - Room 204"
                        value="<?= e($location) ?>"
                        required
                    >

                </div>



                <!-- ATTACHMENT -->

                <div class="form-group">

                    <label for="attachment">
                        Attachment
                    </label>

                    <input
                        type="file"
                        id="attachment"
                        name="attachment"
                    >

                    <p class="file-note">

                        Attachment upload will be enabled
                        in a later step.

                    </p>

                </div>



                <button
                    type="submit"
                    class="auth-btn"
                >
                    Submit Support Ticket
                </button>


            </form>

        </section>



        <!-- GUIDELINES -->

        <section class="dashboard-panel">


            <div class="panel-header">

                <div>

                    <h2>
                        Ticket Guidelines
                    </h2>

                    <p>
                        Help us resolve your issue faster
                    </p>

                </div>

            </div>



            <div class="asset-row">

                <div class="asset-icon">
                    📝
                </div>

                <div class="asset-info">

                    <strong>
                        Describe the Problem
                    </strong>

                    <span>
                        Clearly explain what happened and
                        when the problem started.
                    </span>

                </div>

            </div>



            <div class="asset-row">

                <div class="asset-icon">
                    💻
                </div>

                <div class="asset-info">

                    <strong>
                        Select the Device
                    </strong>

                    <span>
                        Choose the affected device if it
                        has been assigned to you.
                    </span>

                </div>

            </div>



            <div class="asset-row">

                <div class="asset-icon">
                    📎
                </div>

                <div class="asset-info">

                    <strong>
                        Add an Attachment
                    </strong>

                    <span>
                        Attachment support can be enabled
                        when file storage is added.
                    </span>

                </div>

            </div>



            <div class="asset-row">

                <div class="asset-icon">
                    ⚡
                </div>

                <div class="asset-info">

                    <strong>
                        Select Correct Priority
                    </strong>

                    <span>
                        Choose Critical only when the
                        issue seriously affects your work.
                    </span>

                </div>

            </div>


        </section>


    </div>

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