<?php

session_start();

/*
|--------------------------------------------------------------------------
| ADMIN LOGIN CHECK
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "admin"
) {
    header("Location: ../login.php");
    exit;
}

$username = $_SESSION["username"] ?? "Admin";

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard - IT Help Desk</title>

    <link rel="stylesheet" href="../css/style.css">

    <style>

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f4f7f6;
        }

        .dashboard-navbar {
            background: #075c48;
            color: white;
            padding: 18px 40px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dashboard-logo {
            font-size: 24px;
            font-weight: bold;
        }

        .dashboard-logo span {
            color: #b9eadc;
        }

        .admin-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logout-btn {
            background: white;
            color: #075c48;
            padding: 10px 18px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
        }

        .dashboard-container {
            max-width: 1100px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .welcome-section {
            margin-bottom: 30px;
        }

        .welcome-section h1 {
            margin-bottom: 8px;
            color: #123b35;
        }

        .welcome-section p {
            color: #666;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }

        .dashboard-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            text-decoration: none;
            color: #222;
            transition: 0.2s;
        }

        .dashboard-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .card-icon {
            font-size: 35px;
            margin-bottom: 15px;
        }

        .dashboard-card h2 {
            margin: 0 0 10px;
            color: #075c48;
        }

        .dashboard-card p {
            color: #666;
            margin: 0;
        }

        @media (max-width: 700px) {

            .dashboard-grid {
                grid-template-columns: 1fr;
            }

            .dashboard-navbar {
                padding: 15px 20px;
            }

            .admin-info {
                gap: 10px;
            }

        }

    </style>

</head>


<body>


<!-- NAVIGATION BAR -->

<header class="dashboard-navbar">

    <div class="dashboard-logo">
        <span>IT</span> Help Desk
    </div>

    <div class="admin-info">

        <span>
            Admin: <?php echo htmlspecialchars($username); ?>
        </span>

        <a href="../logout.php" class="logout-btn">
            Logout
        </a>

    </div>

</header>


<!-- MAIN DASHBOARD -->

<main class="dashboard-container">


    <section class="welcome-section">

        <h1>
            Admin Dashboard
        </h1>

        <p>
            Welcome back,
            <?php echo htmlspecialchars($username); ?>.
            Manage your IT Help Desk system from here.
        </p>

    </section>


    <!-- DASHBOARD OPTIONS -->

    <section class="dashboard-grid">


        <!-- EMPLOYEES -->

        <a href="employees.php" class="dashboard-card">

            <div class="card-icon">
                👥
            </div>

            <h2>
                Employees
            </h2>

            <p>
                Manage employee accounts and information.
            </p>

        </a>


        <!-- TECHNICIANS -->

        <a href="technicians.php" class="dashboard-card">

            <div class="card-icon">
                🧑‍💻
            </div>

            <h2>
                Technicians
            </h2>

            <p>
                Manage technicians and their availability.
            </p>

        </a>


        <!-- TICKETS -->

        <a href="tickets.php" class="dashboard-card">

            <div class="card-icon">
                🎫
            </div>

            <h2>
                Tickets
            </h2>

            <p>
                View and manage IT support tickets.
            </p>

        </a>


        <!-- REPORTS -->

        <a href="reports.php" class="dashboard-card">

            <div class="card-icon">
                📊
            </div>

            <h2>
                Reports
            </h2>

            <p>
                View IT Help Desk reports and statistics.
            </p>

        </a>


        <!-- ASSETS -->

        <a href="assets.php" class="dashboard-card">

            <div class="card-icon">
                💻
            </div>

            <h2>
                Assets
            </h2>

            <p>
                View and manage IT assets and equipment.
            </p>

        </a>


    </section>


</main>


</body>

</html>