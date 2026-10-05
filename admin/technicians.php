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
   HELPER FUNCTION
========================================================= */

function e($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

/* =========================================================
   ADD TECHNICIAN
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_technician"])) {

    $full_name      = trim($_POST["full_name"] ?? "");
    $email          = trim($_POST["email"] ?? "");
    $password       = $_POST["password"] ?? "";
    $technician_code = trim($_POST["technician_code"] ?? "");
    $specialization = trim($_POST["specialization"] ?? "");
    $phone          = trim($_POST["phone"] ?? "");
    $availability   = $_POST["availability"] ?? "Available";

    $errors = [];

    if ($full_name === "") {
        $errors[] = "Full name is required.";
    }

    if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    if (strlen($password) < 6) {
        $errors[] = "Password must contain at least 6 characters.";
    }

    if ($technician_code === "") {
        $errors[] = "Technician code is required.";
    }

    if (!in_array($availability, ["Available", "Busy", "Offline"], true)) {
        $errors[] = "Invalid availability selected.";
    }

    if (empty($errors)) {

        try {

            $conn->begin_transaction();

            /* Check duplicate email */

            $stmt = $conn->prepare(
                "SELECT user_id
                 FROM users
                 WHERE email = ?
                 LIMIT 1"
            );

            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                throw new Exception("Email address already exists.");
            }

            $stmt->close();

            /* Check duplicate technician code */

            $stmt = $conn->prepare(
                "SELECT technician_id
                 FROM technicians
                 WHERE technician_code = ?
                 LIMIT 1"
            );

            $stmt->bind_param("s", $technician_code);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                throw new Exception("Technician code already exists.");
            }

            $stmt->close();

            /* Create user account */

            $hashed_password = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $username = $full_name;

            $stmt = $conn->prepare(
                "INSERT INTO users
                (username, email, password, role, status)
                VALUES (?, ?, ?, 'technician', 'active')"
            );

            $stmt->bind_param(
                "sss",
                $username,
                $email,
                $hashed_password
            );

            if (!$stmt->execute()) {
                throw new Exception("Unable to create technician account.");
            }

            $user_id = $stmt->insert_id;

            $stmt->close();

            /* Create technician profile */

            $stmt = $conn->prepare(
                "INSERT INTO technicians
                (
                    user_id,
                    technician_code,
                    full_name,
                    specialization,
                    phone,
                    availability
                )
                VALUES (?, ?, ?, ?, ?, ?)"
            );

            $stmt->bind_param(
                "isssss",
                $user_id,
                $technician_code,
                $full_name,
                $specialization,
                $phone,
                $availability
            );

            if (!$stmt->execute()) {
                throw new Exception("Unable to create technician profile.");
            }

            $stmt->close();

            $conn->commit();

            header("Location: technicians.php?success=1");
            exit;

        } catch (Exception $e) {

            $conn->rollback();

            $error_message = $e->getMessage();
        }
    } else {

        $error_message = implode(" ", $errors);
    }
}

/* =========================================================
   EDIT TECHNICIAN
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["edit_technician"])) {

    $technician_id  = (int)($_POST["technician_id"] ?? 0);
    $user_id        = (int)($_POST["user_id"] ?? 0);

    $full_name      = trim($_POST["edit_full_name"] ?? "");
    $email          = trim($_POST["edit_email"] ?? "");
    $password       = $_POST["edit_password"] ?? "";
    $technician_code = trim($_POST["edit_technician_code"] ?? "");
    $specialization = trim($_POST["edit_specialization"] ?? "");
    $phone          = trim($_POST["edit_phone"] ?? "");
    $availability   = $_POST["edit_availability"] ?? "Available";

    $errors = [];

    if ($technician_id <= 0 || $user_id <= 0) {
        $errors[] = "Invalid technician information.";
    }

    if ($full_name === "") {
        $errors[] = "Full name is required.";
    }

    if ($email === "" || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    if ($technician_code === "") {
        $errors[] = "Technician code is required.";
    }

    if (!in_array($availability, ["Available", "Busy", "Offline"], true)) {
        $errors[] = "Invalid availability selected.";
    }

    if ($password !== "" && strlen($password) < 6) {
        $errors[] = "New password must contain at least 6 characters.";
    }

    if (empty($errors)) {

        try {

            $conn->begin_transaction();

            /* Check duplicate email */

            $stmt = $conn->prepare(
                "SELECT user_id
                 FROM users
                 WHERE email = ?
                 AND user_id != ?
                 LIMIT 1"
            );

            $stmt->bind_param(
                "si",
                $email,
                $user_id
            );

            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                throw new Exception("Email address already exists.");
            }

            $stmt->close();

            /* Check duplicate technician code */

            $stmt = $conn->prepare(
                "SELECT technician_id
                 FROM technicians
                 WHERE technician_code = ?
                 AND technician_id != ?
                 LIMIT 1"
            );

            $stmt->bind_param(
                "si",
                $technician_code,
                $technician_id
            );

            $stmt->execute();

            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                throw new Exception("Technician code already exists.");
            }

            $stmt->close();

            /* Update users table */

            if ($password !== "") {

                $hashed_password = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $stmt = $conn->prepare(
                    "UPDATE users
                     SET username = ?,
                         email = ?,
                         password = ?
                     WHERE user_id = ?"
                );

                $stmt->bind_param(
                    "sssi",
                    $full_name,
                    $email,
                    $hashed_password,
                    $user_id
                );

            } else {

                $stmt = $conn->prepare(
                    "UPDATE users
                     SET username = ?,
                         email = ?
                     WHERE user_id = ?"
                );

                $stmt->bind_param(
                    "ssi",
                    $full_name,
                    $email,
                    $user_id
                );
            }

            if (!$stmt->execute()) {
                throw new Exception("Unable to update user account.");
            }

            $stmt->close();

            /* Update technicians table */

            $stmt = $conn->prepare(
                "UPDATE technicians
                 SET technician_code = ?,
                     full_name = ?,
                     specialization = ?,
                     phone = ?,
                     availability = ?
                 WHERE technician_id = ?
                 AND user_id = ?"
            );

            $stmt->bind_param(
                "sssssii",
                $technician_code,
                $full_name,
                $specialization,
                $phone,
                $availability,
                $technician_id,
                $user_id
            );

            if (!$stmt->execute()) {
                throw new Exception("Unable to update technician profile.");
            }

            $stmt->close();

            $conn->commit();

            header("Location: technicians.php?updated=1");
            exit;

        } catch (Exception $e) {

            $conn->rollback();

            $error_message = $e->getMessage();
        }
    } else {

        $error_message = implode(" ", $errors);
    }
}

/* =========================================================
   SUCCESS / ERROR MESSAGES
========================================================= */

$success_message = "";

if (isset($_GET["success"])) {
    $success_message = "Technician added successfully.";
}

if (isset($_GET["updated"])) {
    $success_message = "Technician updated successfully.";
}

/* =========================================================
   GET TECHNICIANS
========================================================= */

$technicians = [];

$sql = "
    SELECT
        t.technician_id,
        t.user_id,
        t.technician_code,
        t.full_name,
        t.specialization,
        t.phone,
        t.availability,
        u.email,
        u.status AS user_status,
        u.created_at
    FROM technicians t
    INNER JOIN users u
        ON t.user_id = u.user_id
    WHERE u.role = 'technician'
    ORDER BY t.technician_id DESC
";

$result = $conn->query($sql);

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $technicians[] = $row;
    }
}

/* =========================================================
   STATISTICS
========================================================= */

$total_technicians = count($technicians);

$available_count = 0;
$busy_count = 0;
$offline_count = 0;

foreach ($technicians as $technician) {

    if ($technician["availability"] === "Available") {
        $available_count++;
    }

    if ($technician["availability"] === "Busy") {
        $busy_count++;
    }

    if ($technician["availability"] === "Offline") {
        $offline_count++;
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

    <title>Technicians - IT Helpdesk</title>

    <link
        rel="stylesheet"
        href="../css/style.css"
    >

    <style>

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f7f6;
        }

        .page-container {
            min-height: 100vh;
        }

        /* ==============================
           HEADER
        ============================== */

        .top-header {
            background: #0f5132;
            color: white;
            padding: 18px 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .top-header h1 {
            margin: 0;
            font-size: 22px;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 9px 13px;
            border-radius: 6px;
            font-size: 14px;
        }

        .nav-links a:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .nav-links a.active {
            background: white;
            color: #0f5132;
        }

        .nav-links a.nav-login {
            background: #dc3545;
        }

        .nav-links a.nav-login:hover {
            background: #bb2d3b;
        }

        /* ==============================
           MAIN
        ============================== */

        .main-content {
            padding: 30px;
            max-width: 1400px;
            margin: auto;
        }

        .page-title-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            gap: 15px;
        }

        .page-title-row h2 {
            margin: 0;
            color: #173b2a;
        }

        /* ==============================
           BUTTONS
        ============================== */

        .btn {
            border: none;
            padding: 10px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-primary {
            background: #198754;
            color: white;
        }

        .btn-primary:hover {
            background: #157347;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5c636a;
        }

        .btn-warning {
            background: #ffc107;
            color: #212529;
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-small {
            padding: 7px 11px;
            font-size: 13px;
        }

        /* ==============================
           MESSAGES
        ============================== */

        .message {
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
        }

        .success-message {
            background: #d1e7dd;
            color: #0f5132;
            border: 1px solid #badbcc;
        }

        .error-message {
            background: #f8d7da;
            color: #842029;
            border: 1px solid #f5c2c7;
        }

        /* ==============================
           STATISTICS
        ============================== */

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .stat-card h3 {
            margin: 0 0 8px;
            color: #666;
            font-size: 14px;
        }

        .stat-number {
            font-size: 30px;
            font-weight: bold;
            color: #0f5132;
        }

        /* ==============================
           SEARCH / FILTER
        ============================== */

        .filter-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .filter-grid {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 15px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 7px;
        }

        .form-group label {
            font-size: 13px;
            font-weight: bold;
            color: #333;
        }

        input,
        select {
            width: 100%;
            box-sizing: border-box;
            padding: 10px 12px;
            border: 1px solid #ced4da;
            border-radius: 6px;
            font-size: 14px;
            background: white;
        }

        input:focus,
        select:focus {
            outline: none;
            border-color: #198754;
            box-shadow: 0 0 0 2px rgba(25, 135, 84, 0.12);
        }

        /* ==============================
           FORM CARD
        ============================== */

        .form-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .form-card h3 {
            margin-top: 0;
            color: #173b2a;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .form-actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }

        /* ==============================
           TABLE
        ============================== */

        .table-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        th,
        td {
            padding: 13px 15px;
            border-bottom: 1px solid #e9ecef;
            text-align: left;
            font-size: 14px;
        }

        th {
            background: #e9f5ef;
            color: #0f5132;
            font-weight: bold;
        }

        tr:hover td {
            background: #f8fbf9;
        }

        .status {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .status-available {
            background: #d1e7dd;
            color: #0f5132;
        }

        .status-busy {
            background: #fff3cd;
            color: #664d03;
        }

        .status-offline {
            background: #f8d7da;
            color: #842029;
        }

        .action-buttons {
            display: flex;
            gap: 6px;
        }

        .no-data {
            text-align: center;
            padding: 30px;
            color: #777;
        }

        /* ==============================
           MODAL
        ============================== */

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
            padding: 20px;
            box-sizing: border-box;
        }

        .modal-content {
            background: white;
            width: 100%;
            max-width: 650px;
            max-height: 90vh;
            overflow-y: auto;
            border-radius: 10px;
            padding: 25px;
            position: relative;
        }

        .modal-content h3 {
            margin-top: 0;
            color: #0f5132;
        }

        .close-modal {
            position: absolute;
            right: 18px;
            top: 12px;
            font-size: 28px;
            cursor: pointer;
            color: #777;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .detail-item {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 6px;
        }

        .detail-item strong {
            display: block;
            margin-bottom: 5px;
            color: #555;
            font-size: 12px;
        }

        @media (max-width: 1000px) {

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .filter-grid {
                grid-template-columns: 1fr 1fr;
            }

            .top-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }

        @media (max-width: 650px) {

            .main-content {
                padding: 15px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filter-grid,
            .form-grid,
            .detail-grid {
                grid-template-columns: 1fr;
            }

            .page-title-row {
                flex-direction: column;
                align-items: flex-start;
            }
        }

    </style>

</head>

<body>

<div class="page-container">

    <!-- =====================================================
         HEADER / NAVIGATION
    ====================================================== -->

    <header class="top-header">

        <h1>IT Helpdesk & Asset Management</h1>

        <nav class="nav-links">

            <a href="dashboard.php">
                Dashboard
            </a>

            <a href="employees.php">
                Employees
            </a>

            <a href="technicians.php" class="active">
                Technicians
            </a>

            <a href="tickets.php">
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

    <!-- =====================================================
         MAIN CONTENT
    ====================================================== -->

    <main class="main-content">

        <div class="page-title-row">

            <div>
                <h2>Technician Management</h2>
                <p>
                    Manage IT support technicians and their availability.
                </p>
            </div>

            <button
                type="button"
                class="btn btn-primary"
                onclick="showAddForm()"
            >
                + Add Technician
            </button>

        </div>

        <!-- =================================================
             MESSAGES
        ================================================== -->

        <?php if (!empty($success_message)): ?>

            <div class="message success-message">
                <?= e($success_message) ?>
            </div>

        <?php endif; ?>

        <?php if (!empty($error_message)): ?>

            <div class="message error-message">
                <?= e($error_message) ?>
            </div>

        <?php endif; ?>

        <!-- =================================================
             STATISTICS
        ================================================== -->

        <div class="stats-grid">

            <div class="stat-card">

                <h3>Total Technicians</h3>

                <div class="stat-number">
                    <?= $total_technicians ?>
                </div>

            </div>

            <div class="stat-card">

                <h3>Available</h3>

                <div class="stat-number">
                    <?= $available_count ?>
                </div>

            </div>

            <div class="stat-card">

                <h3>Busy</h3>

                <div class="stat-number">
                    <?= $busy_count ?>
                </div>

            </div>

            <div class="stat-card">

                <h3>Offline</h3>

                <div class="stat-number">
                    <?= $offline_count ?>
                </div>

            </div>

        </div>

        <!-- =================================================
             SEARCH / FILTER
        ================================================== -->

        <div class="filter-card">

            <div class="filter-grid">

                <div class="form-group">

                    <label for="searchTechnician">
                        Search
                    </label>

                    <input
                        type="text"
                        id="searchTechnician"
                        placeholder="Search by name, code, email, phone..."
                        onkeyup="filterTechnicians()"
                    >

                </div>

                <div class="form-group">

                    <label for="specializationFilter">
                        Specialization
                    </label>

                    <select
                        id="specializationFilter"
                        onchange="filterTechnicians()"
                    >

                        <option value="">
                            All Specializations
                        </option>

                        <?php

                        $specializations = [];

                        foreach ($technicians as $technician) {

                            $spec = trim($technician["specialization"]);

                            if (
                                $spec !== "" &&
                                !in_array($spec, $specializations, true)
                            ) {
                                $specializations[] = $spec;
                            }
                        }

                        sort($specializations);

                        foreach ($specializations as $spec):
                        ?>

                            <option value="<?= e($spec) ?>">
                                <?= e($spec) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="form-group">

                    <label for="availabilityFilter">
                        Availability
                    </label>

                    <select
                        id="availabilityFilter"
                        onchange="filterTechnicians()"
                    >

                        <option value="">
                            All Availability
                        </option>

                        <option value="Available">
                            Available
                        </option>

                        <option value="Busy">
                            Busy
                        </option>

                        <option value="Offline">
                            Offline
                        </option>

                    </select>

                </div>

                <div class="form-group">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        onclick="clearFilters()"
                    >
                        Clear
                    </button>

                </div>

            </div>

        </div>

        <!-- =================================================
             ADD TECHNICIAN FORM
        ================================================== -->

        <div
            id="addTechnicianForm"
            class="form-card"
            style="display: none;"
        >

            <h3>Add New Technician</h3>

            <form
                method="POST"
                action="technicians.php"
            >

                <input
                    type="hidden"
                    name="add_technician"
                    value="1"
                >

                <div class="form-grid">

                    <div class="form-group">

                        <label>
                            Full Name *
                        </label>

                        <input
                            type="text"
                            name="full_name"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label>
                            Email *
                        </label>

                        <input
                            type="email"
                            name="email"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label>
                            Password *
                        </label>

                        <input
                            type="password"
                            name="password"
                            minlength="6"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label>
                            Technician Code *
                        </label>

                        <input
                            type="text"
                            name="technician_code"
                            placeholder="TECH001"
                            required
                        >

                    </div>

                    <div class="form-group">

                        <label>
                            Specialization
                        </label>

                        <input
                            type="text"
                            name="specialization"
                            placeholder="Network / Hardware / Software"
                        >

                    </div>

                    <div class="form-group">

                        <label>
                            Phone
                        </label>

                        <input
                            type="text"
                            name="phone"
                        >

                    </div>

                    <div class="form-group">

                        <label>
                            Availability
                        </label>

                        <select name="availability">

                            <option value="Available">
                                Available
                            </option>

                            <option value="Busy">
                                Busy
                            </option>

                            <option value="Offline">
                                Offline
                            </option>

                        </select>

                    </div>

                </div>

                <div class="form-actions">

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Save Technician
                    </button>

                    <button
                        type="button"
                        class="btn btn-secondary"
                        onclick="hideAddForm()"
                    >
                        Cancel
                    </button>

                </div>

            </form>

        </div>

        <!-- =================================================
             TECHNICIANS TABLE
        ================================================== -->

        <div class="table-card">

            <table>

                <thead>

                    <tr>

                        <th>Technician</th>
                        <th>Code</th>
                        <th>Specialization</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Availability</th>
                        <th>Actions</th>

                    </tr>

                </thead>

                <tbody id="technicianTableBody">

                <?php if (empty($technicians)): ?>

                    <tr id="noTechniciansRow">

                        <td
                            colspan="7"
                            class="no-data"
                        >
                            No technicians found.
                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($technicians as $technician): ?>

                        <?php

                        $availability = $technician["availability"];

                        $status_class = "status-offline";

                        if ($availability === "Available") {
                            $status_class = "status-available";
                        } elseif ($availability === "Busy") {
                            $status_class = "status-busy";
                        }

                        $name = $technician["full_name"];

                        $words = preg_split(
                            '/\s+/',
                            trim($name)
                        );

                        $initials = "";

                        foreach ($words as $word) {

                            if ($word !== "") {
                                $initials .= strtoupper(
                                    substr($word, 0, 1)
                                );
                            }

                            if (strlen($initials) >= 2) {
                                break;
                            }
                        }

                        ?>

                        <tr
                            data-name="<?= e(strtolower($technician["full_name"])) ?>"
                            data-code="<?= e(strtolower($technician["technician_code"])) ?>"
                            data-email="<?= e(strtolower($technician["email"])) ?>"
                            data-phone="<?= e(strtolower($technician["phone"])) ?>"
                            data-specialization="<?= e(strtolower($technician["specialization"])) ?>"
                            data-availability="<?= e($availability) ?>"
                        >

                            <td>

                                <strong>
                                    <?= e($technician["full_name"]) ?>
                                </strong>

                            </td>

                            <td>
                                <?= e($technician["technician_code"]) ?>
                            </td>

                            <td>
                                <?= e(
                                    $technician["specialization"] !== ""
                                        ? $technician["specialization"]
                                        : "Not Specified"
                                ) ?>
                            </td>

                            <td>
                                <?= e($technician["email"]) ?>
                            </td>

                            <td>
                                <?= e(
                                    $technician["phone"] !== ""
                                        ? $technician["phone"]
                                        : "Not Provided"
                                ) ?>
                            </td>

                            <td>

                                <span
                                    class="status <?= e($status_class) ?>"
                                >
                                    <?= e($availability) ?>
                                </span>

                            </td>

                            <td>

                                <div class="action-buttons">

                                    <button
                                        type="button"
                                        class="btn btn-secondary btn-small"
                                        onclick='viewTechnician(<?= json_encode($technician, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                    >
                                        View
                                    </button>

                                    <button
                                        type="button"
                                        class="btn btn-warning btn-small"
                                        onclick='editTechnician(<?= json_encode($technician, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                    >
                                        Edit
                                    </button>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </main>

</div>

<!-- =========================================================
     VIEW TECHNICIAN MODAL
========================================================= -->

<div
    id="viewModal"
    class="modal"
    onclick="closeModalOnBackground(event, 'viewModal')"
>

    <div class="modal-content">

        <span
            class="close-modal"
            onclick="closeModal('viewModal')"
        >
            &times;
        </span>

        <h3>Technician Details</h3>

        <div
            id="viewTechnicianContent"
            class="detail-grid"
        >
        </div>

    </div>

</div>

<!-- =========================================================
     EDIT TECHNICIAN MODAL
========================================================= -->

<div
    id="editModal"
    class="modal"
    onclick="closeModalOnBackground(event, 'editModal')"
>

    <div class="modal-content">

        <span
            class="close-modal"
            onclick="closeModal('editModal')"
        >
            &times;
        </span>

        <h3>Edit Technician</h3>

        <form
            method="POST"
            action="technicians.php"
        >

            <input
                type="hidden"
                name="edit_technician"
                value="1"
            >

            <input
                type="hidden"
                id="edit_technician_id"
                name="technician_id"
            >

            <input
                type="hidden"
                id="edit_user_id"
                name="user_id"
            >

            <div class="form-grid">

                <div class="form-group">

                    <label>
                        Full Name *
                    </label>

                    <input
                        type="text"
                        id="edit_full_name"
                        name="edit_full_name"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>
                        Email *
                    </label>

                    <input
                        type="email"
                        id="edit_email"
                        name="edit_email"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>
                        New Password
                    </label>

                    <input
                        type="password"
                        id="edit_password"
                        name="edit_password"
                        minlength="6"
                        placeholder="Leave blank to keep current password"
                    >

                </div>

                <div class="form-group">

                    <label>
                        Technician Code *
                    </label>

                    <input
                        type="text"
                        id="edit_technician_code"
                        name="edit_technician_code"
                        required
                    >

                </div>

                <div class="form-group">

                    <label>
                        Specialization
                    </label>

                    <input
                        type="text"
                        id="edit_specialization"
                        name="edit_specialization"
                    >

                </div>

                <div class="form-group">

                    <label>
                        Phone
                    </label>

                    <input
                        type="text"
                        id="edit_phone"
                        name="edit_phone"
                    >

                </div>

                <div class="form-group">

                    <label>
                        Availability
                    </label>

                    <select
                        id="edit_availability"
                        name="edit_availability"
                    >

                        <option value="Available">
                            Available
                        </option>

                        <option value="Busy">
                            Busy
                        </option>

                        <option value="Offline">
                            Offline
                        </option>

                    </select>

                </div>

            </div>

            <div class="form-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Save Changes
                </button>

                <button
                    type="button"
                    class="btn btn-secondary"
                    onclick="closeModal('editModal')"
                >
                    Cancel
                </button>

            </div>

        </form>

    </div>

</div>

<script>

/* =========================================================
   ADD FORM
========================================================= */

function showAddForm() {

    document.getElementById("addTechnicianForm").style.display = "block";

    document.getElementById("addTechnicianForm").scrollIntoView({
        behavior: "smooth",
        block: "start"
    });
}

function hideAddForm() {

    document.getElementById("addTechnicianForm").style.display = "none";
}

/* =========================================================
   VIEW TECHNICIAN
========================================================= */

function viewTechnician(technician) {

    const content =
        document.getElementById("viewTechnicianContent");

    const availability = technician.availability || "";

    let statusClass = "status-offline";

    if (availability === "Available") {
        statusClass = "status-available";
    } else if (availability === "Busy") {
        statusClass = "status-busy";
    }

    content.innerHTML = `

        <div class="detail-item">

            <strong>Full Name</strong>

            ${escapeHtml(technician.full_name || "")}

        </div>

        <div class="detail-item">

            <strong>Technician Code</strong>

            ${escapeHtml(technician.technician_code || "")}

        </div>

        <div class="detail-item">

            <strong>Email</strong>

            ${escapeHtml(technician.email || "")}

        </div>

        <div class="detail-item">

            <strong>Phone</strong>

            ${escapeHtml(technician.phone || "Not Provided")}

        </div>

        <div class="detail-item">

            <strong>Specialization</strong>

            ${escapeHtml(
                technician.specialization || "Not Specified"
            )}

        </div>

        <div class="detail-item">

            <strong>Availability</strong>

            <span class="status ${statusClass}">
                ${escapeHtml(availability)}
            </span>

        </div>

        <div class="detail-item">

            <strong>Account Status</strong>

            ${escapeHtml(technician.user_status || "Active")}

        </div>

        <div class="detail-item">

            <strong>Created At</strong>

            ${escapeHtml(technician.created_at || "")}

        </div>

    `;

    document.getElementById("viewModal").style.display = "flex";
}

/* =========================================================
   EDIT TECHNICIAN
========================================================= */

function editTechnician(technician) {

    document.getElementById("edit_technician_id").value =
        technician.technician_id || "";

    document.getElementById("edit_user_id").value =
        technician.user_id || "";

    document.getElementById("edit_full_name").value =
        technician.full_name || "";

    document.getElementById("edit_email").value =
        technician.email || "";

    document.getElementById("edit_password").value = "";

    document.getElementById("edit_technician_code").value =
        technician.technician_code || "";

    document.getElementById("edit_specialization").value =
        technician.specialization || "";

    document.getElementById("edit_phone").value =
        technician.phone || "";

    document.getElementById("edit_availability").value =
        technician.availability || "Available";

    document.getElementById("editModal").style.display = "flex";
}

/* =========================================================
   MODAL FUNCTIONS
========================================================= */

function closeModal(id) {

    document.getElementById(id).style.display = "none";
}

function closeModalOnBackground(event, id) {

    if (event.target === event.currentTarget) {
        closeModal(id);
    }
}

/* =========================================================
   SEARCH + FILTER
========================================================= */

function filterTechnicians() {

    const searchValue =
        document
            .getElementById("searchTechnician")
            .value
            .toLowerCase()
            .trim();

    const specializationValue =
        document
            .getElementById("specializationFilter")
            .value
            .toLowerCase()
            .trim();

    const availabilityValue =
        document
            .getElementById("availabilityFilter")
            .value;

    const rows =
        document.querySelectorAll(
            "#technicianTableBody tr[data-name]"
        );

    let visibleCount = 0;

    rows.forEach(function(row) {

        const name =
            row.dataset.name || "";

        const code =
            row.dataset.code || "";

        const email =
            row.dataset.email || "";

        const phone =
            row.dataset.phone || "";

        const specialization =
            row.dataset.specialization || "";

        const availability =
            row.dataset.availability || "";

        const searchableText =
            name +
            " " +
            code +
            " " +
            email +
            " " +
            phone;

        const matchesSearch =
            searchValue === "" ||
            searchableText.includes(searchValue);

        const matchesSpecialization =
            specializationValue === "" ||
            specialization === specializationValue;

        const matchesAvailability =
            availabilityValue === "" ||
            availability === availabilityValue;

        if (
            matchesSearch &&
            matchesSpecialization &&
            matchesAvailability
        ) {

            row.style.display = "";

            visibleCount++;

        } else {

            row.style.display = "none";
        }

    });

    let noResultsRow =
        document.getElementById("noFilterResults");

    if (noResultsRow) {
        noResultsRow.remove();
    }

    if (rows.length > 0 && visibleCount === 0) {

        const tbody =
            document.getElementById("technicianTableBody");

        const row =
            document.createElement("tr");

        row.id = "noFilterResults";

        row.innerHTML = `
            <td colspan="7" class="no-data">
                No technicians match your search or filter.
            </td>
        `;

        tbody.appendChild(row);
    }
}

/* =========================================================
   CLEAR FILTERS
========================================================= */

function clearFilters() {

    document.getElementById("searchTechnician").value = "";

    document.getElementById("specializationFilter").value = "";

    document.getElementById("availabilityFilter").value = "";

    filterTechnicians();
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
   ESC KEY CLOSE MODALS
========================================================= */

document.addEventListener("keydown", function(event) {

    if (event.key === "Escape") {

        closeModal("viewModal");

        closeModal("editModal");
    }

});

/* =========================================================
   AUTO HIDE SUCCESS MESSAGE
========================================================= */

setTimeout(function() {

    const message =
        document.querySelector(".success-message");

    if (message) {
        message.style.display = "none";
    }

}, 4000);

</script>

</body>

</html>