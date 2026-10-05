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

$message = "";
$messageType = "";

/* =========================================================
   ADD EMPLOYEE
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["add_employee"])) {

    $full_name = trim($_POST["full_name"] ?? "");
    $employee_code = trim($_POST["employee_code"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $department = trim($_POST["department"] ?? "");
    $phone = trim($_POST["phone"] ?? "");

    if (
        $full_name === "" ||
        $employee_code === "" ||
        $email === "" ||
        $password === "" ||
        $department === ""
    ) {
        $message = "Please fill in all required fields.";
        $messageType = "error";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $messageType = "error";
    } else {

        /* Check duplicate email */
        $stmt = $conn->prepare(
            "SELECT user_id FROM users WHERE email = ? LIMIT 1"
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $existingEmail = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        /* Check duplicate employee code */
        $stmt = $conn->prepare(
            "SELECT employee_id FROM employees WHERE employee_code = ? LIMIT 1"
        );
        $stmt->bind_param("s", $employee_code);
        $stmt->execute();
        $existingCode = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existingEmail) {

            $message = "Email address already exists.";
            $messageType = "error";

        } elseif ($existingCode) {

            $message = "Employee code already exists.";
            $messageType = "error";

        } else {

            $conn->begin_transaction();

            try {

                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                /* Insert into users */
                $stmt = $conn->prepare(
                    "INSERT INTO users
                    (username, email, password, role, status)
                    VALUES (?, ?, ?, 'employee', 'active')"
                );

                $stmt->bind_param(
                    "sss",
                    $full_name,
                    $email,
                    $hashedPassword
                );

                $stmt->execute();

                $user_id = $conn->insert_id;

                $stmt->close();

                /* Insert into employees */
                $stmt = $conn->prepare(
                    "INSERT INTO employees
                    (user_id, employee_code, full_name, department, phone)
                    VALUES (?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "issss",
                    $user_id,
                    $employee_code,
                    $full_name,
                    $department,
                    $phone
                );

                $stmt->execute();

                $stmt->close();

                $conn->commit();

                $message = "Employee added successfully.";
                $messageType = "success";

            } catch (Exception $e) {

                $conn->rollback();

                $message = "Failed to add employee.";
                $messageType = "error";
            }
        }
    }
}

/* =========================================================
   EDIT EMPLOYEE
========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["edit_employee"])) {

    $employee_id = (int)($_POST["employee_id"] ?? 0);
    $user_id = (int)($_POST["user_id"] ?? 0);

    $full_name = trim($_POST["full_name"] ?? "");
    $employee_code = trim($_POST["employee_code"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $department = trim($_POST["department"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $status = $_POST["status"] ?? "active";
    $password = $_POST["password"] ?? "";

    if (
        $employee_id <= 0 ||
        $user_id <= 0 ||
        $full_name === "" ||
        $employee_code === "" ||
        $email === "" ||
        $department === ""
    ) {

        $message = "Please fill in all required fields.";
        $messageType = "error";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";
        $messageType = "error";

    } else {

        /* Check duplicate email for another user */
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

        $existingEmail = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        /* Check duplicate employee code */
        $stmt = $conn->prepare(
            "SELECT employee_id
             FROM employees
             WHERE employee_code = ?
             AND employee_id != ?
             LIMIT 1"
        );

        $stmt->bind_param(
            "si",
            $employee_code,
            $employee_id
        );

        $stmt->execute();

        $existingCode = $stmt->get_result()->fetch_assoc();

        $stmt->close();

        if ($existingEmail) {

            $message = "Email address already exists.";
            $messageType = "error";

        } elseif ($existingCode) {

            $message = "Employee code already exists.";
            $messageType = "error";

        } else {

            $conn->begin_transaction();

            try {

                /* Update users */
                if ($password !== "") {

                    $hashedPassword = password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    );

                    $stmt = $conn->prepare(
                        "UPDATE users
                         SET username = ?,
                             email = ?,
                             password = ?,
                             status = ?
                         WHERE user_id = ?"
                    );

                    $stmt->bind_param(
                        "ssssi",
                        $full_name,
                        $email,
                        $hashedPassword,
                        $status,
                        $user_id
                    );

                } else {

                    $stmt = $conn->prepare(
                        "UPDATE users
                         SET username = ?,
                             email = ?,
                             status = ?
                         WHERE user_id = ?"
                    );

                    $stmt->bind_param(
                        "sssi",
                        $full_name,
                        $email,
                        $status,
                        $user_id
                    );
                }

                $stmt->execute();

                $stmt->close();

                /* Update employees */
                $stmt = $conn->prepare(
                    "UPDATE employees
                     SET employee_code = ?,
                         full_name = ?,
                         department = ?,
                         phone = ?
                     WHERE employee_id = ?"
                );

                $stmt->bind_param(
                    "ssssi",
                    $employee_code,
                    $full_name,
                    $department,
                    $phone,
                    $employee_id
                );

                $stmt->execute();

                $stmt->close();

                $conn->commit();

                $message = "Employee updated successfully.";
                $messageType = "success";

            } catch (Exception $e) {

                $conn->rollback();

                $message = "Failed to update employee.";
                $messageType = "error";
            }
        }
    }
}

/* =========================================================
   GET EMPLOYEES
========================================================= */

$employees = [];

$sql = "
    SELECT
        e.employee_id,
        e.user_id,
        e.employee_code,
        e.full_name,
        e.department,
        e.phone,
        e.created_at,
        u.email,
        u.status,
        u.username
    FROM employees e
    INNER JOIN users u
        ON e.user_id = u.user_id
    WHERE u.role = 'employee'
    ORDER BY e.employee_id DESC
";

$result = $conn->query($sql);

if ($result) {

    while ($row = $result->fetch_assoc()) {
        $employees[] = $row;
    }

    $result->free();
}

/* =========================================================
   STATISTICS
========================================================= */

$totalEmployees = count($employees);

$activeEmployees = 0;
$inactiveEmployees = 0;

foreach ($employees as $employee) {

    if ($employee["status"] === "active") {
        $activeEmployees++;
    } else {
        $inactiveEmployees++;
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

    <title>Employees - IT Help Desk</title>

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: #f4f7f6;
            color: #263238;
        }

        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            height: 100vh;
            background: #064e3b;
            color: white;
            padding: 25px 18px;
        }

        .logo {
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 35px;
            text-align: center;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 13px 15px;
            margin-bottom: 8px;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: 0.2s;
        }

        .nav-link:hover {
            background: #047857;
        }

        .nav-link.active {
            background: #10b981;
        }

        .nav-icon {
            width: 25px;
            text-align: center;
        }

        .main {
            margin-left: 250px;
            padding: 30px;
        }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .topbar h1 {
            font-size: 28px;
            color: #064e3b;
        }

        .btn {
            border: none;
            padding: 11px 18px;
            border-radius: 7px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
        }

        .btn-primary {
            background: #059669;
            color: white;
        }

        .btn-primary:hover {
            background: #047857;
        }

        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }

        .btn-view {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .btn-edit {
            background: #fef3c7;
            color: #92400e;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 22px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }

        .stat-title {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .stat-number {
            font-size: 30px;
            font-weight: bold;
            color: #064e3b;
        }

        .message {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: bold;
        }

        .message.success {
            background: #d1fae5;
            color: #065f46;
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;
        }

        .toolbar {
            background: white;
            padding: 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .search {
            flex: 1;
            min-width: 250px;
            padding: 11px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
        }

        .filter {
            padding: 11px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
        }

        .table-container {
            background: white;
            border-radius: 12px;
            overflow-x: auto;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #ecfdf5;
            color: #064e3b;
            text-align: left;
            padding: 15px;
            font-size: 14px;
        }

        td {
            padding: 14px 15px;
            border-top: 1px solid #e5e7eb;
            font-size: 14px;
        }

        tr:hover {
            background: #f9fafb;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .status.active {
            background: #d1fae5;
            color: #065f46;
        }

        .status.inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .actions {
            display: flex;
            gap: 7px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.55);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal-content {
            background: white;
            width: 100%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            border-radius: 12px;
            padding: 25px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h2 {
            color: #064e3b;
        }

        .close {
            font-size: 25px;
            cursor: pointer;
            color: #6b7280;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 11px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .modal-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }

        .details {
            line-height: 1.8;
        }

        .details-item {
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .details-item strong {
            display: inline-block;
            width: 150px;
            color: #064e3b;
        }

        @media (max-width: 900px) {

            .sidebar {
                width: 200px;
            }

            .main {
                margin-left: 200px;
            }

            .stats {
                grid-template-columns: 1fr;
            }

        }

        @media (max-width: 650px) {

            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
            }

            .main {
                margin-left: 0;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .topbar {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

        }

    </style>

</head>

<body>

<!-- =====================================================
     SIDEBAR
===================================================== -->

<div class="sidebar">

    <div class="logo">
        IT Help Desk
    </div>

    <a href="dashboard.php" class="nav-link">
        <span class="nav-icon">🏠</span>
        <span>Dashboard</span>
    </a>

    <a href="employees.php" class="nav-link active">
        <span class="nav-icon">👥</span>
        <span>Employees</span>
    </a>

    <a href="technicians.php" class="nav-link">
        <span class="nav-icon">🧑‍💻</span>
        <span>Technicians</span>
    </a>

    <a href="tickets.php" class="nav-link">
        <span class="nav-icon">🎫</span>
        <span>Tickets</span>
    </a>

    <a href="reports.php" class="nav-link">
        <span class="nav-icon">📊</span>
        <span>Reports</span>
    </a>

    <a href="assets.php" class="nav-link">
        <span class="nav-icon">💻</span>
        <span>Assets</span>
    </a>

    <a href="../logout.php" class="nav-link">
        <span class="nav-icon">🚪</span>
        <span>Logout</span>
    </a>

</div>


<!-- =====================================================
     MAIN CONTENT
===================================================== -->

<div class="main">

    <div class="topbar">

        <h1>Employees</h1>

        <button
            class="btn btn-primary"
            onclick="openAddModal()"
        >
            + Add Employee
        </button>

    </div>


    <!-- MESSAGE -->

    <?php if ($message !== ""): ?>

        <div class="message <?php echo e($messageType); ?>">
            <?php echo e($message); ?>
        </div>

    <?php endif; ?>


    <!-- =================================================
         STATISTICS
    ================================================= -->

    <div class="stats">

        <div class="stat-card">

            <div class="stat-title">
                Total Employees
            </div>

            <div class="stat-number">
                <?php echo $totalEmployees; ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">
                Active Employees
            </div>

            <div class="stat-number">
                <?php echo $activeEmployees; ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">
                Inactive Employees
            </div>

            <div class="stat-number">
                <?php echo $inactiveEmployees; ?>
            </div>

        </div>

    </div>


    <!-- =================================================
         SEARCH AND FILTER
    ================================================= -->

    <div class="toolbar">

        <input
            type="text"
            id="searchEmployee"
            class="search"
            placeholder="Search employee by name, code, email or department..."
            onkeyup="filterEmployees()"
        >

        <select
            id="statusFilter"
            class="filter"
            onchange="filterEmployees()"
        >

            <option value="">All Status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>

        </select>

        <select
            id="departmentFilter"
            class="filter"
            onchange="filterEmployees()"
        >

            <option value="">All Departments</option>
            <option value="Computer Science">Computer Science</option>
            <option value="Electronics">Electronics</option>
            <option value="Mechanical">Mechanical</option>
            <option value="Human Resources">Human Resources</option>
            <option value="Finance">Finance</option>

        </select>

    </div>


    <!-- =================================================
         EMPLOYEE TABLE
    ================================================= -->

    <div class="table-container">

        <table>

            <thead>

                <tr>

                    <th>Employee ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Department</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Actions</th>

                </tr>

            </thead>

            <tbody id="employeeTableBody">

            <?php if (empty($employees)): ?>

                <tr>

                    <td
                        colspan="7"
                        style="text-align:center; padding:30px;"
                    >
                        No employees found.
                    </td>

                </tr>

            <?php else: ?>

                <?php foreach ($employees as $employee): ?>

                    <tr
                        data-name="<?php echo e(strtolower($employee["full_name"])); ?>"
                        data-code="<?php echo e(strtolower($employee["employee_code"])); ?>"
                        data-email="<?php echo e(strtolower($employee["email"])); ?>"
                        data-search-department="<?php echo e(strtolower($employee["department"])); ?>"
                        data-search-status="<?php echo e(strtolower($employee["status"])); ?>"
                        data-employee-id="<?php echo (int)$employee["employee_id"]; ?>"
                        data-user-id="<?php echo (int)$employee["user_id"]; ?>"
                        data-full-name="<?php echo e($employee["full_name"]); ?>"
                        data-department="<?php echo e($employee["department"]); ?>"
                        data-phone="<?php echo e($employee["phone"]); ?>"
                        data-employee-code="<?php echo e($employee["employee_code"]); ?>"
                        data-email-value="<?php echo e($employee["email"]); ?>"
                        data-account-status="<?php echo e($employee["status"]); ?>"
                    >

                        <td>
                            <?php echo e($employee["employee_code"]); ?>
                        </td>

                        <td>
                            <?php echo e($employee["full_name"]); ?>
                        </td>

                        <td>
                            <?php echo e($employee["email"]); ?>
                        </td>

                        <td>
                            <?php echo e($employee["department"]); ?>
                        </td>

                        <td>
                            <?php echo e($employee["phone"] ?: "-"); ?>
                        </td>

                        <td>

                            <span
                                class="status <?php echo e($employee["status"]); ?>"
                            >
                                <?php echo ucfirst(e($employee["status"])); ?>
                            </span>

                        </td>

                        <td>

                            <div class="actions">

                                <button
                                    class="btn btn-view"
                                    onclick="viewEmployee(<?php echo (int)$employee["employee_id"]; ?>)"
                                >
                                    View
                                </button>

                                <button
                                    class="btn btn-edit"
                                    onclick="editEmployee(<?php echo (int)$employee["employee_id"]; ?>)"
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

</div>


<!-- =====================================================
     ADD EMPLOYEE MODAL
===================================================== -->

<div
    class="modal"
    id="addModal"
>

    <div class="modal-content">

        <div class="modal-header">

            <h2>Add Employee</h2>

            <span
                class="close"
                onclick="closeModal('addModal')"
            >
                &times;
            </span>

        </div>


        <form method="POST">

            <div class="form-grid">

                <div class="form-group">

                    <label>Full Name *</label>

                    <input
                        type="text"
                        name="full_name"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Employee Code *</label>

                    <input
                        type="text"
                        name="employee_code"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Email *</label>

                    <input
                        type="email"
                        name="email"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Login Password *</label>

                    <input
                        type="password"
                        name="password"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Department *</label>

                    <select
                        name="department"
                        required
                    >

                        <option value="">
                            Select Department
                        </option>

                        <option value="Computer Science">
                            Computer Science
                        </option>

                        <option value="Electronics">
                            Electronics
                        </option>

                        <option value="Mechanical">
                            Mechanical
                        </option>

                        <option value="Human Resources">
                            Human Resources
                        </option>

                        <option value="Finance">
                            Finance
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label>Phone</label>

                    <input
                        type="text"
                        name="phone"
                    >

                </div>

            </div>


            <div class="modal-buttons">

                <button
                    type="button"
                    class="btn btn-secondary"
                    onclick="closeModal('addModal')"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    name="add_employee"
                    class="btn btn-primary"
                >
                    Add Employee
                </button>

            </div>

        </form>

    </div>

</div>


<!-- =====================================================
     VIEW EMPLOYEE MODAL
===================================================== -->

<div
    class="modal"
    id="viewModal"
>

    <div class="modal-content">

        <div class="modal-header">

            <h2>Employee Details</h2>

            <span
                class="close"
                onclick="closeModal('viewModal')"
            >
                &times;
            </span>

        </div>


        <div class="details">

            <div class="details-item">
                <strong>Employee Code:</strong>
                <span id="viewCode"></span>
            </div>

            <div class="details-item">
                <strong>Full Name:</strong>
                <span id="viewName"></span>
            </div>

            <div class="details-item">
                <strong>Email:</strong>
                <span id="viewEmail"></span>
            </div>

            <div class="details-item">
                <strong>Department:</strong>
                <span id="viewDepartment"></span>
            </div>

            <div class="details-item">
                <strong>Phone:</strong>
                <span id="viewPhone"></span>
            </div>

            <div class="details-item">
                <strong>Status:</strong>
                <span id="viewStatus"></span>
            </div>

        </div>

    </div>

</div>


<!-- =====================================================
     EDIT EMPLOYEE MODAL
===================================================== -->

<div
    class="modal"
    id="editModal"
>

    <div class="modal-content">

        <div class="modal-header">

            <h2>Edit Employee</h2>

            <span
                class="close"
                onclick="closeModal('editModal')"
            >
                &times;
            </span>

        </div>


        <form method="POST">

            <input
                type="hidden"
                name="employee_id"
                id="editEmployeeId"
            >

            <input
                type="hidden"
                name="user_id"
                id="editUserId"
            >


            <div class="form-grid">

                <div class="form-group">

                    <label>Full Name *</label>

                    <input
                        type="text"
                        name="full_name"
                        id="editName"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Employee Code *</label>

                    <input
                        type="text"
                        name="employee_code"
                        id="editCode"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>Email *</label>

                    <input
                        type="email"
                        name="email"
                        id="editEmail"
                        required
                    >

                </div>


                <div class="form-group">

                    <label>New Password</label>

                    <input
                        type="password"
                        name="password"
                        id="editPassword"
                        placeholder="Leave blank to keep current password"
                    >

                </div>


                <div class="form-group">

                    <label>Department *</label>

                    <select
                        name="department"
                        id="editDepartment"
                        required
                    >

                        <option value="Computer Science">
                            Computer Science
                        </option>

                        <option value="Electronics">
                            Electronics
                        </option>

                        <option value="Mechanical">
                            Mechanical
                        </option>

                        <option value="Human Resources">
                            Human Resources
                        </option>

                        <option value="Finance">
                            Finance
                        </option>

                    </select>

                </div>


                <div class="form-group">

                    <label>Phone</label>

                    <input
                        type="text"
                        name="phone"
                        id="editPhone"
                    >

                </div>


                <div class="form-group">

                    <label>Status</label>

                    <select
                        name="status"
                        id="editStatus"
                    >

                        <option value="active">
                            Active
                        </option>

                        <option value="inactive">
                            Inactive
                        </option>

                    </select>

                </div>

            </div>


            <div class="modal-buttons">

                <button
                    type="button"
                    class="btn btn-secondary"
                    onclick="closeModal('editModal')"
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    name="edit_employee"
                    class="btn btn-primary"
                >
                    Save Changes
                </button>

            </div>

        </form>

    </div>

</div>


<!-- =====================================================
     JAVASCRIPT
===================================================== -->

<script>

function openAddModal() {

    document.getElementById("addModal").style.display = "flex";

}


function closeModal(id) {

    document.getElementById(id).style.display = "none";

}


function getEmployeeRow(id) {

    return document.querySelector(
        'tr[data-employee-id="' + id + '"]'
    );

}


function viewEmployee(id) {

    const row = getEmployeeRow(id);

    if (!row) {
        return;
    }

    document.getElementById("viewCode").textContent =
        row.dataset.employeeCode || "-";

    document.getElementById("viewName").textContent =
        row.dataset.fullName || "-";

    document.getElementById("viewEmail").textContent =
        row.dataset.emailValue || "-";

    document.getElementById("viewDepartment").textContent =
        row.dataset.department || "-";

    document.getElementById("viewPhone").textContent =
        row.dataset.phone || "-";

    document.getElementById("viewStatus").textContent =
        row.dataset.accountStatus || "-";

    document.getElementById("viewModal").style.display = "flex";

}


function editEmployee(id) {

    const row = getEmployeeRow(id);

    if (!row) {
        return;
    }

    document.getElementById("editEmployeeId").value =
        row.dataset.employeeId || "";

    document.getElementById("editUserId").value =
        row.dataset.userId || "";

    document.getElementById("editName").value =
        row.dataset.fullName || "";

    document.getElementById("editCode").value =
        row.dataset.employeeCode || "";

    document.getElementById("editEmail").value =
        row.dataset.emailValue || "";

    document.getElementById("editDepartment").value =
        row.dataset.department || "";

    document.getElementById("editPhone").value =
        row.dataset.phone || "";

    document.getElementById("editStatus").value =
        row.dataset.accountStatus || "active";

    document.getElementById("editPassword").value = "";

    document.getElementById("editModal").style.display = "flex";

}


function filterEmployees() {

    const search =
        document.getElementById("searchEmployee")
        .value
        .toLowerCase()
        .trim();

    const status =
        document.getElementById("statusFilter")
        .value
        .toLowerCase();

    const department =
        document.getElementById("departmentFilter")
        .value
        .toLowerCase();

    const rows =
        document.querySelectorAll(
            "#employeeTableBody tr[data-employee-id]"
        );

    rows.forEach(function(row) {

        const name =
            row.dataset.name || "";

        const code =
            row.dataset.code || "";

        const email =
            row.dataset.email || "";

        const rowDepartment =
            row.dataset.searchDepartment || "";

        const rowStatus =
            row.dataset.searchStatus || "";

        const matchesSearch =
            name.includes(search) ||
            code.includes(search) ||
            email.includes(search) ||
            rowDepartment.includes(search);

        const matchesStatus =
            status === "" ||
            rowStatus === status;

        const matchesDepartment =
            department === "" ||
            rowDepartment === department;

        if (
            matchesSearch &&
            matchesStatus &&
            matchesDepartment
        ) {

            row.style.display = "";

        } else {

            row.style.display = "none";

        }

    });

}


/* Close modal when clicking outside */

window.addEventListener("click", function(event) {

    const addModal =
        document.getElementById("addModal");

    const viewModal =
        document.getElementById("viewModal");

    const editModal =
        document.getElementById("editModal");

    if (event.target === addModal) {
        addModal.style.display = "none";
    }

    if (event.target === viewModal) {
        viewModal.style.display = "none";
    }

    if (event.target === editModal) {
        editModal.style.display = "none";
    }

});

</script>

</body>

</html>